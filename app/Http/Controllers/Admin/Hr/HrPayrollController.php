<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Services\HrPayrollService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HrPayrollController extends Controller
{
    public function __construct(
        protected HrPayrollService $payrollService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('hr.payroll.view');

        $vendor = Auth::user()->vendor;
        $periods = PayrollPeriod::where('vendor_id', $vendor->id)
            ->withCount('items')
            ->orderBy('start_date', 'desc')
            ->paginate(15);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $periods]);
        }

        return view('admin.hr.payroll.index', compact('vendor', 'periods'));
    }

    public function createPeriod(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.payroll.calculate');

        $vendor = Auth::user()->vendor;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'cycle_type' => 'required|string|in:monthly,semi_monthly,weekly,custom',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'cutoff_date' => 'required|date',
        ]);

        $period = PayrollPeriod::create([
            'vendor_id' => $vendor->id,
            'name' => $validated['name'],
            'cycle_type' => $validated['cycle_type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'cutoff_date' => $validated['cutoff_date'],
            'currency' => $vendor->currency ?? 'AMD',
            'status' => 'draft',
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Payroll period created successfully.'), 'data' => $period], 201);
        }

        return redirect()->route('admin.hr.payroll.preview', $period)->with('success', __('Payroll period created. Run calculation to generate employee earnings.'));
    }

    public function calculate(Request $request, PayrollPeriod $period): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.payroll.calculate');

        if ((int) $period->vendor_id !== (int) Auth::user()->vendor_id) {
            abort(403);
        }

        $this->payrollService->runPeriodCalculation($period);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Payroll calculated successfully.')]);
        }

        return back()->with('success', __('Payroll calculated successfully. Review breakdown before approving.'));
    }

    public function preview(Request $request, PayrollPeriod $period): View|JsonResponse
    {
        $this->authorize('hr.payroll.view');

        $vendor = Auth::user()->vendor;
        if ((int) $period->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        $period->load([
            'items.user.employeeProfile.position',
            'items.user.employeeProfile.department',
            'items.components',
            'approver:id,name',
            'locker:id,name',
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $period]);
        }

        return view('admin.hr.payroll.preview', compact('vendor', 'period'));
    }

    public function approve(Request $request, PayrollPeriod $period): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.payroll.approve');

        if ((int) $period->vendor_id !== (int) Auth::user()->vendor_id) {
            abort(403);
        }

        if ($period->status !== 'pending_approval') {
            abort(422, __('Only pending approval periods can be approved.'));
        }

        $period->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => Carbon::now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Payroll period approved successfully.')]);
        }

        return back()->with('success', __('Payroll period approved successfully. You may now disburse payments or lock the period.'));
    }

    public function lock(Request $request, PayrollPeriod $period): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.payroll.lock');

        if ((int) $period->vendor_id !== (int) Auth::user()->vendor_id) {
            abort(403);
        }

        $this->payrollService->lockPeriod($period, Auth::user());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Payroll period locked successfully.')]);
        }

        return back()->with('success', __('Payroll period locked and finalized. Historical snapshot archived.'));
    }

    public function payslip(Request $request, PayrollItem $item): View
    {
        $vendor = Auth::user()->vendor;

        // Either user with hr.payroll.view or the employee themselves
        $isOwnPayslip = (int) $item->user_id === (int) Auth::id();
        if (! $isOwnPayslip) {
            $this->authorize('hr.payroll.view');
        }

        if ((int) $item->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        $item->load(['user.employeeProfile.position', 'user.employeeProfile.department', 'period', 'components', 'payments']);

        return view('admin.hr.payroll.payslip', compact('vendor', 'item'));
    }
}
