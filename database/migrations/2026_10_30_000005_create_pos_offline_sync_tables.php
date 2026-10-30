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
        // 1. POS Offline Sync Batches
        Schema::create('pos_sync_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_device_id')->nullable()->constrained('pos_devices')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('batch_id', 100);
            $table->string('status', 30)->default('processing'); // processing, completed, partially_failed, rejected
            $table->integer('total_operations')->default(0);
            $table->integer('synced_operations')->default(0);
            $table->integer('conflict_operations')->default(0);
            $table->timestamp('client_started_at')->nullable();
            $table->timestamp('client_ended_at')->nullable();
            $table->timestamp('server_processed_at')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['vendor_id', 'batch_id']);
        });

        // 2. POS Offline Sync Individual Operations (Idempotent tracking)
        Schema::create('pos_sync_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('pos_sync_batch_id')->constrained('pos_sync_batches')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_device_id')->nullable()->constrained('pos_devices')->nullOnDelete();
            $table->string('client_sync_id', 100);
            $table->string('entity_type', 50); // order, payment, table_status, cash_movement
            $table->string('action', 50); // create, update, pay, status
            $table->timestamp('client_timestamp');
            $table->json('payload');
            $table->string('status', 30)->default('synced'); // synced, duplicate, conflict, rejected
            $table->unsignedBigInteger('server_entity_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'client_sync_id']);
            $table->index(['pos_sync_batch_id', 'status']);
        });

        // 3. POS Offline Sync Conflicts Log
        Schema::create('pos_sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_sync_batch_id')->nullable()->constrained('pos_sync_batches')->cascadeOnDelete();
            $table->foreignId('pos_device_id')->nullable()->constrained('pos_devices')->nullOnDelete();
            $table->string('client_sync_id', 100);
            $table->string('entity_type', 50);
            $table->string('conflict_type', 50); // table_collision, version_mismatch, price_drift, limit_exceeded
            $table->json('client_data');
            $table->json('server_data')->nullable();
            $table->string('resolution_strategy', 50)->default('server_wins'); // server_wins, client_wins, merged, manual_review
            $table->boolean('is_resolved')->default(false);
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'is_resolved']);
            $table->index(['vendor_id', 'conflict_type']);
        });

        // 4. Enhance Orders for Offline Traceability
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_offline_created')->default(false)->after('status');
            $table->string('client_sync_id', 100)->nullable()->after('is_offline_created');
            $table->timestamp('offline_synced_at')->nullable()->after('client_sync_id');

            $table->index(['vendor_id', 'client_sync_id']);
        });

        // 5. Enhance POS Payments for Offline Tracking
        Schema::table('pos_payments', function (Blueprint $table) {
            $table->boolean('is_offline')->default(false)->after('status');
            $table->string('client_sync_id', 100)->nullable()->after('is_offline');
            $table->timestamp('offline_synced_at')->nullable()->after('client_sync_id');

            $table->index(['vendor_id', 'client_sync_id']);
        });

        // 6. Enhance POS Settings for Offline Safety Limits
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->integer('max_offline_hours')->default(48)->after('allow_offline_orders');
            $table->integer('max_offline_orders')->default(100)->after('max_offline_hours');
            $table->decimal('max_offline_amount', 12, 2)->default(500000.00)->after('max_offline_orders');
            $table->boolean('allow_offline_cash_payments')->default(true)->after('max_offline_amount');
            $table->boolean('allow_offline_card_payments')->default(false)->after('allow_offline_cash_payments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            $table->dropColumn([
                'max_offline_hours',
                'max_offline_orders',
                'max_offline_amount',
                'allow_offline_cash_payments',
                'allow_offline_card_payments',
            ]);
        });

        Schema::table('pos_payments', function (Blueprint $table) {
            $table->dropIndex(['vendor_id', 'client_sync_id']);
            $table->dropColumn(['is_offline', 'client_sync_id', 'offline_synced_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['vendor_id', 'client_sync_id']);
            $table->dropColumn(['is_offline_created', 'client_sync_id', 'offline_synced_at']);
        });

        Schema::dropIfExists('pos_sync_conflicts');
        Schema::dropIfExists('pos_sync_operations');
        Schema::dropIfExists('pos_sync_batches');
    }
};
