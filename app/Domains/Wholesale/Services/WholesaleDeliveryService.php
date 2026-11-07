<?php

namespace App\Domains\Wholesale\Services;

use App\Domains\Wholesale\Models\WholesaleCustomer;
use App\Domains\Wholesale\Models\WholesaleDelivery;
use App\Domains\Wholesale\Models\WholesaleDeliveryItem;
use App\Domains\Wholesale\Models\WholesaleOrder;
use App\Domains\Wholesale\Models\WholesaleProduct;
use App\Domains\Wholesale\Models\WholesaleReturn;
use App\Domains\Wholesale\Models\WholesaleReturnItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\InventoryLedgerService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WholesaleDeliveryService
{
    public function __construct(
        protected InventoryLedgerService $inventoryService
    ) {}

    /**
     * List deliveries for a tenant.
     *
     * @param  array{status?: string, order_id?: int}  $filters
     */
    public function listDeliveries(Vendor $vendor, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = WholesaleDelivery::where('vendor_id', $vendor->id)
            ->with(['order.customer', 'warehouse', 'destinationAddress']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * List returns for a tenant.
     *
     * @param  array{status?: string, customer_id?: int}  $filters
     */
    public function listReturns(Vendor $vendor, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = WholesaleReturn::where('vendor_id', $vendor->id)
            ->with(['customer', 'order', 'sale', 'warehouse']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Plan a new delivery from an order.
     *
     * @param  array{
     *     warehouse_id?: int|null,
     *     driver_name?: string|null,
     *     driver_phone?: string|null,
     *     vehicle_number?: string|null,
     *     destination_address_id?: int|null,
     *     notes?: string|null,
     *     items?: array<int, array{order_item_id: int, quantity: float}>
     * }  $data
     */
    public function planDelivery(WholesaleOrder $order, array $data, ?User $user = null): WholesaleDelivery
    {
        if (! in_array($order->status, ['CONFIRMED', 'PARTIALLY_DELIVERED'])) {
            throw ValidationException::withMessages([
                'order' => __('Deliveries can only be scheduled for confirmed or partially delivered orders.'),
            ]);
        }

        return DB::transaction(function () use ($order, $data, $user) {
            $vendor = $order->vendor;
            $warehouseId = $data['warehouse_id'] ?? Warehouse::where('vendor_id', $vendor->id)->where('is_active', true)->where('is_default', true)->value('id');

            $deliveryNumber = $this->generateDeliveryNumber($vendor);

            $delivery = WholesaleDelivery::create([
                'vendor_id' => $vendor->id,
                'order_id' => $order->id,
                'delivery_number' => $deliveryNumber,
                'warehouse_id' => $warehouseId,
                'destination_address_id' => $data['destination_address_id'] ?? $order->delivery_address_id,
                'driver_name' => $data['driver_name'] ?? null,
                'driver_phone' => $data['driver_phone'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'status' => 'PLANNED',
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $user?->id,
            ]);

            // If specific items provided, plan those; otherwise plan all remaining unfulfilled items
            $orderItems = $order->items()->get();

            if (! empty($data['items'])) {
                foreach ($data['items'] as $itemInput) {
                    $orderItem = $orderItems->firstWhere('id', $itemInput['order_item_id']);
                    if ($orderItem) {
                        WholesaleDeliveryItem::create([
                            'delivery_id' => $delivery->id,
                            'order_item_id' => $orderItem->id,
                            'product_id' => $orderItem->product_id,
                            'quantity_planned' => (float) $itemInput['quantity'],
                            'quantity_delivered' => 0.0000,
                            'unit_code' => $orderItem->unit_code,
                        ]);
                    }
                }
            } else {
                foreach ($orderItems as $orderItem) {
                    $remaining = max(0, (float) $orderItem->quantity_ordered - (float) $orderItem->quantity_delivered);
                    if ($remaining > 0) {
                        WholesaleDeliveryItem::create([
                            'delivery_id' => $delivery->id,
                            'order_item_id' => $orderItem->id,
                            'product_id' => $orderItem->product_id,
                            'quantity_planned' => $remaining,
                            'quantity_delivered' => 0.0000,
                            'unit_code' => $orderItem->unit_code,
                        ]);
                    }
                }
            }

            return $delivery->fresh(['items', 'order']);
        });
    }

    /**
     * Dispatch delivery.
     */
    public function dispatchDelivery(WholesaleDelivery $delivery): WholesaleDelivery
    {
        $delivery->update([
            'status' => 'DISPATCHED',
            'dispatched_at' => now(),
        ]);

        return $delivery;
    }

    /**
     * Confirm delivery completion, record proof of delivery, and deplete core physical stock.
     *
     * @param  array{
     *     received_by_name?: string|null,
     *     proof_of_delivery_note?: string|null,
     *     items?: array<int, array{delivery_item_id: int, quantity_delivered: float, quantity_rejected?: float, rejection_reason?: string|null}>
     * }  $data
     */
    public function confirmDelivery(WholesaleDelivery $delivery, array $data = [], ?User $user = null): WholesaleDelivery
    {
        return DB::transaction(function () use ($delivery, $data, $user) {
            $delivery->load(['items.product', 'items.orderItem', 'order']);
            $warehouse = $delivery->warehouse ?? Warehouse::where('vendor_id', $delivery->vendor_id)->where('is_active', true)->first();

            $inventoryIssueItems = [];

            foreach ($delivery->items as $deliveryItem) {
                // If specific delivery item quantities passed
                $deliveredQty = (float) $deliveryItem->quantity_planned;
                $rejectedQty = 0.0000;
                $rejectionReason = null;

                if (! empty($data['items'])) {
                    foreach ($data['items'] as $inputItem) {
                        if ($inputItem['delivery_item_id'] == $deliveryItem->id) {
                            $deliveredQty = (float) $inputItem['quantity_delivered'];
                            $rejectedQty = (float) ($inputItem['quantity_rejected'] ?? 0);
                            $rejectionReason = $inputItem['rejection_reason'] ?? null;
                            break;
                        }
                    }
                }

                $deliveryItem->update([
                    'quantity_delivered' => $deliveredQty,
                    'quantity_rejected' => $rejectedQty,
                    'rejection_reason' => $rejectionReason,
                ]);

                // Update order item delivered quantity
                $orderItem = $deliveryItem->orderItem;
                if ($orderItem) {
                    $orderItem->increment('quantity_delivered', $deliveredQty);
                }

                // If wholesale product is mapped to core physical stock, queue for ledger depletion
                $product = $deliveryItem->product;
                if ($warehouse && $product && $product->physical_stock_ingredient_id && $deliveredQty > 0) {
                    $inventoryIssueItems[] = [
                        'ingredient_id' => $product->physical_stock_ingredient_id,
                        'quantity' => $deliveredQty,
                        'unit_id' => $product->unit_id,
                        'notes' => __('Depleted via Wholesale Delivery :deliv [Item: :sku]', [
                            'deliv' => $delivery->delivery_number,
                            'sku' => $product->sku,
                        ]),
                    ];
                }
            }

            // Execute transactional physical inventory depletion via core ledger
            if (! empty($inventoryIssueItems) && $warehouse) {
                $this->inventoryService->recordIssue(
                    warehouse: $warehouse,
                    items: $inventoryIssueItems,
                    type: InventoryTransaction::TYPE_ISSUE,
                    user: $user,
                    notes: __('Wholesale Delivery :deliv fulfillment', ['deliv' => $delivery->delivery_number]),
                    idempotencyKey: 'wholesale_delivery_issue_'.$delivery->id,
                    referenceType: 'wholesale_delivery',
                    referenceId: $delivery->id,
                    allowNegative: true
                );
            }

            // Update order status
            $order = $delivery->order;
            if ($order) {
                if ($order->isFullyDelivered()) {
                    $order->update(['status' => 'DELIVERED']);
                } else {
                    $order->update(['status' => 'PARTIALLY_DELIVERED']);
                }
            }

            $delivery->update([
                'status' => 'DELIVERED',
                'delivered_at' => now(),
                'received_by_name' => $data['received_by_name'] ?? null,
                'proof_of_delivery_note' => $data['proof_of_delivery_note'] ?? null,
            ]);

            return $delivery->fresh(['items', 'order']);
        });
    }

    /**
     * Process a customer return with optional physical restocking and AR adjustment.
     *
     * @param  array{
     *     customer_id: int,
     *     order_id?: int|null,
     *     sale_id?: int|null,
     *     warehouse_id?: int|null,
     *     reason: string,
     *     action_type: string,
     *     notes?: string|null,
     *     items: array<int, array{product_id: int, quantity: float, unit_price: float, reason_detail?: string|null}>
     * }  $data
     */
    public function processReturn(Vendor $vendor, array $data, ?User $user = null): WholesaleReturn
    {
        return DB::transaction(function () use ($vendor, $data, $user) {
            $customer = WholesaleCustomer::where('vendor_id', $vendor->id)->findOrFail($data['customer_id']);
            $warehouseId = $data['warehouse_id'] ?? Warehouse::where('vendor_id', $vendor->id)->where('is_active', true)->where('is_default', true)->value('id');
            $warehouse = $warehouseId ? Warehouse::find($warehouseId) : null;

            $returnNumber = $this->generateReturnNumber($vendor);

            $return = WholesaleReturn::create([
                'vendor_id' => $vendor->id,
                'customer_id' => $customer->id,
                'order_id' => $data['order_id'] ?? null,
                'sale_id' => $data['sale_id'] ?? null,
                'warehouse_id' => $warehouseId,
                'return_number' => $returnNumber,
                'return_date' => now()->toDateString(),
                'status' => 'COMPLETED',
                'action_type' => $data['action_type'],
                'total_return_amount' => 0.0000,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'processed_by_user_id' => $user?->id,
            ]);

            $totalAmount = 0.0000;
            $restockItems = [];

            foreach ($data['items'] as $itemData) {
                $product = WholesaleProduct::where('vendor_id', $vendor->id)->findOrFail($itemData['product_id']);
                $qty = (float) $itemData['quantity'];
                $price = (float) $itemData['unit_price'];
                $lineTotal = round($qty * $price, 4);
                $totalAmount += $lineTotal;

                $isRestocked = ($data['action_type'] === 'RESTOCK');

                WholesaleReturnItem::create([
                    'return_id' => $return->id,
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'total_amount' => $lineTotal,
                    'is_restocked' => $isRestocked,
                    'reason_detail' => $itemData['reason_detail'] ?? null,
                ]);

                if ($isRestocked && $warehouse && $product->physical_stock_ingredient_id) {
                    $restockItems[] = [
                        'ingredient_id' => $product->physical_stock_ingredient_id,
                        'quantity' => $qty,
                        'unit_id' => $product->unit_id,
                        'unit_cost' => (float) ($product->cost_price ?: $product->base_wholesale_price),
                        'notes' => __('Restocked from Wholesale Return :ret', ['ret' => $return->return_number]),
                    ];
                }
            }

            $return->update(['total_return_amount' => $totalAmount]);

            // Physical restock via Core Inventory Ledger
            if (! empty($restockItems) && $warehouse) {
                $this->inventoryService->recordReceipt(
                    warehouse: $warehouse,
                    items: $restockItems,
                    user: $user,
                    notes: __('Wholesale Return :ret restock', ['ret' => $return->return_number]),
                    idempotencyKey: 'wholesale_return_receipt_'.$return->id,
                    referenceType: 'wholesale_return',
                    referenceId: $return->id
                );
            }

            // Financial AR Credit Adjustment
            if (in_array($data['action_type'], ['RESTOCK', 'REFUND_CREDIT']) && $totalAmount > 0) {
                $customer->decrement('current_balance', $totalAmount);
            }

            return $return->fresh(['items', 'customer']);
        });
    }

    /**
     * Generate sequential delivery number: WD-YYYYMMDD-####
     */
    public function generateDeliveryNumber(Vendor $vendor): string
    {
        $datePrefix = 'WD-'.now()->format('Ymd');
        $countToday = WholesaleDelivery::where('vendor_id', $vendor->id)
            ->where('delivery_number', 'like', "{$datePrefix}-%")
            ->count();

        return sprintf('%s-%04d', $datePrefix, $countToday + 1);
    }

    /**
     * Generate sequential return number: WR-YYYYMMDD-####
     */
    public function generateReturnNumber(Vendor $vendor): string
    {
        $datePrefix = 'WR-'.now()->format('Ymd');
        $countToday = WholesaleReturn::where('vendor_id', $vendor->id)
            ->where('return_number', 'like', "{$datePrefix}-%")
            ->count();

        return sprintf('%s-%04d', $datePrefix, $countToday + 1);
    }
}
