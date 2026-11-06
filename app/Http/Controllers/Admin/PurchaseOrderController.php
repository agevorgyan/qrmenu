<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReceiveGoodsRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierIngredient;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Security\Permission;
use App\Services\ProcurementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct(
        protected ProcurementService $procurementService
    ) {}

    /**
     * Display a listing of purchase orders.
     */
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::PROCUREMENT_VIEW);

        $vendorId = Auth::user()->vendor_id;

        $query = PurchaseOrder::where('vendor_id', $vendorId)
            ->with(['supplier', 'warehouse', 'items']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'ilike', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search) {
                        $sq->where('name', 'ilike', "%{$search}%");
                    });
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($supplierId = $request->input('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        $purchaseOrders = $query->orderByDesc('order_date')->paginate(20)->withQueryString();

        $suppliers = Supplier::where('vendor_id', $vendorId)->where('is_active', true)->orderBy('name')->get();
        $warehouses = Warehouse::where('vendor_id', $vendorId)->where('is_active', true)->orderBy('name')->get();

        $totalCount = PurchaseOrder::where('vendor_id', $vendorId)->count();
        $draftCount = PurchaseOrder::where('vendor_id', $vendorId)->where('status', PurchaseOrder::STATUS_DRAFT)->count();
        $approvedCount = PurchaseOrder::where('vendor_id', $vendorId)->where('status', PurchaseOrder::STATUS_APPROVED)->count();
        $pendingDeliveryCount = PurchaseOrder::where('vendor_id', $vendorId)->whereIn('status', [PurchaseOrder::STATUS_SENT, PurchaseOrder::STATUS_PARTIALLY_RECEIVED])->count();

        return view('admin.inventory.procurement.index', compact(
            'purchaseOrders',
            'suppliers',
            'warehouses',
            'totalCount',
            'draftCount',
            'approvedCount',
            'pendingDeliveryCount'
        ));
    }

    /**
     * Show form for creating a new purchase order.
     */
    public function create(): View
    {
        $this->authorizePermission(Permission::PROCUREMENT_CREATE);

        $vendor = Auth::user()->vendor;
        $suppliers = Supplier::where('vendor_id', $vendor->id)->where('is_active', true)->orderBy('name')->get();
        $warehouses = Warehouse::where('vendor_id', $vendor->id)->where('is_active', true)->orderBy('name')->get();
        $categories = IngredientCategory::where('vendor_id', $vendor->id)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $ingredients = Ingredient::where('vendor_id', $vendor->id)->where('is_active', true)->with(['unit', 'category'])->orderBy('name')->get();
        $units = Unit::forCurrentVendor()->where('is_active', true)->orderBy('name')->get();

        $suggestedItems = $this->procurementService->suggestReplenishment($vendor);

        $supplierIngredients = SupplierIngredient::where('vendor_id', $vendor->id)
            ->with(['ingredient.unit', 'unit'])
            ->get();

        $supplierIngredientsData = [];
        foreach ($supplierIngredients as $si) {
            $sId = (string) $si->supplier_id;
            if (! isset($supplierIngredientsData[$sId])) {
                $supplierIngredientsData[$sId] = [];
            }
            $supplierIngredientsData[$sId][] = [
                'ingredient_id' => (string) $si->ingredient_id,
                'name' => $si->ingredient?->name ?? '',
                'unit_id' => (string) ($si->unit_id ?? $si->ingredient?->unit_id ?? ''),
                'unit_code' => $si->unit?->code ?? $si->ingredient?->unit?->code ?? '',
                'price' => (float) ($si->price ?? $si->ingredient?->last_purchase_price ?? $si->ingredient?->current_cost ?? 0),
                'discount_percent' => (float) ($si->discount_percent ?? 0),
                'supplier_sku' => $si->supplier_sku ?? '',
            ];
        }

        $ingredientsData = $ingredients->map(fn ($ing) => [
            'id' => (string) $ing->id,
            'name' => $ing->name,
            'sku' => $ing->sku,
            'unit_id' => (string) $ing->unit_id,
            'unit_code' => $ing->unit?->code ?? '',
            'category_id' => (string) ($ing->category_id ?? ''),
            'category_name' => $ing->category?->name ?? '',
            'cost' => (float) ($ing->last_purchase_price ?? $ing->current_cost ?? 0),
        ])->values()->all();

        $suppliersData = $suppliers->map(fn ($s) => [
            'id' => (string) $s->id,
            'name' => $s->name,
            'discount_percent' => (float) ($s->discount_percent ?? 0),
            'tax_id' => $s->tax_id ?? '',
        ])->values()->all();

        return view('admin.inventory.procurement.create', compact(
            'suppliers',
            'warehouses',
            'categories',
            'ingredients',
            'units',
            'suggestedItems',
            'supplierIngredientsData',
            'ingredientsData',
            'suppliersData'
        ));
    }

    /**
     * Store newly created purchase order.
     */
    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $vendor = Auth::user()->vendor;
        $po = $this->procurementService->createPurchaseOrder($vendor, $request->validated(), Auth::user());

        return redirect()->route('admin.inventory.procurement.show', $po)
            ->with('success', __('Purchase Order created successfully as draft.'));
    }

    /**
     * Show form for editing an existing purchase order.
     */
    public function edit(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        $this->authorizePermission(Permission::PROCUREMENT_CREATE);

        if (
            $purchaseOrder->status === PurchaseOrder::STATUS_RECEIVED ||
            $purchaseOrder->status === PurchaseOrder::STATUS_PARTIALLY_RECEIVED ||
            $purchaseOrder->items()->where('quantity_received', '>', 0)->exists()
        ) {
            return redirect()->route('admin.inventory.procurement.show', $purchaseOrder)
                ->with('error', __('Cannot edit a purchase order that has already received stock.'));
        }

        $purchaseOrder->load(['supplier', 'warehouse', 'items.ingredient.unit', 'items.unit']);

        $vendor = Auth::user()->vendor;
        $suppliers = Supplier::where('vendor_id', $vendor->id)->where('is_active', true)->orderBy('name')->get();
        $warehouses = Warehouse::where('vendor_id', $vendor->id)->where('is_active', true)->orderBy('name')->get();
        $categories = IngredientCategory::where('vendor_id', $vendor->id)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $ingredients = Ingredient::where('vendor_id', $vendor->id)->where('is_active', true)->with(['unit', 'category'])->orderBy('name')->get();
        $units = Unit::forCurrentVendor()->where('is_active', true)->orderBy('name')->get();

        $suggestedItems = $this->procurementService->suggestReplenishment($vendor);

        $supplierIngredients = SupplierIngredient::where('vendor_id', $vendor->id)
            ->with(['ingredient.unit', 'unit'])
            ->get();

        $supplierIngredientsData = [];
        foreach ($supplierIngredients as $si) {
            $sId = (string) $si->supplier_id;
            if (! isset($supplierIngredientsData[$sId])) {
                $supplierIngredientsData[$sId] = [];
            }
            $supplierIngredientsData[$sId][] = [
                'ingredient_id' => (string) $si->ingredient_id,
                'name' => $si->ingredient?->name ?? '',
                'unit_id' => (string) ($si->unit_id ?? $si->ingredient?->unit_id ?? ''),
                'unit_code' => $si->unit?->code ?? $si->ingredient?->unit?->code ?? '',
                'price' => (float) ($si->price ?? $si->ingredient?->last_purchase_price ?? $si->ingredient?->current_cost ?? 0),
                'discount_percent' => (float) ($si->discount_percent ?? 0),
                'supplier_sku' => $si->supplier_sku ?? '',
            ];
        }

        $ingredientsData = $ingredients->map(fn ($ing) => [
            'id' => (string) $ing->id,
            'name' => $ing->name,
            'sku' => $ing->sku,
            'unit_id' => (string) $ing->unit_id,
            'unit_code' => $ing->unit?->code ?? '',
            'category_id' => (string) ($ing->category_id ?? ''),
            'category_name' => $ing->category?->name ?? '',
            'cost' => (float) ($ing->last_purchase_price ?? $ing->current_cost ?? 0),
        ])->values()->all();

        $suppliersData = $suppliers->map(fn ($s) => [
            'id' => (string) $s->id,
            'name' => $s->name,
            'discount_percent' => (float) ($s->discount_percent ?? 0),
            'tax_id' => $s->tax_id ?? '',
        ])->values()->all();

        $existingItemsData = old('items', $purchaseOrder->items->map(fn ($item) => [
            'ingredient_id' => (string) $item->ingredient_id,
            'ingredient_name' => $item->ingredient?->name ?? '',
            'category_name' => $item->ingredient?->category?->name ?? '',
            'quantity' => (float) $item->quantity_ordered,
            'unit_id' => (string) $item->unit_id,
            'unit_price' => (float) $item->unit_price,
            'discount_percent' => (float) ($item->discount_percent ?? 0),
            'tax_percent' => (float) ($item->tax_percent ?? 0),
        ])->values()->all());

        return view('admin.inventory.procurement.edit', compact(
            'purchaseOrder',
            'suppliers',
            'warehouses',
            'categories',
            'ingredients',
            'units',
            'suggestedItems',
            'supplierIngredientsData',
            'ingredientsData',
            'suppliersData',
            'existingItemsData'
        ));
    }

    /**
     * Update an existing purchase order.
     */
    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            $po = $this->procurementService->updatePurchaseOrder($purchaseOrder, $request->validated(), Auth::user());

            return redirect()->route('admin.inventory.procurement.show', $po)
                ->with('success', __('Purchase Order #:number updated successfully.', ['number' => $po->po_number]));
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Delete an unreceived purchase order.
     */
    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorizePermission(Permission::PROCUREMENT_CREATE);

        $poNumber = $purchaseOrder->po_number;

        try {
            $this->procurementService->deletePurchaseOrder($purchaseOrder, Auth::user());

            return redirect()->route('admin.inventory.procurement.index')
                ->with('success', __('Purchase Order #:number deleted successfully.', ['number' => $poNumber]));
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Display a purchase order details and goods receipt panel.
     */
    public function show(PurchaseOrder $purchaseOrder): View
    {
        $this->authorizePermission(Permission::PROCUREMENT_VIEW);

        $purchaseOrder->load([
            'supplier',
            'warehouse',
            'items.ingredient.unit',
            'items.unit',
            'invoices.warehouse',
            'invoices.payments',
            'approver',
            'creator',
        ]);

        return view('admin.inventory.procurement.show', compact('purchaseOrder'));
    }

    /**
     * Print official purchase order document.
     */
    public function print(PurchaseOrder $purchaseOrder): View
    {
        $this->authorizePermission(Permission::PROCUREMENT_VIEW);

        $purchaseOrder->load([
            'supplier',
            'warehouse',
            'items.ingredient.unit',
            'items.unit',
            'approver',
            'creator',
        ]);

        return view('admin.inventory.procurement.print', compact('purchaseOrder'));
    }

    /**
     * Approve purchase order.
     */
    public function approve(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorizePermission(Permission::PROCUREMENT_APPROVE);

        $this->procurementService->approvePurchaseOrder($purchaseOrder, Auth::user());

        return back()->with('success', __('Purchase Order approved successfully.'));
    }

    /**
     * Mark purchase order as sent to supplier.
     */
    public function markSent(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorizePermission(Permission::PROCUREMENT_APPROVE);

        $this->procurementService->markAsSent($purchaseOrder);

        return back()->with('success', __('Purchase Order marked as sent to supplier.'));
    }

    /**
     * Receive goods from purchase order into warehouse stock.
     */
    public function receive(ReceiveGoodsRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorizePermission(Permission::PROCUREMENT_RECEIVE);

        $invoice = $this->procurementService->receiveGoodsFromPo(
            po: $purchaseOrder,
            receivedItems: $request->input('items'),
            invoiceNumber: $request->input('invoice_number'),
            dueDate: $request->input('due_date'),
            user: Auth::user(),
            notes: $request->input('notes')
        );

        return back()->with('success', __('Stock received and logged to warehouse. Invoice #:invoice created.', ['invoice' => $invoice->invoice_number]));
    }

    /**
     * Cancel a purchase order.
     */
    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorizePermission(Permission::PROCUREMENT_APPROVE);

        if ($purchaseOrder->status === PurchaseOrder::STATUS_RECEIVED) {
            return back()->with('error', __('Cannot cancel a purchase order that has already been received.'));
        }

        $purchaseOrder->status = PurchaseOrder::STATUS_CANCELLED;
        $purchaseOrder->save();

        return back()->with('success', __('Purchase Order cancelled.'));
    }

    /**
     * Auto replenishment suggestions API.
     */
    public function suggestions(): JsonResponse
    {
        $this->authorizePermission(Permission::PROCUREMENT_VIEW);

        $vendor = Auth::user()->vendor;
        $suggestions = $this->procurementService->suggestReplenishment($vendor);

        return response()->json($suggestions);
    }

    protected function authorizePermission(string $permission): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission($permission)) {
            abort(403, 'Unauthorized action.');
        }
    }
}
