<?php

namespace App\Domains\Loyalty\Enums;

enum RewardType: string
{
    case FreeProduct = 'free_product';
    case FixedDiscount = 'fixed_discount';
    case PercentageDiscount = 'percentage_discount';
    case Voucher = 'voucher';

    public function label(): string
    {
        return match ($this) {
            self::FreeProduct => 'Free Menu Item',
            self::FixedDiscount => 'Fixed Amount Discount',
            self::PercentageDiscount => 'Percentage Discount',
            self::Voucher => 'Special Voucher',
        };
    }
}
