<?php

namespace Modules\Payments\Gateways;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Manager;
use Modules\Payments\Contracts\PaymentGatewayInterface;
use Stripe\StripeClient;

class PaymentGatewayManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('payments.default_gateway', 'stripe');
    }

    public function driver($driver = null): PaymentGatewayInterface
    {
        return parent::driver($driver);
    }

    protected function createStripeDriver(): StripeGateway
    {
        return new StripeGateway(
            new StripeClient((string) $this->config->get('payments.stripe.secret')),
            $this->config->get('payments.stripe.webhook_secret'),
        );
    }

    protected function createPaystackDriver(): PaystackGateway
    {
        return new PaystackGateway(
            $this->container->make(HttpFactory::class),
            (string) $this->config->get('payments.paystack.secret'),
            (string) $this->config->get('payments.paystack.base_url'),
        );
    }

    protected function createFlutterwaveDriver(): FlutterwaveGateway
    {
        return new FlutterwaveGateway(
            $this->container->make(HttpFactory::class),
            (string) $this->config->get('payments.flutterwave.secret'),
            (string) $this->config->get('payments.flutterwave.base_url'),
            $this->config->get('payments.flutterwave.webhook_hash'),
        );
    }
}
