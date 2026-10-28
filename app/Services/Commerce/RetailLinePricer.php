<?php

namespace App\Services\Commerce;

use App\Enums\RetailTaxMode;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class RetailLinePricer
{
    /**
     * @param  array{name: string, quantity: int|string, unit_price: string, tax_mode: string, tax_percent?: string|null, tax_amount?: string|null}  $row
     * @return array{taxable_amount: string, tax_amount: string, line_amount: string, tax_mode: RetailTaxMode, tax_percent: ?string}
     */
    public function price(array $row, ?string $fallbackTaxPercent = null): array
    {
        $qty = max(1, (int) $row['quantity']);
        $unit = BigDecimal::of((string) $row['unit_price'])->toScale(2, RoundingMode::HalfUp);

        if ($unit->isNegativeOrZero()) {
            throw ValidationException::withMessages([
                'retail_items' => 'Each other item needs a price greater than zero.',
            ]);
        }

        $subtotal = $unit->multipliedBy($qty)->toScale(2, RoundingMode::HalfUp);
        $mode = RetailTaxMode::tryFrom((string) ($row['tax_mode'] ?? '')) ?? RetailTaxMode::None;

        if ($mode === RetailTaxMode::None) {
            return [
                'taxable_amount' => (string) $subtotal,
                'tax_amount' => '0.00',
                'line_amount' => (string) $subtotal,
                'tax_mode' => $mode,
                'tax_percent' => null,
            ];
        }

        $manualTax = trim((string) ($row['tax_amount'] ?? ''));

        if ($manualTax !== '') {
            $tax = BigDecimal::of($manualTax)->toScale(2, RoundingMode::HalfUp);

            if ($tax->isNegative()) {
                throw ValidationException::withMessages([
                    'retail_items' => 'GST amount cannot be negative.',
                ]);
            }

            [$taxable, $line] = $mode === RetailTaxMode::Exclusive
                ? [$subtotal, $subtotal->plus($tax)->toScale(2, RoundingMode::HalfUp)]
                : [$subtotal->minus($tax)->toScale(2, RoundingMode::HalfUp), $subtotal];

            return [
                'taxable_amount' => (string) $taxable,
                'tax_amount' => (string) $tax,
                'line_amount' => (string) $line,
                'tax_mode' => $mode,
                'tax_percent' => $this->percentLabel($subtotal, $tax, $mode),
            ];
        }

        $percentRaw = trim((string) ($row['tax_percent'] ?? ''));
        $percent = BigDecimal::of($percentRaw !== '' ? $percentRaw : ($fallbackTaxPercent ?? '0'))
            ->toScale(4, RoundingMode::HalfUp);

        if ($percent->isNegative()) {
            throw ValidationException::withMessages([
                'retail_items' => 'GST percent cannot be negative.',
            ]);
        }

        if ($percent->isZero()) {
            return [
                'taxable_amount' => (string) $subtotal,
                'tax_amount' => '0.00',
                'line_amount' => (string) $subtotal,
                'tax_mode' => $mode,
                'tax_percent' => '0.0000',
            ];
        }

        if ($mode === RetailTaxMode::Exclusive) {
            $tax = $subtotal->multipliedBy($percent)->dividedBy('100', 2, RoundingMode::HalfUp);
            $line = $subtotal->plus($tax)->toScale(2, RoundingMode::HalfUp);

            return [
                'taxable_amount' => (string) $subtotal,
                'tax_amount' => (string) $tax,
                'line_amount' => (string) $line,
                'tax_mode' => $mode,
                'tax_percent' => (string) $percent,
            ];
        }

        $tax = $subtotal->multipliedBy($percent)->dividedBy($percent->plus('100'), 2, RoundingMode::HalfUp);
        $taxable = $subtotal->minus($tax)->toScale(2, RoundingMode::HalfUp);

        return [
            'taxable_amount' => (string) $taxable,
            'tax_amount' => (string) $tax,
            'line_amount' => (string) $subtotal,
            'tax_mode' => $mode,
            'tax_percent' => (string) $percent,
        ];
    }

    private function percentLabel(BigDecimal $subtotal, BigDecimal $tax, RetailTaxMode $mode): ?string
    {
        if ($subtotal->isZero() || $tax->isZero()) {
            return null;
        }

        $derived = $mode === RetailTaxMode::Exclusive
            ? $tax->multipliedBy('100')->dividedBy($subtotal, 4, RoundingMode::HalfUp)
            : $tax->multipliedBy('100')->dividedBy($subtotal->minus($tax), 4, RoundingMode::HalfUp);

        return (string) $derived;
    }
}
