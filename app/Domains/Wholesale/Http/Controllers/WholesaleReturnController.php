<?php

namespace App\Domains\Wholesale\Http\Controllers;

use App\Domains\Wholesale\Models\WholesaleCustomer;
use App\Domains\Wholesale\Models\WholesaleProduct;
use App\Domains\Wholesale\Models\WholesaleReturn;
use App\Domains\Wholesale\Services\WholesaleDeliveryService;
use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WholesaleReturnController extends Controller
{
    public function __construct(
        protected WholesaleDeliveryService $deliveryService,
        protected TenantContext $tenantContext
    ) {}

    /**
     * Display a listing of returns.
     */
    public function index(Request $request): View
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;
        $filters = $request->only(['status', 'customer_id']);

        $returns = $this->deliveryService->listReturns($vendor, $filters, 15);

        return view('wholesale.returns.index', compact('vendor', 'returns', 'filters'));
    }

    /**
     * Show form to create a return.
     */
    public function create(Request $request): View
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;
        $customers = WholesaleCustomer::where('vendor_id', $vendor->id)->where('is_active', true)->orderBy('name')->get();
        $warehouses = Warehouse::where('vendor_id', $vendor->id)->where('is_active', true)->get();
        $products = WholesaleProduct::where('vendor_id', $vendor->id)->where('is_active', true)->get();

        return view('wholesale.returns.create', compact('vendor', 'customers', 'warehouses', 'products'));
    }

    /**
     * Store and process a customer return.
     */
    public function store(Request $request): RedirectResponse
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        $validated = $request->validate([
            'customer_id' => ['required', 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
            'reason' => ['required', 'string', 'max:100'],
            'action_type' => ['required', 'string', 'in:RESTOCK,WASTE,REFUND_CREDIT'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.reason_detail' => ['nullable', 'string', 'max:255'],
        ]);

        $return = $this->deliveryService->processReturn($vendor, $validated, $request->user());

        return redirect()->route('admin.wholesale.returns.show', $return)
            ->with('success', __('Customer return :num processed successfully.', ['num' => $return->return_number]));
    }

    /**
     * Display the specified return.
     */
    public function show(WholesaleReturn $return): View
    {
        $return->load(['customer', 'warehouse', 'items.product.unit']);
        $vendor = $this->tenantContext->getTenant();

        return view('wholesale.returns.show', compact('return', 'vendor'));
    }
}
