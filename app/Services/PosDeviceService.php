<?php

namespace App\Services;

use App\Models\Location;
use App\Models\PosDevice;
use App\Models\PosDeviceSession;
use App\Models\PosOperationLog;
use App\Models\User;
use App\Models\Vendor;
use App\Security\Permission;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
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
     * Regenerate the device pairing code.
     */
    public function regeneratePairingCode(PosDevice $device, ?User $actor = null): string
    {
        $newCode = PosDevice::generatePairingCode();
        $device->update([
            'pairing_code' => $newCode,
            'is_paired' => false,
            'paired_at' => null,
        ]);

        PosOperationLog::log(
            vendor: $device->vendor,
            action: 'device_regenerate_code',
            user: $actor,
            device: $device,
            entity: $device,
            details: ['new_code' => $newCode]
        );

        return $newCode;
    }

    /**
     * Authenticate an employee using username/email and password.
     */
    public function loginWithCredentials(string $email, string $password, ?string $deviceUuid = null, string $mode = 'waiter'): array
    {
        $user = User::where('email', strtolower(trim($email)))->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => __('This user account is inactive.'),
            ]);
        }

        if (! $user->hasPermission(Permission::POS_ACCESS)) {
            throw ValidationException::withMessages([
                'email' => __('Your role is not authorized to access the POS terminal.'),
            ]);
        }

        $device = null;
        if (! empty($deviceUuid)) {
            $device = PosDevice::where('uuid', $deviceUuid)->first();
            if ($device && (int) $device->vendor_id !== (int) $user->vendor_id) {
                throw ValidationException::withMessages([
                    'device' => __('This device belongs to a different restaurant account.'),
                ]);
            }
        }

        // If no device supplied, look for or create a default terminal record
        if (! $device && $user->vendor) {
            $device = PosDevice::firstOrCreate(
                ['vendor_id' => $user->vendor_id, 'name' => 'Default POS Terminal'],
                [
                    'uuid' => (string) Str::uuid(),
                    'location_id' => $user->location_id,
                    'device_type' => 'terminal',
                    'is_active' => true,
                    'is_paired' => true,
                    'paired_at' => now(),
                ]
            );
        }

        $session = $this->createSession(
            device: $device,
            user: $user,
            mode: in_array($mode, ['waiter', 'cashier', 'manager', 'kds'], true) ? $mode : ($user->pos_mode ?: 'waiter'),
            ipAddress: request()?->ip(),
            userAgent: request()?->userAgent()
        );

        return [
            'user' => $user,
            'device' => $device,
            'session' => $session,
            'token' => $session->session_token,
        ];
    }

    /**
     * Authenticate an employee using their PIN on a registered device.
     */
    public function loginWithPin(int $userId, string $pin, string $deviceUuid, string $mode = 'waiter'): array
    {
        $throttleKey = 'pos_pin_attempt:'.$deviceUuid.':'.$userId;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'pin' => __('Too many failed PIN attempts. Please wait :seconds seconds before trying again.', ['seconds' => $seconds]),
            ]);
        }

        $device = PosDevice::where('uuid', $deviceUuid)->first();
        if (! $device || ! $device->is_active) {
            throw ValidationException::withMessages([
                'device' => __('POS device is unregistered, revoked, or inactive.'),
            ]);
        }

        $user = User::where('id', $userId)
            ->where('vendor_id', $device->vendor_id)
            ->where('is_active', true)
            ->first();

        if (! $user) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages([
                'pin' => __('Invalid employee selection for this restaurant.'),
            ]);
        }

        if (! $user->hasPin()) {
            throw ValidationException::withMessages([
                'pin' => __('PIN is not configured for this employee. Please login with password first.'),
            ]);
        }

        if (! $user->verifyPin($pin)) {
            RateLimiter::hit($throttleKey, 60);

            PosOperationLog::log(
                vendor: $device->vendor,
                action: 'pin_login_failed',
                user: $user,
                device: $device,
                details: ['reason' => 'invalid_pin']
            );

            throw ValidationException::withMessages([
                'pin' => __('Incorrect PIN code.'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        $session = $this->createSession(
            device: $device,
            user: $user,
            mode: in_array($mode, ['waiter', 'cashier', 'manager', 'kds'], true) ? $mode : ($user->pos_mode ?: 'waiter'),
            ipAddress: request()?->ip(),
            userAgent: request()?->userAgent()
        );

        return [
            'user' => $user,
            'device' => $device,
            'session' => $session,
            'token' => $session->session_token,
        ];
    }

    /**
     * Quick employee switching on a POS terminal using a PIN.
     */
    public function quickSwitchEmployee(PosDevice $device, int $targetUserId, string $pin, string $mode = 'waiter'): array
    {
        $throttleKey = 'pos_pin_switch:'.$device->uuid.':'.$targetUserId;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'pin' => __('Too many failed attempts. Try again in :seconds seconds.', ['seconds' => $seconds]),
            ]);
        }

        $targetUser = User::where('id', $targetUserId)
            ->where('vendor_id', $device->vendor_id)
            ->where('is_active', true)
            ->first();

        if (! $targetUser) {
            throw ValidationException::withMessages([
                'target_user_id' => __('Selected employee not found or inactive.'),
            ]);
        }

        if (! $targetUser->verifyPin($pin)) {
            RateLimiter::hit($throttleKey, 60);

            PosOperationLog::log(
                vendor: $device->vendor,
                action: 'quick_switch_failed',
                user: $targetUser,
                device: $device,
                details: ['reason' => 'incorrect_pin']
            );

            throw ValidationException::withMessages([
                'pin' => __('Incorrect PIN code.'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        // Terminate previous sessions on this device
        PosDeviceSession::where('device_id', $device->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $session = $this->createSession(
            device: $device,
            user: $targetUser,
            mode: $mode,
            ipAddress: request()?->ip(),
            userAgent: request()?->userAgent()
        );

        PosOperationLog::log(
            vendor: $device->vendor,
            action: 'quick_switch',
            user: $targetUser,
            device: $device,
            entity: $session,
            details: ['mode' => $session->mode]
        );

        return [
            'user' => $targetUser,
            'session' => $session,
            'token' => $session->session_token,
        ];
    }

    /**
     * Set or change employee PIN code.
     */
    public function setUserPin(User $user, string $newPin, ?User $actor = null): void
    {
        if (! preg_match('/^\d{4,6}$/', $newPin)) {
            throw ValidationException::withMessages([
                'pin' => __('PIN must be between 4 and 6 numeric digits.'),
            ]);
        }

        $user->setPin($newPin);

        PosOperationLog::log(
            vendor: $user->vendor,
            action: 'pin_change',
            user: $user,
            entity: $user,
            details: [
                'updated_by' => $actor?->id ?? $user->id,
            ]
        );
    }

    /**
     * Get active employees eligible for POS for a vendor and branch.
     */
    public function getActiveEmployees(Vendor $vendor, ?int $locationId = null): array
    {
        $users = $vendor->users()
            ->where('is_active', true)
            ->whereIn('role', ['vendor_owner', 'manager', 'waiter', 'staff', 'cashier', 'chef'])
            ->when($locationId, function ($query, $locId) {
                return $query->where(function ($q) use ($locId) {
                    $q->whereNull('location_id')->orWhere('location_id', $locId);
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $users->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'display_name' => $u->display_name ?: $u->name,
            'role' => $u->role,
            'avatar_url' => $u->avatar_url,
            'has_pin' => $u->hasPin(),
            'pos_mode' => $u->pos_mode ?: 'waiter',
            'assigned_table_ids' => $u->assigned_table_ids ?? [],
        ])->values()->all();
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
