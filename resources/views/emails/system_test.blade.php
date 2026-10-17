@php
    $targetLocale = $locale ?? app()->getLocale();
    $siteName = \App\Models\SystemSetting::getSiteName();
    $timestamp = now()->toDateTimeString();
    $customTpl = app(\App\Services\EmailTemplateService::class)->render('system_test', [
        'recipient_email' => $recipientEmail,
        'timestamp' => $timestamp,
        'connection_name' => $emailConnection->name,
        'site_name' => $siteName,
    ], $targetLocale);
@endphp
<!DOCTYPE html>
<html lang="{{ $targetLocale }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Test Email (SMTP Test)', [], $targetLocale) }}</title>
</head>
<body style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; color: #0f172a;">
    <div style="max-width: 540px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 32px; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);">
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="display: inline-block; width: 48px; height: 48px; border-radius: 12px; background: #eff6ff; border: 1px solid #bfdbfe; line-height: 48px; font-size: 22px;">
                ✉️
            </div>
            <h2 style="font-size: 20px; font-weight: 800; color: #0f172a; margin: 12px 0 4px;">{{ __('Test Email (SMTP Test)', [], $targetLocale) }}</h2>
            <p style="font-size: 14px; color: #64748b; margin: 0;">{{ __(':site_name Email Management', ['site_name' => $siteName], $targetLocale) }}</p>
        </div>

        <p style="font-size: 15px; color: #334155; line-height: 1.5;">
            {{ $customTpl['intro'] ?? __('Hello. This is an automated test email from SuperAdmin Email Diagnostics.', [], $targetLocale) }}
        </p>

        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin: 20px 0; font-size: 13px;">
            <div style="margin-bottom: 8px;"><strong>{{ __('Connection Name:', [], $targetLocale) }}</strong> {{ $emailConnection->name }}</div>
            <div style="margin-bottom: 8px;"><strong>{{ __('Transport / Driver:', [], $targetLocale) }}</strong> {{ strtoupper($emailConnection->transport) }}</div>
            <div style="margin-bottom: 8px;"><strong>{{ __('Host / Port:', [], $targetLocale) }}</strong> {{ $emailConnection->host ?? 'localhost' }}:{{ $emailConnection->port }} ({{ strtoupper($emailConnection->encryption ?? 'none') }})</div>
            <div style="margin-bottom: 8px;"><strong>{{ __('Sender:', [], $targetLocale) }}</strong> {{ $sender?->name ?? 'System' }} &lt;{{ $sender?->email ?? config('mail.from.address') }}&gt;</div>
            <div style="margin-bottom: 8px;"><strong>{{ __('Recipient:', [], $targetLocale) }}</strong> {{ $recipientEmail }}</div>
            <div><strong>{{ __('Date / Time:', [], $targetLocale) }}</strong> {{ $timestamp }}</div>
        </div>

        @if(!empty($notes))
            <p style="font-size: 14px; color: #475569; line-height: 1.5;">
                <strong>{{ __('Notes:', [], $targetLocale) }}</strong> {{ $notes }}
            </p>
        @endif

        <p style="font-size: 13px; color: #10b981; font-weight: 600; line-height: 1.5; border-top: 1px solid #f1f5f9; padding-top: 16px;">
            {{ __('✓ SMTP connection, authentication, and transport delivery are working successfully.', [], $targetLocale) }}
        </p>

        <div style="text-align: center; margin-top: 24px; font-size: 12px; color: #94a3b8;">
            {{ $customTpl['footer'] ?? ('&copy; ' . date('Y') . ' ' . $siteName . ' SaaS Platform') }}
        </div>
    </div>
</body>
</html>
