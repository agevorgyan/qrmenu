<?php

namespace App\Http\Controllers;

use App\Models\PosDevice;
use App\Models\PosDeviceSession;
use App\Models\User;
use App\Security\Permission;
use App\Services\PosDeviceService;
use App\Services\PosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PosAuthController extends Controller
{
    public function __construct(
        protected PosDeviceService $posDeviceService,
        protected PosService $posService,
    ) {}

    /**
     * Authenticate employee with username/email and password for POS terminal.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_uuid' => 'nullable|string',
            'mode' => 'nullable|string|in:waiter,cashier,manager,kds',
        ]);

        $result = $this->posDeviceService->loginWithCredentials(
            email: $validated['email'],
            password: $validated['password'],
            deviceUuid: $validated['device_uuid'] ?? null,
            mode: $validated['mode'] ?? 'waiter'
        );

        Auth::login($result['user']);

        $bootstrapData = $this->posService->getBootstrapPayload(
            user: $result['user'],
            deviceUuid: $result['device']?->uuid
        );

        return response()->json([
            'success' => true,
            'message' => __('POS login successful.'),
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'display_name' => $result['user']->display_name ?: $result['user']->name,
                'role' => $result['user']->role,
                'avatar_url' => $result['user']->avatar_url,
                'has_pin' => $result['user']->hasPin(),
                'permissions' => $result['user']->getPermissions(),
            ],
            'session' => [
                'session_token' => $result['session']->session_token,
                'mode' => $result['session']->mode,
                'expires_at' => $result['session']->expires_at->toIso8601String(),
            ],
            'device' => $result['device'] ? [
                'id' => $result['device']->id,
                'uuid' => $result['device']->uuid,
                'name' => $result['device']->name,
            ] : null,
            'bootstrap' => $bootstrapData,
        ]);
    }

    /**
     * Authenticate employee using their PIN on a registered device.
     */
    public function pinLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'pin' => 'required|string|min:4|max:6',
            'device_uuid' => 'required|string',
            'mode' => 'nullable|string|in:waiter,cashier,manager,kds',
        ]);

        $result = $this->posDeviceService->loginWithPin(
            userId: (int) $validated['user_id'],
            pin: $validated['pin'],
            deviceUuid: $validated['device_uuid'],
            mode: $validated['mode'] ?? 'waiter'
        );

        Auth::login($result['user']);

        $bootstrapData = $this->posService->getBootstrapPayload(
            user: $result['user'],
            deviceUuid: $result['device']->uuid
        );

        return response()->json([
            'success' => true,
            'message' => __('PIN login successful.'),
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'display_name' => $result['user']->display_name ?: $result['user']->name,
                'role' => $result['user']->role,
                'avatar_url' => $result['user']->avatar_url,
                'has_pin' => true,
                'permissions' => $result['user']->getPermissions(),
            ],
            'session' => [
                'session_token' => $result['session']->session_token,
                'mode' => $result['session']->mode,
                'expires_at' => $result['session']->expires_at->toIso8601String(),
            ],
            'device' => [
                'id' => $result['device']->id,
                'uuid' => $result['device']->uuid,
                'name' => $result['device']->name,
            ],
            'bootstrap' => $bootstrapData,
        ]);
    }

    /**
     * Quick switch active employee on the device using PIN.
     */
    public function quickSwitch(Request $request): JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $validated = $request->validate([
            'target_user_id' => 'required|integer',
            'pin' => 'required|string|min:4|max:6',
            'device_uuid' => 'required|string',
            'mode' => 'nullable|string|in:waiter,cashier,manager,kds',
        ]);

        $device = PosDevice::where('vendor_id', $vendor->id)
            ->where('uuid', $validated['device_uuid'])
            ->first();

        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => __('Device not found.'),
            ], 404);
        }

        $result = $this->posDeviceService->quickSwitchEmployee(
            device: $device,
            targetUserId: (int) $validated['target_user_id'],
            pin: $validated['pin'],
            mode: $validated['mode'] ?? 'waiter'
        );

        Auth::login($result['user']);

        return response()->json([
            'success' => true,
            'message' => __('Switched employee successfully.'),
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'display_name' => $result['user']->display_name ?: $result['user']->name,
                'role' => $result['user']->role,
                'avatar_url' => $result['user']->avatar_url,
                'permissions' => $result['user']->getPermissions(),
            ],
            'session' => [
                'session_token' => $result['session']->session_token,
                'mode' => $result['session']->mode,
            ],
        ]);
    }

    /**
     * List active employees for quick-PIN selection grid.
     */
    public function getEmployees(Request $request): JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $locationId = $request->filled('location_id') ? (int) $request->get('location_id') : $user->location_id;
        $employees = $this->posDeviceService->getActiveEmployees($vendor, $locationId);

        return response()->json([
            'success' => true,
            'employees' => $employees,
        ]);
    }

    /**
     * Set or change an employee's PIN.
     */
    public function setPin(Request $request): JsonResponse
    {
        $actor = Auth::user();
        $vendor = $actor->vendor;

        $validated = $request->validate([
            'pin' => 'required|string|min:4|max:6|regex:/^\d+$/',
            'target_user_id' => 'nullable|integer',
        ]);

        $targetUser = $actor;
        if (! empty($validated['target_user_id']) && (int) $validated['target_user_id'] !== (int) $actor->id) {
            if (! $actor->hasPermission(Permission::TEAM_MANAGE) && ! $actor->isVendorOwner()) {
                abort(403, 'Unauthorized to configure PIN for other employees.');
            }

            $targetUser = User::where('id', $validated['target_user_id'])
                ->where('vendor_id', $vendor->id)
                ->firstOrFail();
        }

        $this->posDeviceService->setUserPin($targetUser, $validated['pin'], $actor);

        return response()->json([
            'success' => true,
            'message' => __('PIN code updated successfully.'),
        ]);
    }

    /**
     * Lock the current POS terminal screen.
     */
    public function lock(Request $request): JsonResponse
    {
        $user = Auth::user();
        $token = $request->header('X-POS-Session-Token', $request->get('session_token'));

        if ($token) {
            $session = PosDeviceSession::where('session_token', $token)
                ->where('vendor_id', $user->vendor_id)
                ->first();

            if ($session) {
                $this->posDeviceService->lockSession($session);
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('POS terminal locked.'),
        ]);
    }

    /**
     * Unlock current POS session with PIN or password.
     */
    public function unlock(Request $request): JsonResponse
    {
        $user = Auth::user();
        $token = $request->header('X-POS-Session-Token', $request->get('session_token'));

        $validated = $request->validate([
            'pin' => 'required|string',
        ]);

        if ($token) {
            $session = PosDeviceSession::where('session_token', $token)
                ->where('vendor_id', $user->vendor_id)
                ->first();

            if ($session) {
                $unlocked = $this->posDeviceService->unlockSession($session, $validated['pin']);
                if (! $unlocked) {
                    return response()->json([
                        'success' => false,
                        'message' => __('Invalid PIN or password.'),
                    ], 422);
                }

                return response()->json([
                    'success' => true,
                    'message' => __('Terminal unlocked.'),
                    'session' => $session->fresh(),
                ]);
            }
        }

        // Direct PIN check on current auth user
        if ($user->hasPin() && $user->verifyPin($validated['pin'])) {
            return response()->json([
                'success' => true,
                'message' => __('Terminal unlocked.'),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => __('Invalid PIN.'),
        ], 422);
    }

    /**
     * Terminate POS session and log out.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = Auth::user();
        $token = $request->header('X-POS-Session-Token', $request->get('session_token'));

        if ($token) {
            $session = PosDeviceSession::where('session_token', $token)
                ->where('vendor_id', $user->vendor_id)
                ->first();

            if ($session) {
                $this->posDeviceService->terminateSession($session);
            }
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => __('Logged out successfully.'),
        ]);
    }
}

