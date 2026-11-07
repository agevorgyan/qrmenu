<?php

declare(strict_types=1);

namespace App\Domains\Wholesale\Services;

use App\Domains\Wholesale\Models\WholesaleCustomer;
use App\Domains\Wholesale\Models\WholesaleDelivery;
use App\Domains\Wholesale\Models\WholesaleReturn;
use App\Domains\Wholesale\Models\WholesaleSale;
use App\Domains\Wholesale\Models\WholesaleSaleItem;
use App\Domains\Wholesale\Models\WholesaleTaxDocument;
use App\Models\Vendor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WholesaleReportService
{
    public function __construct(
        protected WholesaleAccountingService $accountingService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getExecutiveSummary(Vendor $vendor, ?string $fromDate = null, ?string $toDate = null): array
    {
        $salesQuery = WholesaleSale::where('vendor_id', $vendor->id)
            ->where('status', '!=', 'CANCELLED');

        if ($fromDate) {
            $salesQuery->whereDate('sale_date', '>=', $fromDate);
        }
        if ($toDate) {
            $salesQuery->whereDate('sale_date', '<=', $toDate);
        }

        $totalRevenue = (float) $salesQuery->sum('total_amount');
        $totalTaxable = (float) $salesQuery->sum('subtotal');
        $totalVat = (float) $salesQuery->sum('tax_total');
        $totalPaid = (float) $salesQuery->sum('paid_amount');
        $totalSalesCount = $salesQuery->count();

        // Customer Outstanding A/R Balance
        $totalAr = (float) WholesaleCustomer::where('vendor_id', $vendor->id)->sum('current_balance');

        // Deliveries
        $deliveryQuery = WholesaleDelivery::where('vendor_id', $vendor->id);
        if ($fromDate) {
            $deliveryQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $deliveryQuery->whereDate('created_at', '<=', $toDate);
        }
        $totalDeliveries = $deliveryQuery->count();
        $completedDeliveries = (clone $deliveryQuery)->where('status', 'DELIVERED')->count();

        // Returns
        $returnQuery = WholesaleReturn::where('vendor_id', $vendor->id);
        if ($fromDate) {
            $returnQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $returnQuery->whereDate('created_at', '<=', $toDate);
        }
        $totalReturnsCount = $returnQuery->count();
        $totalReturnsAmount = (float) $returnQuery->sum('total_return_amount');

        return [
            'total_revenue' => $totalRevenue,
            'total_taxable' => $totalTaxable,
            'total_vat' => $totalVat,
            'total_paid' => $totalPaid,
            'sales_count' => $totalSalesCount,
            'outstanding_ar' => $totalAr,
            'total_deliveries' => $totalDeliveries,
            'completed_deliveries' => $completedDeliveries,
            'total_returns_count' => $totalReturnsCount,
            'total_returns_amount' => $totalReturnsAmount,
        ];
    }

    public function getSalesBySeller(Vendor $vendor, ?string $fromDate = null, ?string $toDate = null): Collection
    {
        $query = WholesaleSale::where('wholesale_sales.vendor_id', $vendor->id)
            ->where('wholesale_sales.status', '!=', 'CANCELLED')
            ->join('wholesale_sellers', 'wholesale_sales.seller_id', '=', 'wholesale_sellers.id')
            ->select(
                'wholesale_sellers.id as seller_id',
                'wholesale_sellers.name as seller_name',
                'wholesale_sellers.legal_name',
                'wholesale_sellers.tin',
                'wholesale_sellers.tax_regime',
                DB::raw('COUNT(wholesale_sales.id) as sales_count'),
                DB::raw('SUM(wholesale_sales.subtotal) as total_subtotal'),
                DB::raw('SUM(wholesale_sales.tax_total) as total_tax'),
                DB::raw('SUM(wholesale_sales.total_amount) as grand_total'),
                DB::raw('SUM(wholesale_sales.paid_amount) as total_paid')
            )
            ->groupBy(
                'wholesale_sellers.id',
                'wholesale_sellers.name',
                'wholesale_sellers.legal_name',
                'wholesale_sellers.tin',
                'wholesale_sellers.tax_regime'
            )
            ->orderByDesc('grand_total');

        if ($fromDate) {
            $query->whereDate('wholesale_sales.sale_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('wholesale_sales.sale_date', '<=', $toDate);
        }

        return $query->get();
    }

    public function getSalesByCustomer(Vendor $vendor, ?string $fromDate = null, ?string $toDate = null): Collection
    {
        $query = WholesaleSale::where('wholesale_sales.vendor_id', $vendor->id)
            ->where('wholesale_sales.status', '!=', 'CANCELLED')
            ->join('wholesale_customers', 'wholesale_sales.customer_id', '=', 'wholesale_customers.id')
            ->select(
                'wholesale_customers.id as customer_id',
                'wholesale_customers.name as customer_name',
                'wholesale_customers.legal_name',
                'wholesale_customers.tin',
                'wholesale_customers.current_balance',
                'wholesale_customers.credit_limit',
                DB::raw('COUNT(wholesale_sales.id) as sales_count'),
                DB::raw('SUM(wholesale_sales.total_amount) as total_purchased'),
                DB::raw('SUM(wholesale_sales.paid_amount) as total_paid')
            )
            ->groupBy(
                'wholesale_customers.id',
                'wholesale_customers.name',
                'wholesale_customers.legal_name',
                'wholesale_customers.tin',
                'wholesale_customers.current_balance',
                'wholesale_customers.credit_limit'
            )
            ->orderByDesc('total_purchased');

        if ($fromDate) {
            $query->whereDate('wholesale_sales.sale_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('wholesale_sales.sale_date', '<=', $toDate);
        }

        return $query->get();
    }

    public function getSalesByProduct(Vendor $vendor, ?string $fromDate = null, ?string $toDate = null): Collection
    {
        $query = WholesaleSaleItem::join('wholesale_sales', 'wholesale_sale_items.sale_id', '=', 'wholesale_sales.id')
            ->where('wholesale_sales.vendor_id', $vendor->id)
            ->where('wholesale_sales.status', '!=', 'CANCELLED')
            ->select(
                'wholesale_sale_items.product_id',
                'wholesale_sale_items.product_name',
                'wholesale_sale_items.sku',
                'wholesale_sale_items.classifier_code as atgaa',
                DB::raw('SUM(wholesale_sale_items.quantity) as total_quantity'),
                DB::raw('SUM(wholesale_sale_items.subtotal) as total_subtotal'),
                DB::raw('SUM(wholesale_sale_items.vat_amount) as total_vat'),
                DB::raw('SUM(wholesale_sale_items.total_amount) as total_revenue')
            )
            ->groupBy(
                'wholesale_sale_items.product_id',
                'wholesale_sale_items.product_name',
                'wholesale_sale_items.sku',
                'wholesale_sale_items.classifier_code'
            )
            ->orderByDesc('total_revenue');

        if ($fromDate) {
            $query->whereDate('wholesale_sales.sale_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('wholesale_sales.sale_date', '<=', $toDate);
        }

        return $query->get();
    }

    public function getSalesByAtgaa(Vendor $vendor, ?string $fromDate = null, ?string $toDate = null): Collection
    {
        $query = WholesaleSaleItem::join('wholesale_sales', 'wholesale_sale_items.sale_id', '=', 'wholesale_sales.id')
            ->where('wholesale_sales.vendor_id', $vendor->id)
            ->where('wholesale_sales.status', '!=', 'CANCELLED')
            ->select(
                'wholesale_sale_items.classifier_code as atgaa_code',
                DB::raw('COUNT(DISTINCT wholesale_sale_items.product_id) as products_count'),
                DB::raw('SUM(wholesale_sale_items.quantity) as total_units_sold'),
                DB::raw('SUM(wholesale_sale_items.subtotal) as total_base'),
                DB::raw('SUM(wholesale_sale_items.vat_amount) as total_vat'),
                DB::raw('SUM(wholesale_sale_items.total_amount) as total_gross')
            )
            ->groupBy('wholesale_sale_items.classifier_code')
            ->orderByDesc('total_gross');

        if ($fromDate) {
            $query->whereDate('wholesale_sales.sale_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('wholesale_sales.sale_date', '<=', $toDate);
        }

        return $query->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function getTaxSummary(Vendor $vendor, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = WholesaleTaxDocument::where('vendor_id', $vendor->id)
            ->where('status', '!=', 'CANCELLED');

        if ($fromDate) {
            $query->whereDate('issue_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('issue_date', '<=', $toDate);
        }

        $docs = $query->get();

        $vatInvoices = $docs->where('document_type', 'TAX_INVOICE');
        $accountingDocs = $docs->where('document_type', 'ACCOUNTING_DOCUMENT');

        return [
            'vat' => [
                'count' => $vatInvoices->count(),
                'taxable' => (float) $vatInvoices->sum('taxable_amount'),
                'vat_amount' => (float) $vatInvoices->sum('vat_amount'),
                'total' => (float) $vatInvoices->sum('total_amount'),
            ],
            'turnover' => [
                'count' => $accountingDocs->count(),
                'taxable' => (float) $accountingDocs->sum('taxable_amount'),
                'vat_amount' => 0.00,
                'total' => (float) $accountingDocs->sum('total_amount'),
            ],
            'totals' => [
                'count' => $docs->count(),
                'taxable' => (float) $docs->sum('taxable_amount'),
                'vat_amount' => (float) $docs->sum('vat_amount'),
                'total' => (float) $docs->sum('total_amount'),
            ],
        ];
    }
}
