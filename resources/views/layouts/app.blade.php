<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', \App\Models\SystemSetting::getSiteName() . ' Admin Platform')</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ \App\Models\SystemSetting::getFavicon() }}">
    <link rel="apple-touch-icon" href="{{ \App\Models\SystemSetting::getFavicon() }}">

    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Alpine.js & FontAwesome Icons -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">

    <!-- Prevent FOUC Theme Script -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('qrmenu_theme') || (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>

    <style>
        /* Theme Variables - Dark Mode (Default Obsidian Slate) */
        html[data-theme="dark"] {
            --bg-body: #0a0e1a;
            --bg-card: #12192c;
            --bg-card-hover: #18223c;
            --bg-sidebar: #0e1424;
            --bg-header: rgba(14, 20, 36, 0.88);
            --border-color: rgba(255, 255, 255, 0.08);
            --border-color-light: rgba(255, 255, 255, 0.04);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --text-subtle: #64748b;
            --primary: #f59e0b;
            --primary-hover: #d97706;
            --primary-glow: rgba(245, 158, 11, 0.25);
            --primary-gradient: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
            --nav-hover: rgba(245, 158, 11, 0.08);
            --nav-active: rgba(245, 158, 11, 0.14);
            --nav-active-border: #f59e0b;
            --table-row-border: rgba(255, 255, 255, 0.05);
            --input-bg: rgba(10, 14, 26, 0.7);
            --badge-bg: rgba(245, 158, 11, 0.15);
            --badge-text: #fbbf24;
            --shadow-card: 0 10px 30px -5px rgba(0, 0, 0, 0.4), 0 4px 12px -2px rgba(0, 0, 0, 0.25);
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.2);
            --glass-bg: rgba(18, 25, 44, 0.75);
            --glass-border: rgba(255, 255, 255, 0.08);
            --modal-overlay: rgba(5, 8, 15, 0.82);
            --scrollbar-thumb: rgba(255, 255, 255, 0.16);
            --scrollbar-track: rgba(0, 0, 0, 0.2);
        }

        /* Theme Variables - Light Mode (Minimalist White & Slate) */
        html[data-theme="light"] {
            --bg-body: #f4f6f9;
            --bg-card: #ffffff;
            --bg-card-hover: #f8fafc;
            --bg-sidebar: #ffffff;
            --bg-header: rgba(255, 255, 255, 0.92);
            --border-color: #e2e8f0;
            --border-color-light: #f1f5f9;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-subtle: #94a3b8;
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --primary-glow: rgba(37, 99, 235, 0.18);
            --primary-gradient: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%);
            --nav-hover: #f1f5f9;
            --nav-active: rgba(37, 99, 235, 0.08);
            --nav-active-border: #2563eb;
            --table-row-border: #f1f5f9;
            --input-bg: #f8fafc;
            --badge-bg: #eff6ff;
            --badge-text: #2563eb;
            --shadow-card: 0 4px 20px -2px rgba(15, 23, 42, 0.06), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
            --shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.05);
            --glass-bg: rgba(255, 255, 255, 0.88);
            --glass-border: #e2e8f0;
            --modal-overlay: rgba(15, 23, 42, 0.55);
            --scrollbar-thumb: rgba(0, 0, 0, 0.15);
            --scrollbar-track: rgba(0, 0, 0, 0.03);
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            transition: background-color 0.2s ease, border-color 0.2s ease, color 0.15s ease;
        }

        html, body {
            overflow-x: hidden;
            width: 100%;
            height: 100%;
            -webkit-text-size-adjust: 100%;
        }

        /* SVG & Pagination Containment */
        nav[role="navigation"] svg, svg.w-5, svg.h-5 {
            width: 1.25rem !important;
            height: 1.25rem !important;
            max-width: 20px !important;
            max-height: 20px !important;
            display: inline-block;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            min-height: 100vh;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
        }

        button, input, select, textarea {
            font-family: inherit;
        }

        /* Modern Font Awesome Icon Shield - Protects icons from font inheritance conflicts */
        .fa, .fas, .far, .fal, .fad, .fab, .fa-solid, .fa-regular, .fa-brands, [class*="fa-"] {
            font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands" !important;
            display: inline-block;
            font-style: normal;
            font-variant: normal;
            text-rendering: auto;
            line-height: 1;
        }
        .fa-brands, .fab, [class*="fa-brands"] {
            font-family: "Font Awesome 6 Brands" !important;
        }
        .fa-solid, .fas {
            font-weight: 900 !important;
        }
        .fa-regular, .far {
            font-weight: 400 !important;
        }
        [class*="fa-"]::before, [class*="fa-"]::after {
            font-family: inherit !important;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', sans-serif;
            letter-spacing: -0.015em;
        }

        img, video, svg {
            max-width: 100%;
            height: auto;
            vertical-align: middle;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: var(--scrollbar-track);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb);
            border-radius: 9999px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }

        /* Sidebar Styling */
        .sidebar {
            width: 260px;
            background-color: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 50;
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), background-color 0.25s ease;
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.08);
        }

        .sidebar-brand {
            padding: 1.15rem 1.15rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.85rem;
            border-bottom: 1px solid var(--border-color);
            min-height: 72px;
        }

        .sidebar-close-btn {
            display: none;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            width: 32px;
            height: 32px;
            border-radius: 8px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.95rem;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }
        .sidebar-close-btn:hover {
            color: var(--text-main);
            border-color: var(--primary);
        }

        /* Mobile Burger Menu Quick Panel */
        .sidebar-quick-panel {
            padding: 0.75rem 0.85rem;
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
        }
        @media (min-width: 993px) {
            .sidebar-quick-panel {
                display: none !important;
            }
        }
        .sidebar-quick-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }
        .sidebar-quick-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            padding: 0.5rem 0.65rem;
            background: var(--bg-sidebar);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-main);
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .sidebar-quick-action-btn:hover {
            border-color: var(--primary);
            background: var(--nav-hover);
        }
        .sidebar-lang-selector {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 0.35rem;
            background: var(--bg-sidebar);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 3px;
        }
        .sidebar-lang-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            padding: 0.45rem 0.35rem;
            border-radius: 7px;
            font-size: 0.76rem;
            font-weight: 700;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .sidebar-lang-pill:hover {
            color: var(--text-main);
        }
        .sidebar-lang-pill.active {
            background: var(--primary-gradient);
            color: #ffffff;
            box-shadow: 0 2px 6px var(--primary-glow);
        }

        .sidebar-menu {
            padding: 1rem 0.5rem;
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .menu-category {
            padding: 0.85rem 0.85rem 0.35rem;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--text-subtle);
            font-weight: 800;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 0.65rem 0.85rem;
            margin: 0.15rem 0.35rem;
            border-radius: 12px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-item:hover {
            color: var(--text-main);
            background: var(--nav-hover);
            transform: translateX(2px);
        }

        .nav-item.active {
            color: var(--text-main);
            background: var(--nav-active);
            font-weight: 700;
            border-left: 3px solid var(--nav-active-border);
        }

        .nav-item i {
            width: 20px;
            text-align: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        /* Sidebar Help Banner Widget */
        .sidebar-widget {
            margin: 0.75rem 0.85rem 1rem;
            padding: 1rem;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            text-align: center;
        }

        .sidebar-widget p {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-bottom: 0.65rem;
            line-height: 1.4;
        }

        /* Main Container */
        .main-wrapper {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            width: calc(100% - 260px);
            max-width: 100%;
            transition: margin-left 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Top Header Navbar */
        .header-navbar {
            height: 72px;
            background: var(--bg-header);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.75rem;
            position: sticky;
            top: 0;
            z-index: 40;
            gap: 1rem;
        }

        .mobile-toggle {
            display: none;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            width: 40px;
            height: 40px;
            border-radius: 10px;
            font-size: 1.15rem;
            cursor: pointer;
            align-items: center;
            justify-content: center;
            margin-right: 0.5rem;
            flex-shrink: 0;
        }

        .location-switcher select {
            background: var(--bg-card);
            color: var(--text-main);
            border: 1px solid var(--border-color);
            padding: 0.45rem 0.85rem;
            border-radius: 10px;
            outline: none;
            font-size: 0.82rem;
            cursor: pointer;
            font-weight: 600;
            max-width: 220px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        /* Minimal Theme Toggle Switcher */
        .theme-toggle-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 9999px;
            padding: 0.35rem 0.75rem;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-main);
            box-shadow: var(--shadow-sm);
            white-space: nowrap;
        }

        .theme-toggle-btn:hover {
            border-color: var(--primary);
        }

        .lang-dropdown-menu a:hover {
            background: var(--nav-hover);
            color: var(--primary) !important;
        }
        .lang-dropdown-menu a.active {
            background: var(--nav-active);
            color: var(--primary) !important;
        }

        .badge-role {
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            white-space: nowrap;
        }

        .badge-superadmin { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .badge-owner { background: var(--badge-bg); color: var(--badge-text); border: 1px solid var(--border-color); }
        .badge-manager { background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }

        .content-body {
            padding: 2rem;
            flex: 1;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            overflow-x: hidden;
        }

        /* Cards System */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 1.5rem;
            box-shadow: var(--shadow-card);
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
            box-sizing: border-box;
            max-width: 100%;
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 18px;
            box-shadow: var(--shadow-card);
            box-sizing: border-box;
            max-width: 100%;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            text-decoration: none;
            white-space: nowrap;
            flex-shrink: 0;
            line-height: 1.25;
        }
        .btn:active {
            transform: scale(0.98);
        }

        .btn-primary {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 4px 14px var(--primary-glow);
        }
        html[data-theme="dark"] .btn-primary {
            color: #0b0f19;
            font-weight: 700;
        }
        .btn-primary:hover {
            background: var(--primary-hover);
            border-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--bg-card);
            color: var(--text-main);
            border: 1px solid var(--border-color);
        }
        .btn-secondary:hover {
            background: var(--bg-card-hover);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-1px);
        }

        .btn-success { background: #10b981; color: #fff; border-color: #10b981; }
        .btn-success:hover { background: #059669; }
        .btn-danger { background: #ef4444; color: #fff; border-color: #ef4444; }
        .btn-danger:hover { background: #dc2626; }

        /* Alert Notifications */
        .alert {
            padding: 0.85rem 1.25rem;
            border-radius: 14px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.88rem;
            gap: 1rem;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.28);
            color: #10b981;
        }

        /* Form Controls */
        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 0.4rem;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            background: var(--input-bg);
            color: var(--text-main);
            border: 1px solid var(--border-color);
            padding: 0.65rem 0.9rem;
            border-radius: 12px;
            font-size: 0.875rem;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            box-sizing: border-box;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .form-select option {
            background: var(--bg-card);
            color: var(--text-main);
        }

        /* Responsive Data Tables */
        .responsive-table-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 14px;
            border: 1px solid var(--border-color);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
            white-space: nowrap;
        }

        .data-table th {
            text-align: left;
            padding: 0.85rem 1rem;
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: var(--input-bg);
            border-bottom: 1px solid var(--border-color);
            font-weight: 700;
        }

        .data-table td {
            padding: 0.9rem 1rem;
            border-bottom: 1px solid var(--table-row-border);
            vertical-align: middle;
        }

        /* Fluid Grid System */
        .grid-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
        }
        @media (max-width: 1200px) {
            .grid-4 { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .grid-4 { grid-template-columns: 1fr; }
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.25rem;
        }
        @media (max-width: 1024px) {
            .grid-3 { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .grid-3 { grid-template-columns: 1fr; }
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
        }
        @media (max-width: 900px) {
            .grid-2 { grid-template-columns: 1fr; }
        }

        /* Modern KPI & Stat Tiles */
        .stat-kpi-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-card);
            position: relative;
            overflow: hidden;
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s ease, border-color 0.25s ease;
        }
        .stat-kpi-card:hover {
            transform: translateY(-3px);
            border-color: rgba(245, 158, 11, 0.35);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.2);
        }
        .kpi-icon-badge {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            flex-shrink: 0;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .badge-emerald { background: rgba(16, 185, 129, 0.12); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.28); }
        .badge-amber { background: rgba(245, 158, 11, 0.12); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.28); }
        .badge-rose { background: rgba(239, 68, 68, 0.12); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.28); }
        .badge-indigo { background: rgba(99, 102, 241, 0.12); color: #6366f1; border: 1px solid rgba(99, 102, 241, 0.28); }
        .badge-cyan { background: rgba(6, 182, 212, 0.12); color: #06b6d4; border: 1px solid rgba(6, 182, 212, 0.28); }
        .badge-purple { background: rgba(168, 85, 247, 0.12); color: #a855f7; border: 1px solid rgba(168, 85, 247, 0.28); }

        /* Text Utilities */
        .truncate-text {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .break-word {
            word-break: break-word;
            overflow-wrap: break-word;
        }

        /* Modern Modal System */
        .modern-modal-overlay {
            position: fixed;
            inset: 0;
            background: var(--modal-overlay);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            box-sizing: border-box;
        }
        .modern-modal-box {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.45);
            border-radius: 20px;
            width: 100%;
            max-width: 620px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 1.75rem 2rem;
            box-sizing: border-box;
            position: relative;
        }

        /* Mobile Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 45;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .sidebar-overlay.mobile-open {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            display: block;
        }

        /* Responsive Breakpoints */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            .main-wrapper {
                margin-left: 0;
                width: 100%;
            }
            .mobile-toggle {
                display: inline-flex;
            }
            .header-navbar {
                padding: 0 1rem;
            }
            .content-body {
                padding: 1.25rem 1rem;
            }
            .hide-on-mobile {
                display: none !important;
            }
            .sidebar-close-btn {
                display: inline-flex;
            }
        }
    </style>
    @yield('styles')
</head>
<body x-data="themeApp()">

    <!-- Mobile Overlay Backdrop -->
    <div class="sidebar-overlay" :class="{ 'mobile-open': mobileOpen }" @click="mobileOpen = false"></div>

    <!-- Sidebar Navigation -->
    <aside class="sidebar" :class="{ 'mobile-open': mobileOpen }">
        <div class="sidebar-brand">
            @if(Auth::user()?->isSuperAdmin())
                @php
                    $sysLogo = \App\Models\SystemSetting::getLogoDark();
                    $sysName = \App\Models\SystemSetting::getSiteName();
                @endphp
                <div style="display: flex; align-items: center; gap: 0.75rem; width: 100%; min-width: 0;">
                    @if($sysLogo)
                        <img src="{{ $sysLogo }}" alt="{{ $sysName }}" style="max-height: 38px; max-width: 140px; object-fit: contain;">
                    @else
                        <div style="width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 1.2rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4); flex-shrink: 0;">
                            <i class="fa-solid fa-crown"></i>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <h1 style="font-size: 1.15rem; font-weight: 800; margin: 0; background: linear-gradient(135deg, #6366f1 0%, #ec4899 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $sysName }}</h1>
                            <div style="font-size: 0.68rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">SuperAdmin Console</div>
                        </div>
                    @endif
                </div>
            @else
                @php 
                    $currVendor = Auth::user()?->vendor;
                    $activeLocation = session('active_location_id') 
                        ? $currVendor?->locations->firstWhere('id', session('active_location_id')) 
                        : $currVendor?->locations->first();
                @endphp
                <div style="display: flex; align-items: center; gap: 0.85rem; width: 100%; min-width: 0;">
                    @if($currVendor?->logo)
                        <img src="{{ $currVendor->logo }}" alt="{{ $currVendor->name }}" style="width: 42px; height: 42px; border-radius: 12px; object-fit: cover; border: 2px solid var(--border-color); flex-shrink: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                    @else
                        <div style="width: 42px; height: 42px; border-radius: 12px; background: var(--primary-gradient); display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 1.2rem; font-weight: 800; flex-shrink: 0; box-shadow: 0 4px 14px var(--primary-glow);">
                            <i class="fa-solid fa-utensils"></i>
                        </div>
                    @endif
                    <div style="flex: 1; min-width: 0;">
                        <h1 style="font-size: 1.05rem; font-weight: 800; margin: 0; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $currVendor?->name ?? 'QRMenu Admin' }}">
                            {{ $currVendor?->name ?? 'QRMenu Admin' }}
                        </h1>
                        <div style="display: flex; align-items: center; gap: 0.35rem; margin-top: 0.15rem;">
                            <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981; box-shadow: 0 0 6px #10b981; flex-shrink: 0;"></span>
                            <span style="font-size: 0.7rem; font-weight: 600; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                {{ $activeLocation?->name ?? 'Live Multi-Branch' }}
                            </span>
                        </div>
                    </div>
                </div>
            @endif

            <button type="button" class="sidebar-close-btn" @click="mobileOpen = false" aria-label="Close menu">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Mobile Burger Menu Quick Controls: Language, Theme & 2FA -->
        <div class="sidebar-quick-panel">
            <div class="sidebar-quick-row">
                <!-- Theme Switcher -->
                <button type="button" class="sidebar-quick-action-btn" @click="toggleTheme()" title="Toggle Light / Dark Mode">
                    <i class="fa-solid" :class="currentTheme === 'dark' ? 'fa-sun' : 'fa-moon'" style="color: var(--primary); font-size: 0.95rem;"></i>
                    <span x-text="currentTheme === 'dark' ? '{{ __('Light') }}' : '{{ __('Dark') }}'"></span>
                </button>

                <!-- Security & 2FA Profile -->
                <a href="{{ Auth::user()?->isSuperAdmin() ? route('superadmin.settings.index') . '#security-section' : route('admin.profile') }}" class="sidebar-quick-action-btn" title="{{ __('Security & 2FA') }}">
                    <i class="fa-solid fa-shield-halved" style="color: {{ Auth::user()?->hasTwoFactorEnabled() ? '#10b981' : '#f59e0b' }}; font-size: 0.95rem;"></i>
                    <span>{{ __('2FA & Profile') }}</span>
                </a>
            </div>

            <!-- Language Switcher Segmented Pills -->
            <div class="sidebar-lang-selector">
                <a href="{{ route('lang.switch', 'hy') }}" class="sidebar-lang-pill {{ app()->getLocale() === 'hy' ? 'active' : '' }}">
                    <span>🇦🇲</span> <span>ՀԱՅ</span>
                </a>
                <a href="{{ route('lang.switch', 'ru') }}" class="sidebar-lang-pill {{ app()->getLocale() === 'ru' ? 'active' : '' }}">
                    <span>🇷🇺</span> <span>РУС</span>
                </a>
                <a href="{{ route('lang.switch', 'en') }}" class="sidebar-lang-pill {{ app()->getLocale() === 'en' ? 'active' : '' }}">
                    <span>🇬🇧</span> <span>ENG</span>
                </a>
            </div>
        </div>

        <nav class="sidebar-menu">
            @if(Auth::user()?->isSuperAdmin())
                <div class="menu-category">{{ __('Super Admin') }}</div>
                <a href="{{ route('superadmin.dashboard') }}" class="nav-item {{ request()->routeIs('superadmin.dashboard') ? 'active' : '' }}">
                    <i class="fa-solid fa-chart-line" style="color: #6366f1;"></i> <span>{{ __('Dashboard') }}</span>
                </a>
                <a href="{{ route('superadmin.vendors.index') }}" class="nav-item {{ request()->routeIs('superadmin.vendors.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-store" style="color: #10b981;"></i> <span>{{ __('Vendor Directory') }}</span>
                </a>
                <a href="{{ route('superadmin.plans.index') }}" class="nav-item {{ request()->routeIs('superadmin.plans.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-box-archive" style="color: #f59e0b;"></i> <span>{{ __('Plans') }}</span>
                </a>
                <a href="{{ route('superadmin.subscriptions.index') }}" class="nav-item {{ request()->routeIs('superadmin.subscriptions.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-credit-card" style="color: #ec4899;"></i> <span>{{ __('Vendor Subscriptions') }}</span>
                </a>
                <a href="{{ route('superadmin.settings.index') }}" class="nav-item {{ request()->routeIs('superadmin.settings.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-sliders" style="color: #06b6d4;"></i> <span>{{ __('Settings & Landing') }}</span>
                </a>
                <a href="{{ route('superadmin.languages.index') }}" class="nav-item {{ request()->routeIs('superadmin.languages.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-language" style="color: #a855f7;"></i> <span>{{ __('Languages & i18n') }}</span>
                </a>

                <div class="menu-category">{{ __('Email Management') }}</div>
                <a href="{{ route('superadmin.emails.overview') }}" class="nav-item {{ request()->routeIs('superadmin.emails.overview') ? 'active' : '' }}">
                    <i class="fa-solid fa-gauge-high" style="color: #3b82f6;"></i> <span>{{ __('Overview') }}</span>
                </a>
                <a href="{{ route('superadmin.emails.senders.index') }}" class="nav-item {{ request()->routeIs('superadmin.emails.senders.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-id-card-clip" style="color: #10b981;"></i> <span>{{ __('Sender Identities') }}</span>
                </a>
                <a href="{{ route('superadmin.emails.connections.index') }}" class="nav-item {{ request()->routeIs('superadmin.emails.connections.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-server" style="color: #f59e0b;"></i> <span>{{ __('SMTP Connections') }}</span>
                </a>
                <a href="{{ route('superadmin.emails.routing.index') }}" class="nav-item {{ request()->routeIs('superadmin.emails.routing.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-route" style="color: #8b5cf6;"></i> <span>{{ __('Email Routing') }}</span>
                </a>
                <a href="{{ route('superadmin.emails.templates.index') }}" class="nav-item {{ request()->routeIs('superadmin.emails.templates.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-envelope-open-text" style="color: #ec4899;"></i> <span>{{ __('Templates') }}</span>
                </a>
                <a href="{{ route('superadmin.emails.logs.index') }}" class="nav-item {{ request()->routeIs('superadmin.emails.logs.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-list-check" style="color: #06b6d4;"></i> <span>{{ __('Email Logs') }}</span>
                </a>
                <a href="{{ route('superadmin.emails.queue.index') }}" class="nav-item {{ request()->routeIs('superadmin.emails.queue.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-clock-rotate-left" style="color: #eab308;"></i> <span>{{ __('Queue Monitor') }}</span>
                </a>
                <a href="{{ route('superadmin.emails.failed.index') }}" class="nav-item {{ request()->routeIs('superadmin.emails.failed.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-triangle-exclamation" style="color: #ef4444;"></i> <span>{{ __('Failed Emails') }}</span>
                </a>
                <a href="{{ route('superadmin.emails.reports.index') }}" class="nav-item {{ request()->routeIs('superadmin.emails.reports.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-chart-pie" style="color: #6366f1;"></i> <span>{{ __('Reports') }}</span>
                </a>
                <a href="{{ route('superadmin.emails.settings.index') }}" class="nav-item {{ request()->routeIs('superadmin.emails.settings.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-gear" style="color: #94a3b8;"></i> <span>{{ __('Email Settings') }}</span>
                </a>
            @else
                @php $v = Auth::user()?->vendor; @endphp
                
                <div class="menu-category">{{ __('Vendor Operations') }}</div>
                <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <i class="fa-solid fa-gauge-high" style="color: #f59e0b;"></i> <span>{{ __('Dashboard') }}</span>
                </a>
                @can('orders.view')
                <a href="{{ route('admin.orders.index') }}" class="nav-item {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}" style="justify-content: space-between;">
                    <span style="display: flex; align-items: center; gap: 0.75rem; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <i class="fa-solid fa-bell-concierge" style="color: #ef4444;"></i> <span>{{ __('Kitchen Orders') }}</span>
                    </span>
                    @if($v && !$v->hasFeature('orders'))
                        <span style="font-size: 0.65rem; background: rgba(245, 158, 11, 0.2); color: #f59e0b; padding: 0.15rem 0.4rem; border-radius: 6px; font-weight: 700; flex-shrink: 0;">🔒 PRO</span>
                    @endif
                </a>
                @endcan

                @can('locations.view')
                <a href="{{ route('admin.floor_plan.index') }}" class="nav-item {{ request()->routeIs('admin.floor_plan.*') ? 'active' : '' }}" style="justify-content: space-between;">
                    <span style="display: flex; align-items: center; gap: 0.75rem; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <i class="fa-solid fa-map-location-dot" style="color: #3b82f6;"></i> <span>{{ __('Floor Plan') }}</span>
                    </span>
                    @if($v && !$v->hasFeature('orders'))
                        <span style="font-size: 0.65rem; background: rgba(245, 158, 11, 0.2); color: #f59e0b; padding: 0.15rem 0.4rem; border-radius: 6px; font-weight: 700; flex-shrink: 0;">🔒 PRO</span>
                    @endif
                </a>
                @endcan

                @can('menu.view')
                <div class="menu-category">{{ __('Menu & Content') }}</div>
                <a href="{{ route('admin.menu.index') }}" class="nav-item {{ request()->routeIs('admin.menu.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-utensils" style="color: #10b981;"></i> <span>{{ __('Menu Builder') }}</span>
                </a>
                <a href="{{ route('admin.translations.index') }}" class="nav-item {{ request()->routeIs('admin.translations.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-language" style="color: #6366f1;"></i> <span>{{ __('Translations & AI') }}</span>
                </a>
                @endcan

                @can('ai.view')
                <a href="{{ route('admin.ai.import') }}" class="nav-item {{ request()->routeIs('admin.ai.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-wand-magic-sparkles" style="color: #8b5cf6;"></i> <span>{{ __('AI Menu & Translate') }}</span>
                </a>
                @endcan

                <div class="menu-category">{{ __('Storefront & Growth') }}</div>
                @can('settings.view')
                <a href="{{ route('admin.branding.index') }}" class="nav-item {{ request()->routeIs('admin.branding.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-palette" style="color: #ec4899;"></i> <span>{{ __('Theme Customizer') }}</span>
                </a>
                <a href="{{ route('admin.qr.index') }}" class="nav-item {{ request()->routeIs('admin.qr.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-qrcode" style="color: #06b6d4;"></i> <span>{{ __('Table QR Studio') }}</span>
                </a>
                @endcan

                @can('customers.view')
                <a href="{{ route('admin.customers.index') }}" class="nav-item {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}" style="justify-content: space-between;">
                    <span style="display: flex; align-items: center; gap: 0.75rem; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <i class="fa-solid fa-users-gear" style="color: #3b82f6;"></i> <span>{{ __('Customers & CRM') }}</span>
                    </span>
                    @if($v && !$v->hasFeature('customers'))
                        <span style="font-size: 0.65rem; background: rgba(245, 158, 11, 0.2); color: #f59e0b; padding: 0.15rem 0.4rem; border-radius: 6px; font-weight: 700; flex-shrink: 0;">🔒 PRO</span>
                    @endif
                </a>
                @endcan

                @can('reports.view')
                <a href="{{ route('admin.analytics.index') }}" class="nav-item {{ request()->routeIs('admin.analytics.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-chart-pie" style="color: #f97316;"></i> <span>{{ __('Analytics & Traffic') }}</span>
                </a>
                @endcan

                <div class="menu-category">{{ __('Settings & Administration') }}</div>
                <a href="{{ route('admin.profile') }}" class="nav-item {{ request()->routeIs('admin.profile') ? 'active' : '' }}">
                    <i class="fa-solid fa-user-shield" style="color: #10b981;"></i> <span>{{ __('Security & 2FA') }}</span>
                </a>

                @can('settings.view')
                <a href="{{ route('admin.settings.index') }}" class="nav-item {{ request()->routeIs('admin.settings.index') ? 'active' : '' }}">
                    <i class="fa-solid fa-sliders" style="color: #64748b;"></i> <span>{{ __('Settings') }}</span>
                </a>
                @endcan

                @can('ai.view')
                <a href="{{ route('admin.settings.ai') }}" class="nav-item {{ request()->routeIs('admin.settings.ai*') ? 'active' : '' }}" style="justify-content: space-between;">
                    <span style="display: flex; align-items: center; gap: 0.75rem; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <i class="fa-solid fa-robot" style="color: #8b5cf6;"></i> <span>{{ __('AI Settings') }}</span>
                    </span>
                    <span style="font-size: 0.65rem; background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(236, 72, 153, 0.2)); color: #8b5cf6; padding: 0.15rem 0.4rem; border-radius: 6px; font-weight: 700; flex-shrink: 0;">AI</span>
                </a>
                @endcan

                @can('billing.view')
                <a href="{{ route('admin.subscription') }}" class="nav-item {{ request()->routeIs('admin.subscription') ? 'active' : '' }}">
                    <i class="fa-solid fa-file-invoice-dollar" style="color: #f59e0b;"></i> <span>{{ __('Subscription') }}</span>
                </a>
                @endcan

                @can('locations.view')
                <a href="{{ route('admin.locations.index') }}" class="nav-item {{ request()->routeIs('admin.locations.*') ? 'active' : '' }}" style="justify-content: space-between;">
                    <span style="display: flex; align-items: center; gap: 0.75rem; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <i class="fa-solid fa-location-dot" style="color: #14b8a6;"></i> <span>{{ __('Multi-Locations') }}</span>
                    </span>
                    @if($v && !$v->hasFeature('locations'))
                        <span style="font-size: 0.65rem; background: rgba(6, 182, 212, 0.2); color: #06b6d4; padding: 0.15rem 0.4rem; border-radius: 6px; font-weight: 700; flex-shrink: 0;">🔒 BIZ</span>
                    @endif
                </a>
                @endcan

                @can('team.view')
                <a href="{{ route('admin.team.index') }}" class="nav-item {{ request()->routeIs('admin.team.*') ? 'active' : '' }}" style="justify-content: space-between;">
                    <span style="display: flex; align-items: center; gap: 0.75rem; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <i class="fa-solid fa-users" style="color: #0ea5e9;"></i> <span>{{ __('Team & Staff') }}</span>
                    </span>
                    @if($v && !$v->hasFeature('team'))
                        <span style="font-size: 0.65rem; background: rgba(6, 182, 212, 0.2); color: #06b6d4; padding: 0.15rem 0.4rem; border-radius: 6px; font-weight: 700; flex-shrink: 0;">🔒 BIZ</span>
                    @endif
                </a>
                @endcan

                @if(Auth::user()?->vendor)
                    <div style="padding: 0.75rem 0.65rem 0.25rem;">
                        <a href="{{ Auth::user()->vendor->getStorefrontUrl() }}" target="_blank" class="btn btn-secondary" style="width: 100%; justify-content: center; font-size: 0.8rem; border-radius: 12px; gap: 0.5rem; border-color: rgba(245, 158, 11, 0.3);">
                            <i class="fa-solid fa-arrow-up-right-from-square" style="color: var(--primary);"></i> <span>{{ __('Live Storefront') }}</span>
                        </a>
                    </div>
                @endif
            @endif
        </nav>

        <!-- Sidebar Help / Support Widget -->
        @if(Auth::user()?->isSuperAdmin())
            <div class="sidebar-widget" style="text-align: left; padding: 0.85rem 1rem; margin: 0.75rem 0.85rem 1.25rem;">
                <div style="font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.4rem;">
                    <i class="fa-solid fa-server" style="color: #6366f1;"></i> Core Platform
                </div>
                <div style="font-size: 0.82rem; font-weight: 700; color: var(--text-main);">Laravel 13 & Reverb</div>
                <div style="font-size: 0.72rem; color: #10b981; margin-top: 0.25rem; display: flex; align-items: center; gap: 0.35rem;">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span>
                    <span>WebSocket: Port 8080 Active</span>
                </div>
            </div>
        @else
            <div class="sidebar-widget">
                <div style="font-weight: 700; font-size: 0.8rem; color: var(--text-main); margin-bottom: 0.25rem;">
                    <i class="fa-solid fa-headset" style="color: var(--primary);"></i> Support Desk
                </div>
                <p>{{ __('Need help or custom menu translation?') }}</p>
                <a href="mailto:{{ \App\Models\SystemSetting::get('contact_email') ?: 'support@elab.am' }}" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.35rem 0.75rem; border-radius: 8px; width: 100%;">
                    {{ __('Get Support') }}
                </a>
            </div>
        @endif
    </aside>

    <!-- Main Content Shell -->
    <div class="main-wrapper">
        <header class="header-navbar">
            <div style="display: flex; align-items: center; min-width: 0;">
                <button class="mobile-toggle" @click="mobileOpen = !mobileOpen" aria-label="Toggle navigation menu">
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="location-switcher">
                    @if(Auth::user()?->vendor && Auth::user()->vendor->locations->count() > 0)
                        <form action="{{ route('admin.dashboard') }}" method="GET" id="locationSwitchForm" style="display: flex; align-items: center;">
                            <label style="font-size: 0.8rem; color: var(--text-muted); margin-right: 0.5rem; white-space: nowrap;" class="hide-on-mobile">
                                <i class="fa-solid fa-location-dot" style="color: var(--primary);"></i> {{ __('Location:') }}
                            </label>
                            <select name="location_id" onchange="document.getElementById('locationSwitchForm').submit();">
                                @foreach(Auth::user()->vendor->locations as $loc)
                                    <option value="{{ $loc->id }}" {{ session('active_location_id') == $loc->id ? 'selected' : '' }}>
                                        {{ $loc->name }} ({{ $loc->table_count }} {{ __('tables') }})
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                </div>
            </div>

            <div class="user-profile">
                <!-- Platform Language Switcher Dropdown -->
                <div class="lang-dropdown-wrapper hide-on-mobile" x-data="{ openLang: false }" @click.outside="openLang = false" style="position: relative;">
                    <button type="button" @click="openLang = !openLang" class="theme-toggle-btn" style="border-radius: 10px; gap: 0.45rem; padding: 0.4rem 0.75rem;" title="{{ __('Select Language') }}">
                        @if(app()->getLocale() === 'hy')
                            <span style="font-size: 1.05rem; line-height: 1;">🇦🇲</span>
                            <span class="hide-on-mobile" style="font-size: 0.8rem; font-weight: 600;">Հայերեն</span>
                        @elseif(app()->getLocale() === 'ru')
                            <span style="font-size: 1.05rem; line-height: 1;">🇷🇺</span>
                            <span class="hide-on-mobile" style="font-size: 0.8rem; font-weight: 600;">Русский</span>
                        @else
                            <span style="font-size: 1.05rem; line-height: 1;">🇬🇧</span>
                            <span class="hide-on-mobile" style="font-size: 0.8rem; font-weight: 600;">English</span>
                        @endif
                        <i class="fa-solid fa-chevron-down" style="font-size: 0.65rem; opacity: 0.7; transition: transform 0.2s;" :style="openLang ? 'transform: rotate(180deg)' : ''"></i>
                    </button>
                    <div x-show="openLang" x-transition.origin.top.right class="lang-dropdown-menu" style="display: none; position: absolute; right: 0; top: calc(100% + 6px); z-index: 1000; min-width: 145px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; box-shadow: var(--shadow-card); padding: 5px; flex-direction: column; gap: 3px;">
                        <a href="{{ route('lang.switch', 'hy') }}" class="lang-item {{ app()->getLocale() === 'hy' ? 'active' : '' }}" style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.45rem 0.65rem; border-radius: 8px; text-decoration: none; font-size: 0.8rem; font-weight: 600; color: var(--text-main); transition: all 0.15s ease;">
                            <span style="display: flex; align-items: center; gap: 0.45rem;"><span style="font-size: 1rem;">🇦🇲</span> Հայերեն</span>
                            @if(app()->getLocale() === 'hy') <i class="fa-solid fa-check" style="color: var(--primary); font-size: 0.75rem;"></i> @endif
                        </a>
                        <a href="{{ route('lang.switch', 'en') }}" class="lang-item {{ app()->getLocale() === 'en' ? 'active' : '' }}" style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.45rem 0.65rem; border-radius: 8px; text-decoration: none; font-size: 0.8rem; font-weight: 600; color: var(--text-main); transition: all 0.15s ease;">
                            <span style="display: flex; align-items: center; gap: 0.45rem;"><span style="font-size: 1rem;">🇬🇧</span> English</span>
                            @if(app()->getLocale() === 'en') <i class="fa-solid fa-check" style="color: var(--primary); font-size: 0.75rem;"></i> @endif
                        </a>
                        <a href="{{ route('lang.switch', 'ru') }}" class="lang-item {{ app()->getLocale() === 'ru' ? 'active' : '' }}" style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.45rem 0.65rem; border-radius: 8px; text-decoration: none; font-size: 0.8rem; font-weight: 600; color: var(--text-main); transition: all 0.15s ease;">
                            <span style="display: flex; align-items: center; gap: 0.45rem;"><span style="font-size: 1rem;">🇷🇺</span> Русский</span>
                            @if(app()->getLocale() === 'ru') <i class="fa-solid fa-check" style="color: var(--primary); font-size: 0.75rem;"></i> @endif
                        </a>
                    </div>
                </div>

                <!-- Light / Dark Theme Switcher Button -->
                <button class="theme-toggle-btn hide-on-mobile" @click="toggleTheme()" title="Toggle Light / Dark Mode">
                    <i class="fa-solid" :class="currentTheme === 'dark' ? 'fa-sun' : 'fa-moon'" style="color: var(--primary);"></i>
                    <span class="hide-on-mobile" x-text="currentTheme === 'dark' ? '{{ __('Light') }}' : '{{ __('Dark') }}'"></span>
                </button>

                <!-- User Profile & 2FA Button -->
                <a href="{{ Auth::user()?->isSuperAdmin() ? route('superadmin.settings.index') . '#security-section' : route('admin.profile') }}" class="theme-toggle-btn hide-on-mobile" style="text-decoration: none; border-radius: 10px;" title="{{ __('Security & 2FA') }}">
                    <i class="fa-solid fa-shield-halved" style="color: {{ Auth::user()?->hasTwoFactorEnabled() ? '#10b981' : '#f59e0b' }};"></i>
                    <span class="hide-on-mobile">{{ __('2FA & Profile') }}</span>
                </a>

                <!-- User Role Badge -->
                <span class="badge-role hide-on-mobile {{ Auth::user()?->isSuperAdmin() ? 'badge-superadmin' : (Auth::user()?->isVendorOwner() ? 'badge-owner' : 'badge-manager') }}">
                    {{ str_replace('_', ' ', Auth::user()?->role ?? 'staff') }}
                </span>

                <!-- Logout Button -->
                <form action="{{ route('logout') }}" method="POST" style="margin-left: 0.15rem;">
                    @csrf
                    <button type="submit" class="btn btn-secondary" style="padding: 0.45rem 0.75rem; border-radius: 10px;" title="Logout">
                        <i class="fa-solid fa-right-from-bracket" style="font-size: 0.9rem;"></i>
                    </button>
                </form>
            </div>
        </header>

        <main class="content-body">
            @if(session('success'))
                <div class="alert alert-success">
                    <span style="display: flex; align-items: center; gap: 0.5rem;"><i class="fa-solid fa-circle-check"></i> {{ session('success') }}</span>
                    <button onclick="this.parentElement.remove()" style="background:none; border:none; color:inherit; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                </div>
            @endif

            @if(session('warning'))
                <div class="alert" style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); color: #f59e0b;">
                    <span style="display: flex; align-items: center; gap: 0.5rem;"><i class="fa-solid fa-triangle-exclamation"></i> {{ session('warning') }}</span>
                    <button onclick="this.parentElement.remove()" style="background:none; border:none; color:inherit; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert" style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444;">
                    <span style="display: flex; align-items: center; gap: 0.5rem;"><i class="fa-solid fa-circle-exclamation"></i> {{ session('error') }}</span>
                    <button onclick="this.parentElement.remove()" style="background:none; border:none; color:inherit; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                </div>
            @endif

            @if(Auth::user()?->vendor && !Auth::user()?->isSuperAdmin() && Auth::user()->vendor->daysLeft() <= 3 && !request()->routeIs('admin.subscription'))
                <div style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.4); color: #f59e0b; padding: 0.85rem 1.25rem; border-radius: 14px; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-clock"></i>
                        <span><strong>{{ __('Subscription Reminder:') }}</strong> {{ Auth::user()->vendor->daysLeft() }} {{ __('days left') }} ({{ Auth::user()->vendor->subscription_status_label }})</span>
                    </div>
                    <a href="{{ route('admin.subscription') }}" class="btn btn-primary" style="font-size: 0.78rem; padding: 0.35rem 0.75rem;">
                        {{ __('Details / Extend') }}
                    </a>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script>
        function themeApp() {
            return {
                mobileOpen: false,
                currentTheme: document.documentElement.getAttribute('data-theme') || 'dark',
                toggleTheme() {
                    this.currentTheme = this.currentTheme === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-theme', this.currentTheme);
                    localStorage.setItem('qrmenu_theme', this.currentTheme);
                }
            }
        }
    </script>
    @yield('scripts')
</body>
</html>
