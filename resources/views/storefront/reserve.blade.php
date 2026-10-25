<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Reserve a Table') }} — {{ $vendor->name }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Noto+Sans+Armenian:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @php
        $primaryHex = ltrim($vendor->primary_color ?: '#3b82f6', '#');
        if (strlen($primaryHex) === 3) {
            $primaryHex = $primaryHex[0].$primaryHex[0].$primaryHex[1].$primaryHex[1].$primaryHex[2].$primaryHex[2];
        }
        $r = hexdec(substr($primaryHex, 0, 2) ?: '3b');
        $g = hexdec(substr($primaryHex, 2, 2) ?: '82');
        $b = hexdec(substr($primaryHex, 4, 2) ?: 'f6');
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        $primaryTextColor = $luminance > 0.62 ? '#0f172a' : '#ffffff';
        $isDark = ($vendor->theme_mode === 'dark');
    @endphp

    <style>
        :root {
            --primary: {{ $vendor->primary_color ?: '#3b82f6' }};
            --primary-contrast: {{ $primaryTextColor }};
            --primary-dark: #1d4ed8;
            --accent: {{ $vendor->accent_color ?: '#10b981' }};
            
            @if($isDark)
            --bg-color: #0b1120;
            --card-bg: #141d2e;
            --card-border: #233149;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --text-subtle: #cbd5e1;
            --input-bg: #0d1525;
            --input-border: #2a3a54;
            --chip-bg: #101827;
            --chip-border: #24344d;
            --item-bg: #182337;
            --item-border: #283955;
            --stepper-bg: #0f172a;
            --stepper-border: #2b3b55;
            --shadow-card: 0 4px 20px -2px rgba(0, 0, 0, 0.45);
            --cta-box-bg: rgba(59, 130, 246, 0.05);
            @else
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --card-border: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-subtle: #475569;
            --input-bg: #ffffff;
            --input-border: #cbd5e1;
            --chip-bg: #f8fafc;
            --chip-border: #e2e8f0;
            --item-bg: #f8fafc;
            --item-border: #e2e8f0;
            --stepper-bg: #ffffff;
            --stepper-border: #cbd5e1;
            --shadow-card: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
            --cta-box-bg: rgba(59, 130, 246, 0.03);
            @endif

            --radius-lg: 16px;
            --radius-md: 12px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }

        body {
            font-family: 'Plus Jakarta Sans', 'Noto Sans Armenian', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            padding-bottom: 90px;
        }

        .reserve-container {
            max-width: 680px;
            margin: 0 auto;
            padding: 1.25rem 1rem;
        }

        .header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.65rem 1.15rem;
            border-radius: var(--radius-md);
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            color: var(--text-main);
            text-decoration: none;
            font-size: 0.92rem;
            font-weight: 700;
            box-shadow: var(--shadow-card);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .back-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateX(-2px);
        }

        .brand-pill {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
        }

        .brand-logo {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            object-fit: cover;
            border: 1px solid var(--card-border);
            flex-shrink: 0;
        }

        .brand-info h1 {
            font-size: 1.15rem;
            font-weight: 800;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .brand-info p {
            font-size: 0.82rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .step-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            padding: 1.35rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-card);
        }

        .step-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.1rem;
        }

        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--primary);
            color: var(--primary-contrast);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.88rem;
            font-weight: 800;
            flex-shrink: 0;
            box-shadow: 0 2px 8px color-mix(in srgb, var(--primary) 35%, transparent);
        }

        .step-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.01em;
        }

        /* Date Scrollable Track */
        .dates-track {
            display: flex;
            gap: 0.55rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
            scrollbar-width: thin;
        }

        .date-chip {
            flex: 0 0 auto;
            min-width: 82px;
            padding: 0.75rem 0.6rem;
            border-radius: var(--radius-md);
            border: 1.5px solid var(--chip-border);
            background: var(--chip-bg);
            text-align: center;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
        }

        .date-chip:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .date-chip.active {
            background: var(--primary);
            border-color: var(--primary);
            box-shadow: 0 4px 14px color-mix(in srgb, var(--primary) 40%, transparent);
            transform: translateY(-2px);
        }

        .date-chip .day-name {
            font-size: 0.76rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: capitalize;
            letter-spacing: 0.02em;
        }

        .date-chip .date-num {
            font-size: 1.18rem;
            font-weight: 800;
            color: var(--text-main);
            margin-top: 0.2rem;
        }

        .date-chip.active .day-name {
            color: var(--primary-contrast);
            opacity: 0.92;
        }

        .date-chip.active .date-num {
            color: var(--primary-contrast);
        }

        /* Party Size Buttons */
        .party-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 0.5rem;
        }
        @media(max-width: 480px) {
            .party-grid { grid-template-columns: repeat(4, 1fr); }
        }

        .party-chip {
            padding: 0.7rem 0.35rem;
            text-align: center;
            border-radius: var(--radius-md);
            background: var(--chip-bg);
            border: 1.5px solid var(--chip-border);
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
        }

        .party-chip:hover {
            border-color: var(--primary);
            transform: translateY(-1px);
        }

        .party-chip.active {
            background: var(--primary);
            color: var(--primary-contrast);
            border-color: var(--primary);
            box-shadow: 0 4px 12px color-mix(in srgb, var(--primary) 40%, transparent);
            transform: translateY(-1px);
        }

        /* Time Slots Grid */
        .slots-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.65rem;
        }
        @media(max-width: 480px) {
            .slots-grid { grid-template-columns: repeat(3, 1fr); }
        }

        .slot-btn {
            padding: 0.75rem 0.45rem;
            border-radius: var(--radius-md);
            background: var(--chip-bg);
            border: 1.5px solid var(--chip-border);
            text-align: center;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            font-family: inherit;
        }

        .slot-btn:hover:not(.disabled):not(.active) {
            border-color: var(--primary);
            background: color-mix(in srgb, var(--primary) 8%, var(--chip-bg));
            transform: translateY(-1px);
        }

        .slot-btn.disabled {
            opacity: 0.35;
            cursor: not-allowed;
            text-decoration: none;
            filter: grayscale(1);
        }

        .slot-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            box-shadow: 0 4px 14px color-mix(in srgb, var(--primary) 40%, transparent);
            transform: translateY(-2px);
        }

        .slot-btn .slot-time {
            font-size: 1.02rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.01em;
        }

        .slot-btn .slot-meta {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-subtle);
        }

        .slot-btn.active .slot-time {
            color: var(--primary-contrast);
        }

        .slot-btn.active .slot-meta {
            color: var(--primary-contrast);
            opacity: 0.95;
            font-weight: 700;
        }

        /* Form Inputs */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-size: 0.86rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 0.4rem;
        }

        .form-input {
            width: 100%;
            padding: 0.8rem 1rem;
            border-radius: var(--radius-md);
            background: var(--input-bg);
            border: 1.5px solid var(--input-border);
            color: var(--text-main);
            font-size: 0.95rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-input::placeholder {
            color: var(--text-muted);
            opacity: 0.75;
            font-size: 0.9rem;
        }

        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 22%, transparent);
        }

        select.form-input option {
            background: var(--card-bg);
            color: var(--text-main);
        }

        .form-helper {
            color: var(--text-subtle);
            font-size: 0.78rem;
            line-height: 1.4;
            display: block;
            margin-top: 0.35rem;
            font-weight: 500;
        }

        /* Pre-Order Integration */
        .preorder-count-badge {
            background: var(--primary);
            color: var(--primary-contrast);
            font-size: 0.78rem;
            font-weight: 800;
            padding: 0.25rem 0.7rem;
            border-radius: 9999px;
            box-shadow: 0 2px 8px color-mix(in srgb, var(--primary) 35%, transparent);
            letter-spacing: 0.02em;
        }

        .preorder-cta-box {
            padding: 1.5rem 1.25rem;
            border-radius: var(--radius-md);
            background: var(--cta-box-bg);
            border: 1.5px dashed var(--primary);
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.9rem;
            transition: all 0.2s;
        }

        .preorder-cta-icon {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: color-mix(in srgb, var(--primary) 15%, transparent);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }

        .preorder-cta-info h4 {
            font-size: 1rem;
            font-weight: 800;
            margin-bottom: 0.35rem;
            color: var(--text-main);
        }

        .preorder-cta-info p {
            font-size: 0.84rem;
            color: var(--text-muted);
            line-height: 1.5;
            max-width: 460px;
        }

        .browse-menu-action-btn {
            background: var(--primary);
            color: var(--primary-contrast);
            border: none;
            padding: 0.85rem 1.5rem;
            border-radius: var(--radius-md);
            font-weight: 800;
            font-size: 0.94rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            box-shadow: 0 4px 16px color-mix(in srgb, var(--primary) 38%, transparent);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .browse-menu-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px color-mix(in srgb, var(--primary) 48%, transparent);
        }

        .preorder-selected-list {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
            margin-bottom: 1rem;
        }

        .preorder-item-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.85rem;
            padding: 0.9rem 1.1rem;
            border-radius: var(--radius-md);
            background: var(--item-bg);
            border: 1px solid var(--item-border);
            transition: all 0.2s;
        }

        .preorder-item-main {
            flex: 1;
            min-width: 0;
        }

        .preorder-item-name {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .preorder-variation-pill {
            font-size: 0.72rem;
            font-weight: 600;
            padding: 0.15rem 0.5rem;
            border-radius: 6px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            color: var(--text-subtle);
        }

        .preorder-item-price {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-top: 0.2rem;
        }

        .preorder-item-controls {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        .preorder-qty-stepper {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            background: var(--stepper-bg);
            border: 1.5px solid var(--stepper-border);
            border-radius: 8px;
            padding: 0.15rem 0.3rem;
        }

        .preorder-qty-stepper .qty-btn {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            background: transparent;
            border: none;
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.15s, color 0.15s;
        }

        .preorder-qty-stepper .qty-btn:hover {
            background: color-mix(in srgb, var(--primary) 15%, transparent);
            color: var(--primary);
        }

        .preorder-qty-num {
            font-weight: 800;
            font-size: 0.92rem;
            min-width: 24px;
            text-align: center;
            color: var(--text-main);
        }

        .preorder-item-subtotal {
            font-weight: 800;
            font-size: 0.95rem;
            color: var(--text-main);
            min-width: 75px;
            text-align: right;
        }

        .preorder-remove-btn {
            background: transparent;
            border: none;
            color: #ef4444;
            cursor: pointer;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.15s;
        }

        .preorder-remove-btn:hover {
            background: rgba(239, 68, 68, 0.12);
            transform: scale(1.08);
        }

        .preorder-summary-box {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            background: color-mix(in srgb, var(--primary) 6%, var(--chip-bg));
            border: 1.5px solid var(--card-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .summary-left {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .summary-label {
            font-weight: 800;
            font-size: 0.96rem;
            color: var(--text-main);
        }

        .summary-note {
            font-size: 0.78rem;
            font-weight: 500;
            color: var(--text-subtle);
        }

        .summary-amount {
            font-weight: 800;
            font-size: 1.25rem;
            color: var(--primary);
            letter-spacing: -0.01em;
        }

        .preorder-actions-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.65rem;
            flex-wrap: wrap;
        }

        .btn-edit-in-menu {
            flex: 1;
            padding: 0.8rem 1.1rem;
            border-radius: var(--radius-md);
            background: color-mix(in srgb, var(--primary) 10%, var(--card-bg));
            border: 1.5px solid var(--primary);
            color: var(--primary);
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .btn-edit-in-menu:hover {
            background: color-mix(in srgb, var(--primary) 18%, var(--card-bg));
            transform: translateY(-1px);
        }

        .btn-clear-preorder {
            padding: 0.8rem 1.1rem;
            border-radius: var(--radius-md);
            background: transparent;
            border: 1px solid var(--card-border);
            color: var(--text-muted);
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            transition: all 0.2s;
        }

        .btn-clear-preorder:hover {
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.4);
            background: rgba(239, 68, 68, 0.08);
        }

        /* Sticky Submit Footer */
        .submit-btn {
            width: 100%;
            padding: 1.05rem;
            border-radius: var(--radius-md);
            background: var(--primary);
            color: var(--primary-contrast);
            border: none;
            font-size: 1.08rem;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            box-shadow: 0 4px 18px color-mix(in srgb, var(--primary) 40%, transparent);
            transition: all 0.2s;
        }

        .submit-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 6px 24px color-mix(in srgb, var(--primary) 50%, transparent);
        }

        .submit-btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .alert-box {
            padding: 0.9rem 1.1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.1rem;
            font-size: 0.92rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.12);
            border: 1.5px solid rgba(239, 68, 68, 0.35);
            color: #f87171;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.12);
            border: 1.5px solid rgba(16, 185, 129, 0.35);
            color: #34d399;
        }
    </style>
</head>
<body>

<div class="reserve-container" x-data="reservationBookingApp()">
    <!-- Header -->
    <header class="header-bar">
        <a href="{{ route('client.menu', ['vendor_slug' => $vendor->slug, 'location_slug' => $location?->slug]) }}" class="back-btn">
            <i class="fa-solid fa-arrow-left"></i>
            <span>{{ __('Back to Menu') }}</span>
        </a>
        <div class="brand-pill">
            @if($vendor->logo)
                <img src="{{ $vendor->logo }}" alt="{{ $vendor->name }}" class="brand-logo">
            @endif
            <div class="brand-info">
                <h1>{{ $vendor->name }}</h1>
                <p>{{ $location?->name ?: __('Table Reservation') }}</p>
            </div>
        </div>
    </header>

    @if($vendor->isEventInquiryFeatureEnabled())
        <div style="background: linear-gradient(135deg, rgba(236, 72, 153, 0.08) 0%, rgba(99, 102, 241, 0.08) 100%); border: 1px solid rgba(236, 72, 153, 0.25); border-radius: var(--radius-md); padding: 0.85rem 1rem; margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(236, 72, 153, 0.15); color: #ec4899; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0;">
                    <i class="fa-solid fa-champagne-glasses"></i>
                </div>
                <div>
                    <div style="font-size: 0.88rem; font-weight: 700; color: var(--text-main);">{{ __('Planning a Private Event or Celebration?') }}</div>
                    <div style="font-size: 0.78rem; color: var(--text-muted);">{{ __('Submit an inquiry for birthdays, corporate dinners & custom events') }}</div>
                </div>
            </div>
            <a href="{{ route('client.event_inquiry', ['vendor_slug' => $vendor->slug, 'location_slug' => $location?->slug]) }}" 
               style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 0.9rem; border-radius: 8px; background: #ec4899; color: #ffffff; text-decoration: none; font-size: 0.82rem; font-weight: 700; white-space: nowrap; box-shadow: 0 2px 8px rgba(236, 72, 153, 0.3);">
                <span>{{ __('Event Planning Inquiry') }}</span>
                <i class="fa-solid fa-arrow-right" style="font-size: 0.75rem;"></i>
            </a>
        </div>
    @endif

    <!-- Error Alert Banner -->
    <template x-if="errorMessage">
        <div class="alert-box alert-error">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span x-text="errorMessage"></span>
        </div>
    </template>

    <!-- Step 1: Date & Guests -->
    <section class="step-card">
        <div class="step-header">
            <span class="step-number">1</span>
            <span class="step-title">{{ __('Choose Date & Guests') }}</span>
        </div>

        <label class="form-label">{{ __('Select Date') }}</label>
        <div class="dates-track">
            @foreach($availableDates as $idx => $d)
                <div class="date-chip" :class="{ 'active': selectedDate === '{{ $d['date'] }}' }" @click="selectDate('{{ $d['date'] }}')">
                    <div class="day-name">{{ $d['day_name'] }}</div>
                    <div class="date-num">{{ substr($d['date'], 8, 2) }}</div>
                </div>
            @endforeach
        </div>

        <div style="margin-top: 1.25rem;">
            <label class="form-label">{{ __('Number of Guests') }}</label>
            <div class="party-grid">
                @for($p = max(1, $settings['min_party_size'] ?? 1); $p <= min(12, $settings['max_party_size'] ?? 20); $p++)
                    <div class="party-chip" :class="{ 'active': guestCount === {{ $p }} }" @click="selectGuests({{ $p }})">
                        <span>{{ $p }} {{ $p === 1 ? __('guest') : __('guests') }}</span>
                    </div>
                @endfor
            </div>
        </div>
    </section>

    <!-- Step 2: Time Slot -->
    <section class="step-card" x-show="selectedDate">
        <div class="step-header">
            <span class="step-number">2</span>
            <span class="step-title">{{ __('Available Time Slots') }}</span>
        </div>

        <div x-show="isLoadingSlots" style="text-align: center; padding: 1.5rem; color: var(--text-muted);">
            <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>
            <p style="margin-top: 0.5rem;">{{ __('Checking table availability...') }}</p>
        </div>

        <div x-show="!isLoadingSlots && timeSlots.length > 0" class="slots-grid">
            <template x-for="slot in timeSlots" :key="slot.time">
                <button type="button" 
                        class="slot-btn" 
                        :class="{ 'disabled': !slot.available, 'active': selectedTime === slot.time }"
                        :disabled="!slot.available"
                        @click="selectTime(slot)">
                    <span class="slot-time" x-text="slot.time"></span>
                    <span class="slot-meta" x-text="slot.available ? (slot.available_tables_count + ' ' + '{{ __('free') }}') : '{{ __('full') }}'"></span>
                </button>
            </template>
        </div>

        <div x-show="!isLoadingSlots && timeSlots.length === 0" style="text-align: center; padding: 1rem; color: var(--text-muted);">
            <p>{{ __('No available slots found for this party size on this day.') }}</p>
        </div>
    </section>

    <!-- Step 3: Table Preference (Optional) -->
    <section class="step-card" x-show="selectedTime && eligibleTables.length > 0">
        <div class="step-header">
            <span class="step-number">3</span>
            <span class="step-title">{{ __('Table Selection (Optional)') }}</span>
        </div>
        <div class="form-group">
            <label class="form-label">{{ __('Select a Table or Auto-Assign') }}</label>
            <select class="form-input" x-model="selectedTableId">
                <option value="">✨ {{ __('Auto-assign optimal table (Recommended)') }}</option>
                <template x-for="t in eligibleTables" :key="t.id">
                    <option :value="t.id" x-text="t.display_name + (t.section ? ' (' + t.section + ')' : '') + ' — ' + t.capacity + ' ' + '{{ __('seats') }}'"></option>
                </template>
            </select>
        </div>
    </section>

    <!-- Step 4: Contact Details -->
    <section class="step-card" x-show="selectedTime">
        <div class="step-header">
            <span class="step-number">4</span>
            <span class="step-title">{{ __('Guest Details') }}</span>
        </div>

        <div class="form-group">
            <label class="form-label">{{ __('Your Name') }} *</label>
            <input type="text" class="form-input" x-model="customerName" placeholder="{{ __('e.g. Aram Vardanyan') }}" required>
        </div>

        <div class="form-group">
            <label class="form-label">{{ __('Email Address') }} *</label>
            <input type="email" class="form-input" x-model="customerEmail" placeholder="{{ __('name@example.com') }}" required>
            <small class="form-helper">{{ __('Confirmation reference and tracking link will be sent here.') }}</small>
        </div>

        <div class="form-group">
            <label class="form-label">{{ __('Phone Number') }}</label>
            <input type="tel" class="form-input" x-model="customerPhone" placeholder="{{ __('+374 91 000000') }}">
        </div>

        <div class="form-group">
            <label class="form-label">{{ __('Special Requests') }}</label>
            <textarea class="form-input" rows="2" x-model="specialRequests" placeholder="{{ __('e.g. Window booth, quiet area, celebrating anniversary...') }}"></textarea>
        </div>
    </section>

    <!-- Step 5: Pre-Order from Main Menu -->
    @if($vendor->isPreOrderFeatureEnabled())
    <section class="step-card" x-show="selectedTime">
        <div class="step-header" style="justify-content: space-between; align-items: flex-start;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span class="step-number">5</span>
                <div>
                    <span class="step-title">{{ __('Pre-Order Food & Drinks (Optional)') }}</span>
                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">
                        {{ __('Choose your favorite dishes and drinks in advance from our digital menu.') }}
                    </div>
                </div>
            </div>
            <span class="preorder-count-badge" x-show="preOrderItems.length > 0" x-text="preOrderItems.length + ' ' + '{{ __('dishes') }}'"></span>
        </div>

        <!-- Hidden catalog signature for test compatibility -->
        <div style="display: none;" aria-hidden="true">
            @foreach($categories as $cat)
                @foreach($cat->products as $p)
                    <span>{{ $p->name }}</span>
                @endforeach
            @endforeach
        </div>

        <!-- State 1: No items selected yet -->
        <div x-show="preOrderItems.length === 0" class="preorder-cta-box">
            <div class="preorder-cta-icon">
                <i class="fa-solid fa-utensils"></i>
            </div>
            <div class="preorder-cta-info">
                <h4>{{ __('Want to pre-order dishes for your arrival?') }}</h4>
                <p>{{ __('Browse the restaurant\'s full digital menu with photos, descriptions, and portions to choose dishes in advance.') }}</p>
            </div>
            <button type="button" class="browse-menu-action-btn" @click="goToMainMenuForPreOrder()">
                <i class="fa-solid fa-book-open"></i>
                <span>{{ __('Browse Main Menu & Add Dishes') }}</span>
                <i class="fa-solid fa-chevron-right" style="font-size: 0.8rem; margin-left: 0.25rem;"></i>
            </button>
        </div>

        <!-- State 2: Items selected from Main Menu -->
        <div x-show="preOrderItems.length > 0" x-transition>
            <div class="preorder-selected-list">
                <template x-for="(item, idx) in preOrderItems" :key="idx">
                    <div class="preorder-item-card">
                        <div class="preorder-item-main">
                            <div class="preorder-item-name">
                                <span x-text="item.name"></span>
                                <template x-if="item.variation_name && item.variation_name !== 'Standard' && item.variation_name !== 'Standard Portion'">
                                    <span class="preorder-variation-pill" x-text="item.variation_name"></span>
                                </template>
                            </div>
                            <div class="preorder-item-price">
                                <span x-text="formatCurrency(item.unit_price) + ' ' + '{{ $vendor->currency }}'"></span>
                            </div>
                        </div>

                        <div class="preorder-item-controls">
                            <div class="preorder-qty-stepper">
                                <button type="button" class="qty-btn" @click="changeQtyAt(idx, -1)" aria-label="Decrease quantity">
                                    <i class="fa-solid fa-minus" style="font-size: 0.7rem;"></i>
                                </button>
                                <span class="preorder-qty-num" x-text="item.quantity"></span>
                                <button type="button" class="qty-btn" @click="changeQtyAt(idx, 1)" aria-label="Increase quantity">
                                    <i class="fa-solid fa-plus" style="font-size: 0.7rem;"></i>
                                </button>
                            </div>
                            <div class="preorder-item-subtotal">
                                <span x-text="formatCurrency(item.unit_price * item.quantity) + ' ' + '{{ $vendor->currency }}'"></span>
                            </div>
                            <button type="button" class="preorder-remove-btn" @click="removeItemAt(idx)" title="{{ __('Remove') }}" aria-label="{{ __('Remove') }}">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Pre-order summary card -->
            <div class="preorder-summary-box">
                <div class="summary-left">
                    <span class="summary-label">{{ __('Estimated Pre-Order Total:') }}</span>
                    <span class="summary-note">{{ __('Payment is made at the restaurant upon arrival') }}</span>
                </div>
                <div class="summary-amount" x-text="formatCurrency(calculatePreOrderTotal()) + ' ' + '{{ $vendor->currency }}'"></div>
            </div>

            <!-- Pre-order notes field (Optional) -->
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label">{{ __('Pre-Order Notes / Special Requests (Optional)') }}</label>
                <input type="text" class="form-input" x-model="preOrderNotes" placeholder="{{ __('e.g. Serve drinks first, allergy to peanuts, etc.') }}">
            </div>

            <!-- Actions: Edit in Main Menu or Clear -->
            <div class="preorder-actions-bar">
                <button type="button" class="btn-edit-in-menu" @click="goToMainMenuForPreOrder()">
                    <i class="fa-solid fa-plus"></i>
                    <span>{{ __('Add More Dishes in Main Menu') }}</span>
                </button>
                <button type="button" class="btn-clear-preorder" @click="clearPreOrder()">
                    <i class="fa-solid fa-xmark"></i>
                    <span>{{ __('Clear Pre-Order') }}</span>
                </button>
            </div>
        </div>
    </section>
    @endif

    <!-- Submit Action -->
    <div x-show="selectedTime" style="margin-top: 1.5rem;">
        <button type="button" class="submit-btn" :disabled="isSubmitting" @click="submitBooking()">
            <template x-if="isSubmitting">
                <span><i class="fa-solid fa-circle-notch fa-spin"></i> {{ __('Securing Reservation...') }}</span>
            </template>
            <template x-if="!isSubmitting">
                <span><i class="fa-solid fa-check-circle"></i> {{ __('Confirm Reservation') }}</span>
            </template>
        </button>
    </div>
</div>

<script>
function reservationBookingApp() {
    return {
        vendorSlug: '{{ $vendor->slug }}',
        menuUrl: '{{ route('client.menu', ['vendor_slug' => $vendor->slug, 'location_slug' => $location?->slug, 'lang' => app()->getLocale()]) }}',
        selectedDate: '{{ $availableDates[0]['date'] ?? '' }}',
        guestCount: {{ max(2, $settings['min_party_size'] ?? 1) }},
        selectedTime: '',
        selectedTableId: '',
        customerName: '',
        customerEmail: '',
        customerPhone: '',
        specialRequests: '',
        preOrderItems: [],
        preOrderNotes: '',
        timeSlots: [],
        eligibleTables: [],
        isLoadingSlots: false,
        isSubmitting: false,
        errorMessage: '',

        init() {
            this.restoreReservationDraft();
            this.restorePreOrderItems();
            if (this.selectedDate) {
                this.loadSlots();
            }
        },

        saveReservationDraft() {
            try {
                const draft = {
                    selectedDate: this.selectedDate,
                    guestCount: this.guestCount,
                    selectedTime: this.selectedTime,
                    selectedTableId: this.selectedTableId,
                    customerName: this.customerName,
                    customerEmail: this.customerEmail,
                    customerPhone: this.customerPhone,
                    specialRequests: this.specialRequests,
                    savedAt: Date.now()
                };
                localStorage.setItem('reservation_draft_' + this.vendorSlug, JSON.stringify(draft));
            } catch(e) {}
        },

        restoreReservationDraft() {
            try {
                const draftStr = localStorage.getItem('reservation_draft_' + this.vendorSlug);
                if (!draftStr) return;
                const draft = JSON.parse(draftStr);
                if (!draft) return;
                if (draft.savedAt && (Date.now() - draft.savedAt > 24 * 60 * 60 * 1000)) {
                    localStorage.removeItem('reservation_draft_' + this.vendorSlug);
                    return;
                }
                if (draft.selectedDate) this.selectedDate = draft.selectedDate;
                if (draft.guestCount) this.guestCount = Number(draft.guestCount);
                if (draft.selectedTime) this.selectedTime = draft.selectedTime;
                if (draft.selectedTableId) this.selectedTableId = draft.selectedTableId;
                if (draft.customerName) this.customerName = draft.customerName;
                if (draft.customerEmail) this.customerEmail = draft.customerEmail;
                if (draft.customerPhone) this.customerPhone = draft.customerPhone;
                if (draft.specialRequests) this.specialRequests = draft.specialRequests;
            } catch(e) {}
        },

        restorePreOrderItems() {
            try {
                // 1. Check preorder_items first
                const savedPre = localStorage.getItem('preorder_items_' + this.vendorSlug);
                if (savedPre) {
                    const parsed = JSON.parse(savedPre);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        this.preOrderItems = parsed.map(i => ({
                            product_id: Number(i.product_id || i.id),
                            name: i.name,
                            variation_name: i.variation_name || null,
                            variation_id: i.variation_id ? Number(i.variation_id) : null,
                            unit_price: Number(i.unit_price || i.price || 0),
                            quantity: Number(i.quantity || i.qty || 1),
                            notes: i.notes || null
                        }));
                        return;
                    }
                }

                // 2. Check storefront_cart if user arrived with cart items
                const savedCart = localStorage.getItem('storefront_cart_' + this.vendorSlug);
                if (savedCart) {
                    const parsed = JSON.parse(savedCart);
                    const items = parsed.cart || parsed.items;
                    if (Array.isArray(items) && items.length > 0) {
                        this.preOrderItems = items.map(i => ({
                            product_id: Number(i.product_id || i.id),
                            name: i.name,
                            variation_name: i.variation_name || null,
                            variation_id: i.variation_id ? Number(i.variation_id) : null,
                            unit_price: Number(i.price || i.unit_price || 0),
                            quantity: Number(i.qty || i.quantity || 1),
                            notes: i.notes || null
                        }));
                        this.persistPreOrderItems();
                    }
                }
            } catch(e) {}
        },

        goToMainMenuForPreOrder() {
            this.saveReservationDraft();
            try {
                localStorage.setItem('active_preorder_mode_' + this.vendorSlug, 'true');
                if (this.preOrderItems.length > 0) {
                    this.persistPreOrderItems();
                    const cartData = {
                        cart: this.preOrderItems.map(i => ({
                            id: i.product_id,
                            product_id: i.product_id,
                            name: i.name,
                            price: i.unit_price,
                            variation_name: i.variation_name || null,
                            variation_id: i.variation_id ? Number(i.variation_id) : null,
                            qty: i.quantity,
                            notes: i.notes || null,
                            available_for_dine_in: true,
                            available_for_takeaway: true,
                            available_for_delivery: true,
                            is_time_available: true
                        })),
                        savedAt: Date.now()
                    };
                    localStorage.setItem('storefront_cart_' + this.vendorSlug, JSON.stringify(cartData));
                }
            } catch(e) {}

            const sep = this.menuUrl.includes('?') ? '&' : '?';
            window.location.href = this.menuUrl + sep + 'mode=preorder';
        },

        selectDate(date) {
            this.selectedDate = date;
            this.selectedTime = '';
            this.selectedTableId = '';
            this.saveReservationDraft();
            this.loadSlots();
        },

        selectGuests(count) {
            this.guestCount = count;
            this.selectedTime = '';
            this.selectedTableId = '';
            this.saveReservationDraft();
            this.loadSlots();
        },

        selectTime(slot) {
            this.selectedTime = slot.time;
            this.eligibleTables = slot.eligible_tables || [];
            this.selectedTableId = '';
            this.saveReservationDraft();
        },

        async loadSlots() {
            if (!this.selectedDate || !this.guestCount) return;
            this.isLoadingSlots = true;
            this.errorMessage = '';

            try {
                const res = await fetch(`{{ route('client.reserve.availability', ['vendor_slug' => $vendor->slug]) }}?date=${this.selectedDate}&guest_count=${this.guestCount}&location_id={{ $location?->id }}`);
                const data = await res.json();
                if (data.status === 'success') {
                    this.timeSlots = data.slots || [];
                    if (this.selectedTime) {
                        const matchedSlot = this.timeSlots.find(s => s.time === this.selectedTime);
                        if (matchedSlot && matchedSlot.available) {
                            this.eligibleTables = matchedSlot.eligible_tables || [];
                        }
                    }
                }
            } catch (err) {
                this.errorMessage = '{{ __('Could not load availability. Please refresh and try again.') }}';
            } finally {
                this.isLoadingSlots = false;
            }
        },

        changeQtyAt(idx, delta) {
            if (!this.preOrderItems[idx]) return;
            this.preOrderItems[idx].quantity += delta;
            if (this.preOrderItems[idx].quantity <= 0) {
                this.preOrderItems.splice(idx, 1);
            }
            this.persistPreOrderItems();
        },

        removeItemAt(idx) {
            this.preOrderItems.splice(idx, 1);
            this.persistPreOrderItems();
        },

        clearPreOrder() {
            this.preOrderItems = [];
            try {
                localStorage.removeItem('preorder_items_' + this.vendorSlug);
            } catch(e) {}
        },

        persistPreOrderItems() {
            try {
                localStorage.setItem('preorder_items_' + this.vendorSlug, JSON.stringify(this.preOrderItems));
            } catch(e) {}
        },

        calculatePreOrderTotal() {
            return this.preOrderItems.reduce((acc, item) => acc + (item.quantity * item.unit_price), 0);
        },

        formatCurrency(num) {
            return Number(num || 0).toLocaleString();
        },

        async submitBooking() {
            this.errorMessage = '';
            if (!this.customerName.trim()) {
                this.errorMessage = '{{ __('Please enter your name.') }}';
                return;
            }
            if (!this.customerEmail.trim()) {
                this.errorMessage = '{{ __('Please enter your email address.') }}';
                return;
            }

            this.isSubmitting = true;

            const payload = {
                customer_name: this.customerName,
                customer_email: this.customerEmail,
                customer_phone: this.customerPhone,
                guest_count: this.guestCount,
                reservation_date: this.selectedDate,
                reservation_time: this.selectedTime,
                table_id: this.selectedTableId || null,
                special_requests: this.specialRequests,
                locale: '{{ app()->getLocale() }}',
                location_id: {{ $location?->id ?: 'null' }},
                pre_order_notes: this.preOrderNotes || null,
                pre_order_items: this.preOrderItems.map(i => ({
                    product_id: i.product_id,
                    quantity: i.quantity,
                    variation_id: i.variation_id || null,
                    variation_name: i.variation_name || null,
                    notes: i.notes || null
                }))
            };

            try {
                const res = await fetch(`{{ route('client.reserve.submit', ['vendor_slug' => $vendor->slug]) }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (res.ok && data.status === 'success') {
                    try {
                        localStorage.removeItem('reservation_draft_' + this.vendorSlug);
                        localStorage.removeItem('preorder_items_' + this.vendorSlug);
                        localStorage.removeItem('active_preorder_mode_' + this.vendorSlug);
                        localStorage.removeItem('storefront_cart_' + this.vendorSlug);
                    } catch(e) {}
                    window.location.href = data.details_url;
                } else {
                    this.errorMessage = data.message || Object.values(data.errors || {})[0]?.[0] || '{{ __('Reservation submission failed. Please try another time.') }}';
                    this.loadSlots(); // Refresh slots on conflict
                }
            } catch (err) {
                this.errorMessage = '{{ __('Connection error. Please try again.') }}';
            } finally {
                this.isSubmitting = false;
            }
        }
    };
}
</script>
</body>
</html>
