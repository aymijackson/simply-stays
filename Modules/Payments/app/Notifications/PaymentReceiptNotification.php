<?php

namespace Modules\Payments\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Payments\Models\PaymentTransaction;

class PaymentReceiptNotification extends Notification implements ShouldQueue
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
            ->subject('Payment receipt: '.$this->description)
            ->greeting('Thanks for your payment!')
            ->line('We received your payment for '.$this->description.'.')
            ->line('Amount: '.$this->transaction->total()->format())
            ->line('Reference: '.$this->transaction->reference);
    }
}
