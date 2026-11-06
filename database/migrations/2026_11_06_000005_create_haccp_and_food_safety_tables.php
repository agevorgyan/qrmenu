<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('haccp_ccps')) {
            Schema::create('haccp_ccps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->string('name', 200);
                $table->string('code', 60);
                $table->string('type', 50); // receiving, storage, cooking, cooling, holding, reheating, sanitation
                $table->string('target_type', 50)->nullable(); // warehouse, equipment, recipe, ingredient
                $table->decimal('min_value', 8, 2)->nullable();
                $table->decimal('max_value', 8, 2)->nullable();
                $table->string('unit', 20)->default('°C');
                $table->text('critical_limit_description')->nullable();
                $table->text('corrective_action_instructions')->nullable();
                $table->string('frequency', 50)->default('daily'); // per_delivery, twice_daily, shift_start, per_batch
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['vendor_id', 'is_active']);
                $table->index(['vendor_id', 'code']);
            });
        }

        if (! Schema::hasTable('haccp_temperature_logs')) {
            Schema::create('haccp_temperature_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('ccp_id')->nullable()->constrained('haccp_ccps')->nullOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
                $table->foreignId('ingredient_id')->nullable()->constrained('ingredients')->nullOnDelete();
                $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
                $table->string('equipment_name', 150)->nullable();
                $table->decimal('measured_value', 8, 2);
                $table->string('unit', 20)->default('°C');
                $table->boolean('is_breached')->default(false);
                $table->text('corrective_action_taken')->nullable();
                $table->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('logged_at');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['vendor_id', 'logged_at']);
                $table->index(['vendor_id', 'is_breached']);
            });
        }

        if (! Schema::hasTable('haccp_checklists')) {
            Schema::create('haccp_checklists', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->string('title', 200);
                $table->string('type', 50); // opening, closing, sanitation, hygiene, temperature
                $table->string('shift', 30)->nullable(); // morning, afternoon, evening, night
                $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('completed_at')->nullable();
                $table->string('status', 30)->default('completed'); // pending, completed, failed
                $table->text('notes')->nullable();
                $table->jsonb('items')->default('[]'); // array of {task: string, is_passed: bool, remarks: ?string}
                $table->timestamps();

                $table->index(['vendor_id', 'completed_at']);
                $table->index(['vendor_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('haccp_checklists');
        Schema::dropIfExists('haccp_temperature_logs');
        Schema::dropIfExists('haccp_ccps');
    }
};
