<?php

namespace Modules\Services\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Services\Models\MenuItemOptionGroup;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;
use Modules\Services\Models\ServiceOrder;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MenuItemOptionOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $guest;

    private Service $kitchen;

    private MenuItemOptionGroup $proteinGroup;

    private $chicken;

    private $goat;

    private MenuItemOptionGroup $extrasGroup;

    private $extraEgg;

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
        ]);

        $jollof = $this->kitchen->menuItems()->create([
            'name' => 'Jollof Combo', 'price_amount' => 450000, 'currency' => 'NGN', 'is_available' => true,
        ]);

        $this->proteinGroup = $jollof->optionGroups()->create([
            'name' => 'Choose your protein', 'selection_type' => 'single', 'is_required' => true, 'min_select' => 1,
        ]);
        $this->chicken = $this->proteinGroup->options()->create(['name' => 'Chicken', 'price_delta_amount' => 0, 'is_default' => true]);
        $this->goat = $this->proteinGroup->options()->create(['name' => 'Goat meat', 'price_delta_amount' => 100000]);

        $this->extrasGroup = $jollof->optionGroups()->create(['name' => 'Add extras', 'selection_type' => 'multiple']);
        $this->extraEgg = $this->extrasGroup->options()->create(['name' => 'Extra egg', 'price_delta_amount' => 30000, 'max_quantity' => 4]);

        $this->jollof = $jollof;
    }

    public function test_omitting_a_required_single_group_is_rejected(): void
    {
        $response = $this->actingAs($this->guest)->post(route('service-orders.store', $this->kitchen), [
            'details' => ['delivery_address' => 'Unit 4'],
            'items' => [
                $this->jollof->id => ['quantity' => 1],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, ServiceOrder::count());
    }

    public function test_a_variant_choice_and_an_addon_compute_the_correct_total(): void
    {
        $response = $this->actingAs($this->guest)->post(route('service-orders.store', $this->kitchen), [
            'details' => ['delivery_address' => 'Unit 4'],
            'items' => [
                $this->jollof->id => [
                    'quantity' => 2,
                    'options' => [
                        $this->proteinGroup->id => $this->goat->id,
                        $this->extrasGroup->id => [$this->extraEgg->id => 2],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect();

        $order = ServiceOrder::first();
        $this->assertNotNull($order);

        // Base: 2 x 450000 = 900000. Goat delta: 2 x 100000 = 200000. Extra egg: 2 units x 2 qty x 30000 = 120000.
        $this->assertSame(1220000, $order->total_amount);

        $item = $order->items->first();
        $this->assertSame(2, $item->options()->count());
    }

    public function test_addon_quantity_over_the_options_own_max_is_rejected(): void
    {
        $response = $this->actingAs($this->guest)->post(route('service-orders.store', $this->kitchen), [
            'details' => ['delivery_address' => 'Unit 4'],
            'items' => [
                $this->jollof->id => [
                    'quantity' => 1,
                    'options' => [
                        $this->proteinGroup->id => $this->chicken->id,
                        $this->extrasGroup->id => [$this->extraEgg->id => 5],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, ServiceOrder::count());
    }

    public function test_order_item_options_survive_the_underlying_option_being_deleted(): void
    {
        $this->actingAs($this->guest)->post(route('service-orders.store', $this->kitchen), [
            'details' => ['delivery_address' => 'Unit 4'],
            'items' => [
                $this->jollof->id => [
                    'quantity' => 1,
                    'options' => [
                        $this->proteinGroup->id => $this->goat->id,
                    ],
                ],
            ],
        ]);

        $this->goat->delete();

        $orderItemOption = ServiceOrder::first()->items->first()->options->first();
        $this->assertSame('Goat meat', $orderItemOption->fresh()->name);
        $this->assertNull($orderItemOption->fresh()->menu_item_option_id);
    }
}
