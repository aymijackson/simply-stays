<?php

namespace Modules\Payments\Contracts;

interface Payable
{
    public function payerId(): int;

    public function paymentDescription(): string;

    public function paymentAmountMinorUnits(): int;

    public function paymentCurrency(): string;
}
