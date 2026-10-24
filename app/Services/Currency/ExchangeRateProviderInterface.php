<?php

namespace App\Services\Currency;

interface ExchangeRateProviderInterface
{
    /**
     * Get the unique provider identifier (e.g., 'manual', 'cba', 'open_exchange_rates').
     */
    public function getIdentifier(): string;

    /**
     * Get the human-readable display name.
     */
    public function getName(): string;

    /**
     * Check whether this provider is configured and available.
     */
    public function isAvailable(): bool;

    /**
     * Retrieve the exchange rate between base currency and target currency.
     * Rate convention: Number of base currency units per 1 unit of target currency.
     * Example: base='AMD', target='USD', rate=350.0 means 1 USD = 350 AMD.
     *
     * @return float|null Positive float rate or null if unavailable / failed
     */
    public function getRate(string $baseCurrency, string $targetCurrency): ?float;
}
