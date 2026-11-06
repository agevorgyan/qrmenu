<?php

namespace App\Services;

use App\Models\Location;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HrScheduleService
{
    /**
     * Create or update a work shift with overlap validation.
     */
    public function createShift(
        Vendor $vendor,
        User $user,
        Location $location,
        Carbon $shiftDate,
        string $startTime,
        string $endTime,
        bool $crossMidnight = false,
        int $breakMinutes = 30,
        ?int $scheduleId = null,
        ?User $creator = null
    ): WorkShift {
        // Overlap validation for same user on same shift date
        $existing = WorkShift::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('user_id', $user->id)
            ->where('shift_date', $shiftDate->toDateString())
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->first();

        if ($existing) {
            throw new InvalidArgumentException(__('Employee already has a scheduled shift on this date.'));
        }

        return WorkShift::create([
            'vendor_id' => $vendor->id,
            'schedule_id' => $scheduleId,
            'location_id' => $location->id,
            'user_id' => $user->id,
            'shift_date' => $shiftDate->toDateString(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'cross_midnight' => $crossMidnight,
            'unpaid_break_minutes' => $breakMinutes,
            'status' => 'scheduled',
            'created_by' => $creator?->id,
        ]);
    }

    /**
     * Duplicate an entire week's schedule and shifts to the following week.
     */
    public function duplicateWeekRoster(
        Vendor $vendor,
        Carbon $sourceWeekStart,
        Carbon $targetWeekStart,
        ?Location $location = null
    ): int {
        $sourceStart = $sourceWeekStart->copy()->startOfWeek();
        $sourceEnd = $sourceWeekStart->copy()->endOfWeek();
        $targetStart = $targetWeekStart->copy()->startOfWeek();

        $query = WorkShift::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->whereBetween('shift_date', [$sourceStart->toDateString(), $sourceEnd->toDateString()])
            ->whereIn('status', ['scheduled', 'confirmed']);

        if ($location) {
            $query->where('location_id', $location->id);
        }

        $shifts = $query->get();
        $copiedCount = 0;

        DB::transaction(function () use ($shifts, $sourceStart, $targetStart, &$copiedCount, $vendor) {
            foreach ($shifts as $shift) {
                $shiftCarbon = Carbon::parse($shift->shift_date);
                $dayOffset = $shiftCarbon->diffInDays($sourceStart);
                $newDate = $targetStart->copy()->addDays($dayOffset);

                // Prevent duplicates
                $exists = WorkShift::withoutGlobalScopes()
                    ->where('vendor_id', $vendor->id)
                    ->where('user_id', $shift->user_id)
                    ->where('shift_date', $newDate->toDateString())
                    ->exists();

                if (! $exists) {
                    WorkShift::create([
                        'vendor_id' => $vendor->id,
                        'schedule_id' => $shift->schedule_id,
                        'location_id' => $shift->location_id,
                        'user_id' => $shift->user_id,
                        'shift_date' => $newDate->toDateString(),
                        'start_time' => $shift->start_time,
                        'end_time' => $shift->end_time,
                        'cross_midnight' => $shift->cross_midnight,
                        'unpaid_break_minutes' => $shift->unpaid_break_minutes,
                        'status' => 'scheduled',
                    ]);
                    $copiedCount++;
                }
            }
        });

        return $copiedCount;
    }
}
