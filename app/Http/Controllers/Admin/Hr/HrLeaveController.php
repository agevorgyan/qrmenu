<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\HrLeaveService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HrLeaveController extends Controller
{
    public function __construct(
        protected HrLeaveService $leaveService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('hr.leave.view');

        $vendor = Auth::user()->vendor;
        $status = $request->get('status');
        $year = (int) $request->get('year', Carbon::now()->year);

        $query = LeaveRequest::where('vendor_id', $vendor->id)
            ->with(['user.employeeProfile.position', 'leaveType', 'reviewer:id,name'])
            ->latest('start_date');

        if ($status) {
            $query->where('status', $status);
        }

        $requests = $query->paginate(20)->withQueryString();
        $leaveTypes = LeaveType::where('vendor_id', $vendor->id)->where('is_active', true)->get();
        $employees = User::where('vendor_id', $vendor->id)->where('is_active', true)->get();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $requests]);
        }

        return view('admin.hr.leaves.index', compact('vendor', 'requests', 'leaveTypes', 'employees', 'year'));
    }

    public function storeRequest(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.leave.request');

        $vendor = Auth::user()->vendor;

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $targetUser = User::where('vendor_id', $vendor->id)->findOrFail($validated['user_id']);
        $leaveType = LeaveType::where('vendor_id', $vendor->id)->findOrFail($validated['leave_type_id']);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store("leaves/{$vendor->id}", 'local');
        }

        $leaveReq = $this->leaveService->submitLeaveRequest(
            vendor: $vendor,
            user: $targetUser,
            leaveType: $leaveType,
            startDate: Carbon::parse($validated['start_date']),
            endDate: Carbon::parse($validated['end_date']),
            reason: $validated['reason'],
            attachmentPath: $attachmentPath
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Leave request submitted successfully.'), 'data' => $leaveReq], 201);
        }

        return back()->with('success', __('Leave request submitted successfully.'));
    }

    public function review(Request $request, LeaveRequest $leaveRequest): RedirectResponse|JsonResponse
    {
        $this->authorize('hr.leave.approve');

        if ((int) $leaveRequest->vendor_id !== (int) Auth::user()->vendor_id) {
            abort(403);
        }

        $validated = $request->validate([
            'action' => 'required|string|in:approve,reject',
            'review_notes' => 'nullable|string|max:500',
        ]);

        $this->leaveService->reviewLeaveRequest(
            request: $leaveRequest,
            reviewer: Auth::user(),
            approve: $validated['action'] === 'approve',
            notes: $validated['review_notes'] ?? null
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Leave request :status successfully.', ['status' => $validated['action'] === 'approve' ? 'approved' : 'rejected'])]);
        }

        return back()->with('success', __('Leave request :status successfully.', ['status' => $validated['action'] === 'approve' ? 'approved' : 'rejected']));
    }

    public function balancesIndex(Request $request): View|JsonResponse
    {
        $this->authorize('hr.leave.view');

        $vendor = Auth::user()->vendor;
        $year = (int) $request->get('year', Carbon::now()->year);

        $balances = EmployeeLeaveBalance::where('vendor_id', $vendor->id)
            ->where('year', $year)
            ->with(['user', 'leaveType'])
            ->get();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $balances]);
        }

        return view('admin.hr.leaves.balances', compact('vendor', 'balances', 'year'));
    }
}
