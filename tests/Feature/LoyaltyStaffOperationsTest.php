<?php

namespace Tests\Feature;

use App\Domains\Loyalty\Actions\GenerateCustomerQrTokenAction;
use App\Domains\Loyalty\DTOs\PointsTransactionDTO;
use App\Domains\Loyalty\Enums\LoyaltyTransactionType;
use App\Domains\Loyalty\Models\LoyaltyAuditLog;
use App\Domains\Loyalty\Models\LoyaltyProgram;
use App\Domains\Loyalty\Services\PointsLedgerService;
use App\Models\Customer;
use App\Models\Location;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyStaffOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected User $staffUser;

    protected Customer $customer;

    protected LoyaltyProgram $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        $proPlan = SubscriptionPlan::where('slug', 'pro')->first();

        $this->vendor = Vendor::create([
            'name' => 'Staff Ops Bistro',
            'slug' => 'staff-bistro',
            'email' => 'staffops@bistro.com',
            'password' => bcrypt('password'),
            'subscription_plan_id' => $proPlan?->id,
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Station',
            'slug' => 'main-station',
            'is_active' => true,
        ]);

        $this->staffUser = User::create([
            'name' => 'Cashier Jack',
            'email' => 'jack@bistro.com',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'is_active' => true,
        ]);

        $this->program = LoyaltyProgram::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Staff Program',
            'currency' => 'USD',
            'points_enabled' => true,
            'cashback_enabled' => true,
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Loyal Larry',
            'phone' => '+15558889999',
            'loyalty_points' => 0,
        ]);
    }

    public function test_staff_lookup_by_ephemeral_qr_token(): void
    {
        $qrAction = new GenerateCustomerQrTokenAction;
        $qrRecord = $qrAction->execute($this->customer);

        $response = $this->actingAs($this->staffUser)
            ->postJson(route('admin.loyalty.staff.lookup'), [
                'query' => $qrRecord->token,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'overview' => [
                    'customer' => [
                        'id' => $this->customer->id,
                        'name' => 'Loyal Larry',
                    ],
                ],
            ]);
    }

    public function test_staff_issue_points_with_audit_log(): void
    {
        $response = $this->actingAs($this->staffUser)
            ->postJson(route('admin.loyalty.staff.points.issue'), [
                'customer_id' => $this->customer->id,
                'points' => 120,
                'notes' => 'Customer VIP treat',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'new_balance' => 120,
            ]);

        $this->customer->refresh();
        $this->assertEquals(120, $this->customer->loyalty_points);

        $audit = LoyaltyAuditLog::where('vendor_id', $this->vendor->id)
            ->where('customer_id', $this->customer->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('points_issued', $audit->action);
        $this->assertEquals($this->staffUser->id, $audit->staff_id);
    }

    public function test_staff_redeem_points(): void
    {
        // Give customer 200 points
        app(PointsLedgerService::class)->creditPoints(new PointsTransactionDTO(
            vendorId: $this->vendor->id,
            customerId: $this->customer->id,
            points: 200,
            type: LoyaltyTransactionType::Earned
        ));

        $response = $this->actingAs($this->staffUser)
            ->postJson(route('admin.loyalty.staff.points.redeem'), [
                'customer_id' => $this->customer->id,
                'points' => 75,
                'notes' => 'Redeemed for dessert discount',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'new_balance' => 125,
            ]);

        $this->customer->refresh();
        $this->assertEquals(125, $this->customer->loyalty_points);
    }

    public function test_unauthorized_user_blocked(): void
    {
        $unauthorized = User::create([
            'name' => 'Random Hacker',
            'email' => 'hacker@outside.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        $response = $this->actingAs($unauthorized)
            ->postJson(route('admin.loyalty.staff.lookup'), [
                'query' => 'anything',
            ]);

        $response->assertStatus(403);
    }
}
