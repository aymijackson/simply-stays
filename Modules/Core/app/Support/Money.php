<?php

namespace Modules\Core\Support;

use InvalidArgumentException;
use Modules\Core\Enums\Currency;

final class Money
{
    private function __construct(
        public readonly int $minorUnits,
        public readonly Currency $currency,
    ) {}

    public static function fromMinorUnits(int $minorUnits, Currency $currency): self
    {
        return new self($minorUnits, $currency);
    }

    public static function fromDecimal(float $amount, Currency $currency): self
    {
        return new self((int) round($amount * $currency->minorUnit()), $currency);
    }

    public function toDecimal(): float
    {
        return $this->minorUnits / $this->currency->minorUnit();
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    public function format(): string
    {
        return $this->currency->symbol().number_format($this->toDecimal(), 2);
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Cannot operate on Money values with different currencies.');
        }
    }
}
