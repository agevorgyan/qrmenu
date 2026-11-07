<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkShift extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'work_shifts';

    protected $fillable = [
        'vendor_id',
        'schedule_id',
        'location_id',
        'user_id',
        'shift_date',
        'start_time',
        'end_time',
        'cross_midnight',
        'unpaid_break_minutes',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
            'cross_midnight' => 'boolean',
            'unpaid_break_minutes' => 'integer',
        ];
    }

    /**
     * Get the shift date formatted as a Y-m-d string.
     */
    public function getShiftDateString(): string
    {
        if ($this->shift_date instanceof DateTimeInterface) {
            return $this->shift_date->format('Y-m-d');
        }

        $dateStr = (string) $this->shift_date;

        return str_contains($dateStr, ' ') ? explode(' ', $dateStr)[0] : $dateStr;
    }

    /**
     * Get the scheduled start datetime.
     */
    public function getStartDateTime(?string $timezone = null): Carbon
    {
        $tz = $timezone ?? $this->vendor?->getTimezone() ?? config('app.timezone', 'UTC');

        return Carbon::parse("{$this->getShiftDateString()} {$this->start_time}", $tz);
    }

    /**
     * Get the scheduled end datetime, accounting for overnight/cross-midnight shifts.
     */
    public function getEndDateTime(?string $timezone = null): Carbon
    {
        $tz = $timezone ?? $this->vendor?->getTimezone() ?? config('app.timezone', 'UTC');

        $end = Carbon::parse("{$this->getShiftDateString()} {$this->end_time}", $tz);
        if ($this->cross_midnight) {
            $end->addDay();
        }

        return $end;
    }

    /**
     * Get scheduled duration in minutes minus unpaid breaks.
     */
    public function getScheduledDurationMinutes(): int
    {
        $start = $this->getStartDateTime();
        $end = $this->getEndDateTime();

        return max(0, $start->diffInMinutes($end) - (int) $this->unpaid_break_minutes);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'schedule_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'shift_id');
    }
}
