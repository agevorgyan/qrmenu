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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('vat_number', 50)->nullable()->after('tax_id');
            $table->decimal('discount_percent', 5, 2)->default(0)->after('payment_terms');
        });

        // 2. Supplier Ingredients
        Schema::table('supplier_ingredients', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->default(0)->after('price');
        });

        // 3. Purchase Orders
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->default(0)->after('subtotal');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('discount_percent');
        });

        // 4. Purchase Order Items
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->string('classifier_code', 20)->nullable()->after('ingredient_id');
            $table->decimal('discount_percent', 5, 2)->default(0)->after('unit_price');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('discount_percent');
        });

        // 5. Ingredients
        Schema::table('ingredients', function (Blueprint $table) {
            $table->string('classifier_code', 20)->nullable()->after('barcode');
        });

        // 6. Supplier Invoices
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->string('series', 10)->nullable()->after('invoice_number');
            $table->string('number', 30)->nullable()->after('series');
            $table->date('supply_date')->nullable()->after('invoice_date');
            $table->string('supply_location')->nullable()->after('supply_date');
            $table->string('delivery_location')->nullable()->after('supply_location');
            $table->string('additional_data')->nullable()->after('delivery_location');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('subtotal');
            $table->decimal('environmental_fee', 12, 2)->default(0)->after('tax_amount');
            $table->decimal('tare_price', 12, 2)->default(0)->after('environmental_fee');
            $table->text('einvoice_xml')->nullable()->after('notes');
        });

        // 7. Supplier Invoice Items table
        Schema::create('supplier_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->nullable()->constrained('ingredients')->nullOnDelete();
            $table->string('description');
            $table->string('classifier_code', 20)->nullable();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('unit_name', 50)->nullable();
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_price', 12, 4);
            $table->decimal('price', 12, 2);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2);
            $table->timestamps();

            $table->index(['vendor_id', 'supplier_invoice_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_items');

        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'series',
                'number',
                'supply_date',
                'supply_location',
                'delivery_location',
                'additional_data',
                'discount_amount',
                'environmental_fee',
                'tare_price',
                'einvoice_xml',
            ]);
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn('classifier_code');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn([
                'classifier_code',
                'discount_percent',
                'discount_amount',
            ]);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn([
                'discount_percent',
                'discount_amount',
            ]);
        });

        Schema::table('supplier_ingredients', function (Blueprint $table) {
            $table->dropColumn('discount_percent');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'vat_number',
                'discount_percent',
            ]);
        });
    }
};
