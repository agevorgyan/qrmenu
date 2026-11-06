<?php

namespace Tests\Feature\Hr;

use App\Models\EmployeeAdvance;
use App\Models\EmployeeProfile;
use App\Models\Location;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\SalaryPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Database\Seeders\EnterpriseAuthorizationSeeder;
use Database\Seeders\HrDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected User $owner;

    protected User $employee;

    protected EmployeeProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = SubscriptionPlan::create([
            'name' => 'Business',
            'slug' => 'business',
            'price' => 49000,
            'features' => ['team' => true, 'locations' => true, 'orders' => true],
            'is_active' => true,
        ]);

        $this->vendor = Vendor::create([
            'name' => 'Ani Restaurant',
            'slug' => 'ani-restaurant',
            'subdomain' => 'ani-restaurant',
            'email' => 'contact@ani.am',
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
            'subscription_ends_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Hall Branch',
            'slug' => 'main-hall',
            'address' => 'Northern Ave 1, Yerevan',
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Aram Owner',
            'email' => 'owner@ani.am',
            'password' => bcrypt('Password123!'),
            'role' => 'vendor_owner',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->employee = User::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Karen Staff',
            'email' => 'karen@ani.am',
            'password' => bcrypt('Password123!'),
            'role' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->seed(EnterpriseAuthorizationSeeder::class);
        $this->seed(HrDefaultsSeeder::class);

        $this->profile = EmployeeProfile::updateOrCreate(
            ['user_id' => $this->employee->id],
            [
                'vendor_id' => $this->vendor->id,
                'employee_code' => 'EMP-001',
                'employment_status' => 'active',
                'employment_type' => 'full_time',
                'compensation_type' => 'monthly',
                'base_salary' => 300000.00,
                'currency' => 'AMD',
                'hire_date' => '2026-01-01',
            ]
        );
    }

    public function test_owner_can_create_payroll_period(): void
    {
        $start = Carbon::today()->startOfMonth()->toDateString();
        $end = Carbon::today()->endOfMonth()->toDateString();

        $response = $this->actingAs($this->owner)
            ->post(route('admin.hr.payroll.create_period'), [
                'name' => 'November 2026 Monthly Payroll',
                'cycle_type' => 'monthly',
                'start_date' => $start,
                'end_date' => $end,
                'cutoff_date' => $end,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('payroll_periods', [
            'vendor_id' => $this->vendor->id,
            'name' => 'November 2026 Monthly Payroll',
            'cycle_type' => 'monthly',
            'status' => 'draft',
        ]);
    }

    public function test_owner_can_run_payroll_calculation_with_armenian_tax_rules(): void
    {
        $start = Carbon::today()->startOfMonth()->toDateString();
        $end = Carbon::today()->endOfMonth()->toDateString();

        $period = PayrollPeriod::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'November 2026 Monthly Payroll',
            'cycle_type' => 'monthly',
            'start_date' => $start,
            'end_date' => $end,
            'cutoff_date' => $end,
            'currency' => 'AMD',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->owner)
            ->post(route('admin.hr.payroll.calculate', $period));

        $response->assertRedirect();

        $period->refresh();
        $this->assertEquals('pending_approval', $period->status);

        $item = PayrollItem::where('payroll_period_id', $period->id)
            ->where('user_id', $this->employee->id)
            ->first();

        $this->assertNotNull($item);
        $this->assertEquals(300000.00, (float) $item->gross_salary);
        // Armenian statutory deductions:
        // Income Tax: 20% of 300,000 = 60,000
        $this->assertEquals(60000.00, (float) $item->income_tax);
        // Pension: 5% of 300,000 = 15,000
        $this->assertEquals(15000.00, (float) $item->pension_fee);
        // Military Stamp: 5,500 (bracket 200,001 - 500,000 AMD)
        $this->assertEquals(5500.00, (float) $item->stamp_fee);
        // Total Deductions: 60,000 + 15,000 + 5,500 = 80,500
        $this->assertEquals(80500.00, (float) $item->total_deductions);
        // Net Salary: 300,000 - 80,500 = 219,500 AMD
        $this->assertEquals(219500.00, (float) $item->net_salary);
        $this->assertEquals('unpaid', $item->payment_status);
    }

    public function test_owner_can_approve_and_lock_payroll_period(): void
    {
        $start = Carbon::today()->startOfMonth()->toDateString();
        $end = Carbon::today()->endOfMonth()->toDateString();

        $period = PayrollPeriod::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'November 2026 Monthly Payroll',
            'cycle_type' => 'monthly',
            'start_date' => $start,
            'end_date' => $end,
            'cutoff_date' => $end,
            'currency' => 'AMD',
            'status' => 'pending_approval',
            'total_gross' => 300000.00,
            'total_net' => 219500.00,
        ]);

        // 1. Approve period
        $approveResp = $this->actingAs($this->owner)
            ->post(route('admin.hr.payroll.approve', $period));
        $approveResp->assertRedirect();

        $period->refresh();
        $this->assertEquals('approved', $period->status);
        $this->assertEquals($this->owner->id, $period->approved_by);

        // 2. Lock period
        $lockResp = $this->actingAs($this->owner)
            ->post(route('admin.hr.payroll.lock', $period));
        $lockResp->assertRedirect();

        $period->refresh();
        $this->assertEquals('locked', $period->status);
        $this->assertTrue($period->isLocked());
    }

    public function test_salary_advance_lifecycle_and_automatic_payroll_recovery(): void
    {
        // 1. Request advance of 50,000 AMD
        $advanceResp = $this->actingAs($this->owner)
            ->post(route('admin.hr.advances.store'), [
                'user_id' => $this->employee->id,
                'amount' => 50000.00,
                'reason' => 'Emergency medical expense',
                'payment_method' => 'cash',
            ]);
        $advanceResp->assertRedirect();

        $advance = EmployeeAdvance::where('user_id', $this->employee->id)->first();
        $this->assertNotNull($advance);
        $this->assertEquals('pending', $advance->status);

        // 2. Disburse advance
        $disburseResp = $this->actingAs($this->owner)
            ->post(route('admin.hr.advances.review', $advance), [
                'action' => 'disburse',
            ]);
        $disburseResp->assertRedirect();

        $advance->refresh();
        $this->assertEquals('disbursed', $advance->status);

        // 3. Run payroll period calculation with advance recovery
        $start = Carbon::today()->startOfMonth()->toDateString();
        $end = Carbon::today()->endOfMonth()->toDateString();

        $period = PayrollPeriod::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Monthly Payroll With Advance Recovery',
            'cycle_type' => 'monthly',
            'start_date' => $start,
            'end_date' => $end,
            'cutoff_date' => $end,
            'currency' => 'AMD',
            'status' => 'draft',
        ]);

        $this->actingAs($this->owner)
            ->post(route('admin.hr.payroll.calculate', $period));

        $item = PayrollItem::where('payroll_period_id', $period->id)
            ->where('user_id', $this->employee->id)
            ->first();

        $this->assertNotNull($item);
        $this->assertEquals(50000.00, (float) $item->advance_deduction);
        // Net: 219,500 - 50,000 = 169,500 AMD
        $this->assertEquals(169500.00, (float) $item->net_salary);

        // 4. Lock period to settle advance recovery
        $period->update(['status' => 'approved']);
        $this->actingAs($this->owner)
            ->post(route('admin.hr.payroll.lock', $period));

        $advance->refresh();
        $this->assertEquals('recovered', $advance->status);
        $this->assertEquals(0.00, (float) $advance->remaining_amount);
        $this->assertEquals(50000.00, (float) $advance->recovered_amount);
    }

    public function test_salary_payout_disbursement_and_status_tracking(): void
    {
        $start = Carbon::today()->startOfMonth()->toDateString();
        $end = Carbon::today()->endOfMonth()->toDateString();

        $period = PayrollPeriod::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'November Payout Period',
            'cycle_type' => 'monthly',
            'start_date' => $start,
            'end_date' => $end,
            'cutoff_date' => $end,
            'currency' => 'AMD',
            'status' => 'approved',
            'total_net' => 200000.00,
        ]);

        $item = PayrollItem::create([
            'vendor_id' => $this->vendor->id,
            'payroll_period_id' => $period->id,
            'user_id' => $this->employee->id,
            'base_salary' => 250000.00,
            'worked_days' => 21,
            'worked_hours' => 168.00,
            'regular_pay' => 250000.00,
            'overtime_hours' => 0.00,
            'overtime_pay' => 0.00,
            'night_hours' => 0.00,
            'night_pay' => 0.00,
            'holiday_pay' => 0.00,
            'bonuses_total' => 0.00,
            'allowances_total' => 0.00,
            'gross_salary' => 250000.00,
            'income_tax' => 50000.00,
            'pension_fee' => 0.00,
            'stamp_fee' => 0.00,
            'other_deductions' => 0.00,
            'advance_deduction' => 0.00,
            'total_deductions' => 50000.00,
            'net_salary' => 200000.00,
            'paid_amount' => 0.00,
            'payment_status' => 'unpaid',
        ]);

        // 1. Partial payment of 100,000 AMD
        $partResp = $this->actingAs($this->owner)
            ->post(route('admin.hr.payments.store'), [
                'payroll_item_id' => $item->id,
                'amount' => 100000.00,
                'payment_method' => 'bank_transfer',
                'reference_number' => 'TXN-001',
            ]);
        $partResp->assertRedirect();

        $item->refresh();
        $this->assertEquals(100000.00, (float) $item->paid_amount);
        $this->assertEquals('partially_paid', $item->payment_status);

        // 2. Final payment of remaining 100,000 AMD
        $finalResp = $this->actingAs($this->owner)
            ->post(route('admin.hr.payments.store'), [
                'payroll_item_id' => $item->id,
                'amount' => 100000.00,
                'payment_method' => 'bank_transfer',
                'reference_number' => 'TXN-002',
            ]);
        $finalResp->assertRedirect();

        $item->refresh();
        $this->assertEquals(200000.00, (float) $item->paid_amount);
        $this->assertEquals('paid', $item->payment_status);

        $this->assertEquals(2, SalaryPayment::where('payroll_item_id', $item->id)->count());
    }
}
