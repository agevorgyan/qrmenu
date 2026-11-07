<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Admin\Finance\Concerns\AuthorizesFinance;
use App\Http\Controllers\Controller;
use App\Models\FinancialApprovalRequest;
use App\Security\Permission;
use App\Services\FinancialApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialApprovalController extends Controller
{
    use AuthorizesFinance;

    public function __construct(
        protected FinancialApprovalService $approvalService
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::FINANCE_EXPENSES_APPROVE);

        $vendor = $this->getActiveVendor();

        $query = FinancialApprovalRequest::where('vendor_id', $vendor->id)
            ->with(['requestedByUser', 'decidedByUser', 'approvable']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.finance.approvals.index', compact('requests'));
    }

    public function decide(Request $request, FinancialApprovalRequest $approvalRequest): RedirectResponse
    {
        $this->authorizePermission(Permission::FINANCE_EXPENSES_APPROVE);

        $vendor = $this->getActiveVendor();
        if ((int) $approvalRequest->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        $action = $request->input('decision'); // approve or reject
        $notes = $request->input('notes');

        try {
            if ($action === 'approve') {
                $this->approvalService->approve($approvalRequest, $request->user(), $notes);

                return back()->with('success', __('Financial request approved successfully.'));
            }

            $this->approvalService->reject($approvalRequest, $request->user(), $notes ?: 'Rejected by manager.');

            return back()->with('success', __('Financial request rejected.'));
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
