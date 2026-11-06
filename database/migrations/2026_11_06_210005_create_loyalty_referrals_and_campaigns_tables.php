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
        // 1. Referrals
        Schema::create('loyalty_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('referrer_customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('referred_customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('referral_code', 32);
            $table->string('status', 20)->default('registered'); // registered, qualified, rewarded, fraud_rejected
            $table->foreignId('qualifying_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->integer('referrer_points_awarded')->default(0);
            $table->integer('referred_points_awarded')->default(0);
            $table->timestamp('qualified_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'referral_code']);
            $table->index(['vendor_id', 'referrer_customer_id']);
        });

        // 2. Campaigns
        Schema::create('loyalty_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('trigger_type', 30); // welcome, birthday, first_order, inactive_customer, seasonal, custom
            $table->string('reward_type', 20); // points, cashback, reward_voucher
            $table->decimal('reward_value', 14, 2);
            $table->foreignId('reward_id')->nullable()->constrained('loyalty_rewards')->nullOnDelete();
            $table->integer('days_inactive')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('target_audience')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'trigger_type', 'is_active']);
        });

        // 3. Campaign Events
        Schema::create('loyalty_campaign_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained('loyalty_campaigns')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->text('reward_granted');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['vendor_id', 'campaign_id', 'customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_campaign_events');
        Schema::dropIfExists('loyalty_campaigns');
        Schema::dropIfExists('loyalty_referrals');
    }
};
