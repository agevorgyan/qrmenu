<?php

namespace App\Domains\Wholesale\Http\Controllers;

use App\Domains\Wholesale\Models\WholesaleCustomer;
use App\Domains\Wholesale\Models\WholesaleSale;
use App\Domains\Wholesale\Services\WholesaleOrderService;
use App\Http\Controllers\Controller;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WholesaleSaleController extends Controller
{
    public function __construct(
        protected WholesaleOrderService $orderService,
        protected TenantContext $tenantContext
    ) {}

    /**
     * Display a listing of sales.
     */
    public function index(Request $request): View
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;
        $filters = $request->only(['status', 'customer_id', 'search']);

        $sales = $this->orderService->listSales($vendor, $filters, 15);
        $customers = WholesaleCustomer::where('vendor_id', $vendor->id)->orderBy('name')->get();

        return view('wholesale.sales.index', compact('vendor', 'sales', 'customers', 'filters'));
    }

    /**
     * Display the specified sale details.
     */
    public function show(WholesaleSale $sale): View
    {
        $sale->load(['seller.taxProfile', 'seller.defaultBankAccount', 'customer', 'items.product.unit', 'order']);
        $vendor = $this->tenantContext->getTenant();

        return view('wholesale.sales.show', compact('sale', 'vendor'));
    }

    /**
     * Printable view of commercial wholesale invoice (Հաշիվ-Ապրանքագիր).
     */
    public function print(WholesaleSale $sale): View
    {
        $sale->load(['seller.taxProfile', 'seller.defaultBankAccount', 'customer.defaultAddress', 'items.product.unit', 'order']);
        $vendor = $this->tenantContext->getTenant();

        return view('wholesale.sales.print', compact('sale', 'vendor'));
    }
}
