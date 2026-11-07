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
        // 1. Journal Entries Table
        if (! Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->foreignId('accounting_period_id')->nullable()->constrained('accounting_periods')->nullOnDelete();
                $table->string('entry_number', 60);
                $table->date('entry_date');
                $table->string('source_type', 60); // Order, PosPayment, Expense, SupplierInvoice, SalaryPayment, CashMovement, Refund, BankTransaction, Manual
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('idempotency_key', 100);
                $table->string('status', 30)->default('posted'); // draft, posted, reversed
                $table->boolean('is_reversal')->default(false);
                $table->foreignId('reversal_of_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->string('description', 255);
                $table->decimal('total_debit', 14, 2)->default(0.00);
                $table->decimal('total_credit', 14, 2)->default(0.00);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->timestamps();

                $table->unique(['vendor_id', 'entry_number']);
                $table->unique(['vendor_id', 'idempotency_key']);
                $table->index(['vendor_id', 'source_type', 'source_id']);
                $table->index(['vendor_id', 'location_id', 'entry_date']);
                $table->index(['vendor_id', 'status']);
            });
        }

        // 2. Journal Entry Lines Table
        if (! Schema::hasTable('journal_entry_lines')) {
            Schema::create('journal_entry_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
                $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->string('type', 10); // debit, credit
                $table->decimal('amount', 14, 2);
                $table->string('currency', 10)->default('AMD');
                $table->decimal('exchange_rate', 12, 6)->default(1.000000);
                $table->decimal('amount_base_currency', 14, 2);
                $table->string('memo', 255)->nullable();
                $table->timestamps();

                $table->index(['journal_entry_id']);
                $table->index(['account_id', 'type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
    }
};

