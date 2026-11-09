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
        if (Schema::hasTable('wholesale_products') && ! Schema::hasColumn('wholesale_products', 'recipe_id')) {
            Schema::table('wholesale_products', function (Blueprint $table) {
                $table->foreignId('recipe_id')->nullable()->after('default_warehouse_id')->constrained('recipes')->nullOnDelete();
            });
        }

        if (Schema::hasTable('recipes') && ! Schema::hasColumn('recipes', 'wholesale_product_id')) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->foreignId('wholesale_product_id')->nullable()->after('product_id')->constrained('wholesale_products')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('recipes') && Schema::hasColumn('recipes', 'wholesale_product_id')) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->dropForeign(['wholesale_product_id']);
                $table->dropColumn('wholesale_product_id');
            });
        }

        if (Schema::hasTable('wholesale_products') && Schema::hasColumn('wholesale_products', 'recipe_id')) {
            Schema::table('wholesale_products', function (Blueprint $table) {
                $table->dropForeign(['recipe_id']);
                $table->dropColumn('recipe_id');
            });
        }
    }
};
