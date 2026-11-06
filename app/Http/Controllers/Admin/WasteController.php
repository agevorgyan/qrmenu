<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\InventoryBatch;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\WasteLog;
use App\Security\Permission;
use App\Services\WasteManagementService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WasteController extends Controller
{
    public function __construct(
        protected WasteManagementService $wasteService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::INVENTORY_VIEW);

        $vendor = $request->user()->vendor;
        $query = WasteLog::where('vendor_id', $vendor->id)
            ->with(['ingredient.unit', 'warehouse', 'unit', 'batch', 'loggedBy']);

        if ($request->filled('reason')) {
            $query->where('reason', $request->reason);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('ingredient_id')) {
            $query->where('ingredient_id', $request->ingredient_id);
        }

        $tz = $vendor->getTimezone();
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('logged_at', [
                Carbon::parse($request->from, $tz)->startOfDay()->utc(),
                Carbon::parse($request->to, $tz)->endOfDay()->utc(),
            ]);
        }

        $logs = $query->orderBy('logged_at', 'desc')->paginate(25)->withQueryString();

        $from = $request->filled('from') ? Carbon::parse($request->from, $tz)->startOfDay() : $vendor->getLocalTime()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->to, $tz)->endOfDay() : $vendor->getLocalTime()->endOfDay();
        $summary = $this->wasteService->getWasteSummary($vendor, $from, $to);

        $warehouses = Warehouse::where('vendor_id', $vendor->id)->where('is_active', true)->get();
        $ingredients = Ingredient::where('vendor_id', $vendor->id)->where('is_active', true)->orderBy('name')->get();

        return view('admin.inventory.waste.index', compact('logs', 'summary', 'warehouses', 'ingredients'));
    }

    public function create(Request $request): View
    {
        $this->authorizePermission(Permission::INVENTORY_WASTE);

        $vendor = $request->user()->vendor;
        $warehouses = Warehouse::where('vendor_id', $vendor->id)->where('is_active', true)->get();
        $ingredients = Ingredient::where('vendor_id', $vendor->id)->where('is_active', true)->with('unit')->orderBy('name')->get();
        $batches = InventoryBatch::where('vendor_id', $vendor->id)
            ->where('remaining_quantity', '>', 0)
            ->where('status', InventoryBatch::STATUS_ACTIVE)
            ->with('ingredient')
            ->orderBy('expiry_date', 'asc')
            ->get();
        $units = Unit::where(function ($q) use ($vendor) {
            $q->whereNull('vendor_id')->orWhere('vendor_id', $vendor->id);
        })->where('is_active', true)->get();

        return view('admin.inventory.waste.create', compact('warehouses', 'ingredients', 'batches', 'units'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission(Permission::INVENTORY_WASTE);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'ingredient_id' => ['required', 'exists:ingredients,id'],
            'batch_id' => ['nullable', 'exists:inventory_batches,id'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:50'],
            'action_taken' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'logged_at' => ['nullable', 'date'],
        ]);

        $vendor = $request->user()->vendor;
        $log = $this->wasteService->recordWaste($vendor, $validated, $request->user());

        return redirect()->route('admin.inventory.waste.index')
            ->with('success', __('Waste write-off :number recorded successfully.', ['number' => $log->waste_number]));
    }

    public function show(WasteLog $wasteLog): View
    {
        $this->authorizePermission(Permission::INVENTORY_VIEW);

        $vendor = auth()->user()->vendor;
        if ($wasteLog->vendor_id !== $vendor->id) {
            abort(403, 'Cross-tenant access forbidden.');
        }

        $wasteLog->load(['ingredient.unit', 'warehouse', 'unit', 'batch', 'loggedBy', 'authorizedBy', 'inventoryTransaction.items']);

        return view('admin.inventory.waste.show', compact('wasteLog'));
    }

    /**
     * Print official waste and spoilage write-off act.
     */
    public function print(WasteLog $wasteLog): View
    {
        $this->authorizePermission(Permission::INVENTORY_VIEW);

        $vendor = auth()->user()->vendor;
        if ($wasteLog->vendor_id !== $vendor->id) {
            abort(403, 'Cross-tenant access forbidden.');
        }

        $wasteLog->load(['ingredient.unit', 'warehouse', 'unit', 'batch', 'loggedBy', 'authorizedBy']);

        return view('admin.inventory.waste.print', compact('wasteLog'));
    }

    protected function authorizePermission(string $permission): void
    {
        if (! auth()->user() || ! auth()->user()->hasPermission($permission)) {
            abort(403, 'Unauthorized access.');
        }
    }
}
