<?php

namespace Tests\Feature\Hr;

use App\Models\AttendanceRecord;
use App\Models\Location;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WorkShift;
use Carbon\Carbon;
use Database\Seeders\EnterpriseAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceAndScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected User $owner;

    protected User $employee;

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

        $this->employee->setPin('1234');

        $this->seed(EnterpriseAuthorizationSeeder::class);
    }

    public function test_owner_can_schedule_work_shift(): void
    {
        $shiftDate = Carbon::tomorrow()->toDateString();

        $response = $this->actingAs($this->owner)
            ->post(route('admin.hr.shifts.store'), [
                'user_id' => $this->employee->id,
                'location_id' => $this->location->id,
                'shift_date' => $shiftDate,
                'start_time' => '10:00',
                'end_time' => '19:00',
                'unpaid_break_minutes' => 60,
                'notes' => 'Lunch and Dinner service shift',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('work_shifts', [
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->employee->id,
            'shift_date' => $shiftDate,
            'start_time' => '10:00:00',
            'end_time' => '19:00:00',
            'status' => 'scheduled',
        ]);
    }

    public function test_overlapping_shifts_are_blocked(): void
    {
        $shiftDate = Carbon::tomorrow()->toDateString();

        WorkShift::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'user_id' => $this->employee->id,
            'shift_date' => $shiftDate,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'status' => 'scheduled',
            'created_by' => $this->owner->id,
        ]);

        // Attempt overlapping shift from 14:00 to 22:00
        $response = $this->actingAs($this->owner)
            ->post(route('admin.hr.shifts.store'), [
                'user_id' => $this->employee->id,
                'location_id' => $this->location->id,
                'shift_date' => $shiftDate,
                'start_time' => '14:00',
                'end_time' => '22:00',
            ]);

        $response->assertSessionHasErrors();
        $this->assertEquals(1, WorkShift::where('user_id', $this->employee->id)->where('shift_date', $shiftDate)->count());
    }

    public function test_cross_midnight_shift_scheduling(): void
    {
        $shiftDate = Carbon::tomorrow()->toDateString();

        $response = $this->actingAs($this->owner)
            ->post(route('admin.hr.shifts.store'), [
                'user_id' => $this->employee->id,
                'location_id' => $this->location->id,
                'shift_date' => $shiftDate,
                'start_time' => '22:00',
                'end_time' => '06:00',
                'cross_midnight' => true,
                'unpaid_break_minutes' => 30,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('work_shifts', [
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->employee->id,
            'cross_midnight' => true,
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
        ]);
    }

    public function test_copy_week_roster_duplicates_shifts(): void
    {
        $sourceStart = Carbon::now()->startOfWeek();
        $targetStart = $sourceStart->copy()->addWeek();

        WorkShift::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'user_id' => $this->employee->id,
            'shift_date' => $sourceStart->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'status' => 'scheduled',
            'created_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->post(route('admin.hr.shifts.copy_week'), [
                'source_date' => $sourceStart->toDateString(),
                'target_date' => $targetStart->toDateString(),
                'location_id' => $this->location->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('work_shifts', [
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->employee->id,
            'shift_date' => $targetStart->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
        ]);
    }

    public function test_attendance_clock_in_and_out_lifecycle(): void
    {
        // 1. Clock In via PIN
        $response = $this->actingAs($this->owner)
            ->postJson(route('admin.hr.attendance.punch'), [
                'action' => 'clock_in',
                'pin' => '1234',
                'location_id' => $this->location->id,
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $record = AttendanceRecord::where('user_id', $this->employee->id)->latest('check_in_at')->first();
        $this->assertNotNull($record);
        $this->assertNull($record->check_out_at);

        // 2. Start Break
        $breakResp = $this->actingAs($this->owner)
            ->postJson(route('admin.hr.attendance.punch'), [
                'action' => 'break_start',
                'pin' => '1234',
            ]);
        $breakResp->assertOk();
        $record->refresh();
        $this->assertEquals('on_break', $record->status);

        // 3. End Break
        $endBreakResp = $this->actingAs($this->owner)
            ->postJson(route('admin.hr.attendance.punch'), [
                'action' => 'break_end',
                'pin' => '1234',
            ]);
        $endBreakResp->assertOk();
        $record->refresh();
        $this->assertEquals('present', $record->status);

        // 4. Clock Out
        $outResp = $this->actingAs($this->owner)
            ->postJson(route('admin.hr.attendance.punch'), [
                'action' => 'clock_out',
                'pin' => '1234',
            ]);
        $outResp->assertOk();
        $record->refresh();
        $this->assertNotNull($record->check_out_at);
        $this->assertEquals('completed', $record->status);
    }

    public function test_attendance_clock_in_and_out_with_scheduled_shift(): void
    {
        $today = Carbon::today();

        $shift = WorkShift::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'user_id' => $this->employee->id,
            'shift_date' => $today->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'cross_midnight' => false,
            'unpaid_break_minutes' => 60,
            'status' => 'scheduled',
            'created_by' => $this->owner->id,
        ]);

        // Clock in at 09:15 (15 min late)
        Carbon::setTestNow($today->copy()->setHour(9)->setMinute(15));

        $inResp = $this->actingAs($this->owner)
            ->postJson(route('admin.hr.attendance.punch'), [
                'action' => 'clock_in',
                'pin' => '1234',
                'location_id' => $this->location->id,
            ]);

        $inResp->assertOk();
        $record = AttendanceRecord::where('user_id', $this->employee->id)->latest('check_in_at')->first();
        $this->assertNotNull($record);
        $this->assertEquals($shift->id, $record->shift_id);
        $this->assertEquals('late', $record->status);
        $this->assertEquals(15, $record->late_minutes);

        // Clock out at 17:30 (30 min early departure)
        Carbon::setTestNow($today->copy()->setHour(17)->setMinute(30));

        $outResp = $this->actingAs($this->owner)
            ->postJson(route('admin.hr.attendance.punch'), [
                'action' => 'clock_out',
                'pin' => '1234',
            ]);

        $outResp->assertOk();
        $record->refresh();
        $this->assertEquals('early_departure', $record->status);
        $this->assertEquals(30, $record->early_departure_minutes);

        Carbon::setTestNow(null);
    }

    public function test_schedule_calendar_view_displays_shifts_in_correct_day_column(): void
    {
        $today = Carbon::today();
        $weekStart = $today->copy()->startOfWeek();

        WorkShift::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'user_id' => $this->employee->id,
            'shift_date' => $weekStart->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '19:00:00',
            'cross_midnight' => false,
            'unpaid_break_minutes' => 60,
            'status' => 'scheduled',
            'created_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('admin.hr.schedules.index', [
                'week_start' => $weekStart->toDateString(),
            ]));

        $response->assertOk();
        $response->assertSee($this->employee->name);
        $response->assertSee('10:00 - 19:00');
    }

    public function test_supervisor_attendance_adjustment_with_audit_trail(): void
    {
        $checkIn = Carbon::today()->setHour(9)->setMinute(0);
        $checkOut = Carbon::today()->setHour(17)->setMinute(0);

        $record = AttendanceRecord::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'user_id' => $this->employee->id,
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'total_hours' => 8.0,
            'status' => 'completed',
            'source' => 'pin',
            'is_verified' => true,
        ]);

        $newCheckIn = Carbon::today()->setHour(8)->setMinute(30)->toDateTimeString();
        $newCheckOut = Carbon::today()->setHour(18)->setMinute(0)->toDateTimeString();

        $response = $this->actingAs($this->owner)
            ->post(route('admin.hr.attendance.adjust', $record), [
                'adjusted_check_in' => $newCheckIn,
                'adjusted_check_out' => $newCheckOut,
                'reason' => 'Employee worked 30 min early and 1 hour late on prep',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('attendance_adjustments', [
            'vendor_id' => $this->vendor->id,
            'attendance_record_id' => $record->id,
            'adjusted_by' => $this->owner->id,
            'reason' => 'Employee worked 30 min early and 1 hour late on prep',
        ]);

        $record->refresh();
        $this->assertEquals(9.5, (float) $record->total_hours);
    }
}
