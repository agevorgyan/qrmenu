<!DOCTYPE html>
<html lang="hy">
<head>
    <meta charset="UTF-8">
    <title>Գաղտնաբառի Վերականգնում</title>
</head>
<body style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; color: #0f172a;">
    <div style="max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 32px; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);">
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="display: inline-block; width: 48px; height: 48px; border-radius: 12px; background: #fffbeb; border: 1px solid #fde68a; line-height: 48px; font-size: 22px;">
                🔑
            </div>
            <h2 style="font-size: 20px; font-weight: 800; color: #0f172a; margin: 12px 0 4px;">Գաղտնաբառի Վերականգնում</h2>
            <p style="font-size: 14px; color: #64748b; margin: 0;">QRMenu SaaS Կառավարման Համակարգ</p>
        </div>

        @php
            $customTpl = app(\App\Services\EmailTemplateService::class)->render('reset_password', [
                'user_name' => $user->name,
                'reset_url' => $resetUrl,
                'expire_minutes' => '60',
                'support_email' => 'support@menu.elab.am',
                'site_name' => \App\Models\SystemSetting::getSiteName(),
            ]);
        @endphp

        @if($customTpl && !empty($customTpl['intro']))
            <p style="font-size: 15px; color: #334155; line-height: 1.5;">{!! nl2br(e($customTpl['intro'])) !!}</p>
        @else
            <p style="font-size: 15px; color: #334155; line-height: 1.5;">
                Ողջույն, <strong>{{ $user->name }}</strong>։ Մենք ստացել ենք ձեր հաշվի գաղտնաբառի վերականգնման հարցում։
            </p>
            <p style="font-size: 14px; color: #475569; line-height: 1.5;">
                Նոր գաղտնաբառ սահմանելու համար սեղմեք ստորև գտնվող կոճակը․
            </p>
        @endif

        <div style="text-align: center; margin: 28px 0;">
            <a href="{{ $resetUrl }}" style="display: inline-block; padding: 12px 28px; background: linear-gradient(135deg, #f59e0b, #ea580c); color: #ffffff; font-weight: 700; text-decoration: none; border-radius: 10px; font-size: 15px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35);">
                {{ $customTpl['cta_text'] ?? 'Վերականգնել Գաղտնաբառը' }}
            </a>
            <p style="font-size: 12px; color: #94a3b8; margin-top: 10px;">Այս հղումը վավեր է 60 րոպե</p>
        </div>

        <p style="font-size: 13px; color: #64748b; line-height: 1.5; border-top: 1px solid #f1f5f9; padding-top: 16px;">
            {{ $customTpl['footer'] ?? 'Եթե դուք չեք կատարել գաղտնաբառի վերականգնման հարցում, անտեսեք այս նամակը։ Ձեր հաշիվը գտնվում է ապահով վիճակում։' }}
        </p>

        <div style="text-align: center; margin-top: 24px; font-size: 12px; color: #94a3b8;">
            &copy; {{ date('Y') }} {{ \App\Models\SystemSetting::getSiteName() }} SaaS Platform
        </div>
    </div>
</body>
</html>
