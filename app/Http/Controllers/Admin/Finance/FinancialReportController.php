<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Admin\Finance\Concerns\AuthorizesFinance;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Security\Permission;
use App\Services\FinancialReportingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class FinancialReportController extends Controller
{
    use AuthorizesFinance;

    public function __construct(
        protected FinancialReportingService $reportingService
    ) {}

    public function index(): View
    {
        $this->authorizePermission(Permission::FINANCE_REPORTS_VIEW);

        return view('admin.finance.reports.index');
    }

    public function profitAndLoss(Request $request): View
    {
        $this->authorizePermission(Permission::FINANCE_REPORTS_VIEW);

        $vendor = $this->getActiveVendor();
        $locationId = $request->filled('location_id') ? (int) $request->input('location_id') : null;

        $startDate = $request->filled('start_date') ? Carbon::parse($request->input('start_date')) : Carbon::now()->startOfMonth();
        $endDate = $request->filled('end_date') ? Carbon::parse($request->input('end_date')) : Carbon::now();

        $pnl = $this->reportingService->getProfitAndLoss($vendor, $locationId, $startDate, $endDate);
        $locations = Location::where('vendor_id', $vendor->id)->get();

        return view('admin.finance.reports.profit_loss', compact('pnl', 'locations', 'startDate', 'endDate', 'locationId'));
    }

    public function balanceSheet(Request $request): View
    {
        $this->authorizePermission(Permission::FINANCE_REPORTS_VIEW);

        $vendor = $this->getActiveVendor();
        $locationId = $request->filled('location_id') ? (int) $request->input('location_id') : null;
        $asOfDate = $request->filled('as_of_date') ? Carbon::parse($request->input('as_of_date')) : Carbon::now();

        $balanceSheet = $this->reportingService->getBalanceSheet($vendor, $locationId, $asOfDate);
        $locations = Location::where('vendor_id', $vendor->id)->get();

        return view('admin.finance.reports.balance_sheet', compact('balanceSheet', 'locations', 'asOfDate', 'locationId'));
    }

    public function cashFlow(Request $request): View
    {
        $this->authorizePermission(Permission::FINANCE_REPORTS_VIEW);

        $vendor = $this->getActiveVendor();
        $locationId = $request->filled('location_id') ? (int) $request->input('location_id') : null;

        $startDate = $request->filled('start_date') ? Carbon::parse($request->input('start_date')) : Carbon::now()->startOfMonth();
        $endDate = $request->filled('end_date') ? Carbon::parse($request->input('end_date')) : Carbon::now();

        $cashFlow = $this->reportingService->getCashFlowStatement($vendor, $locationId, $startDate, $endDate);
        $locations = Location::where('vendor_id', $vendor->id)->get();

        return view('admin.finance.reports.cash_flow', compact('cashFlow', 'locations', 'startDate', 'endDate', 'locationId'));
    }

    public function trialBalance(Request $request): View
    {
        $this->authorizePermission(Permission::FINANCE_REPORTS_VIEW);

        $vendor = $this->getActiveVendor();
        $locationId = $request->filled('location_id') ? (int) $request->input('location_id') : null;
        $asOfDate = $request->filled('as_of_date') ? Carbon::parse($request->input('as_of_date')) : Carbon::now();

        $trialBalance = $this->reportingService->getTrialBalance($vendor, $locationId, $asOfDate);
        $locations = Location::where('vendor_id', $vendor->id)->get();

        return view('admin.finance.reports.trial_balance', compact('trialBalance', 'locations', 'asOfDate', 'locationId'));
    }

    public function salesAnalytics(Request $request): View
    {
        $this->authorizePermission(Permission::FINANCE_REPORTS_VIEW);

        $vendor = $this->getActiveVendor();
        $locationId = $request->filled('location_id') ? (int) $request->input('location_id') : null;

        $startDate = $request->filled('start_date') ? Carbon::parse($request->input('start_date')) : Carbon::now()->startOfMonth();
        $endDate = $request->filled('end_date') ? Carbon::parse($request->input('end_date')) : Carbon::now();

        $analytics = $this->reportingService->getSalesAnalytics($vendor, $locationId, $startDate, $endDate);
        $locations = Location::where('vendor_id', $vendor->id)->get();

        return view('admin.finance.reports.sales_analytics', compact('analytics', 'locations', 'startDate', 'endDate', 'locationId'));
    }

    public function export(Request $request, string $type): Response
    {
        $this->authorizePermission(Permission::FINANCE_REPORTS_EXPORT);

        $vendor = $this->getActiveVendor();
        $locationId = $request->filled('location_id') ? (int) $request->input('location_id') : null;
        $startDate = $request->filled('start_date') ? Carbon::parse($request->input('start_date')) : Carbon::now()->startOfMonth();
        $endDate = $request->filled('end_date') ? Carbon::parse($request->input('end_date')) : Carbon::now();

        $csvContent = '';

        if ($type === 'profit-loss') {
            $data = $this->reportingService->getProfitAndLoss($vendor, $locationId, $startDate, $endDate);
            $csvContent = "Category,Account Code,Account Name,Amount ({$data['currency']})\n";
            $csvContent .= 'Gross Revenue,,,'.$data['revenue']['gross_revenue']."\n";
            foreach ($data['revenue']['gross_accounts'] as $a) {
                $csvContent .= "Revenue,{$a['code']},\"{$a['name']}\",{$a['net_amount']}\n";
            }
            $csvContent .= 'Contra Revenue / Discounts / Returns,,,-'.$data['revenue']['total_contra']."\n";
            $csvContent .= 'Net Revenue,,,'.$data['revenue']['net_revenue']."\n";
            $csvContent .= 'Cost of Goods Sold (COGS),,,-'.$data['cogs']['total_cogs']."\n";
            $csvContent .= 'Gross Profit,,,'.$data['gross_profit']."\n";
            $csvContent .= 'Operating Expenses (OPEX),,,-'.$data['operating_expenses']['total_opex']."\n";
            foreach ($data['operating_expenses']['accounts'] as $a) {
                $csvContent .= "Operating Expense,{$a['code']},\"{$a['name']}\",{$a['net_amount']}\n";
            }
            $csvContent .= 'Operating Profit (EBITDA),,,'.$data['operating_profit']."\n";
            $csvContent .= 'Net Profit,,,'.$data['net_profit']."\n";
        } elseif ($type === 'trial-balance') {
            $data = $this->reportingService->getTrialBalance($vendor, $locationId, $endDate);
            $csvContent = "Account Code,Account Name,Type,Debit ({$data['currency']}),Credit ({$data['currency']})\n";
            foreach ($data['rows'] as $r) {
                $csvContent .= "{$r['code']},\"{$r['name']}\",{$r['type']},{$r['net_debit']},{$r['net_credit']}\n";
            }
            $csvContent .= "TOTAL,,,{$data['total_debit']},{$data['total_credit']}\n";
        }

        $filename = "financial-report-{$type}-".Carbon::now()->format('Y-m-d').'.csv';

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
