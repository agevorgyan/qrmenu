<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PosDevice;
use App\Models\PosOperationLog;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Models\Vendor;
use App\Security\Permission;
use App\Services\PosDeviceService;
use App\Services\PosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(
        protected PosService $posService,
        protected PosDeviceService $posDeviceService,
    ) {}

    /**
     * Verify manager PIN for elevated operations.
     */
    protected function verifyManagerPin(Vendor $vendor, string $pin, string $action): User
    {
        $managers = User::where('vendor_id', $vendor->id)
            ->whereIn('role', ['vendor_owner', 'manager'])
            ->whereNotNull('pin_code')
            ->get();

        foreach ($managers as $mgr) {
            if ($mgr->verifyPin($pin)) {
                PosOperationLog::log(
                    vendor: $vendor,
                    action: 'manager_approval',
                    user: $mgr,
                    details: [
                        'authorized_action' => $action,
                        'approved_for_user_id' => Auth::id(),
                    ]
                );

                return $mgr;
            }
        }

        abort(403, 'Invalid manager PIN or insufficient permissions.');
    }

    /**
     * Show the main fullscreen POS terminal interface.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $mode = $request->get('mode', $user->pos_mode ?: 'waiter');
        if (! in_array($mode, ['waiter', 'cashier', 'manager', 'kds'], true)) {
            $mode = 'waiter';
        }

        $deviceUuid = $request->header('X-POS-Device-UUID', $request->cookie('pos_device_uuid'));
        $bootstrapData = $this->posService->getBootstrapPayload($user, $deviceUuid);

        return view('pos.index', compact('vendor', 'user', 'mode', 'bootstrapData'));
    }

    /**
     * Get the initialization bootstrap data via JSON.
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $user = Auth::user();
        $deviceUuid = $request->header('X-POS-Device-UUID', $request->cookie('pos_device_uuid'));
        $locationId = $request->filled('location_id') ? (int) $request->get('location_id') : null;

        $data = $this->posService->getBootstrapPayload($user, $deviceUuid, $locationId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get menu catalog for POS.
     */
    public function getMenu(Request $request): JsonResponse
    {
        $user = Auth::user();
        $locationId = $request->filled('location_id') ? (int) $request->get('location_id') : $user->location_id;
        $menu = $this->posService->getMenuPayload($user->vendor, $locationId);

        return response()->json([
            'success' => true,
            'menu' => $menu,
        ]);
    }

    /**
     * List active orders for the POS feed.
     */
    public function getOrders(Request $request): JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $locationId = $request->filled('location_id') ? (int) $request->get('location_id') : $user->location_id;
        $status = $request->get('status');
        $tableNumber = $request->get('table_number');
        $waiterId = $request->filled('waiter_id') ? (int) $request->get('waiter_id') : null;

        $orders = $this->posService->getPosOrders(
            vendor: $vendor,
            locationId: $locationId,
            status: $status,
            tableNumber: $tableNumber,
            waiterId: $waiterId,
            perPage: min(100, max(5, (int) $request->get('per_page', 30)))
        );

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Create a new order directly from the POS interface.
     */
    public function storeOrder(Request $request): JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $validated = $request->validate([
            'location_id' => 'required|integer',
            'type' => 'nullable|string|in:dine_in,takeaway,delivery,whatsapp',
            'table_number' => 'nullable|string|max:50',
            'restaurant_table_id' => 'nullable|integer',
            'guest_count' => 'nullable|integer|min:1|max:99',
            'customer_name' => 'nullable|string|max:100',
            'customer_phone' => 'nullable|string|max:30',
            'customer_email' => 'nullable|email|max:100',
            'notes' => 'nullable|string|max:500',
            'payment_method' => 'nullable|string|max:30',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|string|max:30',
            'discount_reason' => 'nullable|string|max:200',
            'manager_pin' => 'nullable|string|max:10',
            'tax_amount' => 'nullable|numeric|min:0',
            'tip_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:draft,held,pending,confirmed',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.variation_id' => 'nullable|integer',
            'items.*.variation_name' => 'nullable|string|max:100',
            'items.*.notes' => 'nullable|string|max:255',
            'items.*.modifiers' => 'nullable|array',
            'items.*.variants' => 'nullable|array',
            'items.*.kitchen_station' => 'nullable|string|max:50',
        ]);

        if (! empty($validated['discount_amount']) && (float) $validated['discount_amount'] > 0) {
            if ($validated['discount_type'] === 'manager_override' || (! $user->hasPermission(Permission::POS_DISCOUNTS) && ! in_array($user->role, ['vendor_owner', 'manager', 'cashier', 'waiter'], true))) {
                if ($request->filled('manager_pin')) {
                    $this->verifyManagerPin($vendor, (string) $request->input('manager_pin'), 'discount');
                } else {
                    abort(403, 'Unauthorized to apply manager override discount. Manager approval required.');
                }
            }
        }

        $device = null;
        $deviceUuid = $request->header('X-POS-Device-UUID', $request->cookie('pos_device_uuid'));
        if ($deviceUuid) {
            $device = PosDevice::where('vendor_id', $vendor->id)->where('uuid', $deviceUuid)->first();
        }

        $result = $this->posService->createPosOrder($vendor, $validated, $user, $device);

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully.',
            'order' => $result['order']->load(['items', 'customer', 'restaurantTable', 'waiter']),
        ], 201);
    }

    /**
     * Show single order details.
     */
    public function showOrder(Request $request, Order $order): JsonResponse
    {
        $user = Auth::user();

        if ((int) $order->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized order access.');
        }

        $order->load(['items', 'customer', 'restaurantTable', 'waiter', 'cashier', 'posDevice', 'paymentAttempts']);

        return response()->json([
            'success' => true,
            'order' => $order,
        ]);
    }

    /**
     * Update order status from the POS.
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $user = Auth::user();

        if ((int) $order->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized order status modification.');
        }

        $validated = $request->validate([
            'status' => 'required|string|in:draft,held,pending,accepted,confirmed,preparing,partially_ready,ready,served,completed,cancelled',
            'reason' => 'nullable|string|max:255',
            'lock_version' => 'nullable|integer',
            'manager_pin' => 'nullable|string|max:10',
        ]);

        if ($validated['status'] === 'cancelled') {
            if (! $user->hasPermission(Permission::ORDERS_CANCEL) && ! in_array($user->role, ['vendor_owner', 'manager', 'cashier'], true)) {
                if ($request->filled('manager_pin')) {
                    $this->verifyManagerPin($order->vendor, (string) $request->input('manager_pin'), 'cancellation');
                } else {
                    abort(403, 'Unauthorized to cancel orders. Manager approval required.');
                }
            }
        }

        $device = null;
        $deviceUuid = $request->header('X-POS-Device-UUID');
        if ($deviceUuid) {
            $device = PosDevice::where('vendor_id', $user->vendor_id)->where('uuid', $deviceUuid)->first();
        }

        $updated = $this->posService->updateOrderStatus(
            order: $order,
            newStatus: $validated['status'],
            user: $user,
            device: $device,
            reason: $validated['reason'] ?? null,
            expectedLockVersion: $validated['lock_version'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully.',
            'order' => $updated,
        ]);
    }

    /**
     * Hold an active order.
     */
    public function holdOrder(Request $request, Order $order): JsonResponse
    {
        $user = Auth::user();

        if ((int) $order->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized order modification.');
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $held = $this->posService->holdOrder($order, $user, null, $validated['reason'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Order held successfully.',
            'order' => $held,
        ]);
    }

    /**
     * Resume a held order.
     */
    public function resumeOrder(Request $request, Order $order): JsonResponse
    {
        $user = Auth::user();

        if ((int) $order->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized order modification.');
        }

        $resumed = $this->posService->resumeOrder($order, $user);

        return response()->json([
            'success' => true,
            'message' => 'Order resumed successfully.',
            'order' => $resumed,
        ]);
    }

    /**
     * Get tables with floor plan, real-time statuses, and active orders.
     */
    public function getTables(Request $request): JsonResponse
    {
        $user = Auth::user();
        $locationId = $request->filled('location_id') ? (int) $request->get('location_id') : $user->location_id;
        $data = $this->posService->getTablesPayload($user->vendor, $locationId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Update table status (e.g. cleaning, available).
     */
    public function updateTableStatus(Request $request, RestaurantTable $table): JsonResponse
    {
        $user = Auth::user();

        if ((int) $table->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized table access.');
        }

        $validated = $request->validate([
            'status' => 'required|string|in:available,occupied,reserved,ordering,preparing,ready,awaiting_payment,cleaning',
            'lock_version' => 'nullable|integer',
        ]);

        $updated = $this->posService->updateTableStatus(
            table: $table,
            status: $validated['status'],
            user: $user,
            expectedLockVersion: $validated['lock_version'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Table status updated successfully.',
            'table' => $updated,
        ]);
    }

    /**
     * Transfer an active order to another table.
     */
    public function transferTable(Request $request, Order $order): JsonResponse
    {
        $user = Auth::user();

        if ((int) $order->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized order transfer.');
        }

        $validated = $request->validate([
            'destination_table_id' => 'required|integer',
            'reason' => 'nullable|string|max:255',
            'lock_version' => 'nullable|integer',
        ]);

        $destinationTable = RestaurantTable::where('vendor_id', $user->vendor_id)
            ->findOrFail($validated['destination_table_id']);

        $transferred = $this->posService->transferTable(
            order: $order,
            destinationTable: $destinationTable,
            user: $user,
            reason: $validated['reason'] ?? null,
            expectedOrderLockVersion: $validated['lock_version'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Order transferred to table '.$destinationTable->table_number.' successfully.',
            'order' => $transferred,
        ]);
    }

    /**
     * Merge source order into target order.
     */
    public function mergeOrders(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'source_order_id' => 'required|integer',
            'target_order_id' => 'required|integer|different:source_order_id',
            'reason' => 'nullable|string|max:255',
            'target_lock_version' => 'nullable|integer',
        ]);

        $sourceOrder = Order::where('vendor_id', $user->vendor_id)->findOrFail($validated['source_order_id']);
        $targetOrder = Order::where('vendor_id', $user->vendor_id)->findOrFail($validated['target_order_id']);

        $merged = $this->posService->mergeOrders(
            sourceOrder: $sourceOrder,
            targetOrder: $targetOrder,
            user: $user,
            reason: $validated['reason'] ?? null,
            expectedTargetLockVersion: $validated['target_lock_version'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Orders merged successfully.',
            'order' => $merged,
        ]);
    }

    /**
     * Split items from an order into a newly created split order.
     */
    public function splitOrder(Request $request, Order $order): JsonResponse
    {
        $user = Auth::user();

        if ((int) $order->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized order split.');
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'target_table_id' => 'nullable|integer',
            'reason' => 'nullable|string|max:255',
            'lock_version' => 'nullable|integer',
        ]);

        $targetTable = null;
        if (! empty($validated['target_table_id'])) {
            $targetTable = RestaurantTable::where('vendor_id', $user->vendor_id)->findOrFail($validated['target_table_id']);
        }

        $result = $this->posService->splitOrder(
            sourceOrder: $order,
            itemsToSplit: $validated['items'],
            targetTable: $targetTable,
            user: $user,
            reason: $validated['reason'] ?? null,
            expectedLockVersion: $validated['lock_version'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Order split successfully.',
            'source_order' => $result['source_order'],
            'split_order' => $result['split_order'],
        ]);
    }

    /**
     * Assign / reassign designated waiter to an order.
     */
    public function assignWaiter(Request $request, Order $order): JsonResponse
    {
        $user = Auth::user();

        if ((int) $order->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized waiter reassignment.');
        }

        $validated = $request->validate([
            'waiter_id' => 'required|integer',
            'reason' => 'nullable|string|max:255',
            'lock_version' => 'nullable|integer',
        ]);

        $newWaiter = User::where('vendor_id', $user->vendor_id)->findOrFail($validated['waiter_id']);

        $updated = $this->posService->assignWaiter(
            order: $order,
            newWaiter: $newWaiter,
            actor: $user,
            reason: $validated['reason'] ?? null,
            expectedLockVersion: $validated['lock_version'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Waiter assigned successfully.',
            'order' => $updated,
        ]);
    }
}
