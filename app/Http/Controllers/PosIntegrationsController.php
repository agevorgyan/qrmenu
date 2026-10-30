<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PosDevice;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Vendor;
use App\Models\WaiterCall;
use App\Services\Fiscal\PosFiscalService;
use App\Services\PosIntegrationsService;
use App\Services\PosInventoryService;
use App\Services\PosLoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosIntegrationsController extends Controller
{
    public function __construct(
        protected PosIntegrationsService $integrationsService,
        protected PosInventoryService $inventoryService,
        protected PosLoyaltyService $loyaltyService,
        protected PosFiscalService $fiscalService
    ) {}

    /**
     * Get incoming external orders (QR Menu, Web Online, Takeaway, Delivery).
     */
    public function incomingOrders(Request $request): JsonResponse
    {
        $user = $request->user();
        $vendor = $user->vendor ?: Vendor::find($user->vendor_id);
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;

        $orders = $this->integrationsService->getIncomingExternalOrders($vendor, $locationId);

        return response()->json([
            'success' => true,
            'count' => $orders->count(),
            'orders' => $orders,
        ]);
    }

    /**
     * Accept an online or takeaway/delivery order from the POS terminal.
     */
    public function acceptOrder(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        $prepTime = $request->input('prep_time_minutes') ? (int) $request->input('prep_time_minutes') : null;

        $updatedOrder = $this->integrationsService->acceptOnlineOrder($order, $user, $prepTime);

        return response()->json([
            'success' => true,
            'message' => 'Online order accepted and queued for kitchen.',
            'order' => $updatedOrder,
        ]);
    }

    /**
     * Reject an incoming online or takeaway order.
     */
    public function rejectOrder(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $user = $request->user();
        $reason = $request->input('reason');

        $updatedOrder = $this->integrationsService->rejectOnlineOrder($order, $user, $reason);

        return response()->json([
            'success' => true,
            'message' => 'Order rejected.',
            'order' => $updatedOrder,
        ]);
    }

    /**
     * Retrieve active table reservations for today.
     */
    public function reservations(Request $request): JsonResponse
    {
        $user = $request->user();
        $vendor = $user->vendor ?: Vendor::find($user->vendor_id);
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;
        $date = $request->query('date');

        $reservations = $this->integrationsService->getActiveReservations($vendor, $locationId, $date);

        return response()->json([
            'success' => true,
            'count' => $reservations->count(),
            'reservations' => $reservations,
        ]);
    }

    /**
     * Seat a reservation at a dining table.
     */
    public function seatReservation(Request $request, Reservation $reservation): JsonResponse
    {
        $request->validate([
            'table_id' => 'required|integer',
        ]);

        $user = $request->user();
        $tableId = (int) $request->input('table_id');
        $table = RestaurantTable::where('vendor_id', $user->vendor_id)->findOrFail($tableId);

        $result = $this->integrationsService->seatReservation($reservation, $table, $user);

        return response()->json([
            'success' => true,
            'message' => 'Reservation seated at table.',
            'reservation' => $result['reservation'],
            'table' => $result['table'],
            'order' => $result['order'],
        ]);
    }

    /**
     * Retrieve active waiter calls.
     */
    public function waiterCalls(Request $request): JsonResponse
    {
        $user = $request->user();
        $vendor = $user->vendor ?: Vendor::find($user->vendor_id);
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;

        $calls = $this->integrationsService->getActiveWaiterCalls($vendor, $locationId);

        return response()->json([
            'success' => true,
            'count' => $calls->count(),
            'waiter_calls' => $calls,
        ]);
    }

    /**
     * Resolve/dismiss a waiter call.
     */
    public function resolveWaiterCall(Request $request, WaiterCall $waiterCall): JsonResponse
    {
        $user = $request->user();
        $deviceUuid = $request->header('X-POS-Device-UUID');
        $device = $deviceUuid ? PosDevice::where('vendor_id', $user->vendor_id)->where('uuid', $deviceUuid)->first() : null;

        $resolved = $this->integrationsService->resolveWaiterCall($waiterCall, $user, $device);

        return response()->json([
            'success' => true,
            'message' => 'Waiter call resolved.',
            'waiter_call' => $resolved,
        ]);
    }

    /**
     * Search customers for order assignment and loyalty balance.
     */
    public function searchCustomers(Request $request): JsonResponse
    {
        $user = $request->user();
        $vendor = $user->vendor ?: Vendor::find($user->vendor_id);
        $query = $request->query('query', '');

        $customers = $this->integrationsService->searchCustomers($vendor, (string) $query);

        return response()->json([
            'success' => true,
            'customers' => $customers->map(fn (Customer $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'email' => $c->email,
                'loyalty_points' => (int) $c->loyalty_points,
                'loyalty_tier' => $c->loyalty_tier ?: 'bronze',
                'total_spent' => (float) $c->total_spent,
            ]),
        ]);
    }

    /**
     * Redeem customer loyalty points on an active order.
     */
    public function redeemLoyalty(Request $request, Customer $customer): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|integer',
            'points' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $order = Order::where('vendor_id', $user->vendor_id)->findOrFail((int) $request->input('order_id'));
        $points = (int) $request->input('points');

        $result = $this->loyaltyService->redeemPointsForOrder($order, $customer, $points);

        return response()->json([
            'success' => true,
            'message' => "Successfully redeemed {$points} loyalty points.",
            'result' => $result,
        ]);
    }

    /**
     * Initiate online QR payment (Idram, Telcell, etc.) on POS screen.
     */
    public function initiateQrPayment(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'gateway' => 'required|string|in:idram,telcell,fastshift,arca,stripe',
        ]);

        $user = $request->user();
        $gateway = $request->input('gateway');

        $result = $this->integrationsService->initiateQrPayment($order, $gateway, $user);

        return response()->json([
            'success' => true,
            'payment' => $result,
        ]);
    }

    /**
     * Manually trigger fiscal receipt printing for an order payment.
     */
    public function printFiscalReceipt(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        if ((int) $order->vendor_id !== (int) $user->vendor_id) {
            abort(403);
        }

        $payment = $order->posPayments()->latest()->first();
        if (! $payment) {
            return response()->json([
                'success' => false,
                'message' => 'No payment found on this order to fiscalize.',
            ], 400);
        }

        $result = $this->fiscalService->fiscalizePayment($order, $payment);

        return response()->json([
            'success' => $result->success,
            'fiscal' => $result->toArray(),
            'message' => $result->success ? 'Fiscal receipt printed.' : ($result->errorMessage ?: 'Fiscal receipt failed.'),
        ]);
    }

    /**
     * Get stock details and inventory movements for a product.
     */
    public function getInventory(Request $request, Product $product): JsonResponse
    {
        $user = $request->user();
        if ((int) $product->vendor_id !== (int) $user->vendor_id) {
            abort(403);
        }

        $movements = $product->inventoryMovements()->latest()->take(20)->get();

        return response()->json([
            'success' => true,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'tracks_inventory' => (bool) $product->tracks_inventory,
            'stock_quantity' => (float) $product->stock_quantity,
            'low_stock_threshold' => (int) $product->low_stock_threshold,
            'allow_out_of_stock_sale' => (bool) $product->allow_out_of_stock_sale,
            'movements' => $movements,
        ]);
    }

    /**
     * Manually adjust stock from POS.
     */
    public function adjustInventory(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'quantity_change' => 'required|numeric',
            'type' => 'nullable|string|in:restock,waste,adjustment',
            'variation_id' => 'nullable|integer',
            'reason' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $vendor = $user->vendor ?: Vendor::find($user->vendor_id);
        $qty = (float) $request->input('quantity_change');
        $type = $request->input('type', 'adjustment');
        $reason = $request->input('reason');
        $variation = $request->input('variation_id') ? ProductVariation::find($request->input('variation_id')) : null;

        $movement = $this->inventoryService->adjustStock($vendor, $product, $qty, $type, $variation, $user, $reason);

        return response()->json([
            'success' => true,
            'message' => 'Stock updated.',
            'movement' => $movement,
            'current_stock' => $movement->resulting_quantity,
        ]);
    }
}
