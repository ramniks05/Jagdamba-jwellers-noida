<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class StoneRate
{
    public const UNITS = ['gram', 'carat', 'fixed'];

    public const CARATS_PER_GRAM = 5;

    /**
     * @param  array<string, mixed>  $stone
     * @return array{name: string, weight: string, value: string, rate: ?string, rate_unit: ?string}
     */
    public static function row(array $stone): array
    {
        $weight = BigDecimal::of((string) ($stone['weight'] ?? '0'))->toScale(3, RoundingMode::HalfUp);
        $unit = in_array($stone['rate_unit'] ?? null, self::UNITS, true) ? $stone['rate_unit'] : null;
        $rateText = trim((string) ($stone['rate'] ?? ''));
        $rate = $unit !== null && $unit !== 'fixed' && $rateText !== '' ? BigDecimal::of($rateText)->toScale(2, RoundingMode::HalfUp) : null;
        $value = $rate !== null
            ? $weight->multipliedBy($unit === 'carat' ? self::CARATS_PER_GRAM : 1)->multipliedBy($rate)->toScale(2, RoundingMode::HalfUp)
            : BigDecimal::of((string) ($stone['value'] ?? '0'))->toScale(2, RoundingMode::HalfUp);

        return [
            'name' => trim((string) ($stone['name'] ?? '')),
            'weight' => (string) $weight,
            'value' => (string) $value,
            'rate' => $rate !== null ? (string) $rate : null,
            'rate_unit' => $rate !== null ? $unit : null,
        ];
    }

    public static function carats(string $weight): string
    {
        return (string) BigDecimal::of($weight)->multipliedBy(self::CARATS_PER_GRAM)->toScale(2, RoundingMode::HalfUp);
    }
}
