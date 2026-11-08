<?php

namespace App\Domains\Wholesale\Models;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Traits\BelongsToVendor;
use App\Models\Unit;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WholesaleProduct extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    protected $table = 'wholesale_products';

    protected $fillable = [
        'vendor_id',
        'category_id',
        'name',
        'sku',
        'barcode',
        'gtin',
        'classifier_code',
        'unit_id',
        'packaging_unit_id',
        'units_per_package',
        'min_order_quantity',
        'order_quantity_multiple',
        'cost_price',
        'base_wholesale_price',
        'min_wholesale_price',
        'is_vat_applicable',
        'vat_rate',
        'physical_stock_ingredient_id',
        'default_warehouse_id',
        'recipe_id',
        'description',
        'image',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'units_per_package' => 'decimal:4',
            'min_order_quantity' => 'decimal:4',
            'order_quantity_multiple' => 'decimal:4',
            'cost_price' => 'decimal:4',
            'base_wholesale_price' => 'decimal:4',
            'min_wholesale_price' => 'decimal:4',
            'is_vat_applicable' => 'boolean',
            'vat_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $product) {
            if ($product->min_wholesale_price === null) {
                $product->min_wholesale_price = $product->base_wholesale_price ?? 0;
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(WholesaleCategory::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function packagingUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'packaging_unit_id');
    }

    public function physicalStockIngredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'physical_stock_ingredient_id');
    }

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
    }

    public function packagings(): HasMany
    {
        return $this->hasMany(WholesaleProductPackaging::class, 'product_id');
    }

    public function atgaaClassification(): BelongsTo
    {
        return $this->belongsTo(WholesaleAtgaaClassification::class, 'classifier_code', 'code');
    }

    /**
     * Compute VAT amount for a given unit price according to this product's tax profile.
     */
    public function calculateVatAmount(float $price): float
    {
        if (! $this->is_vat_applicable || (float) $this->vat_rate <= 0) {
            return 0.00;
        }

        return round($price * ((float) $this->vat_rate / 100), 2);
    }

    /**
     * Check if physical inventory tracking is enabled for this product.
     */
    public function hasPhysicalInventory(): bool
    {
        return $this->physical_stock_ingredient_id !== null;
    }

    /**
     * Display label with SKU.
     */
    public function getDisplayNameAttribute(): string
    {
        return "[{$this->sku}] {$this->name}";
    }
}
