<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrDepartment extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'hr_departments';

    protected $fillable = [
        'vendor_id',
        'name',
        'description',
        'manager_id',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(HrPosition::class, 'department_id');
    }

    public function employeeProfiles(): HasMany
    {
        return $this->hasMany(EmployeeProfile::class, 'department_id');
    }
}
