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
        Schema::table('supplier_invoice_items', function (Blueprint $table) {
            $table->decimal('original_quantity', 12, 4)->nullable()->after('quantity');
            $table->string('original_unit_name', 50)->nullable()->after('unit_name');
            $table->decimal('conversion_factor', 12, 4)->nullable()->after('original_quantity');
            $table->boolean('is_converted')->default(false)->after('total_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_invoice_items', function (Blueprint $table) {
            $table->dropColumn([
                'original_quantity',
                'original_unit_name',
                'conversion_factor',
                'is_converted',
            ]);
        });
    }
};
