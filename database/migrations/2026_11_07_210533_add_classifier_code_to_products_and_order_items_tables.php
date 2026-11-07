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
        if (! Schema::hasColumn('products', 'classifier_code')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('classifier_code', 30)->default('56.10')->after('category_id');
                $table->index(['vendor_id', 'classifier_code']);
            });
        }

        if (! Schema::hasColumn('order_items', 'classifier_code')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->string('classifier_code', 30)->nullable()->after('product_id');
            });
        }

        // Backfill specific FEACN / ԱՏԳԱԱ codes for seeded products if present
        $codeMap = [
            'Truffle Hummus with Warm Pita' => '2106.90.98',
            'Crispy Calamari Rings' => '0307.49.00',
            'Ribeye Steak (350g)' => '0201.20.00',
            'Grilled Norwegian Salmon' => '0304.81.00',
            'Pistachio Molten Lava Cake' => '1905.90.90',
            'Pomegranate Smoked Old Fashioned' => '2208.90.69',
        ];

        foreach ($codeMap as $name => $code) {
            DB::table('products')
                ->where('name', $name)
                ->update(['classifier_code' => $code]);
        }

        // Ensure all remaining products have a valid default FEACN code
        DB::table('products')
            ->whereNull('classifier_code')
            ->orWhere('classifier_code', '')
            ->update(['classifier_code' => '56.10']);

        // Backfill existing order items from products in a DB-agnostic manner
        $products = DB::table('products')->select('id', 'classifier_code')->get();
        foreach ($products as $prod) {
            DB::table('order_items')
                ->where('product_id', $prod->id)
                ->whereNull('classifier_code')
                ->update(['classifier_code' => $prod->classifier_code ?: '56.10']);
        }

        DB::table('order_items')
            ->whereNull('classifier_code')
            ->update(['classifier_code' => '56.10']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('products', 'classifier_code')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex(['vendor_id', 'classifier_code']);
                $table->dropColumn('classifier_code');
            });
        }

        if (Schema::hasColumn('order_items', 'classifier_code')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('classifier_code');
            });
        }
    }
};
