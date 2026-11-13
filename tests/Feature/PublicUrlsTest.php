<?php

namespace Tests\Feature;

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

/** URLs must not expose database ids, and in-house services show the property, not the admin account. */
class PublicUrlsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guest;

    private Property $property;

    private Booking $booking;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Guest', 'Host', 'ServiceProvider', 'Admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->admin = User::factory()->create(['name' => 'Platform Admin']);
        $this->admin->assignRole('Admin');
        $this->guest = User::factory()->create();
        $this->guest->assignRole('Guest');

        $this->property = Property::create([
            'host_id' => $this->admin->id, 'name' => 'Premium Stay', 'property_type' => 'apartment',
            'address' => '6 Etim Expo', 'city' => 'Calabar', 'country' => 'Nigeria',
        ]);
        $listing = Listing::create([
            'property_id' => $this->property->id, 'title' => 'King Suite', 'slug' => 'king-suite',
            'base_price_amount' => 5000000, 'currency' => 'NGN', 'max_guests' => 2, 'bedrooms' => 1, 'bathrooms' => 1, 'status' => 'published',
        ]);
        $this->booking = Booking::create([
            'listing_id' => $listing->id, 'guest_id' => $this->guest->id, 'check_in' => today()->addDay(), 'check_out' => today()->addDays(3),
            'guests_count' => 1, 'status' => 'confirmed', 'total_amount' => 10000000, 'currency' => 'NGN',
        ]);

        $category = ServiceCategory::create(['name' => 'Laundry', 'slug' => 'laundry', 'fulfillment_type' => 'laundry']);
        $this->service = Service::create([
            'provider_id' => $this->admin->id, 'service_category_id' => $category->id, 'title' => 'Express Laundry',
            'price_amount' => 100000, 'currency' => 'NGN', 'status' => 'published', 'audience' => 'both',
        ]);
    }

    public function test_links_use_slugs_or_random_keys_never_ids(): void
    {
        $this->assertSame('express-laundry', $this->service->slug);
        $this->assertStringEndsWith('/services/express-laundry', route('services.show', $this->service));
        $this->assertStringEndsWith('/host/properties/premium-stay/edit', route('host.properties.edit', $this->property));

        $bookingUrl = route('bookings.show', $this->booking);
        $this->assertMatchesRegularExpression('#/bookings/[0-9a-z]{26}$#', $bookingUrl);
        $this->assertStringNotContainsString('/bookings/'.$this->booking->id, $bookingUrl.'/');

        $order = ServiceOrder::create(['service_id' => $this->service->id, 'customer_id' => $this->guest->id, 'status' => 'pending', 'total_amount' => 1, 'currency' => 'NGN']);
        $this->assertMatchesRegularExpression('#/service-orders/[0-9a-z]{26}$#', route('service-orders.show', $order));
        $this->assertMatchesRegularExpression('#^BK-[0-9A-Z]{8}$#', $this->booking->referenceCode());
    }

    public function test_old_numeric_urls_no_longer_resolve(): void
    {
        $this->actingAs($this->guest)->get('/bookings/'.$this->booking->id)->assertNotFound();
        $this->actingAs($this->guest)->get(route('bookings.show', $this->booking))->assertOk();

        $this->get('/services/'.$this->service->id)->assertNotFound();
        $this->get(route('services.show', $this->service))->assertOk();

        // Ids in query strings are ignored too.
        $this->actingAs($this->admin)->get(route('guest-forms.create', ['booking' => $this->booking->id]))->assertNotFound();
        $this->actingAs($this->admin)->get(route('guest-forms.create', ['booking' => $this->booking]))->assertOk();
    }

    public function test_in_house_services_show_the_property_not_the_admin(): void
    {
        $this->get(route('services.show', $this->service))
            ->assertOk()->assertSee('Offered by '.config('app.name'))->assertDontSee('Platform Admin');

        $this->service->properties()->attach($this->property->id);
        $this->get(route('services.show', $this->service->fresh()))
            ->assertOk()->assertSee('Offered by Premium Stay')->assertDontSee('Platform Admin');

        $vendor = User::factory()->create(['name' => 'CleanCo']);
        $vendor->assignRole('ServiceProvider');
        $this->service->update(['provider_id' => $vendor->id]);
        $this->get(route('services.show', $this->service->fresh()))->assertOk()->assertSee('Offered by CleanCo');
    }
}
