<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TenantContext;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TwoFactorController extends Controller
{
    /**
     * Display the 2FA challenge screen.
     */
    public function showChallenge(TenantContext $tenantContext)
    {
        $userId = session('login.2fa.user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (! $user) {
            session()->forget(['login.2fa.user_id', 'login.2fa.remember']);

            return redirect()->route('login');
        }

        // If user uses email 2FA and code is not yet sent or expired, auto-dispatch
        if ($user->two_factor_type !== 'authenticator' && (empty($user->two_factor_email_code) || now()->greaterThan($user->two_factor_email_expires_at))) {
            TwoFactorAuthService::sendEmailCode($user, 'login');
        }

        $customVendor = $tenantContext->getTenant();
        if ($customVendor && ! $user->isSuperAdmin() && (int) $user->vendor_id !== (int) $customVendor->id) {
            session()->forget(['login.2fa.user_id', 'login.2fa.remember']);

            return redirect()->route('login')->withErrors([
                'email' => 'This dashboard is reserved for '.$customVendor->name.' staff only.',
            ]);
        }

        return view('auth.two_factor_challenge', compact('user', 'customVendor'));
    }

    /**
     * Resend a fresh 6-digit 2FA email code.
     */
    public function resendEmailCode()
    {
        $userId = session('login.2fa.user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if ($user) {
            TwoFactorAuthService::sendEmailCode($user);
        }

        return back()->with('status', 'A new security code has been sent to your email.');
    }

    /**
     * Verify the 2FA code (Google Authenticator TOTP or Email code).
     */
    public function verify(Request $request, TenantContext $tenantContext)
    {
        $userId = session('login.2fa.user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (! $user) {
            session()->forget(['login.2fa.user_id', 'login.2fa.remember']);

            return redirect()->route('login');
        }

        $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:8'],
            'auth_type' => ['nullable', 'string', 'in:authenticator,email'],
        ]);

        $code = trim($request->input('code'));
        $authType = $request->input('auth_type');
        $isValid = false;

        if ($authType === 'authenticator' && ! empty($user->two_factor_secret)) {
            $isValid = TwoFactorAuthService::verifyGoogleAuthenticator($user->two_factor_secret, $code);
        } elseif ($authType === 'email') {
            $isValid = TwoFactorAuthService::verifyEmailCode($user, $code);
        }

        if (! $isValid) {
            $isValid = TwoFactorAuthService::verifyCode($user, $code);
        }

        if (! $isValid) {
            return back()->withErrors([
                'code' => 'The provided 2FA security code is invalid or has expired.',
            ]);
        }

        $remember = session('login.2fa.remember', false);
        session()->forget(['login.2fa.user_id', 'login.2fa.remember']);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $customVendor = $tenantContext->getTenant();

        if ($user->isSuperAdmin()) {
            return redirect()->route('superadmin.dashboard');
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return redirect()->route('admin.dashboard');
    }
}
