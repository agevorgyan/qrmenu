<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierIngredient extends Model
{
    use BelongsToVendor, HasFactory;

    protected $fillable = [
        'vendor_id',
        'supplier_id',
        'ingredient_id',
        'supplier_sku',
        'unit_id',
        'price',
        'discount_percent',
        'is_primary_supplier',
        'lead_time_days',
        'minimum_order_quantity',
        'package_size',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'is_primary_supplier' => 'boolean',
        'lead_time_days' => 'integer',
        'minimum_order_quantity' => 'decimal:4',
        'package_size' => 'decimal:4',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
