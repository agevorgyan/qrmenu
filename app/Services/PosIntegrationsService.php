<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentAttempt;
use App\Models\PosDevice;
use App\Models\PosOperationLog;
use App\Models\PosPayment;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Tip;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WaiterCall;
use App\Services\Fiscal\PosFiscalService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosIntegrationsService
{
    public function __construct(
        protected PosInventoryService $inventoryService,
        protected PosLoyaltyService $loyaltyService,
        protected PosFiscalService $fiscalService,
        protected CustomerSyncService $customerSyncService,
        protected TelegramNotificationService $telegramService,
        protected PaymentGatewayService $paymentGatewayService
    ) {}

    /**
     * Get incoming external orders (QR Menu, Web Online, Takeaway, Delivery).
     *
     * @return Collection<int, Order>
     */
    public function getIncomingExternalOrders(Vendor $vendor, ?int $locationId = null): Collection
    {
        $query = Order::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->whereIn('source', ['qr', 'online', 'takeaway', 'delivery'])
            ->whereIn('status', ['pending', 'confirmed', 'preparing'])
            ->with(['items.product', 'customer', 'restaurantTable'])
            ->orderByDesc('created_at');

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        return $query->get();
    }

    /**
     * Accept an online or takeaway/delivery order from the POS terminal.
     */
    public function acceptOnlineOrder(Order $order, User $user, ?int $prepTimeMinutes = null): Order
    {
        if ((int) $order->vendor_id !== (int) $user->vendor_id) {
            throw ValidationException::withMessages([
                'order' => 'Cross-tenant order access prohibited.',
            ]);
        }

        return DB::transaction(function () use ($order, $user, $prepTimeMinutes) {
            $order->update([
                'status' => 'confirmed',
                'cashier_id' => $user->id,
            ]);

            // Deduct inventory if items track stock
            $this->inventoryService->deductStockForOrder($order);

            PosOperationLog::log(
                vendor: $order->vendor ?: Vendor::find($order->vendor_id),
                action: 'pos_online_order_accepted',
                user: $user,
                entity: $order,
                details: [
                    'order_number' => $order->order_number,
                    'source' => $order->source,
                    'prep_time_minutes' => $prepTimeMinutes,
                ]
            );

            return $order->fresh(['items', 'customer', 'restaurantTable']);
        });
    }

    /**
     * Reject an incoming online or takeaway order.
     */
    public function rejectOnlineOrder(Order $order, User $user, string $reason): Order
    {
        if ((int) $order->vendor_id !== (int) $user->vendor_id) {
            throw ValidationException::withMessages([
                'order' => 'Cross-tenant order access prohibited.',
            ]);
        }

        return DB::transaction(function () use ($order, $user, $reason) {
            $order->update([
                'status' => 'cancelled',
                'notes' => trim(($order->notes ? $order->notes.' | ' : '')."Rejected by POS: {$reason}"),
            ]);

            // Restock if inventory was deducted
            $this->inventoryService->restockForOrder($order, "Online Order Rejected: {$reason}");

            PosOperationLog::log(
                vendor: $order->vendor ?: Vendor::find($order->vendor_id),
                action: 'pos_online_order_rejected',
                user: $user,
                entity: $order,
                details: [
                    'order_number' => $order->order_number,
                    'reason' => $reason,
                ]
            );

            return $order->fresh();
        });
    }

    /**
     * Retrieve active table reservations for today.
     *
     * @return Collection<int, Reservation>
     */
    public function getActiveReservations(Vendor $vendor, ?int $locationId = null, ?string $date = null): Collection
    {
        $targetDate = $date ?: Carbon::today()->toDateString();

        $query = Reservation::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('reservation_date', $targetDate)
            ->whereIn('status', [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED])
            ->with(['preOrder'])
            ->orderBy('reservation_time', 'asc');

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        return $query->get();
    }

    /**
     * Seat a reservation at a restaurant table and convert any pre-order into a live POS order.
     */
    public function seatReservation(Reservation $reservation, RestaurantTable $table, User $waiter): array
    {
        if ((int) $reservation->vendor_id !== (int) $waiter->vendor_id || (int) $table->vendor_id !== (int) $waiter->vendor_id) {
            throw ValidationException::withMessages([
                'reservation' => 'Cross-tenant reservation seating prohibited.',
            ]);
        }

        return DB::transaction(function () use ($reservation, $table, $waiter) {
            // 1. Mark reservation as seated
            $reservation->update([
                'status' => Reservation::STATUS_SEATED,
                'table_number' => $table->table_number ?: $table->name,
                'table_id' => (string) $table->id,
            ]);

            // 2. Occupy the table
            $table->update([
                'status' => 'occupied',
            ]);

            $createdOrder = null;

            // 3. Convert pre-order to live POS order if exists
            $preOrder = $reservation->preOrder;
            $itemsList = is_array($preOrder?->items) ? $preOrder->items : [];
            if ($preOrder && ! empty($itemsList)) {
                $vendor = $table->vendor ?: Vendor::find($table->vendor_id);
                $orderNumber = 'POS-RES-'.str_pad((string) mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);

                $createdOrder = Order::create([
                    'vendor_id' => $table->vendor_id,
                    'location_id' => $table->location_id,
                    'restaurant_table_id' => $table->id,
                    'table_number' => $table->table_number ?: $table->name,
                    'order_number' => $orderNumber,
                    'source' => 'pos',
                    'type' => 'dine_in',
                    'status' => 'pending',
                    'waiter_id' => $waiter->id,
                    'guest_count' => $reservation->guest_count ?: 2,
                    'customer_name' => $reservation->customer_name,
                    'customer_phone' => $reservation->customer_phone,
                    'customer_email' => $reservation->customer_email,
                    'notes' => "Seated from Reservation #{$reservation->reference}",
                    'subtotal' => 0,
                    'total_amount' => 0,
                    'opened_at' => Carbon::now(),
                ]);

                $subtotal = 0;
                foreach ($itemsList as $preItem) {
                    $qty = (float) ($preItem['quantity'] ?? 1);
                    $price = (float) ($preItem['unit_price'] ?? 0);
                    $itemTotal = (float) ($preItem['subtotal'] ?? ($price * $qty));
                    $subtotal += $itemTotal;

                    OrderItem::create([
                        'order_id' => $createdOrder->id,
                        'product_id' => $preItem['product_id'] ?? null,
                        'product_name' => $preItem['product_name'] ?? 'Item',
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'subtotal' => $itemTotal,
                        'status' => 'pending',
                        'kitchen_station' => 'kitchen',
                    ]);
                }

                $createdOrder->update([
                    'subtotal' => $subtotal,
                    'total_amount' => $subtotal,
                ]);

                // Deduct stock for pre-ordered items
                $this->inventoryService->deductStockForOrder($createdOrder);
            }

            PosOperationLog::log(
                vendor: $table->vendor ?: Vendor::find($table->vendor_id),
                action: 'pos_reservation_seated',
                user: $waiter,
                details: [
                    'reservation_id' => $reservation->id,
                    'reference' => $reservation->reference,
                    'table' => $table->name,
                    'created_order_id' => $createdOrder?->id,
                ]
            );

            return [
                'reservation' => $reservation->fresh(),
                'table' => $table->fresh(),
                'order' => $createdOrder?->fresh(['items']),
            ];
        });
    }

    /**
     * Retrieve active/pending waiter calls.
     *
     * @return Collection<int, WaiterCall>
     */
    public function getActiveWaiterCalls(Vendor $vendor, ?int $locationId = null): Collection
    {
        $query = WaiterCall::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('status', 'pending')
            ->orderByDesc('created_at');

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        return $query->get();
    }

    /**
     * Resolve/dismiss a waiter call from POS.
     */
    public function resolveWaiterCall(WaiterCall $call, User $user, ?PosDevice $device = null): WaiterCall
    {
        if ((int) $call->vendor_id !== (int) $user->vendor_id) {
            throw ValidationException::withMessages([
                'waiter_call' => 'Cross-tenant waiter call resolution prohibited.',
            ]);
        }

        $call->update([
            'status' => 'completed',
            'resolved_by_user_id' => $user->id,
            'pos_device_id' => $device?->id,
            'resolved_at' => Carbon::now(),
        ]);

        return $call->fresh(['resolvedBy']);
    }

    /**
     * Record a tip from a POS payment and link with waiter and shift.
     */
    public function recordOrderTip(
        Order $order,
        PosPayment $payment,
        float $tipAmount,
        ?User $waiter = null
    ): ?Tip {
        if ($tipAmount <= 0) {
            return null;
        }

        $waiterId = $waiter?->id ?: $order->waiter_id;
        $isCash = $payment->payment_method === 'cash';

        return Tip::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $order->vendor_id,
            'location_id' => $order->location_id,
            'order_id' => $order->id,
            'table_number' => $order->table_number,
            'waiter_id' => $waiterId,
            'amount' => $tipAmount,
            'currency' => $payment->currency ?: 'AMD',
            'tip_type' => 'fixed',
            'payment_method' => $payment->payment_method,
            'payment_status' => 'completed',
            'allocation_policy' => 'waiter_direct',
            'allocation_status' => 'allocated',
            'is_cash' => $isCash,
            'idempotency_key' => 'pos_tip_'.$payment->uuid,
            'settled_at' => Carbon::now(),
            'notes' => "POS Tip recorded via Payment #{$payment->uuid}",
        ]);
    }

    /**
     * Search customers for order assignment and loyalty.
     *
     * @return Collection<int, Customer>
     */
    public function searchCustomers(Vendor $vendor, string $query): Collection
    {
        $cleanQuery = trim($query);
        if (empty($cleanQuery)) {
            return new Collection;
        }

        return Customer::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where(function ($q) use ($cleanQuery) {
                $q->where('phone', 'like', "%{$cleanQuery}%")
                    ->orWhere('email', 'like', "%{$cleanQuery}%")
                    ->orWhere('name', 'like', "%{$cleanQuery}%");
            })
            ->take(20)
            ->get();
    }

    /**
     * Initiate an on-screen QR payment (e.g. Idram, Telcell, ArCa) directly from POS.
     */
    public function initiateQrPayment(Order $order, string $gateway, User $cashier): array
    {
        if ((int) $order->vendor_id !== (int) $cashier->vendor_id) {
            throw ValidationException::withMessages([
                'order' => 'Cross-tenant payment initiation prohibited.',
            ]);
        }

        $supported = ['idram', 'telcell', 'fastshift', 'arca', 'stripe'];
        if (! in_array(strtolower($gateway), $supported, true)) {
            throw ValidationException::withMessages([
                'gateway' => "Unsupported POS QR gateway '{$gateway}'.",
            ]);
        }

        $amount = (float) $order->total_amount;
        $orderNumber = $order->order_number;
        $vendor = $order->vendor ?: Vendor::find($order->vendor_id);

        // Generate synthetic or gateway-specific QR payload
        $paymentAttempt = PaymentAttempt::create([
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'merchant_reference' => 'POS-'.$orderNumber.'-'.Str::random(6),
            'amount' => $amount,
            'currency' => $vendor->currency ?: 'AMD',
            'gateway' => strtolower($gateway),
            'status' => 'pending',
            'idempotency_key' => 'pos_qr_'.Str::uuid(),
        ]);

        $checkoutUrl = url("/payment/checkout/{$order->id}?gateway={$gateway}");

        $qrData = match (strtolower($gateway)) {
            'idram' => "idram://pay?receiver=EDELAM&title=Order-{$orderNumber}&amount={$amount}&currency=AMD",
            'telcell' => "telcell://pay?merchant=ELAM_POS&invoice={$orderNumber}&sum={$amount}",
            'fastshift' => "fastshift://checkout?ref={$orderNumber}&amount={$amount}",
            default => $checkoutUrl,
        };

        return [
            'attempt_id' => $paymentAttempt->id,
            'gateway' => $gateway,
            'amount' => $amount,
            'currency' => $vendor->currency ?: 'AMD',
            'qr_data' => $qrData,
            'checkout_url' => $checkoutUrl,
        ];
    }
}
