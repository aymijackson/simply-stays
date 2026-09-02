<?php

namespace Modules\Events\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventBooking;
use Modules\Events\Support\EventOccurrenceGenerator;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventTicketBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $guest;

    private User $organizer;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Guest', 'Host'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->guest = User::factory()->create();
        $this->guest->assignRole('Guest');

        $this->organizer = User::factory()->create();
        $this->organizer->assignRole('Host');
    }

    private function makeEvent(array $overrides = []): Event
    {
        $event = Event::create(array_merge([
            'created_by' => $this->organizer->id,
            'title' => 'Ticketed Event',
            'is_ticketed' => true,
            'capacity' => 2,
            'price_amount' => 500000,
            'currency' => 'NGN',
            'recurrence_type' => 'none',
            'starts_on' => now()->addDays(5)->toDateString(),
            'start_time' => '18:00',
            'status' => 'published',
        ], $overrides));

        EventOccurrenceGenerator::generate($event);

        return $event;
    }

    public function test_a_guest_can_reserve_a_free_ticketed_event_without_payment(): void
    {
        $event = $this->makeEvent(['price_amount' => 0]);
        $occurrence = $event->occurrences()->first();

        $response = $this->actingAs($this->guest)->post(route('event-bookings.store', $occurrence), ['quantity' => 1]);

        $response->assertRedirect();
        $booking = EventBooking::first();
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame(0, $booking->total_amount);
        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_a_paid_ticketed_event_requires_checkout(): void
    {
        $event = $this->makeEvent();
        $occurrence = $event->occurrences()->first();

        $this->actingAs($this->guest)->post(route('event-bookings.store', $occurrence), ['quantity' => 2]);

        $booking = EventBooking::first();
        $this->assertSame('pending', $booking->status);
        $this->assertSame(1000000, $booking->total_amount);
    }

    public function test_booking_beyond_remaining_capacity_is_rejected(): void
    {
        $event = $this->makeEvent(['capacity' => 2]);
        $occurrence = $event->occurrences()->first();

        $this->actingAs($this->guest)->post(route('event-bookings.store', $occurrence), ['quantity' => 2]);

        $anotherGuest = User::factory()->create();
        $anotherGuest->assignRole('Guest');

        $response = $this->actingAs($anotherGuest)->post(route('event-bookings.store', $occurrence), ['quantity' => 1]);

        $response->assertStatus(422);
        $this->assertSame(1, EventBooking::count());
    }

    public function test_a_non_ticketed_event_cannot_be_booked(): void
    {
        $event = $this->makeEvent(['is_ticketed' => false, 'price_amount' => 0]);
        $occurrence = $event->occurrences()->first();

        $response = $this->actingAs($this->guest)->post(route('event-bookings.store', $occurrence), ['quantity' => 1]);

        $response->assertStatus(422);
    }
}
