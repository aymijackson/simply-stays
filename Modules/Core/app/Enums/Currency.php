<?php

namespace Modules\Core\Enums;

enum Currency: string
{
    case USD = 'USD';
    case NGN = 'NGN';
    case GHS = 'GHS';
    case KES = 'KES';
    case ZAR = 'ZAR';
    case GBP = 'GBP';
    case EUR = 'EUR';

    public function symbol(): string
    {
        return match ($this) {
            self::USD => '$',
            self::NGN => "\u{20a6}",
            self::GHS => "GH\u{20b5}",
            self::KES => 'KSh',
            self::ZAR => 'R',
            self::GBP => "\u{a3}",
            self::EUR => "\u{20ac}",
        };
    }

    public function minorUnit(): int
    {
        return 100;
    }
}
