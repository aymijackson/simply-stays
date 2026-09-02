<?php

namespace Modules\Bookings\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Notifications\BookingCancelled;
use Modules\Bookings\Notifications\BookingConfirmed;
use Modules\Bookings\Notifications\BookingRequested;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private User $guest;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Guest', 'Host'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->host = User::factory()->create();
        $this->host->assignRole('Host');

        $this->guest = User::factory()->create();
        $this->guest->assignRole('Guest');

        $property = Property::create([
            'host_id' => $this->host->id,
            'name' => 'Notification Test Property',
            'property_type' => 'apartment',
            'address' => '1 Test St',
            'city' => 'Lagos',
            'country' => 'Nigeria',
        ]);

        $this->listing = Listing::create([
            'property_id' => $property->id,
            'title' => 'Notification Test Listing',
            'slug' => 'notification-test-listing',
            'base_price_amount' => 1000000,
            'currency' => 'NGN',
            'max_guests' => 2,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'status' => 'published',
        ]);
    }

    public function test_creating_a_booking_notifies_the_host(): void
    {
        Notification::fake();

        $this->actingAs($this->guest)->post(route('bookings.store'), [
            'listing_id' => $this->listing->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests_count' => 1,
        ]);

        Notification::assertSentTo($this->host, BookingRequested::class);
        Notification::assertNotSentTo($this->guest, BookingRequested::class);
    }

    public function test_confirming_a_booking_notifies_the_guest(): void
    {
        $booking = Booking::create([
            'listing_id' => $this->listing->id,
            'guest_id' => $this->guest->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests_count' => 1,
            'status' => 'pending',
            'total_amount' => 2000000,
            'currency' => 'NGN',
        ]);

        Notification::fake();

        $this->actingAs($this->host)->patch(route('bookings.confirm', $booking));

        Notification::assertSentTo($this->guest, BookingConfirmed::class);
    }

    public function test_guest_cancelling_notifies_the_host(): void
    {
        $booking = Booking::create([
            'listing_id' => $this->listing->id,
            'guest_id' => $this->guest->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests_count' => 1,
            'status' => 'confirmed',
            'total_amount' => 2000000,
            'currency' => 'NGN',
        ]);

        Notification::fake();

        $this->actingAs($this->guest)->patch(route('bookings.cancel', $booking));

        Notification::assertSentTo($this->host, BookingCancelled::class);
        Notification::assertNotSentTo($this->guest, BookingCancelled::class);
    }
}
