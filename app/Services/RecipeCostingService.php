<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeCost;
use App\Models\RecipeItem;
use App\Models\RecipeVersion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecipeCostingService
{
    public function __construct(
        protected UnitConversionService $conversionService
    ) {}

    /**
     * Calculate and save recipe cost breakdown and update recipe item costs.
     *
     * @param  Recipe  $recipe  The recipe to cost
     * @param  string  $costMethod  'weighted_average' or 'last_purchase_price'
     * @param  float  $laborCost  Direct labor cost
     * @param  float  $overheadCost  Overhead cost
     * @return RecipeCost The created cost breakdown record
     */
    public function calculateAndPersist(
        Recipe $recipe,
        string $costMethod = RecipeCost::METHOD_WEIGHTED_AVERAGE,
        float $laborCost = 0.0,
        float $overheadCost = 0.0
    ): RecipeCost {
        return DB::transaction(function () use ($recipe, $costMethod, $laborCost, $overheadCost) {
            // Eager load relationships
            $recipe->load(['items.ingredient', 'items.subRecipe', 'items.unit', 'packagings.ingredient', 'packagings.unit', 'product', 'yieldUnit']);

            // Validate no DAG cycles
            $this->validateNoCycles($recipe);

            $ingredientsCost = 0.0;

            foreach ($recipe->items as $item) {
                $itemCost = $this->calculateItemCost($item, $costMethod);
                $item->unit_cost = $itemCost['unit_cost'];
                $item->total_cost = $itemCost['total_cost'];
                $item->net_weight = $itemCost['net_weight'];
                $item->final_weight = $itemCost['final_weight'];
                $item->save();

                $ingredientsCost += $itemCost['total_cost'];
            }

            // Calculate packaging cost
            $packagingCost = 0.0;
            foreach ($recipe->packagings as $pkg) {
                $pkgCost = $this->calculatePackagingCost($pkg, $costMethod);
                $pkg->unit_cost = $pkgCost['unit_cost'];
                $pkg->total_cost = $pkgCost['total_cost'];
                $pkg->save();

                $packagingCost += $pkgCost['total_cost'];
            }

            $totalCost = $ingredientsCost + $packagingCost + $laborCost + $overheadCost;
            $yieldQty = (float) $recipe->yield_quantity > 0 ? (float) $recipe->yield_quantity : 1.0;
            $portionCost = $totalCost / $yieldQty;

            $sellingPrice = (float) ($recipe->selling_price ?? $recipe->product?->price ?? 0);
            $foodCostPercent = null;
            $grossProfit = null;
            $profitMarginPercent = null;

            if ($sellingPrice > 0) {
                $foodCostPercent = round(($portionCost / $sellingPrice) * 100, 2);
                $grossProfit = round($sellingPrice - $portionCost, 4);
                $profitMarginPercent = round(($grossProfit / $sellingPrice) * 100, 2);
            }

            return RecipeCost::create([
                'vendor_id' => $recipe->vendor_id,
                'recipe_id' => $recipe->id,
                'recipe_version_id' => null,
                'ingredients_cost' => round($ingredientsCost, 4),
                'packaging_cost' => round($packagingCost, 4),
                'labor_cost' => round($laborCost, 4),
                'overhead_cost' => round($overheadCost, 4),
                'total_cost' => round($totalCost, 4),
                'portion_cost' => round($portionCost, 4),
                'selling_price' => $sellingPrice > 0 ? round($sellingPrice, 2) : null,
                'food_cost_percent' => $foodCostPercent,
                'gross_profit' => $grossProfit,
                'profit_margin_percent' => $profitMarginPercent,
                'cost_method' => $costMethod,
                'calculated_at' => now(),
            ]);
        });
    }

    /**
     * Calculate cost and weight transformations for a single recipe item.
     *
     * @return array{unit_cost: float, total_cost: float, net_weight: float, final_weight: float}
     */
    public function calculateItemCost(RecipeItem $item, string $costMethod = RecipeCost::METHOD_WEIGHTED_AVERAGE): array
    {
        $gross = (float) ($item->gross_weight > 0 ? $item->gross_weight : $item->quantity);
        $prepLoss = (float) $item->prep_loss_percent;
        $cookLoss = (float) $item->cooking_loss_percent;

        // Net weight after cold prep loss
        $net = (float) ($item->net_weight > 0 ? $item->net_weight : ($gross * (1 - ($prepLoss / 100.0))));
        // Final weight after cooking loss
        $final = (float) ($item->final_weight > 0 ? $item->final_weight : ($net * (1 - ($cookLoss / 100.0))));

        $unitCost = 0.0;
        $totalCost = 0.0;

        if ($item->type === RecipeItem::TYPE_INGREDIENT && $item->ingredient) {
            $ingredient = $item->ingredient;

            // Unit cost in ingredient's primary unit
            $primaryCost = $costMethod === RecipeCost::METHOD_LAST_PURCHASE
                ? (float) $ingredient->last_purchase_price
                : (float) $ingredient->current_cost;

            // Quantity in primary unit of ingredient (charged based on gross weight/quantity)
            $qtyInPrimaryUnit = $this->conversionService->convert(
                $gross,
                $item->unit_id,
                $ingredient->unit_id,
                $ingredient
            );

            $totalCost = $qtyInPrimaryUnit * $primaryCost;
            $unitCost = $gross > 0 ? ($totalCost / $gross) : $primaryCost;
        } elseif ($item->type === RecipeItem::TYPE_SUB_RECIPE && $item->subRecipe) {
            $subRecipe = $item->subRecipe;

            // Recursively calculate sub-recipe cost
            $subCostRecord = $this->calculateAndPersist($subRecipe, $costMethod);
            $subYieldQty = (float) $subRecipe->yield_quantity > 0 ? (float) $subRecipe->yield_quantity : 1.0;
            $subUnitCost = (float) $subCostRecord->total_cost / $subYieldQty;

            // Convert quantity to sub-recipe yield unit if different
            $qtyInSubUnit = $this->conversionService->convert(
                $gross,
                $item->unit_id,
                $subRecipe->yield_unit_id
            );

            $totalCost = $qtyInSubUnit * $subUnitCost;
            $unitCost = $gross > 0 ? ($totalCost / $gross) : $subUnitCost;
        }

        return [
            'unit_cost' => round($unitCost, 4),
            'total_cost' => round($totalCost, 4),
            'net_weight' => round($net, 4),
            'final_weight' => round($final, 4),
        ];
    }

    /**
     * Calculate cost for packaging entry.
     *
     * @return array{unit_cost: float, total_cost: float}
     */
    public function calculatePackagingCost(mixed $pkg, string $costMethod = RecipeCost::METHOD_WEIGHTED_AVERAGE): array
    {
        $ingredient = $pkg->ingredient;
        if (! $ingredient) {
            return ['unit_cost' => 0.0, 'total_cost' => 0.0];
        }

        $primaryCost = $costMethod === RecipeCost::METHOD_LAST_PURCHASE
            ? (float) $ingredient->last_purchase_price
            : (float) $ingredient->current_cost;

        $qtyInPrimaryUnit = $this->conversionService->convert(
            (float) $pkg->quantity,
            $pkg->unit_id,
            $ingredient->unit_id,
            $ingredient
        );

        $totalCost = $qtyInPrimaryUnit * $primaryCost;
        $unitCost = (float) $pkg->quantity > 0 ? ($totalCost / (float) $pkg->quantity) : $primaryCost;

        return [
            'unit_cost' => round($unitCost, 4),
            'total_cost' => round($totalCost, 4),
        ];
    }

    /**
     * Validate directed acyclic graph (DAG) to prevent circular recipe references.
     *
     * @throws InvalidArgumentException
     */
    public function validateNoCycles(Recipe $recipe, ?int $targetRecipeId = null, array $visited = []): void
    {
        $targetId = $targetRecipeId ?? $recipe->id;

        if (in_array($targetId, $visited, true)) {
            throw new InvalidArgumentException("Circular recipe dependency detected for recipe ID #{$targetId}.");
        }

        $visited[] = $targetId;

        $subItems = RecipeItem::where('recipe_id', $targetId)
            ->where('type', RecipeItem::TYPE_SUB_RECIPE)
            ->whereNotNull('sub_recipe_id')
            ->get();

        foreach ($subItems as $item) {
            if ($item->sub_recipe_id === $recipe->id || in_array($item->sub_recipe_id, $visited, true)) {
                throw new InvalidArgumentException(
                    "Circular recipe reference detected between recipe #{$recipe->id} ({$recipe->name}) and sub-recipe #{$item->sub_recipe_id}."
                );
            }

            $this->validateNoCycles($recipe, $item->sub_recipe_id, $visited);
        }
    }

    /**
     * Create a permanent version snapshot of the recipe.
     */
    public function createVersion(Recipe $recipe, ?string $changeSummary = null, ?User $user = null): RecipeVersion
    {
        return DB::transaction(function () use ($recipe, $changeSummary, $user) {
            $recipe->load(['items.ingredient', 'items.subRecipe', 'items.unit', 'packagings.ingredient', 'packagings.unit', 'yieldUnit']);

            // Current cost record
            $cost = $this->calculateAndPersist($recipe);

            $nextVersionNumber = ($recipe->versions()->max('version_number') ?? 0) + 1;

            $snapshotItems = $recipe->items->map(function (RecipeItem $item) {
                return [
                    'id' => $item->id,
                    'type' => $item->type,
                    'ingredient_id' => $item->ingredient_id,
                    'ingredient_name' => $item->ingredient?->name,
                    'sub_recipe_id' => $item->sub_recipe_id,
                    'sub_recipe_name' => $item->subRecipe?->name,
                    'quantity' => (float) $item->quantity,
                    'unit_id' => $item->unit_id,
                    'unit_code' => $item->unit?->code,
                    'gross_weight' => (float) $item->gross_weight,
                    'net_weight' => (float) $item->net_weight,
                    'prep_loss_percent' => (float) $item->prep_loss_percent,
                    'cooking_loss_percent' => (float) $item->cooking_loss_percent,
                    'final_weight' => (float) $item->final_weight,
                    'unit_cost' => (float) $item->unit_cost,
                    'total_cost' => (float) $item->total_cost,
                    'notes' => $item->notes,
                ];
            })->toArray();

            $version = RecipeVersion::create([
                'vendor_id' => $recipe->vendor_id,
                'recipe_id' => $recipe->id,
                'version_number' => $nextVersionNumber,
                'change_summary' => $changeSummary,
                'yield_quantity' => $recipe->yield_quantity,
                'yield_unit_id' => $recipe->yield_unit_id,
                'portion_size' => $recipe->portion_size,
                'portion_unit_id' => $recipe->portion_unit_id,
                'instructions' => $recipe->instructions,
                'calculated_cost' => $cost->total_cost,
                'portion_cost' => $cost->portion_cost,
                'food_cost_percent' => $cost->food_cost_percent,
                'status' => RecipeVersion::STATUS_APPROVED,
                'snapshot_data' => [
                    'items' => $snapshotItems,
                    'cost' => $cost->toArray(),
                ],
                'approved_by' => $user?->id,
                'approved_at' => now(),
                'created_by' => $user?->id,
            ]);

            $recipe->current_version = $nextVersionNumber;
            $recipe->save();

            return $version;
        });
    }

    /**
     * Recalculate all recipes that depend on a given ingredient.
     *
     * @return Collection<int, Recipe>
     */
    public function recalculateForIngredient(Ingredient $ingredient): Collection
    {
        $recipeIds = RecipeItem::where('ingredient_id', $ingredient->id)->pluck('recipe_id')->unique();
        $recipes = Recipe::whereIn('id', $recipeIds)->where('status', '!=', Recipe::STATUS_ARCHIVED)->get();

        foreach ($recipes as $recipe) {
            $this->calculateAndPersist($recipe);
        }

        return $recipes;
    }
}

