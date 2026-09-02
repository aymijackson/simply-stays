<?php

namespace Modules\Services\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Bookings\Models\Booking;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;
use Modules\Services\Models\ServiceOrder;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceAudienceOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $guest;

    private Property $property;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Guest', 'Host', 'ServiceProvider'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $host = User::factory()->create();
        $host->assignRole('Host');

        $this->guest = User::factory()->create();
        $this->guest->assignRole('Guest');

        $this->property = Property::create([
            'host_id' => $host->id, 'name' => 'Test Property', 'property_type' => 'apartment',
            'address' => '10 Palm St', 'city' => 'Lagos', 'country' => 'Nigeria',
        ]);

        $this->listing = Listing::create([
            'property_id' => $this->property->id, 'title' => '2 Bedroom Unit',
            'base_price_amount' => 1000000, 'currency' => 'NGN', 'max_guests' => 2, 'bedrooms' => 2, 'bathrooms' => 2, 'status' => 'published',
        ]);
    }

    private function makeBooking(): Booking
    {
        return Booking::create([
            'listing_id' => $this->listing->id,
            'guest_id' => $this->guest->id,
            'check_in' => now()->addDays(2)->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'guests_count' => 1,
            'status' => 'confirmed',
            'total_amount' => 2000000,
            'currency' => 'NGN',
        ]);
    }

    private function makeFoodService(string $audience): Service
    {
        $provider = User::factory()->create();
        $provider->assignRole('ServiceProvider');
        $category = ServiceCategory::create(['name' => 'Food', 'slug' => 'food', 'fulfillment_type' => 'food']);

        $service = Service::create([
            'provider_id' => $provider->id, 'service_category_id' => $category->id,
            'title' => 'Kitchen', 'price_amount' => 0, 'currency' => 'NGN', 'status' => 'published',
            'audience' => $audience,
        ]);

        $service->menuItems()->create(['name' => 'Snack', 'price_amount' => 100000, 'currency' => 'NGN', 'is_available' => true]);

        return $service;
    }

    public function test_a_guests_only_service_cannot_be_ordered_without_a_booking(): void
    {
        $service = $this->makeFoodService('guests_only');
        $item = $service->menuItems()->first();

        $response = $this->actingAs($this->guest)->post(route('service-orders.store', $service), [
            'items' => [$item->id => ['quantity' => 1]],
        ]);

        $response->assertSessionHasErrors('booking_id');
        $this->assertSame(0, ServiceOrder::count());
    }

    public function test_a_guests_only_service_auto_fills_the_delivery_address_from_the_selected_booking(): void
    {
        $service = $this->makeFoodService('guests_only');
        $item = $service->menuItems()->first();
        $booking = $this->makeBooking();

        $response = $this->actingAs($this->guest)->post(route('service-orders.store', $service), [
            'booking_id' => $booking->id,
            'items' => [$item->id => ['quantity' => 1]],
        ]);

        $response->assertRedirect();

        $order = ServiceOrder::first();
        $this->assertNotNull($order);
        $this->assertSame($booking->id, $order->booking_id);
        $this->assertSame($booking->deliveryAddressLabel(), $order->fulfillment_details['delivery_address']);
    }

    public function test_a_non_guests_only_service_ignores_a_submitted_booking_id_and_requires_a_manual_address(): void
    {
        $service = $this->makeFoodService('non_guests_only');
        $item = $service->menuItems()->first();
        $booking = $this->makeBooking();

        // Without an address at all: rejected, since booking-linking is disabled for this audience.
        $this->actingAs($this->guest)->post(route('service-orders.store', $service), [
            'booking_id' => $booking->id,
            'items' => [$item->id => ['quantity' => 1]],
        ])->assertSessionHasErrors('details.delivery_address');

        // With a manual address: succeeds, and the booking link is stripped even though one was submitted.
        $response = $this->actingAs($this->guest)->post(route('service-orders.store', $service), [
            'booking_id' => $booking->id,
            'details' => ['delivery_address' => '55 Manual Street'],
            'items' => [$item->id => ['quantity' => 1]],
        ]);

        $response->assertRedirect();

        $order = ServiceOrder::first();
        $this->assertNotNull($order);
        $this->assertNull($order->booking_id);
        $this->assertSame('55 Manual Street', $order->fulfillment_details['delivery_address']);
    }

    public function test_a_service_open_to_both_still_lets_a_guest_use_their_booking_address_or_type_one(): void
    {
        $service = $this->makeFoodService('both');
        $item = $service->menuItems()->first();
        $booking = $this->makeBooking();

        $response = $this->actingAs($this->guest)->post(route('service-orders.store', $service), [
            'details' => ['delivery_address' => 'Typed address'],
            'items' => [$item->id => ['quantity' => 1]],
        ]);

        $response->assertRedirect();
        $this->assertSame('Typed address', ServiceOrder::first()->fulfillment_details['delivery_address']);
    }
}
