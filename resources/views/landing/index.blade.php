<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $siteName = \App\Models\SystemSetting::getSiteName();
        $seoTitle = \App\Models\SystemSetting::getSeoTitle(app()->getLocale()) ?? ($siteName . ' — ' . __('hero_title'));
        $seoDesc = \App\Models\SystemSetting::getSeoDescription(app()->getLocale()) ?? __('hero_subtitle');
        $seoKeywords = \App\Models\SystemSetting::getSeoKeywords();
        $siteFavicon = \App\Models\SystemSetting::getFavicon();
        $siteLogoDark = \App\Models\SystemSetting::getLogoDark();
        $ogImage = \App\Models\SystemSetting::getOgImage();
        $loc = app()->getLocale();

        // 3-way language translator helper: hy, ru, en
        $tr = function (string $hy, string $ru, string $en) use ($loc): string {
            if ($loc === 'hy') {
                return $hy;
            }
            if ($loc === 'ru') {
                return $ru;
            }
            return $en;
        };

        // Dynamic CMS resolver: checks localized setting key ($key . '_' . $loc), then unlocalized ($key), then fallback
        $cms = function (string $key, $fallback = '') use ($settings, $loc) {
            $locKey = $key . '_' . $loc;
            if (isset($settings[$locKey]) && $settings[$locKey] !== null && $settings[$locKey] !== '') {
                return $settings[$locKey];
            }
            if (isset($settings[$key]) && $settings[$key] !== null && $settings[$key] !== '') {
                return $settings[$key];
            }
            if (isset($settings[$key . '_en']) && $settings[$key . '_en'] !== null && $settings[$key . '_en'] !== '') {
                return $settings[$key . '_en'];
            }
            if (isset($settings[$key . '_hy']) && $settings[$key . '_hy'] !== null && $settings[$key . '_hy'] !== '') {
                return $settings[$key . '_hy'];
            }
            return $fallback;
        };
    @endphp
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDesc }}">
    @if($seoKeywords)
        <meta name="keywords" content="{{ $seoKeywords }}">
    @endif

    <!-- Dynamic Favicon -->
    <link rel="icon" type="image/png" href="{{ $siteFavicon }}">
    <link rel="apple-touch-icon" href="{{ $siteFavicon }}">

    <!-- Open Graph / Facebook / Telegram -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDesc }}">
    @if($ogImage)
        <meta property="og:image" content="{{ str_starts_with($ogImage, 'http') ? $ogImage : url($ogImage) }}">
    @endif

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="{{ url()->current() }}">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDesc }}">
    @if($ogImage)
        <meta name="twitter:image" content="{{ str_starts_with($ogImage, 'http') ? $ogImage : url($ogImage) }}">
    @endif

    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">

    <style>
        :root {
            --bg-main: #070913;
            --bg-card: rgba(15, 23, 42, 0.7);
            --bg-card-hover: rgba(30, 41, 59, 0.85);
            --border-glass: rgba(255, 255, 255, 0.08);
            --border-highlight: rgba(245, 158, 11, 0.4);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent-amber: #f59e0b;
            --accent-red: #ef4444;
            --accent-emerald: #10b981;
            --accent-cyan: #06b6d4;
            --accent-gradient: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
            --accent-gradient-cyan: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%);
            --shadow-card: 0 20px 40px -15px rgba(0, 0, 0, 0.5);
            --shadow-glow: 0 0 35px rgba(245, 158, 11, 0.25);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            scroll-behavior: smooth;
        }

        html, body {
            overflow-x: clip;
            width: 100%;
            max-width: 100vw;
        }

        html {
            scroll-padding-top: 110px;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            position: relative;
            padding-top: 116px;
        }

        /* Ambient Glow Backgrounds */
        .ambient-glow-1 {
            position: absolute;
            top: 0;
            left: 20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(245, 158, 11, 0.12) 0%, rgba(239, 68, 68, 0.04) 50%, transparent 70%);
            filter: blur(80px);
            z-index: 0;
            pointer-events: none;
        }
        .ambient-glow-2 {
            position: absolute;
            top: 1400px;
            right: 5%;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(6, 182, 212, 0.1) 0%, rgba(59, 130, 246, 0.03) 50%, transparent 70%);
            filter: blur(90px);
            z-index: 0;
            pointer-events: none;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
            position: relative;
            z-index: 1;
        }

        /* Top Floating Capsule Header (Sticky / Fixed) */
        .site-header {
            position: fixed;
            top: 14px;
            left: 0;
            right: 0;
            width: 100%;
            z-index: 1000;
            padding: 0 1rem;
            pointer-events: none;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .site-header .container {
            max-width: 1220px;
            padding: 0;
            pointer-events: auto;
        }
        .nav-wrapper {
            background: rgba(10, 15, 29, 0.85);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 999px;
            padding: 0.45rem 1.25rem;
            min-height: 88px;
            height: auto;
            box-shadow: 0 15px 40px -10px rgba(0, 0, 0, 0.65), 0 0 25px rgba(245, 158, 11, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .site-header.scrolled .nav-wrapper {
            background: rgba(7, 10, 20, 0.95);
            border-color: rgba(245, 158, 11, 0.35);
            box-shadow: 0 18px 45px -8px rgba(0, 0, 0, 0.85), 0 0 35px rgba(245, 158, 11, 0.18);
            min-height: 72px;
            padding: 0.35rem 1.15rem;
        }
        .brand-logo {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            text-decoration: none;
            color: var(--text-primary);
            flex-shrink: 0;
        }
        .brand-logo img,
        .landing-nav-logo {
            height: 72px;
            max-height: 72px;
            width: auto;
            max-width: 260px;
            object-fit: contain;
            display: block;
            transition: height 0.25s ease;
        }
        .site-header.scrolled .landing-nav-logo {
            height: 56px;
            max-height: 56px;
        }
        .logo-icon {
            width: 58px;
            height: 58px;
            border-radius: 16px;
            background: var(--accent-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.65rem;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35);
            flex-shrink: 0;
        }
        .brand-text {
            font-family: 'Outfit', sans-serif;
            font-size: 1.45rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .brand-text span {
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Center Nav Group: Burger Capsule & Quick Jumps */
        .nav-center-group {
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }
        .nav-burger-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            min-width: 38px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 12px;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.22s ease;
            padding: 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
        }
        .nav-burger-pill:hover, .nav-burger-pill.active {
            background: rgba(245, 158, 11, 0.18);
            border-color: rgba(245, 158, 11, 0.45);
            color: var(--accent-amber);
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.28);
        }
        .burger-bars-icon {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 3.5px;
            width: 16px;
        }
        .burger-bars-icon span {
            display: block;
            height: 2px;
            width: 100%;
            background: currentColor;
            border-radius: 2px;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .nav-burger-pill.active .burger-bars-icon span:nth-child(1) {
            transform: translateY(5.5px) rotate(45deg);
        }
        .nav-burger-pill.active .burger-bars-icon span:nth-child(2) {
            opacity: 0;
            transform: scaleX(0);
        }
        .nav-burger-pill.active .burger-bars-icon span:nth-child(3) {
            transform: translateY(-5.5px) rotate(-45deg);
        }
        .nav-quick-pills {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .quick-nav-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 999px;
            padding: 0.4rem 0.85rem;
            color: var(--text-secondary);
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .quick-nav-pill:hover {
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
        }
        @media (max-width: 900px) {
            .nav-quick-pills {
                display: none;
            }
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Language Switcher Segmented Pill (Desktop) */
        .lang-switch {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-glass);
            border-radius: 30px;
            padding: 3px;
        }
        .lang-btn {
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 0.76rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .lang-btn.active {
            background: var(--accent-amber);
            color: #000000;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }

        /* Mobile Compact Language Dropdown */
        .landing-lang-dropdown {
            position: relative;
            display: none;
        }
        .landing-lang-trigger {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            height: 36px;
            padding: 0 0.65rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 999px;
            color: var(--text-primary);
            font-family: 'Outfit', sans-serif;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        }
        .landing-lang-trigger:hover,
        .landing-lang-dropdown.open .landing-lang-trigger {
            background: rgba(245, 158, 11, 0.16);
            border-color: rgba(245, 158, 11, 0.45);
            color: var(--accent-amber);
        }
        .landing-lang-trigger .active-flag {
            font-size: 0.95rem;
            line-height: 1;
        }
        .landing-lang-trigger .active-code {
            letter-spacing: 0.4px;
        }
        .landing-lang-trigger .lang-chevron {
            font-size: 0.62rem;
            opacity: 0.75;
            transition: transform 0.25s ease;
        }
        .landing-lang-dropdown.open .landing-lang-trigger .lang-chevron {
            transform: rotate(180deg);
        }
        .landing-lang-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 148px;
            background: rgba(13, 20, 36, 0.96);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 16px;
            padding: 0.35rem;
            box-shadow: 0 16px 36px -8px rgba(0, 0, 0, 0.85), 0 0 24px rgba(245, 158, 11, 0.12);
            z-index: 1050;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px) scale(0.96);
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
            pointer-events: none;
        }
        .landing-lang-dropdown.open .landing-lang-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }
        .landing-lang-option {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.55rem 0.75rem;
            border-radius: 10px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 600;
            transition: all 0.18s ease;
        }
        .landing-lang-option:hover {
            background: rgba(255, 255, 255, 0.08);
            color: var(--text-primary);
        }
        .landing-lang-option.active {
            background: rgba(245, 158, 11, 0.16);
            color: var(--accent-amber);
            font-weight: 700;
        }
        .landing-lang-option .lang-option-flag {
            font-size: 1rem;
            line-height: 1;
        }
        .landing-lang-option .lang-option-title {
            flex: 1;
        }
        .landing-lang-option .active-check {
            font-size: 0.75rem;
            color: var(--accent-amber);
        }

        .btn-login {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            padding: 0.5rem 0.85rem;
            transition: color 0.2s;
        }
        .btn-login:hover {
            color: var(--text-primary);
        }
        .btn-register-top {
            background: var(--accent-gradient);
            color: #ffffff;
            font-size: 0.85rem;
            font-weight: 700;
            text-decoration: none;
            padding: 0.55rem 1.25rem;
            border-radius: 999px;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.35);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            white-space: nowrap;
        }
        .btn-register-top:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.5);
        }

        /* Command Center Navigation Drawer / Modal */
        .command-menu-overlay {
            position: fixed;
            inset: 0;
            background: rgba(5, 7, 15, 0.78);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 2.5rem 1rem;
            overflow-y: auto;
        }
        .command-menu-overlay.open {
            opacity: 1;
            visibility: visible;
        }
        .command-menu-modal {
            background: rgba(15, 23, 42, 0.96);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 28px;
            max-width: 960px;
            width: 100%;
            padding: 2rem;
            box-shadow: 0 30px 80px -15px rgba(0, 0, 0, 0.9), 0 0 60px rgba(245, 158, 11, 0.12);
            transform: translateY(-15px) scale(0.98);
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
        }
        .command-menu-overlay.open .command-menu-modal {
            transform: translateY(0) scale(1);
        }
        .command-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 1.5rem;
        }
        .command-modal-close {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: var(--text-secondary);
            width: 38px;
            height: 38px;
            border-radius: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.2s ease;
        }
        .command-modal-close:hover {
            color: #ffffff;
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.4);
        }
        .command-bento-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 768px) {
            .command-bento-grid {
                grid-template-columns: 1fr;
            }
        }
        .command-bento-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 18px;
            padding: 1.15rem;
            text-decoration: none;
            display: flex;
            gap: 0.85rem;
            align-items: flex-start;
            transition: all 0.25s ease;
        }
        .command-bento-card:hover {
            background: rgba(245, 158, 11, 0.08);
            border-color: rgba(245, 158, 11, 0.35);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
        }
        .command-icon-box {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .command-card-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-primary);
            margin-bottom: 0.2rem;
        }
        .command-card-desc {
            font-size: 0.78rem;
            color: var(--text-secondary);
            line-height: 1.4;
            margin: 0;
        }
        .command-modal-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-top: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }
        .drawer-contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.65rem;
            width: 100%;
        }
        .drawer-contact-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.65rem 1rem;
            border-radius: 12px;
            font-size: 0.84rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .drawer-contact-btn.btn-wa {
            background: rgba(37, 211, 102, 0.15);
            color: #25d366;
            border: 1px solid rgba(37, 211, 102, 0.35);
        }
        .drawer-contact-btn.btn-wa:hover {
            background: #25d366;
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);
        }
        .drawer-contact-btn.btn-tg {
            background: rgba(0, 136, 204, 0.15);
            color: #29b6f6;
            border: 1px solid rgba(0, 136, 204, 0.35);
        }
        .drawer-contact-btn.btn-tg:hover {
            background: #0088cc;
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(0, 136, 204, 0.4);
        }
        .drawer-cta-wrap {
            width: 100%;
        }
        .drawer-btn-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.8rem 1.25rem;
            border-radius: 999px;
            background: var(--accent-gradient);
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-size: 0.92rem;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 4px 18px rgba(245, 158, 11, 0.35);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .drawer-btn-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(245, 158, 11, 0.5);
        }
        .drawer-bottom-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            gap: 0.75rem;
            padding-top: 0.25rem;
        }
        .drawer-lang-switch {
            background: rgba(255, 255, 255, 0.08);
        }
        .drawer-login-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 0.4rem 0.75rem;
            border-radius: 8px;
            transition: color 0.2s ease;
        }
        .drawer-login-link:hover {
            color: var(--text-primary);
        }

        /* Hero Section */
        .hero-section {
            padding: 5rem 0 4rem;
            position: relative;
        }
        .hero-grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 3.5rem;
            align-items: center;
        }
        .hero-badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.25);
            color: var(--accent-amber);
            font-size: 0.82rem;
            font-weight: 600;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            margin-bottom: 1.5rem;
        }
        .hero-title {
            font-family: 'Outfit', sans-serif;
            font-size: 3.25rem;
            font-weight: 900;
            line-height: 1.15;
            letter-spacing: -1px;
            margin-bottom: 1.5rem;
        }
        .hero-title .gradient-text {
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-subtitle {
            font-size: 1.15rem;
            color: var(--text-secondary);
            line-height: 1.65;
            margin-bottom: 2.25rem;
            max-width: 620px;
        }
        .hero-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 2.5rem;
        }
        .btn-cta-primary {
            background: var(--accent-gradient);
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            text-decoration: none;
            padding: 1rem 2rem;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
            transition: all 0.25s ease;
        }
        .btn-cta-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(245, 158, 11, 0.55);
        }
        .btn-cta-secondary {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-glass);
            color: var(--text-primary);
            font-family: 'Outfit', sans-serif;
            font-size: 1.05rem;
            font-weight: 600;
            text-decoration: none;
            padding: 1rem 1.75rem;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
            transition: all 0.2s ease;
        }
        .btn-cta-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        .hero-trust-list {
            display: flex;
            align-items: center;
            gap: 1.75rem;
            flex-wrap: wrap;
        }
        .trust-item {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        .trust-item i {
            color: var(--accent-emerald);
        }

        /* Realistic iPhone 16 Pro Mockup Visual */
        .mockup-wrapper {
            position: relative;
            display: flex;
            justify-content: center;
        }
        .iphone-mockup-outer {
            position: relative;
            display: flex;
            justify-content: center;
            padding: 0.5rem 0;
        }
        .iphone-16-pro {
            width: 328px;
            background: #0f141f;
            border-radius: 54px;
            position: relative;
            padding: 11px 11px 13px;
            border: 4px solid #334155;
            box-shadow: 
                0 0 0 1.5px rgba(255, 255, 255, 0.18),
                inset 0 0 0 2px #0a0d16,
                0 30px 80px -15px rgba(0, 0, 0, 0.9),
                0 0 50px rgba(245, 158, 11, 0.14);
            transform: perspective(1000px) rotateY(-5deg) rotateX(3deg);
            transition: transform 0.4s ease, box-shadow 0.4s ease;
        }
        .iphone-16-pro:hover {
            transform: perspective(1000px) rotateY(0deg) rotateX(0deg);
            box-shadow: 
                0 0 0 1.5px rgba(255, 255, 255, 0.25),
                inset 0 0 0 2px #0a0d16,
                0 35px 90px -10px rgba(0, 0, 0, 0.95),
                0 0 60px rgba(245, 158, 11, 0.22);
        }

        /* Physical Side Hardware Buttons */
        .iphone-hw-button {
            position: absolute;
            background: #1e293b;
            border-radius: 4px;
            box-shadow: 0 0 2px rgba(255, 255, 255, 0.2);
        }
        .iphone-btn-action {
            left: -8px;
            top: 92px;
            width: 4px;
            height: 24px;
        }
        .iphone-btn-vol-up {
            left: -8px;
            top: 130px;
            width: 4px;
            height: 44px;
        }
        .iphone-btn-vol-down {
            left: -8px;
            top: 184px;
            width: 4px;
            height: 44px;
        }
        .iphone-btn-power {
            right: -8px;
            top: 140px;
            width: 4px;
            height: 68px;
        }

        /* Screen Glass & Reflection */
        .iphone-screen {
            border-radius: 44px;
            overflow: hidden;
            background: #070913;
            border: 1px solid rgba(255, 255, 255, 0.08);
            position: relative;
            padding: 10px 14px 12px;
        }
        .iphone-glare {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0.02) 32%, transparent 55%);
            pointer-events: none;
            z-index: 20;
        }

        /* Dynamic Island */
        .dynamic-island {
            width: 108px;
            height: 26px;
            background: #000000;
            border-radius: 20px;
            margin: 0 auto 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 9px;
            position: relative;
            z-index: 25;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.8);
        }
        .di-camera {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #0c1524;
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: inset 0 0 3px #38bdf8;
        }
        .di-sensor {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #050810;
        }

        /* iOS Status Bar */
        .iphone-status-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.74rem;
            color: #ffffff;
            font-weight: 700;
            padding: 0 4px 8px;
            letter-spacing: -0.2px;
        }
        .status-icons {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.68rem;
        }
        .battery-pill {
            width: 20px;
            height: 10px;
            border: 1.5px solid #ffffff;
            border-radius: 3px;
            padding: 1px;
            position: relative;
            display: flex;
            align-items: center;
        }
        .battery-pill::after {
            content: '';
            position: absolute;
            right: -3.5px;
            width: 2px;
            height: 4px;
            background: #ffffff;
            border-radius: 0 1px 1px 0;
        }
        .battery-fill {
            height: 100%;
            width: 82%;
            background: #10b981;
            border-radius: 1px;
        }

        /* In-Screen App UI */
        .iphone-app-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 0 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 10px;
        }
        .app-venue-badge {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .venue-avatar {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            background: var(--accent-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            color: #fff;
        }
        .app-table-pill {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .pulse-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 6px #10b981;
            animation: pulse 1.8s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.85); }
        }

        /* Category Chips Slider */
        .app-categories {
            display: flex;
            gap: 5px;
            overflow-x: hidden;
            margin-bottom: 10px;
        }
        .app-cat-chip {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: var(--text-secondary);
            font-size: 0.68rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 8px;
            white-space: nowrap;
        }
        .app-cat-chip.active {
            background: rgba(245, 158, 11, 0.15);
            color: var(--accent-amber);
            border-color: rgba(245, 158, 11, 0.3);
        }

        /* Dish Card Inside iPhone */
        .app-dish-card {
            background: rgba(30, 41, 59, 0.75);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 9px;
            margin-bottom: 9px;
        }
        .app-dish-img {
            width: 100%;
            height: 110px;
            border-radius: 11px;
            background: linear-gradient(135deg, #1e293b, #0f172a);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.8rem;
            position: relative;
            margin-bottom: 7px;
        }
        .app-dish-rating {
            position: absolute;
            bottom: 6px;
            right: 6px;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            border-radius: 6px;
            padding: 2px 5px;
            font-size: 0.65rem;
            color: #fbbf24;
            font-weight: 700;
        }
        .app-dish-title {
            font-family: 'Outfit', sans-serif;
            font-size: 0.88rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 2px;
        }
        .app-dish-price {
            font-size: 0.84rem;
            font-weight: 800;
            color: var(--accent-amber);
        }

        /* Floating AI Waiter Bubble */
        .ai-floating-bubble {
            background: linear-gradient(135deg, rgba(30, 27, 75, 0.95), rgba(15, 23, 42, 0.95));
            border: 1px solid rgba(139, 92, 246, 0.4);
            border-radius: 16px;
            padding: 0.85rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            margin-bottom: 1rem;
            position: relative;
        }
        .ai-bubble-tag {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.7rem;
            font-weight: 700;
            color: #a78bfa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.3rem;
        }
        .ai-bubble-tag i {
            color: #c084fc;
        }
        .ai-bubble-quote {
            font-size: 0.78rem;
            color: #e2e8f0;
            line-height: 1.4;
            font-style: italic;
        }
        .phone-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }
        .phone-btn {
            background: var(--accent-gradient);
            border: none;
            color: #fff;
            padding: 0.6rem 0.5rem;
            border-radius: 10px;
            font-size: 0.72rem;
            font-weight: 700;
            text-align: center;
            cursor: pointer;
        }
        .phone-btn-alt {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--border-glass);
            color: #fff;
        }

        /* Home Indicator Bar */
        .iphone-home-bar {
            width: 125px;
            height: 4px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.75);
            margin: 8px auto 0;
        }

        /* Section Commons */
        .section {
            padding: 6rem 0;
            position: relative;
        }
        .section-header {
            text-align: center;
            max-width: 750px;
            margin: 0 auto 4rem;
        }
        .section-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.25);
            color: var(--accent-amber);
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.75px;
            padding: 0.35rem 0.9rem;
            border-radius: 30px;
            margin-bottom: 1rem;
        }
        .section-title {
            font-family: 'Outfit', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -0.5px;
            margin-bottom: 1rem;
        }
        .section-subtitle {
            font-size: 1.05rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* 1. Comparison Section (Paper vs Digital) */
        .vs-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        .vs-card {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: var(--shadow-card);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        .vs-card:hover {
            transform: translateY(-4px);
        }
        .vs-card-paper {
            border-top: 4px solid var(--accent-red);
        }
        .vs-card-qr {
            border-top: 4px solid var(--accent-emerald);
            background: linear-gradient(180deg, rgba(16, 185, 129, 0.05), rgba(15, 23, 42, 0.85));
            border-color: rgba(16, 185, 129, 0.25);
        }
        .vs-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.75rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-glass);
        }
        .vs-card-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.35rem;
            font-weight: 700;
        }
        .vs-badge-bad {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
        }
        .vs-badge-good {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
        }
        .vs-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 1.15rem;
        }
        .vs-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            font-size: 0.95rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }
        .vs-icon-bad {
            color: #ef4444;
            font-size: 1.1rem;
            margin-top: 2px;
            flex-shrink: 0;
        }
        .vs-icon-good {
            color: #10b981;
            font-size: 1.1rem;
            margin-top: 2px;
            flex-shrink: 0;
        }

        /* 2. AI Waiter Feature Section & Live Simulator */
        #ai-waiter {
            overflow: hidden;
            width: 100%;
        }
        .ai-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 3rem;
            align-items: center;
            width: 100%;
            max-width: 100%;
        }
        .ai-features-column {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            min-width: 0;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        .ai-feature-card {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 1.5rem;
            display: flex;
            gap: 1.25rem;
            transition: all 0.25s ease;
            min-width: 0;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            overflow: hidden;
        }
        .ai-feature-card:hover {
            border-color: rgba(139, 92, 246, 0.4);
            transform: translateX(6px);
        }
        .ai-feature-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: rgba(139, 92, 246, 0.15);
            color: #a78bfa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            flex-shrink: 0;
        }
        .ai-feature-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
            color: var(--text-primary);
            word-break: break-word;
        }
        .ai-feature-desc {
            font-size: 0.88rem;
            color: var(--text-secondary);
            line-height: 1.5;
            word-break: break-word;
        }

        /* Simulator Card */
        .simulator-box {
            background: linear-gradient(145deg, #101528, #0c101d);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 28px;
            padding: 2.25rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.6), 0 0 30px rgba(139, 92, 246, 0.15);
            min-width: 0;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            overflow: hidden;
        }
        .sim-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-glass);
            flex-wrap: wrap;
            gap: 0.5rem;
            min-width: 0;
            width: 100%;
        }
        .sim-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 0;
            flex: 1 1 auto;
            word-break: break-word;
        }
        .sim-title span {
            min-width: 0;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .sim-title i {
            color: #c084fc;
            flex-shrink: 0;
        }
        .dish-tabs {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.65rem;
            margin-bottom: 1.75rem;
            min-width: 0;
            width: 100%;
            max-width: 100%;
        }
        .dish-tab-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-glass);
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 600;
            padding: 0.65rem 0.85rem;
            border-radius: 12px;
            cursor: pointer;
            text-align: left;
            transition: all 0.2s ease;
            min-width: 0;
        }
        .dish-tab-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
        }
        .dish-tab-btn.active {
            background: rgba(139, 92, 246, 0.2);
            border-color: #a78bfa;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.25);
        }

        .sim-output-card {
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 18px;
            padding: 1.25rem;
            position: relative;
            min-width: 0;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            overflow: hidden;
        }
        .sim-recommendation-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: #a78bfa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .sim-recommendation-text {
            font-size: 0.95rem;
            color: #f1f5f9;
            line-height: 1.5;
            font-style: italic;
            margin-bottom: 1rem;
            word-break: break-word;
            overflow-wrap: break-word;
            min-width: 0;
        }
        .sim-upsell-stat {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(16, 185, 129, 0.1);
            border: 1px dashed rgba(16, 185, 129, 0.3);
            border-radius: 12px;
            padding: 0.6rem 0.85rem;
            font-size: 0.8rem;
            color: #34d399;
            font-weight: 600;
            flex-wrap: wrap;
            gap: 0.5rem;
            min-width: 0;
            width: 100%;
            box-sizing: border-box;
        }
        .sim-upsell-stat span {
            min-width: 0;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        /* 3. Interactive ROI Calculator */
        .calculator-card {
            background: linear-gradient(135deg, rgba(20, 26, 46, 0.85), rgba(12, 17, 30, 0.9));
            border: 1px solid var(--border-glass);
            border-radius: 32px;
            padding: 3.5rem;
            box-shadow: var(--shadow-card);
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 3.5rem;
            align-items: center;
        }
        .calc-inputs {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        .calc-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .calc-label-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.95rem;
            font-weight: 600;
        }
        .calc-value-display {
            font-family: 'Outfit', sans-serif;
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--accent-amber);
        }
        .calc-slider {
            width: 100%;
            height: 8px;
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.15);
            outline: none;
            -webkit-appearance: none;
            appearance: none;
            cursor: pointer;
        }
        .calc-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--accent-amber);
            box-shadow: 0 0 10px rgba(245, 158, 11, 0.6);
            cursor: pointer;
            transition: transform 0.1s;
        }
        .calc-slider::-webkit-slider-thumb:hover {
            transform: scale(1.15);
        }

        .calc-results-box {
            background: rgba(10, 14, 26, 0.8);
            border: 1px solid rgba(245, 158, 11, 0.25);
            border-radius: 24px;
            padding: 2.25rem;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
        }
        .calc-res-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.75rem;
        }
        .calc-main-num {
            font-family: 'Outfit', sans-serif;
            font-size: 2.75rem;
            font-weight: 900;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.1;
            margin-bottom: 0.35rem;
        }
        .calc-main-desc {
            font-size: 0.88rem;
            color: var(--text-muted);
            margin-bottom: 1.75rem;
        }
        .calc-sub-metrics {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-glass);
            margin-bottom: 2rem;
        }
        .calc-metric-item {
            text-align: center;
        }
        .calc-metric-value {
            font-family: 'Outfit', sans-serif;
            font-size: 1.25rem;
            font-weight: 800;
            color: #10b981;
        }
        .calc-metric-label {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        .btn-calc-cta {
            display: block;
            width: 100%;
            background: var(--accent-gradient);
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            padding: 0.85rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.35);
            transition: all 0.2s ease;
        }
        .btn-calc-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.5);
        }

        /* 4. Features Bento Grid */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.75rem;
        }
        .bento-card {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 24px;
            padding: 2rem;
            transition: transform 0.25s ease, border-color 0.25s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .bento-card:hover {
            transform: translateY(-5px);
            border-color: rgba(255, 255, 255, 0.2);
            background: var(--bg-card-hover);
        }
        .bento-icon-circle {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 1.5rem;
        }
        .bento-card h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.65rem;
        }
        .bento-card p {
            font-size: 0.92rem;
            color: var(--text-secondary);
            line-height: 1.55;
        }

        /* 5. Dynamic Pricing Section */
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
            gap: 1.75rem;
            align-items: stretch;
        }
        .pricing-card {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 28px;
            padding: 2.25rem;
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            transition: all 0.3s ease;
        }
        .pricing-card:hover {
            transform: translateY(-6px);
            border-color: rgba(255, 255, 255, 0.25);
        }
        .pricing-card.is-popular {
            border: 2px solid var(--accent-amber);
            background: linear-gradient(180deg, rgba(245, 158, 11, 0.08) 0%, rgba(15, 23, 42, 0.9) 100%);
            box-shadow: 0 20px 40px -10px rgba(245, 158, 11, 0.25);
        }
        .popular-badge {
            position: absolute;
            top: -14px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--accent-gradient);
            color: #ffffff;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 4px 14px;
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
            white-space: nowrap;
        }
        .plan-header {
            margin-bottom: 1.5rem;
        }
        .plan-name {
            font-family: 'Outfit', sans-serif;
            font-size: 1.45rem;
            font-weight: 800;
            margin-bottom: 0.35rem;
        }
        .plan-desc {
            font-size: 0.85rem;
            color: var(--text-secondary);
            min-height: 40px;
        }
        .plan-price-box {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-glass);
        }
        .plan-price-num {
            font-family: 'Outfit', sans-serif;
            font-size: 2.25rem;
            font-weight: 900;
            color: var(--text-primary);
        }
        .plan-price-unit {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        .plan-features-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 2rem;
            flex-grow: 1;
        }
        .plan-feature-item {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            font-size: 0.88rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }
        .plan-feature-item i {
            color: var(--accent-emerald);
            font-size: 0.85rem;
            margin-top: 3px;
            flex-shrink: 0;
        }
        .btn-plan-cta {
            display: block;
            width: 100%;
            text-align: center;
            font-family: 'Outfit', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            text-decoration: none;
            padding: 0.85rem;
            border-radius: 12px;
            transition: all 0.2s ease;
        }
        .btn-plan-primary {
            background: var(--accent-gradient);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.35);
        }
        .btn-plan-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.5);
        }
        .btn-plan-secondary {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--border-glass);
            color: var(--text-primary);
        }
        .btn-plan-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        /* 6. FAQ Accordion */
        .faq-accordion {
            max-width: 800px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .faq-item {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 18px;
            overflow: hidden;
            transition: border-color 0.2s ease;
        }
        .faq-item:hover {
            border-color: rgba(255, 255, 255, 0.2);
        }
        .faq-question {
            padding: 1.35rem 1.75rem;
            font-family: 'Outfit', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            user-select: none;
        }
        .faq-question i {
            color: var(--accent-amber);
            font-size: 0.95rem;
            transition: transform 0.25s ease;
        }
        .faq-item.open .faq-question i {
            transform: rotate(180deg);
        }
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s cubic-bezier(0, 1, 0, 1);
            padding: 0 1.75rem;
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
        }
        .faq-item.open .faq-answer {
            max-height: 500px;
            padding-bottom: 1.5rem;
            transition: max-height 0.35s ease-in-out;
        }

        /* 7. Final High-Impact CTA Banner */
        .final-cta-section {
            padding: 5rem 0;
        }
        .final-cta-card {
            background: radial-gradient(circle at center, #1e1b4b 0%, #0f172a 70%, #070913 100%);
            border: 1px solid rgba(245, 158, 11, 0.35);
            border-radius: 36px;
            padding: 4.5rem 2rem;
            text-align: center;
            box-shadow: 0 25px 60px -10px rgba(0, 0, 0, 0.7), 0 0 50px rgba(245, 158, 11, 0.2);
            position: relative;
            overflow: hidden;
        }
        .final-cta-title {
            font-family: 'Outfit', sans-serif;
            font-size: 2.85rem;
            font-weight: 900;
            margin-bottom: 1.25rem;
            line-height: 1.2;
        }
        .final-cta-subtitle {
            font-size: 1.15rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto 2.5rem;
        }
        .final-cta-buttons {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.25rem;
            flex-wrap: wrap;
        }
        .final-cta-social-group {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .btn-whatsapp {
            background: #25d366;
            color: #ffffff;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            padding: 0.9rem 1.65rem;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.35);
            transition: all 0.2s ease;
        }
        .btn-whatsapp:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.5);
        }
        .btn-telegram {
            background: #229ed9;
            color: #ffffff;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            padding: 0.9rem 1.65rem;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            box-shadow: 0 4px 15px rgba(34, 158, 217, 0.35);
            transition: all 0.2s ease;
        }
        .btn-telegram:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(34, 158, 217, 0.5);
        }

        /* Footer */
        .site-footer {
            border-top: 1px solid var(--border-glass);
            background: #04060c;
            padding: 4.5rem 0 2rem;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1.5fr 1fr;
            gap: 3rem;
            margin-bottom: 3.5rem;
        }
        .footer-brand p {
            color: var(--text-muted);
            font-size: 0.88rem;
            margin-top: 1rem;
            line-height: 1.6;
            max-width: 320px;
        }
        .footer-column h4 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1.25rem;
        }
        .footer-column ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .footer-column a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.88rem;
            transition: color 0.2s;
        }
        .footer-column a:hover {
            color: var(--text-primary);
        }
        .contact-list {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }
        .contact-entry {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.88rem;
            color: var(--text-secondary);
        }
        .contact-entry i {
            color: var(--accent-amber);
            font-size: 1rem;
            width: 20px;
        }
        .social-row {
            display: flex;
            gap: 0.75rem;
            margin-top: 0.75rem;
        }
        .social-link {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-glass);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .social-link:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }
        .footer-bottom {
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.82rem;
            color: var(--text-muted);
            flex-wrap: wrap;
            gap: 1rem;
        }

        /* Comprehensive Mobile Responsiveness & Polish */
        @media (max-width: 992px) {
            .nav-links {
                display: none;
            }
            .hero-grid, .vs-grid, .ai-grid, .calculator-card, .footer-grid {
                grid-template-columns: minmax(0, 1fr);
                gap: 2.5rem;
                width: 100%;
                max-width: 100%;
            }
            .ai-feature-card:hover {
                transform: none !important;
            }
            .pricing-grid {
                grid-template-columns: 1fr 1fr;
                gap: 1.5rem;
            }
            .bento-grid {
                grid-template-columns: 1fr 1fr;
            }
            .hero-title {
                font-size: 2.5rem;
            }
            .calculator-card {
                padding: 2rem;
            }
            .command-bento-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        /* Tablet & Mobile Nav Header Re-layout */
        @media (max-width: 768px) {
            html {
                scroll-padding-top: 72px;
            }
            body {
                padding-top: 68px;
            }
            .site-header {
                top: 8px;
                padding: 0 0.5rem;
            }
            .nav-wrapper {
                min-height: 52px;
                height: 52px;
                padding: 0.35rem 0.65rem;
                border-radius: 999px;
                gap: 0.45rem;
            }
            .site-header.scrolled .nav-wrapper {
                min-height: 52px;
                height: 52px;
                padding: 0.35rem 0.65rem;
            }
            .brand-logo {
                gap: 0.4rem;
                flex-shrink: 0;
            }
            .brand-logo img,
            .landing-nav-logo {
                height: 36px !important;
                max-height: 36px !important;
                width: auto;
                max-width: 140px;
            }
            .site-header.scrolled .landing-nav-logo {
                height: 36px !important;
                max-height: 36px !important;
            }
            .logo-icon {
                width: 34px;
                height: 34px;
                font-size: 1rem;
                border-radius: 9px;
            }
            .brand-text {
                font-size: 1.05rem;
            }
            .nav-center-group {
                order: 3;
                margin-left: 0;
            }
            .nav-quick-pills {
                display: none;
            }
            .nav-burger-pill {
                width: 36px;
                height: 36px;
                min-width: 36px;
                padding: 0;
                border-radius: 10px;
            }
            .burger-bars-icon {
                width: 16px;
            }
            .nav-actions {
                order: 2;
                margin-left: auto;
                gap: 0.45rem;
            }
            .nav-actions .btn-login,
            .nav-actions .btn-register-top {
                display: none !important;
            }
            .landing-lang-segmented {
                display: none !important;
            }
            .landing-lang-dropdown {
                display: inline-block !important;
            }
        }

        /* Mobile Screens (Phones < 640px) */
        @media (max-width: 640px) {
            .container {
                padding: 0 1rem;
            }
            .section {
                padding: 3.5rem 0;
            }
            .section-header {
                margin-bottom: 2rem;
            }
            .section-badge {
                font-size: 0.74rem;
                padding: 0.3rem 0.75rem;
                margin-bottom: 0.75rem;
            }
            .section-title {
                font-size: 1.85rem;
                line-height: 1.25;
                letter-spacing: -0.5px;
                margin-bottom: 0.75rem;
            }
            .section-subtitle {
                font-size: 0.92rem;
                line-height: 1.55;
            }

            /* Hero Section */
            .hero-section {
                padding: 2.25rem 0 2rem;
            }
            .hero-grid {
                gap: 2rem;
            }
            .hero-badge-pill {
                font-size: 0.76rem;
                padding: 0.35rem 0.85rem;
                margin-bottom: 1rem;
                line-height: 1.35;
                max-width: 100%;
            }
            .hero-title {
                font-size: 1.9rem;
                line-height: 1.22;
                letter-spacing: -0.5px;
                margin-bottom: 1rem;
            }
            .hero-subtitle {
                font-size: 0.95rem;
                line-height: 1.55;
                margin-bottom: 1.75rem;
            }
            .hero-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
                margin-bottom: 1.75rem;
            }
            .btn-cta-primary,
            .btn-cta-secondary {
                width: 100%;
                justify-content: center;
                text-align: center;
                padding: 0.9rem 1.25rem;
                font-size: 0.98rem;
                border-radius: 14px;
            }
            .hero-trust-list {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.55rem;
            }
            .trust-item {
                font-size: 0.82rem;
            }

            /* iPhone Mockup Mobile Optimization */
            .iphone-mockup-outer {
                padding: 0;
                width: 100%;
            }
            .iphone-16-pro {
                width: 100%;
                max-width: 310px;
                margin: 0 auto;
                transform: none !important;
                border-radius: 46px;
                padding: 9px 9px 12px;
                box-shadow: 
                    0 0 0 1.5px rgba(255, 255, 255, 0.15),
                    inset 0 0 0 2px #0a0d16,
                    0 25px 60px -10px rgba(0, 0, 0, 0.9),
                    0 0 35px rgba(245, 158, 11, 0.15);
            }
            .iphone-16-pro:hover {
                transform: none !important;
            }
            .iphone-screen {
                border-radius: 38px;
                padding: 8px 10px 10px;
            }
            .dynamic-island {
                width: 90px;
                height: 22px;
                margin-bottom: 4px;
                padding: 0 7px;
            }
            .di-camera {
                width: 7px;
                height: 7px;
            }
            .di-sensor {
                width: 6px;
                height: 6px;
            }
            .iphone-status-bar {
                font-size: 0.7rem;
                padding: 0 2px 6px;
            }
            .app-dish-card {
                padding: 7px;
                border-radius: 13px;
                margin-bottom: 7px;
            }
            .app-dish-img {
                height: 90px;
                font-size: 2.2rem;
                border-radius: 9px;
                margin-bottom: 5px;
            }
            .app-dish-title {
                font-size: 0.82rem;
            }
            .app-dish-price {
                font-size: 0.78rem;
            }
            .ai-floating-bubble {
                padding: 0.65rem 0.75rem;
                border-radius: 13px;
                margin-bottom: 0.75rem;
            }
            .ai-bubble-tag {
                font-size: 0.65rem;
            }
            .ai-bubble-quote {
                font-size: 0.72rem;
                line-height: 1.35;
            }
            .phone-actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 0.4rem;
            }
            .phone-btn {
                padding: 0.55rem 0.25rem;
                font-size: 0.66rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 3px;
                border-radius: 8px;
            }
            .iphone-home-bar {
                width: 100px;
                height: 3.5px;
                margin-top: 6px;
            }

            /* Comparison Section */
            .vs-grid {
                grid-template-columns: 1fr;
                gap: 1.25rem;
            }
            .vs-card {
                padding: 1.35rem 1.15rem;
                border-radius: 20px;
            }
            .vs-card-title {
                font-size: 1.15rem;
            }
            .vs-list {
                gap: 0.75rem;
            }
            .vs-list li {
                font-size: 0.88rem;
                gap: 0.65rem;
                align-items: flex-start;
            }

            /* AI Waiter Section */
            #ai-waiter {
                overflow: hidden;
                width: 100%;
                max-width: 100%;
            }
            .ai-grid {
                grid-template-columns: minmax(0, 1fr) !important;
                gap: 1.5rem;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box;
            }
            .ai-features-column {
                gap: 0.85rem;
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                box-sizing: border-box;
            }
            .ai-feature-card {
                padding: 1.15rem 1rem;
                border-radius: 18px;
                gap: 0.85rem;
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                box-sizing: border-box;
                transform: none !important;
            }
            .ai-feature-card:hover {
                transform: none !important;
            }
            .ai-feature-icon {
                width: 42px;
                height: 42px;
                font-size: 1.05rem;
                border-radius: 12px;
                flex-shrink: 0;
            }
            .ai-feature-title {
                font-size: 1.02rem;
                margin-bottom: 0.25rem;
                word-break: break-word;
            }
            .ai-feature-desc {
                font-size: 0.85rem;
                line-height: 1.5;
                word-break: break-word;
            }
            .simulator-box {
                padding: 1.25rem 1rem;
                border-radius: 20px;
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                box-sizing: border-box;
                overflow: hidden;
            }
            .sim-header {
                flex-wrap: wrap;
                gap: 0.5rem;
                margin-bottom: 1.15rem;
                width: 100%;
                box-sizing: border-box;
            }
            .sim-title {
                font-size: 1.02rem;
                word-break: break-word;
                flex: 1 1 auto;
                min-width: 0;
            }
            .sim-title span {
                word-break: break-word;
                overflow-wrap: break-word;
                min-width: 0;
            }
            .dish-tabs {
                display: flex;
                gap: 0.4rem;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 4px;
                scrollbar-width: none;
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }
            .dish-tabs::-webkit-scrollbar {
                display: none;
            }
            .dish-tab-btn {
                white-space: nowrap;
                font-size: 0.78rem;
                padding: 0.45rem 0.75rem;
                flex-shrink: 0;
            }
            .sim-output-card {
                padding: 1.15rem 0.95rem;
                border-radius: 16px;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box;
                overflow: hidden;
            }
            .sim-recommendation-text {
                font-size: 0.85rem;
                line-height: 1.5;
                word-break: break-word;
            }
            .sim-upsell-stat {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.35rem;
                font-size: 0.75rem;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box;
            }

            /* ROI Calculator */
            .calculator-card {
                padding: 1.35rem 1rem;
                border-radius: 22px;
                gap: 1.5rem;
            }
            .calc-main-num {
                font-size: 2.2rem;
                margin: 0.25rem 0;
            }
            .calc-sub-metrics {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 0.65rem;
                margin: 1.15rem 0 1.35rem;
            }
            .calc-metric-item {
                padding: 0.75rem 0.5rem;
                border-radius: 12px;
            }
            .calc-metric-value {
                font-size: 1.15rem;
            }
            .calc-metric-label {
                font-size: 0.72rem;
            }
            .btn-calc-cta {
                padding: 0.85rem;
                font-size: 0.95rem;
                width: 100%;
                justify-content: center;
            }

            /* Bento Grid */
            .bento-grid {
                grid-template-columns: 1fr;
                gap: 0.85rem;
            }
            .bento-card {
                padding: 1.35rem 1.15rem;
                border-radius: 18px;
            }
            .bento-card h3 {
                font-size: 1.1rem;
                margin-bottom: 0.35rem;
            }
            .bento-card p {
                font-size: 0.86rem;
                line-height: 1.5;
            }

            /* Pricing */
            .pricing-grid {
                grid-template-columns: 1fr;
                gap: 1.25rem;
            }
            .pricing-card {
                padding: 1.75rem 1.25rem;
                border-radius: 22px;
            }
            .plan-price-num {
                font-size: 2.2rem;
            }
            .plan-price-box {
                margin: 1.25rem 0;
            }
            .plan-features-list {
                gap: 0.7rem;
                margin-bottom: 1.75rem;
            }
            .plan-feature-item {
                font-size: 0.88rem;
            }
            .btn-plan-cta {
                padding: 0.85rem;
                font-size: 0.95rem;
                width: 100%;
            }

            /* FAQ Accordion */
            .faq-accordion {
                gap: 0.65rem;
            }
            .faq-item {
                border-radius: 14px;
            }
            .faq-question {
                padding: 1.05rem 1.15rem;
                font-size: 0.95rem;
                gap: 0.65rem;
            }
            .faq-answer {
                font-size: 0.88rem;
                line-height: 1.55;
            }
            .faq-item.open .faq-answer {
                padding-bottom: 1.15rem;
            }

            /* Final CTA Banner */
            .final-cta-section {
                padding: 2.75rem 0;
            }
            .final-cta-card {
                padding: 2.25rem 1.15rem;
                border-radius: 24px;
            }
            .final-cta-title {
                font-size: 1.75rem;
                line-height: 1.25;
                margin-bottom: 0.85rem;
            }
            .final-cta-subtitle {
                font-size: 0.92rem;
                line-height: 1.55;
                margin-bottom: 1.5rem;
            }
            .final-cta-buttons {
                display: flex;
                flex-direction: column;
                width: 100%;
                gap: 0.75rem;
            }
            .final-cta-buttons .btn-cta-primary {
                width: 100%;
                padding: 0.95rem 1.25rem !important;
                font-size: 1.02rem !important;
                justify-content: center;
            }
            .final-cta-social-group {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 0.65rem;
                width: 100%;
            }
            .btn-whatsapp,
            .btn-telegram {
                width: 100%;
                justify-content: center;
                padding: 0.85rem 0.5rem;
                font-size: 0.85rem;
            }

            /* Footer */
            .site-footer {
                padding: 3rem 0 1.75rem;
            }
            .footer-grid {
                grid-template-columns: 1fr;
                gap: 1.75rem;
                margin-bottom: 2rem;
            }
            .footer-brand p {
                max-width: 100%;
                font-size: 0.85rem;
            }
            .footer-column h4 {
                margin-bottom: 0.85rem;
            }
            .footer-column ul {
                gap: 0.6rem;
            }
            .footer-bottom {
                flex-direction: column;
                text-align: center;
                gap: 0.65rem;
                font-size: 0.78rem;
            }

            /* Command Center Navigation Modal */
            .command-menu-overlay {
                padding: 0.75rem 0.5rem;
            }
            .command-menu-modal {
                padding: 1.25rem 0.95rem;
                border-radius: 22px;
                max-height: 92vh;
            }
            .command-modal-header {
                padding-bottom: 0.85rem;
                margin-bottom: 1rem;
            }
            .command-bento-grid {
                grid-template-columns: 1fr;
                gap: 0.6rem;
                margin-bottom: 1.15rem;
            }
            .command-bento-card {
                padding: 0.85rem;
                border-radius: 14px;
                gap: 0.75rem;
            }
            .command-icon-box {
                width: 36px;
                height: 36px;
                font-size: 0.95rem;
                border-radius: 10px;
            }
            .command-card-title {
                font-size: 0.88rem;
            }
            .command-card-desc {
                font-size: 0.74rem;
            }
            .command-modal-footer {
                gap: 0.75rem;
                padding-top: 0.95rem;
            }
            .esc-badge {
                display: none !important;
            }
        }

        /* Ultra-small phones (< 390px) */
        @media (max-width: 390px) {
            .nav-wrapper {
                padding: 0.3rem 0.5rem;
                gap: 0.35rem;
            }
            .brand-logo img,
            .landing-nav-logo {
                height: 32px !important;
                max-height: 32px !important;
                max-width: 120px;
            }
            .brand-text {
                font-size: 0.95rem;
            }
            .landing-lang-trigger {
                height: 34px;
                padding: 0 0.5rem;
                font-size: 0.74rem;
            }
            .nav-burger-pill {
                width: 34px;
                height: 34px;
                min-width: 34px;
            }
            .hero-title {
                font-size: 1.7rem;
            }
            .section-title {
                font-size: 1.6rem;
            }
            .final-cta-social-group {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <!-- Ambient Glow Blobs -->
    <div class="ambient-glow-1"></div>
    <div class="ambient-glow-2"></div>

    <!-- 1. Top Floating Capsule Header -->
    <header class="site-header" id="siteHeader">
        <div class="container">
            <div class="nav-wrapper">
                <a href="{{ route('landing') }}" class="brand-logo" style="text-decoration: none; display: flex; align-items: center; gap: 0.65rem;">
                    @if($siteLogoDark)
                        <img src="{{ $siteLogoDark }}" alt="{{ $siteName }}" class="landing-nav-logo">
                    @else
                        <div class="logo-icon">
                            <i class="fa-solid fa-qrcode"></i>
                        </div>
                        <div class="brand-text">{{ $siteName }}</div>
                    @endif
                </a>

                <!-- Center UI: Burger Capsule & Quick Jumps -->
                <div class="nav-center-group">
                    <button type="button" class="nav-burger-pill" id="commandMenuBtn" onclick="toggleCommandDrawer()" aria-label="Menu">
                        <span class="burger-bars-icon">
                            <span></span>
                            <span></span>
                            <span></span>
                        </span>
                    </button>

                    <div class="nav-quick-pills">
                        <a href="#comparison" class="quick-nav-pill"><i class="fa-solid fa-code-compare" style="color: #06b6d4;"></i> {{ $cms('vs_badge', __('nav_comparison')) }}</a>
                        <a href="#ai-waiter" class="quick-nav-pill"><i class="fa-solid fa-robot" style="color: #a78bfa;"></i> AI</a>
                        <a href="#calculator" class="quick-nav-pill"><i class="fa-solid fa-calculator" style="color: #f59e0b;"></i> ROI</a>
                        <a href="#pricing" class="quick-nav-pill"><i class="fa-solid fa-tag" style="color: #10b981;"></i> {{ $cms('pricing_badge', __('nav_pricing')) }}</a>
                    </div>
                </div>

                <div class="nav-actions">
                    <!-- Desktop Segmented Language Switcher -->
                    <div class="lang-switch landing-lang-segmented" id="landingLangSwitch">
                        <a href="{{ route('lang.switch', 'hy') }}" class="lang-btn {{ $loc === 'hy' ? 'active' : '' }}" title="Հայերեն">🇦🇲 ՀԱՅ</a>
                        <a href="{{ route('lang.switch', 'ru') }}" class="lang-btn {{ $loc === 'ru' ? 'active' : '' }}" title="Русский">🇷🇺 РУС</a>
                        <a href="{{ route('lang.switch', 'en') }}" class="lang-btn {{ $loc === 'en' ? 'active' : '' }}" title="English">🇬🇧 ENG</a>
                    </div>

                    <!-- Mobile Compact Language Dropdown -->
                    <div class="landing-lang-dropdown" id="landingLangDropdown">
                        <button type="button" class="landing-lang-trigger" id="landingLangTrigger" onclick="toggleLandingLang(event)" aria-label="Select Language" aria-expanded="false">
                            <span class="active-flag">
                                @if($loc === 'hy') 🇦🇲
                                @elseif($loc === 'ru') 🇷🇺
                                @else 🇬🇧
                                @endif
                            </span>
                            <span class="active-code">
                                @if($loc === 'hy') ՀԱՅ
                                @elseif($loc === 'ru') РУС
                                @else ENG
                                @endif
                            </span>
                            <i class="fa-solid fa-chevron-down lang-chevron"></i>
                        </button>
                        <div class="landing-lang-menu" id="landingLangMenu">
                            <a href="{{ route('lang.switch', 'hy') }}" class="landing-lang-option {{ $loc === 'hy' ? 'active' : '' }}">
                                <span class="lang-option-flag">🇦🇲</span>
                                <span class="lang-option-title">Հայերեն</span>
                                @if($loc === 'hy') <i class="fa-solid fa-check active-check"></i> @endif
                            </a>
                            <a href="{{ route('lang.switch', 'ru') }}" class="landing-lang-option {{ $loc === 'ru' ? 'active' : '' }}">
                                <span class="lang-option-flag">🇷🇺</span>
                                <span class="lang-option-title">Русский</span>
                                @if($loc === 'ru') <i class="fa-solid fa-check active-check"></i> @endif
                            </a>
                            <a href="{{ route('lang.switch', 'en') }}" class="landing-lang-option {{ $loc === 'en' ? 'active' : '' }}">
                                <span class="lang-option-flag">🇬🇧</span>
                                <span class="lang-option-title">English</span>
                                @if($loc === 'en') <i class="fa-solid fa-check active-check"></i> @endif
                            </a>
                        </div>
                    </div>

                    @auth
                        @if(Auth::user()->isSuperAdmin())
                            <a href="{{ route('superadmin.dashboard') }}" class="btn-register-top">
                                <i class="fa-solid fa-gauge-high"></i> SuperAdmin
                            </a>
                        @else
                            <a href="{{ route('admin.dashboard') }}" class="btn-register-top">
                                <i class="fa-solid fa-gauge-high"></i> {{ __('Dashboard') }}
                            </a>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="btn-login">{{ __('nav_login') }}</a>
                        <a href="{{ route('register.show') }}" class="btn-register-top">
                            {{ __('nav_register_cta') }}
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </header>

    <!-- Command Center Navigation Drawer / Modal -->
    <div id="commandMenuDrawer" class="command-menu-overlay" onclick="handleDrawerOverlayClick(event)">
        <div class="command-menu-modal" role="dialog" aria-modal="true" aria-labelledby="commandModalTitle">
            <div class="command-modal-header">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div class="logo-icon" style="width: 32px; height: 32px; font-size: 0.95rem;">
                        <i class="fa-solid fa-compass"></i>
                    </div>
                    <div>
                        <div id="commandModalTitle" style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 800; color: #fff;">
                            {{ $tr('Համակարգի Բաժիններ', 'Разделы и Инструменты', 'Navigation & Services') }}
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-secondary);">
                            {{ $tr('Արագ անցում դեպի լենդինգի գլխավոր հնարավորությունները', 'Быстрый переход к ключевым возможностям системы', 'Fast-track to key features and tools') }}
                        </div>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <span class="esc-badge" style="font-size: 0.72rem; color: var(--text-muted); background: rgba(255, 255, 255, 0.05); padding: 4px 8px; border-radius: 6px; border: 1px solid rgba(255, 255, 255, 0.1);">ESC</span>
                    <button type="button" class="command-modal-close" onclick="closeCommandDrawer()" aria-label="Close Navigation">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <!-- Bento Navigation Tiles -->
            <div class="command-bento-grid">
                <a href="#comparison" class="command-bento-card" onclick="closeCommandDrawer()">
                    <div class="command-icon-box" style="background: rgba(6, 182, 212, 0.15); color: #06b6d4;">
                        <i class="fa-solid fa-code-compare"></i>
                    </div>
                    <div>
                        <div class="command-card-title">{{ $cms('vs_badge', __('nav_comparison')) }}</div>
                        <p class="command-card-desc">{{ $tr('Թղթե մենյուի և թվային QR մենյուի համեմատություն', 'Сравнение бумажного меню и умного QR-меню', 'Paper vs Intelligent QR Menu comparison') }}</p>
                    </div>
                </a>

                <a href="#ai-waiter" class="command-bento-card" onclick="closeCommandDrawer()">
                    <div class="command-icon-box" style="background: rgba(139, 92, 246, 0.15); color: #a78bfa;">
                        <i class="fa-solid fa-robot"></i>
                    </div>
                    <div>
                        <div class="command-card-title">{{ $cms('ai_section_badge', __('nav_ai')) }}</div>
                        <p class="command-card-desc">{{ $tr('Խելացի AI մատուցող & սոմելիե, ավտոմատ upsell', 'Умный AI официант и сомелье, автоматический upsell', 'AI Waiter & Sommelier cross-selling simulator') }}</p>
                    </div>
                </a>

                <a href="#calculator" class="command-bento-card" onclick="closeCommandDrawer()">
                    <div class="command-icon-box" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                        <i class="fa-solid fa-calculator"></i>
                    </div>
                    <div>
                        <div class="command-card-title">{{ $cms('calc_badge', __('nav_calculator')) }}</div>
                        <p class="command-card-desc">{{ $tr('Հաշվեք հավելյալ շահույթն ու տնտեսումները', 'Рассчитайте рост прибыли и экономию на печати', 'Estimate your monthly profit lift and print savings') }}</p>
                    </div>
                </a>

                <a href="#features" class="command-bento-card" onclick="closeCommandDrawer()">
                    <div class="command-icon-box" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                    <div>
                        <div class="command-card-title">{{ $cms('features_badge', __('nav_features')) }}</div>
                        <p class="command-card-desc">{{ $tr('KDS խոհանոց, կանչ, բազմալեզվություն & QR ստուդիա', 'PWA, кухонный KDS, вызов официанта и QR-студия', 'PWA, Kitchen Display, Waiter Call & QR Studio') }}</p>
                    </div>
                </a>

                <a href="#pricing" class="command-bento-card" onclick="closeCommandDrawer()">
                    <div class="command-icon-box" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                        <i class="fa-solid fa-tag"></i>
                    </div>
                    <div>
                        <div class="command-card-title">{{ $cms('pricing_badge', __('nav_pricing')) }}</div>
                        <p class="command-card-desc">{{ $tr('Սակագնային պլաններ և 14 օր անվճար փորձաշրջան', 'Тарифные планы и 14 дней бесплатного периода', 'Transparent pricing plans with free 14-day trial') }}</p>
                    </div>
                </a>

                <a href="#faq" class="command-bento-card" onclick="closeCommandDrawer()">
                    <div class="command-icon-box" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;">
                        <i class="fa-solid fa-circle-question"></i>
                    </div>
                    <div>
                        <div class="command-card-title">{{ $cms('faq_badge', __('nav_faq')) }}</div>
                        <p class="command-card-desc">{{ $tr('Հաճախ տրվող հարցերի պատասխաններ', 'Ответы на частые вопросы и детали интеграции', 'Frequently asked questions & integration details') }}</p>
                    </div>
                </a>

                <a href="#contacts" class="command-bento-card" onclick="closeCommandDrawer()" style="grid-column: 1 / -1;">
                    <div class="command-icon-box" style="background: rgba(236, 72, 153, 0.15); color: #ec4899;">
                        <i class="fa-solid fa-headset"></i>
                    </div>
                    <div>
                        <div class="command-card-title">{{ __('footer_contact_us') }}</div>
                        <p class="command-card-desc">{{ $tr('Կապ մեր մասնագետների հետ, WhatsApp & Telegram խորհրդատվություն', 'Связь с экспертами, консультация в WhatsApp и Telegram', 'Connect with our team directly via WhatsApp or Telegram') }}</p>
                    </div>
                </a>
            </div>

            <!-- Drawer Footer Quick Actions -->
            <div class="command-modal-footer">
                @php
                    $rawPhone = $settings['contact_phone'] ?? '+37455776066';
                    $cleanPhone = preg_replace('/[^0-9]/', '', $settings['contact_whatsapp'] ?? $rawPhone);
                    $tgHandle = str_replace('@', '', $settings['contact_telegram'] ?? '+37455776066');
                @endphp
                <div class="drawer-contact-grid">
                    <a href="https://wa.me/{{ $cleanPhone }}" target="_blank" class="drawer-contact-btn btn-wa">
                        <i class="fa-brands fa-whatsapp"></i> WhatsApp
                    </a>
                    <a href="https://t.me/{{ $tgHandle }}" target="_blank" class="drawer-contact-btn btn-tg">
                        <i class="fa-brands fa-telegram"></i> Telegram
                    </a>
                </div>

                <div class="drawer-cta-wrap">
                    @auth
                        @if(Auth::user()->isSuperAdmin())
                            <a href="{{ route('superadmin.dashboard') }}" class="drawer-btn-cta">
                                <i class="fa-solid fa-gauge-high"></i> SuperAdmin <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        @else
                            <a href="{{ route('admin.dashboard') }}" class="drawer-btn-cta">
                                <i class="fa-solid fa-gauge-high"></i> {{ __('Dashboard') }} <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        @endif
                    @else
                        <a href="{{ route('register.show') }}" class="drawer-btn-cta">
                            {{ __('nav_register_cta') }} <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    @endauth
                </div>

                <div class="drawer-bottom-bar">
                    <div class="lang-switch drawer-lang-switch">
                        <a href="{{ route('lang.switch', 'hy') }}" class="lang-btn {{ $loc === 'hy' ? 'active' : '' }}" title="Հայերեն">🇦🇲 ՀԱՅ</a>
                        <a href="{{ route('lang.switch', 'ru') }}" class="lang-btn {{ $loc === 'ru' ? 'active' : '' }}" title="Русский">🇷🇺 РУС</a>
                        <a href="{{ route('lang.switch', 'en') }}" class="lang-btn {{ $loc === 'en' ? 'active' : '' }}" title="English">🇬🇧 ENG</a>
                    </div>
                    @guest
                        <a href="{{ route('login') }}" class="drawer-login-link">
                            <i class="fa-solid fa-arrow-right-to-bracket"></i> {{ __('nav_login') }}
                        </a>
                    @endguest
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Hero Section (Hook & Value Prop) -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-grid">
                <div>
                    <div class="hero-badge-pill">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                        <span>{{ $cms('hero_badge', __('hero_badge')) }}</span>
                    </div>

                    <h1 class="hero-title">
                        {!! $cms('hero_title', ($loc === 'hy' 
                            ? 'Ավելացրեք ռեստորանի շրջանառությունը <span class="gradient-text">+30%-ով</span> խելացի QR մենյուի և AI-ի շնորհիվ' 
                            : ($loc === 'ru'
                                ? 'Увеличьте оборот ресторана на <span class="gradient-text">+30%</span> благодаря умному QR-меню и AI'
                                : 'Boost Restaurant Revenue by <span class="gradient-text">+30%</span> with Smart QR Menu & AI Waiter'))) !!}
                    </h1>

                    <p class="hero-subtitle">
                        {{ $cms('hero_subtitle', __('hero_subtitle')) }}
                    </p>

                    <div class="hero-actions">
                        <a href="{{ route('register.show') }}" class="btn-cta-primary">
                            <span>{{ $cms('hero_cta_primary', __('hero_cta_primary')) }}</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>

                        @php
                            $demoSlug = $settings['demo_vendor_slug'] ?? 'bistro-yerevan';
                        @endphp
                        <a href="{{ route('client.menu', ['vendor_slug' => $demoSlug]) }}" target="_blank" class="btn-cta-secondary">
                            <i class="fa-solid fa-mobile-screen-button"></i>
                            <span>{{ $cms('hero_cta_secondary', __('hero_cta_secondary')) }}</span>
                        </a>
                    </div>

                    <div class="hero-trust-list">
                        <div class="trust-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>{{ $cms('hero_trust_badge1', __('hero_trust_badge1')) }}</span>
                        </div>
                        <div class="trust-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>{{ $cms('hero_trust_badge2', __('hero_trust_badge2')) }}</span>
                        </div>
                        <div class="trust-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>{{ $cms('hero_trust_badge3', __('hero_trust_badge3')) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Realistic iPhone 16 Pro Mockup Visual -->
                <div class="mockup-wrapper">
                    <div class="iphone-mockup-outer">
                        <div class="iphone-16-pro">
                            <!-- Physical Side Hardware Buttons -->
                            <div class="iphone-hw-button iphone-btn-action"></div>
                            <div class="iphone-hw-button iphone-btn-vol-up"></div>
                            <div class="iphone-hw-button iphone-btn-vol-down"></div>
                            <div class="iphone-hw-button iphone-btn-power"></div>

                            <!-- Screen Glass -->
                            <div class="iphone-screen">
                                <div class="iphone-glare"></div>

                                <!-- Dynamic Island -->
                                <div class="dynamic-island">
                                    <span class="di-camera"></span>
                                    <span class="di-sensor"></span>
                                </div>

                                <!-- iOS Status Bar -->
                                <div class="iphone-status-bar">
                                    <span>9:41</span>
                                    <div class="status-icons">
                                        <i class="fa-solid fa-signal"></i>
                                        <i class="fa-solid fa-wifi"></i>
                                        <div class="battery-pill"><span class="battery-fill"></span></div>
                                    </div>
                                </div>

                                <!-- In-Screen Digital Menu App UI -->
                                <div class="iphone-app-header">
                                    <div class="app-venue-badge">
                                        <div class="venue-avatar">
                                            <i class="fa-solid fa-utensils"></i>
                                        </div>
                                        <div>
                                            <div style="font-size: 0.8rem; font-weight: 800; color: #fff;">{{ $cms('hero_mockup_restaurant', __('hero_mockup_restaurant')) }}</div>
                                            <div style="font-size: 0.65rem; color: var(--text-secondary);">{{ __('Table #04 • Dine-In') }}</div>
                                        </div>
                                    </div>
                                    <span class="app-table-pill">
                                        <span class="pulse-dot"></span> Live
                                    </span>
                                </div>

                                <!-- Category Chips -->
                                <div class="app-categories">
                                    <span class="app-cat-chip active">{{ $loc === 'hy' ? '🔥 Թոփ Մսային' : ($loc === 'ru' ? '🔥 Стейки Прайм' : '🔥 Top Steaks') }}</span>
                                    <span class="app-cat-chip">{{ $loc === 'hy' ? '🍷 Գինիներ' : ($loc === 'ru' ? '🍷 Вина' : '🍷 Wines') }}</span>
                                    <span class="app-cat-chip">{{ $loc === 'hy' ? '🥗 Աղցաններ' : ($loc === 'ru' ? '🥗 Салаты' : '🥗 Salads') }}</span>
                                </div>

                                <!-- Dish Card Inside iPhone -->
                                <div class="app-dish-card">
                                    <div class="app-dish-img">
                                        🥩
                                        <div class="app-dish-rating">
                                            <i class="fa-solid fa-star"></i> 4.9
                                        </div>
                                    </div>
                                    <div class="app-dish-title">{{ __('Ribeye Prime Angus Steak') }}</div>
                                    <div class="app-dish-price">8 900 ֏</div>
                                </div>

                                <!-- Floating AI Waiter Bubble -->
                                <div class="ai-floating-bubble">
                                    <div class="ai-bubble-tag">
                                        <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('AI Waiter Suggestion') }}
                                    </div>
                                    <div class="ai-bubble-quote">
                                        {{ $cms('hero_mockup_ai_bubble', __('hero_mockup_ai_bubble')) }}
                                    </div>
                                </div>

                                <!-- Phone Order Actions -->
                                <div class="phone-actions">
                                    <button type="button" class="phone-btn"><i class="fa-solid fa-bag-shopping"></i> {{ $cms('hero_mockup_add_order', __('hero_mockup_add_order')) }}</button>
                                    <button type="button" class="phone-btn phone-btn-alt"><i class="fa-solid fa-bell"></i> {{ $cms('hero_mockup_call_waiter', __('hero_mockup_call_waiter')) }}</button>
                                </div>

                                <!-- Home Indicator Bar -->
                                <div class="iphone-home-bar"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. Comparison Section (Paper Menu vs elab QR Menu) -->
    <section id="comparison" class="section">
        <div class="container">
            <div class="section-header">
                <div class="section-badge">{{ $cms('vs_badge', __('nav_comparison')) }}</div>
                <h2 class="section-title">{{ $cms('vs_title', __('vs_title')) }}</h2>
                <p class="section-subtitle">{{ $cms('vs_subtitle', __('vs_subtitle')) }}</p>
            </div>

            <div class="vs-grid">
                <!-- Old Paper Menu -->
                <div class="vs-card vs-card-paper">
                    <div class="vs-card-head">
                        <h3 class="vs-card-title">{{ $cms('vs_paper_title', __('vs_paper_title')) }}</h3>
                        <span class="vs-badge-bad">{{ $cms('vs_paper_badge', __('vs_paper_badge')) }}</span>
                    </div>
                    <ul class="vs-list">
                        <li>
                            <i class="fa-solid fa-xmark vs-icon-bad"></i>
                            <span>{{ $cms('vs_paper_item1', __('vs_paper_item1')) }}</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-xmark vs-icon-bad"></i>
                            <span>{{ $cms('vs_paper_item2', __('vs_paper_item2')) }}</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-xmark vs-icon-bad"></i>
                            <span>{{ $cms('vs_paper_item3', __('vs_paper_item3')) }}</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-xmark vs-icon-bad"></i>
                            <span>{{ $cms('vs_paper_item4', __('vs_paper_item4')) }}</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-xmark vs-icon-bad"></i>
                            <span>{{ $cms('vs_paper_item5', __('vs_paper_item5')) }}</span>
                        </li>
                    </ul>
                </div>

                <!-- elab Digital QR Menu -->
                <div class="vs-card vs-card-qr">
                    <div class="vs-card-head">
                        <h3 class="vs-card-title">{{ $cms('vs_qr_title', __('vs_qr_title')) }}</h3>
                        <span class="vs-badge-good">{{ $cms('vs_qr_badge', __('vs_qr_badge')) }}</span>
                    </div>
                    <ul class="vs-list">
                        <li>
                            <i class="fa-solid fa-check vs-icon-good"></i>
                            <span>{{ $cms('vs_qr_item1', __('vs_qr_item1')) }}</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-check vs-icon-good"></i>
                            <span>{{ $cms('vs_qr_item2', __('vs_qr_item2')) }}</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-check vs-icon-good"></i>
                            <span>{{ $cms('vs_qr_item3', __('vs_qr_item3')) }}</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-check vs-icon-good"></i>
                            <span>{{ $cms('vs_qr_item4', __('vs_qr_item4')) }}</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-check vs-icon-good"></i>
                            <span>{{ $cms('vs_qr_item5', __('vs_qr_item5')) }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. AI Waiter & Sommelier Feature Section -->
    <section id="ai-waiter" class="section">
        <div class="container">
            <div class="section-header">
                <div class="section-badge">{{ $cms('ai_section_badge', __('ai_section_badge')) }}</div>
                <h2 class="section-title">{{ $cms('ai_section_title', __('ai_section_title')) }}</h2>
                <p class="section-subtitle">{{ $cms('ai_section_subtitle', __('ai_section_subtitle')) }}</p>
            </div>

            <div class="ai-grid">
                <div class="ai-features-column">
                    <div class="ai-feature-card">
                        <div class="ai-feature-icon">
                            <i class="fa-solid fa-wine-glass"></i>
                        </div>
                        <div>
                            <h3 class="ai-feature-title">{{ $cms('ai_feature1_title', __('ai_feature1_title')) }}</h3>
                            <p class="ai-feature-desc">{{ $cms('ai_feature1_desc', __('ai_feature1_desc')) }}</p>
                        </div>
                    </div>

                    <div class="ai-feature-card">
                        <div class="ai-feature-icon">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <div>
                            <h3 class="ai-feature-title">{{ $cms('ai_feature2_title', __('ai_feature2_title')) }}</h3>
                            <p class="ai-feature-desc">{{ $cms('ai_feature2_desc', __('ai_feature2_desc')) }}</p>
                        </div>
                    </div>

                    <div class="ai-feature-card">
                        <div class="ai-feature-icon">
                            <i class="fa-solid fa-language"></i>
                        </div>
                        <div>
                            <h3 class="ai-feature-title">{{ $cms('ai_feature3_title', __('ai_feature3_title')) }}</h3>
                            <p class="ai-feature-desc">{{ $cms('ai_feature3_desc', __('ai_feature3_desc')) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Interactive AI Simulator -->
                <div class="simulator-box">
                    <div class="sim-header">
                        <div class="sim-title">
                            <i class="fa-solid fa-robot"></i>
                            <span>{{ __('ai_interactive_demo_title') }}</span>
                        </div>
                        <span style="font-size: 0.72rem; background: rgba(139, 92, 246, 0.2); color: #c084fc; padding: 2px 8px; border-radius: 12px; font-weight: 700;">LIVE SIM</span>
                    </div>

                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">
                        {{ __('ai_select_dish_label') }}
                    </p>

                    <div class="dish-tabs">
                        <button class="dish-tab-btn active" onclick="switchDish('steak')">{{ __('ai_dish_steak') }}</button>
                        <button class="dish-tab-btn" onclick="switchDish('pizza')">{{ __('ai_dish_pizza') }}</button>
                        <button class="dish-tab-btn" onclick="switchDish('salad')">{{ __('ai_dish_salad') }}</button>
                        <button class="dish-tab-btn" onclick="switchDish('dessert')">{{ __('ai_dish_dessert') }}</button>
                    </div>

                    <div class="sim-output-card">
                        <div class="sim-recommendation-label">
                            <i class="fa-solid fa-sparkles"></i> {{ __('ai_demo_recommends') }}
                        </div>
                        <div id="sim-text" class="sim-recommendation-text">
                            @if(app()->getLocale() === 'hy')
                                «Տավարի Սթեյքի հյութեղությունը հիանալի կընդգծի Areni Noir պահուստային կարմիր գինին (3 200 ֏) և ջեռոցում բոված հազարաթերթ բանջարեղենը։»
                            @else
                                "To elevate this Prime Ribeye, we recommend pairing it with a glass of Areni Noir Reserve Red Wine and char-grilled asparagus."
                            @endif
                        </div>
                        <div class="sim-upsell-stat">
                            <span><i class="fa-solid fa-arrow-trend-up"></i> +32% Average Check Lift</span>
                            <span>Automatic Cross-Sell</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 5. Interactive ROI & Savings Calculator -->
    <section id="calculator" class="section">
        <div class="container">
            <div class="section-header">
                <div class="section-badge">{{ $cms('calc_badge', __('nav_calculator')) }}</div>
                <h2 class="section-title">{{ $cms('calc_title', __('calc_title')) }}</h2>
                <p class="section-subtitle">{{ $cms('calc_subtitle', __('calc_subtitle')) }}</p>
            </div>

            <div class="calculator-card">
                <div class="calc-inputs">
                    <div class="calc-group">
                        <div class="calc-label-row">
                            <span>{{ __('calc_tables_label') }}</span>
                            <span id="tables-val" class="calc-value-display">20</span>
                        </div>
                        <input type="range" id="tables-slider" class="calc-slider" min="5" max="80" value="20" step="1" oninput="calculateROI()">
                    </div>

                    <div class="calc-group">
                        <div class="calc-label-row">
                            <span>{{ __('calc_guests_label') }}</span>
                            <span id="guests-val" class="calc-value-display">8</span>
                        </div>
                        <input type="range" id="guests-slider" class="calc-slider" min="2" max="25" value="8" step="1" oninput="calculateROI()">
                    </div>

                    <div class="calc-group">
                        <div class="calc-label-row">
                            <span>{{ __('calc_check_label') }}</span>
                            <span id="check-val" class="calc-value-display">8 500 ֏</span>
                        </div>
                        <input type="range" id="check-slider" class="calc-slider" min="3000" max="30000" value="8500" step="500" oninput="calculateROI()">
                    </div>
                </div>

                <div class="calc-results-box">
                    <div class="calc-res-title">{{ __('calc_result_title') }}</div>
                    <div id="calc-revenue" class="calc-main-num">+420 000 ֏</div>
                    <div class="calc-main-desc">{{ __('calc_extra_revenue') }}</div>

                    <div class="calc-sub-metrics">
                        <div class="calc-metric-item">
                            <div id="calc-print" class="calc-metric-value">360 000 ֏</div>
                            <div class="calc-metric-label">{{ __('calc_print_saved') }}</div>
                        </div>
                        <div class="calc-metric-item">
                            <div class="calc-metric-value">2.2x</div>
                            <div class="calc-metric-label">{{ __('calc_table_turnover') }}</div>
                        </div>
                    </div>

                    <a href="{{ route('register.show') }}" class="btn-calc-cta">
                        {{ $cms('calc_cta', __('calc_cta')) }} <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. Features Grid (Bento Style) -->
    <section id="features" class="section">
        <div class="container">
            <div class="section-header">
                <div class="section-badge">{{ $cms('features_badge', __('nav_features')) }}</div>
                <h2 class="section-title">{{ $cms('features_title', __('features_title')) }}</h2>
                <p class="section-subtitle">{{ $cms('features_subtitle', __('features_subtitle')) }}</p>
            </div>

            <div class="bento-grid">
                <div class="bento-card">
                    <div>
                        <div class="bento-icon-circle" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                            <i class="fa-solid fa-mobile-screen"></i>
                        </div>
                        <h3>{{ $cms('feat_pwa_title', __('feat_pwa_title')) }}</h3>
                        <p>{{ $cms('feat_pwa_desc', __('feat_pwa_desc')) }}</p>
                    </div>
                </div>

                <div class="bento-card">
                    <div>
                        <div class="bento-icon-circle" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                            <i class="fa-solid fa-bell-concierge"></i>
                        </div>
                        <h3>{{ $cms('feat_kds_title', __('feat_kds_title')) }}</h3>
                        <p>{{ $cms('feat_kds_desc', __('feat_kds_desc')) }}</p>
                    </div>
                </div>

                <div class="bento-card">
                    <div>
                        <div class="bento-icon-circle" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                            <i class="fa-solid fa-hand"></i>
                        </div>
                        <h3>{{ $cms('feat_call_title', __('feat_call_title')) }}</h3>
                        <p>{{ $cms('feat_call_desc', __('feat_call_desc')) }}</p>
                    </div>
                </div>

                <div class="bento-card">
                    <div>
                        <div class="bento-icon-circle" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">
                            <i class="fa-solid fa-wand-magic-sparkles"></i>
                        </div>
                        <h3>{{ $cms('feat_ai_import_title', __('feat_ai_import_title')) }}</h3>
                        <p>{{ $cms('feat_ai_import_desc', __('feat_ai_import_desc')) }}</p>
                    </div>
                </div>

                <div class="bento-card">
                    <div>
                        <div class="bento-icon-circle" style="background: rgba(6, 182, 212, 0.15); color: #06b6d4;">
                            <i class="fa-solid fa-palette"></i>
                        </div>
                        <h3>{{ $cms('feat_branding_title', __('feat_branding_title')) }}</h3>
                        <p>{{ $cms('feat_branding_desc', __('feat_branding_desc')) }}</p>
                    </div>
                </div>

                <div class="bento-card">
                    <div>
                        <div class="bento-icon-circle" style="background: rgba(236, 72, 153, 0.15); color: #ec4899;">
                            <i class="fa-solid fa-print"></i>
                        </div>
                        <h3>{{ $cms('feat_qr_studio_title', __('feat_qr_studio_title')) }}</h3>
                        <p>{{ $cms('feat_qr_studio_desc', __('feat_qr_studio_desc')) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 7. Dynamic Subscription Plans (Pricing) -->
    <section id="pricing" class="section">
        <div class="container">
            <div class="section-header">
                <div class="section-badge">{{ $cms('pricing_badge', __('nav_pricing')) }}</div>
                <h2 class="section-title">{{ $cms('pricing_title', __('pricing_title')) }}</h2>
                <p class="section-subtitle">{{ $cms('pricing_subtitle', __('pricing_subtitle')) }}</p>
            </div>

            <div class="pricing-grid">
                @foreach($plans as $plan)
                    @php
                        $isPopular = ($plan->slug === 'pro');
                    @endphp
                    <div class="pricing-card {{ $isPopular ? 'is-popular' : '' }}">
                        @if($isPopular)
                            <div class="popular-badge">{{ __('pricing_popular_badge') }}</div>
                        @endif

                        <div class="plan-header">
                            <h3 class="plan-name">{{ $plan->name }}</h3>
                            <p class="plan-desc">{{ $plan->description }}</p>
                        </div>

                        <div class="plan-price-box">
                            @if($plan->is_custom || $plan->price <= 0)
                                <div class="plan-price-num" style="font-size: 1.75rem;">
                                    {{ app()->getLocale() === 'hy' ? 'Պայմանագրային' : 'Custom' }}
                                </div>
                                <div class="plan-price-unit">{{ app()->getLocale() === 'hy' ? 'Անհատական լուծում' : 'Bespoke integration' }}</div>
                            @else
                                <span class="plan-price-num">{{ number_format($plan->price, 0, '.', ' ') }}</span>
                                <span class="plan-price-unit">֏ / {{ $plan->billing_interval === 'yearly' ? (app()->getLocale() === 'hy' ? 'տարի' : 'year') : (app()->getLocale() === 'hy' ? 'ամիս' : 'month') }}</span>
                            @endif
                        </div>

                        <ul class="plan-features-list">
                            @if(is_array($plan->features))
                                @foreach($plan->features as $feature)
                                    <li class="plan-feature-item">
                                        <i class="fa-solid fa-check"></i>
                                        <span>{{ $feature }}</span>
                                    </li>
                                @endforeach
                            @endif
                            <li class="plan-feature-item" style="color: var(--accent-amber); font-weight: 600;">
                                <i class="fa-solid fa-bolt" style="color: var(--accent-amber);"></i>
                                <span>{{ __('pricing_plan_trial') }}</span>
                            </li>
                        </ul>

                        <div>
                            @if($plan->is_custom)
                                <a href="#contacts" class="btn-plan-cta btn-plan-secondary">
                                    {{ __('pricing_cta_custom') }}
                                </a>
                            @else
                                <a href="{{ route('register.show', ['plan' => $plan->slug]) }}" class="btn-plan-cta {{ $isPopular ? 'btn-plan-primary' : 'btn-plan-secondary' }}">
                                    {{ __('pricing_cta_start') }}
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- 8. FAQ Section -->
    <section id="faq" class="section">
        <div class="container">
            <div class="section-header">
                <div class="section-badge">{{ $cms('faq_badge', __('nav_faq')) }}</div>
                <h2 class="section-title">{{ $cms('faq_title', __('faq_title')) }}</h2>
                <p class="section-subtitle">{{ $cms('faq_subtitle', __('faq_subtitle')) }}</p>
            </div>

            <div class="faq-accordion">
                <div class="faq-item open">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>{{ $cms('faq_q1', __('faq_q1')) }}</span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>{{ $cms('faq_a1', __('faq_a1')) }}</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>{{ $cms('faq_q2', __('faq_q2')) }}</span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>{{ $cms('faq_a2', __('faq_a2')) }}</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>{{ $cms('faq_q3', __('faq_q3')) }}</span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>{{ $cms('faq_a3', __('faq_a3')) }}</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>{{ $cms('faq_q4', __('faq_q4')) }}</span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>{{ $cms('faq_a4', __('faq_a4')) }}</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>{{ $cms('faq_q5', __('faq_q5')) }}</span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>{{ $cms('faq_a5', __('faq_a5')) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 9. Final High-Impact CTA Banner -->
    <section class="final-cta-section">
        <div class="container">
            <div class="final-cta-card">
                <h2 class="final-cta-title">
                    {!! $cms('cta_banner_title', ($loc === 'hy'
                        ? 'Պատրա՞ստ եք ռեստորանը տեղափոխել <span style="background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">նոր մակարդակ</span>'
                        : ($loc === 'ru'
                            ? 'Готовы вывести ресторан на <span style="background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">новый уровень</span>?'
                            : 'Ready to Transform Your <span style="background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Dining Experience</span>?'))) !!}
                </h2>
                <p class="final-cta-subtitle">
                    {{ $cms('cta_banner_subtitle', __('cta_final_subtitle')) }}
                </p>

                <div class="final-cta-buttons">
                    <a href="{{ route('register.show') }}" class="btn-cta-primary" style="padding: 1.1rem 2.5rem; font-size: 1.15rem;">
                        <span>{{ $cms('cta_banner_btn_text', __('cta_final_btn')) }}</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>

                    @php
                        $rawPhone = $settings['contact_phone'] ?? '+37455776066';
                        $cleanPhone = preg_replace('/[^0-9]/', '', $settings['contact_whatsapp'] ?? $rawPhone);
                        $tgHandle = str_replace('@', '', $settings['contact_telegram'] ?? '+37455776066');
                    @endphp

                    <div class="final-cta-social-group">
                        <a href="https://wa.me/{{ $cleanPhone }}?text=Hello%2C%20I%20am%20interested%20in%20elab.menu%20QR%20system" target="_blank" class="btn-whatsapp">
                            <i class="fa-brands fa-whatsapp" style="font-size: 1.25rem;"></i>
                            <span>WhatsApp</span>
                        </a>

                        <a href="https://t.me/{{ $tgHandle }}" target="_blank" class="btn-telegram">
                            <i class="fa-brands fa-telegram" style="font-size: 1.25rem;"></i>
                            <span>Telegram</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 10. Footer Section with SuperAdmin Contacts -->
    <footer id="contacts" class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <a href="{{ route('landing') }}" class="brand-logo" style="text-decoration: none; display: flex; align-items: center; gap: 0.65rem;">
                        @if($siteLogoDark)
                            <img src="{{ $siteLogoDark }}" alt="{{ $siteName }}" class="landing-footer-logo" style="height: 60px; max-height: 60px; width: auto; max-width: 220px; object-fit: contain;">
                        @else
                            <div class="logo-icon">
                                <i class="fa-solid fa-qrcode"></i>
                            </div>
                            <div class="brand-text">{{ $siteName }}</div>
                        @endif
                    </a>
                    <p>{{ \App\Models\SystemSetting::getSiteTagline() ?: __('footer_description') }}</p>
                </div>

                <div class="footer-column">
                    <h4>{{ __('footer_quick_links') }}</h4>
                    <ul>
                        <li><a href="#comparison">{{ $cms('vs_badge', __('nav_comparison')) }}</a></li>
                        <li><a href="#ai-waiter">{{ $cms('ai_section_badge', __('nav_ai')) }}</a></li>
                        <li><a href="#calculator">{{ $cms('calc_badge', __('nav_calculator')) }}</a></li>
                        <li><a href="#pricing">{{ $cms('pricing_badge', __('nav_pricing')) }}</a></li>
                        <li><a href="#faq">{{ $cms('faq_badge', __('nav_faq')) }}</a></li>
                        <li><a href="{{ route('login') }}">{{ __('nav_login') }}</a></li>
                        <li><a href="{{ route('register.show') }}">{{ __('nav_register_cta') }}</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h4>{{ __('footer_contact_us') }}</h4>
                    <div class="contact-list">
                        <div class="contact-entry">
                            <i class="fa-solid fa-phone"></i>
                            <a href="tel:{{ $settings['contact_phone'] ?? '+37455776066' }}">
                                {{ $settings['contact_phone'] ?? '+37455776066' }}
                            </a>
                        </div>
                        <div class="contact-entry">
                            <i class="fa-brands fa-whatsapp" style="color: #25d366;"></i>
                            <a href="https://wa.me/{{ $cleanPhone }}" target="_blank">
                                {{ $settings['contact_whatsapp'] ?? '+37455776066' }} (WhatsApp)
                            </a>
                        </div>
                        <div class="contact-entry">
                            <i class="fa-brands fa-telegram" style="color: #229ed9;"></i>
                            <a href="https://t.me/{{ $tgHandle }}" target="_blank">
                                Telegram: {{ $settings['contact_telegram'] ?? '+37455776066' }}
                            </a>
                        </div>
                        <div class="contact-entry">
                            <i class="fa-solid fa-envelope"></i>
                            <a href="mailto:{{ $settings['contact_email'] ?? 'menu@elab.am' }}">
                                {{ $settings['contact_email'] ?? 'menu@elab.am' }}
                            </a>
                        </div>
                    </div>
                </div>

                <div class="footer-column">
                    <h4>{{ __('footer_socials') }}</h4>
                    <div class="social-row">
                        @php
                            $fb = $settings['social_facebook'] ?? 'https://facebook.com/elab.menu';
                            if (!str_starts_with($fb, 'http')) {
                                $fb = 'https://facebook.com/'.ltrim($fb, '@');
                            }
                            $insta = $settings['social_instagram'] ?? 'https://instagram.com/elab.menu';
                            if (!str_starts_with($insta, 'http')) {
                                $insta = 'https://instagram.com/'.ltrim($insta, '@');
                            }
                        @endphp
                        <a href="{{ $fb }}" target="_blank" class="social-link" title="Facebook">
                            <i class="fa-brands fa-facebook-f"></i>
                        </a>
                        <a href="{{ $insta }}" target="_blank" class="social-link" title="Instagram">
                            <i class="fa-brands fa-instagram"></i>
                        </a>
                    </div>
                    <div style="margin-top: 1.5rem;">
                        <h4>{{ __('footer_legal') }}</h4>
                        <ul>
                            <li><a href="{{ route('legal.privacy') }}">{{ __('footer_privacy') }}</a></li>
                            <li><a href="{{ route('legal.terms') }}">{{ __('footer_terms') }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <div>
                    @if(!empty($settings['footer_copyright']))
                        {{ $settings['footer_copyright'] }}
                    @else
                        &copy; {{ date('Y') }} {{ $siteName }}. {{ __('footer_rights') }}
                    @endif
                </div>
                <div style="display: flex; gap: 1rem;">
                    <span>Yerevan, Armenia</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Interactive Scripts for Command Center, Simulator & ROI Calculator -->
    <script>
        // Command Drawer Toggle
        function toggleCommandDrawer() {
            const drawer = document.getElementById('commandMenuDrawer');
            const btn = document.getElementById('commandMenuBtn');
            if (!drawer) return;
            const isOpen = drawer.classList.contains('open');
            if (isOpen) {
                closeCommandDrawer();
            } else {
                drawer.classList.add('open');
                if (btn) btn.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeCommandDrawer() {
            const drawer = document.getElementById('commandMenuDrawer');
            const btn = document.getElementById('commandMenuBtn');
            if (drawer) drawer.classList.remove('open');
            if (btn) btn.classList.remove('active');
            document.body.style.overflow = '';
        }

        function handleDrawerOverlayClick(event) {
            if (event.target && event.target.id === 'commandMenuDrawer') {
                closeCommandDrawer();
            }
        }

        // Mobile Language Dropdown Toggle
        function toggleLandingLang(event) {
            if (event) {
                event.stopPropagation();
            }
            const dropdown = document.getElementById('landingLangDropdown');
            const trigger = document.getElementById('landingLangTrigger');
            if (!dropdown) return;
            const isOpen = dropdown.classList.toggle('open');
            if (trigger) {
                trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('landingLangDropdown');
            if (dropdown && dropdown.classList.contains('open')) {
                if (!dropdown.contains(e.target)) {
                    dropdown.classList.remove('open');
                    const trigger = document.getElementById('landingLangTrigger');
                    if (trigger) trigger.setAttribute('aria-expanded', 'false');
                }
            }
        });

        // Close on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCommandDrawer();
                const dropdown = document.getElementById('landingLangDropdown');
                if (dropdown) dropdown.classList.remove('open');
            }
        });

        // Floating Sticky Header Effect on Scroll
        window.addEventListener('scroll', function() {
            const header = document.getElementById('siteHeader');
            if (!header) return;
            if (window.scrollY > 30) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        }, { passive: true });

        // AI Waiter Simulator Data
        const dishData = {
            steak: {
                hy: "«Տավարի Սթեյքի հյութեղությունը հիանալի կընդգծի Areni Noir պահուստային կարմիր գինին (3 200 ֏) և ջեռոցում բոված հազարաթերթ բանջարեղենը։»",
                ru: "«Сочность стейка Рибай идеально подчеркнет выдержанное красное вино Areni Noir (3 200 ֏) и запеченные на гриле овощи.»",
                en: "\"To elevate this Prime Ribeye, we recommend pairing it with a glass of Areni Noir Reserve Red Wine and char-grilled asparagus.\"",
                lift: "+32% Check Lift"
            },
            pizza: {
                hy: "«Դիավոլա պիցցայի կծվությունը իդեալական մեղմացնում է մեր թարմ իտալական ցիտրուսային լիմոնադը (1 400 ֏) կամ սառը սպիտակ գինին։»",
                ru: "«Пикантную остроту пиццы Диавола прекрасно сбалансирует домашний цитрусовый лимонад (1 400 ֏) или охлажденное белое вино.»",
                en: "\"For spicy Diavola pizza, the AI suggests our homemade San Pellegrino citrus spritz or chilled Italian Pinot Grigio.\"",
                lift: "+28% Check Lift"
            },
            salad: {
                hy: "«Թարմ Բուրատա աղցանի հետ հիանալի համադրվում են իտալական տաք ֆոկաչիան (1 200 ֏) և թեթև ռոզե գինին։»",
                ru: "«К свежей буррате изумительно подходит теплая фокачча с розмарином (1 200 ֏) и бокал освежающего прованского розе.»",
                en: "\"Pairing Burrata salad with crispy rosemary focaccia and Provence Rosé wine increases satisfaction and check value.\"",
                lift: "+24% Check Lift"
            },
            dessert: {
                hy: "«Շոկոլադե Ֆոնդանի տաք շոկոլադի հետ անհրաժեշտ է թարմ աղացած էսպրեսսո (900 ֏) և վանիլային պաղպաղակի գնդիկ։»",
                ru: "«К горячему шоколадному фондану AI мгновенно предложит чашку ароматного двойного эспрессо (900 ֏) и шарик мороженого.»",
                en: "\"For Chocolate Fondant, AI automatically offers a freshly roasted Double Espresso and artisanal vanilla gelato.\"",
                lift: "+35% Check Lift"
            }
        };

        const currentLocale = "{{ app()->getLocale() }}";

        function switchDish(dishKey) {
            document.querySelectorAll('.dish-tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            const item = dishData[dishKey];
            const textElem = document.getElementById('sim-text');
            textElem.style.opacity = 0;
            setTimeout(() => {
                textElem.innerText = item[currentLocale] || item['en'];
                textElem.style.opacity = 1;
            }, 150);
        }

        // Interactive ROI Calculator Logic
        function calculateROI() {
            const tables = parseInt(document.getElementById('tables-slider').value);
            const guests = parseInt(document.getElementById('guests-slider').value);
            const avgCheck = parseInt(document.getElementById('check-slider').value);

            document.getElementById('tables-val').innerText = tables;
            document.getElementById('guests-val').innerText = guests;
            document.getElementById('check-val').innerText = avgCheck.toLocaleString() + ' ֏';

            // Calculate extra monthly revenue from AI upsell (+18% conservative average) & table turnover
            const dailyOrders = tables * guests;
            const dailyRevenue = dailyOrders * avgCheck;
            const monthlyRevenue = dailyRevenue * 30;
            
            // 18% upsell growth
            const extraMonthlyProfit = Math.round(monthlyRevenue * 0.08); 
            // Printing savings: ~ 15,000 AMD per table per year
            const printingSaved = tables * 18000;

            document.getElementById('calc-revenue').innerText = '+' + extraMonthlyProfit.toLocaleString() + ' ֏';
            document.getElementById('calc-print').innerText = printingSaved.toLocaleString() + ' ֏';
        }

        // FAQ Accordion Toggle
        function toggleFaq(element) {
            const parent = element.parentElement;
            const wasOpen = parent.classList.contains('open');

            // Close all
            document.querySelectorAll('.faq-item').forEach(item => item.classList.remove('open'));

            // Toggle current
            if (!wasOpen) {
                parent.classList.add('open');
            }
        }

        // Run initial calculation
        calculateROI();
    </script>
</body>
</html>
