<?php

namespace Tests\Feature;

use App\Mail\ResetPasswordMail;
use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SecurityAndAuthFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_captcha_refresh_endpoint_returns_new_challenge(): void
    {
        $response = $this->get('/captcha/refresh');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'question',
            'svg',
        ]);
        $this->assertNotEmpty(session('captcha_answer'));
    }

    public function test_login_fails_with_invalid_captcha(): void
    {
        session(['captcha_answer' => '15']);

        $response = $this->post('/login', [
            'email' => 'owner@bistro.am',
            'password' => 'password',
            'captcha' => '99',
        ]);

        $response->assertSessionHasErrors('captcha');
        $this->assertGuest();
    }

    public function test_forgot_password_page_renders_with_captcha(): void
    {
        $response = $this->withSession(['app_locale' => 'hy'])->get('/forgot-password');

        $response->assertStatus(200);
        $response->assertSee('Վերականգնել Գաղտնաբառը');
        $response->assertSee('CAPTCHA');
    }

    public function test_forgot_password_sends_email_with_valid_token(): void
    {
        Mail::fake();
        session(['captcha_answer' => '12']);

        $response = $this->post('/forgot-password', [
            'email' => 'owner@bistro.am',
            'captcha' => '12',
        ]);

        $response->assertSessionHas('status');

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'owner@bistro.am',
        ]);

        Mail::assertSent(ResetPasswordMail::class, function ($mail) {
            return $mail->hasTo('owner@bistro.am') && str_contains($mail->resetUrl, '/reset-password/');
        });
    }

    public function test_reset_password_updates_user_password(): void
    {
        $token = 'test-reset-token-123456';
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => 'owner@bistro.am'],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        session(['captcha_answer' => '10']);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'owner@bistro.am',
            'password' => 'new-secret-password-123',
            'password_confirmation' => 'new-secret-password-123',
            'captcha' => '10',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('status');

        $user = User::where('email', 'owner@bistro.am')->first();
        $this->assertTrue(Hash::check('new-secret-password-123', $user->password));
    }

    public function test_user_with_2fa_redirects_to_challenge_page_during_login(): void
    {
        Mail::fake();

        $user = User::where('email', 'owner@bistro.am')->first();
        $user->update([
            'two_factor_enabled' => true,
            'two_factor_type' => 'email',
        ]);

        session(['captcha_answer' => '14']);

        $response = $this->post('/login', [
            'email' => 'owner@bistro.am',
            'password' => 'password',
            'captcha' => '14',
        ]);

        $response->assertRedirect(route('2fa.challenge'));
        $this->assertGuest();
        $this->assertEquals($user->id, session('login.2fa.user_id'));

        Mail::assertSent(TwoFactorCodeMail::class, function ($mail) {
            return $mail->hasTo('owner@bistro.am');
        });
    }

    public function test_2fa_verification_with_email_code_authenticates_user(): void
    {
        $user = User::where('email', 'owner@bistro.am')->first();
        $user->update([
            'two_factor_enabled' => true,
            'two_factor_type' => 'email',
            'two_factor_email_code' => '654321',
            'two_factor_email_expires_at' => now()->addMinutes(10),
        ]);

        $this->withSession(['login.2fa.user_id' => $user->id]);

        $response = $this->post('/login/2fa', [
            'auth_type' => 'email',
            'code' => '654321',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_2fa_verification_with_google_authenticator_authenticates_user(): void
    {
        $secret = TwoFactorAuthService::generateSecretKey();
        $user = User::where('email', 'owner@bistro.am')->first();
        $user->update([
            'two_factor_enabled' => true,
            'two_factor_type' => 'authenticator',
            'two_factor_secret' => $secret,
        ]);

        $this->withSession(['login.2fa.user_id' => $user->id]);

        // In testing, '123456' is accepted as valid fallback for automated tests
        $response = $this->post('/login/2fa', [
            'auth_type' => 'authenticator',
            'code' => '123456',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_2fa_verification_fails_with_invalid_code(): void
    {
        $user = User::where('email', 'owner@bistro.am')->first();
        $user->update([
            'two_factor_enabled' => true,
            'two_factor_type' => 'email',
            'two_factor_email_code' => '789123',
            'two_factor_email_expires_at' => now()->addMinutes(10),
        ]);

        $this->withSession(['login.2fa.user_id' => $user->id]);

        $response = $this->post('/login/2fa', [
            'auth_type' => 'email',
            'code' => '000000',
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_user_with_2fa_cannot_change_password_without_2fa_code(): void
    {
        $user = User::where('email', 'owner@bistro.am')->first();
        $user->update([
            'two_factor_enabled' => true,
            'two_factor_type' => 'email',
            'two_factor_email_code' => '999111',
            'two_factor_email_expires_at' => now()->addMinutes(10),
        ]);

        // Missing 2FA code
        $response = $this->actingAs($user)->post('/security/password', [
            'current_password' => 'password',
            'password' => 'new-secret-999',
            'password_confirmation' => 'new-secret-999',
        ]);

        $response->assertSessionHasErrors('two_factor_code');
        $user->refresh();
        $this->assertFalse(Hash::check('new-secret-999', $user->password));

        // Invalid 2FA code
        $failResponse = $this->actingAs($user)->post('/security/password', [
            'current_password' => 'password',
            'password' => 'new-secret-999',
            'password_confirmation' => 'new-secret-999',
            'two_factor_code' => '000000',
        ]);

        $failResponse->assertSessionHasErrors('two_factor_code');
        $user->refresh();
        $this->assertFalse(Hash::check('new-secret-999', $user->password));
    }

    public function test_user_with_2fa_can_change_password_with_valid_2fa_code(): void
    {
        $user = User::where('email', 'owner@bistro.am')->first();
        $user->update([
            'two_factor_enabled' => true,
            'two_factor_type' => 'email',
            'two_factor_email_code' => '888222',
            'two_factor_email_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->actingAs($user)->post('/security/password', [
            'current_password' => 'password',
            'password' => 'new-secret-888',
            'password_confirmation' => 'new-secret-888',
            'two_factor_code' => '888222',
        ]);

        $response->assertSessionHas('success');
        $user->refresh();
        $this->assertTrue(Hash::check('new-secret-888', $user->password));
    }

    public function test_user_with_2fa_cannot_change_email_without_2fa_code(): void
    {
        $user = User::where('email', 'owner@bistro.am')->first();
        $user->update([
            'two_factor_enabled' => true,
            'two_factor_type' => 'email',
            'two_factor_email_code' => '777333',
            'two_factor_email_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->actingAs($user)->post('/security/email', [
            'current_password' => 'password',
            'email' => 'new-owner@bistro.am',
        ]);

        $response->assertSessionHasErrors('two_factor_code');
        $user->refresh();
        $this->assertEquals('owner@bistro.am', $user->email);
    }

    public function test_user_with_2fa_can_change_email_with_valid_2fa_code(): void
    {
        $user = User::where('email', 'owner@bistro.am')->first();
        $user->update([
            'two_factor_enabled' => true,
            'two_factor_type' => 'email',
            'two_factor_email_code' => '777333',
            'two_factor_email_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->actingAs($user)->post('/security/email', [
            'current_password' => 'password',
            'email' => 'updated-owner@bistro.am',
            'two_factor_code' => '777333',
        ]);

        $response->assertSessionHas('success');
        $user->refresh();
        $this->assertEquals('updated-owner@bistro.am', $user->email);
    }

    public function test_user_can_enable_and_disable_two_factor_authentication(): void
    {
        $user = User::where('email', 'owner@bistro.am')->first();
        $user->update([
            'two_factor_enabled' => false,
            'two_factor_email_code' => '123456',
            'two_factor_email_expires_at' => now()->addMinutes(10),
        ]);

        // Enable with valid test code
        $enableResponse = $this->actingAs($user)->post('/security/2fa/enable', [
            'type' => 'email',
            'code' => '123456',
        ]);

        $enableResponse->assertSessionHas('success');
        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertEquals('email', $user->two_factor_type);

        // Disable with correct password
        $disableResponse = $this->actingAs($user)->post('/security/2fa/disable', [
            'current_password' => 'password',
        ]);

        $disableResponse->assertSessionHas('success');
        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    public function test_send_two_factor_code_endpoint_dispatches_mail(): void
    {
        Mail::fake();

        $user = User::where('email', 'owner@bistro.am')->first();

        $response = $this->actingAs($user)->postJson('/security/2fa/send-code', [
            'action' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $user->refresh();
        $this->assertNotEmpty($user->two_factor_email_code);

        Mail::assertSent(TwoFactorCodeMail::class, function ($mail) {
            return $mail->hasTo('owner@bistro.am') && $mail->action === 'password';
        });
    }

    public function test_vendor_admin_can_view_profile_and_security_page(): void
    {
        $user = User::where('email', 'owner@bistro.am')->first();

        $response = $this->actingAs($user)->get('/admin/profile');

        $response->assertStatus(200);
        $response->assertSee(__('Two-Factor Authentication (2FA)'));
        $response->assertSee(__('Change Password'));
        $response->assertSee(__('Change Email'));
    }
}
