<?php

namespace App\Http\Controllers;

use App\Models\RestaurantTable;
use App\Models\User;
use App\Security\Permission;
use App\Services\QuotaService;
use App\Services\SecurityAuditService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminWaiterController extends Controller
{
    public function __construct(
        protected StorageService $storageService,
        protected SecurityAuditService $auditService,
        protected QuotaService $quotaService
    ) {}

    /**
     * Display the dedicated Waiter Management dashboard.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $this->authorizeWaitersView($user);
        $vendor = $user->vendor;

        $search = $request->string('search')->trim()->value();
        $status = $request->string('status', 'all')->value();
        $locationId = $request->filled('location_id') ? (int) $request->input('location_id') : null;

        $waitersQuery = User::where('vendor_id', $vendor->id)
            ->whereIn('role', ['waiter', 'staff', 'manager', 'cashier'])
            ->with(['assignedTables', 'location'])
            ->withCount(['ratings', 'tips'])
            ->withAvg('ratings', 'rating')
            ->withSum([
                'tips as tips_sum_amount' => function ($q) {
                    $q->whereIn('payment_status', ['paid', 'settled']);
                },
            ], 'amount')
            ->when(! empty($search), function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('display_name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when(! empty($locationId), fn ($q) => $q->where('location_id', $locationId))
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc');

        $waiters = $waitersQuery->paginate(20)->withQueryString();

        // Metrics for summary cards
        $totalWaiters = User::where('vendor_id', $vendor->id)
            ->whereIn('role', ['waiter', 'staff', 'manager', 'cashier'])
            ->count();

        $activeWaitersCount = User::where('vendor_id', $vendor->id)
            ->whereIn('role', ['waiter', 'staff', 'manager', 'cashier'])
            ->where('is_active', true)
            ->count();

        $locations = $vendor->locations()->orderBy('name')->get();
        $tables = RestaurantTable::where('vendor_id', $vendor->id)->orderBy('table_number')->get();
        $settings = $vendor->getTipRatingSetting();

        return view('admin.waiters.index', compact(
            'vendor',
            'waiters',
            'totalWaiters',
            'activeWaitersCount',
            'locations',
            'tables',
            'settings',
            'search',
            'status',
            'locationId'
        ));
    }

    /**
     * Store a newly created waiter in the vendor's roster.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeWaitersManage($user);
        $vendor = $user->vendor;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'short_description' => 'nullable|string|max:500',
            'employee_id' => 'nullable|string|max:50',
            'role' => 'nullable|string|in:waiter,staff,manager,cashier',
            'location_id' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'assigned_tables' => 'nullable|array',
            'assigned_tables.*' => 'integer',
            'photo' => 'nullable|file|mimes:jpeg,png,webp,jpg|max:5120',
            'email' => 'nullable|email|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'password' => 'nullable|string|min:6',
        ]);

        if (! empty($validated['location_id'])) {
            $location = $vendor->locations()->find($validated['location_id']);
            if (! $location) {
                abort(403, 'Unauthorized location assignment.');
            }
        }

        $photoUrl = null;
        if ($request->hasFile('photo')) {
            $storageFile = $this->storageService->store(
                file: $request->file('photo'),
                namespace: 'waiters',
                vendor: $vendor
            );
            $photoUrl = 'storage/'.$storageFile->path;
        }

        $email = $validated['email'] ?? null;
        $password = $validated['password'] ?? null;

        if (empty($email)) {
            $email = 'waiter_'.Str::uuid().'@internal.invalid';
            $password = Str::random(32);
        } else {
            $password = $password ?: Str::random(24);
        }

        $validTableIds = [];
        if (! empty($validated['assigned_tables'])) {
            $validTableIds = RestaurantTable::where('vendor_id', $vendor->id)
                ->whereIn('id', $validated['assigned_tables'])
                ->pluck('id')
                ->all();
        }

        $waiter = DB::transaction(function () use ($vendor, $validated, $email, $password, $photoUrl, $validTableIds) {
            $waiter = User::create([
                'vendor_id' => $vendor->id,
                'location_id' => $validated['location_id'] ?? null,
                'name' => $validated['name'],
                'display_name' => $validated['display_name'] ?? null,
                'short_description' => $validated['short_description'] ?? null,
                'employee_id' => $validated['employee_id'] ?? null,
                'avatar_url' => $photoUrl,
                'role' => $validated['role'] ?? 'waiter',
                'is_active' => $validated['is_active'] ?? true,
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'assigned_table_ids' => $validTableIds,
                'email' => $email,
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($password),
            ]);

            if (! empty($validTableIds)) {
                $syncData = [];
                foreach ($validTableIds as $tableId) {
                    $syncData[$tableId] = ['vendor_id' => $vendor->id];
                }
                $waiter->assignedTables()->sync($syncData);
            }

            return $waiter;
        });

        $this->auditService->record(
            event: 'waiter.created',
            action: "Created waiter profile [{$waiter->name}]",
            vendor: $vendor,
            actor: $user,
            targetType: 'user',
            targetId: $waiter->id,
            metadata: [
                'display_name' => $waiter->display_name,
                'employee_id' => $waiter->employee_id,
                'has_photo' => ! empty($photoUrl),
                'assigned_tables_count' => count($validTableIds),
            ]
        );

        return back()->with('success', __('Waiter profile added successfully!'));
    }

    /**
     * Update an existing waiter profile.
     */
    public function update(Request $request, User $waiter): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeWaitersManage($user);
        $vendor = $user->vendor;

        $this->assertTenantOwnership($waiter, $vendor);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'short_description' => 'nullable|string|max:500',
            'employee_id' => 'nullable|string|max:50',
            'role' => 'nullable|string|in:waiter,staff,manager,cashier',
            'location_id' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'assigned_tables' => 'nullable|array',
            'assigned_tables.*' => 'integer',
            'photo' => 'nullable|file|mimes:jpeg,png,webp,jpg|max:5120',
            'remove_photo' => 'nullable|boolean',
            'email' => 'nullable|email|unique:users,email,'.$waiter->id,
            'phone' => 'nullable|string|max:50',
            'password' => 'nullable|string|min:6',
        ]);

        if (! empty($validated['location_id'])) {
            $location = $vendor->locations()->find($validated['location_id']);
            if (! $location) {
                abort(403, 'Unauthorized location assignment.');
            }
        }

        $photoUrl = $waiter->avatar_url;

        // Handle photo removal if requested
        if ($request->boolean('remove_photo') && ! empty($waiter->avatar_url)) {
            $this->storageService->delete($waiter->avatar_url, $vendor);
            $photoUrl = null;
        }

        // Handle photo replacement or new upload
        if ($request->hasFile('photo')) {
            $storageFile = $this->storageService->replace(
                oldPathOrUuid: $waiter->avatar_url,
                newFile: $request->file('photo'),
                namespace: 'waiters',
                vendor: $vendor
            );
            $photoUrl = 'storage/'.$storageFile->path;
        }

        $validTableIds = [];
        if (isset($validated['assigned_tables'])) {
            $validTableIds = RestaurantTable::where('vendor_id', $vendor->id)
                ->whereIn('id', $validated['assigned_tables'])
                ->pluck('id')
                ->all();
        }

        DB::transaction(function () use ($waiter, $vendor, $validated, $photoUrl, $validTableIds, $request) {
            $updateData = [
                'name' => $validated['name'],
                'display_name' => $validated['display_name'] ?? null,
                'short_description' => $validated['short_description'] ?? null,
                'employee_id' => $validated['employee_id'] ?? null,
                'avatar_url' => $photoUrl,
                'location_id' => $validated['location_id'] ?? null,
                'role' => $validated['role'] ?? ($waiter->role ?? 'waiter'),
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $waiter->is_active,
                'sort_order' => (int) ($validated['sort_order'] ?? $waiter->sort_order),
                'assigned_table_ids' => $validTableIds,
                'phone' => $validated['phone'] ?? $waiter->phone,
            ];

            if (! empty($validated['email'])) {
                $updateData['email'] = $validated['email'];
            }

            if (! empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            $waiter->update($updateData);

            if ($request->has('assigned_tables')) {
                $syncData = [];
                foreach ($validTableIds as $tableId) {
                    $syncData[$tableId] = ['vendor_id' => $vendor->id];
                }
                $waiter->assignedTables()->sync($syncData);
            }
        });

        $this->auditService->record(
            event: 'waiter.updated',
            action: "Updated waiter profile [{$waiter->name}]",
            vendor: $vendor,
            actor: $user,
            targetType: 'user',
            targetId: $waiter->id,
            metadata: [
                'display_name' => $waiter->display_name,
                'is_active' => $waiter->is_active,
            ]
        );

        return back()->with('success', __('Waiter profile updated successfully!'));
    }

    /**
     * Toggle active/inactive status of a waiter.
     */
    public function toggleStatus(User $waiter): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeWaitersManage($user);
        $vendor = $user->vendor;

        $this->assertTenantOwnership($waiter, $vendor);

        $waiter->update([
            'is_active' => ! $waiter->is_active,
        ]);

        $this->auditService->record(
            event: 'waiter.status_toggled',
            action: 'Toggled waiter status to ['.($waiter->is_active ? 'active' : 'inactive')."] for [{$waiter->name}]",
            vendor: $vendor,
            actor: $user,
            targetType: 'user',
            targetId: $waiter->id
        );

        $statusMsg = $waiter->is_active
            ? __('Waiter activated successfully!')
            : __('Waiter deactivated successfully! They will not appear in customer selection.');

        return back()->with('success', $statusMsg);
    }

    /**
     * Remove the waiter's profile photo.
     */
    public function deletePhoto(User $waiter): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeWaitersManage($user);
        $vendor = $user->vendor;

        $this->assertTenantOwnership($waiter, $vendor);

        if (! empty($waiter->avatar_url)) {
            $this->storageService->delete($waiter->avatar_url, $vendor);
            $waiter->update(['avatar_url' => null]);
        }

        return back()->with('success', __('Profile photo removed successfully.'));
    }

    /**
     * Reorder waiters for customer-facing presentation.
     */
    public function reorder(Request $request): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeWaitersManage($user);
        $vendor = $user->vendor;

        $validated = $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|integer',
            'order.*.sort_order' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($validated, $vendor) {
            foreach ($validated['order'] as $item) {
                User::where('id', $item['id'])
                    ->where('vendor_id', $vendor->id)
                    ->update(['sort_order' => (int) $item['sort_order']]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => __('Waiter display order saved successfully.'),
        ]);
    }

    /**
     * Delete a waiter record with historical data preservation protection.
     */
    public function destroy(User $waiter): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeWaitersManage($user);
        $vendor = $user->vendor;

        $this->assertTenantOwnership($waiter, $vendor);

        // Financial & audit constraint check: prevent destructive deletion if historical records exist
        $hasOrders = $waiter->orders()->exists();
        $hasTips = $waiter->tips()->exists();
        $hasRatings = $waiter->ratings()->exists();

        if ($hasOrders || $hasTips || $hasRatings) {
            return back()->with('error', __(
                'Cannot delete waiter with historical transactions, tips, or ratings. Please deactivate the waiter profile instead to preserve audit history.'
            ));
        }

        DB::transaction(function () use ($waiter, $vendor) {
            if (! empty($waiter->avatar_url)) {
                $this->storageService->delete($waiter->avatar_url, $vendor);
            }
            $waiter->assignedTables()->detach();
            $waiter->delete();
        });

        $this->auditService->record(
            event: 'waiter.deleted',
            action: "Permanently deleted waiter [{$waiter->name}]",
            vendor: $vendor,
            actor: $user,
            targetType: 'user',
            targetId: $waiter->id
        );

        return back()->with('success', __('Waiter removed successfully.'));
    }

    /**
     * Update customer waiter selection settings.
     */
    public function updateCustomerSelectionSettings(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeWaitersManage($user);
        $vendor = $user->vendor;

        $validated = $request->validate([
            'allow_waiter_selection' => 'nullable|boolean',
            'waiter_selection_stage' => 'required|string|in:order,bill,rating,all',
            'waiter_selection_required' => 'nullable|boolean',
            'show_waiter_photos' => 'nullable|boolean',
            'show_waiter_descriptions' => 'nullable|boolean',
            'waiter_sort_by' => 'required|string|in:sort_order,name',
            'filter_waiters_by_table' => 'nullable|boolean',
            'waiter_attribution_scope' => 'required|string|in:both,tips,ratings,none',
        ]);

        $settings = $vendor->getTipRatingSetting();

        $settings->update([
            'allow_waiter_selection' => $request->boolean('allow_waiter_selection', true),
            'waiter_selection_stage' => $validated['waiter_selection_stage'],
            'waiter_selection_required' => $request->boolean('waiter_selection_required', false),
            'show_waiter_photos' => $request->boolean('show_waiter_photos', true),
            'show_waiter_descriptions' => $request->boolean('show_waiter_descriptions', true),
            'waiter_sort_by' => $validated['waiter_sort_by'],
            'filter_waiters_by_table' => $request->boolean('filter_waiters_by_table', false),
            'waiter_attribution_scope' => $validated['waiter_attribution_scope'],
        ]);

        $this->auditService->record(
            event: 'waiter_settings.updated',
            action: 'Updated customer waiter selection configuration',
            vendor: $vendor,
            actor: $user,
            targetType: 'tip_rating_setting',
            targetId: $settings->id,
            metadata: $validated
        );

        return back()->with('success', __('Customer waiter selection settings updated successfully!'));
    }

    /**
     * Verify tenant ownership on target user.
     */
    protected function assertTenantOwnership(User $waiter, $vendor): void
    {
        if ((int) $waiter->vendor_id !== (int) $vendor->id) {
            abort(403, 'Unauthorized access to cross-tenant waiter profile.');
        }
    }

    /**
     * Check authorization to view waiters.
     */
    protected function authorizeWaitersView(User $user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if (! $user->hasPermission(Permission::WAITERS_VIEW) && ! $user->hasPermission(Permission::TEAM_VIEW)) {
            abort(403, 'Unauthorized access to Waiter management.');
        }
    }

    /**
     * Check authorization to manage waiters.
     */
    protected function authorizeWaitersManage(User $user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if (! $user->hasPermission(Permission::WAITERS_MANAGE) && ! $user->hasPermission(Permission::TEAM_MANAGE)) {
            abort(403, 'Unauthorized action in Waiter management.');
        }
    }
}
