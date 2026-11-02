<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\MembershipPermission;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\VendorMembership;
use App\Security\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EnterpriseAuthorizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Seed or update canonical permissions
            $permissionModels = [];
            foreach (PermissionCatalog::all() as $item) {
                $permission = Permission::updateOrCreate(
                    ['key' => $item['key']],
                    [
                        'module' => $item['module'],
                        'action' => $item['action'],
                        'description' => $item['description'],
                        'risk_level' => $item['risk_level'],
                        'is_sensitive' => $item['is_sensitive'],
                        'is_system' => $item['is_system'],
                    ]
                );
                $permissionModels[$item['key']] = $permission;
            }

            // 2. Seed system roles & attach role permissions
            $roles = [];
            foreach (PermissionCatalog::roleTemplates() as $slug => $data) {
                $role = Role::updateOrCreate(
                    [
                        'vendor_id' => null,
                        'slug' => $slug,
                    ],
                    [
                        'name' => $data['name'],
                        'description' => $data['description'],
                        'is_system' => true,
                        'is_active' => true,
                    ]
                );

                $permissionIds = [];
                foreach ($data['permissions'] as $permKey) {
                    if (isset($permissionModels[$permKey])) {
                        $permissionIds[] = $permissionModels[$permKey]->id;
                    }
                }

                $role->permissions()->sync($permissionIds);
                $roles[$slug] = $role;
            }

            // 3. Migrate existing users into VendorMemberships
            User::whereNotNull('vendor_id')->chunk(100, function ($users) use ($roles, $permissionModels) {
                foreach ($users as $user) {
                    $hasLocation = ! empty($user->location_id);

                    $membership = VendorMembership::firstOrCreate(
                        [
                            'vendor_id' => $user->vendor_id,
                            'user_id' => $user->id,
                        ],
                        [
                            'status' => $user->is_active ? 'active' : 'inactive',
                            'all_locations' => ! $hasLocation,
                            'roles_migrated' => true,
                            'joined_at' => $user->created_at ?? now(),
                        ]
                    );

                    $membership->update(['roles_migrated' => true]);

                    // Sync role
                    $targetRoleSlug = $user->role ?? 'staff';
                    if (isset($roles[$targetRoleSlug])) {
                        $membership->roles()->syncWithoutDetaching([$roles[$targetRoleSlug]->id]);
                    }

                    // Sync location if set
                    if ($hasLocation) {
                        $locationExists = Location::where('id', $user->location_id)
                            ->where('vendor_id', $user->vendor_id)
                            ->exists();

                        if ($locationExists) {
                            $membership->locations()->syncWithoutDetaching([$user->location_id]);
                        }
                    }

                    // Sync custom permissions
                    if (! empty($user->custom_permissions) && is_array($user->custom_permissions)) {
                        $granted = $user->custom_permissions['granted'] ?? [];
                        $denied = $user->custom_permissions['denied'] ?? [];

                        foreach ($granted as $permKey) {
                            if (isset($permissionModels[$permKey])) {
                                MembershipPermission::updateOrCreate(
                                    [
                                        'membership_id' => $membership->id,
                                        'permission_id' => $permissionModels[$permKey]->id,
                                    ],
                                    [
                                        'effect' => 'allow',
                                    ]
                                );
                            }
                        }

                        foreach ($denied as $permKey) {
                            if (isset($permissionModels[$permKey])) {
                                MembershipPermission::updateOrCreate(
                                    [
                                        'membership_id' => $membership->id,
                                        'permission_id' => $permissionModels[$permKey]->id,
                                    ],
                                    [
                                        'effect' => 'deny',
                                    ]
                                );
                            }
                        }
                    }
                }
            });
        });
    }
}
