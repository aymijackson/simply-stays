<?php

namespace Modules\Services\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;
use Modules\Services\Models\ServiceOrder;

/** Receipt / confirmation to the customer when they place an order. */
class ServiceOrderPlaced extends Notification implements ShouldQueue
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
        $currency = Currency::from($order->currency);
        $fmt = fn ($minor) => Money::fromMinorUnits((int) $minor, $currency)->format();

        $mail = (new MailMessage)
            ->subject("Order received: {$order->service->title} (#{$order->id})")
            ->greeting('Thanks, '.$order->contactName().'!')
            ->line("We've received your order for {$order->service->title}. We'll confirm it shortly.");

        if ($order->deliverTo()) {
            $mail->line('Deliver to: '.$order->deliverTo());
        }

        foreach ($order->items as $item) {
            $mail->line("{$item->quantity} x {$item->name} — ".$item->lineTotal($order->currency)->format());
        }

        $b = $order->price_breakdown ?? [];
        foreach (['service_charge' => 'Service charge', 'tax' => 'VAT / tax', 'other_charge' => $b['other_charge_label'] ?? 'Other charge', 'delivery_fee' => 'Delivery fee'] as $key => $label) {
            if (($b[$key] ?? 0) > 0) {
                $mail->line("{$label}: ".$fmt($b[$key]));
            }
        }

        return $mail
            ->line('**Total: '.$order->total()->format().'**')
            ->action('View your order', $order->customer_id ? route('service-orders.show', $order) : $order->trackingUrl())
            ->line('Keep this email as your receipt.');
    }
}
