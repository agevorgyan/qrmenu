<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecipeVersion extends Model
{
    use BelongsToVendor, HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'vendor_id',
        'recipe_id',
        'version_number',
        'change_summary',
        'yield_quantity',
        'yield_unit_id',
        'portion_size',
        'portion_unit_id',
        'instructions',
        'calculated_cost',
        'portion_cost',
        'food_cost_percent',
        'status',
        'snapshot_data',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'yield_quantity' => 'decimal:4',
        'portion_size' => 'decimal:4',
        'calculated_cost' => 'decimal:4',
        'portion_cost' => 'decimal:4',
        'food_cost_percent' => 'decimal:2',
        'snapshot_data' => 'array',
        'approved_at' => 'datetime',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
    }

    public function yieldUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'yield_unit_id');
    }

    public function portionUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'portion_unit_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecipeItem::class, 'recipe_version_id');
    }
}
