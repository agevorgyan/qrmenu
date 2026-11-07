<?php

namespace Tests\Feature\Finance;

use App\Models\Location;
use App\Models\Order;
use App\Models\PosPayment;
use App\Models\User;
use App\Models\Vendor;
use App\Services\CashMovementService;
use App\Services\CashRegisterService;
use App\Services\CashShiftService;
use App\Services\FinancialLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CashRegisterAndShiftManagementTest extends TestCase
{
    use RefreshDatabase;

    protected FinancialLedgerService $ledgerService;

    protected CashRegisterService $registerService;

    protected CashShiftService $shiftService;

    protected CashMovementService $movementService;

    protected Vendor $vendor;

    protected Location $location;

    protected User $cashier;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = app(FinancialLedgerService::class);
        $this->registerService = app(CashRegisterService::class);
        $this->shiftService = app(CashShiftService::class);
        $this->movementService = app(CashMovementService::class);

        $this->vendor = Vendor::create([
            'name' => 'Bistro Till Test',
            'slug' => 'bistro-till',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Dining Room',
            'slug' => 'main-dining',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $this->cashier = User::factory()->create([
            'vendor_id' => $this->vendor->id,
            'role' => 'manager',
        ]);

        $this->manager = User::factory()->create([
            'vendor_id' => $this->vendor->id,
            'role' => 'manager',
        ]);

        $this->ledgerService->initializeDefaultAccounts($this->vendor);
    }

    public function test_creates_cash_register_linked_to_ledger(): void
    {
        $register = $this->registerService->createRegister($this->vendor, [
            'location_id' => $this->location->id,
            'name' => 'Main POS Register 1',
            'code' => 'REG-01',
            'opening_float' => 30000.00,
        ], $this->manager);

        $this->assertDatabaseHas('cash_registers', [
            'id' => $register->id,
            'code' => 'REG-01',
            'name' => 'Main POS Register 1',
        ]);

        $this->assertNotNull($register->account_id);
    }

    public function test_opens_shift_and_rejects_concurrent_shift_on_same_register(): void
    {
        $register = $this->registerService->createRegister($this->vendor, [
            'location_id' => $this->location->id,
            'name' => 'Main POS Register 2',
            'code' => 'REG-02',
            'opening_float' => 20000.00,
        ], $this->manager);

        $shift = $this->shiftService->openShift($this->vendor, [
            'cash_register_id' => $register->id,
            'opening_float' => 25000.00,
            'notes' => 'Morning Shift',
        ], $this->cashier, $register);

        $this->assertEquals('open', $shift->status);
        $this->assertEquals(25000.00, $shift->opening_cash);
        $this->assertTrue($register->fresh()->hasActiveShift());

        // Attempting to open a second shift on the same register must fail
        $this->expectException(ValidationException::class);
        $this->shiftService->openShift($this->vendor, [
            'cash_register_id' => $register->id,
            'opening_float' => 25000.00,
        ], $this->cashier, $register);
    }

    public function test_expected_cash_formula_incorporates_payments_movements_and_safe_drops(): void
    {
        $register = $this->registerService->createRegister($this->vendor, [
            'location_id' => $this->location->id,
            'name' => 'Main Bar Till',
            'code' => 'REG-BAR',
            'opening_float' => 50000.00,
        ], $this->manager);

        $shift = $this->shiftService->openShift($this->vendor, [
            'cash_register_id' => $register->id,
            'opening_float' => 50000.00,
        ], $this->cashier, $register);

        // 1. Record an order and a cash payment of 12,000 AMD
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-TEST-01',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 12000.00,
            'currency' => 'AMD',
        ]);

        PosPayment::create([
            'vendor_id' => $this->vendor->id,
            'order_id' => $order->id,
            'pos_shift_id' => $shift->id,
            'user_id' => $this->cashier->id,
            'payment_method' => 'cash',
            'amount' => 12000.00,
            'status' => 'completed',
            'currency' => 'AMD',
        ]);

        // 2. Record manual Cash In of 5,000 AMD
        $this->movementService->recordCashIn($shift, 5000.00, 'Petty cash refill', $this->cashier);

        // 3. Record manual Cash Out of 2,000 AMD
        $this->movementService->recordCashOut($shift, 2000.00, 'Emergency milk purchase', $this->cashier);

        // 4. Record Safe Drop of 15,000 AMD
        $this->movementService->recordSafeDrop($shift, 15000.00, 'Mid-day cash drop to vault', $this->cashier);

        // Expected cash: 50,000 (Float) + 12,000 (Sales) + 5,000 (In) - 2,000 (Out) - 15,000 (Safe Drop) = 50,000
        $breakdown = $this->shiftService->calculateExpectedCashBreakdown($shift);

        $this->assertEquals(50000.00, $breakdown['opening_float']);
        $this->assertEquals(12000.00, $breakdown['cash_payments']);
        $this->assertEquals(5000.00, $breakdown['cash_in']);
        $this->assertEquals(2000.00, $breakdown['cash_out']);
        $this->assertEquals(15000.00, $breakdown['safe_drops']);
        $this->assertEquals(50000.00, $breakdown['expected_cash']);
    }

    public function test_blind_cash_count_calculates_shortage_and_surplus_accurately(): void
    {
        $register = $this->registerService->createRegister($this->vendor, [
            'location_id' => $this->location->id,
            'name' => 'Terrace POS',
            'code' => 'REG-TER',
            'opening_float' => 40000.00,
        ], $this->manager);

        // Test Cash Shortage: Expected 40,000, Actual Counted 38,500 -> Shortage of -1,500
        $shift = $this->shiftService->openShift($this->vendor, [
            'cash_register_id' => $register->id,
            'opening_float' => 40000.00,
        ], $this->cashier, $register);
        $closedShift = $this->shiftService->closeShift($shift, 38500.00, $this->cashier, 'Count completed');

        $this->assertEquals('closed', $closedShift->status);
        $this->assertEquals(40000.00, $closedShift->expected_cash);
        $this->assertEquals(38500.00, $closedShift->actual_cash);
        $this->assertEquals(-1500.00, $closedShift->cash_discrepancy);
        $this->assertEquals('shortage', $closedShift->variance_status);
    }

    public function test_manager_discrepancy_approval_posts_to_general_ledger(): void
    {
        $register = $this->registerService->createRegister($this->vendor, [
            'location_id' => $this->location->id,
            'name' => 'Garden Till',
            'code' => 'REG-GAR',
            'opening_float' => 30000.00,
        ], $this->manager);

        $shift = $this->shiftService->openShift($this->vendor, [
            'cash_register_id' => $register->id,
            'opening_float' => 30000.00,
        ], $this->cashier, $register);
        $this->shiftService->closeShift($shift, 29000.00, $this->cashier); // Shortage of 1,000

        $approvedShift = $this->shiftService->approveShiftVariance(
            $shift,
            $this->manager,
            'Approved register shortage after review'
        );

        $this->assertEquals('approved', $approvedShift->variance_status);
        $this->assertEquals($this->manager->id, $approvedShift->manager_approved_by);

        // GL Account 5800 (Cash Over / Short) must be debited 1,000 AMD
        $shortageAccount = $this->ledgerService->getAccountByCode($this->vendor, '5800');
        $this->assertEquals(1000.00, $shortageAccount->fresh()->current_balance);
    }

    public function test_generates_official_z_report(): void
    {
        $register = $this->registerService->createRegister($this->vendor, [
            'location_id' => $this->location->id,
            'name' => 'Bar Till Z',
            'code' => 'REG-Z',
            'opening_float' => 20000.00,
        ], $this->manager);

        $shift = $this->shiftService->openShift($this->vendor, [
            'cash_register_id' => $register->id,
            'opening_float' => 20000.00,
        ], $this->cashier, $register);
        $this->shiftService->closeShift($shift, 20000.00, $this->cashier);

        $zReport = $this->shiftService->generateZReport($shift);

        $this->assertArrayHasKey('report_number', $zReport);
        $this->assertArrayHasKey('cash_reconciliation', $zReport);
        $this->assertArrayHasKey('payment_breakdown', $zReport);
        $this->assertEquals(0.00, $zReport['cash_reconciliation']['discrepancy']);
    }
}
