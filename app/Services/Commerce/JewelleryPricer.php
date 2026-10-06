<?php

namespace App\Services\Commerce;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class JewelleryPricer
{
    /**
     * @return array{metal_amount: string, wastage_amount: string, making_amount: string, stone_amount: string, line_amount: string}
     */
    public function line(
        string $netWeight,
        string $ratePerGram,
        string $wastageMethod,
        string $wastageValue,
        string $makingMethod,
        string $makingValue,
        string $stoneCharge,
    ): array {
        $net = $this->decimal($netWeight);
        $rate = $this->decimal($ratePerGram);
        $metal = $this->enabled('metal_value') ? $net->multipliedBy($rate) : BigDecimal::zero();
        $wastage = $this->enabled('wastage')
            ? $this->wastageAmount($net, $rate, $wastageMethod, $wastageValue)
            : BigDecimal::zero();
        $making = $this->enabled('making')
            ? $this->makingAmount($net, $metal, $makingMethod, $makingValue)
            : BigDecimal::zero();
        $stone = $this->enabled('stone') ? $this->decimal($stoneCharge) : BigDecimal::zero();

        return [
            'metal_amount' => $this->money($metal),
            'wastage_amount' => $this->money($wastage),
            'making_amount' => $this->money($making),
            'stone_amount' => $this->money($stone),
            'line_amount' => $this->money($metal->plus($wastage)->plus($making)->plus($stone)),
        ];
    }

    public function bill(
        string $linesAmount,
        string $discount,
        string $taxPercent,
        bool $taxExclusive,
        bool $roundRupee,
    ): PricingBreakdown {
        $base = $this->decimal($linesAmount);
        $discountAmount = $this->enabled('discount') ? $this->decimal($discount) : BigDecimal::zero();

        if ($discountAmount->isGreaterThan($base)) {
            throw ValidationException::withMessages([
                'discount' => 'The discount cannot be more than the item total.',
            ]);
        }

        $afterDiscount = $base->minus($discountAmount);
        $percent = $this->decimal($taxPercent);

        if (! $this->enabled('tax') || $percent->isZero()) {
            $taxable = $afterDiscount;
            $tax = BigDecimal::zero();
            $exact = $afterDiscount;
        } elseif ($taxExclusive) {
            $taxable = $afterDiscount;
            $tax = $afterDiscount->multipliedBy($percent)->dividedBy('100', 2, RoundingMode::HalfUp);
            $exact = $taxable->plus($tax);
        } else {
            $exact = $afterDiscount;
            $tax = $afterDiscount->multipliedBy($percent)->dividedBy($percent->plus('100'), 2, RoundingMode::HalfUp);
            $taxable = $exact->minus($tax);
        }

        $exactMoney = $this->money($exact);
        $roundOff = '0.00';
        $total = $exactMoney;

        if ($this->enabled('round_off') && $roundRupee) {
            $rounded = BigDecimal::of($exactMoney)->toScale(0, RoundingMode::HalfUp);
            $roundOff = $this->money($rounded->minus($exactMoney));
            $total = $this->money($rounded);
        }

        return new PricingBreakdown(
            linesAmount: $this->money($base),
            discountAmount: $this->money($discountAmount),
            taxableAmount: $this->money($taxable),
            taxAmount: $this->money($tax),
            exactTotal: $exactMoney,
            roundOff: $roundOff,
            total: $total,
        );
    }

    private function wastageAmount(BigDecimal $net, BigDecimal $rate, string $method, string $value): BigDecimal
    {
        $amount = $this->decimal($value);

        return match ($method) {
            'percentage' => $net->multipliedBy($amount)->dividedBy('100', 6, RoundingMode::HalfUp)->multipliedBy($rate),
            'per_gram' => $net->multipliedBy($amount),
            'fixed' => $amount,
            default => throw ValidationException::withMessages([
                'wastage' => 'This wastage method cannot be used on a bill.',
            ]),
        };
    }

    private function makingAmount(BigDecimal $net, BigDecimal $metal, string $method, string $value): BigDecimal
    {
        $amount = $this->decimal($value);

        return match ($method) {
            'per_gram' => $net->multipliedBy($amount),
            'percentage' => $metal->multipliedBy($amount)->dividedBy('100', 6, RoundingMode::HalfUp),
            'fixed', 'per_piece' => $amount,
            default => throw ValidationException::withMessages([
                'making' => 'This making method cannot be used on a bill.',
            ]),
        };
    }

    private function enabled(string $component): bool
    {
        $components = config('pricing.components');

        return is_array($components) && in_array($component, $components, true);
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
