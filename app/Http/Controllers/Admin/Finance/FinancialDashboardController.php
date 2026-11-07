<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Admin\Finance\Concerns\AuthorizesFinance;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\CashRegister;
use App\Models\Expense;
use App\Models\FinancialApprovalRequest;
use App\Models\Location;
use App\Models\PosShift;
use App\Security\Permission;
use App\Services\FinancialReportingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialDashboardController extends Controller
{
    use AuthorizesFinance;

    public function __construct(
        protected FinancialReportingService $reportingService
    ) {}

    /**
     * Display executive financial dashboard.
     */
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::FINANCE_VIEW);

        $vendor = $this->getActiveVendor();

        // Branch filter
        $locationId = $request->filled('location_id') ? (int) $request->input('location_id') : null;

        // Date range
        $period = $request->input('period', 'this_month');

        [$startDate, $endDate, $prevStartDate, $prevEndDate] = match ($period) {
            'today' => [
                Carbon::today(),
                Carbon::today(),
                Carbon::yesterday(),
                Carbon::yesterday(),
            ],
            'yesterday' => [
                Carbon::yesterday(),
                Carbon::yesterday(),
                Carbon::yesterday()->subDay(),
                Carbon::yesterday()->subDay(),
            ],
            'last_7_days' => [
                Carbon::today()->subDays(6),
                Carbon::today(),
                Carbon::today()->subDays(13),
                Carbon::today()->subDays(7),
            ],
            'last_30_days' => [
                Carbon::today()->subDays(29),
                Carbon::today(),
                Carbon::today()->subDays(59),
                Carbon::today()->subDays(30),
            ],
            'last_month' => [
                Carbon::now()->subMonth()->startOfMonth(),
                Carbon::now()->subMonth()->endOfMonth(),
                Carbon::now()->subMonths(2)->startOfMonth(),
                Carbon::now()->subMonths(2)->endOfMonth(),
            ],
            'custom' => [
                Carbon::parse($request->input('start_date', Carbon::today()->startOfMonth())),
                Carbon::parse($request->input('end_date', Carbon::today())),
                Carbon::parse($request->input('start_date', Carbon::today()->startOfMonth()))->subDays(
                    Carbon::parse($request->input('start_date', Carbon::today()->startOfMonth()))->diffInDays(Carbon::parse($request->input('end_date', Carbon::today()))) + 1
                ),
                Carbon::parse($request->input('start_date', Carbon::today()->startOfMonth()))->subDay(),
            ],
            default => [ // 'this_month'
                Carbon::now()->startOfMonth(),
                Carbon::now(),
                Carbon::now()->subMonth()->startOfMonth(),
                Carbon::now()->subMonth()->startOfMonth()->addDays(Carbon::now()->day - 1),
            ],
        };

        $summary = $this->reportingService->getDashboardSummary(
            $vendor,
            $locationId,
            $startDate,
            $endDate,
            $prevStartDate,
            $prevEndDate
        );

        $locations = Location::where('vendor_id', $vendor->id)->get();
        $registers = CashRegister::where('vendor_id', $vendor->id)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->with(['location', 'activeShift.user'])
            ->get();

        $bankAccounts = BankAccount::where('vendor_id', $vendor->id)->get();

        $pendingApprovals = FinancialApprovalRequest::where('vendor_id', $vendor->id)
            ->where('status', 'pending')
            ->count();

        $recentShifts = PosShift::where('vendor_id', $vendor->id)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->with(['user', 'location', 'cashRegister'])
            ->latest()
            ->take(5)
            ->get();

        $recentExpenses = Expense::where('vendor_id', $vendor->id)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->with(['category', 'supplier'])
            ->latest()
            ->take(5)
            ->get();

        return view('admin.finance.dashboard', compact(
            'summary',
            'locations',
            'registers',
            'bankAccounts',
            'pendingApprovals',
            'recentShifts',
            'recentExpenses',
            'period',
            'startDate',
            'endDate',
            'locationId'
        ));
    }
}

