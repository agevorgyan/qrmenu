<?php

namespace App\Http\Controllers;

use App\Exceptions\QuotaExceededException;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Allergen;
use App\Models\Category;
use App\Models\Product;
use App\Services\MenuManagementService;
use App\Services\SecurityAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MenuBuilderController extends Controller
{
    public function __construct(
        protected MenuManagementService $menuService,
        protected ?SecurityAuditService $auditService = null
    ) {
        $this->auditService = $auditService ?? app(SecurityAuditService::class);
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Category::class);

        $vendor = Auth::user()->vendor;
        $activeLocationId = $request->get('location_id', session('active_location_id', $vendor->locations->first()?->id));
        if ($activeLocationId) {
            session(['active_location_id' => $activeLocationId]);
        }
        $activeLocation = $vendor->locations->firstWhere('id', (int) $activeLocationId) ?? $vendor->locations->first();

        $vendor->load('dailyOffers.product');

        $categories = Category::where('vendor_id', $vendor->id)
            ->with(['products.variations', 'products.allergens', 'products.overrides', 'products.dailyOffers'])
            ->orderBy('sort_order', 'asc')
            ->get();

        $allergens = Allergen::all();
        $locations = $vendor->locations;

        return view('admin.menu.index', compact('vendor', 'categories', 'allergens', 'locations', 'activeLocationId', 'activeLocation'));
    }

    public function storeCategory(StoreCategoryRequest $request)
    {
        $this->authorize('create', Category::class);

        $validated = $request->validated();

        $this->menuService->createCategory(Auth::user()->vendor, $validated);

        return back()->with('success', __('Category added successfully!'));
    }

    public function updateCategory(UpdateCategoryRequest $request, Category $category)
    {
        $this->authorize('update', $category);

        $validated = $request->validated();

        $this->menuService->updateCategory($category, $validated);

        return back()->with('success', __('Category updated successfully!'));
    }

    public function destroyCategory(Request $request, Category $category)
    {
        $this->authorize('delete', $category);

        $reassignId = $request->filled('reassign_to_category_id') ? (int) $request->input('reassign_to_category_id') : null;
        $forceDelete = $request->boolean('force_delete');

        $result = $this->menuService->deleteCategorySafely($category, $reassignId, $forceDelete);

        $this->auditService->record(
            event: 'menu.category_deleted',
            action: "Deleted category [{$result['category_name']}]",
            vendor: Auth::user()->vendor,
            actor: Auth::user(),
            targetType: 'category',
            targetId: $category->id,
            metadata: ['reassigned_dishes' => $result['reassigned_count']]
        );

        $msg = $result['reassigned_count'] > 0
            ? __('Category deleted and :count dishes reassigned successfully.', ['count' => $result['reassigned_count']])
            : __('Category deleted successfully.');

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return back()->with('success', $msg);
    }

    public function reorderCategories(Request $request)
    {
        $this->authorize('reorder', Category::class);

        $validated = $request->validate([
            'order' => 'required|array|min:1',
            'order.*.id' => 'required|integer',
            'order.*.sort_order' => 'required|integer|min:0',
        ]);

        $this->menuService->reorderCategories(Auth::user()->vendor, $validated['order']);

        $this->auditService->record(
            event: 'menu.categories_reordered',
            action: 'Updated category display order',
            vendor: Auth::user()->vendor,
            actor: Auth::user(),
            targetType: 'category'
        );

        return response()->json([
            'success' => true,
            'message' => __('Category display order saved successfully.'),
        ]);
    }

    public function duplicateCategory(Category $category)
    {
        $this->authorize('create', Category::class);
        $this->authorize('view', $category);

        $newCategory = $this->menuService->duplicateCategory($category);

        $this->auditService->record(
            event: 'menu.category_duplicated',
            action: "Duplicated category [{$category->name}] into [{$newCategory->name}]",
            vendor: Auth::user()->vendor,
            actor: Auth::user(),
            targetType: 'category',
            targetId: $newCategory->id
        );

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('Category and dishes duplicated successfully!'),
                'category_id' => $newCategory->id,
            ]);
        }

        return back()->with('success', __('Category and dishes duplicated successfully!'));
    }

    public function storeProduct(StoreProductRequest $request)
    {
        $this->authorize('create', Product::class);

        $validated = $request->validated();

        try {
            $this->menuService->createProduct(Auth::user()->vendor, $validated, $request->file('image_file'));
        } catch (ValidationException|QuotaExceededException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Failed to create product dish: '.$e->getMessage(), ['exception' => $e]);

            return back()->withInput()->with('error', __('Error creating dish: :error', ['error' => $e->getMessage()]));
        }

        return back()->with('success', __('Product dish created successfully!'));
    }

    public function updateProduct(UpdateProductRequest $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validated();

        try {
            $this->menuService->updateProduct($product, $validated, $request->file('image_file'));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error("Failed to update product {$product->id}: ".$e->getMessage(), ['exception' => $e]);

            return back()->withInput()->with('error', __('Error updating dish: :error', ['error' => $e->getMessage()]));
        }

        return back()->with('success', __('Dish :name updated successfully!', ['name' => $product->name]));
    }

    public function toggleAvailability(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $locationId = $request->input('location_id', session('active_location_id'));
        if ($locationId && ! $product->vendor->locations->contains('id', (int) $locationId)) {
            $locationId = null;
        }

        $isAvailable = $this->menuService->toggleProductAvailability($product, $locationId ? (int) $locationId : null);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'is_available' => $isAvailable]);
        }

        return back()->with('success', __("Dish ':name' stock status updated.", ['name' => $product->name]));
    }

    public function toggleDailyOffer(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $vendor = Auth::user()->vendor;
        if ($product->vendor_id !== $vendor->id) {
            abort(403);
        }

        $existing = $vendor->dailyOffers()->where('product_id', $product->id)->first();
        if ($existing) {
            $existing->delete();
            if ($vendor->featured_product_id == $product->id) {
                $next = $vendor->dailyOffers()->first();
                $vendor->update(['featured_product_id' => $next?->product_id]);
            }
            $isOffer = false;
        } else {
            $vendor->dailyOffers()->create([
                'vendor_id' => $vendor->id,
                'product_id' => $product->id,
                'badge' => $vendor->featured_dish_badge ?: "⭐ TODAY'S SPECIAL",
                'subtitle' => $product->description,
                'is_active' => true,
                'sort_order' => (int) ($vendor->dailyOffers()->max('sort_order') ?? 0) + 1,
            ]);
            if (! $vendor->featured_dish_enabled) {
                $vendor->update(['featured_dish_enabled' => true]);
            }
            if (! $vendor->featured_product_id) {
                $vendor->update(['featured_product_id' => $product->id]);
            }
            $isOffer = true;
        }

        $this->menuService->invalidateVendorMenuCache($vendor);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_daily_offer' => $isOffer,
                'total_offers' => $vendor->dailyOffers()->count(),
                'product_id' => $product->id,
            ]);
        }

        return back()->with('success', $isOffer ? __('Added to Daily Offers!') : __('Removed from Daily Offers!'));
    }

    public function saveOverride(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'location_id' => ['required', Rule::exists('locations', 'id')->where('vendor_id', Auth::user()?->vendor_id)],
            'override_price' => 'nullable|numeric',
            'is_available' => 'required|boolean',
        ]);

        $this->menuService->saveLocationOverride($product, $validated);

        return back()->with('success', __('Location product settings updated!'));
    }

    public function destroyProduct(Product $product)
    {
        $this->authorize('delete', $product);

        $this->menuService->deleteProduct($product);

        return back()->with('success', __('Product deleted successfully.'));
    }

    /**
     * Bulk update multiple products.
     */
    public function bulkUpdateProducts(Request $request)
    {
        $this->authorize('bulkUpdate', Product::class);

        $validated = $request->validate([
            'product_ids' => 'required|array|min:1|max:250',
            'product_ids.*' => 'integer',
            'price_action' => 'nullable|string|in:none,set,increase_amount,decrease_amount,increase_percent,decrease_percent',
            'price_value' => 'nullable|numeric|min:0',
            'price_rounding' => 'nullable|string|in:none,nearest_10,nearest_50,nearest_100,integer',
            'category_id' => 'nullable|integer',
            'is_available' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'available_for_dine_in' => 'nullable|boolean',
            'available_for_takeaway' => 'nullable|boolean',
            'available_for_delivery' => 'nullable|boolean',
            'ai_enabled' => 'nullable|boolean',
        ]);

        $result = $this->menuService->bulkUpdateProducts(Auth::user()->vendor, $validated['product_ids'], $validated);

        $this->auditService->record(
            event: 'menu.products_bulk_updated',
            action: "Bulk updated {$result['updated_count']} dishes",
            vendor: Auth::user()->vendor,
            actor: Auth::user(),
            targetType: 'product',
            metadata: [
                'updated_count' => $result['updated_count'],
                'actions' => array_filter($validated, fn ($k) => $k !== 'product_ids', ARRAY_FILTER_USE_KEY),
            ]
        );

        $msg = __('Successfully updated :count dishes in bulk.', ['count' => $result['updated_count']]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'updated_count' => $result['updated_count'],
                'product_ids' => $result['product_ids'],
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Bulk delete multiple products with explicit confirmation.
     */
    public function bulkDeleteProducts(Request $request)
    {
        $this->authorize('bulkDelete', Product::class);

        $validated = $request->validate([
            'product_ids' => 'required|array|min:1|max:250',
            'product_ids.*' => 'integer',
        ]);

        $deletedCount = $this->menuService->bulkDeleteProducts(Auth::user()->vendor, $validated['product_ids']);

        $this->auditService->record(
            event: 'menu.products_bulk_deleted',
            action: "Bulk deleted {$deletedCount} dishes",
            vendor: Auth::user()->vendor,
            actor: Auth::user(),
            targetType: 'product',
            metadata: ['deleted_count' => $deletedCount]
        );

        $msg = __('Successfully removed :count dishes.', ['count' => $deletedCount]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'deleted_count' => $deletedCount,
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Inline update a single field on a product.
     */
    public function inlineUpdateProduct(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'field' => 'required|string|in:name,price,is_available,sort_order',
            'value' => 'required',
        ]);

        $updatedProduct = $this->menuService->inlineUpdateProduct($product, $validated['field'], $validated['value']);

        $this->auditService->record(
            event: 'menu.product_inline_updated',
            action: "Inline updated [{$validated['field']}] for dish [{$product->name}]",
            vendor: Auth::user()->vendor,
            actor: Auth::user(),
            targetType: 'product',
            targetId: $product->id,
            metadata: ['field' => $validated['field'], 'value' => $validated['value']]
        );

        return response()->json([
            'success' => true,
            'message' => __('Dish updated successfully.'),
            'product' => [
                'id' => $updatedProduct->id,
                'name' => $updatedProduct->name,
                'price' => (float) $updatedProduct->price,
                'is_available' => (bool) $updatedProduct->is_available,
                'sort_order' => (int) $updatedProduct->sort_order,
            ],
        ]);
    }
}
