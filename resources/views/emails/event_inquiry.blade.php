@php
    $targetLocale = $locale ?? app()->getLocale();
    $siteName = \App\Models\SystemSetting::getSiteName();
    $vendorName = $inquiry->vendor?->name ?? $siteName;
    $dateFormatted = $inquiry->getFormattedDate($targetLocale);
    $timeFormatted = $inquiry->getFormattedTime();
    $eventTypeLabel = $inquiry->getEventTypeLabel($targetLocale);
    $venueLabel = $inquiry->getVenueArrangementLabel($targetLocale);
    $isCustomer = ($recipientType ?? 'customer') === 'customer';
@endphp
<!DOCTYPE html>
<html lang="{{ $targetLocale }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? __('Event Planning Inquiry', [], $targetLocale) }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; color: #334155; margin: 0; padding: 24px 12px; }
        .wrapper { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 18px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2); border: 1px solid #e2e8f0; }
        .hero { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 28px 24px; text-align: center; color: #ffffff; }
        .hero h1 { margin: 8px 0 4px; font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: -0.02em; }
        .badge { display: inline-block; font-weight: 700; font-size: 12px; padding: 5px 14px; border-radius: 9999px; text-transform: uppercase; letter-spacing: 0.05em; background: #fef3c7; color: #92400e; }
        .content { padding: 24px; }
        .intro-box { font-size: 14.5px; line-height: 1.6; color: #475569; margin-bottom: 20px; }
        .notice-card { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 14px; margin-bottom: 20px; font-size: 13.5px; color: #166534; line-height: 1.5; }
        .notice-card strong { color: #14532d; }
        .details-grid { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px; margin-bottom: 20px; }
        .grid-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #e2e8f0; font-size: 13.5px; }
        .grid-row:last-child { border-bottom: none; }
        .label { color: #64748b; font-weight: 600; }
        .value { color: #0f172a; font-weight: 700; text-align: right; }
        .section-title { font-size: 14px; font-weight: 700; color: #1e293b; margin: 18px 0 8px; text-transform: uppercase; letter-spacing: 0.05em; }
        .text-block { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; font-size: 13px; color: #334155; line-height: 1.5; margin-bottom: 16px; }
        .cta-btn { display: inline-block; width: 100%; box-sizing: border-box; background: #f59e0b; color: #ffffff !important; text-align: center; padding: 12px 20px; border-radius: 12px; font-weight: 700; font-size: 14px; text-decoration: none; margin-top: 10px; }
        .policy-note { font-size: 12px; color: #94a3b8; line-height: 1.5; margin-top: 18px; padding-top: 14px; border-top: 1px solid #f1f5f9; text-align: center; }
        .footer { background: #f8fafc; padding: 18px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="hero">
            <span class="badge">
                {{ $isCustomer ? __('Inquiry Acknowledgment', [], $targetLocale) : __('New Event Inquiry', [], $targetLocale) }}
            </span>
            <h1>{{ $vendorName }}</h1>
            <div style="font-size: 13px; color: #94a3b8; margin-top: 4px;">
                {{ __('Reference', [], $targetLocale) }}: <strong>{{ $inquiry->reference }}</strong>
            </div>
        </div>

        <div class="content">
            @if($isCustomer)
                <div class="intro-box">
                    {{ __('Dear :name, thank you for your event planning inquiry. We have received your request and our team will get in touch with you soon using your preferred contact method.', ['name' => $inquiry->customer_name], $targetLocale) }}
                </div>
                <div class="notice-card">
                    <strong>{{ __('Important Note', [], $targetLocale) }}:</strong>
                    {{ __('Submitting an inquiry does not confirm a reservation or guarantee availability. Our team will review the details and contact you with a customized offer.', [], $targetLocale) }}
                </div>
            @else
                <div class="intro-box">
                    {{ __('A new event planning inquiry has been submitted for :restaurant. Please review the details below and contact the customer.', ['restaurant' => $vendorName], $targetLocale) }}
                </div>
            @endif

            <div class="details-grid">
                <div class="grid-row">
                    <span class="label">{{ __('Event Type', [], $targetLocale) }}</span>
                    <span class="value">{{ $eventTypeLabel }}</span>
                </div>
                <div class="grid-row">
                    <span class="label">{{ __('Preferred Date', [], $targetLocale) }}</span>
                    <span class="value">{{ $dateFormatted }}</span>
                </div>
                <div class="grid-row">
                    <span class="label">{{ __('Time / Duration', [], $targetLocale) }}</span>
                    <span class="value">{{ $timeFormatted }}</span>
                </div>
                <div class="grid-row">
                    <span class="label">{{ __('Estimated Guests', [], $targetLocale) }}</span>
                    <span class="value">{{ $inquiry->guest_count }} {{ __('guests', [], $targetLocale) }}</span>
                </div>
                <div class="grid-row">
                    <span class="label">{{ __('Venue Arrangement', [], $targetLocale) }}</span>
                    <span class="value">{{ $venueLabel }}</span>
                </div>
                @if(filled($inquiry->budget) || filled($inquiry->budget_range))
                    <div class="grid-row">
                        <span class="label">{{ __('Estimated Budget', [], $targetLocale) }}</span>
                        <span class="value">
                            @if(filled($inquiry->budget))
                                {{ number_format((float) $inquiry->budget) }} {{ $inquiry->budget_currency }}
                            @else
                                {{ $inquiry->budget_range }}
                            @endif
                        </span>
                    </div>
                @endif
                <div class="grid-row">
                    <span class="label">{{ __('Customer Name', [], $targetLocale) }}</span>
                    <span class="value">{{ $inquiry->customer_name }}</span>
                </div>
                <div class="grid-row">
                    <span class="label">{{ __('Phone Number', [], $targetLocale) }}</span>
                    <span class="value">{{ $inquiry->customer_phone }}</span>
                </div>
                @if(filled($inquiry->customer_email))
                    <div class="grid-row">
                        <span class="label">{{ __('Email Address', [], $targetLocale) }}</span>
                        <span class="value">{{ $inquiry->customer_email }}</span>
                    </div>
                @endif
                <div class="grid-row">
                    <span class="label">{{ __('Preferred Contact Method', [], $targetLocale) }}</span>
                    <span class="value">{{ ucfirst($inquiry->preferred_contact_method) }}</span>
                </div>
            </div>

            @if(!empty($inquiry->additional_services))
                <div class="section-title">{{ __('Requested Additional Services', [], $targetLocale) }}</div>
                <div class="text-block">
                    {{ implode(', ', array_map(fn($s) => ucfirst(str_replace('_', ' ', $s)), (array) $inquiry->additional_services)) }}
                </div>
            @endif

            @if(filled($inquiry->food_beverage_preferences))
                <div class="section-title">{{ __('Food & Beverage Preferences', [], $targetLocale) }}</div>
                <div class="text-block">
                    {{ $inquiry->food_beverage_preferences }}
                </div>
            @endif

            @if(filled($inquiry->comments))
                <div class="section-title">{{ __('Additional Notes / Comments', [], $targetLocale) }}</div>
                <div class="text-block">
                    {{ $inquiry->comments }}
                </div>
            @endif

            @if(!$isCustomer)
                <a href="{{ route('admin.event_inquiries.show', $inquiry) }}" class="cta-btn">
                    {{ __('Manage Inquiry in Dashboard', [], $targetLocale) }}
                </a>
            @endif

            <div class="policy-note">
                {{ $inquiry->vendor?->operating_address ?? '' }} 
                @if($inquiry->vendor?->phone) • {{ $inquiry->vendor->phone }} @endif
            </div>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} {{ $vendorName }} • {{ __('Powered by :site', ['site' => $siteName], $targetLocale) }}
        </div>
    </div>
</body>
</html>
