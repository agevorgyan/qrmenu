<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\InventoryBatch;
use App\Models\InventoryTransaction;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WasteLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WasteManagementService
{
    public function __construct(
        protected InventoryLedgerService $ledgerService,
        protected UnitConversionService $conversionService,
    ) {}

    /**
     * Record an inventory waste, spoilage, or write-off event.
     * Depletes stock in the warehouse via signed ledger and logs financial loss.
     *
     * @param  array{
     *     warehouse_id: int,
     *     ingredient_id: int,
     *     batch_id?: ?int,
     *     quantity: float,
     *     unit_id?: ?int,
     *     unit_cost?: ?float,
     *     reason: string,
     *     action_taken?: ?string,
     *     authorized_by?: ?int,
     *     notes?: ?string,
     *     logged_at?: ?string
     * }  $data
     */
    public function recordWaste(Vendor $vendor, array $data, ?User $user = null): WasteLog
    {
        return DB::transaction(function () use ($vendor, $data, $user) {
            $ingredient = Ingredient::where('vendor_id', $vendor->id)->findOrFail($data['ingredient_id']);
            $warehouse = Warehouse::where('vendor_id', $vendor->id)->findOrFail($data['warehouse_id']);

            $quantity = (float) $data['quantity'];
            if ($quantity <= 0) {
                throw new InvalidArgumentException('Waste quantity must be greater than zero.');
            }

            $unitId = $data['unit_id'] ?? $ingredient->unit_id;
            $unit = Unit::findOrFail($unitId);

            // Convert quantity to ingredient's base unit for stock deduction
            $baseQuantity = $quantity;
            if ($unit->id !== $ingredient->unit_id) {
                $baseQuantity = $this->conversionService->convert(
                    $quantity,
                    $unit,
                    $ingredient->unit,
                    $ingredient->id,
                    $vendor->id
                );
            }

            // Batch verification
            $batch = null;
            if (! empty($data['batch_id'])) {
                $batch = InventoryBatch::where('vendor_id', $vendor->id)
                    ->where('ingredient_id', $ingredient->id)
                    ->findOrFail($data['batch_id']);
            }

            // Unit cost resolution
            $unitCost = isset($data['unit_cost']) && $data['unit_cost'] > 0
                ? (float) $data['unit_cost']
                : ($batch?->unit_cost > 0 ? (float) $batch->unit_cost : (float) ($ingredient->last_purchase_price ?? 0.0));

            $totalCost = round($baseQuantity * $unitCost, 4);
            $wasteNumber = WasteLog::generateWasteNumber($vendor->id);

            // Deplete warehouse stock via ledger
            $items = [
                [
                    'ingredient_id' => $ingredient->id,
                    'quantity' => $baseQuantity,
                    'unit_id' => $ingredient->unit_id,
                ],
            ];

            $transaction = $this->ledgerService->recordIssue(
                $warehouse,
                $items,
                InventoryTransaction::TYPE_ISSUE,
                $user,
                sprintf('Waste write-off (%s): %s', $data['reason'], $data['notes'] ?? ''),
                "WASTE-{$wasteNumber}",
                'waste_log',
                null,
                false
            );

            // Deduct batch quantity if batch specified
            if ($batch) {
                $batch->remaining_quantity = max(0, (float) $batch->remaining_quantity - $baseQuantity);
                if ($batch->remaining_quantity <= 0) {
                    $batch->status = $data['reason'] === WasteLog::REASON_EXPIRATION
                        ? InventoryBatch::STATUS_EXPIRED
                        : InventoryBatch::STATUS_DEPLETED;
                }
                $batch->save();
            }

            return WasteLog::create([
                'vendor_id' => $vendor->id,
                'waste_number' => $wasteNumber,
                'warehouse_id' => $warehouse->id,
                'ingredient_id' => $ingredient->id,
                'batch_id' => $batch?->id,
                'quantity' => $quantity,
                'unit_id' => $unit->id,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'reason' => $data['reason'],
                'action_taken' => $data['action_taken'] ?? WasteLog::ACTION_DISCARDED,
                'logged_by' => $user?->id,
                'authorized_by' => $data['authorized_by'] ?? null,
                'logged_at' => isset($data['logged_at']) ? Carbon::parse($data['logged_at']) : now(),
                'notes' => $data['notes'] ?? null,
                'inventory_transaction_id' => $transaction->id,
            ]);
        });
    }

    /**
     * Get aggregate waste summary KPIs and breakdown by reason.
     *
     * @return array{
     *     total_loss_cost: float,
     *     total_records: int,
     *     by_reason: array<string, array{count: int, cost: float, percentage: float}>,
     *     top_wasted_ingredients: array<int, array{name: string, cost: float, quantity: float, unit: string}>
     * }
     */
    public function getWasteSummary(Vendor $vendor, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->startOfMonth();
        $to = $to ?? now()->endOfDay();

        $logs = WasteLog::where('vendor_id', $vendor->id)
            ->whereBetween('logged_at', [$from, $to])
            ->with(['ingredient.unit', 'unit'])
            ->get();

        $totalCost = (float) $logs->sum('total_cost');
        $totalRecords = $logs->count();

        // Breakdown by reason
        $byReason = [];
        $reasons = [
            WasteLog::REASON_SPOILAGE,
            WasteLog::REASON_EXPIRATION,
            WasteLog::REASON_PREP_MISTAKE,
            WasteLog::REASON_BURN_SPILL,
            WasteLog::REASON_DAMAGE,
            WasteLog::REASON_STORAGE_FAILURE,
            WasteLog::REASON_CUSTOMER_COMPLAINT,
            WasteLog::REASON_OTHER,
        ];

        foreach ($reasons as $reason) {
            $reasonLogs = $logs->where('reason', $reason);
            $reasonCost = (float) $reasonLogs->sum('total_cost');
            $byReason[$reason] = [
                'count' => $reasonLogs->count(),
                'cost' => $reasonCost,
                'percentage' => $totalCost > 0 ? round(($reasonCost / $totalCost) * 100, 1) : 0.0,
            ];
        }

        // Top wasted ingredients
        $topIngredients = $logs->groupBy('ingredient_id')
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'name' => $first->ingredient->name,
                    'cost' => (float) $group->sum('total_cost'),
                    'quantity' => (float) $group->sum('quantity'),
                    'unit' => $first->ingredient->unit?->symbol ?? 'kg',
                ];
            })
            ->sortByDesc('cost')
            ->values()
            ->take(5)
            ->all();

        return [
            'total_loss_cost' => $totalCost,
            'total_records' => $totalRecords,
            'by_reason' => $byReason,
            'top_wasted_ingredients' => $topIngredients,
        ];
    }
}
