<?php

namespace Modules\Payments\Tests\Unit;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\Payments\DataTransferObjects\PaymentInitiationData;
use Modules\Payments\Gateways\FlutterwaveGateway;
use Tests\TestCase;

class FlutterwaveGatewayTest extends TestCase
{
    public function test_initialize_sends_amount_in_major_units_not_minor_units(): void
    {
        Http::fake([
            'api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'data' => ['link' => 'https://checkout.flutterwave.com/pay/xyz'],
            ], 200),
        ]);

        $gateway = new FlutterwaveGateway(app(HttpFactory::class), 'FLWSECK_TEST-fake', 'https://api.flutterwave.com/v3', null);

        $result = $gateway->initialize(new PaymentInitiationData(
            reference: 'txref-1',
            amountMinorUnits: 150000, // 1,500.00 in major units
            currency: 'NGN',
            description: 'Test booking',
            callbackUrl: 'https://example.test/callback',
            customerEmail: 'guest@example.test',
        ));

        $this->assertSame('https://checkout.flutterwave.com/pay/xyz', $result->redirectUrl);
        $this->assertSame('txref-1', $result->gatewayReference);

        Http::assertSent(function ($request) {
            // Flutterwave expects the major-unit decimal amount (1500.0), not the 150000 minor-unit value.
            return $request->url() === 'https://api.flutterwave.com/v3/payments'
                && $request['amount'] === 1500.0
                && $request['tx_ref'] === 'txref-1';
        });
    }

    public function test_verify_transaction_normalizes_major_units_back_to_minor_units(): void
    {
        Http::fake([
            'api.flutterwave.com/v3/transactions/verify_by_reference*' => Http::response([
                'status' => 'success',
                'data' => [
                    'tx_ref' => 'txref-2',
                    'status' => 'successful',
                    'amount' => 1500,
                    'currency' => 'NGN',
                ],
            ], 200),
        ]);

        $gateway = new FlutterwaveGateway(app(HttpFactory::class), 'FLWSECK_TEST-fake', 'https://api.flutterwave.com/v3', null);

        $verified = $gateway->verifyTransaction('txref-2');

        $this->assertTrue($verified->succeeded());
        $this->assertSame(150000, $verified->amountMinorUnits);
        $this->assertSame('NGN', $verified->currency);
    }

    public function test_refund_looks_up_the_numeric_transaction_id_then_refunds_in_major_units(): void
    {
        Http::fake([
            'api.flutterwave.com/v3/transactions/verify_by_reference*' => Http::response([
                'status' => 'success',
                'data' => ['id' => 998877, 'tx_ref' => 'txref-3', 'status' => 'successful', 'amount' => 1500, 'currency' => 'NGN'],
            ], 200),
            'api.flutterwave.com/v3/transactions/998877/refund' => Http::response(['status' => 'success'], 200),
        ]);

        $gateway = new FlutterwaveGateway(app(HttpFactory::class), 'FLWSECK_TEST-fake', 'https://api.flutterwave.com/v3', null);

        $this->assertTrue($gateway->refund('txref-3', 150000));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.flutterwave.com/v3/transactions/998877/refund'
                && $request['amount'] === 1500.0;
        });
    }

    public function test_refund_returns_false_when_the_transaction_cannot_be_found(): void
    {
        Http::fake([
            'api.flutterwave.com/v3/transactions/verify_by_reference*' => Http::response(['status' => 'error', 'data' => null], 200),
        ]);

        $gateway = new FlutterwaveGateway(app(HttpFactory::class), 'FLWSECK_TEST-fake', 'https://api.flutterwave.com/v3', null);

        $this->assertFalse($gateway->refund('unknown-ref'));
    }

    public function test_webhook_signature_is_a_static_hash_comparison(): void
    {
        $gateway = new FlutterwaveGateway(app(HttpFactory::class), 'secret', 'https://api.flutterwave.com/v3', 'my-configured-hash');

        $valid = Request::create('/webhook', 'POST', server: ['HTTP_verif-hash' => 'my-configured-hash']);
        $invalid = Request::create('/webhook', 'POST', server: ['HTTP_verif-hash' => 'wrong']);

        $this->assertTrue($gateway->verifyWebhookSignature($valid));
        $this->assertFalse($gateway->verifyWebhookSignature($invalid));
    }
}
