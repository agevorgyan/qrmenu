<?php

namespace App\Services;

use App\DTOs\QuotaStatus;
use App\Exceptions\QuotaExceededException;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\TenantQuotaOverride;
use App\Models\TenantUsageEvent;
use App\Models\TenantUsageMeter;
use App\Models\User;
use App\Models\Vendor;
use App\Security\QuotaCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuotaService
{
    /**
     * Resolve effective quota limit for a vendor.
     *
     * Precedence:
     * 1. Active, unexpired TenantQuotaOverride.
     * 2. Active Subscription plan configured quota_limits.
     * 3. Plan default from QuotaCatalog.
     * 4. Basic plan default fallback.
     */
    public function getLimit(Vendor|int $vendor, string $quotaKey): int
    {
        $resolvedVendor = $this->resolveVendor($vendor);
        $canonicalKey = QuotaCatalog::normalize($quotaKey);

        // 1. Check administrative override
        $override = TenantQuotaOverride::where('vendor_id', $resolvedVendor->id)
            ->where('quota_key', $canonicalKey)
            ->active()
            ->first();

        if ($override !== null) {
            return (int) $override->quota_limit;
        }

        // 2. Check active subscription plan
        $subscription = $resolvedVendor->currentSubscription
            ?? $resolvedVendor->subscriptions()->latest('id')->first();

        if ($subscription && $subscription->isActive() && $subscription->plan) {
            $planLimits = $subscription->plan->quota_limits ?? [];
            if (array_key_exists($canonicalKey, $planLimits) && $planLimits[$canonicalKey] !== null) {
                return (int) $planLimits[$canonicalKey];
            }

            return QuotaCatalog::getDefaultLimit($subscription->plan->slug, $canonicalKey);
        }

        // 3. Fallback to vendor legacy subscription_plan column
        if (! empty($resolvedVendor->subscription_plan)) {
            $plan = SubscriptionPlan::where('slug', $resolvedVendor->subscription_plan)->first();
            if ($plan && ! empty($plan->quota_limits) && array_key_exists($canonicalKey, $plan->quota_limits)) {
                return (int) $plan->quota_limits[$canonicalKey];
            }

            return QuotaCatalog::getDefaultLimit($resolvedVendor->subscription_plan, $canonicalKey);
        }

        // 4. Default to basic tier limit
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

            default => 0,
        };
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

            // If past period end, calculate aligned window
            if ($pointInTime->greaterThan($subscription->current_period_end)) {
                return [
                    $subscription->current_period_start->copy(),
                    $subscription->current_period_end->copy(),
                ];
            }
        }

        // Default calendar month window
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
     * Prevents race conditions between counting records and inserting new ones.
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
            // Lock vendor row exclusively in PostgreSQL to serialize resource creation for this tenant
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
            // 1. Idempotency check: if event already processed, do not double-increment
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

            // 2. Fetch or create meter record and lock row
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

            // Lock meter row for update
            $meter = TenantUsageMeter::where('id', $meter->id)->lockForUpdate()->first();

            // 3. Quota capacity check
            $limit = $this->getLimit($resolvedVendor, $canonicalKey);
            if ($limit !== QuotaCatalog::UNLIMITED && ($meter->used_count + $quantity > $limit)) {
                $status = QuotaStatus::make($canonicalKey, $limit, $meter->used_count, $periodEnd);
                throw new QuotaExceededException($status);
            }

            // 4. Atomic increment
            $meter->increment('used_count', $quantity);

            // 5. Append audit ledger event
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

        return TenantQuotaOverride::updateOrCreate(
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
    }

    /**
     * Remove an administrative override.
     */
    public function removeOverride(Vendor|int $vendor, string $quotaKey): bool
    {
        $resolvedVendor = $this->resolveVendor($vendor);
        $canonicalKey = QuotaCatalog::normalize($quotaKey);

        return (bool) TenantQuotaOverride::where('vendor_id', $resolvedVendor->id)
            ->where('quota_key', $canonicalKey)
            ->delete();
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
