<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\PaymentAttempt;
use App\Models\Subscription;
use App\Models\Vendor;
use App\Services\AuditSanitizer;
use App\Services\SecurityAuditService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentReconciliationService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager,
        protected PaymentVerificationService $verificationService,
        protected SubscriptionService $subscriptionService,
        protected SecurityAuditService $auditService,
        protected AuditSanitizer $sanitizer
    ) {}

    /**
     * Reconcile pending payment attempts with payment providers.
     * Idempotent, auditable, and never grants access when records conflict.
     *
     * @return array{
     *     total_checked: int,
     *     resolved_paid: int,
     *     resolved_failed: int,
     *     discrepancies: int,
     *     still_pending: int,
     *     dry_run: bool,
     *     details: list<array<string, mixed>>
     * }
     */
    public function reconcilePendingAttempts(
        ?int $olderThanMinutes = 15,
        bool $dryRun = false,
        ?string $gatewayFilter = null,
        ?int $vendorId = null
    ): array {
        $cutoff = $olderThanMinutes ? now()->subMinutes($olderThanMinutes) : now();

        $query = PaymentAttempt::whereIn('status', [PaymentStatus::Pending, PaymentStatus::Created])
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id', 'asc');

        if ($gatewayFilter) {
            $query->where('gateway', $gatewayFilter);
        }

        if ($vendorId) {
            $query->where('vendor_id', $vendorId);
        }

        $attempts = $query->get();

        $summary = [
            'total_checked' => 0,
            'resolved_paid' => 0,
            'resolved_failed' => 0,
            'discrepancies' => 0,
            'still_pending' => 0,
            'dry_run' => $dryRun,
            'details' => [],
        ];

        foreach ($attempts as $attempt) {
            $summary['total_checked']++;
            $result = $this->reconcileAttempt($attempt, $dryRun);

            match ($result['action']) {
                'marked_paid', 'would_mark_paid' => $summary['resolved_paid']++,
                'marked_failed', 'marked_expired', 'would_mark_failed', 'would_expire' => $summary['resolved_failed']++,
                'discrepancy_flagged', 'discrepancy_recorded' => $summary['discrepancies']++,
                default => $summary['still_pending']++,
            };

            $summary['details'][] = $result;
        }

        Log::info('Payment reconciliation completed', [
            'total_checked' => $summary['total_checked'],
            'resolved_paid' => $summary['resolved_paid'],
            'resolved_failed' => $summary['resolved_failed'],
            'discrepancies' => $summary['discrepancies'],
            'dry_run' => $dryRun,
        ]);

        return $summary;
    }

    /**
     * Reconcile a single PaymentAttempt against the provider.
     *
     * @return array<string, mixed>
     */
    public function reconcileAttempt(PaymentAttempt $attempt, bool $dryRun = false): array
    {
        if (! $this->gatewayManager->hasGateway($attempt->gateway)) {
            return [
                'attempt_id' => $attempt->id,
                'merchant_reference' => $attempt->merchant_reference,
                'action' => 'skipped_unknown_gateway',
                'gateway' => $attempt->gateway,
            ];
        }

        $gateway = $this->gatewayManager->gateway($attempt->gateway);
        $verification = $gateway->queryTransactionStatus($attempt);

        // Case 1: Provider confirms transaction as PAID
        if ($verification->status === PaymentStatus::Paid) {
            // Check for amount discrepancy
            if ($verification->amount !== null && abs((float) $verification->amount - (float) $attempt->amount) > 0.01) {
                return $this->handleDiscrepancy(
                    $attempt,
                    "Amount mismatch during reconciliation: expected {$attempt->amount}, provider reported {$verification->amount}",
                    $verification->rawPayload,
                    $dryRun
                );
            }

            // Check for currency discrepancy
            if ($verification->currency !== null && strtoupper($verification->currency) !== strtoupper($attempt->currency)) {
                return $this->handleDiscrepancy(
                    $attempt,
                    "Currency mismatch during reconciliation: expected {$attempt->currency}, provider reported {$verification->currency}",
                    $verification->rawPayload,
                    $dryRun
                );
            }

            if ($dryRun) {
                return [
                    'attempt_id' => $attempt->id,
                    'merchant_reference' => $attempt->merchant_reference,
                    'action' => 'would_mark_paid',
                    'provider_transaction_id' => $verification->providerTransactionId,
                    'amount' => $attempt->amount,
                ];
            }

            // Live execution: finalize attempt inside transaction
            $this->verificationService->applyAuthoritativeResult($attempt, $verification, array_merge(
                $verification->rawPayload,
                ['reconciled_at' => now()->toIso8601String()]
            ));

            return [
                'attempt_id' => $attempt->id,
                'merchant_reference' => $attempt->merchant_reference,
                'action' => 'marked_paid',
                'provider_transaction_id' => $verification->providerTransactionId,
                'amount' => $attempt->amount,
            ];
        }

        // Case 2: Provider confirms transaction as FAILED or CANCELLED
        if ($verification->status === PaymentStatus::Failed || $verification->status === PaymentStatus::Cancelled) {
            if ($dryRun) {
                return [
                    'attempt_id' => $attempt->id,
                    'merchant_reference' => $attempt->merchant_reference,
                    'action' => 'would_mark_failed',
                    'reason' => $verification->errorMessage,
                ];
            }

            DB::transaction(function () use ($attempt, $verification) {
                $lockedAttempt = PaymentAttempt::where('id', $attempt->id)->lockForUpdate()->first();
                if ($lockedAttempt && $lockedAttempt->status !== PaymentStatus::Paid) {
                    $lockedAttempt->update([
                        'status' => PaymentStatus::Failed,
                        'response_payload' => array_merge(
                            $verification->rawPayload,
                            ['reconciliation_failed' => $verification->errorMessage]
                        ),
                    ]);

                    if ($lockedAttempt->subscription_id) {
                        $vendor = Vendor::find($lockedAttempt->vendor_id);
                        if ($vendor) {
                            $sub = $vendor->subscription ?? $this->subscriptionService->getOrCreateForVendor($vendor);
                            $this->subscriptionService->handlePaymentFailure(
                                subscription: $sub,
                                payment: $lockedAttempt->subscriptionPayment,
                                attempt: $lockedAttempt
                            );
                        }
                    }
                }
            });

            return [
                'attempt_id' => $attempt->id,
                'merchant_reference' => $attempt->merchant_reference,
                'action' => 'marked_failed',
                'reason' => $verification->errorMessage,
            ];
        }

        // Case 3: Still Pending
        if ($attempt->isExpired() || ($attempt->expires_at && $attempt->expires_at->isPast())) {
            if ($dryRun) {
                return [
                    'attempt_id' => $attempt->id,
                    'merchant_reference' => $attempt->merchant_reference,
                    'action' => 'would_expire',
                    'expires_at' => $attempt->expires_at?->toIso8601String(),
                ];
            }

            DB::transaction(function () use ($attempt) {
                $lockedAttempt = PaymentAttempt::where('id', $attempt->id)->lockForUpdate()->first();
                if ($lockedAttempt && $lockedAttempt->status !== PaymentStatus::Paid) {
                    $lockedAttempt->update([
                        'status' => PaymentStatus::Expired,
                        'response_payload' => array_merge($lockedAttempt->response_payload ?? [], [
                            'expired_during_reconciliation' => true,
                            'reconciled_at' => now()->toIso8601String(),
                        ]),
                    ]);

                    if ($lockedAttempt->subscription_id && $lockedAttempt->subscriptionPayment) {
                        $lockedAttempt->subscriptionPayment->update(['status' => 'failed']);
                    }
                }
            });

            return [
                'attempt_id' => $attempt->id,
                'merchant_reference' => $attempt->merchant_reference,
                'action' => 'marked_expired',
                'expires_at' => $attempt->expires_at?->toIso8601String(),
            ];
        }

        return [
            'attempt_id' => $attempt->id,
            'merchant_reference' => $attempt->merchant_reference,
            'action' => 'still_pending',
            'expires_at' => $attempt->expires_at?->toIso8601String(),
        ];
    }

    /**
     * Handle security discrepancy during reconciliation.
     * Never grants access when provider and local records conflict.
     *
     * @param  array<string, mixed>  $rawPayload
     * @return array<string, mixed>
     */
    protected function handleDiscrepancy(
        PaymentAttempt $attempt,
        string $reason,
        array $rawPayload,
        bool $dryRun
    ): array {
        Log::warning("Payment reconciliation discrepancy: {$reason}", [
            'attempt_id' => $attempt->id,
            'reference' => $attempt->merchant_reference,
            'vendor_id' => $attempt->vendor_id,
        ]);

        if ($dryRun) {
            return [
                'attempt_id' => $attempt->id,
                'merchant_reference' => $attempt->merchant_reference,
                'action' => 'discrepancy_flagged',
                'reason' => $reason,
            ];
        }

        DB::transaction(function () use ($attempt, $reason, $rawPayload) {
            $lockedAttempt = PaymentAttempt::where('id', $attempt->id)->lockForUpdate()->first();
            if ($lockedAttempt) {
                $lockedAttempt->update([
                    'status' => PaymentStatus::Failed,
                    'response_payload' => array_merge(
                        $this->sanitizer->sanitize($rawPayload),
                        [
                            'discrepancy' => true,
                            'discrepancy_reason' => $reason,
                            'reconciled_at' => now()->toIso8601String(),
                        ]
                    ),
                ]);

                if ($lockedAttempt->subscriptionPayment) {
                    $lockedAttempt->subscriptionPayment->update(['status' => 'failed']);
                }

                $vendor = Vendor::find($lockedAttempt->vendor_id);
                if ($vendor) {
                    $sub = $vendor->subscription ?? $this->subscriptionService->getOrCreateForVendor($vendor);
                    $sub->recordLifecycleEvent(
                        event: 'reconciliation_discrepancy',
                        toStatus: $sub->status,
                        fromStatus: $sub->status,
                        reason: "Reconciliation discrepancy: {$reason}",
                        metadata: ['attempt_id' => $lockedAttempt->id, 'reference' => $lockedAttempt->merchant_reference]
                    );
                }
            }
        });

        return [
            'attempt_id' => $attempt->id,
            'merchant_reference' => $attempt->merchant_reference,
            'action' => 'discrepancy_recorded',
            'reason' => $reason,
        ];
    }

    /**
     * Identify and repair state discrepancies between Vendor cache and authoritative Subscription model.
     *
     * @return array{checked: int, synchronized: int, dry_run: bool}
     */
    public function reconcileSubscriptionDiscrepancies(?Vendor $vendor = null, bool $dryRun = false): array
    {
        $query = Vendor::query();
        if ($vendor) {
            $query->where('id', $vendor->id);
        }

        $vendors = $query->get();
        $checked = 0;
        $synchronized = 0;

        foreach ($vendors as $v) {
            $checked++;
            $sub = $v->subscription;
            if (! $sub) {
                if (! $dryRun) {
                    $this->subscriptionService->getOrCreateForVendor($v);
                }
                $synchronized++;

                continue;
            }

            $mismatch = false;
            if ($v->subscription_status !== $sub->status->value) {
                $mismatch = true;
            }
            if ((int) $v->subscription_plan_id !== (int) $sub->subscription_plan_id) {
                $mismatch = true;
            }

            if ($mismatch) {
                if (! $dryRun) {
                    $this->subscriptionService->syncVendor($sub);
                }
                $synchronized++;
            }
        }

        return [
            'checked' => $checked,
            'synchronized' => $synchronized,
            'dry_run' => $dryRun,
        ];
    }
}
