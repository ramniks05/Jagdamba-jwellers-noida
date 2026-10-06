<?php

namespace App\Services\Commerce;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class SchemeBenefit
{
    public function maturity(string $paid, string $monthlyAmount, string $bonusType, string $bonusValue): string
    {
        $paidAmount = BigDecimal::of($paid);
        $bonus = match ($bonusType) {
            'extra_installment' => BigDecimal::of($monthlyAmount === '' ? '0' : $monthlyAmount),
            'percent' => $paidAmount->multipliedBy($bonusValue === '' ? '0' : $bonusValue)->dividedBy('100', 2, RoundingMode::HalfUp),
            'fixed' => BigDecimal::of($bonusValue === '' ? '0' : $bonusValue),
            default => throw ValidationException::withMessages([
                'bonus_type' => 'Choose a bonus rule from the scheme list.',
            ]),
        };

        return (string) $paidAmount->plus($bonus)->toScale(2, RoundingMode::HalfUp);
    }
}
