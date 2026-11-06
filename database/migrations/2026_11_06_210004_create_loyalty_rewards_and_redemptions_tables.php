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
        // 1. Rewards Catalog
        Schema::create('loyalty_rewards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('reward_type', 30); // free_product, fixed_discount, percentage_discount, voucher
            $table->integer('points_cost')->default(0);
            $table->decimal('discount_value', 14, 2)->default(0.00);
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('required_tier_id')->nullable()->constrained('loyalty_tiers')->nullOnDelete();
            $table->integer('stock_quantity')->nullable(); // null = unlimited
            $table->integer('redeemed_count')->default(0);
            $table->integer('max_per_customer')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'is_active', 'points_cost']);
        });

        // 2. Redemptions
        Schema::create('loyalty_reward_redemptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('reward_id')->constrained('loyalty_rewards')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('redemption_code', 32)->unique();
            $table->integer('points_spent');
            $table->string('status', 20)->default('pending'); // pending, used, cancelled, expired
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_reward_redemptions');
        Schema::dropIfExists('loyalty_rewards');
    }
};
