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
        // 1. Enhance POS Devices with LAN Capabilities
        Schema::table('pos_devices', function (Blueprint $table) {
            $table->boolean('is_lan_hub')->default(false)->after('is_paired');
            $table->string('lan_ip_address', 45)->nullable()->after('is_lan_hub');
            $table->integer('lan_port')->default(8080)->after('lan_ip_address');
            $table->string('lan_auth_token', 64)->nullable()->after('lan_port');
            $table->integer('lan_sync_priority')->default(10)->after('lan_auth_token');
            $table->timestamp('last_lan_heartbeat_at')->nullable()->after('lan_sync_priority');
        });

        // 2. POS LAN Active Peers Table (Local subnet discovery registry)
        Schema::create('pos_lan_peers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pos_device_id')->constrained('pos_devices')->cascadeOnDelete();
            $table->string('device_name');
            $table->string('device_type', 30)->default('tablet'); // desktop, tablet, handheld, kds
            $table->string('role', 20)->default('satellite'); // hub, satellite
            $table->string('ip_address', 45);
            $table->integer('port')->default(8080);
            $table->string('status', 20)->default('active'); // active, offline
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'pos_device_id']);
            $table->index(['vendor_id', 'location_id', 'status']);
        });

        // 3. POS LAN Sync Dispatches (Peer-to-peer event routing)
        Schema::create('pos_lan_sync_dispatches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_device_id')->nullable()->constrained('pos_devices')->nullOnDelete();
            $table->foreignId('target_device_id')->nullable()->constrained('pos_devices')->nullOnDelete();
            $table->string('event_type', 50); // order_created, item_status_updated, payment_recorded, table_updated
            $table->json('payload');
            $table->string('status', 20)->default('queued'); // queued, dispatched, acknowledged, failed
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['vendor_id', 'location_id', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_lan_sync_dispatches');
        Schema::dropIfExists('pos_lan_peers');

        Schema::table('pos_devices', function (Blueprint $table) {
            $table->dropColumn([
                'is_lan_hub',
                'lan_ip_address',
                'lan_port',
                'lan_auth_token',
                'lan_sync_priority',
                'last_lan_heartbeat_at',
            ]);
        });
    }
};
