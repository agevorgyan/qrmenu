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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MenuBuilderController extends Controller
{
    public function __construct(
        protected MenuManagementService $menuService
    ) {}

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

    public function destroyCategory(Category $category)
    {
        $this->authorize('delete', $category);

        $this->menuService->deleteCategory($category);

        return back()->with('success', __('Category deleted successfully.'));
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
}
