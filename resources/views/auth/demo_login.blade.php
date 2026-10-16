<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo Login — Explore QRMenu Platform</title>
    
    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">

    <style>
        :root {
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --bg-card-inner: #f8fafc;
            --border-card: #e2e8f0;
            --border-highlight: #f59e0b;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --accent-amber: #f59e0b;
            --accent-amber-dark: #d97706;
            --accent-gradient: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
            --shadow-card: 0 10px 30px -5px rgba(15, 23, 42, 0.08), 0 4px 12px -2px rgba(15, 23, 42, 0.04);
            --shadow-card-hover: 0 18px 36px -6px rgba(15, 23, 42, 0.12), 0 8px 16px -4px rgba(15, 23, 42, 0.06);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-body);
            background-image: 
                radial-gradient(circle at 10% 15%, rgba(245, 158, 11, 0.08) 0%, transparent 45%),
                radial-gradient(circle at 90% 85%, rgba(59, 130, 246, 0.06) 0%, transparent 45%);
            background-attachment: fixed;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1rem;
            position: relative;
        }

        .demo-container {
            width: 100%;
            max-width: 820px;
            position: relative;
            z-index: 10;
        }

        /* Top Nav */
        .top-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding: 0 0.5rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.88rem;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.2s ease, transform 0.2s ease;
        }

        .back-link:hover {
            color: var(--text-primary);
            transform: translateX(-3px);
        }

        .top-nav-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-top-login {
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            padding: 0.4rem 0.85rem;
            border-radius: 8px;
            border: 1px solid var(--border-card);
            background: #ffffff;
            transition: all 0.2s ease;
        }

        .btn-top-login:hover {
            color: var(--text-primary);
            border-color: #cbd5e1;
            background: #f1f5f9;
        }

        .btn-top-register {
            background: var(--accent-gradient);
            color: #ffffff;
            font-size: 0.85rem;
            font-weight: 700;
            text-decoration: none;
            padding: 0.45rem 1rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }

        .btn-top-register:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }

        .lang-switch-box {
            display: inline-flex;
            align-items: center;
            background: #f1f5f9;
            border: 1px solid var(--border-card);
            border-radius: 999px;
            padding: 2px;
        }

        .lang-switch-link {
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 0.74rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .lang-switch-link.active {
            background: #ffffff;
            color: #b45309;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        /* Header Hero */
        .demo-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .badge-live-demo {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 1rem;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 999px;
            color: #b45309;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            margin-bottom: 1.15rem;
        }

        .demo-title {
            font-family: 'Outfit', sans-serif;
            font-size: 2.25rem;
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: 0.75rem;
            line-height: 1.2;
            color: #0f172a;
        }

        .demo-title span {
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .demo-subtitle {
            font-size: 1rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Account Cards Grid */
        .demo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .demo-grid {
                grid-template-columns: 1fr;
            }
        }

        .demo-card {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 20px;
            padding: 2rem 1.75rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            box-shadow: var(--shadow-card);
            transition: all 0.25s ease;
        }

        .demo-card:hover {
            transform: translateY(-3px);
            border-color: #cbd5e1;
            box-shadow: var(--shadow-card-hover);
        }

        .demo-card.featured {
            border-color: #fde68a;
            background: #ffffff;
            box-shadow: 0 10px 30px -5px rgba(245, 158, 11, 0.12), 0 4px 12px -2px rgba(15, 23, 42, 0.04);
        }

        .featured-pill {
            position: absolute;
            top: -12px;
            right: 1.5rem;
            background: var(--accent-gradient);
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3);
        }

        .card-top {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .role-avatar {
            width: 58px;
            height: 58px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .avatar-owner {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #d97706;
        }

        .avatar-manager {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            color: #0284c7;
        }

        .role-meta h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.22rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.2rem;
        }

        .role-meta p {
            font-size: 0.82rem;
            color: #d97706;
            font-weight: 600;
        }

        .credentials-pill {
            background: var(--bg-card-inner);
            border: 1px solid var(--border-card);
            border-radius: 10px;
            padding: 0.65rem 0.85rem;
            font-size: 0.82rem;
            margin-bottom: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .cred-row {
            display: flex;
            justify-content: space-between;
            color: var(--text-secondary);
        }

        .cred-row strong {
            color: #0f172a;
            font-weight: 600;
            font-family: monospace;
        }

        .features-list {
            list-style: none;
            margin-bottom: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }

        .features-list li {
            font-size: 0.84rem;
            color: #334155;
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            line-height: 1.45;
        }

        .features-list li i {
            margin-top: 0.2rem;
            font-size: 0.85rem;
        }

        .btn-oneclick {
            width: 100%;
            padding: 0.88rem;
            border-radius: 12px;
            font-family: 'Outfit', sans-serif;
            font-size: 0.98rem;
            font-weight: 800;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-owner {
            background: var(--accent-gradient);
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35);
        }

        .btn-owner:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(245, 158, 11, 0.45);
            opacity: 0.95;
        }

        .btn-manager {
            background: linear-gradient(135deg, #0284c7, #2563eb);
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35);
        }

        .btn-manager:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(2, 132, 199, 0.45);
            opacity: 0.95;
        }

        /* Features Banner */
        .demo-features-banner {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 20px;
            padding: 1.75rem 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-card);
        }

        .banner-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .banner-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.25rem;
        }

        @media (max-width: 680px) {
            .banner-grid {
                grid-template-columns: 1fr;
            }
        }

        .banner-item {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
        }

        .banner-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #d97706;
            font-size: 0.95rem;
            flex-shrink: 0;
        }

        .banner-item-text h4 {
            font-size: 0.88rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.2rem;
        }

        .banner-item-text p {
            font-size: 0.78rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        /* Register CTA Footer */
        .bottom-cta-card {
            text-align: center;
            padding: 1.75rem 1.25rem;
            border-radius: 18px;
            background: #ffffff;
            border: 1px solid var(--border-card);
            box-shadow: var(--shadow-card);
        }

        .bottom-cta-card h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.4rem;
        }

        .bottom-cta-card p {
            font-size: 0.86rem;
            color: var(--text-secondary);
            margin-bottom: 1.15rem;
        }

        .cta-buttons-row {
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn-register-action {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--accent-gradient);
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 0.95rem;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .btn-register-action:hover {
            transform: translateY(-1px);
            opacity: 0.95;
            box-shadow: 0 6px 16px rgba(245, 158, 11, 0.4);
        }

        .btn-login-action {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            background: #ffffff;
            border: 1px solid var(--border-card);
            color: #334155;
            font-size: 0.88rem;
            font-weight: 600;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-login-action:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
        }
    </style>
</head>
<body>
    <div class="demo-container">
        <!-- Top Navigation -->
        <div class="top-nav">
            <a href="/" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Home') }}
            </a>
            <div class="top-nav-actions">
                <div class="lang-switch-box">
                    <a href="{{ route('lang.switch', 'hy') }}" class="lang-switch-link {{ app()->getLocale() === 'hy' ? 'active' : '' }}" title="Հայերեն">🇦🇲 ՀԱՅ</a>
                    <a href="{{ route('lang.switch', 'en') }}" class="lang-switch-link {{ app()->getLocale() === 'en' ? 'active' : '' }}" title="English">🇬🇧 ENG</a>
                    <a href="{{ route('lang.switch', 'ru') }}" class="lang-switch-link {{ app()->getLocale() === 'ru' ? 'active' : '' }}" title="Русский">🇷🇺 РУС</a>
                </div>
                <a href="{{ route('login') }}" class="btn-top-login">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> {{ __('Sign In') }}
                </a>
                <a href="{{ route('register.show') }}" class="btn-top-register">
                    <i class="fa-solid fa-user-plus"></i> {{ __('nav_register_cta') }}
                </a>
            </div>
        </div>

        <!-- Header Hero -->
        <div class="demo-header">
            <div class="badge-live-demo">
                <i class="fa-solid fa-bolt"></i> Interactive Demo System • Free Exploration
            </div>
            <h1 class="demo-title">
                Explore the <span>QRMenu</span> Platform
            </h1>
            <p class="demo-subtitle">
                Discover all restaurant management tools and digital menu features before registering. Instant 1-click access.
            </p>
        </div>

        @if($errors->any())
            <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 0.85rem 1.25rem; border-radius: 12px; font-size: 0.88rem; margin-bottom: 1.5rem; text-align: center;">
                {{ $errors->first() }}
            </div>
        @endif

        <!-- Two Demo Vendor Persona Cards -->
        <div class="demo-grid">
            <!-- 1. Vendor Owner Card -->
            <div class="demo-card featured">
                <div class="featured-pill">
                    <i class="fa-solid fa-star"></i> Full Access
                </div>

                <div>
                    <div class="card-top">
                        <div class="role-avatar avatar-owner">
                            <i class="fa-solid fa-crown"></i>
                        </div>
                        <div class="role-meta">
                            <h3>Bistro Yerevan</h3>
                            <p><i class="fa-solid fa-store"></i> Restaurant Owner</p>
                        </div>
                    </div>

                    <div class="credentials-pill">
                        <div class="cred-row">
                            <span>User:</span>
                            <strong>Arman Petrosyan</strong>
                        </div>
                        <div class="cred-row">
                            <span>Email:</span>
                            <strong>owner@bistro.am</strong>
                        </div>
                    </div>

                    <ul class="features-list">
                        <li>
                            <i class="fa-solid fa-circle-check" style="color: #059669;"></i>
                            <span><strong>Menu Management:</strong> Dishes, categories, prices, photos & allergens</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-circle-check" style="color: #059669;"></i>
                            <span><strong>QR Studio:</strong> Generate branded table QR codes with custom logo</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-circle-check" style="color: #059669;"></i>
                            <span><strong>Live Analytics:</strong> Real-time sales, orders, and visitor charts</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-circle-check" style="color: #059669;"></i>
                            <span><strong>Settings & Staff:</strong> Branches, managers, and service staff</span>
                        </li>
                    </ul>
                </div>

                <form action="{{ route('demo.login.post') }}" method="POST">
                    @csrf
                    <input type="hidden" name="role" value="owner">
                    <button type="submit" class="btn-oneclick btn-owner">
                        <i class="fa-solid fa-bolt"></i>
                        <span>Sign In as Owner</span>
                    </button>
                </form>
            </div>

            <!-- 2. Branch Manager Card -->
            <div class="demo-card">
                <div>
                    <div class="card-top">
                        <div class="role-avatar avatar-manager">
                            <i class="fa-solid fa-user-tie"></i>
                        </div>
                        <div class="role-meta">
                            <h3>Cascades Branch</h3>
                            <p style="color: #0284c7;"><i class="fa-solid fa-location-dot"></i> Branch Manager</p>
                        </div>
                    </div>

                    <div class="credentials-pill">
                        <div class="cred-row">
                            <span>User:</span>
                            <strong>Anahit Sargsyan</strong>
                        </div>
                        <div class="cred-row">
                            <span>Email:</span>
                            <strong>manager@bistro.am</strong>
                        </div>
                    </div>

                    <ul class="features-list">
                        <li>
                            <i class="fa-solid fa-circle-check" style="color: #0284c7;"></i>
                            <span><strong>Live Orders:</strong> Real-time kitchen and bar order feed</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-circle-check" style="color: #0284c7;"></i>
                            <span><strong>Waiter Calls:</strong> Instant table service paging alerts</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-circle-check" style="color: #0284c7;"></i>
                            <span><strong>Stop-List Mode:</strong> Instant dish availability toggle</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-circle-check" style="color: #0284c7;"></i>
                            <span><strong>Table Management:</strong> Live floor plan and table status tracking</span>
                        </li>
                    </ul>
                </div>

                <form action="{{ route('demo.login.post') }}" method="POST">
                    @csrf
                    <input type="hidden" name="role" value="manager">
                    <button type="submit" class="btn-oneclick btn-manager">
                        <i class="fa-solid fa-bolt"></i>
                        <span>Sign In as Manager</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- System Highlights Feature Banner -->
        <div class="demo-features-banner">
            <div class="banner-title">
                <i class="fa-solid fa-wand-magic-sparkles" style="color: #d97706;"></i>
                What can you explore in the demo?
            </div>
            <div class="banner-grid">
                <div class="banner-item">
                    <div class="banner-icon">
                        <i class="fa-solid fa-qrcode"></i>
                    </div>
                    <div class="banner-item-text">
                        <h4>Smart QR Codes</h4>
                        <p>Create branded table QR codes with custom colors and logo.</p>
                    </div>
                </div>
                <div class="banner-item">
                    <div class="banner-icon">
                        <i class="fa-solid fa-bell-concierge"></i>
                    </div>
                    <div class="banner-item-text">
                        <h4>Online Ordering & Paging</h4>
                        <p>Guests order from table and call staff with zero app downloads.</p>
                    </div>
                </div>
                <div class="banner-item">
                    <div class="banner-icon">
                        <i class="fa-solid fa-brain"></i>
                    </div>
                    <div class="banner-item-text">
                        <h4>AI Waiter & Assistant</h4>
                        <p>Automated pairings, dietary advice, and smart menu import.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Registration CTA Block -->
        <div class="bottom-cta-card">
            <h3>Ready to launch your restaurant's digital menu?</h3>
            <p>Sign up now and get a 14-day free trial with all features included.</p>
            <div class="cta-buttons-row">
                <a href="{{ route('register.show') }}" class="btn-register-action">
                    <i class="fa-solid fa-rocket"></i>
                    <span>Register (14 Days Free)</span>
                </a>
                <a href="{{ route('login') }}" class="btn-login-action">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    <span>Already have an account? Sign In</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
