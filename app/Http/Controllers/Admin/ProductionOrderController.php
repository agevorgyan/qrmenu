<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteProductionOrderRequest;
use App\Http\Requests\StoreProductionOrderRequest;
use App\Models\Ingredient;
use App\Models\ProductionOrder;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Security\Permission;
use App\Services\ProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionOrderController extends Controller
{
    public function __construct(
        protected ProductionService $productionService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::PRODUCTION_VIEW);

        $vendor = $request->user()->vendor;
        $query = ProductionOrder::where('vendor_id', $vendor->id)
            ->with(['recipe', 'producedIngredient', 'sourceWarehouse', 'destinationWarehouse', 'unit', 'producedBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('recipe_id')) {
            $query->where('recipe_id', $request->recipe_id);
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('order_number', 'ILIKE', "%{$s}%")
                    ->orWhere('batch_number', 'ILIKE', "%{$s}%")
                    ->orWhereHas('recipe', fn ($rq) => $rq->where('name', 'ILIKE', "%{$s}%"));
            });
        }

        $orders = $query->orderBy('id', 'desc')->paginate(20)->withQueryString();

        $metrics = [
            'in_progress' => ProductionOrder::where('vendor_id', $vendor->id)->where('status', ProductionOrder::STATUS_IN_PROGRESS)->count(),
            'completed' => ProductionOrder::where('vendor_id', $vendor->id)->where('status', ProductionOrder::STATUS_COMPLETED)->count(),
            'total_cost_completed' => (float) ProductionOrder::where('vendor_id', $vendor->id)->where('status', ProductionOrder::STATUS_COMPLETED)->sum('total_cost'),
        ];

        $recipes = Recipe::where('vendor_id', $vendor->id)->orderBy('name')->get();

        return view('admin.inventory.production.index', compact('orders', 'metrics', 'recipes'));
    }

    public function create(Request $request): View
    {
        $this->authorizePermission(Permission::PRODUCTION_CREATE);

        $vendor = $request->user()->vendor;

        $recipes = Recipe::where('vendor_id', $vendor->id)
            ->with(['yieldUnit', 'items.ingredient.unit', 'items.subRecipe'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::where('vendor_id', $vendor->id)->where('is_active', true)->orderBy('name')->get();
        $ingredients = Ingredient::where('vendor_id', $vendor->id)
            ->whereIn('type', [Ingredient::TYPE_SEMI_FINISHED, Ingredient::TYPE_FINISHED])
            ->orderBy('name')
            ->get();
        $units = Unit::all();

        $preselectedRecipe = null;
        if ($request->filled('recipe_id')) {
            $preselectedRecipe = $recipes->firstWhere('id', (int) $request->recipe_id);
        }

        return view('admin.inventory.production.create', compact('recipes', 'warehouses', 'ingredients', 'units', 'preselectedRecipe'));
    }

    public function store(StoreProductionOrderRequest $request): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        $order = $this->productionService->createOrder($vendor, $request->validated(), $request->user());

        return redirect()->route('admin.inventory.production.show', $order)
            ->with('success', __('Production order created successfully.'));
    }

    public function show(ProductionOrder $productionOrder, Request $request): View
    {
        $this->authorizePermission(Permission::PRODUCTION_VIEW);
        $this->ensureTenantOwnership($productionOrder, $request->user());

        $productionOrder->load([
            'recipe.yieldUnit',
            'producedIngredient.unit',
            'sourceWarehouse',
            'destinationWarehouse',
            'unit',
            'producedBy',
            'items.ingredient.unit',
            'items.subRecipe.yieldUnit',
            'items.unit',
            'batches',
            'issueTransaction',
            'receiptTransaction',
        ]);

        return view('admin.inventory.production.show', compact('productionOrder'));
    }

    /**
     * Print production and batch completion act.
     */
    public function print(ProductionOrder $productionOrder, Request $request): View
    {
        $this->authorizePermission(Permission::PRODUCTION_VIEW);
        $this->ensureTenantOwnership($productionOrder, $request->user());

        $productionOrder->load([
            'recipe.yieldUnit',
            'producedIngredient.unit',
            'sourceWarehouse',
            'destinationWarehouse',
            'unit',
            'producedBy',
            'items.ingredient.unit',
            'items.subRecipe.yieldUnit',
            'items.unit',
        ]);

        return view('admin.inventory.production.print', compact('productionOrder'));
    }

    public function start(ProductionOrder $productionOrder, Request $request): RedirectResponse
    {
        $this->authorizePermission(Permission::PRODUCTION_START);
        $this->ensureTenantOwnership($productionOrder, $request->user());

        try {
            $this->productionService->startOrder($productionOrder, $request->user());

            return redirect()->route('admin.inventory.production.show', $productionOrder)
                ->with('success', __('Production order started.'));
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function complete(ProductionOrder $productionOrder, CompleteProductionOrderRequest $request): RedirectResponse
    {
        $this->authorizePermission(Permission::PRODUCTION_COMPLETE);
        $this->ensureTenantOwnership($productionOrder, $request->user());

        try {
            $this->productionService->completeOrder($productionOrder, $request->validated(), $request->user());

            return redirect()->route('admin.inventory.production.show', $productionOrder)
                ->with('success', __('Production completed! Stock received and raw materials deducted.'));
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(ProductionOrder $productionOrder, Request $request): RedirectResponse
    {
        $this->authorizePermission(Permission::PRODUCTION_CANCEL);
        $this->ensureTenantOwnership($productionOrder, $request->user());

        try {
            $this->productionService->cancelOrder($productionOrder, $request->input('reason', 'Cancelled by user'));

            return redirect()->route('admin.inventory.production.show', $productionOrder)
                ->with('success', __('Production order cancelled.'));
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(ProductionOrder $productionOrder, Request $request): RedirectResponse
    {
        $this->authorizePermission(Permission::PRODUCTION_DELETE);
        $this->ensureTenantOwnership($productionOrder, $request->user());

        if ($productionOrder->isCompleted()) {
            return back()->with('error', __('Cannot delete an already completed production order.'));
        }

        $productionOrder->delete();

        return redirect()->route('admin.inventory.production.index')
            ->with('success', __('Production order deleted.'));
    }

    protected function authorizePermission(string $permission): void
    {
        if (! auth()->user() || ! auth()->user()->hasPermission($permission)) {
            abort(403, 'Unauthorized access.');
        }
    }

    protected function ensureTenantOwnership(ProductionOrder $order, $user): void
    {
        if ((int) $order->vendor_id !== (int) $user->vendor_id) {
            abort(404, 'Production order not found.');
        }
    }
}
