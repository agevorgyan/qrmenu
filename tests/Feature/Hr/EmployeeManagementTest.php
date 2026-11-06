<?php

namespace Tests\Feature\Hr;

use App\Models\AttendanceRecord;
use App\Models\EmployeeDocument;
use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeePositionHistory;
use App\Models\EmployeeSalaryHistory;
use App\Models\EmploymentContract;
use App\Models\HrDepartment;
use App\Models\HrPosition;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\EnterpriseAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected Location $location;

    protected User $owner;

    protected User $employee;

    protected HrDepartment $department;

    protected HrPosition $position;

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
            'name' => 'Karen Staff',
            'email' => 'karen@ani.am',
            'password' => bcrypt('Password123!'),
            'role' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->department = HrDepartment::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Service & Hall',
            'is_active' => true,
        ]);

        $this->position = HrPosition::create([
            'vendor_id' => $this->vendor->id,
            'department_id' => $this->department->id,
            'title' => 'Senior Waiter',
            'default_pay_type' => 'monthly',
            'default_pay_rate' => 250000.00,
            'is_active' => true,
        ]);

        $this->seed(EnterpriseAuthorizationSeeder::class);
    }

    public function test_owner_can_view_employees_directory(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('admin.hr.employees.index'));

        $response->assertOk();
        $response->assertSee('Karen Staff');
        $response->assertSee('Employees Directory');
    }

    public function test_owner_can_view_employee_profile_details(): void
    {
        // Setup profile
        $this->employee->employeeProfile->update([
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'employment_status' => 'active',
            'employment_type' => 'full_time',
            'hired_at' => '2026-01-10',
            'base_salary' => 250000.00,
        ]);

        // Position history
        EmployeePositionHistory::create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->employee->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'location_id' => $this->location->id,
            'effective_from' => '2026-01-10',
            'reason' => 'Initial Placement',
            'changed_by' => $this->owner->id,
        ]);

        // Salary history
        EmployeeSalaryHistory::create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->employee->id,
            'compensation_type' => 'monthly',
            'base_salary' => 250000.00,
            'hourly_rate' => 0.00,
            'effective_from' => '2026-01-10',
            'reason' => 'Starting Offer',
            'approved_by' => $this->owner->id,
        ]);

        // Contract
        EmploymentContract::create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->employee->id,
            'contract_number' => 'CTR-2026-099',
            'contract_type' => 'indefinite',
            'start_date' => '2026-01-10',
            'wage_amount' => 250000.00,
            'wage_type' => 'monthly',
            'working_hours_per_week' => 40,
            'status' => 'active',
        ]);

        // Document
        EmployeeDocument::create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->employee->id,
            'title' => 'Passport Scan',
            'category' => 'id',
            'file_path' => 'hr_docs/test.pdf',
            'file_size_bytes' => 102400,
            'mime_type' => 'application/pdf',
        ]);

        // Attendance
        AttendanceRecord::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'user_id' => $this->employee->id,
            'check_in_at' => now()->subHours(8),
            'check_out_at' => now(),
            'source' => 'qr_badge',
            'status' => 'completed',
            'late_minutes' => 0,
        ]);

        // Leave Type & Balance & Request
        $leaveType = LeaveType::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Annual Leave',
            'code' => 'annual_test',
            'is_paid' => true,
            'default_days_per_year' => 20,
            'is_active' => true,
        ]);

        EmployeeLeaveBalance::create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => 2026,
            'allocated_days' => 20,
            'used_days' => 0,
            'remaining_days' => 20,
        ]);

        LeaveRequest::create([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-05',
            'total_days' => 5,
            'reason' => 'Summer vacation',
            'status' => 'approved',
        ]);

        // Payroll Period & Item
        $period = PayrollPeriod::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'January 2026',
            'cycle_type' => 'monthly',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'cutoff_date' => '2026-01-25',
            'currency' => 'AMD',
            'status' => 'draft',
        ]);

        PayrollItem::create([
            'payroll_period_id' => $period->id,
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->employee->id,
            'base_salary' => 250000.00,
            'gross_salary' => 250000.00,
            'net_salary' => 195000.00,
            'payment_status' => 'unpaid',
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('admin.hr.employees.show', $this->employee));

        $response->assertOk();
        $response->assertSee('Karen Staff');
        $response->assertSee('Position & Career History');
        $response->assertSee('Initial Placement');
        $response->assertSee('Starting Offer');
        $response->assertSee('CTR-2026-099');
        $response->assertSee('Passport Scan');
        $response->assertSee('Annual Leave');
        $response->assertSee('January 2026');
    }

    public function test_owner_can_update_employee_profile(): void
    {
        $response = $this->actingAs($this->owner)
            ->put(route('admin.hr.employees.update', $this->employee), [
                'name' => 'Karen Updated',
                'phone' => '+37491112233',
                'department_id' => $this->department->id,
                'position_id' => $this->position->id,
                'location_id' => $this->location->id,
                'employment_status' => 'active',
                'employment_type' => 'full_time',
                'hired_at' => '2026-02-01',
            ]);

        $response->assertRedirect();

        $this->employee->refresh();
        $this->assertEquals('Karen Updated', $this->employee->name);

        $profile = $this->employee->employeeProfile;
        $this->assertNotNull($profile);
        $this->assertEquals('active', $profile->employment_status);
        $this->assertEquals('full_time', $profile->employment_type);
    }

    public function test_position_transfer_records_audit_history(): void
    {
        $newDept = HrDepartment::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Bar & Drinks',
            'is_active' => true,
        ]);

        $newPos = HrPosition::create([
            'vendor_id' => $this->vendor->id,
            'department_id' => $newDept->id,
            'title' => 'Head Bartender',
            'default_pay_type' => 'monthly',
            'default_pay_rate' => 300000.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->post(route('admin.hr.employees.transfer', $this->employee), [
                'department_id' => $newDept->id,
                'position_id' => $newPos->id,
                'reason' => 'Promotion to Bartender',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('employee_position_histories', [
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->employee->id,
            'department_id' => $newDept->id,
            'position_id' => $newPos->id,
            'reason' => 'Promotion to Bartender',
        ]);
    }

    public function test_salary_revision_records_audit_history(): void
    {
        $response = $this->actingAs($this->owner)
            ->post(route('admin.hr.employees.salary', $this->employee), [
                'compensation_type' => 'monthly',
                'base_salary' => 280000.00,
                'hourly_rate' => 1666.67,
                'reason' => 'Annual Performance Merit Raise',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('employee_salary_histories', [
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->employee->id,
            'base_salary' => 280000.00,
            'reason' => 'Annual Performance Merit Raise',
        ]);

        $this->assertEquals(280000.00, (float) $this->employee->employeeProfile->fresh()->base_salary);
    }

    public function test_employment_contract_issuance(): void
    {
        $response = $this->actingAs($this->owner)
            ->post(route('admin.hr.employees.contracts.store', $this->employee), [
                'contract_number' => 'CTR-2026-001',
                'contract_type' => 'indefinite',
                'start_date' => '2026-01-15',
                'wage_amount' => 250000.00,
                'wage_type' => 'monthly',
                'working_hours_per_week' => 40,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('employment_contracts', [
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->employee->id,
            'contract_number' => 'CTR-2026-001',
            'contract_type' => 'indefinite',
            'status' => 'active',
        ]);
    }

    public function test_employee_document_upload_and_destroy(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('contract.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->owner)
            ->post(route('admin.hr.employees.documents.store', $this->employee), [
                'title' => 'Signed Contract 2026',
                'category' => 'contract',
                'document' => $file,
            ]);

        $response->assertRedirect();

        $document = EmployeeDocument::where('user_id', $this->employee->id)->first();
        $this->assertNotNull($document);
        $this->assertEquals('Signed Contract 2026', $document->title);

        // Test destroy
        $delResponse = $this->actingAs($this->owner)
            ->delete(route('admin.hr.employees.documents.destroy', [$this->employee, $document]));

        $delResponse->assertRedirect();
        $this->assertDatabaseMissing('employee_documents', ['id' => $document->id]);
    }
}
