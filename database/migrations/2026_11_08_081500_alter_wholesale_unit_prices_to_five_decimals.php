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
        if (Schema::hasTable('wholesale_order_items')) {
            Schema::table('wholesale_order_items', function (Blueprint $table) {
                $table->decimal('unit_price', 16, 5)->change();
            });
        }

        if (Schema::hasTable('wholesale_sale_items')) {
            Schema::table('wholesale_sale_items', function (Blueprint $table) {
                $table->decimal('unit_price', 16, 5)->change();
            });
        }

        if (Schema::hasTable('wholesale_return_items')) {
            Schema::table('wholesale_return_items', function (Blueprint $table) {
                $table->decimal('unit_price', 16, 5)->change();
            });
        }

        if (Schema::hasTable('wholesale_tax_document_items')) {
            Schema::table('wholesale_tax_document_items', function (Blueprint $table) {
                $table->decimal('unit_price', 16, 5)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('wholesale_order_items')) {
            Schema::table('wholesale_order_items', function (Blueprint $table) {
                $table->decimal('unit_price', 14, 4)->change();
            });
        }

        if (Schema::hasTable('wholesale_sale_items')) {
            Schema::table('wholesale_sale_items', function (Blueprint $table) {
                $table->decimal('unit_price', 14, 4)->change();
            });
        }

        if (Schema::hasTable('wholesale_return_items')) {
            Schema::table('wholesale_return_items', function (Blueprint $table) {
                $table->decimal('unit_price', 14, 4)->change();
            });
        }

        if (Schema::hasTable('wholesale_tax_document_items')) {
            Schema::table('wholesale_tax_document_items', function (Blueprint $table) {
                $table->decimal('unit_price', 15, 4)->change();
            });
        }
    }
};
