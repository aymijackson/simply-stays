<?php

namespace Modules\Payments\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Payments\Models\Wallet;
use Modules\Payments\Services\RefundProcessor;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;
use Modules\Services\Models\ServiceOrder;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceCommissionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_paying_a_commissioned_service_order_splits_credit_between_provider_and_platform(): void
    {
        foreach (['Guest', 'ServiceProvider'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        config([
            'payments.paystack.secret' => 'sk_test_fake',
            'payments.platform_account_email' => 'platform@shortstay.internal',
        ]);

        $provider = User::factory()->create();
        $provider->assignRole('ServiceProvider');

        $guest = User::factory()->create();
        $guest->assignRole('Guest');

        $category = ServiceCategory::create(['name' => 'Laundry', 'slug' => 'laundry', 'fulfillment_type' => 'laundry']);

        $service = Service::create([
            'provider_id' => $provider->id,
            'service_category_id' => $category->id,
            'title' => 'Commissioned Laundry',
            'price_amount' => 500000,
            'currency' => 'NGN',
            'status' => 'published',
            'commission_type' => 'percentage',
            'commission_value' => 10,
        ]);

        $order = ServiceOrder::create([
            'service_id' => $service->id,
            'customer_id' => $guest->id,
            'status' => 'confirmed',
            'total_amount' => 500000,
            'currency' => 'NGN',
        ]);

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/x', 'reference' => 'x'],
            ], 200),
        ]);

        $this->actingAs($guest)->post(route('checkout.initiate.service', $order), ['gateway' => 'paystack']);

        $transaction = $order->transactions()->first();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['reference' => $transaction->gateway_reference, 'status' => 'success', 'amount' => 500000, 'currency' => 'NGN'],
            ], 200),
        ]);

        $this->actingAs($guest)
            ->get(route('payments.callback', ['gateway' => 'paystack']).'?reference='.$transaction->reference);

        $providerWallet = Wallet::where('holder_type', User::class)->where('holder_id', $provider->id)->first();
        $platformUser = User::where('email', 'platform@shortstay.internal')->first();
        $platformWallet = Wallet::where('holder_type', User::class)->where('holder_id', $platformUser->id)->first();

        $this->assertNotNull($providerWallet);
        $this->assertNotNull($platformWallet);
        $this->assertSame(450000, $providerWallet->balance);
        $this->assertSame(50000, $platformWallet->balance);

        // Refunding should reverse the split identically.
        $refunds = app(RefundProcessor::class);
        Http::fake(['api.paystack.co/refund' => Http::response(['status' => true], 200)]);

        $charge = $order->transactions()->where('type', 'charge')->first();
        $refunds->refund($charge);

        $this->assertSame(0, $providerWallet->fresh()->balance);
        $this->assertSame(0, $platformWallet->fresh()->balance);
    }
}
