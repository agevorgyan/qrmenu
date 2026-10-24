<?php

namespace App\Services\Currency;

class CurrencyExchangeService
{
    public const SUPPORTED_CURRENCIES = [
        'AMD' => ['name' => 'Armenian Dram', 'symbol' => '֏', 'symbol_position' => 'after'],
        'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'symbol_position' => 'before'],
        'EUR' => ['name' => 'Euro', 'symbol' => '€', 'symbol_position' => 'before'],
        'RUB' => ['name' => 'Russian Ruble', 'symbol' => '₽', 'symbol_position' => 'after'],
        'GEL' => ['name' => 'Georgian Lari', 'symbol' => '₾', 'symbol_position' => 'after'],
        'GBP' => ['name' => 'British Pound', 'symbol' => '£', 'symbol_position' => 'before'],
        'AED' => ['name' => 'UAE Dirham', 'symbol' => 'د.إ', 'symbol_position' => 'after'],
        'CNY' => ['name' => 'Chinese Yuan', 'symbol' => '¥', 'symbol_position' => 'before'],
        'TRY' => ['name' => 'Turkish Lira', 'symbol' => '₺', 'symbol_position' => 'before'],
        'KZT' => ['name' => 'Kazakhstani Tenge', 'symbol' => '₸', 'symbol_position' => 'after'],
        'IRR' => ['name' => 'Iranian Rial', 'symbol' => '﷼', 'symbol_position' => 'after'],
    ];

    /**
     * Minimum and maximum safe rate bounds.
     */
    public const MIN_EXCHANGE_RATE = 0.000001;

    public const MAX_EXCHANGE_RATE = 100000000.0;

    /**
     * @var array<string, ExchangeRateProviderInterface>
     */
    protected array $providers = [];

    public function __construct()
    {
        $this->registerProvider(new ManualExchangeRateProvider);
    }

    /**
     * Register an exchange rate provider.
     */
    public function registerProvider(ExchangeRateProviderInterface $provider): void
    {
        $this->providers[$provider->getIdentifier()] = $provider;
    }

    /**
     * Get a registered provider by identifier.
     */
    public function getProvider(string $identifier): ?ExchangeRateProviderInterface
    {
        return $this->providers[$identifier] ?? null;
    }

    /**
     * Get all registered providers.
     *
     * @return array<string, ExchangeRateProviderInterface>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Validate whether an exchange rate value is a positive, finite decimal within safety bounds.
     */
    public function isValidRate(mixed $rate): bool
    {
        if ($rate === null || $rate === '') {
            return false;
        }

        if (! is_numeric($rate)) {
            return false;
        }

        $val = (float) $rate;

        return is_finite($val) && ! is_nan($val) && $val >= self::MIN_EXCHANGE_RATE && $val <= self::MAX_EXCHANGE_RATE;
    }

    /**
     * Calculate secondary price from primary price and exchange rate.
     * Formula: secondary_price = primary_price / exchange_rate
     *
     * Example:
     * Primary price: 3,500 AMD
     * Exchange rate: 1 USD = 350 AMD
     * secondary_price = 3,500 / 350 = 10.00 USD
     *
     * @param  float|int|string|null  $primaryPrice
     * @param  float|int|string|null  $exchangeRate
     */
    public function convert(mixed $primaryPrice, mixed $exchangeRate, int $precision = 2): ?float
    {
        if ($primaryPrice === null || $primaryPrice === '') {
            return null;
        }

        if (! is_numeric($primaryPrice) || ! $this->isValidRate($exchangeRate)) {
            return null;
        }

        $price = (float) $primaryPrice;
        $rate = (float) $exchangeRate;

        if ($price <= 0 || ! is_finite($price) || is_nan($price)) {
            return null;
        }

        $decimals = max(0, min(4, $precision));

        return round($price / $rate, $decimals);
    }

    /**
     * Format a converted amount with appropriate symbol, prefix, and precision.
     */
    public function formatAmount(
        float $amount,
        string $currency,
        int $precision = 2,
        string $prefix = '≈'
    ): string {
        $currencyCode = strtoupper(trim($currency));
        $decimals = max(0, min(4, $precision));
        $formattedNum = number_format($amount, $decimals, '.', ',');
        $cleanPrefix = trim($prefix);

        $currencyMeta = self::SUPPORTED_CURRENCIES[$currencyCode] ?? null;
        $symbol = $currencyMeta['symbol'] ?? $currencyCode;
        $position = $currencyMeta['symbol_position'] ?? 'after';

        $priceWithCurrency = match ($position) {
            'before' => "{$symbol}{$formattedNum}",
            default => "{$formattedNum} {$symbol}",
        };

        if ($cleanPrefix !== '') {
            return "{$cleanPrefix} {$priceWithCurrency}";
        }

        return $priceWithCurrency;
    }

    /**
     * Convert and format primary price into secondary price string.
     */
    public function formatSecondaryPrice(
        mixed $primaryPrice,
        string $secondaryCurrency,
        mixed $exchangeRate,
        int $precision = 2,
        string $prefix = '≈'
    ): ?string {
        $converted = $this->convert($primaryPrice, $exchangeRate, $precision);

        if ($converted === null) {
            return null;
        }

        return $this->formatAmount($converted, $secondaryCurrency, $precision, $prefix);
    }

    /**
     * Retrieve supported currencies with labels.
     *
     * @return array<string, string>
     */
    public function getSupportedCurrencyOptions(): array
    {
        $options = [];
        foreach (self::SUPPORTED_CURRENCIES as $code => $meta) {
            $options[$code] = "{$code} ({$meta['symbol']} - {$meta['name']})";
        }

        return $options;
    }

    /**
     * Check if a currency code is supported.
     */
    public function isCurrencySupported(string $currency): bool
    {
        return array_key_exists(strtoupper(trim($currency)), self::SUPPORTED_CURRENCIES);
    }
}
