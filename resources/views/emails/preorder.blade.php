@php
    $targetLocale = $locale ?? app()->getLocale();
    $siteName = \App\Models\SystemSetting::getSiteName();
    $vendorName = $reservation->vendor?->name ?? $siteName;
    $dateFormatted = $reservation->getFormattedDate($targetLocale);
    $timeFormatted = $reservation->getFormattedTime();
    
    $badgeStyle = match ($event) {
        'confirmed' => 'background: #dcfce7; color: #166534;',
        'cancelled' => 'background: #fee2e2; color: #991b1b;',
        'status_changed', 'updated' => 'background: #fef3c7; color: #92400e;',
        default => 'background: #e0f2fe; color: #075985;',
    };

    $badgeLabel = match ($event) {
        'confirmed' => '✓ ' . __('Pre-Order Confirmed', [], $targetLocale),
        'cancelled' => '✕ ' . __('Pre-Order Cancelled', [], $targetLocale),
        'status_changed', 'updated' => '↻ ' . __('Pre-Order Updated', [], $targetLocale),
        default => '🍲 ' . __('Pre-Order Received', [], $targetLocale),
    };
@endphp
<!DOCTYPE html>
<html lang="{{ $targetLocale }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tpl['subject'] ?? __('Pre-Order Update', [], $targetLocale) }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #334155; margin: 0; padding: 24px 12px; }
        .wrapper { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 18px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2); border: 1px solid #e2e8f0; }
        .hero { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 28px 24px; text-align: center; color: #ffffff; }
        .hero h1 { margin: 8px 0 4px; font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: -0.02em; }
        .badge { display: inline-block; font-weight: 700; font-size: 12px; padding: 5px 14px; border-radius: 9999px; text-transform: uppercase; letter-spacing: 0.05em; }
        .content { padding: 24px; }
        .intro-box { font-size: 14px; line-height: 1.6; color: #475569; margin-bottom: 20px; }
        .items-box { border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px 18px; margin-bottom: 20px; background: #ffffff; }
        .item-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .item-row:last-child { border-bottom: none; }
        .item-name { font-weight: 600; color: #0f172a; }
        .item-variation { font-size: 12px; color: #64748b; }
        .item-subtotal { font-weight: 700; color: #0f172a; text-align: right; }
        .total-box { margin-top: 16px; padding-top: 14px; border-top: 2px solid #0f172a; display: flex; justify-content: space-between; font-size: 17px; font-weight: 800; color: #0f172a; }
        .meta-pill { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 16px; margin-bottom: 20px; font-size: 13px; color: #475569; }
        .cta-btn { display: inline-block; width: 100%; box-sizing: border-box; background: #f59e0b; color: #ffffff !important; text-align: center; padding: 12px 20px; border-radius: 12px; font-weight: 700; font-size: 14px; text-decoration: none; margin-top: 10px; }
        .footer { background: #f8fafc; padding: 18px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="hero">
            <span class="badge" style="{{ $badgeStyle }}">{{ $badgeLabel }}</span>
            <h1>{{ $vendorName }}</h1>
            <div style="font-size: 13px; color: #94a3b8; margin-top: 4px;">
                {{ __('Reservation', [], $targetLocale) }}: <strong>{{ $reservation->reference }}</strong>
            </div>
        </div>

        <div class="content">
            @if(!empty($tpl['intro']))
                <div class="intro-box">
                    {!! nl2br(e($tpl['intro'])) !!}
                </div>
            @endif

            <div class="meta-pill">
                <strong>{{ __('Reservation Arrival', [], $targetLocale) }}:</strong> {{ $dateFormatted }} at {{ $timeFormatted }}<br>
                <strong>{{ __('Customer', [], $targetLocale) }}:</strong> {{ $reservation->customer_name }} ({{ $reservation->guest_count }} {{ __('Guests', [], $targetLocale) }})
            </div>

            <div class="items-box">
                <div style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 8px;">
                    {{ __('Pre-Ordered Menu Items', [], $targetLocale) }}
                </div>
                @foreach($preOrder->items ?? [] as $item)
                    @php
                        $name = $item['name'] ?? ($item['product_name'] ?? 'Dish');
                        if (!empty($item['name_translations'][$targetLocale])) {
                            $name = $item['name_translations'][$targetLocale];
                        }
                    @endphp
                    <div class="item-row">
                        <div>
                            <span class="item-name">{{ $item['quantity'] ?? 1 }}x {{ $name }}</span>
                            @if(!empty($item['variation_name']))
                                <div class="item-variation">({{ $item['variation_name'] }})</div>
                            @endif
                        </div>
                        <div class="item-subtotal">
                            {{ isset($item['subtotal']) ? number_format((float) $item['subtotal']) : '0' }} {{ $preOrder->currency }}
                        </div>
                    </div>
                @endforeach

                <div class="total-box">
                    <span>{{ __('Total Pre-Order Amount', [], $targetLocale) }}</span>
                    <span style="color: #e11d48;">{{ number_format((float) $preOrder->total_amount) }} {{ $preOrder->currency }}</span>
                </div>
            </div>

            @if(!empty($isCustomer))
                <a href="{{ $reservation->getPublicDetailsUrl() }}" class="cta-btn">
                    {{ $tpl['cta_text'] ?? __('View Details & Manage Reservation', [], $targetLocale) }}
                </a>
            @else
                <a href="{{ url('/admin/floor-plan') }}" class="cta-btn" style="background: #2563eb;">
                    {{ __('View Kitchen & Table Orders', [], $targetLocale) }}
                </a>
            @endif
        </div>

        <div class="footer">
            {{ $tpl['footer'] ?? __('Thank you for choosing :site.', ['site' => $siteName], $targetLocale) }}<br>
            <span style="font-size: 11px; margin-top: 4px; display: inline-block;">
                {{ $siteName }} &bull; support@menu.elab.am
            </span>
        </div>
    </div>
</body>
</html>
