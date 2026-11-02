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
        // 1. Add roles_migrated flag to vendor_memberships
        if (Schema::hasTable('vendor_memberships') && ! Schema::hasColumn('vendor_memberships', 'roles_migrated')) {
            Schema::table('vendor_memberships', function (Blueprint $table) {
                $table->boolean('roles_migrated')->default(true)->after('all_locations');
            });

            // Mark all existing vendor memberships as migrated
            DB::table('vendor_memberships')->update(['roles_migrated' => true]);
        }

        // 2. Database-level trigger to enforce append-only immutability on authorization_audit_logs
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_audit_log_mutation()
                RETURNS TRIGGER AS $$
                BEGIN
                    IF TG_OP = 'DELETE' THEN
                        RAISE EXCEPTION 'authorization_audit_logs is append-only and cannot be deleted.';
                    ELSIF TG_OP = 'UPDATE' THEN
                        -- Allow cascade foreign key ON DELETE SET NULL for actor_id, target_user_id, vendor_id
                        IF (OLD.action IS NOT DISTINCT FROM NEW.action)
                           AND (OLD.resource_type IS NOT DISTINCT FROM NEW.resource_type)
                           AND (OLD.resource_id IS NOT DISTINCT FROM NEW.resource_id)
                           AND (OLD.old_values::text IS NOT DISTINCT FROM NEW.old_values::text)
                           AND (OLD.new_values::text IS NOT DISTINCT FROM NEW.new_values::text)
                           AND (OLD.metadata::text IS NOT DISTINCT FROM NEW.metadata::text)
                           AND (OLD.ip_address IS NOT DISTINCT FROM NEW.ip_address)
                           AND (OLD.user_agent IS NOT DISTINCT FROM NEW.user_agent)
                           AND (OLD.created_at IS NOT DISTINCT FROM NEW.created_at)
                           AND (NEW.actor_id IS NULL OR NEW.actor_id = OLD.actor_id)
                           AND (NEW.target_user_id IS NULL OR NEW.target_user_id = OLD.target_user_id)
                           AND (NEW.vendor_id IS NULL OR NEW.vendor_id = OLD.vendor_id)
                        THEN
                            RETURN NEW;
                        END IF;

                        RAISE EXCEPTION 'authorization_audit_logs is append-only and cannot be modified.';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                DROP TRIGGER IF EXISTS trg_protect_authorization_audit_logs ON authorization_audit_logs;
                CREATE TRIGGER trg_protect_authorization_audit_logs
                BEFORE UPDATE OR DELETE ON authorization_audit_logs
                FOR EACH ROW
                EXECUTE FUNCTION prevent_audit_log_mutation();
            SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS trg_protect_authorization_audit_logs ON authorization_audit_logs;
                DROP FUNCTION IF EXISTS prevent_audit_log_mutation();
            SQL);
        }

        if (Schema::hasTable('vendor_memberships') && Schema::hasColumn('vendor_memberships', 'roles_migrated')) {
            Schema::table('vendor_memberships', function (Blueprint $table) {
                $table->dropColumn('roles_migrated');
            });
        }
    }
};
