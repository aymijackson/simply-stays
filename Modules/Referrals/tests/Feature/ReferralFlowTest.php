<?php

namespace Modules\Referrals\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Bookings\Models\Booking;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Modules\Payments\Models\Wallet;
use Modules\Referrals\Models\AgentProfile;
use Modules\Referrals\Models\Referral;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReferralFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $agentUser;

    private AgentProfile $agent;

    private User $host;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Guest', 'Host', 'Agent'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->agentUser = User::factory()->create();
        $this->agentUser->assignRole('Agent');

        $this->agent = AgentProfile::create([
            'user_id' => $this->agentUser->id,
            'referral_code' => 'TESTAGENT10',
            'commission_rate' => 10,
            'status' => 'active',
        ]);

        $this->host = User::factory()->create();
        $this->host->assignRole('Host');

        $property = Property::create([
            'host_id' => $this->host->id,
            'name' => 'Referral Test Property',
            'property_type' => 'apartment',
            'address' => '1 Test St',
            'city' => 'Lagos',
            'country' => 'Nigeria',
        ]);

        $this->listing = Listing::create([
            'property_id' => $property->id,
            'title' => 'Referral Test Listing',
            'slug' => 'referral-test-listing',
            'base_price_amount' => 1000000,
            'currency' => 'NGN',
            'max_guests' => 2,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'status' => 'published',
        ]);
    }

    public function test_registering_with_a_valid_referral_code_links_the_agent(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'New Guest',
            'email' => 'newguest@example.test',
            'password' => 'SuperSecret123',
            'password_confirmation' => 'SuperSecret123',
            'account_type' => 'guest',
            'referral_code' => 'TESTAGENT10',
        ]);

        $response->assertRedirect(route('dashboard'));

        $user = User::where('email', 'newguest@example.test')->first();
        $this->assertSame($this->agent->id, $user->referred_by_agent_id);
    }

    public function test_registering_with_an_invalid_referral_code_fails_validation(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'New Guest',
            'email' => 'newguest2@example.test',
            'password' => 'SuperSecret123',
            'password_confirmation' => 'SuperSecret123',
            'account_type' => 'guest',
            'referral_code' => 'NOT-A-REAL-CODE',
        ]);

        $response->assertSessionHasErrors('referral_code');
        $this->assertDatabaseMissing('users', ['email' => 'newguest2@example.test']);
    }

    public function test_booking_with_an_explicit_referral_code_creates_a_pending_referral_with_correct_commission(): void
    {
        $guest = User::factory()->create();
        $guest->assignRole('Guest');

        $response = $this->actingAs($guest)->post(route('bookings.store'), [
            'listing_id' => $this->listing->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests_count' => 1,
            'referral_code' => 'TESTAGENT10',
        ]);

        $response->assertRedirect();

        $booking = Booking::first();
        $this->assertSame('TESTAGENT10', $booking->referral_code);

        $referral = Referral::where('referable_type', Booking::class)->where('referable_id', $booking->id)->first();
        $this->assertNotNull($referral);
        $this->assertSame('pending', $referral->status);
        // 2 nights x 1,000,000 minor units = 2,000,000; 10% commission = 200,000.
        $this->assertSame(200000, $referral->commission_amount);
    }

    public function test_signup_time_referral_attribution_applies_automatically_at_checkout(): void
    {
        $guest = User::factory()->create(['referred_by_agent_id' => $this->agent->id]);
        $guest->assignRole('Guest');

        $this->actingAs($guest)->post(route('bookings.store'), [
            'listing_id' => $this->listing->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(6)->toDateString(),
            'guests_count' => 1,
        ])->assertRedirect();

        $booking = Booking::first();
        $referral = Referral::where('referable_id', $booking->id)->first();

        $this->assertNotNull($referral, 'Referral should be auto-attributed from the guest signup-time agent.');
        $this->assertSame($this->agent->id, $referral->agent_id);
    }

    public function test_an_agent_booking_their_own_listing_does_not_generate_a_self_referral(): void
    {
        $ownProperty = Property::create([
            'host_id' => $this->agentUser->id,
            'name' => 'Agents Own Place',
            'property_type' => 'apartment',
            'address' => '2 Test St',
            'city' => 'Lagos',
            'country' => 'Nigeria',
        ]);

        $ownListing = Listing::create([
            'property_id' => $ownProperty->id,
            'title' => 'Agents Own Place',
            'slug' => 'agents-own-place',
            'base_price_amount' => 500000,
            'currency' => 'NGN',
            'max_guests' => 2,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'status' => 'published',
        ]);

        $this->agentUser->assignRole('Guest');

        $this->actingAs($this->agentUser)->post(route('bookings.store'), [
            'listing_id' => $ownListing->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(6)->toDateString(),
            'guests_count' => 1,
            'referral_code' => 'TESTAGENT10',
        ])->assertRedirect();

        $this->assertSame(0, Referral::count());
    }

    public function test_paying_off_a_referred_booking_credits_the_agents_wallet_and_marks_referral_paid(): void
    {
        config(['payments.paystack.secret' => 'sk_test_fake']);

        $guest = User::factory()->create();
        $guest->assignRole('Guest');

        $this->actingAs($guest)->post(route('bookings.store'), [
            'listing_id' => $this->listing->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests_count' => 1,
            'referral_code' => 'TESTAGENT10',
        ]);

        $booking = Booking::first();
        $referral = Referral::where('referable_id', $booking->id)->first();

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/ref123', 'reference' => 'ref123'],
            ], 200),
        ]);

        $this->actingAs($guest)->post(route('checkout.initiate', $booking), ['gateway' => 'paystack']);

        $transaction = $booking->transactions()->first();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['reference' => $transaction->gateway_reference, 'status' => 'success', 'amount' => $booking->total_amount, 'currency' => 'NGN'],
            ], 200),
        ]);

        $this->actingAs($guest)
            ->get(route('payments.callback', ['gateway' => 'paystack']).'?reference='.$transaction->reference)
            ->assertRedirect(route('bookings.show', $booking));

        $referral->refresh();
        $this->assertSame('paid', $referral->status);

        $wallet = Wallet::where('holder_type', User::class)->where('holder_id', $this->agentUser->id)->first();
        $this->assertNotNull($wallet);
        $this->assertSame($referral->commission_amount, $wallet->balance);
    }

    public function test_visiting_the_dashboard_auto_provisions_an_agent_profile_for_a_new_agent(): void
    {
        $newAgentUser = User::factory()->create();
        $newAgentUser->assignRole('Agent');

        $this->assertDatabaseMissing('agent_profiles', ['user_id' => $newAgentUser->id]);

        $this->actingAs($newAgentUser)->get(route('referrals.index'))->assertOk();

        $this->assertDatabaseHas('agent_profiles', ['user_id' => $newAgentUser->id, 'status' => 'active']);
    }
}
