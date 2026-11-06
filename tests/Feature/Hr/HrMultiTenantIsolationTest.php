<?php

namespace Tests\Feature\Hr;

use App\Models\AttendanceRecord;
use App\Models\EmployeeAdvance;
use App\Models\HrDepartment;
use App\Models\HrPosition;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\PayrollPeriod;
use App\Models\SubscriptionPlan;
use App\Models\Timesheet;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Database\Seeders\EnterpriseAuthorizationSeeder;
use Database\Seeders\HrDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrMultiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendorA;

    protected Location $locationA;

    protected User $ownerA;

    protected User $employeeA;

    protected Vendor $vendorB;

    protected Location $locationB;

    protected User $ownerB;

    protected User $employeeB;

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

        // Tenant A
        $this->vendorA = Vendor::create([
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

        $this->locationA = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Ani Northern',
            'slug' => 'ani-northern',
            'address' => 'Northern Ave 1, Yerevan',
            'is_active' => true,
        ]);

        $this->ownerA = User::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Aram Owner A',
            'email' => 'owner.a@ani.am',
            'password' => bcrypt('Password123!'),
            'role' => 'vendor_owner',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->employeeA = User::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'name' => 'Karen Staff A',
            'email' => 'karen.a@ani.am',
            'password' => bcrypt('Password123!'),
            'role' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Tenant B
        $this->vendorB = Vendor::create([
            'name' => 'Dvin Cafe',
            'slug' => 'dvin-cafe',
            'subdomain' => 'dvin-cafe',
            'email' => 'contact@dvin.am',
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
            'subscription_ends_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $this->locationB = Location::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Dvin Cascade',
            'slug' => 'dvin-cascade',
            'address' => 'Tamanyan 5, Yerevan',
            'is_active' => true,
        ]);

        $this->ownerB = User::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Babken Owner B',
            'email' => 'owner.b@dvin.am',
            'password' => bcrypt('Password123!'),
            'role' => 'vendor_owner',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->employeeB = User::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'name' => 'Narek Staff B',
            'email' => 'narek.b@dvin.am',
            'password' => bcrypt('Password123!'),
            'role' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->seed(EnterpriseAuthorizationSeeder::class);
        $this->seed(HrDefaultsSeeder::class);
    }

    public function test_tenant_cannot_view_or_list_other_tenant_employees(): void
    {
        // Owner B viewing employee list does not see Employee A
        $response = $this->actingAs($this->ownerB)->get(route('admin.hr.employees.index'));
        $response->assertOk();
        $response->assertSee($this->employeeB->name);
        $response->assertDontSee($this->employeeA->name);

        // Owner B attempting to view Employee A profile receives 403 or 404
        $showResp = $this->actingAs($this->ownerB)->get(route('admin.hr.employees.show', $this->employeeA));
        $this->assertContains($showResp->status(), [403, 404]);
    }

    public function test_tenant_cannot_view_or_modify_other_tenant_departments_and_positions(): void
    {
        $deptA = HrDepartment::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Kitchen Operations A',
            'is_active' => true,
        ]);

        $posA = HrPosition::create([
            'vendor_id' => $this->vendorA->id,
            'department_id' => $deptA->id,
            'title' => 'Executive Chef A',
            'is_active' => true,
        ]);

        // Owner B attempts to update Dept A -> 403
        $updDept = $this->actingAs($this->ownerB)->put(route('admin.hr.departments.update', $deptA), [
            'name' => 'Hacked Dept',
        ]);
        $this->assertContains($updDept->status(), [403, 404]);

        // Owner B attempts to delete Position A -> 403
        $delPos = $this->actingAs($this->ownerB)->delete(route('admin.hr.positions.destroy', $posA));
        $this->assertContains($delPos->status(), [403, 404]);
    }

    public function test_tenant_cannot_access_or_adjust_other_tenant_attendance_records(): void
    {
        $recA = AttendanceRecord::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'user_id' => $this->employeeA->id,
            'check_in_at' => Carbon::today()->setHour(9)->setMinute(0),
            'check_out_at' => Carbon::today()->setHour(17)->setMinute(0),
            'status' => 'completed',
            'source' => 'pin',
            'is_verified' => true,
        ]);

        $response = $this->actingAs($this->ownerB)->post(route('admin.hr.attendance.adjust', $recA), [
            'adjusted_check_in' => Carbon::today()->setHour(8)->toDateTimeString(),
            'adjusted_check_out' => Carbon::today()->setHour(18)->toDateTimeString(),
            'reason' => 'Unauthorized adjustment attempt',
        ]);

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_tenant_cannot_view_or_approve_other_tenant_timesheets(): void
    {
        $start = Carbon::today()->startOfMonth();
        $end = Carbon::today()->endOfMonth();

        $sheetA = Timesheet::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'user_id' => $this->employeeA->id,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'scheduled_hours' => 160.00,
            'worked_hours' => 160.00,
            'regular_hours' => 160.00,
            'status' => 'draft',
        ]);

        // Owner B attempts to approve sheet A -> 403
        $appResp = $this->actingAs($this->ownerB)->post(route('admin.hr.timesheets.approve', $sheetA));
        $this->assertContains($appResp->status(), [403, 404]);

        // Owner B attempts to update sheet A -> 403
        $updResp = $this->actingAs($this->ownerB)->put(route('admin.hr.timesheets.update', $sheetA), [
            'regular_hours' => 100.00,
        ]);
        $this->assertContains($updResp->status(), [403, 404]);
    }

    public function test_tenant_cannot_view_or_approve_other_tenant_leave_requests(): void
    {
        $leaveTypeA = LeaveType::where('vendor_id', $this->vendorA->id)->first() ?? LeaveType::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Paid Leave A',
            'code' => 'vacation_a',
            'default_days_per_year' => 20,
            'is_paid' => true,
            'is_active' => true,
        ]);

        $leaveA = LeaveRequest::create([
            'vendor_id' => $this->vendorA->id,
            'user_id' => $this->employeeA->id,
            'leave_type_id' => $leaveTypeA->id,
            'start_date' => Carbon::today()->addDays(5)->toDateString(),
            'end_date' => Carbon::today()->addDays(8)->toDateString(),
            'total_days' => 4,
            'reason' => 'Rest for employee A',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->ownerB)->post(route('admin.hr.leaves.review', $leaveA), [
            'action' => 'approve',
        ]);

        $this->assertContains($response->status(), [403, 404]);
        $leaveA->refresh();
        $this->assertEquals('pending', $leaveA->status);
    }

    public function test_tenant_cannot_view_calculate_or_approve_other_tenant_payroll(): void
    {
        $start = Carbon::today()->startOfMonth()->toDateString();
        $end = Carbon::today()->endOfMonth()->toDateString();

        $periodA = PayrollPeriod::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Vendor A November Payroll',
            'cycle_type' => 'monthly',
            'start_date' => $start,
            'end_date' => $end,
            'cutoff_date' => $end,
            'currency' => 'AMD',
            'status' => 'draft',
        ]);

        // Preview -> 403
        $prevResp = $this->actingAs($this->ownerB)->get(route('admin.hr.payroll.preview', $periodA));
        $this->assertContains($prevResp->status(), [403, 404]);

        // Calculate -> 403
        $calcResp = $this->actingAs($this->ownerB)->post(route('admin.hr.payroll.calculate', $periodA));
        $this->assertContains($calcResp->status(), [403, 404]);

        // Approve -> 403
        $appResp = $this->actingAs($this->ownerB)->post(route('admin.hr.payroll.approve', $periodA));
        $this->assertContains($appResp->status(), [403, 404]);

        // Lock -> 403
        $lockResp = $this->actingAs($this->ownerB)->post(route('admin.hr.payroll.lock', $periodA));
        $this->assertContains($lockResp->status(), [403, 404]);
    }

    public function test_tenant_cannot_review_other_tenant_advances_or_payments(): void
    {
        $advA = EmployeeAdvance::create([
            'vendor_id' => $this->vendorA->id,
            'user_id' => $this->employeeA->id,
            'amount' => 40000.00,
            'requested_date' => Carbon::today()->toDateString(),
            'payment_method' => 'cash',
            'recovered_amount' => 0.00,
            'remaining_amount' => 40000.00,
            'status' => 'pending',
            'reason' => 'Personal emergency',
        ]);

        $reviewResp = $this->actingAs($this->ownerB)->post(route('admin.hr.advances.review', $advA), [
            'action' => 'disburse',
        ]);

        $this->assertContains($reviewResp->status(), [403, 404]);
        $advA->refresh();
        $this->assertEquals('pending', $advA->status);
    }
}
