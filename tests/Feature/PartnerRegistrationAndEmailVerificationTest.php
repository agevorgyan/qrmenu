<?php

namespace Tests\Feature;

use App\Mail\VendorWelcomeVerificationMail;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PartnerRegistrationAndEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_partner_registration_creates_inactive_vendor_and_unverified_user(): void
    {
        Mail::fake();

        $response = $this->post('/register', [
            'type' => 'restaurant',
            'name' => 'Armenian Tavern',
            'expected_locations_count' => 1,
            'operating_address' => 'Amiryan 5, Yerevan',
            'legal_name' => '«Հայկական Պանդոկ» ՍՊԸ',
            'legal_address' => 'ք․ Երևան, Ամիրյան 5',
            'tax_id' => '01589632',
            'director_name' => 'Տիգրան Սարգսյան',
            'contact_person_name' => 'Տիգրան Սարգսյան',
            'phone' => '+37494556677',
            'email' => 'tigran@tavern.am',
            'subscription_plan' => 'pro',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertRedirect('/email/verify');

        $user = User::where('email', 'tigran@tavern.am')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasVerifiedEmail());

        $vendor = $user->vendor;
        $this->assertNotNull($vendor);
        $this->assertFalse((bool) $vendor->is_active);
        $this->assertNull($vendor->email_verified_at);

        // Verification code was stored in cache
        $cachedCode = cache()->get('email_verify_code_'.$user->id);
        $this->assertNotNull($cachedCode);
        $this->assertEquals(6, strlen($cachedCode));

        // Welcome mail was sent synchronously with code and verification link
        Mail::assertSent(VendorWelcomeVerificationMail::class, function ($mail) use ($user, $cachedCode) {
            return $mail->hasTo('tigran@tavern.am')
                && $mail->user->id === $user->id
                && $mail->otpCode === $cachedCode;
        });
    }

    public function test_unverified_user_cannot_access_vendor_admin_panel(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'role' => 'vendor_owner',
            'vendor_id' => Vendor::first()->id,
        ]);

        $this->actingAs($user);

        $response = $this->get('/admin/dashboard');

        // Blocked by verified middleware
        $response->assertRedirect('/email/verify');
    }

    public function test_unverified_user_logging_in_is_redirected_to_verification_screen(): void
    {
        $user = User::factory()->create([
            'email' => 'unverified@partner.am',
            'password' => bcrypt('password123'),
            'email_verified_at' => null,
            'role' => 'vendor_owner',
            'vendor_id' => Vendor::first()->id,
        ]);

        $response = $this->post('/login', [
            'email' => 'unverified@partner.am',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/email/verify');
        $this->assertAuthenticatedAs($user);
    }

    public function test_demo_email_verify_route_does_not_exist(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'role' => 'vendor_owner',
            'vendor_id' => Vendor::first()->id,
        ]);

        $this->actingAs($user);

        // The old demo verify POST route is completely removed
        $response = $this->post('/email/verify/demo');
        $this->assertTrue(in_array($response->getStatusCode(), [404, 405]));
    }

    public function test_verify_email_page_does_not_contain_demo_verification_button(): void
    {
        $user = User::factory()->create([
            'name' => 'Aram Khachatryan',
            'email' => 'aram@music-cafe.am',
            'email_verified_at' => null,
            'role' => 'vendor_owner',
            'vendor_id' => Vendor::first()->id,
        ]);

        $this->actingAs($user);

        $response = $this->get('/email/verify');

        $response->assertStatus(200);
        $response->assertSee('aram@music-cafe.am');
        $response->assertSee('Մուտքագրեք 6-նիշ ստուգիչ կոդը');
        $response->assertSee('Ուղարկել նորից');
        $response->assertDontSee('⚡ Դեմո Ակնթարթային Հաստատում');
        $response->assertDontSee('Demo One-Click Verify');
        $response->assertDontSee('/email/verify/demo');
    }

    public function test_user_can_verify_email_with_6_digit_otp_code(): void
    {
        $vendor = Vendor::create([
            'name' => 'Jazz Corner',
            'slug' => 'jazz-corner',
            'type' => 'cafe',
            'email' => 'jazz@corner.am',
            'is_active' => false,
            'email_verified_at' => null,
        ]);

        $user = User::create([
            'vendor_id' => $vendor->id,
            'name' => 'Narek Hakhnazaryan',
            'email' => 'narek@jazz.am',
            'password' => bcrypt('secret123'),
            'role' => 'vendor_owner',
            'email_verified_at' => null,
        ]);

        $otpCode = '839201';
        cache()->put('email_verify_code_'.$user->id, $otpCode, now()->addHours(24));

        $this->actingAs($user);

        $response = $this->post('/email/verify/code', [
            'code' => $otpCode,
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNotNull($user->email_verified_at);

        $vendor->refresh();
        $this->assertTrue((bool) $vendor->is_active);
        $this->assertNotNull($vendor->email_verified_at);

        // Code is cleared from cache after successful verification
        $this->assertNull(cache()->get('email_verify_code_'.$user->id));
    }

    public function test_submitting_incorrect_otp_code_fails(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'role' => 'vendor_owner',
            'vendor_id' => Vendor::first()->id,
        ]);

        cache()->put('email_verify_code_'.$user->id, '111222', now()->addHours(24));

        $this->actingAs($user);

        $response = $this->post('/email/verify/code', [
            'code' => '999888',
        ]);

        $response->assertSessionHasErrors('code');
        $user->refresh();
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_user_can_verify_email_via_email_verification_url(): void
    {
        $vendor = Vendor::create([
            'name' => 'Gyumri Heritage Restaurant',
            'slug' => 'gyumri-heritage',
            'type' => 'restaurant',
            'email' => 'heritage@gyumri.am',
            'is_active' => false,
            'email_verified_at' => null,
        ]);

        $user = User::create([
            'vendor_id' => $vendor->id,
            'name' => 'Mkrtich Armen',
            'email' => 'mkrtich@gyumri.am',
            'password' => bcrypt('secret123'),
            'role' => 'vendor_owner',
            'email_verified_at' => null,
        ]);

        $verificationUrl = route('verification.verify', [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        // Access while unauthenticated (e.g. opened from email client)
        Auth::logout();

        $response = $this->get($verificationUrl);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertTrue($user->hasVerifiedEmail());

        $vendor->refresh();
        $this->assertTrue((bool) $vendor->is_active);
        $this->assertNotNull($vendor->email_verified_at);
    }

    public function test_invalid_hash_email_verification_url_is_rejected(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'role' => 'vendor_owner',
            'vendor_id' => Vendor::first()->id,
        ]);

        $response = $this->get(route('verification.verify', [
            'id' => $user->id,
            'hash' => 'invalid-tampered-hash',
        ]));

        $response->assertStatus(403);
        $user->refresh();
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_resend_verification_email_dispatches_new_mail_and_code(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'resend@test.am',
            'email_verified_at' => null,
            'role' => 'vendor_owner',
            'vendor_id' => Vendor::first()->id,
        ]);

        $this->actingAs($user);

        $response = $this->post('/email/verification-notification');

        $response->assertRedirect();
        $response->assertSessionHas('status', 'verification-link-sent');

        $newCode = cache()->get('email_verify_code_'.$user->id);
        $this->assertNotNull($newCode);

        Mail::assertSent(VendorWelcomeVerificationMail::class, function ($mail) use ($newCode) {
            return $mail->hasTo('resend@test.am')
                && $mail->otpCode === $newCode;
        });
    }

    public function test_already_verified_user_is_redirected_away_from_verification_notice(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'role' => 'vendor_owner',
            'vendor_id' => Vendor::first()->id,
        ]);

        $this->actingAs($user);

        $response = $this->get('/email/verify');

        $response->assertRedirect(route('admin.dashboard'));
    }
}
