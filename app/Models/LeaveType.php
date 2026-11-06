<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'leave_types';

    protected $fillable = [
        'vendor_id',
        'name',
        'code',
        'is_paid',
        'default_days_per_year',
        'requires_approval',
        'requires_attachment',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'requires_approval' => 'boolean',
            'requires_attachment' => 'boolean',
            'is_active' => 'boolean',
            'default_days_per_year' => 'integer',
        ];
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'leave_type_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(EmployeeLeaveBalance::class, 'leave_type_id');
    }
}
