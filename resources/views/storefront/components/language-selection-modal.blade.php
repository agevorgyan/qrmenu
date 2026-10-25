<!-- Mandatory Language Selection Modal & In-Menu Language Switcher -->
<div x-show="showLanguageModal"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="language-selection-modal-backdrop"
     style="position: fixed; inset: 0; z-index: 99998; display: flex; align-items: center; justify-content: center; padding: 1.25rem; background: rgba(15, 23, 42, 0.82); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); overflow-y: auto; -webkit-overflow-scrolling: touch;"
     x-cloak>

    <div @click.away="if (!needsLanguageSelection) showLanguageModal = false"
         x-show="showLanguageModal"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-6 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-6 scale-95"
         class="language-selection-card"
         style="width: 100%; max-width: 440px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 28px; box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.5); overflow: hidden; position: relative; text-align: center; margin: auto;">

        <!-- Top Accent Glow -->
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 110px; background: radial-gradient(circle at 50% 0%, color-mix(in srgb, var(--primary) 22%, transparent), transparent 70%); pointer-events: none;"></div>

        <!-- Dismiss button only visible when changing language from menu (not on mandatory initial entry) -->
        <button type="button"
                x-show="!needsLanguageSelection"
                @click="showLanguageModal = false"
                class="ai-header-btn"
                style="position: absolute; top: 1rem; right: 1rem; z-index: 10;"
                title="{{ __('menu.close') ?: 'Փակել' }}">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div style="padding: 2.25rem 1.75rem 2rem 1.75rem; position: relative; z-index: 5;">
            <!-- Restaurant Logo / Icon -->
            <div style="position: relative; width: 76px; height: 76px; margin: 0 auto 1.25rem auto;">
                @if(!empty($vendor->logo))
                    <img src="{{ $vendor->logo }}" alt="{{ $vendor->name }}" style="width: 76px; height: 76px; border-radius: 50%; object-fit: cover; border: 2.5px solid var(--primary); box-shadow: 0 6px 20px color-mix(in srgb, var(--primary) 25%, transparent);">
                @else
                    <div style="width: 76px; height: 76px; border-radius: 50%; background: color-mix(in srgb, var(--primary) 12%, var(--bg-card)); border: 2.5px solid var(--primary); display: flex; align-items: center; justify-content: center; font-size: 2rem; color: var(--primary);">
                        <i class="fa-solid fa-utensils"></i>
                    </div>
                @endif
                <span style="position: absolute; bottom: -2px; right: -2px; width: 24px; height: 24px; border-radius: 50%; background: #10b981; color: #fff; border: 2px solid var(--bg-card); display: flex; align-items: center; justify-content: center; font-size: 0.68rem;">
                    <i class="fa-solid fa-globe"></i>
                </span>
            </div>

            <!-- Restaurant Title -->
            <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.45rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.35rem 0; line-height: 1.25;">
                {{ $vendor->name }}
            </h2>

            <!-- Context Badge (Table QR or General Menu) -->
            @if(!empty($table))
                <div style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.25rem 0.75rem; border-radius: 9999px; background: color-mix(in srgb, var(--primary) 12%, transparent); border: 1px solid color-mix(in srgb, var(--primary) 25%, transparent); color: var(--primary); font-size: 0.78rem; font-weight: 800; margin-bottom: 1.25rem;">
                    <i class="fa-solid fa-chair"></i>
                    <span>{{ __('Table') }} #{{ $table }}</span>
                </div>
            @else
                <div style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.25rem 0.75rem; border-radius: 9999px; background: rgba(148, 163, 184, 0.12); color: var(--text-muted); font-size: 0.75rem; font-weight: 700; margin-bottom: 1.25rem;">
                    <i class="fa-solid fa-book-open"></i>
                    <span>{{ __('Digital Menu') }}</span>
                </div>
            @endif

            <!-- Language Header Prompt -->
            <p style="font-size: 0.88rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 1rem 0;">
                <span x-show="selectedLang === 'hy'">Ընտրեք Ձեր լեզուն</span>
                <span x-show="selectedLang === 'ru'">Выберите ваш язык</span>
                <span x-show="selectedLang !== 'hy' && selectedLang !== 'ru'">Select Your Language</span>
            </p>

            <!-- Language Cards Grid (Strictly only enabled vendor languages) -->
            <div style="display: flex; flex-direction: column; gap: 0.65rem; margin-bottom: 1.25rem;">
                @foreach($supportedLanguages as $slang)
                    @php
                        $code = strtolower($slang['code'] ?? 'en');
                        $nativeName = $slang['native_name'] ?? ($slang['name'] ?? strtoupper($code));
                        $englishName = $slang['name'] ?? strtoupper($code);
                        $flag = $slang['flag'] ?? '🌐';
                    @endphp
                    <button type="button"
                            @click="selectStorefrontLanguage('{{ $code }}')"
                            :class="{ 'active-lang-choice': selectedLang === '{{ $code }}' }"
                            class="lang-choice-card"
                            style="display: flex; align-items: center; justify-content: space-between; padding: 0.85rem 1.15rem; border-radius: 16px; border: 1.5px solid var(--border-color); background: var(--bg-card); cursor: pointer; transition: all 0.2s ease; text-align: left; width: 100%;">
                        <div style="display: flex; align-items: center; gap: 0.85rem;">
                            <span style="font-size: 1.5rem; line-height: 1; flex-shrink: 0;">{{ $flag }}</span>
                            <div>
                                <div style="font-weight: 800; font-size: 1.02rem; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                    {{ $nativeName }}
                                </div>
                                @if($nativeName !== $englishName)
                                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">
                                        {{ $englishName }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div style="width: 24px; height: 24px; border-radius: 50%; border: 2px solid var(--border-color); display: flex; align-items: center; justify-content: center; transition: all 0.2s;"
                             :style="selectedLang === '{{ $code }}' ? 'border-color: var(--primary); background: var(--primary); color: #fff;' : 'color: transparent;'">
                            <i class="fa-solid fa-check" style="font-size: 0.72rem;"></i>
                        </div>
                    </button>
                @endforeach
            </div>

            <div style="font-size: 0.75rem; color: var(--text-muted); opacity: 0.8;">
                <span x-show="selectedLang === 'hy'">Դուք կարող եք փոխել լեզուն ցանկացած պահի</span>
                <span x-show="selectedLang === 'ru'">Вы можете сменить язык в любой момент</span>
                <span x-show="selectedLang !== 'hy' && selectedLang !== 'ru'">You can change language anytime from the menu</span>
            </div>
        </div>
    </div>
</div>

<style>
.lang-choice-card:hover {
    border-color: var(--primary) !important;
    background: color-mix(in srgb, var(--primary) 4%, var(--bg-card)) !important;
    transform: translateY(-1px);
}
.lang-choice-card.active-lang-choice {
    border-color: var(--primary) !important;
    background: color-mix(in srgb, var(--primary) 8%, var(--bg-card)) !important;
    box-shadow: 0 4px 16px color-mix(in srgb, var(--primary) 18%, transparent);
}
</style>
