<?php

namespace App\Http\Controllers;

use App\Events\OrderStatusUpdated;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Models\WaiterCall;
use App\Services\OrderService;
use App\Services\ThermalPrinterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Order::class);

        $user = Auth::user();
        $vendor = $user->vendor;

        if ($user->location_id) {
            $activeLocationId = $user->location_id;
            if ($request->has('location_id') && (int) $request->get('location_id') !== (int) $user->location_id) {
                abort(403, 'Unauthorized access to other branch orders.');
            }
        } else {
            $activeLocationId = $request->get('location_id', session('active_location_id', $vendor->locations->first()?->id));
            if ($activeLocationId) {
                session(['active_location_id' => $activeLocationId]);
            }
        }

        $status = $request->get('status', 'all');

        $location = ($activeLocationId ? $vendor->locations->firstWhere('id', (int) $activeLocationId) : null) ?? $vendor->locations->first();
        $orders = $this->orderService->getOrders($location?->id, $status);
        $waiterCalls = $this->orderService->getPendingWaiterCalls($location?->id);

        return view('admin.orders.index', compact('vendor', 'orders', 'location', 'status', 'waiterCalls'));
    }

    public function feed(Request $request)
    {
        $this->authorize('viewAny', Order::class);

        $user = Auth::user();
        $vendor = $user->vendor;

        if ($user->location_id) {
            $activeLocationId = $user->location_id;
            if ($request->has('location_id') && (int) $request->get('location_id') !== (int) $user->location_id) {
                abort(403, 'Unauthorized access to other branch orders.');
            }
        } else {
            $activeLocationId = $request->get('location_id', session('active_location_id', $vendor->locations->first()?->id));
        }

        $location = ($activeLocationId ? $vendor->locations->firstWhere('id', (int) $activeLocationId) : null) ?? $vendor->locations->first();
        $status = $request->get('status', 'all');
        $lastOrderId = (int) $request->get('last_order_id', 0);

        $orders = $this->orderService->getOrders($location?->id, $status);
        $latestOrderId = $this->orderService->getLatestOrderId($location?->id);
        $pendingCount = $this->orderService->getPendingOrdersCount($location?->id);

        $waiterCalls = $this->orderService->getPendingWaiterCalls($location?->id);
        $waiterCallsCount = $waiterCalls->count();
        $waiterCallsHtml = view('admin.orders.partials.waiter_call_cards', compact('waiterCalls'))->render();

        $hasNew = ($lastOrderId > 0 && $latestOrderId > $lastOrderId);

        $html = view('admin.orders.partials.order_cards', compact('orders', 'vendor'))->render();

        return response()->json([
            'success' => true,
            'latest_order_id' => $latestOrderId,
            'pending_count' => $pendingCount,
            'waiter_calls_count' => $waiterCallsCount,
            'waiter_calls_html' => $waiterCallsHtml,
            'has_new' => $hasNew,
            'html' => $html,
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order)
    {
        $validated = $request->validated();
        $newStatus = $validated['status'];

        if ($newStatus === 'cancelled') {
            $this->authorize('cancel', $order);
        } elseif ($newStatus === 'refunded') {
            $this->authorize('refund', $order);
        } else {
            $this->authorize('update', $order);
        }

        $order->update(['status' => $validated['status']]);

        // Broadcast real-time status update for kitchen screens & customer tracker
        try {
            event(new OrderStatusUpdated($order));
        } catch (\Throwable $e) {
            Log::warning('OrderStatusUpdated broadcast failed: '.$e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'status' => $order->status, 'message' => "Order #{$order->order_number} updated to {$order->status}."]);
        }

        return back()->with('success', __('Order #:number status updated to :status.', [
            'number' => $order->order_number,
            'status' => __($order->status),
        ]));
    }

    public function updateWaiterCallStatus(Request $request, WaiterCall $waiterCall)
    {
        $this->authorize('update', $waiterCall);

        $status = $request->input('status', 'attended');
        if (! in_array($status, ['pending', 'attended', 'cancelled'])) {
            $status = 'attended';
        }

        $waiterCall->update(['status' => $status]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'status' => $waiterCall->status,
                'message' => "Table #{$waiterCall->table_number} call marked as attended.",
            ]);
        }

        return back()->with('success', __('Table #:table call marked as attended.', ['table' => $waiterCall->table_number]));
    }

    /**
     * Get ESC/POS formatted receipt text and RawBT URL for thermal printing.
     */
    public function receiptText(Order $order, ThermalPrinterService $printerService)
    {
        $this->authorize('view', $order);

        $order->load(['items.product', 'location', 'vendor']);

        $receiptText = $printerService->generateReceiptText($order);
        $rawBtUrl = $printerService->getRawBtUrl($order);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'receipt_text' => $receiptText,
            'rawbt_url' => $rawBtUrl,
        ]);
    }
}
