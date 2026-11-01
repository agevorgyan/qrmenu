<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PosOperationLog;
use App\Models\PosPayment;
use App\Models\PosShift;
use App\Models\RestaurantTable;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Security\Permission;
use App\Services\PinSecurityService;
use App\Services\SecurityAuditService;
use App\Services\TwoFactorAuthService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PosWorkspaceController extends Controller
{
    public function __construct(
        protected PinSecurityService $pinSecurityService,
        protected SecurityAuditService $securityAuditService,
    ) {}

    /**
     * Get user profile details, roles, permissions and allowed workspaces.
     */
    public function getProfile(): JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $allowedModes = match ($user->role) {
            'waiter' => ['waiter'],
            'cashier' => ['cashier'],
            'chef' => ['kds'],
            'manager', 'vendor_owner' => ['manager', 'cashier', 'waiter', 'kds'],
            default => $user->isSuperAdmin() ? ['manager', 'cashier', 'waiter', 'kds'] : ['waiter'],
        };

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'display_name' => $user->display_name ?: $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'employee_id' => $user->employee_id,
                'avatar_url' => $user->avatar_url,
                'pos_mode' => $user->pos_mode ?: $allowedModes[0],
                'has_pin' => $user->hasPin(),
                'two_factor_enabled' => (bool) $user->two_factor_enabled,
                'assigned_table_ids' => $user->assigned_table_ids ?? [],
                'permissions' => $user->getPermissions(),
                'allowed_modes' => $allowedModes,
                'vendor_name' => $vendor?->name,
            ],
        ]);
    }

    /**
     * Update user profile information.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'display_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'avatar_url' => 'nullable|string|max:500',
        ]);

        $user->update([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'] ?? $user->display_name,
            'phone' => $validated['phone'] ?? null,
            'avatar_url' => $validated['avatar_url'] ?? $user->avatar_url,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Profile details updated successfully.'),
            'user' => [
                'name' => $user->name,
                'display_name' => $user->display_name,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_url,
            ],
        ]);
    }

    /**
     * Change user account password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'password.confirmed' => __('The new password confirmation does not match.'),
            'password.min' => __('The password must be at least 6 characters.'),
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => __('Current password is incorrect.'),
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->securityAuditService->logPasswordChange($user, actor: $user);

        return response()->json([
            'success' => true,
            'message' => __('Your password has been changed successfully.'),
        ]);
    }

    /**
     * Set or change user PIN.
     */
    public function updatePin(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_auth' => 'required|string',
            'pin' => 'required|string|min:4|max:6|confirmed',
            'pin_confirmation' => 'required|string',
            'email_code' => 'nullable|string',
        ], [
            'pin.confirmed' => __('The PIN confirmation does not match.'),
            'pin.min' => __('The PIN code must be between 4 and 6 numeric digits.'),
            'pin.max' => __('The PIN code must be between 4 and 6 numeric digits.'),
        ]);

        try {
            $this->pinSecurityService->setPin(
                user: $user,
                newPin: $validated['pin'],
                currentPasswordOrPin: $validated['current_auth'],
                emailVerificationCode: $request->input('email_code'),
                actor: $user,
                requireCurrentAuth: true
            );

            return response()->json([
                'success' => true,
                'message' => __('Your PIN code has been updated successfully.'),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: __('Failed to update PIN code.'),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Send email verification code for PIN update.
     */
    public function sendPinCode(): JsonResponse
    {
        $user = Auth::user();
        TwoFactorAuthService::sendEmailCode($user, 'pin');

        return response()->json([
            'success' => true,
            'message' => __('A 6-digit security code for PIN update has been sent to your email.'),
            'masked_email' => $user->maskedEmail(),
        ]);
    }

    /**
     * Logout other devices.
     */
    public function logoutOtherDevices(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        $user = Auth::user();

        if (! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => __('Current password is incorrect.'),
            ], 422);
        }

        Auth::logoutOtherDevices($validated['password']);

        $this->securityAuditService->record(
            event: SecurityAuditLog::EVENT_LOGOUT,
            action: 'logout_other_devices',
            vendor: $user->vendor,
            actor: $user,
            targetType: 'User',
            targetId: $user->id,
            metadata: ['description' => 'User logged out all other browser and POS sessions.']
        );

        return response()->json([
            'success' => true,
            'message' => __('Successfully logged out of all other active sessions.'),
        ]);
    }

    /**
     * 1.1 WAITER DASHBOARD
     */
    public function getWaiterDashboard(): JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        if (! in_array($user->role, ['waiter', 'staff', 'manager', 'vendor_owner'], true) && ! $user->hasPermission(Permission::ORDERS_VIEW_OWN)) {
            abort(403, 'Unauthorized. Waiter permission required.');
        }

        $startOfDay = Carbon::today();

        // 1. Waiter's active orders
        $activeOrders = Order::where('vendor_id', $vendor->id)
            ->where('waiter_id', $user->id)
            ->whereIn('status', ['pending', 'accepted', 'confirmed', 'preparing', 'partially_ready', 'ready', 'served'])
            ->with(['items', 'restaurantTable'])
            ->latest('id')
            ->get();

        // 2. Count of orders completed or handled today
        $todayOrdersCount = Order::where('vendor_id', $vendor->id)
            ->where('waiter_id', $user->id)
            ->where('created_at', '>=', $startOfDay)
            ->count();

        // 3. Today's tips for this waiter
        $todayTips = (float) Order::where('vendor_id', $vendor->id)
            ->where('waiter_id', $user->id)
            ->where('created_at', '>=', $startOfDay)
            ->whereIn('status', ['completed', 'served'])
            ->sum('tip_amount');

        // 4. Assigned tables
        $assignedTableIds = is_array($user->assigned_table_ids) ? $user->assigned_table_ids : [];
        $tables = RestaurantTable::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->when(! empty($assignedTableIds), fn ($q) => $q->whereIn('id', $assignedTableIds))
            ->with(['currentOrder.items', 'currentWaiter'])
            ->orderBy('sort_order')
            ->orderBy('table_number')
            ->get()
            ->map(function ($table) use ($user) {
                $hasActiveOrder = $table->currentOrder && ! in_array($table->currentOrder->status, ['completed', 'cancelled'], true);

                return [
                    'id' => $table->id,
                    'table_number' => $table->table_number,
                    'section' => $table->section ?: 'Main Hall',
                    'capacity' => (int) $table->capacity,
                    'status' => $table->status,
                    'is_assigned_to_me' => true,
                    'active_order' => $hasActiveOrder ? [
                        'id' => $table->currentOrder->id,
                        'order_number' => $table->currentOrder->order_number,
                        'status' => $table->currentOrder->status,
                        'total_amount' => (float) $table->currentOrder->total_amount,
                        'guest_count' => (int) $table->currentOrder->guest_count,
                        'waiter_id' => $table->currentOrder->waiter_id,
                        'is_my_order' => (int) $table->currentOrder->waiter_id === (int) $user->id,
                    ] : null,
                ];
            });

        return response()->json([
            'success' => true,
            'summary' => [
                'active_orders_count' => $activeOrders->count(),
                'today_orders_count' => $todayOrdersCount,
                'today_tips' => $todayTips,
                'assigned_tables_count' => $tables->count(),
            ],
            'active_orders' => $activeOrders,
            'assigned_tables' => $tables,
        ]);
    }

    /**
     * Waiter's personal tip history & tracker.
     */
    public function getWaiterTips(): JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        if (! in_array($user->role, ['waiter', 'staff', 'manager', 'vendor_owner'], true) && ! $user->hasPermission(Permission::TIPS_VIEW_OWN)) {
            abort(403, 'Unauthorized. Waiter permission required.');
        }

        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();

        $tipsQuery = Order::where('vendor_id', $vendor->id)
            ->where('waiter_id', $user->id)
            ->where('tip_amount', '>', 0)
            ->whereIn('status', ['completed', 'served']);

        $todayTotal = (float) (clone $tipsQuery)->where('created_at', '>=', $today)->sum('tip_amount');
        $weekTotal = (float) (clone $tipsQuery)->where('created_at', '>=', $startOfWeek)->sum('tip_amount');
        $monthTotal = (float) (clone $tipsQuery)->where('created_at', '>=', $startOfMonth)->sum('tip_amount');

        $recentTips = (clone $tipsQuery)
            ->with(['restaurantTable'])
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn ($o) => [
                'order_id' => $o->id,
                'order_number' => $o->order_number,
                'table_number' => $o->table_number ?: $o->restaurantTable?->table_number,
                'tip_amount' => (float) $o->tip_amount,
                'order_total' => (float) $o->total_amount,
                'payment_method' => $o->payment_method ?: 'cash',
                'created_at' => $o->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'today_total' => $todayTotal,
            'week_total' => $weekTotal,
            'month_total' => $monthTotal,
            'recent_tips' => $recentTips,
        ]);
    }

    /**
     * Waiter submits an order cancellation request.
     */
    public function requestOrderCancellation(Request $request, Order $order): JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        if ((int) $order->vendor_id !== (int) $vendor->id) {
            abort(403, 'Unauthorized order access.');
        }

        // Waiter can only request cancellation for their own order unless they have orders.manage
        if ($user->role === 'waiter' && (int) $order->waiter_id !== (int) $user->id && ! $user->hasPermission(Permission::ORDERS_MANAGE)) {
            abort(403, 'You may only request cancellation for your own assigned orders.');
        }

        if (in_array($order->status, ['completed', 'cancelled'], true)) {
            return response()->json([
                'success' => false,
                'message' => __('Cannot request cancellation for an order that is already :status.', ['status' => $order->status]),
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:255',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'integer',
        ]);

        $order->update([
            'cancellation_status' => 'pending_approval',
            'cancellation_reason' => $validated['reason'],
        ]);

        PosOperationLog::log(
            vendor: $vendor,
            action: 'cancel_request',
            user: $user,
            entity: $order,
            details: [
                'order_number' => $order->order_number,
                'reason' => $validated['reason'],
                'items' => $validated['item_ids'] ?? 'entire_order',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => __('Cancellation request submitted to manager for approval.'),
            'order' => $order->fresh(),
        ]);
    }

    /**
     * 1.2 CASHIER DASHBOARD
     */
    public function getCashierDashboard(): JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        if (! in_array($user->role, ['cashier', 'manager', 'vendor_owner'], true) && ! $user->hasPermission(Permission::POS_PAYMENTS)) {
            abort(403, 'Unauthorized. Cashier permission required.');
        }

        $startOfDay = Carbon::today();

        // 1. Current active shift for cashier / device
        $currentShift = PosShift::where('vendor_id', $vendor->id)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->latest('id')
            ->first();

        // If no shift for this specific cashier, check latest open shift for the vendor
        if (! $currentShift) {
            $currentShift = PosShift::where('vendor_id', $vendor->id)
                ->where('status', 'open')
                ->latest('id')
                ->first();
        }

        // 2. Unpaid orders needing checkout/billing
        $unpaidOrders = Order::where('vendor_id', $vendor->id)
            ->whereIn('status', ['confirmed', 'preparing', 'partially_ready', 'ready', 'served'])
            ->with(['restaurantTable', 'waiter', 'items'])
            ->latest('id')
            ->get();

        $unpaidTotal = (float) $unpaidOrders->sum('total_amount');

        // 3. Today's payments processed by this cashier
        $cashierPayments = PosPayment::where('vendor_id', $vendor->id)
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $startOfDay)
            ->where('status', 'completed')
            ->get();

        $todayCashTotal = (float) $cashierPayments->where('payment_method', 'cash')->sum('amount');
        $todayCardTotal = (float) $cashierPayments->whereIn('payment_method', ['card', 'pos_terminal', 'stripe'])->sum('amount');
        $todayTotalCollected = (float) $cashierPayments->sum('amount');

        // 4. Recent payments
        $recentPayments = PosPayment::where('vendor_id', $vendor->id)
            ->where('created_at', '>=', $startOfDay)
            ->with(['order.restaurantTable', 'user'])
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'order_id' => $p->order_id,
                'order_number' => $p->order?->order_number,
                'amount' => (float) $p->amount,
                'payment_method' => $p->payment_method,
                'status' => $p->status,
                'cashier_name' => $p->user?->name ?: 'Cashier',
                'created_at' => $p->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'summary' => [
                'unpaid_orders_count' => $unpaidOrders->count(),
                'unpaid_orders_total' => $unpaidTotal,
                'today_collected_total' => $todayTotalCollected,
                'today_cash_total' => $todayCashTotal,
                'today_card_total' => $todayCardTotal,
                'shift_status' => $currentShift ? 'open' : 'closed',
            ],
            'current_shift' => $currentShift ? [
                'id' => $currentShift->id,
                'shift_id' => $currentShift->id,
                'opened_at' => $currentShift->opened_at?->toIso8601String(),
                'opening_float' => (float) $currentShift->opening_float,
                'current_cash' => (float) ($currentShift->expected_cash ?: ($currentShift->opening_float + $currentShift->total_cash_sales)),
                'cash_sales' => (float) $currentShift->total_cash_sales,
                'card_sales' => (float) $currentShift->total_card_sales,
                'total_sales' => (float) ($currentShift->total_cash_sales + $currentShift->total_card_sales),
            ] : null,
            'unpaid_orders' => $unpaidOrders,
            'recent_payments' => $recentPayments,
        ]);
    }

    /**
     * 1.3 MANAGER DASHBOARD
     */
    public function getManagerDashboard(): JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        if (! in_array($user->role, ['manager', 'vendor_owner'], true) && ! $user->hasPermission(Permission::ORDERS_VIEW_ALL)) {
            abort(403, 'Unauthorized. Manager access required.');
        }

        $startOfDay = Carbon::today();

        // 1. Operational Overview
        $allActiveOrders = Order::where('vendor_id', $vendor->id)
            ->whereIn('status', ['pending', 'accepted', 'confirmed', 'preparing', 'partially_ready', 'ready', 'served'])
            ->with(['waiter', 'restaurantTable', 'items'])
            ->latest('id')
            ->get();

        $todayCompletedOrders = Order::where('vendor_id', $vendor->id)
            ->where('created_at', '>=', $startOfDay)
            ->where('status', 'completed')
            ->get();

        $todaySales = (float) $todayCompletedOrders->sum('total_amount');
        $todayDiscounts = (float) $todayCompletedOrders->sum('discount_amount');
        $todayTips = (float) $todayCompletedOrders->sum('tip_amount');

        // 2. Table occupancy
        $totalTables = RestaurantTable::where('vendor_id', $vendor->id)->where('is_active', true)->count();
        $occupiedTables = RestaurantTable::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->where('status', 'occupied')
            ->count();

        // 3. Active shifts
        $openShiftsCount = PosShift::where('vendor_id', $vendor->id)->where('status', 'open')->count();

        // 4. Pending exceptions (cancellation requests)
        $pendingExceptions = Order::where('vendor_id', $vendor->id)
            ->whereIn('status', ['pending', 'accepted', 'confirmed', 'preparing', 'partially_ready', 'ready', 'served'])
            ->where('cancellation_status', 'pending_approval')
            ->with(['waiter'])
            ->get()
            ->map(fn ($o) => [
                'order_id' => $o->id,
                'order_number' => $o->order_number,
                'table_number' => $o->table_number,
                'total_amount' => (float) $o->total_amount,
                'waiter_name' => $o->waiter?->name,
                'request' => [
                    'requested_by_user_id' => $o->waiter_id,
                    'requested_by_name' => $o->waiter?->name,
                    'reason' => $o->cancellation_reason,
                    'status' => 'pending_approval',
                    'requested_at' => $o->updated_at?->toIso8601String(),
                ],
            ]);

        // 5. Staff activity
        $staffMembers = User::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->whereIn('role', ['waiter', 'cashier', 'chef', 'manager'])
            ->get(['id', 'name', 'role', 'pos_mode', 'avatar_url', 'phone', 'employee_id']);

        return response()->json([
            'success' => true,
            'metrics' => [
                'active_orders_count' => $allActiveOrders->count(),
                'today_sales' => $todaySales,
                'today_discounts' => $todayDiscounts,
                'today_tips' => $todayTips,
                'total_tables' => $totalTables,
                'occupied_tables' => $occupiedTables,
                'occupancy_rate' => $totalTables > 0 ? round(($occupiedTables / $totalTables) * 100, 1) : 0,
                'open_shifts_count' => $openShiftsCount,
                'pending_exceptions_count' => $pendingExceptions->count(),
            ],
            'pending_exceptions' => $pendingExceptions,
            'active_orders' => $allActiveOrders,
            'staff' => $staffMembers,
        ]);
    }

    /**
     * Manager approves an order cancellation request.
     */
    public function approveOrderCancellation(Request $request, Order $order): JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        if ((int) $order->vendor_id !== (int) $vendor->id) {
            abort(403, 'Unauthorized order access.');
        }

        if (! $user->hasPermission(Permission::ORDERS_APPROVE_CANCELLATION) && ! in_array($user->role, ['manager', 'vendor_owner'], true)) {
            abort(403, 'Unauthorized. Manager cancellation approval permission required.');
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $cancelReason = $validated['reason'] ?? ($order->cancellation_reason ?: 'Manager approved cancellation');

        DB::transaction(function () use ($order, $cancelReason, $user, $vendor) {
            $order->update([
                'status' => 'cancelled',
                'cancellation_status' => 'approved',
                'cancellation_reason' => $cancelReason,
            ]);

            // Release table if dine-in
            if ($order->restaurant_table_id) {
                RestaurantTable::where('id', $order->restaurant_table_id)->update(['status' => 'available']);
            }

            PosOperationLog::log(
                vendor: $vendor,
                action: 'cancellation_approved',
                user: $user,
                entity: $order,
                details: [
                    'order_number' => $order->order_number,
                    'approved_by_manager' => $user->name,
                    'reason' => $cancelReason,
                ]
            );

            $this->securityAuditService->record(
                event: SecurityAuditLog::EVENT_SECURITY_CONFIG_CHANGED,
                action: 'order_cancellation_approved',
                vendor: $vendor,
                actor: $user,
                targetType: 'Order',
                targetId: $order->id,
                metadata: [
                    'order_number' => $order->order_number,
                    'reason' => $cancelReason,
                ]
            );
        });

        return response()->json([
            'success' => true,
            'message' => __('Order cancellation has been approved.'),
            'order' => $order->fresh(),
        ]);
    }

    /**
     * Manager rejects an order cancellation request.
     */
    public function rejectOrderCancellation(Request $request, Order $order): JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        if ((int) $order->vendor_id !== (int) $vendor->id) {
            abort(403, 'Unauthorized order access.');
        }

        if (! $user->hasPermission(Permission::ORDERS_APPROVE_CANCELLATION) && ! in_array($user->role, ['manager', 'vendor_owner'], true)) {
            abort(403, 'Unauthorized. Manager permission required.');
        }

        $order->update([
            'cancellation_status' => 'rejected',
        ]);

        PosOperationLog::log(
            vendor: $vendor,
            action: 'cancellation_rejected',
            user: $user,
            entity: $order,
            details: [
                'order_number' => $order->order_number,
                'rejected_by' => $user->name,
                'reason' => $request->input('reason'),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => __('Order cancellation request rejected.'),
            'order' => $order->fresh(),
        ]);
    }
}
