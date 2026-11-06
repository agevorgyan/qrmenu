<?php

namespace App\Services;

use App\Models\AttendanceAdjustment;
use App\Models\AttendanceRecord;
use App\Models\Location;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HrAttendanceService
{
    public function __construct(
        protected TimeAwareService $timeService
    ) {}

    /**
     * Generate rotating signed dynamic QR token for a vendor's time clock terminal.
     */
    public function generateClockQrToken(Vendor $vendor, Location $location): string
    {
        $timestamp = time();
        $payload = "{$vendor->id}:{$location->id}:{$timestamp}";
        $signature = hash_hmac('sha256', $payload, config('app.key'));

        return base64_encode("{$payload}:{$signature}");
    }

    /**
     * Verify rotating dynamic QR token (valid for 60 seconds).
     *
     * @return array{vendor_id: int, location_id: int}|false
     */
    public function verifyClockQrToken(string $token): array|false
    {
        try {
            $decoded = base64_decode($token, true);
            if (! $decoded) {
                return false;
            }

            $parts = explode(':', $decoded);
            if (count($parts) !== 4) {
                return false;
            }

            [$vendorId, $locationId, $timestamp, $signature] = $parts;
            $payload = "{$vendorId}:{$locationId}:{$timestamp}";
            $expectedSignature = hash_hmac('sha256', $payload, config('app.key'));

            if (! hash_equals($expectedSignature, $signature)) {
                return false;
            }

            // Expiration window: 120 seconds to tolerate minor clock skews
            if (abs(time() - (int) $timestamp) > 120) {
                return false;
            }

            return [
                'vendor_id' => (int) $vendorId,
                'location_id' => (int) $locationId,
            ];
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Clock in an employee via QR or PIN.
     */
    public function clockIn(
        User $user,
        Location $location,
        string $source = 'pin',
        ?float $lat = null,
        ?float $lng = null
    ): AttendanceRecord {
        $vendor = $user->vendor;
        $now = Carbon::now();

        // Check if employee already has an open attendance record
        $activeRecord = AttendanceRecord::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('user_id', $user->id)
            ->whereNull('check_out_at')
            ->latest('check_in_at')
            ->first();

        if ($activeRecord) {
            throw new InvalidArgumentException(__('Employee is already clocked in. Please clock out first.'));
        }

        // Match scheduled work shift for today
        $shift = WorkShift::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('user_id', $user->id)
            ->where('shift_date', $now->toDateString())
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->first();

        $lateMinutes = 0;
        $status = 'present';

        if ($shift) {
            $scheduledStart = $shift->getStartDateTime();
            if ($now->greaterThan($scheduledStart)) {
                $diff = $scheduledStart->diffInMinutes($now);
                // 5-minute grace period
                if ($diff > 5) {
                    $lateMinutes = $diff;
                    $status = 'late';
                }
            }
        }

        return AttendanceRecord::create([
            'vendor_id' => $vendor->id,
            'location_id' => $location->id,
            'user_id' => $user->id,
            'shift_id' => $shift?->id,
            'check_in_at' => $now,
            'source' => $source,
            'check_in_latitude' => $lat,
            'check_in_longitude' => $lng,
            'status' => $status,
            'late_minutes' => $lateMinutes,
            'is_verified' => true,
        ]);
    }

    /**
     * Clock out an employee.
     */
    public function clockOut(User $user): AttendanceRecord
    {
        $vendor = $user->vendor;
        $now = Carbon::now();

        $record = AttendanceRecord::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('user_id', $user->id)
            ->whereNull('check_out_at')
            ->latest('check_in_at')
            ->first();

        if (! $record) {
            throw new InvalidArgumentException(__('No active clock-in session found for this employee.'));
        }

        $earlyDepartureMinutes = 0;
        $status = 'completed';
        if ($record->shift) {
            $shift = $record->shift;
            $scheduledEnd = $shift->getEndDateTime();

            if ($now->lessThan($scheduledEnd)) {
                $diff = $now->diffInMinutes($scheduledEnd);
                if ($diff > 5) {
                    $earlyDepartureMinutes = $diff;
                    $status = 'early_departure';
                }
            }
        }

        $record->update([
            'check_out_at' => $now,
            'early_departure_minutes' => $earlyDepartureMinutes,
            'status' => $status,
        ]);

        return $record->fresh();
    }

    /**
     * Start a break for an active attendance session.
     */
    public function startBreak(User $user): AttendanceRecord
    {
        $record = $this->getActiveRecord($user);
        if ($record->break_start_at && ! $record->break_end_at) {
            throw new InvalidArgumentException(__('Employee is already on a break.'));
        }

        $record->update([
            'break_start_at' => Carbon::now(),
            'break_end_at' => null,
            'status' => 'on_break',
        ]);

        return $record->fresh();
    }

    /**
     * End current break.
     */
    public function endBreak(User $user): AttendanceRecord
    {
        $record = $this->getActiveRecord($user);
        if (! $record->break_start_at || $record->break_end_at) {
            throw new InvalidArgumentException(__('No active break found to resume from.'));
        }

        $now = Carbon::now();
        $breakMins = (int) round(abs($now->getTimestamp() - $record->break_start_at->getTimestamp()) / 60);

        $record->update([
            'break_end_at' => $now,
            'total_break_minutes' => (int) ($record->total_break_minutes ?? 0) + $breakMins,
            'status' => 'present',
        ]);

        return $record->fresh();
    }

    /**
     * Submit supervisor attendance adjustment with audit trail.
     */
    public function adjustAttendance(
        AttendanceRecord $record,
        User $adjuster,
        Carbon $adjustedCheckIn,
        Carbon $adjustedCheckOut,
        string $reason
    ): AttendanceRecord {
        return DB::transaction(function () use ($record, $adjuster, $adjustedCheckIn, $adjustedCheckOut, $reason) {
            AttendanceAdjustment::create([
                'vendor_id' => $record->vendor_id,
                'attendance_record_id' => $record->id,
                'adjusted_by' => $adjuster->id,
                'original_check_in' => $record->check_in_at,
                'original_check_out' => $record->check_out_at,
                'adjusted_check_in' => $adjustedCheckIn,
                'adjusted_check_out' => $adjustedCheckOut,
                'reason' => $reason,
                'status' => 'approved',
            ]);

            $record->update([
                'check_in_at' => $adjustedCheckIn,
                'check_out_at' => $adjustedCheckOut,
                'is_verified' => true,
                'verified_by' => $adjuster->id,
                'notes' => trim($record->notes." [Adjusted: {$reason}]"),
            ]);

            return $record->fresh();
        });
    }

    protected function getActiveRecord(User $user): AttendanceRecord
    {
        $record = AttendanceRecord::withoutGlobalScopes()
            ->where('vendor_id', $user->vendor_id)
            ->where('user_id', $user->id)
            ->whereNull('check_out_at')
            ->latest('check_in_at')
            ->first();

        if (! $record) {
            throw new InvalidArgumentException(__('No active attendance record found for this employee.'));
        }

        return $record;
    }
}
