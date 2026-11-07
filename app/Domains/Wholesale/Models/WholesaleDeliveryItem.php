<?php

namespace App\Domains\Wholesale\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WholesaleDeliveryItem extends Model
{
    use HasFactory;

    protected $table = 'wholesale_delivery_items';

    protected $fillable = [
        'delivery_id',
        'order_item_id',
        'product_id',
        'quantity_planned',
        'quantity_delivered',
        'quantity_rejected',
        'unit_code',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity_planned' => 'decimal:4',
            'quantity_delivered' => 'decimal:4',
            'quantity_rejected' => 'decimal:4',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(WholesaleDelivery::class, 'delivery_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(WholesaleOrderItem::class, 'order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(WholesaleProduct::class, 'product_id');
    }
}
