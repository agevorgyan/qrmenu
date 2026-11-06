<?php

namespace App\Domains\Loyalty\Models;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Traits\BelongsToVendor;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LoyaltyRewardRedemption extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'loyalty_reward_redemptions';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'reward_id',
        'customer_id',
        'order_id',
        'staff_id',
        'redemption_code',
        'points_spent',
        'status',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'points_spent' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LoyaltyRewardRedemption $redemption) {
            if (empty($redemption->uuid)) {
                $redemption->uuid = (string) Str::uuid();
            }
            if (empty($redemption->redemption_code)) {
                $redemption->redemption_code = 'RWD-'.strtoupper(Str::random(8));
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(LoyaltyReward::class, 'reward_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function isExpired(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isPast();
    }
}
