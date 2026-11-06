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
        // 1. Recipes & Technical Cards
        if (! Schema::hasTable('recipes')) {
            Schema::create('recipes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->foreignId('produced_ingredient_id')->nullable()->constrained('ingredients')->nullOnDelete();
                $table->foreignId('category_id')->nullable()->constrained('ingredient_categories')->nullOnDelete();
                $table->string('name', 200);
                $table->jsonb('name_translations')->nullable();
                $table->string('code', 60);
                $table->string('type', 40)->default('dish'); // dish, semi_finished, sauce, bakery, beverage, prep
                $table->string('status', 30)->default('draft'); // draft, pending_approval, approved, active, archived
                $table->text('description')->nullable();
                $table->text('instructions')->nullable();
                $table->decimal('yield_quantity', 12, 4)->default(1.0000);
                $table->foreignId('yield_unit_id')->constrained('units');
                $table->decimal('portion_size', 12, 4)->nullable();
                $table->foreignId('portion_unit_id')->nullable()->constrained('units')->nullOnDelete();
                $table->integer('prep_time_minutes')->nullable();
                $table->integer('cook_time_minutes')->nullable();
                $table->string('storage_conditions', 150)->nullable();
                $table->integer('shelf_life_hours')->nullable();
                $table->decimal('selling_price', 12, 2)->nullable();
                $table->decimal('target_food_cost_percent', 5, 2)->nullable();
                $table->text('critical_control_points')->nullable();
                $table->string('image')->nullable();
                $table->integer('current_version')->default(1);
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['vendor_id', 'code']);
                $table->index(['vendor_id', 'product_id']);
                $table->index(['vendor_id', 'produced_ingredient_id']);
                $table->index(['vendor_id', 'type']);
                $table->index(['vendor_id', 'status']);
                $table->index(['vendor_id', 'is_active']);
            });
        }

        // 2. Recipe Versions
        if (! Schema::hasTable('recipe_versions')) {
            Schema::create('recipe_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
                $table->integer('version_number');
                $table->text('change_summary')->nullable();
                $table->decimal('yield_quantity', 12, 4);
                $table->foreignId('yield_unit_id')->constrained('units');
                $table->decimal('portion_size', 12, 4)->nullable();
                $table->foreignId('portion_unit_id')->nullable()->constrained('units')->nullOnDelete();
                $table->text('instructions')->nullable();
                $table->decimal('calculated_cost', 14, 4)->default(0);
                $table->decimal('portion_cost', 14, 4)->default(0);
                $table->decimal('food_cost_percent', 5, 2)->nullable();
                $table->string('status', 30)->default('draft'); // draft, approved, archived
                $table->jsonb('snapshot_data')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['recipe_id', 'version_number']);
                $table->index(['vendor_id', 'recipe_id']);
            });
        }

        // 3. Recipe Items (Ingredients and Sub-Recipes)
        if (! Schema::hasTable('recipe_items')) {
            Schema::create('recipe_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
                $table->foreignId('recipe_version_id')->nullable()->constrained('recipe_versions')->nullOnDelete();
                $table->string('type', 30)->default('ingredient'); // ingredient, sub_recipe
                $table->foreignId('ingredient_id')->nullable()->constrained('ingredients')->cascadeOnDelete();
                $table->foreignId('sub_recipe_id')->nullable()->constrained('recipes')->cascadeOnDelete();
                $table->decimal('quantity', 12, 4);
                $table->foreignId('unit_id')->constrained('units');
                $table->decimal('gross_weight', 12, 4);
                $table->decimal('net_weight', 12, 4);
                $table->decimal('prep_loss_percent', 5, 2)->default(0.00);
                $table->decimal('cooking_loss_percent', 5, 2)->default(0.00);
                $table->decimal('final_weight', 12, 4)->nullable();
                $table->decimal('unit_cost', 14, 4)->default(0);
                $table->decimal('total_cost', 14, 4)->default(0);
                $table->integer('sort_order')->default(0);
                $table->string('notes', 255)->nullable();
                $table->boolean('is_optional')->default(false);
                $table->timestamps();

                $table->index(['recipe_id', 'sort_order']);
                $table->index(['vendor_id', 'ingredient_id']);
                $table->index(['vendor_id', 'sub_recipe_id']);
            });
        }

        // 4. Recipe Packaging
        if (! Schema::hasTable('recipe_packagings')) {
            Schema::create('recipe_packagings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
                $table->decimal('quantity', 12, 4);
                $table->foreignId('unit_id')->constrained('units');
                $table->decimal('unit_cost', 14, 4)->default(0);
                $table->decimal('total_cost', 14, 4)->default(0);
                $table->timestamps();

                $table->index(['recipe_id']);
                $table->index(['vendor_id', 'ingredient_id']);
            });
        }

        // 5. Recipe Costs (Calculation Breakdown & Historical Tracking)
        if (! Schema::hasTable('recipe_costs')) {
            Schema::create('recipe_costs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
                $table->foreignId('recipe_version_id')->nullable()->constrained('recipe_versions')->nullOnDelete();
                $table->decimal('ingredients_cost', 14, 4)->default(0);
                $table->decimal('packaging_cost', 14, 4)->default(0);
                $table->decimal('labor_cost', 14, 4)->default(0);
                $table->decimal('overhead_cost', 14, 4)->default(0);
                $table->decimal('total_cost', 14, 4)->default(0);
                $table->decimal('portion_cost', 14, 4)->default(0);
                $table->decimal('selling_price', 12, 2)->nullable();
                $table->decimal('food_cost_percent', 5, 2)->nullable();
                $table->decimal('gross_profit', 14, 4)->nullable();
                $table->decimal('profit_margin_percent', 5, 2)->nullable();
                $table->string('cost_method', 30)->default('weighted_average'); // weighted_average, last_purchase_price
                $table->timestamp('calculated_at');
                $table->timestamps();

                $table->index(['recipe_id', 'calculated_at']);
                $table->index(['vendor_id', 'recipe_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_costs');
        Schema::dropIfExists('recipe_packagings');
        Schema::dropIfExists('recipe_items');
        Schema::dropIfExists('recipe_versions');
        Schema::dropIfExists('recipes');
    }
};

