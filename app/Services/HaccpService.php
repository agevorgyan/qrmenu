<?php

namespace App\Services;

use App\Models\HaccpCcp;
use App\Models\HaccpChecklist;
use App\Models\HaccpTemperatureLog;
use App\Models\InventoryBatch;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HaccpService
{
    /**
     * Seed recommended standard HACCP CCP points for a restaurant tenant.
     *
     * @return Collection<int, HaccpCcp>
     */
    public function seedDefaultCcps(Vendor $vendor): Collection
    {
        $defaults = [
            [
                'name' => 'Chilled Goods Delivery Inspection',
                'code' => 'CCP-1-RECEIVE-CHILLED',
                'type' => HaccpCcp::TYPE_RECEIVING,
                'min_value' => 0.0,
                'max_value' => 4.0,
                'unit' => '°C',
                'critical_limit_description' => 'Chilled food received must be between 0°C and 4°C.',
                'corrective_action_instructions' => 'Reject delivery if temperature exceeds 5°C.',
                'frequency' => 'per_delivery',
            ],
            [
                'name' => 'Frozen Goods Delivery Inspection',
                'code' => 'CCP-1-RECEIVE-FROZEN',
                'type' => HaccpCcp::TYPE_RECEIVING,
                'min_value' => null,
                'max_value' => -18.0,
                'unit' => '°C',
                'critical_limit_description' => 'Frozen food received must be at or below -18°C.',
                'corrective_action_instructions' => 'Reject delivery if signs of thawing or temperature above -15°C.',
                'frequency' => 'per_delivery',
            ],
            [
                'name' => 'Refrigerated Storage (Walk-in Cooler)',
                'code' => 'CCP-2-STORAGE-FRIDGE',
                'type' => HaccpCcp::TYPE_STORAGE,
                'min_value' => 0.0,
                'max_value' => 4.0,
                'unit' => '°C',
                'critical_limit_description' => 'Cold rooms and refrigerators must remain between 0°C and 4°C.',
                'corrective_action_instructions' => 'Move items to backup cooler and service refrigeration unit if above 5°C.',
                'frequency' => 'twice_daily',
            ],
            [
                'name' => 'Deep Freezer Storage',
                'code' => 'CCP-2-STORAGE-FREEZER',
                'type' => HaccpCcp::TYPE_STORAGE,
                'min_value' => null,
                'max_value' => -18.0,
                'unit' => '°C',
                'critical_limit_description' => 'Freezers must remain at or below -18°C.',
                'corrective_action_instructions' => 'Check defrost cycle and door seals if temperature rises above -15°C.',
                'frequency' => 'twice_daily',
            ],
            [
                'name' => 'Cooking & Thermal Processing Core Temp',
                'code' => 'CCP-3-COOKING-CORE',
                'type' => HaccpCcp::TYPE_COOKING,
                'min_value' => 75.0,
                'max_value' => null,
                'unit' => '°C',
                'critical_limit_description' => 'Internal core temperature must reach at least 75°C for 30 seconds.',
                'corrective_action_instructions' => 'Continue cooking until internal probe reads >= 75°C.',
                'frequency' => 'per_batch',
            ],
            [
                'name' => 'Hot Food Holding Line',
                'code' => 'CCP-4-HOT-HOLDING',
                'type' => HaccpCcp::TYPE_HOLDING,
                'min_value' => 63.0,
                'max_value' => null,
                'unit' => '°C',
                'critical_limit_description' => 'Hot held buffet/kitchen items must be maintained at or above 63°C.',
                'corrective_action_instructions' => 'Reheat rapidly to 75°C or discard if held below 63°C for over 2 hours.',
                'frequency' => 'twice_daily',
            ],
            [
                'name' => 'Rapid Cooling / Blast Chilling',
                'code' => 'CCP-5-RAPID-COOLING',
                'type' => HaccpCcp::TYPE_COOLING,
                'min_value' => null,
                'max_value' => 10.0,
                'unit' => '°C',
                'critical_limit_description' => 'Food must cool from 60°C to 10°C in less than 2 hours.',
                'corrective_action_instructions' => 'Use ice bath or blast chiller. Discard if cooling time exceeded.',
                'frequency' => 'per_batch',
            ],
        ];

        $ccps = collect();
        foreach ($defaults as $item) {
            $ccp = HaccpCcp::firstOrCreate(
                ['vendor_id' => $vendor->id, 'code' => $item['code']],
                $item
            );
            $ccps->push($ccp);
        }

        return $ccps;
    }

    /**
     * Record a new temperature measurement log.
     *
     * @param  array{
     *     ccp_id?: int,
     *     warehouse_id?: int,
     *     ingredient_id?: int,
     *     batch_id?: int,
     *     equipment_name?: string,
     *     measured_value: float,
     *     unit?: string,
     *     corrective_action_taken?: string,
     *     notes?: string,
     *     logged_at?: string
     * }  $data
     */
    public function recordTemperature(Vendor $vendor, array $data, ?User $user = null): HaccpTemperatureLog
    {
        $measuredValue = (float) $data['measured_value'];
        $isBreached = false;

        $ccp = null;
        if (! empty($data['ccp_id'])) {
            $ccp = HaccpCcp::where('vendor_id', $vendor->id)->find($data['ccp_id']);
            if ($ccp) {
                $isBreached = $ccp->isBreached($measuredValue);
            }
        }

        return HaccpTemperatureLog::create([
            'vendor_id' => $vendor->id,
            'ccp_id' => $ccp?->id,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'ingredient_id' => $data['ingredient_id'] ?? null,
            'batch_id' => $data['batch_id'] ?? null,
            'equipment_name' => $data['equipment_name'] ?? null,
            'measured_value' => $measuredValue,
            'unit' => $data['unit'] ?? ($ccp?->unit ?? '°C'),
            'is_breached' => $isBreached,
            'corrective_action_taken' => $isBreached ? ($data['corrective_action_taken'] ?? null) : null,
            'logged_by' => $user?->id,
            'logged_at' => isset($data['logged_at']) ? Carbon::parse($data['logged_at']) : now(),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Record a completed HACCP hygiene/shift checklist.
     */
    public function createChecklist(Vendor $vendor, array $data, ?User $user = null): HaccpChecklist
    {
        $items = $data['items'] ?? [];
        $failedCount = collect($items)->filter(fn ($i) => empty($i['is_passed']))->count();
        $status = $failedCount > 0 ? HaccpChecklist::STATUS_FAILED : HaccpChecklist::STATUS_COMPLETED;

        return HaccpChecklist::create([
            'vendor_id' => $vendor->id,
            'title' => $data['title'],
            'type' => $data['type'] ?? HaccpChecklist::TYPE_OPENING,
            'shift' => $data['shift'] ?? 'morning',
            'completed_by' => $user?->id,
            'completed_at' => now(),
            'status' => $status,
            'notes' => $data['notes'] ?? null,
            'items' => $items,
        ]);
    }

    /**
     * Retrieve all active inventory batches expiring within the given threshold (days),
     * categorized into expired, critical (<=3 days) and warning (<=days).
     *
     * @return array{
     *     expired: Collection<int, InventoryBatch>,
     *     critical: Collection<int, InventoryBatch>,
     *     warning: Collection<int, InventoryBatch>,
     *     total_expiring: int
     * }
     */
    public function getExpiringBatches(Vendor $vendor, int $days = 7): array
    {
        $today = now()->startOfDay();
        $threshold = now()->addDays($days)->endOfDay();

        $batches = InventoryBatch::where('vendor_id', $vendor->id)
            ->where('remaining_quantity', '>', 0)
            ->where('status', InventoryBatch::STATUS_ACTIVE)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $threshold)
            ->with(['ingredient.unit', 'warehouse', 'unit'])
            ->orderBy('expiry_date', 'asc')
            ->get();

        $expired = $batches->filter(fn ($b) => $b->expiry_date->isBefore($today));
        $critical = $batches->filter(fn ($b) => ! $b->expiry_date->isBefore($today) && $today->diffInDays($b->expiry_date->startOfDay()) <= 3);
        $warning = $batches->filter(fn ($b) => ! $b->expiry_date->isBefore($today) && $today->diffInDays($b->expiry_date->startOfDay()) > 3);

        return [
            'expired' => $expired->values(),
            'critical' => $critical->values(),
            'warning' => $warning->values(),
            'total_expiring' => $batches->count(),
        ];
    }

    /**
     * Perform bidirectional traceability and recall audit for a batch number.
     *
     * @return array{
     *     batch: ?InventoryBatch,
     *     source: ?array,
     *     current_stock: float,
     *     downstream_productions: Collection,
     *     customer_orders: Collection
     * }
     */
    public function traceBatch(Vendor $vendor, string $batchNumber): array
    {
        $batch = InventoryBatch::where('vendor_id', $vendor->id)
            ->where('batch_number', $batchNumber)
            ->with(['ingredient', 'warehouse', 'unit', 'supplier', 'purchaseOrder.supplier', 'productionOrder.recipe'])
            ->first();

        if (! $batch) {
            return [
                'batch' => null,
                'source' => null,
                'current_stock' => 0.0,
                'downstream_productions' => collect(),
                'customer_orders' => collect(),
            ];
        }

        // 1. Identify Source
        $source = null;
        if ($batch->source_type === InventoryBatch::SOURCE_PURCHASE && ($batch->purchaseOrder || $batch->supplier)) {
            $source = [
                'type' => 'purchase',
                'po_number' => $batch->purchaseOrder?->po_number ?? 'DIRECT-PURCHASE',
                'supplier_name' => $batch->purchaseOrder?->supplier?->name ?? $batch->supplier?->name,
                'received_date' => $batch->production_date?->toDateString(),
            ];
        } elseif ($batch->source_type === InventoryBatch::SOURCE_PRODUCTION && $batch->productionOrder) {
            $source = [
                'type' => 'production',
                'order_number' => $batch->productionOrder->order_number,
                'recipe_name' => $batch->productionOrder->recipe?->name,
                'produced_at' => $batch->productionOrder->completed_at?->toDateTimeString(),
            ];
        }

        // 2. Discover downstream productions that consumed this ingredient batch
        $downstreamProductions = collect();
        if ($batch->productionOrder) {
            $downstreamProductions->push($batch->productionOrder);
        }

        // 3. Trace customer orders that ordered dishes containing this ingredient
        $orders = Order::where('vendor_id', $vendor->id)
            ->where('created_at', '>=', $batch->created_at->startOfDay())
            ->whereHas('items.product.recipe.items', function ($q) use ($batch) {
                $q->where('ingredient_id', $batch->ingredient_id);
            })
            ->with(['items.product'])
            ->orderBy('id', 'desc')
            ->take(50)
            ->get();

        return [
            'batch' => $batch,
            'source' => $source,
            'current_stock' => (float) $batch->remaining_quantity,
            'downstream_productions' => $downstreamProductions,
            'customer_orders' => $orders,
        ];
    }
}
