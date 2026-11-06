<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Timesheet extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'timesheets';

    protected $fillable = [
        'vendor_id',
        'location_id',
        'user_id',
        'period_start',
        'period_end',
        'scheduled_hours',
        'worked_hours',
        'regular_hours',
        'overtime_hours',
        'night_hours',
        'holiday_hours',
        'paid_leave_hours',
        'unpaid_leave_hours',
        'status',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'scheduled_hours' => 'decimal:2',
            'worked_hours' => 'decimal:2',
            'regular_hours' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'night_hours' => 'decimal:2',
            'holiday_hours' => 'decimal:2',
            'paid_leave_hours' => 'decimal:2',
            'unpaid_leave_hours' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'locked'], true);
    }
}
