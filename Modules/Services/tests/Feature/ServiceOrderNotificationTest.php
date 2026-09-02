<?php

namespace Modules\Services\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;
use Modules\Services\Models\ServiceOrder;
use Modules\Services\Notifications\ServiceOrderStatusChanged;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceOrderNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_order_status_notifies_the_customer(): void
    {
        foreach (['Guest', 'ServiceProvider'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $provider = User::factory()->create();
        $provider->assignRole('ServiceProvider');

        $customer = User::factory()->create();
        $customer->assignRole('Guest');

        $category = ServiceCategory::create(['name' => 'Laundry', 'slug' => 'laundry', 'fulfillment_type' => 'laundry']);
        $service = Service::create([
            'provider_id' => $provider->id,
            'service_category_id' => $category->id,
            'title' => 'Laundry',
            'price_amount' => 200000,
            'currency' => 'NGN',
            'status' => 'published',
        ]);

        $order = ServiceOrder::create([
            'service_id' => $service->id,
            'customer_id' => $customer->id,
            'status' => 'pending',
            'fulfillment_details' => [],
            'total_amount' => 200000,
            'currency' => 'NGN',
        ]);

        Notification::fake();

        $this->actingAs($provider)->patch(route('provider.service-orders.status', $order), ['status' => 'confirmed']);

        Notification::assertSentTo($customer, ServiceOrderStatusChanged::class);
    }
}
