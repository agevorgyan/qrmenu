<?php

namespace Tests\Unit;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\InventoryBatch;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WasteLog;
use App\Services\InventoryLedgerService;
use App\Services\WasteManagementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WasteManagementServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected WasteManagementService $wasteService;

    protected InventoryLedgerService $ledgerService;

    protected Vendor $vendor;

    protected User $user;

    protected Warehouse $warehouse;

    protected Unit $kg;

    protected Ingredient $salmon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = app(InventoryLedgerService::class);
        $this->wasteService = app(WasteManagementService::class);

        $this->vendor = Vendor::create([
            'name' => 'Seafood Bistro',
            'slug' => 'seafood-bistro-'.uniqid(),
            'email' => 'seafood-'.uniqid().'@test.com',
            'phone' => '+37410998877',
            'currency' => 'AMD',
        ]);

        $this->user = User::create([
            'name' => 'Kitchen Manager',
            'email' => 'chef-'.uniqid().'@test.com',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Fish Walk-in Chiller',
            'code' => 'WH-FISH-01',
            'type' => Warehouse::TYPE_MAIN,
            'is_default' => true,
        ]);

        $this->kg = Unit::whereNull('vendor_id')->where('code', 'kg')->firstOrFail();

        $cat = IngredientCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Fresh Seafood',
        ]);

        $this->salmon = Ingredient::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Atlantic Salmon Fillet',
            'sku' => 'SALMON-ATL-01',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'category_id' => $cat->id,
            'unit_id' => $this->kg->id,
            'last_purchase_price' => 6000.0,
        ]);

        // Stock 20 kg of salmon into warehouse
        $this->ledgerService->recordReceipt(
            $this->warehouse,
            [
                [
                    'ingredient_id' => $this->salmon->id,
                    'quantity' => 20.0,
                    'unit_id' => $this->kg->id,
                    'unit_cost' => 6000.0,
                ],
            ],
            $this->user,
            'Opening stock',
            'INIT-FISH-001',
            'opening_balance'
        );
    }

    public function test_record_waste_creates_log_and_depletes_warehouse_stock(): void
    {
        $log = $this->wasteService->recordWaste($this->vendor, [
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $this->salmon->id,
            'quantity' => 3.5,
            'unit_id' => $this->kg->id,
            'unit_cost' => 6000.0,
            'reason' => WasteLog::REASON_SPOILAGE,
            'action_taken' => WasteLog::ACTION_DISCARDED,
            'notes' => 'Fridge temperature breached overnight',
        ], $this->user);

        $this->assertNotNull($log->id);
        $this->assertStringStartsWith('WST-', $log->waste_number);
        $this->assertEquals(3.5, (float) $log->quantity);
        $this->assertEquals(21000.0, (float) $log->total_cost);
        $this->assertEquals(WasteLog::REASON_SPOILAGE, $log->reason);

        // Check stock balance reduced from 20 kg to 16.5 kg
        $balance = $this->ledgerService->getAvailableStock($this->salmon, $this->warehouse);
        $this->assertEquals(16.5, $balance);

        // Check signed ledger item has negative quantity
        $this->assertDatabaseHas('inventory_transaction_items', [
            'transaction_id' => $log->inventory_transaction_id,
            'ingredient_id' => $this->salmon->id,
            'quantity' => -3.5,
        ]);
    }

    public function test_record_waste_deducts_batch_quantity(): void
    {
        $batch = InventoryBatch::create([
            'vendor_id' => $this->vendor->id,
            'ingredient_id' => $this->salmon->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'LOT-SALMON-EXP-01',
            'initial_quantity' => 10.0,
            'remaining_quantity' => 5.0,
            'unit_id' => $this->kg->id,
            'unit_cost' => 6000.0,
            'production_date' => now()->subDays(7),
            'expiry_date' => now()->subDay(),
            'source_type' => InventoryBatch::SOURCE_PURCHASE,
            'status' => InventoryBatch::STATUS_ACTIVE,
        ]);

        // Waste all 5 remaining kg due to expiration
        $log = $this->wasteService->recordWaste($this->vendor, [
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $this->salmon->id,
            'batch_id' => $batch->id,
            'quantity' => 5.0,
            'unit_id' => $this->kg->id,
            'reason' => WasteLog::REASON_EXPIRATION,
            'notes' => 'Passed expiry date',
        ], $this->user);

        $batch->refresh();
        $this->assertEquals(0.0, (float) $batch->remaining_quantity);
        $this->assertEquals(InventoryBatch::STATUS_EXPIRED, $batch->status);
        $this->assertEquals($batch->id, $log->batch_id);
    }

    public function test_waste_summary_aggregates_financial_loss_by_reason(): void
    {
        // 1. Spoilage: 2 kg @ 6000 = 12000 AMD
        $this->wasteService->recordWaste($this->vendor, [
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $this->salmon->id,
            'quantity' => 2.0,
            'unit_cost' => 6000.0,
            'reason' => WasteLog::REASON_SPOILAGE,
        ], $this->user);

        // 2. Prep mistake: 1 kg @ 6000 = 6000 AMD
        $this->wasteService->recordWaste($this->vendor, [
            'warehouse_id' => $this->warehouse->id,
            'ingredient_id' => $this->salmon->id,
            'quantity' => 1.0,
            'unit_cost' => 6000.0,
            'reason' => WasteLog::REASON_PREP_MISTAKE,
        ], $this->user);

        $summary = $this->wasteService->getWasteSummary($this->vendor, now()->subHour(), now()->addHour());

        $this->assertEquals(18000.0, $summary['total_loss_cost']);
        $this->assertEquals(2, $summary['total_records']);
        $this->assertEquals(12000.0, $summary['by_reason']['spoilage']['cost']);
        $this->assertEquals(6000.0, $summary['by_reason']['prep_mistake']['cost']);
        $this->assertNotEmpty($summary['top_wasted_ingredients']);
        $this->assertEquals('Atlantic Salmon Fillet', $summary['top_wasted_ingredients'][0]['name']);
    }
}
