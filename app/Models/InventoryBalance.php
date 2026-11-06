<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBalance extends Model
{
    use BelongsToVendor, HasFactory;

    protected $fillable = [
        'vendor_id',
        'warehouse_id',
        'ingredient_id',
        'batch_id',
        'quantity_on_hand',
        'quantity_reserved',
        'unit_cost',
        'total_value',
        'last_movement_at',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:4',
        'quantity_reserved' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_value' => 'decimal:2',
        'last_movement_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function getAvailableQuantityAttribute(): float
    {
        return max(0.0, (float) $this->quantity_on_hand - (float) $this->quantity_reserved);
    }
}
