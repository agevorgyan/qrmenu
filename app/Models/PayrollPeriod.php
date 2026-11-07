<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'payroll_periods';

    protected $fillable = [
        'vendor_id',
        'name',
        'cycle_type',
        'start_date',
        'end_date',
        'cutoff_date',
        'currency',
        'total_gross',
        'total_net',
        'total_tax',
        'total_deductions',
        'total_paid',
        'status',
        'approved_by',
        'approved_at',
        'locked_by',
        'locked_at',
        'snapshot_rules',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'cutoff_date' => 'date',
            'total_gross' => 'decimal:2',
            'total_net' => 'decimal:2',
            'total_tax' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'approved_at' => 'datetime',
            'locked_at' => 'datetime',
            'snapshot_rules' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class, 'payroll_period_id');
    }

    public function payrollItems(): HasMany
    {
        return $this->items();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class, 'payroll_period_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'paid', 'locked'], true);
    }

    public function getPeriodNameAttribute(): string
    {
        return $this->name;
    }
}
