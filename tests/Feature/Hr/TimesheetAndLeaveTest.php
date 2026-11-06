<?php

namespace Tests\Feature\Hr;

use App\Models\AttendanceRecord;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\SubscriptionPlan;
use App\Models\Timesheet;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WorkShift;
use App\Services\HrLeaveService;
use Carbon\Carbon;
use Database\Seeders\EnterpriseAuthorizationSeeder;
use Database\Seeders\HrDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimesheetAndLeaveTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected User $owner;

    protected User $employee;

    protected LeaveType $vacationType;

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

        $this->vacationType = LeaveType::where('vendor_id', $this->vendor->id)
            ->where('code', 'vacation')
            ->first() ?? LeaveType::create([
                'vendor_id' => $this->vendor->id,
                'name' => 'Annual Paid Vacation',
                'code' => 'vacation',
                'default_days_per_year' => 20,
                'is_paid' => true,
                'requires_approval' => true,
                'is_active' => true,
            ]);
    }

    public function test_owner_can_generate_period_timesheets_from_attendance(): void
    {
        $start = Carbon::today()->startOfMonth();
        $end = Carbon::today()->endOfMonth();

        // Create 10-hour shift punch on the 5th (8 regular + 2 overtime)
        $day1In = $start->copy()->addDays(4)->setHour(9)->setMinute(0);
        $day1Out = $start->copy()->addDays(4)->setHour(19)->setMinute(0);

        AttendanceRecord::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'user_id' => $this->employee->id,
            'check_in_at' => $day1In,
            'check_out_at' => $day1Out,
            'total_break_minutes' => 0,
            'status' => 'completed',
            'is_verified' => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->post(route('admin.hr.timesheets.generate'), [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('timesheets', [
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->employee->id,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'worked_hours' => 10.00,
            'regular_hours' => 8.00,
            'overtime_hours' => 2.00,
            'status' => 'draft',
        ]);
    }

    public function test_owner_can_generate_timesheets_with_scheduled_work_shifts(): void
    {
        $start = Carbon::today()->startOfMonth();
        $end = Carbon::today()->endOfMonth();

        // 1. Regular 9-hour shift minus 60 min unpaid break = 8 scheduled hours
        WorkShift::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'user_id' => $this->employee->id,
            'shift_date' => $start->copy()->addDays(5)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'cross_midnight' => false,
            'unpaid_break_minutes' => 60,
            'status' => 'scheduled',
            'created_by' => $this->owner->id,
        ]);

        // 2. Overnight cross-midnight shift: 22:00 to 06:00 (8h) minus 30 min break = 7.5 scheduled hours
        WorkShift::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'user_id' => $this->employee->id,
            'shift_date' => $start->copy()->addDays(6)->toDateString(),
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'cross_midnight' => true,
            'unpaid_break_minutes' => 30,
            'status' => 'scheduled',
            'created_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->post(route('admin.hr.timesheets.generate'), [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('timesheets', [
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->employee->id,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'scheduled_hours' => 15.50,
            'status' => 'draft',
        ]);
    }

    public function test_owner_can_manually_adjust_draft_timesheet(): void
    {
        $start = Carbon::today()->startOfMonth();
        $end = Carbon::today()->endOfMonth();

        $timesheet = Timesheet::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'user_id' => $this->employee->id,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'scheduled_hours' => 160.00,
            'worked_hours' => 160.00,
            'regular_hours' => 160.00,
            'overtime_hours' => 0.00,
            'night_hours' => 0.00,
            'holiday_hours' => 0.00,
            'paid_leave_hours' => 0.00,
            'unpaid_leave_hours' => 0.00,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->owner)
            ->put(route('admin.hr.timesheets.update', $timesheet), [
                'regular_hours' => 160.00,
                'overtime_hours' => 12.50,
                'night_hours' => 6.00,
                'notes' => 'Adjusted for overtime during weekend banquet',
            ]);

        $response->assertRedirect();

        $timesheet->refresh();
        $this->assertEquals(12.50, (float) $timesheet->overtime_hours);
        $this->assertEquals(6.00, (float) $timesheet->night_hours);
        $this->assertEquals('Adjusted for overtime during weekend banquet', $timesheet->notes);
    }

    public function test_approving_timesheet_locks_it_from_further_modifications(): void
    {
        $start = Carbon::today()->startOfMonth();
        $end = Carbon::today()->endOfMonth();

        $timesheet = Timesheet::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'user_id' => $this->employee->id,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'scheduled_hours' => 160.00,
            'worked_hours' => 160.00,
            'regular_hours' => 160.00,
            'overtime_hours' => 0.00,
            'night_hours' => 0.00,
            'holiday_hours' => 0.00,
            'status' => 'draft',
        ]);

        // 1. Approve timesheet
        $approveResp = $this->actingAs($this->owner)
            ->post(route('admin.hr.timesheets.approve', $timesheet));
        $approveResp->assertRedirect();

        $timesheet->refresh();
        $this->assertEquals('approved', $timesheet->status);
        $this->assertEquals($this->owner->id, $timesheet->approved_by);
        $this->assertNotNull($timesheet->approved_at);

        // 2. Modifying approved timesheet should be forbidden
        $editResp = $this->actingAs($this->owner)
            ->put(route('admin.hr.timesheets.update', $timesheet), [
                'regular_hours' => 140.00,
            ]);

        $editResp->assertStatus(422);
    }

    public function test_leave_request_submission_with_balance_validation(): void
    {
        $startDate = Carbon::today()->addDays(7);
        $endDate = Carbon::today()->addDays(11); // 5 days

        $response = $this->actingAs($this->owner)
            ->post(route('admin.hr.leaves.store'), [
                'user_id' => $this->employee->id,
                'leave_type_id' => $this->vacationType->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'reason' => 'Family vacation abroad',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('leave_requests', [
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->employee->id,
            'leave_type_id' => $this->vacationType->id,
            'total_days' => 5,
            'status' => 'pending',
            'reason' => 'Family vacation abroad',
        ]);
    }

    public function test_leave_request_exceeding_balance_is_rejected(): void
    {
        $startDate = Carbon::today()->addDays(7);
        $endDate = Carbon::today()->addDays(36); // 30 days > default 20 days

        $this->expectException(\InvalidArgumentException::class);

        app(HrLeaveService::class)->submitLeaveRequest(
            vendor: $this->vendor,
            user: $this->employee,
            leaveType: $this->vacationType,
            startDate: $startDate,
            endDate: $endDate,
            reason: 'Excessive vacation request'
        );
    }

    public function test_owner_can_approve_leave_request_and_deduct_balance(): void
    {
        $startDate = Carbon::today()->addDays(10);
        $endDate = Carbon::today()->addDays(14); // 5 days

        $leaveReq = LeaveRequest::create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->employee->id,
            'leave_type_id' => $this->vacationType->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_days' => 5,
            'reason' => 'Summer rest',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->owner)
            ->post(route('admin.hr.leaves.review', $leaveReq), [
                'action' => 'approve',
                'review_notes' => 'Approved by manager',
            ]);

        $response->assertRedirect();

        $leaveReq->refresh();
        $this->assertEquals('approved', $leaveReq->status);
        $this->assertEquals($this->owner->id, $leaveReq->reviewed_by);

        $balance = EmployeeLeaveBalance::where('user_id', $this->employee->id)
            ->where('leave_type_id', $this->vacationType->id)
            ->where('year', (int) $startDate->year)
            ->first();

        $this->assertNotNull($balance);
        $this->assertEquals(5.00, (float) $balance->used_days);
        $this->assertEquals(15.00, (float) $balance->remaining_days);
    }

    public function test_owner_can_reject_leave_request_without_deducting_balance(): void
    {
        $startDate = Carbon::today()->addDays(20);
        $endDate = Carbon::today()->addDays(22); // 3 days

        $leaveReq = LeaveRequest::create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->employee->id,
            'leave_type_id' => $this->vacationType->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_days' => 3,
            'reason' => 'Conference attendance',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->owner)
            ->post(route('admin.hr.leaves.review', $leaveReq), [
                'action' => 'reject',
                'review_notes' => 'Staff shortage during this weekend',
            ]);

        $response->assertRedirect();

        $leaveReq->refresh();
        $this->assertEquals('rejected', $leaveReq->status);

        $balance = EmployeeLeaveBalance::where('user_id', $this->employee->id)
            ->where('leave_type_id', $this->vacationType->id)
            ->where('year', (int) $startDate->year)
            ->first();

        $this->assertTrue($balance === null || (float) $balance->used_days === 0.00);
    }
}
