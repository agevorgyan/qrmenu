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
        // 1. Wholesale Deliveries
        if (! Schema::hasTable('wholesale_deliveries')) {
            Schema::create('wholesale_deliveries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('wholesale_orders')->restrictOnDelete();
                $table->string('delivery_number', 60);
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
                $table->foreignId('destination_address_id')->nullable()->constrained('wholesale_customer_addresses')->nullOnDelete();
                $table->string('driver_name', 100)->nullable();
                $table->string('driver_phone', 50)->nullable();
                $table->string('vehicle_number', 50)->nullable();
                $table->string('status', 30)->default('PLANNED'); // PLANNED, DISPATCHED, DELIVERED, PARTIALLY_DELIVERED, FAILED, CANCELLED
                $table->timestamp('dispatched_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->string('received_by_name', 100)->nullable();
                $table->text('proof_of_delivery_note')->nullable();
                $table->text('failure_reason')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['vendor_id', 'delivery_number'], 'uq_wholesale_deliv_num');
                $table->index(['vendor_id', 'status'], 'idx_wholesale_deliv_status');
                $table->index(['vendor_id', 'order_id'], 'idx_wholesale_deliv_order');
            });
        }

        // 2. Wholesale Delivery Items
        if (! Schema::hasTable('wholesale_delivery_items')) {
            Schema::create('wholesale_delivery_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('delivery_id')->constrained('wholesale_deliveries')->cascadeOnDelete();
                $table->foreignId('order_item_id')->constrained('wholesale_order_items')->restrictOnDelete();
                $table->foreignId('product_id')->constrained('wholesale_products')->restrictOnDelete();
                $table->decimal('quantity_planned', 12, 4);
                $table->decimal('quantity_delivered', 12, 4)->default(0.0000);
                $table->decimal('quantity_rejected', 12, 4)->default(0.0000);
                $table->string('unit_code', 20)->default('unit');
                $table->string('rejection_reason', 255)->nullable();
                $table->timestamps();

                $table->index(['delivery_id', 'product_id'], 'idx_wholesale_deliv_item_prod');
            });
        }

        // 3. Wholesale Returns
        if (! Schema::hasTable('wholesale_returns')) {
            Schema::create('wholesale_returns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('wholesale_customers')->restrictOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('wholesale_orders')->nullOnDelete();
                $table->foreignId('sale_id')->nullable()->constrained('wholesale_sales')->nullOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
                $table->string('return_number', 60);
                $table->date('return_date');
                $table->string('status', 30)->default('PENDING'); // PENDING, APPROVED, COMPLETED, REJECTED
                $table->string('action_type', 30)->default('RESTOCK'); // RESTOCK, WASTE, REFUND_CREDIT
                $table->decimal('total_return_amount', 14, 4)->default(0.0000);
                $table->string('reason', 100);
                $table->text('notes')->nullable();
                $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['vendor_id', 'return_number'], 'uq_wholesale_return_num');
                $table->index(['vendor_id', 'status'], 'idx_wholesale_return_status');
            });
        }

        // 4. Wholesale Return Items
        if (! Schema::hasTable('wholesale_return_items')) {
            Schema::create('wholesale_return_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('return_id')->constrained('wholesale_returns')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('wholesale_products')->restrictOnDelete();
                $table->decimal('quantity', 12, 4);
                $table->decimal('unit_price', 14, 4);
                $table->decimal('total_amount', 14, 4);
                $table->boolean('is_restocked')->default(false);
                $table->string('reason_detail', 255)->nullable();
                $table->timestamps();

                $table->index(['return_id', 'product_id'], 'idx_wholesale_ret_item_prod');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wholesale_return_items');
        Schema::dropIfExists('wholesale_returns');
        Schema::dropIfExists('wholesale_delivery_items');
        Schema::dropIfExists('wholesale_deliveries');
    }
};

