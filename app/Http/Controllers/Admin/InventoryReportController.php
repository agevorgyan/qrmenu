<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Security\Permission;
use App\Services\InventoryReportService;
use App\Services\WasteManagementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryReportController extends Controller
{
    public function __construct(
        protected InventoryReportService $reportService,
        protected WasteManagementService $wasteService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::REPORTS_VIEW);

        $vendor = $request->user()->vendor;
        $from = now()->startOfMonth();
        $to = now()->endOfDay();

        $variance = $this->reportService->getFoodCostVariance($vendor, $from, $to);
        $abc = $this->reportService->getAbcAnalysis($vendor);
        $turnover = $this->reportService->getInventoryTurnoverAndDeadStock($vendor, 30);
        $waste = $this->wasteService->getWasteSummary($vendor, $from, $to);

        return view('admin.inventory.reports.index', compact(
            'variance',
            'abc',
            'turnover',
            'waste'
        ));
    }

    public function foodCostVariance(Request $request): View
    {
        $this->authorizePermission(Permission::REPORTS_VIEW);

        $vendor = $request->user()->vendor;
        $from = $request->filled('from') ? Carbon::parse($request->from) : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->to) : now()->endOfDay();

        $report = $this->reportService->getFoodCostVariance($vendor, $from, $to);

        return view('admin.inventory.reports.food-cost-variance', compact('report', 'from', 'to'));
    }

    public function abcAnalysis(Request $request): View
    {
        $this->authorizePermission(Permission::REPORTS_VIEW);

        $vendor = $request->user()->vendor;
        $abc = $this->reportService->getAbcAnalysis($vendor);

        return view('admin.inventory.reports.abc-analysis', compact('abc'));
    }

    public function turnover(Request $request): View
    {
        $this->authorizePermission(Permission::REPORTS_VIEW);

        $vendor = $request->user()->vendor;
        $days = (int) $request->input('days', 30);
        if (! in_array($days, [15, 30, 60, 90])) {
            $days = 30;
        }

        $turnover = $this->reportService->getInventoryTurnoverAndDeadStock($vendor, $days);

        return view('admin.inventory.reports.turnover-dead-stock', compact('turnover', 'days'));
    }

    public function wasteAnalysis(Request $request): View
    {
        $this->authorizePermission(Permission::REPORTS_VIEW);

        $vendor = $request->user()->vendor;
        $from = $request->filled('from') ? Carbon::parse($request->from) : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->to) : now()->endOfDay();

        $waste = $this->wasteService->getWasteSummary($vendor, $from, $to);

        return view('admin.inventory.reports.waste-analysis', compact('waste', 'from', 'to'));
    }

    protected function authorizePermission(string $permission): void
    {
        if (! auth()->user() || ! auth()->user()->hasPermission($permission)) {
            abort(403, 'Unauthorized access.');
        }
    }
}
