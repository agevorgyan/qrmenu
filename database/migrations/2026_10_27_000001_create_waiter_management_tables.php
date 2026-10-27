<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add waiter profile fields to users table
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'display_name')) {
                $table->string('display_name')->nullable()->after('name');
            }
            if (! Schema::hasColumn('users', 'short_description')) {
                $table->string('short_description', 500)->nullable()->after('display_name');
            }
            if (! Schema::hasColumn('users', 'employee_id')) {
                $table->string('employee_id', 50)->nullable()->after('short_description');
            }
            if (! Schema::hasColumn('users', 'avatar_url')) {
                $table->string('avatar_url', 500)->nullable()->after('employee_id');
            }
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('avatar_url');
            }
            if (! Schema::hasColumn('users', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('is_active');
            }
            if (! Schema::hasColumn('users', 'assigned_table_ids')) {
                $table->json('assigned_table_ids')->nullable()->after('sort_order');
            }
        });

        // 2. Pivot table for assigning waiters to restaurant tables
        if (! Schema::hasTable('restaurant_table_user')) {
            Schema::create('restaurant_table_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_table_id')->constrained('restaurant_tables')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['restaurant_table_id', 'user_id'], 'uniq_tbl_usr');
                $table->index(['vendor_id', 'user_id'], 'idx_tbl_usr_vendor');
                $table->index(['vendor_id', 'restaurant_table_id'], 'idx_tbl_usr_table');
            });
        }

        // 3. Add customer waiter selection configuration fields to tip_rating_settings
        Schema::table('tip_rating_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('tip_rating_settings', 'waiter_selection_stage')) {
                $table->string('waiter_selection_stage', 30)->default('all')->after('allow_waiter_selection'); // 'order', 'bill', 'rating', 'all'
            }
            if (! Schema::hasColumn('tip_rating_settings', 'waiter_selection_required')) {
                $table->boolean('waiter_selection_required')->default(false)->after('waiter_selection_stage');
            }
            if (! Schema::hasColumn('tip_rating_settings', 'show_waiter_photos')) {
                $table->boolean('show_waiter_photos')->default(true)->after('waiter_selection_required');
            }
            if (! Schema::hasColumn('tip_rating_settings', 'show_waiter_descriptions')) {
                $table->boolean('show_waiter_descriptions')->default(true)->after('show_waiter_photos');
            }
            if (! Schema::hasColumn('tip_rating_settings', 'waiter_sort_by')) {
                $table->string('waiter_sort_by', 30)->default('sort_order')->after('show_waiter_descriptions'); // 'sort_order', 'name'
            }
            if (! Schema::hasColumn('tip_rating_settings', 'filter_waiters_by_table')) {
                $table->boolean('filter_waiters_by_table')->default(false)->after('waiter_sort_by');
            }
            if (! Schema::hasColumn('tip_rating_settings', 'waiter_attribution_scope')) {
                $table->string('waiter_attribution_scope', 30)->default('both')->after('filter_waiters_by_table'); // 'both', 'tips', 'ratings', 'none'
            }
        });
    }

    public function down(): void
    {
        Schema::table('tip_rating_settings', function (Blueprint $table) {
            $cols = [
                'waiter_selection_stage',
                'waiter_selection_required',
                'show_waiter_photos',
                'show_waiter_descriptions',
                'waiter_sort_by',
                'filter_waiters_by_table',
                'waiter_attribution_scope',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('tip_rating_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('restaurant_table_user');

        Schema::table('users', function (Blueprint $table) {
            $cols = [
                'display_name',
                'short_description',
                'employee_id',
                'avatar_url',
                'is_active',
                'sort_order',
                'assigned_table_ids',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
