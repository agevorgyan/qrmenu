<?php

namespace App\Domains\Loyalty\Models;

use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyTier extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'loyalty_tiers';

    protected $fillable = [
        'vendor_id',
        'program_id',
        'level',
        'name',
        'min_points',
        'min_spend',
        'evaluation_period_days',
        'points_multiplier',
        'cashback_percent',
        'order_discount_percent',
        'color_hex',
        'icon_name',
        'perks_description',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'min_points' => 'integer',
            'min_spend' => 'decimal:2',
            'evaluation_period_days' => 'integer',
            'points_multiplier' => 'decimal:2',
            'cashback_percent' => 'decimal:2',
            'order_discount_percent' => 'decimal:2',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'program_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(LoyaltyTierHistory::class, 'tier_id');
    }
}
