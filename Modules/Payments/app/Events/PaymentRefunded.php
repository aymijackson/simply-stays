<?php

namespace Modules\Payments\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Payments\Models\PaymentTransaction;

class PaymentRefunded
{
    use Dispatchable;

    /**
     * @param  PaymentTransaction  $charge  The original succeeded charge being refunded.
     */
    public function __construct(public readonly PaymentTransaction $charge) {}
}
