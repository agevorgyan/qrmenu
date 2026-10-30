<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PosCashMovement;
use App\Models\PosDevice;
use App\Models\PosOperationLog;
use App\Models\PosPayment;
use App\Models\PosShift;
use App\Models\User;
use App\Models\Vendor;
use App\Security\Permission;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosPaymentShiftService
{
    /**
     * Get the active open shift for this vendor/location/device.
     */
    public function getCurrentShift(
        Vendor $vendor,
        ?int $locationId = null,
        ?PosDevice $device = null,
        ?User $user = null
    ): ?PosShift {
        return PosShift::where('vendor_id', $vendor->id)
            ->where('status', 'open')
            ->when($device, fn ($q) => $q->where('pos_device_id', $device->id))
            ->when(! $device && $locationId, fn ($q) => $q->where('location_id', $locationId))
            ->when(! $device && $user, fn ($q) => $q->where('user_id', $user->id))
            ->with(['user', 'posDevice', 'cashMovements'])
            ->latest('id')
            ->first();
    }

    /**
     * Open a new cash drawer shift.
     */
    public function openShift(
        Vendor $vendor,
        array $data,
        User $user,
        ?PosDevice $device = null
    ): PosShift {
        if (! $user->hasPermission(Permission::POS_CASH_DRAWER) && ! $user->hasPermission(Permission::POS_ACCESS)) {
            throw ValidationException::withMessages([
                'authorization' => 'User lacks permission to manage cash shifts.',
            ]);
        }

        // Check for existing open shift on this device or for this user
        $existing = PosShift::where('vendor_id', $vendor->id)
            ->where('status', 'open')
            ->when($device, fn ($q) => $q->where('pos_device_id', $device->id))
            ->when(! $device, fn ($q) => $q->where('user_id', $user->id))
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'shift' => 'An active open shift already exists (Shift #'.$existing->id.'). Please close it first.',
            ]);
        }

        $openingFloat = max(0, (float) ($data['opening_float'] ?? 0.00));
        $locationId = $data['location_id'] ?? $device?->location_id ?? $user->location_id ?? $vendor->locations->first()?->id;

        $shift = PosShift::create([
            'vendor_id' => $vendor->id,
            'location_id' => $locationId,
            'pos_device_id' => $device?->id,
            'user_id' => $user->id,
            'status' => 'open',
            'opened_at' => Carbon::now(),
            'opening_float' => $openingFloat,
            'expected_cash' => $openingFloat,
            'opening_notes' => $data['opening_notes'] ?? null,
        ]);

        PosOperationLog::log(
            vendor: $vendor,
            action: 'pos_shift_open',
            user: $user,
            device: $device,
            entity: $shift,
            details: [
                'shift_id' => $shift->id,
                'opening_float' => $openingFloat,
                'opened_at' => $shift->opened_at->toIso8601String(),
            ]
        );

        return $shift->load(['user', 'posDevice']);
    }

    /**
     * Record a cash movement (cash-in, cash-out, safe drop, expense) during an active shift.
     */
    public function recordCashMovement(
        PosShift $shift,
        array $data,
        User $user,
        ?PosDevice $device = null
    ): PosCashMovement {
        if (! $shift->isOpen()) {
            throw ValidationException::withMessages([
                'shift' => 'Cannot record cash movements on a closed shift.',
            ]);
        }

        if ((int) $shift->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized cash movement.');
        }

        $validTypes = ['cash_in', 'cash_out', 'safe_drop', 'expense'];
        $type = $data['type'] ?? '';
        if (! in_array($type, $validTypes, true)) {
            throw ValidationException::withMessages([
                'type' => "Invalid cash movement type '{$type}'.",
            ]);
        }

        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Cash movement amount must be greater than zero.',
            ]);
        }

        // Recalculate live balance before validating cash out
        $shift->recalculateExpectedCash();
        if (in_array($type, ['cash_out', 'safe_drop', 'expense'], true) && $amount > (float) $shift->expected_cash) {
            throw ValidationException::withMessages([
                'amount' => 'Withdrawal amount ('.number_format($amount, 2).' AMD) exceeds current cash in drawer ('.number_format((float) $shift->expected_cash, 2).' AMD).',
            ]);
        }

        $movement = PosCashMovement::create([
            'vendor_id' => $shift->vendor_id,
            'pos_shift_id' => $shift->id,
            'pos_device_id' => $device?->id ?? $shift->pos_device_id,
            'user_id' => $user->id,
            'type' => $type,
            'amount' => $amount,
            'reason' => $data['reason'] ?? ucfirst(str_replace('_', ' ', $type)),
            'notes' => $data['notes'] ?? null,
        ]);

        $shift->recalculateExpectedCash();

        PosOperationLog::log(
            vendor: $shift->vendor,
            action: 'pos_cash_movement',
            user: $user,
            device: $device,
            entity: $movement,
            details: [
                'shift_id' => $shift->id,
                'type' => $type,
                'amount' => $amount,
                'reason' => $movement->reason,
            ]
        );

        return $movement;
    }

    /**
     * Close an active cash shift with counted cash reconciliation.
     */
    public function closeShift(
        PosShift $shift,
        array $data,
        User $user,
        ?PosDevice $device = null
    ): PosShift {
        if (! $shift->isOpen()) {
            throw ValidationException::withMessages([
                'shift' => 'This shift is already closed.',
            ]);
        }

        if ((int) $shift->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized shift closing.');
        }

        $countedCash = (float) ($data['counted_cash'] ?? 0.00);
        $closingNotes = $data['closing_notes'] ?? null;

        $shift->closeShift($countedCash, $user, $closingNotes);

        PosOperationLog::log(
            vendor: $shift->vendor,
            action: 'pos_shift_close',
            user: $user,
            device: $device,
            entity: $shift,
            details: [
                'shift_id' => $shift->id,
                'expected_cash' => (float) $shift->expected_cash,
                'counted_cash' => $countedCash,
                'discrepancy' => (float) $shift->discrepancy,
                'total_sales' => (float) ($shift->total_cash_sales + $shift->total_card_sales + $shift->total_other_sales),
            ]
        );

        return $shift->fresh(['user', 'closedByUser', 'cashMovements']);
    }

    /**
     * Process an order payment with support for Cash, Card, Mixed, and Partial payments.
     */
    public function processOrderPayment(
        Order $order,
        array $paymentData,
        User $cashier,
        ?PosDevice $device = null
    ): array {
        if ((int) $order->vendor_id !== (int) $cashier->vendor_id) {
            abort(403, 'Unauthorized order payment.');
        }

        if (in_array($order->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'order' => 'Cannot accept payment for an already completed or cancelled order.',
            ]);
        }

        $orderTotal = (float) $order->total_amount;
        $activeShift = $this->getCurrentShift($order->vendor, $order->location_id, $device, $cashier);

        $method = $paymentData['payment_method'] ?? 'cash';
        $tipAmount = max(0, (float) ($paymentData['tip_amount'] ?? 0));

        return DB::transaction(function () use ($order, $paymentData, $cashier, $device, $orderTotal, $activeShift, $method, $tipAmount) {
            $createdPayments = [];
            $totalPaid = 0.00;
            $totalChange = 0.00;

            if ($method === 'mixed') {
                $splits = $paymentData['split_payments'] ?? [];
                if (empty($splits)) {
                    throw ValidationException::withMessages([
                        'split_payments' => 'Mixed payment requires breakdown of payments.',
                    ]);
                }

                $mixedSum = 0.00;
                $assignedTip = 0.00;
                foreach ($splits as $index => $split) {
                    $splitMethod = $split['payment_method'] ?? 'cash';
                    $splitAmount = (float) ($split['amount'] ?? 0);
                    $splitTendered = isset($split['cash_tendered']) ? (float) $split['cash_tendered'] : $splitAmount;
                    $splitChange = max(0, $splitTendered - $splitAmount);
                    $splitTip = isset($split['tip_amount']) ? (float) $split['tip_amount'] : ($index === 0 ? $tipAmount : 0.00);

                    if ($splitAmount <= 0) {
                        continue;
                    }

                    $payment = PosPayment::create([
                        'vendor_id' => $order->vendor_id,
                        'order_id' => $order->id,
                        'pos_shift_id' => $activeShift?->id,
                        'pos_device_id' => $device?->id,
                        'user_id' => $cashier->id,
                        'payment_method' => $splitMethod,
                        'amount' => $splitAmount,
                        'cash_tendered' => $splitMethod === 'cash' ? $splitTendered : null,
                        'change_due' => $splitMethod === 'cash' ? $splitChange : 0,
                        'tip_amount' => $splitTip,
                        'currency' => $order->currency ?: 'AMD',
                        'status' => 'completed',
                        'transaction_reference' => $split['transaction_reference'] ?? null,
                    ]);

                    $createdPayments[] = $payment;
                    $mixedSum += $splitAmount;
                    $totalChange += $splitChange;
                    $assignedTip += $splitTip;
                }

                if ($mixedSum < $orderTotal) {
                    throw ValidationException::withMessages([
                        'split_payments' => 'Total mixed payments ('.number_format($mixedSum, 2).') is less than order total ('.number_format($orderTotal, 2).').',
                    ]);
                }

                $totalPaid = $mixedSum;
            } else {
                // Single payment method (cash, card, etc.)
                $payAmount = (float) ($paymentData['amount'] ?? $orderTotal);
                $tendered = isset($paymentData['cash_tendered']) ? (float) $paymentData['cash_tendered'] : $payAmount;
                $changeDue = $method === 'cash' ? max(0, $tendered - $payAmount) : 0.00;

                $payment = PosPayment::create([
                    'vendor_id' => $order->vendor_id,
                    'order_id' => $order->id,
                    'pos_shift_id' => $activeShift?->id,
                    'pos_device_id' => $device?->id,
                    'user_id' => $cashier->id,
                    'payment_method' => $method,
                    'amount' => $payAmount,
                    'cash_tendered' => $method === 'cash' ? $tendered : null,
                    'change_due' => $changeDue,
                    'tip_amount' => $tipAmount,
                    'currency' => $order->currency ?: 'AMD',
                    'status' => 'completed',
                    'transaction_reference' => $paymentData['transaction_reference'] ?? null,
                    'notes' => $paymentData['notes'] ?? null,
                ]);

                $createdPayments[] = $payment;
                $totalPaid = $payAmount;
                $totalChange = $changeDue;
            }

            // Update order status and financials
            $order->status = 'completed';
            $order->payment_status = 'paid';
            $order->payment_method = $method;
            $order->paid_amount = $totalPaid;
            $order->change_amount = $totalChange;
            $order->tip_amount = ($order->tip_amount ?? 0) + $tipAmount;
            $order->cashier_id = $cashier->id;
            $order->pos_shift_id = $activeShift?->id;
            $order->closed_at = Carbon::now();
            $order->lock_version++;
            $order->save();

            // Release dining table to cleaning
            if ($order->restaurant_table_id) {
                $table = $order->restaurantTable;
                $table?->markCleaning();
            }

            // Recalculate shift
            $activeShift?->recalculateExpectedCash();

            PosOperationLog::log(
                vendor: $order->vendor,
                action: 'pos_order_payment',
                user: $cashier,
                device: $device,
                entity: $order,
                details: [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'method' => $method,
                    'paid_amount' => $totalPaid,
                    'change_amount' => $totalChange,
                    'shift_id' => $activeShift?->id,
                ]
            );

            return [
                'order' => $order->fresh(['items', 'posPayments', 'restaurantTable', 'cashier']),
                'payments' => $createdPayments,
                'change_due' => $totalChange,
            ];
        });
    }

    /**
     * Refund a completed POS payment.
     */
    public function refundPayment(
        PosPayment $payment,
        float $amount,
        ?string $reason,
        User $user,
        ?PosDevice $device = null
    ): PosPayment {
        if (! $user->hasPermission(Permission::POS_REFUNDS) && ! $user->hasPermission(Permission::POS_ACCESS)) {
            throw ValidationException::withMessages([
                'authorization' => 'User lacks permission to process refunds.',
            ]);
        }

        if ((int) $payment->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized payment refund.');
        }

        if ($payment->status === 'refunded' || $payment->status === 'voided') {
            throw ValidationException::withMessages([
                'payment' => 'This payment has already been fully refunded or voided.',
            ]);
        }

        $remainingRefundable = (float) $payment->amount - (float) $payment->refunded_amount;
        if ($amount <= 0 || $amount > $remainingRefundable) {
            throw ValidationException::withMessages([
                'amount' => 'Refund amount must be between 0.01 and '.number_format($remainingRefundable, 2).' AMD.',
            ]);
        }

        return DB::transaction(function () use ($payment, $amount, $reason, $user, $device) {
            $payment->refunded_amount = (float) $payment->refunded_amount + $amount;
            if ($payment->refunded_amount >= (float) $payment->amount) {
                $payment->status = 'refunded';
            } else {
                $payment->status = 'partially_refunded';
            }
            $payment->notes = trim(($payment->notes ? $payment->notes.' | ' : '').'Refund: '.number_format($amount, 2).' AMD. Reason: '.($reason ?: 'Customer requested'));
            $payment->save();

            // Update order payment status
            $order = $payment->order;
            if ($order) {
                $allPayments = $order->posPayments;
                $allRefunded = $allPayments->every(fn ($p) => $p->status === 'refunded');
                $order->payment_status = $allRefunded ? 'refunded' : 'partially_refunded';
                $order->save();
            }

            // Recalculate shift
            $payment->posShift?->recalculateExpectedCash();

            PosOperationLog::log(
                vendor: $payment->vendor,
                action: 'pos_payment_refund',
                user: $user,
                device: $device,
                entity: $payment,
                details: [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'refund_amount' => $amount,
                    'reason' => $reason,
                ]
            );

            return $payment->fresh();
        });
    }
}
