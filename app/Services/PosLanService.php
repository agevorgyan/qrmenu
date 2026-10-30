<?php

namespace App\Services;

use App\Models\Location;
use App\Models\PosDevice;
use App\Models\PosLanPeer;
use App\Models\PosLanSyncDispatch;
use App\Models\PosOperationLog;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosLanService
{
    /**
     * Get active LAN peers and designated hub for location.
     */
    public function getLanPeers(Vendor $vendor, ?int $locationId = null): array
    {
        // Prevent cross-tenant parameter poisoning: ensure location belongs to this vendor
        if ($locationId && ! $vendor->locations()->where('id', $locationId)->exists()) {
            $locationId = null;
        }

        $locationId = $locationId ?: $vendor->locations()->first()?->id;

        // Auto elect hub if none exists or previous hub has timed out
        $hubQuery = PosDevice::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->where('is_lan_hub', true);

        if ($locationId) {
            $hubQuery->where('location_id', $locationId);
        }

        $currentHub = $hubQuery->first();

        // Stale hub timeout check (10 minutes)
        if ($currentHub && $currentHub->last_lan_heartbeat_at && $currentHub->last_lan_heartbeat_at->lt(Carbon::now()->subMinutes(10))) {
            $currentHub->stepDownHub();
            $currentHub = $this->autoElectHubIfNone($vendor, $locationId);
        } elseif (! $currentHub) {
            $currentHub = $this->autoElectHubIfNone($vendor, $locationId);
        }

        $devicesQuery = PosDevice::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('is_active', true);

        if ($locationId) {
            $devicesQuery->where('location_id', $locationId);
        }

        $devices = $devicesQuery->get();

        $peers = [];
        $cutoff = Carbon::now()->subMinutes(5);

        foreach ($devices as $d) {
            $isHub = (bool) $d->is_lan_hub;
            $isActive = $d->last_lan_heartbeat_at && $d->last_lan_heartbeat_at->gt($cutoff);

            $peers[] = [
                'device_id' => $d->id,
                'device_uuid' => $d->uuid,
                'device_name' => $d->name,
                'device_type' => $d->device_type,
                'role' => $isHub ? 'hub' : 'satellite',
                'is_hub' => $isHub,
                'ip_address' => $d->lan_ip_address ?: $d->ip_address,
                'port' => $d->lan_port ?: 8080,
                'status' => $isActive ? 'active' : 'offline',
                'last_seen_at' => $d->last_lan_heartbeat_at?->toISOString(),
            ];
        }

        return [
            'vendor_id' => $vendor->id,
            'location_id' => $locationId,
            'hub' => $currentHub ? [
                'device_id' => $currentHub->id,
                'device_uuid' => $currentHub->uuid,
                'device_name' => $currentHub->name,
                'ip_address' => $currentHub->lan_ip_address,
                'port' => $currentHub->lan_port ?: 8080,
                'auth_token' => $currentHub->lan_auth_token,
            ] : null,
            'peers' => $peers,
            'total_peers' => count($peers),
            'timestamp' => Carbon::now()->toISOString(),
        ];
    }

    /**
     * Register or update a device's LAN heartbeat presence on the subnet.
     */
    public function registerHeartbeat(Vendor $vendor, PosDevice $device, string $lanIp, int $lanPort = 8080, ?string $role = null): PosLanPeer
    {
        if ((int) $device->vendor_id !== (int) $vendor->id || ! $device->is_active) {
            throw ValidationException::withMessages([
                'device' => 'Device unauthorized or deactivated.',
            ]);
        }

        return DB::transaction(function () use ($vendor, $device, $lanIp, $lanPort, $role) {
            $device->update([
                'lan_ip_address' => $lanIp,
                'lan_port' => $lanPort,
                'last_lan_heartbeat_at' => Carbon::now(),
            ]);

            if ($role === 'hub') {
                $device->markAsHub($lanIp, $lanPort);
            }

            $effectiveRole = $device->is_lan_hub ? 'hub' : 'satellite';

            $peer = PosLanPeer::withoutGlobalScopes()->where('pos_device_id', $device->id)->first();
            if (! $peer) {
                $peer = PosLanPeer::create([
                    'uuid' => (string) Str::uuid(),
                    'vendor_id' => $vendor->id,
                    'location_id' => $device->location_id,
                    'pos_device_id' => $device->id,
                    'device_name' => $device->name,
                    'device_type' => $device->device_type ?: 'tablet',
                    'role' => $effectiveRole,
                    'ip_address' => $lanIp,
                    'port' => $lanPort,
                    'status' => 'active',
                    'last_seen_at' => Carbon::now(),
                ]);
            } else {
                $peer->touchPeer($lanIp, $lanPort, $effectiveRole);
            }

            return $peer->fresh();
        });
    }

    /**
     * Elect a device as the primary LAN Hub for the branch.
     */
    public function electHub(Vendor $vendor, PosDevice $device, ?User $user = null): PosDevice
    {
        if ((int) $device->vendor_id !== (int) $vendor->id || ! $device->is_active) {
            throw ValidationException::withMessages([
                'device' => 'Device unauthorized or deactivated.',
            ]);
        }

        return DB::transaction(function () use ($vendor, $device, $user) {
            $device->markAsHub();

            // Update peer roles
            PosLanPeer::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->where('location_id', $device->location_id)
                ->where('pos_device_id', '!=', $device->id)
                ->update(['role' => 'satellite']);

            PosLanPeer::withoutGlobalScopes()
                ->where('pos_device_id', $device->id)
                ->update(['role' => 'hub', 'status' => 'active']);

            PosOperationLog::log(
                vendor: $vendor,
                action: 'lan_hub_elected',
                user: $user,
                device: $device,
                details: [
                    'device_name' => $device->name,
                    'lan_ip' => $device->lan_ip_address,
                    'lan_port' => $device->lan_port,
                ]
            );

            return $device->fresh();
        });
    }

    /**
     * Automatically elect a hub if none is currently active.
     */
    public function autoElectHubIfNone(Vendor $vendor, ?int $locationId = null): ?PosDevice
    {
        if ($locationId && ! $vendor->locations()->where('id', $locationId)->exists()) {
            $locationId = null;
        }

        $cutoff = Carbon::now()->subMinutes(10);

        // First attempt to elect an active device that heartbeated recently
        $candidateQuery = PosDevice::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->where('last_lan_heartbeat_at', '>=', $cutoff);

        if ($locationId) {
            $candidateQuery->where('location_id', $locationId);
        }

        $candidate = $candidateQuery
            ->orderByRaw("CASE WHEN device_type = 'desktop' THEN 1 ELSE 2 END")
            ->orderByDesc('lan_sync_priority')
            ->first();

        // Fallback to any active device if no recent heartbeat
        if (! $candidate) {
            $fallbackQuery = PosDevice::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->where('is_active', true);

            if ($locationId) {
                $fallbackQuery->where('location_id', $locationId);
            }

            $candidate = $fallbackQuery
                ->orderByRaw("CASE WHEN device_type = 'desktop' THEN 1 ELSE 2 END")
                ->orderByDesc('lan_sync_priority')
                ->first();
        }

        if ($candidate) {
            $candidate->markAsHub();

            return $candidate->fresh();
        }

        return null;
    }

    /**
     * Route a local peer-to-peer LAN event dispatch.
     */
    public function dispatchLanEvent(
        Vendor $vendor,
        PosDevice $sourceDevice,
        string $eventType,
        array $payload,
        ?int $targetDeviceId = null
    ): PosLanSyncDispatch {
        $allowedEvents = [
            'order_created',
            'item_status_updated',
            'payment_recorded',
            'table_updated',
            'ticket_bumped',
        ];

        if (! in_array($eventType, $allowedEvents, true)) {
            throw ValidationException::withMessages([
                'event_type' => "Unsupported LAN event type '{$eventType}'.",
            ]);
        }

        return PosLanSyncDispatch::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $vendor->id,
            'location_id' => $sourceDevice->location_id,
            'source_device_id' => $sourceDevice->id,
            'target_device_id' => $targetDeviceId,
            'event_type' => $eventType,
            'payload' => $payload,
            'status' => 'queued',
            'dispatched_at' => Carbon::now(),
        ]);
    }

    /**
     * Retrieve pending LAN dispatches for a device.
     *
     * @return Collection<int, PosLanSyncDispatch>
     */
    public function getPendingDispatches(Vendor $vendor, PosDevice $device): Collection
    {
        return PosLanSyncDispatch::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('location_id', $device->location_id)
            ->where(function ($q) use ($device) {
                $q->where('target_device_id', $device->id)
                    ->orWhereNull('target_device_id');
            })
            ->where('source_device_id', '!=', $device->id)
            ->where('status', 'queued')
            ->orderBy('id', 'asc')
            ->get();
    }
}
