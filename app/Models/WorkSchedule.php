<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkSchedule extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'work_schedules';

    protected $fillable = [
        'vendor_id',
        'location_id',
        'department_id',
        'name',
        'schedule_type',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrDepartment::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(WorkShift::class, 'schedule_id');
    }
}
