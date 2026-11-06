<?php

namespace App\Domains\Loyalty\Enums;

enum LoyaltyTierLevel: int
{
    case Bronze = 1;
    case Silver = 2;
    case Gold = 3;
    case Platinum = 4;

    public function label(): string
    {
        return match ($this) {
            self::Bronze => 'Bronze',
            self::Silver => 'Silver',
            self::Gold => 'Gold',
            self::Platinum => 'Platinum',
        };
    }

    public function defaultColor(): string
    {
        return match ($this) {
            self::Bronze => '#cd7f32',
            self::Silver => '#94a3b8',
            self::Gold => '#eab308',
            self::Platinum => '#6366f1',
        };
    }
}
