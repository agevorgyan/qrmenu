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
        // 1. Financial Categories Table (Income & Expense categories)
        if (! Schema::hasTable('financial_categories')) {
            Schema::create('financial_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('code', 50);
                $table->string('type', 20); // expense, income
                $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
                $table->string('color', 30)->nullable();
                $table->string('icon', 50)->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();

                $table->unique(['vendor_id', 'type', 'code']);
                $table->index(['vendor_id', 'type', 'is_active']);
            });
        }

        // 2. Bank Accounts Table
        if (! Schema::hasTable('bank_accounts')) {
            Schema::create('bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->string('account_name', 150);
                $table->string('bank_name', 150);
                $table->string('account_number', 60);
                $table->string('iban', 60)->nullable();
                $table->string('swift', 30)->nullable();
                $table->string('currency', 10)->default('AMD');
                $table->decimal('opening_balance', 14, 2)->default(0.00);
                $table->decimal('current_balance', 14, 2)->default(0.00);
                $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['vendor_id', 'account_number']);
                $table->index(['vendor_id', 'is_active']);
            });
        }

        // 3. Expenses Table
        if (! Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->foreignId('category_id')->constrained('financial_categories')->restrictOnDelete();
                $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
                $table->string('expense_number', 60);
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->decimal('amount', 14, 2);
                $table->string('currency', 10)->default('AMD');
                $table->decimal('tax_amount', 14, 2)->default(0.00);
                $table->date('expense_date');
                $table->date('due_date')->nullable();
                $table->string('status', 30)->default('draft'); // draft, pending_approval, approved, rejected, paid, partially_paid, cancelled
                $table->string('payment_method', 40)->nullable(); // cash, bank_transfer, card, other
                $table->foreignId('cash_register_id')->nullable()->constrained('cash_registers')->nullOnDelete();
                $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
                $table->decimal('paid_amount', 14, 2)->default(0.00);
                $table->decimal('remaining_amount', 14, 2)->default(0.00);
                $table->timestamp('paid_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('rejected_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->string('attachment_path', 255)->nullable();
                $table->boolean('is_recurring')->default(false);
                $table->string('recurring_frequency', 30)->nullable(); // daily, weekly, monthly, annual
                $table->string('idempotency_key', 100)->nullable();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['vendor_id', 'expense_number']);
                $table->index(['vendor_id', 'status', 'expense_date']);
                $table->index(['vendor_id', 'category_id']);
                $table->index(['vendor_id', 'location_id']);
            });
        }

        // 4. Expense Payments Table (Disbursements / Partial settlements)
        if (! Schema::hasTable('expense_payments')) {
            Schema::create('expense_payments', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
                $table->string('payment_number', 60);
                $table->decimal('amount', 14, 2);
                $table->string('currency', 10)->default('AMD');
                $table->date('payment_date');
                $table->string('payment_method', 40); // cash, bank_transfer, card, other
                $table->foreignId('cash_register_id')->nullable()->constrained('cash_registers')->nullOnDelete();
                $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
                $table->string('reference_number', 100)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->string('idempotency_key', 100)->nullable();
                $table->timestamps();

                $table->unique(['vendor_id', 'payment_number']);
                $table->index(['expense_id']);
                $table->index(['vendor_id', 'payment_date']);
            });
        }

        // 5. Incomes Table (Non-menu operational income: venue rental, scrap, catering surcharge)
        if (! Schema::hasTable('incomes')) {
            Schema::create('incomes', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->foreignId('category_id')->constrained('financial_categories')->restrictOnDelete();
                $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
                $table->string('income_number', 60);
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->decimal('amount', 14, 2);
                $table->string('currency', 10)->default('AMD');
                $table->date('income_date');
                $table->string('status', 30)->default('received'); // draft, approved, received, cancelled
                $table->string('payment_method', 40)->default('cash'); // cash, bank_transfer, card, other
                $table->foreignId('cash_register_id')->nullable()->constrained('cash_registers')->nullOnDelete();
                $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
                $table->timestamp('received_at')->nullable();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->string('idempotency_key', 100)->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['vendor_id', 'income_number']);
                $table->index(['vendor_id', 'status', 'income_date']);
                $table->index(['vendor_id', 'category_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incomes');
        Schema::dropIfExists('expense_payments');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('financial_categories');
    }
};
