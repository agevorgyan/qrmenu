<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create subscription_plan_prices table for configurable pricing and billing intervals
        if (! Schema::hasTable('subscription_plan_prices')) {
            Schema::create('subscription_plan_prices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
                $table->string('billing_interval', 30)->default('monthly'); // monthly, quarterly, yearly, custom
                $table->string('currency', 10)->default('AMD');
                $table->decimal('price', 12, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->string('description')->nullable();
                $table->timestamps();

                $table->unique(['subscription_plan_id', 'billing_interval', 'currency'], 'plan_prices_plan_interval_curr_unique');
                $table->index(['subscription_plan_id', 'is_active']);
            });
        }

        // 2. Enhance authoritative subscriptions table with pricing snapshots and cancellation details
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'subscription_plan_price_id')) {
                $table->foreignId('subscription_plan_price_id')->nullable()->after('subscription_plan_id')
                    ->constrained('subscription_plan_prices')->nullOnDelete();
            }
            if (! Schema::hasColumn('subscriptions', 'price')) {
                $table->decimal('price', 12, 2)->nullable()->after('subscription_plan_price_id');
            }
            if (! Schema::hasColumn('subscriptions', 'currency')) {
                $table->string('currency', 10)->nullable()->default('AMD')->after('price');
            }
            if (! Schema::hasColumn('subscriptions', 'cancel_at_period_end')) {
                $table->boolean('cancel_at_period_end')->default(false)->after('auto_renew');
            }
            if (! Schema::hasColumn('subscriptions', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancel_at_period_end');
            }
            if (! Schema::hasColumn('subscriptions', 'trial_duration_days')) {
                $table->integer('trial_duration_days')->nullable()->after('trial_ends_at');
            }

            $table->index(['status', 'current_period_end'], 'subscriptions_status_period_end_idx');
        });

        // 3. Create subscription_lifecycle_events table with composite tenant constraint
        if (! Schema::hasTable('subscription_lifecycle_events')) {
            Schema::create('subscription_lifecycle_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('actor_type', 30)->default('system'); // user, system, cron, webhook, superadmin
                $table->string('event', 50); // trial_started, activated, renewed, cancel_requested, cancelled_immediately, resumed, expired, suspended, reactivated, plan_changed, grace_entered
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30);
                $table->foreignId('from_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
                $table->foreignId('to_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
                $table->text('reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['vendor_id', 'created_at']);
                $table->index(['subscription_id', 'created_at']);
            });

            // Composite Foreign Key (strictly enforces tenant isolation at PostgreSQL level)
            try {
                Schema::table('subscription_lifecycle_events', function (Blueprint $table) {
                    $table->foreign(['subscription_id', 'vendor_id'], 'sub_events_sub_vendor_fk')
                        ->references(['id', 'vendor_id'])
                        ->on('subscriptions')
                        ->cascadeOnDelete();
                });
            } catch (Throwable $e) {
                // Ignore if constraint already established
            }
        }

        // 4. Backfill existing plans into subscription_plan_prices
        if (Schema::hasTable('subscription_plans')) {
            $plans = DB::table('subscription_plans')->get();
            foreach ($plans as $plan) {
                $basePrice = (float) $plan->price;
                $currency = $plan->currency ?: 'AMD';

                // Monthly price
                $monthlyExists = DB::table('subscription_plan_prices')
                    ->where('subscription_plan_id', $plan->id)
                    ->where('billing_interval', 'monthly')
                    ->where('currency', $currency)
                    ->exists();

                if (! $monthlyExists) {
                    $monthlyPriceId = DB::table('subscription_plan_prices')->insertGetId([
                        'subscription_plan_id' => $plan->id,
                        'billing_interval' => 'monthly',
                        'currency' => $currency,
                        'price' => $basePrice,
                        'is_active' => true,
                        'description' => "Monthly {$plan->name}",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Yearly price (20% discount if price > 0)
                $yearlyPrice = $basePrice > 0 ? round($basePrice * 12 * 0.80, 2) : 0;
                $yearlyExists = DB::table('subscription_plan_prices')
                    ->where('subscription_plan_id', $plan->id)
                    ->where('billing_interval', 'yearly')
                    ->where('currency', $currency)
                    ->exists();

                if (! $yearlyExists) {
                    DB::table('subscription_plan_prices')->insert([
                        'subscription_plan_id' => $plan->id,
                        'billing_interval' => 'yearly',
                        'currency' => $currency,
                        'price' => $yearlyPrice,
                        'is_active' => true,
                        'description' => "Yearly {$plan->name} (20% discount)",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Backfill existing subscriptions linked to this plan with historical price snapshots
                DB::table('subscriptions')
                    ->where('subscription_plan_id', $plan->id)
                    ->whereNull('price')
                    ->update([
                        'price' => $basePrice,
                        'currency' => $currency,
                        'trial_duration_days' => $plan->trial_days ?: 14,
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('subscription_lifecycle_events')) {
            try {
                Schema::table('subscription_lifecycle_events', function (Blueprint $table) {
                    $table->dropForeign('sub_events_sub_vendor_fk');
                });
            } catch (Throwable $e) {
                // Ignore if constraint missing
            }
            Schema::dropIfExists('subscription_lifecycle_events');
        }

        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                try {
                    $table->dropIndex('subscriptions_status_period_end_idx');
                } catch (Throwable $e) {
                }

                if (Schema::hasColumn('subscriptions', 'subscription_plan_price_id')) {
                    $table->dropForeign(['subscription_plan_price_id']);
                    $table->dropColumn('subscription_plan_price_id');
                }
                if (Schema::hasColumn('subscriptions', 'price')) {
                    $table->dropColumn('price');
                }
                if (Schema::hasColumn('subscriptions', 'currency')) {
                    $table->dropColumn('currency');
                }
                if (Schema::hasColumn('subscriptions', 'cancel_at_period_end')) {
                    $table->dropColumn('cancel_at_period_end');
                }
                if (Schema::hasColumn('subscriptions', 'cancellation_reason')) {
                    $table->dropColumn('cancellation_reason');
                }
                if (Schema::hasColumn('subscriptions', 'trial_duration_days')) {
                    $table->dropColumn('trial_duration_days');
                }
            });
        }

        Schema::dropIfExists('subscription_plan_prices');
    }
};
