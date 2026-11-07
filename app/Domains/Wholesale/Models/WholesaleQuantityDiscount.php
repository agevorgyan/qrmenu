<?php

namespace App\Domains\Wholesale\Models;

use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WholesaleQuantityDiscount extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'wholesale_quantity_discounts';

    protected $fillable = [
        'vendor_id',
        'product_id',
        'price_list_id',
        'min_quantity',
        'discount_percent',
        'fixed_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_quantity' => 'decimal:4',
            'discount_percent' => 'decimal:2',
            'fixed_price' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(WholesaleProduct::class, 'product_id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(WholesalePriceList::class, 'price_list_id');
    }
}
