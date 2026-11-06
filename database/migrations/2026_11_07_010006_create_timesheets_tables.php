<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');

            $table->decimal('scheduled_hours', 8, 2)->default(0.00);
            $table->decimal('worked_hours', 8, 2)->default(0.00);
            $table->decimal('regular_hours', 8, 2)->default(0.00);
            $table->decimal('overtime_hours', 8, 2)->default(0.00);
            $table->decimal('night_hours', 8, 2)->default(0.00);
            $table->decimal('holiday_hours', 8, 2)->default(0.00);
            $table->decimal('paid_leave_hours', 8, 2)->default(0.00);
            $table->decimal('unpaid_leave_hours', 8, 2)->default(0.00);

            $table->string('status', 50)->default('draft'); // draft, submitted, approved, locked
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'user_id', 'period_start', 'period_end'], 'uq_timesheet_user_period');
            $table->index(['vendor_id', 'period_start', 'period_end', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheets');
    }
};
