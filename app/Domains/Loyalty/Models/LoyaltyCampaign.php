<?php

namespace App\Domains\Loyalty\Models;

use App\Domains\Loyalty\Enums\CampaignTriggerType;
use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyCampaign extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'loyalty_campaigns';

    protected $fillable = [
        'vendor_id',
        'name',
        'description',
        'trigger_type',
        'reward_type',
        'reward_value',
        'reward_id',
        'days_inactive',
        'starts_at',
        'ends_at',
        'is_active',
        'target_audience',
    ];

    protected function casts(): array
    {
        return [
            'trigger_type' => CampaignTriggerType::class,
            'reward_value' => 'decimal:2',
            'days_inactive' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'target_audience' => 'array',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(LoyaltyReward::class, 'reward_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(LoyaltyCampaignEvent::class, 'campaign_id')->latest('created_at');
    }
}
