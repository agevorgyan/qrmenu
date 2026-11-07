<?php

namespace App\Domains\Wholesale\Http\Controllers;

use App\Domains\Wholesale\Models\WholesaleCustomer;
use App\Domains\Wholesale\Models\WholesaleDelivery;
use App\Domains\Wholesale\Models\WholesaleOrder;
use App\Domains\Wholesale\Models\WholesaleProduct;
use App\Domains\Wholesale\Models\WholesaleSale;
use App\Domains\Wholesale\Models\WholesaleSeller;
use App\Http\Controllers\Controller;
use App\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class WholesaleDashboardController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Display Wholesale subsystem overview dashboard.
     */
    public function index(Request $request): View
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;
        $vendorId = $vendor->id;

        $sellerCount = WholesaleSeller::where('vendor_id', $vendorId)->where('is_active', true)->count();
        $customerCount = Schema::hasTable('wholesale_customers')
            ? WholesaleCustomer::where('vendor_id', $vendorId)->where('is_active', true)->count()
            : 0;
        $productCount = Schema::hasTable('wholesale_products')
            ? WholesaleProduct::where('vendor_id', $vendorId)->where('is_active', true)->count()
            : 0;

        $activeSellers = WholesaleSeller::where('vendor_id', $vendorId)->with('activeTaxProfile')->get();

        $stats = [
            'sellers_count' => $sellerCount,
            'customers_count' => $customerCount,
            'products_count' => $productCount,
            'pending_orders_count' => Schema::hasTable('wholesale_orders')
                ? WholesaleOrder::where('vendor_id', $vendorId)->whereIn('status', ['draft', 'pending_approval', 'confirmed'])->count()
                : 0,
            'monthly_sales_total' => Schema::hasTable('wholesale_sales')
                ? WholesaleSale::where('vendor_id', $vendorId)
                    ->where('sale_date', '>=', Carbon::now()->startOfMonth()->toDateString())
                    ->sum('total_amount')
                : 0.00,
            'total_receivables' => Schema::hasTable('wholesale_customers')
                ? WholesaleCustomer::where('vendor_id', $vendorId)->sum('current_balance')
                : 0.00,
            'active_deliveries_count' => Schema::hasTable('wholesale_deliveries')
                ? WholesaleDelivery::where('vendor_id', $vendorId)
                    ->whereIn('status', ['planned', 'loaded', 'in_transit'])
                    ->count()
                : 0,
        ];

        return view('wholesale.dashboard', compact('vendor', 'stats', 'activeSellers'));
    }
}
