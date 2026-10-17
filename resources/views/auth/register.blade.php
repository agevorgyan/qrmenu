<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $siteName = \App\Models\SystemSetting::getSiteName();
        $siteFavicon = \App\Models\SystemSetting::getFavicon();
        $siteLogoLight = \App\Models\SystemSetting::getLogoLight();
    @endphp
    <title>{{ __('Partner Registration') }} — {{ $siteName }}</title>
    
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
            padding: 2.5rem 1rem;
            position: relative;
        }

        .register-container {
            width: 100%;
            max-width: 860px;
            position: relative;
            z-index: 10;
        }

        /* Top Navigation Bar */
        .top-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.75rem;
            padding: 0 0.25rem;
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

        .nav-pill-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.85rem;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 999px;
            color: #b45309;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .nav-pill-btn:hover {
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

        /* Main Registration Card */
        .register-card {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 28px;
            padding: 2.75rem 2.5rem;
            box-shadow: var(--shadow-card);
            position: relative;
        }

        @media (max-width: 640px) {
            .register-card {
                padding: 1.75rem 1.25rem;
                border-radius: 20px;
            }
        }

        /* Brand & Header */
        .brand-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .trial-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.35rem 0.95rem;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 999px;
            color: #047857;
            font-size: 0.82rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .brand-title {
            font-family: 'Outfit', sans-serif;
            font-size: 2.15rem;
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: 0.5rem;
            color: #0f172a;
        }

        .brand-title span {
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .brand-subtitle {
            font-size: 0.95rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.5;
        }

        /* Demo Notice Banner */
        .demo-bar-banner {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 14px;
            padding: 0.85rem 1.25rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .demo-bar-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.86rem;
            color: #92400e;
            font-weight: 500;
        }

        .demo-bar-info i {
            color: #d97706;
            font-size: 1.1rem;
        }

        .btn-demo-inline {
            color: #ffffff;
            font-weight: 700;
            font-size: 0.82rem;
            text-decoration: none;
            padding: 0.4rem 0.85rem;
            background: #d97706;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: all 0.2s ease;
        }

        .btn-demo-inline:hover {
            background: #b45309;
            transform: translateY(-1px);
        }

        /* Error Alert */
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 1rem 1.25rem;
            border-radius: 14px;
            font-size: 0.88rem;
            margin-bottom: 2rem;
        }

        .alert-error ul {
            padding-left: 1.25rem;
            margin-top: 0.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        /* Section Headings */
        .section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 2rem 0 1.25rem;
            padding-bottom: 0.65rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .section-header:first-of-type {
            margin-top: 0;
        }

        .section-number {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #b45309;
            font-size: 0.82rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .section-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.01em;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.15rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 0.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            font-size: 0.83rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.45rem;
        }

        .form-label .req {
            color: #ef4444;
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
            font-size: 0.92rem;
            pointer-events: none;
            transition: color 0.2s ease;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.78rem 1rem 0.78rem 2.65rem;
            background: var(--bg-input);
            border: 1px solid var(--border-input);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 0.92rem;
            font-family: inherit;
            outline: none;
            transition: all 0.2s ease;
        }

        .form-textarea {
            padding-left: 1rem;
            resize: vertical;
            min-height: 80px;
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1.15rem;
            padding-right: 2.75rem;
            cursor: pointer;
        }

        .form-input.has-toggle {
            padding-right: 2.65rem;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--border-focus);
            background: #ffffff;
            box-shadow: var(--shadow-input-focus);
        }

        .form-input:focus + .input-icon, .form-select:focus + .input-icon {
            color: var(--accent-amber-dark);
        }

        .password-toggle-btn {
            position: absolute;
            right: 0.75rem;
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 0.95rem;
            cursor: pointer;
            padding: 0.35rem;
            border-radius: 6px;
            transition: color 0.2s ease;
        }

        .password-toggle-btn:hover {
            color: #475569;
        }

        /* Submit Button */
        .btn-submit-register {
            width: 100%;
            padding: 1rem;
            background: var(--accent-gradient);
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.08rem;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            margin-top: 1.5rem;
            box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35);
        }

        .btn-submit-register:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.45);
            opacity: 0.96;
        }

        .terms-note {
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 1rem;
            line-height: 1.5;
        }

        .terms-note a {
            color: var(--text-secondary);
            text-decoration: underline;
        }

        .terms-note a:hover {
            color: var(--text-primary);
        }

        /* Login Redirect Footer */
        .login-redirect-box {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .login-redirect-box a {
            color: #d97706;
            font-weight: 700;
            text-decoration: none;
            margin-left: 0.35rem;
            transition: color 0.2s ease;
        }

        .login-redirect-box a:hover {
            color: #b45309;
            text-decoration: underline;
        }

        .footer-note {
            text-align: center;
            margin-top: 2rem;
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
    <div class="register-container">
        <!-- Top Navigation -->
        <div class="top-nav">
            <a href="/" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Home') }}
            </a>
            <div class="top-nav-actions">
                @include('auth.partials.lang_switcher')
                <a href="{{ route('demo.login') }}" class="nav-pill-btn">
                    <i class="fa-solid fa-bolt"></i> {{ __('Try Demo') }}
                </a>
                <a href="{{ route('login') }}" class="nav-pill-btn" style="background: #ffffff; border-color: #cbd5e1; color: #334155;">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> {{ __('Login') }}
                </a>
            </div>
        </div>

        <div class="register-card">
            <!-- Header -->
            <div class="brand-header">
                @if($siteLogoLight)
                    <div style="margin-bottom: 1.25rem;">
                        <img src="{{ $siteLogoLight }}" alt="{{ $siteName }}" style="max-height: 48px; max-width: 220px; object-fit: contain;">
                    </div>
                @endif
                <div class="trial-badge">
                    <i class="fa-solid fa-gift"></i> {{ __('14-Day Free Trial • No Credit Card Required') }}
                </div>
                <h1 class="brand-title">
                    {{ __('Partner') }} <span>{{ __('Registration') }}</span>
                </h1>
                <p class="brand-subtitle">
                    {{ __('Create your account in 2 minutes and start accepting online orders with a digital QR menu.') }}
                </p>
            </div>

            <!-- Demo Link Banner -->
            <div class="demo-bar-banner">
                <div class="demo-bar-info">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    <span>{{ __('Want to explore the platform first without registering?') }}</span>
                </div>
                <a href="{{ route('demo.login') }}" class="btn-demo-inline">
                    <span>{{ __('Explore Demo Version') }}</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            @if($errors->any())
                <div class="alert-error">
                    <div style="font-weight: 700; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span>{{ __('Please correct the following errors:') }}</span>
                    </div>
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('register.post') }}" method="POST">
                @csrf

                <!-- 1. Օբյեկտի Տվյալներ -->
                <div class="section-header">
                    <div class="section-number">1</div>
                    <div class="section-title">{{ __('Restaurant / Venue Details') }}</div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">{{ __('Venue Type') }} <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <select name="type" class="form-select" required>
                                <option value="restaurant" {{ old('type') == 'restaurant' ? 'selected' : '' }}>{{ __('Restaurant') }}</option>
                                <option value="cafe" {{ old('type') == 'cafe' ? 'selected' : '' }}>{{ __('Cafe') }}</option>
                                <option value="hotel" {{ old('type') == 'hotel' ? 'selected' : '' }}>{{ __('Hotel & Lounge') }}</option>
                            </select>
                            <i class="fa-solid fa-utensils input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ __('Brand Name') }} <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <input type="text" name="name" class="form-input" required placeholder="{{ __('e.g. Verona Lounge & Cafe') }}" value="{{ old('name') }}">
                            <i class="fa-solid fa-store input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ __('Number of Branches') }} <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <input type="number" name="expected_locations_count" min="1" max="100" class="form-input" required value="{{ old('expected_locations_count', 1) }}">
                            <i class="fa-solid fa-network-wired input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ __('Subscription Plan (14 Days Free)') }} <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <select name="subscription_plan" class="form-select" required>
                                @if(isset($plans) && $plans->count())
                                    @foreach($plans as $p)
                                        <option value="{{ $p->slug }}" {{ old('subscription_plan', request('plan', 'pro')) == $p->slug ? 'selected' : '' }}>
                                            {{ $p->name }} — {{ $p->formatted_price }} ({{ __('14-day free trial') }})
                                        </option>
                                    @endforeach
                                @else
                                    <option value="basic" {{ old('subscription_plan', request('plan', 'pro')) == 'basic' ? 'selected' : '' }}>Basic — 9 900 AMD / {{ __('month') }} ({{ __('14-day free trial') }})</option>
                                    <option value="pro" {{ old('subscription_plan', request('plan', 'pro')) == 'pro' ? 'selected' : '' }}>Pro — 19 900 AMD / {{ __('month') }} ({{ __('14-day free trial') }})</option>
                                    <option value="business" {{ old('subscription_plan', request('plan', 'pro')) == 'business' ? 'selected' : '' }}>Business — 34 900 AMD / {{ __('month') }} ({{ __('14-day free trial') }})</option>
                                    <option value="custom" {{ old('subscription_plan', request('plan', 'pro')) == 'custom' ? 'selected' : '' }}>Custom — {{ __('Contractual') }}</option>
                                @endif
                            </select>
                            <i class="fa-solid fa-crown input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">{{ __('Operating Address / Locations') }} <span class="req">*</span></label>
                        <textarea name="operating_address" rows="2" class="form-textarea" required placeholder="{{ __('e.g. 15 Mashtots Ave, Yerevan') }}">{{ old('operating_address') }}</textarea>
                    </div>
                </div>

                <!-- 2. Իրավաբանական Տվյալներ -->
                <div class="section-header">
                    <div class="section-number">2</div>
                    <div class="section-title">{{ __('Legal Details') }}</div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">{{ __('Legal Entity Name (LLC / Sole Proprietorship)') }} <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <input type="text" name="legal_name" class="form-input" required placeholder="{{ __('e.g. Verona Lounge LLC') }}" value="{{ old('legal_name') }}">
                            <i class="fa-solid fa-building input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ __('Tax ID (ՀՎՀՀ)') }} <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <input type="text" name="tax_id" class="form-input" required placeholder="02589412" value="{{ old('tax_id') }}">
                            <i class="fa-solid fa-hashtag input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ __('Legal Address') }} <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <input type="text" name="legal_address" class="form-input" required placeholder="{{ __('e.g. 24 Baghramyan Ave, Yerevan') }}" value="{{ old('legal_address') }}">
                            <i class="fa-solid fa-map-location-dot input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ __('Director Full Name') }} <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <input type="text" name="director_name" class="form-input" required placeholder="{{ __('e.g. Armen Petrosyan') }}" value="{{ old('director_name') }}">
                            <i class="fa-solid fa-user-tie input-icon"></i>
                        </div>
                    </div>
                </div>

                <!-- 3. Կոնտակտ & Մուտքային Հաշիվ -->
                <div class="section-header">
                    <div class="section-number">3</div>
                    <div class="section-title">{{ __('Contact Details & Account Credentials') }}</div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">{{ __('Contact Person Name') }} <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <input type="text" name="contact_person_name" class="form-input" required placeholder="{{ __('e.g. Anahit Sargsyan') }}" value="{{ old('contact_person_name') }}">
                            <i class="fa-solid fa-user input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ __('Phone Number') }} <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <input type="text" name="phone" class="form-input" required placeholder="+37491000111" value="{{ old('phone') }}">
                            <i class="fa-solid fa-phone input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">{{ __('Login Email') }} <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <input type="email" name="email" class="form-input" required placeholder="info@example.com" value="{{ old('email') }}">
                            <i class="fa-solid fa-envelope input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ __('Password') }} <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <input type="password" name="password" id="regPasswordInput" class="form-input has-toggle" required placeholder="••••••••">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <button type="button" class="password-toggle-btn" id="toggleRegPasswordBtn" title="{{ __('Show/Hide password') }}">
                                <i class="fa-regular fa-eye" id="toggleRegPasswordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ __('Confirm Password') }} <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <input type="password" name="password_confirmation" id="regPasswordConfirmInput" class="form-input has-toggle" required placeholder="••••••••">
                            <i class="fa-solid fa-shield-halved input-icon"></i>
                            <button type="button" class="password-toggle-btn" id="toggleRegPasswordConfirmBtn" title="{{ __('Show/Hide password') }}">
                                <i class="fa-regular fa-eye" id="toggleRegPasswordConfirmIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- CAPTCHA Field -->
                    @if(isset($captcha))
                        <div class="form-group full-width">
                            <label class="form-label">
                                <span>{{ __('Security Verification (CAPTCHA)') }} <span class="req">*</span></span>
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
                                    placeholder="{{ __('Enter the calculation answer') }}"
                                    autocomplete="off"
                                    inputmode="numeric"
                                >
                                <i class="fa-solid fa-calculator input-icon"></i>
                            </div>
                        </div>
                    @endif
                </div>

                <button type="submit" class="btn-submit-register">
                    <i class="fa-solid fa-rocket"></i>
                    <span>{{ __('Register and Start 14-Day Free Trial') }}</span>
                </button>

                <p class="terms-note">
                    {{ __('By registering, you agree to our') }} 
                    <a href="{{ route('legal.terms') }}">{{ __('Terms of Service') }}</a> {{ __('and') }} 
                    <a href="{{ route('legal.privacy') }}">{{ __('Privacy Policy') }}</a>.
                </p>

                <div class="login-redirect-box">
                    {{ __('Already have an account?') }} 
                    <a href="{{ route('login') }}">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i> {{ __('Login here') }}
                    </a>
                </div>
            </form>
        </div>

        <div class="footer-note">
            <span>&copy; {{ date('Y') }} {{ $siteName }} Platform</span>
            <span>•</span>
            <a href="{{ route('legal.privacy') }}">{{ __('Privacy Policy') }}</a>
            <span>•</span>
            <a href="{{ route('legal.terms') }}">{{ __('Terms of Service') }}</a>
        </div>
    </div>

    <script>
        // Password Visibility Toggles
        function setupToggle(btnId, inputId, iconId) {
            const btn = document.getElementById(btnId);
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);

            if (btn && input && icon) {
                btn.addEventListener('click', function () {
                    const isPass = input.getAttribute('type') === 'password';
                    input.setAttribute('type', isPass ? 'text' : 'password');
                    icon.classList.toggle('fa-eye', !isPass);
                    icon.classList.toggle('fa-eye-slash', isPass);
                });
            }
        }

        setupToggle('toggleRegPasswordBtn', 'regPasswordInput', 'toggleRegPasswordIcon');
        setupToggle('toggleRegPasswordConfirmBtn', 'regPasswordConfirmInput', 'toggleRegPasswordConfirmIcon');

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
