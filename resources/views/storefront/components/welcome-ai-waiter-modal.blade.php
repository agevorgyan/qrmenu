@if($vendor->ai_waiter_enabled)
<!-- AI Waiter Welcome & Language Selection Modal -->
<div x-show="showWelcomeModal" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="ai-welcome-modal-backdrop"
     style="position: fixed; inset: 0; z-index: 99990; display: flex; align-items: center; justify-content: center; padding: 1.25rem; background: rgba(15, 23, 42, 0.78); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); overflow-y: auto; -webkit-overflow-scrolling: touch;">

    <div @click.away="skipToMenu()" 
         x-show="showWelcomeModal"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-6 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-6 scale-95"
         class="ai-welcome-card"
         style="width: 100%; max-width: 440px; max-height: calc(100vh - 2.5rem); max-height: calc(100dvh - 2.5rem); background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 28px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4); overflow-y: auto; -webkit-overflow-scrolling: touch; position: relative; text-align: center; margin: auto;">

        <!-- Top Ambient Glow -->
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 120px; background: radial-gradient(circle at 50% 0%, color-mix(in srgb, var(--primary) 20%, transparent), transparent 70%); pointer-events: none;"></div>

        <!-- Close Button -->
        <button type="button" 
                @click="skipToMenu()" 
                class="ai-header-btn"
                style="position: absolute; top: 1rem; right: 1rem; z-index: 10;">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div style="padding: 2.25rem 1.75rem 2rem 1.75rem; position: relative; z-index: 5;">
            <!-- AI Avatar with Glowing Pulsing Rings -->
            <div style="position: relative; width: 88px; height: 88px; margin: 0 auto 1.5rem auto;">
                <div class="ai-avatar-pulse" style="position: absolute; inset: -6px; border-radius: 50%; background: var(--primary); opacity: 0.15; filter: blur(6px); animation: pulseAvatar 2.2s infinite;"></div>
                <div style="position: relative; width: 88px; height: 88px; border-radius: 50%; background: var(--primary); padding: 3px; box-shadow: 0 8px 24px color-mix(in srgb, var(--primary) 25%, transparent);">
                    <div style="width: 100%; height: 100%; border-radius: 50%; background: var(--bg-card); display: flex; align-items: center; justify-content: center; font-size: 2.3rem; color: var(--primary);">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                    </div>
                </div>
                <!-- Mini sommelier badge -->
                <span style="position: absolute; bottom: -2px; right: -2px; width: 26px; height: 26px; border-radius: 50%; background: #10b981; color: #fff; border: 2.5px solid var(--bg-card); display: flex; align-items: center; justify-content: center; font-size: 0.72rem; box-shadow: 0 2px 6px rgba(0,0,0,0.15);">
                    <i class="fa-solid fa-check"></i>
                </span>
            </div>

            <!-- Header Titles -->
            <div style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.25rem 0.75rem; border-radius: 9999px; background: color-mix(in srgb, var(--primary) 10%, transparent); border: 1px solid color-mix(in srgb, var(--primary) 25%, transparent); color: var(--primary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-sparkles"></i> AI SOMMELIER & ADVISOR
            </div>

            <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.55rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.4rem 0; line-height: 1.2;">
                {{ $vendor->name }}
            </h2>

            <!-- Language Selection Chips -->
            <div style="margin: 1.25rem 0 1.5rem 0;">
                <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.65rem; text-transform: uppercase; letter-spacing: 0.04em;">
                    <span x-show="selectedLang === 'hy'">Ընտրեք Ձեր լեզուն / Select Language</span>
                    <span x-show="selectedLang === 'en'">Choose your language</span>
                    <span x-show="selectedLang === 'ru'">Выберите ваш язык</span>
                </div>
                <div style="display: flex; justify-content: center; gap: 0.5rem;">
                    <button type="button" 
                            @click="selectLanguage('hy')"
                            :class="{ 'active-lang': selectedLang === 'hy' }"
                            class="lang-pill-btn">
                        <span style="font-size: 1.15rem;">🇦🇲</span>
                        <span>Հայերեն</span>
                    </button>
                    <button type="button" 
                            @click="selectLanguage('en')"
                            :class="{ 'active-lang': selectedLang === 'en' }"
                            class="lang-pill-btn">
                        <span style="font-size: 1.15rem;">🇬🇧</span>
                        <span>English</span>
                    </button>
                    <button type="button" 
                            @click="selectLanguage('ru')"
                            :class="{ 'active-lang': selectedLang === 'ru' }"
                            class="lang-pill-btn">
                        <span style="font-size: 1.15rem;">🇷🇺</span>
                        <span>Русский</span>
                    </button>
                </div>
            </div>

            <!-- Welcome Greeting Description -->
            <p style="font-size: 0.92rem; line-height: 1.55; color: var(--text-muted); margin: 0 0 1.75rem 0; padding: 0 0.5rem;">
                <span x-show="selectedLang === 'hy'">
                    @if(!empty($vendor->ai_waiter_welcome_text))
                        {{ $vendor->ai_waiter_welcome_text }}
                    @else
                        Ողջույն, ես <strong>{{ $vendor->getAiWaiterName() }}</strong>-ն եմ՝ Ձեր անձնական խոհարարական խորհրդատուն: Կօգնե՞մ ընտրել Ձեր տրամադրությանը համապատասխան ուտեստներ և համահունչ ըմպելիքներ:
                    @endif
                </span>
                <span x-show="selectedLang === 'en'">
                    Hello, I am <strong>{{ $vendor->getAiWaiterName() }}</strong>, your personal dining advisor. May I guide you to exquisite culinary choices and sommelier-matched pairings?
                </span>
                <span x-show="selectedLang === 'ru'">
                    Здравствуйте! Я <strong>{{ $vendor->getAiWaiterName() }}</strong>, ваш персональный гастрономический консультант. Позвольте помочь вам выбрать идеальные блюда и напитки!
                </span>
            </p>

            <!-- Action Buttons -->
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <button type="button" 
                        @click="startAiWaiter()"
                        class="ai-primary-btn"
                        style="width: 100%; justify-content: center;">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    <span x-show="selectedLang === 'hy'">Միանալ AI Մատուցողին</span>
                    <span x-show="selectedLang === 'en'">Connect with AI Waiter</span>
                    <span x-show="selectedLang === 'ru'">Консультация с AI-официантом</span>
                </button>

                <button type="button" 
                        @click="skipToMenu()" 
                        class="ai-secondary-btn"
                        style="width: 100%; justify-content: center;">
                    <span x-show="selectedLang === 'hy'">Անցնել Մենյուին</span>
                    <span x-show="selectedLang === 'en'">Browse Menu Directly</span>
                    <span x-show="selectedLang === 'ru'">Перейти в меню</span>
                    <i class="fa-solid fa-arrow-right" style="font-size: 0.75rem;"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.lang-pill-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 0.85rem;
    border-radius: 12px;
    border: 1px solid var(--border-color);
    background: var(--bg-main);
    color: var(--text-muted);
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}
.lang-pill-btn:hover {
    border-color: var(--primary);
    color: var(--text-main);
}
.lang-pill-btn.active-lang {
    background: color-mix(in srgb, var(--primary) 12%, transparent);
    border-color: var(--primary);
    color: var(--primary);
    font-weight: 700;
    box-shadow: 0 0 0 1px var(--primary);
}
@keyframes pulseAvatar {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.12); opacity: 0.15; }
}
</style>
@endif
