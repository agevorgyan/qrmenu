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
        // 1. Ephemeral Customer QR Tokens (Cryptographically signed, short TTL, zero PII)
        Schema::create('loyalty_customer_qr_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['vendor_id', 'token', 'expires_at']);
        });

        // 2. Loyalty Audit Logs (Staff operations, adjustments, redemptions)
        Schema::create('loyalty_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('action', 50); // points_adjusted, cashback_adjusted, reward_redeemed, tier_overridden, pass_revoked, rule_created
            $table->jsonb('details');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['vendor_id', 'action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_audit_logs');
        Schema::dropIfExists('loyalty_customer_qr_tokens');
    }
};
