<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePositionHistory extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'employee_position_histories';

    protected $fillable = [
        'vendor_id',
        'user_id',
        'department_id',
        'position_id',
        'location_id',
        'effective_from',
        'effective_until',
        'reason',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
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

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function getStartDateAttribute(): mixed
    {
        return $this->effective_from;
    }

    public function getEndDateAttribute(): mixed
    {
        return $this->effective_until;
    }

    public function getChangeReasonAttribute(): ?string
    {
        return $this->reason;
    }
}
