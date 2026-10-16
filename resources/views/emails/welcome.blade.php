<!DOCTYPE html>
<html lang="hy">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Բարի գալուստ QRMenu</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
        .card { max-width: 540px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 32px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { text-align: center; margin-bottom: 24px; }
        .logo { font-size: 24px; font-weight: 800; color: #e11d48; letter-spacing: -0.5px; }
        h1 { font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 16px; margin-bottom: 8px; }
        p { font-size: 15px; line-height: 1.6; color: #475569; margin: 8px 0; }
        .btn-box { text-align: center; margin: 28px 0; }
        .btn { display: inline-block; background: #e11d48; color: #ffffff !important; font-weight: 600; font-size: 15px; padding: 12px 28px; border-radius: 9999px; text-decoration: none; box-shadow: 0 4px 14px rgba(225,29,72,0.3); }
        .footer { text-align: center; margin-top: 24px; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
    @php
        $customTpl = app(\App\Services\EmailTemplateService::class)->render('welcome_verification', [
            'user_name' => $user->name,
            'vendor_name' => $user->vendor?->name ?? 'QRMenu',
            'verification_url' => $verificationUrl,
            'support_email' => 'support@menu.elab.am',
            'site_name' => \App\Models\SystemSetting::getSiteName(),
        ]);
    @endphp
    <div class="card">
        <div class="header">
            <div class="logo">🍽️ {{ \App\Models\SystemSetting::getSiteName() }}</div>
            <h1>Բարի գալուստ, {{ $user->name }}!</h1>
        </div>
        @if($customTpl && !empty($customTpl['intro']))
            <p>{!! nl2br(e($customTpl['intro'])) !!}</p>
        @else
            <p>Շնորհակալություն QRMenu հարթակում ձեր ռեստորանը գրանցելու համար։</p>
            <p>Ձեր թվային մենյուն և վահանակն ակտիվացնելու համար խնդրում ենք հաստատել ձեր էլ․ փոստի հասցեն՝ սեղմելով ներքևի կոճակը։</p>
        @endif
        
        <div class="btn-box">
            <a href="{{ $verificationUrl }}" class="btn" target="_blank">{{ $customTpl['cta_text'] ?? 'Հաստատել Էլ․ Հասցեն' }}</a>
        </div>

        @if(!empty($otpCode))
            <div style="text-align: center; margin: 24px 0; background: #f8fafc; border: 1px dashed #cbd5e1; padding: 16px; border-radius: 12px;">
                <div style="font-size: 13px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Կամ մուտքագրեք 6-նիշ ստուգիչ կոդը</div>
                <div style="font-size: 32px; font-weight: 800; letter-spacing: 6px; color: #0f172a; margin-top: 6px; font-family: monospace;">{{ $otpCode }}</div>
            </div>
        @endif

        <p style="font-size: 13px; color: #64748b;">Եթե կոճակը չի աշխատում, պատճենեք հետևյալ հղումը ձեր բրաուզերում՝<br><a href="{{ $verificationUrl }}" style="color: #e11d48; word-break: break-all;">{{ $verificationUrl }}</a></p>

        <div class="footer">
            {{ $customTpl['footer'] ?? ('© ' . date('Y') . ' ' . \App\Models\SystemSetting::getSiteName() . ' SaaS Platform. Բոլոր իրավունքները պաշտպանված են։') }}
        </div>
    </div>
</body>
</html>
