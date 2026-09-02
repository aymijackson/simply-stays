<?php

namespace Modules\Payments\Gateways;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Modules\Payments\Contracts\PaymentGatewayInterface;
use Modules\Payments\DataTransferObjects\PaymentInitiationData;
use Modules\Payments\DataTransferObjects\PaymentInitiationResult;
use Modules\Payments\DataTransferObjects\VerifiedTransaction;
use Modules\Payments\Exceptions\PaymentException;

class PaystackGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $secretKey,
        private readonly string $baseUrl,
    ) {}

    public function name(): string
    {
        return 'paystack';
    }

    public function initialize(PaymentInitiationData $data): PaymentInitiationResult
    {
        $response = $this->client()->post('/transaction/initialize', [
            'reference' => $data->reference,
            'email' => $data->customerEmail,
            // Paystack processes amounts in the smallest currency unit, same convention we already store in.
            'amount' => $data->amountMinorUnits,
            'currency' => $data->currency,
            'callback_url' => $data->callbackUrl,
            'metadata' => $data->metadata,
        ]);

        if ($response->failed() || ! $response->json('status')) {
            throw new PaymentException('Paystack transaction could not be initialized: '.$response->body());
        }

        return new PaymentInitiationResult(
            $response->json('data.authorization_url'),
            $response->json('data.reference', $data->reference),
        );
    }

    public function verifyTransaction(string $gatewayReference): VerifiedTransaction
    {
        $response = $this->client()->get('/transaction/verify/'.rawurlencode($gatewayReference));

        if ($response->failed()) {
            throw new PaymentException('Paystack transaction could not be verified: '.$response->body());
        }

        $data = $response->json('data', []);
        $gatewayStatus = $data['status'] ?? 'failed';

        return new VerifiedTransaction(
            reference: $data['reference'] ?? $gatewayReference,
            status: $gatewayStatus === 'success' ? 'succeeded' : ($gatewayStatus === 'abandoned' ? 'pending' : 'failed'),
            amountMinorUnits: (int) ($data['amount'] ?? 0),
            currency: strtoupper($data['currency'] ?? ''),
            raw: $data,
        );
    }

    public function refund(string $gatewayReference, ?int $amountMinorUnits = null): bool
    {
        $payload = ['transaction' => $gatewayReference];

        if ($amountMinorUnits !== null) {
            $payload['amount'] = $amountMinorUnits;
        }

        $response = $this->client()->post('/refund', $payload);

        return $response->successful() && (bool) $response->json('status');
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('X-Paystack-Signature', '');

        if (! $signature || ! $this->secretKey) {
            return false;
        }

        $expected = hash_hmac('sha512', $request->getContent(), $this->secretKey);

        return hash_equals($expected, $signature);
    }

    public function referenceFromWebhook(Request $request): ?string
    {
        return data_get($request->json()->all(), 'data.reference');
    }

    private function client()
    {
        return $this->http->baseUrl($this->baseUrl)
            ->withToken($this->secretKey)
            ->acceptJson();
    }
}
