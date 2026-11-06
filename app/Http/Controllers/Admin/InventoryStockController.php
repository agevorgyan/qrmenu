<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockTransactionRequest;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Security\Permission;
use App\Services\InventoryLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InventoryStockController extends Controller
{
    public function __construct(
        protected InventoryLedgerService $ledgerService
    ) {}

    /**
     * Inventory & Production section main dashboard.
     */
    public function dashboard(Request $request): View
    {
        $this->authorizePermission(Permission::INVENTORY_VIEW);

        $vendorId = Auth::user()->vendor_id;

        $totalIngredients = Ingredient::count();
        $totalWarehouses = Warehouse::count();

        $totalStockValue = (float) InventoryBalance::sum('total_value');

        // Low stock count
        $lowStockCount = Ingredient::lowStock()->count();

        // Recent 10 transactions
        $recentTransactions = InventoryTransaction::with(['warehouse', 'targetWarehouse', 'user'])
            ->latest('id')
            ->limit(10)
            ->get();

        // Low stock ingredients list
        $lowStockItems = Ingredient::with(['unit', 'balances'])
            ->whereRaw('min_stock > 0')
            ->get()
            ->filter(fn ($ing) => $ing->isLowStock())
            ->take(5);

        return view('admin.inventory.dashboard', compact(
            'totalIngredients',
            'totalWarehouses',
            'totalStockValue',
            'lowStockCount',
            'recentTransactions',
            'lowStockItems'
        ));
    }

    /**
     * Stock Balances Overview.
     */
    public function overview(Request $request): View
    {
        $this->authorizePermission(Permission::INVENTORY_VIEW);

        $query = InventoryBalance::with(['warehouse', 'ingredient.category', 'ingredient.unit'])
            ->latest('last_movement_at');

        // Filter by warehouse
        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        // Filter by category
        if ($categoryId = $request->input('category_id')) {
            $query->whereHas('ingredient', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        // Search ingredient name or SKU
        if ($search = $request->input('search')) {
            $query->whereHas('ingredient', function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('sku', 'ilike', "%{$search}%");
            });
        }

        // Filter low stock
        if ($request->boolean('low_stock')) {
            $query->whereHas('ingredient', function ($q) {
                $q->whereRaw('min_stock > 0')
                    ->whereRaw('inventory_balances.quantity_on_hand <= ingredients.min_stock');
            });
        }

        $balances = $query->paginate(25)->withQueryString();

        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        $categories = IngredientCategory::orderBy('name')->get();
        $ingredients = Ingredient::where('is_active', true)->orderBy('name')->get();
        $units = Unit::forCurrentVendor()->where('is_active', true)->orderBy('name')->get();

        $totalValuation = (float) InventoryBalance::sum('total_value');

        return view('admin.inventory.stocks.index', compact(
            'balances',
            'warehouses',
            'categories',
            'ingredients',
            'units',
            'totalValuation'
        ));
    }

    /**
     * Stock Movements & Immutable Ledger log.
     */
    public function movements(Request $request): View
    {
        $this->authorizePermission(Permission::INVENTORY_VIEW);

        $query = InventoryTransaction::with(['warehouse', 'targetWarehouse', 'user', 'items.ingredient', 'items.unit'])
            ->latest('id');

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where(function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId)
                    ->orWhere('target_warehouse_id', $warehouseId);
            });
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('date', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('date', '<=', $dateTo);
        }

        $transactions = $query->paginate(20)->withQueryString();
        $warehouses = Warehouse::orderBy('name')->get();

        return view('admin.inventory.stocks.movements', compact('transactions', 'warehouses'));
    }

    /**
     * Process stock transaction (Receipt, Issue, Transfer, Adjustment).
     */
    public function storeTransaction(StoreStockTransactionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = Auth::user();
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);

        try {
            switch ($data['type']) {
                case 'receipt':
                    $this->ledgerService->recordReceipt(
                        warehouse: $warehouse,
                        items: $data['items'],
                        user: $user,
                        notes: $data['notes'] ?? null
                    );
                    $msg = __('Stock receipt recorded successfully.');
                    break;

                case 'issue':
                case 'waste':
                    $this->ledgerService->recordIssue(
                        warehouse: $warehouse,
                        items: $data['items'],
                        type: $data['type'] === 'waste' ? InventoryTransaction::TYPE_WASTE : InventoryTransaction::TYPE_ISSUE,
                        user: $user,
                        notes: $data['notes'] ?? null
                    );
                    $msg = __('Stock issue recorded successfully.');
                    break;

                case 'transfer':
                    $targetWarehouse = Warehouse::findOrFail($data['target_warehouse_id']);
                    $this->ledgerService->recordTransfer(
                        sourceWarehouse: $warehouse,
                        targetWarehouse: $targetWarehouse,
                        items: $data['items'],
                        user: $user,
                        notes: $data['notes'] ?? null
                    );
                    $msg = __('Stock transfer recorded successfully.');
                    break;

                case 'adjustment':
                    $ingredient = Ingredient::findOrFail($data['ingredient_id']);
                    $this->ledgerService->recordAdjustment(
                        warehouse: $warehouse,
                        ingredient: $ingredient,
                        actualQuantity: (float) $data['actual_quantity'],
                        reason: $data['reason'] ?? $data['notes'] ?? null,
                        user: $user
                    );
                    $msg = __('Stock adjustment recorded successfully.');
                    break;

                default:
                    return back()->with('error', __('Invalid transaction type.'));
            }

            return redirect()->route('admin.inventory.stocks.overview')
                ->with('success', $msg);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    protected function authorizePermission(string $permission): void
    {
        if (! Auth::user()?->hasPermission($permission)) {
            abort(403, 'Unauthorized action.');
        }
    }
}
