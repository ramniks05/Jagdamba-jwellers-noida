<?php

namespace Tests\Unit\Foundation;

use App\Services\Foundation\NumberFormatService;
use App\Services\Foundation\SettingService;
use PHPUnit\Framework\TestCase;

class NumberFormatTest extends TestCase
{
    public function test_indian_currency_grouping_and_rounding(): void
    {
        $formatted = $this->formatter()->format('1234567.5', [
            'scale' => 2,
            'decimal_separator' => '.',
            'thousand_separator' => ',',
            'grouping' => 'indian',
            'symbol' => '₹',
            'symbol_position' => 'before',
        ]);

        $this->assertSame('₹ 12,34,567.50', $formatted);
    }

    public function test_international_grouping_and_symbol_after(): void
    {
        $formatted = $this->formatter()->format('-1234567.50', [
            'scale' => 2,
            'decimal_separator' => '.',
            'thousand_separator' => ',',
            'grouping' => 'international',
            'symbol' => '₹',
            'symbol_position' => 'after',
        ]);

        $this->assertSame('-1,234,567.50 ₹', $formatted);
    }

    public function test_weight_uses_half_up_rounding(): void
    {
        $formatted = $this->formatter()->format('12.3456', [
            'scale' => 3,
            'decimal_separator' => '.',
            'thousand_separator' => ',',
            'grouping' => 'indian',
            'suffix' => ' g',
        ]);

        $this->assertSame('12.346 g', $formatted);
    }

    private function formatter(): NumberFormatService
    {
        return new NumberFormatService(new SettingService);
    }
}
