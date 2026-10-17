<?php

use App\Models\EmailTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $translationsMap = [
            'welcome_verification' => [
                'hy' => [
                    'subject' => 'Բարի գալուստ {{ site_name }} — Հաստատեք ձեր էլ․ հասցեն',
                    'intro_text' => 'Շնորհակալություն {{ site_name }} հարթակում ձեր ռեստորանը գրանցելու համար։ Ձեր թվային մենյուն և վահանակն ակտիվացնելու համար խնդրում ենք հաստատել ձեր էլ․ փոստի հասցեն։',
                    'cta_text' => 'Հաստատել Էլ․ Հասցեն',
                    'footer_text' => 'Եթե կոճակը չի աշխատում, պատճենեք հաստատման հղումը ձեր բրաուզերում։ Բոլոր իրավունքները պաշտպանված են։',
                ],
                'en' => [
                    'subject' => 'Welcome to {{ site_name }} — Verify your email',
                    'intro_text' => 'Thank you for registering your restaurant on {{ site_name }}. To activate your digital menu and dashboard, please verify your email address.',
                    'cta_text' => 'Verify Email Address',
                    'footer_text' => 'If the button does not work, copy and paste the verification link into your browser. All rights reserved.',
                ],
                'ru' => [
                    'subject' => 'Добро пожаловать в {{ site_name }} — Подтвердите вашу эл. почту',
                    'intro_text' => 'Благодарим вас за регистрацию вашего заведения на платформе {{ site_name }}. Чтобы активировать электронное меню и панель управления, подтвердите ваш адрес эл. почты.',
                    'cta_text' => 'Подтвердить эл. почту',
                    'footer_text' => 'Если кнопка не работает, скопируйте ссылку для подтверждения в браузер. Все права защищены.',
                ],
            ],
            'order_receipt' => [
                'hy' => [
                    'subject' => 'Ձեր պատվերը #{{ order_number }} հաջողությամբ ընդունվել է',
                    'intro_text' => 'Շնորհակալություն {{ vendor_name }} ռեստորանից պատվիրելու համար։ Ձեր պատվերն ընդունված է և փոխանցվել է խոհանոց։',
                    'cta_text' => 'Պատվերն ընդունված է',
                    'footer_text' => 'Հարցերի դեպքում կարող եք դիմել ռեստորանի աշխատակազմին կամ մեր աջակցման կենտրոնին։',
                ],
                'en' => [
                    'subject' => 'Your order #{{ order_number }} has been successfully received',
                    'intro_text' => 'Thank you for ordering from {{ vendor_name }}. Your order has been accepted and sent to the kitchen.',
                    'cta_text' => 'Order Accepted',
                    'footer_text' => 'If you have any questions, please contact the restaurant staff or our support team.',
                ],
                'ru' => [
                    'subject' => 'Ваш заказ #{{ order_number }} успешно принят',
                    'intro_text' => 'Спасибо за заказ в {{ vendor_name }}. Ваш заказ принят и передан на кухню.',
                    'cta_text' => 'Заказ принят',
                    'footer_text' => 'По всем вопросам обращайтесь к персоналу заведения или в нашу службу поддержки.',
                ],
            ],
            'reset_password' => [
                'hy' => [
                    'subject' => 'Գաղտնաբառի Վերականգնման Հարցում — {{ site_name }}',
                    'intro_text' => 'Ողջույն, {{ user_name }}։ Մենք ստացել ենք ձեր հաշվի գաղտնաբառի վերականգնման հարցում։ Նոր գաղտնաբառ սահմանելու համար սեղմեք ստորև գտնվող կոճակը։',
                    'cta_text' => 'Վերականգնել Գաղտնաբառը',
                    'footer_text' => 'Այս հղումը վավեր է {{ expire_minutes }} րոպե։ Եթե դուք չեք կատարել հարցում, անտեսեք այս նամակը։',
                ],
                'en' => [
                    'subject' => 'Password Reset Request — {{ site_name }}',
                    'intro_text' => 'Hello, {{ user_name }}. We received a request to reset your account password. Click the button below to set a new password.',
                    'cta_text' => 'Reset Password',
                    'footer_text' => 'This link is valid for {{ expire_minutes }} minutes. If you did not make this request, you can safely ignore this email.',
                ],
                'ru' => [
                    'subject' => 'Запрос на восстановление пароля — {{ site_name }}',
                    'intro_text' => 'Здравствуйте, {{ user_name }}. Мы получили запрос на сброс пароля вашей учетной записи. Нажмите кнопку ниже, чтобы установить новый пароль.',
                    'cta_text' => 'Сбросить пароль',
                    'footer_text' => 'Ссылка действительна в течение {{ expire_minutes }} минут. Если вы не отправляли запрос, просто проигнорируйте это письмо.',
                ],
            ],
            'two_factor_code' => [
                'hy' => [
                    'subject' => 'Ձեր Մուտքի Երկփուլային Նույնականացման Կոդը (2FA) — {{ site_name }}',
                    'intro_text' => 'Ողջույն, {{ user_name }}։ Դուք կատարել եք {{ action_description }} հարցում։ Ձեր միանգամյա անվտանգության 2FA կոդն է՝ {{ code }}։',
                    'cta_text' => '2FA Կոդ',
                    'footer_text' => 'Կոդը գործում է {{ expire_minutes }} րոպե։ Եթե դուք չեք փորձել մուտք գործել, անհապաղ փոխեք ձեր գաղտնաբառը։',
                ],
                'en' => [
                    'subject' => 'Your Two-Factor Authentication (2FA) Code — {{ site_name }}',
                    'intro_text' => 'Hello, {{ user_name }}. You requested {{ action_description }}. Your one-time security 2FA code is: {{ code }}.',
                    'cta_text' => '2FA Code',
                    'footer_text' => 'The code expires in {{ expire_minutes }} minutes. If you did not attempt to sign in, please change your password immediately.',
                ],
                'ru' => [
                    'subject' => 'Ваш код двухфакторной аутентификации (2FA) — {{ site_name }}',
                    'intro_text' => 'Здравствуйте, {{ user_name }}. Вы запросили {{ action_description }}. Ваш одноразовый защитный код 2FA: {{ code }}.',
                    'cta_text' => 'Код 2FA',
                    'footer_text' => 'Код действует в течение {{ expire_minutes }} минут. Если вы не пытались войти, немедленно смените пароль.',
                ],
            ],
            'system_test' => [
                'hy' => [
                    'subject' => 'Թեստային Նամակ — {{ connection_name }} ({{ timestamp }})',
                    'intro_text' => 'Սա թեստային նամակ է {{ site_name }} Email Management համակարգից։ Եթե ստացել եք այս նամակը, ձեր SMTP կապը և ուղարկողի ինքնությունը կարգավորված են ճիշտ։',
                    'cta_text' => 'Անցնել Վահանակ',
                    'footer_text' => 'Ուղարկված է SuperAdmin Email Diagnostics գործիքի միջոցով։',
                ],
                'en' => [
                    'subject' => 'Test Email — {{ connection_name }} ({{ timestamp }})',
                    'intro_text' => 'This is an automated test email from the {{ site_name }} Email Management system. If you received this, your connection and sender are configured properly.',
                    'cta_text' => 'Open Dashboard',
                    'footer_text' => 'Dispatched via SuperAdmin Email Diagnostics.',
                ],
                'ru' => [
                    'subject' => 'Тестовое письмо — {{ connection_name }} ({{ timestamp }})',
                    'intro_text' => 'Это тестовое письмо из системы управления почтой {{ site_name }}. Если вы получили его, настройки подключения и отправителя работают корректно.',
                    'cta_text' => 'В панель управления',
                    'footer_text' => 'Отправлено через инструмент диагностики SuperAdmin Email.',
                ],
            ],
        ];

        foreach ($translationsMap as $key => $translations) {
            EmailTemplate::where('key', $key)->update([
                'translations' => $translations,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        EmailTemplate::query()->update([
            'translations' => null,
        ]);
    }
};
