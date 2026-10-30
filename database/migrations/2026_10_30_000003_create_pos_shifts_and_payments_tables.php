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
        // 1. Create pos_shifts table
        if (! Schema::hasTable('pos_shifts')) {
            Schema::create('pos_shifts', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->foreignId('pos_device_id')->nullable()->constrained('pos_devices')->nullOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->string('status', 20)->default('open'); // open, closed
                $table->timestamp('opened_at');
                $table->timestamp('closed_at')->nullable();

                $table->decimal('opening_float', 12, 2)->default(0.00);
                $table->decimal('expected_cash', 12, 2)->default(0.00);
                $table->decimal('counted_cash', 12, 2)->nullable();
                $table->decimal('discrepancy', 12, 2)->default(0.00);

                $table->decimal('total_cash_sales', 12, 2)->default(0.00);
                $table->decimal('total_card_sales', 12, 2)->default(0.00);
                $table->decimal('total_other_sales', 12, 2)->default(0.00);
                $table->decimal('total_tips', 12, 2)->default(0.00);
                $table->decimal('total_refunds', 12, 2)->default(0.00);
                $table->decimal('total_cash_in', 12, 2)->default(0.00);
                $table->decimal('total_cash_out', 12, 2)->default(0.00);
                $table->unsignedInteger('orders_count')->default(0);

                $table->text('opening_notes')->nullable();
                $table->text('closing_notes')->nullable();

                $table->timestamps();

                $table->index(['vendor_id', 'status']);
                $table->index(['vendor_id', 'pos_device_id', 'status']);
                $table->index(['vendor_id', 'user_id', 'status']);
            });
        }

        // 2. Create pos_cash_movements table
        if (! Schema::hasTable('pos_cash_movements')) {
            Schema::create('pos_cash_movements', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('pos_shift_id')->constrained('pos_shifts')->cascadeOnDelete();
                $table->foreignId('pos_device_id')->nullable()->constrained('pos_devices')->nullOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

                $table->string('type', 30); // cash_in, cash_out, safe_drop, expense
                $table->decimal('amount', 12, 2);
                $table->string('reason', 255);
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->index(['vendor_id', 'pos_shift_id']);
                $table->index(['vendor_id', 'type']);
            });
        }

        // 3. Create pos_payments table
        if (! Schema::hasTable('pos_payments')) {
            Schema::create('pos_payments', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('pos_shift_id')->nullable()->constrained('pos_shifts')->nullOnDelete();
                $table->foreignId('pos_device_id')->nullable()->constrained('pos_devices')->nullOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

                $table->string('payment_method', 30); // cash, card, mixed, split, external
                $table->decimal('amount', 12, 2);
                $table->decimal('cash_tendered', 12, 2)->nullable();
                $table->decimal('change_due', 12, 2)->nullable();
                $table->decimal('tip_amount', 12, 2)->default(0.00);
                $table->string('currency', 10)->default('AMD');
                $table->string('status', 30)->default('completed'); // completed, refunded, voided, partially_refunded
                $table->string('transaction_reference', 100)->nullable();
                $table->decimal('refunded_amount', 12, 2)->default(0.00);
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->index(['vendor_id', 'order_id']);
                $table->index(['vendor_id', 'pos_shift_id']);
                $table->index(['vendor_id', 'status']);
            });
        }

        // 4. Add pos_shift_id, paid_amount, change_amount to orders
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'pos_shift_id')) {
                $table->foreignId('pos_shift_id')->nullable()->after('pos_device_id')->constrained('pos_shifts')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'paid_amount')) {
                $table->decimal('paid_amount', 12, 2)->default(0.00)->after('total_amount');
            }
            if (! Schema::hasColumn('orders', 'change_amount')) {
                $table->decimal('change_amount', 12, 2)->default(0.00)->after('paid_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'pos_shift_id')) {
                $table->dropForeign(['pos_shift_id']);
                $table->dropColumn('pos_shift_id');
            }
            $dropCols = array_filter(['paid_amount', 'change_amount'], fn ($c) => Schema::hasColumn('orders', $c));
            if (! empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });

        Schema::dropIfExists('pos_payments');
        Schema::dropIfExists('pos_cash_movements');
        Schema::dropIfExists('pos_shifts');
    }
};
