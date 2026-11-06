<?php

namespace App\Domains\Loyalty\Enums;

enum StampStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Redeemed = 'redeemed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'In Progress',
            self::Completed => 'Reward Ready',
            self::Redeemed => 'Reward Redeemed',
            self::Expired => 'Expired',
        };
    }
}
