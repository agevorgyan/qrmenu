<?php

namespace App\Domains\Wholesale\Models;

use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WholesaleCustomerPricing extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'wholesale_customer_pricing';

    protected $fillable = [
        'vendor_id',
        'customer_id',
        'price_list_id',
        'product_id',
        'contract_price',
        'discount_percent',
        'valid_from',
        'valid_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'contract_price' => 'decimal:4',
            'discount_percent' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(WholesaleCustomer::class, 'customer_id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(WholesalePriceList::class, 'price_list_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(WholesaleProduct::class, 'product_id');
    }

    public function isCurrentlyValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $today = now()->startOfDay();

        if ($this->valid_from && $today->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_to && $today->gt($this->valid_to)) {
            return false;
        }

        return true;
    }
}
