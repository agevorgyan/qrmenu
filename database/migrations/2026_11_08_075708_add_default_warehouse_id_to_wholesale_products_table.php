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
        Schema::table('wholesale_products', function (Blueprint $table) {
            $table->foreignId('default_warehouse_id')
                ->nullable()
                ->after('physical_stock_ingredient_id')
                ->constrained('warehouses')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wholesale_products', function (Blueprint $table) {
            $table->dropForeign(['default_warehouse_id']);
            $table->dropColumn('default_warehouse_id');
        });
    }
};
