<?php

namespace Modules\Payments\Gateways;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Modules\Payments\Contracts\PaymentGatewayInterface;
use Modules\Payments\DataTransferObjects\PaymentInitiationData;
use Modules\Payments\DataTransferObjects\PaymentInitiationResult;
use Modules\Payments\DataTransferObjects\VerifiedTransaction;
use Modules\Payments\Exceptions\PaymentException;

class FlutterwaveGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $secretKey,
        private readonly string $baseUrl,
        private readonly ?string $webhookHash,
    ) {}

    public function name(): string
    {
        return 'flutterwave';
    }

    public function initialize(PaymentInitiationData $data): PaymentInitiationResult
    {
        // Flutterwave expects amounts in the major currency unit (e.g. "150.00"), not minor units.
        $majorAmount = round($data->amountMinorUnits / 100, 2);

        $response = $this->client()->post('/payments', [
            'tx_ref' => $data->reference,
            'amount' => $majorAmount,
            'currency' => $data->currency,
            'redirect_url' => $data->callbackUrl,
            'customer' => ['email' => $data->customerEmail],
            'customizations' => ['title' => $data->description],
            'meta' => $data->metadata,
        ]);

        if ($response->failed() || $response->json('status') !== 'success') {
            throw new PaymentException('Flutterwave payment could not be initialized: '.$response->body());
        }

        return new PaymentInitiationResult(
            $response->json('data.link'),
            $data->reference,
        );
    }

    public function verifyTransaction(string $gatewayReference): VerifiedTransaction
    {
        $response = $this->client()->get('/transactions/verify_by_reference', [
            'tx_ref' => $gatewayReference,
        ]);

        if ($response->failed()) {
            throw new PaymentException('Flutterwave transaction could not be verified: '.$response->body());
        }

        $data = $response->json('data', []);
        $gatewayStatus = $data['status'] ?? 'failed';

        return new VerifiedTransaction(
            reference: $data['tx_ref'] ?? $gatewayReference,
            status: $gatewayStatus === 'successful' ? 'succeeded' : ($gatewayStatus === 'pending' ? 'pending' : 'failed'),
            // Flutterwave reports amounts back in major units; normalize to minor units for storage.
            amountMinorUnits: (int) round((float) ($data['amount'] ?? 0) * 100),
            currency: strtoupper($data['currency'] ?? ''),
            raw: $data,
        );
    }

    public function refund(string $gatewayReference, ?int $amountMinorUnits = null): bool
    {
        // Flutterwave refunds are keyed by their own numeric transaction id, not our tx_ref,
        // so we look it up via verify first.
        $verifyResponse = $this->client()->get('/transactions/verify_by_reference', [
            'tx_ref' => $gatewayReference,
        ]);

        $transactionId = $verifyResponse->json('data.id');

        if (! $transactionId) {
            return false;
        }

        $payload = [];

        if ($amountMinorUnits !== null) {
            $payload['amount'] = round($amountMinorUnits / 100, 2);
        }

        $refundResponse = $this->client()->post("/transactions/{$transactionId}/refund", $payload);

        return $refundResponse->successful() && $refundResponse->json('status') === 'success';
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        if (! $this->webhookHash) {
            return false;
        }

        return hash_equals($this->webhookHash, $request->header('verif-hash', ''));
    }

    public function referenceFromWebhook(Request $request): ?string
    {
        return data_get($request->json()->all(), 'data.tx_ref');
    }

    private function client()
    {
        return $this->http->baseUrl($this->baseUrl)
            ->withToken($this->secretKey)
            ->acceptJson();
    }
}
