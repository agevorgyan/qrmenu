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
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('currency', 10)->default('AMD');
            $table->boolean('is_active')->default(true);
            $table->boolean('points_enabled')->default(true);
            $table->boolean('cashback_enabled')->default(true);
            $table->boolean('tiers_enabled')->default(true);
            $table->boolean('stamps_enabled')->default(true);
            $table->boolean('referrals_enabled')->default(true);
            $table->decimal('default_points_ratio', 10, 4)->default(1.0000); // 1 spend = X points
            $table->decimal('points_redemption_rate', 10, 4)->default(1.0000); // 1 point = X currency discount
            $table->integer('points_expiration_days')->nullable(); // null = never
            $table->integer('cashback_expiration_days')->nullable();
            $table->boolean('allow_concurrent_earn_and_redeem')->default(false);
            $table->boolean('tax_eligible_rewards')->default(true);
            $table->jsonb('settings')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'is_active']);
        });

        Schema::create('loyalty_program_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('loyalty_programs')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->integer('version_number');
            $table->jsonb('config_snapshot');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['program_id', 'version_number']);
        });

        Schema::create('loyalty_earning_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('loyalty_programs')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('type', 30); // spending, percentage, fixed, product, category, branch, time_window, multiplier
            $table->string('reward_type', 20)->default('points'); // points, cashback
            $table->decimal('rate_value', 10, 4);
            $table->decimal('min_order_spend', 14, 2)->nullable();
            $table->integer('max_points_per_order')->nullable();
            $table->jsonb('conditions')->nullable(); // product_ids, category_ids, branch_ids, days_of_week, start_time, end_time
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['vendor_id', 'is_active', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_earning_rules');
        Schema::dropIfExists('loyalty_program_versions');
        Schema::dropIfExists('loyalty_programs');
    }
};
