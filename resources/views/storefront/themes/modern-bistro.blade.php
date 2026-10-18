<!DOCTYPE html>
<html lang="{{ $lang }}" class="{{ $vendor->theme_mode ?? 'light' }}" data-theme="{{ $vendor->theme_mode ?? 'light' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="{{ $vendor->primary_color ?? '#e11d48' }}">
    <link rel="manifest" href="{{ route('client.manifest', ['vendor_slug' => $vendor->slug]) }}">
    <title>{{ $vendor->name }} - {{ __('menu.digital_menu') }}</title>

    <!-- Dynamic Favicon & Icons -->
    <link rel="icon" type="image/png" href="{{ $vendor->logo ?: \App\Models\SystemSetting::getFavicon() }}">
    <link rel="apple-touch-icon" href="{{ $vendor->logo ?: \App\Models\SystemSetting::getFavicon() }}">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    @if(!empty($vendor->sanitized_custom_css))
        <style>{!! $vendor->sanitized_custom_css !!}</style>
    @endif
    
    <style>
        :root {
            --primary: {{ $vendor->primary_color ?? '#e11d48' }};
            --accent: {{ $vendor->accent_color ?? '#f59e0b' }};
            --secondary: {{ $vendor->secondary_color ?? '#4f46e5' }};
            --bg-main: {{ $vendor->bg_color ?? ($vendor->theme_mode == 'dark' ? '#0f172a' : '#f8fafc') }};
            --bg-card: {{ $vendor->theme_mode == 'dark' ? '#1e293b' : '#ffffff' }};
            --bg-glass: {{ $vendor->theme_mode == 'dark' ? 'rgba(30, 41, 59, 0.85)' : 'rgba(255, 255, 255, 0.95)' }};
            --border-color: {{ $vendor->theme_mode == 'dark' ? 'rgba(255, 255, 255, 0.1)' : '#e2e8f0' }};
            --text-main: {{ $vendor->text_color ?? ($vendor->theme_mode == 'dark' ? '#f8fafc' : '#0f172a') }};
            --text-muted: {{ $vendor->theme_mode == 'dark' ? '#94a3b8' : '#64748b' }};
        }

        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        body, button, input, select, textarea { font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-main); color: var(--text-main); min-height: 100vh; padding-bottom: calc(115px + env(safe-area-inset-bottom, 0.5rem)); }

        .hero-bistro {
            padding: 2rem 1.25rem;
            background: linear-gradient(135deg, var(--bg-card), var(--bg-main));
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }

        .category-scroll {
            position: sticky;
            top: 0;
            z-index: 40;
            background: var(--bg-main);
            border-bottom: none;
            padding: 0.5rem 1.25rem 0.75rem;
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            scrollbar-width: none;
        }
        .category-scroll::-webkit-scrollbar { display: none; }

        .cat-chip {
            padding: 0.45rem 0.9rem;
            border-radius: 9999px;
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.2s;
        }
        .cat-chip.active {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }

        .dish-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            gap: 1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            transition: transform 0.2s;
            cursor: pointer;
        }
        .dish-card:active { transform: scale(0.99); }
        .dish-img {
            width: 95px;
            height: 95px;
            border-radius: 12px;
            object-fit: cover;
        }

        .btn-add {
            background: var(--primary);
            color: #ffffff;
            border: none;
            min-width: 42px;
            height: 42px;
            padding: 0 0.75rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
        }
        .btn-add:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }
        .btn-add:active {
            transform: scale(0.92);
        }
        .btn-add i {
            font-size: 1.15rem;
        }

        .input-field {
            width: 100%;
            padding: 0.65rem 0.9rem;
            background: var(--bg-main);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-main);
            font-size: 0.85rem;
            outline: none;
            margin-bottom: 0.65rem;
        }
        .input-field.is-locked {
            cursor: not-allowed !important;
            background: rgba(16, 185, 129, 0.08) !important;
            border-color: rgba(16, 185, 129, 0.35) !important;
            color: var(--text-main) !important;
            font-weight: 700 !important;
            padding-right: 2.25rem !important;
            user-select: none !important;
        }
        .category-section {
            scroll-margin-top: 75px;
        }

        /* Toast Notification Feedback - Full Width Top Banner */
        .toast-notification {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            z-index: 100000;
            background: var(--bg-card);
            color: var(--text-main);
            border-bottom: 2px solid var(--primary);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.35);
            padding: max(0.85rem, env(safe-area-inset-top, 0.85rem)) 1.25rem 0.85rem 1.25rem;
            border-radius: 0 0 16px 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            font-size: 0.95rem;
            font-weight: 700;
            text-align: center;
            box-sizing: border-box;
            pointer-events: none;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }
        .toast-notification span {
            max-width: 90%;
            word-break: break-word;
        }
        .toast-anim-enter, .toast-anim-leave {
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .toast-anim-start {
            opacity: 0;
            transform: translateY(-100%);
        }
        .toast-anim-end {
            opacity: 1;
            transform: translateY(0);
        }
        .toast-success { border-bottom-color: #10b981; }
        .toast-success i { color: #10b981; font-size: 1.15rem; flex-shrink: 0; }
        .toast-info { border-bottom-color: #3b82f6; }
        .toast-info i { color: #3b82f6; font-size: 1.15rem; flex-shrink: 0; }
        .toast-remove { border-bottom-color: #ef4444; }
        .toast-remove i { color: #ef4444; font-size: 1.15rem; flex-shrink: 0; }
    </style>
    @include('storefront.components.desktop-frame-styles')
</head>
<body x-data="modernBistroApp()">

    <!-- Storefront Desktop Max-Width App Shell -->
    <div class="storefront-app-shell">

        <!-- Toast Notification Feedback -->
        <div x-show="toast.show" 
             x-transition:enter="toast-anim-enter"
             x-transition:enter-start="toast-anim-start"
             x-transition:enter-end="toast-anim-end"
             x-transition:leave="toast-anim-leave"
             x-transition:leave-start="toast-anim-end"
             x-transition:leave-end="toast-anim-start"
             class="toast-notification"
             :class="'toast-' + toast.type"
             x-cloak>
            <i :class="toast.icon"></i>
            <span x-text="toast.message"></span>
        </div>

        <!-- Header Hero -->
        <div class="hero-bistro">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.85rem;">
                <div style="display: flex; align-items: center; gap: 1.15rem; min-width: 0;">
                    <img src="{{ $vendor->logo ?? 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=200&q=80' }}" style="width: 70px; height: 70px; border-radius: 16px; object-fit: cover; border: 2px solid var(--primary); flex-shrink: 0;">
                    <div style="min-width: 0;">
                        <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.45rem; font-weight: 800; color: var(--text-main); line-height: 1.25; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $vendor->name }}</h1>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem; display: flex; align-items: center; gap: 0.35rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <i class="fa-solid fa-location-dot" style="color: var(--primary); flex-shrink: 0;"></i> 
                            <span>{{ $location?->name ?? 'Main Location' }}</span>
                            @if($table)
                                • <span style="background: var(--primary); color: #fff; padding: 0.1rem 0.4rem; border-radius: 4px; font-weight: 700; flex-shrink: 0;">Table {{ $table }}</span>
                            @endif
                        </p>
                    </div>
                </div>
                <!-- Redesigned Top Info Button -->
                <button type="button" 
                        class="header-info-pill-btn" 
                        @click="showInfoModal = true" 
                        title="{{ __('menu.nav_info') }}">
                    <i class="fa-solid fa-circle-info"></i>
                    <span class="info-btn-text">{{ __('menu.nav_info') }}</span>
                </button>
            </div>
        </div>

    <!-- Order Type Selector Bar (Direct Visits: Delivery vs Takeaway, or Table QR badge) -->
    @include('storefront.components.order-type-header-toggle')

    <!-- Search & Language Bar -->
    <div style="padding: 1rem 1.25rem 0.5rem; display: flex; gap: 0.75rem;">
        <div style="flex: 1; position: relative;">
            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
            <input type="text" x-model.debounce.250ms="search" placeholder="{{ __('menu.search_placeholder') }}" style="width: 100%; padding: 0.6rem 1rem 0.6rem 2.5rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; color: var(--text-main); outline: none; font-size: 0.85rem;">
        </div>

        <select @change="changeLanguage($event.target.value)" onchange="const u = new URL(window.location.href); u.searchParams.set('lang', this.value); window.location.href = u.toString();" style="background: var(--bg-card); color: var(--text-main); border: 1px solid var(--border-color); padding: 0.5rem 0.75rem; border-radius: 10px; font-size: 0.85rem; font-weight: 700; cursor: pointer;">
            @foreach($vendor->getSupportedLanguages() as $sLang)
                <option value="{{ $sLang['code'] }}" {{ $lang == $sLang['code'] ? 'selected' : '' }}>
                    {{ $sLang['flag'] ?? '🌐' }} {{ strtoupper($sLang['code']) }}
                </option>
            @endforeach
        </select>
    </div>

    <!-- Category Chips -->
    <nav class="category-scroll">
        @foreach($categories as $cat)
            @php
                $catDishesMeta = $cat->products->map(fn($p) => [
                    'name' => mb_strtolower($p->getTranslatedName($lang)),
                    'dine_in' => (bool)($p->available_for_dine_in ?? true),
                    'takeaway' => (bool)($p->available_for_takeaway ?? true),
                    'delivery' => (bool)($p->available_for_delivery ?? true),
                ])->values()->all();
            @endphp
            <button id="chip-cat-{{ $cat->id }}" class="cat-chip" 
                    :class="{ 'active': activeCat === 'cat-{{ $cat->id }}' }" 
                    x-show="isCategoryVisible('cat-{{ $cat->id }}', {{ json_encode($catDishesMeta, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }})"
                    @click="scrollToCat('cat-{{ $cat->id }}')">
                {{ $cat->getTranslatedName($lang) }}
            </button>
        @endforeach
    </nav>

    <!-- Main Dishes -->
    <div style="padding: 1.25rem;">
        @include('storefront.components.closing-banner')
        @include('storefront.components.featured-dish-banner')

        @foreach($categories as $cat)
            @php
                $catDishesMeta = $cat->products->map(fn($p) => [
                    'name' => mb_strtolower($p->getTranslatedName($lang)),
                    'dine_in' => (bool)($p->available_for_dine_in ?? true),
                    'takeaway' => (bool)($p->available_for_takeaway ?? true),
                    'delivery' => (bool)($p->available_for_delivery ?? true),
                ])->values()->all();
            @endphp
            <div id="cat-{{ $cat->id }}" class="category-section" 
                 x-show="isCategoryVisible('cat-{{ $cat->id }}', {{ json_encode($catDishesMeta, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }})"
                 style="margin-bottom: 2.5rem;">
                <h2 style="font-family: 'Outfit'; font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-main); padding-bottom: 0.5rem; border-bottom: 2px solid var(--border-color);">
                    {{ $cat->getTranslatedName($lang) }}
                </h2>

                <div class="dishes-grid">
                    @foreach($cat->products as $prod)
                        @php
                            $isLocAvailable = $prod->isAvailableAtLocation($location?->id);
                            $isTimeAvailable = $prod->isTimeAvailable();
                            $scheduleSummary = $prod->getAvailabilityScheduleSummary($lang);
                            $isDineIn = (bool)($prod->available_for_dine_in ?? true);
                            $isTakeaway = (bool)($prod->available_for_takeaway ?? true);
                            $isDelivery = (bool)($prod->available_for_delivery ?? true);

                            $prodPayload = [
                                'id' => $prod->id,
                                'name' => $prod->getTranslatedName($lang),
                                'image' => $prod->image ?: asset('images/default-dish.png'),
                                'description' => $prod->getTranslatedDescription($lang),
                                'base_price' => (float)$prod->getEffectivePrice($location?->id),
                                'regular_price' => (float)$prod->getRegularPrice($location?->id),
                                'is_discount_active' => $prod->isDiscountActive(),
                                'discount_percentage' => $prod->getDiscountPercentage(),
                                'is_available' => $isLocAvailable,
                                'is_time_available' => $isTimeAvailable,
                                'schedule_summary' => $scheduleSummary,
                                'available_for_dine_in' => $isDineIn,
                                'available_for_takeaway' => $isTakeaway,
                                'available_for_delivery' => $isDelivery,
                                'variations' => $prod->variations->map(fn($v) => [
                                    'id' => $v->id,
                                    'name' => $v->getTranslatedName($lang),
                                    'price' => (float)$v->getEffectivePrice(),
                                    'regular_price' => (float)$v->price,
                                    'is_default' => (bool)$v->is_default
                                ])->values(),
                            ];
                        @endphp
                        <div class="dish-card" style="{{ (!$isLocAvailable || !$isTimeAvailable) ? 'opacity: 0.68;' : '' }}" 
                             x-show='matchesSearch({!! json_encode(mb_strtolower($prod->getTranslatedName($lang))) !!}) && isProductAvailableForCurrentOrderType({{ $isDineIn ? 'true' : 'false' }}, {{ $isTakeaway ? 'true' : 'false' }}, {{ $isDelivery ? 'true' : 'false' }})' 
                             @click='selectDish({{ json_encode($prodPayload, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }})'>
                            <img src="{{ $prod->image }}" onerror="this.onerror=null;this.src='{{ asset('images/default-dish.png') }}';" loading="lazy" decoding="async" class="dish-img" alt="{{ $prod->getTranslatedName($lang) }}">
                            <div style="flex: 1; display: flex; flex-direction: column; justify-content: space-between;">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; margin-bottom: 0.25rem;">
                                        <div style="font-weight: 700; font-size: 1.05rem; color: var(--text-main);">{{ $prod->getTranslatedName($lang) }}</div>

                                        @if(!$isLocAvailable)
                                            <span style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.35); padding: 0.1rem 0.45rem; border-radius: 6px; font-size: 0.65rem; font-weight: 800;">
                                                🚫 {{ __('menu.out_of_stock') ?: 'Սպառված է' }}
                                            </span>

                                        @elseif(!$isTimeAvailable)
                                            <span style="background: rgba(245, 158, 11, 0.15); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.35); padding: 0.12rem 0.5rem; border-radius: 6px; font-size: 0.68rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                                                <i class="fa-regular fa-clock"></i> {{ __('menu.only_available_hours_notice', ['time' => $scheduleSummary]) }}
                                            </span>
                                        @elseif($scheduleSummary)
                                            <span style="background: rgba(16, 185, 129, 0.12); color: #059669; border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.12rem 0.5rem; border-radius: 6px; font-size: 0.68rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                                                <i class="fa-regular fa-clock"></i> {{ $scheduleSummary }}
                                            </span>
                                        @endif

                                        @if(!$isDineIn || !$isTakeaway || !$isDelivery)
                                            <span style="background: rgba(99, 102, 241, 0.15); color: #6366f1; border: 1px solid rgba(99, 102, 241, 0.3); padding: 0.1rem 0.45rem; border-radius: 6px; font-size: 0.65rem; font-weight: 700;">
                                                @if($isDelivery && !$isDineIn && !$isTakeaway)
                                                    🛵 {{ __('menu.delivery_only') ?: 'Միայն առաքում' }}
                                                @elseif($isDineIn && !$isDelivery && !$isTakeaway)
                                                    🍽️ {{ __('menu.dine_in_only') ?: 'Միայն սրահում' }}
                                                @elseif($isTakeaway && !$isDelivery && !$isDineIn)
                                                    🥡 {{ __('menu.takeaway_only') ?: 'Միայն տանելու' }}
                                                @endif
                                            </span>
                                        @endif

                                        @if($prod->isDiscountActive())
                                            <span style="background: linear-gradient(135deg, #ef4444, #f43f5e); color: #ffffff; border: 1px solid rgba(239, 68, 68, 0.4); padding: 0.1rem 0.45rem; border-radius: 6px; font-size: 0.65rem; font-weight: 800; display: inline-flex; align-items: center; gap: 0.25rem;">
                                                <i class="fa-solid fa-tag"></i> -{{ $prod->getDiscountPercentage() }}%
                                            </span>
                                        @endif
                                        @if($prod->is_featured)
                                            <span style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); padding: 0.1rem 0.4rem; border-radius: 6px; font-size: 0.65rem; font-weight: 700;">★ FEATURED</span>
                                        @endif
                                        @if(!empty($prod->dietary_tags))
                                            @foreach($prod->dietary_tags as $tag)
                                                <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.1rem 0.4rem; border-radius: 6px; font-size: 0.65rem; text-transform: uppercase; font-weight: 700;">{{ str_replace('_', ' ', $tag) }}</span>
                                            @endforeach
                                        @endif
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        {{ $prod->getTranslatedDescription($lang) }}
                                    </div>
                                    @if($prod->calories || $prod->preparation_time_min || ($prod->allergens && $prod->allergens->count() > 0))
                                        <div style="display: flex; gap: 0.75rem; align-items: center; margin-top: 0.35rem; font-size: 0.72rem; color: var(--text-muted); flex-wrap: wrap;">
                                            @if($prod->calories)
                                                <span><i class="fa-solid fa-fire" style="color: #f97316;"></i> {{ $prod->calories }} kcal</span>
                                            @endif
                                            @if($prod->preparation_time_min)
                                                <span><i class="fa-solid fa-clock"></i> {{ $prod->preparation_time_min }} {{ __('menu.mins') }}</span>
                                            @endif
                                            @if($prod->allergens && $prod->allergens->count() > 0)
                                                <span><i class="fa-solid fa-triangle-exclamation" style="color: #ef4444;"></i>
                                                    {{ __('menu.allergens') }}
                                                    {{ $prod->allergens->pluck('icon')->join(' ') }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 0.5rem;">
                                    <div style="font-family: 'Outfit'; font-weight: 800; color: var(--accent); font-size: 1.15rem;">
                                        @if($prod->isDiscountActive())
                                            <div style="display: flex; align-items: baseline; gap: 0.4rem; flex-wrap: wrap;">
                                                <span style="color: #ef4444;">{{ number_format($prod->getEffectivePrice($location?->id)) }} {{ $vendor->currency }}</span>
                                                <span style="font-size: 0.8rem; text-decoration: line-through; color: var(--text-muted); font-weight: 500;">
                                                    {{ number_format($prod->getRegularPrice($location?->id)) }}
                                                </span>
                                            </div>
                                        @elseif($prod->variations->count() > 1)
                                            <small style="font-size: 0.72rem; font-weight: 500; color: var(--text-muted);">{{ __('menu.starting_from') }}</small>
                                            {{ number_format($prod->variations->min('price')) }} {{ $vendor->currency }}
                                        @else
                                            {{ number_format($prod->getEffectivePrice($location?->id)) }} {{ $vendor->currency }}
                                        @endif
                                    </div>
                                    @if(!$isLocAvailable)
                                        <button class="btn-add" disabled style="opacity: 0.55; cursor: not-allowed; font-size: 0.75rem; padding: 0.4rem 0.65rem;" title="{{ __('menu.out_of_stock') ?: 'Սպառված է' }}">
                                            <span>{{ __('menu.out_of_stock') ?: 'Սպառված է' }}</span>
                                        </button>
                                    @elseif(!$isTimeAvailable)
                                        <button class="btn-add" disabled style="opacity: 0.85; cursor: not-allowed; font-size: 0.72rem; padding: 0.4rem 0.65rem; background: rgba(245, 158, 11, 0.15); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; gap: 0.3rem;" title="{{ __('menu.only_available_hours_notice', ['time' => $scheduleSummary]) }}">
                                            <i class="fa-regular fa-clock"></i>
                                            <span>{{ __('menu.only_available_hours_notice', ['time' => $scheduleSummary]) }}</span>
                                        </button>
                                    @else
                                        <button class="btn-add" @click.stop='selectDish({{ json_encode($prodPayload, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }})' aria-label="{{ __('menu.add_to_cart') }}" title="{{ __('menu.add_to_cart') }}">
                                            @if($prod->variations->count() > 1)
                                                <span>{{ __('menu.select_portion_btn') }}</span>
                                            @else
                                                <i class="fa-solid fa-cart-plus"></i>
                                            @endif
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <!-- Storefront Discreet Legal & Powered By Footer -->
        <footer style="text-align: center; padding: 2rem 1rem 3rem; margin-top: 2rem; border-top: 1px dashed var(--border-color); color: var(--text-muted); font-size: 0.78rem;">
            <div style="display: flex; justify-content: center; align-items: center; gap: 0.85rem; margin-bottom: 0.65rem;">
                <a href="{{ route('legal.privacy') }}" style="color: var(--text-muted); text-decoration: none; transition: color 0.2s;">{{ __('footer_privacy') }}</a>
                <span>•</span>
                <a href="{{ route('legal.terms') }}" style="color: var(--text-muted); text-decoration: none; transition: color 0.2s;">{{ __('footer_terms') }}</a>
            </div>
            <div style="display: flex; align-items: center; justify-content: center; gap: 0.4rem; font-weight: 500;">
                <span>Powered by</span>
                <a href="{{ route('landing') }}" style="color: var(--primary); text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <img src="{{ \App\Models\SystemSetting::getFavicon() }}" alt="{{ \App\Models\SystemSetting::getSiteName() }}" style="width: 14px; height: 14px; border-radius: 3px; object-fit: contain;">
                    <span>{{ \App\Models\SystemSetting::getSiteName() }}</span>
                </a>
            </div>
        </footer>
    </div>
    </div> <!-- /.storefront-app-shell -->

    <!-- Initial Order Type Selection Modal -->
    @include('storefront.components.order-type-modal')

    <!-- Variation Selection Modal -->
    @include('storefront.components.variation-modal')

    <!-- Modern Cart & Checkout Modal -->
    @include('storefront.components.cart-modal')

    <!-- AI Waiter Components -->
    @include('storefront.components.welcome-ai-waiter-modal')
    @include('storefront.components.fullscreen-ai-waiter')

    @include('storefront.components.bottom-nav')
    @include('storefront.components.info-modal')
    @include('storefront.components.order-tracker-modal')
    @include('storefront.components.waiter-modal')
    @include('storefront.components.storefront-scripts')
</body>
</html>
