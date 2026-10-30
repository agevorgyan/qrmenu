<?php

namespace App\Http\Controllers;

use App\Models\PosDevice;
use App\Models\PosSyncConflict;
use App\Models\Vendor;
use App\Services\PosOfflineSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosOfflineSyncController extends Controller
{
    public function __construct(
        protected PosOfflineSyncService $syncService
    ) {}

    /**
     * Get offline bootstrap manifest for local caching (IndexedDB).
     */
    public function manifest(Request $request): JsonResponse
    {
        $user = $request->user();
        $vendor = $user->vendor ?: Vendor::find($user->vendor_id);
        $device = $this->resolveDevice($request, $vendor->id);

        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => 'No active paired POS device identified for offline manifest.',
            ], 400);
        }

        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;
        $manifest = $this->syncService->getOfflineBootstrapManifest($vendor, $device, $user, $locationId);

        return response()->json([
            'success' => true,
            'manifest' => $manifest,
        ]);
    }

    /**
     * Synchronize a batch of operations recorded while offline.
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'batch_id' => 'required|string|max:100',
            'operations' => 'required|array',
            'operations.*.client_sync_id' => 'required|string|max:100',
            'operations.*.entity_type' => 'required|string|in:order,payment,table_status,cash_movement',
            'operations.*.action' => 'required|string|max:50',
            'operations.*.client_timestamp' => 'required|string',
            'operations.*.payload' => 'required|array',
            'client_started_at' => 'nullable|string',
            'client_ended_at' => 'nullable|string',
        ]);

        $user = $request->user();
        $vendor = $user->vendor ?: Vendor::find($user->vendor_id);
        $device = $this->resolveDevice($request, $vendor->id);

        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => 'Active POS device identification required for synchronization.',
            ], 400);
        }

        $result = $this->syncService->processSyncBatch($vendor, $device, $user, $request->all());

        return response()->json($result);
    }

    /**
     * List offline sync conflicts for manager resolution.
     */
    public function getConflicts(Request $request): JsonResponse
    {
        $user = $request->user();
        $vendor = $user->vendor ?: Vendor::find($user->vendor_id);

        $conflicts = PosSyncConflict::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('is_resolved', false)
            ->with(['posDevice', 'batch'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'conflicts' => $conflicts,
            'count' => $conflicts->count(),
        ]);
    }

    /**
     * Mark an offline sync conflict as resolved.
     */
    public function resolveConflict(Request $request, PosSyncConflict $conflict): JsonResponse
    {
        $request->validate([
            'resolution_notes' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        if ((int) $conflict->vendor_id !== (int) $user->vendor_id) {
            abort(403, 'Unauthorized access to this sync conflict.');
        }

        $conflict->markResolved($user, $request->input('resolution_notes', 'Resolved by manager'));

        return response()->json([
            'success' => true,
            'message' => 'Conflict resolved successfully.',
            'conflict' => $conflict->fresh(),
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

        // Fallback to first active registered device for vendor in testing/local dev
        return PosDevice::where('vendor_id', $vendorId)
            ->where('is_active', true)
            ->first();
    }
}
