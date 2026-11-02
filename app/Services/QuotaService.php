<?php

namespace App\Services;

use App\DTOs\QuotaStatus;
use App\Exceptions\QuotaExceededException;
use App\Models\Category;
use App\Models\Location;
use App\Models\PlanFeature;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanVersion;
use App\Models\TenantFeatureOverride;
use App\Models\TenantQuotaOverride;
use App\Models\TenantUsageEvent;
use App\Models\TenantUsageMeter;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLanguage;
use App\Security\QuotaCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class QuotaService
{
    /**
     * Map canonical QuotaCatalog keys to Feature Catalog codes.
     */
    protected const QUOTA_TO_FEATURE_MAP = [
        QuotaCatalog::KEY_PRODUCTS_COUNT => 'menu.products.limit',
        QuotaCatalog::KEY_CATEGORIES_COUNT => 'menu.categories.limit',
        QuotaCatalog::KEY_BRANCHES_COUNT => 'locations.limit',
        QuotaCatalog::KEY_TEAM_MEMBERS_COUNT => 'staff.users.limit',
        QuotaCatalog::KEY_ORDERS_PERIOD => 'orders.monthly_limit',
        QuotaCatalog::KEY_AI_REQUESTS_PERIOD => 'ai.monthly_credits',
        QuotaCatalog::KEY_LANGUAGES_COUNT => 'menu.languages.limit',
        QuotaCatalog::KEY_DELIVERY_ZONES_COUNT => 'delivery.zones.limit',
        QuotaCatalog::KEY_CURRENCIES_COUNT => 'currency.max_additional',
    ];

    /**
     * Resolve effective quota limit for a vendor.
     *
     * Precedence:
     * 1. Active, unexpired TenantQuotaOverride or TenantFeatureOverride (limit_value / usage_quota).
     * 2. Active Subscription pinned PlanVersion / PlanFeature / quota_limits + Active Add-ons.
     * 3. Plan default from QuotaCatalog.
     * 4. Basic plan default fallback.
     */
    public function getLimit(Vendor|int $vendor, string $quotaKey): int
    {
        $resolvedVendor = $this->resolveVendor($vendor);
        $canonicalKey = QuotaCatalog::normalize($quotaKey);
        $mappedFeatureCode = self::QUOTA_TO_FEATURE_MAP[$canonicalKey] ?? $quotaKey;

        // 1. Check administrative quota override
        $override = TenantQuotaOverride::where('vendor_id', $resolvedVendor->id)
            ->whereIn('quota_key', array_unique([$canonicalKey, $quotaKey, $mappedFeatureCode]))
            ->active()
            ->latest('id')
            ->first();

        if ($override !== null) {
            return (int) $override->quota_limit;
        }

        // Also check TenantFeatureOverride if it carries a limit_value or usage_quota
        if (Schema::hasColumn('tenant_feature_overrides', 'limit_value')) {
            $featureOverride = TenantFeatureOverride::withoutGlobalScopes()
                ->where('vendor_id', $resolvedVendor->id)
                ->whereIn('feature_key', array_unique([$canonicalKey, $quotaKey, $mappedFeatureCode]))
                ->valid()
                ->latest('id')
                ->first();

            if ($featureOverride !== null) {
                if (! $featureOverride->is_enabled) {
                    return 0;
                }
                if ($featureOverride->limit_value !== null) {
                    return (int) $featureOverride->limit_value;
                }
                if ($featureOverride->usage_quota !== null) {
                    return (int) $featureOverride->usage_quota;
                }
            }
        }

        $baseLimit = $this->resolveBasePlanLimit($resolvedVendor, $canonicalKey, $mappedFeatureCode);

        if ($baseLimit === QuotaCatalog::UNLIMITED) {
            return QuotaCatalog::UNLIMITED;
        }

        // Add any active add-on bonuses
        $addonBonus = app(SubscriptionAddonService::class)->getAdditiveBonus($resolvedVendor, $canonicalKey);

        return $baseLimit + $addonBonus;
    }

    protected function resolveBasePlanLimit(Vendor $resolvedVendor, string $canonicalKey, string $mappedFeatureCode): int
    {
        $subscription = $resolvedVendor->currentSubscription
            ?? $resolvedVendor->subscription
            ?? $resolvedVendor->subscriptions()->latest('id')->first();

        // Check pinned plan version first if subscription is active/trialing/grace
        if ($subscription && $subscription->allowsAccess()) {
            if (! empty($subscription->plan_version_id) && Schema::hasTable('subscription_plan_versions')) {
                $version = SubscriptionPlanVersion::find($subscription->plan_version_id);
                if ($version && is_array($version->limits_snapshot) && array_key_exists($mappedFeatureCode, $version->limits_snapshot)) {
                    return (int) $version->limits_snapshot[$mappedFeatureCode];
                }
            }

            if ($subscription->plan) {
                $plan = $subscription->plan;
                $planLimits = $plan->quota_limits ?? [];
                if (array_key_exists($canonicalKey, $planLimits) && $planLimits[$canonicalKey] !== null) {
                    return (int) $planLimits[$canonicalKey];
                }

                if (Schema::hasTable('plan_features')) {
                    $pf = PlanFeature::where('subscription_plan_id', $plan->id)
                        ->where('feature_code', $mappedFeatureCode)
                        ->latest('id')
                        ->first();
                    if ($pf) {
                        if (! $pf->is_enabled) {
                            return 0;
                        }
                        if ($pf->limit_value !== null) {
                            return (int) $pf->limit_value;
                        }
                        if ($pf->usage_quota !== null) {
                            return (int) $pf->usage_quota;
                        }
                    }
                }

                return QuotaCatalog::getDefaultLimit($plan->slug, $canonicalKey);
            }
        }

        // Fallback to vendor plan relation or subscription_plan column
        $plan = $resolvedVendor->plan
            ?? (! empty($resolvedVendor->subscription_plan_id) ? SubscriptionPlan::find($resolvedVendor->subscription_plan_id) : null)
            ?? (! empty($resolvedVendor->subscription_plan) ? SubscriptionPlan::where('slug', $resolvedVendor->subscription_plan)->first() : null);

        if ($plan) {
            if (! empty($plan->quota_limits) && array_key_exists($canonicalKey, $plan->quota_limits)) {
                return (int) $plan->quota_limits[$canonicalKey];
            }

            if (Schema::hasTable('plan_features')) {
                $pf = PlanFeature::where('subscription_plan_id', $plan->id)
                    ->where('feature_code', $mappedFeatureCode)
                    ->latest('id')
                    ->first();
                if ($pf) {
                    if (! $pf->is_enabled) {
                        return 0;
                    }
                    if ($pf->limit_value !== null) {
                        return (int) $pf->limit_value;
                    }
                    if ($pf->usage_quota !== null) {
                        return (int) $pf->usage_quota;
                    }
                }
            }

            return QuotaCatalog::getDefaultLimit($plan->slug, $canonicalKey);
        }

        if (! empty($resolvedVendor->subscription_plan)) {
            return QuotaCatalog::getDefaultLimit($resolvedVendor->subscription_plan, $canonicalKey);
        }

        return QuotaCatalog::getDefaultLimit('basic', $canonicalKey);
    }

    /**
     * Resolve current usage for a vendor and quota key.
     */
    public function getCurrentUsage(Vendor|int $vendor, string $quotaKey, ?Carbon $at = null): int
    {
        $resolvedVendor = $this->resolveVendor($vendor);
        $canonicalKey = QuotaCatalog::normalize($quotaKey);
        $type = QuotaCatalog::getType($canonicalKey);

        if ($type === QuotaCatalog::TYPE_RESOURCE) {
            return $this->getResourceUsage($resolvedVendor, $canonicalKey);
        }

        return $this->getEventUsage($resolvedVendor, $canonicalKey, $at);
    }

    /**
     * Resolve resource-count (stateful) usage.
     */
    protected function getResourceUsage(Vendor $vendor, string $canonicalKey): int
    {
        return match ($canonicalKey) {
            QuotaCatalog::KEY_PRODUCTS_COUNT => (int) Product::where('vendor_id', $vendor->id)
                ->whereNull('deleted_at')
                ->count(),

            QuotaCatalog::KEY_CATEGORIES_COUNT => (int) Category::where('vendor_id', $vendor->id)
                ->whereNull('deleted_at')
                ->count(),

            QuotaCatalog::KEY_BRANCHES_COUNT => (int) Location::where('vendor_id', $vendor->id)
                ->where('is_active', true)
                ->count(),

            QuotaCatalog::KEY_TEAM_MEMBERS_COUNT => (int) User::where('vendor_id', $vendor->id)
                ->where('role', '!=', 'superadmin')
                ->count(),

            QuotaCatalog::KEY_STORAGE_BYTES => (int) ($vendor->storage_used_bytes ?? 0),

            QuotaCatalog::KEY_LANGUAGES_COUNT => $this->countVendorLanguages($vendor),

            QuotaCatalog::KEY_DELIVERY_ZONES_COUNT => $this->countVendorDeliveryZones($vendor),

            QuotaCatalog::KEY_CURRENCIES_COUNT => $vendor->secondary_currency_enabled ? 1 : 0,

            default => 0,
        };
    }

    protected function countVendorLanguages(Vendor $vendor): int
    {
        try {
            if (Schema::hasTable('vendor_languages')) {
                $count = VendorLanguage::where('vendor_id', $vendor->id)->where('is_active', true)->count();
                if ($count > 0) {
                    return (int) $count;
                }
            }
        } catch (\Throwable) {
            // Fallback
        }

        if (is_array($vendor->supported_languages) && ! empty($vendor->supported_languages)) {
            return count($vendor->supported_languages);
        }

        return 1;
    }

    protected function countVendorDeliveryZones(Vendor $vendor): int
    {
        $zones = $vendor->ai_settings['delivery_zones'] ?? ($vendor->payment_settings['delivery_zones'] ?? []);

        return is_array($zones) ? count($zones) : 0;
    }

    /**
     * Resolve event-based (windowed meter) usage.
     */
    protected function getEventUsage(Vendor $vendor, string $canonicalKey, ?Carbon $at = null): int
    {
        [$periodStart, $periodEnd] = $this->resolvePeriodWindow($vendor, $at);

        $meter = TenantUsageMeter::where('vendor_id', $vendor->id)
            ->where('quota_key', $canonicalKey)
            ->where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->first();

        return $meter ? (int) $meter->used_count : 0;
    }

    /**
     * Resolve the active billing period window.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolvePeriodWindow(Vendor|int $vendor, ?Carbon $at = null): array
    {
        $resolvedVendor = $this->resolveVendor($vendor);
        $pointInTime = ($at ?? now())->copy();

        $subscription = $resolvedVendor->currentSubscription
            ?? $resolvedVendor->subscriptions()->latest('id')->first();

        if ($subscription && $subscription->current_period_start && $subscription->current_period_end) {
            if ($pointInTime->betweenIncluded($subscription->current_period_start, $subscription->current_period_end)) {
                return [
                    $subscription->current_period_start->copy(),
                    $subscription->current_period_end->copy(),
                ];
            }

            if ($pointInTime->greaterThan($subscription->current_period_end)) {
                return [
                    $subscription->current_period_start->copy(),
                    $subscription->current_period_end->copy(),
                ];
            }
        }

        return [
            $pointInTime->copy()->startOfMonth(),
            $pointInTime->copy()->endOfMonth(),
        ];
    }

    /**
     * Get complete, standardized quota status.
     */
    public function getQuotaStatus(Vendor|int $vendor, string $quotaKey, ?Carbon $at = null): QuotaStatus
    {
        $resolvedVendor = $this->resolveVendor($vendor);
        $canonicalKey = QuotaCatalog::normalize($quotaKey);

        $limit = $this->getLimit($resolvedVendor, $canonicalKey);
        $currentUsage = $this->getCurrentUsage($resolvedVendor, $canonicalKey, $at);

        $resetAt = null;
        if (QuotaCatalog::getType($canonicalKey) === QuotaCatalog::TYPE_EVENT) {
            $resetAt = $this->resolvePeriodWindow($resolvedVendor, $at)[1];
        }

        return QuotaStatus::make(
            quotaKey: $canonicalKey,
            limit: $limit,
            currentUsage: $currentUsage,
            resetAt: $resetAt,
            scope: 'tenant',
            locale: app()->getLocale()
        );
    }

    /**
     * Check if a vendor can consume a specific quota quantity.
     */
    public function can(Vendor|int $vendor, string $quotaKey, int $quantity = 1): bool
    {
        if ($quantity <= 0) {
            return true;
        }

        $status = $this->getQuotaStatus($vendor, $quotaKey);

        if ($status->isUnlimited) {
            return true;
        }

        return ($status->currentUsage + $quantity) <= $status->limit;
    }

    /**
     * Assert quota capacity or throw QuotaExceededException.
     *
     * @throws QuotaExceededException
     */
    public function ensureWithinQuota(Vendor|int $vendor, string $quotaKey, int $quantity = 1): QuotaStatus
    {
        $status = $this->getQuotaStatus($vendor, $quotaKey);

        if (! $status->isUnlimited && ($status->currentUsage + $quantity > $status->limit)) {
            throw new QuotaExceededException($status);
        }

        return $status;
    }

    /**
     * Atomically execute a callback under a resource-count quota lock.
     *
     * @template T
     *
     * @param  callable(Vendor): T  $callback
     * @return T
     *
     * @throws QuotaExceededException
     */
    public function executeWithResourceQuota(
        Vendor|int $vendor,
        string $quotaKey,
        callable $callback,
        int $quantity = 1
    ): mixed {
        $vendorId = $vendor instanceof Vendor ? $vendor->id : $vendor;
        $canonicalKey = QuotaCatalog::normalize($quotaKey);

        return DB::transaction(function () use ($vendorId, $canonicalKey, $callback, $quantity) {
            $lockedVendor = Vendor::where('id', $vendorId)->lockForUpdate()->firstOrFail();

            $limit = $this->getLimit($lockedVendor, $canonicalKey);
            $currentUsage = $this->getCurrentUsage($lockedVendor, $canonicalKey);

            if ($limit !== QuotaCatalog::UNLIMITED && ($currentUsage + $quantity > $limit)) {
                $status = QuotaStatus::make($canonicalKey, $limit, $currentUsage, null);
                throw new QuotaExceededException($status);
            }

            return $callback($lockedVendor);
        });
    }

    /**
     * Atomically record event-based usage with idempotency and audit trails.
     *
     * @throws QuotaExceededException
     */
    public function recordUsage(
        Vendor|int $vendor,
        string $quotaKey,
        int $quantity = 1,
        ?string $idempotencyKey = null,
        array $metadata = []
    ): QuotaStatus {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Usage quantity must be positive.');
        }

        $resolvedVendor = $this->resolveVendor($vendor);
        $canonicalKey = QuotaCatalog::normalize($quotaKey);

        return DB::transaction(function () use ($resolvedVendor, $canonicalKey, $quantity, $idempotencyKey, $metadata) {
            if (! empty($idempotencyKey)) {
                $existingEvent = TenantUsageEvent::where('vendor_id', $resolvedVendor->id)
                    ->where('quota_key', $canonicalKey)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingEvent !== null) {
                    return $this->getQuotaStatus($resolvedVendor, $canonicalKey);
                }
            }

            [$periodStart, $periodEnd] = $this->resolvePeriodWindow($resolvedVendor);

            $meter = TenantUsageMeter::firstOrCreate(
                [
                    'vendor_id' => $resolvedVendor->id,
                    'quota_key' => $canonicalKey,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                ],
                [
                    'subscription_id' => $resolvedVendor->currentSubscription?->id,
                    'used_count' => 0,
                ]
            );

            $meter = TenantUsageMeter::where('id', $meter->id)->lockForUpdate()->first();

            $limit = $this->getLimit($resolvedVendor, $canonicalKey);
            if ($limit !== QuotaCatalog::UNLIMITED && ($meter->used_count + $quantity > $limit)) {
                $status = QuotaStatus::make($canonicalKey, $limit, $meter->used_count, $periodEnd);
                throw new QuotaExceededException($status);
            }

            $meter->increment('used_count', $quantity);

            TenantUsageEvent::create([
                'vendor_id' => $resolvedVendor->id,
                'quota_key' => $canonicalKey,
                'quantity' => $quantity,
                'idempotency_key' => $idempotencyKey,
                'recorded_at' => now(),
                'metadata' => $metadata,
                'created_by' => auth()->id(),
            ]);

            return QuotaStatus::make(
                quotaKey: $canonicalKey,
                limit: $limit,
                currentUsage: $meter->used_count,
                resetAt: $periodEnd,
                scope: 'tenant',
                locale: app()->getLocale()
            );
        });
    }

    /**
     * Administrative override creation or update.
     */
    public function setOverride(
        Vendor|int $vendor,
        string $quotaKey,
        int $limit,
        string $reason,
        ?Carbon $expiresAt = null,
        ?User $actor = null
    ): TenantQuotaOverride {
        $resolvedVendor = $this->resolveVendor($vendor);
        $canonicalKey = QuotaCatalog::normalize($quotaKey);

        $override = TenantQuotaOverride::updateOrCreate(
            [
                'vendor_id' => $resolvedVendor->id,
                'quota_key' => $canonicalKey,
            ],
            [
                'quota_limit' => $limit,
                'reason' => $reason,
                'expires_at' => $expiresAt,
                'created_by' => $actor?->id ?? auth()->id(),
                'updated_by' => $actor?->id ?? auth()->id(),
            ]
        );

        app(EntitlementService::class)->clearCache($resolvedVendor);

        return $override;
    }

    /**
     * Remove an administrative override.
     */
    public function removeOverride(Vendor|int $vendor, string $quotaKey): bool
    {
        $resolvedVendor = $this->resolveVendor($vendor);
        $canonicalKey = QuotaCatalog::normalize($quotaKey);

        $deleted = (bool) TenantQuotaOverride::where('vendor_id', $resolvedVendor->id)
            ->where('quota_key', $canonicalKey)
            ->delete();

        app(EntitlementService::class)->clearCache($resolvedVendor);

        return $deleted;
    }

    /**
     * Reset a meter's usage (for administrative compensation or adjustments).
     */
    public function resetMeter(Vendor|int $vendor, string $quotaKey, ?Carbon $at = null, ?User $actor = null): bool
    {
        $resolvedVendor = $this->resolveVendor($vendor);
        $canonicalKey = QuotaCatalog::normalize($quotaKey);

        [$periodStart, $periodEnd] = $this->resolvePeriodWindow($resolvedVendor, $at);

        $meter = TenantUsageMeter::where('vendor_id', $resolvedVendor->id)
            ->where('quota_key', $canonicalKey)
            ->where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->first();

        if (! $meter) {
            return false;
        }

        $meter->update(['used_count' => 0]);

        TenantUsageEvent::create([
            'vendor_id' => $resolvedVendor->id,
            'quota_key' => $canonicalKey,
            'quantity' => 0,
            'idempotency_key' => 'admin_reset_'.uniqid(),
            'recorded_at' => now(),
            'metadata' => [
                'action' => 'admin_meter_reset',
                'reset_by' => $actor?->id ?? auth()->id(),
            ],
            'created_by' => $actor?->id ?? auth()->id(),
        ]);

        return true;
    }

    /**
     * Internal helper to resolve Vendor model.
     */
    protected function resolveVendor(Vendor|int $vendor): Vendor
    {
        if ($vendor instanceof Vendor) {
            return $vendor;
        }

        return Vendor::findOrFail($vendor);
    }
}
