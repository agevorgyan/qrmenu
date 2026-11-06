<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderItem extends Model
{
    use BelongsToVendor, HasFactory;

    protected $fillable = [
        'vendor_id',
        'production_order_id',
        'ingredient_id',
        'sub_recipe_id',
        'planned_quantity',
        'actual_quantity',
        'waste_quantity',
        'unit_id',
        'unit_cost',
        'total_cost',
        'notes',
    ];

    protected $casts = [
        'planned_quantity' => 'decimal:4',
        'actual_quantity' => 'decimal:4',
        'waste_quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function subRecipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'sub_recipe_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function calculateUsageVariance(): ?float
    {
        if ($this->actual_quantity === null || (float) $this->planned_quantity <= 0.0) {
            return null;
        }

        $variance = (((float) $this->actual_quantity - (float) $this->planned_quantity) / (float) $this->planned_quantity) * 100.0;

        return round($variance, 2);
    }
}
