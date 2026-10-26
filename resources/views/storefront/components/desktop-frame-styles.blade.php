@php
    $maxWidth = $vendor->getDesktopMaxWidth();
    $isFullWidth = ($maxWidth === '100%');
    $isDark = ($vendor->theme_mode === 'dark');
    $isExplicitMobileNarrow = in_array($maxWidth, ['480px', '500px']);
@endphp

<style>
    :root {
        --desktop-max-width: {{ $maxWidth }};
    }

    /* Responsive Multi-Column Grid on Tablet & Desktop */
    @media (min-width: 768px) {
        .dishes-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)) !important;
            gap: 1.25rem !important;
        }

        .dish-card, .glass-card {
            margin-bottom: 0 !important;
            height: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            box-sizing: border-box !important;
        }

        .dish-card .dish-img, .glass-card .dish-img {
            width: 100% !important;
            height: 165px !important;
            border-radius: 12px !important;
            object-fit: cover !important;
            margin-bottom: 0.75rem !important;
        }

        .dish-card > div:last-child, .glass-card > div:last-child {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
    }

    @if(!$isFullWidth)
    @media (min-width: 601px) {
        body {
            background-color: {{ $isDark ? '#090d16' : '#f1f5f9' }} !important;
            background-image: {{ $isDark 
                ? 'radial-gradient(circle at 50% 0%, rgba(225, 29, 72, 0.12) 0%, transparent 55%), radial-gradient(circle at 80% 100%, rgba(79, 70, 229, 0.08) 0%, transparent 55%)' 
                : 'radial-gradient(circle at 50% 0%, rgba(225, 29, 72, 0.05) 0%, transparent 55%), radial-gradient(circle at 80% 100%, rgba(79, 70, 229, 0.04) 0%, transparent 55%)' }} !important;
            background-attachment: fixed !important;
            min-height: 100vh;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: flex-start !important;
            margin: 0 !important;
            overflow-x: hidden;
        }

        .storefront-app-shell {
            width: 100% !important;
            max-width: var(--desktop-max-width) !important;
            min-height: 100vh;
            background-color: var(--bg-main) !important;
            box-shadow: 0 0 60px rgba(0, 0, 0, {{ $isDark ? '0.5' : '0.12' }}), 0 25px 60px -15px rgba(0, 0, 0, {{ $isDark ? '0.6' : '0.18' }}) !important;
            border-left: 1px solid var(--border-color);
            border-right: 1px solid var(--border-color);
            position: relative;
            box-sizing: border-box;
            margin: 0 auto !important;
        }

        /* Fixed / Floating Top Notification Banners */
        .toast-notification {
            max-width: var(--desktop-max-width) !important;
            left: 50% !important;
            right: auto !important;
            transform: translateX(-50%) !important;
            border-left: 1px solid var(--border-color);
            border-right: 1px solid var(--border-color);
            box-sizing: border-box;
            z-index: 100000 !important;
        }
        .toast-anim-start {
            transform: translate(-50%, -100%) !important;
        }
        .toast-anim-end {
            transform: translate(-50%, 0) !important;
        }

        /* Fixed Bottom Navigation & Trackers */
        .storefront-bottom-nav-container {
            max-width: var(--desktop-max-width) !important;
            left: 50% !important;
            right: auto !important;
            transform: translateX(-50%) !important;
            width: 100% !important;
        }

        .storefront-bottom-nav {
            max-width: 440px !important;
            width: calc(100% - 28px) !important;
            border-radius: 9999px !important;
        }

        .active-order-floating-pill {
            max-width: calc(var(--desktop-max-width) - 1.5rem) !important;
            margin: 0 auto 0.6rem auto !important;
            left: auto !important;
            transform: none !important;
        }

        .ai-waiter-floating-bubble {
            left: auto !important;
            right: calc(50% - (var(--desktop-max-width) / 2) + 1.25rem) !important;
            transform: none !important;
        }

        /* Modals & Bottom Sheets */
        .cart-modal-sheet,
        .info-sheet-container {
            max-width: var(--desktop-max-width) !important;
            margin: 0 auto !important;
            border-left: 1px solid var(--border-color);
            border-right: 1px solid var(--border-color);
        }

        /* Sticky Navigation Bar */
        .category-scroll {
            max-width: 100%;
        }
    }

    /* Wide Desktop Adaptive Layout (Expands from mobile phone constraint on modern large monitors) */
    @if(!$isExplicitMobileNarrow)
    @media (min-width: 992px) {
        .storefront-app-shell {
            max-width: min(1180px, 94vw) !important;
            border-radius: 20px;
            margin: 1.5rem auto 3rem auto !important;
            border: 1px solid var(--border-color) !important;
        }

        .toast-notification,
        .storefront-bottom-nav-container {
            max-width: min(1180px, 94vw) !important;
        }

        .cart-modal-overlay {
            justify-content: center !important;
            padding: 1.5rem !important;
        }

        .cart-modal-sheet,
        .info-sheet-container {
            max-width: 620px !important;
            border-radius: 24px !important;
            border: 1px solid var(--border-color) !important;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.45) !important;
            max-height: 88vh !important;
            margin: auto !important;
        }

        .ai-waiter-floating-bubble,
        .storefront-scroll-top-btn {
            right: calc(50% - (min(1180px, 94vw) / 2) + 1.5rem) !important;
        }

        .dishes-grid {
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)) !important;
            gap: 1.5rem !important;
        }
    }
    @endif
    @endif

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

    /* RTL Layout Rules for Arabic, Persian, Hebrew, Urdu */
    [dir="rtl"] {
        text-align: right;
        direction: rtl;
    }
    [dir="rtl"] .category-scroll,
    [dir="rtl"] .category-nav,
    [dir="rtl"] .category-nav-wrapper {
        direction: rtl;
    }
    [dir="rtl"] .search-box i,
    [dir="rtl"] .search-input-wrap i,
    [dir="rtl"] .search-wrap i {
        left: auto !important;
        right: 1.1rem !important;
    }
    [dir="rtl"] .search-input,
    [dir="rtl"] .search-bar input {
        padding-left: 1rem !important;
        padding-right: 2.85rem !important;
        text-align: right !important;
    }
    [dir="rtl"] .ai-waiter-floating-bubble,
    [dir="rtl"] .storefront-scroll-top-btn {
        right: auto !important;
        left: 1.25rem !important;
    }
    @media (min-width: 601px) {
        [dir="rtl"] .ai-waiter-floating-bubble,
        [dir="rtl"] .storefront-scroll-top-btn {
            right: auto !important;
            left: calc(50% - (var(--desktop-max-width) / 2) + 1.25rem) !important;
        }
    }
    @media (min-width: 992px) {
        [dir="rtl"] .ai-waiter-floating-bubble,
        [dir="rtl"] .storefront-scroll-top-btn {
            right: auto !important;
            left: calc(50% - (min(1180px, 94vw) / 2) + 1.5rem) !important;
        }
    }
    [dir="rtl"] .dish-card .dish-img-wrap span[style*="left: 6px"],
    [dir="rtl"] .dish-card .dish-img-wrap span[style*="left:6px"] {
        left: auto !important;
        right: 6px !important;
    }
</style>
