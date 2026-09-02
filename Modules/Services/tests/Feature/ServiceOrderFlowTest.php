<?php

namespace Modules\Services\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Payments\Models\Wallet;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;
use Modules\Services\Models\ServiceOrder;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $provider;

    private User $customer;

    private ServiceCategory $laundryCategory;

    private Service $laundryService;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('ServiceProvider', 'web');
        Role::findOrCreate('Guest', 'web');

        $this->provider = User::factory()->create();
        $this->provider->assignRole('ServiceProvider');

        $this->customer = User::factory()->create();
        $this->customer->assignRole('Guest');

        $this->laundryCategory = ServiceCategory::create([
            'name' => 'Laundry',
            'slug' => 'laundry',
            'fulfillment_type' => 'laundry',
        ]);

        $this->laundryService = Service::create([
            'provider_id' => $this->provider->id,
            'service_category_id' => $this->laundryCategory->id,
            'title' => 'Test Laundry',
            'price_amount' => 500000,
            'currency' => 'NGN',
            'status' => 'published',
        ]);
    }

    public function test_order_form_rejects_missing_required_fulfillment_fields(): void
    {
        $response = $this->actingAs($this->customer)
            ->post(route('service-orders.store', $this->laundryService), [
                'details' => ['instructions' => 'Fold neatly please'],
            ]);

        $response->assertSessionHasErrors(['details.pickup_window', 'details.dropoff_window']);
        $this->assertSame(0, ServiceOrder::count());
    }

    public function test_order_form_accepts_valid_fulfillment_details_and_stores_them(): void
    {
        $response = $this->actingAs($this->customer)
            ->post(route('service-orders.store', $this->laundryService), [
                'details' => [
                    'pickup_window' => '09:00-11:00',
                    'dropoff_window' => '18:00-20:00',
                ],
            ]);

        $order = ServiceOrder::first();

        $response->assertRedirect(route('service-orders.show', $order));
        $this->assertNotNull($order);
        $this->assertSame('pending', $order->status);
        $this->assertSame(500000, $order->total_amount);
        $this->assertSame('09:00-11:00', $order->fulfillment_details['pickup_window']);
    }

    public function test_provider_can_confirm_then_start_then_complete_an_order_in_sequence(): void
    {
        $order = ServiceOrder::create([
            'service_id' => $this->laundryService->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'fulfillment_details' => ['pickup_window' => '09:00-11:00', 'dropoff_window' => '18:00-20:00'],
            'total_amount' => 500000,
            'currency' => 'NGN',
        ]);

        $this->actingAs($this->provider)
            ->patch(route('provider.service-orders.status', $order), ['status' => 'confirmed'])
            ->assertRedirect();
        $this->assertSame('confirmed', $order->fresh()->status);

        $this->actingAs($this->provider)
            ->patch(route('provider.service-orders.status', $order), ['status' => 'in_progress'])
            ->assertRedirect();
        $this->assertSame('in_progress', $order->fresh()->status);

        $this->actingAs($this->provider)
            ->patch(route('provider.service-orders.status', $order), ['status' => 'completed'])
            ->assertRedirect();
        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_provider_cannot_skip_from_pending_directly_to_completed(): void
    {
        $order = ServiceOrder::create([
            'service_id' => $this->laundryService->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'fulfillment_details' => [],
            'total_amount' => 500000,
            'currency' => 'NGN',
        ]);

        $this->actingAs($this->provider)
            ->patch(route('provider.service-orders.status', $order), ['status' => 'completed'])
            ->assertStatus(422);

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_a_different_provider_cannot_update_someone_elses_order_status(): void
    {
        $otherProvider = User::factory()->create();
        $otherProvider->assignRole('ServiceProvider');

        $order = ServiceOrder::create([
            'service_id' => $this->laundryService->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'fulfillment_details' => [],
            'total_amount' => 500000,
            'currency' => 'NGN',
        ]);

        $this->actingAs($otherProvider)
            ->patch(route('provider.service-orders.status', $order), ['status' => 'confirmed'])
            ->assertStatus(403);
    }

    public function test_paying_for_a_service_order_credits_the_providers_wallet_not_a_host(): void
    {
        config(['payments.paystack.secret' => 'sk_test_fake']);

        $order = ServiceOrder::create([
            'service_id' => $this->laundryService->id,
            'customer_id' => $this->customer->id,
            'status' => 'confirmed',
            'fulfillment_details' => ['pickup_window' => '09:00-11:00', 'dropoff_window' => '18:00-20:00'],
            'total_amount' => 500000,
            'currency' => 'NGN',
        ]);

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/svc123', 'reference' => 'svc-ref'],
            ], 200),
        ]);

        $this->actingAs($this->customer)
            ->post(route('checkout.initiate.service', $order), ['gateway' => 'paystack'])
            ->assertRedirect('https://checkout.paystack.com/svc123');

        $transaction = $order->transactions()->first();
        $this->assertNotNull($transaction);
        $this->assertSame($order->id, $transaction->service_order_id);
        $this->assertNull($transaction->booking_id);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['reference' => $transaction->gateway_reference, 'status' => 'success', 'amount' => 500000, 'currency' => 'NGN'],
            ], 200),
        ]);

        $this->actingAs($this->customer)
            ->get(route('payments.callback', ['gateway' => 'paystack']).'?reference='.$transaction->reference)
            ->assertRedirect(route('service-orders.show', $order));

        $this->assertTrue($order->fresh()->isPaid());

        $wallet = Wallet::where('holder_type', User::class)->where('holder_id', $this->provider->id)->first();
        $this->assertNotNull($wallet, 'Provider wallet should have been created and credited.');
        $this->assertSame(500000, $wallet->balance);
    }
}
