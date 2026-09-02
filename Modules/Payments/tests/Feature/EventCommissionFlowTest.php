<?php

namespace Modules\Payments\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventBooking;
use Modules\Events\Support\EventOccurrenceGenerator;
use Modules\Payments\Models\Wallet;
use Modules\Payments\Services\RefundProcessor;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventCommissionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_paying_for_a_commissioned_event_ticket_splits_credit_between_organizer_and_platform(): void
    {
        foreach (['Guest', 'Host'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        config([
            'payments.paystack.secret' => 'sk_test_fake',
            'payments.platform_account_email' => 'platform@shortstay.internal',
        ]);

        $organizer = User::factory()->create();
        $organizer->assignRole('Host');

        $guest = User::factory()->create();
        $guest->assignRole('Guest');

        $event = Event::create([
            'created_by' => $organizer->id,
            'title' => 'Commissioned Event',
            'is_ticketed' => true,
            'capacity' => 10,
            'price_amount' => 1000000,
            'currency' => 'NGN',
            'commission_type' => 'percentage',
            'commission_value' => 10,
            'recurrence_type' => 'none',
            'starts_on' => now()->addDays(5)->toDateString(),
            'start_time' => '18:00',
            'status' => 'published',
        ]);
        EventOccurrenceGenerator::generate($event);
        $occurrence = $event->occurrences()->first();

        $this->actingAs($guest)->post(route('event-bookings.store', $occurrence), ['quantity' => 1]);
        $booking = EventBooking::first();

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/x', 'reference' => 'x'],
            ], 200),
        ]);

        $this->actingAs($guest)->post(route('checkout.initiate.event', $booking), ['gateway' => 'paystack']);

        $transaction = $booking->transactions()->first();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['reference' => $transaction->gateway_reference, 'status' => 'success', 'amount' => 1000000, 'currency' => 'NGN'],
            ], 200),
        ]);

        $this->actingAs($guest)
            ->get(route('payments.callback', ['gateway' => 'paystack']).'?reference='.$transaction->reference);

        $organizerWallet = Wallet::where('holder_type', User::class)->where('holder_id', $organizer->id)->first();
        $platformUser = User::where('email', 'platform@shortstay.internal')->first();
        $platformWallet = Wallet::where('holder_type', User::class)->where('holder_id', $platformUser->id)->first();

        $this->assertNotNull($organizerWallet);
        $this->assertNotNull($platformWallet);
        $this->assertSame(900000, $organizerWallet->balance);
        $this->assertSame(100000, $platformWallet->balance);

        $refunds = app(RefundProcessor::class);
        Http::fake(['api.paystack.co/refund' => Http::response(['status' => true], 200)]);

        $charge = $booking->transactions()->where('type', 'charge')->first();
        $refunds->refund($charge);

        $this->assertSame(0, $organizerWallet->fresh()->balance);
        $this->assertSame(0, $platformWallet->fresh()->balance);
    }
}
