<?php

namespace App\Services;

use App\Models\Location;
use App\Models\PosDevice;
use App\Models\PosDeviceSession;
use App\Models\PosOperationLog;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosDeviceService
{
    /**
     * Register a new POS hardware device for a vendor branch.
     *
     * @param  array{name: string, device_type?: string, location_id?: int|null, hardware_identifier?: string|null}  $data
     */
    public function registerDevice(Vendor $vendor, array $data, ?User $registeredBy = null): PosDevice
    {
        $locationId = ! empty($data['location_id']) ? (int) $data['location_id'] : null;

        if ($locationId) {
            $location = Location::where('vendor_id', $vendor->id)->find($locationId);
            if (! $location) {
                throw ValidationException::withMessages([
                    'location_id' => 'The selected location does not belong to this vendor.',
                ]);
            }
        }

        $device = PosDevice::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $vendor->id,
            'location_id' => $locationId,
            'name' => trim($data['name']),
            'device_type' => $data['device_type'] ?? 'terminal',
            'pairing_code' => PosDevice::generatePairingCode(),
            'hardware_identifier' => $data['hardware_identifier'] ?? null,
            'ip_address' => request()?->ip(),
            'is_active' => true,
            'is_paired' => false,
            'registered_by_user_id' => $registeredBy?->id,
            'metadata' => [
                'registered_at' => now()->toIso8601String(),
                'platform' => request()?->header('User-Agent'),
            ],
        ]);

        PosOperationLog::log(
            vendor: $vendor,
            action: 'device_register',
            user: $registeredBy,
            device: $device,
            entity: $device,
            details: ['name' => $device->name, 'type' => $device->device_type]
        );

        return $device;
    }

    /**
     * Pair a physical device using its pairing code.
     */
    public function pairDevice(Vendor $vendor, string $pairingCode, ?string $hardwareIdentifier = null, ?string $ipAddress = null): PosDevice
    {
        $code = strtoupper(trim($pairingCode));

        $device = PosDevice::where('vendor_id', $vendor->id)
            ->where('pairing_code', $code)
            ->where('is_active', true)
            ->first();

        if (! $device) {
            throw ValidationException::withMessages([
                'pairing_code' => 'Invalid or expired POS device pairing code.',
            ]);
        }

        $device->update([
            'is_paired' => true,
            'paired_at' => Carbon::now(),
            'hardware_identifier' => $hardwareIdentifier ?? $device->hardware_identifier,
            'ip_address' => $ipAddress ?? request()?->ip(),
            'last_seen_at' => Carbon::now(),
        ]);

        PosOperationLog::log(
            vendor: $vendor,
            action: 'device_pair',
            device: $device,
            entity: $device,
            details: ['paired_at' => now()->toIso8601String()]
        );

        return $device;
    }

    /**
     * Create an authenticated employee session on a POS device.
     */
    public function createSession(PosDevice $device, User $user, string $mode = 'waiter', ?string $ipAddress = null, ?string $userAgent = null): PosDeviceSession
    {
        if (! $device->is_active) {
            throw ValidationException::withMessages([
                'device' => 'This POS device has been deactivated or revoked.',
            ]);
        }

        // Enforce vendor tenant boundary
        if ((int) $device->vendor_id !== (int) $user->vendor_id) {
            throw ValidationException::withMessages([
                'user' => 'User is not authorized for this device tenant.',
            ]);
        }

        // Deactivate previous active sessions for this device
        PosDeviceSession::where('device_id', $device->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $session = PosDeviceSession::create([
            'device_id' => $device->id,
            'user_id' => $user->id,
            'vendor_id' => $device->vendor_id,
            'location_id' => $device->location_id ?? $user->location_id,
            'session_token' => Str::random(64),
            'mode' => in_array($mode, ['waiter', 'cashier', 'manager', 'kds'], true) ? $mode : 'waiter',
            'ip_address' => $ipAddress ?? request()?->ip(),
            'user_agent' => $userAgent ?? request()?->userAgent(),
            'is_active' => true,
            'locked_at' => null,
            'expires_at' => Carbon::now()->addHours(12),
            'last_activity_at' => Carbon::now(),
        ]);

        $device->touchHeartbeat($ipAddress, null, false);

        PosOperationLog::log(
            vendor: $device->vendor,
            action: 'session_login',
            user: $user,
            device: $device,
            entity: $session,
            details: ['mode' => $session->mode]
        );

        return $session;
    }

    /**
     * Lock an active session on the POS device.
     */
    public function lockSession(PosDeviceSession $session): void
    {
        $session->lock();

        PosOperationLog::log(
            vendor: $session->vendor,
            action: 'session_lock',
            user: $session->user,
            device: $session->device,
            entity: $session
        );
    }

    /**
     * Unlock a locked POS session using employee PIN or password.
     */
    public function unlockSession(PosDeviceSession $session, string $pinOrPassword): bool
    {
        $user = $session->user;

        $isValid = false;

        // Check PIN first if set
        if ($user->hasPin() && $user->verifyPin($pinOrPassword)) {
            $isValid = true;
        } elseif (Hash::check($pinOrPassword, $user->password)) {
            $isValid = true;
        }

        if (! $isValid) {
            return false;
        }

        $session->unlock();

        PosOperationLog::log(
            vendor: $session->vendor,
            action: 'session_unlock',
            user: $user,
            device: $session->device,
            entity: $session
        );

        return true;
    }

    /**
     * End an active session.
     */
    public function terminateSession(PosDeviceSession $session): void
    {
        $session->update(['is_active' => false]);

        PosOperationLog::log(
            vendor: $session->vendor,
            action: 'session_logout',
            user: $session->user,
            device: $session->device,
            entity: $session
        );
    }

    /**
     * Revoke or deactivate a device.
     */
    public function revokeDevice(PosDevice $device, ?User $revokedBy = null): void
    {
        $device->update(['is_active' => false]);

        PosDeviceSession::where('device_id', $device->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        PosOperationLog::log(
            vendor: $device->vendor,
            action: 'device_revoke',
            user: $revokedBy,
            device: $device,
            entity: $device
        );
    }

    /**
     * Get all registered devices for a vendor.
     */
    public function getDevices(Vendor $vendor, ?int $locationId = null): Collection
    {
        return PosDevice::where('vendor_id', $vendor->id)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->with(['location', 'registeredBy'])
            ->latest()
            ->get();
    }
}
