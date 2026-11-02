<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFeature extends Model
{
    use HasFactory;

    public const UNLIMITED = -1;

    protected $fillable = [
        'subscription_plan_id',
        'plan_version_id',
        'feature_id',
        'feature_code',
        'is_enabled',
        'limit_value',
        'usage_quota',
        'config_value',
        'sort_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'limit_value' => 'integer',
        'usage_quota' => 'integer',
        'config_value' => 'array',
        'sort_order' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlanVersion::class, 'plan_version_id');
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class, 'feature_id');
    }

    public function isUnlimited(): bool
    {
        return $this->limit_value === self::UNLIMITED || $this->usage_quota === self::UNLIMITED;
    }

    public function getIsUnlimitedAttribute(): bool
    {
        return $this->isUnlimited();
    }
}
