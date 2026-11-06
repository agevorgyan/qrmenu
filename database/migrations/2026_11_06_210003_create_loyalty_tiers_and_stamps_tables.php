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
        // 1. Membership Tiers
        Schema::create('loyalty_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('loyalty_programs')->cascadeOnDelete();
            $table->integer('level'); // 1=Bronze, 2=Silver, 3=Gold, 4=Platinum
            $table->string('name', 100);
            $table->integer('min_points')->default(0);
            $table->decimal('min_spend', 14, 2)->default(0.00);
            $table->integer('evaluation_period_days')->default(365);
            $table->decimal('points_multiplier', 6, 2)->default(1.00);
            $table->decimal('cashback_percent', 5, 2)->default(0.00);
            $table->decimal('order_discount_percent', 5, 2)->default(0.00);
            $table->string('color_hex', 20)->default('#6366f1');
            $table->string('icon_name', 50)->default('star');
            $table->text('perks_description')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'level']);
            $table->index(['vendor_id', 'min_points', 'min_spend']);
        });

        // 2. Tier History
        Schema::create('loyalty_tier_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('tier_id')->constrained('loyalty_tiers')->cascadeOnDelete();
            $table->foreignId('previous_tier_id')->nullable()->constrained('loyalty_tiers')->nullOnDelete();
            $table->string('reason', 50); // points_threshold, spend_threshold, admin_adjustment, downgrade
            $table->timestamp('effective_until')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['vendor_id', 'customer_id', 'created_at']);
        });

        // 3. Stamp Programs
        Schema::create('loyalty_stamp_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->integer('stamps_required')->default(10);
            $table->decimal('min_order_spend', 14, 2)->default(0.00);
            $table->jsonb('qualifying_category_ids')->nullable();
            $table->jsonb('qualifying_product_ids')->nullable();
            $table->text('reward_description');
            $table->foreignId('reward_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('reward_discount_amount', 14, 2)->nullable();
            $table->integer('card_validity_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['vendor_id', 'is_active']);
        });

        // 4. Customer Stamp Cards
        Schema::create('loyalty_stamp_cards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('loyalty_stamp_programs')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->integer('stamps_collected')->default(0);
            $table->integer('stamps_required');
            $table->string('status', 20)->default('active'); // active, completed, redeemed, expired
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'customer_id', 'status']);
        });

        // 5. Stamp Transactions
        Schema::create('loyalty_stamp_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('card_id')->constrained('loyalty_stamp_cards')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20); // awarded, redeemed, reversed
            $table->integer('stamps');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['vendor_id', 'card_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_stamp_transactions');
        Schema::dropIfExists('loyalty_stamp_cards');
        Schema::dropIfExists('loyalty_stamp_programs');
        Schema::dropIfExists('loyalty_tier_histories');
        Schema::dropIfExists('loyalty_tiers');
    }
};
