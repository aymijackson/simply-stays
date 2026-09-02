<?php

namespace Modules\Referrals\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Referrals\Models\Referral;

class CommissionEarned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Referral $referral) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You earned a referral commission!')
            ->greeting('Cha-ching!')
            ->line('You earned '.$this->referral->total()->format().' in commission from one of your referrals.')
            ->line('It has been credited to your wallet.')
            ->action('View my referrals', route('referrals.index'));
    }
}
