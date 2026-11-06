<?php

namespace App\Domains\Loyalty\Models;

use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyProgram extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'loyalty_programs';

    protected $fillable = [
        'vendor_id',
        'name',
        'description',
        'currency',
        'is_active',
        'points_enabled',
        'cashback_enabled',
        'tiers_enabled',
        'stamps_enabled',
        'referrals_enabled',
        'default_points_ratio',
        'points_redemption_rate',
        'points_expiration_days',
        'cashback_expiration_days',
        'allow_concurrent_earn_and_redeem',
        'tax_eligible_rewards',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'points_enabled' => 'boolean',
            'cashback_enabled' => 'boolean',
            'tiers_enabled' => 'boolean',
            'stamps_enabled' => 'boolean',
            'referrals_enabled' => 'boolean',
            'default_points_ratio' => 'decimal:4',
            'points_redemption_rate' => 'decimal:4',
            'points_expiration_days' => 'integer',
            'cashback_expiration_days' => 'integer',
            'allow_concurrent_earn_and_redeem' => 'boolean',
            'tax_eligible_rewards' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function earningRules(): HasMany
    {
        return $this->hasMany(LoyaltyEarningRule::class, 'program_id')->orderBy('priority', 'desc');
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(LoyaltyTier::class, 'program_id')->orderBy('level', 'asc');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LoyaltyProgramVersion::class, 'program_id')->latest('version_number');
    }
}
