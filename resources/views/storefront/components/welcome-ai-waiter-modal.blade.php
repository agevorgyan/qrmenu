@if($vendor->ai_waiter_enabled)
<!-- Context-Aware AI Waiter Welcome Modal (Non-Blocking, Time-Aware) -->
<div x-show="showWelcomeModal" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="ai-welcome-modal-backdrop"
     style="position: fixed; inset: 0; z-index: 99990; display: flex; align-items: center; justify-content: center; padding: 1.25rem; background: rgba(15, 23, 42, 0.72); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); overflow-y: auto; -webkit-overflow-scrolling: touch;"
     x-cloak>

    <div @click.away="skipToMenu()" 
         x-show="showWelcomeModal"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-6 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-6 scale-95"
         class="ai-welcome-card"
         style="width: 100%; max-width: 440px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 28px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4); overflow: hidden; position: relative; text-align: center; margin: auto;">

        <!-- Top Accent Glow -->
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 110px; background: radial-gradient(circle at 50% 0%, color-mix(in srgb, var(--primary) 22%, transparent), transparent 70%); pointer-events: none;"></div>

        <!-- Close Button -->
        <button type="button" 
                @click="skipToMenu()" 
                class="ai-header-btn"
                style="position: absolute; top: 1rem; right: 1rem; z-index: 10;"
                title="{{ __('menu.close') ?: 'Փակել' }}">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div style="padding: 2.25rem 1.75rem 2rem 1.75rem; position: relative; z-index: 5;">
            <!-- AI Avatar -->
            <div style="position: relative; width: 76px; height: 76px; margin: 0 auto 1.25rem auto;">
                <div class="ai-avatar-pulse" style="position: absolute; inset: -6px; border-radius: 50%; background: var(--primary); opacity: 0.15; filter: blur(6px); animation: pulseAvatar 2.2s infinite;"></div>
                <div style="position: relative; width: 76px; height: 76px; border-radius: 50%; background: var(--primary); padding: 2.5px; box-shadow: 0 8px 24px color-mix(in srgb, var(--primary) 25%, transparent);">
                    <div style="width: 100%; height: 100%; border-radius: 50%; background: var(--bg-card); display: flex; align-items: center; justify-content: center; font-size: 2rem; color: var(--primary);">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                    </div>
                </div>
                <span style="position: absolute; bottom: -2px; right: -2px; width: 24px; height: 24px; border-radius: 50%; background: #10b981; color: #fff; border: 2px solid var(--bg-card); display: flex; align-items: center; justify-content: center; font-size: 0.68rem;">
                    <i class="fa-solid fa-check"></i>
                </span>
            </div>

            <!-- Header Badge -->
            <div style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.25rem 0.75rem; border-radius: 9999px; background: color-mix(in srgb, var(--primary) 10%, transparent); border: 1px solid color-mix(in srgb, var(--primary) 25%, transparent); color: var(--primary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-sparkles"></i> {{ $vendor->getAiWaiterName() }}
            </div>

            <!-- Title -->
            <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.45rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.5rem 0; line-height: 1.25;">
                {{ $vendor->name }}
            </h2>

            <!-- Table Context Indicator if seated at a table -->
            @if(!empty($table))
                <div style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.25rem 0.85rem; border-radius: 9999px; background: color-mix(in srgb, var(--primary) 12%, transparent); color: var(--primary); font-weight: 800; font-size: 0.85rem; margin-bottom: 1rem;">
                    <i class="fa-solid fa-chair"></i>
                    <span>{{ __('Table') }} #{{ $table }}</span>
                </div>
            @endif

            <!-- Time-Aware Localized Greeting -->
            <p style="font-size: 0.95rem; line-height: 1.55; color: var(--text-main); margin: 0 0 1.5rem 0; padding: 0 0.5rem; font-weight: 500;">
                <span x-text="aiGreetingText || '{{ addslashes($greeting) }}'">{{ $greeting }}</span>
            </p>

            @if(!empty($table))
                <!-- A. TABLE QR ENTRY ACTIONS: Prioritize browsing menu & direct ordering, no reservations -->
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <button type="button" 
                            @click="skipToMenu()" 
                            class="ai-primary-btn"
                            style="width: 100%; justify-content: center; font-size: 0.95rem; padding: 0.85rem;">
                        <i class="fa-solid fa-utensils"></i>
                        <span x-show="selectedLang === 'hy'">Տեսնել Մենյուն և Պատվիրել</span>
                        <span x-show="selectedLang === 'ru'">Смотреть меню и заказать</span>
                        <span x-show="selectedLang !== 'hy' && selectedLang !== 'ru'">Browse Menu & Order</span>
                    </button>

                    <button type="button" 
                            @click="openAiChatWithIntent('menu_recommendation')"
                            class="ai-secondary-btn"
                            style="width: 100%; justify-content: center; font-size: 0.92rem; padding: 0.75rem;">
                        <i class="fa-solid fa-wand-magic-sparkles" style="color: var(--primary);"></i>
                        <span x-show="selectedLang === 'hy'">Խորհրդակցել AI Մատուցողի հետ</span>
                        <span x-show="selectedLang === 'ru'">Спросить AI-официанта</span>
                        <span x-show="selectedLang !== 'hy' && selectedLang !== 'ru'">Ask AI Waiter for Advice</span>
                    </button>
                </div>
            @else
                <!-- B. GENERAL MENU ENTRY ACTIONS: Discover menu and enabled services -->
                <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                    <button type="button" 
                            @click="skipToMenu()" 
                            class="ai-primary-btn"
                            style="width: 100%; justify-content: center; font-size: 0.95rem; padding: 0.85rem;">
                        <i class="fa-solid fa-book-open"></i>
                        <span x-show="selectedLang === 'hy'">Բացել Ռեստորանի Մենյուն</span>
                        <span x-show="selectedLang === 'ru'">Открыть меню ресторана</span>
                        <span x-show="selectedLang !== 'hy' && selectedLang !== 'ru'">Open Digital Menu</span>
                    </button>

                    <!-- Service Quick Discovery Chips -->
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: center; margin-top: 0.5rem;">
                        @if($vendor->isReservationFeatureEnabled())
                            <a href="{{ route('client.reserve', ['vendor_slug' => $vendor->slug, 'location_slug' => $location?->slug, 'lang' => $lang]) }}"
                               class="ai-service-chip"
                               style="text-decoration: none;">
                                <i class="fa-solid fa-calendar-check" style="color: #ec4899;"></i>
                                <span x-show="selectedLang === 'hy'">Ամրագրել սեղան</span>
                                <span x-show="selectedLang === 'ru'">Забронировать стол</span>
                                <span x-show="selectedLang !== 'hy' && selectedLang !== 'ru'">Reserve a Table</span>
                            </a>
                        @endif

                        @if($vendor->delivery_enabled)
                            <button type="button"
                                    @click="switchModeAndOpen('delivery')"
                                    class="ai-service-chip">
                                <i class="fa-solid fa-motorcycle" style="color: #06b6d4;"></i>
                                <span x-show="selectedLang === 'hy'">Առաքում</span>
                                <span x-show="selectedLang === 'ru'">Доставка</span>
                                <span x-show="selectedLang !== 'hy' && selectedLang !== 'ru'">Delivery</span>
                            </button>
                        @endif

                        @if($vendor->takeaway_enabled)
                            <button type="button"
                                    @click="switchModeAndOpen('takeaway')"
                                    class="ai-service-chip">
                                <i class="fa-solid fa-bag-shopping" style="color: #f59e0b;"></i>
                                <span x-show="selectedLang === 'hy'">Ինքս կվերցնեմ</span>
                                <span x-show="selectedLang === 'ru'">Самовывоз</span>
                                <span x-show="selectedLang !== 'hy' && selectedLang !== 'ru'">Takeaway</span>
                            </button>
                        @endif

                        @if($vendor->isEventInquiryFeatureEnabled())
                            <a href="{{ route('client.event_inquiry', ['vendor_slug' => $vendor->slug, 'location_slug' => $location?->slug, 'lang' => $lang]) }}"
                               class="ai-service-chip"
                               style="text-decoration: none;">
                                <i class="fa-solid fa-champagne-glasses" style="color: #8b5cf6;"></i>
                                <span x-show="selectedLang === 'hy'">Միջոցառման հարցում</span>
                                <span x-show="selectedLang === 'ru'">Банкеты и события</span>
                                <span x-show="selectedLang !== 'hy' && selectedLang !== 'ru'">Event Inquiry</span>
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<style>
.ai-service-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 0.85rem;
    border-radius: 12px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-main);
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}
.ai-service-chip:hover {
    border-color: var(--primary);
    background: color-mix(in srgb, var(--primary) 8%, var(--bg-card));
    transform: translateY(-1px);
}
@keyframes pulseAvatar {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.12); opacity: 0.15; }
}
</style>
@endif
