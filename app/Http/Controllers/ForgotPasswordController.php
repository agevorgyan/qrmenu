<?php

namespace App\Http\Controllers;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use App\Services\CaptchaService;
use App\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    /**
     * Show the forgot password request form.
     */
    public function showLinkRequestForm(TenantContext $tenantContext)
    {
        $customVendor = $tenantContext->getTenant();
        $captcha = CaptchaService::generate();

        return view('auth.forgot_password', compact('customVendor', 'captcha'));
    }

    /**
     * Send a password reset link to the given user.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'captcha' => ['required', 'string'],
        ]);

        if (! CaptchaService::validate($request->input('captcha'))) {
            return back()->withErrors([
                'captcha' => 'The security question (CAPTCHA) answer is incorrect.',
            ])->withInput();
        }

        $email = $request->input('email');
        $user = User::where('email', $email)->first();

        if ($user) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token' => Hash::make($token),
                    'created_at' => Carbon::now(),
                ]
            );

            $resetUrl = route('password.reset', [
                'token' => $token,
                'email' => $email,
            ]);

            try {
                $targetLocale = $user->locale ?: (app()->getLocale() ?: 'hy');
                Mail::to($user->email)->send(new ResetPasswordMail($user, $resetUrl, $targetLocale));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('status', 'If an account exists with this email address, we have sent a password reset link.');
    }
}
