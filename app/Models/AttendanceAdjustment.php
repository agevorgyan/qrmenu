<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceAdjustment extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'attendance_adjustments';

    protected $fillable = [
        'vendor_id',
        'attendance_record_id',
        'adjusted_by',
        'original_check_in',
        'original_check_out',
        'adjusted_check_in',
        'adjusted_check_out',
        'reason',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'original_check_in' => 'datetime',
            'original_check_out' => 'datetime',
            'adjusted_check_in' => 'datetime',
            'adjusted_check_out' => 'datetime',
        ];
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }

    public function adjuster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
