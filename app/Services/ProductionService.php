<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\InventoryBalance;
use App\Models\InventoryBatch;
use App\Models\InventoryTransaction;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderItem;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductionService
{
    public function __construct(
        protected InventoryLedgerService $ledgerService,
        protected UnitConversionService $conversionService,
        protected RecipeCostingService $costingService,
    ) {}

    /**
     * Create a new draft Production Order from a Recipe.
     *
     * @param  array{
     *     recipe_id: int,
     *     source_warehouse_id: int,
     *     destination_warehouse_id: int,
     *     planned_quantity: float,
     *     unit_id?: int,
     *     planned_start_at?: string,
     *     produced_by?: int,
     *     labor_cost?: float,
     *     overhead_cost?: float,
     *     notes?: string
     * }  $data
     */
    public function createOrder(Vendor $vendor, array $data, ?User $user = null): ProductionOrder
    {
        return DB::transaction(function () use ($vendor, $data, $user) {
            $recipe = Recipe::where('vendor_id', $vendor->id)
                ->with(['items.ingredient', 'items.subRecipe.latestCost'])
                ->findOrFail($data['recipe_id']);

            $sourceWarehouse = Warehouse::where('vendor_id', $vendor->id)
                ->findOrFail($data['source_warehouse_id']);

            $destWarehouse = Warehouse::where('vendor_id', $vendor->id)
                ->findOrFail($data['destination_warehouse_id']);

            $plannedQuantity = (float) $data['planned_quantity'];
            if ($plannedQuantity <= 0) {
                throw new InvalidArgumentException('Planned quantity must be greater than zero.');
            }

            $unitId = $data['unit_id'] ?? $recipe->yield_unit_id;
            $orderNumber = $this->generateOrderNumber($vendor);

            // Determine produced ingredient (semi-finished or finished)
            $producedIngredientId = $recipe->produced_ingredient_id;
            if (! $producedIngredientId && isset($data['produced_ingredient_id'])) {
                $producedIngredientId = $data['produced_ingredient_id'];
            }

            if (! $producedIngredientId) {
                // Try finding an ingredient with matching code or name
                $matchingIngredient = Ingredient::where('vendor_id', $vendor->id)
                    ->where(function ($q) use ($recipe) {
                        $q->where('sku', $recipe->code)
                            ->orWhere('name', $recipe->name);
                    })
                    ->first();

                if ($matchingIngredient) {
                    $producedIngredientId = $matchingIngredient->id;
                }
            }

            $order = ProductionOrder::create([
                'vendor_id' => $vendor->id,
                'order_number' => $orderNumber,
                'recipe_id' => $recipe->id,
                'produced_ingredient_id' => $producedIngredientId,
                'source_warehouse_id' => $sourceWarehouse->id,
                'destination_warehouse_id' => $destWarehouse->id,
                'planned_quantity' => $plannedQuantity,
                'unit_id' => $unitId,
                'status' => ProductionOrder::STATUS_DRAFT,
                'planned_start_at' => isset($data['planned_start_at']) ? Carbon::parse($data['planned_start_at'], $vendor->getTimezone())->utc() : now(),
                'produced_by' => $data['produced_by'] ?? $user?->id,
                'labor_cost' => (float) ($data['labor_cost'] ?? 0.0),
                'overhead_cost' => (float) ($data['overhead_cost'] ?? 0.0),
                'notes' => $data['notes'] ?? null,
            ]);

            // Bill of Materials scaling ratio
            $recipeYield = (float) $recipe->yield_quantity ?: 1.0;
            $scalingRatio = $plannedQuantity / $recipeYield;
            $totalEstimatedMaterialCost = 0.0;

            foreach ($recipe->items as $recipeItem) {
                $plannedItemQty = round((float) $recipeItem->gross_weight * $scalingRatio, 4);
                $unitCost = 0.0;

                if ($recipeItem->type === RecipeItem::TYPE_INGREDIENT && $recipeItem->ingredient) {
                    // Try looking up source warehouse inventory unit cost
                    $balance = InventoryBalance::where('warehouse_id', $sourceWarehouse->id)
                        ->where('ingredient_id', $recipeItem->ingredient_id)
                        ->first();

                    $unitCost = $balance && (float) $balance->unit_cost > 0
                        ? (float) $balance->unit_cost
                        : (float) ($recipeItem->ingredient->last_purchase_price ?? 0);
                } elseif ($recipeItem->type === RecipeItem::TYPE_SUB_RECIPE && $recipeItem->subRecipe) {
                    $unitCost = $recipeItem->subRecipe->latestCost
                        ? (float) $recipeItem->subRecipe->latestCost->portion_cost
                        : 0.0;
                }

                $itemTotalCost = round($plannedItemQty * $unitCost, 4);
                $totalEstimatedMaterialCost += $itemTotalCost;

                ProductionOrderItem::create([
                    'vendor_id' => $vendor->id,
                    'production_order_id' => $order->id,
                    'ingredient_id' => $recipeItem->ingredient_id,
                    'sub_recipe_id' => $recipeItem->sub_recipe_id,
                    'planned_quantity' => $plannedItemQty,
                    'unit_id' => $recipeItem->unit_id,
                    'unit_cost' => $unitCost,
                    'total_cost' => $itemTotalCost,
                ]);
            }

            $order->material_cost = $totalEstimatedMaterialCost;
            $order->total_cost = round($totalEstimatedMaterialCost + (float) $order->labor_cost + (float) $order->overhead_cost, 4);
            $order->unit_cost = $plannedQuantity > 0 ? round((float) $order->total_cost / $plannedQuantity, 4) : 0.0;
            $order->save();

            return $order->fresh(['items.ingredient', 'items.subRecipe', 'recipe', 'sourceWarehouse', 'destinationWarehouse']);
        });
    }

    /**
     * Start a draft production order.
     */
    public function startOrder(ProductionOrder $order, ?User $user = null): ProductionOrder
    {
        if (! $order->isDraft()) {
            throw new InvalidArgumentException("Cannot start production order in status [{$order->status}].");
        }

        $order->status = ProductionOrder::STATUS_IN_PROGRESS;
        $order->actual_start_at = now();
        if ($user) {
            $order->produced_by = $user->id;
        }
        $order->save();

        return $order;
    }

    /**
     * Complete a production order, deduct raw materials from source warehouse,
     * and receive finished/semi-finished goods into destination warehouse.
     *
     * @param  array{
     *     actual_quantity?: float,
     *     labor_cost?: float,
     *     overhead_cost?: float,
     *     batch_number?: string,
     *     expiry_date?: string,
     *     items?: array<int, array{id: int, actual_quantity?: float, waste_quantity?: float, notes?: string}>
     * }  $data
     */
    public function completeOrder(ProductionOrder $order, array $data = [], ?User $user = null): ProductionOrder
    {
        return DB::transaction(function () use ($order, $data, $user) {
            if ($order->isCompleted() || $order->isCancelled()) {
                throw new InvalidArgumentException("Cannot complete production order with status [{$order->status}].");
            }

            $actualQuantity = isset($data['actual_quantity'])
                ? (float) $data['actual_quantity']
                : (float) $order->planned_quantity;

            if ($actualQuantity <= 0) {
                throw new InvalidArgumentException('Actual produced quantity must be greater than zero.');
            }

            $order->load(['items.ingredient', 'items.subRecipe', 'recipe', 'sourceWarehouse', 'destinationWarehouse']);

            // Process items consumption and waste
            $itemsPayload = collect($data['items'] ?? [])->keyBy('id');
            $actualMaterialCost = 0.0;
            $issueItems = [];

            foreach ($order->items as $item) {
                $payload = $itemsPayload->get($item->id);

                $actualItemQty = $payload && isset($payload['actual_quantity'])
                    ? (float) $payload['actual_quantity']
                    : (float) $item->planned_quantity;

                $wasteItemQty = $payload && isset($payload['waste_quantity'])
                    ? (float) $payload['waste_quantity']
                    : 0.0;

                $itemCost = round($actualItemQty * (float) $item->unit_cost, 4);
                $actualMaterialCost += $itemCost;

                $item->actual_quantity = $actualItemQty;
                $item->waste_quantity = $wasteItemQty;
                $item->total_cost = $itemCost;
                if ($payload && isset($payload['notes'])) {
                    $item->notes = $payload['notes'];
                }
                $item->save();

                if ($item->ingredient_id && $actualItemQty > 0) {
                    $issueItems[] = [
                        'ingredient_id' => $item->ingredient_id,
                        'quantity' => $actualItemQty,
                        'unit_id' => $item->unit_id,
                        'notes' => "Consumed for production {$order->order_number}",
                    ];
                }
            }

            // Deduct raw materials from source warehouse
            $issueTransaction = null;
            if (! empty($issueItems)) {
                $issueTransaction = $this->ledgerService->recordIssue(
                    warehouse: $order->sourceWarehouse,
                    items: $issueItems,
                    type: InventoryTransaction::TYPE_ISSUE,
                    user: $user,
                    notes: "Raw materials consumed for Production Order {$order->order_number}",
                    idempotencyKey: "PROD-ISSUE-{$order->id}",
                    referenceType: ProductionOrder::class,
                    referenceId: $order->id,
                );
            }

            // Calculate final production costing
            $laborCost = isset($data['labor_cost']) ? (float) $data['labor_cost'] : (float) $order->labor_cost;
            $overheadCost = isset($data['overhead_cost']) ? (float) $data['overhead_cost'] : (float) $order->overhead_cost;
            $totalProductionCost = round($actualMaterialCost + $laborCost + $overheadCost, 4);
            $unitCost = $actualQuantity > 0 ? round($totalProductionCost / $actualQuantity, 4) : 0.0;

            // Stock produced item in destination warehouse if output ingredient is designated
            $receiptTransaction = null;
            $vendor = $order->vendor;
            $batchNumber = $data['batch_number'] ?? $order->batch_number ?? ('LOT-'.$vendor->getLocalTime()->format('Ymd').'-'.str_pad((string) $order->id, 4, '0', STR_PAD_LEFT));
            $expiryDate = $data['expiry_date'] ?? null;

            if (! $expiryDate && $order->recipe->shelf_life_hours) {
                $expiryDate = $vendor->getLocalTime()->addHours((int) $order->recipe->shelf_life_hours)->toDateString();
            }

            if ($order->produced_ingredient_id) {
                $receiptTransaction = $this->ledgerService->recordReceipt(
                    warehouse: $order->destinationWarehouse,
                    items: [[
                        'ingredient_id' => $order->produced_ingredient_id,
                        'quantity' => $actualQuantity,
                        'unit_id' => $order->unit_id,
                        'unit_cost' => $unitCost,
                        'notes' => "Output from Production Order {$order->order_number}",
                    ]],
                    user: $user,
                    notes: "Produced output from Production Order {$order->order_number}",
                    idempotencyKey: "PROD-RECEIPT-{$order->id}",
                    referenceType: ProductionOrder::class,
                    referenceId: $order->id,
                );

                // Create Inventory Batch for traceability
                InventoryBatch::create([
                    'vendor_id' => $order->vendor_id,
                    'batch_number' => $batchNumber,
                    'ingredient_id' => $order->produced_ingredient_id,
                    'warehouse_id' => $order->destination_warehouse_id,
                    'initial_quantity' => $actualQuantity,
                    'remaining_quantity' => $actualQuantity,
                    'unit_id' => $order->unit_id,
                    'unit_cost' => $unitCost,
                    'production_date' => now()->toDateString(),
                    'expiry_date' => $expiryDate,
                    'status' => InventoryBatch::STATUS_ACTIVE,
                    'source_type' => InventoryBatch::SOURCE_PRODUCTION,
                    'production_order_id' => $order->id,
                    'notes' => "Produced via Recipe: {$order->recipe->name}",
                ]);
            }

            // Update order record
            $order->actual_quantity = $actualQuantity;
            $order->material_cost = $actualMaterialCost;
            $order->labor_cost = $laborCost;
            $order->overhead_cost = $overheadCost;
            $order->total_cost = $totalProductionCost;
            $order->unit_cost = $unitCost;
            $order->yield_variance_percent = $order->calculateYieldVariance();
            $order->batch_number = $batchNumber;
            $order->expiry_date = $expiryDate;
            $order->issue_transaction_id = $issueTransaction?->id;
            $order->receipt_transaction_id = $receiptTransaction?->id;
            $order->completed_at = now();
            $order->status = ProductionOrder::STATUS_COMPLETED;
            $order->save();

            return $order->fresh([
                'items.ingredient',
                'items.subRecipe',
                'recipe',
                'batches',
                'issueTransaction',
                'receiptTransaction',
            ]);
        });
    }

    /**
     * Cancel a draft or in-progress production order.
     */
    public function cancelOrder(ProductionOrder $order, string $reason = ''): ProductionOrder
    {
        if ($order->isCompleted()) {
            throw new InvalidArgumentException('Cannot cancel an already completed production order.');
        }

        $order->status = ProductionOrder::STATUS_CANCELLED;
        if ($reason) {
            $order->notes = trim(($order->notes ?? '')."\n[Cancelled: {$reason}]");
        }
        $order->save();

        return $order;
    }

    /**
     * Generate unique sequential Production Order number: PRD-YYYYMMDD-XXXX.
     */
    public function generateOrderNumber(Vendor $vendor): string
    {
        $today = date('Ymd');
        $prefix = "PRD-{$today}-";

        $latest = ProductionOrder::where('vendor_id', $vendor->id)
            ->where('order_number', 'LIKE', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->value('order_number');

        $nextSeq = 1;
        if ($latest && preg_match('/-(\d+)$/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return $prefix.str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }
}
