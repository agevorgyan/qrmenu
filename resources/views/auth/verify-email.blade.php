<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $siteName = \App\Models\SystemSetting::getSiteName();
        $siteFavicon = \App\Models\SystemSetting::getFavicon();
    @endphp
    <title>{{ __('Verify Email Address') }} — {{ $siteName }}</title>
    <link rel="icon" type="image/png" href="{{ $siteFavicon }}">
    <link rel="apple-touch-icon" href="{{ $siteFavicon }}">

    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">

    <style>
        :root {
            --bg-body: #0b1120;
            --card-bg: rgba(30, 41, 59, 0.78);
            --card-border: rgba(255, 255, 255, 0.12);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent-amber: #f59e0b;
            --accent-rose: #e11d48;
            --accent-gradient: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
            --btn-primary-gradient: linear-gradient(135deg, #e11d48 0%, #be123c 100%);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-body);
            background-image: 
                radial-gradient(circle at 15% 20%, rgba(225, 29, 72, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 85% 80%, rgba(245, 158, 11, 0.12) 0%, transparent 45%),
                radial-gradient(circle at 50% 50%, rgba(15, 23, 42, 0.8) 0%, #060911 100%);
            background-attachment: fixed;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .verify-card {
            background: var(--card-bg);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--card-border);
            border-radius: 28px;
            width: 100%;
            max-width: 520px;
            padding: 2.75rem 2.25rem;
            text-align: center;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.7), 0 0 40px -10px rgba(225, 29, 72, 0.2);
            position: relative;
            overflow: hidden;
        }

        .verify-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-gradient);
        }

        .icon-circle {
            width: 86px;
            height: 86px;
            border-radius: 50%;
            background: rgba(245, 158, 11, 0.12);
            border: 2px solid rgba(245, 158, 11, 0.4);
            color: var(--accent-amber);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 0 30px rgba(245, 158, 11, 0.25);
            animation: pulse-glow 3s infinite alternate;
        }

        @keyframes pulse-glow {
            0% { transform: scale(1); box-shadow: 0 0 20px rgba(245, 158, 11, 0.2); }
            100% { transform: scale(1.04); box-shadow: 0 0 35px rgba(245, 158, 11, 0.35); }
        }

        .brand-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 0.65rem;
            color: #ffffff;
        }

        .brand-title span {
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .desc-text {
            font-size: 0.92rem;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .email-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 999px;
            padding: 0.4rem 1rem;
            font-size: 0.88rem;
            font-weight: 600;
            color: #fde68a;
            margin-bottom: 1.75rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #6ee7b7;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            font-size: 0.86rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            animation: fadeIn 0.3s ease;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            font-size: 0.86rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* 6-Digit Code Section */
        .code-box {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 18px;
            padding: 1.5rem 1.25rem;
            margin-bottom: 1.5rem;
        }

        .code-title {
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-secondary);
            margin-bottom: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }

        .code-input {
            width: 100%;
            max-width: 280px;
            height: 58px;
            background: rgba(255, 255, 255, 0.06);
            border: 2px solid rgba(255, 255, 255, 0.15);
            border-radius: 14px;
            color: #ffffff;
            font-family: 'Outfit', monospace;
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: 10px;
            text-align: center;
            padding-left: 10px;
            margin: 0 auto 1.15rem;
            display: block;
            transition: all 0.2s ease;
            outline: none;
        }

        .code-input:focus {
            border-color: var(--accent-amber);
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.2);
        }

        .btn-submit {
            width: 100%;
            padding: 0.9rem 1.5rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            background: var(--btn-primary-gradient);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(225, 29, 72, 0.35);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(225, 29, 72, 0.45);
        }

        .divider {
            position: relative;
            text-align: center;
            margin: 1.5rem 0;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }

        .divider span {
            position: relative;
            background: rgba(30, 41, 59, 0.95);
            padding: 0 1rem;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .resend-box {
            font-size: 0.88rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .btn-link {
            background: none;
            border: none;
            color: #f59e0b;
            font-weight: 700;
            cursor: pointer;
            text-decoration: underline;
            padding: 0;
            margin-left: 0.3rem;
            font-size: 0.88rem;
            transition: color 0.2s;
        }

        .btn-link:hover {
            color: #fbbf24;
        }

        .btn-logout {
            width: 100%;
            padding: 0.75rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
        }

        .btn-logout:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="verify-card">
        <div class="icon-circle">
            <i class="fa-solid fa-envelope-circle-check"></i>
        </div>

        <h1 class="brand-title">Հաստատեք <span>Էլ․ Փոստը</span></h1>
        
        <p class="desc-text">
            Հարգելի <strong>{{ Auth::user()?->name }}</strong>, շնորհակալություն գրանցվելու համար։ Գրանցումն ավարտելու և համակարգ մուտք գործելու համար անհրաժեշտ է հաստատել էլ․ փոստի հասցեն։
        </p>

        <div class="email-badge">
            <i class="fa-solid fa-envelope"></i>
            {{ Auth::user()?->email }}
        </div>

        @if(session('status') === 'verification-link-sent')
            <div class="alert-success">
                <i class="fa-solid fa-circle-check"></i>
                Հաստատման նոր նամակն ուղարկվել է Ձեր էլ․ փոստին։
            </div>
        @endif

        @if($errors->has('code'))
            <div class="alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                {{ $errors->first('code') }}
            </div>
        @endif

        <!-- 6-Digit Code Form -->
        <div class="code-box">
            <div class="code-title">
                <i class="fa-solid fa-key"></i> Մուտքագրեք 6-նիշ ստուգիչ կոդը
            </div>

            <form action="{{ route('verification.code') }}" method="POST">
                @csrf
                <input 
                    type="text" 
                    name="code" 
                    id="code" 
                    class="code-input" 
                    placeholder="••••••" 
                    maxlength="6" 
                    pattern="[0-9]{6}" 
                    inputmode="numeric" 
                    autocomplete="one-time-code" 
                    required 
                    autofocus
                >
                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-check"></i> Հաստատել Էլ․ Փոստը
                </button>
            </form>
        </div>

        <div class="divider">
            <span>կամ</span>
        </div>

        <!-- Resend Email Form -->
        <div class="resend-box">
            Չե՞ք ստացել նամակը կամ կոդը։
            <form action="{{ route('verification.send') }}" method="POST" style="display: inline;">
                @csrf
                <button type="submit" class="btn-link">Ուղարկել նորից</button>
            </form>
        </div>

        <!-- Logout Form -->
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="btn-logout">
                <i class="fa-solid fa-right-from-bracket"></i> Դուրս գալ
            </button>
        </form>
    </div>
</body>
</html>
