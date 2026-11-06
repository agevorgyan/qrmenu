<?php

namespace App\Domains\Loyalty\Models;

use App\Models\Customer;
use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyTierHistory extends Model
{
    use BelongsToVendor, HasFactory;

    public $timestamps = false;

    protected $table = 'loyalty_tier_histories';

    protected $fillable = [
        'vendor_id',
        'customer_id',
        'tier_id',
        'previous_tier_id',
        'reason',
        'effective_until',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_until' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'tier_id');
    }

    public function previousTier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'previous_tier_id');
    }
}
