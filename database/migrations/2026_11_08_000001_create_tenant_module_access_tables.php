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
        if (! Schema::hasTable('tenant_module_access')) {
            Schema::create('tenant_module_access', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->string('module_key', 64)->default('wholesale_pos');
                $table->boolean('is_enabled')->default(false);
                $table->timestampTz('enabled_at')->nullable();
                $table->timestampTz('expires_at')->nullable();
                $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestampTz('revoked_at')->nullable();
                $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('reason')->nullable();
                $table->timestampsTz();

                $table->unique(['vendor_id', 'module_key'], 'uq_tenant_module_access');
                $table->index(['module_key', 'is_enabled'], 'idx_tenant_module_status');
                $table->index(['vendor_id', 'is_enabled'], 'idx_tenant_module_vendor_status');
            });
        }

        if (! Schema::hasTable('tenant_module_access_logs')) {
            Schema::create('tenant_module_access_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_module_access_id')->nullable()->constrained('tenant_module_access')->cascadeOnDelete();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->string('module_key', 64);
                $table->string('action', 32); // granted, suspended, revoked, renewed, expired
                $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->jsonb('previous_state')->nullable();
                $table->jsonb('new_state')->nullable();
                $table->text('reason')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestampTz('created_at')->useCurrent();

                $table->index(['vendor_id', 'created_at'], 'idx_tenant_module_log_vendor_date');
                $table->index(['module_key', 'action'], 'idx_tenant_module_log_action');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_module_access_logs');
        Schema::dropIfExists('tenant_module_access');
    }
};

