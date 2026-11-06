<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use App\Models\Traits\HasContentTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Recipe extends Model
{
    use BelongsToVendor, HasContentTranslations, HasFactory, SoftDeletes;

    public const TYPE_DISH = 'dish';

    public const TYPE_SEMI_FINISHED = 'semi_finished';

    public const TYPE_SAUCE = 'sauce';

    public const TYPE_BAKERY = 'bakery';

    public const TYPE_BEVERAGE = 'beverage';

    public const TYPE_PREP = 'prep';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'vendor_id',
        'product_id',
        'produced_ingredient_id',
        'category_id',
        'name',
        'name_translations',
        'code',
        'type',
        'status',
        'description',
        'instructions',
        'yield_quantity',
        'yield_unit_id',
        'portion_size',
        'portion_unit_id',
        'prep_time_minutes',
        'cook_time_minutes',
        'storage_conditions',
        'shelf_life_hours',
        'selling_price',
        'target_food_cost_percent',
        'critical_control_points',
        'image',
        'current_version',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
        'is_active',
    ];

    protected $casts = [
        'name_translations' => 'array',
        'yield_quantity' => 'decimal:4',
        'portion_size' => 'decimal:4',
        'prep_time_minutes' => 'integer',
        'cook_time_minutes' => 'integer',
        'shelf_life_hours' => 'integer',
        'selling_price' => 'decimal:2',
        'target_food_cost_percent' => 'decimal:2',
        'current_version' => 'integer',
        'approved_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function producedIngredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'produced_ingredient_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function yieldUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'yield_unit_id');
    }

    public function portionUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'portion_unit_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecipeItem::class, 'recipe_id')->orderBy('sort_order');
    }

    public function packagings(): HasMany
    {
        return $this->hasMany(RecipePackaging::class, 'recipe_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RecipeVersion::class, 'recipe_id')->orderByDesc('version_number');
    }

    public function costs(): HasMany
    {
        return $this->hasMany(RecipeCost::class, 'recipe_id')->orderByDesc('calculated_at');
    }

    public function latestCost(): HasOne
    {
        return $this->hasOne(RecipeCost::class, 'recipe_id')->latestOfMany('calculated_at');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }

    /**
     * Get aggregated allergens from all ingredients in recipe and nested sub-recipes.
     *
     * @return Collection<int, Allergen>
     */
    public function getAllergensAttribute()
    {
        $allergens = collect();

        foreach ($this->items as $item) {
            if ($item->type === RecipeItem::TYPE_INGREDIENT && $item->ingredient) {
                $allergens = $allergens->merge($item->ingredient->allergens);
            } elseif ($item->type === RecipeItem::TYPE_SUB_RECIPE && $item->subRecipe) {
                $allergens = $allergens->merge($item->subRecipe->allergens);
            }
        }

        return $allergens->unique('id')->values();
    }
}
