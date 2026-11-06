<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrPosition extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'hr_positions';

    protected $fillable = [
        'vendor_id',
        'department_id',
        'title',
        'description',
        'default_pay_type',
        'default_pay_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_pay_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrDepartment::class, 'department_id');
    }

    public function employeeProfiles(): HasMany
    {
        return $this->hasMany(EmployeeProfile::class, 'position_id');
    }
}
