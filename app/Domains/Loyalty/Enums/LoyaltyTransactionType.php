<?php

namespace App\Domains\Loyalty\Enums;

enum LoyaltyTransactionType: string
{
    case Earned = 'earned';
    case Redeemed = 'redeemed';
    case Expired = 'expired';
    case Reversal = 'reversal';
    case ManualAdjustment = 'manual_adjustment';
    case ReferralBonus = 'referral_bonus';

    public function label(): string
    {
        return match ($this) {
            self::Earned => 'Points Earned',
            self::Redeemed => 'Points Redeemed',
            self::Expired => 'Points Expired',
            self::Reversal => 'Points Reversal',
            self::ManualAdjustment => 'Manual Adjustment',
            self::ReferralBonus => 'Referral Bonus',
        };
    }

    public function isCredit(): bool
    {
        return in_array($this, [self::Earned, self::ReferralBonus], true);
    }
}
