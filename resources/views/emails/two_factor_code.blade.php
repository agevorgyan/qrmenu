@php
    $targetLocale = $locale ?? ($user->locale ?? app()->getLocale());
    $siteName = \App\Models\SystemSetting::getSiteName();
    $actionDesc = match($action ?? 'login') {
        'password' => __('password change', [], $targetLocale),
        'email' => __('email address change', [], $targetLocale),
        'setup' => __('two-factor authentication setup', [], $targetLocale),
        default => __('account login', [], $targetLocale),
    };
    $customTpl = app(\App\Services\EmailTemplateService::class)->render('two_factor_code', [
        'user_name' => $user->name,
        'code' => $code,
        'action_description' => $actionDesc,
        'expire_minutes' => 10,
        'support_email' => 'support@menu.elab.am',
        'site_name' => $siteName,
    ], $targetLocale);
@endphp
<!DOCTYPE html>
<html lang="{{ $targetLocale }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Two-Factor Authentication (2FA)', [], $targetLocale) }}</title>
</head>
<body style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; color: #0f172a;">
    <div style="max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 32px; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);">
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="display: inline-block; width: 48px; height: 48px; border-radius: 12px; background: #fffbeb; border: 1px solid #fde68a; line-height: 48px; font-size: 22px;">
                🔐
            </div>
            <h2 style="font-size: 20px; font-weight: 800; color: #0f172a; margin: 12px 0 4px;">{{ __('Two-Factor Authentication (2FA)', [], $targetLocale) }}</h2>
            <p style="font-size: 14px; color: #64748b; margin: 0;">{{ __(':site_name SaaS Management System', ['site_name' => $siteName], $targetLocale) }}</p>
        </div>

        @if($customTpl && !empty($customTpl['intro']))
            <p style="font-size: 15px; color: #334155; line-height: 1.5;">{!! nl2br(e($customTpl['intro'])) !!}</p>
        @else
            <p style="font-size: 15px; color: #334155; line-height: 1.5;">
                {{ __('Hello, :name. You have requested a :action. Your one-time security 2FA code is:', ['name' => $user->name, 'action' => $actionDesc], $targetLocale) }}
            </p>
        @endif

        <div style="text-align: center; margin: 24px 0;">
            <div style="display: inline-block; padding: 14px 28px; background: #f8fafc; border: 2px dashed #f59e0b; border-radius: 12px; font-size: 28px; font-weight: 800; letter-spacing: 8px; color: #d97706; font-family: monospace;">
                {{ $code }}
            </div>
            <p style="font-size: 12px; color: #94a3b8; margin-top: 8px;">{{ __('Code expires in :minutes minutes', ['minutes' => 10], $targetLocale) }}</p>
        </div>

        <p style="font-size: 13px; color: #64748b; line-height: 1.5; border-top: 1px solid #f1f5f9; padding-top: 16px;">
            {{ __('If you did not attempt this action, please change your password immediately and contact support (:contact).', ['contact' => $customTpl['support_contact'] ?? 'support@menu.elab.am'], $targetLocale) }}
        </p>

        <div style="text-align: center; margin-top: 24px; font-size: 12px; color: #94a3b8;">
            {{ $customTpl['footer'] ?? ('© ' . date('Y') . ' ' . $siteName . ' SaaS Platform') }}
        </div>
    </div>
</body>
</html>
