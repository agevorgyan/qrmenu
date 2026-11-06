<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\VendorMembership;
use App\Security\PermissionCatalog;
use App\Services\AuthorizationService;
use Database\Seeders\EnterpriseAuthorizationSeeder;
use Illuminate\Console\Command;

class ReconcileAuthorizationPermissionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:reconcile-permissions {--dry-run : Simulate reconciliation without modifying data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile canonical permissions, system roles, and user memberships idempotently.';

    /**
     * Execute the console command.
     */
    public function handle(AuthorizationService $authService): int
    {
        $this->info('Starting authorization reconciliation...');

        if ($this->option('dry-run')) {
            $this->info('[DRY RUN] Calculating reconciliation metrics...');
            $canonicalPermsCount = count(PermissionCatalog::all());
            $systemRolesCount = count(PermissionCatalog::roleTemplates());
            $unmigratedUsersCount = User::whereNotNull('vendor_id')
                ->whereDoesntHave('memberships', function ($q) {
                    $q->whereColumn('vendor_memberships.vendor_id', 'users.vendor_id');
                })->count();

            $this->table(['Metric', 'Count'], [
                ['Canonical Permissions', $canonicalPermsCount],
                ['System Role Templates', $systemRolesCount],
                ['Unmigrated Vendor Users', $unmigratedUsersCount],
            ]);

            $this->info('[DRY RUN] Reconciliation completed with no modifications.');

            return self::SUCCESS;
        }

        // 1. Sync canonical permissions
        $this->line('1. Synchronizing canonical permissions...');
        PermissionCatalog::syncToDatabase();

        // 2. Run idempotent seeder for system roles & user memberships
        $this->line('2. Reconciling system roles and migrating user memberships...');
        (new EnterpriseAuthorizationSeeder)->run();

        // 3. Mark all memberships as roles_migrated
        VendorMembership::where('roles_migrated', false)->update(['roles_migrated' => true]);

        // 4. Invalidate authorization cache across all users
        $this->line('3. Invalidating authorization cache...');
        User::whereNotNull('vendor_id')->chunk(200, function ($users) use ($authService) {
            foreach ($users as $user) {
                $authService->invalidateCache($user->id, $user->vendor_id);
            }
        });

        $this->info('Authorization reconciliation completed successfully.');

        return self::SUCCESS;
    }
}
