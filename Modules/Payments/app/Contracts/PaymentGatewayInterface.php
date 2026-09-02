<?php

namespace Modules\Payments\Contracts;

use Illuminate\Http\Request;
use Modules\Payments\DataTransferObjects\PaymentInitiationData;
use Modules\Payments\DataTransferObjects\PaymentInitiationResult;
use Modules\Payments\DataTransferObjects\VerifiedTransaction;

interface PaymentGatewayInterface
{
    /**
     * Start a hosted-checkout payment. Returns the URL to redirect the payer to,
     * plus the gateway's own reference/id for this transaction.
     */
    public function initialize(PaymentInitiationData $data): PaymentInitiationResult;

    /**
     * Verify a transaction's current status directly with the gateway, by the
     * gateway's own reference (as returned from initialize()).
     */
    public function verifyTransaction(string $gatewayReference): VerifiedTransaction;

    /**
     * Verify that an incoming webhook request genuinely came from this gateway.
     */
    public function verifyWebhookSignature(Request $request): bool;

    /**
     * Extract the gateway's transaction reference from a webhook payload, if present.
     */
    public function referenceFromWebhook(Request $request): ?string;

    /**
     * Refund a previously succeeded charge, by the gateway's own reference.
     * A null amount refunds the transaction in full.
     */
    public function refund(string $gatewayReference, ?int $amountMinorUnits = null): bool;

    /**
     * The gateway's identifying name, e.g. "stripe".
     */
    public function name(): string;
}
