<?php

namespace App\Domains\Wholesale\Http\Controllers;

use App\Domains\Wholesale\Models\WholesaleDelivery;
use App\Domains\Wholesale\Models\WholesaleOrder;
use App\Domains\Wholesale\Services\WholesaleDeliveryService;
use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WholesaleDeliveryController extends Controller
{
    public function __construct(
        protected WholesaleDeliveryService $deliveryService,
        protected TenantContext $tenantContext
    ) {}

    /**
     * Display a listing of deliveries.
     */
    public function index(Request $request): View
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;
        $filters = $request->only(['status', 'order_id']);

        $deliveries = $this->deliveryService->listDeliveries($vendor, $filters, 15);

        return view('wholesale.deliveries.index', compact('vendor', 'deliveries', 'filters'));
    }

    /**
     * Show form to plan a new delivery for an order.
     */
    public function create(Request $request): View
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;
        $orderId = $request->query('order_id');
        $order = WholesaleOrder::where('vendor_id', $vendor->id)
            ->whereIn('status', ['CONFIRMED', 'PARTIALLY_DELIVERED'])
            ->with(['items.product.unit', 'customer.addresses'])
            ->findOrFail($orderId);

        $warehouses = Warehouse::where('vendor_id', $vendor->id)->where('is_active', true)->orderBy('is_default', 'desc')->orderBy('name')->get();
        if ($warehouses->isEmpty()) {
            $defaultWh = Warehouse::create([
                'vendor_id' => $vendor->id,
                'name' => __('Հիմնական Կենտրոնական Պահեստ'),
                'code' => 'WH-MAIN',
                'type' => Warehouse::TYPE_MAIN,
                'is_active' => true,
                'is_default' => true,
            ]);
            $warehouses = collect([$defaultWh]);
        }

        return view('wholesale.deliveries.create', compact('vendor', 'order', 'warehouses'));
    }

    /**
     * Store a newly planned delivery.
     */
    public function store(Request $request): RedirectResponse
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
            'destination_address_id' => ['nullable', 'integer'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'driver_phone' => ['nullable', 'string', 'max:50'],
            'vehicle_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
        ]);

        $order = WholesaleOrder::where('vendor_id', $vendor->id)->findOrFail($validated['order_id']);
        $delivery = $this->deliveryService->planDelivery($order, $validated, $request->user());

        return redirect()->route('admin.wholesale.deliveries.show', $delivery)
            ->with('success', __('Delivery :num scheduled successfully.', ['num' => $delivery->delivery_number]));
    }

    /**
     * Display the specified delivery.
     */
    public function show(WholesaleDelivery $delivery): View
    {
        $delivery->load(['order.customer', 'warehouse', 'destinationAddress', 'items.product.unit', 'items.orderItem']);
        $vendor = $this->tenantContext->getTenant();

        return view('wholesale.deliveries.show', compact('delivery', 'vendor'));
    }

    /**
     * Dispatch delivery.
     */
    public function dispatch(WholesaleDelivery $delivery): RedirectResponse
    {
        $this->deliveryService->dispatchDelivery($delivery);

        return redirect()->route('admin.wholesale.deliveries.show', $delivery)
            ->with('success', __('Delivery dispatched with driver.'));
    }

    /**
     * Confirm delivery with proof of delivery and stock depletion.
     */
    public function confirm(Request $request, WholesaleDelivery $delivery): RedirectResponse
    {
        $validated = $request->validate([
            'received_by_name' => ['nullable', 'string', 'max:100'],
            'proof_of_delivery_note' => ['nullable', 'string'],
        ]);

        $this->deliveryService->confirmDelivery($delivery, $validated, $request->user());

        return redirect()->route('admin.wholesale.deliveries.show', $delivery)
            ->with('success', __('Delivery confirmed and physical inventory updated.'));
    }
}
