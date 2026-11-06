<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeCost;
use App\Models\RecipeItem;
use App\Models\RecipePackaging;
use App\Models\Unit;
use App\Security\Permission;
use App\Services\RecipeCostingService;
use App\Services\StorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RecipeController extends Controller
{
    public function __construct(
        protected StorageService $storageService,
        protected RecipeCostingService $costingService
    ) {}

    /**
     * Display a listing of recipes.
     */
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::RECIPES_VIEW);

        $vendorId = Auth::user()->vendor_id;

        $query = Recipe::with(['yieldUnit', 'category', 'product', 'latestCost'])
            ->where('vendor_id', $vendorId);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            });
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        $recipes = $query->orderBy('name')->paginate(20)->withQueryString();

        $categories = IngredientCategory::where('vendor_id', $vendorId)->orderBy('name')->get();
        $totalRecipes = Recipe::where('vendor_id', $vendorId)->count();
        $approvedRecipes = Recipe::where('vendor_id', $vendorId)->where('status', Recipe::STATUS_APPROVED)->count();
        $dishCount = Recipe::where('vendor_id', $vendorId)->where('type', Recipe::TYPE_DISH)->count();
        $semiCount = Recipe::where('vendor_id', $vendorId)->where('type', Recipe::TYPE_SEMI_FINISHED)->count();

        return view('admin.inventory.recipes.index', compact(
            'recipes',
            'categories',
            'totalRecipes',
            'approvedRecipes',
            'dishCount',
            'semiCount'
        ));
    }

    /**
     * Show form for creating a new recipe.
     */
    public function create(): View
    {
        $this->authorizePermission(Permission::RECIPES_CREATE);

        $vendorId = Auth::user()->vendor_id;

        $units = Unit::forCurrentVendor()->where('is_active', true)->orderBy('name')->get();
        $categories = IngredientCategory::where('vendor_id', $vendorId)->orderBy('name')->get();
        $ingredients = Ingredient::where('vendor_id', $vendorId)->where('is_active', true)->with('unit')->orderBy('name')->get();
        $semiFinishedRecipes = Recipe::where('vendor_id', $vendorId)
            ->whereIn('type', [Recipe::TYPE_SEMI_FINISHED, Recipe::TYPE_SAUCE, Recipe::TYPE_PREP])
            ->where('status', '!=', Recipe::STATUS_ARCHIVED)
            ->with('yieldUnit')
            ->orderBy('name')
            ->get();
        $products = Product::where('vendor_id', $vendorId)->orderBy('name')->get();

        return view('admin.inventory.recipes.create', compact(
            'units',
            'categories',
            'ingredients',
            'semiFinishedRecipes',
            'products'
        ));
    }

    /**
     * Store a newly created recipe.
     */
    public function store(StoreRecipeRequest $request): RedirectResponse
    {
        $vendor = Auth::user()->vendor;
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $file = $this->storageService->store(
                file: $request->file('image'),
                namespace: 'recipes',
                vendor: $vendor
            );
            $data['image'] = $file->getUrl();
        }

        $itemsData = $data['items'] ?? [];
        $packagingData = $data['packagings'] ?? [];
        unset($data['items'], $data['packagings']);

        $data['vendor_id'] = $vendor->id;
        $data['created_by'] = Auth::id();
        $data['status'] = Recipe::STATUS_DRAFT;

        $recipe = DB::transaction(function () use ($data, $itemsData, $packagingData, $vendor) {
            $recipe = Recipe::create($data);

            // Create items
            foreach ($itemsData as $index => $itemRow) {
                if (empty($itemRow['ingredient_id']) && empty($itemRow['sub_recipe_id'])) {
                    continue;
                }

                $gross = (float) ($itemRow['gross_weight'] ?? $itemRow['quantity']);
                $prepLoss = (float) ($itemRow['prep_loss_percent'] ?? 0);
                $cookLoss = (float) ($itemRow['cooking_loss_percent'] ?? 0);
                $net = (float) ($itemRow['net_weight'] ?? ($gross * (1 - ($prepLoss / 100))));
                $final = (float) ($itemRow['final_weight'] ?? ($net * (1 - ($cookLoss / 100))));

                RecipeItem::create([
                    'vendor_id' => $vendor->id,
                    'recipe_id' => $recipe->id,
                    'type' => $itemRow['type'] ?? RecipeItem::TYPE_INGREDIENT,
                    'ingredient_id' => $itemRow['ingredient_id'] ?? null,
                    'sub_recipe_id' => $itemRow['sub_recipe_id'] ?? null,
                    'quantity' => $gross,
                    'unit_id' => $itemRow['unit_id'],
                    'gross_weight' => $gross,
                    'net_weight' => $net,
                    'prep_loss_percent' => $prepLoss,
                    'cooking_loss_percent' => $cookLoss,
                    'final_weight' => $final,
                    'sort_order' => $index,
                    'notes' => $itemRow['notes'] ?? null,
                    'is_optional' => ! empty($itemRow['is_optional']),
                ]);
            }

            // Create packaging
            foreach ($packagingData as $pkgRow) {
                if (empty($pkgRow['ingredient_id'])) {
                    continue;
                }

                RecipePackaging::create([
                    'vendor_id' => $vendor->id,
                    'recipe_id' => $recipe->id,
                    'ingredient_id' => $pkgRow['ingredient_id'],
                    'quantity' => (float) $pkgRow['quantity'],
                    'unit_id' => $pkgRow['unit_id'],
                ]);
            }

            // Calculate costs
            $this->costingService->calculateAndPersist($recipe);

            return $recipe;
        });

        return redirect()->route('admin.inventory.recipes.show', $recipe)
            ->with('success', __('Recipe created and costed successfully.'));
    }

    /**
     * Display technical card and cost sheet of a recipe.
     */
    public function show(Recipe $recipe): View
    {
        $this->authorizePermission(Permission::RECIPES_VIEW);

        $recipe->load([
            'yieldUnit',
            'portionUnit',
            'category',
            'product',
            'producedIngredient',
            'items.ingredient.unit',
            'items.subRecipe.yieldUnit',
            'items.unit',
            'packagings.ingredient.unit',
            'packagings.unit',
            'costs',
            'latestCost',
            'versions.approver',
            'approver',
            'creator',
        ]);

        $canViewCost = Auth::user()->hasPermission(Permission::RECIPES_COST_VIEW);
        $allergens = $recipe->allergens;

        return view('admin.inventory.recipes.show', compact('recipe', 'canViewCost', 'allergens'));
    }

    /**
     * Show form for editing recipe.
     */
    public function edit(Recipe $recipe): View
    {
        $this->authorizePermission(Permission::RECIPES_UPDATE);

        $vendorId = Auth::user()->vendor_id;

        $recipe->load(['items', 'packagings']);

        $units = Unit::forCurrentVendor()->where('is_active', true)->orderBy('name')->get();
        $categories = IngredientCategory::where('vendor_id', $vendorId)->orderBy('name')->get();
        $ingredients = Ingredient::where('vendor_id', $vendorId)->where('is_active', true)->with('unit')->orderBy('name')->get();
        $semiFinishedRecipes = Recipe::where('vendor_id', $vendorId)
            ->where('id', '!=', $recipe->id)
            ->whereIn('type', [Recipe::TYPE_SEMI_FINISHED, Recipe::TYPE_SAUCE, Recipe::TYPE_PREP])
            ->where('status', '!=', Recipe::STATUS_ARCHIVED)
            ->with('yieldUnit')
            ->orderBy('name')
            ->get();
        $products = Product::where('vendor_id', $vendorId)->orderBy('name')->get();

        return view('admin.inventory.recipes.edit', compact(
            'recipe',
            'units',
            'categories',
            'ingredients',
            'semiFinishedRecipes',
            'products'
        ));
    }

    /**
     * Update an existing recipe.
     */
    public function update(UpdateRecipeRequest $request, Recipe $recipe): RedirectResponse
    {
        $vendor = Auth::user()->vendor;
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $file = $this->storageService->store(
                file: $request->file('image'),
                namespace: 'recipes',
                vendor: $vendor
            );
            $data['image'] = $file->getUrl();
        }

        $itemsData = $data['items'] ?? [];
        $packagingData = $data['packagings'] ?? [];
        unset($data['items'], $data['packagings']);

        $data['updated_by'] = Auth::id();

        DB::transaction(function () use ($recipe, $data, $itemsData, $packagingData, $vendor) {
            $recipe->update($data);

            // Recreate items
            $recipe->items()->delete();
            foreach ($itemsData as $index => $itemRow) {
                if (empty($itemRow['ingredient_id']) && empty($itemRow['sub_recipe_id'])) {
                    continue;
                }

                $gross = (float) ($itemRow['gross_weight'] ?? $itemRow['quantity']);
                $prepLoss = (float) ($itemRow['prep_loss_percent'] ?? 0);
                $cookLoss = (float) ($itemRow['cooking_loss_percent'] ?? 0);
                $net = (float) ($itemRow['net_weight'] ?? ($gross * (1 - ($prepLoss / 100))));
                $final = (float) ($itemRow['final_weight'] ?? ($net * (1 - ($cookLoss / 100))));

                RecipeItem::create([
                    'vendor_id' => $vendor->id,
                    'recipe_id' => $recipe->id,
                    'type' => $itemRow['type'] ?? RecipeItem::TYPE_INGREDIENT,
                    'ingredient_id' => $itemRow['ingredient_id'] ?? null,
                    'sub_recipe_id' => $itemRow['sub_recipe_id'] ?? null,
                    'quantity' => $gross,
                    'unit_id' => $itemRow['unit_id'],
                    'gross_weight' => $gross,
                    'net_weight' => $net,
                    'prep_loss_percent' => $prepLoss,
                    'cooking_loss_percent' => $cookLoss,
                    'final_weight' => $final,
                    'sort_order' => $index,
                    'notes' => $itemRow['notes'] ?? null,
                    'is_optional' => ! empty($itemRow['is_optional']),
                ]);
            }

            // Recreate packaging
            $recipe->packagings()->delete();
            foreach ($packagingData as $pkgRow) {
                if (empty($pkgRow['ingredient_id'])) {
                    continue;
                }

                RecipePackaging::create([
                    'vendor_id' => $vendor->id,
                    'recipe_id' => $recipe->id,
                    'ingredient_id' => $pkgRow['ingredient_id'],
                    'quantity' => (float) $pkgRow['quantity'],
                    'unit_id' => $pkgRow['unit_id'],
                ]);
            }

            // Recalculate costs
            $this->costingService->calculateAndPersist($recipe);
        });

        return redirect()->route('admin.inventory.recipes.show', $recipe)
            ->with('success', __('Recipe updated and recalculated successfully.'));
    }

    /**
     * Delete or archive a recipe.
     */
    public function destroy(Recipe $recipe): RedirectResponse
    {
        $this->authorizePermission(Permission::RECIPES_DELETE);

        $vendorId = Auth::user()->vendor_id;

        // Check if used as sub-recipe in any other recipe
        $isUsedAsSubRecipe = RecipeItem::where('vendor_id', $vendorId)
            ->where('type', RecipeItem::TYPE_SUB_RECIPE)
            ->where('sub_recipe_id', $recipe->id)
            ->exists();

        if ($isUsedAsSubRecipe) {
            return back()->with('error', __('Cannot delete this recipe because it is used as a sub-recipe in other recipes.'));
        }

        $recipe->delete();

        return redirect()->route('admin.inventory.recipes.index')
            ->with('success', __('Recipe deleted successfully.'));
    }

    /**
     * Approve a recipe and create an official version snapshot.
     */
    public function approve(Recipe $recipe, Request $request): RedirectResponse
    {
        $this->authorizePermission(Permission::RECIPES_APPROVE);

        $notes = $request->input('notes', 'Approved by chef/manager');

        $this->costingService->createVersion($recipe, $notes, Auth::user());

        $recipe->status = Recipe::STATUS_APPROVED;
        $recipe->approved_by = Auth::id();
        $recipe->approved_at = now();
        $recipe->save();

        return back()->with('success', __('Recipe approved and version created successfully.'));
    }

    /**
     * Recalculate recipe costs with latest ingredient prices.
     */
    public function recalculate(Recipe $recipe, Request $request): RedirectResponse
    {
        $this->authorizePermission(Permission::RECIPES_UPDATE);

        $costMethod = $request->input('cost_method', RecipeCost::METHOD_WEIGHTED_AVERAGE);
        $labor = (float) $request->input('labor_cost', 0);
        $overhead = (float) $request->input('overhead_cost', 0);

        $this->costingService->calculateAndPersist($recipe, $costMethod, $labor, $overhead);

        return back()->with('success', __('Recipe costs recalculated successfully.'));
    }

    /**
     * Helper to verify user permissions.
     */
    protected function authorizePermission(string $permission): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission($permission)) {
            abort(403, 'Unauthorized action.');
        }
    }
}

