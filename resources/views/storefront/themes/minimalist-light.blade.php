<!DOCTYPE html>
<html lang="{{ $lang }}" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="{{ $vendor->primary_color ?? '#0f172a' }}">
    <link rel="manifest" href="{{ route('client.manifest', ['vendor_slug' => $vendor->slug]) }}">
    <title>{{ $vendor->name }} - {{ __('menu.digital_menu') }}</title>

    <!-- Dynamic Favicon & Icons -->
    <link rel="icon" type="image/png" href="{{ $vendor->logo ?: \App\Models\SystemSetting::getFavicon() }}">
    <link rel="apple-touch-icon" href="{{ $vendor->logo ?: \App\Models\SystemSetting::getFavicon() }}">

    <!-- Google Fonts: Outfit & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>

    <style>
        :root {
            --primary: {{ $vendor->primary_color ?? '#0f172a' }};
            --accent: {{ $vendor->accent_color ?? '#f59e0b' }};
            --secondary: {{ $vendor->secondary_color ?? '#3b82f6' }};
            --bg-main: {{ $vendor->bg_color ?? '#fbfbfa' }};
            --bg-card: #ffffff;
            --text-main: {{ $vendor->text_color ?? '#0f172a' }};
            --text-muted: #64748b;
            --border-color: #f1f5f9;
            --border-subtle: #e2e8f0;
            --shadow-card: 0 4px 18px -2px rgba(15, 23, 42, 0.04), 0 2px 6px -1px rgba(15, 23, 42, 0.02);
            --shadow-float: 0 12px 30px -4px rgba(15, 23, 42, 0.08), 0 4px 12px -2px rgba(15, 23, 42, 0.04);
            --radius-md: 14px;
            --radius-lg: 20px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            padding-bottom: 95px;
            overflow-x: hidden;
            letter-spacing: -0.01em;
            line-height: 1.5;
        }

        /* Hero / Header Container */
        .header-wrap {
            position: relative;
            background: #ffffff;
            border-bottom: 1px solid var(--border-subtle);
        }

        .cover-photo {
            width: 100%;
            height: 170px;
            object-fit: cover;
            background-color: #f1f5f9;
        }

        .cover-placeholder {
            width: 100%;
            height: 110px;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }

        .vendor-profile {
            padding: 0 1.25rem 1.25rem;
            display: flex;
            align-items: flex-end;
            gap: 1rem;
            position: relative;
            margin-top: -36px;
        }

        .vendor-logo {
            width: 76px;
            height: 76px;
            border-radius: 18px;
            object-fit: cover;
            background: #ffffff;
            border: 3px solid #ffffff;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
            flex-shrink: 0;
        }

        .vendor-info {
            flex: 1;
            min-width: 0;
            padding-bottom: 0.15rem;
        }

        .vendor-name {
            font-family: 'Outfit', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .vendor-subline {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.35rem;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.15rem 0.55rem;
            border-radius: 9999px;
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #10b981;
        }

        /* Search & Controls Bar */
        .controls-bar {
            padding: 0.85rem 1.25rem 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        .search-box {
            flex: 1;
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.85rem;
            pointer-events: none;
        }

        .search-input {
            width: 100%;
            padding: 0.65rem 1rem 0.65rem 2.4rem;
            background: #ffffff;
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            color: var(--text-main);
            font-size: 0.86rem;
            font-family: inherit;
            outline: none;
            transition: all 0.2s ease;
        }

        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.06);
        }

        .lang-select {
            background: #ffffff;
            color: var(--text-main);
            border: 1px solid var(--border-subtle);
            padding: 0.62rem 0.75rem;
            border-radius: 12px;
            font-size: 0.82rem;
            font-weight: 700;
            outline: none;
            cursor: pointer;
            transition: border-color 0.2s;
        }

        .lang-select:focus {
            border-color: var(--primary);
        }

        /* Minimalist Sticky Category Nav */
        .category-nav-wrap {
            position: sticky;
            top: 0;
            z-index: 40;
            background: rgba(251, 251, 250, 0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-subtle);
            padding: 0.65rem 0;
        }

        .category-scroll {
            display: flex;
            gap: 0.45rem;
            overflow-x: auto;
            padding: 0 1.25rem;
            scrollbar-width: none;
        }

        .category-scroll::-webkit-scrollbar {
            display: none;
        }

        .cat-chip {
            padding: 0.45rem 0.95rem;
            border-radius: 10px;
            font-size: 0.83rem;
            font-weight: 600;
            white-space: nowrap;
            background: transparent;
            color: var(--text-muted);
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .cat-chip.active {
            background: #ffffff;
            color: var(--text-main);
            font-weight: 700;
            border-color: var(--border-subtle);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        /* Dishes Section */
        .menu-container {
            padding: 1.25rem;
            max-width: 900px;
            margin: 0 auto;
        }

        .category-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-main);
            margin: 1.85rem 0 0.85rem 0;
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .category-title:first-child {
            margin-top: 0.5rem;
        }

        .dishes-grid {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }

        /* Minimalist Dish Card */
        .dish-card {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 1rem;
            display: flex;
            gap: 1rem;
            box-shadow: var(--shadow-card);
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            position: relative;
            cursor: pointer;
        }

        .dish-card:hover {
            border-color: #cbd5e1;
            box-shadow: var(--shadow-float);
        }

        .dish-card.dish-hidden {
            display: none !important;
        }

        .dish-img-wrap {
            width: 105px;
            height: 105px;
            border-radius: 12px;
            overflow: hidden;
            flex-shrink: 0;
            background: #f1f5f9;
            position: relative;
        }

        .dish-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .dish-card:hover .dish-img {
            transform: scale(1.05);
        }

        .dish-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-width: 0;
        }

        .dish-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.3;
            margin-bottom: 0.25rem;
        }

        .dish-desc {
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .dish-meta-pills {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-bottom: 0.5rem;
        }

        .meta-pill {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.15rem 0.45rem;
            border-radius: 6px;
            background: #f8fafc;
            color: #64748b;
            border: 1px solid var(--border-color);
        }

        .dish-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: auto;
        }

        .price-text {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.05rem;
            color: var(--text-main);
        }

        .btn-add {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #ffffff;
            color: var(--text-main);
            border: 1px solid var(--border-subtle);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.15s ease;
        }

        .btn-add:hover {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }

        .btn-add:active {
            transform: scale(0.92);
        }

        .btn-portion-select {
            padding: 0.35rem 0.75rem;
            border-radius: 10px;
            background: #ffffff;
            color: var(--text-main);
            border: 1px solid var(--border-subtle);
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.15s ease;
        }

        .btn-portion-select:hover {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }

        .category-anchor {
            scroll-margin-top: 65px;
        }
    </style>
    @include('storefront.components.desktop-frame-styles')
    @if(!empty($vendor->sanitized_custom_css))
        <style>{!! $vendor->sanitized_custom_css !!}</style>
    @endif
