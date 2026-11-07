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
        // 1. Financial Approval Policies Table
        if (! Schema::hasTable('financial_approval_policies')) {
            Schema::create('financial_approval_policies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->string('entity_type', 40); // expense, refund, void, transfer, shift_discrepancy
                $table->decimal('threshold_amount', 14, 2);
                $table->string('required_role', 50); // manager, vendor_owner, accountant
                $table->boolean('auto_approve_below')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['vendor_id', 'entity_type']);
            });
        }

        // 2. Financial Approval Requests Table
        if (! Schema::hasTable('financial_approval_requests')) {
            Schema::create('financial_approval_requests', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->string('approvable_type', 100);
                $table->unsignedBigInteger('approvable_id');
                $table->decimal('amount', 14, 2);
                $table->string('status', 30)->default('pending'); // pending, approved, rejected
                $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['vendor_id', 'status']);
                $table->index(['approvable_type', 'approvable_id']);
            });
        }

        // 3. Financial Audit Logs Table (Tamper-proof event journal)
        if (! Schema::hasTable('financial_audit_logs')) {
            Schema::create('financial_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 60);
                $table->string('auditable_type', 100);
                $table->unsignedBigInteger('auditable_id');
                $table->jsonb('old_values')->nullable();
                $table->jsonb('new_values')->nullable();
                $table->decimal('amount', 14, 2)->nullable();
                $table->string('currency', 10)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['vendor_id', 'created_at']);
                $table->index(['vendor_id', 'action']);
                $table->index(['auditable_type', 'auditable_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_audit_logs');
        Schema::dropIfExists('financial_approval_requests');
        Schema::dropIfExists('financial_approval_policies');
    }
};
