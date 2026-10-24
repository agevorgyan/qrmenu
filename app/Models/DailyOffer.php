<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class DailyOffer extends Model
{
    use BelongsToVendor, HasFactory;

    protected $fillable = [
        'vendor_id',
        'product_id',
        'sort_order',
        'badge',
        'subtitle',
        'discount_price',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'discount_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (DailyOffer $offer) {
            if ($offer->discount_price !== null && $offer->discount_price < 0) {
                throw new InvalidArgumentException('Offer discount price cannot be negative.');
            }

            if ($offer->product_id && $offer->vendor_id) {
                $product = Product::withoutGlobalScopes()->find($offer->product_id);
                if ($product && (int) $product->vendor_id !== (int) $offer->vendor_id) {
                    throw new InvalidArgumentException('Cross-vendor product assignment forbidden.');
                }
            }
        });
    }

    /**
     * Relationship to the featured product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
