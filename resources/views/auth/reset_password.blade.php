<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Նոր Գաղտնաբառի Սահմանում — QRMenu SaaS</title>
    
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
        }

        .auth-wrapper {
            width: 100%;
            max-width: 440px;
            position: relative;
            z-index: 10;
        }

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

        .auth-card {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 24px;
            padding: 2.5rem 2.25rem;
            box-shadow: var(--shadow-card);
        }

        .card-header {
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .card-icon-box {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: var(--accent-amber-dark);
            margin-bottom: 1rem;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.15);
        }

        .card-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #0f172a;
            margin-bottom: 0.4rem;
        }

        .card-subtitle {
            font-size: 0.88rem;
            color: var(--text-secondary);
            line-height: 1.45;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            font-size: 0.86rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
        }

        .form-group {
            margin-bottom: 1.15rem;
        }

        .form-label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.45rem;
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
        }

        .captcha-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .captcha-svg-container {
            display: flex;
            align-items: center;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .btn-refresh-captcha {
            padding: 0.6rem 0.75rem;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            color: #475569;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

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
            margin-top: 0.5rem;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(245, 158, 11, 0.45);
        }

        .footer-note {
            text-align: center;
            margin-top: 1.75rem;
            font-size: 0.78rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="top-nav">
            <a href="{{ route('login') }}" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Login') }}
            </a>
            <div class="lang-switch-box">
                <a href="{{ route('lang.switch', 'hy') }}" class="lang-switch-link {{ app()->getLocale() === 'hy' ? 'active' : '' }}" title="Հայերեն">🇦🇲 ՀԱՅ</a>
                <a href="{{ route('lang.switch', 'en') }}" class="lang-switch-link {{ app()->getLocale() === 'en' ? 'active' : '' }}" title="English">🇬🇧 ENG</a>
                <a href="{{ route('lang.switch', 'ru') }}" class="lang-switch-link {{ app()->getLocale() === 'ru' ? 'active' : '' }}" title="Русский">🇷🇺 РУС</a>
            </div>
        </div>

        <div class="auth-card">
            <div class="card-header">
                <div class="card-icon-box">
                    <i class="fa-solid fa-lock-open"></i>
                </div>
                <h1 class="card-title">{{ __('New Password') }}</h1>
                <p class="card-subtitle">
                    {{ __('Set a new secure password for your account') }}
                </p>
            </div>

            @if($errors->any())
                <div class="alert-error">
                    <i class="fa-solid fa-circle-exclamation" style="font-size: 1.1rem; margin-top: 0.1rem;"></i>
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            <form action="{{ route('password.update') }}" method="POST">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="form-group">
                    <label class="form-label">{{ __('Email Address') }}</label>
                    <div class="input-wrapper">
                        <input 
                            type="email" 
                            name="email" 
                            class="form-input" 
                            required 
                            readonly 
                            value="{{ old('email', $email) }}"
                            style="background: #f1f5f9; cursor: not-allowed;"
                        >
                        <i class="fa-solid fa-envelope input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">{{ __('New Password') }}</label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            name="password" 
                            id="newPassInput" 
                            class="form-input has-toggle" 
                            required 
                            autofocus 
                            placeholder="••••••••"
                        >
                        <i class="fa-solid fa-lock input-icon"></i>
                        <button type="button" class="password-toggle-btn" onclick="togglePass('newPassInput', 'newPassIcon')">
                            <i class="fa-regular fa-eye" id="newPassIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">{{ __('Confirm Password') }}</label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            name="password_confirmation" 
                            id="confirmPassInput" 
                            class="form-input has-toggle" 
                            required 
                            placeholder="••••••••"
                        >
                        <i class="fa-solid fa-shield-halved input-icon"></i>
                        <button type="button" class="password-toggle-btn" onclick="togglePass('confirmPassInput', 'confirmPassIcon')">
                            <i class="fa-regular fa-eye" id="confirmPassIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- CAPTCHA -->
                <div class="form-group">
                    <label class="form-label">{{ __('Security Verification (CAPTCHA)') }}</label>
                    <div class="captcha-row">
                        <div class="captcha-svg-container" id="captchaContainer">
                            {!! $captcha['svg'] !!}
                        </div>
                        <button type="button" class="btn-refresh-captcha" onclick="refreshCaptcha()" title="{{ __('Refresh Question') }}">
                            <i class="fa-solid fa-arrows-rotate"></i>
                        </button>
                    </div>
                    <div class="input-wrapper">
                        <input 
                            type="text" 
                            name="captcha" 
                            class="form-input" 
                            required 
                            placeholder="{{ __('Enter the math answer') }}"
                            autocomplete="off"
                            inputmode="numeric"
                        >
                        <i class="fa-solid fa-shield input-icon"></i>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-check"></i>
                    <span>{{ __('Save New Password') }}</span>
                </button>
            </form>
        </div>

        <div class="footer-note">
            &copy; {{ date('Y') }} QRMenu SaaS Platform
        </div>
    </div>

    <script>
        function togglePass(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input && icon) {
                const isPass = input.type === 'password';
                input.type = isPass ? 'text' : 'password';
                icon.classList.toggle('fa-eye', !isPass);
                icon.classList.toggle('fa-eye-slash', isPass);
            }
        }

        function refreshCaptcha() {
            fetch("{{ route('captcha.refresh') }}")
                .then(res => res.json())
                .then(data => {
                    if (data.svg) {
                        document.getElementById('captchaContainer').innerHTML = data.svg;
                    }
                })
                .catch(() => {});
        }
    </script>
</body>
</html>
