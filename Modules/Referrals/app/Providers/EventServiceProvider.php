<?php

namespace Modules\Referrals\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Payments\Events\PaymentRefunded;
use Modules\Payments\Events\PaymentSucceeded;
use Modules\Referrals\Listeners\CreditReferralCommission;
use Modules\Referrals\Listeners\ReverseReferralCommission;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        PaymentSucceeded::class => [
            CreditReferralCommission::class,
        ],
        PaymentRefunded::class => [
            ReverseReferralCommission::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
