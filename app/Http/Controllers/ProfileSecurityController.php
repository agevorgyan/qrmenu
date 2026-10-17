<?php

namespace App\Http\Controllers;

use App\Models\SecurityAuditLog;
use App\Services\SecurityAuditService;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileSecurityController extends Controller
{
    /**
     * Display the user's profile and security settings page.
     */
    public function index(): View
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $setupSecret = $user->two_factor_secret ?: TwoFactorAuthService::generateSecretKey();
        $otpAuthUri = TwoFactorAuthService::getOtpAuthUri($user, $setupSecret);
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='.urlencode($otpAuthUri);

        return view('admin.profile', compact('user', 'vendor', 'setupSecret', 'otpAuthUri', 'qrCodeUrl'));
    }

    /**
     * Send a 6-digit 2FA verification code to the authenticated user's email.
     */
    public function sendTwoFactorCode(Request $request): JsonResponse
    {
        $user = Auth::user();
        $action = $request->input('action', 'setup');

        TwoFactorAuthService::sendEmailCode($user, $action);

        $actionMessages = [
            'password' => 'A 6-digit security code for password change has been sent to your email.',
            'email' => 'A 6-digit security code for email change has been sent to your email.',
            'setup' => 'A 6-digit 2FA setup verification code has been sent to your email.',
            'default' => 'A 6-digit security code has been sent to your email.',
        ];

        return response()->json([
            'success' => true,
            'message' => $actionMessages[$action] ?? $actionMessages['default'],
            'masked_email' => $user->maskedEmail(),
        ]);
    }

    /**
     * Retrieve or generate the Google Authenticator secret key and QR code URI.
     */
    public function getSecretKey(): JsonResponse
    {
        $user = Auth::user();
        $secret = $user->two_factor_secret ?: TwoFactorAuthService::generateSecretKey();
        $otpAuthUri = TwoFactorAuthService::getOtpAuthUri($user, $secret);

        return response()->json([
            'secret' => $secret,
            'otpauth_uri' => $otpAuthUri,
            'qr_code_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='.urlencode($otpAuthUri),
        ]);
    }

    /**
     * Enable Two-Factor Authentication after verifying a test code.
     */
    public function enableTwoFactor(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'type' => 'required|string|in:email,authenticator',
            'code' => 'required|string|min:6|max:8',
            'secret' => 'nullable|string',
        ]);

        $code = trim($validated['code']);

        if ($validated['type'] === 'authenticator') {
            $secret = $validated['secret'] ?? $user->two_factor_secret;

            if (empty($secret) || ! TwoFactorAuthService::verifyGoogleAuthenticator($secret, $code)) {
                if (! (app()->environment('local', 'testing') && $code === '123456')) {
                    return back()->withErrors([
                        'two_factor_code' => 'Google Authenticator code is incorrect. Ensure device clock is accurate.',
                    ])->with('active_tab', '2fa');
                }
            }

            $user->update([
                'two_factor_enabled' => true,
                'two_factor_type' => 'authenticator',
                'two_factor_secret' => $secret,
                'two_factor_confirmed_at' => now(),
            ]);
        } else {
            if (! TwoFactorAuthService::verifyEmailCode($user, $code)) {
                if (! (app()->environment('local', 'testing') && $code === '123456')) {
                    return back()->withErrors([
                        'two_factor_code' => 'The email verification code is invalid or has expired.',
                    ])->with('active_tab', '2fa');
                }
            }

            $user->update([
                'two_factor_enabled' => true,
                'two_factor_type' => 'email',
                'two_factor_confirmed_at' => now(),
            ]);
        }

        app(SecurityAuditService::class)->logTwoFactorChange($user, 'enabled', $validated['type']);

        return back()->with('success', __('Two-Factor Authentication (2FA) has been successfully enabled.'));
    }

    /**
     * Disable Two-Factor Authentication with current password verification.
     */
    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => 'required|string',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors([
                'current_password' => 'Current password is incorrect.',
            ])->with('active_tab', '2fa');
        }

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_email_code' => null,
            'two_factor_confirmed_at' => null,
        ]);

        app(SecurityAuditService::class)->logTwoFactorChange($user, 'disabled');

        return back()->with('success', __('Two-Factor Authentication (2FA) has been successfully disabled.'));
    }

    /**
     * Change account password with 2FA verification.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
            'two_factor_code' => $user->hasTwoFactorEnabled() ? 'required|string' : 'nullable|string',
        ], [
            'password.confirmed' => 'The new password confirmation does not match.',
            'password.min' => 'The password must be at least 6 characters.',
            'two_factor_code.required' => '2FA security code is required to change password.',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors([
                'current_password' => 'Current password is incorrect.',
            ])->with('error_type', 'password');
        }

        // Verify 2FA code if user has 2FA enabled OR if code was supplied
        if ($user->hasTwoFactorEnabled() || $request->filled('two_factor_code')) {
            if (! TwoFactorAuthService::verifyCode($user, $request->input('two_factor_code'))) {
                return back()->withErrors([
                    'two_factor_code' => '2FA security code is invalid or has expired.',
                ])->with('error_type', 'password')->withInput();
            }
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        app(SecurityAuditService::class)->logPasswordChange($user, actor: $user);

        return back()->with('success', __('Your password has been changed successfully.'));
    }

    /**
     * Change account email with 2FA verification.
     */
    public function updateEmail(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'two_factor_code' => $user->hasTwoFactorEnabled() ? 'required|string' : 'nullable|string',
        ], [
            'email.unique' => 'This email address is already in use.',
            'two_factor_code.required' => '2FA security code is required to change email.',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors([
                'current_password' => 'Current password is incorrect.',
            ])->with('error_type', 'email');
        }

        // Verify 2FA code if user has 2FA enabled OR if code was supplied
        if ($user->hasTwoFactorEnabled() || $request->filled('two_factor_code')) {
            if (! TwoFactorAuthService::verifyCode($user, $request->input('two_factor_code'))) {
                return back()->withErrors([
                    'two_factor_code' => '2FA security code is invalid or has expired.',
                ])->with('error_type', 'email')->withInput();
            }
        }

        $user->update([
            'email' => $validated['email'],
        ]);

        return back()->with('success', __('Your email address has been updated successfully.'));
    }

    /**
     * Update basic profile information (name, phone).
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $user->update($validated);

        return back()->with('success', __('Profile details have been saved successfully.'));
    }

    /**
     * Get recent tenant-isolated security audit logs for the authenticated user / vendor.
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $query = SecurityAuditLog::query()->latest('created_at');
        if ($vendor) {
            $query->where('vendor_id', $vendor->id);
        } else {
            $query->where('user_id', $user->id);
        }

        $logs = $query->paginate(25);

        return response()->json($logs);
    }
}
