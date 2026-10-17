@php
    $currentLocale = app()->getLocale();
    $locales = [
        'hy' => ['flag' => '🇦🇲', 'code' => 'ՀԱՅ', 'name' => 'Հայերեն'],
        'en' => ['flag' => '🇬🇧', 'code' => 'ENG', 'name' => 'English'],
        'ru' => ['flag' => '🇷🇺', 'code' => 'РУС', 'name' => 'Русский'],
    ];
    $activeLang = $locales[$currentLocale] ?? $locales['hy'];
@endphp

<div class="auth-lang-wrapper">
    <!-- Desktop Segmented Switcher (> 640px) -->
    <div class="auth-lang-segmented" role="navigation" aria-label="{{ __('Language') }}">
        @foreach($locales as $code => $lang)
            <a href="{{ route('lang.switch', $code) }}" 
               class="lang-switch-link {{ $currentLocale === $code ? 'active' : '' }}" 
               title="{{ $lang['name'] }}">
                {{ $lang['flag'] }} {{ $lang['code'] }}
            </a>
        @endforeach
    </div>

    <!-- Mobile Compact Dropdown (<= 640px) -->
    <div class="auth-lang-dropdown">
        <button type="button" 
                class="auth-lang-trigger" 
                onclick="toggleAuthLang(this, event)" 
                aria-haspopup="true" 
                aria-expanded="false" 
                title="{{ __('Select Language') }}">
            <span class="auth-lang-trigger-flag">{{ $activeLang['flag'] }}</span>
            <span class="auth-lang-trigger-code">{{ $activeLang['code'] }}</span>
            <i class="fa-solid fa-chevron-down auth-lang-chevron"></i>
        </button>

        <div class="auth-lang-menu" role="menu">
            @foreach($locales as $code => $lang)
                <a href="{{ route('lang.switch', $code) }}" 
                   class="auth-lang-item {{ $currentLocale === $code ? 'active' : '' }}" 
                   role="menuitem">
                    <span class="auth-lang-item-content">
                        <span class="auth-lang-item-flag">{{ $lang['flag'] }}</span>
                        <span class="auth-lang-item-name">{{ $lang['name'] }}</span>
                    </span>
                    @if($currentLocale === $code)
                        <i class="fa-solid fa-check auth-lang-check"></i>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</div>

@once
<style id="auth-lang-styles">
    /* Wrapper */
    .auth-lang-wrapper {
        position: relative;
        display: inline-flex;
        align-items: center;
        vertical-align: middle;
    }

    /* Desktop Segmented */
    .auth-lang-segmented {
        display: inline-flex;
        align-items: center;
        background: #f1f5f9;
        border: 1px solid var(--border-card, #e2e8f0);
        border-radius: 999px;
        padding: 2px;
        gap: 2px;
    }

    .auth-lang-segmented .lang-switch-link {
        padding: 3px 8px;
        border-radius: 999px;
        font-size: 0.74rem;
        font-weight: 700;
        color: var(--text-secondary, #64748b);
        text-decoration: none;
        transition: all 0.2s ease;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        line-height: 1.3;
    }

    .auth-lang-segmented .lang-switch-link:hover {
        color: var(--text-primary, #0f172a);
    }

    .auth-lang-segmented .lang-switch-link.active {
        background: #ffffff;
        color: #b45309;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    /* Mobile Dropdown */
    .auth-lang-dropdown {
        display: none;
        position: relative;
    }

    .auth-lang-trigger {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.65rem;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        color: #334155;
        font-size: 0.78rem;
        font-weight: 700;
        cursor: pointer;
        line-height: 1.2;
        transition: all 0.2s ease;
        white-space: nowrap;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        -webkit-tap-highlight-color: transparent;
        font-family: inherit;
    }

    .auth-lang-trigger:hover,
    .auth-lang-trigger:focus,
    .auth-lang-dropdown.open .auth-lang-trigger {
        background: #ffffff;
        border-color: #f59e0b;
        color: #b45309;
        box-shadow: 0 2px 8px rgba(245, 158, 11, 0.18);
    }

    .auth-lang-chevron {
        font-size: 0.6rem;
        color: #94a3b8;
        transition: transform 0.2s ease;
    }

    .auth-lang-dropdown.open .auth-lang-chevron {
        transform: rotate(180deg);
        color: #b45309;
    }

    .auth-lang-menu {
        position: absolute;
        top: calc(100% + 6px);
        right: 0;
        min-width: 145px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 5px;
        box-shadow: 0 12px 28px -4px rgba(0, 0, 0, 0.16), 0 6px 12px -3px rgba(0, 0, 0, 0.08);
        display: none;
        flex-direction: column;
        gap: 3px;
        z-index: 1050;
        animation: authLangFadeIn 0.15s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes authLangFadeIn {
        from {
            opacity: 0;
            transform: translateY(-6px) scale(0.98);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .auth-lang-dropdown.open .auth-lang-menu {
        display: flex;
    }

    .auth-lang-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.52rem 0.72rem;
        border-radius: 9px;
        text-decoration: none;
        font-size: 0.84rem;
        font-weight: 600;
        color: #334155;
        transition: all 0.15s ease;
        white-space: nowrap;
        min-height: 36px;
        box-sizing: border-box;
    }

    .auth-lang-item-content {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .auth-lang-item:hover,
    .auth-lang-item:active {
        background: #f8fafc;
        color: #0f172a;
    }

    .auth-lang-item.active {
        background: #fffbeb;
        color: #b45309;
        font-weight: 700;
    }

    .auth-lang-check {
        font-size: 0.72rem;
        color: #f59e0b;
    }

    /* Responsive Display Toggle */
    @media (max-width: 640px) {
        .auth-lang-segmented {
            display: none !important;
        }
        .auth-lang-dropdown {
            display: inline-block !important;
        }
    }
</style>

<script id="auth-lang-script">
    function toggleAuthLang(btn, e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        const dropdown = btn.closest('.auth-lang-dropdown');
        if (!dropdown) return;
        const isOpen = dropdown.classList.contains('open');
        document.querySelectorAll('.auth-lang-dropdown.open').forEach(function(el) {
            el.classList.remove('open');
            const b = el.querySelector('.auth-lang-trigger');
            if (b) b.setAttribute('aria-expanded', 'false');
        });
        if (!isOpen) {
            dropdown.classList.add('open');
            btn.setAttribute('aria-expanded', 'true');
        }
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.auth-lang-dropdown')) {
            document.querySelectorAll('.auth-lang-dropdown.open').forEach(function(el) {
                el.classList.remove('open');
                const b = el.querySelector('.auth-lang-trigger');
                if (b) b.setAttribute('aria-expanded', 'false');
            });
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.auth-lang-dropdown.open').forEach(function(el) {
                el.classList.remove('open');
                const b = el.querySelector('.auth-lang-trigger');
                if (b) b.setAttribute('aria-expanded', 'false');
            });
        }
    });
</script>
@endonce
