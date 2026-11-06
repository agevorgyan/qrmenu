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
use App\Services\IngredientImportService;
use App\Services\StorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IngredientController extends Controller
{
    public function __construct(
        protected StorageService $storageService,
        protected IngredientImportService $importService
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
            $query->lowStock();
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
     * Download an Excel or CSV template for ingredient import.
     */
    public function template(Request $request): Response
    {
        $this->authorizePermission(Permission::INGREDIENTS_VIEW);

        $format = $request->query('format', 'xlsx');
        $tpl = $this->importService->generateTemplate($format);

        return response($tpl['content'], 200, [
            'Content-Type' => $tpl['mime'],
            'Content-Disposition' => 'attachment; filename="'.$tpl['filename'].'"',
        ]);
    }

    /**
     * Import ingredients from Excel or CSV file.
     */
    public function import(Request $request): RedirectResponse
    {
        $this->authorizePermission(Permission::INGREDIENTS_CREATE);

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,csv,txt'],
            'update_existing' => ['nullable', 'boolean'],
        ], [
            'file.required' => __('Please select a file to import.'),
            'file.mimes' => __('The import file must be an Excel (.xlsx) or CSV (.csv) file.'),
            'file.max' => __('The import file must not exceed 10MB.'),
        ]);

        $vendorId = Auth::user()->vendor_id;
        $updateExisting = $request->boolean('update_existing', true);

        $result = $this->importService->import(
            file: $request->file('file'),
            vendorId: $vendorId,
            updateExisting: $updateExisting
        );

        if (! $result['success'] && empty($result['created']) && empty($result['updated'])) {
            $errorMsg = ! empty($result['errors']) ? implode(' ', $result['errors']) : __('Failed to import ingredients from file.');

            return back()->with('error', $errorMsg);
        }

        $message = __('Successfully processed file: :created created, :updated updated, :skipped skipped.', [
            'created' => $result['created'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
        ]);

        if (! empty($result['errors'])) {
            return back()->with('warning', $message.' '.__('Warnings:').' '.implode(' ', array_slice($result['errors'], 0, 3)));
        }

        return back()->with('success', $message);
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
