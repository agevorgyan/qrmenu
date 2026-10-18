<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $siteName = \App\Models\SystemSetting::getSiteName();
        $siteFavicon = \App\Models\SystemSetting::getFavicon();
        $siteLogoLight = \App\Models\SystemSetting::getLogoLight();
        $siteTagline = \App\Models\SystemSetting::getSiteTagline();
    @endphp
    <title>{{ isset($customVendor) && $customVendor ? $customVendor->name . ' — ' . __('Sign In') : (__('System Login') . ' — ' . $siteName) }}</title>
    
    <link rel="icon" type="image/png" href="{{ $siteFavicon }}">
    <link rel="apple-touch-icon" href="{{ $siteFavicon }}">
    
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
            --bg-input: #f8fafc;
            --border-card: #e2e8f0;
            --border-input: #cbd5e1;
            --border-focus: #f59e0b;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --accent-amber: #f59e0b;
            --accent-amber-dark: #d97706;
            --accent-gradient: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
            --shadow-card: 0 10px 30px -5px rgba(15, 23, 42, 0.08), 0 4px 12px -2px rgba(15, 23, 42, 0.04);
            --shadow-input-focus: 0 0 0 3px rgba(245, 158, 11, 0.18);
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
            padding: 1.5rem 1rem;
            position: relative;
        }

        .login-wrapper {
            width: 100%;
            max-width: 440px;
            position: relative;
            z-index: 10;
        }

        /* Top Navigation Bar */
        .top-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding: 0 0.25rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.86rem;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.2s ease, transform 0.2s ease;
        }

        .back-link:hover {
            color: var(--text-primary);
            transform: translateX(-3px);
        }

        .nav-badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.8rem;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 999px;
            color: #b45309;
            font-size: 0.78rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .nav-badge-pill:hover {
            background: #fef3c7;
            border-color: #f59e0b;
            color: #92400e;
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

        /* Login Card */
        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 24px;
            padding: 2.5rem 2.25rem;
            box-shadow: var(--shadow-card);
            position: relative;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 2rem 1.5rem;
                border-radius: 20px;
            }
        }

        /* Brand & Header */
        .brand-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .brand-icon-box {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            border: 1px solid #fde68a;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: var(--accent-amber-dark);
            margin-bottom: 1rem;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.15);
        }

        .vendor-avatar {
            width: 72px;
            height: 72px;
            border-radius: 20px;
            object-fit: cover;
            border: 2px solid {{ isset($customVendor) && $customVendor->primary_color ? $customVendor->primary_color : '#f59e0b' }};
            margin-bottom: 1rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .brand-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 0.35rem;
            color: #0f172a;
        }

        .brand-title span {
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .vendor-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.65rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.35rem;
        }

        .brand-subtitle {
            font-size: 0.88rem;
            color: var(--text-secondary);
            line-height: 1.45;
        }

        /* Alerts */
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            font-size: 0.86rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            line-height: 1.4;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            font-size: 0.86rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            line-height: 1.4;
        }

        /* Form Fields */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.82rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.5rem;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            color: #94a3b8;
            font-size: 0.95rem;
            pointer-events: none;
            transition: color 0.2s ease;
        }

        .form-input {
            width: 100%;
            padding: 0.82rem 1rem 0.82rem 2.75rem;
            background: var(--bg-input);
            border: 1px solid var(--border-input);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 0.93rem;
            font-family: inherit;
            outline: none;
            transition: all 0.2s ease;
        }

        .form-input.has-toggle {
            padding-right: 2.75rem;
        }

        .form-input:focus {
            border-color: var(--border-focus);
            background: #ffffff;
            box-shadow: var(--shadow-input-focus);
        }

        .form-input:focus + .input-icon {
            color: var(--accent-amber-dark);
        }

        .password-toggle-btn {
            position: absolute;
            right: 0.85rem;
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 1rem;
            cursor: pointer;
            padding: 0.4rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: color 0.2s ease;
        }

        .password-toggle-btn:hover {
            color: #475569;
        }

        /* Options */
        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 1.15rem 0 1.5rem;
            font-size: 0.84rem;
        }

        .checkbox-label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            user-select: none;
        }

        .checkbox-custom {
            appearance: none;
            width: 18px;
            height: 18px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            background: #ffffff;
            cursor: pointer;
            position: relative;
            transition: all 0.2s ease;
        }

        .checkbox-custom:checked {
            background: var(--accent-amber);
            border-color: var(--accent-amber);
        }

        .checkbox-custom:checked::after {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 0.65rem;
            color: #ffffff;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 0.92rem;
            background: var(--accent-gradient);
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.02rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35);
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(245, 158, 11, 0.45);
            opacity: 0.95;
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        /* Divider */
        .card-divider {
            position: relative;
            text-align: center;
            margin: 1.75rem 0 1.25rem;
        }

        .card-divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e2e8f0;
        }

        .card-divider span {
            position: relative;
            background: #ffffff;
            padding: 0 0.85rem;
            font-size: 0.78rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }

        /* Dedicated Demo Promo Card */
        .demo-promo-card {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 14px;
            padding: 1rem 1.15rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            transition: all 0.2s ease;
        }

        .demo-promo-card:hover {
            border-color: #f59e0b;
            background: #fef3c7;
        }

        .demo-promo-info {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .demo-promo-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #ffffff;
            border: 1px solid #fde68a;
            color: #d97706;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            flex-shrink: 0;
        }

        .demo-promo-title {
            font-size: 0.88rem;
            font-weight: 700;
            color: #92400e;
            margin-bottom: 0.15rem;
        }

        .demo-promo-desc {
            font-size: 0.76rem;
            color: #b45309;
        }

        .btn-demo-link {
            padding: 0.52rem 0.95rem;
            background: #d97706;
            border-radius: 8px;
            color: #ffffff;
            font-size: 0.8rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            white-space: nowrap;
            transition: all 0.2s ease;
        }

        .btn-demo-link:hover {
            background: #b45309;
            transform: translateY(-1px);
        }

        /* Register Banner Link */
        .register-link-box {
            text-align: center;
            font-size: 0.86rem;
            color: var(--text-secondary);
        }

        .register-link-box a {
            color: #d97706;
            font-weight: 700;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .register-link-box a:hover {
            color: #b45309;
            text-decoration: underline;
        }

        /* Footer */
        .footer-note {
            text-align: center;
            margin-top: 1.75rem;
            font-size: 0.78rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .footer-note a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-note a:hover {
            color: var(--text-primary);
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Top Navigation -->
        <div class="top-nav">
            @if(isset($customVendor) && $customVendor)
                <a href="/" class="back-link">
                    <i class="fa-solid fa-arrow-left"></i> {{ $customVendor->name }} {{ __('View Menu') }}
                </a>
                <div style="display: flex; align-items: center; gap: 0.65rem;">
                    @include('auth.partials.lang_switcher')
                    <span class="nav-badge-pill">
                        <i class="fa-solid fa-store"></i> {{ $customVendor->name }}
                    </span>
                </div>
            @else
                <a href="/" class="back-link">
                    <i class="fa-solid fa-arrow-left"></i> {{ __('Home') }}
                </a>
                <div style="display: flex; align-items: center; gap: 0.65rem;">
                    @include('auth.partials.lang_switcher')
                    <a href="{{ route('demo.login') }}" class="nav-badge-pill">
                        <i class="fa-solid fa-bolt"></i> {{ __('Try Demo') }}
                    </a>
                </div>
            @endif
        </div>

        <div class="login-card">
            <!-- Brand / Restaurant Header -->
            <div class="brand-header">
                @if(isset($customVendor) && $customVendor)
                    @if($customVendor->logo)
                        <img src="{{ $customVendor->logo }}" class="vendor-avatar" alt="{{ $customVendor->name }}">
                    @else
                        <div class="brand-icon-box" style="border-color: {{ $customVendor->primary_color ?? '#f59e0b' }}; color: {{ $customVendor->primary_color ?? '#d97706' }};">
                            <i class="fa-solid fa-utensils"></i>
                        </div>
                    @endif
                    <h1 class="vendor-title">{{ $customVendor->name }}</h1>
                    <p class="brand-subtitle">{{ __('Կառավարման Վահանակ') }}</p>
                @else
                    @if($siteLogoLight)
                        <div style="margin-bottom: 1.25rem;">
                            <img src="{{ $siteLogoLight }}" alt="{{ $siteName }}" style="max-height: 48px; max-width: 220px; object-fit: contain;">
                        </div>
                    @else
                        <div class="brand-icon-box">
                            <i class="fa-solid fa-qrcode"></i>
                        </div>
                        <h1 class="brand-title">{{ $siteName }}</h1>
                    @endif
                    <p class="brand-subtitle">{{ __($siteTagline ?: 'Sign in to your restaurant dashboard') }}</p>
                @endif
            </div>

            <!-- Errors Alert -->
            @if($errors->any())
                <div class="alert-error">
                    <i class="fa-solid fa-circle-exclamation" style="font-size: 1.1rem; margin-top: 0.1rem;"></i>
                    <div>
                        {{ $errors->first() }}
                    </div>
                </div>
            @endif

            <!-- Success Alert -->
            @if(session('status') || session('success'))
                <div class="alert-success">
                    <i class="fa-solid fa-circle-check" style="font-size: 1.1rem; margin-top: 0.1rem;"></i>
                    <div>
                        {{ __(session('status') ?? session('success')) }}
                    </div>
                </div>
            @endif

            <!-- Login Form -->
            <form action="{{ route('login.post') }}" method="POST">
                @csrf

                <div class="form-group">
                    <label class="form-label">
                        <span><i class="fa-regular fa-envelope"></i> {{ __('Email Address') }}</span>
                    </label>
                    <div class="input-wrapper">
                        <input 
                            type="email" 
                            name="email" 
                            id="emailInput" 
                            class="form-input" 
                            required 
                            autofocus 
                            placeholder="{{ isset($customVendor) && $customVendor ? 'staff@' . ($customVendor->slug ?? 'restaurant') . '.am' : 'owner@bistro.am' }}" 
                            value="{{ old('email') }}"
                        >
                        <i class="fa-solid fa-at input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <span><i class="fa-solid fa-lock"></i> {{ __('Password') }}</span>
                    </label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            name="password" 
                            id="passwordInput" 
                            class="form-input has-toggle" 
                            required 
                            placeholder="••••••••"
                        >
                        <i class="fa-solid fa-key input-icon"></i>
                        <button type="button" class="password-toggle-btn" id="togglePasswordBtn" title="{{ __('Show/Hide password') }}">
                            <i class="fa-regular fa-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" class="checkbox-custom" {{ old('remember') ? 'checked' : '' }}>
                        <span>{{ __('Remember me') }}</span>
                    </label>
                    <a href="{{ route('password.request') }}" style="color: #d97706; text-decoration: none; font-size: 0.82rem; font-weight: 600;">
                        {{ __('Forgot password?') }}
                    </a>
                </div>

                <!-- CAPTCHA Field -->
                @if(isset($captcha))
                    <div class="form-group">
                        <label class="form-label">
                            <span><i class="fa-solid fa-shield-halved"></i> {{ __('Security Check (CAPTCHA)') }}</span>
                        </label>
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                            <div id="captchaContainer" style="display: flex; align-items: center; border-radius: 8px; overflow: hidden; flex-shrink: 0;">
                                {!! $captcha['svg'] !!}
                            </div>
                            <button type="button" onclick="refreshCaptcha()" title="{{ __('Refresh question') }}" style="padding: 0.55rem 0.75rem; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; color: #475569; cursor: pointer; font-size: 0.9rem;">
                                <i class="fa-solid fa-arrows-rotate"></i>
                            </button>
                        </div>
                        <div class="input-wrapper">
                            <input 
                                type="text" 
                                name="captcha" 
                                class="form-input" 
                                required 
                                placeholder="{{ __('Enter math answer') }}"
                                autocomplete="off"
                                inputmode="numeric"
                            >
                            <i class="fa-solid fa-calculator input-icon"></i>
                        </div>
                    </div>
                @endif

                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    <span>{{ __('Sign In') }}</span>
                </button>
            </form>

            @if(!isset($customVendor) || !$customVendor)
                <div class="card-divider">
                    <span>{{ __('or') }}</span>
                </div>

                <!-- Quick Demo Promo Box -->
                <div class="demo-promo-card">
                    <div class="demo-promo-info">
                        <div class="demo-promo-icon">
                            <i class="fa-solid fa-bolt"></i>
                        </div>
                        <div>
                            <div class="demo-promo-title">{{ __('Explore Demo') }}</div>
                            <div class="demo-promo-desc">{{ __('Test the system in 1 click') }}</div>
                        </div>
                    </div>
                    <a href="{{ route('demo.login') }}" class="btn-demo-link">
                        <span>{{ __('Demo Login') }}</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>

                <!-- Self Registration CTA -->
                <div class="register-link-box">
                    {{ __('Don\'t have a registered restaurant yet?') }} <br>
                    <a href="{{ route('register.show') }}">
                        <i class="fa-solid fa-user-plus"></i> {{ __('Register as New Partner (14 days free)') }}
                    </a>
                </div>
            @else
                <div style="text-align: center; margin-top: 1.5rem; font-size: 0.84rem;">
                    <a href="/" style="color: {{ $customVendor->primary_color ?? '#0284c7' }}; font-weight: 600; text-decoration: none;">
                        &larr; {{ __('Back to') }} {{ $customVendor->name }} {{ __('digital menu') }}
                    </a>
                </div>
            @endif
        </div>

        <div class="footer-note">
            <span>&copy; {{ date('Y') }} QRMenu SaaS Platform</span>
            <span>•</span>
            <a href="{{ route('legal.privacy') }}">{{ __('Privacy Policy') }}</a>
            <span>•</span>
            <a href="{{ route('legal.terms') }}">{{ __('Terms of Service') }}</a>
        </div>
    </div>

    <script>
        // Password Visibility Toggle
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const passInput = document.getElementById('passwordInput');
        const toggleIcon = document.getElementById('togglePasswordIcon');

        if (toggleBtn && passInput && toggleIcon) {
            toggleBtn.addEventListener('click', function () {
                const isPassword = passInput.getAttribute('type') === 'password';
                passInput.setAttribute('type', isPassword ? 'text' : 'password');
                toggleIcon.classList.toggle('fa-eye', !isPassword);
                toggleIcon.classList.toggle('fa-eye-slash', isPassword);
            });
        }

        function refreshCaptcha() {
            fetch("{{ route('captcha.refresh') }}")
                .then(res => res.json())
                .then(data => {
                    if (data.svg) {
                        const container = document.getElementById('captchaContainer');
                        if (container) {
                            container.innerHTML = data.svg;
                        }
                    }
                })
                .catch(() => {});
        }
    </script>
</body>
</html>
