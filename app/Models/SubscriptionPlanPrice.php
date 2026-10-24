<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlanPrice extends Model
{
    use HasFactory;

    protected $table = 'subscription_plan_prices';

    protected $fillable = [
        'subscription_plan_id',
        'billing_interval',
        'currency',
        'price',
        'is_active',
        'description',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'subscription_plan_price_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForInterval(Builder $query, string $interval): Builder
    {
        return $query->where('billing_interval', strtolower(trim($interval)));
    }

    public function getFormattedPriceAttribute(): string
    {
        if ($this->price <= 0) {
            return __('Custom / Negotiated');
        }

        $intervalLabel = match (strtolower($this->billing_interval)) {
            'yearly' => __('year'),
            'quarterly' => __('quarter'),
            default => __('month'),
        };

        return number_format($this->price, 0, '.', ' ').' '.$this->currency.' / '.$intervalLabel;
    }
}
