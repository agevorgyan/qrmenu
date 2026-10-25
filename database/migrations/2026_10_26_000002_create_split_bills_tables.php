<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Split Bill Settings per Tenant
        Schema::create('split_bill_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();

            $table->boolean('is_enabled')->default(false);
            $table->boolean('allow_equal_split')->default(true);
            $table->boolean('allow_item_split')->default(true);
            $table->boolean('allow_custom_amount')->default(true);
            $table->boolean('allow_share_link')->default(true);
            $table->unsignedSmallInteger('max_participants')->default(10);
            $table->boolean('require_staff_approval')->default(false);

            $table->timestampsTz();

            $table->unique('vendor_id', 'uniq_split_bill_settings_vendor');
        });

        // 2. Split Bill Sessions (Attached to an Order)
        Schema::create('split_bills', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            $table->string('split_token', 64)->unique();
            $table->string('status', 20)->default('active'); // active, completed, cancelled
            $table->string('split_type', 20)->default('equal'); // equal, by_item, custom, hybrid

            $table->decimal('total_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0.00);
            $table->decimal('remaining_amount', 10, 2);
            $table->string('currency', 10)->default('AMD');
            $table->unsignedSmallInteger('total_participants')->default(1);

            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['vendor_id', 'status', 'created_at'], 'idx_split_bills_vendor_status');
            $table->index(['vendor_id', 'order_id'], 'idx_split_bills_vendor_order');
        });

        // 3. Split Bill Payments (Individual participant allocations)
        Schema::create('split_bill_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('split_bill_id')->constrained('split_bills')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('payment_attempt_id')->nullable()->constrained('payment_attempts')->nullOnDelete();

            $table->string('participant_name', 100)->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('AMD');
            $table->string('status', 30)->default('pending'); // pending, paid, failed, cancelled, refunded
            $table->string('payment_method', 50)->default('cash');
            $table->string('payment_reference', 100)->nullable();
            $table->json('items_breakdown')->nullable();

            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('expires_at')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();

            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['vendor_id', 'status', 'created_at'], 'idx_split_payments_vendor_status');
            $table->index(['split_bill_id', 'status'], 'idx_split_payments_bill_status');
            $table->index(['order_id'], 'idx_split_payments_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('split_bill_payments');
        Schema::dropIfExists('split_bills');
        Schema::dropIfExists('split_bill_settings');
    }
};
