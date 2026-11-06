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
        // 1. Wallet Issuers
        Schema::create('wallet_issuers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('issuer_mode', 20)->default('platform'); // platform, custom
            $table->string('apple_pass_type_id', 150)->nullable();
            $table->string('apple_team_id', 50)->nullable();
            $table->foreignId('apple_cert_credential_id')->nullable()->constrained('vendor_credentials')->nullOnDelete();
            $table->foreignId('apple_key_credential_id')->nullable()->constrained('vendor_credentials')->nullOnDelete();
            $table->timestamp('apple_cert_expires_at')->nullable();
            $table->string('apple_status', 20)->default('pending'); // pending, active, expired, error
            $table->string('google_issuer_id', 100)->nullable();
            $table->foreignId('google_service_account_credential_id')->nullable()->constrained('vendor_credentials')->nullOnDelete();
            $table->string('google_class_id', 150)->nullable();
            $table->string('google_status', 20)->default('pending');
            $table->timestamps();

            $table->unique('vendor_id');
        });

        // 2. Wallet Pass Designs
        Schema::create('wallet_pass_designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('background_color', 10)->default('#1e293b');
            $table->string('foreground_color', 10)->default('#ffffff');
            $table->string('label_color', 10)->default('#94a3b8');
            $table->string('logo_url', 500)->nullable();
            $table->string('strip_image_url', 500)->nullable();
            $table->string('icon_url', 500)->nullable();
            $table->string('card_title', 100)->default('Member Pass');
            $table->string('header_field', 50)->default('points');
            $table->string('primary_field', 50)->default('customer_name');
            $table->jsonb('secondary_fields')->nullable();
            $table->jsonb('auxiliary_fields')->nullable();
            $table->jsonb('back_fields')->nullable();
            $table->string('barcode_type', 30)->default('PKBarcodeFormatQR');
            $table->boolean('is_published')->default(true);
            $table->integer('version')->default(1);
            $table->timestamps();

            $table->unique('vendor_id');
        });

        // 3. Wallet Passes
        Schema::create('wallet_passes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('serial_number', 64)->unique();
            $table->string('authentication_token', 64);
            $table->text('apple_push_token')->nullable();
            $table->string('google_object_id', 150)->nullable();
            $table->string('pass_type_identifier', 150)->default('pass.am.elab.menu.loyalty');
            $table->string('status', 20)->default('active'); // active, revoked, expired
            $table->timestamp('last_pushed_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'customer_id']);
            $table->index(['pass_type_identifier', 'serial_number']);
        });

        // 4. Wallet Device Registrations (Apple PassKit Web Service)
        Schema::create('wallet_device_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('pass_id')->constrained('wallet_passes')->cascadeOnDelete();
            $table->string('device_library_identifier', 100);
            $table->text('push_token');
            $table->timestamps();

            $table->unique(['pass_id', 'device_library_identifier']);
            $table->index(['vendor_id', 'device_library_identifier']);
        });

        // 5. Wallet Update Jobs
        Schema::create('wallet_update_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('pass_id')->constrained('wallet_passes')->cascadeOnDelete();
            $table->string('provider', 20); // apple, google
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('scheduled_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_update_jobs');
        Schema::dropIfExists('wallet_device_registrations');
        Schema::dropIfExists('wallet_passes');
        Schema::dropIfExists('wallet_pass_designs');
        Schema::dropIfExists('wallet_issuers');
    }
};
