<?php

namespace App\Services\Commerce;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MetalPriceFeed
{
    public function current(): MetalMarketQuote
    {
        /** @var array{gold: string, silver: string, quoted_at: string} $payload */
        $payload = Cache::remember($this->cacheKey(), now()->addMinutes((int) config('metal-prices.cache_minutes')), function (): array {
            return $this->fetch();
        });

        return new MetalMarketQuote($payload['gold'], $payload['silver'], $payload['quoted_at']);
    }

    public function refresh(): MetalMarketQuote
    {
        Cache::forget($this->cacheKey());

        return $this->current();
    }

    /**
     * @return array{gold: string, silver: string, quoted_at: string}
     */
    private function fetch(): array
    {
        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get((string) config('metal-prices.url'));
        } catch (\Throwable $exception) {
            throw new MarketPriceUnavailable('The market price could not be fetched.', 0, $exception);
        }

        if (! $response->successful()) {
            throw new MarketPriceUnavailable('The market price could not be fetched.');
        }

        $gold = $response->json('data.gold');
        $silver = $response->json('data.silver');

        if (! is_array($gold) || ! is_array($silver)) {
            throw new MarketPriceUnavailable('The market price could not be read.');
        }

        return [
            'gold' => $this->perGram($gold, 'Gold'),
            'silver' => $this->perGram($silver, 'Silver'),
            'quoted_at' => (string) ($response->json('data.timestamp') ?: now()->toIso8601String()),
        ];
    }

    /**
     * @param  array<string, mixed>  $metal
     */
    private function perGram(array $metal, string $name): string
    {
        $price = $metal['buy'] ?? null;
        $currency = $metal['currency'] ?? null;
        $unit = $metal['unit'] ?? null;

        if (! is_numeric($price) || (float) $price <= 0 || $currency !== 'INR' || $unit !== 'gram') {
            throw new MarketPriceUnavailable($name.' price was not in rupees per gram.');
        }

        return number_format((float) $price, 2, '.', '');
    }

    private function cacheKey(): string
    {
        return 'metal-market-quote';
    }
}
