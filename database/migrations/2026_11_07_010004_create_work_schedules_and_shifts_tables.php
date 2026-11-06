<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->string('name');
            $table->string('schedule_type', 50)->default('weekly'); // weekly, monthly, custom
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['vendor_id', 'location_id']);
            $table->index(['vendor_id', 'is_active']);
        });

        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('work_schedules')->nullOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('shift_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('cross_midnight')->default(false);
            $table->integer('unpaid_break_minutes')->default(30);
            $table->string('status', 50)->default('scheduled'); // scheduled, confirmed, completed, cancelled, swapped
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'shift_date']);
            $table->index(['vendor_id', 'location_id', 'shift_date']);
            $table->index(['shift_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_shifts');
        Schema::dropIfExists('work_schedules');
    }
};
