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
        // 1. Suppliers
        if (! Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->string('name', 200);
                $table->string('code', 60)->nullable();
                $table->string('tax_id', 50)->nullable(); // Tax Code / VAT / ՀՎՀՀ
                $table->string('contact_person', 150)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('email', 100)->nullable();
                $table->text('address')->nullable();
                $table->string('bank_name', 100)->nullable();
                $table->string('bank_account', 100)->nullable();
                $table->string('payment_terms', 50)->default('net_30'); // cash, net_7, net_15, net_30, prepayment
                $table->jsonb('delivery_schedule')->nullable();
                $table->decimal('minimum_order_amount', 12, 2)->default(0);
                $table->decimal('rating', 3, 2)->default(5.00);
                $table->text('notes')->nullable();
                $table->decimal('current_balance', 14, 2)->default(0); // Accounts payable debt
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['vendor_id', 'name']);
                $table->index(['vendor_id', 'code']);
                $table->index(['vendor_id', 'tax_id']);
                $table->index(['vendor_id', 'is_active']);
            });
        }

        // 2. Supplier Ingredient Catalog & Contract Prices
        if (! Schema::hasTable('supplier_ingredients')) {
            Schema::create('supplier_ingredients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
                $table->string('supplier_sku', 60)->nullable();
                $table->foreignId('unit_id')->constrained('units');
                $table->decimal('price', 12, 2);
                $table->boolean('is_primary_supplier')->default(false);
                $table->integer('lead_time_days')->default(1);
                $table->decimal('minimum_order_quantity', 12, 4)->default(1.0);
                $table->decimal('package_size', 12, 4)->default(1.0);
                $table->timestamps();

                $table->unique(['vendor_id', 'supplier_id', 'ingredient_id']);
                $table->index(['vendor_id', 'ingredient_id', 'is_primary_supplier']);
            });
        }

        // 3. Purchase Orders
        if (! Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->string('po_number', 60);
                $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->date('order_date');
                $table->date('expected_delivery_date')->nullable();
                $table->string('status', 30)->default('draft'); // draft, pending_approval, approved, sent, partially_received, received, cancelled
                $table->string('payment_status', 30)->default('unpaid'); // unpaid, partially_paid, paid
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('shipping_cost', 14, 2)->default(0);
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->text('notes')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['vendor_id', 'po_number']);
                $table->index(['vendor_id', 'supplier_id']);
                $table->index(['vendor_id', 'warehouse_id']);
                $table->index(['vendor_id', 'status']);
                $table->index(['vendor_id', 'order_date']);
            });
        }

        // 4. Purchase Order Items
        if (! Schema::hasTable('purchase_order_items')) {
            Schema::create('purchase_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
                $table->decimal('quantity_ordered', 12, 4);
                $table->decimal('quantity_received', 12, 4)->default(0);
                $table->foreignId('unit_id')->constrained('units');
                $table->decimal('unit_price', 12, 2);
                $table->decimal('tax_percent', 5, 2)->default(0.00);
                $table->decimal('tax_amount', 12, 2)->default(0.00);
                $table->decimal('total_amount', 14, 2);
                $table->string('notes', 255)->nullable();
                $table->timestamps();

                $table->index(['purchase_order_id']);
                $table->index(['vendor_id', 'ingredient_id']);
            });
        }

        // 5. Supplier Invoices (Accounts Payable Bills)
        if (! Schema::hasTable('supplier_invoices')) {
            Schema::create('supplier_invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
                $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->string('invoice_number', 100);
                $table->date('invoice_date');
                $table->date('due_date');
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->decimal('paid_amount', 14, 2)->default(0);
                $table->decimal('remaining_balance', 14, 2)->default(0);
                $table->string('payment_status', 30)->default('unpaid'); // unpaid, partially_paid, paid, overdue
                $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->string('document_url')->nullable();
                $table->timestamps();

                $table->unique(['vendor_id', 'supplier_id', 'invoice_number']);
                $table->index(['vendor_id', 'payment_status']);
                $table->index(['vendor_id', 'due_date']);
                $table->index(['vendor_id', 'purchase_order_id']);
            });
        }

        // 6. Supplier Payments (Disbursements)
        if (! Schema::hasTable('supplier_payments')) {
            Schema::create('supplier_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $table->foreignId('supplier_invoice_id')->nullable()->constrained('supplier_invoices')->nullOnDelete();
                $table->string('payment_number', 60);
                $table->date('payment_date');
                $table->decimal('amount', 14, 2);
                $table->string('payment_method', 40)->default('bank_transfer'); // bank_transfer, cash, card, check
                $table->string('reference_number', 100)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['vendor_id', 'supplier_id']);
                $table->index(['vendor_id', 'supplier_invoice_id']);
                $table->index(['vendor_id', 'payment_date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('supplier_invoices');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('supplier_ingredients');
        Schema::dropIfExists('suppliers');
    }
};
