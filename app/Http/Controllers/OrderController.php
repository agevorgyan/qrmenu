<?php

namespace App\Http\Controllers;

use App\Events\OrderStatusUpdated;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Mail\OrderReceiptMail;
use App\Models\Order;
use App\Models\WaiterCall;
use App\Services\OrderService;
use App\Services\ThermalPrinterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

        $previousStatus = $order->status;
        $order->update(['status' => $validated['status']]);

        // Broadcast real-time status update for kitchen screens & customer tracker
        try {
            event(new OrderStatusUpdated($order));
        } catch (\Throwable $e) {
            Log::warning('OrderStatusUpdated broadcast failed: '.$e->getMessage());
        }

        // When order is marked completed, automatically send receipt email if customer email exists
        if ($validated['status'] === 'completed' && $previousStatus !== 'completed') {
            $this->dispatchCustomerReceiptEmail($order);
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
     * Get ESC/POS formatted receipt text, HTML slip, and RawBT URL for thermal printing.
     */
    public function receiptText(Order $order, ThermalPrinterService $printerService)
    {
        $this->authorize('view', $order);

        $order->load(['items.product', 'location', 'vendor']);
        $settings = $order->vendor->getThermalPrinterSettings();

        $receiptText = $printerService->generateReceiptText($order);
        $receiptHtml = $printerService->generateReceiptHtml($order);
        $rawBtUrl = $printerService->getRawBtUrl($order);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'receipt_text' => $receiptText,
            'receipt_html' => $receiptHtml,
            'rawbt_url' => $rawBtUrl,
            'customer_email' => $order->customer_email,
            'network_printer_ip' => $settings['network_printer_ip'] ?? '',
            'network_printer_port' => $settings['network_printer_port'] ?? 9100,
            'network_printer_enabled' => ! empty($settings['network_printer_enabled']),
        ]);
    }

    /**
     * Print receipt directly to ESC/POS network thermal printer over TCP socket.
     */
    public function printNetwork(Request $request, Order $order, ThermalPrinterService $printerService)
    {
        $this->authorize('view', $order);

        $order->loadMissing(['items.product', 'location', 'vendor']);
        $settings = $order->vendor->getThermalPrinterSettings();

        $ip = trim((string) $request->input('ip', $settings['network_printer_ip'] ?? ''));
        $port = intval($request->input('port', $settings['network_printer_port'] ?? 9100)) ?: 9100;

        if (empty($ip)) {
            return response()->json([
                'success' => false,
                'message' => 'Ցանցային տպիչի IP հասցեն նշված չէ։ Խնդրում ենք լրացնել կարգավորումներում։',
            ], 422);
        }

        $result = $printerService->printToNetworkPrinter($ip, $port, $order);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Manually send order receipt email to customer.
     */
    public function sendEmailReceipt(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        $recipientEmail = trim((string) $request->input('email', $order->customer_email));

        if (empty($recipientEmail) || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'success' => false,
                'message' => 'Խնդրում ենք նշել վավեր էլ. փոստի հասցե։',
            ], 422);
        }

        $order->loadMissing(['items.product', 'vendor', 'location']);
        $settings = $order->vendor->getThermalPrinterSettings();
        $lang = $request->input('lang', $settings['email_receipt_language'] ?? 'bilingual');

        try {
            Mail::to($recipientEmail)->queue(new OrderReceiptMail($order, $lang));

            return response()->json([
                'success' => true,
                'message' => "Կտրոնը հաջողությամբ ուղարկվեց {$recipientEmail} հասցեին։",
            ]);
        } catch (\Throwable $e) {
            Log::error('Send receipt email failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Էլ. փոստն ուղարկելիս առաջացավ սխալ՝ '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Dispatch customer receipt email if customer provided email and feature is enabled.
     */
    protected function dispatchCustomerReceiptEmail(Order $order): void
    {
        try {
            if (empty($order->customer_email)) {
                return;
            }

            $order->loadMissing(['items.product', 'vendor', 'location']);
            $settings = $order->vendor->getThermalPrinterSettings();

            if (! ($settings['email_receipt_enabled'] ?? true)) {
                return;
            }

            $lang = $settings['email_receipt_language'] ?? 'bilingual';
            Mail::to($order->customer_email)->queue(new OrderReceiptMail($order, $lang));
        } catch (\Throwable $e) {
            Log::warning('Automatic customer receipt email dispatch failed: '.$e->getMessage());
        }
    }
}
