<?php

namespace Modules\Payments\DataTransferObjects;

final class PaymentInitiationData
{
    public function __construct(
        public readonly string $reference,
        public readonly int $amountMinorUnits,
        public readonly string $currency,
        public readonly string $description,
        public readonly string $callbackUrl,
        public readonly string $customerEmail,
        public readonly array $metadata = [],
    ) {}
}
