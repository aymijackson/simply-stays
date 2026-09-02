<?php

namespace Modules\Payments\Tests\Unit;

use Modules\Payments\Services\CommissionCalculator;
use Modules\Services\Models\Service;
use Tests\TestCase;

class CommissionCalculatorTest extends TestCase
{
    public function test_none_commission_type_takes_no_fee(): void
    {
        $service = new Service(['commission_type' => 'none', 'commission_value' => null, 'currency' => 'NGN']);

        $split = CommissionCalculator::split($service, 100000);

        $this->assertSame(0, $split['platformFee']);
        $this->assertSame(100000, $split['providerShare']);
    }

    public function test_percentage_commission_takes_a_cut_of_the_gross(): void
    {
        $service = new Service(['commission_type' => 'percentage', 'commission_value' => 10, 'currency' => 'NGN']);

        $split = CommissionCalculator::split($service, 100000);

        $this->assertSame(10000, $split['platformFee']);
        $this->assertSame(90000, $split['providerShare']);
    }

    public function test_fixed_commission_takes_a_flat_amount_converted_to_minor_units(): void
    {
        $service = new Service(['commission_type' => 'fixed', 'commission_value' => 500, 'currency' => 'NGN']);

        $split = CommissionCalculator::split($service, 100000);

        // NGN minor unit factor is 100, so 500 NGN = 50000 minor units.
        $this->assertSame(50000, $split['platformFee']);
        $this->assertSame(50000, $split['providerShare']);
    }

    public function test_fixed_commission_is_capped_at_the_gross_amount(): void
    {
        $service = new Service(['commission_type' => 'fixed', 'commission_value' => 5000, 'currency' => 'NGN']);

        $split = CommissionCalculator::split($service, 100000);

        $this->assertSame(100000, $split['platformFee']);
        $this->assertSame(0, $split['providerShare']);
    }
}
