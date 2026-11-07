<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Admin\Finance\Concerns\AuthorizesFinance;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\User;
use App\Security\Permission;
use App\Services\FinancialAuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialAuditController extends Controller
{
    use AuthorizesFinance;

    public function __construct(
        protected FinancialAuditService $auditService
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::FINANCE_LEDGER_VIEW);

        $vendor = $this->getActiveVendor();

        $locationId = $request->filled('location_id') ? (int) $request->input('location_id') : null;
        $action = $request->input('action');
        $startDate = $request->filled('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate = $request->filled('end_date') ? Carbon::parse($request->input('end_date')) : null;

        $logs = $this->auditService->getAuditHistory(
            $vendor,
            $locationId,
            $action,
            null,
            null,
            $startDate,
            $endDate,
            25
        );

        $locations = Location::where('vendor_id', $vendor->id)->get();
        $users = User::where('vendor_id', $vendor->id)->get();

        return view('admin.finance.audit.index', compact('logs', 'locations', 'users', 'action', 'startDate', 'endDate'));
    }

    public function verify(Request $request): View
    {
        $this->authorizePermission(Permission::FINANCE_LEDGER_MANAGE);

        $vendor = $this->getActiveVendor();
        $integrityResult = $this->auditService->verifyTamperIntegrity($vendor);

        return view('admin.finance.audit.verify', compact('integrityResult'));
    }
}
