<?php

namespace Modules\Operations\Support;

use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;

/** View formatting helpers for the operations screens. */
class Fmt
{
    public static function money(?int $minor, ?string $currency = null): string
    {
        if ($minor === null) {
            return '—';
        }

        return Money::fromMinorUnits($minor, Currency::from($currency ?? OpsContext::currency()))->format();
    }

    /** Minor units back to a plain input value, e.g. 1250050 => "12500.50". */
    public static function input(?int $minor): string
    {
        if ($minor === null) {
            return '';
        }

        return rtrim(rtrim(number_format($minor / 100, 2, '.', ''), '0'), '.');
    }
}
