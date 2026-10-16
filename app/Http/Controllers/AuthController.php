<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CaptchaService;
use App\Services\SecurityAuditService;
use App\Services\TenantContext;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin(TenantContext $tenantContext)
    {
        $customVendor = $tenantContext->getTenant();
        $captcha = CaptchaService::generate();

        if (Auth::check()) {
            $user = Auth::user();

            if ($customVendor && ! $user->isSuperAdmin() && (int) $user->vendor_id !== (int) $customVendor->id) {
                Auth::logout();
                session()->invalidate();
                session()->regenerateToken();

                return view('auth.login', compact('customVendor', 'captcha'))->withErrors([
                    'email' => "This dashboard is reserved for {$customVendor->name} staff only.",
                ]);
            }

            return $this->redirectUser($user, $customVendor);
        }

        return view('auth.login', compact('customVendor', 'captcha'));
    }

    public function refreshCaptcha()
    {
        return response()->json(CaptchaService::generate());
    }

    public function showDemoLogin(TenantContext $tenantContext)
    {
        $customVendor = $tenantContext->getTenant();

        return view('auth.demo_login', compact('customVendor'));
    }

    public function demoLogin(Request $request, TenantContext $tenantContext)
    {
        $role = $request->input('role', 'owner');

        // Only vendor accounts are permitted for demo; superadmin is strictly excluded
        $email = match ($role) {
            'manager' => 'manager@bistro.am',
            default => 'owner@bistro.am',
        };

        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::where('role', 'vendor_owner')->first();
        }

        if (! $user) {
            return back()->withErrors([
                'email' => 'Demo user not found in the system.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $activeLocale = $user->locale ?? session('admin_locale') ?? $request->cookie('app_locale') ?? app()->getLocale();
        if ($activeLocale) {
            session(['admin_locale' => $activeLocale, 'app_locale' => $activeLocale, 'locale' => $activeLocale]);
            cookie()->queue('app_locale', $activeLocale, 525600);
            cookie()->queue('admin_locale', $activeLocale, 525600);
            cookie()->queue('locale', $activeLocale, 525600);
        }

        $customVendor = $tenantContext->getTenant();

        return $this->redirectUser($user, $customVendor);
    }

    public function login(Request $request, TenantContext $tenantContext)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'captcha' => app()->environment('testing') ? ['nullable', 'string'] : ['required', 'string'],
        ]);

        if ($request->filled('captcha') || ! app()->environment('testing')) {
            if (! CaptchaService::validate($request->input('captcha'))) {
                return back()->withErrors([
                    'captcha' => 'The security question (CAPTCHA) answer is incorrect.',
                ])->onlyInput('email');
            }
        }

        if (Auth::validate(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            $user = User::where('email', $credentials['email'])->first();
            $customVendor = $tenantContext->getTenant();

            if ($customVendor && ! $user->isSuperAdmin() && (int) $user->vendor_id !== (int) $customVendor->id) {
                app(SecurityAuditService::class)->logFailedLogin(
                    attemptedIdentifier: $credentials['email'],
                    vendor: $customVendor,
                    reason: "User does not belong to vendor [{$customVendor->name}]."
                );

                return back()->withErrors([
                    'email' => "Only users from {$customVendor->name} can log in to this panel.",
                ])->onlyInput('email');
            }

            // Check Two-Factor Authentication
            if ($user->hasTwoFactorEnabled()) {
                session([
                    'login.2fa.user_id' => $user->id,
                    'login.2fa.remember' => $request->boolean('remember'),
                ]);

                if ($user->two_factor_type !== 'authenticator') {
                    TwoFactorAuthService::sendEmailCode($user, 'login');
                }

                return redirect()->route('2fa.challenge');
            }

            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            $activeLocale = $user->locale ?? session('admin_locale') ?? $request->cookie('app_locale') ?? app()->getLocale();
            if ($activeLocale) {
                session(['admin_locale' => $activeLocale, 'app_locale' => $activeLocale, 'locale' => $activeLocale]);
                cookie()->queue('app_locale', $activeLocale, 525600);
                cookie()->queue('admin_locale', $activeLocale, 525600);
                cookie()->queue('locale', $activeLocale, 525600);
                if (! $user->locale) {
                    $user->locale = $activeLocale;
                    $user->saveQuietly();
                }
            }

            return $this->redirectUser($user, $customVendor);
        }

        $customVendor = $tenantContext->getTenant();
        app(SecurityAuditService::class)->logFailedLogin(
            attemptedIdentifier: $credentials['email'],
            vendor: $customVendor,
            reason: 'The provided credentials do not match our records.'
        );

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        $currentLocale = session('admin_locale') ?? session('app_locale') ?? $request->cookie('app_locale') ?? app()->getLocale();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($currentLocale) {
            session([
                'admin_locale' => $currentLocale,
                'app_locale' => $currentLocale,
                'locale' => $currentLocale,
            ]);
            cookie()->queue('app_locale', $currentLocale, 525600);
            cookie()->queue('admin_locale', $currentLocale, 525600);
            cookie()->queue('locale', $currentLocale, 525600);
        }

        return redirect()->route('login');
    }

    private function redirectUser($user, $customVendor = null)
    {
        if ($user->isSuperAdmin()) {
            if ($customVendor) {
                $platformUrl = rtrim(config('app.url', 'https://menu.elab.am'), '/');

                return redirect()->to($platformUrl.'/superadmin/dashboard');
            }

            return redirect()->route('superadmin.dashboard');
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return redirect()->route('admin.dashboard');
    }
}
