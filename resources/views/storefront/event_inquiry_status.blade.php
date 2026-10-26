<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <title>{{ __('Inquiry Details') }} — {{ $inquiry->reference }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Noto+Sans+Armenian:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', 'Noto Sans Armenian', sans-serif;
            background: #0f172a;
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
            margin: 0;
            box-sizing: border-box;
        }
        .status-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 20px;
            max-width: 520px;
            width: 100%;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
        }
        .header {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .icon-circle {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 1rem;
        }
        .ref-pill {
            display: inline-block;
            background: #0f172a;
            border: 1px solid #334155;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 700;
            color: #94a3b8;
            margin-top: 0.5rem;
        }
        .grid {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }
        .row {
            display: flex;
            justify-content: space-between;
            padding: 0.6rem 0;
            border-bottom: 1px dashed #1e293b;
            font-size: 0.9rem;
        }
        .row:last-child { border-bottom: none; }
        .label { color: #94a3b8; }
        .val { font-weight: 700; }
        .btn {
            display: block;
            width: 100%;
            background: #3b82f6;
            color: #ffffff;
            text-align: center;
            padding: 0.85rem;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <div class="status-card">
        <div class="header">
            <div class="icon-circle">
                <i class="fa-solid fa-champagne-glasses"></i>
            </div>
            <h1 style="font-size: 1.4rem; font-weight: 800; margin: 0;">{{ $vendor->name }}</h1>
            <div class="ref-pill">{{ __('Reference') }}: {{ $inquiry->reference }}</div>
        </div>

        <div class="grid">
            <div class="row">
                <span class="label">{{ __('Status') }}</span>
                <span class="val">{{ $inquiry->getStatusLabel() }}</span>
            </div>
            <div class="row">
                <span class="label">{{ __('Event Type') }}</span>
                <span class="val">{{ $inquiry->getEventTypeLabel() }}</span>
            </div>
            <div class="row">
                <span class="label">{{ __('Event Date') }}</span>
                <span class="val">{{ $inquiry->getFormattedDate() }}</span>
            </div>
            <div class="row">
                <span class="label">{{ __('Guest Count') }}</span>
                <span class="val">{{ $inquiry->guest_count }}</span>
            </div>
            <div class="row">
                <span class="label">{{ __('Customer') }}</span>
                <span class="val">{{ $inquiry->customer_name }}</span>
            </div>
        </div>

        <a href="{{ route('client.menu', ['vendor_slug' => $vendor->slug]) }}" class="btn">
            {{ __('Back to Menu') }}
        </a>
    </div>
</body>
</html>
