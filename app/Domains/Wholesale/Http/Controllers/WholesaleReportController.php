<?php

declare(strict_types=1);

namespace App\Domains\Wholesale\Http\Controllers;

use App\Domains\Wholesale\Services\WholesaleAccountingService;
use App\Domains\Wholesale\Services\WholesaleReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class WholesaleReportController extends Controller
{
    public function __construct(
        protected WholesaleReportService $reportService,
        protected WholesaleAccountingService $accountingService
    ) {}

    public function index(Request $request): View
    {
        $vendor = $request->user()->vendor ?? abort(403, 'Tenant context missing');

        $preset = $request->query('preset', 'this_month');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        if ($preset && ! ($fromDate || $toDate)) {
            match ($preset) {
                'today' => [$fromDate = now()->toDateString(), $toDate = now()->toDateString()],
                'this_week' => [$fromDate = now()->startOfWeek()->toDateString(), $toDate = now()->endOfWeek()->toDateString()],
                'this_month' => [$fromDate = now()->startOfMonth()->toDateString(), $toDate = now()->endOfMonth()->toDateString()],
                'this_quarter' => [$fromDate = now()->startOfQuarter()->toDateString(), $toDate = now()->endOfQuarter()->toDateString()],
                'all_time' => [$fromDate = null, $toDate = null],
                default => null,
            };
        }

        $summary = $this->reportService->getExecutiveSummary($vendor, $fromDate, $toDate);
        $salesBySeller = $this->reportService->getSalesBySeller($vendor, $fromDate, $toDate);
        $salesByCustomer = $this->reportService->getSalesByCustomer($vendor, $fromDate, $toDate);
        $salesByProduct = $this->reportService->getSalesByProduct($vendor, $fromDate, $toDate);
        $salesByAtgaa = $this->reportService->getSalesByAtgaa($vendor, $fromDate, $toDate);
        $taxSummary = $this->reportService->getTaxSummary($vendor, $fromDate, $toDate);
        $arAging = $this->accountingService->getAccountsReceivableSummary($vendor);

        return view('wholesale.reports.index', compact(
            'summary',
            'salesBySeller',
            'salesByCustomer',
            'salesByProduct',
            'salesByAtgaa',
            'taxSummary',
            'arAging',
            'preset',
            'fromDate',
            'toDate'
        ));
    }

    public function exportCsv(Request $request, string $type): Response
    {
        $vendor = $request->user()->vendor ?? abort(403, 'Tenant context missing');

        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"wholesale-report-{$type}-".now()->format('Ymd').'.csv"',
        ];

        $output = fopen('php://temp', 'r+');
        // Add BOM for Excel UTF-8 support
        fwrite($output, "\xEF\xBB\xBF");

        match ($type) {
            'sellers' => $this->exportSellersCsv($output, $vendor, $fromDate, $toDate),
            'customers' => $this->exportCustomersCsv($output, $vendor, $fromDate, $toDate),
            'products' => $this->exportProductsCsv($output, $vendor, $fromDate, $toDate),
            'atgaa' => $this->exportAtgaaCsv($output, $vendor, $fromDate, $toDate),
            default => abort(400, "Unknown export type: {$type}"),
        };

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return response((string) $content, 200, $headers);
    }

    protected function exportSellersCsv($stream, $vendor, $fromDate, $toDate): void
    {
        fputcsv($stream, ['Seller Name', 'Legal Name', 'TIN (ՀՎՀՀ)', 'Tax Regime', 'Sales Count', 'Subtotal (AMD)', 'VAT Total (AMD)', 'Grand Total (AMD)', 'Paid Total (AMD)']);
        $rows = $this->reportService->getSalesBySeller($vendor, $fromDate, $toDate);
        foreach ($rows as $r) {
            fputcsv($stream, [
                $r->seller_name,
                $r->legal_name,
                $r->tin,
                $r->tax_regime,
                $r->sales_count,
                number_format((float) $r->total_subtotal, 2, '.', ''),
                number_format((float) $r->total_tax, 2, '.', ''),
                number_format((float) $r->grand_total, 2, '.', ''),
                number_format((float) $r->total_paid, 2, '.', ''),
            ]);
        }
    }

    protected function exportCustomersCsv($stream, $vendor, $fromDate, $toDate): void
    {
        fputcsv($stream, ['Customer Name', 'Legal Name', 'TIN (ՀՎՀՀ)', 'Sales Count', 'Total Purchased (AMD)', 'Total Paid (AMD)', 'Current Balance (AMD)', 'Credit Limit (AMD)']);
        $rows = $this->reportService->getSalesByCustomer($vendor, $fromDate, $toDate);
        foreach ($rows as $r) {
            fputcsv($stream, [
                $r->customer_name,
                $r->legal_name,
                $r->tin,
                $r->sales_count,
                number_format((float) $r->total_purchased, 2, '.', ''),
                number_format((float) $r->total_paid, 2, '.', ''),
                number_format((float) $r->current_balance, 2, '.', ''),
                number_format((float) $r->credit_limit, 2, '.', ''),
            ]);
        }
    }

    protected function exportProductsCsv($stream, $vendor, $fromDate, $toDate): void
    {
        fputcsv($stream, ['Product Name', 'SKU', 'ATGAA (ԱՏԳ ԱԱ)', 'Units Sold', 'Subtotal (AMD)', 'VAT (AMD)', 'Total Revenue (AMD)']);
        $rows = $this->reportService->getSalesByProduct($vendor, $fromDate, $toDate);
        foreach ($rows as $r) {
            fputcsv($stream, [
                $r->product_name,
                $r->sku,
                $r->atgaa,
                $r->total_quantity,
                number_format((float) $r->total_subtotal, 2, '.', ''),
                number_format((float) $r->total_vat, 2, '.', ''),
                number_format((float) $r->total_revenue, 2, '.', ''),
            ]);
        }
    }

    protected function exportAtgaaCsv($stream, $vendor, $fromDate, $toDate): void
    {
        fputcsv($stream, ['ATGAA Code (ԱՏԳ ԱԱ)', 'Products Count', 'Units Sold', 'Base Taxable (AMD)', 'VAT (AMD)', 'Total Gross (AMD)']);
        $rows = $this->reportService->getSalesByAtgaa($vendor, $fromDate, $toDate);
        foreach ($rows as $r) {
            fputcsv($stream, [
                $r->atgaa_code,
                $r->products_count,
                $r->total_units_sold,
                number_format((float) $r->total_base, 2, '.', ''),
                number_format((float) $r->total_vat, 2, '.', ''),
                number_format((float) $r->total_gross, 2, '.', ''),
            ]);
        }
    }
}
