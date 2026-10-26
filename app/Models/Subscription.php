<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Exceptions\InvalidSubscriptionTransitionException;
use App\Models\Traits\BelongsToVendor;
use App\Services\EntitlementService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use BelongsToVendor, HasFactory;

    protected $fillable = [
        'vendor_id',
        'subscription_plan_id',
        'subscription_plan_price_id',
        'price',
        'currency',
        'status',
        'billing_interval',
        'trial_starts_at',
        'trial_ends_at',
        'trial_duration_days',
        'current_period_start',
        'current_period_end',
        'grace_ends_at',
        'cancelled_at',
        'cancel_at_period_end',
        'cancellation_reason',
        'renewal_state',
        'auto_renew',
        'last_payment_reference',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'status' => SubscriptionStatus::class,
        'trial_starts_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'trial_duration_days' => 'integer',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'grace_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'auto_renew' => 'boolean',
        'metadata' => 'array',
    ];

    public function getCurrencyAttribute(?string $value): string
    {
        $val = trim((string) ($value ?: '֏'));

        return strtoupper($val) === 'AMD' ? '֏' : $val;
    }

    /**
     * Relationship to the owning vendor.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Authoritative Subscription Plan (single source of truth).
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * Specific plan pricing tier / interval snapshot.
     */
    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlanPrice::class, 'subscription_plan_price_id');
    }

    /**
     * Associated billing and invoice payments.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class, 'subscription_id');
    }

    /**
     * Historical audit trail of all subscription lifecycle events.
     */
    public function lifecycleEvents(): HasMany
    {
        return $this->hasMany(SubscriptionLifecycleEvent::class, 'subscription_id')->orderBy('created_at', 'desc');
    }

    /**
     * Transition subscription to a new state governed by the formal state machine.
     * Throws InvalidSubscriptionTransitionException on forbidden state changes.
     */
    public function transitionTo(
        SubscriptionStatus $target,
        ?string $reason = null,
        ?User $actor = null,
        string $actorType = 'system',
        array $metadata = []
    ): void {
        if ($this->status === $target) {
            return;
        }

        if (! $this->status->canTransitionTo($target)) {
            throw new InvalidSubscriptionTransitionException($this->status, $target);
        }

        $oldStatus = $this->status;

        // Apply state-specific side effects
        if ($target === SubscriptionStatus::CancelAtPeriodEnd) {
            $this->cancel_at_period_end = true;
            $this->cancelled_at = now();
            $this->auto_renew = false;
            $this->renewal_state = 'cancelled';
            if ($reason) {
                $this->cancellation_reason = $reason;
            }
        } elseif ($target === SubscriptionStatus::Cancelled) {
            $this->auto_renew = false;
            $this->renewal_state = 'cancelled';
            $this->cancelled_at = now();
            if ($reason) {
                $this->cancellation_reason = $reason;
            }
        } elseif ($target === SubscriptionStatus::Active) {
            $this->auto_renew = true;
            $this->cancel_at_period_end = false;
            $this->cancelled_at = null;
            $this->cancellation_reason = null;
            $this->grace_ends_at = null;
            $this->renewal_state = 'active';
        } elseif ($target === SubscriptionStatus::Grace || $target === SubscriptionStatus::GracePeriod) {
            if (! $this->grace_ends_at || $this->grace_ends_at->isPast()) {
                $this->grace_ends_at = now()->addDays(3);
            }
        } elseif ($target === SubscriptionStatus::Expired || $target === SubscriptionStatus::Suspended) {
            $this->grace_ends_at = null;
            $this->renewal_state = 'none';
        }

        $this->status = $target;
        $this->save();

        $this->recordLifecycleEvent(
            event: 'state_transition',
            toStatus: $target,
            fromStatus: $oldStatus,
            reason: $reason,
            actor: $actor,
            actorType: $actorType,
            metadata: $metadata
        );
    }

    /**
     * Record an immutable lifecycle event for this subscription.
     */
    public function recordLifecycleEvent(
        string $event,
        ?SubscriptionStatus $toStatus = null,
        ?SubscriptionStatus $fromStatus = null,
        ?string $reason = null,
        ?User $actor = null,
        string $actorType = 'system',
        array $metadata = [],
        ?int $fromPlanId = null,
        ?int $toPlanId = null
    ): SubscriptionLifecycleEvent {
        return SubscriptionLifecycleEvent::create([
            'vendor_id' => $this->vendor_id,
            'subscription_id' => $this->id,
            'actor_id' => $actor?->id,
            'actor_type' => $actorType,
            'event' => $event,
            'from_status' => $fromStatus?->value ?? ($this->getOriginal('status') instanceof SubscriptionStatus ? $this->getOriginal('status')->value : (string) $this->getOriginal('status')),
            'to_status' => $toStatus?->value ?? $this->status->value,
            'from_plan_id' => $fromPlanId ?? $this->getOriginal('subscription_plan_id'),
            'to_plan_id' => $toPlanId ?? $this->subscription_plan_id,
            'reason' => $reason,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    /**
     * Check if currently in a valid trial period.
     */
    public function isTrialing(): bool
    {
        if ($this->status === SubscriptionStatus::Trialing) {
            return $this->trial_ends_at ? $this->trial_ends_at->isFuture() : true;
        }

        return false;
    }

    /**
     * Check if subscription is actively paid and valid.
     */
    public function isActive(): bool
    {
        if ($this->status === SubscriptionStatus::Active) {
            return $this->current_period_end ? $this->current_period_end->isFuture() : true;
        }

        return false;
    }

    /**
     * Check if subscription is currently in grace period.
     */
    public function isInGracePeriod(): bool
    {
        return in_array($this->status, [SubscriptionStatus::Grace, SubscriptionStatus::GracePeriod, SubscriptionStatus::PastDue], true)
            && $this->grace_ends_at !== null
            && $this->grace_ends_at->isFuture();
    }

    /**
     * Check if subscription has a pending cancellation scheduled at period end.
     */
    public function isCancellationRequested(): bool
    {
        if ($this->cancel_at_period_end || $this->status === SubscriptionStatus::CancelAtPeriodEnd) {
            return true;
        }

        return $this->cancelled_at !== null && $this->current_period_end && $this->current_period_end->isFuture();
    }

    /**
     * Check if subscription was cancelled.
     */
    public function isCancelled(): bool
    {
        return in_array($this->status, [SubscriptionStatus::Cancelled, SubscriptionStatus::CancelAtPeriodEnd], true)
            || $this->cancelled_at !== null;
    }

    /**
     * Check if subscription has been administratively suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === SubscriptionStatus::Suspended;
    }

    /**
     * Check if subscription has expired and has no access.
     */
    public function isExpired(): bool
    {
        if (in_array($this->status, [SubscriptionStatus::Expired, SubscriptionStatus::Suspended, SubscriptionStatus::PastDue, SubscriptionStatus::Pending, SubscriptionStatus::Incomplete], true)) {
            if ($this->isInGracePeriod()) {
                return false;
            }

            return true;
        }

        if ($this->isInGracePeriod()) {
            return false;
        }

        if (in_array($this->status, [SubscriptionStatus::Grace, SubscriptionStatus::GracePeriod], true)) {
            return true;
        }

        if ($this->isTrialing()) {
            return false;
        }

        if ($this->status === SubscriptionStatus::Trialing && $this->trial_ends_at && $this->trial_ends_at->isPast()) {
            return true;
        }

        if ($this->current_period_end && $this->current_period_end->isPast()) {
            return true;
        }

        return false;
    }

    /**
     * Determine if this subscription currently allows access to vendor features.
     */
    public function allowsAccess(): bool
    {
        if (in_array($this->status, [
            SubscriptionStatus::Suspended,
            SubscriptionStatus::Expired,
            SubscriptionStatus::PastDue,
            SubscriptionStatus::Pending,
            SubscriptionStatus::Incomplete,
        ], true)) {
            return false;
        }

        if ($this->isInGracePeriod()) {
            return true;
        }

        if (in_array($this->status, [SubscriptionStatus::Grace, SubscriptionStatus::GracePeriod], true)) {
            return false;
        }

        if ($this->isTrialing()) {
            return $this->trial_ends_at ? $this->trial_ends_at->isFuture() : true;
        }

        if ($this->status === SubscriptionStatus::Active) {
            return $this->current_period_end ? $this->current_period_end->isFuture() : true;
        }

        if ($this->status === SubscriptionStatus::CancelAtPeriodEnd) {
            return $this->current_period_end ? $this->current_period_end->isFuture() : false;
        }

        if ($this->status === SubscriptionStatus::Cancelled) {
            return $this->current_period_end ? $this->current_period_end->isFuture() : false;
        }

        return false;
    }

    /**
     * Days left in current period, trial, or grace.
     */
    public function daysLeft(): int
    {
        $targetDate = null;

        if ($this->isInGracePeriod()) {
            $targetDate = $this->grace_ends_at;
        } elseif ($this->isTrialing()) {
            $targetDate = $this->trial_ends_at;
        } elseif ($this->current_period_end) {
            $targetDate = $this->current_period_end;
        }

        if (! $targetDate) {
            return 0;
        }

        return max(0, (int) ceil(now()->diffInHours($targetDate, false) / 24));
    }

    /**
     * Check if subscription includes a particular feature.
     */
    public function hasFeature(string $feature): bool
    {
        return app(EntitlementService::class)->can($this->vendor_id, $feature);
    }

    /**
     * Resolve the effective historical locked price for this subscription.
     */
    public function getEffectivePriceAttribute(): float
    {
        if ($this->price !== null) {
            return (float) $this->price;
        }

        if ($this->planPrice) {
            return (float) $this->planPrice->price;
        }

        return (float) ($this->plan?->price ?? 0);
    }
}
