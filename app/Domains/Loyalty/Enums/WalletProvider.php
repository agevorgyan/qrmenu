<?php

namespace App\Domains\Loyalty\Enums;

enum WalletProvider: string
{
    case Apple = 'apple';
    case Google = 'google';

    public function label(): string
    {
        return match ($this) {
            self::Apple => 'Apple Wallet',
            self::Google => 'Google Wallet',
        };
    }
}
