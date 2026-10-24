<?php

namespace App\Services\Currency;

class ManualExchangeRateProvider implements ExchangeRateProviderInterface
{
    public function getIdentifier(): string
    {
        return 'manual';
    }

    public function getName(): string
    {
        return 'Ձեռքով մուտքագրում (Manual Entry)';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getRate(string $baseCurrency, string $targetCurrency): ?float
    {
        return null;
    }
}
