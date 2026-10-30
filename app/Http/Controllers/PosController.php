<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PosDevice;
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
        ]);

        $device = null;
        $deviceUuid = $request->header('X-POS-Device-UUID');
        if ($deviceUuid) {
            $device = PosDevice::where('vendor_id', $user->vendor_id)->where('uuid', $deviceUuid)->first();
        }

        $updated = $this->posService->updateOrderStatus($order, $validated['status'], $user, $device, $validated['reason'] ?? null);

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
}
