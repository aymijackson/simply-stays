<?php

namespace Modules\Bookings\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Bookings\Models\Booking;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PricingBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private User $guest;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Guest', 'Host'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->guest = User::factory()->create();
        $this->guest->assignRole('Guest');
    }

    private function makeListing(float $serviceChargePercent, float $taxPercent): Listing
    {
        $host = User::factory()->create();
        $host->assignRole('Host');

        $property = Property::create([
            'host_id' => $host->id,
            'name' => 'Pricing Test Property',
            'property_type' => 'apartment',
            'address' => '1 Test St',
            'city' => 'Lagos',
            'country' => 'Nigeria',
            'service_charge_percent' => $serviceChargePercent,
            'tax_percent' => $taxPercent,
        ]);

        return Listing::create([
            'property_id' => $property->id,
            'title' => 'Pricing Test Unit',
            'base_price_amount' => 1000000,
            'currency' => 'NGN',
            'max_guests' => 2,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'status' => 'published',
        ]);
    }

    public function test_booking_total_includes_property_service_charge_and_tax(): void
    {
        $listing = $this->makeListing(5, 7.5);

        $this->actingAs($this->guest)->post(route('bookings.store'), [
            'listing_id' => $listing->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests_count' => 1,
        ])->assertRedirect();

        $booking = Booking::first();

        // Subtotal: 2 nights x 1,000,000 = 2,000,000. Service charge 5% = 100,000. Tax 7.5% = 150,000.
        $this->assertSame(2000000, $booking->price_breakdown['subtotal']);
        $this->assertSame(100000, $booking->price_breakdown['service_charge']);
        $this->assertSame(150000, $booking->price_breakdown['tax']);
        $this->assertSame(2250000, $booking->total_amount);
    }

    public function test_a_property_with_zero_rates_behaves_like_before(): void
    {
        $listing = $this->makeListing(0, 0);

        $this->actingAs($this->guest)->post(route('bookings.store'), [
            'listing_id' => $listing->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests_count' => 1,
        ])->assertRedirect();

        $booking = Booking::first();

        $this->assertSame(2000000, $booking->total_amount);
    }
}
