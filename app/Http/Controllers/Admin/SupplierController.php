<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Ingredient;
use App\Models\Supplier;
use App\Models\SupplierIngredient;
use App\Models\Unit;
use App\Security\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers.
     */
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::SUPPLIERS_VIEW);

        $vendorId = Auth::user()->vendor_id;

        $query = Supplier::where('vendor_id', $vendorId)
            ->withCount(['purchaseOrders', 'invoices']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('tax_id', 'ilike', "%{$search}%")
                    ->orWhere('contact_person', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        if ($request->boolean('with_balance')) {
            $query->where('current_balance', '>', 0);
        }

        $suppliers = $query->orderBy('name')->paginate(20)->withQueryString();

        $totalSuppliers = Supplier::where('vendor_id', $vendorId)->count();
        $activeSuppliers = Supplier::where('vendor_id', $vendorId)->where('is_active', true)->count();
        $totalDebt = (float) Supplier::where('vendor_id', $vendorId)->sum('current_balance');

        return view('admin.inventory.suppliers.index', compact(
            'suppliers',
            'totalSuppliers',
            'activeSuppliers',
            'totalDebt'
        ));
    }

    /**
     * Show form for creating a new supplier.
     */
    public function create(): View
    {
        $this->authorizePermission(Permission::SUPPLIERS_CREATE);

        $vendorId = Auth::user()->vendor_id;
        $ingredients = Ingredient::where('vendor_id', $vendorId)->where('is_active', true)->with('unit')->orderBy('name')->get();
        $units = Unit::forCurrentVendor()->where('is_active', true)->orderBy('name')->get();

        return view('admin.inventory.suppliers.create', compact('ingredients', 'units'));
    }

    /**
     * Store a newly created supplier.
     */
    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $vendor = Auth::user()->vendor;
        $data = $request->validated();
        $ingredientsData = $data['ingredients'] ?? [];
        unset($data['ingredients']);

        $data['vendor_id'] = $vendor->id;

        $supplier = DB::transaction(function () use ($data, $ingredientsData, $vendor) {
            $supplier = Supplier::create($data);

            foreach ($ingredientsData as $item) {
                if (empty($item['ingredient_id']) || ! isset($item['price'])) {
                    continue;
                }

                SupplierIngredient::create([
                    'vendor_id' => $vendor->id,
                    'supplier_id' => $supplier->id,
                    'ingredient_id' => $item['ingredient_id'],
                    'unit_id' => $item['unit_id'],
                    'price' => (float) $item['price'],
                    'supplier_sku' => $item['supplier_sku'] ?? null,
                    'is_primary_supplier' => ! empty($item['is_primary_supplier']),
                ]);
            }

            return $supplier;
        });

        return redirect()->route('admin.inventory.suppliers.show', $supplier)
            ->with('success', __('Supplier created successfully.'));
    }

    /**
     * Display supplier profile, ledger, and price catalog.
     */
    public function show(Supplier $supplier): View
    {
        $this->authorizePermission(Permission::SUPPLIERS_VIEW);

        $supplier->load([
            'supplierIngredients.ingredient.unit',
            'supplierIngredients.unit',
            'purchaseOrders.warehouse',
            'invoices.warehouse',
            'payments',
        ]);

        $vendorId = Auth::user()->vendor_id;
        $allIngredients = Ingredient::where('vendor_id', $vendorId)->where('is_active', true)->orderBy('name')->get();
        $units = Unit::forCurrentVendor()->where('is_active', true)->orderBy('name')->get();

        return view('admin.inventory.suppliers.show', compact('supplier', 'allIngredients', 'units'));
    }

    /**
     * Show form for editing supplier.
     */
    public function edit(Supplier $supplier): View
    {
        $this->authorizePermission(Permission::SUPPLIERS_UPDATE);

        $vendorId = Auth::user()->vendor_id;
        $ingredients = Ingredient::where('vendor_id', $vendorId)->where('is_active', true)->with('unit')->orderBy('name')->get();
        $units = Unit::forCurrentVendor()->where('is_active', true)->orderBy('name')->get();

        $supplier->load('supplierIngredients');

        return view('admin.inventory.suppliers.edit', compact('supplier', 'ingredients', 'units'));
    }

    /**
     * Update existing supplier.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validated();
        $ingredientsData = $data['ingredients'] ?? [];
        unset($data['ingredients']);

        DB::transaction(function () use ($supplier, $data, $ingredientsData) {
            $supplier->update($data);

            if (! empty($ingredientsData)) {
                $supplier->supplierIngredients()->delete();

                foreach ($ingredientsData as $item) {
                    if (empty($item['ingredient_id']) || ! isset($item['price'])) {
                        continue;
                    }

                    SupplierIngredient::create([
                        'vendor_id' => $supplier->vendor_id,
                        'supplier_id' => $supplier->id,
                        'ingredient_id' => $item['ingredient_id'],
                        'unit_id' => $item['unit_id'],
                        'price' => (float) $item['price'],
                        'supplier_sku' => $item['supplier_sku'] ?? null,
                        'is_primary_supplier' => ! empty($item['is_primary_supplier']),
                    ]);
                }
            }
        });

        return redirect()->route('admin.inventory.suppliers.show', $supplier)
            ->with('success', __('Supplier updated successfully.'));
    }

    /**
     * Delete supplier.
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorizePermission(Permission::SUPPLIERS_DELETE);

        if ((float) $supplier->current_balance > 0) {
            return back()->with('error', __('Cannot delete supplier with an outstanding unpaid balance of $:amount.', ['amount' => number_format($supplier->current_balance, 2)]));
        }

        $supplier->delete();

        return redirect()->route('admin.inventory.suppliers.index')
            ->with('success', __('Supplier deleted successfully.'));
    }

    /**
     * Add or update ingredient prices for this supplier.
     */
    public function savePrices(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->authorizePermission(Permission::SUPPLIERS_UPDATE);

        $request->validate([
            'ingredient_id' => ['required', 'exists:ingredients,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'supplier_sku' => ['nullable', 'string', 'max:60'],
            'is_primary_supplier' => ['nullable', 'boolean'],
        ]);

        SupplierIngredient::updateOrCreate(
            [
                'vendor_id' => $supplier->vendor_id,
                'supplier_id' => $supplier->id,
                'ingredient_id' => $request->input('ingredient_id'),
            ],
            [
                'unit_id' => $request->input('unit_id'),
                'price' => (float) $request->input('price'),
                'supplier_sku' => $request->input('supplier_sku'),
                'is_primary_supplier' => $request->boolean('is_primary_supplier'),
            ]
        );

        return back()->with('success', __('Supplier price saved successfully.'));
    }

    protected function authorizePermission(string $permission): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission($permission)) {
            abort(403, 'Unauthorized action.');
        }
    }
}
