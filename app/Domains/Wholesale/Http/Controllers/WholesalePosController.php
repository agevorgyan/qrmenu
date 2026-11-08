<?php

namespace App\Domains\Wholesale\Http\Controllers;

use App\Domains\Wholesale\Models\WholesaleCategory;
use App\Domains\Wholesale\Models\WholesaleCustomer;
use App\Domains\Wholesale\Models\WholesaleProduct;
use App\Domains\Wholesale\Models\WholesaleSeller;
use App\Domains\Wholesale\Services\WholesaleOrderService;
use App\Domains\Wholesale\Services\WholesalePricingEngine;
use App\Http\Controllers\Controller;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WholesalePosController extends Controller
{
    public function __construct(
        protected WholesaleOrderService $orderService,
        protected WholesalePricingEngine $pricingEngine,
        protected TenantContext $tenantContext
    ) {}

    /**
     * Display the Wholesale POS Terminal interface.
     */
    public function index(Request $request): View
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        $sellers = WholesaleSeller::where('vendor_id', $vendor->id)->where('is_active', true)->with('taxProfile')->get();
        $customers = WholesaleCustomer::where('vendor_id', $vendor->id)->where('is_active', true)->with('defaultAddress')->get();
        $categories = WholesaleCategory::where('vendor_id', $vendor->id)->orderBy('name')->get();
        $products = WholesaleProduct::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->with(['unit', 'packagings', 'category'])
            ->orderBy('name')
            ->get();

        return view('wholesale.pos.index', compact('vendor', 'sellers', 'customers', 'categories', 'products'));
    }

    /**
     * Dynamically calculate pricing and taxes for a selected product and customer line.
     */
    public function calculateLine(Request $request): JsonResponse
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        $validated = $request->validate([
            'seller_id' => ['nullable', 'integer'],
            'product_id' => ['required', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
        ]);

        $product = WholesaleProduct::where('vendor_id', $vendor->id)->findOrFail($validated['product_id']);
        $seller = ! empty($validated['seller_id'])
            ? WholesaleSeller::where('vendor_id', $vendor->id)->find($validated['seller_id'])
            : null;
        $customer = ! empty($validated['customer_id'])
            ? WholesaleCustomer::where('vendor_id', $vendor->id)->find($validated['customer_id'])
            : null;

        $pricing = $this->pricingEngine->resolvePrice(
            product: $product,
            customer: $customer,
            quantity: (float) $validated['quantity'],
            allowOverride: false,
            seller: $seller
        );

        return response()->json([
            'success' => true,
            'pricing' => $pricing,
        ]);
    }

    /**
     * Process checkout from the Wholesale POS terminal.
     */
    public function checkout(Request $request): RedirectResponse
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        $validated = $request->validate([
            'seller_id' => ['required', 'integer'],
            'customer_id' => ['required', 'integer'],
            'order_type' => ['required', 'string', 'in:STANDARD,PREORDER'],
            'action_type' => ['required', 'string', 'in:CREATE_ORDER,CONFIRM_AND_SALE'],
            'payment_method' => ['nullable', 'string', 'in:CASH,BANK_TRANSFER,CREDIT_TERMS,CARD'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'expected_delivery_date' => ['nullable', 'date'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
        ]);

        $order = $this->orderService->createOrder($vendor, $validated, $request->user());

        if ($validated['action_type'] === 'CONFIRM_AND_SALE') {
            $this->orderService->confirmOrder($order, false);
            $sale = $this->orderService->createSale($vendor, [
                'payment_method' => $validated['payment_method'] ?? 'BANK_TRANSFER',
                'paid_amount' => $validated['paid_amount'] ?? 0,
                'notes' => $validated['notes'] ?? null,
            ], $order, $request->user());

            return redirect()->route('admin.wholesale.sales.show', $sale)
                ->with('success', __('Wholesale Sale :num created from POS.', ['num' => $sale->invoice_number]));
        }

        return redirect()->route('admin.wholesale.orders.show', $order)
            ->with('success', __('Wholesale Order :num drafted successfully.', ['num' => $order->order_number]));
    }
}
