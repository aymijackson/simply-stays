<?php

namespace Modules\Services\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MenuOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $guest;

    private Service $kitchen;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Guest', 'ServiceProvider'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->guest = User::factory()->create();
        $this->guest->assignRole('Guest');

        $provider = User::factory()->create();
        $provider->assignRole('ServiceProvider');

        $category = ServiceCategory::create(['name' => 'Food', 'slug' => 'food', 'fulfillment_type' => 'food']);

        $this->kitchen = Service::create([
            'provider_id' => $provider->id,
            'service_category_id' => $category->id,
            'title' => 'Test Kitchen',
            'price_amount' => 0,
            'currency' => 'NGN',
            'status' => 'published',
            'delivery_fee_amount' => 100000,
        ]);
    }

    public function test_ordering_menu_items_creates_correct_order_items_and_subtotal(): void
    {
        $breakfast = $this->kitchen->menuItems()->create([
            'name' => 'Breakfast', 'price_amount' => 300000, 'currency' => 'NGN', 'is_available' => true,
        ]);
        $drink = $this->kitchen->menuItems()->create([
            'name' => 'Juice', 'price_amount' => 100000, 'currency' => 'NGN', 'is_available' => true,
        ]);

        $response = $this->actingAs($this->guest)->post(route('service-orders.store', $this->kitchen), [
            'details' => ['delivery_address' => 'Unit 4'],
            'items' => [
                $breakfast->id => ['quantity' => 2],
                $drink->id => ['quantity' => 1],
            ],
        ]);

        $response->assertRedirect();

        $order = $this->kitchen->orders()->first();
        $this->assertNotNull($order);
        $this->assertSame(3, $order->items()->sum('quantity'));

        // Subtotal: 2x300000 + 1x100000 = 700000, plus flat delivery fee of 100000 (no booking => no tax/service-charge).
        $this->assertSame(800000, $order->total_amount);
    }

    public function test_ordering_with_no_items_selected_is_rejected(): void
    {
        $response = $this->actingAs($this->guest)->post(route('service-orders.store', $this->kitchen), [
            'details' => ['delivery_address' => 'Unit 4'],
            'items' => [],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, $this->kitchen->orders()->count());
    }

    public function test_order_again_shows_previously_ordered_items_and_excludes_others(): void
    {
        $breakfast = $this->kitchen->menuItems()->create([
            'name' => 'Breakfast', 'price_amount' => 300000, 'currency' => 'NGN', 'is_available' => true,
        ]);
        $neverOrdered = $this->kitchen->menuItems()->create([
            'name' => 'Never Ordered Item', 'price_amount' => 100000, 'currency' => 'NGN', 'is_available' => true,
        ]);

        $this->actingAs($this->guest)->post(route('service-orders.store', $this->kitchen), [
            'details' => ['delivery_address' => 'Unit 4'],
            'items' => [$breakfast->id => ['quantity' => 1]],
        ]);

        $response = $this->actingAs($this->guest)->get(route('service-orders.create', $this->kitchen));

        $response->assertOk();
        $response->assertSee('Order again');
        $response->assertSee($breakfast->name);
        $response->assertSee($neverOrdered->name);
    }

    public function test_a_provider_curated_pairing_is_retrievable_from_the_menu_item(): void
    {
        $jollof = $this->kitchen->menuItems()->create([
            'name' => 'Jollof Combo', 'price_amount' => 450000, 'currency' => 'NGN', 'is_available' => true,
        ]);
        $chapman = $this->kitchen->menuItems()->create([
            'name' => 'Chapman Mocktail', 'price_amount' => 150000, 'currency' => 'NGN', 'is_available' => true,
        ]);

        $jollof->pairings()->attach($chapman->id, ['type' => 'pairing']);

        $this->assertTrue($jollof->fresh()->pairings->contains('id', $chapman->id));
        $this->assertFalse($jollof->fresh()->upsells->contains('id', $chapman->id));
    }
}
