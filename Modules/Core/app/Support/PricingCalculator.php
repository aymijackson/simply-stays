<?php

namespace Modules\Core\Support;

final class PricingCalculator
{
    /**
     * @return array{subtotal: int, service_charge: int, tax: int, total: int}
     */
    public static function breakdown(int $subtotalMinorUnits, float $serviceChargePercent, float $taxPercent): array
    {
        $serviceCharge = (int) round($subtotalMinorUnits * $serviceChargePercent / 100);
        $tax = (int) round($subtotalMinorUnits * $taxPercent / 100);

        return [
            'subtotal' => $subtotalMinorUnits,
            'service_charge' => $serviceCharge,
            'tax' => $tax,
            'total' => $subtotalMinorUnits + $serviceCharge + $tax,
        ];
    }
}
