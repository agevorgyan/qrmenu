<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_orders')) {
            Schema::create('production_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->string('order_number', 60);
                $table->foreignId('recipe_id')->constrained('recipes')->restrictOnDelete();
                $table->foreignId('produced_ingredient_id')->nullable()->constrained('ingredients')->nullOnDelete();
                $table->foreignId('source_warehouse_id')->constrained('warehouses')->restrictOnDelete();
                $table->foreignId('destination_warehouse_id')->constrained('warehouses')->restrictOnDelete();
                $table->decimal('planned_quantity', 12, 4);
                $table->decimal('actual_quantity', 12, 4)->nullable();
                $table->foreignId('unit_id')->constrained('units');
                $table->string('status', 30)->default('draft'); // draft, in_progress, completed, cancelled
                $table->timestamp('planned_start_at')->nullable();
                $table->timestamp('actual_start_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('produced_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('batch_number', 60)->nullable();
                $table->date('expiry_date')->nullable();
                $table->decimal('labor_cost', 12, 4)->default(0.0000);
                $table->decimal('overhead_cost', 12, 4)->default(0.0000);
                $table->decimal('material_cost', 12, 4)->default(0.0000);
                $table->decimal('total_cost', 12, 4)->default(0.0000);
                $table->decimal('unit_cost', 12, 4)->default(0.0000);
                $table->decimal('yield_variance_percent', 8, 2)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('issue_transaction_id')->nullable()->constrained('inventory_transactions')->nullOnDelete();
                $table->foreignId('receipt_transaction_id')->nullable()->constrained('inventory_transactions')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['vendor_id', 'order_number']);
                $table->index(['vendor_id', 'status']);
                $table->index(['vendor_id', 'recipe_id']);
            });
        }

        if (! Schema::hasTable('production_order_items')) {
            Schema::create('production_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
                $table->foreignId('ingredient_id')->nullable()->constrained('ingredients')->nullOnDelete();
                $table->foreignId('sub_recipe_id')->nullable()->constrained('recipes')->nullOnDelete();
                $table->decimal('planned_quantity', 12, 4);
                $table->decimal('actual_quantity', 12, 4)->nullable();
                $table->decimal('waste_quantity', 12, 4)->default(0.0000);
                $table->foreignId('unit_id')->constrained('units');
                $table->decimal('unit_cost', 12, 4)->default(0.0000);
                $table->decimal('total_cost', 12, 4)->default(0.0000);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['vendor_id', 'production_order_id']);
            });
        }

        if (! Schema::hasTable('inventory_batches')) {
            Schema::create('inventory_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->string('batch_number', 60);
                $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->decimal('initial_quantity', 12, 4);
                $table->decimal('remaining_quantity', 12, 4);
                $table->foreignId('unit_id')->constrained('units');
                $table->decimal('unit_cost', 12, 4)->default(0.0000);
                $table->date('production_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->string('status', 30)->default('active'); // active, quarantined, expired, depleted
                $table->string('source_type', 40); // purchase, production, opening_balance, transfer
                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
                $table->foreignId('production_order_id')->nullable()->constrained('production_orders')->nullOnDelete();
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['vendor_id', 'batch_number']);
                $table->index(['vendor_id', 'ingredient_id']);
                $table->index(['vendor_id', 'warehouse_id']);
                $table->index(['vendor_id', 'status']);
                $table->index(['vendor_id', 'expiry_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_batches');
        Schema::dropIfExists('production_order_items');
        Schema::dropIfExists('production_orders');
    }
};
