@php
    $themeMode = $vendor->theme_mode ?? null;
    $templateSlug = $vendor->menuTemplate?->slug ?? '';
    
    // Determine whether default mode is dark or light
    if ($themeMode === 'dark') {
        $isDarkNav = true;
    } elseif ($themeMode === 'light') {
        $isDarkNav = false;
    } elseif ($templateSlug === 'luxury-dark') {
        $isDarkNav = true;
    } else {
        $isDarkNav = false;
    }

    // If custom background color is specified, dynamically evaluate perceived luminance
    if (!empty($vendor->bg_color)) {
        $rawHex = ltrim($vendor->bg_color, '#');
        if (strlen($rawHex) === 3) {
            $rawHex = $rawHex[0].$rawHex[0].$rawHex[1].$rawHex[1].$rawHex[2].$rawHex[2];
        }
        if (strlen($rawHex) === 6 && ctype_xdigit($rawHex)) {
            $r = hexdec(substr($rawHex, 0, 2));
            $g = hexdec(substr($rawHex, 2, 2));
            $b = hexdec(substr($rawHex, 4, 2));
            $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b);
            $isDarkNav = ($luminance < 140);
        }
    }
    $currencySymbol = (!$vendor->currency || strtoupper($vendor->currency) === 'AMD') ? '֏' : $vendor->currency;
@endphp

<!-- Pre-Order Floating Top Banner (Shown when customer is choosing dishes from main menu for table reservation) -->
<div class="storefront-preorder-top-banner" 
     x-show="isPreOrderMode" 
     x-cloak
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 -translate-y-full"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 -translate-y-full">
    <div class="preorder-top-banner-inner">
        <div class="preorder-top-banner-left">
            <span class="preorder-live-dot"></span>
            <div class="preorder-top-banner-texts">
                <div class="preorder-top-banner-title">
                    <i class="fa-solid fa-calendar-days" style="color: var(--primary);"></i>
                    <span>{{ __('menu.preorder_for_reservation') }}</span>
                </div>
                <div class="preorder-top-banner-desc" x-text="preOrderSummaryText || '{{ __('menu.select_dishes_for_arrival') }}'"></div>
            </div>
        </div>
        <div class="preorder-top-banner-right">
            <button type="button" @click="returnToReservation()" class="preorder-return-btn">
                <span>{{ __('menu.return_to_reservation') }}</span>
                <span class="preorder-cart-badge" x-show="cartTotalCount > 0" x-text="cartTotalCount"></span>
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </div>
    </div>
</div>

