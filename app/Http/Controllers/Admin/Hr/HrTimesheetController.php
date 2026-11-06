<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Timesheet;
use App\Services\HrTimesheetService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HrTimesheetController extends Controller
{
    public function __construct(
        protected HrTimesheetService $timesheetService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('hr.timesheets.view');

        $vendor = Auth::user()->vendor;
        $periodStart = $request->filled('start_date') ? Carbon::parse($request->get('start_date')) : Carbon::now()->startOfMonth();
        $periodEnd = $request->filled('end_date') ? Carbon::parse($request->get('end_date')) : Carbon::now()->endOfMonth();
        $locId = $request->get('location_id');

        $query = Timesheet::where('vendor_id', $vendor->id)
            ->where('period_start', $periodStart->toDateString())
            ->where('period_end', $periodEnd->toDateString())
            ->with(['user.employeeProfile.position', 'user.employeeProfile.department', 'approver:id,name'])
            ->orderBy('created_at', 'desc');

        if ($locId) {
            $query->where('location_id', $locId);
        }

        $timesheets = $query->get();
        $locations = $vendor->locations;

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $timesheets]);
        }

        return view('admin.hr.timesheets.index', compact('vendor', 'timesheets', 'locations', 'periodStart', 'periodEnd'));
    }

    public function generate(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.timesheets.manage');

        $vendor = Auth::user()->vendor;
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);

        $generated = $this->timesheetService->generateTimesheetsForPeriod($vendor, $start, $end);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __(':count timesheets compiled successfully.', ['count' => count($generated)]), 'data' => $generated]);
        }

        return back()->with('success', __(':count timesheets compiled successfully.', ['count' => count($generated)]));
    }

    public function approve(Request $request, Timesheet $timesheet): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.timesheets.approve');

        if ((int) $timesheet->vendor_id !== (int) Auth::user()->vendor_id) {
            abort(403);
        }

        $this->timesheetService->approveTimesheet($timesheet, Auth::user());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Timesheet approved successfully.')]);
        }

        return back()->with('success', __('Timesheet approved successfully.'));
    }

    public function update(Request $request, Timesheet $timesheet): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.timesheets.manage');

        if ((int) $timesheet->vendor_id !== (int) Auth::user()->vendor_id) {
            abort(403);
        }

        if ($timesheet->isApproved()) {
            abort(422, __('Approved timesheets cannot be modified.'));
        }

        $validated = $request->validate([
            'regular_hours' => 'required|numeric|min:0',
            'overtime_hours' => 'nullable|numeric|min:0',
            'night_hours' => 'nullable|numeric|min:0',
            'holiday_hours' => 'nullable|numeric|min:0',
            'paid_leave_hours' => 'nullable|numeric|min:0',
            'unpaid_leave_hours' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $reg = (float) $validated['regular_hours'];
        $ot = (float) ($validated['overtime_hours'] ?? 0.00);
        $totalWorked = round($reg + $ot, 2);

        $timesheet->update([
            'regular_hours' => $reg,
            'overtime_hours' => $ot,
            'worked_hours' => $totalWorked,
            'night_hours' => (float) ($validated['night_hours'] ?? 0.00),
            'holiday_hours' => (float) ($validated['holiday_hours'] ?? 0.00),
            'paid_leave_hours' => (float) ($validated['paid_leave_hours'] ?? 0.00),
            'unpaid_leave_hours' => (float) ($validated['unpaid_leave_hours'] ?? 0.00),
            'notes' => $validated['notes'] ?? $timesheet->notes,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Timesheet updated successfully.'), 'data' => $timesheet]);
        }

        return back()->with('success', __('Timesheet updated successfully.'));
    }
}
