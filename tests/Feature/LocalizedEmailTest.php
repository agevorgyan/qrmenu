<?php

namespace Tests\Feature;

use App\Mail\OrderReceiptMail;
use App\Mail\ResetPasswordMail;
use App\Mail\SystemTestMail;
use App\Mail\TwoFactorCodeMail;
use App\Mail\VendorWelcomeVerificationMail;
use App\Models\EmailConnection;
use App\Models\Language;
use App\Models\Location;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use App\Services\TwoFactorAuthService;
use Database\Seeders\EmailManagementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LocalizedEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::firstOrCreate(
            ['code' => 'en'],
            ['name' => 'English', 'native_name' => 'English', 'flag' => '🇬🇧', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true, 'sort_order' => 1]
        );
        Language::firstOrCreate(
            ['code' => 'hy'],
            ['name' => 'Armenian', 'native_name' => 'Հայերեն', 'flag' => '🇦🇲', 'direction' => 'ltr', 'is_active' => true, 'is_default' => false, 'sort_order' => 2]
        );
        Language::firstOrCreate(
            ['code' => 'ru'],
            ['name' => 'Russian', 'native_name' => 'Русский', 'flag' => '🇷🇺', 'direction' => 'ltr', 'is_active' => true, 'is_default' => false, 'sort_order' => 3]
        );

        $this->seed(EmailManagementSeeder::class);
    }

    public function test_welcome_verification_email_renders_in_armenian_english_and_russian(): void
    {
        $user = User::factory()->create(['name' => 'Arman Test', 'locale' => 'hy']);

        // Armenian
        $mailHy = new VendorWelcomeVerificationMail($user, 'https://example.com/verify-hy', '111111', 'hy');
        $this->assertStringContainsString('Բարի գալուստ', $mailHy->envelope()->subject);
        $htmlHy = $mailHy->render();
        $this->assertStringContainsString('Բարի գալուստ', $htmlHy);
        $this->assertStringContainsString('Հաստատել Էլ․ Հասցեն', $htmlHy);
        $this->assertStringContainsString('111111', $htmlHy);

        // English
        $mailEn = new VendorWelcomeVerificationMail($user, 'https://example.com/verify-en', '222222', 'en');
        $this->assertStringContainsString('Welcome to', $mailEn->envelope()->subject);
        $htmlEn = $mailEn->render();
        $this->assertStringContainsString('Welcome', $htmlEn);
        $this->assertStringContainsString('Verify Email Address', $htmlEn);
        $this->assertStringContainsString('222222', $htmlEn);

        // Russian
        $mailRu = new VendorWelcomeVerificationMail($user, 'https://example.com/verify-ru', '333333', 'ru');
        $this->assertStringContainsString('Добро пожаловать в', $mailRu->envelope()->subject);
        $htmlRu = $mailRu->render();
        $this->assertStringContainsString('Добро пожаловать', $htmlRu);
        $this->assertStringContainsString('Подтвердить эл. почту', $htmlRu);
        $this->assertStringContainsString('333333', $htmlRu);
    }

    public function test_reset_password_email_renders_in_armenian_english_and_russian(): void
    {
        $user = User::factory()->create(['name' => 'Karen Test']);

        // Armenian
        $mailHy = new ResetPasswordMail($user, 'https://example.com/reset-hy', 'hy');
        $this->assertStringContainsString('Գաղտնաբառի Վերականգնման Հարցում', $mailHy->envelope()->subject);
        $this->assertStringContainsString('Վերականգնել Գաղտնաբառը', $mailHy->render());

        // English
        $mailEn = new ResetPasswordMail($user, 'https://example.com/reset-en', 'en');
        $this->assertStringContainsString('Password Reset Request', $mailEn->envelope()->subject);
        $this->assertStringContainsString('Reset Password', $mailEn->render());

        // Russian
        $mailRu = new ResetPasswordMail($user, 'https://example.com/reset-ru', 'ru');
        $this->assertStringContainsString('Запрос на восстановление пароля', $mailRu->envelope()->subject);
        $this->assertStringContainsString('Сбросить пароль', $mailRu->render());
    }

    public function test_two_factor_code_email_renders_in_armenian_english_and_russian(): void
    {
        $user = User::factory()->create(['name' => 'Anna Test']);

        // Armenian
        $mailHy = new TwoFactorCodeMail($user, '654321', 'login', 'hy');
        $this->assertStringContainsString('Երկփուլային Նույնականացման Կոդը', $mailHy->envelope()->subject);
        $this->assertStringContainsString('654321', $mailHy->render());

        // English
        $mailEn = new TwoFactorCodeMail($user, '654321', 'login', 'en');
        $this->assertStringContainsString('Two-Factor Authentication (2FA) Code', $mailEn->envelope()->subject);
        $this->assertStringContainsString('Two-Factor Authentication', $mailEn->render());

        // Russian
        $mailRu = new TwoFactorCodeMail($user, '654321', 'login', 'ru');
        $this->assertStringContainsString('код двухфакторной аутентификации', $mailRu->envelope()->subject);
        $this->assertStringContainsString('Двухфакторная аутентификация', $mailRu->render());
    }

    public function test_order_receipt_email_renders_in_armenian_english_and_russian(): void
    {
        $vendor = Vendor::create([
            'name' => 'Bistro Cafe',
            'slug' => 'bistro-cafe-test',
            'email' => 'bistro@example.com',
            'currency' => 'AMD',
            'is_active' => true,
        ]);

        $location = Location::create([
            'vendor_id' => $vendor->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch',
            'is_active' => true,
        ]);

        $order = Order::create([
            'vendor_id' => $vendor->id,
            'location_id' => $location->id,
            'order_number' => 'ORD-777',
            'customer_name' => 'David',
            'customer_email' => 'david@example.com',
            'total_amount' => 5000,
            'status' => 'pending',
            'type' => 'dine_in',
        ]);

        // Armenian
        $mailHy = new OrderReceiptMail($order, 'hy');
        $this->assertStringContainsString('հաջողությամբ ընդունվել է', $mailHy->envelope()->subject);
        $this->assertStringContainsString('Պատվերն ընդունված է', $mailHy->render());

        // English
        $mailEn = new OrderReceiptMail($order, 'en');
        $this->assertStringContainsString('has been successfully received', $mailEn->envelope()->subject);
        $this->assertStringContainsString('Order Receipt', $mailEn->render());

        // Russian
        $mailRu = new OrderReceiptMail($order, 'ru');
        $this->assertStringContainsString('успешно принят', $mailRu->envelope()->subject);
        $this->assertStringContainsString('Чек заказа', $mailRu->render());
    }

    public function test_system_test_email_renders_in_armenian_english_and_russian(): void
    {
        $conn = EmailConnection::first();

        // Armenian
        $mailHy = new SystemTestMail('test@menu.am', $conn, null, null, 'hy');
        $this->assertStringContainsString('Թեստային', $mailHy->envelope()->subject);

        // English
        $mailEn = new SystemTestMail('test@menu.am', $conn, null, null, 'en');
        $this->assertStringContainsString('Test Email', $mailEn->envelope()->subject);

        // Russian
        $mailRu = new SystemTestMail('test@menu.am', $conn, null, null, 'ru');
        $this->assertStringContainsString('Тестовое письмо', $mailRu->envelope()->subject);
    }

    public function test_two_factor_auth_service_sends_email_in_user_locale(): void
    {
        Mail::fake();

        $userRu = User::factory()->create([
            'name' => 'Russian Partner',
            'email' => 'partner_ru@example.com',
            'locale' => 'ru',
            'two_factor_enabled' => true,
            'two_factor_type' => 'email',
        ]);

        TwoFactorAuthService::sendEmailCode($userRu, 'login');

        Mail::assertSent(TwoFactorCodeMail::class, function ($mail) {
            return $mail->hasTo('partner_ru@example.com')
                && $mail->locale === 'ru'
                && str_contains($mail->envelope()->subject, 'двухфакторной');
        });
    }

    public function test_forgot_password_sends_email_in_user_preferred_locale(): void
    {
        Mail::fake();
        session(['captcha_answer' => '12']);

        $userRu = User::factory()->create([
            'name' => 'Russian User',
            'email' => 'user_ru@example.com',
            'locale' => 'ru',
        ]);

        $this->post('/forgot-password', [
            'email' => 'user_ru@example.com',
            'captcha' => '12',
        ]);

        Mail::assertSent(ResetPasswordMail::class, function ($mail) {
            return $mail->hasTo('user_ru@example.com')
                && $mail->locale === 'ru'
                && str_contains($mail->envelope()->subject, 'Запрос на восстановление пароля');
        });
    }

    public function test_forgot_password_sends_email_in_active_session_locale_if_user_locale_null(): void
    {
        Mail::fake();
        session(['captcha_answer' => '12']);

        $user = User::factory()->create([
            'name' => 'No Locale User',
            'email' => 'nolocale@example.com',
            'locale' => null,
        ]);

        $this->withSession(['app_locale' => 'ru', 'captcha_answer' => '12'])
            ->withCookie('app_locale', 'ru')
            ->post('/forgot-password', [
                'email' => 'nolocale@example.com',
                'captcha' => '12',
            ]);

        Mail::assertSent(ResetPasswordMail::class, function ($mail) {
            return $mail->hasTo('nolocale@example.com')
                && $mail->locale === 'ru'
                && str_contains($mail->envelope()->subject, 'Запрос на восстановление пароля');
        });
    }

    public function test_partner_registration_in_russian_queues_welcome_mail_in_russian(): void
    {
        Mail::fake();

        $payload = [
            'name' => 'Русское Кафе',
            'type' => 'restaurant',
            'expected_locations_count' => 1,
            'operating_address' => 'Ереван, ул. Пушкина 10',
            'legal_name' => 'ООО Русское Кафе',
            'legal_address' => 'Ереван, ул. Пушкина 10',
            'tax_id' => '02468135',
            'director_name' => 'Иван Иванов',
            'contact_person_name' => 'Иван Иванов',
            'phone' => '+37499112233',
            'email' => 'ivan@russiancafe.com',
            'subscription_plan' => 'pro',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ];

        $response = $this->withSession(['app_locale' => 'ru'])
            ->withCookie('app_locale', 'ru')
            ->post('/register', $payload);

        $response->assertRedirect('/email/verify');

        $createdUser = User::where('email', 'ivan@russiancafe.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertEquals('ru', $createdUser->locale);

        Mail::assertQueued(VendorWelcomeVerificationMail::class, function ($mail) {
            return $mail->hasTo('ivan@russiancafe.com')
                && $mail->locale === 'ru'
                && str_contains($mail->envelope()->subject, 'Добро пожаловать');
        });
    }
}
