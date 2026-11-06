<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\EmployeeAdvance;
use App\Models\PayrollItem;
use App\Models\SalaryPayment;
use App\Models\User;
use App\Services\HrPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HrPaymentAdvanceController extends Controller
{
    public function __construct(
        protected HrPaymentService $paymentService
    ) {}

    public function paymentsIndex(Request $request): View|JsonResponse
    {
        $this->authorize('hr.payroll.view');

        $vendor = Auth::user()->vendor;
        $payments = SalaryPayment::where('vendor_id', $vendor->id)
            ->with(['user', 'period', 'creator:id,name'])
            ->latest('payment_date')
            ->paginate(20);

        $unpaidItems = PayrollItem::where('vendor_id', $vendor->id)
            ->whereIn('payment_status', ['unpaid', 'partially_paid'])
            ->with(['user', 'period'])
            ->get();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $payments]);
        }

        return view('admin.hr.payments.index', compact('vendor', 'payments', 'unpaidItems'));
    }

    public function storePayment(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.payroll.pay');

        $vendor = Auth::user()->vendor;

        $validated = $request->validate([
            'payroll_item_id' => 'required|exists:payroll_items,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:bank_transfer,cash,other',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $item = PayrollItem::where('vendor_id', $vendor->id)->findOrFail($validated['payroll_item_id']);

        $payment = $this->paymentService->recordSalaryPayment(
            item: $item,
            actor: Auth::user(),
            amount: (float) $validated['amount'],
            method: $validated['payment_method'],
            reference: $validated['reference_number'] ?? null,
            notes: $validated['notes'] ?? null
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Salary payment recorded successfully.'), 'data' => $payment], 201);
        }

        return back()->with('success', __('Salary payment of :amount recorded successfully.', ['amount' => number_format($payment->amount, 2)]));
    }

    public function advancesIndex(Request $request): View|JsonResponse
    {
        $this->authorize('hr.advances.view');

        $vendor = Auth::user()->vendor;
        $advances = EmployeeAdvance::where('vendor_id', $vendor->id)
            ->with(['user.employeeProfile.position', 'approver:id,name'])
            ->latest('requested_date')
            ->paginate(20);

        $employees = User::where('vendor_id', $vendor->id)->where('is_active', true)->get();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $advances]);
        }

        return view('admin.hr.advances.index', compact('vendor', 'advances', 'employees'));
    }

    public function storeAdvance(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.advances.request');

        $vendor = Auth::user()->vendor;

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:1',
            'reason' => 'required|string|max:1000',
            'payment_method' => 'nullable|string|in:cash,bank_transfer,other',
        ]);

        $targetUser = User::where('vendor_id', $vendor->id)->findOrFail($validated['user_id']);

        $advance = $this->paymentService->requestAdvance(
            vendor: $vendor,
            user: $targetUser,
            amount: (float) $validated['amount'],
            reason: $validated['reason'],
            method: $validated['payment_method'] ?? 'cash'
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Advance requested successfully.'), 'data' => $advance], 201);
        }

        return back()->with('success', __('Advance requested successfully.'));
    }

    public function reviewAdvance(Request $request, EmployeeAdvance $advance): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.advances.approve');

        if ((int) $advance->vendor_id !== (int) Auth::user()->vendor_id) {
            abort(403);
        }

        $validated = $request->validate([
            'action' => 'required|string|in:disburse,reject',
        ]);

        $this->paymentService->disburseAdvance(
            advance: $advance,
            actor: Auth::user(),
            approve: $validated['action'] === 'disburse'
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Advance :action successfully.', ['action' => $validated['action'] === 'disburse' ? 'disbursed' : 'rejected'])]);
        }

        return back()->with('success', __('Advance :action successfully.', ['action' => $validated['action'] === 'disburse' ? 'disbursed' : 'rejected']));
    }
}
