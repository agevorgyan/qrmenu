<?php

namespace App\Services\Currency;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateApiProvider implements ExchangeRateProviderInterface
{
    /**
     * Approximate baseline safety fallback rates against common base currencies
     * in case all remote network calls fail or time out.
     */
    protected const FALLBACK_RATES = [
        'AMD' => [
            'USD' => 388.50,
            'EUR' => 422.00,
            'RUB' => 4.35,
            'GEL' => 141.20,
            'GBP' => 495.00,
            'AED' => 105.80,
            'CNY' => 54.20,
            'TRY' => 11.30,
            'KZT' => 0.78,
            'IRR' => 0.0092,
        ],
        'USD' => [
            'AMD' => 0.00257,
            'EUR' => 1.085,
            'RUB' => 0.0112,
            'GEL' => 0.364,
            'GBP' => 1.275,
            'AED' => 0.272,
            'CNY' => 0.139,
            'TRY' => 0.029,
            'KZT' => 0.0020,
        ],
        'EUR' => [
            'AMD' => 0.00237,
            'USD' => 0.922,
            'RUB' => 0.0103,
            'GEL' => 0.335,
            'GBP' => 1.175,
            'AED' => 0.251,
            'CNY' => 0.128,
            'TRY' => 0.027,
            'KZT' => 0.0018,
        ],
    ];

    public function getIdentifier(): string
    {
        return 'exchangerate_api';
    }

    public function getName(): string
    {
        return 'ExchangeRate-API (Live Financial Market)';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Retrieve live or cached exchange rate.
     * Rate convention: Units of baseCurrency per 1 unit of targetCurrency.
     * Example: base='AMD', target='USD' => ~388.5 AMD per 1 USD.
     */
    public function getRate(string $baseCurrency, string $targetCurrency): ?float
    {
        $base = strtoupper(trim($baseCurrency));
        $target = strtoupper(trim($targetCurrency));

        if ($base === '' || $target === '') {
            return null;
        }

        if ($base === $target) {
            return 1.0;
        }

        $cacheKey = "exchange_rate:v3:{$base}:{$target}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($base, $target) {
            return $this->fetchLiveRate($base, $target) ?? $this->resolveFallbackRate($base, $target);
        });
    }

    /**
     * Fetch live rate from public financial APIs.
     */
    protected function fetchLiveRate(string $base, string $target): ?float
    {
        // 1. Primary endpoint: open.er-api.com with base = target
        // When base=USD, rates['AMD'] gives units of AMD per 1 USD
        try {
            $response = Http::timeout(5)->get("https://open.er-api.com/v6/latest/{$target}");
            if ($response->successful()) {
                $json = $response->json();
                if (($json['result'] ?? '') === 'success' && isset($json['rates'][$base])) {
                    $rate = (float) $json['rates'][$base];
                    if ($rate > 0 && is_finite($rate)) {
                        return $rate;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("ExchangeRateApiProvider primary lookup failed for {$base}/{$target}: ".$e->getMessage());
        }

        // 2. Secondary endpoint: open.er-api.com with base = baseCurrency (inverted)
        try {
            $response = Http::timeout(5)->get("https://open.er-api.com/v6/latest/{$base}");
            if ($response->successful()) {
                $json = $response->json();
                if (($json['result'] ?? '') === 'success' && isset($json['rates'][$target])) {
                    $targetRate = (float) $json['rates'][$target];
                    if ($targetRate > 0 && is_finite($targetRate)) {
                        return 1.0 / $targetRate;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("ExchangeRateApiProvider inverse lookup failed for {$base}/{$target}: ".$e->getMessage());
        }

        // 3. Fallback: Frankfurter API (European Central Bank data)
        try {
            $response = Http::timeout(5)->get('https://api.frankfurter.dev/v1/latest', [
                'from' => $target,
                'to' => $base,
            ]);
            if ($response->successful()) {
                $json = $response->json();
                if (isset($json['rates'][$base])) {
                    $rate = (float) $json['rates'][$base];
                    if ($rate > 0 && is_finite($rate)) {
                        return $rate;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("ExchangeRateApiProvider frankfurter fallback failed for {$base}/{$target}: ".$e->getMessage());
        }

        return null;
    }

    /**
     * Resolve safety fallback rate when network is completely offline.
     */
    protected function resolveFallbackRate(string $base, string $target): ?float
    {
        if (isset(self::FALLBACK_RATES[$base][$target])) {
            return (float) self::FALLBACK_RATES[$base][$target];
        }

        if (isset(self::FALLBACK_RATES[$target][$base]) && self::FALLBACK_RATES[$target][$base] > 0) {
            return (float) (1.0 / self::FALLBACK_RATES[$target][$base]);
        }

        return null;
    }
}
