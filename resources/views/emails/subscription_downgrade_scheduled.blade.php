@php
    $targetLocale = $locale ?? app()->getLocale();
    $siteName = \App\Models\SystemSetting::getSiteName();
    $formattedDate = $effectiveDate ? $effectiveDate->format('d.m.Y') : '—';
@endphp
<!DOCTYPE html>
<html lang="{{ $targetLocale }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Scheduled Plan Downgrade Confirmation', [], $targetLocale) }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
        .card { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 32px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { text-align: center; margin-bottom: 24px; }
        .logo { font-size: 24px; font-weight: 800; color: #2563eb; letter-spacing: -0.5px; }
        h1 { font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 16px; margin-bottom: 8px; }
        p { font-size: 15px; line-height: 1.6; color: #475569; margin: 8px 0; }
        .info-box { background: #fffbeb; border: 1px solid #fef3c7; border-radius: 12px; padding: 16px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 24px; font-size: 12px; color: #94a3b8; }
        .btn-box { text-align: center; margin: 24px 0; }
        .btn { display: inline-block; background: #2563eb; color: #ffffff !important; font-weight: 600; font-size: 14px; padding: 12px 24px; border-radius: 10px; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div class="logo">💎 {{ $siteName }}</div>
            <h1>{{ __('Scheduled Plan Downgrade', [], $targetLocale) }}</h1>
        </div>

        <p>{{ __('Dear :name Partner,', ['name' => $vendor->name], $targetLocale) }}</p>

        <p>{{ __('You have requested a plan change to ":plan". In accordance with our billing policy, your current paid plan remains fully active until your current period ends.', ['plan' => __($targetPlan->name, [], $targetLocale)], $targetLocale) }}</p>

        <div class="info-box">
            <div style="font-weight: 700; color: #b45309; font-size: 14px; margin-bottom: 6px;">
                ⏳ {{ __('Change Details', [], $targetLocale) }}
            </div>
            <div style="font-size: 14px; color: #92400e; line-height: 1.5;">
                • <strong>{{ __('Target Plan:', [], $targetLocale) }}</strong> {{ __($targetPlan->name, [], $targetLocale) }}<br>
                • <strong>{{ __('Effective Date:', [], $targetLocale) }}</strong> {{ $formattedDate }} ({{ __('at period end', [], $targetLocale) }})<br>
                • <strong>{{ __('Current Entitlements:', [], $targetLocale) }}</strong> {{ __('All features of your current tier remain active until this date.', [], $targetLocale) }}
            </div>
        </div>

        <p>{{ __('If you wish to cancel this downgrade and keep your current plan, you can do so at any time before :date in your dashboard.', ['date' => $formattedDate], $targetLocale) }}</p>

        <div class="btn-box">
            <a href="{{ route('admin.subscription.index') }}" class="btn" target="_blank">{{ __('Open Subscription Dashboard', [], $targetLocale) }}</a>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} {{ $siteName }}. {{ __('All rights reserved.', [], $targetLocale) }}
        </div>
    </div>
</body>
</html>

