<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRecord extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'attendance_records';

    protected $fillable = [
        'vendor_id',
        'location_id',
        'user_id',
        'shift_id',
        'check_in_at',
        'check_out_at',
        'break_start_at',
        'break_end_at',
        'total_break_minutes',
        'source',
        'check_in_latitude',
        'check_in_longitude',
        'status',
        'late_minutes',
        'early_departure_minutes',
        'is_verified',
        'verified_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'break_start_at' => 'datetime',
            'break_end_at' => 'datetime',
            'total_break_minutes' => 'integer',
            'late_minutes' => 'integer',
            'early_departure_minutes' => 'integer',
            'is_verified' => 'boolean',
            'check_in_latitude' => 'decimal:7',
            'check_in_longitude' => 'decimal:7',
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

    public function shift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'shift_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(AttendanceAdjustment::class, 'attendance_record_id');
    }

    /**
     * Calculate worked minutes excluding breaks.
     */
    public function getNetWorkedMinutesAttribute(): int
    {
        if (! $this->check_out_at || ! $this->check_in_at) {
            return 0;
        }

        $totalMinutes = (int) round(abs($this->check_out_at->getTimestamp() - $this->check_in_at->getTimestamp()) / 60);

        return max(0, $totalMinutes - (int) $this->total_break_minutes);
    }

    /**
     * Calculate worked hours as decimal.
     */
    public function getNetWorkedHoursAttribute(): float
    {
        return round($this->net_worked_minutes / 60, 2);
    }

    /**
     * Total hours alias.
     */
    public function getTotalHoursAttribute(): float
    {
        return $this->net_worked_hours;
    }

    public function getIsLateAttribute(): bool
    {
        return (int) $this->late_minutes > 0;
    }

    public function getPunchMethodAttribute(): ?string
    {
        return $this->source;
    }

    public function localCheckInAt(?Vendor $vendor = null): ?Carbon
    {
        return $this->check_in_at?->toVendorTimezone($vendor ?? $this->vendor);
    }

    public function localCheckOutAt(?Vendor $vendor = null): ?Carbon
    {
        return $this->check_out_at?->toVendorTimezone($vendor ?? $this->vendor);
    }
}
