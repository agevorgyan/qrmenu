<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnitConversionRequest;
use App\Http\Requests\StoreUnitRequest;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Security\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::INGREDIENTS_VIEW);

        $units = Unit::forCurrentVendor()
            ->with(['baseUnit'])
            ->orderBy('is_system', 'desc')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $conversions = UnitConversion::forCurrentVendor()
            ->with(['fromUnit', 'toUnit', 'ingredient'])
            ->latest('id')
            ->paginate(20);

        $ingredients = Ingredient::orderBy('name')->get();

        return view('admin.inventory.units.index', compact('units', 'conversions', 'ingredients'));
    }

    public function store(StoreUnitRequest $request): RedirectResponse
    {
        $vendor = Auth::user()->vendor;
        $data = $request->validated();
        $data['vendor_id'] = $vendor->id;
        $data['is_system'] = false;
        $data['is_active'] = true;

        Unit::create($data);

        return redirect()->route('admin.inventory.units.index')
            ->with('success', __('Custom unit created successfully.'));
    }

    public function storeConversion(StoreUnitConversionRequest $request): RedirectResponse
    {
        $vendor = Auth::user()->vendor;
        $data = $request->validated();
        $data['vendor_id'] = $vendor->id;

        UnitConversion::updateOrCreate(
            [
                'vendor_id' => $vendor->id,
                'ingredient_id' => $data['ingredient_id'] ?? null,
                'from_unit_id' => $data['from_unit_id'],
                'to_unit_id' => $data['to_unit_id'],
            ],
            [
                'factor' => $data['factor'],
            ]
        );

        return redirect()->route('admin.inventory.units.index')
            ->with('success', __('Unit conversion rule saved successfully.'));
    }

    public function destroyConversion(UnitConversion $conversion): RedirectResponse
    {
        $this->authorizePermission(Permission::INGREDIENTS_UPDATE);

        if ($conversion->vendor_id && (int) $conversion->vendor_id !== (int) Auth::user()->vendor_id) {
            abort(403);
        }

        $conversion->delete();

        return redirect()->route('admin.inventory.units.index')
            ->with('success', __('Unit conversion deleted successfully.'));
    }

    protected function authorizePermission(string $permission): void
    {
        if (! Auth::user()?->hasPermission($permission)) {
            abort(403, 'Unauthorized action.');
        }
    }
}
