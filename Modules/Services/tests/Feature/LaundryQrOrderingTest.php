<?php

namespace Modules\Services\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Bookings\Models\Booking;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Modules\Operations\Models\InventoryLocation;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;
use Modules\Services\Models\ServiceOrder;
use Modules\Services\Models\ServiceQrCode;
use Modules\Services\Notifications\NewServiceOrder;
use Modules\Services\Notifications\ServiceOrderPlaced;
use Modules\Services\Notifications\ServiceOrderStatusChanged;
use Modules\Services\Support\PriceList;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LaundryQrOrderingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $vendor;

    private User $host;

    private Property $property;

    private InventoryLocation $kingBedroom;

    private Service $laundry;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Guest', 'Host', 'ServiceProvider', 'Admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->admin = User::factory()->create(['name' => 'Admin']);
        $this->admin->assignRole('Admin');
        $this->vendor = User::factory()->create(['name' => 'CleanCo']);
        $this->vendor->assignRole('ServiceProvider');
        $this->host = User::factory()->create(['name' => 'Host']);
        $this->host->assignRole('Host');

        $this->property = Property::create([
            'host_id' => $this->host->id, 'name' => 'Premium Stay', 'property_type' => 'apartment',
            'address' => '6 Etim Expo', 'city' => 'Calabar', 'country' => 'Nigeria',
            'service_charge_percent' => 5, 'tax_percent' => 7.5,
        ]);
        $listing = Listing::create([
            'property_id' => $this->property->id, 'title' => 'King Suite', 'slug' => 'king-suite',
            'base_price_amount' => 5000000, 'currency' => 'NGN', 'max_guests' => 2, 'bedrooms' => 1, 'bathrooms' => 1, 'status' => 'published',
        ]);
        $this->kingBedroom = InventoryLocation::create([
            'property_id' => $this->property->id, 'listing_id' => $listing->id, 'name' => 'King Suite', 'area' => 'Bedroom', 'type' => 'apartment',
        ]);

        $category = ServiceCategory::create(['name' => 'Laundry', 'slug' => 'laundry', 'fulfillment_type' => 'laundry']);
        $this->laundry = Service::create([
            'provider_id' => $this->vendor->id, 'service_category_id' => $category->id, 'title' => 'Express Laundry',
            'price_amount' => 100000, 'currency' => 'NGN', 'status' => 'published', 'audience' => 'both',
        ]);

        // Shirt: wash 800, wash & iron 1,200. Jeans: wash 1,000, no ironing.
        PriceList::save($this->laundry, ['Wash only', 'Wash & iron'], [
            ['name' => 'Shirt', 'category' => 'Clothing', 'prices' => [0 => 80000, 1 => 120000]],
            ['name' => 'Jeans', 'category' => 'Clothing', 'prices' => [0 => 100000, 1 => null]],
        ]);
    }

    private function option(string $garment, string $treatment): array
    {
        $item = $this->laundry->menuItems()->where('name', $garment)->with('optionGroups.options')->sole();

        return [$item->id, $item->optionGroups->flatMap->options->firstWhere('name', $treatment)->id];
    }

    public function test_price_list_round_trips_and_hides_blank_cells(): void
    {
        $list = PriceList::for($this->laundry);

        $this->assertSame(['Wash only', 'Wash & iron'], $list['treatments']);
        $jeans = $list['garments']->first(fn ($g) => $g['item']->name === 'Jeans');
        $this->assertSame(100000, $jeans['prices']['Wash only']['amount']);
        $this->assertArrayNotHasKey('Wash & iron', $jeans['prices']);
    }

    public function test_price_list_editor_keeps_prices_in_the_right_column_after_a_column_is_removed(): void
    {
        $shirt = $this->laundry->menuItems()->where('name', 'Shirt')->sole();

        // Column 0 was removed in the browser: only keys 1 and 2 arrive.
        $this->actingAs($this->vendor)->put(route('provider.services.price-list.update', $this->laundry), [
            'treatments' => [1 => 'Wash & iron', 2 => 'Wash, iron & starch'],
            'rows' => [
                ['id' => $shirt->id, 'name' => 'Shirt', 'category' => 'Clothing', 'available' => 1, 'prices' => [1 => '1,300', 2 => '1500']],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect(route('provider.services.price-list.edit', $this->laundry));

        $list = PriceList::for($this->laundry->fresh());
        $prices = $list['garments']->first()['prices'];
        $this->assertSame(130000, $prices['Wash & iron']['amount']);
        $this->assertSame(150000, $prices['Wash, iron & starch']['amount']);
        $this->assertFalse($prices['Wash only']['available']);
        $this->assertSame(1, $this->laundry->menuItems()->count(), 'Jeans row was removed');
    }

    public function test_signed_in_laundry_order_sums_items_and_adds_the_services_own_charges(): void
    {
        Notification::fake();
        $this->laundry->update(['tax_percent' => 7.5, 'service_charge_percent' => 0, 'other_charge_label' => 'Levy', 'other_charge_percent' => 2]);
        $guest = User::factory()->create();
        [$shirt, $shirtIron] = $this->option('Shirt', 'Wash & iron');
        [$jeans, $jeansWash] = $this->option('Jeans', 'Wash only');

        $this->actingAs($guest)->post(route('service-orders.store', $this->laundry), [
            'laundry' => [$shirt => [$shirtIron => 3], $jeans => [$jeansWash => 2]],
            'details' => ['pickup_window' => '9-11', 'dropoff_window' => '18-20'],
        ])->assertRedirect();

        $order = ServiceOrder::with('items')->sole();
        // 3 x 1,200 + 2 x 1,000 = 5,600; VAT 7.5% = 420; levy 2% = 112; no service charge.
        $this->assertSame(560000, $order->price_breakdown['subtotal']);
        $this->assertSame(0, $order->price_breakdown['service_charge']);
        $this->assertSame(42000, $order->price_breakdown['tax']);
        $this->assertSame(11200, $order->price_breakdown['other_charge']);
        $this->assertSame(613200, $order->total_amount);
        $this->assertSame(['Shirt — Wash & iron', 'Jeans — Wash only'], $order->items->pluck('name')->all());

        Notification::assertSentTo($guest, ServiceOrderPlaced::class);
        Notification::assertSentTo($this->vendor, NewServiceOrder::class);
    }

    public function test_room_qr_order_needs_no_login_and_uses_the_room_and_current_stay(): void
    {
        Notification::fake();
        $stayer = User::factory()->create();
        $booking = Booking::create([
            'listing_id' => $this->kingBedroom->listing_id, 'guest_id' => $stayer->id,
            'check_in' => today()->subDay(), 'check_out' => today()->addDays(2), 'guests_count' => 1,
            'status' => 'confirmed', 'total_amount' => 10000000, 'currency' => 'NGN',
        ]);
        $code = ServiceQrCode::for($this->laundry, $this->kingBedroom);
        [$shirt, $shirtWash] = $this->option('Shirt', 'Wash only');

        $this->get($code->url())->assertOk()->assertSee('King Suite — Bedroom')->assertSee('Shirt');
        $this->assertSame(1, $code->fresh()->scans);

        $response = $this->post(route('qr.order.store', $code->token), [
            'contact_name' => 'Ada',
            'contact_email' => 'ada@example.test',
            'payment_method' => 'room_bill',
            'laundry' => [$shirt => [$shirtWash => 2]],
            'details' => ['pickup_window' => 'now', 'dropoff_window' => 'tonight'],
        ]);

        $order = ServiceOrder::sole();
        $response->assertRedirect(route('qr.track', $order->public_token));
        $this->assertNull($order->customer_id);
        $this->assertSame('King Suite — Bedroom', $order->location_label);
        $this->assertSame($booking->id, $order->booking_id);
        $this->assertSame('qr_room', $order->source);
        // Property rates apply: 1,600 + 5% + 7.5% = 1,800.
        $this->assertSame(180000, $order->total_amount);

        Notification::assertSentOnDemand(ServiceOrderPlaced::class, fn ($n, $channels, $notifiable) => array_key_exists('ada@example.test', $notifiable->routes['mail']));
        Notification::assertSentTo([$this->vendor, $this->host], NewServiceOrder::class);

        $this->get(route('qr.track', $order->public_token))->assertOk()->assertSee('Shirt — Wash only')->assertSee('Received');
        $this->getJson(route('qr.track.status', $order->public_token))->assertJson(['status' => 'pending']);

        // The vendor moving it along emails the guest, who has no account.
        $this->actingAs($this->vendor)->patch(route('provider.service-orders.status', $order), ['status' => 'confirmed'])->assertRedirect();
        Notification::assertSentOnDemand(ServiceOrderStatusChanged::class);
    }

    public function test_generic_qr_requires_phone_and_location_and_vendor_board_shows_guest_details(): void
    {
        Notification::fake();
        $code = ServiceQrCode::for($this->laundry);
        [$shirt, $shirtWash] = $this->option('Shirt', 'Wash only');
        $payload = [
            'contact_name' => 'Walk-in Guest',
            'payment_method' => 'on_delivery',
            'laundry' => [$shirt => [$shirtWash => 1]],
            'details' => ['pickup_window' => 'now', 'dropoff_window' => 'later'],
        ];

        $this->post(route('qr.order.store', $code->token), $payload)
            ->assertSessionHasErrors(['contact_phone', 'details.delivery_address']);

        // Room billing isn't offered on a generic code.
        $this->post(route('qr.order.store', $code->token), array_merge($payload, ['contact_phone' => '0803 000 0000', 'payment_method' => 'room_bill']))
            ->assertSessionHasErrors('payment_method');

        $payload['contact_phone'] = '0803 000 0000';
        $payload['details']['delivery_address'] = 'Poolside table 4';
        $this->post(route('qr.order.store', $code->token), $payload)->assertRedirect();

        $order = ServiceOrder::sole();
        $this->assertSame('Poolside table 4', $order->deliverTo());
        $this->assertSame(80000, $order->total_amount, 'No property context, no service rates: no charges');

        $this->actingAs($this->vendor)->get(route('provider.services.orders'))
            ->assertOk()->assertSee('Walk-in Guest')->assertSee('0803 000 0000')->assertSee('Poolside table 4')->assertSee('New');
        $this->assertNotNull($order->fresh()->seen_at);
    }

    public function test_switched_off_codes_and_guests_only_services_are_protected(): void
    {
        $code = ServiceQrCode::for($this->laundry);
        $code->update(['is_active' => false]);
        $this->get($code->url())->assertNotFound();

        $this->laundry->update(['audience' => 'guests_only']);
        $generic = ServiceQrCode::for($this->laundry);
        $generic->update(['is_active' => true]);
        $this->get($generic->url())->assertOk()->assertSee('Sign in');
        $this->post(route('qr.order.store', $generic->token), ['contact_name' => 'X'])->assertForbidden();

        // A room code is proof enough of a stay.
        $this->get(ServiceQrCode::for($this->laundry, $this->kingBedroom)->url())->assertOk()->assertSee('Place order');
    }

    public function test_live_feed_reports_new_orders_only_to_people_who_handle_them(): void
    {
        $otherVendor = User::factory()->create();
        $otherVendor->assignRole('ServiceProvider');
        $first = ServiceOrder::create(['service_id' => $this->laundry->id, 'contact_name' => 'A', 'status' => 'pending', 'total_amount' => 100, 'currency' => 'NGN']);

        $this->actingAs($this->vendor)->getJson(route('provider.services.orders.feed'))
            ->assertOk()->assertJson(['latest_id' => $first->id, 'unseen' => 1, 'orders' => []]);

        $second = ServiceOrder::create(['service_id' => $this->laundry->id, 'contact_name' => 'B', 'status' => 'pending', 'total_amount' => 100, 'currency' => 'NGN']);
        $this->actingAs($this->vendor)->getJson(route('provider.services.orders.feed', ['after' => $first->id]))
            ->assertOk()->assertJsonPath('orders.0.id', $second->id)->assertJsonPath('orders.0.who', 'B');
        $this->actingAs($this->admin)->getJson(route('provider.services.orders.feed', ['after' => $first->id]))
            ->assertJsonPath('orders.0.id', $second->id);
        $this->actingAs($otherVendor)->getJson(route('provider.services.orders.feed', ['after' => 0]))
            ->assertJson(['latest_id' => 0, 'unseen' => 0]);

        $this->actingAs($this->vendor)->patchJson(route('provider.services.orders.seen'))->assertOk();
        $this->actingAs($this->vendor)->getJson(route('provider.services.orders.feed'))->assertJson(['unseen' => 0]);
    }

    public function test_qr_management_pages(): void
    {
        $this->actingAs($this->vendor)->get(route('provider.services.qr', $this->laundry))
            ->assertOk()->assertSee('General code')->assertSee('Bedroom')->assertSee('<svg', false);

        $this->actingAs($this->vendor)->get(route('provider.services.qr.print', [$this->laundry, 'generic' => 1, 'locations' => [$this->kingBedroom->id], 'copies' => 2]))
            ->assertOk()->assertSee('4 cards')->assertSee('King Suite — Bedroom');

        $code = ServiceQrCode::for($this->laundry, $this->kingBedroom);
        $this->actingAs($this->vendor)->get(route('provider.services.qr.download', [$this->laundry, $code]))
            ->assertOk()->assertHeader('Content-Type', 'image/svg+xml');

        $this->actingAs(User::factory()->create())->get(route('provider.services.qr', $this->laundry))->assertForbidden();
        $this->actingAs($this->vendor)->get(route('provider.services.price-list.edit', $this->laundry))->assertOk()->assertSee('Shirt');
    }
}
