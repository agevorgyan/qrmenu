<?php

namespace App\Domains\Loyalty\Models;

use App\Domains\Loyalty\Enums\RewardType;
use App\Models\Product;
use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LoyaltyReward extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'loyalty_rewards';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'title',
        'description',
        'reward_type',
        'points_cost',
        'discount_value',
        'product_id',
        'required_tier_id',
        'stock_quantity',
        'redeemed_count',
        'max_per_customer',
        'is_active',
        'starts_at',
        'ends_at',
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'reward_type' => RewardType::class,
            'points_cost' => 'integer',
            'discount_value' => 'decimal:2',
            'stock_quantity' => 'integer',
            'redeemed_count' => 'integer',
            'max_per_customer' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LoyaltyReward $reward) {
            if (empty($reward->uuid)) {
                $reward->uuid = (string) Str::uuid();
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function requiredTier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'required_tier_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(LoyaltyRewardRedemption::class, 'reward_id');
    }

    public function hasStock(): bool
    {
        if ($this->stock_quantity === null) {
            return true;
        }

        return $this->stock_quantity > $this->redeemed_count;
    }
}
