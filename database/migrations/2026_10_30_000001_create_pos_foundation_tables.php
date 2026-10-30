<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. POS Devices Table
        Schema::create('pos_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('name');
            $table->string('device_type')->default('terminal'); // terminal, tablet, mobile, kds, gateway
            $table->string('pairing_code', 32)->nullable()->unique();
            $table->string('hardware_identifier')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_paired')->default(false);
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('app_version')->nullable();
            $table->foreignId('registered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vendor_id', 'location_id', 'is_active']);
            $table->index(['vendor_id', 'is_paired']);
        });

        // 2. POS Device Sessions Table
        Schema::create('pos_device_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('pos_devices')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('session_token', 64)->unique();
            $table->string('mode')->default('waiter'); // waiter, cashier, manager, kds
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'is_active']);
            $table->index(['vendor_id', 'user_id', 'is_active']);
        });

        // 3. POS Settings Table
        Schema::create('pos_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->cascadeOnDelete();
            $table->boolean('enable_quick_pin')->default(true);
            $table->boolean('require_table_selection')->default(true);
            $table->integer('auto_lock_seconds')->default(120); // 0 = disabled
            $table->boolean('allow_manual_discounts')->default(true);
            $table->decimal('max_discount_percent', 5, 2)->default(20.00);
            $table->boolean('require_manager_approval_discount')->default(true);
            $table->boolean('require_manager_approval_void')->default(true);
            $table->string('default_order_type')->default('dine_in'); // dine_in, takeaway, delivery
            $table->string('default_payment_method')->default('cash'); // cash, card, mixed
            $table->boolean('allow_offline_orders')->default(true);
            $table->boolean('print_receipt_on_payment')->default(true);
            $table->boolean('print_kitchen_ticket_on_order')->default(true);
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->boolean('tax_inclusive')->default(true);
            $table->decimal('service_charge_percent', 5, 2)->default(0.00);
            $table->json('custom_settings')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'location_id']);
        });

        // 4. POS Operation Logs Table (Audit Trail)
        Schema::create('pos_operation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('pos_devices')->nullOnDelete();
            $table->string('action'); // login, logout, lock, unlock, order_create, order_update, item_void, discount, payment, refund, shift_open, shift_close
            $table->string('entity_type')->nullable(); // Order, Payment, Shift
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['vendor_id', 'action']);
            $table->index(['vendor_id', 'location_id', 'created_at']);
        });

        // 5. Enhance Orders Table for POS Integration
        Schema::table('orders', function (Blueprint $table) {
            $table->string('source')->default('qr_menu')->after('order_number'); // qr_menu, pos, waiter_pos, online, takeaway, delivery, phone
            $table->foreignId('cashier_id')->nullable()->after('waiter_id')->constrained('users')->nullOnDelete();
            $table->foreignId('restaurant_table_id')->nullable()->after('table_number')->constrained('restaurant_tables')->nullOnDelete();
            $table->integer('guest_count')->nullable()->default(1)->after('table_number');
            $table->decimal('discount_amount', 10, 2)->default(0.00)->after('total_amount');
            $table->string('discount_type')->nullable()->after('discount_amount'); // percentage, fixed, promo, employee
            $table->string('discount_reason')->nullable()->after('discount_type');
            $table->decimal('tax_amount', 10, 2)->default(0.00)->after('discount_reason');
            $table->foreignId('pos_device_id')->nullable()->after('tax_amount')->constrained('pos_devices')->nullOnDelete();
            $table->timestamp('opened_at')->nullable()->after('pos_device_id');
            $table->timestamp('closed_at')->nullable()->after('opened_at');

            $table->index(['vendor_id', 'source', 'status']);
            $table->index(['vendor_id', 'location_id', 'restaurant_table_id']);
        });

        // 6. Update PostgreSQL Check Constraints for Enhanced POS Statuses & Types
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('draft', 'held', 'pending', 'accepted', 'confirmed', 'preparing', 'partially_ready', 'ready', 'served', 'completed', 'cancelled'))");
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_type_check');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_type_check CHECK (type IN ('dine_in', 'takeaway', 'delivery', 'whatsapp', 'pos', 'counter', 'quick_sale', 'phone'))");
        }

        // 7. Enhance Order Items Table for Independent Item Status and Modifiers
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('quantity'); // pending, preparing, ready, served, cancelled
            $table->json('variants')->nullable()->after('variation_name');
            $table->json('modifiers')->nullable()->after('variants');
            $table->decimal('tax_amount', 10, 2)->default(0.00)->after('subtotal');
            $table->decimal('discount_amount', 10, 2)->default(0.00)->after('tax_amount');
            $table->string('kitchen_station')->nullable()->after('status'); // kitchen, bar, coffee, dessert, etc.

            $table->index(['order_id', 'status']);
        });

        // 7. Enhance Users Table for POS Quick PIN and Default Mode
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_code', 128)->nullable()->after('password');
            $table->string('pos_mode', 32)->nullable()->default('waiter')->after('pin_code'); // waiter, cashier, manager, kds
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pin_code', 'pos_mode']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'status']);
            $table->dropColumn(['status', 'variants', 'modifiers', 'tax_amount', 'discount_amount', 'kitchen_station']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['vendor_id', 'source', 'status']);
            $table->dropIndex(['vendor_id', 'location_id', 'restaurant_table_id']);
            $table->dropConstrainedForeignId('pos_device_id');
            $table->dropConstrainedForeignId('restaurant_table_id');
            $table->dropConstrainedForeignId('cashier_id');
            $table->dropColumn([
                'source',
                'guest_count',
                'discount_amount',
                'discount_type',
                'discount_reason',
                'tax_amount',
                'opened_at',
                'closed_at',
            ]);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('pending', 'accepted', 'preparing', 'ready', 'completed', 'cancelled'))");
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_type_check');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_type_check CHECK (type IN ('dine_in', 'takeaway', 'delivery', 'whatsapp'))");
        }

        Schema::dropIfExists('pos_operation_logs');
        Schema::dropIfExists('pos_settings');
        Schema::dropIfExists('pos_device_sessions');
        Schema::dropIfExists('pos_devices');
    }
};
