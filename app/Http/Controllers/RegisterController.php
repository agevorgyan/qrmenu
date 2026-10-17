<?php

namespace App\Http\Controllers;

use App\Mail\VendorWelcomeVerificationMail;
use App\Models\Location;
use App\Models\MenuTemplate;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Services\CaptchaService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        $templates = MenuTemplate::where('is_active', true)->get();
        $plans = SubscriptionPlan::where('is_active', true)->orderBy('sort_order', 'asc')->get();
        $captcha = CaptchaService::generate();

        return view('auth.register', compact('templates', 'plans', 'captcha'));
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:restaurant,cafe,hotel',
            'name' => 'required|string|max:255',
            'expected_locations_count' => 'required|integer|min:1|max:100',
            'operating_address' => 'required|string',
            'legal_name' => 'required|string|max:255',
            'legal_address' => 'required|string|max:255',
            'tax_id' => 'required|string|max:50', // Tax ID / TIN
            'director_name' => 'required|string|max:255',
            'contact_person_name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'required|email|unique:users,email|unique:vendors,email',
            'subscription_plan' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
            'captcha' => 'nullable|string',
        ]);

        if (! CaptchaService::validate($request->input('captcha'))) {
            return back()->withErrors([
                'captcha' => 'The security question (CAPTCHA) answer is incorrect.',
            ])->withInput();
        }

        $template = MenuTemplate::first();
        $plan = SubscriptionPlan::where('slug', $validated['subscription_plan'])->first()
            ?? SubscriptionPlan::where('slug', 'pro')->first();

        $user = DB::transaction(function () use ($validated, $plan, $template) {
            $trialDays = $plan?->trial_days ?? 14;

            // 1. Create Vendor Record with Free Trial (Inactive until email is verified)
            $vendor = Vendor::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']).'-'.Str::random(4),
                'type' => $validated['type'],
                'legal_name' => $validated['legal_name'],
                'legal_address' => $validated['legal_address'],
                'tax_id' => $validated['tax_id'],
                'director_name' => $validated['director_name'],
                'contact_person_name' => $validated['contact_person_name'],
                'operating_address' => $validated['operating_address'],
                'expected_locations_count' => $validated['expected_locations_count'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'subscription_plan' => $plan?->slug ?? 'pro',
                'subscription_plan_id' => $plan?->id,
                'subscription_status' => 'trialing',
                'trial_ends_at' => now()->addDays($trialDays),
                'subscription_expires_at' => now()->addDays($trialDays),
                'menu_template_id' => $template?->id,
                'primary_color' => '#e11d48',
                'secondary_color' => '#4f46e5',
                'is_active' => false,
            ]);

            if ($plan) {
                app(SubscriptionService::class)->startTrial($vendor, $plan, $trialDays);
                $vendor->updateQuietly(['is_active' => false]);
            }

            // 2. Create Initial Main Location
            $location = Location::create([
                'vendor_id' => $vendor->id,
                'name' => $validated['name'].' (Main)',
                'slug' => 'main',
                'address' => $validated['operating_address'],
                'phone' => $validated['phone'],
                'table_count' => 20,
                'is_active' => true,
            ]);

            // 3. Create Vendor Owner User
            return User::create([
                'vendor_id' => $vendor->id,
                'location_id' => $location->id,
                'name' => $validated['contact_person_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'vendor_owner',
                'phone' => $validated['phone'],
                'locale' => app()->getLocale() ?: 'hy',
            ]);
        });

        $otpCode = (string) random_int(100000, 999999);
        cache()->put('email_verify_code_'.$user->id, $otpCode, now()->addHours(24));

        $verificationUrl = route('verification.verify', [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        try {
            Mail::to($user->email)->queue(
                new VendorWelcomeVerificationMail($user, $verificationUrl, $otpCode, $user->locale ?? app()->getLocale())
            );
        } catch (\Throwable $e) {
            // Silently handle mail dispatch failure if queue/mailer is offline
        }

        Auth::login($user);

        return redirect()->route('verification.notice');
    }

    public function showVerificationNotice()
    {
        $user = Auth::user();
        if ($user && $user->hasVerifiedEmail()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user && ! cache()->has('email_verify_code_'.$user->id)) {
            $otpCode = (string) random_int(100000, 999999);
            cache()->put('email_verify_code_'.$user->id, $otpCode, now()->addHours(24));
        }

        return view('auth.verify-email');
    }

    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(403, 'Invalid verification link.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            cache()->forget('email_verify_code_'.$user->id);

            if ($user->vendor) {
                $user->vendor->update([
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]);
            }
        }

        if (! Auth::check()) {
            Auth::login($user);
        }

        return redirect()->route('admin.dashboard')->with('success', '🎉 Ձեր էլ․ փոստը հաջողությամբ հաստատվել է։ Բարի գալուստ QRMenu!');
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('admin.dashboard');
        }

        $inputCode = trim($request->input('code'));
        $cachedCode = cache()->get('email_verify_code_'.$user->id);

        if (! $cachedCode || ! hash_equals((string) $cachedCode, $inputCode)) {
            return back()->withErrors([
                'code' => 'Մուտքագրված ստուգիչ կոդը սխալ է կամ ժամկետանց։',
            ]);
        }

        $user->markEmailAsVerified();
        cache()->forget('email_verify_code_'.$user->id);

        if ($user->vendor) {
            $user->vendor->update([
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
        }

        return redirect()->route('admin.dashboard')->with('success', '🎉 Ձեր էլ․ փոստը հաջողությամբ հաստատվել է։ Բարի գալուստ QRMenu!');
    }

    public function resendVerificationEmail(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('admin.dashboard');
        }

        $otpCode = (string) random_int(100000, 999999);
        cache()->put('email_verify_code_'.$user->id, $otpCode, now()->addHours(24));

        $verificationUrl = route('verification.verify', [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        try {
            Mail::to($user->email)->queue(
                new VendorWelcomeVerificationMail($user, $verificationUrl, $otpCode, $user->locale ?? app()->getLocale())
            );
        } catch (\Throwable $e) {
            // Silently handle mail dispatch failure
        }

        return back()->with('status', 'verification-link-sent');
    }
}
