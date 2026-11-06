<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('hr_positions')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();

            // Personal Information
            $table->string('national_id_ssn', 100)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 50)->nullable();
            $table->string('emergency_contact_relation', 100)->nullable();

            // Employment Details
            $table->string('internal_employee_code', 100)->nullable();
            $table->string('employment_status', 50)->default('active'); // active, on_leave, suspended, terminated
            $table->string('employment_type', 50)->default('full_time'); // full_time, part_time, contract, seasonal, temporary
            $table->date('hired_at')->useCurrent();
            $table->date('probation_ends_at')->nullable();
            $table->date('terminated_at')->nullable();
            $table->text('termination_reason')->nullable();

            // Compensation & Banking Details
            $table->string('compensation_type', 50)->default('monthly'); // monthly, hourly, daily, shift, mixed
            $table->decimal('base_salary', 14, 2)->default(0.00);
            $table->decimal('hourly_rate', 14, 2)->default(0.00);
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number', 100)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'employment_status']);
            $table->index(['department_id', 'position_id']);
            $table->index(['manager_id']);
        });

        Schema::create('employee_position_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('hr_positions')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'effective_from']);
            $table->index(['vendor_id', 'effective_from']);
        });

        Schema::create('employee_salary_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('compensation_type', 50);
            $table->decimal('base_salary', 14, 2)->default(0.00);
            $table->decimal('hourly_rate', 14, 2)->default(0.00);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'effective_from']);
            $table->index(['vendor_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_histories');
        Schema::dropIfExists('employee_position_histories');
        Schema::dropIfExists('employee_profiles');
    }
};
