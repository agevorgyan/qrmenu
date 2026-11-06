<?php

namespace Tests\Unit;

use App\Domains\Loyalty\DTOs\CashbackTransactionDTO;
use App\Domains\Loyalty\DTOs\PointsTransactionDTO;
use App\Domains\Loyalty\Enums\LoyaltyTransactionType;
use App\Domains\Loyalty\Exceptions\InsufficientCashbackException;
use App\Domains\Loyalty\Exceptions\InsufficientPointsException;
use App\Domains\Loyalty\Models\LoyaltyPointLot;
use App\Domains\Loyalty\Models\LoyaltyPointsAccount;
use App\Domains\Loyalty\Models\LoyaltyProgram;
use App\Domains\Loyalty\Services\CashbackService;
use App\Domains\Loyalty\Services\PointsLedgerService;
use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyLedgersTest extends TestCase
{
    use RefreshDatabase;

    protected PointsLedgerService $pointsService;

    protected CashbackService $cashbackService;

    protected Vendor $vendor;

    protected Customer $customer;

    protected LoyaltyProgram $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pointsService = app(PointsLedgerService::class);
        $this->cashbackService = app(CashbackService::class);

        $this->vendor = Vendor::create([
            'name' => 'Ledger Bistro',
            'slug' => 'ledger-bistro',
            'email' => 'ledger@bistro.com',
            'password' => bcrypt('password'),
        ]);

        $this->program = LoyaltyProgram::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Ledger Program',
            'currency' => 'USD',
            'default_points_ratio' => 1.0,
            'points_expiration_days' => 90,
            'points_enabled' => true,
            'cashback_enabled' => true,
        ]);

        $this->customer = Customer::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'John Ledger',
            'phone' => '+1999888777',
        ]);
    }

    public function test_credits_points_and_creates_lot(): void
    {
        $dto = new PointsTransactionDTO(
            vendorId: $this->vendor->id,
            customerId: $this->customer->id,
            points: 250,
            type: LoyaltyTransactionType::Earned,
            notes: 'First order reward'
        );

        $tx = $this->pointsService->creditPoints($dto);

        $this->assertEquals(250, $tx->points);
        $this->assertEquals(0, $tx->balance_before);
        $this->assertEquals(250, $tx->balance_after);

        $account = LoyaltyPointsAccount::where('vendor_id', $this->vendor->id)
            ->where('customer_id', $this->customer->id)
            ->first();

        $this->assertNotNull($account);
        $this->assertEquals(250, $account->current_balance);
        $this->assertEquals(250, $account->lifetime_earned);

        $lot = LoyaltyPointLot::where('account_id', $account->id)->first();
        $this->assertNotNull($lot);
        $this->assertEquals(250, $lot->initial_points);
        $this->assertEquals(250, $lot->remaining_points);
        $this->assertFalse($lot->is_expired);
    }

    public function test_debits_points_with_fifo_lot_depletion(): void
    {
        // Credit Lot 1: 100 points
        $this->pointsService->creditPoints(new PointsTransactionDTO(
            vendorId: $this->vendor->id,
            customerId: $this->customer->id,
            points: 100,
            type: LoyaltyTransactionType::Earned
        ));

        // Credit Lot 2: 150 points
        $this->pointsService->creditPoints(new PointsTransactionDTO(
            vendorId: $this->vendor->id,
            customerId: $this->customer->id,
            points: 150,
            type: LoyaltyTransactionType::Earned
        ));

        // Total balance = 250. Debit 120 points.
        // FIFO: Lot 1 (100) should be fully depleted (0 remaining).
        // Lot 2 (150) should have 150 - 20 = 130 remaining.
        $debitTx = $this->pointsService->debitPoints(new PointsTransactionDTO(
            vendorId: $this->vendor->id,
            customerId: $this->customer->id,
            points: 120,
            type: LoyaltyTransactionType::Redeemed,
            notes: 'Redeemed voucher'
        ));

        $this->assertEquals(-120, $debitTx->points);
        $this->assertEquals(250, $debitTx->balance_before);
        $this->assertEquals(130, $debitTx->balance_after);

        $lots = LoyaltyPointLot::where('vendor_id', $this->vendor->id)
            ->orderBy('id', 'asc')
            ->get();

        $this->assertCount(2, $lots);
        $this->assertEquals(0, $lots[0]->remaining_points);
        $this->assertEquals(130, $lots[1]->remaining_points);
    }

    public function test_insufficient_points_throws_exception(): void
    {
        $this->expectException(InsufficientPointsException::class);

        $this->pointsService->creditPoints(new PointsTransactionDTO(
            vendorId: $this->vendor->id,
            customerId: $this->customer->id,
            points: 50,
            type: LoyaltyTransactionType::Earned
        ));

        // Attempt to debit 100 points when only 50 available
        $this->pointsService->debitPoints(new PointsTransactionDTO(
            vendorId: $this->vendor->id,
            customerId: $this->customer->id,
            points: 100,
            type: LoyaltyTransactionType::Redeemed
        ));
    }

    public function test_credits_and_debits_cashback(): void
    {
        $creditDto = new CashbackTransactionDTO(
            vendorId: $this->vendor->id,
            customerId: $this->customer->id,
            amount: 15.50,
            type: LoyaltyTransactionType::Earned,
            notes: 'Cashback from order'
        );

        $creditTx = $this->cashbackService->creditCashback($creditDto);
        $this->assertEquals(15.50, $creditTx->amount);
        $this->assertEquals(15.50, $creditTx->balance_after);

        $debitDto = new CashbackTransactionDTO(
            vendorId: $this->vendor->id,
            customerId: $this->customer->id,
            amount: 5.50,
            type: LoyaltyTransactionType::Redeemed,
            notes: 'Partial bill offset'
        );

        $debitTx = $this->cashbackService->debitCashback($debitDto);
        $this->assertEquals(-5.50, $debitTx->amount);
        $this->assertEquals(10.00, $debitTx->balance_after);
    }

    public function test_insufficient_cashback_throws_exception(): void
    {
        $this->expectException(InsufficientCashbackException::class);

        $this->cashbackService->creditCashback(new CashbackTransactionDTO(
            vendorId: $this->vendor->id,
            customerId: $this->customer->id,
            amount: 10.00,
            type: LoyaltyTransactionType::Earned
        ));

        // Attempt debiting 25 when only 10 exists
        $this->cashbackService->debitCashback(new CashbackTransactionDTO(
            vendorId: $this->vendor->id,
            customerId: $this->customer->id,
            amount: 25.00,
            type: LoyaltyTransactionType::Redeemed
        ));
    }

    public function test_idempotency_prevents_duplicate_credits(): void
    {
        $key = 'order-tx-12345';

        $tx1 = $this->pointsService->creditPoints(new PointsTransactionDTO(
            vendorId: $this->vendor->id,
            customerId: $this->customer->id,
            points: 100,
            type: LoyaltyTransactionType::Earned,
            idempotencyKey: $key
        ));

        $tx2 = $this->pointsService->creditPoints(new PointsTransactionDTO(
            vendorId: $this->vendor->id,
            customerId: $this->customer->id,
            points: 100,
            type: LoyaltyTransactionType::Earned,
            idempotencyKey: $key
        ));

        $this->assertEquals($tx1->id, $tx2->id);

        $account = LoyaltyPointsAccount::where('vendor_id', $this->vendor->id)
            ->where('customer_id', $this->customer->id)
            ->first();

        // Must still be 100, not 200
        $this->assertEquals(100, $account->current_balance);
    }
}
