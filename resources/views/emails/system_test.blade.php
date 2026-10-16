<!DOCTYPE html>
<html lang="hy">
<head>
    <meta charset="UTF-8">
    <title>Թեստային Էլ․ Նամակ</title>
</head>
<body style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; color: #0f172a;">
    <div style="max-width: 540px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 32px; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);">
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="display: inline-block; width: 48px; height: 48px; border-radius: 12px; background: #eff6ff; border: 1px solid #bfdbfe; line-height: 48px; font-size: 22px;">
                ✉️
            </div>
            <h2 style="font-size: 20px; font-weight: 800; color: #0f172a; margin: 12px 0 4px;">Թեստային Էլ․ Նամակ (SMTP Test)</h2>
            <p style="font-size: 14px; color: #64748b; margin: 0;">{{ \App\Models\SystemSetting::getSiteName() }} Email Management</p>
        </div>

        <p style="font-size: 15px; color: #334155; line-height: 1.5;">
            Ողջույն։ Սա ավտոմատ թեստային նամակ է SuperAdmin Email Diagnostics գործիքից։
        </p>

        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin: 20px 0; font-size: 13px;">
            <div style="margin-bottom: 8px;"><strong>Կապի անվանում:</strong> {{ $emailConnection->name }}</div>
            <div style="margin-bottom: 8px;"><strong>Transport / Driver:</strong> {{ strtoupper($emailConnection->transport) }}</div>
            <div style="margin-bottom: 8px;"><strong>Host / Port:</strong> {{ $emailConnection->host ?? 'localhost' }}:{{ $emailConnection->port }} ({{ strtoupper($emailConnection->encryption ?? 'none') }})</div>
            <div style="margin-bottom: 8px;"><strong>Ուղարկող (Sender):</strong> {{ $sender?->name ?? 'System' }} &lt;{{ $sender?->email ?? config('mail.from.address') }}&gt;</div>
            <div style="margin-bottom: 8px;"><strong>Ստացող (Recipient):</strong> {{ $recipientEmail }}</div>
            <div><strong>Ամսաթիվ / Ժամ:</strong> {{ now()->toDateTimeString() }}</div>
        </div>

        @if(!empty($notes))
            <p style="font-size: 14px; color: #475569; line-height: 1.5;">
                <strong>Նշումներ:</strong> {{ $notes }}
            </p>
        @endif

        <p style="font-size: 13px; color: #10b981; font-weight: 600; line-height: 1.5; border-top: 1px solid #f1f5f9; padding-top: 16px;">
            ✓ SMTP միացումը, իսկորոշումը (authentication) և տրանսպորտային առաքումը հաջողությամբ աշխատում են։
        </p>

        <div style="text-align: center; margin-top: 24px; font-size: 12px; color: #94a3b8;">
            &copy; {{ date('Y') }} {{ \App\Models\SystemSetting::getSiteName() }} SaaS Platform
        </div>
    </div>
</body>
</html>
