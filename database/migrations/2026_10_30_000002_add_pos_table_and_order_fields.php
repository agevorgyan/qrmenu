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
        // 1. Add POS status and occupancy tracking to restaurant_tables
        Schema::table('restaurant_tables', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurant_tables', 'status')) {
                $table->string('status', 30)->default('available')->after('section');
            }
            if (! Schema::hasColumn('restaurant_tables', 'dining_area')) {
                $table->string('dining_area', 100)->nullable()->after('status');
            }
            if (! Schema::hasColumn('restaurant_tables', 'current_order_id')) {
                $table->foreignId('current_order_id')->nullable()->after('dining_area')->constrained('orders')->nullOnDelete();
            }
            if (! Schema::hasColumn('restaurant_tables', 'current_waiter_id')) {
                $table->foreignId('current_waiter_id')->nullable()->after('current_order_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('restaurant_tables', 'occupied_at')) {
                $table->timestamp('occupied_at')->nullable()->after('current_waiter_id');
            }
            if (! Schema::hasColumn('restaurant_tables', 'lock_version')) {
                $table->unsignedInteger('lock_version')->default(1)->after('occupied_at');
            }
        });

        // 2. Add concurrency and lineage fields to orders
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'lock_version')) {
                $table->unsignedInteger('lock_version')->default(1)->after('pos_device_id');
            }
            if (! Schema::hasColumn('orders', 'parent_order_id')) {
                $table->foreignId('parent_order_id')->nullable()->after('lock_version')->constrained('orders')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'split_type')) {
                $table->string('split_type', 30)->nullable()->after('parent_order_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'parent_order_id')) {
                $table->dropForeign(['parent_order_id']);
                $table->dropColumn('parent_order_id');
            }
            if (Schema::hasColumn('orders', 'split_type')) {
                $table->dropColumn('split_type');
            }
            if (Schema::hasColumn('orders', 'lock_version')) {
                $table->dropColumn('lock_version');
            }
        });

        Schema::table('restaurant_tables', function (Blueprint $table) {
            if (Schema::hasColumn('restaurant_tables', 'current_order_id')) {
                $table->dropForeign(['current_order_id']);
                $table->dropColumn('current_order_id');
            }
            if (Schema::hasColumn('restaurant_tables', 'current_waiter_id')) {
                $table->dropForeign(['current_waiter_id']);
                $table->dropColumn('current_waiter_id');
            }
            $columnsToDrop = array_filter(['status', 'dining_area', 'occupied_at', 'lock_version'], fn ($col) => Schema::hasColumn('restaurant_tables', $col));
            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
