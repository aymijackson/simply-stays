<?php

namespace Modules\Services\Tests\Unit;

use Carbon\Carbon;
use Modules\Services\Models\Service;
use Modules\Services\Support\DeliveryFeeCalculator;
use Tests\TestCase;

class DeliveryFeeCalculatorTest extends TestCase
{
    public function test_flat_fee_applies_by_default(): void
    {
        $service = new Service(['delivery_fee_amount' => 100000, 'free_delivery_over_amount' => null, 'free_delivery_windows' => null]);

        $fee = DeliveryFeeCalculator::feeFor($service, 200000, Carbon::parse('2026-01-05 12:00'));

        $this->assertSame(100000, $fee);
    }

    public function test_fee_is_waived_when_subtotal_meets_free_over_threshold(): void
    {
        $service = new Service(['delivery_fee_amount' => 100000, 'free_delivery_over_amount' => 500000, 'free_delivery_windows' => null]);

        $this->assertSame(0, DeliveryFeeCalculator::feeFor($service, 500000, Carbon::parse('2026-01-05 12:00')));
        $this->assertSame(100000, DeliveryFeeCalculator::feeFor($service, 400000, Carbon::parse('2026-01-05 12:00')));
    }

    public function test_fee_is_waived_inside_a_free_delivery_window(): void
    {
        $service = new Service([
            'delivery_fee_amount' => 100000,
            'free_delivery_over_amount' => null,
            'free_delivery_windows' => [
                ['days' => [1, 2, 3, 4, 5], 'start' => '18:00', 'end' => '22:00'],
            ],
        ]);

        // Monday (dayOfWeek = 1) at 19:00 falls inside the window.
        $this->assertSame(0, DeliveryFeeCalculator::feeFor($service, 10000, Carbon::parse('2026-01-05 19:00')));

        // Monday at 10:00 falls outside the window.
        $this->assertSame(100000, DeliveryFeeCalculator::feeFor($service, 10000, Carbon::parse('2026-01-05 10:00')));
    }
}
