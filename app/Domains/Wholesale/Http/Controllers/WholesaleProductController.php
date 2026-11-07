<?php

namespace App\Domains\Wholesale\Http\Controllers;

use App\Domains\Wholesale\Models\WholesaleProduct;
use App\Domains\Wholesale\Services\WholesaleCatalogService;
use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WholesaleProductController extends Controller
{
    public function __construct(
        protected WholesaleCatalogService $catalogService,
        protected TenantContext $tenantContext
    ) {}

    /**
     * Display a listing of wholesale products.
     */
    public function index(Request $request): View
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;
        $filters = $request->only(['search', 'category_id', 'is_active']);

        $products = $this->catalogService->listProducts($vendor, $filters, 15);
        $categories = $this->catalogService->listCategories($vendor);

        return view('wholesale.products.index', compact('vendor', 'products', 'categories', 'filters'));
    }

    /**
     * Show the form for creating a new wholesale product.
     */
    public function create(Request $request): View
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;
        $categories = $this->catalogService->listCategories($vendor);
        $units = Unit::where(function ($q) use ($vendor) {
            $q->whereNull('vendor_id')->orWhere('vendor_id', $vendor->id);
        })->where('is_active', true)->orderBy('name')->get();

        $physicalIngredients = Ingredient::where('vendor_id', $vendor->id)->where('is_active', true)->orderBy('name')->get();
        $atgaaCodes = $this->catalogService->getAtgaaClassifications();

        return view('wholesale.products.create', compact('vendor', 'categories', 'units', 'physicalIngredients', 'atgaaCodes'));
    }

    /**
     * Store a newly created wholesale product in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required',
                'string',
                'max:80',
                Rule::unique('wholesale_products', 'sku')->where('vendor_id', $vendor->id),
            ],
            'barcode' => ['nullable', 'string', 'max:100'],
            'gtin' => ['nullable', 'string', 'max:50'],
            'category_id' => ['nullable', 'integer', Rule::exists('wholesale_categories', 'id')->where('vendor_id', $vendor->id)],
            'classifier_code' => ['required', 'string', 'exists:wholesale_atgaa_classifications,code'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'packaging_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'units_per_package' => ['nullable', 'numeric', 'min:0.0001'],
            'min_order_quantity' => ['nullable', 'numeric', 'min:0.0001'],
            'order_quantity_multiple' => ['nullable', 'numeric', 'min:0.0001'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'base_wholesale_price' => ['required', 'numeric', 'min:0'],
            'min_wholesale_price' => ['nullable', 'numeric', 'min:0', 'lte:base_wholesale_price'],
            'is_vat_applicable' => ['boolean'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'physical_stock_ingredient_id' => ['nullable', 'integer', Rule::exists('ingredients', 'id')->where('vendor_id', $vendor->id)],
            'description' => ['nullable', 'string'],
            'packaging_type' => ['nullable', 'string', 'max:50'],
            'packaging_units_count' => ['nullable', 'numeric', 'min:1'],
        ], [
            'min_wholesale_price.lte' => __('Minimum wholesale price cannot exceed base wholesale price.'),
            'classifier_code.exists' => __('The selected ATGAA / FEACN classification code is not recognized.'),
        ]);

        $product = $this->catalogService->createProduct($vendor, $validated);

        return redirect()->route('admin.wholesale.products.show', $product)
            ->with('success', __('Wholesale product ":name" [SKU: :sku] has been added.', ['name' => $product->name, 'sku' => $product->sku]));
    }

    /**
     * Display the specified wholesale product.
     */
    public function show(WholesaleProduct $product): View
    {
        $product->load(['category', 'unit', 'packagingUnit', 'physicalStockIngredient', 'packagings', 'atgaaClassification']);
        $vendor = $this->tenantContext->getTenant();

        return view('wholesale.products.show', compact('product', 'vendor'));
    }

    /**
     * Show the form for editing the specified wholesale product.
     */
    public function edit(WholesaleProduct $product): View
    {
        $vendor = $this->tenantContext->getTenant();
        $categories = $this->catalogService->listCategories($vendor);
        $units = Unit::where(function ($q) use ($vendor) {
            $q->whereNull('vendor_id')->orWhere('vendor_id', $vendor->id);
        })->where('is_active', true)->orderBy('name')->get();

        $physicalIngredients = Ingredient::where('vendor_id', $vendor->id)->where('is_active', true)->orderBy('name')->get();
        $atgaaCodes = $this->catalogService->getAtgaaClassifications();

        return view('wholesale.products.edit', compact('product', 'vendor', 'categories', 'units', 'physicalIngredients', 'atgaaCodes'));
    }

    /**
     * Update the specified wholesale product in storage.
     */
    public function update(Request $request, WholesaleProduct $product): RedirectResponse
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required',
                'string',
                'max:80',
                Rule::unique('wholesale_products', 'sku')->where('vendor_id', $vendor->id)->ignore($product->id),
            ],
            'barcode' => ['nullable', 'string', 'max:100'],
            'gtin' => ['nullable', 'string', 'max:50'],
            'category_id' => ['nullable', 'integer', Rule::exists('wholesale_categories', 'id')->where('vendor_id', $vendor->id)],
            'classifier_code' => ['required', 'string', 'exists:wholesale_atgaa_classifications,code'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'packaging_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'units_per_package' => ['nullable', 'numeric', 'min:0.0001'],
            'min_order_quantity' => ['nullable', 'numeric', 'min:0.0001'],
            'order_quantity_multiple' => ['nullable', 'numeric', 'min:0.0001'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'base_wholesale_price' => ['required', 'numeric', 'min:0'],
            'min_wholesale_price' => ['nullable', 'numeric', 'min:0', 'lte:base_wholesale_price'],
            'is_vat_applicable' => ['boolean'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'physical_stock_ingredient_id' => ['nullable', 'integer', Rule::exists('ingredients', 'id')->where('vendor_id', $vendor->id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $this->catalogService->updateProduct($product, $validated);

        return redirect()->route('admin.wholesale.products.show', $product)
            ->with('success', __('Wholesale product updated successfully.'));
    }

    /**
     * Add a packaging tier to a product.
     */
    public function storePackaging(Request $request, WholesaleProduct $product): RedirectResponse
    {
        $validated = $request->validate([
            'packaging_type' => ['required', 'string', 'max:50'],
            'packaging_barcode' => ['nullable', 'string', 'max:100'],
            'units_count' => ['required', 'numeric', 'min:1'],
            'gross_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'dimensions' => ['nullable', 'string', 'max:50'],
        ]);

        $this->catalogService->addPackaging($product, $validated);

        return redirect()->back()->with('success', __('Packaging tier added successfully.'));
    }
}

