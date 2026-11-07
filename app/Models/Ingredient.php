<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use App\Models\Traits\HasContentTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends Model
{
    use BelongsToVendor, HasContentTranslations, HasFactory, SoftDeletes;

    public const TYPE_RAW_MATERIAL = 'raw_material';

    public const TYPE_INGREDIENT = 'ingredient';

    public const TYPE_SEMI_FINISHED = 'semi_finished';

    public const TYPE_FINISHED = 'finished';

    public const TYPE_PACKAGING = 'packaging';

    public const TYPE_CONSUMABLE = 'consumable';

    protected $fillable = [
        'vendor_id',
        'category_id',
        'name',
        'name_translations',
        'sku',
        'barcode',
        'classifier_code',
        'type',
        'description',
        'image',
        'unit_id',
        'min_stock',
        'max_stock',
        'reorder_point',
        'safety_stock',
        'last_purchase_price',
        'current_cost',
        'storage_conditions',
        'storage_temperature_min',
        'storage_temperature_max',
        'shelf_life_days',
        'calories',
        'protein_g',
        'carbs_g',
        'fat_g',
        'density_kg_per_l',
        'is_active',
    ];

    protected $casts = [
        'name_translations' => 'array',
        'min_stock' => 'decimal:4',
        'max_stock' => 'decimal:4',
        'reorder_point' => 'decimal:4',
        'safety_stock' => 'decimal:4',
        'last_purchase_price' => 'decimal:2',
        'current_cost' => 'decimal:4',
        'storage_temperature_min' => 'decimal:2',
        'storage_temperature_max' => 'decimal:2',
        'shelf_life_days' => 'integer',
        'calories' => 'decimal:2',
        'protein_g' => 'decimal:2',
        'carbs_g' => 'decimal:2',
        'fat_g' => 'decimal:2',
        'density_kg_per_l' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(IngredientCategory::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function allergens(): BelongsToMany
    {
        return $this->belongsToMany(Allergen::class, 'ingredient_allergens');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class, 'ingredient_id');
    }

    public function transactionItems(): HasMany
    {
        return $this->hasMany(InventoryTransactionItem::class, 'ingredient_id');
    }

    /**
     * Compute total on-hand stock across all warehouses.
     */
    public function getTotalStockOnHandAttribute(): float
    {
        return (float) $this->balances()->sum('quantity_on_hand');
    }

    /**
     * Compute total reserved stock across all warehouses.
     */
    public function getTotalStockReservedAttribute(): float
    {
        return (float) $this->balances()->sum('quantity_reserved');
    }

    /**
     * Compute total available stock (on_hand - reserved).
     */
    public function getTotalStockAvailableAttribute(): float
    {
        return max(0.0, $this->total_stock_on_hand - $this->total_stock_reserved);
    }

    /**
     * Check if total stock is at or below minimum threshold.
     */
    public function isLowStock(): bool
    {
        if ((float) $this->min_stock <= 0) {
            return false;
        }

        return $this->total_stock_available <= (float) $this->min_stock;
    }

    /**
     * Scope query to ingredients with stock at or below minimum threshold.
     * Compatible with PostgreSQL grouping and handles out-of-stock items.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeLowStock($query)
    {
        return $query->where('min_stock', '>', 0)
            ->whereRaw('COALESCE((SELECT SUM(quantity_on_hand - quantity_reserved) FROM inventory_balances WHERE inventory_balances.ingredient_id = ingredients.id), 0) <= ingredients.min_stock');
    }

    public function recipe(): HasOne
    {
        return $this->hasOne(Recipe::class, 'produced_ingredient_id');
    }

    public function usedInRecipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class, 'ingredient_id');
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_ingredients')
            ->withPivot([
                'supplier_sku',
                'unit_id',
                'price',
                'is_primary_supplier',
                'lead_time_days',
                'minimum_order_quantity',
                'package_size',
            ])
            ->withTimestamps();
    }

    public function supplierIngredients(): HasMany
    {
        return $this->hasMany(SupplierIngredient::class, 'ingredient_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function getAtgaaCodeAttribute(): ?string
    {
        return $this->classifier_code;
    }

    public function setAtgaaCodeAttribute(?string $value): void
    {
        $this->attributes['classifier_code'] = $value;
    }

    public function getAdgCodeAttribute(): ?string
    {
        return $this->classifier_code;
    }

    public function setAdgCodeAttribute(?string $value): void
    {
        $this->attributes['classifier_code'] = $value;
    }
}
