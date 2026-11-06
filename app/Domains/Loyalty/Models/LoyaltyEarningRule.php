<?php

namespace App\Domains\Loyalty\Models;

use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyEarningRule extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'loyalty_earning_rules';

    protected $fillable = [
        'vendor_id',
        'program_id',
        'name',
        'type',
        'reward_type',
        'rate_value',
        'min_order_spend',
        'max_points_per_order',
        'conditions',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate_value' => 'decimal:4',
            'min_order_spend' => 'decimal:2',
            'max_points_per_order' => 'integer',
            'conditions' => 'array',
            'priority' => 'integer',
            'is_active' => 'boolean',
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
}
