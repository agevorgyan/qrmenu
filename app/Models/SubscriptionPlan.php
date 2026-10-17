<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'price',
        'currency',
        'billing_interval',
        'duration_days',
        'trial_days',
        'features',
        'description',
        'is_active',
        'is_custom',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_custom' => 'boolean',
        'duration_days' => 'integer',
        'trial_days' => 'integer',
        'sort_order' => 'integer',
    ];

    public function vendors()
    {
        return $this->hasMany(Vendor::class, 'subscription_plan_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'subscription_plan_id');
    }

    public function getFormattedPriceAttribute(): string
    {
        if ($this->is_custom || $this->price <= 0) {
            return __('Custom / Negotiated');
        }

        return number_format($this->price, 0, '.', ' ').' '.$this->currency.' / '.__('month');
    }
}
