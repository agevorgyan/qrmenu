<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\FinancialApprovalPolicy;
use App\Models\FinancialApprovalRequest;
use App\Models\FinancialAuditLog;
use App\Models\PosShift;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FinancialApprovalService
{
    /**
     * Check if a financial action requires approval based on tenant policies.
     */
    public function requiresApproval(Vendor $vendor, string $entityType, float $amount, ?User $user = null): bool
    {
        $policy = FinancialApprovalPolicy::where('vendor_id', $vendor->id)
            ->where('entity_type', $entityType)
            ->where('is_active', true)
            ->first();

        if (! $policy) {
            // Default rule: expenses or variances > 50,000 require approval if no custom policy
            if (in_array($entityType, ['expense', 'shift_discrepancy'], true) && $amount >= 50000.0) {
                return true;
            }

            return false;
        }

        // If amount is strictly below threshold and auto-approve is active, no approval needed
        if ($policy->auto_approve_below && $amount < (float) $policy->threshold_amount) {
            return false;
        }

        return $amount >= (float) $policy->threshold_amount;
    }

    /**
     * Create an approval request for an approvable entity.
     */
    public function createRequest(
        Vendor $vendor,
        Model $approvable,
        float $amount,
        User $requester,
        ?string $notes = null
    ): FinancialApprovalRequest {
        return DB::transaction(function () use ($vendor, $approvable, $amount, $requester, $notes) {
            $request = FinancialApprovalRequest::create([
                'vendor_id' => $vendor->id,
                'approvable_type' => get_class($approvable),
                'approvable_id' => $approvable->getKey(),
                'amount' => round($amount, 2),
                'status' => 'pending',
                'requested_by' => $requester->id,
                'notes' => $notes,
            ]);

            FinancialAuditLog::record(
                vendor: $vendor,
                action: 'approval_requested',
                auditable: $request,
                user: $requester,
                newValues: [
                    'entity_type' => class_basename($approvable),
                    'entity_id' => $approvable->getKey(),
                    'amount' => $amount,
                    'notes' => $notes,
                ],
                amount: $amount
            );

            return $request;
        });
    }

    /**
     * Approve a pending financial request.
     */
    public function approve(FinancialApprovalRequest $request, User $decider, ?string $notes = null): bool
    {
        if ($request->status !== 'pending') {
            throw new InvalidArgumentException("Approval request is already {$request->status}.");
        }

        return DB::transaction(function () use ($request, $decider, $notes) {
            $request->update([
                'status' => 'approved',
                'decided_by' => $decider->id,
                'decided_at' => Carbon::now(),
                'notes' => $notes ? ($request->notes."\nDecider note: ".$notes) : $request->notes,
            ]);

            $approvable = $request->approvable;

            if ($approvable instanceof Expense) {
                $approvable->update([
                    'status' => 'approved',
                    'approved_by' => $decider->id,
                    'approved_at' => Carbon::now(),
                ]);
            } elseif ($approvable instanceof PosShift) {
                $approvable->update([
                    'manager_approved_by' => $decider->id,
                    'manager_approved_at' => Carbon::now(),
                    'approval_notes' => $notes,
                    'variance_status' => 'approved',
                ]);
            }

            FinancialAuditLog::record(
                vendor: $request->vendor,
                action: 'approval_granted',
                auditable: $request,
                user: $decider,
                newValues: [
                    'request_id' => $request->id,
                    'approvable_type' => $request->approvable_type,
                    'approvable_id' => $request->approvable_id,
                    'decided_by' => $decider->id,
                ],
                amount: (float) $request->amount
            );

            return true;
        });
    }

    /**
     * Reject a pending financial request.
     */
    public function reject(FinancialApprovalRequest $request, User $decider, string $notes): bool
    {
        if ($request->status !== 'pending') {
            throw new InvalidArgumentException("Approval request is already {$request->status}.");
        }

        return DB::transaction(function () use ($request, $decider, $notes) {
            $request->update([
                'status' => 'rejected',
                'decided_by' => $decider->id,
                'decided_at' => Carbon::now(),
                'notes' => $request->notes ? ($request->notes."\nRejection reason: ".$notes) : $notes,
            ]);

            $approvable = $request->approvable;

            if ($approvable instanceof Expense) {
                $approvable->update([
                    'status' => 'rejected',
                ]);
            } elseif ($approvable instanceof PosShift) {
                $approvable->update([
                    'variance_status' => 'rejected',
                    'approval_notes' => 'Rejected: '.$notes,
                ]);
            }

            FinancialAuditLog::record(
                vendor: $request->vendor,
                action: 'approval_rejected',
                auditable: $request,
                user: $decider,
                newValues: [
                    'request_id' => $request->id,
                    'reason' => $notes,
                    'decided_by' => $decider->id,
                ],
                amount: (float) $request->amount
            );

            return true;
        });
    }

    /**
     * Retrieve pending approval requests for a tenant.
     */
    public function getPendingRequests(Vendor $vendor, ?string $entityType = null): Collection
    {
        return FinancialApprovalRequest::where('vendor_id', $vendor->id)
            ->where('status', 'pending')
            ->when($entityType, fn ($q) => $q->where('approvable_type', $entityType))
            ->with(['requestedByUser', 'approvable'])
            ->orderBy('created_at', 'asc')
            ->get();
    }
}
