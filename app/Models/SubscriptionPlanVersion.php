<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlanVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_plan_id',
        'version_number',
        'name',
        'name_translations',
        'description',
        'description_translations',
        'monthly_price',
        'yearly_price',
        'currency',
        'trial_days',
        'features_snapshot',
        'limits_snapshot',
        'change_summary',
        'is_current',
        'created_by',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'name_translations' => 'array',
        'description_translations' => 'array',
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'trial_days' => 'integer',
        'features_snapshot' => 'array',
        'limits_snapshot' => 'array',
        'is_current' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class, 'plan_version_id')->orderBy('sort_order', 'asc');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

