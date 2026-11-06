<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use App\Services\HrLeaveService;
use App\Services\HrPaymentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HrSelfServiceController extends Controller
{
    public function __construct(
        protected HrLeaveService $leaveService,
        protected HrPaymentService $paymentService
    ) {}

    public function index(Request $request): View
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $user->load([
            'employeeProfile.position',
            'employeeProfile.department',
            'location',
            'workShifts' => fn ($q) => $q->where('shift_date', '>=', Carbon::today()->toDateString())->orderBy('shift_date')->limit(7),
            'attendanceRecords' => fn ($q) => $q->latest('check_in_at')->limit(7),
            'leaveBalances.leaveType',
            'payrollItems' => fn ($q) => $q->latest('created_at')->limit(6)->with('period'),
            'employeeAdvances' => fn ($q) => $q->latest('requested_date')->limit(5),
        ]);

        $leaveTypes = LeaveType::where('vendor_id', $vendor->id)->where('is_active', true)->get();

        return view('admin.hr.self_service.index', compact('vendor', 'user', 'leaveTypes'));
    }

    public function requestLeave(Request $request): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $leaveType = LeaveType::where('vendor_id', $vendor->id)->findOrFail($validated['leave_type_id']);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store("leaves/{$vendor->id}", 'local');
        }

        $leaveReq = $this->leaveService->submitLeaveRequest(
            vendor: $vendor,
            user: $user,
            leaveType: $leaveType,
            startDate: Carbon::parse($validated['start_date']),
            endDate: Carbon::parse($validated['end_date']),
            reason: $validated['reason'],
            attachmentPath: $attachmentPath
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Leave application submitted successfully.'), 'data' => $leaveReq], 201);
        }

        return back()->with('success', __('Leave application submitted successfully.'));
    }

    public function requestAdvance(Request $request): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'reason' => 'required|string|max:1000',
        ]);

        $advance = $this->paymentService->requestAdvance(
            vendor: $vendor,
            user: $user,
            amount: (float) $validated['amount'],
            reason: $validated['reason'],
            method: 'bank_transfer'
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Salary advance requested successfully.'), 'data' => $advance], 201);
        }

        return back()->with('success', __('Salary advance requested successfully.'));
    }
}
