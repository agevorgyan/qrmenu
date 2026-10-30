<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosInventoryMovement extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'pos_inventory_movements';

    protected $fillable = [
        'vendor_id',
        'location_id',
        'product_id',
        'product_variation_id',
        'order_id',
        'user_id',
        'type', // 'sale', 'restock', 'waste', 'adjustment', 'refund'
        'quantity_change',
        'resulting_quantity',
        'reason',
    ];

    protected $casts = [
        'quantity_change' => 'decimal:2',
        'resulting_quantity' => 'decimal:2',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
