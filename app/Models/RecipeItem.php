<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeItem extends Model
{
    use BelongsToVendor, HasFactory;

    public const TYPE_INGREDIENT = 'ingredient';

    public const TYPE_SUB_RECIPE = 'sub_recipe';

    protected $fillable = [
        'vendor_id',
        'recipe_id',
        'recipe_version_id',
        'type',
        'ingredient_id',
        'sub_recipe_id',
        'quantity',
        'unit_id',
        'gross_weight',
        'net_weight',
        'prep_loss_percent',
        'cooking_loss_percent',
        'final_weight',
        'unit_cost',
        'total_cost',
        'sort_order',
        'notes',
        'is_optional',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'gross_weight' => 'decimal:4',
        'net_weight' => 'decimal:4',
        'prep_loss_percent' => 'decimal:2',
        'cooking_loss_percent' => 'decimal:2',
        'final_weight' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'sort_order' => 'integer',
        'is_optional' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (RecipeItem $item) {
            $gross = (float) ($item->gross_weight > 0 ? $item->gross_weight : ($item->quantity > 0 ? $item->quantity : 1.0));
            $item->gross_weight = $gross;

            if ($item->net_weight === null || (float) $item->net_weight <= 0) {
                $prepLoss = (float) ($item->prep_loss_percent ?? 0);
                $item->net_weight = round($gross * (1 - ($prepLoss / 100.0)), 4);
            }

            if ($item->final_weight === null || (float) $item->final_weight <= 0) {
                $cookLoss = (float) ($item->cooking_loss_percent ?? 0);
                $item->final_weight = round((float) $item->net_weight * (1 - ($cookLoss / 100.0)), 4);
            }
        });
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class, 'recipe_version_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    public function subRecipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'sub_recipe_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
