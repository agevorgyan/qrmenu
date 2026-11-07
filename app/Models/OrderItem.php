<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'classifier_code',
        'product_name',
        'variation_name',
        'unit_price',
        'quantity',
        'subtotal',
        'status',
        'variants',
        'modifiers',
        'tax_amount',
        'discount_amount',
        'kitchen_station',
        'notes',
        'prepared_at',
        'ready_at',
        'served_at',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'variants' => 'array',
        'modifiers' => 'array',
        'prepared_at' => 'datetime',
        'ready_at' => 'datetime',
        'served_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (OrderItem $item) {
            if ($item->quantity <= 0) {
                throw new \InvalidArgumentException('Order item quantity must be greater than zero.');
            }
            if ($item->unit_price < 0 || $item->subtotal < 0) {
                throw new \InvalidArgumentException('Order item pricing cannot be negative.');
            }

            if (empty($item->classifier_code)) {
                $product = $item->product ?: ($item->product_id ? Product::withoutGlobalScopes()->withTrashed()->find($item->product_id) : null);
                $item->classifier_code = $product?->classifier_code ?: '56.10';
            }

            if ($item->product_id && $item->order_id) {
                $order = $item->order ?: Order::withoutGlobalScopes()->find($item->order_id);
                $product = $item->product ?: Product::withoutGlobalScopes()->withTrashed()->find($item->product_id);
                if ($order && $product && (int) $order->vendor_id !== (int) $product->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor product assigned to order item.');
                }
            }
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function getAtgaaCodeAttribute(): ?string
    {
        return $this->classifier_code;
    }

    public function setAtgaaCodeAttribute(?string $value): void
    {
        $this->attributes['classifier_code'] = $value;
    }

    public function getAdgCodeAttribute(): ?string
    {
        return $this->classifier_code;
    }

    public function setAdgCodeAttribute(?string $value): void
    {
        $this->attributes['classifier_code'] = $value;
    }
}
