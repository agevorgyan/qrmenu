<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ filled($settings['form_title'] ?? '') ? $settings['form_title'] : __('Event Planning Inquiry') }} — {{ $vendor->name }}</title>
    
    <!-- Icons & Fonts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Armenian:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @php
        $primaryColor = $vendor->primary_color ?: '#d97706';
        $accentColor = $vendor->accent_color ?: '#10b981';
        $isDark = ($vendor->theme_mode === 'dark');
        $curLocale = app()->getLocale();
        $isHy = ($curLocale === 'hy');
        $isRu = ($curLocale === 'ru');

        // Calculate contrast
        $hex = ltrim($primaryColor, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2) ?: 'd9');
        $g = hexdec(substr($hex, 2, 2) ?: '77');
        $b = hexdec(substr($hex, 4, 2) ?: '06');
        $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        $primaryContrast = $lum > 0.6 ? '#0f172a' : '#ffffff';

        $opt = $settings['optional_fields'] ?? [];
        $eventTypes = $settings['event_types'] ?? [
            'birthday' => 'Birthday Party',
            'wedding' => 'Wedding & Engagement',
            'corporate' => 'Corporate Event & Business Dinner',
            'family_celebration' => 'Family Celebration',
            'anniversary' => 'Anniversary',
            'kids_party' => "Children's Party",
            'other' => 'Other Custom Event',
        ];

        $minGuests = isset($settings['min_guests']) && $settings['min_guests'] > 0 ? (int)$settings['min_guests'] : 1;
        $maxGuests = !empty($settings['max_guests']) ? (int)$settings['max_guests'] : 500;
        $contactMethods = $settings['contact_methods'] ?? ['phone', 'whatsapp', 'email'];
    @endphp

    <style>
        :root {
            --primary: {{ $primaryColor }};
            --primary-contrast: {{ $primaryContrast }};
            --primary-soft: rgba({{ $r }}, {{ $g }}, {{ $b }}, 0.12);
            --primary-glow: rgba({{ $r }}, {{ $g }}, {{ $b }}, 0.28);
            --accent: {{ $accentColor }};
            --accent-soft: rgba(16, 185, 129, 0.14);

            @if($isDark)
            --bg-body: #0b1120;
            --card-bg: #131c2e;
            --card-subtle: #19243b;
            --card-border: #23334d;
            --card-hover: #2a3d5c;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --text-subtle: #cbd5e1;
            --input-bg: #0e1626;
            --input-border: #263854;
            --chip-bg: #162033;
            --chip-border: #24344d;
            --chip-active-bg: rgba({{ $r }}, {{ $g }}, {{ $b }}, 0.18);
            --shadow-card: 0 10px 30px rgba(0, 0, 0, 0.45);
            --banner-bg: rgba(245, 158, 11, 0.1);
            --banner-border: rgba(245, 158, 11, 0.28);
            --banner-text: #fbbf24;
            --sticky-bar-bg: rgba(19, 28, 46, 0.92);
            --sticky-bar-border: rgba(35, 51, 77, 0.95);
            @else
            --bg-body: #f8fafc;
            --card-bg: #ffffff;
            --card-subtle: #f1f5f9;
            --card-border: #e2e8f0;
            --card-hover: #f8fafc;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-subtle: #475569;
            --input-bg: #ffffff;
            --input-border: #cbd5e1;
            --chip-bg: #ffffff;
            --chip-border: #e2e8f0;
            --chip-active-bg: rgba({{ $r }}, {{ $g }}, {{ $b }}, 0.1);
            --shadow-card: 0 8px 30px rgba(15, 23, 42, 0.06);
            --banner-bg: #fffbeb;
            --banner-border: #fde68a;
            --banner-text: #b45309;
            --sticky-bar-bg: rgba(255, 255, 255, 0.92);
            --sticky-bar-border: rgba(226, 232, 240, 0.95);
            @endif

            --radius-xl: 20px;
            --radius-lg: 16px;
            --radius-md: 12px;
            --radius-sm: 8px;
            --radius-full: 9999px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Plus Jakarta Sans', 'Noto Sans Armenian', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            padding-bottom: 120px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .event-container {
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
            gap: 0.5rem;
            padding: 0.55rem 0.95rem;
            border-radius: var(--radius-md);
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            color: var(--text-main);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 700;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .back-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateX(-2px);
        }

        .lang-switch {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            padding: 0.25rem;
            border-radius: var(--radius-md);
        }
        .lang-link {
            padding: 0.3rem 0.6rem;
            border-radius: var(--radius-sm);
            font-size: 0.78rem;
            font-weight: 800;
            text-decoration: none;
            color: var(--text-muted);
            text-transform: uppercase;
            transition: all 0.15s;
        }
        .lang-link.active {
            background: var(--primary);
            color: var(--primary-contrast);
        }

        /* Page Hero */
        .page-hero {
            text-align: center;
            margin-bottom: 1.5rem;
            padding: 0 0.5rem;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.9rem;
            border-radius: var(--radius-full);
            background: var(--primary-soft);
            color: var(--primary);
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.01em;
            margin-bottom: 0.75rem;
        }
        .hero-title {
            font-family: 'Outfit', 'Noto Sans Armenian', sans-serif;
            font-size: clamp(1.6rem, 4vw, 2.1rem);
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }
        .hero-desc {
            font-size: 0.92rem;
            color: var(--text-muted);
            max-width: 540px;
            margin: 0 auto;
            line-height: 1.55;
        }

        /* Sleek Notice Alert */
        .notice-card {
            background: var(--banner-bg);
            border: 1px solid var(--banner-border);
            color: var(--banner-text);
            border-radius: var(--radius-lg);
            padding: 0.9rem 1.15rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-size: 0.88rem;
            line-height: 1.5;
        }
        .notice-card i {
            font-size: 1.1rem;
            margin-top: 0.15rem;
            flex-shrink: 0;
        }

        /* Section Container */
        .step-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-xl);
            padding: 1.35rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-card);
            transition: border-color 0.2s;
        }
        .step-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.2rem;
        }
        .step-badge {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            background: var(--primary-soft);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.92rem;
            font-weight: 800;
            flex-shrink: 0;
        }
        .step-title-wrap {
            flex: 1;
        }
        .step-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        .step-subtitle {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.15rem;
        }

        /* Event Type Grid */
        .event-types-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }
        @media (min-width: 580px) {
            .event-types-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        .event-type-card {
            background: var(--chip-bg);
            border: 1.5px solid var(--chip-border);
            border-radius: var(--radius-lg);
            padding: 0.9rem 0.75rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
            position: relative;
        }
        .event-type-card:hover {
            border-color: var(--primary);
            background: var(--card-hover);
            transform: translateY(-2px);
        }
        .event-type-card.active {
            border-color: var(--primary);
            background: var(--chip-active-bg);
            box-shadow: 0 0 0 1px var(--primary), 0 4px 16px var(--primary-glow);
        }
        .event-icon-box {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--primary-soft);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            transition: transform 0.2s;
        }
        .event-type-card.active .event-icon-box {
            background: var(--primary);
            color: var(--primary-contrast);
            transform: scale(1.08);
        }
        .event-type-name {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.25;
        }
        .event-type-card.active .event-type-name {
            color: var(--primary);
            font-weight: 800;
        }
        .event-active-check {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--primary);
            color: var(--primary-contrast);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
        }

        /* Form Controls */
        .field-group {
            margin-bottom: 1.15rem;
        }
        .field-group:last-child {
            margin-bottom: 0;
        }
        .field-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.86rem;
            font-weight: 700;
            color: var(--text-subtle);
            margin-bottom: 0.45rem;
        }
        .field-label .required {
            color: #ef4444;
            margin-left: 0.2rem;
        }
        .field-hint {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 0.35rem;
        }
        .field-error {
            font-size: 0.8rem;
            color: #ef4444;
            margin-top: 0.35rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .input-control {
            width: 100%;
            background: var(--input-bg);
            border: 1.5px solid var(--input-border);
            color: var(--text-main);
            padding: 0.75rem 0.95rem;
            border-radius: var(--radius-md);
            font-size: 0.92rem;
            outline: none;
            transition: all 0.2s;
            font-family: inherit;
        }
        .input-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft);
        }

        /* Tactile Stepper & Quick Pills */
        .guest-counter-wrapper {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .stepper-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--input-bg);
            border: 1.5px solid var(--input-border);
            padding: 0.45rem 0.65rem;
            border-radius: var(--radius-md);
        }
        .stepper-btn {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            border: 1px solid var(--card-border);
            background: var(--card-bg);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            cursor: pointer;
            transition: all 0.15s;
        }
        .stepper-btn:disabled {
            opacity: 0.35;
            cursor: not-allowed;
        }
        .stepper-btn:not(:disabled):hover {
            background: var(--primary);
            color: var(--primary-contrast);
            border-color: var(--primary);
        }
        .stepper-val-wrap {
            text-align: center;
        }
        .stepper-val {
            font-family: 'Outfit', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1;
        }
        .stepper-unit {
            font-size: 0.76rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-top: 0.15rem;
        }

        .quick-pills-row {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            overflow-x: auto;
            padding-bottom: 0.25rem;
            scrollbar-width: none;
        }
        .quick-pills-row::-webkit-scrollbar {
            display: none;
        }
        .quick-pill {
            padding: 0.4rem 0.85rem;
            border-radius: var(--radius-full);
            background: var(--chip-bg);
            border: 1px solid var(--chip-border);
            color: var(--text-subtle);
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.15s;
        }
        .quick-pill:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        .quick-pill.active {
            background: var(--primary);
            border-color: var(--primary);
            color: var(--primary-contrast);
        }

        /* Venue Area Cards */
        .venue-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }
        .venue-card {
            background: var(--chip-bg);
            border: 1.5px solid var(--chip-border);
            border-radius: var(--radius-lg);
            padding: 0.95rem 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        .venue-card:hover {
            border-color: var(--primary);
            background: var(--card-hover);
        }
        .venue-card.active {
            border-color: var(--primary);
            background: var(--chip-active-bg);
            box-shadow: 0 0 0 1px var(--primary);
        }
        .venue-card-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .venue-card-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--primary-soft);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            flex-shrink: 0;
        }
        .venue-card.active .venue-card-icon {
            background: var(--primary);
            color: var(--primary-contrast);
        }
        .venue-card-title {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-main);
        }
        .venue-card.active .venue-card-title {
            color: var(--primary);
        }
        .venue-card-desc {
            font-size: 0.76rem;
            color: var(--text-muted);
            line-height: 1.35;
        }

        /* Budget Input with Currency Badge */
        .budget-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .budget-currency-badge {
            position: absolute;
            right: 12px;
            font-family: 'Outfit', sans-serif;
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--primary);
            background: var(--primary-soft);
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            pointer-events: none;
        }

        /* Additional Services */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.65rem;
        }
        @media (min-width: 540px) {
            .services-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        .service-card {
            background: var(--chip-bg);
            border: 1.5px solid var(--chip-border);
            border-radius: var(--radius-md);
            padding: 0.75rem 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
        }
        .service-card:hover {
            border-color: var(--accent);
            background: var(--card-hover);
        }
        .service-card.active {
            background: var(--accent-soft);
            border-color: var(--accent);
            color: var(--accent);
            font-weight: 700;
            box-shadow: 0 0 0 1px var(--accent);
        }
        .service-card i {
            font-size: 1.05rem;
            flex-shrink: 0;
        }
        .service-card span {
            font-size: 0.82rem;
            line-height: 1.25;
            color: var(--text-main);
        }
        .service-card.active span {
            color: var(--accent);
            font-weight: 700;
        }

        /* Quick Suggestion Chips for Food Preferences */
        .food-suggestion-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-top: 0.65rem;
        }
        .food-chip {
            padding: 0.3rem 0.65rem;
            border-radius: var(--radius-full);
            background: var(--card-subtle);
            border: 1px dashed var(--card-border);
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.15s;
        }
        .food-chip:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-soft);
        }
        .food-chip.selected {
            border-style: solid;
            border-color: var(--primary);
            background: var(--primary-soft);
            color: var(--primary);
            font-weight: 700;
        }

        /* Contact Methods Buttons */
        .contact-methods-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.65rem;
        }
        .contact-method-card {
            background: var(--chip-bg);
            border: 1.5px solid var(--chip-border);
            border-radius: var(--radius-md);
            padding: 0.75rem 0.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        .contact-method-card:hover {
            border-color: var(--primary);
        }
        .contact-method-card.active {
            border-color: var(--primary);
            background: var(--primary-soft);
            box-shadow: 0 0 0 1px var(--primary);
        }
        .contact-method-card i {
            font-size: 1.2rem;
            color: var(--primary);
        }
        .contact-method-card span {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text-main);
        }
        .contact-method-card.active span {
            color: var(--primary);
        }

        /* ========================================================
           EXACT ORDER-STYLE PHONE FIELD WITH COUNTRY SELECTOR
           ======================================================== */
        .cart-phone-field-wrapper {
            position: relative;
            margin-bottom: 0;
        }
        .cart-phone-input-box {
            display: flex;
            align-items: center;
            background: var(--input-bg);
            border: 1.5px solid var(--input-border);
            border-radius: var(--radius-md);
            transition: all 0.2s;
            position: relative;
        }
        .cart-phone-input-box:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft);
        }
        .cart-phone-input-box.has-error {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
        }
        .cart-phone-input-box.is-valid {
            border-color: #10b981;
        }
        .cart-phone-country-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.7rem 0.85rem;
            background: var(--card-subtle);
            border: none;
            border-right: 1px solid var(--card-border);
            border-radius: var(--radius-md) 0 0 var(--radius-md);
            cursor: pointer;
            color: var(--text-main);
            font-size: 0.92rem;
            font-weight: 700;
            flex-shrink: 0;
            transition: background 0.15s;
        }
        .cart-phone-country-btn:hover {
            background: var(--card-hover);
        }
        .cart-flag-icon {
            font-size: 1.15rem;
            line-height: 1;
        }
        .cart-dial-code {
            font-family: 'Outfit', sans-serif;
            font-size: 0.88rem;
            font-weight: 700;
        }
        .cart-country-caret {
            font-size: 0.72rem;
            color: var(--text-muted);
            transition: transform 0.2s;
        }
        .cart-country-caret.is-open {
            transform: rotate(180deg);
        }
        .cart-phone-native-input {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: var(--text-main);
            padding: 0.75rem 0.85rem;
            font-size: 0.95rem;
            font-family: inherit;
            letter-spacing: 0.02em;
        }
        .cart-phone-native-input::placeholder {
            color: var(--text-muted);
            opacity: 0.7;
        }
        .cart-phone-status-indicator {
            padding-right: 0.85rem;
            display: flex;
            align-items: center;
        }
        .cart-status-badge {
            font-size: 0.78rem;
            font-weight: 800;
            padding: 0.2rem 0.45rem;
            border-radius: 6px;
        }
        .cart-status-badge.is-valid {
            color: #10b981;
            font-size: 1rem;
        }
        .cart-status-badge.is-incomplete {
            background: var(--card-subtle);
            color: var(--text-muted);
            font-size: 0.72rem;
        }

        .cart-country-dropdown {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            width: 100%;
            max-width: 320px;
            max-height: 260px;
            overflow-y: auto;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-md);
            box-shadow: 0 12px 30px rgba(0,0,0,0.25);
            z-index: 999;
            padding: 0.35rem;
        }
        .cart-country-item {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.6rem 0.75rem;
            border: none;
            background: transparent;
            border-radius: var(--radius-sm);
            cursor: pointer;
            color: var(--text-main);
            text-align: left;
            font-size: 0.88rem;
            transition: background 0.15s;
        }
        .cart-country-item:hover {
            background: var(--primary-soft);
            color: var(--primary);
        }
        .cart-country-item.is-selected {
            background: var(--primary-soft);
            color: var(--primary);
            font-weight: 700;
        }
        .cart-item-flag {
            font-size: 1.15rem;
        }
        .cart-item-name {
            flex: 1;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .cart-item-dial {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            color: var(--text-muted);
            font-size: 0.82rem;
        }
        .cart-item-digits {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* Error Banner */
        .form-error-banner {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #ef4444;
            padding: 0.85rem 1.15rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.25rem;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        /* Sticky Bottom Summary Bar */
        .sticky-summary-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--sticky-bar-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-top: 1px solid var(--sticky-bar-border);
            padding: 0.85rem 1rem;
            z-index: 100;
            box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.08);
        }
        .sticky-summary-inner {
            max-width: 660px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .sticky-summary-info {
            min-width: 0;
            flex: 1;
        }
        .sticky-summary-pills {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            flex-wrap: wrap;
            font-size: 0.84rem;
            font-weight: 700;
            color: var(--text-main);
        }
        .sticky-summary-sub {
            font-size: 0.76rem;
            color: var(--text-muted);
            margin-top: 0.15rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cta-submit-btn {
            background: var(--primary);
            color: var(--primary-contrast);
            border: none;
            padding: 0.85rem 1.45rem;
            border-radius: var(--radius-md);
            font-size: 0.98rem;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 4px 16px var(--primary-glow);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .cta-submit-btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none !important;
        }
        .cta-submit-btn:not(:disabled):hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px var(--primary-glow);
        }
        .cta-submit-btn:not(:disabled):active {
            transform: translateY(0);
        }

        /* Success Celebratory Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.72);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            z-index: 9999;
        }
        .modal-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-xl);
            max-width: 480px;
            width: 100%;
            padding: 2.25rem 1.5rem;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: modalPopIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes modalPopIn {
            from { opacity: 0; transform: scale(0.92) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal-celebrate-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.25rem;
            box-shadow: 0 0 0 8px rgba(16, 185, 129, 0.08);
        }
        .ref-box-container {
            background: var(--card-subtle);
            border: 1.5px dashed var(--card-border);
            border-radius: var(--radius-lg);
            padding: 1rem 1.25rem;
            margin: 1.25rem 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .ref-number {
            font-family: 'Outfit', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            color: var(--primary);
        }
        .copy-btn {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            padding: 0.4rem 0.75rem;
            border-radius: 8px;
            color: var(--text-subtle);
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
        }
        .copy-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
    </style>
</head>
<body>

<div class="event-container" x-data="eventInquiryApp()">

    <!-- Header Navigation -->
    <div class="header-bar">
        <a href="{{ route('client.menu', ['vendor_slug' => $vendor->slug, 'location_slug' => $location?->slug]) }}" class="back-btn">
            <i class="fa-solid fa-arrow-left"></i>
            <span>{{ __('Back to Menu') }}</span>
        </a>

        <div class="lang-switch">
            <a href="?lang=hy" class="lang-link {{ $curLocale === 'hy' ? 'active' : '' }}">HY</a>
            <a href="?lang=en" class="lang-link {{ $curLocale === 'en' ? 'active' : '' }}">EN</a>
            <a href="?lang=ru" class="lang-link {{ $curLocale === 'ru' ? 'active' : '' }}">RU</a>
        </div>
    </div>

    <!-- Page Hero Header -->
    <div class="page-hero">
        <div class="hero-badge">
            <i class="fa-solid fa-champagne-glasses"></i>
            <span>{{ $vendor->name }}</span>
            @if($location)
                <span>• {{ $location->name }}</span>
            @endif
        </div>
        <h1 class="hero-title">
            {{ filled($settings['form_title'] ?? '') ? $settings['form_title'] : ($isHy ? 'Միջոցառման կազմակերպման հարցում' : ($isRu ? 'Запрос на организацию мероприятия' : 'Event Planning Inquiry')) }}
        </h1>
        <p class="hero-desc">
            {{ filled($settings['form_description'] ?? '') ? $settings['form_description'] : ($isHy ? 'Պլանավորեք Ձեր ծննդյան տարեդարձը, հարսանիքը, կորպորատիվ երեկոն կամ ընտանեկան հանդիսությունը մեզ հետ։' : ($isRu ? 'Организуйте день рождения, свадьбу, корпоратив или семейное торжество вместе с нами.' : 'Plan your private celebration, corporate dinner, birthday party or wedding with us.')) }}
        </p>
    </div>

    <!-- Notice Card -->
    <div class="notice-card">
        <i class="fa-solid fa-circle-info"></i>
        <div>
            <strong>{{ $isHy ? 'Հարցման ծանուցում' : ($isRu ? 'Примечание к запросу' : __('Inquiry Notice')) }}:</strong>
            <span>{{ $isHy ? 'Հարցումն ուղարկելը չի հանդիսանում վերջնական ամրագրում։ Մեր մենեջերը կստուգի մանրամասները և կկապվի Ձեզ հետ անհատական առաջարկով։' : ($isRu ? 'Отправка запроса не является гарантированной бронью. Наш менеджер свяжется с вами для согласования индивидуального предложения.' : __('Submitting an inquiry does not confirm a reservation or guarantee availability. Our team will review your requirements and contact you with a customized offer.')) }}</span>
        </div>
    </div>

    <!-- Error Banner -->
    <template x-if="errorMessage">
        <div class="form-error-banner">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span x-text="errorMessage"></span>
        </div>
    </template>

    <form @submit.prevent="submitForm">

        <!-- ==============================================
             STEP 1: EVENT TYPE
             ============================================== -->
        <div class="step-card">
            <div class="step-header">
                <div class="step-badge">1</div>
                <div class="step-title-wrap">
                    <div class="step-title">
                        <span>{{ $isHy ? 'Միջոցառման տեսակը' : ($isRu ? 'Тип мероприятия' : __('1. Event Type')) }}</span>
                        <span style="color:#ef4444;">*</span>
                    </div>
                    <div class="step-subtitle">{{ $isHy ? 'Ընտրեք Ձեր տոնակատարության կամ հանդիպման բնույթը' : ($isRu ? 'Выберите формат вашего праздника' : 'Choose the occasion that fits your gathering') }}</div>
                </div>
            </div>

            <div class="event-types-grid">
                @foreach($eventTypes as $key => $title)
                    @php
                        $icon = match($key) {
                            'birthday' => 'fa-solid fa-cake-candles',
                            'wedding' => 'fa-solid fa-rings-wedding fa-heart',
                            'corporate' => 'fa-solid fa-briefcase',
                            'family_celebration' => 'fa-solid fa-people-roof',
                            'anniversary' => 'fa-solid fa-champagne-glasses',
                            'kids_party' => 'fa-solid fa-child-reaching',
                            default => 'fa-solid fa-sparkles',
                        };
                        $displayTitle = match($key) {
                            'birthday' => ($isHy ? 'Ծննդյան օր' : ($isRu ? 'День рождения' : 'Birthday Party')),
                            'wedding' => ($isHy ? 'Հարսանիք / Նշանադրություն' : ($isRu ? 'Свадьба / Помолвка' : 'Wedding & Engagement')),
                            'corporate' => ($isHy ? 'Կորպորատիվ / Գործնական' : ($isRu ? 'Корпоратив / Бизнес' : 'Corporate Dinner')),
                            'family_celebration' => ($isHy ? 'Ընտանեկան հավաքույթ' : ($isRu ? 'Семейное торжество' : 'Family Celebration')),
                            'anniversary' => ($isHy ? 'Հոբելյան / Տարեդարձ' : ($isRu ? 'Юбилей / Годовщина' : 'Anniversary')),
                            'kids_party' => ($isHy ? 'Մանկական տոն' : ($isRu ? 'Детский праздник' : "Children's Party")),
                            default => ($isHy ? 'Այլ միջոցառում' : ($isRu ? 'Другое событие' : 'Other Event')),
                        };
                    @endphp
                    <div class="event-type-card" 
                         :class="{ 'active': form.event_type === '{{ $key }}' }"
                         @click="form.event_type = '{{ $key }}'">
                        <div class="event-icon-box">
                            <i class="{{ $icon }}"></i>
                        </div>
                        <span class="event-type-name">{{ $displayTitle }}</span>
                        <div class="event-active-check" x-show="form.event_type === '{{ $key }}'">
                            <i class="fa-solid fa-check"></i>
                        </div>
                    </div>
                @endforeach
            </div>
            <template x-if="errors.event_type">
                <div class="field-error" x-text="errors.event_type[0]"></div>
            </template>
        </div>

        <!-- ==============================================
             STEP 2: DATE, GUESTS & TIMING
             ============================================== -->
        <div class="step-card">
            <div class="step-header">
                <div class="step-badge">2</div>
                <div class="step-title-wrap">
                    <div class="step-title">
                        <span>{{ $isHy ? 'Ամսաթիվ, հյուրեր և ժամ' : ($isRu ? 'Дата, гости и время' : __('2. Date & Estimated Guests')) }}</span>
                    </div>
                    <div class="step-subtitle">{{ $isHy ? 'Նշեք օրը, նախատեսվող հյուրերի քանակը և ժամը' : ($isRu ? 'Укажите дату и примерное число гостей' : 'Specify when and for how many people') }}</div>
                </div>
            </div>

            <!-- Date & Guest Count Split -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.15rem;">
                
                <!-- Date Input -->
                <div class="field-group" style="margin-bottom: 0;">
                    <label class="field-label">
                        <span>{{ $isHy ? 'Միջոցառման օր' : ($isRu ? 'Дата события' : __('Preferred Date')) }} <span class="required">*</span></span>
                    </label>
                    <input type="date" 
                           class="input-control" 
                           x-model="form.event_date" 
                           min="{{ date('Y-m-d') }}" 
                           required>
                    <div class="field-hint" x-text="getFormattedDatePreview(form.event_date)"></div>
                    <template x-if="errors.event_date">
                        <div class="field-error" x-text="errors.event_date[0]"></div>
                    </template>
                </div>

                <!-- Guest Count Stepper -->
                <div class="field-group" style="margin-bottom: 0;">
                    <label class="field-label">
                        <span>{{ $isHy ? 'Հյուրերի քանակ' : ($isRu ? 'Число гостей' : __('Number of Guests')) }} <span class="required">*</span></span>
                        <span style="font-size: 0.75rem; color: var(--text-muted);">
                            {{ $minGuests }}-{{ $maxGuests }}
                        </span>
                    </label>

                    <div class="stepper-box">
                        <button type="button" 
                                class="stepper-btn" 
                                @click="decrementGuests" 
                                :disabled="form.guest_count <= {{ $minGuests }}"
                                title="Նվազեցնել">
                            <i class="fa-solid fa-minus"></i>
                        </button>
                        <div class="stepper-val-wrap">
                            <div class="stepper-val" x-text="form.guest_count"></div>
                            <div class="stepper-unit">{{ $isHy ? 'հյուր' : ($isRu ? 'гостей' : 'guests') }}</div>
                        </div>
                        <button type="button" 
                                class="stepper-btn" 
                                @click="incrementGuests" 
                                :disabled="form.guest_count >= {{ $maxGuests }}"
                                title="Ավելացնել">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quick Jump Guest Pills -->
            <div class="field-group">
                <div class="field-label" style="font-size: 0.78rem; margin-bottom: 0.35rem;">
                    <span>{{ $isHy ? 'Արագ ընտրություն' : ($isRu ? 'Быстрый выбор' : 'Quick Presets') }}:</span>
                </div>
                <div class="quick-pills-row">
                    @foreach([10, 20, 30, 50, 75, 100, 150] as $gPill)
                        @if($gPill >= $minGuests && $gPill <= $maxGuests)
                            <button type="button" 
                                    class="quick-pill" 
                                    :class="{ 'active': form.guest_count === {{ $gPill }} }"
                                    @click="form.guest_count = {{ $gPill }}">
                                {{ $gPill }} {{ $isHy ? 'հյուր' : ($isRu ? 'гостей' : 'guests') }}
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>

            <!-- Time Options -->
            @if(!empty($opt['start_time']) || !empty($opt['end_time']))
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--card-border);">
                    @if(!empty($opt['start_time']))
                        <div class="field-group" style="margin-bottom: 0;">
                            <label class="field-label">{{ $isHy ? 'Սկիզբ (ժամ)' : ($isRu ? 'Время начала' : __('Preferred Start Time')) }}</label>
                            <input type="time" class="input-control" x-model="form.start_time">
                        </div>
                    @endif

                    @if(!empty($opt['end_time']))
                        <div class="field-group" style="margin-bottom: 0;">
                            <label class="field-label">{{ $isHy ? 'Ավարտ / Տևողություն' : ($isRu ? 'Окончание' : __('End Time / Estimated Duration')) }}</label>
                            <input type="time" class="input-control" x-model="form.end_time">
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <!-- ==============================================
             STEP 3: VENUE & ESTIMATED BUDGET
             ============================================== -->
        @if(!empty($opt['venue_arrangement']) || !empty($opt['budget']))
            <div class="step-card">
                <div class="step-header">
                    <div class="step-badge">3</div>
                    <div class="step-title-wrap">
                        <div class="step-title">
                            <span>{{ $isHy ? 'Սրահի նախասիրություն և բյուջե' : ($isRu ? 'Зона зала и бюджет' : __('3. Venue & Budget')) }}</span>
                        </div>
                        <div class="step-subtitle">{{ $isHy ? 'Ընտրեք Ձեզ հարմար սրահը և ակնկալվող բյուջեն' : ($isRu ? 'Предпочитаемая зона и примерный бюджет' : 'Seating zone preference and target budget') }}</div>
                    </div>
                </div>

                @if(!empty($opt['venue_arrangement']))
                    <div class="field-group">
                        <label class="field-label">{{ $isHy ? 'Նախընտրելի սրահ' : ($isRu ? 'Желаемая зона' : __('Preferred Venue Arrangement')) }}</label>
                        <div class="venue-grid">
                            <div class="venue-card" :class="{ 'active': form.venue_arrangement === 'no_preference' }" @click="form.venue_arrangement = 'no_preference'">
                                <div class="venue-card-header">
                                    <div class="venue-card-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                                    <div class="venue-card-title">{{ $isHy ? 'Ավտոմատ / Լավագույնը' : ($isRu ? 'На усмотрение' : __('No Preference')) }}</div>
                                </div>
                                <div class="venue-card-desc">{{ $isHy ? 'Մենք կընտրենք առավել հարմարավետ գոտին' : ($isRu ? 'Подберем лучший вариант' : 'We will suggest the best matching area') }}</div>
                            </div>

                            <div class="venue-card" :class="{ 'active': form.venue_arrangement === 'indoor' }" @click="form.venue_arrangement = 'indoor'">
                                <div class="venue-card-header">
                                    <div class="venue-card-icon"><i class="fa-solid fa-house"></i></div>
                                    <div class="venue-card-title">{{ $isHy ? 'Ներքին սրահ' : ($isRu ? 'Основной зал' : __('Indoor Hall')) }}</div>
                                </div>
                                <div class="venue-card-desc">{{ $isHy ? 'Հարմարավետ կլիմայով հիմնական դահլիճ' : ($isRu ? 'Уютный закрытый зал' : 'Main indoor restaurant hall') }}</div>
                            </div>

                            <div class="venue-card" :class="{ 'active': form.venue_arrangement === 'outdoor' }" @click="form.venue_arrangement = 'outdoor'">
                                <div class="venue-card-header">
                                    <div class="venue-card-icon"><i class="fa-solid fa-tree"></i></div>
                                    <div class="venue-card-title">{{ $isHy ? 'Բացօթյա / Տերասա' : ($isRu ? 'Летняя терраса' : __('Outdoor / Terrace')) }}</div>
                                </div>
                                <div class="venue-card-desc">{{ $isHy ? 'Թարմ օդ և բացօթյա մթնոլորտ' : ($isRu ? 'Открытая веранда на воздухе' : 'Open-air terrace and fresh atmosphere') }}</div>
                            </div>

                            <div class="venue-card" :class="{ 'active': form.venue_arrangement === 'private_room' }" @click="form.venue_arrangement = 'private_room'">
                                <div class="venue-card-header">
                                    <div class="venue-card-icon"><i class="fa-solid fa-crown"></i></div>
                                    <div class="venue-card-title">{{ $isHy ? 'VIP Առանձնասենյակ' : ($isRu ? 'VIP комната' : __('Private VIP Room')) }}</div>
                                </div>
                                <div class="venue-card-desc">{{ $isHy ? 'Մեկուսի և առանձնացված միջավայր' : ($isRu ? 'Изолированное пространство' : 'Quiet, isolated VIP space') }}</div>
                            </div>
                        </div>
                    </div>
                @endif

                @if(!empty($opt['budget']))
                    <div class="field-group" style="margin-bottom: 0;">
                        <label class="field-label">
                            <span>{{ $isHy ? 'Մոտավոր բյուջե' : ($isRu ? 'Примерный бюджет' : __('Estimated Budget')) }}</span>
                            <span style="font-size: 0.75rem; color: var(--text-muted);">{{ $isHy ? 'Ըստ ցանկության' : ($isRu ? 'Необязательно' : 'Optional') }}</span>
                        </label>
                        <div class="budget-input-wrap">
                            <input type="number" 
                                   step="10000" 
                                   min="0" 
                                   class="input-control" 
                                   x-model="form.budget" 
                                   placeholder="{{ $isHy ? 'օր.՝ 250,000' : 'e.g. 250000' }}">
                            <span class="budget-currency-badge">{{ $vendor->currency ?: '֏' }}</span>
                        </div>
                        <div class="field-hint">{{ $isHy ? 'Օգնում է մեր թիմին կազմել համահունչ ճաշացանկ Ձեր ակնկալիքների սահմաններում։' : ($isRu ? 'Поможет составить оптимальное меню под ваш бюджет.' : __('Optional: helps our team customize the menu within your expectations.')) }}</div>
                    </div>
                @endif
            </div>
        @endif

        <!-- ==============================================
             STEP 4: SERVICES & PREFERENCES
             ============================================== -->
        @if(!empty($opt['additional_services']) || !empty($opt['food_beverage_preferences']))
            <div class="step-card">
                <div class="step-header">
                    <div class="step-badge">4</div>
                    <div class="step-title-wrap">
                        <div class="step-title">
                            <span>{{ $isHy ? 'Ծառայություններ և նախասիրություններ' : ($isRu ? 'Услуги и пожелания' : __('4. Services & Preferences')) }}</span>
                        </div>
                        <div class="step-subtitle">{{ $isHy ? 'Նշեք լրացուցիչ ծառայությունները և ուտեստների նախասիրությունները' : ($isRu ? 'Дополнительные опции и предпочтения по меню' : 'Custom touches to elevate your event') }}</div>
                    </div>
                </div>

                @if(!empty($opt['additional_services']))
                    <div class="field-group">
                        <label class="field-label">{{ $isHy ? 'Լրացուցիչ ծառայություններ' : ($isRu ? 'Дополнительные услуги' : __('Additional Services Needed')) }}</label>
                        <div class="services-grid">
                            @php
                                $servicesList = [
                                    'decoration' => ['icon' => 'fa-solid fa-ribbon', 'hy' => 'Ծաղիկներ / Դեկոր', 'ru' => 'Декор / Цветы', 'en' => 'Decoration & Flowers'],
                                    'music' => ['icon' => 'fa-solid fa-music', 'hy' => 'Երաժշտություն / DJ', 'ru' => 'Музыка / DJ', 'en' => 'Music / DJ'],
                                    'entertainment' => ['icon' => 'fa-solid fa-microphone', 'hy' => 'Հանդիսավար / Շոու', 'ru' => 'Ведущий / Шоу', 'en' => 'Host / Entertainment'],
                                    'equipment' => ['icon' => 'fa-solid fa-tv', 'hy' => 'Պրոյեկտոր / Ձայն', 'ru' => 'Проектор / Звук', 'en' => 'Projector & Sound'],
                                    'cake' => ['icon' => 'fa-solid fa-cake-candles', 'hy' => 'Տոնական տորթ', 'ru' => 'Праздничный торт', 'en' => 'Celebration Cake'],
                                    'photography' => ['icon' => 'fa-solid fa-camera', 'hy' => 'Ֆոտո / Վիդեո', 'ru' => 'Фото / Видео', 'en' => 'Photo / Video'],
                                ];
                            @endphp
                            @foreach($servicesList as $sKey => $sVal)
                                <div class="service-card" 
                                     :class="{ 'active': form.additional_services.includes('{{ $sKey }}') }"
                                     @click="toggleService('{{ $sKey }}')">
                                    <i class="{{ $sVal['icon'] }}"></i>
                                    <span>{{ $isHy ? $sVal['hy'] : ($isRu ? $sVal['ru'] : $sVal['en']) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($opt['food_beverage_preferences']))
                    <div class="field-group" style="margin-bottom: 0;">
                        <label class="field-label">{{ $isHy ? 'Խոհանոցի և ըմպելիքների նախասիրություններ' : ($isRu ? 'Предпочтения по блюдам и напиткам' : __('Food & Beverage Preferences')) }}</label>
                        <textarea class="input-control" 
                                  rows="2" 
                                  x-model="form.food_beverage_preferences" 
                                  placeholder="{{ $isHy ? 'օր.՝ Հայկական ավանդական ուտեստներ, խորովածի տեսականի, գինու ընտրանի...' : 'e.g. Armenian traditional, barbecue, vegetarian options, specific wines...' }}"></textarea>
                        
                        <!-- Quick Click Chips -->
                        <div class="food-suggestion-chips">
                            <span class="food-chip" @click="appendFoodPref('{{ $isHy ? 'Հայկական խոհանոց' : 'Armenian Cuisine' }}')">+ 🇦🇲 {{ $isHy ? 'Հայկական խոհանոց' : 'Armenian' }}</span>
                            <span class="food-chip" @click="appendFoodPref('{{ $isHy ? 'Խորովածի տեսականի' : 'Barbecue assortment' }}')">+ 🥩 {{ $isHy ? 'Խորովածի տեսականի' : 'Barbecue' }}</span>
                            <span class="food-chip" @click="appendFoodPref('{{ $isHy ? 'Բուսակերական ընտրանի' : 'Vegetarian options' }}')">+ 🥗 {{ $isHy ? 'Բուսակերական' : 'Vegetarian' }}</span>
                            <span class="food-chip" @click="appendFoodPref('{{ $isHy ? 'Պրեմիում գինիներ' : 'Wine selection' }}')">+ 🍷 {{ $isHy ? 'Գինիների ընտրանի' : 'Wines' }}</span>
                            <span class="food-chip" @click="appendFoodPref('{{ $isHy ? 'Հատուկ դեսերտներ' : 'Dessert table' }}')">+ 🍰 {{ $isHy ? 'Դեսերտներ' : 'Desserts' }}</span>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- ==============================================
             STEP 5: CUSTOMER CONTACT DETAILS
             (EXACT ORDER-STYLE VALIDATED PHONE FIELD)
             ============================================== -->
        <div class="step-card">
            <div class="step-header">
                <div class="step-badge">5</div>
                <div class="step-title-wrap">
                    <div class="step-title">
                        <span>{{ $isHy ? 'Կոնտակտային տվյալներ' : ($isRu ? 'Контактные данные' : __('5. Contact Information')) }}</span>
                        <span style="color:#ef4444;">*</span>
                    </div>
                    <div class="step-subtitle">{{ $isHy ? 'Ինչպե՞ս կապվել Ձեզ հետ մանրամասները քննարկելու համար' : ($isRu ? 'Как с вами связаться для уточнения деталей' : 'How should our events manager reach you') }}</div>
                </div>
            </div>

            <!-- Full Name -->
            <div class="field-group">
                <label class="field-label">
                    <span>{{ $isHy ? 'Ձեր անունը / Ազգանունը' : ($isRu ? 'Ваше имя' : __('Full Name')) }} <span class="required">*</span></span>
                </label>
                <input type="text" 
                       class="input-control" 
                       x-model="form.customer_name" 
                       placeholder="{{ $isHy ? 'օր.՝ Արամ Գրիգորյան' : ($isRu ? 'напр. Арам Григорян' : 'e.g. Aram Grigoryan') }}" 
                       required>
                <template x-if="errors.customer_name">
                    <div class="field-error" x-text="errors.customer_name[0]"></div>
                </template>
            </div>

            <!-- Phone & Email Grid -->
            <div style="display: grid; grid-template-columns: 1fr; gap: 1rem; margin-bottom: 1.15rem;">
                
                <!-- VALIDATED PHONE FIELD (Exact Order / Checkout Component) -->
                <div class="field-group" style="margin-bottom: 0;">
                    <label class="field-label">
                        <span>{{ $isHy ? 'Հեռախոսահամար' : ($isRu ? 'Номер телефона' : __('Phone Number')) }} <span class="required">*</span></span>
                        <span style="font-size: 0.75rem; color: var(--text-muted);" x-text="selectedPhoneCountry.name"></span>
                    </label>

                    <div class="cart-phone-field-wrapper">
                        <div class="cart-phone-input-box" 
                             :class="{ 'has-error': phoneErrorMsg || errors.customer_phone, 'is-valid': isPhoneValid() && phoneCleanDigits.length > 0 }">
                            
                            <!-- Country Pill Button -->
                            <button type="button" 
                                    @click="showPhoneCountryDropdown = !showPhoneCountryDropdown" 
                                    class="cart-phone-country-btn"
                                    title="Երկրի կոդ">
                                <span class="cart-flag-icon" x-text="selectedPhoneCountry.flag"></span>
                                <span class="cart-dial-code" x-text="selectedPhoneCountry.dial"></span>
                                <i class="fa-solid fa-chevron-down cart-country-caret" :class="{ 'is-open': showPhoneCountryDropdown }"></i>
                            </button>

                            <!-- Native Phone Input -->
                            <input type="tel" 
                                   id="eventCustomerPhoneInput"
                                   x-model="phoneNationalNumber" 
                                   @input="onPhoneInput($event)"
                                   @blur="validatePhoneOnBlur()"
                                   class="cart-phone-native-input" 
                                   :placeholder="selectedPhoneCountry.placeholder"
                                   required>

                            <!-- Live Digit Status Counter -->
                            <div class="cart-phone-status-indicator">
                                <template x-if="isPhoneValid() && phoneCleanDigits.length > 0">
                                    <span class="cart-status-badge is-valid" title="Համարը վավեր է">
                                        <i class="fa-solid fa-circle-check"></i>
                                    </span>
                                </template>
                                <template x-if="phoneCleanDigits.length > 0 && !isPhoneValid()">
                                    <span class="cart-status-badge is-incomplete" :title="'Պահանջվում է ' + (selectedPhoneCountry.digits || '7-15') + ' նիշ'">
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
                                    <span class="cart-item-digits" x-show="item.digits" x-text="'(' + item.digits + ' նիշ)'"></span>
                                </button>
                            </template>
                        </div>

                        <!-- Phone Error Message -->
                        <template x-if="phoneErrorMsg">
                            <div class="field-error" style="margin-top: 0.35rem;">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                <span x-text="phoneErrorMsg"></span>
                            </div>
                        </template>
                        <template x-if="errors.customer_phone">
                            <div class="field-error" x-text="errors.customer_phone[0]"></div>
                        </template>
                    </div>
                </div>

                <!-- Email Input -->
                <div class="field-group" style="margin-bottom: 0;">
                    <label class="field-label">
                        <span>{{ $isHy ? 'Էլ. փոստի հասցե' : ($isRu ? 'Эл. почта' : __('Email Address')) }}</span>
                        <span x-show="form.preferred_contact_method === 'email'" class="required">*</span>
                    </label>
                    <input type="email" 
                           class="input-control" 
                           x-model="form.customer_email" 
                           placeholder="{{ $isHy ? 'name@example.com' : 'example@gmail.com' }}"
                           :required="form.preferred_contact_method === 'email'">
                    <template x-if="errors.customer_email">
                        <div class="field-error" x-text="errors.customer_email[0]"></div>
                    </template>
                </div>
            </div>

            <!-- Preferred Contact Method -->
            <div class="field-group">
                <label class="field-label">
                    <span>{{ $isHy ? 'Կապի նախընտրելի եղանակ' : ($isRu ? 'Предпочтительный способ связи' : __('Preferred Contact Method')) }} <span class="required">*</span></span>
                </label>
                <div class="contact-methods-row">
                    @foreach($contactMethods as $method)
                        @php
                            $mIcon = match($method) {
                                'whatsapp' => 'fa-brands fa-whatsapp',
                                'email' => 'fa-solid fa-envelope',
                                default => 'fa-solid fa-phone',
                            };
                            $mLabel = match($method) {
                                'whatsapp' => 'WhatsApp',
                                'email' => ($isHy ? 'Էլ. փոստ' : ($isRu ? 'Эл. почта' : 'Email')),
                                default => ($isHy ? 'Հեռախոս' : ($isRu ? 'Звонок' : 'Phone Call')),
                            };
                        @endphp
                        <div class="contact-method-card" 
                             :class="{ 'active': form.preferred_contact_method === '{{ $method }}' }"
                             @click="form.preferred_contact_method = '{{ $method }}'">
                            <i class="{{ $mIcon }}"></i>
                            <span>{{ $mLabel }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Comments / Notes -->
            @if(!empty($opt['comments']))
                <div class="field-group" style="margin-bottom: 0;">
                    <label class="field-label">{{ $isHy ? 'Հատուկ նշումներ կամ հարցեր' : ($isRu ? 'Дополнительные пожелания' : __('Additional Requirements & Comments')) }}</label>
                    <textarea class="input-control" 
                              rows="3" 
                              x-model="form.comments" 
                              placeholder="{{ $isHy ? 'Նշեք անակնկալի մանրամասները, հատուկ հյուրերին կամ ցանկացած այլ հարց...' : 'Any specific requests, surprise elements, timing preferences or questions...' }}"></textarea>
                </div>
            @endif
        </div>

        <!-- ==============================================
             STICKY BOTTOM SUMMARY BAR & CTA
             ============================================== -->
        <div class="sticky-summary-bar">
            <div class="sticky-summary-inner">
                <div class="sticky-summary-info">
                    <div class="sticky-summary-pills">
                        <span x-text="getEventTypePreview()"></span>
                        <span>•</span>
                        <span x-text="'👥 ' + form.guest_count + ' {{ $isHy ? 'հյուր' : ($isRu ? 'гостей' : 'guests') }}'"></span>
                    </div>
                    <div class="sticky-summary-sub">
                        <span x-text="getFormattedDatePreview(form.event_date)"></span>
                        <template x-if="form.start_time">
                            <span x-text="' • ' + form.start_time"></span>
                        </template>
                    </div>
                </div>

                <button type="submit" id="submitInquiryBtn" class="cta-submit-btn" :disabled="submitting">
                    <template x-if="!submitting">
                        <span>
                            <i class="fa-solid fa-paper-plane"></i>
                            {{ $isHy ? 'Ուղարկել հարցումը' : ($isRu ? 'Отправить запрос' : __('Submit Event Inquiry')) }}
                        </span>
                    </template>
                    <template x-if="submitting">
                        <span>
                            <i class="fa-solid fa-circle-notch fa-spin"></i>
                            {{ $isHy ? 'Ուղարկվում է...' : ($isRu ? 'Отправка...' : __('Submitting Inquiry...')) }}
                        </span>
                    </template>
                </button>
            </div>
        </div>

    </form>

    <!-- ==============================================
         SUCCESS CELEBRATION MODAL
         ============================================== -->
    <template x-if="successModal">
        <div class="modal-overlay" @click.self="redirectToMenu">
            <div class="modal-card">
                <div class="modal-celebrate-icon">
                    <i class="fa-solid fa-check"></i>
                </div>
                <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem; color: var(--text-main);">
                    {{ $isHy ? 'Հարցումն ընդունվա՛ծ է' : ($isRu ? 'Запрос принят!' : __('Inquiry Received!')) }}
                </h2>
                <p style="font-size: 0.92rem; color: var(--text-muted); line-height: 1.5;" x-text="successMessage"></p>

                <div class="ref-box-container">
                    <div style="text-align: left;">
                        <div style="font-size: 0.76rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                            {{ $isHy ? 'Հարցման կոդ' : ($isRu ? 'Код запроса' : __('Reference Code')) }}
                        </div>
                        <div class="ref-number" x-text="submittedReference"></div>
                    </div>
                    <button type="button" class="copy-btn" @click="copyReference">
                        <i class="fa-regular fa-copy"></i>
                        <span x-text="copied ? '{{ $isHy ? 'Պատճենվեց' : 'Copied' }}' : '{{ $isHy ? 'Պատճենել' : 'Copy' }}'"></span>
                    </button>
                </div>

                <p style="font-size: 0.82rem; color: var(--text-subtle); margin-bottom: 1.5rem; line-height: 1.4;">
                    {{ $isHy ? 'Մեր մենեջերը շուտով կկապվի Ձեզ հետ՝ հաստատելու մանրամասները և ներկայացնելու անհատական առաջարկը։' : ($isRu ? 'Наш менеджер свяжется с вами в ближайшее время для подтверждения всех деталей.' : __('Please keep this reference code for your communication with the restaurant.')) }}
                </p>

                <button type="button" class="cta-submit-btn" style="width: 100%; justify-content: center;" @click="redirectToMenu">
                    <i class="fa-solid fa-utensils"></i>
                    <span>{{ $isHy ? 'Վերադառնալ ճաշացանկ' : ($isRu ? 'Вернуться в меню' : __('Back to Restaurant Menu')) }}</span>
                </button>
            </div>
        </div>
    </template>

</div>

<script>
function eventInquiryApp() {
    return {
        form: {
            event_type: '{{ array_key_first($eventTypes) ?: "birthday" }}',
            event_date: '{{ date("Y-m-d", strtotime("+3 days")) }}',
            guest_count: {{ max(15, $minGuests) }},
            start_time: '18:30',
            end_time: '',
            venue_arrangement: 'no_preference',
            budget: null,
            additional_services: [],
            food_beverage_preferences: '',
            customer_name: '',
            customer_phone: '',
            customer_email: '',
            preferred_contact_method: 'phone',
            comments: '',
            location_id: {{ $location?->id ? $location->id : 'null' }},
            locale: '{{ app()->getLocale() }}',
        },

        // Phone state (exact parity with cart / order checkout)
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

        submitting: false,
        errorMessage: null,
        errors: {},
        successModal: false,
        successMessage: '',
        submittedReference: '',
        copied: false,

        get selectedPhoneCountry() {
            return this.phoneCountries.find(c => c.code === this.phoneCountryCode) || this.phoneCountries[0];
        },
        get phoneCleanDigits() {
            return (this.phoneNationalNumber || '').replace(/\D/g, '');
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
            } else if (country.code === 'RU' || country.code === 'US') {
                if (digits.length > 6) {
                    formatted = digits.slice(0, 3) + ' ' + digits.slice(3, 6) + '-' + digits.slice(6);
                } else if (digits.length > 3) {
                    formatted = digits.slice(0, 3) + ' ' + digits.slice(3);
                }
            } else if (country.code === 'GE') {
                if (digits.length > 5) {
                    formatted = digits.slice(0, 3) + ' ' + digits.slice(3, 5) + '-' + digits.slice(5);
                } else if (digits.length > 3) {
                    formatted = digits.slice(0, 3) + ' ' + digits.slice(3);
                }
            }

            this.phoneNationalNumber = formatted;
            if (this.phoneErrorMsg && this.isPhoneValid()) {
                this.phoneErrorMsg = '';
            }
        },

        validatePhoneOnBlur() {
            if (!this.phoneNationalNumber) {
                this.phoneErrorMsg = '{{ __('menu.phone_required') ?? 'Հեռախոսահամարը պարտադիր է' }}';
                return;
            }
            if (!this.isPhoneValid()) {
                const req = this.selectedPhoneCountry.digits || '7-15';
                this.phoneErrorMsg = '{{ __('menu.phone_invalid_length') ?? 'Համարը պետք է ունենա' }} ' + req + ' {{ __('menu.phone_digits') ?? 'նիշ' }}';
            } else {
                this.phoneErrorMsg = '';
            }
        },

        buildFullPhoneNumber() {
            const digits = this.phoneCleanDigits;
            if (!digits) return '';
            const dial = this.selectedPhoneCountry.dial;
            return dial.endsWith('+') ? '+' + digits : dial + digits;
        },

        incrementGuests() {
            if (this.form.guest_count < {{ $maxGuests }}) {
                const step = this.form.guest_count >= 50 ? 10 : 5;
                this.form.guest_count = Math.min({{ $maxGuests }}, this.form.guest_count + step);
            }
        },

        decrementGuests() {
            if (this.form.guest_count > {{ $minGuests }}) {
                const step = this.form.guest_count > 50 ? 10 : 5;
                this.form.guest_count = Math.max({{ $minGuests }}, this.form.guest_count - step);
            }
        },

        toggleService(serviceKey) {
            const idx = this.form.additional_services.indexOf(serviceKey);
            if (idx > -1) {
                this.form.additional_services.splice(idx, 1);
            } else {
                this.form.additional_services.push(serviceKey);
            }
        },

        appendFoodPref(text) {
            if (!this.form.food_beverage_preferences) {
                this.form.food_beverage_preferences = text;
            } else if (!this.form.food_beverage_preferences.includes(text)) {
                this.form.food_beverage_preferences += ', ' + text;
            }
        },

        getEventTypePreview() {
            const map = {
                'birthday': '🎂 {{ $isHy ? 'Ծննդյան օր' : 'Birthday' }}',
                'wedding': '💍 {{ $isHy ? 'Հարսանիք' : 'Wedding' }}',
                'corporate': '💼 {{ $isHy ? 'Կորպորատիվ' : 'Corporate' }}',
                'family_celebration': '👨‍👩‍👧‍👦 {{ $isHy ? 'Ընտանեկան' : 'Family' }}',
                'anniversary': '🥂 {{ $isHy ? 'Հոբելյան' : 'Anniversary' }}',
                'kids_party': '🎈 {{ $isHy ? 'Մանկական' : 'Kids Party' }}',
                'other': '✨ {{ $isHy ? 'Միջոցառում' : 'Event' }}',
            };
            return map[this.form.event_type] || '✨ {{ $isHy ? 'Միջոցառում' : 'Event' }}';
        },

        getFormattedDatePreview(dateStr) {
            if (!dateStr) return '';
            try {
                const parts = dateStr.split('-');
                if (parts.length === 3) {
                    const lang = '{{ $curLocale }}';
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

        copyReference() {
            if (!this.submittedReference) return;
            navigator.clipboard.writeText(this.submittedReference).then(() => {
                this.copied = true;
                setTimeout(() => this.copied = false, 2500);
            });
        },

        async submitForm() {
            this.validatePhoneOnBlur();
            if (this.phoneErrorMsg) {
                document.getElementById('eventCustomerPhoneInput')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            this.submitting = true;
            this.errorMessage = null;
            this.errors = {};

            const payload = {
                ...this.form,
                customer_phone: this.buildFullPhoneNumber(),
            };

            try {
                const response = await fetch('{{ route("client.event_inquiry.submit", ["vendor_slug" => $vendor->slug]) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (!response.ok) {
                    if (response.status === 422) {
                        this.errors = data.errors || {};
                        this.errorMessage = data.message || '{{ $isHy ? 'Խնդրում ենք ուղղել լրացված տվյալների սխալները։' : ($isRu ? 'Пожалуйста, исправьте ошибки в форме.' : 'Please correct the errors in the form.') }}';
                    } else {
                        this.errorMessage = data.message || '{{ $isHy ? 'Տեղի ունեցավ սխալ։ Խնդրում ենք փորձել կրկին։' : ($isRu ? 'Произошла ошибка. Пожалуйста, повторите.' : 'An error occurred. Please try again.') }}';
                    }
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return;
                }

                this.submittedReference = data.reference;
                this.successMessage = data.message;
                this.successModal = true;

            } catch (err) {
                this.errorMessage = '{{ $isHy ? 'Կապի խափանում։ Ստուգեք ինտերնետ կապը։' : ($isRu ? 'Ошибка соединения. Проверьте подключение.' : 'Network error. Please check your connection.') }}';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } finally {
                this.submitting = false;
            }
        },

        redirectToMenu() {
            window.location.href = '{{ route("client.menu", ["vendor_slug" => $vendor->slug, "location_slug" => $location?->slug]) }}';
        }
    }
}
</script>

</body>
</html>
