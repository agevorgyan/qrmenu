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
        // 1. Official ATGAA (FEACN / ԱՏԳ ԱԱ) Classifier Directory
        if (! Schema::hasTable('wholesale_atgaa_classifications')) {
            Schema::create('wholesale_atgaa_classifications', function (Blueprint $table) {
                $table->id();
                $table->string('code', 30)->unique(); // e.g. '1602', '1902', '2106.90.98'
                $table->string('name_hy');
                $table->string('name_en')->nullable();
                $table->string('name_ru')->nullable();
                $table->string('section', 50)->nullable();
                $table->boolean('is_common')->default(false);
                $table->timestamps();

                $table->index('code');
                $table->index('is_common');
            });

            // Seed common official Armenian FEACN / ATGAA codes
            $commonCodes = [
                ['code' => '0201', 'name_hy' => 'Տավարի միս՝ թարմ կամ պաղեցրած', 'name_en' => 'Meat of bovine animals, fresh or chilled', 'section' => 'Meat'],
                ['code' => '0202', 'name_hy' => 'Տավարի միս՝ սառեցված', 'name_en' => 'Meat of bovine animals, frozen', 'section' => 'Meat'],
                ['code' => '0207', 'name_hy' => 'Ընտանի թռչնի միս և ենթամթերքներ', 'name_en' => 'Meat and edible offal of poultry', 'section' => 'Poultry'],
                ['code' => '0304', 'name_hy' => 'Ձկան ֆիլե և ձկան այլ միս', 'name_en' => 'Fish fillets and other fish meat', 'section' => 'Fish'],
                ['code' => '0406', 'name_hy' => 'Պանիրներ և կաթնաշոռ', 'name_en' => 'Cheese and curd', 'section' => 'Dairy'],
                ['code' => '0901', 'name_hy' => 'Սուրճ, բոված կամ չբոված', 'name_en' => 'Coffee, roasted or not', 'section' => 'Beverages'],
                ['code' => '1601', 'name_hy' => 'Երշիկներ, նրբերշիկներ և համանման մսամթերք', 'name_en' => 'Sausages and similar products of meat', 'section' => 'Meat Preparations'],
                ['code' => '1602', 'name_hy' => 'Մսից այլ պատրաստի կամ պահածոյացված մթերք (Պելմենի, կոտլետներ)', 'name_en' => 'Other prepared or preserved meat (Cutlets, meatballs)', 'section' => 'Meat Preparations'],
                ['code' => '1902', 'name_hy' => 'Մակարոնեղեն, լցոնած խմորեղեն (Պելմենի, վարենիկներ, խինկալի)', 'name_en' => 'Pasta and stuffed dough products (Pelmeni, dumplings)', 'section' => 'Bakery & Pasta'],
                ['code' => '1905', 'name_hy' => 'Հացաբուլկեղեն, խմորեղեն, տորթեր, թխվածքաբլիթներ', 'name_en' => 'Bread, pastry, cakes, biscuits', 'section' => 'Bakery'],
                ['code' => '2005', 'name_hy' => 'Այլ բանջարեղեն՝ պատրաստված կամ պահածոյացված', 'name_en' => 'Other vegetables prepared or preserved', 'section' => 'Vegetables'],
                ['code' => '2103', 'name_hy' => 'Սոուսներ և դրանց պատրաստուկներ, համեմունքներ', 'name_en' => 'Sauces and preparations thereof, condiments', 'section' => 'Condiments'],
                ['code' => '2106', 'name_hy' => 'Սննդամթերք՝ այլ տեղ չնշված (կիսապատրաստուկներ)', 'name_en' => 'Food preparations not elsewhere specified', 'section' => 'Preparations'],
                ['code' => '2202', 'name_hy' => 'Ջրեր, հանքային ջրեր, հովացուցիչ ըմպելիքներ', 'name_en' => 'Waters, mineral waters and soft drinks', 'section' => 'Beverages'],
                ['code' => '2208', 'name_hy' => 'Սպիրտային թրմօղիներ, լիկյորներ և ոգելից ըմպելիքներ', 'name_en' => 'Spirits, liqueurs and other spirituous beverages', 'section' => 'Alcohol'],
                ['code' => '56.10', 'name_hy' => 'Ռեստորանների և սննդի ծառայությունների մատուցում', 'name_en' => 'Restaurant and food service activities', 'section' => 'Services'],
            ];

            foreach ($commonCodes as $row) {
                DB::table('wholesale_atgaa_classifications')->insert([
                    'code' => $row['code'],
                    'name_hy' => $row['name_hy'],
                    'name_en' => $row['name_en'],
                    'section' => $row['section'],
                    'is_common' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2. Hierarchical Wholesale Product Categories
        if (! Schema::hasTable('wholesale_categories')) {
            Schema::create('wholesale_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->string('name', 150);
                $table->foreignId('parent_id')->nullable()->constrained('wholesale_categories')->nullOnDelete();
                $table->text('description')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['vendor_id', 'is_active'], 'idx_wholesale_cat_vendor_status');
                $table->index(['vendor_id', 'parent_id'], 'idx_wholesale_cat_parent');
            });
        }

        // 3. Independent Wholesale Product Catalog
        if (! Schema::hasTable('wholesale_products')) {
            Schema::create('wholesale_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('category_id')->nullable()->constrained('wholesale_categories')->nullOnDelete();
                $table->string('name', 255);
                $table->string('sku', 80);
                $table->string('barcode', 100)->nullable();
                $table->string('gtin', 50)->nullable();
                $table->string('classifier_code', 30)->default('1602'); // Official ATGAA / FEACN code
                $table->foreignId('unit_id')->constrained('units');
                $table->foreignId('packaging_unit_id')->nullable()->constrained('units')->nullOnDelete();
                $table->decimal('units_per_package', 12, 4)->default(1.0000);
                $table->decimal('min_order_quantity', 12, 4)->default(1.0000);
                $table->decimal('order_quantity_multiple', 12, 4)->default(1.0000);
                $table->decimal('cost_price', 14, 4)->default(0.0000);
                $table->decimal('base_wholesale_price', 14, 4);
                $table->decimal('min_wholesale_price', 14, 4);
                $table->boolean('is_vat_applicable')->default(true);
                $table->decimal('vat_rate', 5, 2)->default(20.00);
                $table->foreignId('physical_stock_ingredient_id')->nullable()->constrained('ingredients')->nullOnDelete();
                $table->text('description')->nullable();
                $table->string('image')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['vendor_id', 'sku'], 'uq_wholesale_prod_vendor_sku');
                $table->index(['vendor_id', 'barcode'], 'idx_wholesale_prod_barcode');
                $table->index(['vendor_id', 'classifier_code'], 'idx_wholesale_prod_classifier');
                $table->index(['vendor_id', 'is_active'], 'idx_wholesale_prod_status');
            });
        }

        // 4. Packaging Specifications
        if (! Schema::hasTable('wholesale_product_packagings')) {
            Schema::create('wholesale_product_packagings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('wholesale_products')->cascadeOnDelete();
                $table->string('packaging_type', 50); // e.g. box, case, pallet, master_carton
                $table->string('packaging_barcode', 100)->nullable();
                $table->decimal('units_count', 12, 4);
                $table->decimal('gross_weight_kg', 10, 4)->nullable();
                $table->string('dimensions', 50)->nullable(); // e.g. 30x20x15 cm
                $table->timestamps();

                $table->index('product_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wholesale_product_packagings');
        Schema::dropIfExists('wholesale_products');
        Schema::dropIfExists('wholesale_categories');
        Schema::dropIfExists('wholesale_atgaa_classifications');
    }
};
