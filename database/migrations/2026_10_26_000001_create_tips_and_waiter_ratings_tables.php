<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tip & Rating Settings per Vendor
        Schema::create('tip_rating_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();

            // Feature toggles
            $table->boolean('is_enabled')->default(true);
            $table->boolean('tips_enabled')->default(true);
            $table->boolean('ratings_enabled')->default(true);

            // Tip settings
            $table->json('tip_percentages')->nullable();
            $table->json('fixed_tip_amounts')->nullable();
            $table->boolean('allow_custom_tip')->default(true);
            $table->string('default_tip_selection', 20)->default('none');
            $table->string('calculation_base', 30)->default('subtotal');
            $table->json('applicable_order_types')->nullable();
            $table->decimal('min_tip_amount', 10, 2)->default(0.00);
            $table->decimal('max_tip_amount', 10, 2)->default(50000.00);
            $table->json('supported_payment_methods')->nullable();
            $table->boolean('allow_cash_tips')->default(true);
            $table->string('allocation_policy', 30)->default('pool'); // waiter, pool, house

            // Rating settings
            $table->smallInteger('rating_scale')->default(5);
            $table->boolean('allow_written_feedback')->default(true);
            $table->boolean('require_written_feedback')->default(false);
            $table->boolean('is_anonymous_by_default')->default(true);
            $table->boolean('allow_customer_name')->default(true);
            $table->json('feedback_categories')->nullable();
            $table->boolean('allow_waiter_selection')->default(true);
            $table->integer('submission_window_hours')->default(24);
            $table->string('duplicate_policy', 30)->default('once_per_order');
            $table->boolean('auto_publish_ratings')->default(true);

            $table->timestampsTz();

            $table->unique('vendor_id', 'uniq_tip_rating_settings_vendor');
        });

        // 2. Add tip_amount and waiter_id to orders table
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'tip_amount')) {
                $table->decimal('tip_amount', 10, 2)->default(0.00)->after('delivery_fee');
            }
            if (! Schema::hasColumn('orders', 'waiter_id')) {
                $table->foreignId('waiter_id')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
            }
        });

        // 3. Tips financial transaction table
        Schema::create('tips', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('table_number', 50)->nullable();
            $table->foreignId('waiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payment_attempt_id')->nullable()->constrained('payment_attempts')->nullOnDelete();

            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('AMD');
            $table->string('tip_type', 20)->default('custom'); // percentage, fixed, custom
            $table->decimal('percentage_applied', 5, 2)->nullable();
            $table->string('payment_method', 50)->default('cash');
            $table->string('payment_status', 30)->default('pending'); // pending, paid, refunded, failed
            $table->string('allocation_policy', 30)->default('pool');
            $table->string('allocation_status', 30)->default('pending');
            $table->boolean('is_cash')->default(false);
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->text('notes')->nullable();

            $table->timestampTz('settled_at')->nullable();
            $table->timestampTz('refunded_at')->nullable();
            $table->text('refund_reason')->nullable();

            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['vendor_id', 'payment_status', 'created_at'], 'idx_tips_vendor_status_created');
            $table->index(['vendor_id', 'waiter_id', 'created_at'], 'idx_tips_vendor_waiter_created');
            $table->index(['vendor_id', 'order_id'], 'idx_tips_vendor_order');
            $table->index(['vendor_id', 'payment_method'], 'idx_tips_vendor_method');
        });

        // 4. Customer Service & Waiter Ratings table
        Schema::create('service_ratings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('table_number', 50)->nullable();
            $table->foreignId('waiter_id')->nullable()->constrained('users')->nullOnDelete();

            $table->smallInteger('rating'); // 1 to 5
            $table->json('categories')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('is_anonymous')->default(true);
            $table->string('customer_name', 100)->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Moderation & status
            $table->string('status', 20)->default('published'); // published, hidden, flagged
            $table->text('moderation_note')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('moderated_at')->nullable();

            $table->string('idempotency_key', 64)->nullable()->unique();

            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['vendor_id', 'status', 'created_at'], 'idx_ratings_vendor_status_created');
            $table->index(['vendor_id', 'waiter_id', 'created_at'], 'idx_ratings_vendor_waiter_created');
            $table->index(['vendor_id', 'order_id'], 'idx_ratings_vendor_order');
            $table->index(['vendor_id', 'rating'], 'idx_ratings_vendor_rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_ratings');
        Schema::dropIfExists('tips');

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'waiter_id')) {
                $table->dropConstrainedForeignId('waiter_id');
            }
            if (Schema::hasColumn('orders', 'tip_amount')) {
                $table->dropColumn('tip_amount');
            }
        });

        Schema::dropIfExists('tip_rating_settings');
    }
};
