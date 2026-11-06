<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feature extends Model
{
    use HasFactory;

    public const TYPE_BOOLEAN = 'BOOLEAN';

    public const TYPE_LIMIT = 'LIMIT';

    public const TYPE_USAGE = 'USAGE';

    public const TYPE_CONFIGURATION = 'CONFIGURATION';

    public const TYPE_ENUM = 'ENUM';

    public const TYPE_METERED = 'METERED';

    public const UNLIMITED = -1;

    protected $fillable = [
        'category_id',
        'code',
        'name',
        'name_translations',
        'description',
        'description_translations',
        'feature_type',
        'default_value',
        'allowed_options',
        'unit',
        'reset_period',
        'is_active',
        'is_archived',
        'admin_visible',
        'vendor_visible',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'name_translations' => 'array',
        'description_translations' => 'array',
        'default_value' => 'array',
        'allowed_options' => 'array',
        'is_active' => 'boolean',
        'is_archived' => 'boolean',
        'admin_visible' => 'boolean',
        'vendor_visible' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (Feature $feature): void {
            if ($feature->isDirty('code') && ! empty($feature->getOriginal('code'))) {
                // Feature codes are immutable once created unless explicitly flagged for safe migration
                if (! ($feature->metadata['allow_code_migration'] ?? false)) {
                    $feature->code = $feature->getOriginal('code');
                }
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FeatureCategory::class, 'category_id');
    }

    public function dependencies(): HasMany
    {
        return $this->hasMany(FeatureDependency::class, 'feature_id')->where('is_active', true);
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(FeatureDependency::class, 'depends_on_feature_id')->where('is_active', true);
    }

    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class, 'feature_id');
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

    public function isUsedByActiveSubscriptions(): bool
    {
        return PlanFeature::where('feature_id', $this->id)
            ->where('is_enabled', true)
            ->whereHas('plan', function ($q) {
                $q->whereHas('subscriptions', function ($sq) {
                    $sq->whereIn('status', ['active', 'trialing', 'grace', 'past_due']);
                })->orWhereHas('vendors', function ($vq) {
                    $vq->where('is_active', true);
                });
            })
            ->exists();
    }

    public function resolveDefaultScalar(): mixed
    {
        $val = $this->default_value;
        if (is_array($val) && array_key_exists('value', $val)) {
            return $val['value'];
        }

        return match ($this->feature_type) {
            self::TYPE_BOOLEAN => (bool) ($val ?? false),
            self::TYPE_LIMIT, self::TYPE_USAGE, self::TYPE_METERED => (int) ($val ?? 0),
            default => $val,
        };
    }
}
