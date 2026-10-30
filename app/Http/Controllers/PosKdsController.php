<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosDevice;
use App\Models\User;
use App\Models\Vendor;
use App\Security\Permission;
use App\Services\KdsService;
use App\Services\ReceiptPrinterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PosKdsController extends Controller
{
    public function __construct(
        protected KdsService $kdsService,
        protected ReceiptPrinterService $printerService
    ) {}

    /**
     * Authorize user access to KDS.
     */
    protected function authorizeKds(Request $request): User
    {
        $user = $request->user();

        if (! $user->hasPermission(Permission::POS_KDS) && ! in_array($user->role, ['vendor_owner', 'manager', 'chef', 'kitchen'], true)) {
            abort(403, 'Unauthorized access to Kitchen Display System.');
        }

        return $user;
    }

    /**
     * Render the fullscreen KDS view.
     */
    public function index(Request $request): View
    {
        $user = $this->authorizeKds($request);
        $vendor = $user->vendor ?: Vendor::find($user->vendor_id);
        $stations = $this->kdsService->getStations($vendor);
        $device = $this->resolveDevice($request, $vendor->id);

        return view('pos.kds', [
            'user' => $user,
            'vendor' => $vendor,
            'stations' => $stations,
            'device' => $device,
        ]);
    }

    /**
     * Get active kitchen tickets API.
     */
    public function getTickets(Request $request): JsonResponse
    {
        $user = $this->authorizeKds($request);
        $vendor = $user->vendor ?: Vendor::find($user->vendor_id);
        $station = $request->query('station', 'all');
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;

        $tickets = $this->kdsService->getActiveTickets($vendor, $locationId, $station);

        return response()->json([
            'success' => true,
            'tickets' => $tickets,
            'total_count' => count($tickets),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get configured kitchen stations.
     */
    public function getStations(Request $request): JsonResponse
    {
        $user = $this->authorizeKds($request);
        $vendor = $user->vendor ?: Vendor::find($user->vendor_id);
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;

        $stations = $this->kdsService->getStations($vendor, $locationId);

        return response()->json([
            'success' => true,
            'stations' => $stations,
        ]);
    }

    /**
     * Update preparation status for an individual order item.
     */
    public function updateItemStatus(Request $request, OrderItem $item): JsonResponse
    {
        $user = $this->authorizeKds($request);

        $request->validate([
            'status' => 'required|string|in:pending,preparing,ready,served,cancelled',
        ]);

        $order = $item->order ?: Order::withoutGlobalScopes()->findOrFail($item->order_id);

        if ((int) $order->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized access to this order item.');
        }

        $device = $this->resolveDevice($request, (int) $order->vendor_id);
        $result = $this->kdsService->updateItemStatus($item, $request->input('status'), $user, $device);

        return response()->json([
            'success' => true,
            'message' => "Item status updated to {$request->input('status')}.",
            'item' => $result['item'],
            'order_status' => $result['order_status'],
        ]);
    }

    /**
     * Bump ticket (bulk update items to ready or served).
     */
    public function bumpOrder(Request $request, Order $order): JsonResponse
    {
        $user = $this->authorizeKds($request);

        $request->validate([
            'status' => 'required|string|in:ready,served,preparing',
            'station' => 'nullable|string|max:50',
        ]);

        if ((int) $order->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized access to this order.');
        }

        $device = $this->resolveDevice($request, (int) $order->vendor_id);
        $targetStatus = $request->input('status', 'ready');
        $station = $request->input('station');

        $updatedOrder = $this->kdsService->bumpOrder($order, $targetStatus, $user, $device, $station);

        return response()->json([
            'success' => true,
            'message' => "Order bumped to {$targetStatus}.",
            'order' => $updatedOrder,
            'order_status' => $updatedOrder->status,
        ]);
    }

    /**
     * Print kitchen ticket for order.
     */
    public function printKitchenTicket(Request $request, Order $order): JsonResponse
    {
        $user = $this->authorizeKds($request);

        $request->validate([
            'station' => 'nullable|string|max:50',
        ]);

        if ((int) $order->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized access to this order.');
        }

        $device = $this->resolveDevice($request, (int) $order->vendor_id);
        $station = $request->input('station', 'kitchen');

        $result = $this->kdsService->printKitchenTicket($order, $station, $device);

        return response()->json([
            'success' => true,
            'message' => 'Kitchen ticket spooled successfully.',
            'print_job' => $result['print_job'],
            'ticket_text' => $result['ticket_text'],
        ]);
    }

    /**
     * Print or preview customer receipt.
     */
    public function printCustomerReceipt(Request $request, Order $order): JsonResponse|Response
    {
        $user = $request->user();

        if ((int) $order->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized access to this order.');
        }

        $vendor = $order->vendor ?: Vendor::find($order->vendor_id);
        $device = $this->resolveDevice($request, (int) $order->vendor_id);

        if ($request->query('format') === 'html') {
            return response($this->printerService->buildHtmlReceipt($order, null, $vendor));
        }

        $escpos = $this->printerService->buildCustomerReceiptEscpos($order, null, $vendor);
        $text = $this->printerService->buildCustomerReceiptText($order, null, $vendor);

        $printJob = $this->printerService->queuePrintJob(
            vendor: $vendor,
            ticketType: 'receipt',
            escpos: $escpos,
            text: $text,
            order: $order,
            device: $device,
            title: 'Customer Receipt #'.$order->order_number
        );

        return response()->json([
            'success' => true,
            'message' => 'Customer receipt spooled successfully.',
            'print_job' => $printJob,
            'receipt_text' => $text,
        ]);
    }

    /**
     * Helper to resolve POS device from header or cookie.
     */
    protected function resolveDevice(Request $request, int $vendorId): ?PosDevice
    {
        $deviceUuid = $request->header('X-POS-Device-UUID', $request->cookie('pos_device_uuid'));
        if (! empty($deviceUuid)) {
            return PosDevice::where('vendor_id', $vendorId)
                ->where('uuid', $deviceUuid)
                ->where('is_active', true)
                ->first();
        }

        return null;
    }
}
