<?php

namespace Modules\Payments\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Modules\Bookings\Models\Booking;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Modules\Payments\Notifications\PaymentReceiptNotification;
use Modules\Payments\Notifications\PaymentReceivedNotification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_payment_notifies_both_the_payer_and_the_host(): void
    {
        foreach (['Guest', 'Host'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        config(['payments.paystack.secret' => 'sk_test_fake']);

        $host = User::factory()->create();
        $host->assignRole('Host');

        $guest = User::factory()->create();
        $guest->assignRole('Guest');

        $property = Property::create([
            'host_id' => $host->id,
            'name' => 'Payment Notification Property',
            'property_type' => 'apartment',
            'address' => '1 Test St',
            'city' => 'Lagos',
            'country' => 'Nigeria',
        ]);

        $listing = Listing::create([
            'property_id' => $property->id,
            'title' => 'Payment Notification Listing',
            'slug' => 'payment-notification-listing',
            'base_price_amount' => 1000000,
            'currency' => 'NGN',
            'max_guests' => 2,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'status' => 'published',
        ]);

        $booking = Booking::create([
            'listing_id' => $listing->id,
            'guest_id' => $guest->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests_count' => 1,
            'status' => 'confirmed',
            'total_amount' => 2000000,
            'currency' => 'NGN',
        ]);

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/x', 'reference' => 'x'],
            ], 200),
        ]);

        $this->actingAs($guest)->post(route('checkout.initiate', $booking), ['gateway' => 'paystack']);

        $transaction = $booking->transactions()->first();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['reference' => $transaction->gateway_reference, 'status' => 'success', 'amount' => 2000000, 'currency' => 'NGN'],
            ], 200),
        ]);

        Notification::fake();

        $this->actingAs($guest)
            ->get(route('payments.callback', ['gateway' => 'paystack']).'?reference='.$transaction->reference);

        Notification::assertSentTo($guest, PaymentReceiptNotification::class);
        Notification::assertSentTo($host, PaymentReceivedNotification::class);
    }
}
