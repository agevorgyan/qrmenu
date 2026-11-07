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
        // 1. Financial Transfers Table (Safe drops, Register transfers, Bank deposits)
        if (! Schema::hasTable('financial_transfers')) {
            Schema::create('financial_transfers', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->string('transfer_number', 60);
                $table->string('from_type', 40); // cash_register, bank_account, safe
                $table->unsignedBigInteger('from_id');
                $table->string('to_type', 40); // cash_register, bank_account, safe
                $table->unsignedBigInteger('to_id');
                $table->decimal('amount', 14, 2);
                $table->string('currency', 10)->default('AMD');
                $table->date('transfer_date');
                $table->string('status', 30)->default('completed'); // pending, completed, cancelled
                $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->string('idempotency_key', 100);
                $table->timestamps();

                $table->unique(['vendor_id', 'transfer_number']);
                $table->unique(['vendor_id', 'idempotency_key']);
                $table->index(['vendor_id', 'status', 'transfer_date']);
            });
        }

        // 2. Bank Transactions Table (Statement lines for automated & manual matching)
        if (! Schema::hasTable('bank_transactions')) {
            Schema::create('bank_transactions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
                $table->date('transaction_date');
                $table->date('value_date')->nullable();
                $table->string('type', 20); // credit, debit
                $table->decimal('amount', 14, 2);
                $table->string('currency', 10)->default('AMD');
                $table->string('reference_number', 100)->nullable();
                $table->string('counterparty_name', 200)->nullable();
                $table->string('counterparty_account', 100)->nullable();
                $table->text('description')->nullable();
                $table->string('reconciliation_status', 30)->default('unreconciled'); // unreconciled, matched, manually_reconciled, excluded
                $table->string('matched_source_type', 60)->nullable(); // PosPayment, SupplierPayment, SalaryPayment, ExpensePayment, Income, FinancialTransfer
                $table->unsignedBigInteger('matched_source_id')->nullable();
                $table->timestamp('matched_at')->nullable();
                $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->timestamps();

                $table->index(['vendor_id', 'bank_account_id', 'reconciliation_status']);
                $table->index(['vendor_id', 'transaction_date']);
                $table->index(['matched_source_type', 'matched_source_id']);
            });
        }

        // 3. Bank Reconciliations Table (Period-end reconciliation statements)
        if (! Schema::hasTable('bank_reconciliations')) {
            Schema::create('bank_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
                $table->string('reconciliation_number', 60);
                $table->date('statement_date');
                $table->decimal('statement_ending_balance', 14, 2);
                $table->decimal('gl_book_balance', 14, 2);
                $table->decimal('difference', 14, 2)->default(0.00);
                $table->string('status', 30)->default('completed'); // draft, completed
                $table->foreignId('reconciled_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('reconciled_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['vendor_id', 'reconciliation_number']);
                $table->index(['vendor_id', 'bank_account_id', 'statement_date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('financial_transfers');
    }
};
