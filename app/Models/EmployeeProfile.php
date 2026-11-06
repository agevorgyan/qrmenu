<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeProfile extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'employee_profiles';

    protected $fillable = [
        'user_id',
        'vendor_id',
        'department_id',
        'position_id',
        'manager_id',
        'national_id_ssn',
        'date_of_birth',
        'gender',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'internal_employee_code',
        'employment_status',
        'employment_type',
        'hired_at',
        'probation_ends_at',
        'terminated_at',
        'termination_reason',
        'compensation_type',
        'base_salary',
        'hourly_rate',
        'bank_name',
        'bank_account_number',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'hired_at' => 'date',
            'probation_ends_at' => 'date',
            'terminated_at' => 'date',
            'base_salary' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrDepartment::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(HrPosition::class, 'position_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function positionHistories(): HasMany
    {
        return $this->hasMany(EmployeePositionHistory::class, 'user_id', 'user_id');
    }

    public function salaryHistories(): HasMany
    {
        return $this->hasMany(EmployeeSalaryHistory::class, 'user_id', 'user_id');
    }

    public function isActive(): bool
    {
        return $this->employment_status === 'active';
    }

    public function getEmployeeCodeAttribute(): ?string
    {
        return $this->internal_employee_code;
    }

    public function getSalaryTypeAttribute(): ?string
    {
        return $this->compensation_type;
    }

    public function getBirthDateAttribute(): mixed
    {
        return $this->date_of_birth;
    }
}
