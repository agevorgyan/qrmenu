<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryHistory extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'employee_salary_histories';

    protected $fillable = [
        'vendor_id',
        'user_id',
        'compensation_type',
        'base_salary',
        'hourly_rate',
        'effective_from',
        'effective_until',
        'reason',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getEffectiveDateAttribute(): mixed
    {
        return $this->effective_from;
    }

    public function getSalaryTypeAttribute(): ?string
    {
        return $this->compensation_type;
    }

    public function getChangeReasonAttribute(): ?string
    {
        return $this->reason;
    }
}
