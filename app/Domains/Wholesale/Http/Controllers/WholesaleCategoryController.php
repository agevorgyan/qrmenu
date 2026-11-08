<?php

namespace App\Domains\Wholesale\Http\Controllers;

use App\Domains\Wholesale\Models\WholesaleCategory;
use App\Domains\Wholesale\Services\WholesaleCatalogService;
use App\Http\Controllers\Controller;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WholesaleCategoryController extends Controller
{
    public function __construct(
        protected WholesaleCatalogService $catalogService,
        protected TenantContext $tenantContext
    ) {}

    /**
     * Display a listing of wholesale product categories.
     */
    public function index(Request $request): View
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        $categories = WholesaleCategory::where('vendor_id', $vendor->id)
            ->withCount('products')
            ->with('parent')
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->paginate(20);

        $totalCategories = WholesaleCategory::where('vendor_id', $vendor->id)->count();
        $rootCategories = WholesaleCategory::where('vendor_id', $vendor->id)->whereNull('parent_id')->count();
        $activeCategories = WholesaleCategory::where('vendor_id', $vendor->id)->where('is_active', true)->count();

        return view('wholesale.categories.index', compact('vendor', 'categories', 'totalCategories', 'rootCategories', 'activeCategories'));
    }

    /**
     * Show the form for creating a new category.
     */
    public function create(Request $request): View
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        $parentCategories = WholesaleCategory::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('wholesale.categories.create', compact('vendor', 'parentCategories'));
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request): RedirectResponse
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('wholesale_categories', 'id')->where('vendor_id', $vendor->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->has('is_active');

        $category = $this->catalogService->createCategory($vendor, $validated);

        return redirect()->route('admin.wholesale.categories.index')
            ->with('success', __('Wholesale category ":name" has been created.', ['name' => $category->name]));
    }

    /**
     * Show the form for editing the specified category.
     */
    public function edit(WholesaleCategory $category): View
    {
        $vendor = $this->tenantContext->getTenant();
        abort_unless($category->vendor_id === $vendor->id, 404);

        $parentCategories = WholesaleCategory::where('vendor_id', $vendor->id)
            ->where('id', '!=', $category->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('wholesale.categories.edit', compact('vendor', 'category', 'parentCategories'));
    }

    /**
     * Update the specified category in storage.
     */
    public function update(Request $request, WholesaleCategory $category): RedirectResponse
    {
        $vendor = $this->tenantContext->getTenant() ?? $request->user()->vendor;
        abort_unless($category->vendor_id === $vendor->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'integer',
                'not_in:'.$category->id,
                Rule::exists('wholesale_categories', 'id')->where('vendor_id', $vendor->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->has('is_active');

        $this->catalogService->updateCategory($category, $validated);

        return redirect()->route('admin.wholesale.categories.index')
            ->with('success', __('Wholesale category ":name" has been updated.', ['name' => $category->name]));
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(WholesaleCategory $category): RedirectResponse
    {
        $vendor = $this->tenantContext->getTenant();
        abort_unless($category->vendor_id === $vendor->id, 404);

        try {
            $this->catalogService->deleteCategory($category);

            return redirect()->route('admin.wholesale.categories.index')
                ->with('success', __('Wholesale category deleted successfully.'));
        } catch (ValidationException $e) {
            return redirect()->route('admin.wholesale.categories.index')
                ->withErrors($e->errors());
        }
    }
}

