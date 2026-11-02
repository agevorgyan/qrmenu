<?php

namespace App\Services;

use App\Models\SecurityAuditLog;
use App\Models\Subscription;
use App\Models\SubscriptionAuditLog;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class SubscriptionAuditService
{
    public function record(
        ?string $action = null,
        ?Vendor $vendor = null,
        ?Subscription $subscription = null,
        ?string $targetType = null,
        int|string|null $targetId = null,
        ?array $previousValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?User $actor = null,
        array $metadata = [],
        ?string $actionType = null,
        ?string $entityType = null,
        int|string|null $entityId = null
    ): ?SubscriptionAuditLog {
        $resolvedAction = $action ?? $actionType ?? 'subscription.updated';
        $resolvedTargetType = $targetType ?? $entityType;
        $resolvedTargetId = is_numeric($targetId ?? $entityId) ? (int) ($targetId ?? $entityId) : null;
        $resolvedActor = $actor ?? Auth::user();
        $auditLog = null;

        try {
            if (Schema::hasTable('subscription_audit_logs')) {
                $auditLog = SubscriptionAuditLog::create([
                    'vendor_id' => $vendor?->id ?? $subscription?->vendor_id,
                    'subscription_id' => $subscription?->id,
                    'actor_id' => $resolvedActor?->id,
                    'action' => $resolvedAction,
                    'target_type' => $resolvedTargetType,
                    'target_id' => $resolvedTargetId,
                    'previous_values' => $previousValues,
                    'new_values' => $newValues,
                    'reason' => $reason,
                    'ip_address' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                    'metadata' => $metadata,
                    'created_at' => now(),
                ]);
            }

            if (class_exists(SecurityAuditService::class)) {
                app(SecurityAuditService::class)->record(
                    event: SecurityAuditLog::EVENT_SUBSCRIPTION_CHANGED,
                    action: $resolvedAction.($reason ? " — Reason: {$reason}" : ''),
                    vendor: $vendor,
                    actor: $resolvedActor,
                    targetType: $resolvedTargetType,
                    targetId: $resolvedTargetId,
                    metadata: array_filter([
                        'previous_values' => $previousValues,
                        'new_values' => $newValues,
                        'reason' => $reason,
                        'extra' => $metadata,
                    ])
                );
            }
        } catch (\Throwable) {
            // Do not break business operations if audit table is unavailable during early bootstrap
        }

        return $auditLog;
    }
}
