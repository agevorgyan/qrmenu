<?php

namespace App\Domains\Wholesale\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WholesaleOrderItem extends Model
{
    use HasFactory;

    protected $table = 'wholesale_order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'sku',
        'product_name',
        'classifier_code',
        'unit_code',
        'quantity_ordered',
        'quantity_delivered',
        'base_price',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'subtotal',
        'is_vat_applicable',
        'vat_rate',
        'vat_amount',
        'total_amount',
        'applied_rule',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:4',
            'quantity_delivered' => 'decimal:4',
            'base_price' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'is_vat_applicable' => 'boolean',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:4',
            'total_amount' => 'decimal:4',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(WholesaleOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(WholesaleProduct::class, 'product_id');
    }
}

