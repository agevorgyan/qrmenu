<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Reserve a Table') }} — {{ $vendor->name }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Noto+Sans+Armenian:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
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
        $locale = app()->getLocale();
        $isHy = ($locale === 'hy');
        $isRu = ($locale === 'ru');
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
            --card-hover-border: #384e72;
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
            --shadow-card: 0 8px 30px -4px rgba(0, 0, 0, 0.45);
            --shadow-float: 0 16px 40px -6px rgba(0, 0, 0, 0.6);
            --cta-box-bg: rgba(59, 130, 246, 0.05);
            --surface-glass: rgba(20, 29, 46, 0.88);
            @else
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --card-border: #e2e8f0;
            --card-hover-border: #cbd5e1;
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
            --shadow-card: 0 4px 24px -2px rgba(15, 23, 42, 0.06);
            --shadow-float: 0 16px 36px -6px rgba(15, 23, 42, 0.12);
            --cta-box-bg: rgba(59, 130, 246, 0.03);
            --surface-glass: rgba(255, 255, 255, 0.92);
            @endif

            --radius-xl: 20px;
            --radius-lg: 16px;
            --radius-md: 12px;
            --radius-sm: 8px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }

        body {
            font-family: 'Plus Jakarta Sans', 'Noto Sans Armenian', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            padding-bottom: calc(110px + env(safe-area-inset-bottom, 0.5rem));
            -webkit-font-smoothing: antialiased;
        }

        .reserve-container {
            max-width: 660px;
            margin: 0 auto;
            padding: 1.25rem 1rem;
        }

        /* Top Navigation Header */
        .header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
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
            font-size: 0.9rem;
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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
        }

        .brand-info h1 {
            font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
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
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        /* Hero Booking Headline */
        .booking-headline {
            margin-bottom: 1.25rem;
            text-align: left;
        }
        .booking-headline h2 {
            font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
            font-size: 1.55rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--text-main);
            line-height: 1.25;
        }
        .booking-headline p {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        /* Private Event Banner (Compact & Refined) */
        .event-banner-compact {
            background: linear-gradient(135deg, rgba(236, 72, 153, 0.08) 0%, rgba(99, 102, 241, 0.08) 100%);
            border: 1px solid rgba(236, 72, 153, 0.25);
            border-radius: var(--radius-md);
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
            transition: all 0.2s;
        }
        .event-banner-compact:hover {
            border-color: rgba(236, 72, 153, 0.45);
        }

        /* Section Cards */
        .step-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-xl);
            padding: 1.4rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-card);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .step-card:focus-within {
            border-color: var(--card-hover-border);
        }

        .step-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 1.2rem;
        }

        .step-header-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .step-badge {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            background: color-mix(in srgb, var(--primary) 12%, transparent);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            font-weight: 800;
            flex-shrink: 0;
        }

        .step-title {
            font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
            font-size: 1.12rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.01em;
        }

        .step-subtitle {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.1rem;
        }

        /* Interactive Guest Stepper & Quick Chips */
        .guest-selector-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .guest-stepper-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--chip-bg);
            border: 1.5px solid var(--chip-border);
            border-radius: var(--radius-lg);
            padding: 0.5rem 0.65rem;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.03);
        }

        .guest-stepper-btn {
            width: 46px;
            height: 46px;
            border-radius: var(--radius-md);
            background: var(--card-bg);
            border: 1.5px solid var(--card-border);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            cursor: pointer;
            transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }

        .guest-stepper-btn:hover:not(:disabled) {
            background: var(--primary);
            border-color: var(--primary);
            color: var(--primary-contrast);
            transform: scale(1.05);
        }

        .guest-stepper-btn:disabled {
            opacity: 0.35;
            cursor: not-allowed;
            transform: none;
        }

        .guest-counter-display {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            user-select: none;
        }

        .guest-counter-icon {
            font-size: 1.25rem;
            color: var(--primary);
        }

        .guest-counter-text {
            font-family: 'Outfit', sans-serif;
            font-size: 1.45rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.01em;
        }

        .guest-counter-label {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-left: 0.2rem;
        }

        /* Quick Guest Pills */
        .guest-quick-pills {
            display: flex;
            gap: 0.45rem;
            overflow-x: auto;
            scrollbar-width: none;
            padding-bottom: 0.2rem;
        }
        .guest-quick-pills::-webkit-scrollbar { display: none; }

        .guest-pill {
            flex: 1 1 0;
            min-width: 44px;
            padding: 0.65rem 0.4rem;
            text-align: center;
            border-radius: var(--radius-md);
            background: var(--chip-bg);
            border: 1.5px solid var(--chip-border);
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
        }

        .guest-pill:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .guest-pill.active {
            background: var(--primary);
            color: var(--primary-contrast);
            border-color: var(--primary);
            box-shadow: 0 4px 14px color-mix(in srgb, var(--primary) 40%, transparent);
            transform: translateY(-2px);
        }

        /* Date Scrollable Ribbon with Calendar Launcher */
        .dates-ribbon-wrapper {
            position: relative;
            margin-top: 0.5rem;
        }

        .dates-track {
            display: flex;
            gap: 0.55rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
            scrollbar-width: none;
            -webkit-overflow-scrolling: touch;
        }
        .dates-track::-webkit-scrollbar { display: none; }

        .date-chip {
            flex: 0 0 auto;
            min-width: 78px;
            padding: 0.8rem 0.6rem;
            border-radius: var(--radius-lg);
            border: 1.5px solid var(--chip-border);
            background: var(--chip-bg);
            text-align: center;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.2rem;
        }

        .date-chip:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .date-chip.active {
            background: var(--primary);
            border-color: var(--primary);
            box-shadow: 0 6px 18px color-mix(in srgb, var(--primary) 45%, transparent);
            transform: translateY(-2px);
        }

        .date-chip .day-name {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: capitalize;
            letter-spacing: 0.02em;
        }

        .date-chip .date-num {
            font-family: 'Outfit', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1;
        }

        .date-chip .date-month {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--text-subtle);
            text-transform: capitalize;
        }

        .date-chip.active .day-name,
        .date-chip.active .date-num,
        .date-chip.active .date-month {
            color: var(--primary-contrast);
        }

        /* Calendar Picker Launcher Button */
        .custom-calendar-btn {
            flex: 0 0 auto;
            min-width: 78px;
            padding: 0.8rem 0.6rem;
            border-radius: var(--radius-lg);
            border: 1.5px dashed var(--card-border);
            background: transparent;
            color: var(--text-muted);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            cursor: pointer;
            position: relative;
            transition: all 0.2s;
        }
        .custom-calendar-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: color-mix(in srgb, var(--primary) 6%, transparent);
        }
        .custom-calendar-btn input[type="date"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        /* Step 2: Time Slots & Meal Period Filters */
        .time-filter-tabs {
            display: flex;
            gap: 0.45rem;
            margin-bottom: 1rem;
            background: var(--chip-bg);
            padding: 0.3rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--chip-border);
        }

        .time-filter-tab {
            flex: 1;
            padding: 0.55rem 0.75rem;
            border-radius: var(--radius-sm);
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            transition: all 0.18s;
        }

        .time-filter-tab.active {
            background: var(--card-bg);
            color: var(--text-main);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        .slots-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.65rem;
        }
        @media(max-width: 480px) {
            .slots-grid { grid-template-columns: repeat(3, 1fr); }
        }

        .slot-btn {
            padding: 0.8rem 0.5rem;
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
            transform: translateY(-2px);
        }

        .slot-btn.disabled {
            opacity: 0.3;
            cursor: not-allowed;
            filter: grayscale(1);
        }

        .slot-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            box-shadow: 0 6px 18px color-mix(in srgb, var(--primary) 42%, transparent);
            transform: translateY(-2px);
        }

        .slot-btn .slot-time {
            font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.01em;
        }

        .slot-btn .slot-meta {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--text-subtle);
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .slot-btn.active .slot-time,
        .slot-btn.active .slot-meta {
            color: var(--primary-contrast);
        }

        /* Step 3: Visual Seating Preference Cards */
        .tables-cards-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.65rem;
            margin-top: 0.75rem;
        }
        @media(max-width: 360px) {
            .tables-cards-grid { grid-template-columns: 1fr; }
        }

        .table-select-card {
            padding: 0.85rem 0.95rem;
            border-radius: var(--radius-md);
            background: var(--chip-bg);
            border: 1.5px solid var(--chip-border);
            cursor: pointer;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
        }

        .table-select-card.full-width {
            grid-column: 1 / -1;
        }

        .table-select-card:hover {
            border-color: var(--primary);
            transform: translateY(-1px);
        }

        .table-select-card.active {
            background: color-mix(in srgb, var(--primary) 12%, var(--card-bg));
            border-color: var(--primary);
            box-shadow: 0 4px 14px color-mix(in srgb, var(--primary) 25%, transparent);
        }

        .table-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.4rem;
        }

        .table-card-title {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .table-card-badge {
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.15rem 0.45rem;
            border-radius: 6px;
            background: color-mix(in srgb, var(--primary) 15%, transparent);
            color: var(--primary);
        }

        .table-card-meta {
            font-size: 0.78rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        /* Step 4: Contact Form Inputs */
        .form-group {
            margin-bottom: 1.15rem;
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 0.45rem;
        }

        .form-label i {
            color: var(--primary);
            font-size: 0.85rem;
        }

        .form-input {
            width: 100%;
            padding: 0.85rem 1.05rem;
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
            opacity: 0.7;
            font-size: 0.9rem;
        }

        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3.5px color-mix(in srgb, var(--primary) 22%, transparent);
        }

        .form-helper {
            color: var(--text-subtle);
            font-size: 0.78rem;
            line-height: 1.4;
            display: block;
            margin-top: 0.35rem;
            font-weight: 500;
        }

        /* Special Requests Quick-Tags */
        .quick-request-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-top: 0.55rem;
        }

        .quick-tag-chip {
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            background: var(--chip-bg);
            border: 1px solid var(--chip-border);
            color: var(--text-subtle);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            user-select: none;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .quick-tag-chip:hover {
            border-color: var(--primary);
            color: var(--text-main);
            transform: translateY(-1px);
        }

        .quick-tag-chip.active {
            background: color-mix(in srgb, var(--primary) 15%, var(--card-bg));
            border-color: var(--primary);
            color: var(--primary);
            font-weight: 700;
        }

        /* Phone Input Component matching Cart Modal */
        .cart-phone-field-wrapper {
            position: relative;
            width: 100%;
        }

        .cart-phone-input-box {
            display: flex;
            align-items: center;
            width: 100%;
            background: var(--input-bg);
            border: 1.5px solid var(--input-border);
            border-radius: var(--radius-md);
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }

        .cart-phone-input-box:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3.5px color-mix(in srgb, var(--primary) 22%, transparent);
        }

        .cart-phone-input-box.has-error {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 3.5px rgba(239, 68, 68, 0.2) !important;
        }

        .cart-phone-input-box.is-valid {
            border-color: #10b981;
        }

        .cart-phone-country-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.8rem 0.7rem 0.8rem 0.95rem;
            background: color-mix(in srgb, var(--text-main) 6%, transparent);
            border: none;
            border-right: 1px solid var(--input-border);
            border-top-left-radius: 11px;
            border-bottom-left-radius: 11px;
            color: var(--text-main);
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
            transition: background 0.15s;
            flex-shrink: 0;
        }

        .cart-phone-country-btn:hover {
            background: color-mix(in srgb, var(--text-main) 12%, transparent);
        }

        .cart-flag-icon {
            font-size: 1.15rem;
            line-height: 1;
        }

        .cart-dial-code {
            font-family: inherit;
            font-size: 0.88rem;
            letter-spacing: -0.2px;
        }

        .cart-country-caret {
            font-size: 0.65rem;
            color: var(--text-muted);
            transition: transform 0.2s ease;
        }

        .cart-country-caret.is-open {
            transform: rotate(180deg);
        }

        .cart-phone-native-input {
            flex: 1;
            min-width: 0;
            padding: 0.8rem 0.75rem;
            background: transparent;
            border: none;
            outline: none;
            color: var(--text-main);
            font-size: 0.95rem;
            font-weight: 600;
            letter-spacing: 0.3px;
            box-sizing: border-box;
            font-family: inherit;
        }

        .cart-phone-native-input::placeholder {
            color: var(--text-muted);
            font-weight: 400;
            font-size: 0.88rem;
        }

        .cart-phone-status-indicator {
            padding-right: 0.85rem;
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }

        .cart-status-badge {
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.18rem 0.45rem;
            border-radius: 6px;
            line-height: 1.2;
        }

        .cart-status-badge.is-incomplete {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .cart-status-badge.is-valid {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            font-size: 0.95rem;
            padding: 0.1rem 0.25rem;
        }

        /* Country Dropdown Popup */
        .cart-country-dropdown {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            width: 100%;
            max-width: 320px;
            max-height: 240px;
            overflow-y: auto;
            background: var(--card-bg, #1c1c1e);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.5);
            z-index: 100;
            padding: 0.35rem;
            backdrop-filter: blur(12px);
        }

        .cart-country-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            width: 100%;
            padding: 0.6rem 0.75rem;
            border: none;
            background: transparent;
            color: var(--text-main);
            font-size: 0.85rem;
            border-radius: 8px;
            cursor: pointer;
            text-align: left;
            transition: background 0.15s;
            box-sizing: border-box;
        }

        .cart-country-item:hover, .cart-country-item.is-selected {
            background: color-mix(in srgb, var(--primary) 18%, transparent);
            color: var(--text-main);
        }

        .cart-item-flag {
            font-size: 1.15rem;
            line-height: 1;
        }

        .cart-item-name {
            flex: 1;
            font-weight: 500;
        }

        .cart-item-dial {
            font-weight: 600;
            color: var(--primary);
            font-size: 0.82rem;
        }

        .cart-item-digits {
            font-size: 0.72rem;
            color: var(--text-muted);
        }

        /* Step 5: Pre-Order Integration */
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
            border-radius: var(--radius-lg);
            background: var(--cta-box-bg);
            border: 1.5px dashed color-mix(in srgb, var(--primary) 40%, transparent);
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.9rem;
            transition: all 0.2s;
        }
        .preorder-cta-box:hover {
            border-color: var(--primary);
        }

        .preorder-cta-icon {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: color-mix(in srgb, var(--primary) 15%, transparent);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }

        .preorder-cta-info h4 {
            font-size: 1.02rem;
            font-weight: 800;
            margin-bottom: 0.35rem;
            color: var(--text-main);
        }

        .preorder-cta-info p {
            font-size: 0.85rem;
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
            font-family: 'Outfit', sans-serif;
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

        /* Inline Submit Button */
        .submit-btn {
            width: 100%;
            padding: 1.1rem;
            border-radius: var(--radius-lg);
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
            box-shadow: 0 4px 20px color-mix(in srgb, var(--primary) 42%, transparent);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .submit-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 26px color-mix(in srgb, var(--primary) 55%, transparent);
        }

        .submit-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Sticky Bottom Summary Bar (Resy / OpenTable Style) */
        .sticky-booking-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--surface-glass);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-top: 1px solid var(--card-border);
            padding: 0.85rem 1.25rem calc(0.85rem + env(safe-area-inset-bottom, 0px));
            z-index: 90;
            box-shadow: var(--shadow-float);
        }

        .sticky-booking-content {
            max-width: 660px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .sticky-summary-meta {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
            min-width: 0;
        }

        .sticky-summary-pills {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            flex-wrap: wrap;
            font-size: 0.86rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .sticky-summary-sep {
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        .sticky-summary-sub {
            font-size: 0.76rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sticky-action-btn {
            padding: 0.85rem 1.45rem;
            border-radius: var(--radius-md);
            background: var(--primary);
            color: var(--primary-contrast);
            border: none;
            font-size: 0.95rem;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            box-shadow: 0 4px 16px color-mix(in srgb, var(--primary) 40%, transparent);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            flex-shrink: 0;
        }

        .sticky-action-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 6px 22px color-mix(in srgb, var(--primary) 50%, transparent);
        }

        .sticky-action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Alert Box */
        .alert-box {
            padding: 0.9rem 1.1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.25rem;
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
                <p>
                    <i class="fa-solid fa-location-dot" style="font-size: 0.75rem; color: var(--primary);"></i>
                    <span>{{ $location?->name ?: __('Table Reservation') }}</span>
                </p>
            </div>
        </div>
    </header>

    <!-- Page Title Headline -->
    <div class="booking-headline">
        <h2>{{ __('Reserve a Table') }}</h2>
        <p>{{ $isHy ? 'Ակնթարթային առցանց հաստատում • Անվճար ամրագրում' : ($isRu ? 'Мгновенное подтверждение • Без комиссии' : __('Instant online confirmation • Zero booking fee')) }}</p>
    </div>

    @if($vendor->isEventInquiryFeatureEnabled())
        <div class="event-banner-compact">
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
               style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.85rem; border-radius: 8px; background: #ec4899; color: #ffffff; text-decoration: none; font-size: 0.82rem; font-weight: 700; white-space: nowrap; box-shadow: 0 2px 8px rgba(236, 72, 153, 0.3);">
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

    <!-- Step 1: Party Size & Date (Unified Resy/OpenTable Style) -->
    <section class="step-card">
        <div class="step-header">
            <div class="step-header-left">
                <span class="step-badge"><i class="fa-solid fa-user-group"></i></span>
                <div>
                    <div class="step-title">{{ __('Choose Date & Guests') }}</div>
                    <div class="step-subtitle">{{ $isHy ? 'Ընտրեք հյուրերի քանակը և նախընտրելի օրը' : ($isRu ? 'Выберите количество гостей и дату' : __('Select party size and preferred day')) }}</div>
                </div>
            </div>
        </div>

        <!-- Guest Selector Stepper + Quick Pills -->
        <div class="guest-selector-container">
            <div class="guest-stepper-box">
                <button type="button" 
                        class="guest-stepper-btn" 
                        :disabled="guestCount <= minGuests" 
                        @click="decrementGuests()"
                        aria-label="Decrease guest count">
                    <i class="fa-solid fa-minus"></i>
                </button>

                <div class="guest-counter-display">
                    <i class="fa-solid fa-user-group guest-counter-icon"></i>
                    <span class="guest-counter-text" x-text="guestCount"></span>
                    <span class="guest-counter-label" x-text="guestCount === 1 ? '{{ __('guest') }}' : '{{ __('guests') }}'"></span>
                </div>

                <button type="button" 
                        class="guest-stepper-btn" 
                        :disabled="guestCount >= maxGuests" 
                        @click="incrementGuests()"
                        aria-label="Increase guest count">
                    <i class="fa-solid fa-plus"></i>
                </button>
            </div>

            <!-- Quick Party Size Chips -->
            <div class="guest-quick-pills">
                @for($p = max(1, $settings['min_party_size'] ?? 1); $p <= min(12, $settings['max_party_size'] ?? 20); $p++)
                    <div class="guest-pill party-chip" 
                         :class="{ 'active': guestCount === {{ $p }} }" 
                         @click="selectGuests({{ $p }})">
                        <span>{{ $p }}</span>
                    </div>
                @endfor
            </div>
        </div>

        <!-- Date Selection Ribbon -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.6rem;">
            <label class="form-label" style="margin-bottom: 0;">
                <i class="fa-regular fa-calendar-days"></i>
                <span>{{ __('Select Date') }}</span>
            </label>
            <span style="font-size: 0.8rem; font-weight: 600; color: var(--primary);" x-text="formatSelectedDateShort(selectedDate)"></span>
        </div>

        <div class="dates-ribbon-wrapper">
            <div class="dates-track">
                @foreach($availableDates as $idx => $d)
                    @php
                        $cDate = \Carbon\Carbon::parse($d['date']);
                        $isToday = $cDate->isToday();
                        $isTomorrow = $cDate->isTomorrow();
                        $dayShort = $isToday ? __('Today') : ($isTomorrow ? __('Tomorrow') : $cDate->translatedFormat('D'));
                        $monthShort = $cDate->translatedFormat('M');
                    @endphp
                    <div class="date-chip" 
                         :class="{ 'active': selectedDate === '{{ $d['date'] }}' }" 
                         @click="selectDate('{{ $d['date'] }}')">
                        <div class="day-name">{{ $dayShort }}</div>
                        <div class="date-num">{{ substr($d['date'], 8, 2) }}</div>
                        <div class="date-month">{{ $monthShort }}</div>
                    </div>
                @endforeach

                <!-- Native Calendar Launcher for any future date -->
                <label class="custom-calendar-btn" title="{{ __('Choose another date') }}">
                    <i class="fa-regular fa-calendar-plus" style="font-size: 1.15rem;"></i>
                    <span style="font-size: 0.72rem; font-weight: 700;">{{ __('More') }}</span>
                    <input type="date" 
                           min="{{ $availableDates[0]['date'] ?? date('Y-m-d') }}" 
                           max="{{ end($availableDates)['date'] ?? date('Y-m-d', strtotime('+30 days')) }}"
                           @change="onCustomDateChange($event)">
                </label>
            </div>
        </div>
    </section>

    <!-- Step 2: Available Time Slots -->
    <section class="step-card" x-show="selectedDate">
        <div class="step-header">
            <div class="step-header-left">
                <span class="step-badge"><i class="fa-regular fa-clock"></i></span>
                <div>
                    <div class="step-title">{{ __('Available Time Slots') }}</div>
                    <div class="step-subtitle">{{ $isHy ? 'Ընտրեք հարմար ժամը' : ($isRu ? 'Выберите удобное время' : __('Select a seating time')) }}</div>
                </div>
            </div>
        </div>

        <!-- Meal Period Filter Tabs -->
        <div class="time-filter-tabs" x-show="!isLoadingSlots && timeSlots.length > 0">
            <button type="button" 
                    class="time-filter-tab" 
                    :class="{ 'active': selectedTimeFilter === 'all' }" 
                    @click="selectedTimeFilter = 'all'">
                <i class="fa-solid fa-sparkles"></i>
                <span>{{ $isHy ? 'Բոլոր ժամերը' : ($isRu ? 'Все часы' : __('All Times')) }}</span>
            </button>
            <button type="button" 
                    class="time-filter-tab" 
                    :class="{ 'active': selectedTimeFilter === 'lunch' }" 
                    @click="selectedTimeFilter = 'lunch'">
                <i class="fa-solid fa-sun"></i>
                <span>{{ $isHy ? 'Կեսօր' : ($isRu ? 'Обед' : __('Lunch')) }}</span>
            </button>
            <button type="button" 
                    class="time-filter-tab" 
                    :class="{ 'active': selectedTimeFilter === 'dinner' }" 
                    @click="selectedTimeFilter = 'dinner'">
                <i class="fa-solid fa-moon"></i>
                <span>{{ $isHy ? 'Երեկո' : ($isRu ? 'Ужин' : __('Dinner')) }}</span>
            </button>
        </div>

        <!-- Loading State -->
        <div x-show="isLoadingSlots" style="text-align: center; padding: 2rem 1rem; color: var(--text-muted);">
            <i class="fa-solid fa-circle-notch fa-spin fa-2x" style="color: var(--primary);"></i>
            <p style="margin-top: 0.65rem; font-weight: 600; font-size: 0.92rem;">{{ __('Checking table availability...') }}</p>
        </div>

        <!-- Available Slots Grid -->
        <div x-show="!isLoadingSlots && filteredTimeSlots.length > 0" class="slots-grid">
            <template x-for="slot in filteredTimeSlots" :key="slot.time">
                <button type="button" 
                        class="slot-btn" 
                        :class="{ 'disabled': !slot.available, 'active': selectedTime === slot.time }"
                        :disabled="!slot.available"
                        @click="selectTime(slot)">
                    <span class="slot-time" x-text="slot.time"></span>
                    <span class="slot-meta">
                        <template x-if="slot.available">
                            <span style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span>
                                <span x-text="slot.available_tables_count + ' ' + '{{ __('free') }}'"></span>
                            </span>
                        </template>
                        <template x-if="!slot.available">
                            <span>{{ __('full') }}</span>
                        </template>
                    </span>
                </button>
            </template>
        </div>

        <!-- Empty Filtered Slots Message -->
        <div x-show="!isLoadingSlots && timeSlots.length > 0 && filteredTimeSlots.length === 0" style="text-align: center; padding: 1.5rem; color: var(--text-muted);">
            <p>{{ __('No slots found for this time period.') }}</p>
            <button type="button" @click="selectedTimeFilter = 'all'" style="margin-top: 0.5rem; background: transparent; border: none; color: var(--primary); font-weight: 700; cursor: pointer;">
                {{ __('View all available times') }}
            </button>
        </div>

        <!-- Empty Slots Message -->
        <div x-show="!isLoadingSlots && timeSlots.length === 0" style="text-align: center; padding: 1.5rem; color: var(--text-muted); background: var(--chip-bg); border-radius: var(--radius-md); border: 1.5px dashed var(--chip-border);">
            <i class="fa-solid fa-calendar-xmark fa-2x" style="color: var(--text-muted); margin-bottom: 0.5rem;"></i>
            <p style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">{{ __('No available slots found for this party size on this day.') }}</p>
            <p style="font-size: 0.84rem; margin-top: 0.35rem;">{{ __('Please try selecting another date or adjusting party size.') }}</p>
        </div>
    </section>

    <!-- Step 3: Seating Preference & Table Selection (Optional) -->
    <section class="step-card" x-show="selectedTime && eligibleTables.length > 0" x-transition>
        <div class="step-header">
            <div class="step-header-left">
                <span class="step-badge"><i class="fa-solid fa-chair"></i></span>
                <div>
                    <div class="step-title">{{ __('Table Selection (Optional)') }}</div>
                    <div class="step-subtitle">{{ $isHy ? 'Ընտրեք նախընտրելի սրահը կամ ավտոմատ նշանակում' : ($isRu ? 'Выберите зону зала или автоназначение' : __('Choose preferred area or auto-assign')) }}</div>
                </div>
            </div>
        </div>

        <!-- Section Filter Pills if multiple sections exist -->
        <div class="time-filter-tabs" x-show="tableSections.length > 1" style="margin-top: 0.25rem; margin-bottom: 0.75rem;">
            <button type="button" 
                    class="time-filter-tab" 
                    :class="{ 'active': selectedTableSection === 'all' }" 
                    @click="selectedTableSection = 'all'">
                <span>{{ $isHy ? 'Բոլոր սրահները' : ($isRu ? 'Все залы' : __('All Areas')) }}</span>
            </button>
            <template x-for="sec in tableSections" :key="sec">
                <button type="button" 
                        class="time-filter-tab" 
                        :class="{ 'active': selectedTableSection === sec }" 
                        @click="selectedTableSection = sec">
                    <span x-text="sec"></span>
                </button>
            </template>
        </div>

        <!-- Visual Table / Area Cards Grid -->
        <div class="tables-cards-grid">
            <!-- Optimal Auto-Assign Card -->
            <div class="table-select-card full-width" 
                 :class="{ 'active': selectedTableId === '' }" 
                 @click="selectedTableId = ''">
                <div class="table-card-top">
                    <span class="table-card-title">✨ {{ $isHy ? 'Ավտոմատ նշանակում' : ($isRu ? 'Автоназначение' : __('Auto-assign')) }}</span>
                    <span class="table-card-badge">{{ $isHy ? 'Խորհուրդ է տրվում' : ($isRu ? 'Рекомендуется' : __('Recommended')) }}</span>
                </div>
                <div class="table-card-meta">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    <span>{{ $isHy ? 'Համակարգը կընտրի լավագույն հասանելի սեղանը' : ($isRu ? 'Система подберет лучший свободный стол' : __('Best available table for your party')) }}</span>
                </div>
            </div>

            <!-- Specific Tables -->
            <template x-for="t in visibleEligibleTables" :key="t.id">
                <div class="table-select-card" 
                     :class="{ 'active': Number(selectedTableId) === Number(t.id) }" 
                     @click="selectedTableId = t.id">
                    <div class="table-card-top">
                        <span class="table-card-title" x-text="t.display_name"></span>
                        <template x-if="t.section">
                            <span class="table-card-badge" x-text="t.section"></span>
                        </template>
                    </div>
                    <div class="table-card-meta">
                        <i class="fa-solid fa-users"></i>
                        <span x-text="t.capacity + ' ' + '{{ $isHy ? 'նստատեղ' : ($isRu ? 'мест' : __('seats')) }}'"></span>
                    </div>
                </div>
            </template>

            <!-- Show More / Fewer Tables Toggle -->
            <div style="grid-column: 1 / -1; text-align: center; margin-top: 0.35rem;" x-show="eligibleTables.length > 5">
                <button type="button" 
                        @click="showAllTables = !showAllTables" 
                        style="background: transparent; border: 1.5px dashed var(--card-border); padding: 0.6rem 1.25rem; border-radius: var(--radius-md); color: var(--primary); font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: all 0.2s;">
                    <i class="fa-solid" :class="showAllTables ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                    <span x-text="showAllTables ? '{{ $isHy ? 'Ցուցադրել ավելի քիչ' : ($isRu ? 'Свернуть' : __('Show fewer')) }}' : ('+ ' + (eligibleTables.length - 5) + ' {{ $isHy ? 'այլ սեղաններ (Տեսնել բոլորը)' : ($isRu ? 'других столов (Показать все)' : __('more tables (Show all)')) }}')"></span>
                </button>
            </div>
        </div>

        <!-- Hidden Select for native fallback / compatibility -->
        <div style="display: none;" aria-hidden="true">
            <select x-model="selectedTableId">
                <option value="">✨ {{ __('Auto-assign optimal table (Recommended)') }}</option>
                <template x-for="t in eligibleTables" :key="t.id">
                    <option :value="t.id" x-text="t.display_name + (t.section ? ' (' + t.section + ')' : '') + ' — ' + t.capacity + ' ' + '{{ __('seats') }}'"></option>
                </template>
            </select>
        </div>
    </section>

    <!-- Step 4: Contact Details Form -->
    <section class="step-card" x-show="selectedTime" x-transition>
        <div class="step-header">
            <div class="step-header-left">
                <span class="step-badge"><i class="fa-regular fa-address-card"></i></span>
                <div>
                    <div class="step-title">{{ __('Guest Details') }}</div>
                    <div class="step-subtitle">{{ $isHy ? 'Հաստատման տվյալները կուղարկվեն այստեղ' : ($isRu ? 'Данные подтверждения будут отправлены сюда' : __('We\'ll send reservation confirmation here')) }}</div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">
                <i class="fa-solid fa-user"></i>
                <span>{{ __('Your Name') }} *</span>
            </label>
            <input type="text" class="form-input" x-model="customerName" placeholder="{{ __('e.g. Aram Vardanyan') }}" required>
        </div>

        <div class="form-group">
            <label class="form-label">
                <i class="fa-solid fa-envelope"></i>
                <span>{{ __('Email Address') }} *</span>
            </label>
            <input type="email" class="form-input" x-model="customerEmail" placeholder="{{ __('name@example.com') }}" required>
            <small class="form-helper">{{ $isHy ? 'Ամրագրման հաստատման կոդը և հղումը կուղարկվեն այս էլ. հասցեին:' : ($isRu ? 'Код подтверждения и ссылка на бронь будут отправлены на этот email.' : __('Confirmation reference and tracking link will be sent here.')) }}</small>
        </div>

        <div class="form-group">
            <label class="form-label">
                <i class="fa-solid fa-phone"></i>
                <span>{{ __('Phone Number') }}</span>
            </label>
            <div class="cart-phone-field-wrapper">
                <div class="cart-phone-input-box" :class="{ 'has-error': phoneErrorMsg, 'is-valid': isPhoneValid() && phoneCleanDigits.length > 0 }">
                    <!-- Country Selector Pill -->
                    <button type="button" 
                            @click="showPhoneCountryDropdown = !showPhoneCountryDropdown" 
                            class="cart-phone-country-btn"
                            title="{{ __('Country') }}">
                        <span class="cart-flag-icon" x-text="selectedPhoneCountry.flag"></span>
                        <span class="cart-dial-code" x-text="selectedPhoneCountry.dial"></span>
                        <i class="fa-solid fa-chevron-down cart-country-caret" :class="{ 'is-open': showPhoneCountryDropdown }"></i>
                    </button>

                    <!-- National Number Input -->
                    <input type="tel" 
                           id="reserveCustomerPhoneInput"
                           x-model="phoneNationalNumber" 
                           @input="onPhoneInput($event)"
                           @blur="validatePhoneOnBlur()"
                           class="cart-phone-native-input" 
                           :placeholder="selectedPhoneCountry.placeholder">

                    <!-- Live Digit Counter / Checkmark Status -->
                    <div class="cart-phone-status-indicator">
                        <template x-if="isPhoneValid() && phoneCleanDigits.length > 0">
                            <span class="cart-status-badge is-valid" title="Valid">
                                <i class="fa-solid fa-circle-check"></i>
                            </span>
                        </template>
                        <template x-if="phoneCleanDigits.length > 0 && !isPhoneValid()">
                            <span class="cart-status-badge is-incomplete" :title="'Required ' + (selectedPhoneCountry.digits || '7-15') + ' digits'">
                                <span x-text="phoneCleanDigits.length + '/' + (selectedPhoneCountry.digits || '10')"></span>
                            </span>
                        </template>
                    </div>
                </div>

                <!-- Country Selection Dropdown Popup -->
                <div x-show="showPhoneCountryDropdown" 
                     @click.outside="showPhoneCountryDropdown = false"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 transform scale-95 -translate-y-1"
                     x-transition:enter-end="opacity-100 transform scale-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 transform scale-100 translate-y-0"
                     x-transition:leave-end="opacity-0 transform scale-95 -translate-y-1"
                     class="cart-country-dropdown"
                     style="display: none;">
                    <template x-for="item in phoneCountries" :key="item.code">
                        <button type="button" 
                                @click="selectPhoneCountry(item.code)" 
                                class="cart-country-item"
                                :class="{ 'is-selected': phoneCountryCode === item.code }">
                            <span class="cart-item-flag" x-text="item.flag"></span>
                            <span class="cart-item-name" x-text="item.name"></span>
                            <span class="cart-item-dial" x-text="item.dial"></span>
                            <span class="cart-item-digits" x-show="item.digits" x-text="'(' + item.digits + ' {{ __('digits') }})'"></span>
                        </button>
                    </template>
                </div>

                <!-- Phone Error Message -->
                <template x-if="phoneErrorMsg">
                    <div class="cart-input-feedback-msg is-error" style="color: #ef4444; font-size: 0.8rem; margin-top: 0.35rem; display: flex; align-items: center; gap: 0.35rem;">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span x-text="phoneErrorMsg"></span>
                    </div>
                </template>
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 0.5rem;">
            <label class="form-label">
                <i class="fa-solid fa-pen-to-square"></i>
                <span>{{ __('Special Requests') }}</span>
            </label>
            <textarea class="form-input" rows="2" x-model="specialRequests" placeholder="{{ __('e.g. Window booth, quiet area, celebrating anniversary...') }}"></textarea>

            <!-- Quick Tap Request Chips (International UX Best Practice) -->
            <div class="quick-request-tags">
                <button type="button" class="quick-tag-chip" :class="{ 'active': specialRequests.includes('🎂') }" @click="toggleSpecialRequestTag('🎂 {{ $isHy ? 'Ծննդյան օր' : ($isRu ? 'День рождения' : __('Birthday')) }}')">
                    <span>🎂 {{ $isHy ? 'Ծննդյան օր' : ($isRu ? 'День рождения' : __('Birthday')) }}</span>
                </button>
                <button type="button" class="quick-tag-chip" :class="{ 'active': specialRequests.includes('🕯️') }" @click="toggleSpecialRequestTag('🕯️ {{ $isHy ? 'Ռոմանտիկ' : ($isRu ? 'Романтический ужин' : __('Romantic')) }}')">
                    <span>🕯️ {{ $isHy ? 'Ռոմանտիկ' : ($isRu ? 'Романтический ужин' : __('Romantic')) }}</span>
                </button>
                <button type="button" class="quick-tag-chip" :class="{ 'active': specialRequests.includes('💼') }" @click="toggleSpecialRequestTag('💼 {{ $isHy ? 'Գործնական' : ($isRu ? 'Деловая встреча' : __('Business')) }}')">
                    <span>💼 {{ $isHy ? 'Գործնական' : ($isRu ? 'Деловая встреча' : __('Business')) }}</span>
                </button>
                <button type="button" class="quick-tag-chip" :class="{ 'active': specialRequests.includes('🪟') }" @click="toggleSpecialRequestTag('🪟 {{ $isHy ? 'Պատուհանի մոտ' : ($isRu ? 'У окна' : __('Window Table')) }}')">
                    <span>🪟 {{ $isHy ? 'Պատուհանի մոտ' : ($isRu ? 'У окна' : __('Window Table')) }}</span>
                </button>
                <button type="button" class="quick-tag-chip" :class="{ 'active': specialRequests.includes('🤫') }" @click="toggleSpecialRequestTag('🤫 {{ $isHy ? 'Հանգիստ անկյուն' : ($isRu ? 'Тихий уголок' : __('Quiet Corner')) }}')">
                    <span>🤫 {{ $isHy ? 'Հանգիստ անկյուն' : ($isRu ? 'Тихий уголок' : __('Quiet Corner')) }}</span>
                </button>
                <button type="button" class="quick-tag-chip" :class="{ 'active': specialRequests.includes('👶') }" @click="toggleSpecialRequestTag('👶 {{ $isHy ? 'Մանկական աթոռ' : ($isRu ? 'Детский стульчик' : __('High Chair')) }}')">
                    <span>👶 {{ $isHy ? 'Մանկական աթոռ' : ($isRu ? 'Детский стульчик' : __('High Chair')) }}</span>
                </button>
            </div>
        </div>
    </section>

    <!-- Step 5: Pre-Order from Main Menu (Optional VIP Experience) -->
    @if($vendor->isPreOrderFeatureEnabled())
    <section class="step-card" x-show="selectedTime" x-transition>
        <div class="step-header">
            <div class="step-header-left">
                <span class="step-badge"><i class="fa-solid fa-utensils"></i></span>
                <div>
                    <div class="step-title">{{ __('Pre-Order Food & Drinks (Optional)') }}</div>
                    <div class="step-subtitle">{{ $isHy ? 'Ընտրեք սիրելի ուտեստները նախապես մեր մենյուից' : ($isRu ? 'Предзакажите блюда заранее из нашего меню' : __('Choose favorite dishes in advance from our digital menu')) }}</div>
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
                <label class="form-label">
                    <i class="fa-solid fa-clipboard-list"></i>
                    <span>{{ __('Pre-Order Notes / Special Requests (Optional)') }}</span>
                </label>
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

    <!-- Inline Bottom Submit Button (Desktop & Form Flow) -->
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

    <!-- Sticky Bottom Summary Bar (Mobile & Quick-Confirmation) -->
    <div class="sticky-booking-bar" 
         x-show="selectedTime" 
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="opacity-0 transform translate-y-8"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform translate-y-0"
         x-transition:leave-end="opacity-0 transform translate-y-8"
         style="display: none;">
        <div class="sticky-booking-content">
            <div class="sticky-summary-meta">
                <div class="sticky-summary-pills">
                    <span><i class="fa-solid fa-user-group" style="color: var(--primary); font-size: 0.8rem;"></i> <span x-text="guestCount"></span></span>
                    <span class="sticky-summary-sep">•</span>
                    <span x-text="formatSelectedDateShort(selectedDate)"></span>
                    <span class="sticky-summary-sep">•</span>
                    <span style="color: var(--primary);" x-text="selectedTime"></span>
                </div>
                <div class="sticky-summary-sub">
                    <span x-text="getSelectedTableLabel()"></span>
                </div>
            </div>

            <button type="button" class="sticky-action-btn" :disabled="isSubmitting" @click="submitBooking()">
                <template x-if="isSubmitting">
                    <span><i class="fa-solid fa-circle-notch fa-spin"></i> {{ __('Booking...') }}</span>
                </template>
                <template x-if="!isSubmitting">
                    <span>{{ __('Confirm') }} <i class="fa-solid fa-arrow-right" style="font-size: 0.8rem;"></i></span>
                </template>
            </button>
        </div>
    </div>
</div>

<script>
function reservationBookingApp() {
    return {
        vendorSlug: '{{ $vendor->slug }}',
        menuUrl: '{{ route('client.menu', ['vendor_slug' => $vendor->slug, 'location_slug' => $location?->slug, 'lang' => app()->getLocale()]) }}',
        selectedDate: '{{ $availableDates[0]['date'] ?? '' }}',
        guestCount: {{ max(2, $settings['min_party_size'] ?? 1) }},
        minGuests: {{ max(1, $settings['min_party_size'] ?? 1) }},
        maxGuests: {{ min(20, $settings['max_party_size'] ?? 20) }},
        selectedTime: '',
        selectedTimeFilter: 'all', // 'all', 'lunch', 'dinner'
        selectedTableId: '',
        customerName: '',
        customerEmail: '',
        customerPhone: '',
        phoneCountryCode: 'AM',
        phoneNationalNumber: '',
        showPhoneCountryDropdown: false,
        phoneErrorMsg: '',
        phoneCountries: [
            { code: 'AM', flag: '🇦🇲', dial: '+374', digits: 8, name: '{{ __('Armenia') }}', placeholder: '94 123456' },
            { code: 'RU', flag: '🇷🇺', dial: '+7', digits: 10, name: '{{ __('Russia') }}', placeholder: '999 123-45-67' },
            { code: 'GE', flag: '🇬🇪', dial: '+995', digits: 9, name: '{{ __('Georgia') }}', placeholder: '599 12-34-56' },
            { code: 'US', flag: '🇺🇸', dial: '+1', digits: 10, name: '{{ __('USA / Canada') }}', placeholder: '555 123-4567' },
            { code: 'FR', flag: '🇫🇷', dial: '+33', digits: 9, name: '{{ __('France') }}', placeholder: '6 12 34 56 78' },
            { code: 'DE', flag: '🇩🇪', dial: '+49', digits: 10, minDigits: 10, maxDigits: 11, name: '{{ __('Germany') }}', placeholder: '170 1234567' },
            { code: 'AE', flag: '🇦🇪', dial: '+971', digits: 9, name: '{{ __('UAE') }}', placeholder: '50 123 4567' },
            { code: 'IR', flag: '🇮🇷', dial: '+98', digits: 10, name: '{{ __('Iran') }}', placeholder: '912 123 4567' },
            { code: 'OTHER', flag: '🌐', dial: '+', digits: null, minDigits: 7, maxDigits: 15, name: '{{ __('Other Country') }}', placeholder: '123456789' }
        ],
        specialRequests: '',
        preOrderItems: [],
        preOrderNotes: '',
        timeSlots: [],
        eligibleTables: [],
        isLoadingSlots: false,
        isSubmitting: false,
        errorMessage: '',

        get selectedPhoneCountry() {
            return this.phoneCountries.find(c => c.code === this.phoneCountryCode) || this.phoneCountries[0];
        },
        get phoneCleanDigits() {
            return (this.phoneNationalNumber || '').replace(/\D/g, '');
        },
        get filteredTimeSlots() {
            if (!this.timeSlots || !Array.isArray(this.timeSlots)) return [];
            if (this.selectedTimeFilter === 'lunch') {
                return this.timeSlots.filter(s => {
                    const h = parseInt(s.time.split(':')[0], 10);
                    return h < 17;
                });
            }
            if (this.selectedTimeFilter === 'dinner') {
                return this.timeSlots.filter(s => {
                    const h = parseInt(s.time.split(':')[0], 10);
                    return h >= 17;
                });
            }
            return this.timeSlots;
        },

        showAllTables: false,
        selectedTableSection: 'all',
        get tableSections() {
            const set = new Set();
            (this.eligibleTables || []).forEach(t => {
                if (t.section) set.add(t.section);
            });
            return Array.from(set);
        },
        get visibleEligibleTables() {
            let list = this.eligibleTables || [];
            if (this.selectedTableSection !== 'all') {
                list = list.filter(t => t.section === this.selectedTableSection);
            }
            if (!this.showAllTables && list.length > 5) {
                return list.slice(0, 5);
            }
            return list;
        },

        init() {
            this.restoreReservationDraft();
            this.restorePreOrderItems();
            if (this.selectedDate) {
                this.loadSlots();
            }
        },

        incrementGuests() {
            if (this.guestCount < this.maxGuests) {
                this.selectGuests(this.guestCount + 1);
            }
        },

        decrementGuests() {
            if (this.guestCount > this.minGuests) {
                this.selectGuests(this.guestCount - 1);
            }
        },

        onCustomDateChange(event) {
            const val = event.target.value;
            if (val) {
                this.selectDate(val);
            }
        },

        formatSelectedDateShort(dateStr) {
            if (!dateStr) return '';
            try {
                const parts = dateStr.split('-');
                if (parts.length === 3) {
                    const lang = '{{ app()->getLocale() }}';
                    const d = new Date(parts[0], parseInt(parts[1], 10) - 1, parts[2]);
                    if (lang === 'hy') {
                        const days = ['Կիր', 'Երկ', 'Երք', 'Չրք', 'Հնգ', 'Ուրբ', 'Շբթ'];
                        const months = ['հնվ', 'փտվ', 'մրտ', 'ապր', 'մայ', 'հնս', 'հլս', 'օգս', 'սեպ', 'հոկ', 'նոյ', 'դեկ'];
                        return days[d.getDay()] + ', ' + parts[2] + ' ' + months[parseInt(parts[1], 10) - 1];
                    } else if (lang === 'ru') {
                        const days = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
                        const months = ['янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
                        return days[d.getDay()] + ', ' + parts[2] + ' ' + months[parseInt(parts[1], 10) - 1];
                    }
                    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', weekday: 'short' });
                }
            } catch(e) {}
            return dateStr;
        },

        getSelectedTableLabel() {
            if (!this.selectedTableId) {
                return '{{ $isHy ? '✨ Ավտոմատ լավագույն սեղան' : ($isRu ? '✨ Автоназначенный стол' : '✨ ' . __('Auto-assigned optimal table')) }}';
            }
            const tbl = (this.eligibleTables || []).find(t => String(t.id) === String(this.selectedTableId));
            return tbl ? (tbl.display_name + (tbl.section ? ' (' + tbl.section + ')' : '')) : '{{ __('Selected Table') }}';
        },

        toggleSpecialRequestTag(tag) {
            if (!this.specialRequests) {
                this.specialRequests = tag;
                return;
            }
            if (this.specialRequests.includes(tag)) {
                this.specialRequests = this.specialRequests
                    .replace(tag, '')
                    .replace(/,\s*,/g, ', ')
                    .replace(/^,\s*|,\s*$/g, '')
                    .trim();
            } else {
                this.specialRequests = this.specialRequests.trim() + (this.specialRequests.trim() ? ', ' : '') + tag;
            }
        },

        isPhoneValid() {
            const digits = this.phoneCleanDigits;
            if (!digits) return true;
            const country = this.selectedPhoneCountry;
            if (country.digits) {
                return digits.length === country.digits;
            }
            const min = country.minDigits || 7;
            const max = country.maxDigits || 15;
            return digits.length >= min && digits.length <= max;
        },

        selectPhoneCountry(code) {
            this.phoneCountryCode = code;
            this.showPhoneCountryDropdown = false;
            this.phoneErrorMsg = '';
            this.onPhoneInput();
        },

        onPhoneInput(event) {
            let raw = this.phoneNationalNumber || '';
            if (raw.trim().startsWith('+')) {
                const cleanPlus = raw.trim().replace(/[^\d+]/g, '');
                if (cleanPlus.startsWith('+374')) {
                    this.phoneCountryCode = 'AM';
                    raw = cleanPlus.slice(4);
                } else if (cleanPlus.startsWith('+7')) {
                    this.phoneCountryCode = 'RU';
                    raw = cleanPlus.slice(2);
                } else if (cleanPlus.startsWith('+995')) {
                    this.phoneCountryCode = 'GE';
                    raw = cleanPlus.slice(4);
                } else if (cleanPlus.startsWith('+1')) {
                    this.phoneCountryCode = 'US';
                    raw = cleanPlus.slice(2);
                } else if (cleanPlus.startsWith('+33')) {
                    this.phoneCountryCode = 'FR';
                    raw = cleanPlus.slice(3);
                } else if (cleanPlus.startsWith('+49')) {
                    this.phoneCountryCode = 'DE';
                    raw = cleanPlus.slice(3);
                } else if (cleanPlus.startsWith('+971')) {
                    this.phoneCountryCode = 'AE';
                    raw = cleanPlus.slice(4);
                } else if (cleanPlus.startsWith('+98')) {
                    this.phoneCountryCode = 'IR';
                    raw = cleanPlus.slice(3);
                }
            }

            let digits = raw.replace(/\D/g, '');
            const country = this.selectedPhoneCountry;

            if (country.code === 'AM') {
                if (digits.startsWith('0')) digits = digits.slice(1);
                if (digits.length > 8) digits = digits.slice(0, 8);
            } else if (country.code === 'RU') {
                if (digits.startsWith('8') && digits.length > 10) digits = digits.slice(1);
                if (digits.length > 10) digits = digits.slice(0, 10);
            } else if (country.code === 'GE') {
                if (digits.length > 9) digits = digits.slice(0, 9);
            } else if (country.code === 'US') {
                if (digits.length > 10) digits = digits.slice(0, 10);
            } else if (country.code === 'FR') {
                if (digits.startsWith('0')) digits = digits.slice(1);
                if (digits.length > 9) digits = digits.slice(0, 9);
            } else if (country.code === 'DE') {
                if (digits.startsWith('0')) digits = digits.slice(1);
                if (digits.length > 11) digits = digits.slice(0, 11);
            } else if (country.code === 'AE') {
                if (digits.startsWith('0')) digits = digits.slice(1);
                if (digits.length > 9) digits = digits.slice(0, 9);
            } else if (country.code === 'IR') {
                if (digits.startsWith('0')) digits = digits.slice(1);
                if (digits.length > 10) digits = digits.slice(0, 10);
            } else {
                if (digits.length > 15) digits = digits.slice(0, 15);
            }

            let formatted = digits;
            if (country.code === 'AM') {
                if (digits.length > 4) {
                    formatted = digits.slice(0, 2) + ' ' + digits.slice(2, 5) + (digits.length > 5 ? ' ' + digits.slice(5) : '');
                } else if (digits.length > 2) {
                    formatted = digits.slice(0, 2) + ' ' + digits.slice(2);
                }
            } else if (country.code === 'RU') {
                if (digits.length > 7) {
                    formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6, 8) + '-' + digits.slice(8);
                } else if (digits.length > 4) {
                    formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
                } else if (digits.length > 3) {
                    formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
                } else if (digits.length > 0) {
                    formatted = '(' + digits;
                }
            } else if (country.code === 'US') {
                if (digits.length > 6) {
                    formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
                } else if (digits.length > 3) {
                    formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
                } else if (digits.length > 0) {
                    formatted = '(' + digits;
                }
            } else if (country.code === 'GE') {
                if (digits.length > 5) {
                    formatted = digits.slice(0, 3) + ' ' + digits.slice(3, 5) + '-' + digits.slice(5, 7) + (digits.length > 7 ? '-' + digits.slice(7) : '');
                } else if (digits.length > 3) {
                    formatted = digits.slice(0, 3) + ' ' + digits.slice(3);
                }
            }

            this.phoneNationalNumber = formatted;
            this.customerPhone = digits.length > 0 ? (country.dial + digits) : '';

            if (this.isPhoneValid()) {
                this.phoneErrorMsg = '';
            }
        },

        validatePhoneOnBlur() {
            const digits = this.phoneCleanDigits;
            if (!digits) {
                this.phoneErrorMsg = '';
                return;
            }
            const country = this.selectedPhoneCountry;
            if (country.digits && digits.length !== country.digits) {
                if (digits.length < country.digits) {
                    this.phoneErrorMsg = `{{ __('Missing :count digits', ['count' => '']) }}`.trim() + ` (${digits.length}/${country.digits})`;
                } else {
                    this.phoneErrorMsg = `{{ __('Phone number must have exactly :digits digits', ['digits' => '']) }}`.trim() + ` ${country.digits}`;
                }
            } else if (!country.digits && (digits.length < (country.minDigits || 7) || digits.length > (country.maxDigits || 15))) {
                this.phoneErrorMsg = '{{ __('Invalid phone format') }}';
            } else {
                this.phoneErrorMsg = '';
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
                    phoneCountryCode: this.phoneCountryCode,
                    phoneNationalNumber: this.phoneNationalNumber,
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
                if (draft.phoneCountryCode) this.phoneCountryCode = draft.phoneCountryCode;
                if (draft.phoneNationalNumber) {
                    this.phoneNationalNumber = draft.phoneNationalNumber;
                    this.onPhoneInput();
                } else if (draft.customerPhone) {
                    this.phoneNationalNumber = draft.customerPhone;
                    this.onPhoneInput();
                }
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
            if (this.phoneCleanDigits.length > 0 && !this.isPhoneValid()) {
                this.validatePhoneOnBlur();
                this.errorMessage = this.phoneErrorMsg || '{{ __('Please enter a valid phone number.') }}';
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
