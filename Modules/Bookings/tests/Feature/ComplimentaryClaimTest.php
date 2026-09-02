<?php

namespace Modules\Bookings\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Models\ComplimentaryRule;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ComplimentaryClaimTest extends TestCase
{
    use RefreshDatabase;

    private User $guest;

    private Listing $listing;

    private ComplimentaryRule $rule;

    private $breakfast;

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

        $provider = User::factory()->create();
        $provider->assignRole('ServiceProvider');

        $property = Property::create([
            'host_id' => $host->id, 'name' => 'Complimentary Property', 'property_type' => 'apartment',
            'address' => '1 Test St', 'city' => 'Lagos', 'country' => 'Nigeria',
        ]);

        $this->listing = Listing::create([
            'property_id' => $property->id, 'title' => '3 Bedroom Suite',
            'base_price_amount' => 2000000, 'currency' => 'NGN', 'max_guests' => 6, 'bedrooms' => 3, 'bathrooms' => 3, 'status' => 'published',
        ]);

        $category = ServiceCategory::create(['name' => 'Food', 'slug' => 'food', 'fulfillment_type' => 'food']);
        $kitchen = Service::create([
            'provider_id' => $provider->id, 'service_category_id' => $category->id,
            'title' => 'Kitchen', 'price_amount' => 0, 'currency' => 'NGN', 'status' => 'published',
        ]);

        $this->breakfast = $kitchen->menuItems()->create([
            'name' => 'Breakfast', 'price_amount' => 350000, 'currency' => 'NGN', 'is_available' => true,
        ]);

        $this->rule = ComplimentaryRule::create([
            'listing_id' => $this->listing->id,
            'service_id' => $kitchen->id,
            'label' => 'Complimentary Breakfast',
            'min_guests' => 3,
            'max_quantity_per_guest' => 1,
            'is_active' => true,
        ]);
        $this->rule->menuItems()->attach($this->breakfast->id);
    }

    private function bookWithGuests(int $guests): Booking
    {
        return Booking::create([
            'listing_id' => $this->listing->id,
            'guest_id' => $this->guest->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests_count' => $guests,
            'status' => 'confirmed',
            'total_amount' => 4000000,
            'currency' => 'NGN',
        ]);
    }

    public function test_an_eligible_booking_can_claim_the_complimentary_offer(): void
    {
        $booking = $this->bookWithGuests(3);

        $response = $this->actingAs($this->guest)->post(route('bookings.complimentary.claim', [$booking, $this->rule]), [
            'items' => [$this->breakfast->id => 3],
        ]);

        $response->assertRedirect();

        $order = $booking->serviceOrders()->first();
        $this->assertNotNull($order);
        $this->assertTrue((bool) $order->is_complimentary);
        $this->assertSame(0, $order->total_amount);
        $this->assertSame(3, $order->items()->sum('quantity'));
    }

    public function test_the_same_offer_cannot_be_claimed_twice(): void
    {
        $booking = $this->bookWithGuests(3);

        $this->actingAs($this->guest)->post(route('bookings.complimentary.claim', [$booking, $this->rule]), [
            'items' => [$this->breakfast->id => 1],
        ])->assertRedirect();

        $response = $this->actingAs($this->guest)->post(route('bookings.complimentary.claim', [$booking, $this->rule]), [
            'items' => [$this->breakfast->id => 1],
        ]);

        $response->assertStatus(422);
    }

    public function test_a_booking_below_the_guest_threshold_cannot_claim(): void
    {
        $booking = $this->bookWithGuests(2);

        $response = $this->actingAs($this->guest)->post(route('bookings.complimentary.claim', [$booking, $this->rule]), [
            'items' => [$this->breakfast->id => 1],
        ]);

        $response->assertStatus(422);
    }
}
