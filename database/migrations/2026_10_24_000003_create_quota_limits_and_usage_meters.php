<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add quota_limits JSON column to subscription_plans
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->json('quota_limits')->nullable()->after('feature_codes');
        });

        // 2. Create tenant_quota_overrides table
        Schema::create('tenant_quota_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('quota_key', 64);
            $table->bigInteger('quota_limit')->comment('-1 indicates unlimited, >= 0 indicates fixed limit');
            $table->string('reason');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['vendor_id', 'quota_key'], 'uq_tenant_quota_overrides_vendor_quota');
            $table->index(['vendor_id', 'expires_at'], 'idx_tenant_quota_overrides_lookup');
        });

        // 3. Create tenant_usage_meters table (aggregated billing-period usage)
        Schema::create('tenant_usage_meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('quota_key', 64);
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->bigInteger('used_count')->default(0);
            $table->timestamps();

            $table->unique(['vendor_id', 'quota_key', 'period_start', 'period_end'], 'uq_tenant_usage_meters_vendor_period');
            $table->index(['vendor_id', 'quota_key'], 'idx_tenant_usage_meters_vendor_quota');
        });

        // 4. Create tenant_usage_events table (audit log & idempotency ledger)
        Schema::create('tenant_usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('quota_key', 64);
            $table->integer('quantity')->default(1);
            $table->string('idempotency_key', 128)->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['vendor_id', 'quota_key', 'idempotency_key'], 'uq_tenant_usage_events_idempotency');
            $table->index(['vendor_id', 'quota_key', 'recorded_at'], 'idx_tenant_usage_events_history');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_usage_events');
        Schema::dropIfExists('tenant_usage_meters');
        Schema::dropIfExists('tenant_quota_overrides');

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('quota_limits');
        });
    }
};
