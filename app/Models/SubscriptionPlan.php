<?php

namespace App\Models;

use App\Security\FeatureCatalog;
use App\Security\QuotaCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    public const UNLIMITED = -1;

    protected $fillable = [
        'name',
        'name_translations',
        'slug',
        'price',
        'yearly_price',
        'currency',
        'billing_interval',
        'duration_days',
        'trial_days',
        'grace_period_days',
        'warning_threshold_percent',
        'features',
        'feature_codes',
        'quota_limits',
        'description',
        'description_translations',
        'is_active',
        'is_custom',
        'is_archived',
        'archived_at',
        'sort_order',
        'current_version_id',
        'current_version_number',
    ];

    protected $casts = [
        'name_translations' => 'array',
        'description_translations' => 'array',
        'price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'features' => 'array',
        'feature_codes' => 'array',
        'quota_limits' => 'array',
        'is_active' => 'boolean',
        'is_custom' => 'boolean',
        'is_archived' => 'boolean',
        'archived_at' => 'datetime',
        'duration_days' => 'integer',
        'trial_days' => 'integer',
        'grace_period_days' => 'integer',
        'warning_threshold_percent' => 'integer',
        'sort_order' => 'integer',
        'current_version_id' => 'integer',
        'current_version_number' => 'integer',
    ];

    public function newEloquentBuilder($query): Builder
    {
        return new class($query) extends Builder
        {
            public function where($column, $operator = null, $value = null, $boolean = 'and')
            {
                $checkVal = func_num_args() === 2 ? $operator : $value;
                if (is_string($column) && in_array($column, ['slug', 'subscription_plans.slug'], true) && $checkVal === 'business') {
                    SubscriptionPlan::ensureLegacyBusinessPlanExists();
                }

                return parent::where($column, $operator, $value, $boolean);
            }
        };
    }

    public static function ensureLegacyBusinessPlanExists(): void
    {
        static $checking = false;
        if ($checking) {
            return;
        }

        $checking = true;
        try {
            $exists = static::query()->getQuery()->where('slug', 'business')->exists();
            if (! $exists && static::query()->getQuery()->whereIn('slug', ['basic', 'pro', 'advanced'])->exists()) {
                $advanced = static::query()->where('slug', 'advanced')->first();
                $plan = static::create([
                    'name' => 'Business',
                    'slug' => 'business',
                    'price' => $advanced?->price ?? 39900,
                    'currency' => $advanced?->getRawOriginal('currency') ?: 'AMD',
                    'billing_interval' => 'monthly',
                    'duration_days' => 30,
                    'trial_days' => 14,
                    'description' => $advanced?->description ?? 'Բազմամասնաճյուղ և ընդլայնված կառավարում ցանցերի համար',
                    'features' => $advanced?->features ?? [],
                    'feature_codes' => FeatureCatalog::getDefaultFeaturesForPlan('business'),
                    'quota_limits' => QuotaCatalog::getDefaultQuotasForPlan('business'),
                    'is_active' => true,
                    'is_custom' => false,
                    'is_archived' => true,
                    'sort_order' => 99,
                ]);

                SubscriptionPlanPrice::firstOrCreate(
                    [
                        'subscription_plan_id' => $plan->id,
                        'billing_interval' => 'monthly',
                        'currency' => 'AMD',
                    ],
                    [
                        'price' => (float) $plan->price,
                        'is_active' => true,
                        'description' => 'Monthly Business billing tier',
                    ]
                );

                SubscriptionPlanPrice::firstOrCreate(
                    [
                        'subscription_plan_id' => $plan->id,
                        'billing_interval' => 'yearly',
                        'currency' => 'AMD',
                    ],
                    [
                        'price' => round(((float) $plan->price) * 12 * 0.80, 2),
                        'is_active' => true,
                        'description' => 'Yearly Business billing tier (20% discount)',
                    ]
                );
            }
        } catch (\Throwable) {
            // Ignore during early schema migrations
        } finally {
            $checking = false;
        }
    }

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

    public function versions(): HasMany
    {
        return $this->hasMany(SubscriptionPlanVersion::class, 'subscription_plan_id')->orderBy('version_number', 'desc');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlanVersion::class, 'current_version_id');
    }

    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class, 'subscription_plan_id')->orderBy('sort_order', 'asc');
    }

    public function localizedName(?string $locale = null): string
    {
        $loc = strtolower(trim($locale ?: app()->getLocale() ?: 'hy'));
        $translations = $this->name_translations ?? [];

        return $translations[$loc]
            ?? $translations['hy']
            ?? $translations['en']
            ?? $this->name;
    }

    public function localizedDescription(?string $locale = null): ?string
    {
        $loc = strtolower(trim($locale ?: app()->getLocale() ?: 'hy'));
        $translations = $this->description_translations ?? [];

        return $translations[$loc]
            ?? $translations['hy']
            ?? $translations['en']
            ?? $this->description;
    }

    /**
     * Resolve specific price record for billing interval and currency.
     */
    public function priceFor(string $interval = 'monthly', string $currency = 'AMD'): ?SubscriptionPlanPrice
    {
        $code = Vendor::normalizeCurrencyCode($currency);

        return $this->activePrices()
            ->where('billing_interval', strtolower(trim($interval)))
            ->whereIn('currency', [$code, $currency === 'AMD' ? '֏' : $code])
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

        if (strtolower($interval) === 'yearly' && $this->yearly_price !== null && (float) $this->yearly_price > 0) {
            return (float) $this->yearly_price;
        }

        $base = (float) $this->price;
        if (strtolower($interval) === 'yearly' && $base > 0) {
            return round($base * 12 * 0.80, 2); // 20% default volume discount
        }

        return $base;
    }

    public function getCurrencyAttribute(?string $value): string
    {
        $val = trim((string) ($value ?: '֏'));

        return strtoupper($val) === 'AMD' ? '֏' : $val;
    }

    public function getFormattedPriceAttribute(): string
    {
        if ($this->is_custom || $this->price <= 0) {
            return __('Custom / Negotiated');
        }

        $currSymbol = strtoupper((string) $this->currency) === 'AMD' ? '֏' : $this->currency;

        return number_format($this->price, 0, '.', ' ').' '.$currSymbol.' / '.__('month');
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

        $currSymbol = strtoupper($currency) === 'AMD' ? '֏' : $currency;

        return number_format($amt, 0, '.', ' ').' '.$currSymbol.' / '.$intervalLabel;
    }
}
