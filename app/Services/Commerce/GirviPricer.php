<?php

namespace App\Services\Commerce;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class GirviPricer
{
    /**
     * @return array{net_weight: string, gold_value: string}
     */
    public function piece(string $grossWeight, string $stoneWeight, string $ratePerGram): array
    {
        $gross = $this->decimal($grossWeight)->toScale(3, RoundingMode::HalfUp);
        $stone = $this->decimal($stoneWeight)->toScale(3, RoundingMode::HalfUp);

        if ($stone->isGreaterThan($gross)) {
            throw ValidationException::withMessages([
                'stone_weight' => 'Stone weight cannot be more than the gross weight.',
            ]);
        }

        $net = $gross->minus($stone)->toScale(3, RoundingMode::HalfUp);

        if ($net->isNegativeOrZero()) {
            throw ValidationException::withMessages([
                'gross_weight' => 'Enter a gross weight greater than the stone weight.',
            ]);
        }

        return [
            'net_weight' => (string) $net,
            'gold_value' => (string) $net->multipliedBy($this->decimal($ratePerGram))->toScale(2, RoundingMode::HalfUp),
        ];
    }

    public function principal(string $goldValue, string $loanMode, string $loanPercent, string $loanAmount): string
    {
        $gold = $this->decimal($goldValue)->toScale(2, RoundingMode::HalfUp);
        $principal = $loanMode === 'amount'
            ? $this->decimal($loanAmount)->toScale(2, RoundingMode::HalfUp)
            : $gold->multipliedBy($this->decimal($loanPercent))->dividedBy('100', 2, RoundingMode::HalfUp);

        if ($principal->isNegativeOrZero()) {
            throw ValidationException::withMessages([
                'loan_amount' => 'The loan must be more than zero.',
            ]);
        }

        if ($principal->isGreaterThan($gold)) {
            throw ValidationException::withMessages([
                'loan_amount' => 'The loan cannot be more than the gold value, '.$gold.'.',
            ]);
        }

        return (string) $principal;
    }

    public function interest(string $principal, string $percent, int $months): string
    {
        if ($months < 0) {
            throw ValidationException::withMessages([
                'months' => 'Months cannot be less than zero.',
            ]);
        }

        return (string) $this->decimal($principal)
            ->multipliedBy($this->decimal($percent))
            ->multipliedBy($months)
            ->dividedBy('100', 2, RoundingMode::HalfUp);
    }

    public function monthsBetween(Carbon $from, Carbon $until): int
    {
        $days = (int) abs($from->copy()->startOfDay()->diffInDays($until->copy()->startOfDay()));

        if ($days === 0) {
            return 1;
        }

        return (int) max(1, (int) ceil($days / 30));
    }

    private function decimal(string $value): BigDecimal
    {
        return BigDecimal::of(trim($value) === '' ? '0' : $value);
    }
}
