<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CaptchaService;
use App\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResetPasswordController extends Controller
{
    /**
     * Display the password reset form for the given token.
     */
    public function showResetForm(Request $request, string $token, TenantContext $tenantContext)
    {
        $email = $request->query('email');
        $customVendor = $tenantContext->getTenant();
        $captcha = CaptchaService::generate();

        return view('auth.reset_password', compact('token', 'email', 'customVendor', 'captcha'));
    }

    /**
     * Reset the given user's password.
     */
    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'captcha' => ['required', 'string'],
        ]);

        if (! CaptchaService::validate($request->input('captcha'))) {
            return back()->withErrors([
                'captcha' => 'The security question (CAPTCHA) answer is incorrect.',
            ])->withInput();
        }

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $record) {
            return back()->withErrors([
                'email' => 'Password reset request not found or link is invalid.',
            ]);
        }

        // Token lifetime: 60 minutes
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return back()->withErrors([
                'email' => 'The password reset link has expired. Please request a new one.',
            ]);
        }

        if (! Hash::check($request->token, $record->token)) {
            return back()->withErrors([
                'token' => 'Invalid password reset token.',
            ]);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return back()->withErrors([
                'email' => 'No user found with this email address.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return redirect()->route('login')->with('status', __('Your password has been reset successfully. You can now log in with your new password.'));
    }
}
