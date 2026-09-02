<?php

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Bookings\Models\Booking;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Modules\Payments\Models\PaymentTransaction;
use Modules\Payments\Models\Wallet;
use Modules\Referrals\Models\AgentProfile;
use Modules\Referrals\Models\Referral;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRefundFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $host;

    private User $guest;

    private Listing $listing;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Guest', 'Host', 'Agent', 'Admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        config(['payments.paystack.secret' => 'sk_test_fake']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->host = User::factory()->create();
        $this->host->assignRole('Host');

        $this->guest = User::factory()->create();
        $this->guest->assignRole('Guest');

        $property = Property::create([
            'host_id' => $this->host->id,
            'name' => 'Refund Test Property',
            'property_type' => 'apartment',
            'address' => '1 Test St',
            'city' => 'Lagos',
            'country' => 'Nigeria',
        ]);

        $this->listing = Listing::create([
            'property_id' => $property->id,
            'title' => 'Refund Test Listing',
            'slug' => 'refund-test-listing',
            'base_price_amount' => 1000000,
            'currency' => 'NGN',
            'max_guests' => 2,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'status' => 'published',
        ]);

        $this->booking = Booking::create([
            'listing_id' => $this->listing->id,
            'guest_id' => $this->guest->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests_count' => 1,
            'status' => 'confirmed',
            'total_amount' => 2000000,
            'currency' => 'NGN',
        ]);
    }

    private function payOffBooking(): PaymentTransaction
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/x', 'reference' => 'x'],
            ], 200),
        ]);

        $this->actingAs($this->guest)->post(route('checkout.initiate', $this->booking), ['gateway' => 'paystack']);

        $transaction = $this->booking->transactions()->first();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['reference' => $transaction->gateway_reference, 'status' => 'success', 'amount' => 2000000, 'currency' => 'NGN'],
            ], 200),
        ]);

        $this->actingAs($this->guest)
            ->get(route('payments.callback', ['gateway' => 'paystack']).'?reference='.$transaction->reference);

        return $transaction->fresh();
    }

    public function test_admin_can_refund_a_paid_booking_and_it_debits_the_host_wallet_and_cancels_the_booking(): void
    {
        $charge = $this->payOffBooking();

        $hostWallet = Wallet::where('holder_type', User::class)->where('holder_id', $this->host->id)->first();
        $this->assertSame(2000000, $hostWallet->balance);

        Http::fake([
            'api.paystack.co/refund' => Http::response(['status' => true, 'message' => 'Refund queued'], 200),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.bookings.refund', $this->booking));
        $response->assertRedirect();

        $this->assertSame('cancelled', $this->booking->fresh()->status);
        $this->assertSame('refunded', $charge->fresh()->status);

        $this->assertDatabaseHas('payment_transactions', [
            'booking_id' => $this->booking->id,
            'type' => 'refund',
            'status' => 'succeeded',
            'amount' => 2000000,
        ]);

        $this->assertSame(0, $hostWallet->fresh()->balance);
    }

    public function test_refunding_also_reverses_the_referring_agents_commission(): void
    {
        $agentUser = User::factory()->create();
        $agentUser->assignRole('Agent');
        $agent = AgentProfile::create([
            'user_id' => $agentUser->id,
            'referral_code' => 'REFUNDTEST',
            'commission_rate' => 10,
            'status' => 'active',
        ]);

        $referral = Referral::create([
            'agent_id' => $agent->id,
            'referred_user_id' => $this->guest->id,
            'referable_type' => Booking::class,
            'referable_id' => $this->booking->id,
            'status' => 'pending',
            'commission_amount' => 200000,
            'currency' => 'NGN',
        ]);

        $this->payOffBooking();

        $agentWallet = Wallet::where('holder_type', User::class)->where('holder_id', $agentUser->id)->first();
        $this->assertSame(200000, $agentWallet->balance);
        $this->assertSame('paid', $referral->fresh()->status);

        Http::fake([
            'api.paystack.co/refund' => Http::response(['status' => true], 200),
        ]);

        $this->actingAs($this->admin)->post(route('admin.bookings.refund', $this->booking));

        $this->assertSame(0, $agentWallet->fresh()->balance);
    }

    public function test_refund_fails_gracefully_when_the_gateway_rejects_it(): void
    {
        $this->payOffBooking();

        Http::fake([
            'api.paystack.co/refund' => Http::response(['status' => false, 'message' => 'Already refunded'], 400),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.bookings.refund', $this->booking));

        $response->assertRedirect();
        $this->assertSame('confirmed', $this->booking->fresh()->status);
        $this->assertDatabaseMissing('payment_transactions', ['booking_id' => $this->booking->id, 'type' => 'refund']);
    }

    public function test_non_admin_cannot_issue_a_refund(): void
    {
        $this->payOffBooking();

        $this->actingAs($this->guest)
            ->post(route('admin.bookings.refund', $this->booking))
            ->assertStatus(403);
    }
}
