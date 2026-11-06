<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrPayrollSetting extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'hr_payroll_settings';

    protected $fillable = [
        'vendor_id',
        'country_code',
        'income_tax_rate',
        'pension_rate',
        'pension_ceiling_gross',
        'stamp_fee_tiers',
        'overtime_rate_multiplier',
        'night_rate_multiplier',
        'holiday_rate_multiplier',
        'standard_monthly_hours',
        'standard_weekly_hours',
        'effective_from',
        'effective_until',
    ];

    protected function casts(): array
    {
        return [
            'income_tax_rate' => 'decimal:4',
            'pension_rate' => 'decimal:4',
            'pension_ceiling_gross' => 'decimal:2',
            'overtime_rate_multiplier' => 'decimal:2',
            'night_rate_multiplier' => 'decimal:2',
            'holiday_rate_multiplier' => 'decimal:2',
            'standard_monthly_hours' => 'integer',
            'standard_weekly_hours' => 'integer',
            'stamp_fee_tiers' => 'array',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    /**
     * Return default Armenian stamp fee tiers if null.
     */
    public function getResolvedStampFeeTiers(): array
    {
        return $this->stamp_fee_tiers ?? [
            ['min' => 0, 'max' => 100000, 'fee' => 1500],
            ['min' => 100001, 'max' => 200000, 'fee' => 3000],
            ['min' => 200001, 'max' => 500000, 'fee' => 5500],
            ['min' => 500001, 'max' => 1000000, 'fee' => 8500],
            ['min' => 1000001, 'max' => null, 'fee' => 15000],
        ];
    }
}
