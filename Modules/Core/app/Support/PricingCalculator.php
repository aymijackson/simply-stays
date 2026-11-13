<?php

namespace Modules\Core\Support;

final class PricingCalculator
{
    /**
     * Each charge is a percentage of the subtotal (they don't compound).
     * The "other" charge (e.g. a consumption levy) is only included when set.
     *
     * @return array{subtotal: int, service_charge: int, tax: int, total: int, other_charge?: int, other_charge_label?: string}
     */
    public static function breakdown(
        int $subtotalMinorUnits,
        float $serviceChargePercent,
        float $taxPercent,
        float $otherPercent = 0.0,
        ?string $otherLabel = null,
    ): array {
        $serviceCharge = (int) round($subtotalMinorUnits * $serviceChargePercent / 100);
        $tax = (int) round($subtotalMinorUnits * $taxPercent / 100);

        $breakdown = [
            'subtotal' => $subtotalMinorUnits,
            'service_charge' => $serviceCharge,
            'tax' => $tax,
            'total' => $subtotalMinorUnits + $serviceCharge + $tax,
        ];

        if ($otherPercent > 0) {
            $other = (int) round($subtotalMinorUnits * $otherPercent / 100);
            $breakdown['other_charge'] = $other;
            $breakdown['other_charge_label'] = $otherLabel ?: 'Other charge';
            $breakdown['total'] += $other;
        }

        return $breakdown;
    }
}