</head>
<body x-data="createStorefrontApp({
    activeCat: 'cat-{{ $categories->first()?->id ?? 1 }}',
    tableNumber: '{{ !empty($table) ? $table : '' }}'
})" x-init="initApp()">

    <!-- Storefront Desktop Max-Width App Shell -->
    <div class="storefront-app-shell">

    <!-- Top Header -->
    <header class="header-wrap">
        @if(!empty($vendor->cover_image))
            <img src="{{ $vendor->cover_image }}" alt="Cover" class="cover-photo">
        @else
            <div class="cover-placeholder"></div>
        @endif

        <div class="vendor-profile" style="display: flex; align-items: center; justify-content: space-between; gap: 0.85rem;">
            <div style="display: flex; align-items: center; gap: 0.85rem; min-width: 0;">
                @if(!empty($vendor->logo))
                    <img src="{{ $vendor->logo }}" alt="{{ $vendor->name }}" class="vendor-logo" style="flex-shrink: 0;">
                @else
                    <div class="vendor-logo" style="display: flex; align-items: center; justify-content: center; font-size: 1.75rem; color: var(--text-muted); background: #f8fafc; flex-shrink: 0;">
                        <i class="fa-solid fa-utensils"></i>
                    </div>
                @endif

                <div class="vendor-info" style="min-width: 0; flex: 1;">
                    <h1 class="vendor-name" style="word-break: break-word;">{{ $vendor->name }}</h1>
                    <div class="vendor-subline" @click="showInfoModal = true" style="cursor: pointer; flex-wrap: wrap;" title="{{ __('menu.restaurant_info') }}">
                        <span class="status-pill">
                            <span class="status-dot"></span>
                            {{ __('menu.open') ?? 'Բաց է' }}
                        </span>
                        @if($location)
                            <span><i class="fa-solid fa-location-dot" style="font-size: 0.7rem; color: var(--text-muted);"></i> {{ $location->name }}</span>
                        @endif
                        @if(!empty($table))
                            <span style="font-weight: 700; color: var(--primary);">{{ __('Սեղան') }} #{{ $table }}</span>
                        @endif
                        <span style="color: var(--primary); font-weight: 600;"><i class="fa-solid fa-circle-info" style="font-size: 0.78rem;"></i> {{ __('menu.nav_info') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Order Type Selector Bar (Direct Visits: Delivery vs Takeaway, or Table QR badge) -->
    @include('storefront.components.order-type-header-toggle')

    <!-- Search & Language Bar -->
    <div class="controls-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" x-model.debounce.250ms="search" placeholder="{{ __('menu.search_placeholder') }}" class="search-input">
        </div>

        <!-- Info Button (moved here so it never obstructs or crowds the restaurant name) -->
        <button type="button" 
                class="header-info-pill-btn" 
                @click="showInfoModal = true" 
                title="{{ __('menu.nav_info') }}"
                aria-label="{{ __('menu.nav_info') }}">
            <i class="fa-solid fa-circle-info"></i>
            <span class="info-btn-text">{{ __('menu.nav_info') }}</span>
        </button>

        <select @change="changeLanguage($event.target.value)" onchange="const u = new URL(window.location.href); u.searchParams.set('lang', this.value); window.location.href = u.toString();" class="lang-select" aria-label="Language selection" style="height: 38px;">
            @foreach($vendor->getSupportedLanguages() as $sLang)
                <option value="{{ $sLang['code'] }}" {{ $lang == $sLang['code'] ? 'selected' : '' }}>
                    {{ $sLang['flag'] ?? '🌐' }} {{ strtoupper($sLang['code']) }}
                </option>
            @endforeach
        </select>
    </div>

    <!-- Category Nav Chips -->
    <div class="category-nav-wrap">
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
                <button id="chip-cat-{{ $cat->id }}" 
                        class="cat-chip" 
                        :class="{ 'active': activeCat === 'cat-{{ $cat->id }}' }" 
                        x-show="isCategoryVisible('cat-{{ $cat->id }}', {{ json_encode($catDishesMeta, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }})"
                        @click="scrollToCat('cat-{{ $cat->id }}')">
                    {{ $cat->getTranslatedName($lang) }}
                </button>
            @endforeach
        </nav>
    </div>

    <!-- Menu Content -->
    <main class="menu-container">
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
            <div id="cat-{{ $cat->id }}" class="category-anchor" x-show="isCategoryVisible('cat-{{ $cat->id }}', {{ json_encode($catDishesMeta, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }})">
                <div class="category-title">
                    <span>{{ $cat->getTranslatedName($lang) }}</span>
                    <span style="font-size: 0.78rem; font-weight: 600; color: var(--text-muted);">{{ $cat->products->count() }}</span>
                </div>

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
                                'description' => $prod->getTranslatedDescription($lang),
                                'price' => $prod->getEffectivePrice($location?->id),
                                'regular_price' => $prod->getRegularPrice($location?->id),
                                'is_discount' => $prod->isDiscountActive(),
                                'image' => $prod->image,
                                'calories' => $prod->calories,
                                'is_available' => $isLocAvailable,
                                'is_time_available' => $isTimeAvailable,
                                'schedule_summary' => $scheduleSummary,
                                'available_for_dine_in' => $isDineIn,
                                'available_for_takeaway' => $isTakeaway,
                                'available_for_delivery' => $isDelivery,
                                'dietary_tags' => $prod->dietary_tags ?? [],
                                'allergens' => $prod->allergens->map(fn($a) => ['icon' => $a->icon, 'name' => $a->name])->toArray(),
                                'variations' => $prod->variations->map(fn($v) => [
                                    'id' => $v->id,
                                    'name' => $v->name,
                                    'price' => (float)$v->price,
                                    'secondary_price' => $vendor->formatSecondaryPrice($v->getEffectivePrice(), $lang),
                                ])->toArray()
                            ];
                        @endphp

                        <div class="dish-card" 
                             style="{{ (!$isLocAvailable || !$isTimeAvailable) ? 'opacity: 0.65;' : '' }}"
                             x-show="matchesSearch({!! json_encode(mb_strtolower($prod->getTranslatedName($lang))) !!}) && isProductAvailableForCurrentOrderType({{ $isDineIn ? 'true' : 'false' }}, {{ $isTakeaway ? 'true' : 'false' }}, {{ $isDelivery ? 'true' : 'false' }})"
                             :class="{ 'dish-hidden': !isDishVisible({{ $prod->id }}, '{{ addslashes(mb_strtolower($prod->getTranslatedName($lang))) }}', {{ $isDineIn ? 'true' : 'false' }}, {{ $isTakeaway ? 'true' : 'false' }}, {{ $isDelivery ? 'true' : 'false' }}) }"
                             @click="selectDish({{ json_encode($prodPayload, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }})">


                            @if(!empty($prod->image))
                                <div class="dish-img-wrap">
                                    <img src="{{ $prod->image }}" alt="{{ $prod->getTranslatedName($lang) }}" class="dish-img" loading="lazy" decoding="async">
                                    @if($prod->isDiscountActive())
                                        <span style="position: absolute; top: 6px; left: 6px; background: #ef4444; color: #ffffff; font-size: 0.65rem; font-weight: 800; padding: 2px 6px; border-radius: 4px;">
                                            SALE
                                        </span>
                                    @endif
                                </div>
                            @endif

                            <div class="dish-content">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; margin-bottom: 0.25rem;">
                                        <h3 class="dish-title" style="margin-bottom: 0;">{{ $prod->getTranslatedName($lang) }}</h3>

                                        @if(!$isLocAvailable)
                                            <span style="background: rgba(239, 68, 68, 0.15); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3); padding: 0.1rem 0.45rem; border-radius: 4px; font-size: 0.65rem; font-weight: 800;">
                                                🚫 {{ __('menu.out_of_stock') ?: 'Սպառված է' }}
                                            </span>
                                        @elseif(!$isTimeAvailable)
                                            <span style="background: rgba(245, 158, 11, 0.15); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); padding: 0.12rem 0.5rem; border-radius: 4px; font-size: 0.68rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                                                <i class="fa-regular fa-clock"></i> {{ __('menu.only_available_hours_notice', ['time' => $scheduleSummary]) }}
                                            </span>
                                        @elseif($scheduleSummary)
                                            <span style="background: rgba(16, 185, 129, 0.12); color: #059669; border: 1px solid rgba(16, 185, 129, 0.25); padding: 0.12rem 0.5rem; border-radius: 4px; font-size: 0.68rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                                                <i class="fa-regular fa-clock"></i> {{ $scheduleSummary }}
                                            </span>
                                        @endif

                                        @if(!$isDineIn || !$isTakeaway || !$isDelivery)
                                            <span style="background: rgba(99, 102, 241, 0.12); color: #4f46e5; border: 1px solid rgba(99, 102, 241, 0.25); padding: 0.1rem 0.45rem; border-radius: 4px; font-size: 0.65rem; font-weight: 700;">
                                                @if($isDelivery && !$isDineIn && !$isTakeaway)
                                                    🛵 {{ __('menu.delivery_only') ?: 'Միայն առաքում' }}
                                                @elseif($isDineIn && !$isDelivery && !$isTakeaway)
                                                    🍽️ {{ __('menu.dine_in_only') ?: 'Միայն սրահում' }}
                                                @elseif($isTakeaway && !$isDelivery && !$isDineIn)
                                                    🥡 {{ __('menu.takeaway_only') ?: 'Միայն տանելու' }}
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                    @if(!empty($prod->getTranslatedDescription($lang)))
                                        <p class="dish-desc">{{ $prod->getTranslatedDescription($lang) }}</p>
                                    @endif

                                    <div class="dish-meta-pills">
                                        @if($prod->calories)
                                            <span class="meta-pill"><i class="fa-solid fa-fire-flame-curved" style="font-size: 0.65rem; color: #f59e0b;"></i> {{ $prod->calories }} kcal</span>
                                        @endif
                                        @if(!empty($prod->dietary_tags) && is_array($prod->dietary_tags))
                                            @foreach($prod->dietary_tags as $tag)
                                                <span class="meta-pill">{{ $tag }}</span>
                                            @endforeach
                                        @endif
                                        @if($prod->allergens->isNotEmpty())
                                            <span class="meta-pill" title="Allergens">
                                                {{ $prod->allergens->pluck('icon')->join(' ') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="dish-footer">
                                    <div class="price-text" style="line-height: 1.2;">
                                        @if($prod->isDiscountActive())
                                            <div style="display: flex; align-items: baseline; gap: 0.35rem;">
                                                <span style="color: #ef4444;">{{ number_format($prod->getEffectivePrice($location?->id)) }} {{ $vendor->currency }}</span>
                                                <span style="font-size: 0.75rem; text-decoration: line-through; color: var(--text-muted); font-weight: 500;">
                                                    {{ number_format($prod->getRegularPrice($location?->id)) }}
                                                </span>
                                            </div>
                                        @elseif($prod->variations->count() > 1)
                                            <span style="font-size: 0.72rem; font-weight: 500; color: var(--text-muted); padding-right: 0.2rem;">{{ __('menu.starting_from') }}</span>
                                            <span>{{ number_format($prod->variations->min('price')) }} {{ $vendor->currency }}</span>
                                        @else
                                            <span>{{ number_format($prod->getEffectivePrice($location?->id)) }} {{ $vendor->currency }}</span>
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
                                        <span style="font-size: 0.75rem; font-weight: 700; color: #dc2626; padding: 0.35rem 0.5rem; background: #fee2e2; border-radius: 6px;">
                                            {{ __('menu.out_of_stock') ?: 'Սպառված է' }}
                                        </span>
                                    @elseif(!$isTimeAvailable)
                                        <span style="font-size: 0.72rem; font-weight: 700; color: #d97706; padding: 0.35rem 0.5rem; background: #fef3c7; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.3rem;">
                                            <i class="fa-regular fa-clock"></i>
                                            <span>{{ __('menu.only_available_hours_notice', ['time' => $scheduleSummary]) }}</span>
                                        </span>
                                    @elseif($prod->variations->count() > 1)
                                        <button class="btn-portion-select" 
                                                @click.stop='selectDish({{ json_encode($prodPayload, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }})'>
                                            {{ __('menu.select_portion_btn') }}
                                        </button>
                                    @else
                                        <button class="btn-add" 
                                                @click.stop='selectDish({{ json_encode($prodPayload, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }})'
                                                aria-label="{{ __('menu.add_to_cart') }}">
                                            <i class="fa-solid fa-plus"></i>
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
        <footer style="text-align: center; padding: 2rem 1rem 3rem; margin-top: 2rem; border-top: 1px dashed var(--border-subtle); color: var(--text-muted); font-size: 0.78rem;">
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
    </main>
    </div> <!-- /.storefront-app-shell -->

    <!-- Order Type Selection Modal (Direct Visitors) -->
    @include('storefront.components.order-type-modal')

    <!-- Variation Selection Modal -->
    @include('storefront.components.variation-modal')

    <!-- Modern Cart & Checkout Modal -->
    @include('storefront.components.cart-modal')

    <!-- AI Waiter Components -->
    @include('storefront.components.welcome-ai-waiter-modal')
    @include('storefront.components.fullscreen-ai-waiter')

    <!-- Navigation & Feedback Modals -->
    @include('storefront.components.bottom-nav')
    @include('storefront.components.info-modal')
    @include('storefront.components.order-tracker-modal')
    @include('storefront.components.waiter-modal')
    @include('storefront.components.storefront-scripts')
</body>
</html>
