<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeCost extends Model
{
    use BelongsToVendor, HasFactory;

    public const METHOD_WEIGHTED_AVERAGE = 'weighted_average';

    public const METHOD_LAST_PURCHASE = 'last_purchase_price';

    protected $fillable = [
        'vendor_id',
        'recipe_id',
        'recipe_version_id',
        'ingredients_cost',
        'packaging_cost',
        'labor_cost',
        'overhead_cost',
        'total_cost',
        'portion_cost',
        'selling_price',
        'food_cost_percent',
        'gross_profit',
        'profit_margin_percent',
        'cost_method',
        'calculated_at',
    ];

    protected $casts = [
        'ingredients_cost' => 'decimal:4',
        'packaging_cost' => 'decimal:4',
        'labor_cost' => 'decimal:4',
        'overhead_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'portion_cost' => 'decimal:4',
        'selling_price' => 'decimal:2',
        'food_cost_percent' => 'decimal:2',
        'gross_profit' => 'decimal:4',
        'profit_margin_percent' => 'decimal:2',
        'calculated_at' => 'datetime',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class, 'recipe_version_id');
    }
}