<!-- Mobile Bottom Navigation Bar & Persistent Trackers -->
<div class="storefront-bottom-nav-container">
    <!-- Active Order Tracker Floating Pill (Shown when customer has an active order) -->
    <template x-if="isOrderPillVisible()">
        <div class="active-order-floating-pill" 
             @click="showOrderTracker = true"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 scale-95">
            <div class="order-pill-left">
                <span class="live-pulse-dot"></span>
                <i :class="activeOrder.status_icon || 'fa-solid fa-clock'" class="order-pill-icon"></i>
                <div class="order-pill-info">
                    <div class="order-pill-title">
                        <span>{{ __('menu.order_number_label') }}<strong x-text="activeOrder.order_number"></strong></span>
                        <span class="order-pill-badge" x-text="activeOrder.status_label"></span>
                    </div>
                    <div class="order-pill-desc" style="display: flex; align-items: center; gap: 0.4rem;">
                        <span x-text="activeOrder.status_desc"></span>
                        <template x-if="activeOrder.total_amount">
                            <span style="font-weight: 700; color: var(--text-main); white-space: nowrap;" x-text="'• ' + Number(activeOrder.total_amount).toLocaleString() + ' ' + (activeOrder.currency || '{{ $currencySymbol }}')"></span>
                        </template>
                    </div>
                </div>
            </div>
            <div class="order-pill-actions" style="display: flex; align-items: center; gap: 0.4rem;">
                <button type="button" class="order-pill-open-btn" title="{{ __('menu.track_order_btn') }}" @click.stop="showOrderTracker = true">
                    <i class="fa-solid fa-chevron-up"></i>
                </button>
                <template x-if="['completed', 'cancelled'].includes(activeOrder.status)">
                    <button type="button" class="order-pill-close-btn" @click.stop="dismissTrackerPill()" title="{{ __('menu.close') }}" aria-label="{{ __('menu.close') }}">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </template>
            </div>
        </div>
    </template>

    @if($vendor->ai_waiter_enabled)
    <!-- AI Waiter Floating Launcher Bubble -->
    <div class="ai-waiter-floating-bubble" 
         :class="{ 'has-active-order-pill': isOrderPillVisible() }"
         x-show="!showAiWaiter && !showWelcomeModal && !showCartModal" 
         @click="openAiWaiter()"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4 scale-90"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 scale-90"
         title="{{ $vendor->getAiWaiterName() }}">
        <div class="ai-bubble-avatar">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
        </div>
        <div class="ai-bubble-content">
            <span class="ai-bubble-title">{{ $vendor->getAiWaiterName() }}</span>
            <span class="ai-bubble-desc">{{ __('menu.ai_help_choose') }}</span>
        </div>
    </div>
    @endif

    <!-- Floating Scroll-To-Top Button (iOS Glass) -->
    <button type="button" 
            class="storefront-scroll-top-btn {{ $isDarkNav ? 'btn-dark' : 'btn-light' }}" 
            :class="{ 
                'has-ai-bubble': {{ $vendor->ai_waiter_enabled ? 'true' : 'false' }} && !showAiWaiter && !showWelcomeModal && !showCartModal,
                'has-active-order-pill': isOrderPillVisible()
            }" 
            x-show="showScrollTop" 
            @click="scrollToTop()" 
            x-transition:enter="transition ease-out duration-300" 
            x-transition:enter-start="opacity-0 translate-y-4 scale-90" 
            x-transition:enter-end="opacity-100 translate-y-0 scale-100" 
            x-transition:leave="transition ease-in duration-200" 
            x-transition:leave-start="opacity-100 translate-y-0 scale-100" 
            x-transition:leave-end="opacity-0 translate-y-4 scale-90" 
            title="{{ __('menu.scroll_to_top') }}" 
            aria-label="{{ __('menu.scroll_to_top') }}">
        <i class="fa-solid fa-arrow-up"></i>
    </button>

    <!-- Bottom Navigation Bar (iOS Floating Island Pill) -->
    <nav class="storefront-bottom-nav {{ $isDarkNav ? 'nav-dark' : 'nav-light' }}" aria-label="Storefront Mobile Navigation">
        <!-- 1. Home / Menu -->
        <button type="button" 
                class="bottom-nav-item" 
                :class="{ 'active': currentTab === 'menu' && !showCartModal && !showOrderTracker && !showAiWaiter }"
                @click="goToMenu()">
            <div class="nav-icon-wrapper">
                <i class="fa-solid fa-utensils"></i>
            </div>
            <span class="nav-label">{{ __('menu.nav_home') }}</span>
        </button>

        @if($vendor->ai_waiter_enabled)
        <!-- 2. AI Waiter Advisor -->
        <button type="button" 
                class="bottom-nav-item bottom-nav-ai" 
                :class="{ 'active': showAiWaiter }"
                @click="openAiWaiter()"
                title="{{ $vendor->getAiWaiterName() }}">
            <div class="nav-icon-wrapper" style="position: relative;">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
            </div>
            <span class="nav-label">{{ __('menu.ai_waiter') }}</span>
        </button>
        @endif

        @if(!empty($table))
        <!-- 3. Call Waiter (Only displayed when scanned from a table QR) -->
        <button type="button" 
                class="bottom-nav-item bottom-nav-waiter"
                :class="{ 'active': showWaiterModal }"
                @click="openWaiterModal('call_waiter')">
            <div class="nav-icon-wrapper">
                <i class="fa-solid fa-bell"></i>
            </div>
            <span class="nav-label">{{ __('menu.nav_waiter') }}</span>
        </button>
        @endif

        <!-- 4. Cart -->
        <button type="button" 
                class="bottom-nav-item bottom-nav-cart" 
                :class="{ 'active': showCartModal, 'has-items': cartTotalCount > 0, 'cart-bump': cartBump }"
                @click="showCartModal = true"
                title="{{ __('menu.view_cart') }}"
                aria-label="{{ __('menu.view_cart') }}">
            <div class="nav-icon-wrapper">
                <i class="fa-solid fa-basket-shopping"></i>
                <span class="nav-badge" x-show="cartTotalCount > 0" x-text="cartTotalCount" x-transition.scale></span>
            </div>
            <span class="nav-label">{{ __('menu.nav_cart') }}</span>
            <span style="position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border-width: 0;">{{ __('menu.view_cart') }}</span>
        </button>

        @if($vendor->isReservationFeatureEnabled())
        <!-- 5. Table Reservation -->
        <button type="button" 
                class="bottom-nav-item bottom-nav-reserve" 
                :class="{ 'active': isPreOrderMode }"
                @click="goToReservationPage()"
                title="{{ __('Reserve a Table') }}"
                aria-label="{{ __('Reserve a Table') }}">
            <div class="nav-icon-wrapper">
                <i class="fa-solid fa-calendar-days"></i>
                <span class="nav-badge" x-show="isPreOrderMode && cartTotalCount > 0" x-text="cartTotalCount" x-transition.scale></span>
            </div>
            <span class="nav-label">{{ __('Reserve') }}</span>
        </button>
        @endif
    </nav>
