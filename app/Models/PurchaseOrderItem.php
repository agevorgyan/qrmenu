<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use BelongsToVendor, HasFactory;

    protected $fillable = [
        'vendor_id',
        'purchase_order_id',
        'ingredient_id',
        'classifier_code',
        'quantity_ordered',
        'quantity_received',
        'unit_id',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'tax_percent',
        'tax_amount',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'quantity_ordered' => 'decimal:4',
        'quantity_received' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (PurchaseOrderItem $item) {
            $base = (float) $item->quantity_ordered * (float) $item->unit_price;
            $discountPercent = (float) ($item->discount_percent ?? 0);
            $discountAmount = $discountPercent > 0 ? round($base * ($discountPercent / 100.0), 2) : (float) ($item->discount_amount ?? 0);
            $item->discount_amount = $discountAmount;
            $netBase = max(0.0, $base - $discountAmount);

            $taxPercent = (float) ($item->tax_percent ?? 0);
            $taxAmount = round($netBase * ($taxPercent / 100.0), 2);
            $item->tax_amount = $taxAmount;
            $item->total_amount = round($netBase + $taxAmount, 2);
        });
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
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
