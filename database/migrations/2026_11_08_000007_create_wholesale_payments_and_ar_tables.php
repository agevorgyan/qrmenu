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
        // 1. Wholesale Payments
        if (! Schema::hasTable('wholesale_payments')) {
            Schema::create('wholesale_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('wholesale_customers')->restrictOnDelete();
                $table->foreignId('seller_id')->constrained('wholesale_sellers')->restrictOnDelete();
                $table->string('payment_number', 60);
                $table->date('payment_date');
                $table->string('payment_method', 30)->default('BANK_TRANSFER'); // CASH, BANK_TRANSFER, CARD, CHECK
                $table->decimal('amount', 14, 4);
                $table->decimal('allocated_amount', 14, 4)->default(0.0000);
                $table->decimal('unallocated_amount', 14, 4)->default(0.0000);
                $table->string('status', 30)->default('COMPLETED'); // COMPLETED, CANCELLED
                $table->string('reference_number', 100)->nullable();
                $table->foreignId('bank_account_id')->nullable()->constrained('wholesale_seller_bank_accounts')->nullOnDelete();
                $table->foreignId('cash_register_id')->nullable()->constrained('cash_registers')->nullOnDelete();
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['vendor_id', 'payment_number'], 'uq_wholesale_pmt_num');
                $table->index(['vendor_id', 'customer_id'], 'idx_wholesale_pmt_cust');
                $table->index(['vendor_id', 'status'], 'idx_wholesale_pmt_status');
            });
        }

        // 2. Payment Allocations
        if (! Schema::hasTable('wholesale_payment_allocations')) {
            Schema::create('wholesale_payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_id')->constrained('wholesale_payments')->cascadeOnDelete();
                $table->foreignId('sale_id')->constrained('wholesale_sales')->cascadeOnDelete();
                $table->decimal('amount', 14, 4);
                $table->timestamp('allocated_at')->useCurrent();
                $table->timestamps();

                $table->index(['payment_id', 'sale_id'], 'idx_wholesale_pmt_alloc');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wholesale_payment_allocations');
        Schema::dropIfExists('wholesale_payments');
    }
};
