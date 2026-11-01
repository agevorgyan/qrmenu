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
        // 1. Roles table
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->cascadeOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['vendor_id', 'slug'], 'roles_vendor_slug_unique');
                $table->index(['vendor_id', 'is_active']);
                $table->index(['slug', 'is_system']);
            });
        }

        // 2. Permissions table
        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('module')->index();
                $table->string('action');
                $table->text('description')->nullable();
                $table->string('risk_level', 32)->default('low'); // low, medium, high, critical
                $table->boolean('is_sensitive')->default(false);
                $table->boolean('is_system')->default(true);
                $table->timestamps();

                $table->index(['module', 'action']);
                $table->index(['is_system', 'is_sensitive']);
            });
        }

        // 3. Role Permissions table
        if (! Schema::hasTable('role_permissions')) {
            Schema::create('role_permissions', function (Blueprint $table) {
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['role_id', 'permission_id']);
                $table->index('permission_id');
            });
        }

        // 4. Vendor Memberships table
        if (! Schema::hasTable('vendor_memberships')) {
            Schema::create('vendor_memberships', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('status', 32)->default('active'); // active, inactive, suspended
                $table->boolean('all_locations')->default(true);
                $table->timestamp('joined_at')->nullable()->useCurrent();
                $table->timestamps();

                $table->unique(['vendor_id', 'user_id'], 'vendor_user_membership_unique');
                $table->index(['vendor_id', 'status']);
                $table->index(['user_id', 'status']);
            });
        }

        // 5. Membership Roles table
        if (! Schema::hasTable('membership_roles')) {
            Schema::create('membership_roles', function (Blueprint $table) {
                $table->foreignId('membership_id')->constrained('vendor_memberships')->cascadeOnDelete();
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['membership_id', 'role_id']);
                $table->index('role_id');
            });
        }

        // 6. Membership Locations table
        if (! Schema::hasTable('membership_locations')) {
            Schema::create('membership_locations', function (Blueprint $table) {
                $table->foreignId('membership_id')->constrained('vendor_memberships')->cascadeOnDelete();
                $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['membership_id', 'location_id']);
                $table->index('location_id');
            });
        }

        // 7. Membership Permissions table (individual overrides)
        if (! Schema::hasTable('membership_permissions')) {
            Schema::create('membership_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('membership_id')->constrained('vendor_memberships')->cascadeOnDelete();
                $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
                $table->string('effect', 16)->default('allow'); // allow, deny
                $table->timestamp('expires_at')->nullable()->index();
                $table->string('reason')->nullable();
                $table->timestamps();

                $table->unique(['membership_id', 'permission_id'], 'membership_permission_unique');
                $table->index(['membership_id', 'effect']);
            });
        }

        // 8. Authorization Audit Logs table
        if (! Schema::hasTable('authorization_audit_logs')) {
            Schema::create('authorization_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 64)->index();
                $table->string('resource_type', 64)->nullable();
                $table->unsignedBigInteger('resource_id')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->json('metadata')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->useCurrent()->index();

                $table->index(['vendor_id', 'created_at']);
                $table->index(['actor_id', 'created_at']);
                $table->index(['target_user_id', 'created_at']);
                $table->index(['resource_type', 'resource_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authorization_audit_logs');
        Schema::dropIfExists('membership_permissions');
        Schema::dropIfExists('membership_locations');
        Schema::dropIfExists('membership_roles');
        Schema::dropIfExists('vendor_memberships');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
