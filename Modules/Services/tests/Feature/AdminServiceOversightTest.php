<?php

namespace Modules\Services\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;
use Modules\Services\Models\ServiceOrder;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminServiceOversightTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $provider;

    private User $otherProvider;

    private Service $service;

    private Service $otherService;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ServiceProvider', 'Admin', 'Guest'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->provider = User::factory()->create(['name' => 'CleanCo']);
        $this->provider->assignRole('ServiceProvider');
        $this->otherProvider = User::factory()->create(['name' => 'Sunrise Kitchen']);
        $this->otherProvider->assignRole('ServiceProvider');

        $category = ServiceCategory::create(['name' => 'Laundry', 'slug' => 'laundry', 'fulfillment_type' => 'laundry']);

        $this->service = Service::create([
            'provider_id' => $this->provider->id, 'service_category_id' => $category->id,
            'title' => 'Same-day Laundry', 'price_amount' => 500000, 'currency' => 'NGN', 'status' => 'published',
        ]);
        $this->otherService = Service::create([
            'provider_id' => $this->otherProvider->id, 'service_category_id' => $category->id,
            'title' => 'Ironing Only', 'price_amount' => 200000, 'currency' => 'NGN', 'status' => 'draft',
        ]);
    }

    public function test_admin_sees_every_providers_services_and_can_filter(): void
    {
        $this->actingAs($this->admin)->get(route('provider.services.index'))
            ->assertOk()->assertSee('All services')->assertSee('Same-day Laundry')->assertSee('Ironing Only')->assertSee('CleanCo');

        $this->actingAs($this->admin)->get(route('provider.services.index', ['provider' => $this->otherProvider]))
            ->assertOk()->assertSee('Ironing Only')->assertDontSee('Same-day Laundry');

        $this->actingAs($this->admin)->get(route('provider.services.index', ['status' => 'published']))
            ->assertOk()->assertSee('Same-day Laundry')->assertDontSee('Ironing Only');
    }

    public function test_providers_still_only_see_their_own(): void
    {
        $this->actingAs($this->provider)->get(route('provider.services.index'))
            ->assertOk()->assertSee('My services')->assertSee('Same-day Laundry')->assertDontSee('Ironing Only');
    }

    public function test_admin_editing_a_service_keeps_its_provider(): void
    {
        $this->actingAs($this->admin)->put(route('provider.services.update', $this->service), [
            'service_category_id' => $this->service->service_category_id,
            'title' => 'Same-day Laundry (updated)',
            'price' => 6000,
            'currency' => 'NGN',
            'audience' => 'both',
        ])->assertSessionHasNoErrors();

        $this->service->refresh();
        $this->assertSame('Same-day Laundry (updated)', $this->service->title);
        $this->assertSame($this->provider->id, $this->service->provider_id);
    }

    public function test_admin_sees_all_orders(): void
    {
        $customer = User::factory()->create(['name' => 'Ada Guest']);
        ServiceOrder::create([
            'service_id' => $this->otherService->id, 'customer_id' => $customer->id,
            'status' => 'pending', 'total_amount' => 200000, 'currency' => 'NGN',
        ]);

        $this->actingAs($this->admin)->get(route('provider.services.orders'))
            ->assertOk()->assertSee('Ironing Only')->assertSee('Sunrise Kitchen');

        $this->actingAs($this->provider)->get(route('provider.services.orders'))
            ->assertOk()->assertDontSee('Ironing Only');
    }
}
