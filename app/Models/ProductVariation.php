<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariation extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'name_translations',
        'price',
        'is_default',
        'tracks_inventory',
        'stock_quantity',
    ];

    protected $casts = [
        'name_translations' => 'array',
        'price' => 'decimal:2',
        'is_default' => 'boolean',
        'tracks_inventory' => 'boolean',
        'stock_quantity' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the translated name of the variation based on the current or specified locale.
     */
    public function getTranslatedName(?string $lang = null): string
    {
        $lang = $lang ?: app()->getLocale();
        if ($this->name_translations && isset($this->name_translations[$lang]) && ! empty($this->name_translations[$lang])) {
            return $this->name_translations[$lang];
        }

        return $this->name;
    }

    /**
     * Get effective price taking into account parent product discount if active.
     */
    public function getEffectivePrice(): float
    {
        $product = $this->product;
        if ($product && $product->isDiscountActive() && (float) $product->price > 0) {
            $ratio = (float) $product->discount_price / (float) $product->price;

            return round((float) $this->price * $ratio, 2);
        }

        return (float) $this->price;
    }
}
