<?php

namespace App\Services;

use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HrLeaveService
{
    /**
     * Submit a leave application.
     */
    public function submitLeaveRequest(
        Vendor $vendor,
        User $user,
        LeaveType $leaveType,
        Carbon $startDate,
        Carbon $endDate,
        string $reason,
        ?string $attachmentPath = null
    ): LeaveRequest {
        if ($endDate->lessThan($startDate)) {
            throw new InvalidArgumentException(__('End date cannot precede start date.'));
        }

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $year = (int) $startDate->year;

        // Check or initialize balance for this year
        $balance = $this->getOrCreateBalance($vendor, $user, $leaveType, $year);

        if ($leaveType->is_paid && ($balance->remaining_days < $totalDays)) {
            throw new InvalidArgumentException(__('Requested days exceed available leave balance. Available: :days days', ['days' => $balance->remaining_days]));
        }

        return LeaveRequest::create([
            'vendor_id' => $vendor->id,
            'user_id' => $user->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_days' => $totalDays,
            'reason' => $reason,
            'attachment_path' => $attachmentPath,
            'status' => 'pending',
        ]);
    }

    /**
     * Review (Approve or Reject) a leave request.
     */
    public function reviewLeaveRequest(
        LeaveRequest $request,
        User $reviewer,
        bool $approve,
        ?string $notes = null
    ): LeaveRequest {
        if ($request->status !== 'pending') {
            throw new InvalidArgumentException(__('Only pending leave requests can be reviewed.'));
        }

        return DB::transaction(function () use ($request, $reviewer, $approve, $notes) {
            $status = $approve ? 'approved' : 'rejected';

            $request->update([
                'status' => $status,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => Carbon::now(),
                'review_notes' => $notes,
            ]);

            if ($approve) {
                // Deduct from balance
                $year = (int) Carbon::parse($request->start_date)->year;
                $balance = $this->getOrCreateBalance($request->vendor, $request->user, $request->leaveType, $year);

                $newUsed = round((float) $balance->used_days + (float) $request->total_days, 2);
                $newRemaining = max(0.00, round((float) $balance->allocated_days - $newUsed, 2));

                $balance->update([
                    'used_days' => $newUsed,
                    'remaining_days' => $newRemaining,
                ]);
            }

            return $request->fresh();
        });
    }

    /**
     * Get or initialize annual leave balance for an employee.
     */
    public function getOrCreateBalance(
        Vendor $vendor,
        User $user,
        LeaveType $leaveType,
        int $year
    ): EmployeeLeaveBalance {
        return EmployeeLeaveBalance::withoutGlobalScopes()->firstOrCreate(
            [
                'vendor_id' => $vendor->id,
                'user_id' => $user->id,
                'leave_type_id' => $leaveType->id,
                'year' => $year,
            ],
            [
                'allocated_days' => $leaveType->default_days_per_year,
                'used_days' => 0.00,
                'remaining_days' => $leaveType->default_days_per_year,
            ]
        );
    }
}
