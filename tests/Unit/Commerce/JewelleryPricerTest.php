<?php

namespace Tests\Unit\Commerce;

use App\Services\Commerce\JewelleryPricer;
use Tests\TestCase;

class JewelleryPricerTest extends TestCase
{
    public function test_bill_uses_weight_wastage_making_stone_discount_and_tax(): void
    {
        $pricer = new JewelleryPricer;
        $line = $pricer->line('10', '6000', 'percentage', '5', 'per_gram', '500', '1000');

        $this->assertSame('60000.00', $line['metal_amount']);
        $this->assertSame('3000.00', $line['wastage_amount']);
        $this->assertSame('5000.00', $line['making_amount']);
        $this->assertSame('1000.00', $line['stone_amount']);
        $this->assertSame('69000.00', $line['line_amount']);

        $bill = $pricer->bill('69000.00', '500', '3', true, true);

        $this->assertSame('68500.00', $bill->taxableAmount);
        $this->assertSame('2055.00', $bill->taxAmount);
        $this->assertSame('0.00', $bill->roundOff);
        $this->assertSame('70555.00', $bill->total);
    }
}
