<?php

namespace App\Domains\Loyalty\Enums;

enum WalletIssuerMode: string
{
    case Platform = 'platform';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Platform => 'Centralized Platform Issuer (eLab)',
            self::Custom => 'Individual Restaurant Issuer',
        };
    }
}
