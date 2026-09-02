<?php

namespace Modules\Payments\Gateways;

use Illuminate\Http\Request;
use Modules\Payments\Contracts\PaymentGatewayInterface;
use Modules\Payments\DataTransferObjects\PaymentInitiationData;
use Modules\Payments\DataTransferObjects\PaymentInitiationResult;
use Modules\Payments\DataTransferObjects\VerifiedTransaction;
use Modules\Payments\Exceptions\PaymentException;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly StripeClient $client,
        private readonly ?string $webhookSecret,
    ) {}

    public function name(): string
    {
        return 'stripe';
    }

    public function initialize(PaymentInitiationData $data): PaymentInitiationResult
    {
        try {
            $session = $this->client->checkout->sessions->create([
                'mode' => 'payment',
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($data->currency),
                        'product_data' => ['name' => $data->description],
                        'unit_amount' => $data->amountMinorUnits,
                    ],
                    'quantity' => 1,
                ]],
                'customer_email' => $data->customerEmail,
                'client_reference_id' => $data->reference,
                'success_url' => $data->callbackUrl.'?reference='.$data->reference,
                'cancel_url' => $data->callbackUrl.'?reference='.$data->reference.'&cancelled=1',
                'metadata' => $data->metadata,
            ]);
        } catch (ApiErrorException $exception) {
            throw new PaymentException('Stripe checkout session could not be created: '.$exception->getMessage(), previous: $exception);
        }

        return new PaymentInitiationResult($session->url, $session->id);
    }

    public function verifyTransaction(string $gatewayReference): VerifiedTransaction
    {
        try {
            $session = $this->client->checkout->sessions->retrieve($gatewayReference, ['expand' => ['payment_intent']]);
        } catch (ApiErrorException $exception) {
            throw new PaymentException('Stripe session could not be retrieved: '.$exception->getMessage(), previous: $exception);
        }

        return new VerifiedTransaction(
            reference: $session->id,
            status: $session->payment_status === 'paid' ? 'succeeded' : 'pending',
            amountMinorUnits: (int) $session->amount_total,
            currency: strtoupper($session->currency),
            raw: $session->toArray(),
        );
    }

    public function refund(string $gatewayReference, ?int $amountMinorUnits = null): bool
    {
        try {
            $session = $this->client->checkout->sessions->retrieve($gatewayReference, ['expand' => ['payment_intent']]);

            $paymentIntentId = is_string($session->payment_intent) ? $session->payment_intent : $session->payment_intent?->id;

            if (! $paymentIntentId) {
                return false;
            }

            $params = ['payment_intent' => $paymentIntentId];

            if ($amountMinorUnits !== null) {
                $params['amount'] = $amountMinorUnits;
            }

            $this->client->refunds->create($params);
        } catch (ApiErrorException) {
            return false;
        }

        return true;
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        if (! $this->webhookSecret) {
            return false;
        }

        try {
            Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                $this->webhookSecret,
            );
        } catch (UnexpectedValueException|\Stripe\Exception\SignatureVerificationException) {
            return false;
        }

        return true;
    }

    public function referenceFromWebhook(Request $request): ?string
    {
        return data_get($request->json()->all(), 'data.object.id');
    }
}
