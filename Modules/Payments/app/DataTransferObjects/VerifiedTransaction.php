<?php

namespace Modules\Payments\DataTransferObjects;

final class VerifiedTransaction
{
    public function __construct(
        public readonly string $reference,
        public readonly string $status,
        public readonly int $amountMinorUnits,
        public readonly string $currency,
        public readonly array $raw = [],
    ) {}

    public function succeeded(): bool
    {
        return $this->status === 'succeeded';
    }
}
