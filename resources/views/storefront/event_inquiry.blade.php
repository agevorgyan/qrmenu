<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ filled($settings['form_title'] ?? '') ? $settings['form_title'] : __('Event Planning Inquiry') }} — {{ $vendor->name }}</title>
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
            --primary: {{ $vendor->primary_color ?: '#3b82f6' }};
            --primary-contrast: {{ $primaryTextColor }};
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
            --shadow-card: 0 8px 30px rgba(0, 0, 0, 0.45);
            --banner-bg: rgba(245, 158, 11, 0.08);
            --banner-border: rgba(245, 158, 11, 0.25);
            --banner-text: #fbbf24;
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
            --shadow-card: 0 8px 30px rgba(0, 0, 0, 0.06);
            --banner-bg: #fffbeb;
            --banner-border: #fde68a;
            --banner-text: #b45309;
            @endif

            --radius-lg: 18px;
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

        .event-container {
            max-width: 680px;
            margin: 0 auto;
            padding: 1.25rem 1rem;
        }

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
            padding: 0.6rem 1rem;
            border-radius: var(--radius-md);
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            color: var(--text-main);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
            transition: all 0.2s;
        }
        .back-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .lang-switch {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            padding: 0.25rem;
            border-radius: var(--radius-md);
        }
        .lang-link {
            padding: 0.35rem 0.6rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            text-decoration: none;
            color: var(--text-muted);
            text-transform: uppercase;
        }
        .lang-link.active {
            background: var(--primary);
            color: var(--primary-contrast);
        }

        .page-header {
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .restaurant-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary);
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }
        .page-title {
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
            line-height: 1.25;
        }
        .page-desc {
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.5;
            max-width: 540px;
            margin: 0 auto;
        }

        .disclaimer-card {
            background: var(--banner-bg);
            border: 1px solid var(--banner-border);
            color: var(--banner-text);
            border-radius: var(--radius-md);
            padding: 1rem 1.15rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .disclaimer-card i {
            font-size: 1.15rem;
            margin-top: 0.15rem;
            flex-shrink: 0;
        }

        .form-section {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-card);
        }
        .section-header {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--card-border);
        }
        .section-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(59, 130, 246, 0.12);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .field-group {
            margin-bottom: 1.15rem;
        }
        .field-group:last-child {
            margin-bottom: 0;
        }
        .field-label {
            display: block;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--text-subtle);
            margin-bottom: 0.45rem;
        }
        .field-label .required {
            color: #ef4444;
            margin-left: 0.2rem;
        }
        .field-hint {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.3rem;
        }

        .input-control {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            color: var(--text-main);
            padding: 0.75rem 0.95rem;
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            outline: none;
            transition: all 0.2s;
            font-family: inherit;
        }
        .input-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .chips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 0.6rem;
        }
        .chip-option {
            background: var(--chip-bg);
            border: 1px solid var(--chip-border);
            padding: 0.75rem 0.85rem;
            border-radius: var(--radius-md);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 0.4rem;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--text-main);
            user-select: none;
        }
        .chip-option:hover {
            border-color: var(--primary);
        }
        .chip-option.active {
            background: rgba(59, 130, 246, 0.12);
            border-color: var(--primary);
            color: var(--primary);
            font-weight: 700;
            box-shadow: 0 0 0 1px var(--primary);
        }
        .chip-option i {
            font-size: 1.25rem;
        }
        .chip-option span {
            font-size: 0.85rem;
            line-height: 1.2;
        }

        .counter-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            padding: 0.5rem 0.75rem;
            border-radius: var(--radius-md);
        }
        .counter-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: 1px solid var(--card-border);
            background: var(--card-bg);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.15s;
        }
        .counter-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .counter-btn:not(:disabled):hover {
            background: var(--primary);
            color: var(--primary-contrast);
            border-color: var(--primary);
        }
        .counter-value {
            font-size: 1.35rem;
            font-weight: 800;
            min-width: 60px;
            text-align: center;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 0.6rem;
        }
        .service-pill {
            background: var(--chip-bg);
            border: 1px solid var(--chip-border);
            border-radius: var(--radius-md);
            padding: 0.7rem 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.88rem;
            user-select: none;
        }
        .service-pill.active {
            background: rgba(16, 185, 129, 0.12);
            border-color: var(--accent);
            color: var(--accent);
            font-weight: 700;
        }

        .submit-btn {
            width: 100%;
            background: var(--primary);
            color: var(--primary-contrast);
            border: none;
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.35);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            margin-top: 1.5rem;
        }
        .submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        .submit-btn:not(:disabled):hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.45);
        }

        .form-error-banner {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 0.85rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        /* Success Overlay Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(6px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            z-index: 9999;
        }
        .modal-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            max-width: 480px;
            width: 100%;
            padding: 2rem 1.5rem;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            animation: modalFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .success-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            margin: 0 auto 1.25rem;
        }
        .ref-box {
            background: var(--chip-bg);
            border: 1px dashed var(--chip-border);
            border-radius: var(--radius-md);
            padding: 0.85rem 1rem;
            margin: 1.25rem 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .ref-code {
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            color: var(--primary);
        }
    </style>
</head>
<body>

<div class="event-container" x-data="eventInquiryForm()">
    
    <!-- Header Bar -->
    <div class="header-bar">
        <a href="{{ route('client.menu', ['vendor_slug' => $vendor->slug, 'location_slug' => $location?->slug]) }}" class="back-btn">
            <i class="fa-solid fa-arrow-left"></i>
            <span>{{ __('Back to Menu') }}</span>
        </a>

        <div class="lang-switch">
            @php $curLocale = app()->getLocale(); @endphp
            <a href="?lang=hy" class="lang-link {{ $curLocale === 'hy' ? 'active' : '' }}">HY</a>
            <a href="?lang=en" class="lang-link {{ $curLocale === 'en' ? 'active' : '' }}">EN</a>
            <a href="?lang=ru" class="lang-link {{ $curLocale === 'ru' ? 'active' : '' }}">RU</a>
        </div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <div class="restaurant-badge">
            <i class="fa-solid fa-champagne-glasses"></i>
            <span>{{ $vendor->name }}</span>
        </div>
        <h1 class="page-title">
            {{ filled($settings['form_title'] ?? '') ? $settings['form_title'] : __('Event Planning Inquiry') }}
        </h1>
        <p class="page-desc">
            {{ filled($settings['form_description'] ?? '') ? $settings['form_description'] : __('Plan your private celebration, corporate dinner, birthday party or wedding with us. Fill in your details below and our team will get in touch.') }}
        </p>
    </div>

    <!-- Disclaimer Alert -->
    <div class="disclaimer-card">
        <i class="fa-solid fa-circle-info"></i>
        <div>
            <strong>{{ __('Inquiry Notice') }}:</strong>
            {{ __('Submitting an inquiry does not confirm a reservation or guarantee availability. Our team will review your requirements and contact you with a customized offer.') }}
        </div>
    </div>

    <!-- Error Banner -->
    <template x-if="errorMessage">
        <div class="form-error-banner">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span x-text="errorMessage"></span>
        </div>
    </template>

    <form @submit.prevent="submitForm">
        <!-- 1. Event Type -->
        <div class="form-section">
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-cake-candles"></i></div>
                <div class="section-title">{{ __('1. Event Type') }} <span style="color:#ef4444;">*</span></div>
            </div>

            <div class="chips-grid">
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
                    @endphp
                    <div class="chip-option" 
                         :class="{ 'active': form.event_type === '{{ $key }}' }"
                         @click="form.event_type = '{{ $key }}'">
                        <i class="{{ $icon }}"></i>
                        <span>{{ __($title) }}</span>
                    </div>
                @endforeach
            </div>
            <template x-if="errors.event_type">
                <div class="field-hint" style="color: #ef4444;" x-text="errors.event_type[0]"></div>
            </template>
        </div>

        <!-- 2. Date, Time & Guests -->
        <div class="form-section">
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-calendar-days"></i></div>
                <div class="section-title">{{ __('2. Date & Estimated Guests') }}</div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.15rem;">
                <div class="field-group" style="margin-bottom: 0;">
                    <label class="field-label">{{ __('Preferred Date') }} <span class="required">*</span></label>
                    <input type="date" 
                           class="input-control" 
                           x-model="form.event_date" 
                           min="{{ date('Y-m-d') }}" 
                           required>
                    <template x-if="errors.event_date">
                        <div class="field-hint" style="color: #ef4444;" x-text="errors.event_date[0]"></div>
                    </template>
                </div>

                <div class="field-group" style="margin-bottom: 0;">
                    <label class="field-label">{{ __('Number of Guests') }} <span class="required">*</span></label>
                    <div class="counter-box">
                        <button type="button" class="counter-btn" @click="decrementGuests" :disabled="form.guest_count <= {{ $minGuests }}">
                            <i class="fa-solid fa-minus"></i>
                        </button>
                        <span class="counter-value" x-text="form.guest_count"></span>
                        <button type="button" class="counter-btn" @click="incrementGuests" :disabled="form.guest_count >= {{ $maxGuests }}">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                    <div class="field-hint" style="text-align: center;">
                        {{ __('Min :min, Max :max', ['min' => $minGuests, 'max' => $maxGuests]) }}
                    </div>
                </div>
            </div>

            @if(!empty($opt['start_time']) || !empty($opt['end_time']))
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    @if(!empty($opt['start_time']))
                        <div class="field-group" style="margin-bottom: 0;">
                            <label class="field-label">{{ __('Preferred Start Time') }}</label>
                            <input type="time" class="input-control" x-model="form.start_time">
                        </div>
                    @endif

                    @if(!empty($opt['end_time']))
                        <div class="field-group" style="margin-bottom: 0;">
                            <label class="field-label">{{ __('End Time / Estimated Duration') }}</label>
                            <input type="time" class="input-control" x-model="form.end_time">
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <!-- 3. Venue Arrangement & Budget -->
        @if(!empty($opt['venue_arrangement']) || !empty($opt['budget']))
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon"><i class="fa-solid fa-door-open"></i></div>
                    <div class="section-title">{{ __('3. Venue & Budget') }}</div>
                </div>

                @if(!empty($opt['venue_arrangement']))
                    <div class="field-group">
                        <label class="field-label">{{ __('Preferred Venue Arrangement') }}</label>
                        <div class="chips-grid">
                            <div class="chip-option" :class="{ 'active': form.venue_arrangement === 'no_preference' }" @click="form.venue_arrangement = 'no_preference'">
                                <i class="fa-solid fa-shuffle"></i>
                                <span>{{ __('No Preference') }}</span>
                            </div>
                            <div class="chip-option" :class="{ 'active': form.venue_arrangement === 'indoor' }" @click="form.venue_arrangement = 'indoor'">
                                <i class="fa-solid fa-house"></i>
                                <span>{{ __('Indoor Hall') }}</span>
                            </div>
                            <div class="chip-option" :class="{ 'active': form.venue_arrangement === 'outdoor' }" @click="form.venue_arrangement = 'outdoor'">
                                <i class="fa-solid fa-sun"></i>
                                <span>{{ __('Outdoor / Terrace') }}</span>
                            </div>
                            <div class="chip-option" :class="{ 'active': form.venue_arrangement === 'private_room' }" @click="form.venue_arrangement = 'private_room'">
                                <i class="fa-solid fa-key"></i>
                                <span>{{ __('Private VIP Room') }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                @if(!empty($opt['budget']))
                    <div class="field-group" style="margin-bottom: 0;">
                        <label class="field-label">{{ __('Estimated Budget') }} ({{ $vendor->currency ?: 'AMD' }})</label>
                        <input type="number" 
                               step="5000" 
                               min="0" 
                               class="input-control" 
                               x-model="form.budget" 
                               placeholder="{{ __('e.g. 150000') }}">
                        <div class="field-hint">{{ __('Optional: helps our team customize the menu within your expectations.') }}</div>
                    </div>
                @endif
            </div>
        @endif

        <!-- 4. Additional Services & Food Preferences -->
        @if(!empty($opt['additional_services']) || !empty($opt['food_beverage_preferences']))
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                    <div class="section-title">{{ __('4. Services & Preferences') }}</div>
                </div>

                @if(!empty($opt['additional_services']))
                    <div class="field-group">
                        <label class="field-label">{{ __('Additional Services Needed') }}</label>
                        <div class="services-grid">
                            @php
                                $servicesList = [
                                    'decoration' => ['icon' => 'fa-solid fa-ribbon', 'label' => 'Decoration & Flowers'],
                                    'music' => ['icon' => 'fa-solid fa-music', 'label' => 'Music / DJ'],
                                    'entertainment' => ['icon' => 'fa-solid fa-microphone', 'label' => 'Host / Entertainment'],
                                    'equipment' => ['icon' => 'fa-solid fa-tv', 'label' => 'Projector & Sound'],
                                    'cake' => ['icon' => 'fa-solid fa-cake-candles', 'label' => 'Celebration Cake'],
                                    'photography' => ['icon' => 'fa-solid fa-camera', 'label' => 'Photo / Video'],
                                ];
                            @endphp
                            @foreach($servicesList as $sKey => $sVal)
                                <div class="service-pill" 
                                     :class="{ 'active': form.additional_services.includes('{{ $sKey }}') }"
                                     @click="toggleService('{{ $sKey }}')">
                                    <i class="{{ $sVal['icon'] }}"></i>
                                    <span>{{ __($sVal['label']) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($opt['food_beverage_preferences']))
                    <div class="field-group" style="margin-bottom: 0;">
                        <label class="field-label">{{ __('Food & Beverage Preferences') }}</label>
                        <textarea class="input-control" 
                                  rows="2" 
                                  x-model="form.food_beverage_preferences" 
                                  placeholder="{{ __('e.g. Traditional Armenian dishes, vegetarian options, specific wine preferences...') }}"></textarea>
                    </div>
                @endif
            </div>
        @endif

        <!-- 5. Customer Contact Info -->
        <div class="form-section">
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-address-card"></i></div>
                <div class="section-title">{{ __('5. Contact Information') }}</div>
            </div>

            <div class="field-group">
                <label class="field-label">{{ __('Full Name') }} <span class="required">*</span></label>
                <input type="text" 
                       class="input-control" 
                       x-model="form.customer_name" 
                       placeholder="{{ __('e.g. Aram Grigoryan') }}" 
                       required>
                <template x-if="errors.customer_name">
                    <div class="field-hint" style="color: #ef4444;" x-text="errors.customer_name[0]"></div>
                </template>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.15rem;">
                <div class="field-group" style="margin-bottom: 0;">
                    <label class="field-label">{{ __('Phone Number') }} <span class="required">*</span></label>
                    <input type="tel" 
                           class="input-control" 
                           x-model="form.customer_phone" 
                           placeholder="{{ __('+374 XX XXXXXX') }}" 
                           required>
                    <template x-if="errors.customer_phone">
                        <div class="field-hint" style="color: #ef4444;" x-text="errors.customer_phone[0]"></div>
                    </template>
                </div>

                <div class="field-group" style="margin-bottom: 0;">
                    <label class="field-label">
                        {{ __('Email Address') }}
                        <span x-show="form.preferred_contact_method === 'email'" class="required">*</span>
                    </label>
                    <input type="email" 
                           class="input-control" 
                           x-model="form.customer_email" 
                           placeholder="{{ __('example@gmail.com') }}">
                    <template x-if="errors.customer_email">
                        <div class="field-hint" style="color: #ef4444;" x-text="errors.customer_email[0]"></div>
                    </template>
                </div>
            </div>

            <div class="field-group">
                <label class="field-label">{{ __('Preferred Contact Method') }} <span class="required">*</span></label>
                <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
                    @foreach($contactMethods as $method)
                        @php
                            $mIcon = match($method) {
                                'whatsapp' => 'fa-brands fa-whatsapp',
                                'email' => 'fa-solid fa-envelope',
                                default => 'fa-solid fa-phone',
                            };
                            $mLabel = match($method) {
                                'whatsapp' => 'WhatsApp',
                                'email' => 'Email',
                                default => 'Phone Call',
                            };
                        @endphp
                        <div class="service-pill" 
                             :class="{ 'active': form.preferred_contact_method === '{{ $method }}' }"
                             @click="form.preferred_contact_method = '{{ $method }}'">
                            <i class="{{ $mIcon }}"></i>
                            <span>{{ __($mLabel) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            @if(!empty($opt['comments']))
                <div class="field-group" style="margin-bottom: 0;">
                    <label class="field-label">{{ __('Additional Requirements & Comments') }}</label>
                    <textarea class="input-control" 
                              rows="3" 
                              x-model="form.comments" 
                              placeholder="{{ __('Any specific requests, surprise elements, timing preferences or questions...') }}"></textarea>
                </div>
            @endif
        </div>

        <!-- Submit Button -->
        <button type="submit" class="submit-btn" :disabled="submitting">
            <template x-if="!submitting">
                <span>
                    <i class="fa-solid fa-paper-plane" style="margin-right: 0.35rem;"></i>
                    {{ __('Submit Event Inquiry') }}
                </span>
            </template>
            <template x-if="submitting">
                <span>
                    <i class="fa-solid fa-circle-notch fa-spin" style="margin-right: 0.35rem;"></i>
                    {{ __('Submitting Inquiry...') }}
                </span>
            </template>
        </button>
    </form>

    <!-- Success Modal -->
    <template x-if="successModal">
        <div class="modal-overlay" @click.self="redirectToMenu">
            <div class="modal-card">
                <div class="success-icon">
                    <i class="fa-solid fa-check"></i>
                </div>
                <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem;">
                    {{ __('Inquiry Received!') }}
                </h2>
                <p style="font-size: 0.95rem; color: var(--text-muted); line-height: 1.5;" x-text="successMessage"></p>

                <div class="ref-box">
                    <span style="font-size: 0.85rem; color: var(--text-muted);">{{ __('Reference Code') }}</span>
                    <span class="ref-code" x-text="submittedReference"></span>
                </div>

                <p style="font-size: 0.85rem; color: var(--text-subtle); margin-bottom: 1.5rem; line-height: 1.4;">
                    {{ __('Please keep this reference code for your communication with the restaurant.') }}
                </p>

                <button type="button" class="submit-btn" style="margin-top: 0;" @click="redirectToMenu">
                    <i class="fa-solid fa-utensils"></i>
                    <span>{{ __('Back to Restaurant Menu') }}</span>
                </button>
            </div>
        </div>
    </template>

</div>

<script>
function eventInquiryForm() {
    return {
        form: {
            event_type: '{{ array_key_first($eventTypes) ?: "birthday" }}',
            event_date: '{{ date("Y-m-d", strtotime("+3 days")) }}',
            guest_count: {{ max(10, $minGuests) }},
            start_time: '18:00',
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
        submitting: false,
        errorMessage: null,
        errors: {},
        successModal: false,
        successMessage: '',
        submittedReference: '',

        incrementGuests() {
            if (this.form.guest_count < {{ $maxGuests }}) {
                this.form.guest_count += 5;
            }
        },

        decrementGuests() {
            if (this.form.guest_count > {{ $minGuests }}) {
                this.form.guest_count = Math.max({{ $minGuests }}, this.form.guest_count - 5);
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

        async submitForm() {
            this.submitting = true;
            this.errorMessage = null;
            this.errors = {};

            try {
                const response = await fetch('{{ route("client.event_inquiry.submit", ["vendor_slug" => $vendor->slug]) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify(this.form)
                });

                const data = await response.json();

                if (!response.ok) {
                    if (response.status === 422) {
                        this.errors = data.errors || {};
                        this.errorMessage = data.message || '{{ __("Please correct the errors in the form.") }}';
                    } else {
                        this.errorMessage = data.message || '{{ __("An error occurred. Please try again.") }}';
                    }
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return;
                }

                this.submittedReference = data.reference;
                this.successMessage = data.message;
                this.successModal = true;

            } catch (err) {
                this.errorMessage = '{{ __("Network error. Please check your connection.") }}';
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
