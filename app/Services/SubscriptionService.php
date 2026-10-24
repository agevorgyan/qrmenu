<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\InvalidSubscriptionTransitionException;
use App\Models\PaymentAttempt;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /**
     * Get or create authoritative subscription for a vendor.
     */
    public function getOrCreateForVendor(Vendor $vendor): Subscription
    {
        $existing = Subscription::withoutTenantQuery()->where('vendor_id', $vendor->id)->latest()->first();
        if ($existing) {
            return $existing;
        }

        $planId = $vendor->subscription_plan_id;
        if (! $planId && ! empty($vendor->subscription_plan)) {
            $planId = SubscriptionPlan::where('slug', $vendor->subscription_plan)->value('id');
        }
        if (! $planId) {
            $planId = SubscriptionPlan::where('slug', 'pro')->value('id') ?? SubscriptionPlan::value('id') ?? 1;
        }

        $plan = SubscriptionPlan::find($planId);
        $statusStr = $vendor->subscription_status ?? 'trialing';
        $status = SubscriptionStatus::tryFrom($statusStr) ?? SubscriptionStatus::Trialing;

        $priceObj = $plan?->priceFor('monthly', $vendor->currency ?: 'AMD');
        $basePrice = $priceObj ? (float) $priceObj->price : (float) ($plan?->price ?? 0);

        $subscription = Subscription::create([
            'vendor_id' => $vendor->id,
            'subscription_plan_id' => $planId,
            'subscription_plan_price_id' => $priceObj?->id,
            'price' => $basePrice,
            'currency' => $vendor->currency ?: ($plan?->currency ?? 'AMD'),
            'status' => $status,
            'billing_interval' => 'monthly',
            'trial_starts_at' => $vendor->created_at ?? now(),
            'trial_ends_at' => $vendor->trial_ends_at,
            'trial_duration_days' => $plan?->trial_days ?? 14,
            'current_period_start' => $vendor->current_period_start ?? $vendor->created_at ?? now(),
            'current_period_end' => $vendor->subscription_expires_at,
            'grace_ends_at' => $vendor->grace_ends_at,
            'cancelled_at' => $vendor->cancelled_at,
            'renewal_state' => 'active',
            'auto_renew' => true,
        ]);

        $subscription->recordLifecycleEvent(
            event: 'created',
            toStatus: $status,
            reason: 'Authoritative subscription initialized for vendor'
        );

        return $subscription;
    }

    /**
     * Start a new trial subscription for a vendor.
     */
    public function startTrial(
        Vendor $vendor,
        SubscriptionPlan $plan,
        ?int $trialDays = null,
        ?User $actor = null
    ): Subscription {
        return DB::transaction(function () use ($vendor, $plan, $trialDays, $actor) {
            $existing = Subscription::withoutTenantQuery()
                ->where('vendor_id', $vendor->id)
                ->lockForUpdate()
                ->first();

            // Invariant 1: If vendor's current status cannot transition to Trialing, reject downgrade/re-trial
            if ($existing && ! $existing->status->canTransitionTo(SubscriptionStatus::Trialing)) {
                Log::warning("Vendor {$vendor->id} has subscription status {$existing->status->value} which cannot transition to trialing. Ignoring startTrial downgrade request.");

                return $existing;
            }

            $days = $trialDays ?? $plan->trial_days ?? 14;
            $now = now();

            // Invariant 2: If vendor is currently trialing, do not extend or reset the trial clock
            if ($existing && $existing->status === SubscriptionStatus::Trialing) {
                if ($existing->trial_ends_at?->isFuture()) {
                    if ($existing->subscription_plan_id !== $plan->id) {
                        $oldPlanId = $existing->subscription_plan_id;
                        $existing->update(['subscription_plan_id' => $plan->id]);
                        $existing->recordLifecycleEvent(
                            event: 'trial_plan_switched',
                            toStatus: SubscriptionStatus::Trialing,
                            fromStatus: SubscriptionStatus::Trialing,
                            reason: "Trial plan switched to {$plan->slug}",
                            actor: $actor,
                            fromPlanId: $oldPlanId,
                            toPlanId: $plan->id
                        );
                        $this->syncVendor($existing);
                    }

                    Log::info("Vendor {$vendor->id} trial already active until {$existing->trial_ends_at->toDateTimeString()}. Preserving existing trial.");

                    return $existing;
                }

                $this->expire($existing, 'Trial period elapsed without payment', $actor);
                Log::warning("Vendor {$vendor->id} trial has already elapsed. Re-trialing not permitted.");

                return $existing;
            }

            $trialStartsAt = $existing?->trial_starts_at ?? $now;
            $trialEnd = (clone $now)->addDays($days);
            $priceObj = $plan->priceFor('monthly', $vendor->currency ?: 'AMD');
            $basePrice = $priceObj ? (float) $priceObj->price : (float) $plan->price;

            $subscription = Subscription::withoutTenantQuery()->updateOrCreate(
                ['vendor_id' => $vendor->id],
                [
                    'subscription_plan_id' => $plan->id,
                    'subscription_plan_price_id' => $priceObj?->id,
                    'price' => $basePrice,
                    'currency' => $vendor->currency ?: ($plan->currency ?? 'AMD'),
                    'status' => SubscriptionStatus::Trialing,
                    'billing_interval' => 'monthly',
                    'trial_starts_at' => $trialStartsAt,
                    'trial_ends_at' => $trialEnd,
                    'trial_duration_days' => $days,
                    'current_period_start' => $now,
                    'current_period_end' => $trialEnd,
                    'grace_ends_at' => null,
                    'cancelled_at' => null,
                    'cancel_at_period_end' => false,
                    'renewal_state' => 'active',
                    'auto_renew' => true,
                ]
            );

            $subscription->recordLifecycleEvent(
                event: 'trial_started',
                toStatus: SubscriptionStatus::Trialing,
                fromStatus: $existing?->status,
                reason: "Started {$days}-day free trial for plan {$plan->slug}",
                actor: $actor,
                toPlanId: $plan->id
            );

            $this->syncVendor($subscription);

            Log::info("Vendor {$vendor->id} trial started for plan {$plan->slug} until {$trialEnd->toDateTimeString()}");

            return $subscription;
        });
    }

    /**
     * Activate a subscription following a successful initial payment.
     */
    public function activate(
        Subscription $subscription,
        SubscriptionPlan $plan,
        SubscriptionPayment $payment,
        ?PaymentAttempt $attempt = null,
        ?User $actor = null
    ): void {
        DB::transaction(function () use ($subscription, $plan, $payment, $attempt, $actor) {
            $lockedSub = Subscription::withoutTenantQuery()->where('id', $subscription->id)->lockForUpdate()->first();
            if (! $lockedSub) {
                return;
            }

            $now = now();
            $periodStart = $payment->period_start ? Carbon::parse($payment->period_start) : $now;
            $periodEnd = $payment->period_end ? Carbon::parse($payment->period_end) : (clone $periodStart)->addMonth();
            $ref = $attempt?->provider_transaction_id ?? $attempt?->merchant_reference ?? (string) $payment->invoice_number;

            $priceObj = $plan->priceFor($payment->period_end ? 'monthly' : 'monthly', $payment->currency ?: 'AMD');
            $oldStatus = $lockedSub->status;

            $lockedSub->update([
                'subscription_plan_id' => $plan->id,
                'subscription_plan_price_id' => $priceObj?->id ?? $lockedSub->subscription_plan_price_id,
                'price' => $payment->amount,
                'currency' => $payment->currency ?: ($plan->currency ?? 'AMD'),
                'status' => SubscriptionStatus::Active,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'grace_ends_at' => null,
                'cancelled_at' => null,
                'cancel_at_period_end' => false,
                'renewal_state' => 'active',
                'auto_renew' => true,
                'last_payment_reference' => $ref,
            ]);

            $payment->update([
                'subscription_id' => $lockedSub->id,
                'status' => 'completed',
            ]);

            $lockedSub->recordLifecycleEvent(
                event: 'activated',
                toStatus: SubscriptionStatus::Active,
                fromStatus: $oldStatus,
                reason: "Activated via payment reference {$ref}",
                actor: $actor,
                metadata: ['amount' => (float) $payment->amount, 'currency' => $payment->currency, 'reference' => $ref],
                toPlanId: $plan->id
            );

            $this->syncVendor($lockedSub);

            Log::info("Subscription {$lockedSub->id} activated via payment {$ref} until {$periodEnd->toDateTimeString()}");
        });
    }

    /**
     * Idempotently renew an existing subscription upon successful renewal payment.
     * Returns false if this transaction was already processed (idempotent skip), true if renewed.
     */
    public function renew(
        Subscription $subscription,
        SubscriptionPayment $payment,
        PaymentAttempt $attempt,
        ?string $idempotencyKey = null,
        ?User $actor = null
    ): bool {
        return DB::transaction(function () use ($subscription, $payment, $attempt, $actor): bool {
            $lockedSub = Subscription::withoutTenantQuery()->where('id', $subscription->id)->lockForUpdate()->first();
            if (! $lockedSub) {
                return false;
            }

            $ref = $attempt->provider_transaction_id ?? $attempt->merchant_reference;

            // 1. Idempotency Check: Already processed with this exact reference
            if ($ref && $lockedSub->last_payment_reference === $ref) {
                Log::info("Idempotent renewal skipped: transaction {$ref} already applied to subscription {$lockedSub->id}");

                return false;
            }

            // Also check if payment already completed and period matches/exceeds
            if ($payment->status === 'completed' && $lockedSub->last_payment_reference === $attempt->merchant_reference) {
                Log::info("Idempotent renewal skipped: payment {$payment->id} already marked completed");

                return false;
            }

            // Calculate new period end
            $baseDate = ($lockedSub->current_period_end && $lockedSub->current_period_end->isFuture())
                ? $lockedSub->current_period_end
                : now();

            $newExpiry = $payment->period_end
                ? Carbon::parse($payment->period_end)
                : (clone $baseDate)->addMonth();

            // Guard: If subscription was already extended to this date or beyond, do not re-add
            if ($lockedSub->current_period_end && $lockedSub->current_period_end->gte($newExpiry)) {
                $lockedSub->update([
                    'last_payment_reference' => $ref,
                    'status' => SubscriptionStatus::Active,
                    'grace_ends_at' => null,
                    'cancel_at_period_end' => false,
                    'renewal_state' => 'active',
                ]);
                $this->syncVendor($lockedSub);

                return false;
            }

            $planId = $payment->subscription_plan_id ?? $lockedSub->subscription_plan_id;
            $oldStatus = $lockedSub->status;

            $lockedSub->update([
                'subscription_plan_id' => $planId,
                'price' => $payment->amount,
                'currency' => $payment->currency ?: $lockedSub->currency,
                'status' => SubscriptionStatus::Active,
                'current_period_end' => $newExpiry,
                'grace_ends_at' => null,
                'cancelled_at' => null,
                'cancel_at_period_end' => false,
                'renewal_state' => 'active',
                'last_payment_reference' => $ref,
            ]);

            $payment->update([
                'subscription_id' => $lockedSub->id,
                'status' => 'completed',
            ]);

            $lockedSub->recordLifecycleEvent(
                event: 'renewed',
                toStatus: SubscriptionStatus::Active,
                fromStatus: $oldStatus,
                reason: "Renewed via payment {$ref}. New expiry: {$newExpiry->toDateString()}",
                actor: $actor,
                metadata: ['amount' => (float) $payment->amount, 'currency' => $payment->currency, 'reference' => $ref, 'new_expiry' => $newExpiry->toIso8601String()]
            );

            $this->syncVendor($lockedSub);

            Log::info("Subscription {$lockedSub->id} renewed via payment {$ref}. New expiry: {$newExpiry->toDateTimeString()}");

            return true;
        });
    }

    /**
     * Programmatically renew a subscription with a payment reference and optional amount.
     */
    public function renewSubscription(
        Subscription $subscription,
        string $paymentReference,
        ?float $amount = null,
        ?string $currency = null,
        ?User $actor = null
    ): Subscription {
        return DB::transaction(function () use ($subscription, $paymentReference, $amount, $currency, $actor): Subscription {
            $lockedSub = Subscription::withoutTenantQuery()->where('id', $subscription->id)->lockForUpdate()->firstOrFail();

            // Idempotency check: Already processed with this exact reference
            if ($lockedSub->last_payment_reference === $paymentReference) {
                Log::info("Idempotent renewal skipped: reference {$paymentReference} already applied to subscription {$lockedSub->id}");

                return $lockedSub;
            }

            $baseDate = ($lockedSub->current_period_end && $lockedSub->current_period_end->isFuture())
                ? $lockedSub->current_period_end
                : now();

            $newExpiry = (clone $baseDate)->addMonth();
            $oldStatus = $lockedSub->status;
            $effectivePrice = $amount ?? $lockedSub->effective_price;
            $effectiveCurrency = $currency ?? $lockedSub->currency;

            $lockedSub->update([
                'status' => SubscriptionStatus::Active,
                'price' => $effectivePrice,
                'currency' => $effectiveCurrency,
                'current_period_end' => $newExpiry,
                'grace_ends_at' => null,
                'cancelled_at' => null,
                'cancel_at_period_end' => false,
                'renewal_state' => 'active',
                'last_payment_reference' => $paymentReference,
            ]);

            $lockedSub->recordLifecycleEvent(
                event: 'renewed',
                toStatus: SubscriptionStatus::Active,
                fromStatus: $oldStatus,
                reason: "Renewed via payment reference {$paymentReference}. New expiry: {$newExpiry->toDateString()}",
                actor: $actor,
                metadata: [
                    'amount' => $effectivePrice,
                    'currency' => $effectiveCurrency,
                    'reference' => $paymentReference,
                    'new_expiry' => $newExpiry->toIso8601String(),
                ]
            );

            $this->syncVendor($lockedSub);

            Log::info("Subscription {$lockedSub->id} renewed via reference {$paymentReference}. New expiry: {$newExpiry->toDateTimeString()}");

            return $lockedSub;
        });
    }

    /**
     * Cancel subscription alias. Access remains valid until current_period_end unless immediately requested.
     */
    public function cancelSubscription(
        Subscription $subscription,
        bool $immediately = false,
        ?string $reason = null,
        ?User $actor = null
    ): void {
        $this->cancel($subscription, $immediately, $reason, $actor);
    }

    /**
     * Cancel subscription. Access remains valid until current_period_end unless immediately requested.
     */
    public function cancel(
        Subscription $subscription,
        bool $immediately = false,
        ?string $reason = null,
        ?User $actor = null
    ): void {
        DB::transaction(function () use ($subscription, $immediately, $reason, $actor) {
            $lockedSub = Subscription::withoutTenantQuery()->where('id', $subscription->id)->lockForUpdate()->first();
            if (! $lockedSub) {
                return;
            }

            $now = now();
            $oldStatus = $lockedSub->status;

            if ($immediately) {
                $targetStatus = SubscriptionStatus::Cancelled;
                if (! $lockedSub->status->canTransitionTo($targetStatus)) {
                    throw new InvalidSubscriptionTransitionException($lockedSub->status, $targetStatus);
                }

                $lockedSub->update([
                    'status' => $targetStatus,
                    'cancelled_at' => $now,
                    'cancel_at_period_end' => false,
                    'cancellation_reason' => $reason,
                    'current_period_end' => $now,
                    'grace_ends_at' => null,
                    'auto_renew' => false,
                    'renewal_state' => 'cancelled',
                ]);

                $lockedSub->recordLifecycleEvent(
                    event: 'cancelled_immediately',
                    toStatus: $targetStatus,
                    fromStatus: $oldStatus,
                    reason: $reason ?: 'Immediate cancellation requested',
                    actor: $actor
                );
            } else {
                // Period-end cancellation: access remains valid until current_period_end
                $targetStatus = SubscriptionStatus::CancelAtPeriodEnd;
                if (! $lockedSub->status->canTransitionTo($targetStatus)) {
                    // Fall back to Cancelled if CancelAtPeriodEnd forbidden
                    $targetStatus = SubscriptionStatus::Cancelled;
                }

                $lockedSub->update([
                    'status' => $targetStatus,
                    'cancelled_at' => $now,
                    'cancel_at_period_end' => true,
                    'cancellation_reason' => $reason,
                    'auto_renew' => false,
                    'renewal_state' => 'cancelled',
                ]);

                $lockedSub->recordLifecycleEvent(
                    event: 'cancel_at_period_end_scheduled',
                    toStatus: $targetStatus,
                    fromStatus: $oldStatus,
                    reason: $reason ?: 'Cancellation scheduled at end of billing period',
                    actor: $actor,
                    metadata: ['access_ends_at' => $lockedSub->current_period_end?->toIso8601String()]
                );
            }

            $this->syncVendor($lockedSub);

            Log::info("Subscription {$lockedSub->id} cancelled (Immediately: ".($immediately ? 'yes' : 'no').')');
        });
    }

    /**
     * Resume auto-renewal on a subscription previously marked cancel_at_period_end.
     */
    public function resume(Subscription $subscription, ?User $actor = null): void
    {
        DB::transaction(function () use ($subscription, $actor) {
            $lockedSub = Subscription::withoutTenantQuery()->where('id', $subscription->id)->lockForUpdate()->first();
            if (! $lockedSub) {
                return;
            }

            if (! $lockedSub->status->canTransitionTo(SubscriptionStatus::Active)) {
                throw new InvalidSubscriptionTransitionException($lockedSub->status, SubscriptionStatus::Active);
            }

            $oldStatus = $lockedSub->status;

            $lockedSub->update([
                'status' => SubscriptionStatus::Active,
                'cancelled_at' => null,
                'cancel_at_period_end' => false,
                'cancellation_reason' => null,
                'auto_renew' => true,
                'renewal_state' => 'active',
            ]);

            $lockedSub->recordLifecycleEvent(
                event: 'resumed',
                toStatus: SubscriptionStatus::Active,
                fromStatus: $oldStatus,
                reason: 'Subscription auto-renewal resumed by tenant',
                actor: $actor
            );

            $this->syncVendor($lockedSub);

            Log::info("Subscription {$lockedSub->id} auto-renewal resumed.");
        });
    }

    /**
     * Expire subscription after billing period and grace period have ended.
     */
    public function expire(Subscription $subscription, ?string $reason = null, ?User $actor = null): void
    {
        DB::transaction(function () use ($subscription, $reason, $actor) {
            $lockedSub = Subscription::withoutTenantQuery()->where('id', $subscription->id)->lockForUpdate()->first();
            if (! $lockedSub) {
                return;
            }

            if (! $lockedSub->status->canTransitionTo(SubscriptionStatus::Expired)) {
                return;
            }

            $oldStatus = $lockedSub->status;

            $lockedSub->update([
                'status' => SubscriptionStatus::Expired,
                'grace_ends_at' => null,
                'cancel_at_period_end' => false,
                'renewal_state' => 'none',
            ]);

            $lockedSub->recordLifecycleEvent(
                event: 'expired',
                toStatus: SubscriptionStatus::Expired,
                fromStatus: $oldStatus,
                reason: $reason ?: 'Billing period and grace period elapsed without payment',
                actor: $actor
            );

            $this->syncVendor($lockedSub);

            Log::info("Subscription {$lockedSub->id} expired. Access revoked.");
        });
    }

    /**
     * Suspend subscription administratively (fraud, chargeback, policy violation).
     */
    public function suspend(Subscription $subscription, string $reason = '', ?User $actor = null): void
    {
        DB::transaction(function () use ($subscription, $reason, $actor) {
            $lockedSub = Subscription::withoutTenantQuery()->where('id', $subscription->id)->lockForUpdate()->first();
            if (! $lockedSub) {
                return;
            }

            if (! $lockedSub->status->canTransitionTo(SubscriptionStatus::Suspended)) {
                throw new InvalidSubscriptionTransitionException($lockedSub->status, SubscriptionStatus::Suspended);
            }

            $oldStatus = $lockedSub->status;
            $metadata = $lockedSub->metadata ?? [];
            $metadata['suspended_reason'] = $reason;
            $metadata['suspended_at'] = now()->toIso8601String();

            $lockedSub->update([
                'status' => SubscriptionStatus::Suspended,
                'renewal_state' => 'none',
                'metadata' => $metadata,
            ]);

            $lockedSub->recordLifecycleEvent(
                event: 'suspended',
                toStatus: SubscriptionStatus::Suspended,
                fromStatus: $oldStatus,
                reason: $reason ?: 'Administrative suspension applied',
                actor: $actor,
                metadata: ['reason' => $reason]
            );

            $this->syncVendor($lockedSub);

            Log::warning("Subscription {$lockedSub->id} suspended. Reason: {$reason}");
        });
    }

    /**
     * Reactivate an expired, cancelled, or suspended subscription upon verified payment.
     */
    public function reactivate(
        Subscription $subscription,
        SubscriptionPlan $plan,
        SubscriptionPayment $payment,
        PaymentAttempt $attempt,
        ?User $actor = null
    ): void {
        DB::transaction(function () use ($subscription, $plan, $payment, $attempt, $actor) {
            $lockedSub = Subscription::withoutTenantQuery()->where('id', $subscription->id)->lockForUpdate()->first();
            if (! $lockedSub) {
                return;
            }

            if (! $lockedSub->status->canTransitionTo(SubscriptionStatus::Active)) {
                throw new InvalidSubscriptionTransitionException($lockedSub->status, SubscriptionStatus::Active);
            }

            $now = now();
            $periodEnd = $payment->period_end ? Carbon::parse($payment->period_end) : (clone $now)->addMonth();
            $ref = $attempt->provider_transaction_id ?? $attempt->merchant_reference;
            $oldStatus = $lockedSub->status;

            $lockedSub->update([
                'subscription_plan_id' => $plan->id,
                'price' => $payment->amount,
                'currency' => $payment->currency ?: $lockedSub->currency,
                'status' => SubscriptionStatus::Active,
                'current_period_start' => $now,
                'current_period_end' => $periodEnd,
                'grace_ends_at' => null,
                'cancelled_at' => null,
                'cancel_at_period_end' => false,
                'renewal_state' => 'active',
                'auto_renew' => true,
                'last_payment_reference' => $ref,
            ]);

            $payment->update([
                'subscription_id' => $lockedSub->id,
                'status' => 'completed',
            ]);

            $lockedSub->recordLifecycleEvent(
                event: 'reactivated',
                toStatus: SubscriptionStatus::Active,
                fromStatus: $oldStatus,
                reason: "Reactivated via payment {$ref}",
                actor: $actor,
                metadata: ['amount' => (float) $payment->amount, 'currency' => $payment->currency, 'reference' => $ref],
                toPlanId: $plan->id
            );

            $this->syncVendor($lockedSub);

            Log::info("Subscription {$lockedSub->id} reactivated for plan {$plan->slug} until {$periodEnd->toDateTimeString()}");
        });
    }

    /**
     * Change plan tier safely (upgrading or downgrading).
     */
    public function changePlan(
        Subscription $subscription,
        SubscriptionPlan $newPlan,
        ?SubscriptionPlanPrice $newPrice = null,
        ?string $reason = null,
        ?User $actor = null
    ): void {
        DB::transaction(function () use ($subscription, $newPlan, $newPrice, $reason, $actor) {
            $lockedSub = Subscription::withoutTenantQuery()->where('id', $subscription->id)->lockForUpdate()->first();
            if (! $lockedSub) {
                return;
            }

            $oldPlanId = $lockedSub->subscription_plan_id;
            $priceObj = $newPrice ?? $newPlan->priceFor($lockedSub->billing_interval ?: 'monthly', $lockedSub->currency ?: 'AMD');
            $newPriceAmount = $priceObj ? (float) $priceObj->price : (float) $newPlan->price;

            $lockedSub->update([
                'subscription_plan_id' => $newPlan->id,
                'subscription_plan_price_id' => $priceObj?->id,
                'price' => $newPriceAmount,
                'currency' => $priceObj?->currency ?: $newPlan->currency ?: $lockedSub->currency,
            ]);

            $lockedSub->recordLifecycleEvent(
                event: 'plan_changed',
                toStatus: $lockedSub->status,
                fromStatus: $lockedSub->status,
                reason: $reason ?: "Plan changed to {$newPlan->slug}",
                actor: $actor,
                metadata: ['new_price' => $newPriceAmount],
                fromPlanId: $oldPlanId,
                toPlanId: $newPlan->id
            );

            $this->syncVendor($lockedSub);

            Log::info("Subscription {$lockedSub->id} plan changed to {$newPlan->slug}");
        });
    }

    /**
     * Transition a subscription into grace period.
     */
    public function enterGracePeriod(Subscription $subscription, int $graceDays = 3, ?User $actor = null): void
    {
        DB::transaction(function () use ($subscription, $graceDays, $actor) {
            $lockedSub = Subscription::withoutTenantQuery()->where('id', $subscription->id)->lockForUpdate()->first();
            if (! $lockedSub) {
                return;
            }

            $oldStatus = $lockedSub->status;
            $target = SubscriptionStatus::Grace;

            if (! $lockedSub->status->canTransitionTo($target)) {
                return;
            }

            $graceEnd = now()->addDays($graceDays);

            $lockedSub->update([
                'status' => $target,
                'grace_ends_at' => $graceEnd,
            ]);

            $lockedSub->recordLifecycleEvent(
                event: 'grace_entered',
                toStatus: $target,
                fromStatus: $oldStatus,
                reason: "Entered {$graceDays}-day grace period until {$graceEnd->toDateString()}",
                actor: $actor,
                metadata: ['grace_ends_at' => $graceEnd->toIso8601String()]
            );

            $this->syncVendor($lockedSub);

            Log::info("Subscription {$lockedSub->id} entered grace period until {$graceEnd->toDateTimeString()}");
        });
    }

    /**
     * Handle payment failure: NEVER activate or extend the subscription.
     */
    public function handlePaymentFailure(
        Subscription $subscription,
        ?SubscriptionPayment $payment = null,
        ?PaymentAttempt $attempt = null,
        int $graceDays = 3,
        ?User $actor = null
    ): void {
        DB::transaction(function () use ($subscription, $payment, $attempt, $graceDays, $actor) {
            if ($payment && $payment->status !== 'failed') {
                $payment->update(['status' => 'failed']);
            }
            if ($attempt && $attempt->status !== PaymentStatus::Failed) {
                $attempt->update(['status' => PaymentStatus::Failed]);
            }

            $lockedSub = Subscription::withoutTenantQuery()->where('id', $subscription->id)->lockForUpdate()->first();
            if (! $lockedSub) {
                return;
            }

            $lockedSub->renewal_state = 'failed';
            $oldStatus = $lockedSub->status;

            $now = now();
            $periodEnded = ($lockedSub->current_period_end && $lockedSub->current_period_end->isPast())
                || ($lockedSub->status === SubscriptionStatus::Trialing && $lockedSub->trial_ends_at && $lockedSub->trial_ends_at->isPast());

            if ($periodEnded) {
                if ($lockedSub->grace_ends_at && $lockedSub->grace_ends_at->isPast()) {
                    $lockedSub->status = SubscriptionStatus::PastDue;
                } elseif (! $lockedSub->grace_ends_at && $graceDays > 0) {
                    $lockedSub->status = SubscriptionStatus::Grace;
                    $lockedSub->grace_ends_at = (clone $now)->addDays($graceDays);
                } elseif ($lockedSub->isInGracePeriod()) {
                    $lockedSub->status = SubscriptionStatus::Grace;
                } else {
                    $lockedSub->status = SubscriptionStatus::PastDue;
                }
            }

            $lockedSub->save();

            $lockedSub->recordLifecycleEvent(
                event: 'payment_failed',
                toStatus: $lockedSub->status,
                fromStatus: $oldStatus,
                reason: 'Renewal payment failed or was declined',
                actor: $actor,
                metadata: ['attempt_id' => $attempt?->id, 'payment_id' => $payment?->id]
            );

            $this->syncVendor($lockedSub);

            Log::warning("Subscription {$lockedSub->id} payment failure handled. Status: {$lockedSub->status->value}, RenewalState: failed");
        });
    }

    /**
     * Handle payment refund: revokes access and cancels the subscription.
     */
    public function handleRefund(
        Subscription $subscription,
        ?SubscriptionPayment $payment = null,
        ?PaymentAttempt $attempt = null,
        ?User $actor = null
    ): void {
        DB::transaction(function () use ($subscription, $payment, $attempt, $actor) {
            if ($payment) {
                $payment->update(['status' => 'refunded']);
            }
            if ($attempt) {
                $attempt->update(['status' => PaymentStatus::Refunded]);
            }

            $lockedSub = Subscription::withoutTenantQuery()->where('id', $subscription->id)->lockForUpdate()->first();
            if (! $lockedSub) {
                return;
            }

            $oldStatus = $lockedSub->status;

            $lockedSub->update([
                'status' => SubscriptionStatus::Cancelled,
                'renewal_state' => 'failed',
                'current_period_end' => now(),
                'grace_ends_at' => null,
                'cancel_at_period_end' => false,
                'cancelled_at' => now(),
                'cancellation_reason' => 'Payment refunded / charged back',
            ]);

            $lockedSub->recordLifecycleEvent(
                event: 'refunded_cancelled',
                toStatus: SubscriptionStatus::Cancelled,
                fromStatus: $oldStatus,
                reason: 'Access revoked due to payment refund or chargeback',
                actor: $actor
            );

            $this->syncVendor($lockedSub);

            Log::warning("Subscription {$lockedSub->id} access revoked due to payment refund.");
        });
    }

    /**
     * Actively reconcile all expired subscriptions and trials across the platform.
     * Enforces subscription boundaries and updates vendor active status.
     *
     * @return array{checked: int, transitioned: int}
     */
    public function reconcileExpiredSubscriptions(int $graceDays = 3): array
    {
        $now = now();
        $candidates = Subscription::withoutTenantQuery()
            ->whereIn('status', [
                SubscriptionStatus::Trialing,
                SubscriptionStatus::Active,
                SubscriptionStatus::CancelAtPeriodEnd,
                SubscriptionStatus::Grace,
                SubscriptionStatus::GracePeriod,
                SubscriptionStatus::PastDue,
            ])
            ->where(function ($q) use ($now) {
                $q->where(function ($subQ) use ($now) {
                    $subQ->where('status', SubscriptionStatus::Trialing)
                        ->whereNotNull('trial_ends_at')
                        ->where('trial_ends_at', '<=', $now);
                })->orWhere(function ($subQ) use ($now) {
                    $subQ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::CancelAtPeriodEnd])
                        ->whereNotNull('current_period_end')
                        ->where('current_period_end', '<=', $now);
                })->orWhere(function ($subQ) use ($now) {
                    $subQ->whereIn('status', [SubscriptionStatus::Grace, SubscriptionStatus::GracePeriod])
                        ->whereNotNull('grace_ends_at')
                        ->where('grace_ends_at', '<=', $now);
                });
            })
            ->get();

        $transitionedCount = 0;

        foreach ($candidates as $sub) {
            DB::transaction(function () use ($sub, $graceDays, &$transitionedCount) {
                $lockedSub = Subscription::withoutTenantQuery()->where('id', $sub->id)->lockForUpdate()->first();
                if (! $lockedSub) {
                    return;
                }

                if ($lockedSub->status === SubscriptionStatus::Trialing && $lockedSub->trial_ends_at && $lockedSub->trial_ends_at->isPast()) {
                    $this->expire($lockedSub, 'Trial period elapsed without conversion');
                    $transitionedCount++;
                } elseif ($lockedSub->status === SubscriptionStatus::CancelAtPeriodEnd && $lockedSub->current_period_end && $lockedSub->current_period_end->isPast()) {
                    $this->expire($lockedSub, 'Scheduled period end reached following cancellation');
                    $transitionedCount++;
                } elseif (in_array($lockedSub->status, [SubscriptionStatus::Grace, SubscriptionStatus::GracePeriod], true) && $lockedSub->grace_ends_at && $lockedSub->grace_ends_at->isPast()) {
                    $this->expire($lockedSub, 'Grace period elapsed without renewal payment');
                    $transitionedCount++;
                } elseif ($lockedSub->status === SubscriptionStatus::Active && $lockedSub->current_period_end && $lockedSub->current_period_end->isPast()) {
                    if ($graceDays > 0) {
                        $this->enterGracePeriod($lockedSub, $graceDays);
                    } else {
                        $this->expire($lockedSub, 'Active billing period elapsed without renewal payment');
                    }
                    $transitionedCount++;
                }
            });
        }

        return [
            'checked' => $candidates->count(),
            'transitioned' => $transitionedCount,
        ];
    }

    /**
     * Verify that incoming payment amount and currency match plan requirements.
     */
    public function verifyPaymentMatchesPlan(
        SubscriptionPlan $plan,
        float $amount,
        string $currency,
        int $monthsCount = 1
    ): bool {
        $expectedCurrency = $plan->currency ?? 'AMD';
        if (strtoupper($currency) !== strtoupper($expectedCurrency)) {
            return false;
        }

        $basePrice = (float) $plan->resolvePrice('monthly', $currency);
        $expectedTotal = $basePrice * $monthsCount;

        if ($monthsCount >= 12) {
            $expectedTotal *= 0.80; // 20% discount
        } elseif ($monthsCount >= 6) {
            $expectedTotal *= 0.90; // 10% discount
        }

        return abs($amount - $expectedTotal) <= 0.01;
    }

    /**
     * Synchronize authoritative subscription state down to vendor table.
     */
    public function syncVendor(Subscription $subscription): void
    {
        $vendor = $subscription->vendor ?? Vendor::withoutGlobalScopes()->find($subscription->vendor_id);
        if (! $vendor) {
            return;
        }

        $oldStatus = $vendor->subscription_status;
        $newStatus = $subscription->status->value;

        $vendor->subscription_plan_id = $subscription->subscription_plan_id;
        $vendor->subscription_plan = $subscription->plan?->slug ?? 'pro';
        $vendor->subscription_status = $subscription->status->value;
        $vendor->trial_ends_at = $subscription->trial_ends_at;
        $vendor->current_period_start = $subscription->current_period_start;
        $vendor->subscription_expires_at = $subscription->current_period_end;
        $vendor->grace_ends_at = $subscription->grace_ends_at;
        $vendor->cancelled_at = $subscription->cancelled_at;

        $isEmailVerified = $vendor->email_verified_at !== null
            || $vendor->users()->whereNotNull('email_verified_at')->exists();

        if ($vendor->is_active || $isEmailVerified) {
            $vendor->is_active = $subscription->allowsAccess();
        }

        $vendor->saveQuietly();

        if ($oldStatus !== $newStatus) {
            app(SecurityAuditService::class)->logSubscriptionChange(
                vendor: $vendor,
                oldStatus: (string) ($oldStatus ?? 'none'),
                newStatus: $newStatus,
                planSlug: $subscription->plan?->slug,
                actor: auth()->user()
            );
        }
    }
}
