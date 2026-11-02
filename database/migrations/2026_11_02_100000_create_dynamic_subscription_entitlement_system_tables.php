<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feature_categories')) {
            Schema::create('feature_categories', function (Blueprint $table) {
                $table->id();
                $table->string('code', 100)->unique();
                $table->string('name');
                $table->json('name_translations')->nullable();
                $table->text('description')->nullable();
                $table->json('description_translations')->nullable();
                $table->string('icon', 100)->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('features')) {
            Schema::create('features', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->nullable()->constrained('feature_categories')->nullOnDelete();
                $table->string('code', 120)->unique();
                $table->string('name');
                $table->json('name_translations')->nullable();
                $table->text('description')->nullable();
                $table->json('description_translations')->nullable();
                $table->string('feature_type', 40)->default('BOOLEAN'); // BOOLEAN, LIMIT, USAGE, CONFIGURATION, ENUM, METERED
                $table->json('default_value')->nullable();
                $table->json('allowed_options')->nullable();
                $table->string('unit', 64)->nullable();
                $table->string('reset_period', 40)->nullable()->default('none'); // none, monthly, billing_cycle
                $table->boolean('is_active')->default(true);
                $table->boolean('is_archived')->default(false);
                $table->boolean('admin_visible')->default(true);
                $table->boolean('vendor_visible')->default(true);
                $table->integer('sort_order')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['category_id', 'sort_order']);
                $table->index(['is_active', 'is_archived']);
            });
        }

        if (! Schema::hasTable('feature_dependencies')) {
            Schema::create('feature_dependencies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('feature_id')->constrained('features')->cascadeOnDelete();
                $table->foreignId('depends_on_feature_id')->constrained('features')->cascadeOnDelete();
                $table->string('feature_code', 120);
                $table->string('depends_on_code', 120);
                $table->json('required_value')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['feature_id', 'depends_on_feature_id'], 'feature_dep_unique');
                $table->index(['feature_code', 'depends_on_code']);
            });
        }

        if (! Schema::hasTable('subscription_plan_versions')) {
            Schema::create('subscription_plan_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
                $table->integer('version_number')->default(1);
                $table->string('name');
                $table->json('name_translations')->nullable();
                $table->text('description')->nullable();
                $table->json('description_translations')->nullable();
                $table->decimal('monthly_price', 12, 2)->nullable();
                $table->decimal('yearly_price', 12, 2)->nullable();
                $table->string('currency', 10)->default('AMD');
                $table->integer('trial_days')->default(14);
                $table->json('features_snapshot')->nullable();
                $table->json('limits_snapshot')->nullable();
                $table->text('change_summary')->nullable();
                $table->boolean('is_current')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['subscription_plan_id', 'version_number'], 'plan_version_num_unique');
            });
        }

        if (! Schema::hasTable('plan_features')) {
            Schema::create('plan_features', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
                $table->foreignId('plan_version_id')->nullable()->constrained('subscription_plan_versions')->cascadeOnDelete();
                $table->foreignId('feature_id')->constrained('features')->cascadeOnDelete();
                $table->string('feature_code', 120);
                $table->boolean('is_enabled')->default(false);
                $table->bigInteger('limit_value')->nullable();
                $table->bigInteger('usage_quota')->nullable();
                $table->json('config_value')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->index(['subscription_plan_id', 'feature_code']);
                $table->index(['plan_version_id', 'feature_code']);
            });
        }

        Schema::table('subscription_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_plans', 'name_translations')) {
                $table->json('name_translations')->nullable()->after('name');
            }
            if (! Schema::hasColumn('subscription_plans', 'description_translations')) {
                $table->json('description_translations')->nullable()->after('description');
            }
            if (! Schema::hasColumn('subscription_plans', 'yearly_price')) {
                $table->decimal('yearly_price', 12, 2)->nullable()->after('price');
            }
            if (! Schema::hasColumn('subscription_plans', 'current_version_id')) {
                $table->unsignedBigInteger('current_version_id')->nullable()->after('sort_order');
            }
            if (! Schema::hasColumn('subscription_plans', 'current_version_number')) {
                $table->integer('current_version_number')->default(1)->after('current_version_id');
            }
            if (! Schema::hasColumn('subscription_plans', 'is_archived')) {
                $table->boolean('is_archived')->default(false)->after('is_custom');
            }
            if (! Schema::hasColumn('subscription_plans', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('is_archived');
            }
            if (! Schema::hasColumn('subscription_plans', 'grace_period_days')) {
                $table->integer('grace_period_days')->default(3)->after('trial_days');
            }
            if (! Schema::hasColumn('subscription_plans', 'warning_threshold_percent')) {
                $table->integer('warning_threshold_percent')->default(80)->after('grace_period_days');
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'plan_version_id')) {
                $table->unsignedBigInteger('plan_version_id')->nullable()->after('subscription_plan_id');
            }
            if (! Schema::hasColumn('subscriptions', 'plan_version_number')) {
                $table->integer('plan_version_number')->default(1)->after('plan_version_id');
            }
            if (! Schema::hasColumn('subscriptions', 'billing_status')) {
                $table->string('billing_status', 40)->default('current')->after('status');
            }
            if (! Schema::hasColumn('subscriptions', 'entitlement_status')) {
                $table->string('entitlement_status', 40)->default('active')->after('billing_status');
            }
        });

        Schema::table('tenant_feature_overrides', function (Blueprint $table) {
            if (! Schema::hasColumn('tenant_feature_overrides', 'value_type')) {
                $table->string('value_type', 40)->nullable()->default('BOOLEAN')->after('is_enabled');
            }
            if (! Schema::hasColumn('tenant_feature_overrides', 'limit_value')) {
                $table->bigInteger('limit_value')->nullable()->after('value_type');
            }
            if (! Schema::hasColumn('tenant_feature_overrides', 'usage_quota')) {
                $table->bigInteger('usage_quota')->nullable()->after('limit_value');
            }
            if (! Schema::hasColumn('tenant_feature_overrides', 'config_value')) {
                $table->json('config_value')->nullable()->after('usage_quota');
            }
        });

        if (! Schema::hasTable('subscription_addons')) {
            Schema::create('subscription_addons', function (Blueprint $table) {
                $table->id();
                $table->string('code', 100)->unique();
                $table->string('name');
                $table->json('name_translations')->nullable();
                $table->text('description')->nullable();
                $table->json('description_translations')->nullable();
                $table->string('addon_type', 40)->default('feature'); // feature, limit_boost, usage_pack
                $table->string('billing_type', 40)->default('monthly'); // monthly, yearly, one_time
                $table->decimal('monthly_price', 12, 2)->default(0);
                $table->decimal('yearly_price', 12, 2)->default(0);
                $table->decimal('one_time_price', 12, 2)->default(0);
                $table->string('currency', 10)->default('AMD');
                $table->foreignId('feature_id')->nullable()->constrained('features')->nullOnDelete();
                $table->string('feature_code', 120)->nullable();
                $table->bigInteger('quantity_value')->nullable();
                $table->json('compatible_plan_slugs')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_archived')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vendor_subscription_addons')) {
            Schema::create('vendor_subscription_addons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
                $table->foreignId('subscription_addon_id')->constrained('subscription_addons')->cascadeOnDelete();
                $table->integer('quantity')->default(1);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->string('billing_period', 40)->default('monthly');
                $table->string('status', 40)->default('active'); // active, cancelled, expired
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->bigInteger('used_quantity')->default(0);
                $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['vendor_id', 'status']);
            });
        }

        if (! Schema::hasTable('subscription_audit_logs')) {
            Schema::create('subscription_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
                $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 120);
                $table->string('target_type', 120)->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->json('previous_values')->nullable();
                $table->json('new_values')->nullable();
                $table->text('reason')->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['vendor_id', 'created_at']);
                $table->index(['action', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_audit_logs');
        Schema::dropIfExists('vendor_subscription_addons');
        Schema::dropIfExists('subscription_addons');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('subscription_plan_versions');
        Schema::dropIfExists('feature_dependencies');
        Schema::dropIfExists('features');
        Schema::dropIfExists('feature_categories');
    }
};