</div>

<style>
    .storefront-bottom-nav-container {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 80;
        pointer-events: none;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    /* iOS Floating Island Pill Bar */
    .storefront-bottom-nav {
        pointer-events: auto;
        backdrop-filter: blur(28px) saturate(190%);
        -webkit-backdrop-filter: blur(28px) saturate(190%);
        border-radius: 9999px;
        display: flex;
        justify-content: space-around;
        align-items: center;
        gap: 4px;
        padding: 6px 8px;
        margin: 0 auto 2px auto;
        width: calc(100% - 28px);
        max-width: 440px;
        box-sizing: border-box;
        transition: background 0.25s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.25s ease, box-shadow 0.25s ease;
    }

    /* Light Theme (Սպիտակ պանել բաց ֆոնի դեպքում) */
    .storefront-bottom-nav,
    .storefront-bottom-nav.nav-light,
    html.light .storefront-bottom-nav,
    [data-theme="light"] .storefront-bottom-nav {
        background: rgba(255, 255, 255, 0.94);
        border: 1px solid rgba(0, 0, 0, 0.08);
        box-shadow: 0 16px 36px -4px rgba(0, 0, 0, 0.12), 0 4px 12px rgba(0, 0, 0, 0.04), 0 0 0 1px rgba(255, 255, 255, 0.9) inset;
    }

    /* Dark Theme (Մուգ պանել մուգ ֆոնի դեպքում) */
    .storefront-bottom-nav.nav-dark,
    html.dark .storefront-bottom-nav,
    [data-theme="dark"] .storefront-bottom-nav {
        background: rgba(26, 26, 29, 0.88);
        border: 1px solid rgba(255, 255, 255, 0.14);
        box-shadow: 0 16px 38px -4px rgba(0, 0, 0, 0.42), 0 0 0 1px rgba(255, 255, 255, 0.08) inset;
    }

    .bottom-nav-item {
        pointer-events: auto;
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: transparent;
        border: none;
        border-radius: 9999px;
        padding: 6px 8px;
        cursor: pointer;
        transition: all 0.22s cubic-bezier(0.2, 0, 0, 1);
        position: relative;
        text-decoration: none;
        -webkit-tap-highlight-color: transparent;
        user-select: none;
    }

    .bottom-nav-item:active {
        transform: scale(0.92);
    }

    /* Light Theme Nav Item Colors */
    .bottom-nav-item,
    .storefront-bottom-nav.nav-light .bottom-nav-item,
    html.light .bottom-nav-item,
    [data-theme="light"] .bottom-nav-item {
        color: #64748b;
    }

    .bottom-nav-item:hover,
    .storefront-bottom-nav.nav-light .bottom-nav-item:hover,
    html.light .bottom-nav-item:hover,
    [data-theme="light"] .bottom-nav-item:hover {
        color: var(--text-main, #0f172a);
    }

    .bottom-nav-item.active,
    .storefront-bottom-nav.nav-light .bottom-nav-item.active,
    html.light .bottom-nav-item.active,
    [data-theme="light"] .bottom-nav-item.active {
        background: rgba(0, 0, 0, 0.06);
        color: var(--primary, #e11d48);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }

    .bottom-nav-item.has-items,
    .storefront-bottom-nav.nav-light .bottom-nav-item.has-items,
    html.light .bottom-nav-item.has-items,
    [data-theme="light"] .bottom-nav-item.has-items {
        color: var(--text-main, #0f172a);
    }

    .bottom-nav-item.has-items.active,
    .storefront-bottom-nav.nav-light .bottom-nav-item.has-items.active,
    html.light .bottom-nav-item.has-items.active,
    [data-theme="light"] .bottom-nav-item.has-items.active {
        color: var(--primary, #e11d48);
    }

    /* Dark Theme Nav Item Colors */
    .storefront-bottom-nav.nav-dark .bottom-nav-item,
    html.dark .bottom-nav-item,
    [data-theme="dark"] .bottom-nav-item {
        color: rgba(255, 255, 255, 0.65);
    }

    .storefront-bottom-nav.nav-dark .bottom-nav-item:hover,
    html.dark .bottom-nav-item:hover,
    [data-theme="dark"] .bottom-nav-item:hover {
        color: #ffffff;
    }

    .storefront-bottom-nav.nav-dark .bottom-nav-item.active,
    html.dark .bottom-nav-item.active,
    [data-theme="dark"] .bottom-nav-item.active {
        background: rgba(255, 255, 255, 0.14);
        color: var(--primary, #38bdf8);
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.22);
    }

    .storefront-bottom-nav.nav-dark .bottom-nav-item.has-items,
    html.dark .bottom-nav-item.has-items,
    [data-theme="dark"] .bottom-nav-item.has-items {
        color: rgba(255, 255, 255, 0.95);
    }

    .storefront-bottom-nav.nav-dark .bottom-nav-item.has-items.active,
    html.dark .bottom-nav-item.has-items.active,
    [data-theme="dark"] .bottom-nav-item.has-items.active {
        color: var(--primary, #38bdf8);
    }

    .bottom-nav-item .nav-icon-wrapper {
        position: relative;
        font-size: 1.25rem;
        height: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 2px;
        transition: transform 0.2s cubic-bezier(0.2, 0, 0, 1);
    }

    .bottom-nav-item.active .nav-icon-wrapper {
        transform: translateY(-1px);
        color: var(--primary, #e11d48);
    }

    .storefront-bottom-nav.nav-dark .bottom-nav-item.active .nav-icon-wrapper,
    html.dark .bottom-nav-item.active .nav-icon-wrapper,
    [data-theme="dark"] .bottom-nav-item.active .nav-icon-wrapper {
        transform: translateY(-1px);
        color: var(--primary, #38bdf8);
    }

    .bottom-nav-item .nav-label {
        font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "SF Pro Display", "Segoe UI", Roboto, sans-serif;
        font-size: 0.68rem;
        font-weight: 600;
        line-height: 1.1;
        letter-spacing: -0.01em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }

    .bottom-nav-item.active .nav-label {
        font-weight: 700;
        color: var(--primary, #e11d48);
    }

    .storefront-bottom-nav.nav-dark .bottom-nav-item.active .nav-label,
    html.dark .bottom-nav-item.active .nav-label,
    [data-theme="dark"] .bottom-nav-item.active .nav-label {
        font-weight: 700;
        color: var(--primary, #38bdf8);
    }

    .nav-badge {
        position: absolute;
        top: -5px;
        right: -8px;
        background: #ef4444;
        color: #ffffff;
        font-size: 0.66rem;
        font-weight: 800;
        min-width: 18px;
        height: 18px;
        border-radius: 9999px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 4px;
        animation: badgePulse 2s infinite;
        z-index: 5;
    }

    /* Light Theme Nav Badge */
    .nav-badge,
    .storefront-bottom-nav.nav-light .nav-badge,
    html.light .nav-badge,
    [data-theme="light"] .nav-badge {
        border: 2px solid #ffffff;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.35);
    }

    /* Dark Theme Nav Badge */
    .storefront-bottom-nav.nav-dark .nav-badge,
    html.dark .nav-badge,
    [data-theme="dark"] .nav-badge {
        border: 2px solid #1a1a1d;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.45);
    }

    /* Floating Scroll Up Button (iOS style) */
    .storefront-scroll-top-btn {
        pointer-events: auto;
        position: fixed;
        bottom: 70px;
        right: 1.25rem;
        z-index: 76;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        backdrop-filter: blur(28px) saturate(190%);
        -webkit-backdrop-filter: blur(28px) saturate(190%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
        cursor: pointer;
        transition: bottom 0.3s cubic-bezier(0.16, 1, 0.3, 1), transform 0.2s cubic-bezier(0.2, 0, 0, 1), border-color 0.2s, color 0.2s, background 0.25s, box-shadow 0.25s;
        -webkit-tap-highlight-color: transparent;
    }

    /* Light Scroll Up Button */
    .storefront-scroll-top-btn,
    .storefront-scroll-top-btn.btn-light,
    html.light .storefront-scroll-top-btn,
    [data-theme="light"] .storefront-scroll-top-btn {
        background: rgba(255, 255, 255, 0.94);
        border: 1px solid rgba(0, 0, 0, 0.08);
        color: #0f172a;
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.09), 0 0 0 1px rgba(255, 255, 255, 0.9) inset;
    }

    /* Dark Scroll Up Button */
    .storefront-scroll-top-btn.btn-dark,
    html.dark .storefront-scroll-top-btn,
    [data-theme="dark"] .storefront-scroll-top-btn {
        background: rgba(26, 26, 29, 0.88);
        border: 1px solid rgba(255, 255, 255, 0.16);
        color: #ffffff;
        box-shadow: 0 10px 28px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(255, 255, 255, 0.08) inset;
    }

    .storefront-scroll-top-btn:hover {
        transform: translateY(-2px);
        color: var(--primary);
        border-color: var(--primary);
    }

    .storefront-scroll-top-btn:active {
        transform: scale(0.92);
    }

    .storefront-scroll-top-btn.has-ai-bubble {
        bottom: 126px;
    }

    .storefront-scroll-top-btn.has-active-order-pill {
        bottom: 140px;
    }

    .storefront-scroll-top-btn.has-ai-bubble.has-active-order-pill {
        bottom: 208px;
    }

    @media (min-width: 601px) {
        .storefront-scroll-top-btn {
            right: calc(50% - (var(--desktop-max-width, 600px) / 2) + 1.25rem);
        }
    }

    /* Header Info Button (iOS Pill Redesign) */
    .header-info-pill-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        padding: 0.5rem 0.85rem;
        border-radius: 12px;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        color: var(--text-main);
        font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", Roboto, sans-serif;
        font-size: 0.82rem;
        font-weight: 700;
        cursor: pointer;
        flex-shrink: 0;
        height: 38px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        -webkit-tap-highlight-color: transparent;
        text-decoration: none;
        box-sizing: border-box;
    }

    .header-info-pill-btn i {
        font-size: 0.95rem;
        color: var(--primary);
        transition: transform 0.2s ease;
    }

    .header-info-pill-btn:hover {
        transform: translateY(-1px);
        border-color: var(--primary);
        color: var(--primary);
        box-shadow: 0 4px 14px color-mix(in srgb, var(--primary) 22%, transparent);
    }

    .header-info-pill-btn:active {
        transform: scale(0.94);
    }

    .header-info-pill-btn .info-btn-text {
        letter-spacing: -0.01em;
        white-space: nowrap;
    }

    @media (max-width: 380px) {
        .header-info-pill-btn .info-btn-text {
            display: none;
        }
        .header-info-pill-btn {
            padding: 0;
            width: 38px;
            height: 38px;
        }
    }

    .cart-bump .nav-icon-wrapper {
        animation: cartBounce 0.5s ease;
    }

    @keyframes cartBounce {
        0% { transform: scale(1); }
        30% { transform: scale(1.35) rotate(-8deg); }
        60% { transform: scale(0.92) rotate(4deg); }
        100% { transform: scale(1) rotate(0deg); }
    }

    /* Active Order Floating Pill */
    .active-order-floating-pill {
        pointer-events: auto;
        box-sizing: border-box;
        max-width: calc(var(--desktop-max-width, 600px) - 1.5rem);
        margin: 0 auto 0.6rem auto;
        width: calc(100% - 1.5rem);
        background: var(--bg-glass);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1.5px solid var(--primary);
        border-radius: 16px;
        padding: 0.65rem 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.18);
        cursor: pointer;
        animation: slideUpFade 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        transition: transform 0.15s ease;
    }

    .active-order-floating-pill:active {
        transform: scale(0.98);
    }

    .order-pill-left {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 0;
    }

    .live-pulse-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background-color: #10b981;
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
        animation: dotPulse 1.8s infinite;
        flex-shrink: 0;
    }

    .order-pill-icon {
        font-size: 1.15rem;
        color: var(--primary);
        flex-shrink: 0;
    }

    .order-pill-info {
        min-width: 0;
    }

    .order-pill-title {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--text-main);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .order-pill-badge {
        background: var(--primary);
        color: #ffffff;
        font-size: 0.68rem;
        font-weight: 700;
        padding: 0.15rem 0.45rem;
        border-radius: 6px;
        line-height: 1.2;
    }

    .order-pill-desc {
        font-size: 0.73rem;
        color: var(--text-muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-top: 0.1rem;
    }

    .order-pill-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-left: 0.5rem;
        flex-shrink: 0;
    }

    .order-pill-open-btn {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 0.75rem;
    }

    .order-pill-close-btn {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        color: var(--text-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 0.75rem;
        transition: all 0.15s ease;
    }

    .order-pill-close-btn:hover {
        color: #ef4444;
        border-color: #ef4444;
    }

    @keyframes dotPulse {
        0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
        100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }

    @keyframes badgePulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.08); }
    }

    @keyframes slideUpFade {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* AI Waiter Floating Bubble */
    .ai-waiter-floating-bubble {
        pointer-events: auto;
        position: fixed;
        bottom: 70px;
        right: 1.25rem;
        z-index: 75;
        background: var(--primary);
        color: #ffffff;
        border-radius: 9999px;
        padding: 0.45rem 0.95rem 0.45rem 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.55rem;
        box-shadow: 0 8px 24px -2px color-mix(in srgb, var(--primary) 40%, transparent), 0 2px 8px rgba(0, 0, 0, 0.12);
        cursor: pointer;
        animation: aiBubbleFloat 3s ease-in-out infinite;
        transition: bottom 0.35s cubic-bezier(0.16, 1, 0.3, 1), transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .ai-waiter-floating-bubble.has-active-order-pill {
        bottom: 146px;
    }
    @media (min-width: 601px) {
        .ai-waiter-floating-bubble {
            right: calc(50% - (var(--desktop-max-width, 600px) / 2) + 1.25rem);
        }
    }
    .ai-waiter-floating-bubble:hover {
        transform: scale(1.05);
    }
    .ai-waiter-floating-bubble:active {
        transform: scale(0.95);
    }
    .ai-bubble-avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.22);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.88rem;
    }
    .ai-bubble-content {
        display: flex;
        flex-direction: column;
        line-height: 1.15;
    }
    .ai-bubble-title {
        font-size: 0.76rem;
        font-weight: 800;
        letter-spacing: -0.01em;
    }
    .ai-bubble-desc {
        font-size: 0.66rem;
        font-weight: 600;
        opacity: 0.92;
    }
    @keyframes aiBubbleFloat {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-4px); }
    }

    /* Pre-Order Mode Floating Top Banner Styling */
    .storefront-preorder-top-banner {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 999;
        background: var(--bg-card, #ffffff);
        border-bottom: 2px solid var(--primary, #3b82f6);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.14);
        padding: 0.65rem 1rem;
        pointer-events: auto;
    }
    .preorder-top-banner-inner {
        max-width: 900px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }
    .preorder-top-banner-left {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        min-width: 0;
    }
    .preorder-live-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25);
        flex-shrink: 0;
        animation: preorderPulse 2s infinite;
    }
    @keyframes preorderPulse {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.5); }
        70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
    .preorder-top-banner-texts {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .preorder-top-banner-title {
        font-size: 0.88rem;
        font-weight: 800;
        color: var(--text-main, #0f172a);
        display: flex;
        align-items: center;
        gap: 0.45rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .preorder-top-banner-desc {
        font-size: 0.75rem;
        color: var(--text-muted, #64748b);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .preorder-return-btn {
        background: var(--primary, #3b82f6);
        color: #ffffff;
        border: none;
        border-radius: 10px;
        padding: 0.45rem 0.85rem;
        font-size: 0.82rem;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        white-space: nowrap;
        flex-shrink: 0;
        box-shadow: 0 2px 10px rgba(59, 130, 246, 0.35);
        transition: transform 0.15s, opacity 0.15s;
    }
    .preorder-return-btn:active {
        transform: scale(0.97);
    }
    .preorder-cart-badge {
        background: #ffffff;
        color: var(--primary, #3b82f6);
        font-size: 0.72rem;
        font-weight: 800;
        padding: 0.1rem 0.4rem;
        border-radius: 9999px;
    }
</style>
