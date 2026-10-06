<?php

namespace App\Services\Foundation;

use App\Models\Company;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class NumberFormatService
{
    public function __construct(private readonly SettingService $settings) {}

    public function money(string $amount, ?Company $company = null): string
    {
        $company ??= app(CompanyContext::class)->company();

        return $this->format($amount, [
            'scale' => (int) $this->settings->get('currency.decimal_places', $company),
            'decimal_separator' => (string) $this->settings->get('currency.decimal_separator', $company),
            'thousand_separator' => (string) ($this->settings->get('currency.thousand_separator', $company) ?? ''),
            'grouping' => (string) $this->settings->get('number.grouping', $company),
            'symbol' => (string) $this->settings->get('currency.symbol', $company),
            'symbol_position' => (string) $this->settings->get('currency.symbol_position', $company),
        ]);
    }

    public function weight(string $amount, ?Company $company = null): string
    {
        $company ??= app(CompanyContext::class)->company();

        return $this->format($amount, [
            'scale' => (int) $this->settings->get('number.weight_decimal_places', $company),
            'decimal_separator' => (string) $this->settings->get('number.decimal_separator', $company),
            'thousand_separator' => (string) ($this->settings->get('number.thousand_separator', $company) ?? ''),
            'grouping' => (string) $this->settings->get('number.grouping', $company),
            'suffix' => ' g',
        ]);
    }

    /**
     * @param  array{
     *     scale: int,
     *     decimal_separator: string,
     *     thousand_separator?: string,
     *     grouping?: string,
     *     symbol?: string,
     *     symbol_position?: string,
     *     suffix?: string
     * }  $options
     */
    public function format(string $amount, array $options): string
    {
        $scale = $options['scale'];
        $decimalSeparator = $options['decimal_separator'];
        $thousandSeparator = $options['thousand_separator'] ?? '';
        $grouping = $options['grouping'] ?? 'international';
        $negative = str_starts_with(trim($amount), '-');
        $decimal = BigDecimal::of(trim($amount))->abs()->toScale($scale, RoundingMode::HalfUp);
        $plain = (string) $decimal;
        [$whole, $fraction] = array_pad(explode('.', $plain, 2), 2, '');
        $whole = $this->groupDigits($whole, $thousandSeparator, $grouping);
        $number = $scale > 0 ? $whole.$decimalSeparator.$fraction : $whole;
        $symbol = $options['symbol'] ?? '';
        $position = $options['symbol_position'] ?? 'before';

        if ($symbol !== '') {
            $number = $position === 'after'
                ? $number.' '.$symbol
                : $symbol.' '.$number;
        }

        if ($negative) {
            $number = '-'.$number;
        }

        if (! empty($options['suffix'])) {
            $number .= $options['suffix'];
        }

        return $number;
    }

    private function groupDigits(string $whole, string $separator, string $grouping): string
    {
        if ($separator === '' || strlen($whole) <= 3) {
            return $whole;
        }

        if ($grouping === 'indian') {
            $head = substr($whole, 0, -3);
            $tail = substr($whole, -3);
            $head = preg_replace('/\B(?=(\d{2})+(?!\d))/', $separator, $head) ?? $head;

            return $head.$separator.$tail;
        }

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', $separator, $whole) ?? $whole;
    }
}
