<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Models\ComplimentaryRule;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Events\Models\Event;
use Modules\Events\Support\EventOccurrenceGenerator;
use Modules\Listings\Database\Seeders\ListingsDatabaseSeeder;
use Modules\Listings\Models\Amenity;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Modules\Payments\Models\PaymentTransaction;
use Modules\Payments\Models\Wallet;
use Modules\Referrals\Models\AgentProfile;
use Modules\Referrals\Models\Referral;
use Modules\Services\Database\Seeders\ServicesDatabaseSeeder;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;
use Modules\Services\Models\ServiceOrder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([CoreDatabaseSeeder::class, ListingsDatabaseSeeder::class, ServicesDatabaseSeeder::class]);

        $admin = User::factory()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@shortstay.test',
        ]);
        $admin->assignRole('Admin');

        $host = User::factory()->create([
            'name' => 'Amara Okafor',
            'email' => 'host@shortstay.test',
        ]);
        $host->assignRole('Host');

        $guest = User::factory()->create([
            'name' => 'Jordan Lee',
            'email' => 'guest@shortstay.test',
        ]);
        $guest->assignRole('Guest');

        $agentUser = User::factory()->create([
            'name' => 'Priya Sharma',
            'email' => 'agent@shortstay.test',
        ]);
        $agentUser->assignRole('Agent');

        $providerUser = User::factory()->create([
            'name' => 'CleanCo Services',
            'email' => 'provider@shortstay.test',
        ]);
        $providerUser->assignRole('ServiceProvider');

        $kitchenProvider = User::factory()->create([
            'name' => 'Sunrise Kitchen',
            'email' => 'kitchen@shortstay.test',
        ]);
        $kitchenProvider->assignRole('ServiceProvider');

        $property = Property::create([
            'host_id' => $host->id,
            'name' => 'Sunlit Residences',
            'description' => 'A serviced apartment complex close to the city center, offering multiple unit types.',
            'property_type' => 'apartment',
            'address' => '12 Marina Road',
            'city' => 'Lagos',
            'country' => 'Nigeria',
            'latitude' => 6.4550,
            'longitude' => 3.3841,
            'service_charge_percent' => 5,
            'tax_percent' => 7.5,
        ]);

        $listing = Listing::create([
            'property_id' => $property->id,
            'title' => '2 Bedroom Deluxe',
            'slug' => 'sunlit-two-bedroom-apartment',
            'description' => 'A bright, modern two-bedroom apartment close to the city center.',
            'base_price_amount' => 15000000,
            'currency' => 'NGN',
            'max_guests' => 4,
            'bedrooms' => 2,
            'bathrooms' => 2,
            'status' => 'published',
        ]);

        $listing->amenities()->attach(
            Amenity::whereIn('slug', ['wifi', 'air-conditioning', 'kitchen', 'workspace'])->pluck('id')
        );

        $familyListing = Listing::create([
            'property_id' => $property->id,
            'title' => '3 Bedroom Family Suite',
            'slug' => 'sunlit-three-bedroom-suite',
            'description' => 'A spacious three-bedroom suite, perfect for families and small groups.',
            'base_price_amount' => 22000000,
            'currency' => 'NGN',
            'max_guests' => 6,
            'bedrooms' => 3,
            'bathrooms' => 3,
            'status' => 'published',
        ]);

        $familyListing->amenities()->attach(
            Amenity::whereIn('slug', ['wifi', 'air-conditioning', 'kitchen', 'breakfast-included'])->pluck('id')
        );

        $booking = Booking::create([
            'listing_id' => $listing->id,
            'guest_id' => $guest->id,
            'check_in' => now()->addDays(7)->toDateString(),
            'check_out' => now()->addDays(10)->toDateString(),
            'guests_count' => 2,
            'status' => 'confirmed',
            'total_amount' => 45000000,
            'currency' => 'NGN',
        ]);

        $agent = AgentProfile::create([
            'user_id' => $agentUser->id,
            'referral_code' => 'PRIYA10',
            'commission_rate' => 5,
            'status' => 'active',
        ]);

        Referral::create([
            'agent_id' => $agent->id,
            'referred_user_id' => $guest->id,
            'referable_type' => Booking::class,
            'referable_id' => $booking->id,
            'status' => 'approved',
            'commission_amount' => 2250000,
            'currency' => 'NGN',
        ]);

        $laundryCategory = ServiceCategory::where('slug', 'laundry')->firstOrFail();
        $logisticsCategory = ServiceCategory::where('slug', 'logistics')->firstOrFail();
        $foodCategory = ServiceCategory::where('slug', 'food')->firstOrFail();

        $service = Service::create([
            'provider_id' => $providerUser->id,
            'service_category_id' => $laundryCategory->id,
            'title' => 'Same-day Laundry Pickup',
            'description' => 'Bag it, we wash it, back by evening.',
            'price_amount' => 500000,
            'currency' => 'NGN',
            'status' => 'published',
        ]);

        Service::create([
            'provider_id' => $providerUser->id,
            'service_category_id' => $logisticsCategory->id,
            'title' => 'Airport Pickup',
            'description' => 'Comfortable transfer from the airport straight to your stay.',
            'price_amount' => 1200000,
            'currency' => 'NGN',
            'status' => 'published',
            'delivery_fee_amount' => 0,
        ]);

        $kitchen = Service::create([
            'provider_id' => $kitchenProvider->id,
            'service_category_id' => $foodCategory->id,
            'title' => 'Sunlit In-House Restaurant',
            'description' => 'Fresh meals prepared on-site, delivered straight to your unit.',
            'price_amount' => 500000,
            'currency' => 'NGN',
            'status' => 'published',
            'delivery_fee_amount' => 100000,
            'free_delivery_over_amount' => 500000,
            'commission_type' => 'percentage',
            'commission_value' => 10,
        ]);

        $kitchen->properties()->attach($property->id);

        $breakfast = $kitchen->menuItems()->create([
            'name' => 'Continental Breakfast',
            'description' => 'Eggs, toast, fresh juice, and coffee.',
            'price_amount' => 350000,
            'currency' => 'NGN',
            'category' => 'Breakfast',
            'is_available' => true,
        ]);

        $jollof = $kitchen->menuItems()->create([
            'name' => 'Jollof Rice Combo',
            'description' => 'Smoky party-style jollof rice with a protein of your choice.',
            'ingredients' => 'Rice, tomato stew, onions, spices, protein of choice.',
            'allergens' => ['Gluten'],
            'price_amount' => 450000,
            'currency' => 'NGN',
            'category' => 'Mains',
            'is_available' => true,
        ]);

        $proteinGroup = $jollof->optionGroups()->create([
            'name' => 'Choose your protein',
            'selection_type' => 'single',
            'is_required' => true,
            'min_select' => 1,
        ]);
        $proteinGroup->options()->createMany([
            ['name' => 'Chicken', 'price_delta_amount' => 0, 'is_default' => true],
            ['name' => 'Goat meat', 'price_delta_amount' => 100000],
            ['name' => 'Fish', 'price_delta_amount' => 150000],
        ]);

        $extrasGroup = $jollof->optionGroups()->create([
            'name' => 'Add extras',
            'selection_type' => 'multiple',
        ]);
        $extrasGroup->options()->createMany([
            ['name' => 'Fried plantain', 'price_delta_amount' => 50000, 'max_quantity' => 2],
            ['name' => 'Extra egg', 'price_delta_amount' => 30000, 'max_quantity' => 4],
        ]);

        $chapman = $kitchen->menuItems()->create([
            'name' => 'Chapman Mocktail',
            'description' => 'A refreshing house-made Chapman.',
            'allergens' => ['Contains fruit juice'],
            'price_amount' => 150000,
            'currency' => 'NGN',
            'category' => 'Drinks',
            'is_available' => true,
        ]);

        $jollof->pairings()->attach($chapman->id, ['type' => 'pairing']);
        $jollof->upsells()->attach($breakfast->id, ['type' => 'upsell']);

        $rule = ComplimentaryRule::create([
            'listing_id' => $familyListing->id,
            'service_id' => $kitchen->id,
            'label' => 'Complimentary Breakfast',
            'min_guests' => 3,
            'max_quantity_per_guest' => 1,
            'is_active' => true,
        ]);

        $rule->menuItems()->attach($breakfast->id);

        $serviceOrder = ServiceOrder::create([
            'service_id' => $service->id,
            'customer_id' => $guest->id,
            'booking_id' => $booking->id,
            'status' => 'confirmed',
            'fulfillment_details' => ['pickup_window' => '09:00-11:00', 'dropoff_window' => '18:00-20:00'],
            'scheduled_at' => now()->addDays(8),
            'total_amount' => 500000,
            'currency' => 'NGN',
        ]);

        PaymentTransaction::create([
            'booking_id' => $booking->id,
            'service_order_id' => $serviceOrder->id,
            'payer_id' => $guest->id,
            'gateway' => 'paystack',
            'reference' => (string) Str::uuid(),
            'gateway_reference' => 'demo-ref-'.uniqid(),
            'type' => 'charge',
            'amount' => 45500000,
            'currency' => 'NGN',
            'status' => 'succeeded',
        ]);

        Wallet::create([
            'holder_type' => User::class,
            'holder_id' => $host->id,
            'balance' => 42750000,
            'currency' => 'NGN',
        ]);

        $sundayService = Event::create([
            'created_by' => $host->id,
            'title' => 'Sunday Service',
            'slug' => 'sunday-service',
            'description' => 'A welcoming weekly community service, open to guests and neighbors alike.',
            'property_id' => $property->id,
            'category' => 'Community',
            'is_ticketed' => false,
            'price_amount' => 0,
            'currency' => 'NGN',
            'recurrence_type' => 'weekly',
            'recurrence_days_of_week' => [0],
            'starts_on' => now()->startOfWeek()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:30',
            'status' => 'published',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
        EventOccurrenceGenerator::generate($sundayService);

        $rooftopParty = Event::create([
            'created_by' => $kitchenProvider->id,
            'title' => 'Rooftop Sunset Party',
            'slug' => 'rooftop-sunset-party',
            'description' => 'An evening of music, food, and drinks on the Sunlit Residences rooftop.',
            'property_id' => $property->id,
            'category' => 'Party',
            'is_ticketed' => true,
            'capacity' => 60,
            'price_amount' => 750000,
            'currency' => 'NGN',
            'commission_type' => 'percentage',
            'commission_value' => 10,
            'recurrence_type' => 'none',
            'starts_on' => now()->addDays(14)->toDateString(),
            'start_time' => '18:00',
            'end_time' => '22:00',
            'status' => 'published',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
        EventOccurrenceGenerator::generate($rooftopParty);

        $poolParty = Event::create([
            'created_by' => $host->id,
            'title' => 'Pool Party Weekend',
            'slug' => 'pool-party-weekend',
            'description' => 'A weekend pool party for residents and their guests.',
            'property_id' => $property->id,
            'category' => 'Party',
            'is_ticketed' => true,
            'capacity' => 40,
            'price_amount' => 300000,
            'currency' => 'NGN',
            'recurrence_type' => 'none',
            'starts_on' => now()->addDays(21)->toDateString(),
            'start_time' => '13:00',
            'end_time' => '18:00',
            'status' => 'pending_approval',
        ]);
        EventOccurrenceGenerator::generate($poolParty);
    }
}
