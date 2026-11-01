<?php

use App\Security\PermissionCatalog;
use Database\Seeders\EnterpriseAuthorizationSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Auto-seed canonical permissions and system roles on deployment
        PermissionCatalog::syncToDatabase();

        // Migrate existing users into memberships if not already migrated
        (new EnterpriseAuthorizationSeeder)->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive: keep permissions and roles intact
    }
};
