<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionAddon extends Model
{
    use HasFactory;

    public const TYPE_FEATURE = 'feature';

    public const TYPE_LIMIT_BOOST = 'limit_boost';

    public const TYPE_USAGE_PACK = 'usage_pack';

    protected $fillable = [
        'code',
        'name',
        'name_translations',
        'description',
        'description_translations',
        'addon_type',
        'billing_type',
        'monthly_price',
        'yearly_price',
        'one_time_price',
        'currency',
        'feature_id',
        'feature_code',
        'quantity_value',
        'compatible_plan_slugs',
        'is_active',
        'is_archived',
        'sort_order',
    ];

    protected $casts = [
        'name_translations' => 'array',
        'description_translations' => 'array',
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'one_time_price' => 'decimal:2',
        'quantity_value' => 'integer',
        'compatible_plan_slugs' => 'array',
        'is_active' => 'boolean',
        'is_archived' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class, 'feature_id');
    }

    public function vendorAddons(): HasMany
    {
        return $this->hasMany(VendorSubscriptionAddon::class, 'subscription_addon_id');
    }

    public function getValueIncrementAttribute(): ?int
    {
        return $this->quantity_value;
    }

    public function setValueIncrementAttribute(?int $value): void
    {
        $this->attributes['quantity_value'] = $value;
    }

    public function getBillingPeriodAttribute(): ?string
    {
        return $this->billing_type;
    }

    public function setBillingPeriodAttribute(?string $value): void
    {
        $this->attributes['billing_type'] = $value;
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

    public function isCompatibleWithPlan(string $planSlug): bool
    {
        $slugs = $this->compatible_plan_slugs;
        if (empty($slugs) || ! is_array($slugs) || in_array('*', $slugs, true)) {
            return true;
        }

        return in_array(strtolower(trim($planSlug)), array_map('strtolower', $slugs), true);
    }
}
