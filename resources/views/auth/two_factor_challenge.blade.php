<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Երկփուլային Նույնականացում (2FA) — QRMenu</title>
    
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
            font-size: 1.55rem;
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

        /* 2FA Mode Tabs */
        .auth-tabs {
            display: flex;
            background: #f1f5f9;
            border-radius: 12px;
            padding: 0.25rem;
            margin-bottom: 1.5rem;
            gap: 0.25rem;
        }

        .auth-tab-btn {
            flex: 1;
            padding: 0.55rem 0.5rem;
            border: none;
            border-radius: 10px;
            background: none;
            color: var(--text-secondary);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }

        .auth-tab-btn.active {
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
            font-weight: 700;
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

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            font-size: 0.86rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
        }

        .code-input-wrapper {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .code-input {
            width: 100%;
            padding: 0.9rem;
            background: var(--bg-input);
            border: 2px solid var(--border-input);
            border-radius: 12px;
            font-family: monospace;
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: 0.4em;
            text-align: center;
            color: #0f172a;
            outline: none;
            transition: all 0.2s ease;
        }

        .code-input:focus {
            border-color: var(--border-focus);
            background: #ffffff;
            box-shadow: var(--shadow-input-focus);
        }

        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.85rem 1rem;
            font-size: 0.82rem;
            color: var(--text-secondary);
            margin-bottom: 1.25rem;
            line-height: 1.45;
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
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(245, 158, 11, 0.45);
        }

        .resend-box {
            text-align: center;
            margin-top: 1.25rem;
            font-size: 0.84rem;
            color: var(--text-secondary);
        }

        .btn-resend {
            background: none;
            border: none;
            color: #d97706;
            font-weight: 700;
            cursor: pointer;
            padding: 0;
            text-decoration: underline;
        }

        .btn-resend:hover {
            color: #b45309;
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
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <div class="lang-switch-box">
                    <a href="{{ route('lang.switch', 'hy') }}" class="lang-switch-link {{ app()->getLocale() === 'hy' ? 'active' : '' }}" title="Հայերեն">🇦🇲 ՀԱՅ</a>
                    <a href="{{ route('lang.switch', 'en') }}" class="lang-switch-link {{ app()->getLocale() === 'en' ? 'active' : '' }}" title="English">🇬🇧 ENG</a>
                    <a href="{{ route('lang.switch', 'ru') }}" class="lang-switch-link {{ app()->getLocale() === 'ru' ? 'active' : '' }}" title="Русский">🇷🇺 РУС</a>
                </div>
                <span style="font-size: 0.8rem; font-weight: 600; color: #64748b;">
                    <i class="fa-solid fa-user-shield text-amber-500" style="color: #f59e0b;"></i> {{ __('2FA Security') }}
                </span>
            </div>
        </div>

        <div class="auth-card">
            <div class="card-header">
                <div class="card-icon-box">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h1 class="card-title">{{ __('Two-Factor Login') }}</h1>
                <p class="card-subtitle">
                    {{ __('Verify your identity to log in to your account') }}
                </p>
            </div>

            @if(session('status'))
                <div class="alert-success">
                    <i class="fa-solid fa-circle-check" style="font-size: 1.1rem;"></i>
                    <div>{{ session('status') }}</div>
                </div>
            @endif

            @if($errors->any())
                <div class="alert-error">
                    <i class="fa-solid fa-circle-exclamation" style="font-size: 1.1rem;"></i>
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            @php $isAuthApp = ($user->two_factor_type === 'authenticator' && !empty($user->two_factor_secret)); @endphp
            <!-- 2FA Selector Tabs -->
            <div class="auth-tabs">
                <button type="button" class="auth-tab-btn {{ ! $isAuthApp ? 'active' : '' }}" id="tabEmailBtn" onclick="switchTab('email')">
                    <i class="fa-solid fa-envelope"></i> Email Կոդ
                </button>
                <button type="button" class="auth-tab-btn {{ $isAuthApp ? 'active' : '' }}" id="tabAuthBtn" onclick="switchTab('authenticator')">
                    <i class="fa-solid fa-mobile-screen-button"></i> Google Authenticator
                </button>
            </div>

            <form action="{{ route('2fa.verify') }}" method="POST" id="twoFactorForm">
                @csrf
                <input type="hidden" name="auth_type" id="authTypeInput" value="{{ $isAuthApp ? 'authenticator' : 'email' }}">

                <!-- Mode 1: Email 2FA Info -->
                <div id="emailInfoBox" class="info-box" style="{{ $isAuthApp ? 'display: none;' : '' }}">
                    <i class="fa-solid fa-paper-plane" style="color: #d97706; margin-right: 0.35rem;"></i>
                    6-նիշ անվտանգության կոդն ուղարկվել է <strong>{{ $user->maskedEmail() }}</strong> հասցեին։
                </div>

                <!-- Mode 2: Google Authenticator Info -->
                <div id="authInfoBox" class="info-box" style="{{ $isAuthApp ? '' : 'display: none;' }}">
                    <i class="fa-solid fa-key" style="color: #d97706; margin-right: 0.35rem;"></i>
                    Բացեք <strong>Google Authenticator</strong> (կամ Authy) հավելվածը և մուտքագրեք ընթացիկ 6-նիշ կոդը։
                </div>

                <div class="code-input-wrapper">
                    <input 
                        type="text" 
                        name="code" 
                        id="codeInput"
                        class="code-input" 
                        maxlength="6" 
                        required 
                        autofocus 
                        placeholder="••••••" 
                        autocomplete="one-time-code"
                        inputmode="numeric"
                    >
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-lock-open"></i>
                    <span>Հաստատել և Մուտք Գործել</span>
                </button>
            </form>

            <!-- Resend email code block -->
            <div class="resend-box" id="resendCodeBox" style="{{ $isAuthApp ? 'display: none;' : '' }}">
                Չե՞ք ստացել կոդը։
                <form action="{{ route('2fa.resend') }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn-resend">Ուղարկել կրկին</button>
                </form>
            </div>
        </div>

        <div class="footer-note">
            &copy; {{ date('Y') }} QRMenu SaaS Platform • Անվտանգ երկփուլային պաշտպանություն
        </div>
    </div>

    <script>
        function switchTab(mode) {
            const authTypeInput = document.getElementById('authTypeInput');
            const tabEmailBtn = document.getElementById('tabEmailBtn');
            const tabAuthBtn = document.getElementById('tabAuthBtn');
            const emailInfoBox = document.getElementById('emailInfoBox');
            const authInfoBox = document.getElementById('authInfoBox');
            const resendCodeBox = document.getElementById('resendCodeBox');
            const codeInput = document.getElementById('codeInput');

            authTypeInput.value = mode;

            if (mode === 'authenticator') {
                tabAuthBtn.classList.add('active');
                tabEmailBtn.classList.remove('active');
                emailInfoBox.style.display = 'none';
                authInfoBox.style.display = 'block';
                resendCodeBox.style.display = 'none';
            } else {
                tabEmailBtn.classList.add('active');
                tabAuthBtn.classList.remove('active');
                emailInfoBox.style.display = 'block';
                authInfoBox.style.display = 'none';
                resendCodeBox.style.display = 'block';
            }

            codeInput.value = '';
            codeInput.focus();
        }
    </script>
</body>
</html>
