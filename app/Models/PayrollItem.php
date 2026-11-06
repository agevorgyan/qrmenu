<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollItem extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'payroll_items';

    protected $fillable = [
        'payroll_period_id',
        'vendor_id',
        'user_id',
        'base_salary',
        'worked_days',
        'worked_hours',
        'regular_pay',
        'overtime_hours',
        'overtime_pay',
        'night_hours',
        'night_pay',
        'holiday_pay',
        'bonuses_total',
        'allowances_total',
        'gross_salary',
        'income_tax',
        'pension_fee',
        'stamp_fee',
        'other_deductions',
        'advance_deduction',
        'total_deductions',
        'net_salary',
        'paid_amount',
        'payment_status',
        'calculation_breakdown',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'worked_days' => 'decimal:2',
            'worked_hours' => 'decimal:2',
            'regular_pay' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'overtime_pay' => 'decimal:2',
            'night_hours' => 'decimal:2',
            'night_pay' => 'decimal:2',
            'holiday_pay' => 'decimal:2',
            'bonuses_total' => 'decimal:2',
            'allowances_total' => 'decimal:2',
            'gross_salary' => 'decimal:2',
            'income_tax' => 'decimal:2',
            'pension_fee' => 'decimal:2',
            'stamp_fee' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'advance_deduction' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'calculation_breakdown' => 'array',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(PayrollItemComponent::class, 'payroll_item_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class, 'payroll_item_id');
    }

    public function getPendingBalanceAttribute(): float
    {
        return max(0.00, round((float) $this->net_salary - (float) $this->paid_amount, 2));
    }

    public function getGrossPayAttribute(): float
    {
        return (float) $this->gross_salary;
    }

    public function getNetPayAttribute(): float
    {
        return (float) $this->net_salary;
    }

    public function getBasePayAttribute(): float
    {
        return (float) $this->base_salary;
    }

    public function getPensionContributionAttribute(): float
    {
        return (float) $this->pension_fee;
    }

    public function getMilitaryStampDutyAttribute(): float
    {
        return (float) $this->stamp_fee;
    }

    public function getAdvanceDeductionsAttribute(): float
    {
        return (float) $this->advance_deduction;
    }

    public function getRegularHoursAttribute(): float
    {
        return (float) $this->worked_hours;
    }

    public function getBonusesAttribute(): float
    {
        return (float) $this->bonuses_total;
    }
}
