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
        // 1. Wholesale Orders
        if (! Schema::hasTable('wholesale_orders')) {
            Schema::create('wholesale_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('seller_id')->constrained('wholesale_sellers')->restrictOnDelete();
                $table->foreignId('customer_id')->constrained('wholesale_customers')->restrictOnDelete();
                $table->string('order_number', 60);
                $table->string('order_type', 30)->default('STANDARD'); // STANDARD, PREORDER
                $table->string('status', 30)->default('DRAFT'); // DRAFT, CONFIRMED, PARTIALLY_DELIVERED, DELIVERED, CANCELLED
                $table->string('payment_status', 30)->default('PENDING'); // PENDING, PARTIALLY_PAID, PAID
                $table->date('order_date');
                $table->date('expected_delivery_date')->nullable();
                $table->foreignId('delivery_address_id')->nullable()->constrained('wholesale_customer_addresses')->nullOnDelete();
                $table->decimal('subtotal', 14, 4)->default(0.0000);
                $table->decimal('discount_total', 14, 4)->default(0.0000);
                $table->decimal('tax_total', 14, 4)->default(0.0000);
                $table->decimal('delivery_fee', 14, 4)->default(0.0000);
                $table->decimal('total_amount', 14, 4)->default(0.0000);
                $table->decimal('paid_amount', 14, 4)->default(0.0000);
                $table->text('notes')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['vendor_id', 'order_number'], 'uq_wholesale_orders_vendor_num');
                $table->index(['vendor_id', 'status'], 'idx_wholesale_orders_status');
                $table->index(['vendor_id', 'customer_id'], 'idx_wholesale_orders_cust');
                $table->index(['vendor_id', 'seller_id'], 'idx_wholesale_orders_seller');
            });
        }

        // 2. Wholesale Order Items
        if (! Schema::hasTable('wholesale_order_items')) {
            Schema::create('wholesale_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('wholesale_orders')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('wholesale_products')->restrictOnDelete();
                $table->string('sku', 80);
                $table->string('product_name', 255);
                $table->string('classifier_code', 30); // Snapshot of ATGAA / FEACN code
                $table->string('unit_code', 20)->default('unit');
                $table->decimal('quantity_ordered', 12, 4);
                $table->decimal('quantity_delivered', 12, 4)->default(0.0000);
                $table->decimal('base_price', 14, 4);
                $table->decimal('unit_price', 14, 4);
                $table->decimal('discount_percent', 5, 2)->default(0.00);
                $table->decimal('discount_amount', 14, 4)->default(0.0000);
                $table->decimal('subtotal', 14, 4);
                $table->boolean('is_vat_applicable')->default(true);
                $table->decimal('vat_rate', 5, 2)->default(20.00);
                $table->decimal('vat_amount', 14, 4)->default(0.0000);
                $table->decimal('total_amount', 14, 4);
                $table->string('applied_rule', 50)->nullable();
                $table->timestamps();

                $table->index(['order_id', 'product_id'], 'idx_wholesale_ord_item_prod');
            });
        }

        // 3. Wholesale Sales (Invoices)
        if (! Schema::hasTable('wholesale_sales')) {
            Schema::create('wholesale_sales', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('wholesale_orders')->nullOnDelete();
                $table->foreignId('seller_id')->constrained('wholesale_sellers')->restrictOnDelete();
                $table->foreignId('customer_id')->constrained('wholesale_customers')->restrictOnDelete();
                $table->string('invoice_number', 60);
                $table->date('sale_date');
                $table->string('status', 30)->default('ISSUED'); // ISSUED, PAID, CANCELLED
                $table->string('payment_method', 30)->default('BANK_TRANSFER'); // CASH, BANK_TRANSFER, CREDIT_TERMS, CARD
                $table->decimal('subtotal', 14, 4)->default(0.0000);
                $table->decimal('discount_total', 14, 4)->default(0.0000);
                $table->decimal('tax_total', 14, 4)->default(0.0000);
                $table->decimal('delivery_fee', 14, 4)->default(0.0000);
                $table->decimal('total_amount', 14, 4)->default(0.0000);
                $table->decimal('paid_amount', 14, 4)->default(0.0000);
                $table->date('due_date')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['vendor_id', 'invoice_number'], 'uq_wholesale_sales_inv_num');
                $table->index(['vendor_id', 'status'], 'idx_wholesale_sales_status');
                $table->index(['vendor_id', 'customer_id'], 'idx_wholesale_sales_cust');
                $table->index(['vendor_id', 'seller_id'], 'idx_wholesale_sales_seller');
            });
        }

        // 4. Wholesale Sale Items
        if (! Schema::hasTable('wholesale_sale_items')) {
            Schema::create('wholesale_sale_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sale_id')->constrained('wholesale_sales')->cascadeOnDelete();
                $table->foreignId('order_item_id')->nullable()->constrained('wholesale_order_items')->nullOnDelete();
                $table->foreignId('product_id')->constrained('wholesale_products')->restrictOnDelete();
                $table->string('sku', 80);
                $table->string('product_name', 255);
                $table->string('classifier_code', 30);
                $table->string('unit_code', 20)->default('unit');
                $table->decimal('quantity', 12, 4);
                $table->decimal('unit_price', 14, 4);
                $table->decimal('discount_amount', 14, 4)->default(0.0000);
                $table->decimal('subtotal', 14, 4);
                $table->boolean('is_vat_applicable')->default(true);
                $table->decimal('vat_rate', 5, 2)->default(20.00);
                $table->decimal('vat_amount', 14, 4)->default(0.0000);
                $table->decimal('total_amount', 14, 4);
                $table->timestamps();

                $table->index(['sale_id', 'product_id'], 'idx_wholesale_sale_item_prod');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wholesale_sale_items');
        Schema::dropIfExists('wholesale_sales');
        Schema::dropIfExists('wholesale_order_items');
        Schema::dropIfExists('wholesale_orders');
    }
};

