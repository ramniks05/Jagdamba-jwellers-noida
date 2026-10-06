<?php

namespace App\Services\Commerce;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class OldGoldPricer
{
    /**
     * @return array{net_weight: string, melted_weight: string, exchange_value: string}
     */
    public function value(
        string $grossWeight,
        string $stoneWeight,
        string $meltingLossPercent,
        string $ratePerGram,
        string $deduction,
    ): array {
        $gross = $this->decimal($grossWeight)->toScale(3, RoundingMode::HalfUp);
        $stone = $this->decimal($stoneWeight)->toScale(3, RoundingMode::HalfUp);

        if ($stone->isGreaterThan($gross)) {
            throw ValidationException::withMessages([
                'stone_weight' => 'Stone weight cannot be more than the gross weight.',
            ]);
        }

        $net = $gross->minus($stone);
        $loss = $this->enabled('melting_loss') ? $this->decimal($meltingLossPercent) : BigDecimal::zero();

        if ($loss->isNegative() || $loss->isGreaterThan('100')) {
            throw ValidationException::withMessages([
                'melting_loss_percent' => 'Melting loss must be between 0 and 100 percent.',
            ]);
        }

        $melted = $net->multipliedBy(BigDecimal::of('100')->minus($loss))->dividedBy('100', 3, RoundingMode::HalfUp);
        $rate = $this->enabled('rate') ? $this->decimal($ratePerGram) : BigDecimal::zero();
        $metal = $melted->multipliedBy($rate);
        $deductionAmount = $this->enabled('deduction') ? $this->decimal($deduction) : BigDecimal::zero();

        if ($deductionAmount->isGreaterThan($metal)) {
            throw ValidationException::withMessages([
                'deduction_amount' => 'The deduction cannot be more than the metal value.',
            ]);
        }

        return [
            'net_weight' => (string) $net->toScale(3, RoundingMode::HalfUp),
            'melted_weight' => (string) $melted,
            'exchange_value' => $this->money($metal->minus($deductionAmount)),
        ];
    }

    private function enabled(string $step): bool
    {
        $steps = config('exchange.steps');

        return is_array($steps) && in_array($step, $steps, true);
    }

    private function decimal(string $value): BigDecimal
    {
        return BigDecimal::of(trim($value) === '' ? '0' : $value);
    }

    private function money(BigDecimal $value): string
    {
        return (string) $value->toScale(2, RoundingMode::HalfUp);
    }
}
