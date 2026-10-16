<?php

namespace Database\Seeders;

use App\Models\EmailConnection;
use App\Models\EmailRoutingRule;
use App\Models\EmailSenderIdentity;
use App\Models\EmailTemplate;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class EmailManagementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Primary Default Connection
        $connection = EmailConnection::firstOrCreate(
            ['name' => 'Primary cPanel SMTP'],
            [
                'transport' => config('mail.default') === 'array' ? 'array' : 'smtp',
                'host' => config('mail.mailers.smtp.host') ?: '127.0.0.1',
                'port' => (int) (config('mail.mailers.smtp.port') ?: 587),
                'encryption' => 'tls',
                'username' => config('mail.mailers.smtp.username'),
                'password' => config('mail.mailers.smtp.password'),
                'timeout' => 15,
                'is_active' => true,
                'is_default' => true,
            ]
        );

        // 2. Default Sender Identities
        $defaultSender = EmailSenderIdentity::firstOrCreate(
            ['email' => config('mail.from.address', 'no-reply@menu.elab.am')],
            [
                'name' => config('mail.from.name', 'QRMenu Platform'),
                'description' => 'Default system & transactional notification sender',
                'reply_to_email' => 'support@menu.elab.am',
                'reply_to_name' => 'QRMenu Support',
                'is_active' => true,
                'is_default' => true,
                'email_connection_id' => $connection->id,
                'assigned_categories' => array_keys(EmailRoutingRule::categories()),
                'verification_status' => 'verified',
            ]
        );

        EmailSenderIdentity::firstOrCreate(
            ['email' => 'support@menu.elab.am'],
            [
                'name' => 'QRMenu Customer Support',
                'description' => 'Dedicated helpdesk and customer communications',
                'reply_to_email' => 'support@menu.elab.am',
                'reply_to_name' => 'QRMenu Support Team',
                'is_active' => true,
                'is_default' => false,
                'email_connection_id' => $connection->id,
                'assigned_categories' => [
                    EmailRoutingRule::CATEGORY_SECURITY_ALERT,
                    EmailRoutingRule::CATEGORY_ADMIN_ALERT,
                ],
                'verification_status' => 'verified',
            ]
        );

        // 3. Routing Rules for All Standard Categories
        foreach (EmailRoutingRule::categories() as $category => $meta) {
            EmailRoutingRule::firstOrCreate(
                ['category' => $category],
                [
                    'name' => $meta['label'],
                    'description' => $meta['description'],
                    'sender_identity_id' => $defaultSender->id,
                    'email_connection_id' => $connection->id,
                    'is_active' => true,
                    'priority' => 0,
                    'fallback_behavior' => 'default_sender',
                ]
            );
        }

        // 4. Default Email Templates
        $siteName = SystemSetting::getSiteName();

        $templates = [
            [
                'key' => 'welcome_verification',
                'name' => 'Vendor Welcome & Email Verification',
                'category' => EmailRoutingRule::CATEGORY_VENDOR_ONBOARDING,
                'subject' => 'Բարի գալուստ {{ site_name }} — Հաստատեք ձեր էլ․ հասցեն',
                'intro_text' => 'Շնորհակալություն {{ site_name }} հարթակում ձեր ռեստորանը գրանցելու համար։ Ձեր թվային մենյուն և վահանակն ակտիվացնելու համար խնդրում ենք հաստատել ձեր էլ․ փոստի հասցեն։',
                'cta_text' => 'Հաստատել Էլ․ Հասցեն',
                'cta_url_template' => '{{ verification_url }}',
                'footer_text' => 'Եթե կոճակը չի աշխատում, պատճենեք հաստատման հղումը ձեր բրաուզերում։ Բոլոր իրավունքները պաշտպանված են։',
                'support_contact' => 'support@menu.elab.am',
                'allowed_variables' => ['user_name', 'vendor_name', 'verification_url', 'support_email', 'site_name'],
                'is_active' => true,
                'version' => 1,
            ],
            [
                'key' => 'order_receipt',
                'name' => 'Customer Order Receipt & Confirmation',
                'category' => EmailRoutingRule::CATEGORY_ORDER_RECEIPT,
                'subject' => 'Ձեր պատվերը #{{ order_number }} հաջողությամբ ընդունվել է',
                'intro_text' => 'Շնորհակալություն {{ vendor_name }} ռեստորանից պատվիրելու համար։ Ձեր պատվերն ընդունված է և փոխանցվել է խոհանոց։',
                'cta_text' => 'Դիտել Պատվերի Կարգավիճակը',
                'cta_url_template' => '',
                'footer_text' => 'Հարցերի դեպքում կարող եք դիմել ռեստորանի աշխատակազմին կամ մեր աջակցման կենտրոնին։',
                'support_contact' => 'support@menu.elab.am',
                'allowed_variables' => ['customer_name', 'vendor_name', 'order_number', 'order_type', 'total_amount', 'support_email', 'site_name'],
                'is_active' => true,
                'version' => 1,
            ],
            [
                'key' => 'reset_password',
                'name' => 'Password Reset Link',
                'category' => EmailRoutingRule::CATEGORY_PASSWORD_RESET,
                'subject' => 'Գաղտնաբառի Վերականգնման Հարցում — {{ site_name }}',
                'intro_text' => 'Ողջույն, {{ user_name }}։ Մենք ստացել ենք ձեր հաշվի գաղտնաբառի վերականգնման հարցում։ Նոր գաղտնաբառ սահմանելու համար սեղմեք ստորև գտնվող կոճակը։',
                'cta_text' => 'Վերականգնել Գաղտնաբառը',
                'cta_url_template' => '{{ reset_url }}',
                'footer_text' => 'Այս հղումը վավեր է {{ expire_minutes }} րոպե։ Եթե դուք չեք կատարել հարցում, անտեսեք այս նամակը։',
                'support_contact' => 'support@menu.elab.am',
                'allowed_variables' => ['user_name', 'reset_url', 'expire_minutes', 'support_email', 'site_name'],
                'is_active' => true,
                'version' => 1,
            ],
            [
                'key' => 'two_factor_code',
                'name' => 'Two-Factor Authentication Security Code',
                'category' => EmailRoutingRule::CATEGORY_TWO_FACTOR,
                'subject' => 'Ձեր Մուտքի Երկփուլային Նույնականացման Կոդը (2FA) — {{ site_name }}',
                'intro_text' => 'Ողջույն, {{ user_name }}։ Դուք կատարել եք {{ action_description }} հարցում։ Ձեր միանգամյա անվտանգության 2FA կոդն է՝ {{ code }}։',
                'cta_text' => '',
                'cta_url_template' => '',
                'footer_text' => 'Կոդը գործում է {{ expire_minutes }} րոպե։ Եթե դուք չեք փորձել մուտք գործել, անհապաղ փոխեք ձեր գաղտնաբառը։',
                'support_contact' => 'support@menu.elab.am',
                'allowed_variables' => ['user_name', 'code', 'action_description', 'expire_minutes', 'support_email', 'site_name'],
                'is_active' => true,
                'version' => 1,
            ],
            [
                'key' => 'system_test',
                'name' => 'System & Connection Diagnostics Test',
                'category' => EmailRoutingRule::CATEGORY_SYSTEM_TEST,
                'subject' => 'Թեստային Նամակ — {{ connection_name }} ({{ timestamp }})',
                'intro_text' => 'Սա թեստային նամակ է {{ site_name }} Email Management համակարգից։ Եթե ստացել եք այս նամակը, ձեր SMTP կապը և ուղարկողի ինքնությունը կարգավորված են ճիշտ։',
                'cta_text' => 'Անցնել Վահանակ',
                'cta_url_template' => '',
                'footer_text' => 'Ուղարկված է SuperAdmin Email Diagnostics գործիքի միջոցով։',
                'support_contact' => 'support@menu.elab.am',
                'allowed_variables' => ['recipient_email', 'timestamp', 'connection_name', 'site_name'],
                'is_active' => true,
                'version' => 1,
            ],
        ];

        foreach ($templates as $tpl) {
            EmailTemplate::firstOrCreate(
                ['key' => $tpl['key']],
                $tpl
            );
        }
    }
}
