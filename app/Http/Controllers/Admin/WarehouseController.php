<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Models\Location;
use App\Models\User;
use App\Models\Warehouse;
use App\Security\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::INVENTORY_VIEW);

        $warehouses = Warehouse::with(['location', 'responsibleUser'])
            ->withCount('balances')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->paginate(15);

        // Compute total inventory value per warehouse
        $warehouseIds = $warehouses->pluck('id')->all();
        $values = DB::table('inventory_balances')
            ->whereIn('warehouse_id', $warehouseIds)
            ->groupBy('warehouse_id')
            ->select('warehouse_id', DB::raw('SUM(total_value) as total_val'))
            ->pluck('total_val', 'warehouse_id');

        $locations = Location::orderBy('name')->get();
        $staffMembers = User::where('vendor_id', Auth::user()->vendor_id)->orderBy('name')->get();

        return view('admin.inventory.warehouses.index', compact('warehouses', 'values', 'locations', 'staffMembers'));
    }

    public function create(): View
    {
        $this->authorizePermission(Permission::INVENTORY_VIEW);

        $locations = Location::orderBy('name')->get();
        $staffMembers = User::where('vendor_id', Auth::user()->vendor_id)->orderBy('name')->get();

        return view('admin.inventory.warehouses.create', compact('locations', 'staffMembers'));
    }

    public function store(StoreWarehouseRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            if (! empty($data['is_default'])) {
                Warehouse::where('vendor_id', Auth::user()->vendor_id)->update(['is_default' => false]);
            }

            Warehouse::create($data);
        });

        return redirect()->route('admin.inventory.warehouses.index')
            ->with('success', __('Warehouse created successfully.'));
    }

    public function edit(Warehouse $warehouse): View
    {
        $this->authorizePermission(Permission::INVENTORY_VIEW);

        $locations = Location::orderBy('name')->get();
        $staffMembers = User::where('vendor_id', Auth::user()->vendor_id)->orderBy('name')->get();

        return view('admin.inventory.warehouses.edit', compact('warehouse', 'locations', 'staffMembers'));
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($warehouse, $data) {
            if (! empty($data['is_default'])) {
                Warehouse::where('vendor_id', Auth::user()->vendor_id)
                    ->where('id', '!=', $warehouse->id)
                    ->update(['is_default' => false]);
            }

            $warehouse->update($data);
        });

        return redirect()->route('admin.inventory.warehouses.index')
            ->with('success', __('Warehouse updated successfully.'));
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        $this->authorizePermission(Permission::INVENTORY_ADJUST);

        $hasStock = $warehouse->balances()->where('quantity_on_hand', '>', 0)->exists();
        if ($hasStock) {
            return back()->with('error', __('Cannot delete warehouse with active stock. Transfer or write off inventory first.'));
        }

        $warehouse->delete();

        return redirect()->route('admin.inventory.warehouses.index')
            ->with('success', __('Warehouse deleted successfully.'));
    }

    public function setDefault(Warehouse $warehouse): RedirectResponse
    {
        $this->authorizePermission(Permission::INVENTORY_VIEW);

        DB::transaction(function () use ($warehouse) {
            Warehouse::where('vendor_id', Auth::user()->vendor_id)->update(['is_default' => false]);
            $warehouse->update(['is_default' => true]);
        });

        return back()->with('success', __('Default warehouse updated.'));
    }

    protected function authorizePermission(string $permission): void
    {
        if (! Auth::user()?->hasPermission($permission)) {
            abort(403, 'Unauthorized action.');
        }
    }
}
