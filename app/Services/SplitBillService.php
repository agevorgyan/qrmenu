<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\SplitBill;
use App\Models\SplitBillPayment;
use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SplitBillService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager,
        protected SecurityAuditService $auditService
    ) {}

    /**
     * Retrieve or initialize an active SplitBill for an Order.
     */
    public function getOrCreateSplitBill(Order $order): SplitBill
    {
        $vendor = $order->vendor;
        if (! $vendor->isSplitBillEnabled()) {
            throw new InvalidArgumentException(__('Split bill is not enabled for this restaurant.'));
        }

        return DB::transaction(function () use ($order, $vendor) {
            $lockedBill = SplitBill::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->where('order_id', $order->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($lockedBill) {
                // Ensure total reflects latest order total
                $total = (float) $order->total_amount;
                $paid = (float) $lockedBill->payments()->where('status', 'paid')->sum('amount');
                $lockedBill->update([
                    'total_amount' => $total,
                    'paid_amount' => $paid,
                    'remaining_amount' => max(0, $total - $paid),
                ]);

                return $lockedBill;
            }

            $totalAmount = (float) $order->total_amount;
            $existingPaid = (float) SplitBillPayment::withoutGlobalScopes()
                ->where('order_id', $order->id)
                ->where('status', 'paid')
                ->sum('amount');

            return SplitBill::create([
                'uuid' => (string) Str::uuid(),
                'vendor_id' => $vendor->id,
                'order_id' => $order->id,
                'split_token' => SplitBill::generateSplitToken(),
                'status' => 'active',
                'split_type' => 'equal',
                'total_amount' => $totalAmount,
                'paid_amount' => $existingPaid,
                'remaining_amount' => max(0, $totalAmount - $existingPaid),
                'currency' => $vendor->currency ?? 'AMD',
                'total_participants' => 1,
            ]);
        });
    }

    /**
     * Compute mathematically precise equal split shares with zero penny/dram rounding discrepancy.
     *
     * @return array<string, mixed>
     */
    public function calculateEqualSplit(SplitBill $bill, int $participants): array
    {
        $settings = $bill->vendor->getSplitBillSetting();
        $max = max(2, (int) ($settings->max_participants ?? 10));

        if ($participants < 2 || $participants > $max) {
            throw new InvalidArgumentException(__("Participants count must be between 2 and {$max}."));
        }

        $currency = strtoupper($bill->currency ?? 'AMD');
        $decimals = in_array($currency, ['AMD', 'RUB', 'JPY'], true) ? 0 : 2;
        $factor = 10 ** $decimals;

        $remaining = (float) $bill->remaining_amount;
        $totalMinor = (int) round($remaining * $factor);

        if ($totalMinor <= 0) {
            return [
                'total_amount' => (float) $bill->total_amount,
                'remaining_amount' => 0.0,
                'participants' => $participants,
                'per_person_amount' => 0.0,
                'shares' => array_fill(0, $participants, 0.0),
                'currency' => $currency,
            ];
        }

        $baseShareMinor = intdiv($totalMinor, $participants);
        $remainderMinor = $totalMinor % $participants;

        $shares = [];
        for ($i = 0; $i < $participants; $i++) {
            $shareMinor = $baseShareMinor + ($i < $remainderMinor ? 1 : 0);
            $shares[] = (float) ($shareMinor / $factor);
        }

        return [
            'total_amount' => (float) $bill->total_amount,
            'paid_amount' => (float) $bill->paid_amount,
            'remaining_amount' => $remaining,
            'participants' => $participants,
            'per_person_amount' => $shares[0] ?? 0.0,
            'shares' => $shares,
            'currency' => $currency,
        ];
    }

    /**
     * Get real-time item availability for an Order, preventing double-claim of dishes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getItemizedAvailability(Order $order): array
    {
        $order->loadMissing('items.product');

        // Fetch already paid and unexpired pending claimed items
        $activePayments = SplitBillPayment::withoutGlobalScopes()
            ->where('order_id', $order->id)
            ->where(function ($q) {
                $q->where('status', 'paid')
                    ->orWhere(function ($sub) {
                        $sub->where('status', 'pending')
                            ->where(function ($exp) {
                                $exp->whereNull('expires_at')
                                    ->orWhere('expires_at', '>', now());
                            });
                    });
            })
            ->whereNotNull('items_breakdown')
            ->get();

        $claimedCounts = [];
        foreach ($activePayments as $payment) {
            foreach ((array) $payment->items_breakdown as $claimed) {
                $itemId = (int) ($claimed['order_item_id'] ?? 0);
                $qty = (int) ($claimed['quantity'] ?? 0);
                if ($itemId > 0 && $qty > 0) {
                    $claimedCounts[$itemId] = ($claimedCounts[$itemId] ?? 0) + $qty;
                }
            }
        }

        $result = [];
        foreach ($order->items as $idx => $item) {
            $claimed = $claimedCounts[$item->id] ?? 0;
            $available = max(0, $item->quantity - $claimed);
            $unitPrice = (float) ($item->unit_price ?? $item->subtotal / $item->quantity);

            $result[] = [
                'order_item_id' => $item->id,
                'dish_id' => 'item_'.($idx + 1),
                'name' => $item->product_name ?? $item->product?->name ?? 'Dish',
                'variation_name' => $item->variation_name,
                'ordered_quantity' => (int) $item->quantity,
                'claimed_quantity' => $claimed,
                'available_quantity' => $available,
                'unit_price' => $unitPrice,
                'subtotal' => (float) $item->subtotal,
            ];
        }

        return $result;
    }

    /**
     * Calculate payable amount for selected items with proportional apportioning of order fees.
     *
     * @param  array<int, array{order_item_id: int, quantity: int}>  $selectedItems
     * @return array{items_subtotal: float, apportioned_fees: float, total_amount: float, items_breakdown: array}
     */
    public function calculateItemSplit(Order $order, array $selectedItems): array
    {
        $availability = collect($this->getItemizedAvailability($order))->keyBy('order_item_id');

        $itemsSubtotal = 0.0;
        $breakdown = [];

        foreach ($selectedItems as $selection) {
            $itemId = (int) ($selection['order_item_id'] ?? 0);
            $qty = (int) ($selection['quantity'] ?? 0);

            if ($qty <= 0) {
                continue;
            }

            $avail = $availability->get($itemId);
            if (! $avail) {
                throw new InvalidArgumentException(__("Invalid order item #{$itemId}."));
            }

            if ($qty > $avail['available_quantity']) {
                throw new InvalidArgumentException(
                    __("Requested quantity ({$qty}) exceeds available unclaimed quantity ({$avail['available_quantity']}) for '{$avail['name']}''")
                );
            }

            $lineSubtotal = round($qty * $avail['unit_price'], 2);
            $itemsSubtotal += $lineSubtotal;

            $breakdown[] = [
                'order_item_id' => $itemId,
                'name' => $avail['name'],
                'variation_name' => $avail['variation_name'],
                'quantity' => $qty,
                'unit_price' => $avail['unit_price'],
                'subtotal' => $lineSubtotal,
            ];
        }

        if (empty($breakdown) || $itemsSubtotal <= 0) {
            throw new InvalidArgumentException(__('No items were selected to claim.'));
        }

        // Apportion order-level charges (delivery fee, service fee, tips, birthday discount)
        $orderSubtotal = max(1.0, (float) $order->subtotal);
        $ratio = min(1.0, $itemsSubtotal / $orderSubtotal);
        $orderCharges = max(0.0, (float) $order->total_amount - (float) $order->subtotal);
        $apportionedFees = round($orderCharges * $ratio, 2);

        $currency = strtoupper($order->vendor->currency ?? 'AMD');
        $decimals = in_array($currency, ['AMD', 'RUB', 'JPY'], true) ? 0 : 2;

        $totalPayable = round($itemsSubtotal + $apportionedFees, $decimals);

        return [
            'items_subtotal' => round($itemsSubtotal, $decimals),
            'apportioned_fees' => round($apportionedFees, $decimals),
            'total_amount' => $totalPayable,
            'items_breakdown' => $breakdown,
        ];
    }

    /**
     * Initiate a Split Bill Payment transaction with pessimistic row locking and idempotency.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function initiateSplitPayment(
        SplitBill $bill,
        float $amount,
        string $paymentMethod,
        ?string $participantName = null,
        ?array $itemsBreakdown = null,
        ?string $idempotencyKey = null,
        array $options = []
    ): array {
        $vendor = $bill->vendor;
        if (! $vendor->isSplitBillEnabled()) {
            throw new InvalidArgumentException(__('Split bill is disabled for this restaurant.'));
        }

        $currency = strtoupper($bill->currency ?? 'AMD');
        $decimals = in_array($currency, ['AMD', 'RUB', 'JPY'], true) ? 0 : 2;
        $amount = round($amount, $decimals);

        if ($amount <= 0) {
            throw new InvalidArgumentException(__('Payment amount must be greater than zero.'));
        }

        return DB::transaction(function () use ($bill, $amount, $paymentMethod, $participantName, $itemsBreakdown, $idempotencyKey, $vendor, $decimals) {
            // Lock SplitBill and Order
            $lockedBill = SplitBill::where('id', $bill->id)->lockForUpdate()->firstOrFail();
            $lockedOrder = Order::where('id', $lockedBill->order_id)->lockForUpdate()->firstOrFail();

            if ($lockedBill->status !== 'active') {
                throw new InvalidArgumentException(__('This split bill session is no longer active.'));
            }

            // Calculate authoritative unpaid balance taking locked paid rows into account
            $totalPaid = (float) SplitBillPayment::withoutGlobalScopes()
                ->where('split_bill_id', $lockedBill->id)
                ->where('status', 'paid')
                ->sum('amount');

            $currentRemaining = max(0.0, round((float) $lockedBill->total_amount - $totalPaid, $decimals));

            // Prevent overpayment
            if ($amount > ($currentRemaining + 0.001)) {
                throw new InvalidArgumentException(
                    __("Amount ({$amount}) exceeds the remaining payable balance ({$currentRemaining}).")
                );
            }

            // Idempotency check if key provided
            if ($idempotencyKey) {
                $existing = SplitBillPayment::withoutGlobalScopes()
                    ->where('split_bill_id', $lockedBill->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    return [
                        'success' => true,
                        'idempotent' => true,
                        'payment' => $existing,
                        'redirect_url' => null,
                        'status' => $existing->status,
                    ];
                }
            }

            $settings = $vendor->getSplitBillSetting();
            $isOffline = in_array($paymentMethod, ['cash', 'pos_terminal'], true);
            $requiresApproval = $isOffline && (bool) $settings->require_staff_approval;

            $splitPayment = SplitBillPayment::create([
                'uuid' => (string) Str::uuid(),
                'split_bill_id' => $lockedBill->id,
                'vendor_id' => $vendor->id,
                'order_id' => $lockedOrder->id,
                'participant_name' => $participantName ?: __('Guest'),
                'amount' => $amount,
                'currency' => $lockedBill->currency,
                'status' => $requiresApproval ? 'pending' : ($isOffline ? 'paid' : 'pending'),
                'payment_method' => $paymentMethod,
                'items_breakdown' => $itemsBreakdown,
                'idempotency_key' => $idempotencyKey,
                'paid_at' => ($isOffline && ! $requiresApproval) ? now() : null,
                'expires_at' => $isOffline ? null : now()->addMinutes(30),
            ]);

            // If offline and approved immediately
            if ($isOffline && ! $requiresApproval) {
                $this->applyPaidBalance($lockedBill, $lockedOrder, $amount, $paymentMethod);

                return [
                    'success' => true,
                    'status' => 'paid',
                    'payment' => $splitPayment,
                    'redirect_url' => null,
                    'requires_approval' => false,
                ];
            }

            if ($requiresApproval) {
                return [
                    'success' => true,
                    'status' => 'pending',
                    'payment' => $splitPayment,
                    'redirect_url' => null,
                    'requires_approval' => true,
                    'message' => __('Your cash split payment request has been submitted for waiter approval.'),
                ];
            }

            // Digital gateway initiation (e.g. Idram, Telcell, Stripe, ArCa)
            $merchantRef = 'SPLIT-'.$splitPayment->id.'-'.Str::upper(Str::random(8));
            $attempt = PaymentAttempt::create([
                'uuid' => (string) Str::uuid(),
                'vendor_id' => $vendor->id,
                'order_id' => $lockedOrder->id,
                'gateway' => $paymentMethod,
                'merchant_reference' => $merchantRef,
                'amount' => $amount,
                'currency' => $lockedBill->currency,
                'status' => PaymentStatus::Created,
                'idempotency_key' => (string) Str::uuid(),
                'expires_at' => now()->addMinutes(30),
            ]);

            $splitPayment->update([
                'payment_attempt_id' => $attempt->id,
                'payment_reference' => $merchantRef,
            ]);

            $gatewayAdapter = $this->gatewayManager->gateway($paymentMethod);
            $initiation = $gatewayAdapter->initiatePayment($attempt);

            $attempt->update([
                'status' => PaymentStatus::Pending,
                'provider_transaction_id' => $initiation->providerTransactionId,
                'request_payload' => $initiation->gatewayData,
            ]);

            return [
                'success' => $initiation->success,
                'status' => 'pending',
                'payment' => $splitPayment,
                'redirect_url' => $initiation->redirectUrl,
                'transaction_id' => $merchantRef,
            ];
        });
    }

    /**
     * Reconcile payment attempt confirmation from PaymentVerificationService.
     */
    public function syncSplitPaymentStatus(PaymentAttempt $attempt, string $status): void
    {
        $splitPayment = SplitBillPayment::withoutGlobalScopes()
            ->where('payment_attempt_id', $attempt->id)
            ->first();

        if (! $splitPayment) {
            return;
        }

        DB::transaction(function () use ($splitPayment, $attempt, $status) {
            $lockedPayment = SplitBillPayment::where('id', $splitPayment->id)->lockForUpdate()->firstOrFail();
            $lockedBill = SplitBill::where('id', $lockedPayment->split_bill_id)->lockForUpdate()->firstOrFail();
            $lockedOrder = Order::where('id', $lockedPayment->order_id)->lockForUpdate()->firstOrFail();

            if ($status === 'paid' && $lockedPayment->status !== 'paid') {
                $lockedPayment->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'payment_reference' => $attempt->provider_transaction_id ?? $lockedPayment->payment_reference,
                ]);

                $this->applyPaidBalance($lockedBill, $lockedOrder, (float) $lockedPayment->amount, $lockedPayment->payment_method);
            } elseif (in_array($status, ['failed', 'cancelled'], true) && $lockedPayment->status === 'pending') {
                $lockedPayment->update(['status' => $status]);
            }
        });
    }

    /**
     * Staff/Waiter approval of pending cash split payment.
     */
    public function approveCashPayment(SplitBillPayment $payment, User $staff): void
    {
        if ((int) $staff->vendor_id !== (int) $payment->vendor_id) {
            throw new InvalidArgumentException(__('Cross-vendor staff cannot approve split payment.'));
        }

        DB::transaction(function () use ($payment, $staff) {
            $lockedPayment = SplitBillPayment::where('id', $payment->id)->lockForUpdate()->firstOrFail();
            $lockedBill = SplitBill::where('id', $lockedPayment->split_bill_id)->lockForUpdate()->firstOrFail();
            $lockedOrder = Order::where('id', $lockedPayment->order_id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== 'pending') {
                throw new InvalidArgumentException(__('This split payment is not pending approval.'));
            }

            $lockedPayment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'approved_by' => $staff->id,
                'approved_at' => now(),
            ]);

            $this->applyPaidBalance($lockedBill, $lockedOrder, (float) $lockedPayment->amount, $lockedPayment->payment_method);

            $this->auditService->record(
                event: 'split_bill.cash_payment_approved',
                action: "Approved cash split payment #{$lockedPayment->id} of {$lockedPayment->amount} {$lockedPayment->currency}",
                vendor: $lockedBill->vendor,
                actor: $staff,
                targetType: 'split_bill_payment',
                targetId: $lockedPayment->id,
                metadata: [
                    'amount' => $lockedPayment->amount,
                    'order_id' => $lockedOrder->id,
                ]
            );
        });
    }

    /**
     * Atomically recalculate paid/remaining balances and update Order status.
     */
    protected function applyPaidBalance(SplitBill $bill, Order $order, float $amountAdded, string $method): void
    {
        $currency = strtoupper($bill->currency ?? 'AMD');
        $decimals = in_array($currency, ['AMD', 'RUB', 'JPY'], true) ? 0 : 2;

        $totalPaid = (float) SplitBillPayment::withoutGlobalScopes()
            ->where('split_bill_id', $bill->id)
            ->where('status', 'paid')
            ->sum('amount');

        $totalOrder = (float) $bill->total_amount;
        $remaining = max(0.0, round($totalOrder - $totalPaid, $decimals));

        $isCompleted = ($remaining <= 0.001);

        $bill->update([
            'paid_amount' => round($totalPaid, $decimals),
            'remaining_amount' => $remaining,
            'status' => $isCompleted ? 'completed' : 'active',
        ]);

        $order->update([
            'payment_status' => $isCompleted ? 'paid' : 'partially_paid',
            'payment_method' => $method,
        ]);
    }
}
