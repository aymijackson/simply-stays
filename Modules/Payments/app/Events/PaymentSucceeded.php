<?php

namespace Modules\Payments\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Payments\Models\PaymentTransaction;

class PaymentSucceeded
{
    use Dispatchable;

    public function __construct(public readonly PaymentTransaction $transaction) {}
}
