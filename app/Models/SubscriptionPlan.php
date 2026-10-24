<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class, 'subscription_plan_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'subscription_plan_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(SubscriptionPlanPrice::class, 'subscription_plan_id');
    }

    public function activePrices(): HasMany
    {
        return $this->hasMany(SubscriptionPlanPrice::class, 'subscription_plan_id')->where('is_active', true);
    }

    /**
     * Resolve specific price record for billing interval and currency.
     */
    public function priceFor(string $interval = 'monthly', string $currency = 'AMD'): ?SubscriptionPlanPrice
    {
        return $this->activePrices()
            ->where('billing_interval', strtolower(trim($interval)))
            ->where('currency', strtoupper(trim($currency)))
            ->first();
    }

    /**
     * Resolve effective price amount with fallback to plan default price.
     */
    public function resolvePrice(string $interval = 'monthly', string $currency = 'AMD'): float
    {
        $planPrice = $this->priceFor($interval, $currency);
        if ($planPrice !== null) {
            return (float) $planPrice->price;
        }

        $base = (float) $this->price;
        if (strtolower($interval) === 'yearly' && $base > 0) {
            return round($base * 12 * 0.80, 2); // 20% default volume discount
        }

        return $base;
    }

    public function getFormattedPriceAttribute(): string
    {
        if ($this->is_custom || $this->price <= 0) {
            return __('Custom / Negotiated');
        }

        return number_format($this->price, 0, '.', ' ').' '.$this->currency.' / '.__('month');
    }

    public function formattedPriceFor(string $interval = 'monthly', string $currency = 'AMD'): string
    {
        $priceObj = $this->priceFor($interval, $currency);
        if ($priceObj !== null) {
            return $priceObj->formatted_price;
        }

        $amt = $this->resolvePrice($interval, $currency);
        if ($amt <= 0 || $this->is_custom) {
            return __('Custom / Negotiated');
        }

        $intervalLabel = match (strtolower($interval)) {
            'yearly' => __('year'),
            'quarterly' => __('quarter'),
            default => __('month'),
        };

        return number_format($amt, 0, '.', ' ').' '.$currency.' / '.$intervalLabel;
    }
}
