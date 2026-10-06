<?php

namespace Tests\Unit\Commerce;

use App\Services\Commerce\OldGoldPricer;
use App\Services\Commerce\SchemeBenefit;
use Tests\TestCase;

class WorkshopMathTest extends TestCase
{
    public function test_old_gold_value_uses_the_tested_rate_per_gram(): void
    {
        $value = app(OldGoldPricer::class)->value('10', '0', '2', '6000', '100');

        $this->assertSame('10.000', $value['net_weight']);
        $this->assertSame('9.800', $value['melted_weight']);
        $this->assertSame('58700.00', $value['exchange_value']);
    }

    public function test_an_extra_installment_is_added_once_at_maturity(): void
    {
        $value = app(SchemeBenefit::class)->maturity('10000.00', '5000.00', 'extra_installment', '0');

        $this->assertSame('15000.00', $value);
    }
}
