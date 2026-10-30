<?php

namespace App\Http\Controllers;

use App\Models\PosDevice;
use App\Models\PosDeviceSession;
use App\Security\Permission;
use App\Services\PosDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PosDeviceController extends Controller
{
    public function __construct(
        protected PosDeviceService $posDeviceService,
    ) {}

    /**
     * List registered POS devices for current vendor.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $locationId = $request->filled('location_id') ? (int) $request->get('location_id') : null;
        $devices = $this->posDeviceService->getDevices($vendor, $locationId);

        return response()->json([
            'success' => true,
            'devices' => $devices,
        ]);
    }

    /**
     * Register a new POS device.
     */
    public function register(Request $request): JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        if (! $user->hasPermission(Permission::POS_DEVICES) && ! $user->hasPermission(Permission::POS_SETTINGS)) {
            abort(403, 'Unauthorized to register POS devices.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'device_type' => 'nullable|string|in:terminal,tablet,mobile,kds,gateway',
            'location_id' => 'nullable|integer',
            'hardware_identifier' => 'nullable|string|max:255',
        ]);

        $device = $this->posDeviceService->registerDevice($vendor, $validated, $user);

        return response()->json([
            'success' => true,
            'message' => 'POS device registered successfully.',
            'device' => $device,
        ], 201);
    }

    /**
     * Pair a physical hardware device using its pairing code.
     */
    public function pair(Request $request): JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $validated = $request->validate([
            'pairing_code' => 'required|string|max:32',
            'hardware_identifier' => 'nullable|string|max:255',
        ]);

        $device = $this->posDeviceService->pairDevice(
            vendor: $vendor,
            pairingCode: $validated['pairing_code'],
            hardwareIdentifier: $validated['hardware_identifier'] ?? null,
            ipAddress: $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' => 'POS device paired successfully.',
            'device' => $device,
        ]);
    }

    /**
     * Regenerate device pairing code.
     */
    public function regenerateCode(Request $request, PosDevice $device): JsonResponse
    {
        $user = Auth::user();

        if ((int) $device->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized device access.');
        }

        if (! $user->hasPermission(Permission::POS_DEVICES) && ! $user->hasPermission(Permission::POS_SETTINGS)) {
            abort(403, 'Unauthorized to manage POS devices.');
        }

        $newCode = $this->posDeviceService->regeneratePairingCode($device, $user);

        return response()->json([
            'success' => true,
            'message' => 'Pairing code regenerated.',
            'pairing_code' => $newCode,
        ]);
    }

    /**
     * Toggle device active status.
     */
    public function toggle(Request $request, PosDevice $device): JsonResponse
    {
        $user = Auth::user();

        if ((int) $device->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized device access.');
        }

        if (! $user->hasPermission(Permission::POS_DEVICES)) {
            abort(403, 'Unauthorized to manage POS devices.');
        }

        $device->is_active = ! $device->is_active;
        $device->save();

        if (! $device->is_active) {
            $this->posDeviceService->revokeDevice($device, $user);
        }

        return response()->json([
            'success' => true,
            'message' => 'POS device status updated.',
            'is_active' => $device->is_active,
        ]);
    }

    /**
     * Revoke / delete a POS device.
     */
    public function destroy(Request $request, PosDevice $device): JsonResponse
    {
        $user = Auth::user();

        if ((int) $device->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized device access.');
        }

        if (! $user->hasPermission(Permission::POS_DEVICES)) {
            abort(403, 'Unauthorized to revoke POS devices.');
        }

        $this->posDeviceService->revokeDevice($device, $user);
        $device->delete();

        return response()->json([
            'success' => true,
            'message' => 'POS device revoked successfully.',
        ]);
    }

    /**
     * Touch device heartbeat and telemetry.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $validated = $request->validate([
            'device_uuid' => 'required|string',
            'app_version' => 'nullable|string|max:30',
            'synced' => 'nullable|boolean',
        ]);

        $device = PosDevice::where('vendor_id', $vendor->id)
            ->where('uuid', $validated['device_uuid'])
            ->first();

        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not recognized.',
            ], 404);
        }

        $device->touchHeartbeat(
            ipAddress: $request->ip(),
            appVersion: $validated['app_version'] ?? null,
            synced: (bool) ($validated['synced'] ?? false)
        );

        return response()->json([
            'success' => true,
            'is_active' => $device->is_active,
            'is_paired' => $device->is_paired,
            'last_sync_at' => $device->last_sync_at?->toIso8601String(),
        ]);
    }

    /**
     * Start a new employee session on the device.
     */
    public function createSession(Request $request, PosDevice $device): JsonResponse
    {
        $user = Auth::user();

        if ((int) $device->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized device access.');
        }

        $validated = $request->validate([
            'mode' => 'nullable|string|in:waiter,cashier,manager,kds',
        ]);

        $session = $this->posDeviceService->createSession(
            device: $device,
            user: $user,
            mode: $validated['mode'] ?? 'waiter',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'session' => $session,
        ], 201);
    }

    /**
     * Lock an active session.
     */
    public function lockSession(Request $request, PosDeviceSession $session): JsonResponse
    {
        $user = Auth::user();

        if ((int) $session->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized session access.');
        }

        $this->posDeviceService->lockSession($session);

        return response()->json([
            'success' => true,
            'message' => 'Session locked.',
        ]);
    }

    /**
     * Unlock a locked session.
     */
    public function unlockSession(Request $request, PosDeviceSession $session): JsonResponse
    {
        $user = Auth::user();

        if ((int) $session->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized session access.');
        }

        $validated = $request->validate([
            'pin' => 'required|string',
        ]);

        $unlocked = $this->posDeviceService->unlockSession($session, $validated['pin']);

        if (! $unlocked) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid PIN or credentials.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Session unlocked successfully.',
            'session' => $session->fresh(),
        ]);
    }

    /**
     * Terminate an active session.
     */
    public function terminateSession(Request $request, PosDeviceSession $session): JsonResponse
    {
        $user = Auth::user();

        if ((int) $session->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized session access.');
        }

        $this->posDeviceService->terminateSession($session);

        return response()->json([
            'success' => true,
            'message' => 'Session terminated.',
        ]);
    }
}
