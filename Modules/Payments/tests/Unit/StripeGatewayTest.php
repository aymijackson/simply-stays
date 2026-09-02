<?php

namespace Modules\Payments\Tests\Unit;

use Illuminate\Http\Request;
use Modules\Payments\Gateways\StripeGateway;
use Stripe\StripeClient;
use Tests\TestCase;

class StripeGatewayTest extends TestCase
{
    public function test_name_is_stripe(): void
    {
        $gateway = new StripeGateway(new StripeClient('sk_test_fake'), 'whsec_fake');

        $this->assertSame('stripe', $gateway->name());
    }

    public function test_webhook_signature_is_rejected_when_invalid_or_missing(): void
    {
        $gateway = new StripeGateway(new StripeClient('sk_test_fake'), 'whsec_fake');

        $request = Request::create('/webhook', 'POST', content: json_encode(['type' => 'checkout.session.completed']));
        $request->headers->set('Stripe-Signature', 'invalid');

        $this->assertFalse($gateway->verifyWebhookSignature($request));
    }

    public function test_webhook_signature_check_fails_closed_without_configured_secret(): void
    {
        $gateway = new StripeGateway(new StripeClient('sk_test_fake'), null);

        $request = Request::create('/webhook', 'POST');

        $this->assertFalse($gateway->verifyWebhookSignature($request));
    }

    public function test_reference_from_webhook_reads_session_id(): void
    {
        $gateway = new StripeGateway(new StripeClient('sk_test_fake'), 'whsec_fake');

        $request = Request::create('/webhook', 'POST', content: json_encode([
            'data' => ['object' => ['id' => 'cs_test_123']],
        ]));
        $request->headers->set('Content-Type', 'application/json');

        $this->assertSame('cs_test_123', $gateway->referenceFromWebhook($request));
    }
}
