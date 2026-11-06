<?php

namespace App\Domains\Loyalty\Enums;

enum CampaignTriggerType: string
{
    case Welcome = 'welcome';
    case Birthday = 'birthday';
    case FirstOrder = 'first_order';
    case InactiveCustomer = 'inactive_customer';
    case Seasonal = 'seasonal';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Welcome => 'Welcome Enrollment',
            self::Birthday => 'Birthday Celebration',
            self::FirstOrder => 'First Completed Order',
            self::InactiveCustomer => 'Customer Re-engagement',
            self::Seasonal => 'Seasonal Promotion',
            self::Custom => 'Custom Marketing Campaign',
        };
    }
}
