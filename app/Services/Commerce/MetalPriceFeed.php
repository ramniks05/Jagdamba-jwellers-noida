<?php

namespace App\Services\Commerce;

use Illuminate\Http\Client\ConnectionException;
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
        $payload = $this->payload((string) config('metal-prices.url'));
        $gold = $payload['data']['gold'] ?? null;
        $silver = $payload['data']['silver'] ?? null;

        if (! is_array($gold) || ! is_array($silver)) {
            throw new MarketPriceUnavailable('The market price could not be read.');
        }

        return [
            'gold' => $this->perGram($gold, 'Gold'),
            'silver' => $this->perGram($silver, 'Silver'),
            'quoted_at' => (string) ($payload['data']['timestamp'] ?? now()->toIso8601String()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $url): array
    {
        try {
            $response = Http::timeout(8)->acceptJson()->get($url);
        } catch (ConnectionException $exception) {
            if (! $this->missingCertificate($exception)) {
                throw new MarketPriceUnavailable('The market price could not be fetched.', 0, $exception);
            }

            $response = null;
        } catch (\Throwable $exception) {
            throw new MarketPriceUnavailable('The market price could not be fetched.', 0, $exception);
        }

        $body = $response === null ? $this->bodyWithSystemTrust($url) : $response->body();

        if ($response !== null && ! $response->successful()) {
            throw new MarketPriceUnavailable('The market price could not be fetched.');
        }

        $payload = json_decode($body, true);

        return is_array($payload) ? $payload : [];
    }

    /**
     * Windows PHP often has no curl CA bundle, so the market call fails before it is read.
     * The system certificate store can still verify the same address.
     */
    private function bodyWithSystemTrust(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'header' => "Accept: application/json\r\n",
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $status = 0;

        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match) === 1) {
                $status = (int) $match[1];
            }
        }

        if (! is_string($body) || $body === '' || $status < 200 || $status >= 300) {
            throw new MarketPriceUnavailable('The market price could not be fetched.');
        }

        return $body;
    }

    private function missingCertificate(\Throwable $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'SSL certificate')
            || str_contains($message, 'error 60')
            || str_contains($message, 'unable to get local issuer certificate');
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
