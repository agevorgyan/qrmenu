<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('work_shifts')->nullOnDelete();

            $table->timestampTz('check_in_at');
            $table->timestampTz('check_out_at')->nullable();
            $table->timestampTz('break_start_at')->nullable();
            $table->timestampTz('break_end_at')->nullable();
            $table->integer('total_break_minutes')->default(0);

            $table->string('source', 50)->default('qr_badge'); // qr_badge, pin, manager, manual
            $table->decimal('check_in_latitude', 10, 7)->nullable();
            $table->decimal('check_in_longitude', 10, 7)->nullable();

            $table->string('status', 50)->default('present'); // present, late, early_departure, incomplete
            $table->integer('late_minutes')->default(0);
            $table->integer('early_departure_minutes')->default(0);

            $table->boolean('is_verified')->default(true);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'check_in_at']);
            $table->index(['vendor_id', 'location_id', 'check_in_at']);
            $table->index(['vendor_id', 'check_in_at']);
        });

        Schema::create('attendance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('attendance_record_id')->constrained('attendance_records')->cascadeOnDelete();
            $table->foreignId('adjusted_by')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('original_check_in')->nullable();
            $table->timestampTz('original_check_out')->nullable();
            $table->timestampTz('adjusted_check_in');
            $table->timestampTz('adjusted_check_out');
            $table->text('reason');
            $table->string('status', 50)->default('approved'); // pending, approved, rejected
            $table->timestamps();

            $table->index(['attendance_record_id']);
            $table->index(['vendor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_adjustments');
        Schema::dropIfExists('attendance_records');
    }
};
