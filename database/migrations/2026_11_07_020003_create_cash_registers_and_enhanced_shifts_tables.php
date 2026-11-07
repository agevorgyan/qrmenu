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
        // 1. Cash Registers Table
        if (! Schema::hasTable('cash_registers')) {
            Schema::create('cash_registers', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('code', 50); // e.g. REG-01
                $table->foreignId('pos_device_id')->nullable()->constrained('pos_devices')->nullOnDelete();
                $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
                $table->string('status', 30)->default('active'); // active, inactive, maintenance
                $table->decimal('current_balance', 14, 2)->default(0.00);
                $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['vendor_id', 'location_id', 'code']);
                $table->index(['vendor_id', 'status']);
            });
        }

        // 2. Enhance pos_shifts Table for Cash Register Binding & Manager Sign-off
        Schema::table('pos_shifts', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_shifts', 'cash_register_id')) {
                $table->foreignId('cash_register_id')->nullable()->after('pos_device_id')->constrained('cash_registers')->nullOnDelete();
            }
            if (! Schema::hasColumn('pos_shifts', 'manager_approved_by')) {
                $table->foreignId('manager_approved_by')->nullable()->after('closed_by_user_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('pos_shifts', 'manager_approved_at')) {
                $table->timestamp('manager_approved_at')->nullable()->after('manager_approved_by');
            }
            if (! Schema::hasColumn('pos_shifts', 'approval_notes')) {
                $table->text('approval_notes')->nullable()->after('manager_approved_at');
            }
            if (! Schema::hasColumn('pos_shifts', 'variance_status')) {
                $table->string('variance_status', 30)->default('balanced')->after('discrepancy'); // balanced, shortage, overage
            }
        });

        // 3. Enhance pos_cash_movements Table for Inter-Register/Safe Transfer Tracking & Ledger Linking
        Schema::table('pos_cash_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_cash_movements', 'cash_register_id')) {
                $table->foreignId('cash_register_id')->nullable()->after('pos_device_id')->constrained('cash_registers')->nullOnDelete();
            }
            if (! Schema::hasColumn('pos_cash_movements', 'destination_type')) {
                $table->string('destination_type', 40)->nullable()->after('reason'); // safe, bank_account, cash_register
            }
            if (! Schema::hasColumn('pos_cash_movements', 'destination_id')) {
                $table->unsignedBigInteger('destination_id')->nullable()->after('destination_type');
            }
            if (! Schema::hasColumn('pos_cash_movements', 'transfer_id')) {
                $table->unsignedBigInteger('transfer_id')->nullable()->after('destination_id');
            }
            if (! Schema::hasColumn('pos_cash_movements', 'journal_entry_id')) {
                $table->foreignId('journal_entry_id')->nullable()->after('transfer_id')->constrained('journal_entries')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_cash_movements', function (Blueprint $table) {
            $cols = array_filter(['journal_entry_id', 'transfer_id', 'destination_id', 'destination_type', 'cash_register_id'], fn ($c) => Schema::hasColumn('pos_cash_movements', $c));
            if (in_array('journal_entry_id', $cols, true)) {
                $table->dropForeign(['journal_entry_id']);
            }
            if (in_array('cash_register_id', $cols, true)) {
                $table->dropForeign(['cash_register_id']);
            }
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('pos_shifts', function (Blueprint $table) {
            $cols = array_filter(['variance_status', 'approval_notes', 'manager_approved_at', 'manager_approved_by', 'cash_register_id'], fn ($c) => Schema::hasColumn('pos_shifts', $c));
            if (in_array('cash_register_id', $cols, true)) {
                $table->dropForeign(['cash_register_id']);
            }
            if (in_array('manager_approved_by', $cols, true)) {
                $table->dropForeign(['manager_approved_by']);
            }
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::dropIfExists('cash_registers');
    }
};

