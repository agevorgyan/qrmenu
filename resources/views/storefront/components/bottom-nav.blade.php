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
                            <span style="font-weight: 700; color: var(--text-main);" x-text="'• ' + Number(activeOrder.total_amount).toLocaleString() + ' ' + (activeOrder.currency || '{{ $vendor->currency }}')"></span>
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
            <span class="ai-bubble-desc">✨ Օգնե՞մ ընտրել</span>
        </div>
    </div>
    @endif

    <!-- Bottom Navigation Bar -->
    <nav class="storefront-bottom-nav" aria-label="Storefront Mobile Navigation">
        <!-- 1. Home / Menu -->
        <button type="button" 
                class="bottom-nav-item" 
                :class="{ 'active': currentTab === 'menu' && !showCartModal && !showInfoModal && !showOrderTracker && !showAiWaiter }"
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
            <span class="nav-label">{{ __('AI Մատուցող') }}</span>
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

        <!-- 3. Cart -->
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
        <!-- 4. Table Reservation -->
        <a href="{{ route('client.reserve', ['vendor_slug' => $vendor->slug, 'location_slug' => $location?->slug, 'lang' => app()->getLocale()]) }}" 
           class="bottom-nav-item bottom-nav-reserve" 
           title="{{ __('Reserve a Table') }}"
           style="text-decoration: none; color: inherit;">
            <div class="nav-icon-wrapper">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
            <span class="nav-label">{{ __('Reserve') }}</span>
        </a>
        @endif

        <!-- 5. Info & WiFi -->
        <button type="button" 
                class="bottom-nav-item" 
                :class="{ 'active': showInfoModal }"
                @click="showInfoModal = true">
            <div class="nav-icon-wrapper">
                <i class="fa-solid fa-circle-info"></i>
            </div>
            <span class="nav-label">{{ __('menu.nav_info') }}</span>
        </button>
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
    }

    .storefront-bottom-nav {
        pointer-events: auto;
        background: var(--bg-glass);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-top: 1px solid var(--border-color);
        display: flex;
        justify-content: space-around;
        align-items: center;
        padding-top: 0.5rem;
        padding-bottom: max(0.5rem, env(safe-area-inset-bottom));
        padding-left: 0.5rem;
        padding-right: 0.5rem;
        box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.07);
        max-width: var(--desktop-max-width, 600px);
        margin: 0 auto;
    }

    .bottom-nav-item {
        pointer-events: auto;
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: transparent;
        border: none;
        padding: 0.35rem 0.2rem;
        color: var(--text-muted);
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        text-decoration: none;
        -webkit-tap-highlight-color: transparent;
    }

    .bottom-nav-item:active {
        transform: scale(0.92);
    }

    .bottom-nav-item.active {
        color: var(--primary);
    }

    .bottom-nav-item .nav-icon-wrapper {
        position: relative;
        font-size: 1.25rem;
        height: 26px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 0.2rem;
        transition: transform 0.2s ease;
    }

    .bottom-nav-item.active .nav-icon-wrapper {
        transform: translateY(-2px);
    }

    .bottom-nav-item .nav-label {
        font-size: 0.72rem;
        font-weight: 600;
        line-height: 1;
        letter-spacing: -0.01em;
    }

    .bottom-nav-item.has-items {
        color: var(--primary);
    }

    .bottom-nav-item.has-items .nav-icon-wrapper i {
        color: var(--primary);
    }

    .nav-badge {
        position: absolute;
        top: -6px;
        right: -10px;
        background: var(--primary);
        color: #ffffff;
        font-size: 0.72rem;
        font-weight: 800;
        min-width: 19px;
        height: 19px;
        border-radius: 9999px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 4px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35), 0 0 10px rgba(16, 185, 129, 0.5);
        border: 2px solid var(--bg-card);
        animation: badgePulse 2s infinite;
        z-index: 5;
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
        bottom: calc(78px + max(0.5rem, env(safe-area-inset-bottom)));
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
        bottom: calc(150px + max(0.5rem, env(safe-area-inset-bottom)));
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
</style>
