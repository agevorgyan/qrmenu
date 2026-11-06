<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\InventoryTransactionItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryLedgerService
{
    public function __construct(
        protected UnitConversionService $conversionService
    ) {}

    /**
     * Get or create balance record with row-level lock.
     */
    public function getBalanceForUpdate(Warehouse $warehouse, Ingredient $ingredient, ?int $batchId = null): InventoryBalance
    {
        return InventoryBalance::withoutGlobalScopes()
            ->where('warehouse_id', $warehouse->id)
            ->where('ingredient_id', $ingredient->id)
            ->where(function ($q) use ($batchId) {
                if ($batchId === null) {
                    $q->whereNull('batch_id');
                } else {
                    $q->where('batch_id', $batchId);
                }
            })
            ->lockForUpdate()
            ->firstOr(function () use ($warehouse, $ingredient, $batchId) {
                return InventoryBalance::create([
                    'vendor_id' => $warehouse->vendor_id,
                    'warehouse_id' => $warehouse->id,
                    'ingredient_id' => $ingredient->id,
                    'batch_id' => $batchId,
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                    'unit_cost' => $ingredient->current_cost ?: 0,
                    'total_value' => 0,
                    'last_movement_at' => now(),
                ]);
            });
    }

    /**
     * Record a Stock Receipt (increases on-hand stock and updates weighted average unit cost).
     *
     * @param  array<int, array{ingredient_id: int, quantity: float, unit_id: int, unit_cost?: float, notes?: string}>  $items
     */
    public function recordReceipt(
        Warehouse $warehouse,
        array $items,
        ?User $user = null,
        ?string $notes = null,
        ?string $idempotencyKey = null
    ): InventoryTransaction {
        return DB::transaction(function () use ($warehouse, $items, $user, $notes, $idempotencyKey) {
            if ($idempotencyKey) {
                $existing = InventoryTransaction::where('vendor_id', $warehouse->vendor_id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            $tx = InventoryTransaction::create([
                'vendor_id' => $warehouse->vendor_id,
                'transaction_number' => InventoryTransaction::generateTransactionNumber('RCV'),
                'warehouse_id' => $warehouse->id,
                'type' => InventoryTransaction::TYPE_RECEIPT,
                'status' => InventoryTransaction::STATUS_COMPLETED,
                'date' => now()->toDateString(),
                'user_id' => $user?->id,
                'approved_by' => $user?->id,
                'notes' => $notes,
                'idempotency_key' => $idempotencyKey,
            ]);

            $totalTxAmount = 0.0;

            foreach ($items as $itemData) {
                $ingredient = Ingredient::findOrFail($itemData['ingredient_id']);
                $itemUnit = Unit::findOrFail($itemData['unit_id']);
                $rawQty = (float) $itemData['quantity'];
                $itemCost = isset($itemData['unit_cost']) ? (float) $itemData['unit_cost'] : (float) $ingredient->current_cost;

                // Normalize quantity to ingredient primary unit
                $normalizedQty = $this->conversionService->convert($rawQty, $itemUnit, $ingredient->unit_id, $ingredient);
                $costPerPrimaryUnit = $rawQty > 0 ? ($rawQty * $itemCost) / $normalizedQty : $itemCost;
                $lineTotal = $rawQty * $itemCost;
                $totalTxAmount += $lineTotal;

                // Lock balance and apply Weighted Average Cost formula
                $balance = $this->getBalanceForUpdate($warehouse, $ingredient);

                $currentQty = (float) $balance->quantity_on_hand;
                $currentCost = (float) $balance->unit_cost;
                $newQty = $currentQty + $normalizedQty;

                $newAvgCost = $newQty > 0
                    ? (($currentQty * $currentCost) + ($normalizedQty * $costPerPrimaryUnit)) / $newQty
                    : $costPerPrimaryUnit;

                $balance->update([
                    'quantity_on_hand' => $newQty,
                    'unit_cost' => $newAvgCost,
                    'total_value' => $newQty * $newAvgCost,
                    'last_movement_at' => now(),
                ]);

                // Update ingredient latest purchase price and current cost
                $ingredient->update([
                    'last_purchase_price' => $costPerPrimaryUnit,
                    'current_cost' => $newAvgCost,
                ]);

                InventoryTransactionItem::create([
                    'transaction_id' => $tx->id,
                    'ingredient_id' => $ingredient->id,
                    'unit_id' => $itemUnit->id,
                    'quantity' => $rawQty,
                    'unit_cost' => $itemCost,
                    'total_cost' => $lineTotal,
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            $tx->update(['total_amount' => $totalTxAmount]);

            return $tx;
        });
    }

    /**
     * Record a Stock Issue / Deduction (decreases on-hand stock).
     *
     * @param  array<int, array{ingredient_id: int, quantity: float, unit_id: int, notes?: string}>  $items
     */
    public function recordIssue(
        Warehouse $warehouse,
        array $items,
        string $type = InventoryTransaction::TYPE_ISSUE,
        ?User $user = null,
        ?string $notes = null,
        ?string $idempotencyKey = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): InventoryTransaction {
        return DB::transaction(function () use ($warehouse, $items, $type, $user, $notes, $idempotencyKey, $referenceType, $referenceId) {
            if ($idempotencyKey) {
                $existing = InventoryTransaction::where('vendor_id', $warehouse->vendor_id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            $tx = InventoryTransaction::create([
                'vendor_id' => $warehouse->vendor_id,
                'transaction_number' => InventoryTransaction::generateTransactionNumber('ISS'),
                'warehouse_id' => $warehouse->id,
                'type' => $type,
                'status' => InventoryTransaction::STATUS_COMPLETED,
                'date' => now()->toDateString(),
                'user_id' => $user?->id,
                'approved_by' => $user?->id,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'idempotency_key' => $idempotencyKey,
            ]);

            $totalTxAmount = 0.0;

            foreach ($items as $itemData) {
                $ingredient = Ingredient::findOrFail($itemData['ingredient_id']);
                $itemUnit = Unit::findOrFail($itemData['unit_id']);
                $rawQty = (float) $itemData['quantity'];

                $normalizedQty = $this->conversionService->convert($rawQty, $itemUnit, $ingredient->unit_id, $ingredient);

                $balance = $this->getBalanceForUpdate($warehouse, $ingredient);
                $available = (float) $balance->quantity_on_hand;

                if ($normalizedQty > $available) {
                    throw new InvalidArgumentException(
                        "Insufficient stock for [{$ingredient->name}] in warehouse [{$warehouse->name}]. Requested: {$normalizedQty}, Available: {$available}."
                    );
                }

                $newQty = $available - $normalizedQty;
                $unitCost = (float) $balance->unit_cost;
                $lineTotal = $normalizedQty * $unitCost;
                $totalTxAmount += $lineTotal;

                $balance->update([
                    'quantity_on_hand' => $newQty,
                    'total_value' => $newQty * $unitCost,
                    'last_movement_at' => now(),
                ]);

                InventoryTransactionItem::create([
                    'transaction_id' => $tx->id,
                    'ingredient_id' => $ingredient->id,
                    'unit_id' => $itemUnit->id,
                    'quantity' => -$rawQty,
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineTotal,
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            $tx->update(['total_amount' => $totalTxAmount]);

            return $tx;
        });
    }

    /**
     * Record a Stock Transfer between two warehouses.
     *
     * @param  array<int, array{ingredient_id: int, quantity: float, unit_id: int, notes?: string}>  $items
     */
    public function recordTransfer(
        Warehouse $sourceWarehouse,
        Warehouse $targetWarehouse,
        array $items,
        ?User $user = null,
        ?string $notes = null
    ): InventoryTransaction {
        if ($sourceWarehouse->id === $targetWarehouse->id) {
            throw new InvalidArgumentException('Source and target warehouses cannot be identical.');
        }

        if ((int) $sourceWarehouse->vendor_id !== (int) $targetWarehouse->vendor_id) {
            throw new InvalidArgumentException('Cross-tenant warehouse transfers are forbidden.');
        }

        return DB::transaction(function () use ($sourceWarehouse, $targetWarehouse, $items, $user, $notes) {
            $tx = InventoryTransaction::create([
                'vendor_id' => $sourceWarehouse->vendor_id,
                'transaction_number' => InventoryTransaction::generateTransactionNumber('TRF'),
                'warehouse_id' => $sourceWarehouse->id,
                'target_warehouse_id' => $targetWarehouse->id,
                'type' => InventoryTransaction::TYPE_TRANSFER,
                'status' => InventoryTransaction::STATUS_COMPLETED,
                'date' => now()->toDateString(),
                'user_id' => $user?->id,
                'approved_by' => $user?->id,
                'notes' => $notes,
            ]);

            $totalTxAmount = 0.0;

            foreach ($items as $itemData) {
                $ingredient = Ingredient::findOrFail($itemData['ingredient_id']);
                $itemUnit = Unit::findOrFail($itemData['unit_id']);
                $rawQty = (float) $itemData['quantity'];

                $normalizedQty = $this->conversionService->convert($rawQty, $itemUnit, $ingredient->unit_id, $ingredient);

                // Deduct from source warehouse
                $sourceBalance = $this->getBalanceForUpdate($sourceWarehouse, $ingredient);
                if ($normalizedQty > (float) $sourceBalance->quantity_on_hand) {
                    throw new InvalidArgumentException("Insufficient stock in source warehouse for [{$ingredient->name}].");
                }

                $sourceCost = (float) $sourceBalance->unit_cost;
                $sourceNewQty = (float) $sourceBalance->quantity_on_hand - $normalizedQty;
                $sourceBalance->update([
                    'quantity_on_hand' => $sourceNewQty,
                    'total_value' => $sourceNewQty * $sourceCost,
                    'last_movement_at' => now(),
                ]);

                // Add to target warehouse
                $targetBalance = $this->getBalanceForUpdate($targetWarehouse, $ingredient);
                $targetCurrentQty = (float) $targetBalance->quantity_on_hand;
                $targetCurrentCost = (float) $targetBalance->unit_cost;
                $targetNewQty = $targetCurrentQty + $normalizedQty;

                $targetAvgCost = $targetNewQty > 0
                    ? (($targetCurrentQty * $targetCurrentCost) + ($normalizedQty * $sourceCost)) / $targetNewQty
                    : $sourceCost;

                $targetBalance->update([
                    'quantity_on_hand' => $targetNewQty,
                    'unit_cost' => $targetAvgCost,
                    'total_value' => $targetNewQty * $targetAvgCost,
                    'last_movement_at' => now(),
                ]);

                $lineTotal = $normalizedQty * $sourceCost;
                $totalTxAmount += $lineTotal;

                InventoryTransactionItem::create([
                    'transaction_id' => $tx->id,
                    'ingredient_id' => $ingredient->id,
                    'unit_id' => $itemUnit->id,
                    'quantity' => $rawQty,
                    'unit_cost' => $sourceCost,
                    'total_cost' => $lineTotal,
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            $tx->update(['total_amount' => $totalTxAmount]);

            return $tx;
        });
    }

    /**
     * Record a Stock Adjustment (e.g. discrepancy found during count).
     */
    public function recordAdjustment(
        Warehouse $warehouse,
        Ingredient $ingredient,
        float $actualQuantity,
        ?string $reason = null,
        ?User $user = null
    ): InventoryTransaction {
        return DB::transaction(function () use ($warehouse, $ingredient, $actualQuantity, $reason, $user) {
            $balance = $this->getBalanceForUpdate($warehouse, $ingredient);
            $currentQty = (float) $balance->quantity_on_hand;
            $diff = $actualQuantity - $currentQty;

            $tx = InventoryTransaction::create([
                'vendor_id' => $warehouse->vendor_id,
                'transaction_number' => InventoryTransaction::generateTransactionNumber('ADJ'),
                'warehouse_id' => $warehouse->id,
                'type' => InventoryTransaction::TYPE_ADJUSTMENT,
                'status' => InventoryTransaction::STATUS_COMPLETED,
                'date' => now()->toDateString(),
                'user_id' => $user?->id,
                'approved_by' => $user?->id,
                'notes' => $reason ?: "Inventory Adjustment. Old: {$currentQty}, New: {$actualQuantity}, Diff: {$diff}",
                'total_amount' => abs($diff * (float) $balance->unit_cost),
            ]);

            $balance->update([
                'quantity_on_hand' => $actualQuantity,
                'total_value' => $actualQuantity * (float) $balance->unit_cost,
                'last_movement_at' => now(),
            ]);

            InventoryTransactionItem::create([
                'transaction_id' => $tx->id,
                'ingredient_id' => $ingredient->id,
                'unit_id' => $ingredient->unit_id,
                'quantity' => $diff,
                'unit_cost' => $balance->unit_cost,
                'total_cost' => abs($diff * (float) $balance->unit_cost),
                'notes' => $reason,
            ]);

            return $tx;
        });
    }

    /**
     * Reserve stock for order or production.
     */
    public function reserveStock(Warehouse $warehouse, Ingredient $ingredient, float $quantity): void
    {
        DB::transaction(function () use ($warehouse, $ingredient, $quantity) {
            $balance = $this->getBalanceForUpdate($warehouse, $ingredient);
            $available = $balance->available_quantity;

            if ($quantity > $available) {
                throw new InvalidArgumentException(
                    "Cannot reserve {$quantity} of [{$ingredient->name}]. Only {$available} available in [{$warehouse->name}]."
                );
            }

            $balance->increment('quantity_reserved', $quantity);
        });
    }

    /**
     * Release stock reservation.
     */
    public function releaseReservation(Warehouse $warehouse, Ingredient $ingredient, float $quantity): void
    {
        DB::transaction(function () use ($warehouse, $ingredient, $quantity) {
            $balance = $this->getBalanceForUpdate($warehouse, $ingredient);
            $newReserved = max(0.0, (float) $balance->quantity_reserved - $quantity);
            $balance->update(['quantity_reserved' => $newReserved]);
        });
    }

    /**
     * Check stock availability for an ingredient in a specific warehouse or across all vendor warehouses.
     */
    public function getAvailableStock(Ingredient $ingredient, ?Warehouse $warehouse = null): float
    {
        if ($warehouse) {
            $balance = InventoryBalance::withoutGlobalScopes()
                ->where('warehouse_id', $warehouse->id)
                ->where('ingredient_id', $ingredient->id)
                ->first();

            return $balance ? (float) $balance->available_quantity : 0.0;
        }

        return (float) $ingredient->total_stock_available;
    }
}
