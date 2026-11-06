<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50); // annual, sick, unpaid, personal, maternity
            $table->boolean('is_paid')->default(true);
            $table->integer('default_days_per_year')->default(20);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['vendor_id', 'code']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 5, 2);
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->string('status', 50)->default('pending'); // pending, approved, rejected, cancelled
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_date', 'end_date']);
            $table->index(['vendor_id', 'status']);
        });

        Schema::create('employee_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->integer('year');
            $table->decimal('allocated_days', 5, 2)->default(0.00);
            $table->decimal('used_days', 5, 2)->default(0.00);
            $table->decimal('remaining_days', 5, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['vendor_id', 'user_id', 'leave_type_id', 'year'], 'uq_emp_leave_bal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leave_balances');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
    }
};
