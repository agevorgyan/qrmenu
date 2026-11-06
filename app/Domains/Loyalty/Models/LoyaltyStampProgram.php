<?php

namespace App\Domains\Loyalty\Models;

use App\Models\Product;
use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyStampProgram extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'loyalty_stamp_programs';

    protected $fillable = [
        'vendor_id',
        'name',
        'description',
        'stamps_required',
        'min_order_spend',
        'qualifying_category_ids',
        'qualifying_product_ids',
        'reward_description',
        'reward_product_id',
        'reward_discount_amount',
        'card_validity_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'stamps_required' => 'integer',
            'min_order_spend' => 'decimal:2',
            'qualifying_category_ids' => 'array',
            'qualifying_product_ids' => 'array',
            'reward_discount_amount' => 'decimal:2',
            'card_validity_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function rewardProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'reward_product_id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(LoyaltyStampCard::class, 'program_id');
    }
}
