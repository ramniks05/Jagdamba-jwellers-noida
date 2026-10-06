<?php

namespace Tests\Unit\Masters;

use App\Services\Masters\Fineness;
use PHPUnit\Framework\TestCase;

class FinenessTest extends TestCase
{
    public function test_percent_and_ratio_round_half_up(): void
    {
        $this->assertSame('0.916000', Fineness::ratioFromPercent('91.6'));
        $this->assertSame('91.6', Fineness::percentFromRatio('0.916000'));
        $this->assertSame('1.000000', Fineness::ratioFromPercent('100'));
        $this->assertSame('100', Fineness::percentFromRatio('1.000000'));
    }
}
