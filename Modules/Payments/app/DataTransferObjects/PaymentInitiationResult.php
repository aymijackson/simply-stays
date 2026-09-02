<?php

namespace Modules\Payments\DataTransferObjects;

final class PaymentInitiationResult
{
    public function __construct(
        public readonly string $redirectUrl,
        public readonly string $gatewayReference,
    ) {}
}
