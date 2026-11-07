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
        // 1. B2B Wholesale Customers
        if (! Schema::hasTable('wholesale_customers')) {
            Schema::create('wholesale_customers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->string('name', 255);
                $table->string('legal_name', 255)->nullable();
                $table->string('tin', 8)->nullable(); // 8 numeric digits
                $table->string('tax_regime', 50)->default('vat_payer'); // vat_payer, turnover_tax, micro_business, individual
                $table->decimal('credit_limit', 14, 4)->default(0.0000);
                $table->unsignedSmallInteger('payment_term_days')->default(0); // 0 = Cash on delivery / Immediate
                $table->boolean('is_credit_allowed')->default(false);
                $table->decimal('current_balance', 14, 4)->default(0.0000);
                $table->string('phone', 50)->nullable();
                $table->string('email', 255)->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['vendor_id', 'tin'], 'idx_wholesale_cust_tin');
                $table->index(['vendor_id', 'is_active'], 'idx_wholesale_cust_active');
            });
        }

        // 2. Customer Delivery Addresses
        if (! Schema::hasTable('wholesale_customer_addresses')) {
            Schema::create('wholesale_customer_addresses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('wholesale_customers')->cascadeOnDelete();
                $table->string('name', 100); // e.g. "Main Distribution Center"
                $table->string('address_line1', 255);
                $table->string('address_line2', 255)->nullable();
                $table->string('city', 100)->default('Yerevan');
                $table->string('postal_code', 20)->nullable();
                $table->string('contact_name', 100)->nullable();
                $table->string('contact_phone', 50)->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index(['customer_id', 'is_default'], 'idx_wholesale_addr_cust');
            });
        }

        // 3. Customer Contacts
        if (! Schema::hasTable('wholesale_customer_contacts')) {
            Schema::create('wholesale_customer_contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('wholesale_customers')->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('phone', 50)->nullable();
                $table->string('email', 255)->nullable();
                $table->string('role', 50)->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
            });
        }

        // 4. Wholesale Price Lists
        if (! Schema::hasTable('wholesale_price_lists')) {
            Schema::create('wholesale_price_lists', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->string('name', 150);
                $table->string('code', 50);
                $table->string('currency', 3)->default('AMD');
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();

                $table->unique(['vendor_id', 'code'], 'uq_wholesale_plist_vendor_code');
            });
        }

        // 5. Wholesale Price List Items
        if (! Schema::hasTable('wholesale_price_list_items')) {
            Schema::create('wholesale_price_list_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('price_list_id')->constrained('wholesale_price_lists')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('wholesale_products')->cascadeOnDelete();
                $table->decimal('price', 14, 4);
                $table->decimal('min_quantity', 12, 4)->default(1.0000);
                $table->timestamps();

                $table->unique(['price_list_id', 'product_id', 'min_quantity'], 'uq_wholesale_plist_item');
            });
        }

        // 6. Customer Specific Pricing & Contract Agreements
        if (! Schema::hasTable('wholesale_customer_pricing')) {
            Schema::create('wholesale_customer_pricing', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('wholesale_customers')->cascadeOnDelete();
                $table->foreignId('price_list_id')->nullable()->constrained('wholesale_price_lists')->nullOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('wholesale_products')->nullOnDelete();
                $table->decimal('contract_price', 14, 4)->nullable();
                $table->decimal('discount_percent', 5, 2)->nullable();
                $table->date('valid_from')->nullable();
                $table->date('valid_to')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['vendor_id', 'customer_id'], 'idx_wholesale_cust_price');
            });
        }

        // 7. Quantity Breaks / Volume Discounts
        if (! Schema::hasTable('wholesale_quantity_discounts')) {
            Schema::create('wholesale_quantity_discounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('wholesale_products')->cascadeOnDelete();
                $table->foreignId('price_list_id')->nullable()->constrained('wholesale_price_lists')->cascadeOnDelete();
                $table->decimal('min_quantity', 12, 4);
                $table->decimal('discount_percent', 5, 2)->nullable();
                $table->decimal('fixed_price', 14, 4)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['vendor_id', 'product_id', 'min_quantity'], 'idx_wholesale_qty_disc');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wholesale_quantity_discounts');
        Schema::dropIfExists('wholesale_customer_pricing');
        Schema::dropIfExists('wholesale_price_list_items');
        Schema::dropIfExists('wholesale_price_lists');
        Schema::dropIfExists('wholesale_customer_contacts');
        Schema::dropIfExists('wholesale_customer_addresses');
        Schema::dropIfExists('wholesale_customers');
    }
};
