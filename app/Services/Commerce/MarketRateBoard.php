<?php

namespace App\Services\Commerce;

use App\Models\Company;
use App\Models\Purity;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class MarketRateBoard
{
    public function __construct(
        private readonly RateBook $rates,
    ) {}

    /**
     * @return list<array{purity: Purity, rate: string, selected: bool}>
     */
    public function suggestions(MetalMarketQuote $quote): array
    {
        $reference = (string) config('metal-prices.reference_fineness');

        return Purity::query()
            ->with('metalType')
            ->where('is_active', true)
            ->whereHas('metalType', fn ($query) => $query->whereIn('code', ['GOLD', 'SILVER'])->where('is_active', true))
            ->get()
            ->sortBy([
                fn (Purity $purity) => $purity->metalType?->code === 'GOLD' ? 0 : 1,
                fn (Purity $purity) => -1 * (float) $purity->fineness,
            ])
            ->map(function (Purity $purity) use ($quote, $reference): array {
                $base = $purity->metalType?->code === 'SILVER' ? $quote->silverPerGram : $quote->goldPerGram;

                return [
                    'purity' => $purity,
                    'rate' => (string) BigDecimal::of($base)
                        ->multipliedBy((string) $purity->fineness)
                        ->dividedBy($reference, 2, RoundingMode::HalfUp),
                    'selected' => in_array($purity->code, ['24K', '22K', '18K', '999'], true),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array{purity_uuid: string, rate_per_gram: numeric-string|int|float}>  $lines
     */
    public function save(Company $company, array $lines, ?int $userId = null): int
    {
        $saved = 0;

        foreach ($lines as $line) {
            $purity = Purity::query()->with('metalType')->where('uuid', $line['purity_uuid'])->first();

            if (! $purity || ! in_array($purity->metalType?->code, ['GOLD', 'SILVER'], true)) {
                throw ValidationException::withMessages([
                    'lines' => 'Choose a gold or silver purity from this shop.',
                ]);
            }

            $this->rates->record($company, [
                'metal_uuid' => $purity->metalType->uuid,
                'purity_uuid' => $purity->uuid,
                'rate_per_gram' => $line['rate_per_gram'],
                'source' => 'market',
                'note' => 'Market quote. Amount saved by the shop.',
            ], $userId);

            $saved++;
        }

        return $saved;
    }
}
