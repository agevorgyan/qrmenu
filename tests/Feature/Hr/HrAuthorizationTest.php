<?php

namespace Tests\Feature\Hr;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Database\Seeders\EnterpriseAuthorizationSeeder;
use Database\Seeders\HrDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected User $owner;

    protected User $staffA;

    protected User $staffB;

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

        $this->staffA = User::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Karen Staff A',
            'email' => 'karen@ani.am',
            'password' => bcrypt('Password123!'),
            'role' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->staffB = User::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Narek Staff B',
            'email' => 'narek@ani.am',
            'password' => bcrypt('Password123!'),
            'role' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->seed(EnterpriseAuthorizationSeeder::class);
        $this->seed(HrDefaultsSeeder::class);
    }

    public function test_staff_can_access_self_service_portal(): void
    {
        $response = $this->actingAs($this->staffA)->get(route('admin.hr.self_service.index'));
        $response->assertOk();
        $response->assertSee($this->staffA->name);
    }

    public function test_staff_cannot_access_administrative_hr_routes(): void
    {
        // 1. Payroll Management
        $payrollResp = $this->actingAs($this->staffA)->get(route('admin.hr.payroll.index'));
        $payrollResp->assertStatus(403);

        // 2. Department Management
        $deptResp = $this->actingAs($this->staffA)->get(route('admin.hr.departments.index'));
        $deptResp->assertStatus(403);

        // 3. Positions Management
        $posResp = $this->actingAs($this->staffA)->get(route('admin.hr.positions.index'));
        $posResp->assertStatus(403);

        // 4. HR Reports Dashboard
        $repResp = $this->actingAs($this->staffA)->get(route('admin.hr.reports.dashboard'));
        $repResp->assertStatus(403);
    }

    public function test_staff_can_view_own_payslip(): void
    {
        $period = PayrollPeriod::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'November 2026 Monthly Payroll',
            'cycle_type' => 'monthly',
            'start_date' => Carbon::today()->startOfMonth()->toDateString(),
            'end_date' => Carbon::today()->endOfMonth()->toDateString(),
            'cutoff_date' => Carbon::today()->endOfMonth()->toDateString(),
            'currency' => 'AMD',
            'status' => 'approved',
        ]);

        $itemA = PayrollItem::create([
            'vendor_id' => $this->vendor->id,
            'payroll_period_id' => $period->id,
            'user_id' => $this->staffA->id,
            'base_salary' => 250000.00,
            'gross_salary' => 250000.00,
            'net_salary' => 200000.00,
            'payment_status' => 'unpaid',
        ]);

        // Staff A viewing their own payslip -> 200 OK
        $response = $this->actingAs($this->staffA)->get(route('admin.hr.payroll.payslip', $itemA));
        $response->assertOk();
        $response->assertSee($this->staffA->name);
    }

    public function test_staff_cannot_view_other_employees_payslip_idor_protection(): void
    {
        $period = PayrollPeriod::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'November 2026 Monthly Payroll',
            'cycle_type' => 'monthly',
            'start_date' => Carbon::today()->startOfMonth()->toDateString(),
            'end_date' => Carbon::today()->endOfMonth()->toDateString(),
            'cutoff_date' => Carbon::today()->endOfMonth()->toDateString(),
            'currency' => 'AMD',
            'status' => 'approved',
        ]);

        $itemB = PayrollItem::create([
            'vendor_id' => $this->vendor->id,
            'payroll_period_id' => $period->id,
            'user_id' => $this->staffB->id,
            'base_salary' => 350000.00,
            'gross_salary' => 350000.00,
            'net_salary' => 270000.00,
            'payment_status' => 'unpaid',
        ]);

        // Staff A attempting to view Staff B's payslip -> 403 Forbidden
        $response = $this->actingAs($this->staffA)->get(route('admin.hr.payroll.payslip', $itemB));
        $response->assertStatus(403);
    }

    public function test_staff_cannot_approve_leave_requests(): void
    {
        $leaveType = LeaveType::where('vendor_id', $this->vendor->id)->first() ?? LeaveType::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Paid Leave',
            'code' => 'vacation_test',
            'default_days_per_year' => 20,
            'is_paid' => true,
            'is_active' => true,
        ]);

        $leaveReq = LeaveRequest::create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->staffB->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => Carbon::today()->addDays(2)->toDateString(),
            'end_date' => Carbon::today()->addDays(5)->toDateString(),
            'total_days' => 4,
            'reason' => 'Family leave',
            'status' => 'pending',
        ]);

        // Staff A attempting to approve Staff B's leave request -> 403
        $response = $this->actingAs($this->staffA)->post(route('admin.hr.leaves.review', $leaveReq), [
            'action' => 'approve',
        ]);

        $response->assertStatus(403);
    }
}
