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
        // 1. Kitchen Stations Configuration
        Schema::create('pos_kitchen_stations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug', 50);
            $table->string('color', 20)->nullable()->default('#f59e0b');
            $table->string('printer_ip')->nullable();
            $table->integer('printer_port')->default(9100);
            $table->string('printer_type', 30)->default('network'); // network, usb, bluetooth, browser
            $table->string('paper_width', 10)->default('80mm'); // 80mm, 58mm
            $table->boolean('auto_print')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['vendor_id', 'slug']);
            $table->index(['vendor_id', 'is_active']);
        });

        // 2. Hardware / ESC-POS Print Jobs Queue
        Schema::create('pos_print_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pos_device_id')->nullable()->constrained('pos_devices')->nullOnDelete();
            $table->string('station_slug', 50)->nullable();
            $table->string('ticket_type', 30); // receipt, kitchen_ticket, shift_report
            $table->string('title')->nullable();
            $table->text('content_escpos'); // Base64 or raw ESC/POS command stream
            $table->text('content_text'); // Plain human-readable formatted text
            $table->string('status', 20)->default('pending'); // pending, printed, failed
            $table->integer('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['order_id', 'ticket_type']);
        });

        // 3. Timestamps on Order Items for Preparation Analytics
        Schema::table('order_items', function (Blueprint $table) {
            $table->timestamp('prepared_at')->nullable()->after('kitchen_station');
            $table->timestamp('ready_at')->nullable()->after('prepared_at');
            $table->timestamp('served_at')->nullable()->after('ready_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['prepared_at', 'ready_at', 'served_at']);
        });

        Schema::dropIfExists('pos_print_jobs');
        Schema::dropIfExists('pos_kitchen_stations');
    }
};
