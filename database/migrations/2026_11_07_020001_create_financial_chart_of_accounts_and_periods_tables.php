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
        // 1. Chart of Accounts Table
        if (! Schema::hasTable('chart_of_accounts')) {
            Schema::create('chart_of_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->string('code', 30);
                $table->string('name', 200);
                $table->string('type', 30); // asset, liability, equity, revenue, expense
                $table->string('subtype', 50); // cash, bank, accounts_receivable, inventory, accounts_payable, etc.
                $table->string('currency', 10)->default('AMD');
                $table->decimal('current_balance', 14, 2)->default(0.00);
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->text('description')->nullable();
                $table->timestamps();

                $table->unique(['vendor_id', 'code']);
                $table->index(['vendor_id', 'type', 'is_active']);
            });
        }

        // 2. Accounting Periods Table
        if (! Schema::hasTable('accounting_periods')) {
            Schema::create('accounting_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->string('name', 100);
                $table->date('start_date');
                $table->date('end_date');
                $table->string('status', 30)->default('open'); // open, closing, closed
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('closed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['vendor_id', 'start_date', 'end_date', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('chart_of_accounts');
    }
};

