<?php

namespace App\Services\Masters;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class Fineness
{
    public static function ratioFromPercent(string $percent): string
    {
        return (string) BigDecimal::of($percent)->dividedBy('100', 6, RoundingMode::HalfUp);
    }

    public static function percentFromRatio(string $ratio): string
    {
        $percent = BigDecimal::of($ratio)->multipliedBy('100')->toScale(4, RoundingMode::HalfUp);
        $text = rtrim(rtrim((string) $percent, '0'), '.');

        return $text === '' ? '0' : $text;
    }
}
