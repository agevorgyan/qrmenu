<?php

namespace App\Services;

use App\Models\EmployeePositionHistory;
use App\Models\EmployeeProfile;
use App\Models\EmployeeSalaryHistory;
use App\Models\EmploymentContract;
use App\Models\HrDepartment;
use App\Models\HrPosition;
use App\Models\Location;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HrEmployeeService
{
    /**
     * Transfer an employee to a new position, department, or branch with full history tracking.
     */
    public function transferEmployee(
        User $employee,
        User $actor,
        ?HrDepartment $department = null,
        ?HrPosition $position = null,
        ?Location $location = null,
        ?string $reason = null,
        ?string $effectiveFrom = null
    ): EmployeeProfile {
        return DB::transaction(function () use ($employee, $actor, $department, $position, $location, $reason, $effectiveFrom) {
            $profile = $employee->employeeProfile;
            if (! $profile) {
                throw new InvalidArgumentException(__('Employee profile not found.'));
            }

            $effectiveDate = $effectiveFrom ? Carbon::parse($effectiveFrom)->toDateString() : Carbon::now()->toDateString();

            // Close existing open position history
            EmployeePositionHistory::withoutGlobalScopes()
                ->where('vendor_id', $employee->vendor_id)
                ->where('user_id', $employee->id)
                ->whereNull('effective_until')
                ->update(['effective_until' => Carbon::parse($effectiveDate)->subDay()->toDateString()]);

            // Record history
            EmployeePositionHistory::create([
                'vendor_id' => $employee->vendor_id,
                'user_id' => $employee->id,
                'department_id' => $department?->id ?? $profile->department_id,
                'position_id' => $position?->id ?? $profile->position_id,
                'location_id' => $location?->id ?? $employee->location_id,
                'effective_from' => $effectiveDate,
                'reason' => $reason,
                'changed_by' => $actor->id,
            ]);

            // Update user & profile
            if ($location) {
                $employee->update(['location_id' => $location->id]);
            }

            $profile->update([
                'department_id' => $department ? $department->id : $profile->department_id,
                'position_id' => $position ? $position->id : $profile->position_id,
            ]);

            return $profile->fresh(['department', 'position', 'user.location']);
        });
    }

    /**
     * Revise base salary or compensation terms with salary history tracking.
     */
    public function reviseSalary(
        User $employee,
        User $actor,
        string $compensationType,
        float $baseSalary,
        float $hourlyRate,
        ?string $reason = null,
        ?string $effectiveFrom = null
    ): EmployeeProfile {
        return DB::transaction(function () use ($employee, $actor, $compensationType, $baseSalary, $hourlyRate, $reason, $effectiveFrom) {
            $profile = $employee->employeeProfile;
            if (! $profile) {
                throw new InvalidArgumentException(__('Employee profile not found.'));
            }

            $effectiveDate = $effectiveFrom ? Carbon::parse($effectiveFrom)->toDateString() : Carbon::now()->toDateString();

            // Close existing open salary history
            EmployeeSalaryHistory::withoutGlobalScopes()
                ->where('vendor_id', $employee->vendor_id)
                ->where('user_id', $employee->id)
                ->whereNull('effective_until')
                ->update(['effective_until' => Carbon::parse($effectiveDate)->subDay()->toDateString()]);

            // Record new salary history
            EmployeeSalaryHistory::create([
                'vendor_id' => $employee->vendor_id,
                'user_id' => $employee->id,
                'compensation_type' => $compensationType,
                'base_salary' => round($baseSalary, 2),
                'hourly_rate' => round($hourlyRate, 2),
                'effective_from' => $effectiveDate,
                'reason' => $reason,
                'approved_by' => $actor->id,
            ]);

            // Update profile
            $profile->update([
                'compensation_type' => $compensationType,
                'base_salary' => round($baseSalary, 2),
                'hourly_rate' => round($hourlyRate, 2),
            ]);

            return $profile->fresh();
        });
    }

    /**
     * Issue a new employment contract.
     */
    public function issueContract(
        Vendor $vendor,
        User $employee,
        User $creator,
        array $data
    ): EmploymentContract {
        return EmploymentContract::create([
            'vendor_id' => $vendor->id,
            'user_id' => $employee->id,
            'contract_number' => $data['contract_number'],
            'contract_type' => $data['contract_type'] ?? 'indefinite',
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'probation_end_date' => $data['probation_end_date'] ?? null,
            'wage_amount' => round((float) ($data['wage_amount'] ?? 0.00), 2),
            'wage_type' => $data['wage_type'] ?? 'monthly',
            'working_hours_per_week' => (int) ($data['working_hours_per_week'] ?? 40),
            'file_path' => $data['file_path'] ?? null,
            'status' => 'active',
            'notes' => $data['notes'] ?? null,
            'created_by' => $creator->id,
        ]);
    }
}
