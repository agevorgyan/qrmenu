@php
    $targetLocale = $locale ?? app()->getLocale();
    $siteName = \App\Models\SystemSetting::getSiteName();
@endphp
<!DOCTYPE html>
<html lang="{{ $targetLocale }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Subscription Plan Downgraded', [], $targetLocale) }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
        .card { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 32px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { text-align: center; margin-bottom: 24px; }
        .logo { font-size: 24px; font-weight: 800; color: #2563eb; letter-spacing: -0.5px; }
        h1 { font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 16px; margin-bottom: 8px; }
        p { font-size: 15px; line-height: 1.6; color: #475569; margin: 8px 0; }
        .info-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 16px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 24px; font-size: 12px; color: #94a3b8; }
        .btn-box { text-align: center; margin: 24px 0; }
        .btn { display: inline-block; background: #2563eb; color: #ffffff !important; font-weight: 600; font-size: 14px; padding: 12px 24px; border-radius: 10px; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div class="logo">💎 {{ $siteName }}</div>
            <h1>{{ __('Plan Downgraded', [], $targetLocale) }}</h1>
        </div>

        <p>{{ __('Dear :name Partner,', ['name' => $vendor->name], $targetLocale) }}</p>

        <p>{{ __('Your previous billing period has ended, and your account has now successfully transitioned to the ":plan" plan.', ['plan' => __($targetPlan->name, [], $targetLocale)], $targetLocale) }}</p>

        <div class="info-box">
            <div style="font-weight: 700; color: #166534; font-size: 14px; margin-bottom: 6px;">
                ✓ {{ __('Current Active Plan', [], $targetLocale) }}
            </div>
            <div style="font-size: 14px; color: #15803d; line-height: 1.5;">
                • <strong>{{ __('Plan Name:', [], $targetLocale) }}</strong> {{ __($targetPlan->name, [], $targetLocale) }}<br>
                • <strong>{{ __('Status:', [], $targetLocale) }}</strong> {{ __('Active', [], $targetLocale) }}<br>
                • <strong>{{ __('Updated Features:', [], $targetLocale) }}</strong> {{ __('Your limits and accessible modules have been aligned to this tier.', [], $targetLocale) }}
            </div>
        </div>

        <p>{{ __('You can upgrade or renew your subscription at any time from your administration panel.', [], $targetLocale) }}</p>

        <div class="btn-box">
            <a href="{{ route('admin.subscription.index') }}" class="btn" target="_blank">{{ __('View Subscription', [], $targetLocale) }}</a>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} {{ $siteName }}. {{ __('All rights reserved.', [], $targetLocale) }}
        </div>
    </div>
</body>
</html>

