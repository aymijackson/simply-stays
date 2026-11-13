<?php

namespace Modules\Services\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Services\Models\ServiceOrder;

/** Tells the people who fulfil a service that a new order has come in. */
class NewServiceOrder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ServiceOrder $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->loadMissing(['service', 'items']);

        $mail = (new MailMessage)
            ->subject("New order {$order->referenceCode()}: {$order->service->title}".($order->location_label ? " — {$order->location_label}" : ''))
            ->greeting('New order')
            ->line("From: {$order->contactName()}".($order->contactPhone() ? ", {$order->contactPhone()}" : ''));

        if ($order->deliverTo()) {
            $mail->line('Deliver to: '.$order->deliverTo());
        }
        foreach ($order->items as $item) {
            $mail->line("{$item->quantity} x {$item->name}");
        }

        return $mail
            ->line('Total: '.$order->total()->format())
            ->action('Open orders', route('provider.services.orders'));
    }
}
