<?php

namespace Modules\Payments\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Bookings\Models\Booking;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Modules\Payments\Models\PaymentTransaction;
use Modules\Payments\Models\Wallet;
use Tests\TestCase;

class PaystackCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private User $guest;

    private Listing $listing;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.paystack.secret' => 'sk_test_fake',
            'payments.paystack.base_url' => 'https://api.paystack.co',
        ]);

        $this->host = User::factory()->create();
        $this->guest = User::factory()->create();

        $property = Property::create([
            'host_id' => $this->host->id,
            'name' => 'Test Property',
            'property_type' => 'apartment',
            'address' => '1 Test St',
            'city' => 'Lagos',
            'country' => 'Nigeria',
        ]);

        $this->listing = Listing::create([
            'property_id' => $property->id,
            'title' => 'Test Listing',
            'slug' => 'test-listing',
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
            'status' => 'pending',
            'total_amount' => 2000000,
            'currency' => 'NGN',
        ]);
    }

    public function test_guest_can_initiate_checkout_and_is_redirected_to_paystack(): void
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/abc123',
                    'access_code' => 'abc123',
                    'reference' => 'will-be-overridden-by-our-reference',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->guest)
            ->post(route('checkout.initiate', $this->booking), ['gateway' => 'paystack']);

        $response->assertRedirect('https://checkout.paystack.com/abc123');

        $this->assertDatabaseHas('payment_transactions', [
            'booking_id' => $this->booking->id,
            'gateway' => 'paystack',
            'status' => 'pending',
            'amount' => 2000000,
            'currency' => 'NGN',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.paystack.co/transaction/initialize'
                && $request['amount'] === 2000000
                && $request['currency'] === 'NGN'
                && $request['email'] === $this->guest->email;
        });
    }

    public function test_callback_verifies_transaction_and_credits_host_wallet_on_success(): void
    {
        $transaction = PaymentTransaction::create([
            'booking_id' => $this->booking->id,
            'payer_id' => $this->guest->id,
            'gateway' => 'paystack',
            'reference' => 'ref-123',
            'gateway_reference' => 'ref-123',
            'type' => 'charge',
            'amount' => 2000000,
            'currency' => 'NGN',
            'status' => 'pending',
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'reference' => 'ref-123',
                    'status' => 'success',
                    'amount' => 2000000,
                    'currency' => 'NGN',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->guest)
            ->get(route('payments.callback', ['gateway' => 'paystack']).'?reference=ref-123');

        $response->assertRedirect(route('bookings.show', $this->booking));

        $this->assertDatabaseHas('payment_transactions', [
            'id' => $transaction->id,
            'status' => 'succeeded',
        ]);

        $wallet = Wallet::where('holder_type', User::class)->where('holder_id', $this->host->id)->first();
        $this->assertNotNull($wallet);
        $this->assertSame(2000000, $wallet->balance);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'amount' => 2000000,
            'balance_after' => 2000000,
        ]);
    }

    public function test_webhook_with_valid_signature_marks_transaction_succeeded_and_credits_wallet_once(): void
    {
        $secret = 'sk_test_fake';
        config(['payments.paystack.secret' => $secret]);

        $transaction = PaymentTransaction::create([
            'booking_id' => $this->booking->id,
            'payer_id' => $this->guest->id,
            'gateway' => 'paystack',
            'reference' => 'ref-456',
            'gateway_reference' => 'ref-456',
            'type' => 'charge',
            'amount' => 2000000,
            'currency' => 'NGN',
            'status' => 'pending',
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'reference' => 'ref-456',
                    'status' => 'success',
                    'amount' => 2000000,
                    'currency' => 'NGN',
                ],
            ], 200),
        ]);

        $payload = json_encode([
            'event' => 'charge.success',
            'data' => ['reference' => 'ref-456'],
        ]);

        $signature = hash_hmac('sha512', $payload, $secret);

        $response = $this->call(
            'POST',
            route('payments.webhook', ['gateway' => 'paystack']),
            [],
            [],
            [],
            ['HTTP_X-Paystack-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $payload,
        );

        $response->assertOk();

        $transaction->refresh();
        $this->assertSame('succeeded', $transaction->status);

        $wallet = Wallet::where('holder_type', User::class)->where('holder_id', $this->host->id)->first();
        $this->assertSame(2000000, $wallet->balance);

        // Fire the same webhook again to prove idempotency: wallet must not be double-credited.
        $response2 = $this->call(
            'POST',
            route('payments.webhook', ['gateway' => 'paystack']),
            [],
            [],
            [],
            ['HTTP_X-Paystack-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $payload,
        );

        $response2->assertOk();
        $wallet->refresh();
        $this->assertSame(2000000, $wallet->balance, 'Wallet must not be credited twice for the same transaction.');
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        PaymentTransaction::create([
            'booking_id' => $this->booking->id,
            'payer_id' => $this->guest->id,
            'gateway' => 'paystack',
            'reference' => 'ref-789',
            'gateway_reference' => 'ref-789',
            'type' => 'charge',
            'amount' => 2000000,
            'currency' => 'NGN',
            'status' => 'pending',
        ]);

        $payload = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'ref-789']]);

        $response = $this->call(
            'POST',
            route('payments.webhook', ['gateway' => 'paystack']),
            [],
            [],
            [],
            ['HTTP_X-Paystack-Signature' => 'not-the-real-signature', 'CONTENT_TYPE' => 'application/json'],
            $payload,
        );

        $response->assertStatus(401);

        $this->assertDatabaseHas('payment_transactions', [
            'reference' => 'ref-789',
            'status' => 'pending',
        ]);
    }
}
