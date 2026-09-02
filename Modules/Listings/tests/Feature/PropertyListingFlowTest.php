<?php

namespace Modules\Listings\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PropertyListingFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private User $otherHost;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Guest', 'Host', 'Admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->host = User::factory()->create();
        $this->host->assignRole('Host');

        $this->otherHost = User::factory()->create();
        $this->otherHost->assignRole('Host');
    }

    public function test_host_can_create_a_property(): void
    {
        $response = $this->actingAs($this->host)->post(route('host.properties.store'), [
            'name' => 'Luxurious Inn Stay',
            'property_type' => 'apartment',
            'address' => '10 Palm Street',
            'city' => 'Abuja',
            'country' => 'Nigeria',
            'service_charge_percent' => 5,
            'tax_percent' => 7.5,
        ]);

        $response->assertRedirect(route('host.properties.index'));
        $this->assertDatabaseHas('properties', ['name' => 'Luxurious Inn Stay', 'host_id' => $this->host->id]);
    }

    public function test_host_cannot_edit_another_hosts_property(): void
    {
        $property = Property::create([
            'host_id' => $this->otherHost->id,
            'name' => 'Other Host Place',
            'property_type' => 'apartment',
            'address' => '1 Test St',
            'city' => 'Lagos',
            'country' => 'Nigeria',
        ]);

        $this->actingAs($this->host)
            ->put(route('host.properties.update', $property), [
                'name' => 'Hijacked',
                'property_type' => 'apartment',
                'address' => '1 Test St',
                'city' => 'Lagos',
                'country' => 'Nigeria',
            ])
            ->assertForbidden();
    }

    public function test_a_property_can_have_multiple_listing_unit_types(): void
    {
        $property = Property::create([
            'host_id' => $this->host->id,
            'name' => 'Luxurious Inn Stay',
            'property_type' => 'apartment',
            'address' => '10 Palm Street',
            'city' => 'Abuja',
            'country' => 'Nigeria',
        ]);

        $twoBed = Listing::create([
            'property_id' => $property->id,
            'title' => '2 Bedroom Flat',
            'base_price_amount' => 1000000,
            'currency' => 'NGN',
            'max_guests' => 4,
            'bedrooms' => 2,
            'bathrooms' => 2,
            'status' => 'published',
        ]);

        $threeBed = Listing::create([
            'property_id' => $property->id,
            'title' => '3 Bedroom Flat',
            'base_price_amount' => 1500000,
            'currency' => 'NGN',
            'max_guests' => 6,
            'bedrooms' => 3,
            'bathrooms' => 3,
            'status' => 'published',
        ]);

        $this->assertSame(2, $property->fresh()->listings()->count());

        $response = $this->get(route('listings.show', $twoBed));
        $response->assertOk();
        $response->assertSee($threeBed->title);
    }

    public function test_search_by_city_filters_through_the_property_relation(): void
    {
        $abujaProperty = Property::create([
            'host_id' => $this->host->id,
            'name' => 'Abuja Place',
            'property_type' => 'apartment',
            'address' => '1 Test St',
            'city' => 'Abuja',
            'country' => 'Nigeria',
        ]);

        $lagosProperty = Property::create([
            'host_id' => $this->host->id,
            'name' => 'Lagos Place',
            'property_type' => 'apartment',
            'address' => '2 Test St',
            'city' => 'Lagos',
            'country' => 'Nigeria',
        ]);

        $abujaListing = Listing::create([
            'property_id' => $abujaProperty->id,
            'title' => 'Abuja Unit',
            'base_price_amount' => 1000000,
            'currency' => 'NGN',
            'max_guests' => 2,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'status' => 'published',
        ]);

        $lagosListing = Listing::create([
            'property_id' => $lagosProperty->id,
            'title' => 'Lagos Unit',
            'base_price_amount' => 1000000,
            'currency' => 'NGN',
            'max_guests' => 2,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'status' => 'published',
        ]);

        $response = $this->get(route('listings.index', ['city' => 'Abuja']));

        $response->assertOk();
        $response->assertSee($abujaListing->title);
        $response->assertDontSee($lagosListing->title);
    }
}
