<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PosInventoryMovement;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosInventoryService
{
    /**
     * Deduct stock for all items in an order upon confirmation or payment.
     * Idempotent per order item if already deducted.
     */
    public function deductStockForOrder(Order $order): array
    {
        return DB::transaction(function () use ($order) {
            $deductedItems = [];
            $order->loadMissing('items.product');

            foreach ($order->items as $item) {
                // Check if already deducted for this order item
                $alreadyLogged = PosInventoryMovement::where('order_id', $order->id)
                    ->where('product_id', $item->product_id)
                    ->where('type', 'sale')
                    ->exists();

                if ($alreadyLogged) {
                    continue;
                }

                $qty = (float) $item->quantity;
                $product = $item->product;

                // Deduct product stock if product tracks inventory
                if ($product && $product->tracks_inventory) {
                    $newQty = (float) $product->stock_quantity - $qty;
                    $product->update(['stock_quantity' => $newQty]);

                    PosInventoryMovement::create([
                        'vendor_id' => $order->vendor_id,
                        'location_id' => $order->location_id,
                        'product_id' => $product->id,
                        'product_variation_id' => null,
                        'order_id' => $order->id,
                        'user_id' => $order->cashier_id ?: $order->waiter_id,
                        'type' => 'sale',
                        'quantity_change' => -$qty,
                        'resulting_quantity' => $newQty,
                        'reason' => "POS Order #{$order->order_number}",
                    ]);

                    $deductedItems[] = [
                        'item_id' => $item->id,
                        'product_id' => $product->id,
                        'variation_id' => null,
                        'quantity_deducted' => $qty,
                        'remaining_stock' => $newQty,
                    ];
                }
            }

            return $deductedItems;
        });
    }

    /**
     * Restock inventory when an order or item is voided or cancelled.
     */
    public function restockForOrder(Order $order, ?string $reason = null): array
    {
        return DB::transaction(function () use ($order, $reason) {
            $restockedItems = [];
            $order->loadMissing('items.product');

            foreach ($order->items as $item) {
                $qty = (float) $item->quantity;
                $product = $item->product;

                if ($product && $product->tracks_inventory) {
                    $newQty = (float) $product->stock_quantity + $qty;
                    $product->update(['stock_quantity' => $newQty]);

                    PosInventoryMovement::create([
                        'vendor_id' => $order->vendor_id,
                        'location_id' => $order->location_id,
                        'product_id' => $product->id,
                        'product_variation_id' => null,
                        'order_id' => $order->id,
                        'user_id' => $order->cashier_id ?: $order->waiter_id,
                        'type' => 'refund',
                        'quantity_change' => $qty,
                        'resulting_quantity' => $newQty,
                        'reason' => $reason ?: "Order #{$order->order_number} Void/Cancel Restock",
                    ]);

                    $restockedItems[] = [
                        'product_id' => $product->id,
                        'variation_id' => null,
                        'quantity_restocked' => $qty,
                        'remaining_stock' => $newQty,
                    ];
                }
            }

            return $restockedItems;
        });
    }

    /**
     * Manually adjust stock for a product or variation.
     */
    public function adjustStock(
        Vendor $vendor,
        Product $product,
        float $quantityChange,
        string $type = 'adjustment',
        ?ProductVariation $variation = null,
        ?User $user = null,
        ?string $reason = null
    ): PosInventoryMovement {
        if ((int) $product->vendor_id !== (int) $vendor->id) {
            throw ValidationException::withMessages([
                'product' => 'Cross-tenant inventory operation prohibited.',
            ]);
        }

        return DB::transaction(function () use ($vendor, $product, $quantityChange, $type, $variation, $user, $reason) {
            if ($variation) {
                $newQty = (float) $variation->stock_quantity + $quantityChange;
                $variation->update([
                    'tracks_inventory' => true,
                    'stock_quantity' => $newQty,
                ]);

                return PosInventoryMovement::create([
                    'vendor_id' => $vendor->id,
                    'location_id' => $user?->location_id,
                    'product_id' => $product->id,
                    'product_variation_id' => $variation->id,
                    'user_id' => $user?->id,
                    'type' => $type,
                    'quantity_change' => $quantityChange,
                    'resulting_quantity' => $newQty,
                    'reason' => $reason ?: 'Manual stock adjustment',
                ]);
            }

            $newQty = (float) $product->stock_quantity + $quantityChange;
            $product->update([
                'tracks_inventory' => true,
                'stock_quantity' => $newQty,
            ]);

            return PosInventoryMovement::create([
                'vendor_id' => $vendor->id,
                'location_id' => $user?->location_id,
                'product_id' => $product->id,
                'product_variation_id' => null,
                'user_id' => $user?->id,
                'type' => $type,
                'quantity_change' => $quantityChange,
                'resulting_quantity' => $newQty,
                'reason' => $reason ?: 'Manual stock adjustment',
            ]);
        });
    }

    /**
     * Check if items in an order are currently in stock.
     */
    public function checkStockAvailability(Order $order): array
    {
        $outOfStock = [];
        $order->loadMissing('items.product');

        foreach ($order->items as $item) {
            $product = $item->product;
            $qty = (float) $item->quantity;

            if ($product && $product->tracks_inventory && ! $product->allow_out_of_stock_sale) {
                if ((float) $product->stock_quantity < $qty) {
                    $outOfStock[] = [
                        'item_id' => $item->id,
                        'name' => $product->name,
                        'requested' => $qty,
                        'available' => (float) $product->stock_quantity,
                    ];
                }
            }
        }

        return $outOfStock;
    }
}
