<?php

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminVendorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ServiceCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Guest', 'Host', 'ServiceProvider', 'Admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->admin = User::factory()->create(['name' => 'Platform Admin']);
        $this->admin->assignRole('Admin');
        $this->category = ServiceCategory::create(['name' => 'Food', 'slug' => 'food', 'fulfillment_type' => 'food']);
    }

    private function servicePayload(array $extra = []): array
    {
        return $extra + [
            'service_category_id' => $this->category->id,
            'title' => 'Jollof Platter',
            'price' => 4500,
            'currency' => 'NGN',
            'audience' => 'both',
        ];
    }

    private function vendor(string $name = 'Sunrise Kitchen'): User
    {
        $vendor = User::factory()->create(['name' => $name]);
        $vendor->assignRole('ServiceProvider');

        return $vendor;
    }

    public function test_admin_registers_a_vendor_who_can_then_log_in(): void
    {
        $this->actingAs($this->admin)->post(route('admin.vendors.store'), [
            'name' => 'Sunrise Kitchen',
            'email' => 'Kitchen@Example.test',
            'phone' => '08030000000',
            'password' => 'temp-pass-123',
            'password_confirmation' => 'temp-pass-123',
        ])->assertRedirect(route('admin.vendors.index'));

        $vendor = User::where('email', 'kitchen@example.test')->sole();
        $this->assertTrue($vendor->hasRole('ServiceProvider'));
        $this->assertTrue(Hash::check('temp-pass-123', $vendor->password));

        $this->actingAs($this->admin)->get(route('admin.vendors.index'))->assertOk()->assertSee('Sunrise Kitchen');

        auth()->logout();
        $this->post(route('login'), ['email' => 'kitchen@example.test', 'password' => 'temp-pass-123'])->assertRedirect();
        $this->assertAuthenticatedAs($vendor);
        $this->get(route('provider.services.index'))->assertOk()->assertSee('My services');
    }

    public function test_only_admins_can_manage_vendors(): void
    {
        $this->actingAs($this->vendor())->get(route('admin.vendors.index'))->assertForbidden();
        $this->actingAs($this->vendor('Other'))->post(route('admin.vendors.store'), [])->assertForbidden();
    }

    public function test_admin_edits_vendor_and_resets_password(): void
    {
        $vendor = $this->vendor();

        $this->actingAs($this->admin)->put(route('admin.vendors.update', $vendor), [
            'name' => 'Sunrise Kitchen Ltd',
            'email' => $vendor->email,
            'password' => 'new-pass-4567',
            'password_confirmation' => 'new-pass-4567',
        ])->assertSessionHasNoErrors();

        $vendor->refresh();
        $this->assertSame('Sunrise Kitchen Ltd', $vendor->name);
        $this->assertTrue(Hash::check('new-pass-4567', $vendor->password));

        // Non-vendors can't be edited through this screen.
        $guest = User::factory()->create();
        $guest->assignRole('Guest');
        $this->actingAs($this->admin)->get(route('admin.vendors.edit', $guest))->assertNotFound();
    }

    public function test_admin_creates_a_service_for_a_vendor_who_then_manages_it(): void
    {
        $vendor = $this->vendor();

        $this->actingAs($this->admin)->get(route('provider.services.create', ['provider' => $vendor->id]))
            ->assertOk()->assertSee('Sunrise Kitchen');

        $this->actingAs($this->admin)->post(route('provider.services.store'), $this->servicePayload(['provider_id' => $vendor->id]))
            ->assertSessionHasNoErrors();

        $service = Service::sole();
        $this->assertSame($vendor->id, $service->provider_id);

        $this->actingAs($vendor)->get(route('provider.services.index'))->assertOk()->assertSee('Jollof Platter');
        $this->actingAs($vendor)->get(route('provider.services.edit', $service))->assertOk()->assertDontSee('name="provider_id"', false);
    }

    public function test_admin_can_reassign_a_service_but_only_to_a_vendor(): void
    {
        $vendor = $this->vendor();
        $service = Service::create([
            'provider_id' => $this->admin->id, 'service_category_id' => $this->category->id,
            'title' => 'Laundry', 'price_amount' => 500000, 'currency' => 'NGN', 'status' => 'published',
        ]);

        $guest = User::factory()->create();
        $guest->assignRole('Guest');
        $this->actingAs($this->admin)->put(route('provider.services.update', $service), $this->servicePayload(['provider_id' => $guest->id]))
            ->assertSessionHasErrors('provider_id');

        $this->actingAs($this->admin)->put(route('provider.services.update', $service), $this->servicePayload(['provider_id' => $vendor->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($vendor->id, $service->fresh()->provider_id);
    }

    public function test_vendors_cannot_reassign_services(): void
    {
        $vendor = $this->vendor();
        $other = $this->vendor('Other Vendor');
        $service = Service::create([
            'provider_id' => $vendor->id, 'service_category_id' => $this->category->id,
            'title' => 'Laundry', 'price_amount' => 500000, 'currency' => 'NGN', 'status' => 'draft',
        ]);

        $this->actingAs($vendor)->put(route('provider.services.update', $service), $this->servicePayload(['provider_id' => $other->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($vendor->id, $service->fresh()->provider_id);
    }
}
