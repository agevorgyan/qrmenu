<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\FinancialAuditLog;
use App\Models\Order;
use App\Models\PosOperationLog;
use App\Models\PosPayment;
use App\Models\User;
use App\Security\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function __construct(
        protected FinancialTransactionService $transactionService
    ) {}

    /**
     * Process full or partial refund for an order.
     *
     * @param array{
     *     amount?: float|int,
     *     payment_id?: int|null,
     *     payment_method?: string|null,
     *     cash_register_id?: int|null,
     *     reason?: string|null
     * } $data
     */
    public function processRefund(Order $order, array $data, User $user): array
    {
        if (! $user->hasPermission(Permission::ORDERS_REFUND) &&
            ! $user->hasPermission(Permission::POS_REFUNDS) &&
            ! $user->isVendorOwner() &&
            ! $user->isManager()) {
            throw ValidationException::withMessages([
                'authorization' => 'User lacks permission to process customer refunds.',
            ]);
        }

        if ((int) $order->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized order refund.');
        }

        $orderTotal = (float) $order->total_amount;
        $orderPaid = (float) ($order->paid_amount ?: $orderTotal);
        $orderRefunded = (float) ($order->refunded_amount ?? 0);
        $maxRefundable = max(0, round($orderPaid - $orderRefunded, 2));

        if ($maxRefundable <= 0) {
            throw ValidationException::withMessages([
                'refund' => 'This order has already been fully refunded.',
            ]);
        }

        $refundAmount = isset($data['amount']) ? round((float) $data['amount'], 2) : $maxRefundable;
        if ($refundAmount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Refund amount must be greater than zero.',
            ]);
        }

        if ($refundAmount > $maxRefundable) {
            throw ValidationException::withMessages([
                'amount' => "Refund amount ({$refundAmount}) exceeds maximum refundable amount ({$maxRefundable}).",
            ]);
        }

        $method = strtolower($data['payment_method'] ?? $order->payment_method ?? 'cash');
        $reason = trim($data['reason'] ?? 'Customer refund requested');
        $cashRegisterId = $data['cash_register_id'] ?? null;

        // If paying cash from a register, verify drawer balance
        if ($method === 'cash' && $cashRegisterId) {
            $register = CashRegister::where('vendor_id', $order->vendor_id)->find($cashRegisterId);
            if ($register) {
                $shift = $register->activeShift();
                if ($shift) {
                    $shift->recalculateExpectedCash();
                    if ($refundAmount > (float) $shift->expected_cash) {
                        throw ValidationException::withMessages([
                            'amount' => "Refund amount ({$refundAmount}) exceeds available cash in drawer ({$shift->expected_cash}).",
                        ]);
                    }
                }
            }
        }

        return DB::transaction(function () use ($order, $refundAmount, $method, $reason, $cashRegisterId, $orderPaid, $orderRefunded, $user, $data) {
            $newRefundedTotal = round($orderRefunded + $refundAmount, 2);
            $newStatus = $newRefundedTotal >= $orderPaid ? 'refunded' : 'partially_refunded';

            $order->update([
                'payment_status' => $newStatus,
                'refunded_amount' => $newRefundedTotal,
            ]);

            // If a specific PosPayment was referenced, update it
            $posPayment = PosPayment::where('order_id', $order->id)->latest('id')->first();
            if ($posPayment) {
                $paymentRefunded = round((float) $posPayment->refunded_amount + $refundAmount, 2);
                $paymentStatus = $paymentRefunded >= (float) $posPayment->amount ? 'refunded' : 'partially_refunded';
                $posPayment->update([
                    'status' => $paymentStatus,
                    'refunded_amount' => $paymentRefunded,
                ]);
            }

            $refundIdempotencyKey = $data['idempotency_key'] ?? "refund-order-{$order->id}-{$refundAmount}-{$newRefundedTotal}";

            // Post to General Ledger
            $journalEntry = $this->transactionService->recordOrderRefund(
                $order,
                $refundAmount,
                $method,
                $reason,
                $user,
                $refundIdempotencyKey
            );

            // Deduct from register if cash
            if ($method === 'cash' && $cashRegisterId) {
                $register = CashRegister::find($cashRegisterId);
                if ($register) {
                    $register->decrement('current_balance', $refundAmount);
                    $shift = $register->activeShift();
                    if ($shift) {
                        $shift->recalculateExpectedCash();
                    }
                }
            }

            FinancialAuditLog::record(
                vendor: $order->vendor,
                action: 'order_refund_processed',
                auditable: $order,
                user: $user,
                newValues: [
                    'order_id' => $order->id,
                    'refund_amount' => $refundAmount,
                    'new_status' => $newStatus,
                    'reason' => $reason,
                ],
                amount: $refundAmount,
                locationId: $order->location_id
            );

            PosOperationLog::log(
                vendor: $order->vendor,
                action: 'pos_order_refund',
                user: $user,
                entity: $order,
                details: [
                    'order_id' => $order->id,
                    'refund_amount' => $refundAmount,
                    'reason' => $reason,
                ]
            );

            return [
                'success' => true,
                'message' => 'Refund processed successfully.',
                'order_id' => $order->id,
                'refund_amount' => $refundAmount,
                'total_refunded' => $newRefundedTotal,
                'status' => $newStatus,
                'journal_entry_id' => $journalEntry->id,
            ];
        });
    }
}
