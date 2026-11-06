<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\EmployeeProfile;
use App\Models\LeaveRequest;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HrReportService
{
    /**
     * Get real-time HR Dashboard KPIs.
     *
     * @return array{
     *     total_employees: int,
     *     active_employees: int,
     *     on_leave_employees: int,
     *     clocked_in_today: int,
     *     late_today: int,
     *     pending_leaves_count: int,
     *     current_month_gross_payroll: float,
     *     current_month_net_payroll: float,
     *     unpaid_payroll_balance: float
     * }
     */
    public function getDashboardMetrics(Vendor $vendor): array
    {
        $today = Carbon::today()->toDateString();
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

        $totalEmployees = User::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->count();

        $activeEmployees = EmployeeProfile::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('employment_status', 'active')
            ->count();

        $onLeaveEmployees = EmployeeProfile::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('employment_status', 'on_leave')
            ->count();

        $clockedInToday = AttendanceRecord::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->whereDate('check_in_at', $today)
            ->distinct('user_id')
            ->count('user_id');

        $lateToday = AttendanceRecord::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->whereDate('check_in_at', $today)
            ->where('status', 'late')
            ->count();

        $pendingLeaves = LeaveRequest::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('status', 'pending')
            ->count();

        $payrollTotals = PayrollPeriod::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->whereBetween('start_date', [$startOfMonth, $endOfMonth])
            ->selectRaw('SUM(total_gross) as sum_gross, SUM(total_net) as sum_net, SUM(total_paid) as sum_paid')
            ->first();

        $gross = round((float) ($payrollTotals?->sum_gross ?? 0.00), 2);
        $net = round((float) ($payrollTotals?->sum_net ?? 0.00), 2);
        $paid = round((float) ($payrollTotals?->sum_paid ?? 0.00), 2);
        $unpaid = max(0.00, round($net - $paid, 2));

        return [
            'total_employees' => $totalEmployees,
            'active_employees' => $activeEmployees,
            'on_leave_employees' => $onLeaveEmployees,
            'clocked_in_today' => $clockedInToday,
            'late_today' => $lateToday,
            'pending_leaves_count' => $pendingLeaves,
            'current_month_gross_payroll' => $gross,
            'current_month_net_payroll' => $net,
            'unpaid_payroll_balance' => $unpaid,
        ];
    }

    /**
     * Get labor costs aggregated by department.
     */
    public function getSalaryCostByDepartment(Vendor $vendor, ?int $periodId = null): array
    {
        $query = PayrollItem::withoutGlobalScopes()
            ->join('users', 'payroll_items.user_id', '=', 'users.id')
            ->leftJoin('employee_profiles', 'users.id', '=', 'employee_profiles.user_id')
            ->leftJoin('hr_departments', 'employee_profiles.department_id', '=', 'hr_departments.id')
            ->where('payroll_items.vendor_id', $vendor->id);

        if ($periodId) {
            $query->where('payroll_items.payroll_period_id', $periodId);
        }

        return $query->select(
            DB::raw("COALESCE(hr_departments.name, 'Unassigned') as department_name"),
            DB::raw('COUNT(DISTINCT payroll_items.user_id) as employee_count'),
            DB::raw('SUM(payroll_items.gross_salary) as total_gross'),
            DB::raw('SUM(payroll_items.net_salary) as total_net')
        )
            ->groupBy('hr_departments.name')
            ->get()
            ->toArray();
    }
}
