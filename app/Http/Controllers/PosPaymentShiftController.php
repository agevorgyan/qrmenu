<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PosDevice;
use App\Models\PosPayment;
use App\Models\PosShift;
use App\Services\PosPaymentShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PosPaymentShiftController extends Controller
{
    public function __construct(
        protected PosPaymentShiftService $paymentShiftService,
    ) {}

    /**
     * Get the active cash drawer shift.
     */
    public function getCurrentShift(Request $request): JsonResponse
    {
        $user = Auth::user();
        $device = $this->resolveDevice($request, $user->vendor_id);
        $locationId = $request->filled('location_id') ? (int) $request->get('location_id') : $user->location_id;

        $shift = $this->paymentShiftService->getCurrentShift($user->vendor, $locationId, $device, $user);

        return response()->json([
            'success' => true,
            'shift' => $shift?->generateReportPayload(),
        ]);
    }

    /**
     * Open a new cash drawer shift.
     */
    public function openShift(Request $request): JsonResponse
    {
        $user = Auth::user();
        $device = $this->resolveDevice($request, $user->vendor_id);

        $validated = $request->validate([
            'opening_float' => 'required|numeric|min:0',
            'opening_notes' => 'nullable|string|max:500',
            'location_id' => 'nullable|integer',
        ]);

        $shift = $this->paymentShiftService->openShift(
            vendor: $user->vendor,
            data: $validated,
            user: $user,
            device: $device
        );

        return response()->json([
            'success' => true,
            'message' => 'Cash shift opened successfully.',
            'shift' => $shift->generateReportPayload(),
        ], 201);
    }

    /**
     * Record a cash movement (cash-in, cash-out, safe drop, expense) during shift.
     */
    public function recordCashMovement(Request $request): JsonResponse
    {
        $user = Auth::user();
        $device = $this->resolveDevice($request, $user->vendor_id);

        $validated = $request->validate([
            'shift_id' => 'required|integer',
            'type' => 'required|string|in:cash_in,cash_out,safe_drop,expense',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $shift = PosShift::where('vendor_id', $user->vendor_id)->findOrFail($validated['shift_id']);

        $movement = $this->paymentShiftService->recordCashMovement(
            shift: $shift,
            data: $validated,
            user: $user,
            device: $device
        );

        return response()->json([
            'success' => true,
            'message' => 'Cash movement recorded successfully.',
            'movement' => $movement,
            'shift' => $shift->fresh()->generateReportPayload(),
        ]);
    }

    /**
     * Close the active cash drawer shift and generate Z-Report.
     */
    public function closeShift(Request $request, PosShift $shift): JsonResponse
    {
        $user = Auth::user();
        $device = $this->resolveDevice($request, $user->vendor_id);

        if ((int) $shift->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized shift closing.');
        }

        $validated = $request->validate([
            'counted_cash' => 'required|numeric|min:0',
            'closing_notes' => 'nullable|string|max:500',
        ]);

        $closed = $this->paymentShiftService->closeShift(
            shift: $shift,
            data: $validated,
            user: $user,
            device: $device
        );

        return response()->json([
            'success' => true,
            'message' => 'Cash shift closed successfully.',
            'shift' => $closed->generateReportPayload(),
        ]);
    }

    /**
     * Get X-Report or Z-Report summary for a shift.
     */
    public function getShiftReport(Request $request, PosShift $shift): JsonResponse
    {
        $user = Auth::user();

        if ((int) $shift->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized shift access.');
        }

        return response()->json([
            'success' => true,
            'report' => $shift->generateReportPayload(),
        ]);
    }

    /**
     * Settle payment for an order (Cash, Card, Mixed, Partial).
     */
    public function processPayment(Request $request, Order $order): JsonResponse
    {
        $user = Auth::user();
        $device = $this->resolveDevice($request, $user->vendor_id);

        if ((int) $order->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized order payment.');
        }

        $validated = $request->validate([
            'payment_method' => 'required|string|in:cash,card,mixed,split,external',
            'amount' => 'nullable|numeric|min:0',
            'cash_tendered' => 'nullable|numeric|min:0',
            'tip_amount' => 'nullable|numeric|min:0',
            'transaction_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
            'split_payments' => 'nullable|array',
            'split_payments.*.payment_method' => 'required_with:split_payments|string|in:cash,card,external',
            'split_payments.*.amount' => 'required_with:split_payments|numeric|min:0.01',
            'split_payments.*.cash_tendered' => 'nullable|numeric|min:0',
            'split_payments.*.transaction_reference' => 'nullable|string|max:100',
            'split_payments.*.tip_amount' => 'nullable|numeric|min:0',
        ]);

        $result = $this->paymentShiftService->processOrderPayment(
            order: $order,
            paymentData: $validated,
            cashier: $user,
            device: $device
        );

        return response()->json([
            'success' => true,
            'message' => 'Payment processed successfully.',
            'order' => $result['order'],
            'payments' => $result['payments'],
            'change_due' => $result['change_due'],
        ]);
    }

    /**
     * Refund or void a completed payment.
     */
    public function refundPayment(Request $request, PosPayment $payment): JsonResponse
    {
        $user = Auth::user();
        $device = $this->resolveDevice($request, $user->vendor_id);

        if ((int) $payment->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized payment refund.');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
        ]);

        $refunded = $this->paymentShiftService->refundPayment(
            payment: $payment,
            amount: (float) $validated['amount'],
            reason: $validated['reason'] ?? null,
            user: $user,
            device: $device
        );

        return response()->json([
            'success' => true,
            'message' => 'Payment refunded successfully.',
            'payment' => $refunded,
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
