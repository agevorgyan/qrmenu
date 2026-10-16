<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\PaymentAttempt;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
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

        $statusStr = $vendor->subscription_status ?? 'trialing';
        $status = SubscriptionStatus::tryFrom($statusStr) ?? SubscriptionStatus::Trialing;

        return Subscription::create([
            'vendor_id' => $vendor->id,
            'subscription_plan_id' => $planId,
            'status' => $status,
            'billing_interval' => 'monthly',
            'trial_starts_at' => $vendor->created_at ?? now(),
            'trial_ends_at' => $vendor->trial_ends_at,
            'current_period_start' => $vendor->current_period_start ?? $vendor->created_at ?? now(),
            'current_period_end' => $vendor->subscription_expires_at,
            'grace_ends_at' => $vendor->grace_ends_at,
            'cancelled_at' => $vendor->cancelled_at,
            'renewal_state' => 'active',
            'auto_renew' => true,
        ]);
    }

    /**
     * Start a new trial subscription for a vendor.
     */
    public function startTrial(Vendor $vendor, SubscriptionPlan $plan, ?int $trialDays = null): Subscription
    {
        return DB::transaction(function () use ($vendor, $plan, $trialDays) {
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
                        $existing->update(['subscription_plan_id' => $plan->id]);
                        $this->syncVendor($existing);
                    }

                    Log::info("Vendor {$vendor->id} trial already active until {$existing->trial_ends_at->toDateTimeString()}. Preserving existing trial.");

                    return $existing;
                }

                $this->expire($existing);
                Log::warning("Vendor {$vendor->id} trial has already elapsed. Re-trialing not permitted.");

                return $existing;
            }

            $trialStartsAt = $existing?->trial_starts_at ?? $now;
            $trialEnd = (clone $now)->addDays($days);

            $subscription = Subscription::withoutTenantQuery()->updateOrCreate(
                ['vendor_id' => $vendor->id],
                [
                    'subscription_plan_id' => $plan->id,
                    'status' => SubscriptionStatus::Trialing,
                    'billing_interval' => 'monthly',
                    'trial_starts_at' => $trialStartsAt,
                    'trial_ends_at' => $trialEnd,
                    'current_period_start' => $now,
                    'current_period_end' => $trialEnd,
                    'grace_ends_at' => null,
                    'cancelled_at' => null,
                    'renewal_state' => 'active',
                    'auto_renew' => true,
                ]
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
        ?PaymentAttempt $attempt = null
    ): void {
        DB::transaction(function () use ($subscription, $plan, $payment, $attempt) {
            $now = now();
            $periodStart = $payment->period_start ? Carbon::parse($payment->period_start) : $now;
            $periodEnd = $payment->period_end ? Carbon::parse($payment->period_end) : (clone $periodStart)->addMonth();
            $ref = $attempt?->provider_transaction_id ?? $attempt?->merchant_reference ?? (string) $payment->invoice_number;

            $subscription->update([
                'subscription_plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'grace_ends_at' => null,
                'cancelled_at' => null,
                'renewal_state' => 'active',
                'auto_renew' => true,
                'last_payment_reference' => $ref,
            ]);

            $payment->update([
                'subscription_id' => $subscription->id,
                'status' => 'completed',
            ]);

            $this->syncVendor($subscription);

            Log::info("Subscription {$subscription->id} activated via payment {$ref} until {$periodEnd->toDateTimeString()}");
        });
    }

    /**
     * Idempotently renew an existing subscription upon successful renewal payment.
     *
     * Returns false if this transaction was already processed (idempotent skip), true if renewed.
     */
    public function renew(
        Subscription $subscription,
        SubscriptionPayment $payment,
        PaymentAttempt $attempt,
        ?string $idempotencyKey = null
    ): bool {
        return DB::transaction(function () use ($subscription, $payment, $attempt): bool {
            $ref = $attempt->provider_transaction_id ?? $attempt->merchant_reference;

            // 1. Idempotency Check: Already processed with this exact reference
            if ($ref && $subscription->last_payment_reference === $ref) {
                Log::info("Idempotent renewal skipped: transaction {$ref} already applied to subscription {$subscription->id}");

                return false;
            }

            // Also check if payment already completed and period matches/exceeds
            if ($payment->status === 'completed' && $subscription->last_payment_reference === $attempt->merchant_reference) {
                Log::info("Idempotent renewal skipped: payment {$payment->id} already marked completed");

                return false;
            }

            // Calculate new period end
            $baseDate = ($subscription->current_period_end && $subscription->current_period_end->isFuture())
                ? $subscription->current_period_end
                : now();

            $newExpiry = $payment->period_end
                ? Carbon::parse($payment->period_end)
                : (clone $baseDate)->addMonth();

            // Guard: If subscription was already extended to this date or beyond, do not re-add
            if ($subscription->current_period_end && $subscription->current_period_end->gte($newExpiry)) {
                $subscription->update([
                    'last_payment_reference' => $ref,
                    'status' => SubscriptionStatus::Active,
                    'grace_ends_at' => null,
                    'renewal_state' => 'active',
                ]);
                $this->syncVendor($subscription);

                return false;
            }

            $planId = $payment->subscription_plan_id ?? $subscription->subscription_plan_id;

            $subscription->update([
                'subscription_plan_id' => $planId,
                'status' => SubscriptionStatus::Active,
                'current_period_end' => $newExpiry,
                'grace_ends_at' => null,
                'cancelled_at' => null,
                'renewal_state' => 'active',
                'last_payment_reference' => $ref,
            ]);

            $payment->update([
                'subscription_id' => $subscription->id,
                'status' => 'completed',
            ]);

            $this->syncVendor($subscription);

            Log::info("Subscription {$subscription->id} renewed via payment {$ref}. New expiry: {$newExpiry->toDateTimeString()}");

            return true;
        });
    }

    /**
     * Handle payment failure: NEVER activate or extend the subscription.
     */
    public function handlePaymentFailure(
        Subscription $subscription,
        ?SubscriptionPayment $payment = null,
        ?PaymentAttempt $attempt = null,
        int $graceDays = 3
    ): void {
        DB::transaction(function () use ($subscription, $payment, $attempt, $graceDays) {
            // Update payment and attempt records to failed
            if ($payment && $payment->status !== 'failed') {
                $payment->update(['status' => 'failed']);
            }
            if ($attempt && $attempt->status !== PaymentStatus::Failed) {
                $attempt->update(['status' => PaymentStatus::Failed]);
            }

            // Mark renewal state as failed
            $subscription->renewal_state = 'failed';

            $now = now();
            $periodEnded = ($subscription->current_period_end && $subscription->current_period_end->isPast())
                || ($subscription->status === SubscriptionStatus::Trialing && $subscription->trial_ends_at && $subscription->trial_ends_at->isPast());

            if ($periodEnded) {
                if ($subscription->grace_ends_at && $subscription->grace_ends_at->isPast()) {
                    $subscription->status = SubscriptionStatus::PastDue;
                } elseif (! $subscription->grace_ends_at && $graceDays > 0) {
                    $subscription->status = SubscriptionStatus::Grace;
                    $subscription->grace_ends_at = (clone $now)->addDays($graceDays);
                } elseif ($subscription->isInGracePeriod()) {
                    $subscription->status = SubscriptionStatus::Grace;
                } else {
                    $subscription->status = SubscriptionStatus::PastDue;
                }
            } else {
                // Period is still valid in the future; keep status but record failed renewal
                if ($subscription->status === SubscriptionStatus::Trialing) {
                    // Trial continues until trial_ends_at
                } elseif ($subscription->status === SubscriptionStatus::Active) {
                    // Active continues until current_period_end
                }
            }

            $subscription->save();
            $this->syncVendor($subscription);

            Log::warning("Subscription {$subscription->id} payment failure handled. Status: {$subscription->status->value}, RenewalState: failed");
        });
    }

    /**
     * Transition a subscription into grace period.
     */
    public function enterGracePeriod(Subscription $subscription, int $graceDays = 3): void
    {
        DB::transaction(function () use ($subscription, $graceDays) {
            $subscription->update([
                'status' => SubscriptionStatus::Grace,
                'grace_ends_at' => now()->addDays($graceDays),
            ]);

            $this->syncVendor($subscription);

            Log::info("Subscription {$subscription->id} entered grace period until {$subscription->grace_ends_at->toDateTimeString()}");
        });
    }

    /**
     * Cancel subscription. Access remains valid until current_period_end unless immediately requested.
     */
    public function cancel(Subscription $subscription, bool $immediately = false): void
    {
        DB::transaction(function () use ($subscription, $immediately) {
            $now = now();
            $updates = [
                'status' => SubscriptionStatus::Cancelled,
                'cancelled_at' => $now,
                'auto_renew' => false,
                'renewal_state' => 'cancelled',
            ];

            if ($immediately) {
                $updates['current_period_end'] = $now;
                $updates['grace_ends_at'] = null;
            }

            $subscription->update($updates);
            $this->syncVendor($subscription);

            Log::info("Subscription {$subscription->id} cancelled (Immediately: ".($immediately ? 'yes' : 'no').')');
        });
    }

    /**
     * Expire subscription after billing period and grace period have ended.
     */
    public function expire(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $subscription->update([
                'status' => SubscriptionStatus::Expired,
                'grace_ends_at' => null,
                'renewal_state' => 'none',
            ]);

            $this->syncVendor($subscription);

            Log::info("Subscription {$subscription->id} expired. Access revoked.");
        });
    }

    /**
     * Suspend subscription administratively (fraud, chargeback, policy violation).
     */
    public function suspend(Subscription $subscription, string $reason = ''): void
    {
        DB::transaction(function () use ($subscription, $reason) {
            $metadata = $subscription->metadata ?? [];
            $metadata['suspended_reason'] = $reason;
            $metadata['suspended_at'] = now()->toIso8601String();

            $subscription->update([
                'status' => SubscriptionStatus::Suspended,
                'renewal_state' => 'none',
                'metadata' => $metadata,
            ]);

            $this->syncVendor($subscription);

            Log::warning("Subscription {$subscription->id} suspended. Reason: {$reason}");
        });
    }

    /**
     * Reactivate an expired, cancelled, or suspended subscription upon verified payment.
     */
    public function reactivate(
        Subscription $subscription,
        SubscriptionPlan $plan,
        SubscriptionPayment $payment,
        PaymentAttempt $attempt
    ): void {
        DB::transaction(function () use ($subscription, $plan, $payment, $attempt) {
            $now = now();
            $periodEnd = $payment->period_end ? Carbon::parse($payment->period_end) : (clone $now)->addMonth();
            $ref = $attempt->provider_transaction_id ?? $attempt->merchant_reference;

            $subscription->update([
                'subscription_plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'current_period_start' => $now,
                'current_period_end' => $periodEnd,
                'grace_ends_at' => null,
                'cancelled_at' => null,
                'renewal_state' => 'active',
                'auto_renew' => true,
                'last_payment_reference' => $ref,
            ]);

            $payment->update([
                'subscription_id' => $subscription->id,
                'status' => 'completed',
            ]);

            $this->syncVendor($subscription);

            Log::info("Subscription {$subscription->id} reactivated for plan {$plan->slug} until {$periodEnd->toDateTimeString()}");
        });
    }

    /**
     * Handle payment refund: revokes access and cancels the subscription.
     */
    public function handleRefund(
        Subscription $subscription,
        ?SubscriptionPayment $payment = null,
        ?PaymentAttempt $attempt = null
    ): void {
        DB::transaction(function () use ($subscription, $payment, $attempt) {
            if ($payment) {
                $payment->update(['status' => 'refunded']);
            }
            if ($attempt) {
                $attempt->update(['status' => PaymentStatus::Refunded]);
            }

            $subscription->update([
                'status' => SubscriptionStatus::Cancelled,
                'renewal_state' => 'failed',
                'current_period_end' => now(),
                'grace_ends_at' => null,
                'cancelled_at' => now(),
            ]);

            $this->syncVendor($subscription);

            Log::warning("Subscription {$subscription->id} access revoked due to payment refund.");
        });
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

        $basePrice = (float) $plan->price;
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
        if ($vendor->is_active || $vendor->email_verified_at !== null) {
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
