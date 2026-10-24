<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->json('feature_codes')->nullable()->after('features');
        });

        Schema::create('tenant_feature_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('feature_key', 100);
            $table->boolean('is_enabled')->default(true);
            $table->string('scope', 50)->default('global');
            $table->text('reason');
            $table->timestampTz('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['vendor_id', 'feature_key'], 'tenant_feature_overrides_vendor_feature_unique');
            $table->index(['feature_key', 'is_enabled'], 'tenant_feature_overrides_feature_status_idx');
            $table->index(['vendor_id', 'is_enabled'], 'tenant_feature_overrides_vendor_status_idx');
        });

        // Backfill default structured feature codes for known plans
        $planFeatures = [
            'basic' => [
                'menu.base',
                'menu.translations',
                'analytics.basic',
            ],
            'pro' => [
                'menu.base',
                'menu.translations',
                'analytics.basic',
                'orders.online',
                'orders.delivery',
                'reservations.manage',
                'preorders.manage',
                'customers.crm',
                'ai.menu_builder',
                'ai.waiter',
                'branding.custom_domain',
            ],
            'business' => [
                'menu.base',
                'menu.translations',
                'analytics.basic',
                'orders.online',
                'orders.delivery',
                'reservations.manage',
                'preorders.manage',
                'customers.crm',
                'branches.multiple',
                'team.manage',
                'analytics.advanced',
                'ai.menu_builder',
                'ai.waiter',
                'branding.custom_domain',
                'api.access',
            ],
            'custom' => [
                '*',
            ],
        ];

        foreach ($planFeatures as $slug => $codes) {
            DB::table('subscription_plans')
                ->where('slug', $slug)
                ->update(['feature_codes' => json_encode($codes)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_feature_overrides');

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('feature_codes');
        });
    }
};
