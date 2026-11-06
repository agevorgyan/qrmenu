<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class OrderStockDepletionService
{
    public function __construct(
        protected InventoryLedgerService $ledgerService,
        protected UnitConversionService $conversionService,
    ) {}

    /**
     * Deduct inventory stock for all recipe components of an order.
     * Guaranteed idempotent by order ID: ORDER-DEPLETE-{$order->id}.
     */
    public function depleteOrder(Order $order, bool $allowNegative = true): ?InventoryTransaction
    {
        return DB::transaction(function () use ($order, $allowNegative) {
            $idempotencyKey = "ORDER-DEPLETE-{$order->id}";

            // 1. Idempotency check: prevent duplicate deductions
            $existing = InventoryTransaction::where('vendor_id', $order->vendor_id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            // 2. Resolve warehouse for kitchen / order fulfillment
            $warehouse = $this->resolveWarehouseForOrder($order);
            if (! $warehouse) {
                return null;
            }

            // 3. Eager load items, products and recipes
            $order->loadMissing([
                'items.product.recipe.items.ingredient.unit',
                'items.product.recipe.items.subRecipe.producedIngredient.unit',
                'items.product.recipe.items.subRecipe.items.ingredient.unit',
            ]);

            $rawItems = [];
            $affectedProductIds = [];

            foreach ($order->items as $orderItem) {
                $product = $orderItem->product;
                if (! $product) {
                    continue;
                }

                $affectedProductIds[] = $product->id;
                $recipe = $product->recipe ?? $product->recipes()->where('is_active', true)->first();

                if (! $recipe || $recipe->items->isEmpty()) {
                    continue;
                }

                $standardYield = (float) $recipe->yield_quantity ?: 1.0;
                $scaling = (float) $orderItem->quantity / $standardYield;

                foreach ($recipe->items as $recipeItem) {
                    if ($recipeItem->type === RecipeItem::TYPE_INGREDIENT && $recipeItem->ingredient) {
                        $qty = round((float) $recipeItem->gross_weight * $scaling, 4);
                        if ($qty > 0) {
                            $rawItems[] = [
                                'ingredient_id' => $recipeItem->ingredient_id,
                                'quantity' => $qty,
                                'unit_id' => $recipeItem->unit_id,
                                'notes' => "Order #{$order->order_number}: {$product->name} (x{$orderItem->quantity})",
                            ];
                        }
                    } elseif ($recipeItem->type === RecipeItem::TYPE_SUB_RECIPE && $recipeItem->subRecipe) {
                        $sub = $recipeItem->subRecipe;
                        if ($sub->produced_ingredient_id) {
                            // Deduct produced semi-finished product directly
                            $qty = round((float) $recipeItem->gross_weight * $scaling, 4);
                            if ($qty > 0) {
                                $rawItems[] = [
                                    'ingredient_id' => $sub->produced_ingredient_id,
                                    'quantity' => $qty,
                                    'unit_id' => $recipeItem->unit_id,
                                    'notes' => "Order #{$order->order_number}: Semi-Finished [{$sub->name}]",
                                ];
                            }
                        } else {
                            // Deduct underlying raw materials
                            $subYield = (float) $sub->yield_quantity ?: 1.0;
                            $subScaling = ((float) $recipeItem->gross_weight * $scaling) / $subYield;
                            foreach ($sub->items as $subItem) {
                                if ($subItem->ingredient) {
                                    $subQty = round((float) $subItem->gross_weight * $subScaling, 4);
                                    if ($subQty > 0) {
                                        $rawItems[] = [
                                            'ingredient_id' => $subItem->ingredient_id,
                                            'quantity' => $subQty,
                                            'unit_id' => $subItem->unit_id,
                                            'notes' => "Order #{$order->order_number}: Sub [{$sub->name} -> {$subItem->ingredient->name}]",
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if (empty($rawItems)) {
                return null;
            }

            // 4. Consolidate items by ingredient_id and unit_id
            $consolidated = [];
            foreach ($rawItems as $item) {
                $key = $item['ingredient_id'].'_'.$item['unit_id'];
                if (! isset($consolidated[$key])) {
                    $consolidated[$key] = $item;
                } else {
                    $consolidated[$key]['quantity'] += $item['quantity'];
                }
            }

            // 5. Book immutable issue ledger transaction
            $tx = $this->ledgerService->recordIssue(
                warehouse: $warehouse,
                items: array_values($consolidated),
                type: InventoryTransaction::TYPE_ORDER_CONSUMPTION,
                user: null,
                notes: "Automatic stock deduction for Order #{$order->order_number}",
                idempotencyKey: $idempotencyKey,
                referenceType: Order::class,
                referenceId: $order->id,
                allowNegative: $allowNegative
            );

            // 6. Recalculate stop-list availability for affected dishes
            if (! empty($affectedProductIds)) {
                $this->syncStopList((int) $order->vendor_id, array_unique($affectedProductIds), $warehouse);
            }

            return $tx;
        });
    }

    /**
     * Reverse stock deduction upon order cancellation / refund.
     * Idempotent by order ID: ORDER-REVERSE-{$order->id}.
     */
    public function reverseOrderDepletion(Order $order, string $reason = 'order_cancelled'): ?InventoryTransaction
    {
        return DB::transaction(function () use ($order, $reason) {
            $depletionKey = "ORDER-DEPLETE-{$order->id}";
            $reversalKey = "ORDER-REVERSE-{$order->id}";

            // Check if already reversed
            $existingReversal = InventoryTransaction::where('vendor_id', $order->vendor_id)
                ->where('idempotency_key', $reversalKey)
                ->first();

            if ($existingReversal) {
                return $existingReversal;
            }

            // Find original depletion transaction
            $depletionTx = InventoryTransaction::where('vendor_id', $order->vendor_id)
                ->where('idempotency_key', $depletionKey)
                ->with(['items', 'warehouse'])
                ->first();

            if (! $depletionTx || $depletionTx->items->isEmpty()) {
                return null;
            }

            $receiptItems = $depletionTx->items->map(function ($item) use ($order) {
                return [
                    'ingredient_id' => $item->ingredient_id,
                    'quantity' => abs((float) $item->quantity),
                    'unit_id' => $item->unit_id,
                    'unit_cost' => (float) $item->unit_cost,
                    'notes' => "Reversed stock from Order #{$order->order_number}",
                ];
            })->all();

            $reversalTx = $this->ledgerService->recordReceipt(
                warehouse: $depletionTx->warehouse,
                items: $receiptItems,
                user: null,
                notes: "Reversal for Order #{$order->order_number} [{$reason}]",
                idempotencyKey: $reversalKey,
                referenceType: Order::class,
                referenceId: $order->id
            );

            // Re-evaluate stop list for products in order
            $productIds = $order->items()->pluck('product_id')->filter()->unique()->all();
            if (! empty($productIds)) {
                $this->syncStopList((int) $order->vendor_id, $productIds, $depletionTx->warehouse);
            }

            return $reversalTx;
        });
    }

    /**
     * Calculate how many portions of a product can be prepared with currently available stock.
     */
    public function calculateAvailablePortions(Product $product, ?Warehouse $warehouse = null): ?int
    {
        $recipe = $product->recipe ?? $product->recipes()->where('is_active', true)->first();
        if (! $recipe || $recipe->items->isEmpty()) {
            return null; // Unlimited or non-recipe catalog item
        }

        $targetWarehouse = $warehouse ?? $this->resolveWarehouseForVendor((int) $product->vendor_id);
        if (! $targetWarehouse) {
            return null;
        }

        $maxPortions = PHP_INT_MAX;
        $yield = (float) $recipe->yield_quantity ?: 1.0;

        foreach ($recipe->items as $recipeItem) {
            $targetIngredientId = null;
            $grossWeight = (float) $recipeItem->gross_weight;

            if ($recipeItem->type === RecipeItem::TYPE_INGREDIENT && $recipeItem->ingredient) {
                $targetIngredientId = $recipeItem->ingredient_id;
            } elseif ($recipeItem->type === RecipeItem::TYPE_SUB_RECIPE && $recipeItem->subRecipe?->produced_ingredient_id) {
                $targetIngredientId = $recipeItem->subRecipe->produced_ingredient_id;
            }

            if ($targetIngredientId && $grossWeight > 0) {
                $balance = InventoryBalance::where('warehouse_id', $targetWarehouse->id)
                    ->where('ingredient_id', $targetIngredientId)
                    ->first();

                $onHand = $balance ? (float) $balance->quantity_on_hand : 0.0;
                $grossPerPortion = $grossWeight / $yield;
                $possible = (int) floor($onHand / $grossPerPortion);
                $maxPortions = min($maxPortions, max(0, $possible));
            }
        }

        return $maxPortions === PHP_INT_MAX ? null : $maxPortions;
    }

    /**
     * Sync Stop-List availability for products based on real-time stock.
     *
     * @return array{stopped: array<int>, restored: array<int>}
     */
    public function syncStopList(int $vendorId, array $productIds = [], ?Warehouse $warehouse = null): array
    {
        $query = Product::where('vendor_id', $vendorId)->with('recipe.items');
        if (! empty($productIds)) {
            $query->whereIn('id', $productIds);
        }

        $products = $query->get();
        $targetWarehouse = $warehouse ?? $this->resolveWarehouseForVendor($vendorId);
        if (! $targetWarehouse) {
            return ['stopped' => [], 'restored' => []];
        }

        $stopped = [];
        $restored = [];

        foreach ($products as $product) {
            $portions = $this->calculateAvailablePortions($product, $targetWarehouse);
            if ($portions === null) {
                continue;
            }

            if ($portions <= 0 && $product->is_available && ! ($product->allow_out_of_stock_sale ?? false)) {
                $product->is_available = false;
                $product->save();
                $stopped[] = $product->id;
            } elseif ($portions > 0 && ! $product->is_available) {
                $product->is_available = true;
                $product->save();
                $restored[] = $product->id;
            }
        }

        return ['stopped' => $stopped, 'restored' => $restored];
    }

    /**
     * Resolve default fulfillment warehouse for an order.
     */
    public function resolveWarehouseForOrder(Order $order): ?Warehouse
    {
        return Warehouse::where('vendor_id', $order->vendor_id)
            ->where('type', Warehouse::TYPE_KITCHEN)
            ->where('is_active', true)
            ->first()
            ?? Warehouse::where('vendor_id', $order->vendor_id)
                ->where('is_default', true)
                ->where('is_active', true)
                ->first()
            ?? Warehouse::where('vendor_id', $order->vendor_id)
                ->where('is_active', true)
                ->first();
    }

    /**
     * Resolve default fulfillment warehouse for a vendor.
     */
    public function resolveWarehouseForVendor(int $vendorId): ?Warehouse
    {
        return Warehouse::where('vendor_id', $vendorId)
            ->where('type', Warehouse::TYPE_KITCHEN)
            ->where('is_active', true)
            ->first()
            ?? Warehouse::where('vendor_id', $vendorId)
                ->where('is_default', true)
                ->where('is_active', true)
                ->first()
            ?? Warehouse::where('vendor_id', $vendorId)
                ->where('is_active', true)
                ->first();
    }
}
