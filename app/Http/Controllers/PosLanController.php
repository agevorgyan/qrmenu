<?php

namespace App\Http\Controllers;

use App\Models\PosDevice;
use App\Models\PosLanSyncDispatch;
use App\Models\Vendor;
use App\Security\Permission;
use App\Services\PosLanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosLanController extends Controller
{
    public function __construct(
        protected PosLanService $lanService
    ) {}

    /**
     * Get active LAN peers and hub in the branch.
     */
    public function getPeers(Request $request): JsonResponse
    {
        $user = $request->user();
        $vendor = $user->vendor ?: Vendor::find($user->vendor_id);
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;

        $peersData = $this->lanService->getLanPeers($vendor, $locationId);

        return response()->json([
            'success' => true,
            'lan' => $peersData,
        ]);
    }

    /**
     * Announce device LAN presence and local IP/Port.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $request->validate([
            'lan_ip' => 'required|string|max:45',
            'lan_port' => 'nullable|integer|min:1024|max:65535',
            'role' => 'nullable|string|in:hub,satellite',
        ]);

        $user = $request->user();
        $vendor = $user->vendor ?: Vendor::find($user->vendor_id);
        $device = $this->resolveDevice($request, $vendor->id);

        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => 'No active POS device identified for LAN heartbeat.',
            ], 400);
        }

        $lanIp = $request->input('lan_ip');
        $lanPort = (int) $request->input('lan_port', 8080);
        $role = $request->input('role');

        $peer = $this->lanService->registerHeartbeat($vendor, $device, $lanIp, $lanPort, $role);

        return response()->json([
            'success' => true,
            'message' => 'LAN heartbeat recorded.',
            'peer' => $peer,
            'is_hub' => (bool) $device->fresh()->is_lan_hub,
        ]);
    }

    /**
     * Designate a device as the primary LAN Hub.
     */
    public function electHub(Request $request): JsonResponse
    {
        $user = $request->user();
        $vendor = $user->vendor ?: Vendor::find($user->vendor_id);

        if (! $user->hasPermission(Permission::POS_DEVICES) && ! in_array($user->role, ['vendor_owner', 'manager'], true)) {
            abort(403, 'Permission denied: Manager access required to elect LAN hub.');
        }

        $targetDeviceId = $request->input('device_id');
        if ($targetDeviceId) {
            $device = PosDevice::where('vendor_id', $vendor->id)->findOrFail($targetDeviceId);
        } else {
            $device = $this->resolveDevice($request, $vendor->id);
        }

        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => 'No target POS device found for hub election.',
            ], 400);
        }

        $electedHub = $this->lanService->electHub($vendor, $device, $user);

        return response()->json([
            'success' => true,
            'message' => "Device '{$electedHub->name}' elected as LAN Hub.",
            'hub' => $electedHub,
        ]);
    }

    /**
     * Dispatch a local event across the LAN to peers or hub.
     */
    public function dispatchEvent(Request $request): JsonResponse
    {
        $request->validate([
            'event_type' => 'required|string|in:order_created,item_status_updated,payment_recorded,table_updated,ticket_bumped',
            'payload' => 'required|array',
            'target_device_id' => 'nullable|integer',
        ]);

        $user = $request->user();
        $vendor = $user->vendor ?: Vendor::find($user->vendor_id);
        $device = $this->resolveDevice($request, $vendor->id);

        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => 'Source POS device required for LAN event dispatch.',
            ], 400);
        }

        $dispatch = $this->lanService->dispatchLanEvent(
            vendor: $vendor,
            sourceDevice: $device,
            eventType: $request->input('event_type'),
            payload: $request->input('payload'),
            targetDeviceId: $request->input('target_device_id')
        );

        return response()->json([
            'success' => true,
            'dispatch_id' => $dispatch->id,
            'status' => $dispatch->status,
        ]);
    }

    /**
     * Get pending dispatches for the current device.
     */
    public function getPendingDispatches(Request $request): JsonResponse
    {
        $user = $request->user();
        $vendor = $user->vendor ?: Vendor::find($user->vendor_id);
        $device = $this->resolveDevice($request, $vendor->id);

        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => 'Device required for dispatch retrieval.',
            ], 400);
        }

        $dispatches = $this->lanService->getPendingDispatches($vendor, $device);

        return response()->json([
            'success' => true,
            'dispatches' => $dispatches,
            'count' => $dispatches->count(),
        ]);
    }

    /**
     * Acknowledge a received LAN dispatch.
     */
    public function acknowledgeDispatch(Request $request, PosLanSyncDispatch $dispatch): JsonResponse
    {
        $user = $request->user();

        if ((int) $dispatch->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized access to this LAN dispatch.');
        }

        $dispatch->markAcknowledged();

        return response()->json([
            'success' => true,
            'message' => 'LAN dispatch acknowledged.',
        ]);
    }

    /**
     * Helper to resolve POS device from header, parameter, or cookie.
     */
    protected function resolveDevice(Request $request, int $vendorId): ?PosDevice
    {
        $deviceUuid = $request->header('X-POS-Device-UUID')
            ?: $request->input('device_uuid')
            ?: $request->cookie('pos_device_uuid');

        if (! empty($deviceUuid)) {
            return PosDevice::where('vendor_id', $vendorId)
                ->where('uuid', $deviceUuid)
                ->where('is_active', true)
                ->first();
        }

        return PosDevice::where('vendor_id', $vendorId)
            ->where('is_active', true)
            ->first();
    }
}
