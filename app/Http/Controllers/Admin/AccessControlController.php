<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuthorizationAuditLog;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Security\PermissionCatalog;
use App\Services\AuthorizationService;
use App\Services\RoleManagementService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccessControlController extends Controller
{
    public function __construct(
        protected AuthorizationService $authService,
        protected RoleManagementService $roleService
    ) {}

    /**
     * Display the Enterprise Team & Access Control dashboard.
     */
    public function index(Request $request): View
    {
        $this->authorize('team.view');

        $user = Auth::user();
        $vendor = $user->vendor;

        $tab = $request->get('tab', 'users');
        $search = $request->get('search');

        // 1. Team Members
        $teamQuery = User::where('vendor_id', $vendor->id)
            ->with([
                'memberships' => function ($q) use ($vendor) {
                    $q->where('vendor_id', $vendor->id)
                        ->with(['roles', 'locations', 'permissions.permission']);
                },
                'location',
            ]);

        if (! empty($search) && $tab === 'users') {
            $teamQuery->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $team = $teamQuery->orderBy('name', 'asc')->get();

        // 2. Roles
        $roles = $this->roleService->getRolesForVendor(
            vendorId: $vendor->id,
            search: ($tab === 'roles') ? $search : null
        );

        // 3. Permission Catalog grouped by 21 modules
        $permissionCatalog = collect(PermissionCatalog::all())
            ->where('module', '!=', 'platform')
            ->groupBy('module')
            ->all();

        // 4. Locations
        $locations = $vendor->locations()->orderBy('name', 'asc')->get();

        // 5. Audit Logs
        $auditQuery = AuthorizationAuditLog::where('vendor_id', $vendor->id)
            ->with(['actor:id,name,email', 'targetUser:id,name,email'])
            ->latest('created_at');

        if ($request->filled('action')) {
            $auditQuery->where('action', $request->get('action'));
        }

        if ($request->filled('user_id')) {
            $auditQuery->where('target_user_id', (int) $request->get('user_id'));
        }

        if ($request->filled('date_from')) {
            $auditQuery->where('created_at', '>=', Carbon::parse($request->get('date_from'))->startOfDay());
        }

        if ($request->filled('date_to')) {
            $auditQuery->where('created_at', '<=', Carbon::parse($request->get('date_to'))->endOfDay());
        }

        $auditLogs = $auditQuery->paginate(20)->withQueryString();

        return view('admin.access_control.index', compact(
            'vendor',
            'team',
            'roles',
            'permissionCatalog',
            'locations',
            'auditLogs',
            'tab'
        ));
    }

    /**
     * Inspect effective permissions for a specific user.
     */
    public function userEffectivePermissions(User $user): JsonResponse
    {
        $actor = Auth::user();
        $vendor = $actor->vendor;

        if ((int) $user->vendor_id !== (int) $vendor->id) {
            abort(403, 'Cross-restaurant access denied.');
        }

        $membership = $this->authService->resolveMembership($user, $vendor->id);
        $effective = $this->authService->getEffectivePermissions($user, $vendor->id);
        $roles = $this->authService->getUserRoles($user, $vendor->id);

        $overrides = $membership ? $membership->permissions()->with('permission')->get() : collect();

        $allowedOverrides = $overrides->where('effect', 'allow')->map(fn ($o) => [
            'key' => $o->permission?->key,
            'expires_at' => $o->expires_at?->toIso8601String(),
            'is_expired' => $o->isExpired(),
            'reason' => $o->reason,
        ])->values()->all();

        $deniedOverrides = $overrides->where('effect', 'deny')->map(fn ($o) => [
            'key' => $o->permission?->key,
            'expires_at' => $o->expires_at?->toIso8601String(),
            'is_expired' => $o->isExpired(),
            'reason' => $o->reason,
        ])->values()->all();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $membership?->status ?? ($user->is_active ? 'active' : 'inactive'),
                'all_locations' => $membership?->all_locations ?? true,
                'locations' => $membership?->locations->pluck('name', 'id') ?? [],
                'roles' => collect($roles)->map(fn ($r) => is_string($r) ? $r : $r->name)->values()->all(),
            ],
            'effective_permissions' => $effective,
            'allowed_overrides' => $allowedOverrides,
            'denied_overrides' => $deniedOverrides,
        ]);
    }

    /**
     * Update roles assigned to a user.
     */
    public function updateUserRoles(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $actor = Auth::user();
        $vendor = $actor->vendor;

        if ((int) $user->vendor_id !== (int) $vendor->id) {
            abort(403, 'Cross-restaurant role assignment prohibited.');
        }

        $roleIds = $request->input('role_ids', $request->input('roles', []));
        $request->merge(['role_ids' => $roleIds]);

        $validated = $request->validate([
            'role_ids' => 'required|array',
            'role_ids.*' => 'integer|exists:roles,id',
        ]);

        $membership = $this->authService->resolveMembership($user, $vendor->id);
        if (! $membership) {
            abort(404, 'User membership not found.');
        }

        $this->roleService->assignRolesToMembership($membership, $validated['role_ids'], $actor);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('Roles updated successfully.'),
                'roles' => $membership->fresh()->roles->pluck('name')->all(),
            ]);
        }

        return back()->with('success', __('User roles updated successfully.'));
    }

    /**
     * Update location access scoping for a user.
     */
    public function updateUserLocations(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $actor = Auth::user();
        $vendor = $actor->vendor;

        if ((int) $user->vendor_id !== (int) $vendor->id) {
            abort(403, 'Cross-restaurant location scoping prohibited.');
        }

        $locationIds = $request->input('location_ids', $request->input('locations', []));
        $request->merge(['location_ids' => $locationIds]);

        $validated = $request->validate([
            'all_locations' => 'required|boolean',
            'location_ids' => 'nullable|array',
            'location_ids.*' => 'integer|exists:locations,id',
        ]);

        $membership = $this->authService->resolveMembership($user, $vendor->id);
        if (! $membership) {
            abort(404, 'User membership not found.');
        }

        $allLocations = (bool) $validated['all_locations'];
        $locationIds = $allLocations ? [] : ($validated['location_ids'] ?? []);

        $this->roleService->setMembershipLocations($membership, $allLocations, $locationIds, $actor);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('Location access updated successfully.'),
            ]);
        }

        return back()->with('success', __('Location access updated successfully.'));
    }

    /**
     * Update active/inactive/suspended status for a user.
     */
    public function updateUserStatus(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $actor = Auth::user();
        $vendor = $actor->vendor;

        if ((int) $user->vendor_id !== (int) $vendor->id) {
            abort(403, 'Cross-restaurant status change prohibited.');
        }

        $validated = $request->validate([
            'status' => 'required|string|in:active,inactive,suspended',
        ]);

        $membership = $this->authService->resolveMembership($user, $vendor->id);
        if (! $membership) {
            abort(404, 'User membership not found.');
        }

        $this->roleService->setMembershipStatus($membership, $validated['status'], $actor);
        $user->update(['is_active' => $validated['status'] === 'active']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('User status updated successfully.'),
                'status' => $validated['status'],
            ]);
        }

        return back()->with('success', __('User status updated successfully.'));
    }

    /**
     * Grant or deny an individual permission override.
     */
    public function storeUserOverride(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $actor = Auth::user();
        $vendor = $actor->vendor;

        if ((int) $user->vendor_id !== (int) $vendor->id) {
            abort(403, 'Cross-restaurant permission override prohibited.');
        }

        $validated = $request->validate([
            'permission_key' => 'required|string|exists:permissions,key',
            'effect' => 'required|string|in:allow,deny',
            'expires_at' => 'nullable|date|after:now',
            'reason' => 'nullable|string|max:255',
        ]);

        $membership = $this->authService->resolveMembership($user, $vendor->id);
        if (! $membership) {
            abort(404, 'User membership not found.');
        }

        $expiresAt = ! empty($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null;

        $override = $this->roleService->setUserPermissionOverride(
            membership: $membership,
            permissionKey: $validated['permission_key'],
            effect: $validated['effect'],
            expiresAt: $expiresAt,
            reason: $validated['reason'] ?? null,
            actor: $actor
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('Permission override saved successfully.'),
                'override' => $override,
            ]);
        }

        return back()->with('success', __('Permission override saved successfully.'));
    }

    /**
     * Remove an individual permission override.
     */
    public function destroyUserOverride(Request $request, User $user, string $permissionKey): JsonResponse|RedirectResponse
    {
        $actor = Auth::user();
        $vendor = $actor->vendor;

        if ((int) $user->vendor_id !== (int) $vendor->id) {
            abort(403, 'Cross-restaurant permission override prohibited.');
        }

        $membership = $this->authService->resolveMembership($user, $vendor->id);
        if (! $membership) {
            abort(404, 'User membership not found.');
        }

        $this->roleService->removeUserPermissionOverride($membership, $permissionKey, $actor);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('Permission override removed successfully.'),
            ]);
        }

        return back()->with('success', __('Permission override removed successfully.'));
    }

    /**
     * Immediately terminate active sessions for a user.
     */
    public function revokeUserSessions(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $actor = Auth::user();
        $vendor = $actor->vendor;

        if ((int) $user->vendor_id !== (int) $vendor->id) {
            abort(403, 'Cross-restaurant session revocation prohibited.');
        }

        $this->roleService->revokeUserSessions($user, $actor);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('Active user sessions terminated successfully.'),
            ]);
        }

        return back()->with('success', __('Active user sessions terminated successfully.'));
    }

    /**
     * Create a new custom role.
     */
    public function storeRole(Request $request): JsonResponse|RedirectResponse
    {
        $actor = Auth::user();
        $vendor = $actor->vendor;

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,key',
        ]);

        $role = $this->roleService->createCustomRole($vendor, $actor, $validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('Custom role created successfully.'),
                'role' => $role,
            ]);
        }

        return back()->with('success', __('Custom role created successfully.'));
    }

    /**
     * Update an existing custom role.
     */
    public function updateRole(Request $request, Role $role): JsonResponse|RedirectResponse
    {
        $actor = Auth::user();
        $vendor = $actor->vendor;

        if ((int) $role->vendor_id !== (int) $vendor->id) {
            abort(403, 'Cross-restaurant role modification prohibited.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,key',
        ]);

        $updated = $this->roleService->updateCustomRole($role, $actor, $validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('Role updated successfully.'),
                'role' => $updated,
            ]);
        }

        return back()->with('success', __('Role updated successfully.'));
    }

    /**
     * Duplicate a role.
     */
    public function duplicateRole(Request $request, Role $role): JsonResponse|RedirectResponse
    {
        $actor = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100',
        ]);

        $duplicate = $this->roleService->duplicateRole(
            sourceRole: $role,
            actor: $actor,
            newName: $validated['name'],
            newSlug: $validated['slug'] ?? null
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('Role duplicated successfully.'),
                'role' => $duplicate,
            ]);
        }

        return back()->with('success', __('Role duplicated successfully.'));
    }

    /**
     * Delete a custom role.
     */
    public function destroyRole(Request $request, Role $role): JsonResponse|RedirectResponse
    {
        $actor = Auth::user();

        $this->roleService->deleteCustomRole($role, $actor);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('Role deleted successfully.'),
            ]);
        }

        return back()->with('success', __('Role deleted successfully.'));
    }

    /**
     * Export immutable audit logs for compliance.
     */
    public function exportAuditLogs(Request $request): StreamedResponse
    {
        $vendor = Auth::user()->vendor;

        $query = AuthorizationAuditLog::where('vendor_id', $vendor->id)
            ->with(['actor:id,name,email', 'targetUser:id,name,email'])
            ->latest('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->get('action'));
        }

        $logs = $query->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="authorization_audit_logs_'.date('Y_m_d_His').'.csv"',
        ];

        return response()->stream(function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for UTF-8 Excel support

            fputcsv($handle, [
                'ID',
                'Date & Time (UTC)',
                'Actor Name',
                'Actor Email',
                'Target User',
                'Action',
                'Resource Type',
                'Resource ID',
                'IP Address',
                'Old Values',
                'New Values',
            ]);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->created_at->toIso8601String(),
                    $log->actor?->name ?? 'System',
                    $log->actor?->email ?? 'N/A',
                    $log->targetUser?->name ?? 'N/A',
                    $log->action,
                    $log->resource_type ?? 'N/A',
                    $log->resource_id ?? 'N/A',
                    $log->ip_address ?? 'N/A',
                    $log->old_values ? json_encode($log->old_values) : '',
                    $log->new_values ? json_encode($log->new_values) : '',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
