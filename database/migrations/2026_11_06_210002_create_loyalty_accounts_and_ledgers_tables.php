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
        // 1. Points Accounts
        Schema::create('loyalty_points_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->integer('current_balance')->default(0);
            $table->integer('lifetime_earned')->default(0);
            $table->integer('lifetime_redeemed')->default(0);
            $table->integer('lifetime_expired')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'customer_id']);
            $table->index(['vendor_id', 'current_balance']);
        });

        // 2. Points Ledger Transactions
        Schema::create('loyalty_ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('loyalty_points_accounts')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30); // earned, redeemed, expired, reversal, manual_adjustment, referral_bonus
            $table->integer('points'); // positive for credit, negative for debit
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->string('idempotency_key', 64)->nullable();
            $table->string('reference_type', 50)->nullable(); // order, campaign, reward_redemption, reversal
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['vendor_id', 'customer_id', 'created_at']);
            $table->index(['vendor_id', 'order_id']);
            $table->unique(['vendor_id', 'idempotency_key']);
        });

        // 3. FIFO Point Lots
        Schema::create('loyalty_point_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('loyalty_points_accounts')->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained('loyalty_ledger_transactions')->cascadeOnDelete();
            $table->integer('initial_points');
            $table->integer('remaining_points');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_expired')->default(false);
            $table->timestamps();

            $table->index(['vendor_id', 'account_id', 'is_expired', 'expires_at']);
        });

        // 4. Cashback Accounts
        Schema::create('loyalty_cashback_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('currency', 10)->default('AMD');
            $table->decimal('current_balance', 14, 2)->default(0.00);
            $table->decimal('lifetime_earned', 14, 2)->default(0.00);
            $table->decimal('lifetime_redeemed', 14, 2)->default(0.00);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'customer_id']);
            $table->index(['vendor_id', 'current_balance']);
        });

        // 5. Cashback Transactions
        Schema::create('loyalty_cashback_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('loyalty_cashback_accounts')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30); // earned, redeemed, expired, reversal, manual_adjustment
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_before', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('idempotency_key', 64)->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['vendor_id', 'customer_id', 'created_at']);
            $table->index(['vendor_id', 'order_id']);
            $table->unique(['vendor_id', 'idempotency_key']);
        });

        // 6. Cashback Lots
        Schema::create('loyalty_cashback_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('loyalty_cashback_accounts')->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained('loyalty_cashback_transactions')->cascadeOnDelete();
            $table->decimal('initial_amount', 14, 2);
            $table->decimal('remaining_amount', 14, 2);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_expired')->default(false);
            $table->timestamps();

            $table->index(['vendor_id', 'account_id', 'is_expired', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_cashback_lots');
        Schema::dropIfExists('loyalty_cashback_transactions');
        Schema::dropIfExists('loyalty_cashback_accounts');
        Schema::dropIfExists('loyalty_point_lots');
        Schema::dropIfExists('loyalty_ledger_transactions');
        Schema::dropIfExists('loyalty_points_accounts');
    }
};
