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
        // 1. Units of measurement
        if (! Schema::hasTable('units')) {
            Schema::create('units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('code', 30);
                $table->string('name', 100);
                $table->jsonb('name_translations')->nullable();
                $table->string('type', 30)->default('mass'); // 'mass', 'volume', 'piece', 'packaging'
                $table->foreignId('base_unit_id')->nullable()->constrained('units')->nullOnDelete();
                $table->decimal('base_factor', 14, 6)->default(1.0);
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['vendor_id', 'code']);
                $table->index(['is_system', 'type']);
            });
        }

        // 2. Unit conversions
        if (! Schema::hasTable('unit_conversions')) {
            Schema::create('unit_conversions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->nullable()->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('ingredient_id')->nullable(); // will foreign-key after ingredients table
                $table->foreignId('from_unit_id')->constrained('units')->cascadeOnDelete();
                $table->foreignId('to_unit_id')->constrained('units')->cascadeOnDelete();
                $table->decimal('factor', 14, 6);
                $table->timestamps();

                $table->index(['vendor_id', 'from_unit_id', 'to_unit_id']);
            });
        }

        // 3. Hierarchical ingredient categories
        if (! Schema::hasTable('ingredient_categories')) {
            Schema::create('ingredient_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('ingredient_categories')->nullOnDelete();
                $table->string('name', 150);
                $table->jsonb('name_translations')->nullable();
                $table->text('description')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['vendor_id', 'parent_id']);
                $table->index(['vendor_id', 'sort_order']);
            });
        }

        // 4. Ingredients and raw materials catalog
        if (! Schema::hasTable('ingredients')) {
            Schema::create('ingredients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('category_id')->nullable()->constrained('ingredient_categories')->nullOnDelete();
                $table->string('name', 200);
                $table->jsonb('name_translations')->nullable();
                $table->string('sku', 60)->nullable();
                $table->string('barcode', 100)->nullable();
                $table->string('type', 40)->default('ingredient'); // raw_material, ingredient, semi_finished, finished, packaging, consumable
                $table->text('description')->nullable();
                $table->string('image')->nullable();
                $table->foreignId('unit_id')->constrained('units');
                $table->decimal('min_stock', 12, 4)->default(0);
                $table->decimal('max_stock', 12, 4)->nullable();
                $table->decimal('reorder_point', 12, 4)->nullable();
                $table->decimal('safety_stock', 12, 4)->nullable();
                $table->decimal('last_purchase_price', 12, 2)->default(0);
                $table->decimal('current_cost', 12, 4)->default(0);
                $table->string('storage_conditions', 100)->nullable(); // Ambient, Refrigerated, Frozen, Dry
                $table->decimal('storage_temperature_min', 5, 2)->nullable();
                $table->decimal('storage_temperature_max', 5, 2)->nullable();
                $table->integer('shelf_life_days')->nullable();
                $table->decimal('calories', 8, 2)->nullable();
                $table->decimal('protein_g', 8, 2)->nullable();
                $table->decimal('carbs_g', 8, 2)->nullable();
                $table->decimal('fat_g', 8, 2)->nullable();
                $table->decimal('density_kg_per_l', 8, 4)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['vendor_id', 'type']);
                $table->index(['vendor_id', 'sku']);
                $table->index(['vendor_id', 'category_id']);
            });

            // Add foreign key constraint to unit_conversions for ingredient_id
            Schema::table('unit_conversions', function (Blueprint $table) {
                $table->foreign('ingredient_id')->references('id')->on('ingredients')->cascadeOnDelete();
            });
        }

        // 5. Ingredient allergens pivot
        if (! Schema::hasTable('ingredient_allergens')) {
            Schema::create('ingredient_allergens', function (Blueprint $table) {
                $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
                $table->foreignId('allergen_id')->constrained('allergens')->cascadeOnDelete();
                $table->primary(['ingredient_id', 'allergen_id']);
            });
        }

        // 6. Warehouses
        if (! Schema::hasTable('warehouses')) {
            Schema::create('warehouses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->string('name', 150);
                $table->string('code', 50)->nullable();
                $table->string('address')->nullable();
                $table->string('type', 40)->default('main'); // main, kitchen, cold_room, freezer, production, finished_goods, bar
                $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('storage_conditions', 100)->nullable();
                $table->decimal('temperature_min', 5, 2)->nullable();
                $table->decimal('temperature_max', 5, 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['vendor_id', 'is_active']);
                $table->index(['vendor_id', 'location_id']);
            });
        }

        // 7. Inventory balances
        if (! Schema::hasTable('inventory_balances')) {
            Schema::create('inventory_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
                $table->unsignedBigInteger('batch_id')->nullable(); // Phase 4 link
                $table->decimal('quantity_on_hand', 12, 4)->default(0);
                $table->decimal('quantity_reserved', 12, 4)->default(0);
                $table->decimal('unit_cost', 12, 4)->default(0);
                $table->decimal('total_value', 14, 2)->default(0);
                $table->timestamp('last_movement_at')->nullable();
                $table->timestamps();

                $table->index(['vendor_id', 'warehouse_id']);
                $table->index(['vendor_id', 'ingredient_id']);
                $table->unique(['warehouse_id', 'ingredient_id', 'batch_id'], 'inv_balance_unique');
            });
        }

        // 8. Immutable inventory ledger transactions
        if (! Schema::hasTable('inventory_transactions')) {
            Schema::create('inventory_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->string('transaction_number', 60);
                $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->foreignId('target_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
                $table->string('type', 50); // receipt, issue, transfer, adjustment, count, reservation, release, waste, production_consumption, production_receipt, order_consumption, order_refund
                $table->string('status', 30)->default('completed'); // draft, pending, completed, cancelled
                $table->date('date');
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->string('idempotency_key', 120)->nullable();
                $table->timestamps();

                $table->index(['vendor_id', 'type']);
                $table->index(['vendor_id', 'transaction_number']);
                $table->index(['vendor_id', 'idempotency_key']);
                $table->index(['reference_type', 'reference_id']);
            });
        }

        // 9. Inventory transaction items
        if (! Schema::hasTable('inventory_transaction_items')) {
            Schema::create('inventory_transaction_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('transaction_id')->constrained('inventory_transactions')->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
                $table->unsignedBigInteger('batch_id')->nullable();
                $table->foreignId('unit_id')->constrained('units');
                $table->decimal('quantity', 12, 4);
                $table->decimal('unit_cost', 12, 4)->default(0);
                $table->decimal('total_cost', 14, 2)->default(0);
                $table->string('notes', 255)->nullable();
                $table->timestamps();

                $table->index(['transaction_id', 'ingredient_id'], 'inv_tx_item_idx');
            });
        }

        // Seed system default standard units
        $this->seedStandardUnits();
    }

    /**
     * Seed universal system units and baseline conversions.
     */
    protected function seedStandardUnits(): void
    {
        $now = now();
        $units = [
            // Mass
            [
                'code' => 'kg',
                'name' => 'Kilogram',
                'name_translations' => json_encode(['hy' => 'Կիլոգրամ', 'en' => 'Kilogram', 'ru' => 'Килограмм']),
                'type' => 'mass',
                'base_unit_id' => null,
                'base_factor' => 1.0,
                'is_system' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'g',
                'name' => 'Gram',
                'name_translations' => json_encode(['hy' => 'Գրամ', 'en' => 'Gram', 'ru' => 'Грамм']),
                'type' => 'mass',
                'base_unit_id' => null,
                'base_factor' => 0.001,
                'is_system' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'ton',
                'name' => 'Metric Ton',
                'name_translations' => json_encode(['hy' => 'Տոննա', 'en' => 'Metric Ton', 'ru' => 'Тонна']),
                'type' => 'mass',
                'base_unit_id' => null,
                'base_factor' => 1000.0,
                'is_system' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            // Volume
            [
                'code' => 'l',
                'name' => 'Liter',
                'name_translations' => json_encode(['hy' => 'Լիտր', 'en' => 'Liter', 'ru' => 'Литр']),
                'type' => 'volume',
                'base_unit_id' => null,
                'base_factor' => 1.0,
                'is_system' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'ml',
                'name' => 'Milliliter',
                'name_translations' => json_encode(['hy' => 'Միլիլիտր', 'en' => 'Milliliter', 'ru' => 'Миллилитр']),
                'type' => 'volume',
                'base_unit_id' => null,
                'base_factor' => 0.001,
                'is_system' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            // Pieces & Portions
            [
                'code' => 'piece',
                'name' => 'Piece',
                'name_translations' => json_encode(['hy' => 'Հատ', 'en' => 'Piece', 'ru' => 'Штука']),
                'type' => 'piece',
                'base_unit_id' => null,
                'base_factor' => 1.0,
                'is_system' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'portion',
                'name' => 'Portion',
                'name_translations' => json_encode(['hy' => 'Բաժին', 'en' => 'Portion', 'ru' => 'Порция']),
                'type' => 'piece',
                'base_unit_id' => null,
                'base_factor' => 1.0,
                'is_system' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            // Packaging
            [
                'code' => 'pack',
                'name' => 'Pack',
                'name_translations' => json_encode(['hy' => 'Տուփ / Փաթեթ', 'en' => 'Pack', 'ru' => 'Упаковка']),
                'type' => 'packaging',
                'base_unit_id' => null,
                'base_factor' => 1.0,
                'is_system' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'box',
                'name' => 'Box',
                'name_translations' => json_encode(['hy' => 'Արկղ', 'en' => 'Box', 'ru' => 'Коробка']),
                'type' => 'packaging',
                'base_unit_id' => null,
                'base_factor' => 1.0,
                'is_system' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'bottle',
                'name' => 'Bottle',
                'name_translations' => json_encode(['hy' => 'Շիշ', 'en' => 'Bottle', 'ru' => 'Бутылка']),
                'type' => 'packaging',
                'base_unit_id' => null,
                'base_factor' => 1.0,
                'is_system' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($units as $unit) {
            DB::table('units')->updateOrInsert(
                ['vendor_id' => null, 'code' => $unit['code']],
                $unit
            );
        }

        // Link base units
        $kg = DB::table('units')->whereNull('vendor_id')->where('code', 'kg')->first();
        $liter = DB::table('units')->whereNull('vendor_id')->where('code', 'l')->first();

        if ($kg) {
            DB::table('units')->whereNull('vendor_id')->where('code', 'g')->update(['base_unit_id' => $kg->id]);
            DB::table('units')->whereNull('vendor_id')->where('code', 'ton')->update(['base_unit_id' => $kg->id]);
        }
        if ($liter) {
            DB::table('units')->whereNull('vendor_id')->where('code', 'ml')->update(['base_unit_id' => $liter->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transaction_items');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('ingredient_allergens');
        Schema::dropIfExists('unit_conversions');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('ingredient_categories');
        Schema::dropIfExists('units');
    }
};
