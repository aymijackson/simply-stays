<?php

namespace Modules\Payments\Services;

use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;
use Modules\Events\Models\Event;
use Modules\Services\Models\Service;

final class CommissionCalculator
{
    /**
     * @return array{platformFee: int, providerShare: int}
     */
    public static function split(Service|Event $sellable, int $grossAmountMinorUnits): array
    {
        $fee = match ($sellable->commission_type) {
            'percentage' => (int) round($grossAmountMinorUnits * ((float) $sellable->commission_value / 100)),
            'fixed' => Money::fromDecimal((float) $sellable->commission_value, Currency::from($sellable->currency))->minorUnits,
            default => 0,
        };

        $fee = max(0, min($fee, $grossAmountMinorUnits));

        return [
            'platformFee' => $fee,
            'providerShare' => $grossAmountMinorUnits - $fee,
        ];
    }
}
