<?php

namespace Modules\Services\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Services\Models\ServiceOrder;

class ServiceOrderStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ServiceOrder $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = str_replace('_', ' ', $this->order->status);

        return (new MailMessage)
            ->subject('Your order for '.$this->order->service->title.' is now '.$status)
            ->greeting('Order update')
            ->line('Your order for "'.$this->order->service->title.'" is now: '.ucfirst($status).'.')
            ->action('View order', route('service-orders.show', $this->order));
    }
}
