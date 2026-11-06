<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\PayrollPeriod;
use App\Services\HrReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrReportController extends Controller
{
    public function __construct(
        protected HrReportService $reportService
    ) {}

    public function dashboard(Request $request): View|JsonResponse
    {
        $this->authorize('hr.reports.view');

        $vendor = Auth::user()->vendor;
        $metrics = $this->reportService->getDashboardMetrics($vendor);
        $deptCosts = $this->reportService->getSalaryCostByDepartment($vendor);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'metrics' => $metrics, 'department_costs' => $deptCosts]);
        }

        return view('admin.hr.reports.dashboard', compact('vendor', 'metrics', 'deptCosts'));
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        $this->authorize('hr.reports.export');

        $vendor = Auth::user()->vendor;
        $filename = "hr-{$type}-".Carbon::now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($vendor, $type) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel compatibility

            if ($type === 'attendance') {
                fputcsv($handle, ['Employee', 'Email', 'Branch', 'Check In', 'Check Out', 'Worked (Hrs)', 'Break (Mins)', 'Status', 'Late (Mins)']);

                $records = AttendanceRecord::where('vendor_id', $vendor->id)
                    ->with(['user', 'location'])
                    ->latest('check_in_at')
                    ->limit(2000)
                    ->get();

                foreach ($records as $r) {
                    fputcsv($handle, [
                        $r->user->name ?? '',
                        $r->user->email ?? '',
                        $r->location->name ?? '',
                        $r->check_in_at ? $r->check_in_at->toDateTimeString() : '',
                        $r->check_out_at ? $r->check_out_at->toDateTimeString() : '',
                        $r->net_worked_hours,
                        $r->total_break_minutes,
                        $r->status,
                        $r->late_minutes,
                    ]);
                }
            } elseif ($type === 'payroll') {
                fputcsv($handle, ['Period', 'Employee', 'Gross Salary', 'Income Tax', 'Pension', 'Stamp Duty', 'Advance Deduction', 'Net Salary', 'Payment Status']);

                $periods = PayrollPeriod::where('vendor_id', $vendor->id)
                    ->with(['items.user'])
                    ->latest('start_date')
                    ->get();

                foreach ($periods as $p) {
                    foreach ($p->items as $i) {
                        fputcsv($handle, [
                            $p->name,
                            $i->user->name ?? '',
                            $i->gross_salary,
                            $i->income_tax,
                            $i->pension_fee,
                            $i->stamp_fee,
                            $i->advance_deduction,
                            $i->net_salary,
                            $i->payment_status,
                        ]);
                    }
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
