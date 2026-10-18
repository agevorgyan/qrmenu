<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Table Reservations
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 32)->unique();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();

            // Customer Info
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->string('locale', 10)->default('hy'); // Stored customer selection: hy, en, ru

            // Booking Details
            $table->unsignedSmallInteger('guest_count')->default(2);
            $table->string('table_number', 50)->nullable();
            $table->string('table_id', 50)->nullable();
            $table->date('reservation_date');
            $table->string('reservation_time', 10); // e.g. "19:30"
            $table->timestamp('reservation_datetime')->nullable()->index();

            // Status & Notes
            $table->string('status', 30)->default('pending')->index(); // pending, confirmed, rejected, modified, cancelled, completed
            $table->text('notes')->nullable();
            $table->text('special_requests')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('cancellation_policy')->nullable();

            // Reminder Tracking (Minutes before arrival already dispatched)
            $table->jsonb('reminders_sent')->nullable();
            $table->jsonb('staff_reminders_sent')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for fast lookups & scheduler querying
            $table->index(['vendor_id', 'status', 'reservation_datetime']);
            $table->index(['customer_email', 'status']);
        });

        // 2. Reservation Pre-Orders
        Schema::create('reservation_pre_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();

            $table->string('status', 30)->default('pending')->index(); // pending, confirmed, preparing, ready, cancelled
            $table->jsonb('items')->nullable(); // structured order items with names, variations, quantities, unit prices
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('currency', 10)->default('AMD');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['reservation_id', 'status']);
            $table->index(['vendor_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_pre_orders');
        Schema::dropIfExists('reservations');
    }
};
