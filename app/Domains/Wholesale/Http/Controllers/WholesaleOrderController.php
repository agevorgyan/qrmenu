<?php

namespace App\Domains\Wholesale\Http\Controllers;

use App\Domains\Wholesale\Models\WholesaleCustomer;
use App\Domains\Wholesale\Models\WholesaleOrder;
use App\Domains\Wholesale\Services\WholesaleOrderService;
use App\Http\Controllers\Controller;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WholesaleOrderController extends Controller
{
    public function __construct(
        protected WholesaleOrderService $orderService,
        protected TenantContext $tenantContext
    ) {}

    /**
     * Display a listing of orders.
     */
    public function index(Request $request): View
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;
        $filters = $request->only(['status', 'customer_id', 'search']);

        $orders = $this->orderService->listOrders($vendor, $filters, 15);
        $customers = WholesaleCustomer::where('vendor_id', $vendor->id)->orderBy('name')->get();

        return view('wholesale.orders.index', compact('vendor', 'orders', 'customers', 'filters'));
    }

    /**
     * Show order details.
     */
    public function show(WholesaleOrder $order): View
    {
        $order->load(['seller', 'customer.defaultAddress', 'deliveryAddress', 'items.product.unit', 'sales']);
        $vendor = $this->tenantContext->getTenant();

        return view('wholesale.orders.show', compact('order', 'vendor'));
    }

    /**
     * Confirm a draft order.
     */
    public function confirm(Request $request, WholesaleOrder $order): RedirectResponse
    {
        $this->orderService->confirmOrder($order, $request->has('allow_credit_override'));

        return redirect()->route('admin.wholesale.orders.show', $order)
            ->with('success', __('Order :num confirmed successfully.', ['num' => $order->order_number]));
    }

    /**
     * Generate commercial sale invoice from order.
     */
    public function generateSale(Request $request, WholesaleOrder $order): RedirectResponse
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'in:CASH,BANK_TRANSFER,CREDIT_TERMS,CARD'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $sale = $this->orderService->createSale($vendor, $validated, $order, $request->user());

        return redirect()->route('admin.wholesale.sales.show', $sale)
            ->with('success', __('Commercial Sale :num created from Order :order.', [
                'num' => $sale->invoice_number,
                'order' => $order->order_number,
            ]));
    }
}
