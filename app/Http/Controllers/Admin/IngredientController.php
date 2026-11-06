<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIngredientRequest;
use App\Http\Requests\UpdateIngredientRequest;
use App\Models\Allergen;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Unit;
use App\Security\Permission;
use App\Services\StorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IngredientController extends Controller
{
    public function __construct(
        protected StorageService $storageService
    ) {}

    /**
     * Display a listing of ingredients with search and filters.
     */
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::INGREDIENTS_VIEW);

        $vendor = Auth::user()->vendor;

        $query = Ingredient::with(['category', 'unit', 'allergens', 'balances'])
            ->latest('id');

        // Search by name, SKU or barcode
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('sku', 'ilike', "%{$search}%")
                    ->orWhere('barcode', 'ilike', "%{$search}%");
            });
        }

        // Filter by category
        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        // Filter by type
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Filter by low stock
        if ($request->boolean('low_stock')) {
            $query->whereRaw('min_stock > 0')
                ->whereHas('balances', function ($q) {
                    $q->havingRaw('SUM(quantity_on_hand - quantity_reserved) <= ingredients.min_stock');
                });
        }

        $ingredients = $query->paginate(20)->withQueryString();
        $categories = IngredientCategory::orderBy('name')->get();
        $units = Unit::forCurrentVendor()->where('is_active', true)->orderBy('name')->get();
        $allergens = Allergen::orderBy('name')->get();

        return view('admin.inventory.ingredients.index', compact('ingredients', 'categories', 'units', 'allergens'));
    }

    /**
     * Show form for creating a new ingredient.
     */
    public function create(): View
    {
        $this->authorizePermission(Permission::INGREDIENTS_CREATE);

        $categories = IngredientCategory::orderBy('name')->get();
        $units = Unit::forCurrentVendor()->where('is_active', true)->orderBy('name')->get();
        $allergens = Allergen::orderBy('name')->get();

        return view('admin.inventory.ingredients.create', compact('categories', 'units', 'allergens'));
    }

    /**
     * Store a newly created ingredient in storage.
     */
    public function store(StoreIngredientRequest $request): RedirectResponse
    {
        $vendor = Auth::user()->vendor;
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $file = $this->storageService->store(
                file: $request->file('image'),
                namespace: 'products',
                vendor: $vendor
            );
            $data['image'] = $file->getUrl();
        }

        $allergenIds = $data['allergens'] ?? [];
        unset($data['allergens']);

        DB::transaction(function () use ($data, $allergenIds) {
            $ingredient = Ingredient::create($data);
            if (! empty($allergenIds)) {
                $ingredient->allergens()->sync($allergenIds);
            }
        });

        return redirect()->route('admin.inventory.ingredients.index')
            ->with('success', __('Ingredient created successfully.'));
    }

    /**
     * Show form for editing the specified ingredient.
     */
    public function edit(Ingredient $ingredient): View
    {
        $this->authorizePermission(Permission::INGREDIENTS_UPDATE);

        $ingredient->load(['allergens', 'category', 'unit']);
        $categories = IngredientCategory::orderBy('name')->get();
        $units = Unit::forCurrentVendor()->where('is_active', true)->orderBy('name')->get();
        $allergens = Allergen::orderBy('name')->get();

        return view('admin.inventory.ingredients.edit', compact('ingredient', 'categories', 'units', 'allergens'));
    }

    /**
     * Update the specified ingredient.
     */
    public function update(UpdateIngredientRequest $request, Ingredient $ingredient): RedirectResponse
    {
        $vendor = Auth::user()->vendor;
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $file = $this->storageService->store(
                file: $request->file('image'),
                namespace: 'products',
                vendor: $vendor
            );
            $data['image'] = $file->getUrl();
        }

        $allergenIds = $data['allergens'] ?? [];
        unset($data['allergens']);

        DB::transaction(function () use ($ingredient, $data, $allergenIds) {
            $ingredient->update($data);
            $ingredient->allergens()->sync($allergenIds);
        });

        return redirect()->route('admin.inventory.ingredients.index')
            ->with('success', __('Ingredient updated successfully.'));
    }

    /**
     * Remove the specified ingredient from storage.
     */
    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        $this->authorizePermission(Permission::INGREDIENTS_DELETE);

        $ingredient->delete();

        return redirect()->route('admin.inventory.ingredients.index')
            ->with('success', __('Ingredient deleted successfully.'));
    }

    /**
     * Export ingredients catalog to CSV.
     */
    public function export(): StreamedResponse
    {
        $this->authorizePermission(Permission::INGREDIENTS_EXPORT);

        $ingredients = Ingredient::with(['category', 'unit'])->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="ingredients_catalog_'.now()->format('Y-m-d').'.csv"',
        ];

        return response()->stream(function () use ($ingredients) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'SKU', 'Name', 'Type', 'Category', 'Unit', 'Current Cost', 'Min Stock', 'Active']);

            foreach ($ingredients as $ing) {
                fputcsv($handle, [
                    $ing->id,
                    $ing->sku,
                    $ing->name,
                    $ing->type,
                    $ing->category?->name,
                    $ing->unit?->code,
                    $ing->current_cost,
                    $ing->min_stock,
                    $ing->is_active ? 'Yes' : 'No',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Enforce authorization permission.
     */
    protected function authorizePermission(string $permission): void
    {
        if (! Auth::user()?->hasPermission($permission)) {
            abort(403, 'Unauthorized action.');
        }
    }
}

