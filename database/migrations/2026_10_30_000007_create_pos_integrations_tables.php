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
        // 1. Inventory columns on products
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'tracks_inventory')) {
                $table->boolean('tracks_inventory')->default(false)->after('is_available');
            }
            if (! Schema::hasColumn('products', 'stock_quantity')) {
                $table->decimal('stock_quantity', 12, 2)->default(0)->after('tracks_inventory');
            }
            if (! Schema::hasColumn('products', 'low_stock_threshold')) {
                $table->integer('low_stock_threshold')->default(5)->after('stock_quantity');
            }
            if (! Schema::hasColumn('products', 'allow_out_of_stock_sale')) {
                $table->boolean('allow_out_of_stock_sale')->default(true)->after('low_stock_threshold');
            }
        });

        // 2. Inventory columns on product_variations
        Schema::table('product_variations', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variations', 'tracks_inventory')) {
                $table->boolean('tracks_inventory')->default(false);
            }
            if (! Schema::hasColumn('product_variations', 'stock_quantity')) {
                $table->decimal('stock_quantity', 12, 2)->default(0);
            }
        });

        // 3. Inventory movements log
        if (! Schema::hasTable('pos_inventory_movements')) {
            Schema::create('pos_inventory_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_variation_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('type', 30); // 'sale', 'restock', 'waste', 'adjustment', 'refund'
                $table->decimal('quantity_change', 12, 2);
                $table->decimal('resulting_quantity', 12, 2);
                $table->string('reason')->nullable();
                $table->timestamps();

                $table->index(['vendor_id', 'product_id']);
                $table->index(['vendor_id', 'created_at']);
            });
        }

        // 4. Loyalty points on customers
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'loyalty_points')) {
                $table->integer('loyalty_points')->default(0)->after('total_spent');
            }
            if (! Schema::hasColumn('customers', 'loyalty_tier')) {
                $table->string('loyalty_tier', 20)->default('bronze')->after('loyalty_points');
            }
        });

        // 5. Customer loyalty transactions
        if (! Schema::hasTable('pos_customer_loyalty_transactions')) {
            Schema::create('pos_customer_loyalty_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->string('type', 30); // 'earned', 'redeemed', 'adjusted', 'expired'
                $table->integer('points');
                $table->integer('balance_after');
                $table->string('notes')->nullable();
                $table->timestamps();

                $table->index(['vendor_id', 'customer_id']);
            });
        }

        // 6. Fiscal information on pos_payments
        Schema::table('pos_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_payments', 'fiscal_crn')) {
                $table->string('fiscal_crn', 50)->nullable()->after('currency');
            }
            if (! Schema::hasColumn('pos_payments', 'fiscal_receipt_number')) {
                $table->string('fiscal_receipt_number', 50)->nullable()->after('fiscal_crn');
            }
            if (! Schema::hasColumn('pos_payments', 'fiscal_qr_data')) {
                $table->text('fiscal_qr_data')->nullable()->after('fiscal_receipt_number');
            }
            if (! Schema::hasColumn('pos_payments', 'fiscal_signed_at')) {
                $table->timestamp('fiscal_signed_at')->nullable()->after('fiscal_qr_data');
            }
            if (! Schema::hasColumn('pos_payments', 'fiscal_status')) {
                $table->string('fiscal_status', 20)->default('none')->after('fiscal_signed_at');
            }
        });

        // 7. Fiscal settings on pos_settings
        Schema::table('pos_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_settings', 'fiscal_enabled')) {
                $table->boolean('fiscal_enabled')->default(false);
            }
            if (! Schema::hasColumn('pos_settings', 'fiscal_adapter')) {
                $table->string('fiscal_adapter', 50)->default('mock');
            }
            if (! Schema::hasColumn('pos_settings', 'fiscal_crn')) {
                $table->string('fiscal_crn', 50)->nullable();
            }
            if (! Schema::hasColumn('pos_settings', 'fiscal_tax_number')) {
                $table->string('fiscal_tax_number', 50)->nullable();
            }
            if (! Schema::hasColumn('pos_settings', 'fiscal_ip_address')) {
                $table->string('fiscal_ip_address', 50)->nullable();
            }
            if (! Schema::hasColumn('pos_settings', 'fiscal_port')) {
                $table->integer('fiscal_port')->nullable();
            }
        });

        // 8. Waiter calls resolution tracking
        Schema::table('waiter_calls', function (Blueprint $table) {
            if (! Schema::hasColumn('waiter_calls', 'pos_device_id')) {
                $table->foreignId('pos_device_id')->nullable()->constrained('pos_devices')->nullOnDelete();
            }
            if (! Schema::hasColumn('waiter_calls', 'resolved_by_user_id')) {
                $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('waiter_calls', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('waiter_calls', function (Blueprint $table) {
            $table->dropForeign(['pos_device_id']);
            $table->dropForeign(['resolved_by_user_id']);
            $table->dropColumn(['pos_device_id', 'resolved_by_user_id', 'resolved_at']);
        });

        Schema::table('pos_settings', function (Blueprint $table) {
            $table->dropColumn([
                'fiscal_enabled',
                'fiscal_adapter',
                'fiscal_crn',
                'fiscal_tax_number',
                'fiscal_ip_address',
                'fiscal_port',
            ]);
        });

        Schema::table('pos_payments', function (Blueprint $table) {
            $table->dropColumn([
                'fiscal_crn',
                'fiscal_receipt_number',
                'fiscal_qr_data',
                'fiscal_signed_at',
                'fiscal_status',
            ]);
        });

        Schema::dropIfExists('pos_customer_loyalty_transactions');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['loyalty_points', 'loyalty_tier']);
        });

        Schema::dropIfExists('pos_inventory_movements');

        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropColumn(['tracks_inventory', 'stock_quantity']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'tracks_inventory',
                'stock_quantity',
                'low_stock_threshold',
                'allow_out_of_stock_sale',
            ]);
        });
    }
};
