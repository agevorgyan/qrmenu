@if($vendor->ai_waiter_enabled)
<!-- Fullscreen AI Waiter Experiential Modal -->
<div x-show="showAiWaiter" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fullscreen-ai-waiter-backdrop"
     style="position: fixed; inset: 0; width: 100%; height: 100%; height: 100vh; height: 100dvh; z-index: 99995; background: var(--bg-main); display: flex; flex-direction: column; overflow: hidden;"
     x-cloak>

    <!-- Top Navigation Bar -->
    <header style="background: var(--bg-card); border-bottom: 1px solid var(--border-color); padding: 0.75rem 1.25rem; z-index: 30; flex-shrink: 0; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04); width: 100%;">
        <div style="max-width: 680px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <!-- AI Waiter Avatar with clean theme ring -->
                <div class="ai-avatar-badge-wrap" style="position: relative; width: 42px; height: 42px; border-radius: 50%; background: color-mix(in srgb, var(--primary) 15%, transparent); border: 2px solid var(--primary); padding: 1px; flex-shrink: 0;">
                    <div style="width: 100%; height: 100%; border-radius: 50%; background: var(--bg-card); display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 1.15rem;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                    </div>
                    <span style="position: absolute; bottom: 0; right: 0; width: 11px; height: 11px; border-radius: 50%; background: #10b981; border: 2px solid var(--bg-card);" title="Online"></span>
                </div>

                <div>
                    <div style="display: flex; align-items: center; gap: 0.4rem;">
                        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.05rem; font-weight: 800; color: var(--text-main); margin: 0;">
                            {{ $vendor->getAiWaiterName() }}
                        </h3>
                        <span style="font-size: 0.62rem; font-weight: 800; background: color-mix(in srgb, var(--primary) 12%, transparent); color: var(--primary); padding: 0.12rem 0.45rem; border-radius: 6px; letter-spacing: 0.04em;">AI WAITER</span>
                    </div>
                    <div style="font-size: 0.72rem; color: #10b981; font-weight: 600; display: flex; align-items: center; gap: 0.35rem; margin-top: 0.1rem;">
                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span>
                        <span x-show="selectedLang === 'hy'">Առցանց մատուցող</span>
                        <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Online Waiter</span>
                        <span x-show="selectedLang === 'ru'">Онлайн-официант</span>
                    </div>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 0.45rem;">
                <!-- AI Chat Trigger -->
                <button type="button" 
                        @click="openAiChat()"
                        title="AI Chat"
                        class="ai-header-chat-btn">
                    <i class="fa-solid fa-comments"></i>
                    <span class="hidden-xs" x-show="selectedLang === 'hy'">Հարցնել</span>
                    <span class="hidden-xs" x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Chat</span>
                    <span class="hidden-xs" x-show="selectedLang === 'ru'">Чат</span>
                </button>

                <!-- Reset Button -->
                <button type="button" 
                        @click="resetAiQuiz()"
                        :title="selectedLang === 'hy' ? 'Սկսել նորից' : (selectedLang === 'ru' ? 'Начать заново' : 'Start over')"
                        :aria-label="selectedLang === 'hy' ? 'Սկսել նորից' : (selectedLang === 'ru' ? 'Начать заново' : 'Start over')"
                        class="ai-header-btn">
                    <i class="fa-solid fa-rotate-left"></i>
                </button>

                <!-- Close Button -->
                <button type="button" 
                        @click="closeAiWaiter()" 
                        title="{{ __('menu.close') }}"
                        aria-label="{{ __('menu.close') }}"
                        class="ai-header-btn">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Scrollable Body Container -->
    <div class="ai-waiter-scroll-container" 
         style="flex: 1 1 0%; min-height: 0; width: 100%; height: 100%; overflow-y: auto; overflow-x: hidden; -webkit-overflow-scrolling: touch; overscroll-behavior: contain;">
        <div style="max-width: 680px; margin: 0 auto; padding: 1.25rem 1rem 5.5rem 1rem; width: 100%; box-sizing: border-box;">

            <!-- ========================================== -->
            <!-- SCREEN 1: INTRO ANIMATION & WELCOME SCREEN -->
            <!-- ========================================== -->
            <div x-show="aiStep === 'intro'" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 style="text-align: center; padding: 2rem 0.5rem;">
                
                <!-- Animated Floating AI Avatar -->
                <div style="position: relative; width: 100px; height: 100px; margin: 0 auto 1.5rem auto;">
                    <div class="ai-intro-glow" style="position: absolute; inset: -10px; border-radius: 50%; background: color-mix(in srgb, var(--primary) 25%, transparent); filter: blur(8px); animation: pulseAvatar 2.4s infinite ease-in-out;"></div>
                    <div class="ai-intro-avatar" style="position: relative; width: 100px; height: 100px; border-radius: 50%; background: var(--bg-card); border: 3px solid var(--primary); padding: 2px; box-shadow: 0 10px 25px color-mix(in srgb, var(--primary) 25%, transparent); animation: floatAvatar 3s ease-in-out infinite;">
                        <div style="width: 100%; height: 100%; border-radius: 50%; background: color-mix(in srgb, var(--primary) 8%, var(--bg-card)); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: var(--primary);">
                            <i class="fa-solid fa-wand-magic-sparkles"></i>
                        </div>
                    </div>
                    <span style="position: absolute; bottom: 2px; right: 2px; width: 26px; height: 26px; border-radius: 50%; background: #10b981; color: #fff; border: 2.5px solid var(--bg-card); display: flex; align-items: center; justify-content: center; font-size: 0.75rem; box-shadow: 0 2px 8px rgba(16,185,129,0.3);">
                        <i class="fa-solid fa-check"></i>
                    </span>
                </div>

                <div style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.25rem 0.8rem; border-radius: 9999px; background: color-mix(in srgb, var(--primary) 10%, transparent); border: 1px solid color-mix(in srgb, var(--primary) 20%, transparent); color: var(--primary); font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.85rem;">
                    <i class="fa-solid fa-sparkles"></i>
                    <span x-show="selectedLang === 'hy'">Խելացի AI Մատուցող</span>
                    <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Intelligent AI Waiter</span>
                    <span x-show="selectedLang === 'ru'">Интеллектуальный AI-официант</span>
                </div>

                <h1 style="font-family: 'Outfit', sans-serif; font-size: 2rem; font-weight: 900; color: var(--text-main); margin: 0 0 0.5rem 0; line-height: 1.2;">
                    <span x-show="selectedLang === 'hy'">👋 Բարև</span>
                    <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">👋 Hello</span>
                    <span x-show="selectedLang === 'ru'">👋 Здравствуйте</span>
                </h1>

                <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin: 0 0 0.75rem 0;">
                    <span x-show="selectedLang === 'hy'">Ես <strong>{{ $vendor->getAiWaiterName() }}</strong>-ն եմ, ձեր AI մատուցողը։</span>
                    <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">I am <strong>{{ $vendor->getAiWaiterName() }}</strong>, your personal AI dining advisor.</span>
                    <span x-show="selectedLang === 'ru'">Я <strong>{{ $vendor->getAiWaiterName() }}</strong>, ваш персональный AI-официант.</span>
                </h2>

                <p style="font-size: 0.95rem; line-height: 1.6; color: var(--text-muted); max-width: 460px; margin: 0 auto 1.75rem auto;">
                    <span x-show="selectedLang === 'hy'">Կօգնեմ ընտրել հենց այն, ինչ ձեզ առավելագույնս դուր կգա մեր մենյուից՝ Ձեր տրամադրությանն ու նախասիրություններին համապատասխան։</span>
                    <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">I will guide you to culinary delights that match your mood, dietary tastes, and occasion perfectly.</span>
                    <span x-show="selectedLang === 'ru'">Я помогу подобрать идеальные блюда и напитки по вашему вкусу, настроению и предпочтениям.</span>
                </p>

                <!-- Value Highlights Cards -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.65rem; max-width: 480px; margin: 0 auto 2rem auto;">
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 0.85rem 0.5rem; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                        <div style="font-size: 1.4rem; margin-bottom: 0.25rem;">⚡</div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-main);">
                            <span x-show="selectedLang === 'hy'">30 վայրկյան</span>
                            <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">30 Seconds</span>
                            <span x-show="selectedLang === 'ru'">30 секунд</span>
                        </div>
                    </div>
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 0.85rem 0.5rem; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                        <div style="font-size: 1.4rem; margin-bottom: 0.25rem;">🥩</div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-main);">
                            <span x-show="selectedLang === 'hy'">Անհատական</span>
                            <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Tailored</span>
                            <span x-show="selectedLang === 'ru'">Персонально</span>
                        </div>
                    </div>
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 0.85rem 0.5rem; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                        <div style="font-size: 1.4rem; margin-bottom: 0.25rem;">🍷</div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-main);">
                            <span x-show="selectedLang === 'hy'">Զուգորդում</span>
                            <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Pairings</span>
                            <span x-show="selectedLang === 'ru'">Сочетания</span>
                        </div>
                    </div>
                </div>

                <!-- CTA Actions -->
                <div style="max-width: 420px; margin: 0 auto; display: flex; flex-direction: column; gap: 0.75rem;">
                    <button type="button" 
                            @click="aiStep = 'language'"
                            class="ai-primary-btn">
                        <span>🚀</span>
                        <span x-show="selectedLang === 'hy'">Սկսել</span>
                        <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Start</span>
                        <span x-show="selectedLang === 'ru'">Начать</span>
                    </button>

                    <button type="button" 
                            @click="skipToMenu()"
                            class="ai-secondary-btn">
                        <span x-show="selectedLang === 'hy'">Տեսնել ամբողջ մենյուն</span>
                        <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Browse Full Menu Directly</span>
                        <span x-show="selectedLang === 'ru'">Посмотреть всё меню</span>
                        <i class="fa-solid fa-arrow-right" style="font-size: 0.75rem;"></i>
                    </button>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- SCREEN 2: LANGUAGE SELECTION               -->
            <!-- ========================================== -->
            <div x-show="aiStep === 'language'" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 style="max-width: 480px; margin: 0 auto; padding: 1.5rem 0.5rem;">
                
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
                    <button type="button" 
                            @click="aiStep = 'intro'" 
                            style="background: none; border: none; color: var(--text-muted); font-size: 0.85rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 0.35rem;">
                        <i class="fa-solid fa-arrow-left"></i>
                        <span x-show="selectedLang === 'hy'">Հետ</span>
                        <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Back</span>
                        <span x-show="selectedLang === 'ru'">Назад</span>
                    </button>
                    <span style="font-size: 0.75rem; font-weight: 700; color: var(--primary); text-transform: uppercase;">Step 1 of 2</span>
                </div>

                <div style="text-align: center; margin-bottom: 2rem;">
                    <div style="font-size: 2.2rem; margin-bottom: 0.5rem;">🌍</div>
                    <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.45rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.4rem 0;">
                        {{ __('menu.select_your_language') }}
                    </h2>
                    <p style="font-size: 0.88rem; color: var(--text-muted); margin: 0;">
                        {{ __('menu.change_language_anytime') }}
                    </p>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.85rem; margin-bottom: 2rem;">
                    <template x-for="langItem in getAiLanguagesList()" :key="langItem.code">
                        <button type="button" 
                                @click="selectAiLanguage(langItem.code)"
                                :class="{ 'ai-lang-active': selectedLang === langItem.code }"
                                class="ai-language-card">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <span style="font-size: 1.8rem;" x-text="langItem.flag || '🌐'"></span>
                                <div style="text-align: left;">
                                    <div style="font-weight: 800; font-size: 1.05rem; color: var(--text-main);" x-text="langItem.native_name || langItem.name"></div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);" x-text="langItem.name || langItem.code.toUpperCase()"></div>
                                </div>
                            </div>
                            <i class="fa-solid fa-chevron-right" style="color: var(--text-muted); font-size: 0.9rem;"></i>
                        </button>
                    </template>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- SCREEN 3: AI GREETING & CONFIRMATION       -->
            <!-- ========================================== -->
            <div x-show="aiStep === 'greeting'" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 style="max-width: 480px; margin: 0 auto; text-align: center; padding: 2rem 0.5rem;">
                
                <div style="width: 80px; height: 80px; margin: 0 auto 1.25rem auto; border-radius: 50%; background: color-mix(in srgb, var(--primary) 12%, transparent); border: 2px solid var(--primary); display: flex; align-items: center; justify-content: center; font-size: 2.2rem; color: var(--primary);">
                    <i class="fa-solid fa-face-smile-wink"></i>
                </div>

                <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.65rem; font-weight: 900; color: var(--text-main); margin: 0 0 0.5rem 0;">
                    <span x-show="selectedLang === 'hy'">Հիանալի 😊</span>
                    <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Wonderful 😊</span>
                    <span x-show="selectedLang === 'ru'">Отлично 😊</span>
                </h2>

                <p style="font-size: 0.95rem; line-height: 1.6; color: var(--text-muted); margin: 0 auto 2rem auto; max-width: 420px;">
                    <span x-show="selectedLang === 'hy'">Մի քանի կարճ հարց կտամ, որպեսզի ձեզ համար ընտրեմ լավագույն և ամենահամեղ տարբերակները։</span>
                    <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">I'll ask a couple of quick questions to curate the finest dishes and pairings tailored for you.</span>
                    <span x-show="selectedLang === 'ru'">Задам буквально несколько коротких вопросов, чтобы подобрать для вас самые вкусные блюда.</span>
                </p>

                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <button type="button" 
                            @click="startAiQuestions()"
                            class="ai-primary-btn">
                        <span>✨</span>
                        <span x-show="selectedLang === 'hy'">Սկսել</span>
                        <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Start</span>
                        <span x-show="selectedLang === 'ru'">Начать</span>
                    </button>

                    <button type="button" 
                            @click="skipToMenu()"
                            class="ai-secondary-btn">
                        <span x-show="selectedLang === 'hy'">Տեսնել ամբողջ մենյուն</span>
                        <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Browse Full Menu Directly</span>
                        <span x-show="selectedLang === 'ru'">Посмотреть всё меню</span>
                        <i class="fa-solid fa-arrow-right" style="font-size: 0.75rem;"></i>
                    </button>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- SCREEN 4: DYNAMIC QUESTION SCREEN          -->
            <!-- ========================================== -->
            <div x-show="aiStep === 'question'" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 style="max-width: 540px; margin: 0 auto; padding: 0.5rem 0.25rem;">
                
                <!-- Dynamic Progress Header -->
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                    <button type="button" 
                            @click="backAiQuestion()" 
                            style="background: none; border: none; color: var(--text-muted); font-size: 0.85rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 0.35rem;">
                        <i class="fa-solid fa-arrow-left"></i>
                        <span x-show="selectedLang === 'hy'">Հետ</span>
                        <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Back</span>
                        <span x-show="selectedLang === 'ru'">Назад</span>
                    </button>

                    <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; font-weight: 800; color: var(--primary);">
                        <i class="fa-solid fa-circle-dot"></i>
                        <span x-text="(selectedLang === 'hy' ? 'Հարց ' : (selectedLang === 'ru' ? 'Вопрос ' : 'Question ')) + (aiQuestionHistory.length + 1) + ' / 4'"></span>
                    </div>

                    <button type="button" 
                            @click="skipAiQuestion()" 
                            style="background: none; border: none; color: var(--text-muted); font-size: 0.82rem; font-weight: 600; cursor: pointer;">
                        <span x-show="selectedLang === 'hy'">Բաց թողնել</span>
                        <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Skip</span>
                        <span x-show="selectedLang === 'ru'">Пропустить</span>
                    </button>
                </div>

                <!-- Progress Bar -->
                <div style="width: 100%; height: 6px; background: color-mix(in srgb, var(--primary) 12%, transparent); border-radius: 9999px; margin-bottom: 1.5rem; overflow: hidden;">
                    <div style="height: 100%; background: var(--primary); border-radius: 9999px; transition: width 0.3s ease;"
                         :style="'width: ' + Math.min(100, Math.max(25, (aiQuestionHistory.length + 1) * 25)) + '%;'"></div>
                </div>

                <!-- Active Question Card -->
                <template x-if="aiCurrentQuestion">
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: 0 4px 18px -2px rgba(15, 23, 42, 0.04); margin-bottom: 1.25rem;">
                        
                        <!-- Question Header -->
                        <div style="margin-bottom: 1.25rem;">
                            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.35rem; font-weight: 900; color: var(--text-main); margin: 0 0 0.35rem 0; line-height: 1.3;"
                                x-text="aiCurrentQuestion.title"></h3>
                            <p style="font-size: 0.86rem; color: var(--text-muted); margin: 0;"
                                x-text="aiCurrentQuestion.subtitle"></p>
                        </div>

                        <!-- Quick Answer Options Grid -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.65rem; margin-bottom: 1.5rem;">
                            <template x-for="opt in (aiCurrentQuestion.options || [])" :key="opt.value">
                                <button type="button" 
                                        @click="submitAiAnswer(aiCurrentQuestion.key, opt.value)"
                                        :disabled="aiLoading"
                                        class="ai-question-opt-btn">
                                    <span style="font-size: 1.4rem;" x-text="opt.emoji || '🍽️'"></span>
                                    <span style="font-size: 0.88rem; font-weight: 700; color: var(--text-main);" x-text="opt.label"></span>
                                </button>
                            </template>
                        </div>

                        <!-- Free Text Input Divider -->
                        <div style="position: relative; text-align: center; margin: 1.25rem 0 1rem 0;">
                            <div style="position: absolute; inset: 50% 0 auto 0; border-top: 1px solid var(--border-color);"></div>
                            <span style="position: relative; background: var(--bg-card); padding: 0 0.75rem; font-size: 0.76rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">
                                <span x-show="selectedLang === 'hy'">Կամ գրեք Ձեր պատասխանը</span>
                                <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Or type your custom answer</span>
                                <span x-show="selectedLang === 'ru'">Или напишите свой вариант</span>
                            </span>
                        </div>

                        <!-- Free Text Input Form -->
                        <form @submit.prevent="submitAiAnswer(aiCurrentQuestion.key, null, aiFreeTextInput)">
                            <div style="position: relative; display: flex; align-items: center;">
                                <input type="text" 
                                       x-model="aiFreeTextInput"
                                       :placeholder="selectedLang === 'hy' ? 'Օրինակ՝ Մսային ուտեստ առանց սնկի, թեթև սոուսով...' : (selectedLang === 'ru' ? 'например: Мясо без грибов, не острый соус...' : 'e.g. Tender meat without mushrooms, light sauce...')"
                                       class="form-control" 
                                       style="width: 100%; background: var(--bg-main); border: 1.5px solid var(--border-color); color: var(--text-main); border-radius: 14px; padding: 0.85rem 3.4rem 0.85rem 1rem; font-size: 0.92rem; outline: none; transition: border-color 0.2s;">
                                
                                <button type="submit" 
                                        :disabled="aiLoading || !aiFreeTextInput.trim()"
                                        style="position: absolute; right: 0.4rem; width: 36px; height: 36px; border-radius: 10px; border: none; background: var(--primary); color: #ffffff; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: opacity 0.15s;"
                                        :style="aiFreeTextInput.trim() ? 'opacity: 1;' : 'opacity: 0.45; cursor: not-allowed;'">
                                    <i class="fa-solid fa-arrow-right" x-show="!aiLoading"></i>
                                    <i class="fa-solid fa-spinner fa-spin" x-show="aiLoading" x-cloak></i>
                                </button>
                            </div>
                        </form>

                    </div>
                </template>

            </div>

            <!-- ========================================== -->
            <!-- SCREEN 5: AI ANALYSIS ANIMATION SCREEN     -->
            <!-- ========================================== -->
            <div x-show="aiStep === 'analyzing'" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 style="max-width: 480px; margin: 0 auto; text-align: center; padding: 3rem 0.5rem;">
                
                <div style="position: relative; width: 90px; height: 90px; margin: 0 auto 2rem auto;">
                    <div style="position: absolute; inset: -12px; border-radius: 50%; background: color-mix(in srgb, var(--primary) 20%, transparent); filter: blur(8px); animation: pulseAvatar 1.5s infinite ease-in-out;"></div>
                    <div style="width: 90px; height: 90px; border-radius: 50%; background: var(--bg-card); border: 3px solid var(--primary); padding: 2px; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 24px color-mix(in srgb, var(--primary) 25%, transparent);">
                        <div style="width: 100%; height: 100%; border-radius: 50%; background: color-mix(in srgb, var(--primary) 8%, var(--bg-card)); display: flex; align-items: center; justify-content: center; font-size: 2.2rem; color: var(--primary);">
                            <i class="fa-solid fa-wand-magic-sparkles fa-spin" style="--fa-animation-duration: 3s;"></i>
                        </div>
                    </div>
                </div>

                <!-- Analysis Dynamic Steps Sequence -->
                <div style="min-height: 80px;">
                    <div x-show="aiAnalysisStep === 1" x-transition>
                        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.4rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.35rem 0;">
                            <span x-show="selectedLang === 'hy'">🔎 Մի պահ...</span>
                            <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">🔎 One moment...</span>
                            <span x-show="selectedLang === 'ru'">🔎 Одну секунду...</span>
                        </h3>
                        <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">
                            <span x-show="selectedLang === 'hy'">Նախապատրաստում եմ առաջարկները</span>
                            <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Preparing your personalized selection</span>
                            <span x-show="selectedLang === 'ru'">Готовим персональный подбор</span>
                        </p>
                    </div>

                    <div x-show="aiAnalysisStep === 2" x-transition>
                        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.4rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.35rem 0;">
                            <span x-show="selectedLang === 'hy'">📋 Նայում եմ մեր մենյուին...</span>
                            <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">📋 Reviewing our fresh menu...</span>
                            <span x-show="selectedLang === 'ru'">📋 Изучаю актуальное меню...</span>
                        </h3>
                        <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">
                            <span x-show="selectedLang === 'hy'">Ստուգում եմ առկա ուտեստները և բաղադրիչները</span>
                            <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Checking active ingredients and chef specialties</span>
                            <span x-show="selectedLang === 'ru'">Проверяю свежие ингредиенты и блюда</span>
                        </p>
                    </div>

                    <div x-show="aiAnalysisStep === 3" x-transition>
                        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.4rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.35rem 0;">
                            <span x-show="selectedLang === 'hy'">🧠 Համադրում եմ ձեր նախասիրությունները...</span>
                            <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">🧠 Matching your exact taste...</span>
                            <span x-show="selectedLang === 'ru'">🧠 Сопоставляю с вашими вкусами...</span>
                        </h3>
                        <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">
                            <span x-show="selectedLang === 'hy'">Հաշվարկում եմ համային համահունչ զուգորդումները</span>
                            <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Calibrating flavor harmony and pairings</span>
                            <span x-show="selectedLang === 'ru'">Подбираю гармоничные гастро-пары</span>
                        </p>
                    </div>

                    <div x-show="aiAnalysisStep === 4" x-transition>
                        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.4rem; font-weight: 800; color: #10b981; margin: 0 0 0.35rem 0;">
                            <span x-show="selectedLang === 'hy'">✨ Գտա ձեզ համար հարմար տարբերակները։</span>
                            <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">✨ Found your ideal match!</span>
                            <span x-show="selectedLang === 'ru'">✨ Нашел идеальные блюда для вас!</span>
                        </h3>
                        <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">
                            <span x-show="selectedLang === 'hy'">Բացում եմ անհատական մենյուն...</span>
                            <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Opening recommendations...</span>
                            <span x-show="selectedLang === 'ru'">Открываю рекомендации...</span>
                        </p>
                    </div>
                </div>

                <div style="width: 140px; height: 4px; background: color-mix(in srgb, var(--primary) 15%, transparent); border-radius: 9999px; margin: 2rem auto 0 auto; overflow: hidden;">
                    <div style="width: 100%; height: 100%; background: var(--primary); animation: shimmerBar 1.2s infinite ease-in-out;"></div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- SCREEN 6: PERSONALIZED RECOMMENDATIONS     -->
            <!-- ========================================== -->
            <div x-show="aiStep === 'recommendations'" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0">
                
                <!-- Screen Header Title -->
                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.25rem;">
                    <div>
                        <div style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.72rem; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.3rem;">
                            <i class="fa-solid fa-sparkles"></i>
                            <span x-show="selectedLang === 'hy'">Անհատական ընտրանի</span>
                            <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Personalized Selection</span>
                            <span x-show="selectedLang === 'ru'">Персональный выбор</span>
                        </div>
                        <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.45rem; font-weight: 800; color: var(--text-main); margin: 0;">
                            <span x-show="selectedLang === 'hy'">✨ Ձեզ համար ընտրեցինք</span>
                            <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">✨ Selected Just for You</span>
                            <span x-show="selectedLang === 'ru'">✨ Подобранное для вас</span>
                        </h2>
                    </div>

                    <button type="button" 
                            @click="aiStep = 'question'"
                            class="ai-refine-btn">
                        <i class="fa-solid fa-sliders"></i>
                        <span x-show="selectedLang === 'hy'">Փոխել</span>
                        <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Refine</span>
                        <span x-show="selectedLang === 'ru'">Изменить</span>
                    </button>
                </div>

                <!-- Sommelier Commentary Bubble -->
                <div x-show="aiCommentary" 
                     class="ai-note-box">
                    <div style="width: 36px; height: 36px; border-radius: 10px; background: color-mix(in srgb, var(--primary) 12%, transparent); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0;">
                        <i class="fa-solid fa-quote-left"></i>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 800; font-size: 0.74rem; color: var(--primary); margin-bottom: 0.2rem; text-transform: uppercase; letter-spacing: 0.04em;">
                            {{ $vendor->getAiWaiterName() }} &bull; AI NOTE
                        </div>
                        <div style="font-size: 0.88rem; line-height: 1.5; color: var(--text-main);" x-text="aiCommentary"></div>
                    </div>
                </div>

                <!-- 1. MAIN RECOMMENDATIONS (Hero Cards) -->
                <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem;">
                    <template x-for="(rec, idx) in aiMainRecommendations" :key="rec.id">
                        <div class="ai-hero-card">
                            
                            <!-- Match Score Pill Badge -->
                            <div style="position: absolute; top: 1rem; right: 1rem; z-index: 5;" class="ai-match-badge">
                                <i class="fa-solid fa-fire"></i>
                                <span x-text="rec.match_score + '% Match'"></span>
                            </div>

                            <!-- In-cart quantity badge -->
                            <template x-if="getCartItemQty(rec.id) > 0">
                                <div style="position: absolute; top: 1rem; left: 1rem; z-index: 2; background: #10b981; color: #ffffff; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.35);">
                                    <i class="fa-solid fa-check"></i>
                                    <span x-show="selectedLang === 'hy'" x-text="getCartItemQty(rec.id) + ' հատ զամբյուղում'"></span>
                                    <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)" x-text="getCartItemQty(rec.id) + ' in cart'"></span>
                                    <span x-show="selectedLang === 'ru'" x-text="getCartItemQty(rec.id) + ' в корзине'"></span>
                                </div>
                            </template>

                            <div style="display: flex; gap: 1rem; align-items: flex-start;">
                                <img :src="rec.image || '{{ asset('images/default-dish.png') }}'" 
                                     x-on:error="$event.target.src = '{{ asset('images/default-dish.png') }}'" 
                                     :alt="rec.name" 
                                     style="width: 105px; height: 105px; border-radius: 14px; object-fit: cover; flex-shrink: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">

                                <div style="flex: 1; min-width: 0; padding-right: 4.5rem;">
                                    <div style="display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.25rem; flex-wrap: wrap;">
                                        <span class="ai-meta-pill" x-text="rec.category_name"></span>
                                        <template x-if="rec.calories">
                                            <span class="ai-meta-pill">
                                                <i class="fa-solid fa-fire-flame-curved" style="color: #f59e0b; font-size: 0.68rem;"></i>
                                                <span x-text="rec.calories + ' kcal'"></span>
                                            </span>
                                        </template>
                                    </div>

                                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.3rem 0; line-height: 1.3;" x-text="rec.name"></h3>
                                    <div style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.45; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" x-text="rec.description"></div>
                                </div>
                            </div>

                            <!-- Pricing & One-Tap Add CTA -->
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: 0.85rem; border-top: 1px solid var(--border-color);">
                                <div class="ai-price-text" x-text="rec.formatted_price"></div>

                                <button type="button" 
                                        @click="addAiDishToCart(rec)"
                                        class="ai-action-btn"
                                        :class="{ 'in-cart': getCartItemQty(rec.id) > 0 }">
                                    <template x-if="getCartItemQty(rec.id) === 0">
                                        <span style="display: flex; align-items: center; gap: 0.45rem;">
                                            <i class="fa-solid fa-plus"></i>
                                            <span x-show="selectedLang === 'hy'">Ավելացնել պատվերին</span>
                                            <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Add to Order</span>
                                            <span x-show="selectedLang === 'ru'">Добавить к заказу</span>
                                        </span>
                                    </template>
                                    <template x-if="getCartItemQty(rec.id) > 0">
                                        <span style="display: flex; align-items: center; gap: 0.45rem;">
                                            <i class="fa-solid fa-check"></i>
                                            <span x-show="selectedLang === 'hy'" x-text="'Զամբյուղում է (' + getCartItemQty(rec.id) + ') +1'"></span>
                                            <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)" x-text="'In Cart (' + getCartItemQty(rec.id) + ') +1'"></span>
                                            <span x-show="selectedLang === 'ru'" x-text="'В корзине (' + getCartItemQty(rec.id) + ') +1'"></span>
                                        </span>
                                    </template>
                                </button>
                            </div>

                            <!-- AI Explanation: Why this dish? -->
                            <template x-if="rec.reason">
                                <div class="ai-reason-box">
                                    <span style="font-size: 0.95rem; flex-shrink: 0; line-height: 1.2;">💡</span>
                                    <div style="flex: 1; min-width: 0;">
                                        <strong style="color: var(--text-main); font-size: 0.78rem; display: block; margin-bottom: 0.15rem;">
                                            <span x-show="selectedLang === 'hy'">Ինչու՞ սա:</span>
                                            <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Why this dish?</span>
                                            <span x-show="selectedLang === 'ru'">Почему это блюдо?</span>
                                        </strong>
                                        <span x-text="rec.reason"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- 2. SMART PAIRING BUNDLE CARD -->
                <template x-if="aiBundle && aiBundle.items && aiBundle.items.length >= 2">
                    <div class="ai-bundle-card">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.85rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fa-solid fa-sparkles" style="color: var(--primary); font-size: 1rem;"></i>
                                <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0;">
                                    <span x-show="selectedLang === 'hy'">Խելացի Համադրություն · Smart Bundle</span>
                                    <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Smart Pairing Bundle</span>
                                    <span x-show="selectedLang === 'ru'">Комбо-набор · Smart Bundle</span>
                                </h3>
                            </div>
                            <span style="font-size: 0.7rem; font-weight: 800; background: var(--primary); color: #ffffff; padding: 0.2rem 0.65rem; border-radius: 9999px; letter-spacing: 0.03em;">1-TAP COMBO</span>
                        </div>

                        <div style="font-size: 0.84rem; color: var(--text-muted); margin-bottom: 1rem; line-height: 1.45;" x-text="aiBundle.note"></div>

                        <!-- Bundle Items Row -->
                        <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1.25rem;">
                            <template x-for="(item, bIdx) in aiBundle.items" :key="item.id">
                                <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.65rem 0.85rem; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;">
                                    <div style="display: flex; align-items: center; gap: 0.65rem; min-width: 0;">
                                        <img :src="item.image || '{{ asset('images/default-dish.png') }}'" 
                                             x-on:error="$event.target.src = '{{ asset('images/default-dish.png') }}'" 
                                             style="width: 40px; height: 40px; border-radius: 10px; object-fit: cover; flex-shrink: 0;">
                                        <div style="min-width: 0;">
                                            <div style="font-size: 0.88rem; font-weight: 700; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="item.name"></div>
                                            <div style="font-size: 0.72rem; color: var(--text-muted);" x-text="item.category_name"></div>
                                        </div>
                                    </div>
                                    <div style="font-family: 'Outfit', sans-serif; font-size: 0.88rem; font-weight: 800; color: var(--text-main); white-space: nowrap;" x-text="item.formatted_price"></div>
                                </div>
                            </template>
                        </div>

                        <!-- Bundle Total & Single Tap Order CTA -->
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 0.85rem; border-top: 1px dashed var(--border-color);">
                            <div>
                                <div style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.03em;">
                                    <span x-show="selectedLang === 'hy'">Ընդհանուր արժեքը</span>
                                    <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Total Bundle Price</span>
                                    <span x-show="selectedLang === 'ru'">Общая стоимость</span>
                                </div>
                                <div style="font-family: 'Outfit', sans-serif; font-size: 1.3rem; font-weight: 800; color: var(--text-main);" x-text="aiBundle.formatted_total"></div>
                            </div>

                            <button type="button" 
                                    @click="addAiBundleToCart()"
                                    class="ai-bundle-btn">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                                <span x-show="selectedLang === 'hy'">Ավելացնել ամբողջ առաջարկը</span>
                                <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Add Entire Bundle</span>
                                <span x-show="selectedLang === 'ru'">Добавить весь набор</span>
                            </button>
                        </div>
                    </div>
                </template>

                <!-- 3. SECONDARY ALTERNATIVES & DRINK PAIRING GRID -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                    
                    <!-- Secondary Alternatives -->
                    <template x-if="aiSecondaryRecommendations && aiSecondaryRecommendations.length > 0">
                        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.15rem; box-shadow: 0 4px 18px -2px rgba(15, 23, 42, 0.04);">
                            <div style="font-family: 'Outfit', sans-serif; font-size: 0.95rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.85rem; display: flex; align-items: center; gap: 0.45rem;">
                                <span>🥈</span>
                                <span x-show="selectedLang === 'hy'">Այլընտրանքային առաջարկներ</span>
                                <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Alternative Highlights</span>
                                <span x-show="selectedLang === 'ru'">Альтернативные варианты</span>
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 0.55rem;">
                                <template x-for="alt in aiSecondaryRecommendations" :key="alt.id">
                                    <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.65rem 0.85rem; display: flex; align-items: center; justify-content: space-between; gap: 0.65rem;">
                                        <div style="display: flex; align-items: center; gap: 0.65rem; min-width: 0;">
                                            <img :src="alt.image || '{{ asset('images/default-dish.png') }}'" 
                                                 x-on:error="$event.target.src = '{{ asset('images/default-dish.png') }}'" 
                                                 style="width: 44px; height: 44px; border-radius: 10px; object-fit: cover; flex-shrink: 0;">
                                            <div style="min-width: 0;">
                                                <div style="font-size: 0.88rem; font-weight: 700; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="alt.name"></div>
                                                <div style="font-family: 'Outfit', sans-serif; font-size: 0.82rem; font-weight: 800; color: var(--text-main); margin-top: 0.1rem;" x-text="alt.formatted_price"></div>
                                            </div>
                                        </div>

                                        <button type="button" 
                                                @click="addAiDishToCart(alt)"
                                                :title="getCartItemQty(alt.id) > 0 ? '{{ __('menu.added') }} (' + getCartItemQty(alt.id) + ')' : '{{ __('menu.add') }}'"
                                                class="ai-card-btn-add"
                                                :class="{ 'in-cart': getCartItemQty(alt.id) > 0 }">
                                            <i :class="getCartItemQty(alt.id) > 0 ? 'fa-solid fa-check' : 'fa-solid fa-plus'"></i>
                                            <span x-show="getCartItemQty(alt.id) > 0" x-text="getCartItemQty(alt.id)"></span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Drink Pairing -->
                    <template x-if="aiPairingDrink">
                        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.15rem; box-shadow: 0 4px 18px -2px rgba(15, 23, 42, 0.04);">
                            <div style="font-family: 'Outfit', sans-serif; font-size: 0.95rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.85rem; display: flex; align-items: center; gap: 0.45rem;">
                                <span>🍷</span>
                                <span x-show="selectedLang === 'hy'">Առաջարկվող Ըմպելիք</span>
                                <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Suggested Drink Pairing</span>
                                <span x-show="selectedLang === 'ru'">Рекомендуемый напиток</span>
                            </div>

                            <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.65rem 0.85rem; display: flex; align-items: center; justify-content: space-between; gap: 0.65rem;">
                                <div style="display: flex; align-items: center; gap: 0.65rem; min-width: 0;">
                                    <img :src="aiPairingDrink.image || '{{ asset('images/default-dish.png') }}'" 
                                         x-on:error="$event.target.src = '{{ asset('images/default-dish.png') }}'" 
                                         style="width: 44px; height: 44px; border-radius: 10px; object-fit: cover; flex-shrink: 0;">
                                    <div style="min-width: 0;">
                                        <div style="font-size: 0.88rem; font-weight: 700; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="aiPairingDrink.name"></div>
                                        <div style="font-family: 'Outfit', sans-serif; font-size: 0.82rem; font-weight: 800; color: var(--text-main); margin-top: 0.1rem;" x-text="aiPairingDrink.formatted_price"></div>
                                    </div>
                                </div>

                                <button type="button" 
                                        @click="addAiDishToCart(aiPairingDrink)"
                                        :title="getCartItemQty(aiPairingDrink.id) > 0 ? '{{ __('menu.added') }} (' + getCartItemQty(aiPairingDrink.id) + ')' : '{{ __('menu.add') }}'"
                                        class="ai-card-btn-add"
                                        :class="{ 'in-cart': getCartItemQty(aiPairingDrink.id) > 0 }">
                                    <i :class="getCartItemQty(aiPairingDrink.id) > 0 ? 'fa-solid fa-check' : 'fa-solid fa-plus'"></i>
                                    <span x-show="getCartItemQty(aiPairingDrink.id) > 0" x-text="getCartItemQty(aiPairingDrink.id)"></span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- 4. CHAT WITH AI WAITER PROMPT CARD (Section 23) -->
                <div class="ai-ask-card" @click="openAiChat()">
                    <div style="display: flex; align-items: center; gap: 0.85rem; min-width: 0;">
                        <div style="width: 42px; height: 42px; border-radius: 12px; background: color-mix(in srgb, var(--primary) 12%, transparent); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0;">
                            <i class="fa-solid fa-comments"></i>
                        </div>
                        <div style="min-width: 0;">
                            <div style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 0.95rem; color: var(--text-main);">
                                <span x-show="selectedLang === 'hy'">💬 Հարցրեք AI մատուցողին</span>
                                <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">💬 Ask AI Waiter Anything</span>
                                <span x-show="selectedLang === 'ru'">💬 Спросите у AI-официанта</span>
                            </div>
                            <div style="font-size: 0.78rem; color: var(--text-muted); line-height: 1.35; margin-top: 0.15rem;">
                                <span x-show="selectedLang === 'hy'">Ունե՞ք հարցեր բաղադրիչների կամ առանց մսի ուտեստների մասին:</span>
                                <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Have questions about allergens, meatless options or kids menu?</span>
                                <span x-show="selectedLang === 'ru'">Есть вопросы по аллергенам, веганским блюдам или напиткам?</span>
                            </div>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right" style="color: var(--text-muted); font-size: 0.85rem; flex-shrink: 0;"></i>
                </div>

            </div>

        </div>
    </div>

    <!-- ========================================== -->
    <!-- STICKY CART BAR (Sections 19, 21)          -->
    <!-- ========================================== -->
    <footer style="background: var(--bg-card); border-top: 1px solid var(--border-color); padding: 0.75rem 1.25rem max(0.75rem, env(safe-area-inset-bottom)) 1.25rem; z-index: 30; flex-shrink: 0; box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.05); width: 100%;">
        <div style="max-width: 680px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <button type="button" 
                    @click="closeAiWaiter()" 
                    style="background: transparent; border: none; color: var(--text-muted); font-size: 0.88rem; font-weight: 600; display: flex; align-items: center; gap: 0.4rem; cursor: pointer;">
                <i class="fa-solid fa-arrow-left"></i>
                <span x-show="selectedLang === 'hy'">Մենյու</span>
                <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Menu</span>
                <span x-show="selectedLang === 'ru'">В меню</span>
            </button>

            <!-- Sticky Cart summary & direct checkout trigger -->
            <button type="button" 
                    @click="closeAiWaiter(); showCartModal = true"
                    :class="{ 'cart-bump': cartBump }"
                    :style="cartTotalCount > 0 ? 'background: #10b981; box-shadow: 0 4px 18px rgba(16, 185, 129, 0.4);' : 'background: var(--primary);'"
                    style="color: #ffffff; border: none; border-radius: 14px; padding: 0.72rem 1.35rem; font-size: 0.92rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem; cursor: pointer; box-shadow: 0 4px 14px rgba(0,0,0,0.15); transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);">
                <i class="fa-solid fa-basket-shopping"></i>
                <span x-show="cartTotalCount === 0">
                    <span x-show="selectedLang === 'hy'">Զամբյուղ</span>
                    <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Cart</span>
                    <span x-show="selectedLang === 'ru'">Корзина</span>
                </span>
                <span x-show="cartTotalCount > 0" style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="background: rgba(255, 255, 255, 0.25); border-radius: 999px; padding: 0.1rem 0.5rem; font-size: 0.82rem;" x-text="cartTotalCount"></span>
                    <span x-text="formatCurrency(cartTotalPrice)"></span>
                    <span style="opacity: 0.85; font-size: 0.85rem;">•</span>
                    <span x-show="selectedLang === 'hy'">Դեպի Զամբյուղ</span>
                    <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">View Cart</span>
                    <span x-show="selectedLang === 'ru'">В Корзину</span>
                </span>
                <i class="fa-solid fa-arrow-right" style="font-size: 0.8rem;"></i>
            </button>
        </div>
    </footer>

    <!-- ========================================== -->
    <!-- AI CHAT MODE DRAWER (Section 23, 24)       -->
    <!-- ========================================== -->
    <div x-show="showAiChatDrawer" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="position: fixed; inset: 0; z-index: 99998; background: rgba(0,0,0,0.55); backdrop-filter: blur(8px); display: flex; justify-content: flex-end;"
         x-cloak>
        
        <div @click.away="closeAiChat()"
             style="width: 100%; max-width: 480px; height: 100%; background: var(--bg-card); display: flex; flex-direction: column; box-shadow: -10px 0 30px rgba(0,0,0,0.25);">
            
            <!-- Drawer Header -->
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 0.65rem;">
                    <div style="width: 38px; height: 38px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.05rem; box-shadow: 0 3px 10px rgba(0,0,0,0.12);">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                    </div>
                    <div>
                        <div style="font-family: 'Outfit', sans-serif; font-size: 1.05rem; font-weight: 800; color: var(--text-main);">
                            {{ $vendor->getAiWaiterName() }}
                        </div>
                        <div style="font-size: 0.72rem; color: #10b981; font-weight: 600;">
                            ● <span x-show="selectedLang === 'hy'">Պատրաստ է պատասխանել</span>
                            <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Ready to answer</span>
                            <span x-show="selectedLang === 'ru'">Готов ответить</span>
                        </div>
                    </div>
                </div>

                <button type="button" 
                        @click="closeAiChat()"
                        class="ai-header-btn">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Preset Quick Prompt Pills -->
            <div style="padding: 0.65rem 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; gap: 0.45rem; overflow-x: auto; scrollbar-width: none;">
                <button type="button" @click="sendAiChatMessage(selectedLang === 'hy' ? 'Ի՞նչ ունեք առանց մսի։' : (selectedLang === 'ru' ? 'Что есть без мяса?' : 'What meatless options do you have?'))" class="prompt-preset-chip">
                    <span x-text="selectedLang === 'hy' ? '🥗 Առանց մսի' : (selectedLang === 'ru' ? '🥗 Без мяса' : '🥗 Meatless')"></span>
                </button>
                <button type="button" @click="sendAiChatMessage(selectedLang === 'hy' ? 'Ի՞նչ խորհուրդ կտաս գարեջրի հետ։' : (selectedLang === 'ru' ? 'Что посоветуете к пиву?' : 'What goes well with beer?'))" class="prompt-preset-chip">
                    <span x-text="selectedLang === 'hy' ? '🍺 Գարեջրի հետ' : (selectedLang === 'ru' ? '🍺 К пиву' : '🍺 With beer')"></span>
                </button>
                <button type="button" @click="sendAiChatMessage(selectedLang === 'hy' ? 'Ի՞նչն է ամենաքիչ կծուն։' : (selectedLang === 'ru' ? 'Что самое не острое?' : 'What is the least spicy dish?'))" class="prompt-preset-chip">
                    <span x-text="selectedLang === 'hy' ? '🌶️ Ոչ կծու' : (selectedLang === 'ru' ? '🌶️ Не острое' : '🌶️ Not spicy')"></span>
                </button>
                <button type="button" @click="sendAiChatMessage(selectedLang === 'hy' ? 'Ի՞նչն է հարմար երեխաների համար։' : (selectedLang === 'ru' ? 'Что подойдет для детей?' : 'What is good for kids?'))" class="prompt-preset-chip">
                    <span x-text="selectedLang === 'hy' ? '👶 Երեխաների համար' : (selectedLang === 'ru' ? '👶 Для детей' : '👶 For kids')"></span>
                </button>
            </div>

            <!-- Messages Stream Box -->
            <div id="aiChatMessagesBox" 
                 style="flex: 1 1 0%; min-height: 0; padding: 1.25rem; overflow-y: auto; display: flex; flex-direction: column; gap: 1rem; -webkit-overflow-scrolling: touch;">
                <template x-for="(msg, mIdx) in aiChatMessages" :key="mIdx">
                    <div>
                        <!-- User Message -->
                        <template x-if="msg.sender === 'user'">
                            <div style="display: flex; justify-content: flex-end;">
                                <div style="max-width: 82%; background: var(--primary); color: #ffffff; padding: 0.75rem 1rem; border-radius: 18px 18px 4px 18px; font-size: 0.92rem; line-height: 1.45; box-shadow: 0 3px 10px color-mix(in srgb, var(--primary) 25%, transparent);"
                                     x-text="msg.text"></div>
                            </div>
                        </template>

                        <!-- AI Message -->
                        <template x-if="msg.sender === 'ai'">
                            <div style="display: flex; gap: 0.65rem; align-items: flex-start; max-width: 92%;">
                                <div style="width: 30px; height: 30px; border-radius: 50%; background: color-mix(in srgb, var(--primary) 12%, transparent); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0; margin-top: 0.2rem;">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.75rem 1rem; border-radius: 4px 18px 18px 18px; font-size: 0.92rem; line-height: 1.5; position: relative;">
                                        <div x-text="msg.text"></div>
                                        
                                        <!-- Voice Readout Button -->
                                        <div style="display: flex; justify-content: flex-end; margin-top: 0.4rem;">
                                            <button type="button" 
                                                    @click="toggleSpeakAiMessage(msg.text)" 
                                                    :class="{ 'speaking': currentlySpeakingText === msg.text }"
                                                    class="ai-speak-btn"
                                                    :title="currentlySpeakingText === msg.text ? 'Դադարեցնել' : 'Լսել ձայնով'">
                                                <i :class="currentlySpeakingText === msg.text ? 'fa-solid fa-volume-high' : 'fa-solid fa-volume-low'"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Embedded Product Cards inside Chat -->
                                    <template x-if="msg.products && msg.products.length > 0">
                                        <div style="display: flex; flex-direction: column; gap: 0.55rem; margin-top: 0.75rem;">
                                            <template x-for="p in msg.products" :key="p.id">
                                                <div class="ai-chat-product-card">
                                                    <div style="display: flex; align-items: center; gap: 0.65rem; min-width: 0; flex: 1;">
                                                        <img :src="p.image || '{{ asset('images/default-dish.png') }}'" 
                                                             x-on:error="$event.target.src = '{{ asset('images/default-dish.png') }}'" 
                                                             class="ai-chat-product-img">
                                                        <div style="min-width: 0; flex: 1;">
                                                            <div class="ai-chat-product-title" x-text="p.name"></div>
                                                            <div class="ai-chat-product-price" x-text="p.formatted_price"></div>
                                                        </div>
                                                    </div>

                                                    <button type="button" 
                                                            @click="addAiDishToCart(p)"
                                                            :title="getCartItemQty(p.id) > 0 ? '{{ __('menu.added') }} (' + getCartItemQty(p.id) + ')' : '{{ __('menu.add_to_cart') }}'"
                                                            class="ai-card-btn-add"
                                                            :class="{ 'in-cart': getCartItemQty(p.id) > 0 }">
                                                        <template x-if="getCartItemQty(p.id) === 0">
                                                            <i class="fa-solid fa-plus"></i>
                                                        </template>
                                                        <template x-if="getCartItemQty(p.id) > 0">
                                                            <span style="display: flex; align-items: center; gap: 0.25rem;">
                                                                <i class="fa-solid fa-check"></i>
                                                                <span x-text="getCartItemQty(p.id)"></span>
                                                            </span>
                                                        </template>
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                    <!-- Interactive Contextual Quick Replies -->
                                    <template x-if="msg.quick_replies && msg.quick_replies.length > 0">
                                        <div style="display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.65rem;">
                                            <template x-for="qr in msg.quick_replies" :key="qr">
                                                <button type="button" 
                                                        @click="sendAiChatMessage(qr)"
                                                        style="padding: 0.35rem 0.75rem; border-radius: 9999px; background: color-mix(in srgb, var(--primary) 10%, transparent); border: 1px solid color-mix(in srgb, var(--primary) 30%, transparent); color: var(--primary); font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: all 0.2s;"
                                                        x-text="qr">
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- AI Chat Typing indicator -->
                <div x-show="aiChatLoading" style="display: flex; gap: 0.5rem; align-items: center; color: var(--text-muted); font-size: 0.85rem;">
                    <i class="fa-solid fa-spinner fa-spin" style="color: var(--primary);"></i>
                    <span x-show="selectedLang === 'hy'">Մտածում եմ...</span>
                    <span x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Thinking...</span>
                    <span x-show="selectedLang === 'ru'">Печатает...</span>
                </div>
            </div>

            <!-- Chat Bottom Input Bar with Voice Support -->
            <div style="padding: 0.85rem 1.25rem max(0.85rem, env(safe-area-inset-bottom)) 1.25rem; border-top: 1px solid var(--border-color); background: var(--bg-card);">
                <!-- Voice Listening Active Indicator Banner -->
                <div x-show="isVoiceListening" x-cloak class="ai-voice-listening-banner">
                    <span style="width: 8px; height: 8px; border-radius: 50%; background: #ef4444; animation: pulseAvatar 1s infinite;"></span>
                    <span style="flex: 1;" x-show="selectedLang === 'hy'">Ձայնագրվում է... Խոսեք հիմա</span>
                    <span style="flex: 1;" x-show="selectedLang === 'en' || !['hy', 'ru'].includes(selectedLang)">Listening... Speak now</span>
                    <span style="flex: 1;" x-show="selectedLang === 'ru'">Слушаю... Говорите</span>
                    <button type="button" @click="toggleVoiceInput()" style="background: none; border: none; color: #ef4444; font-size: 0.85rem; font-weight: 700; cursor: pointer;">
                        <i class="fa-solid fa-stop"></i>
                    </button>
                </div>

                <form @submit.prevent="sendAiChatMessage()">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <!-- Voice Input (Microphone) Button -->
                        <button type="button" 
                                @click="toggleVoiceInput()"
                                :class="{ 'ai-voice-active': isVoiceListening }"
                                class="ai-voice-btn"
                                :title="isVoiceListening ? 'Դադարեցնել խոսափողը' : 'Խոսել AI մատուցողի հետ'">
                            <i :class="isVoiceListening ? 'fa-solid fa-waveform-lines' : 'fa-solid fa-microphone'"></i>
                        </button>

                        <div style="position: relative; flex: 1; display: flex; align-items: center;">
                            <input type="text" 
                                    x-model="aiChatInput" 
                                    :placeholder="selectedLang === 'hy' ? 'Հարցրեք բաղադրիչների, գինու, աղանդերի մասին...' : (selectedLang === 'ru' ? 'Спросите об ингредиентах, вине, десертах...' : 'Ask about ingredients, wine, desserts...')"
                                    style="width: 100%; background: var(--bg-main); border: 1.5px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 2.85rem 0.75rem 0.85rem; font-size: 0.9rem; outline: none;">
                            
                            <button type="submit" 
                                    :disabled="aiChatLoading || !aiChatInput.trim()"
                                    style="position: absolute; right: 0.35rem; width: 34px; height: 34px; border-radius: 9px; border: none; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: opacity 0.2s;">
                                <i class="fa-solid fa-paper-plane" style="font-size: 0.85rem;"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

        </div>
    </div>

</div>

<link rel="stylesheet" href="{{ asset('css/ai-waiter.css') }}">
@endif
