<?php

namespace Modules\Services\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Bookings\Models\Booking;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PropertyScopedServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $guest;

    private Property $propertyA;

    private Property $propertyB;

    private Listing $listingA;

    private Listing $listingB;

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

        $this->propertyA = Property::create([
            'host_id' => $host->id, 'name' => 'Property A', 'property_type' => 'apartment',
            'address' => '1 Test St', 'city' => 'Lagos', 'country' => 'Nigeria',
        ]);

        $this->propertyB = Property::create([
            'host_id' => $host->id, 'name' => 'Property B', 'property_type' => 'apartment',
            'address' => '2 Test St', 'city' => 'Lagos', 'country' => 'Nigeria',
        ]);

        $this->listingA = Listing::create([
            'property_id' => $this->propertyA->id, 'title' => 'Unit A',
            'base_price_amount' => 1000000, 'currency' => 'NGN', 'max_guests' => 2, 'bedrooms' => 1, 'bathrooms' => 1, 'status' => 'published',
        ]);

        $this->listingB = Listing::create([
            'property_id' => $this->propertyB->id, 'title' => 'Unit B',
            'base_price_amount' => 1000000, 'currency' => 'NGN', 'max_guests' => 2, 'bedrooms' => 1, 'bathrooms' => 1, 'status' => 'published',
        ]);
    }

    private function makeBookingFor(Listing $listing): Booking
    {
        return Booking::create([
            'listing_id' => $listing->id,
            'guest_id' => $this->guest->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(6)->toDateString(),
            'guests_count' => 1,
            'status' => 'confirmed',
            'total_amount' => 1000000,
            'currency' => 'NGN',
        ]);
    }

    public function test_a_property_scoped_service_is_orderable_for_its_own_property(): void
    {
        $provider = User::factory()->create();
        $provider->assignRole('ServiceProvider');
        $category = ServiceCategory::where('slug', 'laundry')->first() ?? ServiceCategory::create(['name' => 'Laundry', 'slug' => 'laundry', 'fulfillment_type' => 'laundry']);

        $service = Service::create([
            'provider_id' => $provider->id, 'service_category_id' => $category->id,
            'title' => 'Scoped Laundry', 'price_amount' => 500000, 'currency' => 'NGN', 'status' => 'published',
        ]);
        $service->properties()->attach($this->propertyA->id);

        $booking = $this->makeBookingFor($this->listingA);

        $response = $this->actingAs($this->guest)->post(route('service-orders.store', $service), [
            'booking_id' => $booking->id,
            'details' => ['pickup_window' => '09:00-11:00', 'dropoff_window' => '18:00-20:00'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('service_orders', ['service_id' => $service->id, 'booking_id' => $booking->id]);
    }

    public function test_a_property_scoped_service_is_blocked_for_a_different_property(): void
    {
        $provider = User::factory()->create();
        $provider->assignRole('ServiceProvider');
        $category = ServiceCategory::where('slug', 'laundry')->first() ?? ServiceCategory::create(['name' => 'Laundry', 'slug' => 'laundry', 'fulfillment_type' => 'laundry']);

        $service = Service::create([
            'provider_id' => $provider->id, 'service_category_id' => $category->id,
            'title' => 'Scoped Laundry', 'price_amount' => 500000, 'currency' => 'NGN', 'status' => 'published',
        ]);
        $service->properties()->attach($this->propertyA->id);

        $bookingAtB = $this->makeBookingFor($this->listingB);

        $response = $this->actingAs($this->guest)->post(route('service-orders.store', $service), [
            'booking_id' => $bookingAtB->id,
            'details' => ['pickup_window' => '09:00-11:00', 'dropoff_window' => '18:00-20:00'],
        ]);

        $response->assertForbidden();
    }

    public function test_a_globally_unscoped_service_is_orderable_at_any_property(): void
    {
        $provider = User::factory()->create();
        $provider->assignRole('ServiceProvider');
        $category = ServiceCategory::where('slug', 'laundry')->first() ?? ServiceCategory::create(['name' => 'Laundry', 'slug' => 'laundry', 'fulfillment_type' => 'laundry']);

        $service = Service::create([
            'provider_id' => $provider->id, 'service_category_id' => $category->id,
            'title' => 'Global Laundry', 'price_amount' => 500000, 'currency' => 'NGN', 'status' => 'published',
        ]);

        $bookingAtB = $this->makeBookingFor($this->listingB);

        $response = $this->actingAs($this->guest)->post(route('service-orders.store', $service), [
            'booking_id' => $bookingAtB->id,
            'details' => ['pickup_window' => '09:00-11:00', 'dropoff_window' => '18:00-20:00'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('service_orders', ['service_id' => $service->id, 'booking_id' => $bookingAtB->id]);
    }
}
