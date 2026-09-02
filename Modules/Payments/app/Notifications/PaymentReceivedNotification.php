<?php

namespace Modules\Payments\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Payments\Models\PaymentTransaction;

class PaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PaymentTransaction $transaction,
        public readonly string $description,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment received for '.$this->description)
            ->greeting('You\'ve been paid!')
            ->line('A payment of '.$this->transaction->total()->format().' was received for '.$this->description.'.')
            ->line('This has been credited to your wallet.')
            ->action('View earnings', route('payments.index'));
    }
}
