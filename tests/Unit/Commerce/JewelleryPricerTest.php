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

    public function test_making_can_be_inside_gst_on_its_own_rate_or_a_processing_charge(): void
    {
        $pricer = new JewelleryPricer;

        $inside = $pricer->billWithMaking('105000', '5000', '0', '3', 'inside', '5', true, true);
        $this->assertSame('3150.00', $inside->taxAmount);
        $this->assertSame('108150.00', $inside->total);

        $separate = $pricer->billWithMaking('105000', '5000', '0', '3', 'separate', '5', true, true);
        $this->assertSame('3250.00', $separate->taxAmount);
        $this->assertSame('250.00', $separate->makingTaxAmount);
        $this->assertSame('105000.00', $separate->taxableAmount);
        $this->assertSame('108250.00', $separate->total);

        $processing = $pricer->billWithMaking('105000', '5000', '0', '3', 'processing', '5', true, true);
        $this->assertSame('3000.00', $processing->taxAmount);
        $this->assertSame('0.00', $processing->makingTaxAmount);
        $this->assertSame('100000.00', $processing->taxableAmount);
        $this->assertSame('108000.00', $processing->total);

        $discounted = $pricer->billWithMaking('105000', '5000', '1000', '3', 'processing', '5', true, true);
        $this->assertSame('2970.00', $discounted->taxAmount);
        $this->assertSame('5000.00', $discounted->makingAmount);
        $this->assertSame('106970.00', $discounted->total);

        $inclusive = $pricer->billWithMaking('105000', '5000', '0', '3', 'separate', '5', false, false);
        $this->assertSame('238.10', $inclusive->makingTaxAmount);
        $this->assertSame('3150.72', $inclusive->taxAmount);
        $this->assertSame('105000.00', $inclusive->total);
    }
}
