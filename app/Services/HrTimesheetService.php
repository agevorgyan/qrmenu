<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\Timesheet;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WorkShift;
use Carbon\Carbon;

class HrTimesheetService
{
    /**
     * Compile or regenerate timesheets for all employees of a vendor for a date range.
     */
    public function generateTimesheetsForPeriod(Vendor $vendor, Carbon $startDate, Carbon $endDate): array
    {
        $employees = User::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->with(['employeeProfile'])
            ->get();

        $generated = [];

        foreach ($employees as $employee) {
            $timesheet = $this->compileEmployeeTimesheet($vendor, $employee, $startDate, $endDate);
            if ($timesheet) {
                $generated[] = $timesheet;
            }
        }

        return $generated;
    }

    /**
     * Compile timesheet for a single employee.
     */
    public function compileEmployeeTimesheet(
        Vendor $vendor,
        User $employee,
        Carbon $startDate,
        Carbon $endDate
    ): Timesheet {
        $existing = Timesheet::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('user_id', $employee->id)
            ->where('period_start', $startDate->toDateString())
            ->where('period_end', $endDate->toDateString())
            ->first();

        if ($existing && $existing->isApproved()) {
            return $existing; // Cannot overwrite approved timesheet
        }

        // 1. Scheduled Hours
        $scheduledShifts = WorkShift::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('user_id', $employee->id)
            ->whereBetween('shift_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereIn('status', ['scheduled', 'confirmed', 'completed'])
            ->get();

        $scheduledHours = 0.00;
        foreach ($scheduledShifts as $shift) {
            $durMinutes = $shift->getScheduledDurationMinutes();
            $scheduledHours += round($durMinutes / 60, 2);
        }

        // 2. Attendance actual punches
        $records = AttendanceRecord::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('user_id', $employee->id)
            ->whereBetween('check_in_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->whereNotNull('check_out_at')
            ->get();

        $workedHours = 0.00;
        $nightHours = 0.00;
        $overtimeHours = 0.00;
        $regularHours = 0.00;

        foreach ($records as $record) {
            $netMins = $record->net_worked_minutes;
            $hours = round($netMins / 60, 2);
            $workedHours += $hours;

            // Calculate night hours (22:00 to 06:00)
            $nightHours += $this->calculateNightShiftHours($record->check_in_at, $record->check_out_at);

            // Daily overtime threshold (standard 8 hours)
            if ($hours > 8.00) {
                $overtimeHours += round($hours - 8.00, 2);
                $regularHours += 8.00;
            } else {
                $regularHours += $hours;
            }
        }

        // 3. Approved Leave Hours
        $leaves = LeaveRequest::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('user_id', $employee->id)
            ->where('status', 'approved')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhereBetween('end_date', [$startDate->toDateString(), $endDate->toDateString()]);
            })
            ->with('leaveType')
            ->get();

        $paidLeaveHours = 0.00;
        $unpaidLeaveHours = 0.00;

        foreach ($leaves as $leave) {
            $hours = (float) $leave->total_days * 8.00;
            if ($leave->leaveType && $leave->leaveType->is_paid) {
                $paidLeaveHours += $hours;
            } else {
                $unpaidLeaveHours += $hours;
            }
        }

        return Timesheet::withoutGlobalScopes()->updateOrCreate(
            [
                'vendor_id' => $vendor->id,
                'user_id' => $employee->id,
                'period_start' => $startDate->toDateString(),
                'period_end' => $endDate->toDateString(),
            ],
            [
                'location_id' => $employee->location_id,
                'scheduled_hours' => round($scheduledHours, 2),
                'worked_hours' => round($workedHours, 2),
                'regular_hours' => round($regularHours, 2),
                'overtime_hours' => round($overtimeHours, 2),
                'night_hours' => round($nightHours, 2),
                'holiday_hours' => 0.00,
                'paid_leave_hours' => round($paidLeaveHours, 2),
                'unpaid_leave_hours' => round($unpaidLeaveHours, 2),
                'status' => 'draft',
            ]
        );
    }

    /**
     * Compute night shift duration (22:00 - 06:00).
     */
    public function calculateNightShiftHours(Carbon $start, Carbon $end): float
    {
        $nightMinutes = 0;
        $cursor = $start->copy();

        while ($cursor->lessThan($end)) {
            $hour = (int) $cursor->format('G');
            if ($hour >= 22 || $hour < 6) {
                $nightMinutes++;
            }
            $cursor->addMinute();
        }

        return round($nightMinutes / 60, 2);
    }

    /**
     * Approve and sign off a timesheet.
     */
    public function approveTimesheet(Timesheet $timesheet, User $approver): Timesheet
    {
        if ($timesheet->isApproved()) {
            return $timesheet;
        }

        $timesheet->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => Carbon::now(),
        ]);

        return $timesheet->fresh();
    }
}
