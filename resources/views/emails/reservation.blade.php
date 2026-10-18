@php
    $targetLocale = $locale ?? app()->getLocale();
    $siteName = \App\Models\SystemSetting::getSiteName();
    $vendorName = $reservation->vendor?->name ?? $siteName;
    $dateFormatted = $reservation->getFormattedDate($targetLocale);
    $timeFormatted = $reservation->getFormattedTime();
    $tableName = $reservation->table_name ?: ($reservation->table_number ? __('Table :num', ['num' => $reservation->table_number], $targetLocale) : __('Pending assignment', [], $targetLocale));
    
    $badgeStyle = match ($event) {
        'confirmed' => 'background: #dcfce7; color: #166534;',
        'cancelled', 'rejected' => 'background: #fee2e2; color: #991b1b;',
        'modified' => 'background: #fef3c7; color: #92400e;',
        'reminder' => 'background: #e0e7ff; color: #3730a3;',
        default => 'background: #e0f2fe; color: #075985;',
    };

    $badgeLabel = match ($event) {
        'confirmed' => '✓ ' . __('Confirmed', [], $targetLocale),
        'cancelled' => '✕ ' . __('Cancelled', [], $targetLocale),
        'rejected' => '✕ ' . __('Declined', [], $targetLocale),
        'modified' => '↻ ' . __('Details Updated', [], $targetLocale),
        'reminder' => '⏰ ' . __('Upcoming Reminder', [], $targetLocale),
        default => '• ' . __('Request Received', [], $targetLocale),
    };
@endphp
<!DOCTYPE html>
<html lang="{{ $targetLocale }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tpl['subject'] ?? __('Reservation Update', [], $targetLocale) }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #334155; margin: 0; padding: 24px 12px; }
        .wrapper { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 18px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2); border: 1px solid #e2e8f0; }
        .hero { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 28px 24px; text-align: center; color: #ffffff; }
        .hero h1 { margin: 8px 0 4px; font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: -0.02em; }
        .badge { display: inline-block; font-weight: 700; font-size: 12px; padding: 5px 14px; border-radius: 9999px; text-transform: uppercase; letter-spacing: 0.05em; }
        .content { padding: 24px; }
        .intro-box { font-size: 14px; line-height: 1.6; color: #475569; margin-bottom: 20px; }
        .details-grid { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px; margin-bottom: 20px; }
        .grid-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #e2e8f0; font-size: 13.5px; }
        .grid-row:last-child { border-bottom: none; }
        .label { color: #64748b; font-weight: 600; }
        .value { color: #0f172a; font-weight: 700; text-align: right; }
        .preorder-card { background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 14px; margin-bottom: 20px; font-size: 13px; color: #92400e; }
        .cta-btn { display: inline-block; width: 100%; box-sizing: border-box; background: #f59e0b; color: #ffffff !important; text-align: center; padding: 12px 20px; border-radius: 12px; font-weight: 700; font-size: 14px; text-decoration: none; margin-top: 10px; }
        .policy-note { font-size: 12px; color: #94a3b8; line-height: 1.5; margin-top: 18px; padding-top: 14px; border-top: 1px solid #f1f5f9; text-align: center; }
        .footer { background: #f8fafc; padding: 18px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="hero">
            <span class="badge" style="{{ $badgeStyle }}">{{ $badgeLabel }}</span>
            <h1>{{ $vendorName }}</h1>
            <div style="font-size: 13px; color: #94a3b8; margin-top: 4px;">
                {{ __('Reference', [], $targetLocale) }}: <strong>{{ $reservation->reference }}</strong>
            </div>
        </div>

        <div class="content">
            @if(!empty($tpl['intro']))
                <div class="intro-box">
                    {!! nl2br(e($tpl['intro'])) !!}
                </div>
            @endif

            <div class="details-grid">
                <div class="grid-row">
                    <span class="label">{{ __('Guest Name', [], $targetLocale) }}</span>
                    <span class="value">{{ $reservation->customer_name }}</span>
                </div>
                <div class="grid-row">
                    <span class="label">{{ __('Date & Time', [], $targetLocale) }}</span>
                    <span class="value">{{ $dateFormatted }} &bull; {{ $timeFormatted }}</span>
                </div>
                <div class="grid-row">
                    <span class="label">{{ __('Party Size', [], $targetLocale) }}</span>
                    <span class="value">{{ $reservation->guest_count }} {{ __('Guests', [], $targetLocale) }}</span>
                </div>
                <div class="grid-row">
                    <span class="label">{{ __('Assigned Table', [], $targetLocale) }}</span>
                    <span class="value">{{ $tableName }}</span>
                </div>
                @if($reservation->location?->address || $reservation->vendor?->operating_address)
                <div class="grid-row">
                    <span class="label">{{ __('Restaurant Address', [], $targetLocale) }}</span>
                    <span class="value">{{ $reservation->location?->address ?? $reservation->vendor?->operating_address }}</span>
                </div>
                @endif
                @if($reservation->location?->phone || $reservation->vendor?->phone)
                <div class="grid-row">
                    <span class="label">{{ __('Phone Contact', [], $targetLocale) }}</span>
                    <span class="value">{{ $reservation->location?->phone ?? $reservation->vendor?->phone }}</span>
                </div>
                @endif
            </div>

            @if($reservation->preOrder)
                <div class="preorder-card">
                    <div style="font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; justify-content: space-between;">
                        <span>🍲 {{ __('Pre-Ordered Dishes Attached', [], $targetLocale) }}</span>
                        <span style="font-weight: 800;">{{ number_format((float) $reservation->preOrder->total_amount) }} {{ $reservation->preOrder->currency }}</span>
                    </div>
                    <div style="font-size: 12.5px; opacity: 0.95;">
                        {{ $reservation->preOrder->getFormattedSummary($targetLocale) }}
                    </div>
                </div>
            @endif

            @if(!empty($isCustomer))
                <a href="{{ $reservation->getPublicDetailsUrl() }}" class="cta-btn">
                    {{ $tpl['cta_text'] ?? __('View Reservation Details', [], $targetLocale) }}
                </a>
            @else
                <a href="{{ url('/admin/floor-plan') }}" class="cta-btn" style="background: #2563eb;">
                    {{ __('Open Kitchen & Table Plan', [], $targetLocale) }}
                </a>
            @endif

            <div class="policy-note">
                <strong>{{ __('Cancellation Policy', [], $targetLocale) }}:</strong>
                {{ $reservation->cancellation_policy ?: __('Free cancellation up to 2 hours before your scheduled arrival time.', [], $targetLocale) }}
            </div>
        </div>

        <div class="footer">
            {{ $tpl['footer'] ?? __('Thank you for choosing :site.', ['site' => $siteName], $targetLocale) }}<br>
            <span style="font-size: 11px; margin-top: 4px; display: inline-block;">
                {{ $siteName }} &bull; {{ __('Support', [], $targetLocale) }}: support@menu.elab.am
            </span>
        </div>
    </div>
</body>
</html>
