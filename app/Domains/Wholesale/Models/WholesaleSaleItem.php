<?php

namespace App\Domains\Wholesale\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WholesaleSaleItem extends Model
{
    use HasFactory;

    protected $table = 'wholesale_sale_items';

    protected $fillable = [
        'sale_id',
        'order_item_id',
        'product_id',
        'sku',
        'product_name',
        'classifier_code',
        'unit_code',
        'quantity',
        'unit_price',
        'discount_amount',
        'subtotal',
        'is_vat_applicable',
        'vat_rate',
        'vat_amount',
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:5',
            'discount_amount' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'is_vat_applicable' => 'boolean',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:4',
            'total_amount' => 'decimal:4',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(WholesaleSale::class, 'sale_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(WholesaleProduct::class, 'product_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(WholesaleOrderItem::class, 'order_item_id');
    }
}
