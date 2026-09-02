<?php

namespace Modules\Referrals\Listeners;

use Modules\Bookings\Models\Booking;
use Modules\Payments\Events\PaymentSucceeded;
use Modules\Payments\Services\WalletService;
use Modules\Referrals\Models\Referral;
use Modules\Referrals\Notifications\CommissionEarned;

class CreditReferralCommission
{
    public function __construct(private readonly WalletService $wallets) {}

    public function handle(PaymentSucceeded $event): void
    {
        $transaction = $event->transaction;

        if (! $transaction->booking_id) {
            return;
        }

        $referral = Referral::where('referable_type', Booking::class)
            ->where('referable_id', $transaction->booking_id)
            ->where('status', 'pending')
            ->with('agent.user')
            ->first();

        if (! $referral || ! $referral->agent?->user) {
            return;
        }

        $this->wallets->credit(
            $referral->agent->user,
            $referral->commission_amount,
            $referral->currency,
            Referral::class,
            $referral->id,
        );

        $referral->update(['status' => 'paid']);

        $referral->agent->user->notify(new CommissionEarned($referral));
    }
}
