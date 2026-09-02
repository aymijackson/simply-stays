<?php

namespace Modules\Services\Support;

use Carbon\Carbon;
use Modules\Services\Models\Service;

final class DeliveryFeeCalculator
{
    public static function feeFor(Service $service, int $subtotalMinorUnits, ?Carbon $now = null): int
    {
        $now ??= Carbon::now();

        if ($service->free_delivery_over_amount !== null && $subtotalMinorUnits >= $service->free_delivery_over_amount) {
            return 0;
        }

        foreach ($service->free_delivery_windows ?? [] as $window) {
            if (self::isWithinWindow($window, $now)) {
                return 0;
            }
        }

        return $service->delivery_fee_amount;
    }

    private static function isWithinWindow(array $window, Carbon $now): bool
    {
        $days = $window['days'] ?? [];

        if (! empty($days) && ! in_array($now->dayOfWeek, $days, true)) {
            return false;
        }

        $start = $window['start'] ?? null;
        $end = $window['end'] ?? null;

        if (! $start || ! $end) {
            return true;
        }

        $current = $now->format('H:i');

        return $current >= $start && $current <= $end;
    }
}
