<?php

namespace Modules\Referrals\Listeners;

use Modules\Bookings\Models\Booking;
use Modules\Payments\Events\PaymentRefunded;
use Modules\Payments\Services\WalletService;
use Modules\Referrals\Models\Referral;

class ReverseReferralCommission
{
    public function __construct(private readonly WalletService $wallets) {}

    public function handle(PaymentRefunded $event): void
    {
        $charge = $event->charge;

        if (! $charge->booking_id) {
            return;
        }

        $referral = Referral::where('referable_type', Booking::class)
            ->where('referable_id', $charge->booking_id)
            ->where('status', 'paid')
            ->with('agent.user')
            ->first();

        if (! $referral || ! $referral->agent?->user) {
            return;
        }

        $this->wallets->debit(
            $referral->agent->user,
            $referral->commission_amount,
            $referral->currency,
            Referral::class,
            $referral->id,
        );
    }
}
