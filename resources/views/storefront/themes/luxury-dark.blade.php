<!DOCTYPE html>
<html lang="{{ $lang }}" class="{{ $vendor->theme_mode ?? 'dark' }}" data-theme="{{ $vendor->theme_mode ?? 'dark' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="{{ $vendor->primary_color ?? '#f59e0b' }}">
    <link rel="manifest" href="{{ route('client.manifest', ['vendor_slug' => $vendor->slug]) }}">
    <title>{{ $vendor->name }} - {{ __('menu.digital_menu') }}</title>

    <!-- Dynamic Favicon & Icons -->
    <link rel="icon" type="image/png" href="{{ $vendor->logo ?: \App\Models\SystemSetting::getFavicon() }}">
    <link rel="apple-touch-icon" href="{{ $vendor->logo ?: \App\Models\SystemSetting::getFavicon() }}">

    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Alpine.js & FontAwesome -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    @if(!empty($vendor->sanitized_custom_css))
        <style>{!! $vendor->sanitized_custom_css !!}</style>
    @endif

    <style>
        :root {
            --primary: {{ $vendor->primary_color ?? '#f59e0b' }};
            --accent: {{ $vendor->accent_color ?? $vendor->primary_color ?? '#f59e0b' }};
            --secondary: {{ $vendor->secondary_color ?? '#4f46e5' }};
            --bg-main: {{ $vendor->bg_color ?? ($vendor->theme_mode == 'light' ? '#f8fafc' : '#09090b') }};
            --bg-card: {{ $vendor->theme_mode == 'light' ? '#ffffff' : '#18181b' }};
            --bg-glass: {{ $vendor->theme_mode == 'light' ? 'rgba(255, 255, 255, 0.9)' : 'rgba(24, 24, 27, 0.85)' }};
            --border-color: {{ $vendor->theme_mode == 'light' ? '#e2e8f0' : 'rgba(255, 255, 255, 0.1)' }};
            --text-main: {{ $vendor->text_color ?? ($vendor->theme_mode == 'light' ? '#0f172a' : '#f4f4f5') }};
            --text-muted: {{ $vendor->theme_mode == 'light' ? '#64748b' : '#a1a1aa' }};
        }

        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        body, button, input, select, textarea { font-family: 'Inter', -apple-system, sans-serif; }
        html { scroll-behavior: smooth; }
        body { background-color: var(--bg-main); color: var(--text-main); min-height: 100vh; padding-bottom: calc(115px + env(safe-area-inset-bottom, 0.5rem)); }

        /* Cover Header */
        .cover-header {
            height: 200px;
            background-size: cover;
            background-position: center;
            position: relative;
        }
        .cover-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.2), var(--bg-main));
        }

        /* Vendor Info Box */
        .vendor-hero {
            margin-top: -60px;
            padding: 0 1.25rem;
            position: relative;
            z-index: 10;
            display: flex;
            align-items: flex-end;
            gap: 1.25rem;
        }
        .vendor-logo {
            width: 90px;
            height: 90px;
            border-radius: 20px;
            object-fit: cover;
            border: 3px solid var(--primary);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .vendor-meta h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-main);
        }

        /* Category Nav */
        .category-nav-wrapper {
            position: sticky;
            top: 0;
            z-index: 40;
            background: var(--bg-main);
            border-bottom: none;
            padding: 0.5rem 1rem 0.75rem;
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            scrollbar-width: none;
        }
        .category-nav-wrapper::-webkit-scrollbar { display: none; }
        .cat-chip {
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.25s ease;
        }
        .cat-chip.active {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .category-section {
            scroll-margin-top: 75px;
            margin-bottom: 2rem;
        }

        /* Dish Cards */
        .dish-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            gap: 1rem;
            padding: 0.85rem;
            margin-bottom: 1rem;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            transition: transform 0.2s;
        }
        .dish-card:active { transform: scale(0.98); }
        .dish-img {
            width: 105px;
            height: 105px;
            border-radius: 12px;
            object-fit: cover;
        }
        .dish-content { flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
        .dish-title { font-family: 'Outfit', sans-serif; font-size: 1.05rem; font-weight: 700; color: var(--text-main); }
        .dish-desc { font-size: 0.8rem; color: var(--text-muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-top: 0.25rem; }
        .dish-price { font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.15rem; color: var(--accent); }

        .btn-primary {
            background: var(--primary);
            color: #ffffff;
            border: none;
            min-width: 42px;
            height: 42px;
            padding: 0 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 4px 14px rgba(245, 158, 11, 0.25);
        }
        .btn-primary:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }
        .btn-primary:active {
            transform: scale(0.92);
        }
        .btn-primary i {
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
            box-shadow: 0 6px 30px rgba(0, 0, 0, 0.5);
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
        .toast-info { border-bottom-color: #f59e0b; }
        .toast-info i { color: #f59e0b; font-size: 1.15rem; flex-shrink: 0; }
        .toast-remove { border-bottom-color: #ef4444; }
        .toast-remove i { color: #ef4444; font-size: 1.15rem; flex-shrink: 0; }
    </style>
    @include('storefront.components.desktop-frame-styles')
</head>
<body x-data="storefrontApp()" x-init="initScrollSpy()">

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


        <!-- Header Cover -->
        <div class="cover-header" style="background-image: url('{{ $vendor->cover_image ?? 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=1200&q=80' }}');">
        <div class="cover-overlay"></div>
    </div>

    <!-- Vendor Hero -->
    <div class="vendor-hero" style="display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem;">
        <div style="display: flex; align-items: flex-end; gap: 1.25rem; min-width: 0;">
            <img src="{{ $vendor->logo ?? 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=300&q=80' }}" class="vendor-logo" style="flex-shrink: 0;">
            <div class="vendor-meta" style="min-width: 0;">
                <h1 style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $vendor->name }}</h1>
                <div style="font-size: 0.85rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem; margin-top: 0.25rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    <span><i class="fa-solid fa-location-dot" style="color: var(--primary);"></i> {{ $location?->name ?? 'Main Branch' }}</span>
                    @if($table)
                        <span style="background: var(--primary); color: #ffffff; padding: 0.15rem 0.5rem; border-radius: 4px; font-weight: 700;">Table {{ $table }}</span>
                    @endif
                </div>
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

    <!-- Order Type Selector Bar (Direct Visits: Delivery vs Takeaway, or Table QR badge) -->
    @include('storefront.components.order-type-header-toggle')

    <!-- Language & Search Bar -->
    <div style="padding: 1.25rem 1.25rem 0.5rem; display: flex; gap: 0.75rem;">
        <div style="flex: 1; position: relative;">
            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
            <input type="text" x-model.debounce.250ms="search" placeholder="{{ __('menu.search_placeholder') }}" style="width: 100%; padding: 0.65rem 1rem 0.65rem 2.5rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); outline: none; font-size: 0.85rem;">
        </div>

        <select @change="changeLanguage($event.target.value)" onchange="const u = new URL(window.location.href); u.searchParams.set('lang', this.value); window.location.href = u.toString();" style="background: var(--bg-card); color: var(--text-main); border: 1px solid var(--border-color); padding: 0.5rem 0.75rem; border-radius: 12px; font-size: 0.85rem; font-weight: 700; cursor: pointer;">
            @foreach($vendor->getSupportedLanguages() as $sLang)
                <option value="{{ $sLang['code'] }}" {{ $lang == $sLang['code'] ? 'selected' : '' }}>
                    {{ $sLang['flag'] ?? '🌐' }} {{ strtoupper($sLang['code']) }}
                </option>
            @endforeach
        </select>
    </div>

    <!-- Category Tabs Navigation (Smooth Scroll + Auto Scrollspy) -->
    <nav class="category-nav-wrapper" id="categoryNavWrapper" style="margin-top: 0.5rem;">
        @foreach($categories as $cat)
            @if(is_object($cat) && isset($cat->id))
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
            @endif
        @endforeach
    </nav>

    <!-- Main Dishes List (Continuous scroll sections) -->
    <div style="padding: 1.25rem;">
        @include('storefront.components.closing-banner')
        @include('storefront.components.featured-dish-banner')

        @foreach($categories as $cat)
            @if(is_object($cat) && isset($cat->id))
                @php
                    $catDishesMeta = $cat->products->map(fn($p) => [
                        'name' => mb_strtolower($p->getTranslatedName($lang)),
                        'dine_in' => (bool)($p->available_for_dine_in ?? true),
                        'takeaway' => (bool)($p->available_for_takeaway ?? true),
                        'delivery' => (bool)($p->available_for_delivery ?? true),
                    ])->values()->all();
                @endphp
                <div id="cat-{{ $cat->id }}" class="category-section" 
                     x-show="isCategoryVisible('cat-{{ $cat->id }}', {{ json_encode($catDishesMeta, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }})">
                    <h2 style="font-family: 'Outfit'; font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-main);">
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
                                        'is_default' => (bool)$v->is_default,
                                        'secondary_price' => $vendor->formatSecondaryPrice($v->getEffectivePrice(), $lang),
                                    ])->values(),
                                ];
                            @endphp
                            <div class="dish-card" style="{{ (!$isLocAvailable || !$isTimeAvailable) ? 'opacity: 0.68;' : '' }}" 
                                 x-show='matchesSearch({!! json_encode(mb_strtolower($prod->getTranslatedName($lang))) !!}) && isProductAvailableForCurrentOrderType({{ $isDineIn ? 'true' : 'false' }}, {{ $isTakeaway ? 'true' : 'false' }}, {{ $isDelivery ? 'true' : 'false' }})' 
                                 @click='selectDish({{ json_encode($prodPayload, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }})'>
                                <img src="{{ $prod->image }}" onerror="this.onerror=null;this.src='{{ asset('images/default-dish.png') }}';" loading="lazy" decoding="async" class="dish-img" alt="{{ $prod->getTranslatedName($lang) }}">
                                <div class="dish-content">
                                    <div>
                                        <div style="display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; margin-bottom: 0.25rem;">
                                            <div class="dish-title">{{ $prod->getTranslatedName($lang) }}</div>

                                            @if(!$isLocAvailable)
                                                <span style="background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.4); padding: 0.1rem 0.45rem; border-radius: 6px; font-size: 0.65rem; font-weight: 800;">
                                                    🚫 {{ __('menu.out_of_stock') ?: 'Սպառված է' }}
                                                </span>

                                        @elseif(!$isTimeAvailable)
                                            <span style="background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.4); padding: 0.12rem 0.5rem; border-radius: 6px; font-size: 0.68rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                                                <i class="fa-regular fa-clock"></i> {{ __('menu.only_available_hours_notice', ['time' => $scheduleSummary]) }}
                                            </span>
                                        @elseif($scheduleSummary)
                                            <span style="background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.35); padding: 0.12rem 0.5rem; border-radius: 6px; font-size: 0.68rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                                                <i class="fa-regular fa-clock"></i> {{ $scheduleSummary }}
                                            </span>
                                        @endif

                                        @if(!$isDineIn || !$isTakeaway || !$isDelivery)
                                            <span style="background: rgba(99, 102, 241, 0.2); color: #a5b4fc; border: 1px solid rgba(99, 102, 241, 0.35); padding: 0.1rem 0.45rem; border-radius: 6px; font-size: 0.65rem; font-weight: 700;">
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
                                            <span style="background: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); padding: 0.1rem 0.4rem; border-radius: 6px; font-size: 0.65rem; font-weight: 700;">★ FEATURED</span>
                                        @endif
                                        @if(!empty($prod->dietary_tags))
                                            @foreach($prod->dietary_tags as $tag)
                                                <span style="background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.1rem 0.4rem; border-radius: 6px; font-size: 0.65rem; text-transform: uppercase; font-weight: 700;">{{ str_replace('_', ' ', $tag) }}</span>
                                            @endforeach
                                        @endif
                                    </div>
                                    <div class="dish-desc">{{ $prod->getTranslatedDescription($lang) }}</div>
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
                                    <div class="dish-price" style="line-height: 1.2;">
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
                                        @if($vendor->isSecondaryCurrencyActive())
                                            @php
                                                $secPriceVal = $prod->variations->count() > 1 
                                                    ? $prod->variations->min('price') 
                                                    : $prod->getEffectivePrice($location?->id);
                                                $secFormatted = $vendor->formatSecondaryPrice($secPriceVal, $lang);
                                            @endphp
                                            @if($secFormatted)
                                                <div class="secondary-price" style="font-size: 0.76rem; font-weight: 600; color: var(--text-muted); opacity: 0.85; margin-top: 0.15rem; line-height: 1.1;">
                                                    {{ $secFormatted }}
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                    
                                    @if(!$isLocAvailable)
                                        <button class="btn btn-secondary" disabled style="opacity: 0.6; cursor: not-allowed; font-size: 0.75rem; padding: 0.4rem 0.65rem;" title="{{ __('menu.out_of_stock') ?: 'Սպառված է' }}">
                                            <span>{{ __('menu.out_of_stock') ?: 'Սպառված է' }}</span>
                                        </button>
                                    @elseif(!$isTimeAvailable)
                                        <button class="btn btn-secondary" disabled style="opacity: 0.85; cursor: not-allowed; font-size: 0.72rem; padding: 0.4rem 0.65rem; background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; gap: 0.3rem;" title="{{ __('menu.only_available_hours_notice', ['time' => $scheduleSummary]) }}">
                                            <i class="fa-regular fa-clock"></i>
                                            <span>{{ __('menu.only_available_hours_notice', ['time' => $scheduleSummary]) }}</span>
                                        </button>
                                    @else
                                        <button class="btn btn-primary" @click.stop='selectDish({{ json_encode($prodPayload, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }})' aria-label="{{ __('menu.add_to_cart') }}" title="{{ __('menu.add_to_cart') }}">
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
            @endif
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
