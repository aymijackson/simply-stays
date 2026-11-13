<?php

namespace Modules\Services\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Modules\Services\Models\ServiceOrder;
use Modules\Services\Notifications\NewServiceOrder;
use Modules\Services\Notifications\ServiceOrderPlaced;
use Throwable;

/**
 * Emails sent when an order is placed. Staff also see new orders live in the
 * portal (polling), so a mail failure is logged rather than failing the order.
 */
class OrderNotifier
{
    public static function placed(ServiceOrder $order): void
    {
        try {
            $order->notifyCustomer(new ServiceOrderPlaced($order));

            $staff = self::staffFor($order);
            if ($staff->isNotEmpty()) {
                Notification::send($staff, new NewServiceOrder($order));
            }
        } catch (Throwable $e) {
            Log::warning('Could not send new-order emails', ['order' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Who handles the order: the vendor, or (for in-house services) every admin;
     * plus the property's host when the order is for one of their rooms or stays.
     *
     * @return Collection<int, User>
     */
    public static function staffFor(ServiceOrder $order): Collection
    {
        $order->loadMissing(['service.provider', 'location.property', 'booking.listing.property']);
        $service = $order->service;

        $people = $service->isInHouse()
            ? User::role('Admin')->where('status', 'active')->get()
            : collect([$service->provider])->filter();

        $host = ($order->location?->property ?? $order->booking?->listing?->property)?->host;
        if ($host) {
            $people->push($host);
        }

        return $people->unique('id')->values();
    }
}
