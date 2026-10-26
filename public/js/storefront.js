/**
 * Elab.menu Digital Menu Storefront Engine
 * High-performance, modular, reactive client runtime.
 */
(function(window) {
    'use strict';

    // Register PWA Service Worker if supported
    if ('serviceWorker' in navigator && window.location.protocol === 'https:') {
        window.addEventListener('load', function() {
            const configEl = document.getElementById('storefront-config');
            let swUrl = '/m/sw.js';
            if (configEl) {
                try {
                    const cfg = JSON.parse(configEl.textContent || '{}');
                    if (cfg.routes && cfg.routes.sw) swUrl = cfg.routes.sw;
                } catch(e) {}
            }
            navigator.serviceWorker.register(swUrl).catch(function(err) {
                console.debug('ServiceWorker registration skipped:', err);
            });
        });
    }

    function createStorefrontApp(customConfig = {}) {
        const configEl = document.getElementById('storefront-config');
        let bootConfig = {};
        if (configEl) {
            try {
                bootConfig = JSON.parse(configEl.textContent || '{}');
            } catch(e) {
                console.error('Failed to parse storefront-config JSON:', e);
            }
        }

        const vendor = bootConfig.vendor || {};
        const routes = bootConfig.routes || {};
        const settings = bootConfig.settings || {};
        const translations = bootConfig.translations || {};
        const catalogProducts = bootConfig.catalogProducts || [];

        const urlParams = new URLSearchParams(window.location.search);
        const isPreOrderUrl = urlParams.get('mode') === 'preorder' || urlParams.get('preorder') === '1';
        let isPreOrderStorage = false;
        try {
            isPreOrderStorage = localStorage.getItem('active_preorder_mode_' + (vendor.slug || '')) === 'true';
        } catch(e) {}
        const isPreOrderMode = isPreOrderUrl || isPreOrderStorage;
        if (isPreOrderUrl) {
            try {
                localStorage.setItem('active_preorder_mode_' + (vendor.slug || ''), 'true');
            } catch(e) {}
        }

        return {
            isPreOrderMode: isPreOrderMode,
            preOrderSummaryText: '',
            catalogProducts: catalogProducts,
            activeCat: customConfig.activeCat || bootConfig.activeCat || 'cat-1',
            _scrollSpyInitialized: false,
            _isProgrammaticScroll: false,
            _programmaticScrollStart: 0,
            _programmaticScrollTimer: null,
            _scrollSpyRaf: null,
            search: '',
            cart: [],
            cartBump: false,
            showCartModal: false,
            showVariationModal: false,
            showPWA: true,
            selectedDish: null,
            selectedVariation: null,
            variationQty: 1,
            currentTab: 'menu',
            showInfoModal: false,
            showScrollTop: false,
            showOrderTracker: false,
            hideTrackerPill: false,
            activeOrder: null,
            orderPollInterval: null,
            orderDismissTimeout: null,
            wifiCopied: false,
            phoneCountryCode: 'AM',
            phoneNationalNumber: '',
            showPhoneCountryDropdown: false,
            phoneErrorMsg: '',
            emailErrorMsg: '',
            customerName: '',
            customerPhone: customConfig.customerPhone || '',
            customerEmail: customConfig.customerEmail || '',
            customerBirthdate: '',
            phoneCountries: [
                { code: 'AM', flag: '🇦🇲', dial: '+374', digits: 8, name: translations.country_armenia || 'Armenia', placeholder: '94 123456' },
                { code: 'RU', flag: '🇷🇺', dial: '+7', digits: 10, name: translations.country_russia || 'Russia', placeholder: '999 123-45-67' },
                { code: 'GE', flag: '🇬🇪', dial: '+995', digits: 9, name: translations.country_georgia || 'Georgia', placeholder: '599 12-34-56' },
                { code: 'US', flag: '🇺🇸', dial: '+1', digits: 10, name: translations.country_usa_canada || 'USA / Canada', placeholder: '555 123-4567' },
                { code: 'FR', flag: '🇫🇷', dial: '+33', digits: 9, name: translations.country_france || 'France', placeholder: '6 12 34 56 78' },
                { code: 'DE', flag: '🇩🇪', dial: '+49', digits: 10, minDigits: 10, maxDigits: 11, name: translations.country_germany || 'Germany', placeholder: '170 1234567' },
                { code: 'AE', flag: '🇦🇪', dial: '+971', digits: 9, name: translations.country_uae || 'UAE', placeholder: '50 123 4567' },
                { code: 'IR', flag: '🇮🇷', dial: '+98', digits: 10, name: translations.country_iran || 'Iran', placeholder: '912 123 4567' },
                { code: 'OTHER', flag: '🌐', dial: '+', digits: null, minDigits: 7, maxDigits: 15, name: translations.country_other || 'Other Country', placeholder: '123456789' }
            ],
            get selectedPhoneCountry() {
                return this.phoneCountries.find(c => c.code === this.phoneCountryCode) || this.phoneCountries[0];
            },
            get phoneCleanDigits() {
                return (this.phoneNationalNumber || '').replace(/\D/g, '');
            },
            get hasUnavailableCartItems() {
                return (this.cart || []).some(item => {
                    if (this.orderType === 'dine_in' && item.available_for_dine_in === false) return true;
                    if (this.orderType === 'takeaway' && item.available_for_takeaway === false) return true;
                    if (this.orderType === 'delivery' && item.available_for_delivery === false) return true;
                    return false;
                });
            },
            paymentMethod: settings.paymentMethod || 'cash',
            birthdayDiscountPercent: Number(settings.birthdayDiscountPercent || 15),
            birthdayDiscountEnabled: !!settings.birthdayDiscountEnabled,
            birthdayValidityDays: Number(settings.birthdayValidityDays || 3),
            orderType: customConfig.orderType || settings.orderType || 'dine_in',
            deliveryAddress: '',
            serviceFeeEnabled: !!settings.serviceFeeEnabled,
            serviceFeeType: settings.serviceFeeType || 'percent',
            serviceFeeValue: Number(settings.serviceFeeValue || 0),
            serviceFeeMinOrder: Number(settings.serviceFeeMinOrder || 0),
            deliveryEnabled: !!settings.deliveryEnabled,
            deliveryFee: Number(settings.deliveryFee || 0),
            deliveryMinAmount: Number(settings.deliveryMinAmount || 0),
            deliveryFreeFrom: settings.deliveryFreeFrom !== null && settings.deliveryFreeFrom !== undefined ? Number(settings.deliveryFreeFrom) : null,
            takeawayEnabled: !!settings.takeawayEnabled,
            takeawayMinAmount: Number(settings.takeawayMinAmount || 0),
            isTableFixed: !!bootConfig.isTableFixed,
            tableNumber: customConfig.tableNumber !== undefined ? customConfig.tableNumber : (bootConfig.table || ''),
            showOrderTypeModal: false,
            showWaiterModal: false,
            serviceType: 'call_waiter',
            serviceTable: customConfig.tableNumber !== undefined && customConfig.tableNumber ? customConfig.tableNumber : (bootConfig.table || ''),
            isCallingService: false,
            marketingOptIn: true,
            orderNotes: '',
            selectedLang: bootConfig.lang || 'hy',
            langDirection: bootConfig.dir || document.documentElement.dir || (['ar', 'fa', 'he', 'ur'].includes(bootConfig.lang || 'hy') ? 'rtl' : 'ltr'),
            i18n: { ...translations },
            localeBundles: bootConfig.localeBundles || {},
            catalogTranslations: (bootConfig.localeBundles && bootConfig.localeBundles[bootConfig.lang || 'hy'] && bootConfig.localeBundles[bootConfig.lang || 'hy'].catalog) || { categories: {}, products: {}, daily_offers: {} },
            isSwitchingLanguage: false,
            _langSwitchSeq: 0,
            _langAbortController: null,
            isSubmittingOrder: false,
            currentOrderIdempotencyKey: null,
            isOffline: typeof navigator !== 'undefined' ? !navigator.onLine : false,
            currency: (!vendor.currency || vendor.currency === 'AMD') ? '֏' : vendor.currency,
            vendorSlug: vendor.slug || '',
            csrfToken: bootConfig.csrfToken || '',
            locationId: bootConfig.locationId || 1,
            schedules: bootConfig.schedules || {},
            dismissedWarningBanner: false,

            // Tips & Ratings State
            tipsRatingsSettings: settings.tipsRatings || {},
            splitBillSettings: settings.splitBill || {},
            selectedTipType: (settings.tipsRatings && settings.tipsRatings.default_tip_selection) || 'none',
            selectedTipValue: 0,
            customTipInput: '',
            selectedWaiterId: null,

            // Service & Waiter Rating Modal State
            showRatingModal: false,
            ratingOrderNumber: null,
            ratingWaiterId: null,
            ratingScore: 5,
            ratingCategories: [],
            ratingComment: '',
            ratingIsAnonymous: false,
            ratingSubmitting: false,
            ratingSubmitted: false,
            ratingErrorMessage: '',

            // Language & Entry Context State
            needsLanguageSelection: bootConfig.needsLanguageSelection || false,
            showLanguageModal: bootConfig.needsLanguageSelection || false,
            entryContext: bootConfig.entryContext || 'general_menu',
            tableNumber: bootConfig.tableNumber || '',
            activeMealPeriod: bootConfig.activeMealPeriod || null,
            timeAwareGreeting: bootConfig.greeting || '',
            aiGreetingText: bootConfig.greeting || '',

            // AI Waiter State
            showWelcomeModal: false,
            showAiWaiter: false,
            showAiChatDrawer: false,
            aiStep: 'intro',
            aiAnalysisStep: 1,
            aiSessionId: null,
            aiSessionToken: null,
            aiCurrentQuestion: null,
            aiQuestionHistory: [],
            aiAnswerHistory: [],
            aiPreferences: {
                craving: null,
                occasion: null,
                drink_preference: null,
                dietary: []
            },
            aiFreeTextInput: '',
            aiPromptInput: '',
            aiLoading: false,
            aiCommentary: '',
            aiMainRecommendations: [],
            aiSecondaryRecommendations: [],
            aiPairingDrink: null,
            aiBundle: null,
            aiRecommendations: [],
            aiChatMessages: [
                {
                    sender: 'ai',
                    text: (vendor.ai_waiter_name || 'AI Waiter') + ': ' + (translations.ai_chat_default_greeting || 'Hello! I am ready to answer any question about our menu.')
                }
            ],
            aiChatInput: '',
            aiChatLoading: false,
            aiAllowedLanguages: vendor.ai_waiter_languages || ['hy', 'en', 'ru'],
            aiLanguageDetails: vendor.ai_waiter_language_details || [],

            // AI Voice Conversation
            isVoiceListening: false,
            speechRecognition: null,
            currentlySpeakingText: null,

            // Toast State
            toast: {
                show: false,
                message: '',
                type: 'success',
                icon: 'fa-solid fa-circle-check',
                timeout: null
            },

            init() {
                this.restoreActiveOrderFromStorage();
                this.restoreCartFromStorage();
                this.initAiWaiterWelcome();

                // Initial order type prompt for direct visitors (no table QR)
                if (this.isPreOrderMode) {
                    this.showOrderTypeModal = false;
                    this.orderType = 'dine_in';
                    this.initPreOrderState();
                } else if (!this.isTableFixed) {
                    if (this.deliveryEnabled && this.takeawayEnabled) {
                        let sessionChosen = null;
                        try {
                            sessionChosen = sessionStorage.getItem('storefront_order_type_chosen_' + this.vendorSlug);
                        } catch(e) {}
                        if (sessionChosen && (sessionChosen === 'delivery' || sessionChosen === 'takeaway')) {
                            this.orderType = sessionChosen;
                            this.showOrderTypeModal = false;
                        } else if (!this.orderType || (this.orderType !== 'delivery' && this.orderType !== 'takeaway')) {
                            this.showOrderTypeModal = true;
                        }
                    } else if (this.deliveryEnabled && !this.takeawayEnabled) {
                        this.orderType = 'delivery';
                    } else if (this.takeawayEnabled && !this.deliveryEnabled) {
                        this.orderType = 'takeaway';
                    }
                }

                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('payment_success') === '1') {
                    this.triggerToast(translations.payment_success || '🎉 Վճարումը հաջողությամբ կատարվել է:', 'success', 'fa-solid fa-circle-check');
                }
                this.$nextTick(() => {
                    this.initScrollSpy();
                });
                if (this.customerPhone) {
                    this.phoneNationalNumber = this.customerPhone;
                    this.onPhoneInput();
                }
                if (this.closingNotice && this.closingNotice.enabled) {
                    if (this.closingNotice.closing_soon && !this.closingNotice.is_closed) {
                        setTimeout(() => {
                            if (!this.dismissedWarningBanner) {
                                this.triggerToast(this.closingNotice.notice_title || this.closingNotice.message || translations.closing_soon_notice_title || 'Ուշադրություն: Խոհանոցը շուտով փակվում է:', 'info', 'fa-solid fa-clock');
                            }
                        }, 1200);
                    }
                }

                if (typeof this.$watch === 'function') {
                    this.$watch('customerName', () => this.saveCartToStorage());
                    this.$watch('phoneNationalNumber', () => this.saveCartToStorage());
                    this.$watch('phoneCountryCode', () => this.saveCartToStorage());
                    this.$watch('customerEmail', () => this.saveCartToStorage());
                    this.$watch('customerBirthdate', () => this.saveCartToStorage());
                    this.$watch('deliveryAddress', () => this.saveCartToStorage());
                    this.$watch('orderNotes', () => this.saveCartToStorage());
                    this.$watch('orderType', () => this.saveCartToStorage());
                }

                window.addEventListener('beforeunload', () => {
                    this.saveCartToStorage();
                });
                window.addEventListener('pagehide', () => {
                    this.saveCartToStorage();
                });
                window.addEventListener('scroll', () => {
                    this.showScrollTop = (window.pageYOffset > 280);
                }, { passive: true });
                window.addEventListener('online', () => {
                    this.isOffline = false;
                    this.triggerToast(this.t('connection_restored', {}, 'Կապը վերականգնված է'), 'success', 'fa-solid fa-wifi');
                });
                window.addEventListener('offline', () => {
                    this.isOffline = true;
                    this.triggerToast(this.t('offline_notice', {}, 'Դուք անցանց ռեժիմում եք: Մենյուն հասանելի է քեշից:'), 'info', 'fa-solid fa-plane');
                });
                window.addEventListener('popstate', () => {
                    try {
                        const urlParams = new URLSearchParams(window.location.search);
                        const langInUrl = urlParams.get('lang');
                        if (langInUrl && langInUrl !== this.selectedLang) {
                            this.selectStorefrontLanguage(langInUrl);
                        }
                    } catch (err) {}
                });
            },

            setOrderType(type) {
                if (this.isTableFixed) return;
                if (type !== 'delivery' && type !== 'takeaway') return;
                if (type === 'delivery' && !this.deliveryEnabled) {
                    this.triggerToast(translations.delivery_disabled_notice || 'Առաքման ծառայությունը ներկայումս հասանելի չէ', 'remove', 'fa-solid fa-ban');
                    return;
                }
                if (type === 'takeaway' && !this.takeawayEnabled) {
                    this.triggerToast(translations.takeaway_disabled_notice || 'Տեղում վերցնելու ծառայությունը ներկայումս հասանելի չէ', 'remove', 'fa-solid fa-ban');
                    return;
                }
                this.orderType = type;
                try {
                    sessionStorage.setItem('storefront_order_type_chosen_' + this.vendorSlug, type);
                } catch(e) {}
                this.saveCartToStorage();
                this.showOrderTypeModal = false;

                const unavailable = (this.cart || []).filter(item => {
                    if (type === 'delivery' && item.available_for_delivery === false) return true;
                    if (type === 'takeaway' && item.available_for_takeaway === false) return true;
                    return false;
                });
                if (unavailable.length > 0) {
                    const names = unavailable.map(i => '«' + i.name + '»').join(', ');
                    const tpl = translations.attention_item_unavailable || 'Ուշադրություն. :items հասանելի չէ ընտրված պատվերի եղանակի համար';
                    this.triggerToast(tpl.replace(':items', names), 'remove', 'fa-solid fa-triangle-exclamation');
                } else {
                    const label = type === 'delivery' 
                        ? (translations.switched_to_delivery || 'Ընտրվել է՝ Առաքում') 
                        : (translations.switched_to_takeaway || 'Ընտրվել է՝ Տեղում վերցնել');
                    const icon = type === 'delivery' ? 'fa-solid fa-motorcycle' : 'fa-solid fa-bag-shopping';
                    this.triggerToast(label, 'info', icon);
                }

                this.$nextTick(() => {
                    this.adjustActiveCategoryIfHidden();
                });
            },

            selectInitialOrderType(type) {
                this.setOrderType(type);
            },

            isProductAvailableForCurrentOrderType(dineIn = true, takeaway = true, delivery = true) {
                if (this.orderType === 'dine_in') {
                    return dineIn !== false;
                }
                if (this.orderType === 'takeaway') {
                    return takeaway !== false;
                }
                if (this.orderType === 'delivery') {
                    return delivery !== false;
                }
                return true;
            },

            isDishVisible(dishId, dishName, dineIn = true, takeaway = true, delivery = true) {
                if (!this.matchesSearch(dishName)) return false;
                return this.isProductAvailableForCurrentOrderType(dineIn, takeaway, delivery);
            },

            isCategoryVisible(catId, dishes = null) {
                let prods = dishes;
                if (!prods && this.catalogProducts) {
                    const idNum = parseInt(String(catId).replace('cat-', ''), 10);
                    prods = this.catalogProducts.filter(p => Number(p.category_id) === idNum).map(p => ({
                        name: p.name,
                        dine_in: p.available_for_dine_in !== false,
                        takeaway: p.available_for_takeaway !== false,
                        delivery: p.available_for_delivery !== false,
                    }));
                }
                if (!prods || prods.length === 0) return false;
                return prods.some(d => {
                    const matches = !this.search || !this.search.trim() || (d.name || '').toLowerCase().includes(this.search.trim().toLowerCase());
                    if (!matches) return false;
                    return this.isProductAvailableForCurrentOrderType(d.dine_in, d.takeaway, d.delivery);
                });
            },

            adjustActiveCategoryIfHidden() {
                const activeChip = document.getElementById('chip-' + this.activeCat);
                if (activeChip && activeChip.offsetParent === null) {
                    const firstVisible = Array.from(document.querySelectorAll('.cat-chip')).find(el => el.offsetParent !== null);
                    if (firstVisible && firstVisible.id) {
                        this.activeCat = firstVisible.id.replace('chip-', '');
                        this.centerActiveCategoryChip(this.activeCat, true);
                    }
                }
            },

            triggerToast(message, type = 'success', icon = null) {
                if (this.toast.timeout) clearTimeout(this.toast.timeout);
                this.toast.message = message;
                this.toast.type = type;
                this.toast.icon = icon || (type === 'success' ? 'fa-solid fa-circle-check' : (type === 'remove' ? 'fa-solid fa-trash-can' : 'fa-solid fa-circle-info'));
                this.toast.show = true;
                if (navigator.vibrate) {
                    try { navigator.vibrate(30); } catch(e) {}
                }
                this.toast.timeout = setTimeout(() => {
                    this.toast.show = false;
                }, 2500);
            },

            initApp() {
                this.initScrollSpy();
            },

            getStickyCategoryHeaderOffset() {
                const stickyNav = document.querySelector('.category-nav-wrap, .category-nav-wrapper, .category-scroll, .category-nav');
                const preorderBanner = document.querySelector('.storefront-preorder-top-banner');
                let offset = 64;
                if (stickyNav) {
                    const rect = stickyNav.getBoundingClientRect();
                    offset = Math.max(offset, rect.height + Math.max(0, rect.top));
                } else if (preorderBanner && preorderBanner.offsetParent !== null) {
                    offset += preorderBanner.offsetHeight;
                }
                return offset;
            },

            centerActiveCategoryChip(catId, smooth = true) {
                const targetId = catId || this.activeCat;
                if (!targetId) return;
                const activeChip = document.getElementById('chip-' + targetId);
                if (!activeChip || activeChip.offsetParent === null) return;

                const scroller = activeChip.closest('.category-scroll, .category-nav, .category-nav-wrapper') || activeChip.parentElement;
                if (!scroller) return;

                const scrollerRect = scroller.getBoundingClientRect();
                const chipRect = activeChip.getBoundingClientRect();
                if (!scrollerRect.width) return;

                const chipCenter = (chipRect.left - scrollerRect.left) + scroller.scrollLeft + (chipRect.width / 2);
                const maxScroll = Math.max(0, scroller.scrollWidth - scroller.clientWidth);
                const targetScrollLeft = Math.max(0, Math.min(maxScroll, chipCenter - (scrollerRect.width / 2)));

                if (Math.abs(scroller.scrollLeft - targetScrollLeft) < 2) return;

                try {
                    scroller.scrollTo({
                        left: targetScrollLeft,
                        behavior: smooth ? 'smooth' : 'auto'
                    });
                } catch (e) {
                    scroller.scrollLeft = targetScrollLeft;
                }
            },

            scrollToCat(catId) {
                this.activeCat = catId;
                this.centerActiveCategoryChip(catId, true);

                const el = document.getElementById(catId);
                if (el) {
                    this._isProgrammaticScroll = true;
                    this._programmaticScrollStart = Date.now();
                    if (this._programmaticScrollTimer) {
                        clearTimeout(this._programmaticScrollTimer);
                    }
                    this._programmaticScrollTimer = setTimeout(() => {
                        this._isProgrammaticScroll = false;
                        this._programmaticScrollTimer = null;
                    }, 750);

                    const stickyNav = document.querySelector('.category-nav-wrap, .category-nav-wrapper, .category-scroll, .category-nav');
                    const preorderBanner = document.querySelector('.storefront-preorder-top-banner');
                    const navHeight = stickyNav ? stickyNav.offsetHeight : 58;
                    const bannerHeight = (preorderBanner && preorderBanner.offsetParent !== null) ? preorderBanner.offsetHeight : 0;
                    const headerOffset = navHeight + bannerHeight + 12;

                    const elementPosition = el.getBoundingClientRect().top;
                    const offsetPosition = Math.max(0, elementPosition + window.pageYOffset - headerOffset);
                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                }
            },

            updateActiveCategoryOnScroll() {
                if (this._isProgrammaticScroll) return;

                const sections = Array.from(document.querySelectorAll('.category-section, .category-anchor, [id^="cat-"]'))
                    .filter(sec => sec.id && /^cat-\d+$/.test(sec.id) && sec.offsetParent !== null && sec.getClientRects().length > 0);

                if (!sections.length) return;

                const scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
                const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 800;
                const docHeight = Math.max(
                    document.body ? document.body.scrollHeight : 0,
                    document.documentElement ? document.documentElement.scrollHeight : 0
                );

                if (scrollY <= 20) {
                    const firstId = sections[0].id;
                    if (this.activeCat !== firstId) {
                        this.activeCat = firstId;
                        this.centerActiveCategoryChip(firstId, true);
                    }
                    return;
                }

                if (scrollY + viewportHeight >= docHeight - 24) {
                    const lastSection = sections[sections.length - 1];
                    if (lastSection && lastSection.getBoundingClientRect().top < viewportHeight * 0.75) {
                        if (this.activeCat !== lastSection.id) {
                            this.activeCat = lastSection.id;
                            this.centerActiveCategoryChip(lastSection.id, true);
                        }
                        return;
                    }
                }

                const stickyBottom = this.getStickyCategoryHeaderOffset();
                const activationLine = Math.max(stickyBottom + 24, Math.min(220, Math.round(viewportHeight * 0.28)));

                let candidateId = sections[0].id;
                for (let i = 0; i < sections.length; i++) {
                    const rect = sections[i].getBoundingClientRect();
                    if (rect.top <= activationLine) {
                        candidateId = sections[i].id;
                    } else {
                        break;
                    }
                }

                if (candidateId && this.activeCat !== candidateId) {
                    this.activeCat = candidateId;
                    this.centerActiveCategoryChip(candidateId, true);
                }
            },

            initScrollSpy() {
                if (this._scrollSpyInitialized) return;
                this._scrollSpyInitialized = true;

                const onScroll = () => {
                    if (this._scrollSpyRaf) return;
                    this._scrollSpyRaf = window.requestAnimationFrame(() => {
                        this._scrollSpyRaf = null;
                        this.updateActiveCategoryOnScroll();
                    });
                };

                const releaseProgrammaticLockOnUserInput = () => {
                    if (this._isProgrammaticScroll && (Date.now() - this._programmaticScrollStart > 120)) {
                        this._isProgrammaticScroll = false;
                        if (this._programmaticScrollTimer) {
                            clearTimeout(this._programmaticScrollTimer);
                            this._programmaticScrollTimer = null;
                        }
                    }
                };

                window.addEventListener('scroll', onScroll, { passive: true });
                window.addEventListener('resize', onScroll, { passive: true });
                window.addEventListener('touchstart', releaseProgrammaticLockOnUserInput, { passive: true });
                window.addEventListener('wheel', releaseProgrammaticLockOnUserInput, { passive: true });
                if ('onscrollend' in window) {
                    window.addEventListener('scrollend', releaseProgrammaticLockOnUserInput, { passive: true });
                }

                this.updateActiveCategoryOnScroll();
                this.centerActiveCategoryChip(this.activeCat, false);
            },

            matchesSearch(title, dishId = null) {
                if (!this.search || !this.search.trim()) return true;
                const query = this.search.trim().toLowerCase();
                if ((title || '').toLowerCase().includes(query)) return true;
                if (dishId) {
                    const localizedTitle = this.dishName(dishId);
                    if (localizedTitle && localizedTitle.toLowerCase().includes(query)) return true;
                    const catProd = this.catalogProducts ? this.catalogProducts.find(p => String(p.id) === String(dishId)) : null;
                    if (catProd && (catProd.name_lower || '').includes(query)) return true;
                }
                return false;
            },

            selectDish(rawDish) {
                const dish = this.getLocalizedDish(rawDish);
                if (dish.is_available === false) {
                    this.triggerToast(translations.out_of_stock || 'Ուտեստը ժամանակավորապես սպառված է այս մասնաճյուղում', 'remove', 'fa-solid fa-ban');
                    return;
                }
                if (dish.is_time_available === false) {
                    const sched = dish.schedule_summary || '';
                    const toastTemplate = translations.product_time_restriction_toast || 'Տվյալ պրոդուկտը կարող եք պատվիրել միայն :time';
                    const msg = sched 
                        ? toastTemplate.replace(':time', sched) 
                        : (translations.closed_hours || 'Ուտեստը հասանելի չէ այս ժամին');
                    this.triggerToast(msg, 'remove', 'fa-regular fa-clock');
                    return;
                }
                if (this.orderType === 'dine_in' && dish.available_for_dine_in === false) {
                    this.triggerToast(translations.item_unavailable_for_channel || 'Այս ուտեստը հասանելի չէ սրահում պատվիրելու համար', 'remove', 'fa-solid fa-ban');
                    return;
                }
                if (this.orderType === 'takeaway' && dish.available_for_takeaway === false) {
                    this.triggerToast(translations.item_unavailable_for_channel || 'Այս ուտեստը հասանելի չէ տանելու պատվերի համար', 'remove', 'fa-solid fa-ban');
                    return;
                }
                if (this.orderType === 'delivery' && dish.available_for_delivery === false) {
                    this.triggerToast(translations.item_unavailable_for_channel || 'Այս ուտեստը հասանելի չէ առաքման համար', 'remove', 'fa-solid fa-ban');
                    return;
                }

                if (!dish.variations || dish.variations.length <= 1) {
                    const v = (dish.variations && dish.variations.length === 1) ? dish.variations[0] : null;
                    this.addToCart(
                        dish.id,
                        dish.name,
                        v ? Number(v.price) : Number(dish.base_price || dish.price || 0),
                        v ? v.name : 'Standard',
                        v ? v.id : null,
                        1,
                        dish
                    );
                    return;
                }
                this.selectedDish = dish;
                this.selectedVariation = dish.variations.find(v => v.is_default) || dish.variations[0];
                this.variationQty = 1;
                this.showVariationModal = true;
            },

            addSelectedVariationToCart() {
                if (!this.selectedDish || !this.selectedVariation) return;
                if (this.selectedDish.is_time_available === false) {
                    const sched = this.selectedDish.schedule_summary || '';
                    const toastTemplate = translations.product_time_restriction_toast || 'Տվյալ պրոդուկտը կարող եք պատվիրել միայն :time';
                    const msg = sched 
                        ? toastTemplate.replace(':time', sched) 
                        : (translations.closed_hours || 'Ուտեստը հասանելի չէ այս ժամին');
                    this.triggerToast(msg, 'remove', 'fa-regular fa-clock');
                    this.showVariationModal = false;
                    return;
                }
                this.addToCart(
                    this.selectedDish.id,
                    this.selectedDish.name,
                    Number(this.selectedVariation.price),
                    this.selectedVariation.name,
                    this.selectedVariation.id,
                    this.variationQty,
                    this.selectedDish
                );
                this.showVariationModal = false;
            },

            addToCart(id, name, price, variationName = 'Standard', variationId = null, qty = 1, dishObj = null) {
                name = this.dishName(id, name);
                if (variationId && variationName !== 'Standard' && variationName !== 'Standard Portion') {
                    variationName = this.variationName(id, variationId, variationName);
                }
                const targetObj = dishObj || (this.catalogProducts ? this.catalogProducts.find(p => p.id === id) : null);
                if (targetObj) {
                    if (targetObj.is_available === false) {
                        this.triggerToast(translations.out_of_stock || 'Ուտեստը ժամանակավորապես սպառված է այս մասնաճյուղում', 'remove', 'fa-solid fa-ban');
                        return;
                    }
                    if (targetObj.is_time_available === false) {
                        const sched = targetObj.schedule_summary || '';
                        const toastTemplate = translations.product_time_restriction_toast || 'Տվյալ պրոդուկտը կարող եք պատվիրել միայն :time';
                        const msg = sched 
                            ? toastTemplate.replace(':time', sched) 
                            : (translations.closed_hours || 'Ուտեստը հասանելի չէ այս ժամին');
                        this.triggerToast(msg, 'remove', 'fa-regular fa-clock');
                        return;
                    }
                    if (this.orderType === 'dine_in' && targetObj.available_for_dine_in === false) {
                        this.triggerToast(translations.item_unavailable_for_channel || 'Այս ուտեստը հասանելի չէ սրահում պատվիրելու համար', 'remove', 'fa-solid fa-ban');
                        return;
                    }
                    if (this.orderType === 'takeaway' && targetObj.available_for_takeaway === false) {
                        this.triggerToast(translations.item_unavailable_for_channel || 'Այս ուտեստը հասանելի չէ տանելու պատվերի համար', 'remove', 'fa-solid fa-ban');
                        return;
                    }
                    if (this.orderType === 'delivery' && targetObj.available_for_delivery === false) {
                        this.triggerToast(translations.item_unavailable_for_channel || 'Այս ուտեստը հասանելի չէ առաքման համար', 'remove', 'fa-solid fa-ban');
                        return;
                    }
                }

                let existing = this.cart.find(c => c.id === id && (c.variation_id && variationId ? c.variation_id === variationId : c.variation_name === variationName));
                if (existing) {
                    existing.qty += qty;
                } else {
                    this.cart.push({
                        id: id,
                        product_id: id,
                        name: name,
                        price: Number(price),
                        variation_name: variationName,
                        variation_id: variationId,
                        qty: qty,
                        available_for_dine_in: dishObj ? (dishObj.available_for_dine_in ?? true) : true,
                        available_for_takeaway: dishObj ? (dishObj.available_for_takeaway ?? true) : true,
                        available_for_delivery: dishObj ? (dishObj.available_for_delivery ?? true) : true,
                        is_time_available: dishObj ? (dishObj.is_time_available ?? true) : true,
                        schedule_summary: dishObj ? (dishObj.schedule_summary || '') : '',
                    });
                }
                this.saveCartToStorage();
                const addedText = translations.added_to_cart || 'ավելացվեց զամբյուղում';
                const varLabel = (variationName && variationName !== 'Standard' && variationName !== 'Standard Portion') ? ' (' + variationName + ')' : '';
                this.triggerToast('«' + name + varLabel + '» ' + addedText, 'success', 'fa-solid fa-circle-check');
                this.cartBump = true;
                setTimeout(() => { this.cartBump = false; }, 600);
            },

            changeQty(idx, delta) {
                const item = this.cart[idx];
                if (!item) return;
                this.cart[idx].qty += delta;
                if (this.cart[idx].qty <= 0) {
                    const removedName = item.name + (item.variation_name && item.variation_name !== 'Standard' && item.variation_name !== 'Standard Portion' ? ' (' + item.variation_name + ')' : '');
                    const removedText = translations.removed_from_cart || 'հեռացվեց զամբյուղից';
                    this.cart.splice(idx, 1);
                    this.triggerToast('«' + removedName + '» ' + removedText, 'remove', 'fa-solid fa-trash-can');
                }
                this.saveCartToStorage();
            },

            get cartTotalCount() {
                return this.cart.reduce((a, b) => a + b.qty, 0);
            },

            get cartSubtotal() {
                return this.cart.reduce((a, b) => a + (b.price * b.qty), 0);
            },

            get cartTotalPrice() {
                return this.cartSubtotal;
            },

            formatCurrency(val) {
                const num = Number(val || 0);
                const curr = (!this.currency || this.currency === 'AMD') ? '֏' : this.currency;
                return num.toLocaleString() + ' ' + curr;
            },

            getCartItemQty(dishId) {
                if (!this.cart || !this.cart.length) return 0;
                return this.cart
                    .filter(c => Number(c.id) === Number(dishId) || Number(c.product_id) === Number(dishId))
                    .reduce((sum, item) => sum + (Number(item.qty) || 0), 0);
            },

            get calculatedServiceFee() {
                if (this.orderType !== 'dine_in' || !this.serviceFeeEnabled) {
                    return 0;
                }
                const sub = this.cartSubtotal;
                if (this.serviceFeeMinOrder > 0 && sub < this.serviceFeeMinOrder) {
                    return 0;
                }
                if (this.serviceFeeType === 'percent') {
                    return Math.round((sub * this.serviceFeeValue) / 100);
                }
                return this.serviceFeeValue;
            },

            get calculatedDeliveryFee() {
                if (this.orderType !== 'delivery' || !this.deliveryEnabled) {
                    return 0;
                }
                const sub = this.cartSubtotal;
                if (this.deliveryFreeFrom !== null && this.deliveryFreeFrom > 0 && sub >= this.deliveryFreeFrom) {
                    return 0;
                }
                return this.deliveryFee;
            },

            get isDeliveryFree() {
                return this.orderType === 'delivery' && this.deliveryFreeFrom !== null && this.deliveryFreeFrom > 0 && this.cartSubtotal >= this.deliveryFreeFrom;
            },

            get freeDeliveryRemaining() {
                if (this.deliveryFreeFrom === null || this.deliveryFreeFrom <= 0) return 0;
                return Math.max(0, this.deliveryFreeFrom - this.cartSubtotal);
            },

            get freeDeliveryProgress() {
                if (this.deliveryFreeFrom === null || this.deliveryFreeFrom <= 0) return 100;
                if (this.cartSubtotal >= this.deliveryFreeFrom) return 100;
                return Math.min(100, Math.round((this.cartSubtotal / this.deliveryFreeFrom) * 100));
            },

            get isBelowDeliveryMin() {
                return this.orderType === 'delivery' && this.deliveryMinAmount > 0 && this.cartSubtotal < this.deliveryMinAmount;
            },

            get deliveryMinRemaining() {
                if (this.deliveryMinAmount <= 0) return 0;
                return Math.max(0, this.deliveryMinAmount - this.cartSubtotal);
            },

            get isBelowTakeawayMin() {
                return this.orderType === 'takeaway' && this.takeawayMinAmount > 0 && this.cartSubtotal < this.takeawayMinAmount;
            },

            get takeawayMinRemaining() {
                if (this.takeawayMinAmount <= 0) return 0;
                return Math.max(0, this.takeawayMinAmount - this.cartSubtotal);
            },

            get currentSchedule() {
                if (!this.schedules) return null;
                return this.schedules[this.orderType] || null;
            },

            get isCurrentChannelOpen() {
                const sched = this.currentSchedule;
                if (!sched || !sched.enabled) return true;
                return !!sched.is_open;
            },

            get isCurrentChannelClosingSoon() {
                const notice = this.closingNotice;
                if (!notice || !notice.enabled || !notice.closing_soon) return false;
                return this.isCurrentChannelOpen && notice.channel === this.orderType;
            },

            get isBirthdayEligible() {
                if (!this.birthdayDiscountEnabled || !this.customerBirthdate) return false;
                try {
                    const bdate = new Date(this.customerBirthdate);
                    if (isNaN(bdate.getTime())) return false;
                    const today = new Date();
                    const thisYearBday = new Date(today.getFullYear(), bdate.getMonth(), bdate.getDate());
                    const diffTime = Math.abs(today - thisYearBday);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    return diffDays <= this.birthdayValidityDays;
                } catch(e) {
                    return false;
                }
            },

            get birthdayDiscountAmount() {
                if (!this.isBirthdayEligible) return 0;
                return Math.round((this.cartSubtotal * this.birthdayDiscountPercent) / 100);
            },

            get isTipsApplicable() {
                if (!this.tipsRatingsSettings || !this.tipsRatingsSettings.tips_enabled) return false;
                const types = this.tipsRatingsSettings.applicable_order_types || ['dine_in'];
                if (!types.includes(this.orderType)) return false;
                if (this.paymentMethod === 'cash' && !this.tipsRatingsSettings.allow_cash_tips) return false;
                return true;
            },

            get tipPercentage() {
                return this.selectedTipType === 'percentage' ? Number(this.selectedTipValue || 0) : 0;
            },

            get tipAmount() {
                if (!this.isTipsApplicable || this.selectedTipType === 'none') {
                    return 0;
                }
                let base = this.cartSubtotal;
                if (this.tipsRatingsSettings.calculation_base === 'after_discount') {
                    base = Math.max(0, this.cartSubtotal - this.birthdayDiscountAmount);
                } else if (this.tipsRatingsSettings.calculation_base === 'total_with_fees') {
                    base = Math.max(0, this.cartSubtotal - this.birthdayDiscountAmount + this.calculatedServiceFee + this.calculatedDeliveryFee);
                }

                let amount = 0;
                if (this.selectedTipType === 'percentage') {
                    amount = Math.round((base * Number(this.selectedTipValue || 0)) / 100);
                } else if (this.selectedTipType === 'fixed') {
                    amount = Number(this.selectedTipValue || 0);
                } else if (this.selectedTipType === 'custom') {
                    amount = Math.max(0, Number(this.customTipInput || 0));
                }

                if (amount > 0) {
                    const min = Number(this.tipsRatingsSettings.min_tip_amount || 0);
                    const max = Number(this.tipsRatingsSettings.max_tip_amount || 50000);
                    if (min > 0 && amount < min) amount = min;
                    if (max > 0 && amount > max) amount = max;
                }

                return Math.round(amount);
            },

            get cartFinalTotal() {
                const rawTotal = this.cartSubtotal + this.calculatedServiceFee + this.calculatedDeliveryFee - this.birthdayDiscountAmount + this.tipAmount;
                return Math.max(0, rawTotal);
            },

            get cartRecommendations() {
                if (!this.cart || this.cart.length === 0 || !this.catalogProducts || this.catalogProducts.length === 0) {
                    return [];
                }

                const inCartIds = new Set(this.cart.map(item => Number(item.id || item.product_id)));
                const cartText = this.cart.map(i => (i.name || '').toLowerCase()).join(' ');

                const hasFriesOrPotato = /fri|fries|ֆրի|картоф|potato|chips|տապակած/.test(cartText);
                const hasBurgerOrPizza = /burger|բուրգեր|бургер|pizza|պիցցա|пицца|sandwich|սենդվիչ|shawarma|շաուրմա|wrap|hotdog|նրբերշիկ/.test(cartText);
                const hasCoffeeOrTea = /coffee|սուրճ|кофе|tea|թեյ|чай|espresso|էսպրեսո|cappuccino|կապուչինո|latte|լատե/.test(cartText);
                const hasMeatOrSteak = /steak|սթեյք|стейк|kebab|քյաբաբ|խորոված|шашлык|meat|միս|beef|տավար|chicken|հավ|pork|խոզ/.test(cartText);
                const hasDrinks = /cola|կոլա|pepsi|պեպսի|fanta|ֆանտա|sprite|սպրայտ|juice|հյութ|сок|water|ջուր|вода|beer|գարեջուր|пиво|wine|գինի|вино|lemonade|լիմոնադ|կոկտեյլ|cocktail/.test(cartText);
                const hasSauce = /ketchup|կետչուպ|кетчуп|sauce|սոուս|соус|mayo|մայոնեզ|barbecue|bbq|garlic|սխտոր|dip|դիպ/.test(cartText);

                const recommendations = [];
                const addedRecIds = new Set();

                const findCandidates = (regex, reasonText, maxCount = 2) => {
                    let count = 0;
                    for (const prod of this.catalogProducts) {
                        if (inCartIds.has(Number(prod.id)) || addedRecIds.has(Number(prod.id))) continue;
                        const matchText = (prod.name_lower || '') + ' ' + (prod.category_name || '');
                        if (regex.test(matchText)) {
                            recommendations.push({
                                ...prod,
                                reason: reasonText
                            });
                            addedRecIds.add(Number(prod.id));
                            count++;
                            if (count >= maxCount) break;
                        }
                    }
                };

                if (hasFriesOrPotato && !hasSauce) {
                    findCandidates(/ketchup|կետչուպ|кетчуп|sauce|սոուս|соус|mayo|մայոնեզ|bbq|barbecue|cheese|պանրային|սխտոր|garlic/i, translations.rec_reason_sauce || '🍟 Կատարյալ սոուս ֆրիի հետ', 2);
                }
                if ((hasFriesOrPotato || hasBurgerOrPizza || hasMeatOrSteak) && !hasDrinks) {
                    findCandidates(/cola|կոլա|pepsi|լիմոնադ|lemonade|beer|գարեջուր|пиво|drink|ըմպելիք|juice|հյութ/i, translations.rec_reason_refreshing_drink || '🥤 Համեղ զովացուցիչ ըմպելիք', 2);
                }
                if (hasBurgerOrPizza && !hasFriesOrPotato) {
                    findCandidates(/fri|fries|ֆրի|картоф|potato|rings|օղակներ|snack/i, translations.rec_reason_burger_side || '🍟 Հաճախ պատվիրում են բուրգերի հետ', 2);
                }
                if (hasCoffeeOrTea) {
                    findCandidates(/croissant|կրուասան|cookie|թխվածք|cake|տորթ|cheesecake|չիզքեյք|dessert|աղանդեր|բրաունի|brownie/i, translations.rec_reason_coffee_sweet || '☕ Քաղցր համադրություն սուրճի հետ', 2);
                }
                if (hasMeatOrSteak) {
                    findCandidates(/sauce|սոուս|соус|vegetable|բանջարեղեն|wine|գինի|гриль|grill/i, translations.rec_reason_meat_side || '🥩 Հիանալի լրացում մսային ուտեստին', 2);
                }
                if (recommendations.length < 3) {
                    findCandidates(/sauce|սոուս|соус|կետչուպ|ketchup|լիմոնադ|lemonade|juice|հյութ|cola|կոլա|dessert|աղանդեր/i, translations.rec_reason_popular_choice || '✨ Հաճախորդների սիրելի ընտրություն', 4 - recommendations.length);
                }
                if (recommendations.length < 2) {
                    for (const prod of this.catalogProducts) {
                        if (inCartIds.has(Number(prod.id)) || addedRecIds.has(Number(prod.id))) continue;
                        recommendations.push({
                            ...prod,
                            reason: translations.rec_reason_chef_addon || '✨ Առաջարկվող համեղ հավելում'
                        });
                        addedRecIds.add(Number(prod.id));
                        if (recommendations.length >= 4) break;
                    }
                }

                return recommendations.slice(0, 5);
            },

            quickAddRec(rec) {
                if (!rec.variations || rec.variations.length <= 1) {
                    const v = (rec.variations && rec.variations.length === 1) ? rec.variations[0] : null;
                    this.addToCart(
                        rec.id,
                        rec.name,
                        v ? Number(v.price) : Number(rec.price),
                        v ? v.name : 'Standard',
                        v ? v.id : null,
                        1
                    );
                } else {
                    this.selectDish(rec);
                }
                const addedMsg = (translations.item_added_to_cart_toast || '✨ :name ավելացվեց զամբյուղում').replace(':name', rec.name);
                this.triggerToast(addedMsg, 'success', 'fa-solid fa-cart-plus');
            },

            async submitOrder(channel) {
                if (this.isSubmittingOrder) return;
                if (this.cart.length === 0) return;

                if (!this.isCurrentChannelOpen) {
                    if (this.currentSchedule && this.currentSchedule.notice_message) {
                        this.triggerToast(this.currentSchedule.notice_message, 'remove', 'fa-solid fa-clock');
                    } else {
                        const channelName = this.orderType === 'delivery' 
                            ? (translations.delivery_service || 'Առաքման ծառայությունը') 
                            : (this.orderType === 'takeaway' ? (translations.takeaway_service || 'Տանելու պատվերների ծառայությունը') : (translations.kitchen_service || 'Խոհանոցը'));
                        const closedMsg = (translations.orders_closed_toast || ':channel այս պահին փակ է: Պատվերներ չեն ընդունվում:').replace(':channel', channelName);
                        this.triggerToast(closedMsg, 'remove', 'fa-solid fa-clock');
                    }
                    return;
                }

                if (this.orderType === 'dine_in') {
                    if (!this.isTableFixed && (!this.tableNumber || !this.tableNumber.trim())) {
                        this.triggerToast(translations.dine_in_requires_table_qr || 'Խնդրում ենք նշել սեղանի համարը', 'remove', 'fa-solid fa-qrcode');
                        return;
                    }
                }
                if (this.orderType === 'takeaway') {
                    if (!this.takeawayEnabled) {
                        this.triggerToast(translations.takeaway_disabled_notice || 'Առհանման պատվերները անջատված են', 'remove', 'fa-solid fa-ban');
                        return;
                    }
                    if (this.isBelowTakeawayMin) {
                        this.triggerToast((translations.min_takeaway_order_warning || 'Նվազագույն գումար՝') + ' ' + Number(this.takeawayMinAmount).toLocaleString() + ' ' + this.currency, 'remove', 'fa-solid fa-triangle-exclamation');
                        return;
                    }
                    if (!this.customerPhone || !this.customerPhone.trim()) {
                        this.phoneErrorMsg = translations.takeaway_phone_required || 'Հեռախոսահամարը պարտադիր է';
                        this.triggerToast(this.phoneErrorMsg, 'remove', 'fa-solid fa-phone');
                        const el = document.getElementById('cartCustomerPhoneInput');
                        if (el) el.focus();
                        return;
                    }
                }
                if (this.orderType === 'delivery') {
                    if (!this.deliveryEnabled) {
                        this.triggerToast(translations.delivery_disabled_notice || 'Առաքման պատվերները անջատված են', 'remove', 'fa-solid fa-ban');
                        return;
                    }
                    if (this.isBelowDeliveryMin) {
                        this.triggerToast((translations.min_delivery_order_warning || 'Նվազագույն գումար՝') + ' ' + Number(this.deliveryMinAmount).toLocaleString() + ' ' + this.currency, 'remove', 'fa-solid fa-triangle-exclamation');
                        return;
                    }
                    if (!this.deliveryAddress || !this.deliveryAddress.trim()) {
                        this.triggerToast(translations.delivery_address_required || 'Առաքման հասցեն պարտադիր է', 'remove', 'fa-solid fa-circle-exclamation');
                        return;
                    }
                    if (!this.customerPhone || !this.customerPhone.trim()) {
                        this.phoneErrorMsg = translations.delivery_phone_required || 'Հեռախոսահամարը պարտադիր է';
                        this.triggerToast(this.phoneErrorMsg, 'remove', 'fa-solid fa-phone');
                        const el = document.getElementById('cartCustomerPhoneInput');
                        if (el) el.focus();
                        return;
                    }
                }

                // Strict Phone Digits Validation
                const phoneDigits = this.phoneCleanDigits;
                if (phoneDigits && !this.isPhoneValid()) {
                    const country = this.selectedPhoneCountry;
                    let msg = translations.invalid_phone_format || 'Խնդրում ենք մուտքագրել ճիշտ հեռախոսահամար';
                    if (country.digits && phoneDigits.length < country.digits) {
                        msg = (translations.phone_missing_digits || 'Պակասում է :count նիշ').replace(':count', country.digits - phoneDigits.length) + ` (${phoneDigits.length}/${country.digits})`;
                    } else if (country.digits && phoneDigits.length > country.digits) {
                        msg = (translations.phone_exact_digits || 'Հեռախոսահամարը պետք է ունենա ճիշտ :digits նիշ').replace(':digits', country.digits);
                    }
                    this.phoneErrorMsg = msg;
                    this.triggerToast(msg, 'remove', 'fa-solid fa-phone-slash');
                    const el = document.getElementById('cartCustomerPhoneInput');
                    if (el) el.focus();
                    return;
                }

                // Strict Email Format Validation
                if (this.customerEmail && !this.isEmailValid()) {
                    const msg = translations.invalid_email_format || 'Խնդրում ենք մուտքագրել վավեր էլ․ հասցե (օրինակ՝ name@example.com)';
                    this.emailErrorMsg = msg;
                    this.triggerToast(msg, 'remove', 'fa-solid fa-envelope-circle-check');
                    const el = document.getElementById('cartCustomerEmailInput');
                    if (el) el.focus();
                    return;
                }

                // Strict Channel Restrictions Validation
                for (const item of this.cart) {
                    if (this.orderType === 'dine_in' && item.available_for_dine_in === false) {
                        this.triggerToast(`«${item.name}» - ` + (translations.item_unavailable_for_channel || 'Հասանելի չէ սրահում պատվիրելու համար'), 'remove', 'fa-solid fa-ban');
                        return;
                    }
                    if (this.orderType === 'takeaway' && item.available_for_takeaway === false) {
                        this.triggerToast(`«${item.name}» - ` + (translations.item_unavailable_for_channel || 'Հասանելի չէ տանելու պատվերի համար'), 'remove', 'fa-solid fa-ban');
                        return;
                    }
                    if (this.orderType === 'delivery' && item.available_for_delivery === false) {
                        this.triggerToast(`«${item.name}» - ` + (translations.item_unavailable_for_channel || 'Հասանելի չէ առաքման համար'), 'remove', 'fa-solid fa-ban');
                        return;
                    }
                }

                if (!this.currentOrderIdempotencyKey) {
                    this.currentOrderIdempotencyKey = 'ord_' + Date.now() + '_' + Math.random().toString(36).substring(2, 10);
                }
                const idempotencyKey = this.currentOrderIdempotencyKey;
                this.isSubmittingOrder = true;

                try {
                    const submitUrl = routes.submitOrder || `/api/m/${this.vendorSlug}/order`;
                    const res = await fetch(submitUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'X-Idempotency-Key': idempotencyKey
                        },
                        body: JSON.stringify({
                            idempotency_key: idempotencyKey,
                            location_id: this.locationId,
                            table_number: (this.orderType === 'delivery' || this.orderType === 'takeaway') ? null : (this.tableNumber || null),
                            delivery_address: this.orderType === 'delivery' ? this.deliveryAddress.trim() : null,
                            type: this.orderType,
                            customer_name: this.customerName || 'Guest',
                            customer_phone: this.customerPhone || null,
                            customer_email: this.customerEmail || null,
                            customer_birthdate: this.customerBirthdate || null,
                            payment_method: this.paymentMethod || 'cash',
                            marketing_opt_in: this.marketingOptIn,
                            notes: this.orderNotes,
                            tip_amount: this.tipAmount,
                            tip_type: this.selectedTipType,
                            tip_value: this.selectedTipValue,
                            waiter_id: this.selectedWaiterId || null,
                            active_order_number: (this.activeOrder && !['completed', 'cancelled'].includes(this.activeOrder.status)) ? this.activeOrder.order_number : null,
                            items: this.cart.map(c => ({
                                product_id: c.id || c.product_id,
                                variation_id: c.variation_id || null,
                                variation_name: c.variation_name || null,
                                quantity: c.qty
                            }))
                        })
                    });
                    const data = await res.json().catch(() => ({}));
                    if (res.ok && data.success) {
                        if (data.payment_redirect_url) {
                            this.cart = [];
                            this.clearStoredCart();
                            this.saveActiveOrderToStorage();
                            window.location.href = data.payment_redirect_url;
                            return;
                        }

                        const isAppended = !!data.is_appended;
                        const orderItems = (data.items && data.items.length > 0)
                            ? data.items
                            : this.cart.map(c => ({
                                name: c.name,
                                variation_name: c.variation_name,
                                quantity: c.qty,
                                price: c.price,
                                subtotal: c.price * c.qty
                            }));

                        if (isAppended && this.activeOrder) {
                            this.activeOrder = {
                                ...this.activeOrder,
                                id: data.order_id || this.activeOrder.id,
                                tracking_token: data.tracking_token || this.activeOrder.tracking_token,
                                order_number: data.order_number,
                                type: data.type || this.activeOrder.type || this.orderType,
                                order_type: data.type || this.activeOrder.order_type || this.orderType,
                                status: data.status || this.activeOrder.status,
                                status_label: data.status_label || this.activeOrder.status_label,
                                subtotal: data.subtotal !== undefined ? Number(data.subtotal) : this.activeOrder.subtotal,
                                service_fee: data.service_fee !== undefined ? Number(data.service_fee) : this.activeOrder.service_fee,
                                delivery_fee: data.delivery_fee !== undefined ? Number(data.delivery_fee) : this.activeOrder.delivery_fee,
                                total_amount: data.total_amount,
                                items: orderItems
                            };
                        } else {
                            this.activeOrder = {
                                id: data.order_id || null,
                                tracking_token: data.tracking_token || null,
                                order_number: data.order_number,
                                status: data.status || 'pending',
                                status_step: 1,
                                status_percent: 25,
                                status_label: data.status_label || translations.status_pending || 'Ընդունված է',
                                status_desc: translations.status_desc_pending || 'Պատվերը սպասում է հաստատման',
                                status_icon: 'fa-solid fa-clock',
                                subtotal: Number(data.subtotal ?? (this.cartSubtotal || 0)),
                                service_fee: Number(data.service_fee ?? (this.calculatedServiceFee || 0)),
                                delivery_fee: Number(data.delivery_fee ?? (this.calculatedDeliveryFee || 0)),
                                total_amount: data.total_amount,
                                type: data.type || this.orderType,
                                order_type: data.type || this.orderType,
                                delivery_address: this.orderType === 'delivery' ? this.deliveryAddress : null,
                                table_number: this.orderType === 'delivery' ? (translations.delivery || 'Առաքում') : (this.orderType === 'takeaway' ? (translations.takeaway || 'Առհանում') : (this.tableNumber || 'Սեղան')),
                                currency: this.currency,
                                created_at_time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                                created_at_human: translations.just_now || 'հենց նոր',
                                items: orderItems
                            };
                        }

                        this.currentOrderIdempotencyKey = null;
                        this.hideTrackerPill = false;
                        this.saveActiveOrderToStorage();
                        this.cart = [];
                        this.clearStoredCart();
                        this.orderNotes = '';
                        this.showCartModal = false;
                        this.startOrderPolling();

                        if (data.whatsapp_url && channel === 'whatsapp') {
                            window.location.href = data.whatsapp_url;
                        } else {
                            this.showOrderTracker = true;
                            if (isAppended) {
                                this.triggerToast((translations.items_appended_toast || 'Ավելացվեց պատվերին') + ' (' + data.order_number + ')', 'success', 'fa-solid fa-circle-plus');
                            } else {
                                const successMsg = (translations.order_submitted_prefix || 'Պատվեր #') + data.order_number + (translations.order_submitted_suffix || ' գրանցված է');
                                this.triggerToast(successMsg, 'success', 'fa-solid fa-circle-check');
                            }
                        }
                    } else {
                        const errMsg = data.message || (data.errors ? Object.values(data.errors)[0][0] : 'Սխալ պատվերն ուղարկելիս');
                        this.triggerToast(errMsg, 'remove', 'fa-solid fa-circle-xmark');
                    }
                } catch (e) {
                    console.error('Order submit error:', e);
                    this.triggerToast(translations.network_error || 'Ցանցային սխալ', 'remove', 'fa-solid fa-triangle-exclamation');
                } finally {
                    this.isSubmittingOrder = false;
                }
            },

            goToMenu() {
                this.currentTab = 'menu';
                this.showCartModal = false;
                this.showInfoModal = false;
                this.showOrderTracker = false;
                this.showRatingModal = false;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },

            setTip(type, value) {
                this.selectedTipType = type;
                this.selectedTipValue = value;
                if (type === 'custom') {
                    this.customTipInput = value;
                }
            },

            openRatingModal(orderNumber = null, waiterId = null) {
                this.ratingOrderNumber = orderNumber || (this.activeOrder ? this.activeOrder.order_number : null);
                this.ratingWaiterId = waiterId || (this.activeOrder ? this.activeOrder.waiter_id : null) || this.selectedWaiterId || null;
                this.ratingScore = Number(this.tipsRatingsSettings.rating_scale || 5);
                this.ratingCategories = [];
                this.ratingComment = '';
                this.ratingIsAnonymous = false;
                this.ratingSubmitting = false;
                this.ratingSubmitted = false;
                this.ratingErrorMessage = '';
                this.showRatingModal = true;
            },

            toggleRatingCategory(cat) {
                if (this.ratingCategories.includes(cat)) {
                    this.ratingCategories = this.ratingCategories.filter(c => c !== cat);
                } else {
                    this.ratingCategories.push(cat);
                }
            },

            formatCategoryName(cat) {
                const map = {
                    food_quality: translations.rating_cat_food_quality || 'Համեղ ուտեստներ',
                    service_speed: translations.rating_cat_service_speed || 'Արագ սպասարկում',
                    waiter_politeness: translations.rating_cat_waiter_politeness || 'Բարեհամբույր մատուցող',
                    cleanliness: translations.rating_cat_cleanliness || 'Մաքրություն',
                    ambiance: translations.rating_cat_ambiance || 'Հաճելի մթնոլորտ',
                    table_presentation: translations.rating_cat_table_presentation || 'Գեղեցիկ մատուցում'
                };
                return map[cat] || (cat.charAt(0).toUpperCase() + cat.slice(1).replace(/_/g, ' '));
            },

            async submitRatingForm() {
                if (this.ratingSubmitting) return;

                const isOptional = this.tipsRatingsSettings.is_written_feedback_optional !== false;
                if (!isOptional && (!this.ratingComment || !this.ratingComment.trim())) {
                    this.ratingErrorMessage = translations.rating_comment_required || 'Մեկնաբանությունը պարտադիր է:';
                    return;
                }

                this.ratingSubmitting = true;
                this.ratingErrorMessage = '';

                try {
                    const ratingUrl = (bootConfig.routes && bootConfig.routes.submitRating) || `/api/m/${this.vendorSlug}/rate`;
                    const res = await fetch(ratingUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken
                        },
                        body: JSON.stringify({
                            order_number: this.ratingOrderNumber || null,
                            table_number: this.tableNumber || (this.activeOrder ? this.activeOrder.table_number : null) || null,
                            waiter_id: this.ratingWaiterId || null,
                            rating: this.ratingScore,
                            categories: this.ratingCategories,
                            comment: this.ratingComment ? this.ratingComment.trim() : null,
                            is_anonymous: this.ratingIsAnonymous,
                            customer_name: this.ratingIsAnonymous ? null : (this.customerName || (this.activeOrder ? this.activeOrder.customer_name : null))
                        })
                    });

                    const data = await res.json().catch(() => ({}));
                    if (res.ok && data.success) {
                        this.ratingSubmitted = true;
                        this.triggerToast(translations.rating_thank_you_toast || 'Շնորհակալություն գնահատականի համար!', 'add', 'fa-solid fa-star');
                    } else {
                        this.ratingErrorMessage = data.message || translations.rating_submit_failed || 'Չհաջողվեց գրանցել գնահատականը:';
                    }
                } catch (e) {
                    this.ratingErrorMessage = translations.connection_error_retry || 'Կապի խնդիր: Խնդրում ենք փորձել կրկին:';
                } finally {
                    this.ratingSubmitting = false;
                }
            },

            scrollToTop() {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },

            copyWifiPassword(password) {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(password).then(() => {
                        this.wifiCopied = true;
                        this.triggerToast(translations.wifi_copied || 'WiFi գաղտնաբառը պատճենվեց', 'success', 'fa-solid fa-check');
                        setTimeout(() => { this.wifiCopied = false; }, 2500);
                    }).catch(() => {
                        this.fallbackCopy(password);
                    });
                } else {
                    this.fallbackCopy(password);
                }
            },

            fallbackCopy(text) {
                const ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                try {
                    document.execCommand('copy');
                    this.wifiCopied = true;
                    this.triggerToast(translations.wifi_copied || 'WiFi գաղտնաբառը պատճենվեց', 'success', 'fa-solid fa-check');
                    setTimeout(() => { this.wifiCopied = false; }, 2500);
                } catch(e) {}
                document.body.removeChild(ta);
            },

            saveCartToStorage() {
                try {
                    const key = 'storefront_cart_' + (this.vendorSlug || 'default');
                    const data = {
                        cart: (this.cart || []).map(item => ({
                            id: Number(item.id || item.product_id),
                            product_id: Number(item.id || item.product_id),
                            name: item.name,
                            price: Number(item.price),
                            variation_name: item.variation_name || null,
                            variation_id: item.variation_id ? Number(item.variation_id) : null,
                            qty: Number(item.qty || 1),
                            available_for_dine_in: item.available_for_dine_in !== false,
                            available_for_takeaway: item.available_for_takeaway !== false,
                            available_for_delivery: item.available_for_delivery !== false,
                            is_time_available: item.is_time_available !== false,
                            schedule_summary: item.schedule_summary || '',
                        })),
                        customerName: this.customerName || '',
                        customerPhone: this.customerPhone || '',
                        phoneNationalNumber: this.phoneNationalNumber || '',
                        phoneCountryCode: this.phoneCountryCode || 'AM',
                        customerEmail: this.customerEmail || '',
                        customerBirthdate: this.customerBirthdate || '',
                        deliveryAddress: this.deliveryAddress || '',
                        orderNotes: this.orderNotes || '',
                        orderType: this.orderType || null,
                        savedAt: Date.now()
                    };
                    localStorage.setItem(key, JSON.stringify(data));
                } catch(e) {
                    console.debug('Failed to save cart to storage:', e);
                }
            },

            clearStoredCart() {
                try {
                    const key = 'storefront_cart_' + (this.vendorSlug || 'default');
                    localStorage.removeItem(key);
                } catch(e) {}
            },

            initPreOrderState() {
                try {
                    const draftStr = localStorage.getItem('reservation_draft_' + this.vendorSlug);
                    if (draftStr) {
                        const draft = JSON.parse(draftStr);
                        const parts = [];
                        if (draft.selectedDate) parts.push(draft.selectedDate);
                        if (draft.selectedTime) parts.push(draft.selectedTime);
                        if (draft.guestCount) {
                            const guestsLabel = translations.guests || 'անձ';
                            parts.push(draft.guestCount + ' ' + guestsLabel);
                        }
                        if (parts.length > 0) {
                            this.preOrderSummaryText = parts.join(' • ');
                        }
                    }

                    // If cart is currently empty, check if preorder_items exists and restore into cart
                    if ((!this.cart || this.cart.length === 0)) {
                        const savedPreorder = localStorage.getItem('preorder_items_' + this.vendorSlug);
                        if (savedPreorder) {
                            const items = JSON.parse(savedPreorder);
                            if (Array.isArray(items) && items.length > 0) {
                                this.cart = items.map(i => ({
                                    id: Number(i.product_id || i.id),
                                    product_id: Number(i.product_id || i.id),
                                    name: i.name,
                                    price: Number(i.unit_price || i.price || 0),
                                    variation_name: i.variation_name || null,
                                    variation_id: i.variation_id ? Number(i.variation_id) : null,
                                    qty: Number(i.quantity || i.qty || 1),
                                    notes: i.notes || null,
                                    available_for_dine_in: true,
                                    available_for_takeaway: true,
                                    available_for_delivery: true,
                                    is_time_available: true
                                }));
                            }
                        }
                    }
                } catch(e) {
                    console.debug('Failed to init pre-order state:', e);
                }
            },

            confirmPreOrderAndReturn() {
                try {
                    const preorderItems = (this.cart || []).map(item => ({
                        product_id: Number(item.product_id || item.id),
                        name: item.name,
                        variation_name: item.variation_name || null,
                        variation_id: item.variation_id ? Number(item.variation_id) : null,
                        unit_price: Number(item.price || 0),
                        quantity: Number(item.qty || 1),
                        notes: item.notes || null
                    }));
                    localStorage.setItem('preorder_items_' + this.vendorSlug, JSON.stringify(preorderItems));
                    localStorage.removeItem('active_preorder_mode_' + this.vendorSlug);
                    const reserveUrl = routes.reserve || ('/m/' + this.vendorSlug + '/reserve');
                    window.location.href = reserveUrl;
                } catch(e) {
                    console.error('Failed to confirm preorder:', e);
                }
            },

            returnToReservation() {
                this.confirmPreOrderAndReturn();
            },

            startReservationWithCart() {
                try {
                    const preorderItems = (this.cart || []).map(item => ({
                        product_id: Number(item.product_id || item.id),
                        name: item.name,
                        variation_name: item.variation_name || null,
                        variation_id: item.variation_id ? Number(item.variation_id) : null,
                        unit_price: Number(item.price || 0),
                        quantity: Number(item.qty || 1),
                        notes: item.notes || null
                    }));
                    localStorage.setItem('preorder_items_' + this.vendorSlug, JSON.stringify(preorderItems));
                    localStorage.removeItem('active_preorder_mode_' + this.vendorSlug);
                    const reserveUrl = routes.reserve || ('/m/' + this.vendorSlug + '/reserve');
                    window.location.href = reserveUrl;
                } catch(e) {
                    console.error('Failed to start reservation with cart:', e);
                }
            },

            goToReservationPage() {
                if (this.cart && this.cart.length > 0) {
                    this.startReservationWithCart();
                } else {
                    const reserveUrl = routes.reserve || ('/m/' + this.vendorSlug + '/reserve');
                    window.location.href = reserveUrl;
                }
            },

            restoreCartFromStorage() {
                try {
                    const key = 'storefront_cart_' + (this.vendorSlug || 'default');
                    const saved = localStorage.getItem(key);
                    if (!saved) return;
                    const parsed = JSON.parse(saved);
                    if (!parsed) return;

                    // Expire cart after 24 hours
                    if (parsed.savedAt && (Date.now() - parsed.savedAt > 24 * 60 * 60 * 1000)) {
                        localStorage.removeItem(key);
                        return;
                    }

                    if (parsed.customerName && !this.customerName) {
                        this.customerName = parsed.customerName;
                    }
                    if (parsed.phoneCountryCode) {
                        this.phoneCountryCode = parsed.phoneCountryCode;
                    }
                    if (parsed.phoneNationalNumber && !this.phoneNationalNumber) {
                        this.phoneNationalNumber = parsed.phoneNationalNumber;
                        if (typeof this.onPhoneInput === 'function') {
                            this.onPhoneInput();
                        }
                    } else if (parsed.customerPhone && !this.customerPhone) {
                        this.customerPhone = parsed.customerPhone;
                        this.phoneNationalNumber = parsed.customerPhone;
                        if (typeof this.onPhoneInput === 'function') {
                            this.onPhoneInput();
                        }
                    }
                    if (parsed.customerEmail && !this.customerEmail) {
                        this.customerEmail = parsed.customerEmail;
                    }
                    if (parsed.customerBirthdate && !this.customerBirthdate) {
                        this.customerBirthdate = parsed.customerBirthdate;
                    }
                    if (parsed.deliveryAddress && !this.deliveryAddress) {
                        this.deliveryAddress = parsed.deliveryAddress;
                    }
                    if (parsed.orderNotes && !this.orderNotes) {
                        this.orderNotes = parsed.orderNotes;
                    }

                    if (!this.isTableFixed && parsed.orderType && (parsed.orderType === 'delivery' || parsed.orderType === 'takeaway')) {
                        if ((parsed.orderType === 'delivery' && this.deliveryEnabled) || (parsed.orderType === 'takeaway' && this.takeawayEnabled)) {
                            this.orderType = parsed.orderType;
                            this.showOrderTypeModal = false;
                        }
                    }

                    if (Array.isArray(parsed.cart) && parsed.cart.length > 0) {
                        const restoredItems = [];
                        for (const item of parsed.cart) {
                            const prodId = Number(item.id || item.product_id);
                            const catalogProduct = (this.catalogProducts || []).find(p => Number(p.id) === prodId);

                            if (catalogProduct) {
                                let variationName = item.variation_name;
                                let price = Number(item.price);

                                if (item.variation_id && catalogProduct.variations && catalogProduct.variations.length > 0) {
                                    const v = catalogProduct.variations.find(v => Number(v.id) === Number(item.variation_id));
                                    if (v) {
                                        variationName = v.name;
                                        price = Number(v.price);
                                    }
                                } else if (!item.variation_id && catalogProduct.price !== undefined) {
                                    price = Number(catalogProduct.price);
                                }

                                restoredItems.push({
                                    id: prodId,
                                    product_id: prodId,
                                    name: catalogProduct.name,
                                    price: price,
                                    variation_name: variationName,
                                    variation_id: item.variation_id ? Number(item.variation_id) : null,
                                    qty: Number(item.qty || 1),
                                    available_for_dine_in: catalogProduct.available_for_dine_in !== false,
                                    available_for_takeaway: catalogProduct.available_for_takeaway !== false,
                                    available_for_delivery: catalogProduct.available_for_delivery !== false,
                                    is_time_available: catalogProduct.is_time_available !== false,
                                    schedule_summary: catalogProduct.schedule_summary || '',
                                });
                            } else {
                                restoredItems.push({
                                    ...item,
                                    id: prodId,
                                    product_id: prodId,
                                    price: Number(item.price || 0),
                                    qty: Number(item.qty || 1)
                                });
                            }
                        }

                        this.cart = restoredItems;
                    }
                } catch(e) {
                    console.error('Failed to restore cart from storage:', e);
                }
            },

            saveActiveOrderToStorage() {
                if (this.activeOrder) {
                    try {
                        localStorage.setItem('active_order_' + this.vendorSlug, JSON.stringify({
                            ...this.activeOrder,
                            savedAt: Date.now()
                        }));
                    } catch(e) {}
                }
            },

            isOrderPillVisible() {
                return !!(this.activeOrder && !this.showOrderTracker && (!['completed', 'cancelled'].includes(this.activeOrder.status) || !this.hideTrackerPill));
            },

            getActiveOrderSubtotal() {
                if (!this.activeOrder) return 0;
                if (this.activeOrder.subtotal !== undefined && Number(this.activeOrder.subtotal) > 0) {
                    return Number(this.activeOrder.subtotal);
                }
                if (this.activeOrder.items && this.activeOrder.items.length > 0) {
                    return this.activeOrder.items.reduce((sum, item) => sum + Number(item.subtotal || (item.price * item.quantity)), 0);
                }
                return Number(this.activeOrder.total_amount || 0);
            },

            getActiveOrderServiceFee() {
                if (!this.activeOrder) return 0;
                if (this.activeOrder.service_fee !== undefined && Number(this.activeOrder.service_fee) > 0) {
                    return Number(this.activeOrder.service_fee);
                }
                const orderType = this.activeOrder.type || this.activeOrder.order_type;
                if (orderType !== 'delivery' && this.activeOrder.items && this.activeOrder.total_amount) {
                    const itemsSum = this.activeOrder.items.reduce((sum, item) => sum + Number(item.subtotal || (item.price * item.quantity)), 0);
                    if (Number(this.activeOrder.total_amount) > itemsSum) {
                        return Number(this.activeOrder.total_amount) - itemsSum;
                    }
                }
                return 0;
            },

            getActiveOrderDeliveryFee() {
                if (!this.activeOrder) return 0;
                if (this.activeOrder.delivery_fee !== undefined && Number(this.activeOrder.delivery_fee) > 0) {
                    return Number(this.activeOrder.delivery_fee);
                }
                const orderType = this.activeOrder.type || this.activeOrder.order_type;
                if (orderType === 'delivery') {
                    if (this.activeOrder.delivery_fee !== undefined) {
                        return Number(this.activeOrder.delivery_fee);
                    }
                    if (this.activeOrder.items && this.activeOrder.total_amount) {
                        const itemsSum = this.activeOrder.items.reduce((sum, item) => sum + Number(item.subtotal || (item.price * item.quantity)), 0);
                        if (Number(this.activeOrder.total_amount) > itemsSum) {
                            return Number(this.activeOrder.total_amount) - itemsSum;
                        }
                    }
                }
                return 0;
            },

            scheduleCompletedOrderDismissal() {
                if (this.orderDismissTimeout) {
                    clearTimeout(this.orderDismissTimeout);
                    this.orderDismissTimeout = null;
                }
                if (!this.activeOrder || !['completed', 'cancelled'].includes(this.activeOrder.status)) {
                    return;
                }

                let completedAt = this.activeOrder.completed_at;
                if (!completedAt) {
                    if (this.activeOrder.updated_at_timestamp) {
                        completedAt = this.activeOrder.updated_at_timestamp * 1000;
                    } else if (this.activeOrder.savedAt) {
                        completedAt = this.activeOrder.savedAt;
                    } else {
                        completedAt = Date.now();
                    }
                    this.activeOrder.completed_at = completedAt;
                    this.saveActiveOrderToStorage();
                }

                const timeoutMs = 60 * 1000;
                const elapsed = Date.now() - completedAt;
                const remaining = timeoutMs - elapsed;

                if (remaining <= 0) {
                    this.activeOrder = null;
                    this.showOrderTracker = false;
                    this.hideTrackerPill = true;
                    try {
                        localStorage.removeItem('active_order_' + this.vendorSlug);
                    } catch(e) {}
                } else {
                    this.orderDismissTimeout = setTimeout(() => {
                        this.activeOrder = null;
                        this.showOrderTracker = false;
                        this.hideTrackerPill = true;
                        try {
                            localStorage.removeItem('active_order_' + this.vendorSlug);
                        } catch(e) {}
                    }, remaining);
                }
            },

            restoreActiveOrderFromStorage() {
                try {
                    const saved = localStorage.getItem('active_order_' + this.vendorSlug);
                    if (saved) {
                        const parsed = JSON.parse(saved);
                        if (parsed && (parsed.order_number || parsed.tracking_token)) {
                            this.activeOrder = parsed;
                            if (['completed', 'cancelled'].includes(this.activeOrder.status)) {
                                this.scheduleCompletedOrderDismissal();
                            } else {
                                this.startOrderPolling();
                            }
                        }
                    }
                } catch(e) {}
            },

            startOrderPolling() {
                this.stopOrderPolling();
                this.pollOrderStatus();
                this.orderPollInterval = setInterval(() => {
                    this.pollOrderStatus();
                }, 5000);
            },

            stopOrderPolling() {
                if (this.orderPollInterval) {
                    clearInterval(this.orderPollInterval);
                    this.orderPollInterval = null;
                }
            },

            async pollOrderStatus() {
                if (!this.activeOrder || (!this.activeOrder.order_number && !this.activeOrder.tracking_token)) return;
                try {
                    const token = this.activeOrder.tracking_token || '';
                    const targetIdentifier = token || this.activeOrder.order_number;
                    let url = (routes.orderStatus || `/api/m/${this.vendorSlug}/order/___NUM___/status`).replace('___NUM___', encodeURIComponent(targetIdentifier));
                    if (token) {
                        url += (url.includes('?') ? '&' : '?') + 'token=' + encodeURIComponent(token);
                    }
                    const res = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            ...(token ? { 'X-Tracking-Token': token } : {})
                        }
                    });
                    if (res.ok) {
                        const data = await res.json();
                        if (data.success && data.order) {
                            const oldStatus = this.activeOrder.status;
                            const newStatus = data.order.status;

                            let completedAt = this.activeOrder.completed_at;
                            if (['completed', 'cancelled'].includes(newStatus)) {
                                if (!completedAt) {
                                completedAt = data.order.updated_at_timestamp ? (data.order.updated_at_timestamp * 1000) : Date.now();
                                }
                            }

                            this.activeOrder = {
                                ...this.activeOrder,
                                ...data.order,
                                completed_at: completedAt
                            };
                            this.saveActiveOrderToStorage();

                            if (oldStatus && oldStatus !== newStatus) {
                                this.triggerToast((translations.order_number_label || 'Պատվեր #') + data.order.order_number + ': ' + data.order.status_label, 'success', data.order.status_icon);
                                if (navigator.vibrate) {
                                    try { navigator.vibrate([100, 50, 100]); } catch(e) {}
                                }
                            }

                            if (['completed', 'cancelled'].includes(newStatus)) {
                                this.stopOrderPolling();
                                this.scheduleCompletedOrderDismissal();
                            }
                        }
                    } else if (res.status === 404) {
                        this.stopOrderPolling();
                        this.activeOrder = null;
                        try { localStorage.removeItem('active_order_' + this.vendorSlug); } catch(e) {}
                    }
                } catch (e) {
                    console.error('Tracker polling error:', e);
                }
            },

            dismissTrackerPill() {
                if (['completed', 'cancelled'].includes(this.activeOrder?.status)) {
                    this.hideTrackerPill = true;
                } else {
                    this.triggerToast((translations.order_number_label || 'Պատվեր #') + (this.activeOrder?.order_number || '') + ' - ' + (this.activeOrder?.status_label || ''), 'info', 'fa-solid fa-clock');
                }
            },

            closeOrderTracker(hidePill = false) {
                this.showOrderTracker = false;
                if (hidePill && ['completed', 'cancelled'].includes(this.activeOrder?.status)) {
                    this.hideTrackerPill = true;
                }
            },

            clearActiveOrder() {
                if (this.orderDismissTimeout) {
                    clearTimeout(this.orderDismissTimeout);
                    this.orderDismissTimeout = null;
                }
                this.stopOrderPolling();
                this.activeOrder = null;
                this.showOrderTracker = false;
                this.hideTrackerPill = true;
                try {
                    localStorage.removeItem('active_order_' + this.vendorSlug);
                } catch(e) {}
                this.triggerToast(translations.order_tracking_finished || 'Պատվերի հետևումն ավարտված է', 'info', 'fa-solid fa-circle-check');
            },

            openWaiterModal(type = 'call_waiter') {
                if (!this.isTableFixed) {
                    this.triggerToast(translations.call_waiter_table_required || 'Մատուցողի կանչը հասանելի է միայն սեղանի QR կոդը սկանավորելուց հետո', 'remove', 'fa-solid fa-qrcode');
                    return;
                }
                this.serviceType = type;
                if (this.tableNumber) {
                    this.serviceTable = this.tableNumber;
                }
                this.showWaiterModal = true;
            },

            async submitServiceCall() {
                if (!this.isTableFixed || !this.serviceTable || this.serviceTable.toString().trim() === '') {
                    this.triggerToast(translations.call_waiter_table_required || 'Մատուցողի կանչը հասանելի է միայն սեղանի QR կոդը սկանավորելուց հետո', 'remove', 'fa-solid fa-qrcode');
                    return;
                }

                this.isCallingService = true;

                try {
                    let formattedTable = this.serviceTable.toString().trim();
                    if (!formattedTable.toLowerCase().startsWith('table ') && !formattedTable.startsWith('Սեղան ')) {
                        formattedTable = 'Table ' + formattedTable;
                    }

                    const waiterUrl = routes.callWaiter || `/api/m/${this.vendorSlug}/call-waiter`;
                    const res = await fetch(waiterUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            location_id: this.locationId,
                            table_number: formattedTable,
                            type: this.serviceType
                        })
                    });

                    const data = await res.json();
                    if (data.success) {
                        this.showWaiterModal = false;
                        const icon = this.serviceType === 'call_waiter' ? 'fa-solid fa-bell' : (this.serviceType === 'bill_cash' ? 'fa-solid fa-money-bill-wave' : 'fa-solid fa-credit-card');
                        this.triggerToast(data.message, 'success', icon);
                    } else {
                        this.triggerToast(data.message || translations.error_try_again || 'Սխալ՝ կրկին փորձեք', 'remove', 'fa-solid fa-triangle-exclamation');
                    }
                } catch (e) {
                    console.error('Service call error:', e);
                    this.triggerToast(translations.connection_error_retry || 'Կապի խնդիր, խնդրում ենք կրկին փորձել', 'remove', 'fa-solid fa-triangle-exclamation');
                } finally {
                    this.isCallingService = false;
                }
            },

            quickCallWaiter() {
                if (this.serviceTable && this.serviceTable.toString().trim() !== '') {
                    this.serviceType = 'call_waiter';
                    this.submitServiceCall();
                } else {
                    this.openWaiterModal('call_waiter');
                }
            },

            quickRequestBill(payment = 'cash') {
                const targetType = payment === 'card' ? 'bill_card' : 'bill_cash';
                if (this.serviceTable && this.serviceTable.toString().trim() !== '') {
                    this.serviceType = targetType;
                    this.submitServiceCall();
                } else {
                    this.openWaiterModal(targetType);
                }
            },

            // AI Waiter Advisor & Storefront Language Methods
            t(key, replacements = {}, fallback = '') {
                let text = (this.i18n && this.i18n[key] !== undefined)
                    ? this.i18n[key]
                    : (translations && translations[key] !== undefined ? translations[key] : (fallback || key));
                if (text && typeof text === 'string' && replacements && typeof replacements === 'object') {
                    for (const [rKey, rVal] of Object.entries(replacements)) {
                        text = text.replace(new RegExp(':' + rKey, 'g'), rVal);
                    }
                }
                return text;
            },

            catName(catId, fallback = '') {
                const idStr = String(catId).replace('cat-', '');
                return this.catalogTranslations?.categories?.[idStr]?.name
                    || this.catalogTranslations?.categories?.[catId]?.name
                    || fallback;
            },

            dishName(productId, fallback = '') {
                const idStr = String(productId);
                return this.catalogTranslations?.products?.[idStr]?.name
                    || (this.catalogProducts?.find(p => String(p.id) === idStr)?.name)
                    || fallback;
            },

            dishDesc(productId, fallback = '') {
                const idStr = String(productId);
                return this.catalogTranslations?.products?.[idStr]?.description
                    || (this.catalogProducts?.find(p => String(p.id) === idStr)?.description)
                    || fallback;
            },

            dishScheduleText(productId, fallback = '') {
                const idStr = String(productId);
                return this.catalogTranslations?.products?.[idStr]?.schedule_summary
                    || fallback;
            },

            dishSecondaryPrice(productId, fallback = '') {
                const idStr = String(productId);
                return this.catalogTranslations?.products?.[idStr]?.secondary_price
                    || fallback;
            },

            variationName(productId, variationId, fallback = '') {
                const pId = String(productId);
                const vId = String(variationId);
                return this.catalogTranslations?.products?.[pId]?.variations?.[vId]?.name
                    || fallback;
            },

            getLocalizedDish(dish) {
                if (!dish || !dish.id) return dish;
                const pId = String(dish.id);
                const trans = this.catalogTranslations?.products?.[pId];
                if (!trans) return dish;
                const localized = { ...dish };
                if (trans.name) localized.name = trans.name;
                if (trans.description !== undefined) localized.description = trans.description;
                if (trans.schedule_summary !== undefined) localized.schedule_summary = trans.schedule_summary;
                if (trans.secondary_price !== undefined) localized.secondary_price = trans.secondary_price;
                if (Array.isArray(localized.variations) && trans.variations) {
                    localized.variations = localized.variations.map(v => {
                        const vTrans = trans.variations[String(v.id)];
                        return vTrans ? { ...v, name: vTrans.name, secondary_price: vTrans.secondary_price || v.secondary_price } : v;
                    });
                }
                return localized;
            },

            patchDomTranslations() {
                // Patch data-i18n elements
                document.querySelectorAll('[data-i18n]').forEach(el => {
                    const key = el.getAttribute('data-i18n');
                    if (!key) return;
                    const translated = this.t(key);
                    if (translated) {
                        const icon = el.querySelector('i, svg');
                        if (icon) {
                            const innerSpan = el.querySelector('span');
                            if (innerSpan) {
                                innerSpan.textContent = translated;
                            } else {
                                let textNodeFound = false;
                                for (let i = 0; i < el.childNodes.length; i++) {
                                    if (el.childNodes[i].nodeType === Node.TEXT_NODE && el.childNodes[i].textContent.trim() !== '') {
                                        el.childNodes[i].textContent = ' ' + translated.trim();
                                        textNodeFound = true;
                                        break;
                                    }
                                }
                                if (!textNodeFound) {
                                    el.appendChild(document.createTextNode(' ' + translated.trim()));
                                }
                            }
                        } else {
                            el.textContent = translated;
                        }
                    }
                });

                // Patch placeholders
                document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
                    const key = el.getAttribute('data-i18n-placeholder');
                    const trans = this.t(key);
                    if (trans) el.setAttribute('placeholder', trans);
                });

                // Patch titles
                document.querySelectorAll('[data-i18n-title]').forEach(el => {
                    const key = el.getAttribute('data-i18n-title');
                    const trans = this.t(key);
                    if (trans) el.setAttribute('title', trans);
                });

                // Patch aria-labels
                document.querySelectorAll('[data-i18n-aria-label]').forEach(el => {
                    const key = el.getAttribute('data-i18n-aria-label');
                    const trans = this.t(key);
                    if (trans) el.setAttribute('aria-label', trans);
                });

                // Patch category names
                document.querySelectorAll('[data-cat-name-id]').forEach(el => {
                    const catId = el.getAttribute('data-cat-name-id');
                    const name = this.catName(catId);
                    if (name) el.textContent = name;
                });

                // Patch dish titles
                document.querySelectorAll('[data-dish-name-id]').forEach(el => {
                    const dishId = el.getAttribute('data-dish-name-id');
                    const name = this.dishName(dishId);
                    if (name) el.textContent = name;
                });

                // Patch dish descriptions
                document.querySelectorAll('[data-dish-desc-id]').forEach(el => {
                    const dishId = el.getAttribute('data-dish-desc-id');
                    const desc = this.dishDesc(dishId);
                    if (desc !== undefined) el.textContent = desc;
                });

                // Patch dish schedule warnings
                document.querySelectorAll('[data-dish-schedule-id]').forEach(el => {
                    const dishId = el.getAttribute('data-dish-schedule-id');
                    const sched = this.dishScheduleText(dishId);
                    if (sched) {
                        el.textContent = this.t('only_available_hours_notice', { time: sched }, sched);
                    }
                });

                // Patch dish secondary prices
                document.querySelectorAll('[data-dish-sec-price-id]').forEach(el => {
                    const dishId = el.getAttribute('data-dish-sec-price-id');
                    const secPrice = this.dishSecondaryPrice(dishId);
                    if (secPrice) {
                        el.textContent = secPrice;
                        el.style.display = '';
                    } else {
                        el.textContent = '';
                        el.style.display = 'none';
                    }
                });
            },

            applyLocaleBundle(locale, bundle) {
                if (!locale || !bundle) return;
                this.selectedLang = locale;
                this.langDirection = bundle.dir || (['ar', 'fa', 'he', 'ur'].includes(locale) ? 'rtl' : 'ltr');

                document.documentElement.lang = locale;
                document.documentElement.dir = this.langDirection;

                if (bundle.i18n && typeof bundle.i18n === 'object') {
                    Object.assign(translations, bundle.i18n);
                    this.i18n = { ...translations };
                }

                if (bundle.catalog) {
                    this.catalogTranslations = bundle.catalog;
                }

                if (bundle.schedules) {
                    this.schedules = bundle.schedules;
                }

                if (bundle.secondary_currency !== undefined) {
                    this.secondaryCurrency = bundle.secondary_currency;
                }

                if (bundle.greeting) {
                    this.timeAwareGreeting = bundle.greeting;
                    this.aiGreetingText = bundle.greeting;
                }

                const menuTitle = this.t('digital_menu', {}, 'Digital Menu');
                const vendorName = vendor.name || '';
                document.title = vendorName ? `${vendorName} - ${menuTitle}` : menuTitle;

                if (Array.isArray(this.catalogProducts) && this.catalogTranslations?.products) {
                    this.catalogProducts = this.catalogProducts.map(prod => {
                        const pId = String(prod.id);
                        const pTrans = this.catalogTranslations.products[pId];
                        if (!pTrans) return prod;
                        return {
                            ...prod,
                            name: pTrans.name || prod.name,
                            name_lower: (pTrans.name || prod.name).toLowerCase(),
                            description: pTrans.description !== undefined ? pTrans.description : prod.description,
                            schedule_summary: pTrans.schedule_summary || '',
                            secondary_price: pTrans.secondary_price || '',
                            variations: Array.isArray(prod.variations) && pTrans.variations ? prod.variations.map(v => {
                                const vTrans = pTrans.variations[String(v.id)];
                                return vTrans ? { ...v, name: vTrans.name, secondary_price: vTrans.secondary_price || v.secondary_price } : v;
                            }) : prod.variations
                        };
                    });
                }

                if (Array.isArray(this.cart) && this.cart.length > 0) {
                    this.cart = this.cart.map(item => {
                        const pId = String(item.id || item.product_id);
                        const pTrans = this.catalogTranslations?.products?.[pId];
                        const newName = pTrans?.name || item.name;
                        let newVarName = item.variation_name;
                        if (item.variation_id && pTrans?.variations?.[String(item.variation_id)]) {
                            newVarName = pTrans.variations[String(item.variation_id)].name;
                        }
                        return {
                            ...item,
                            name: newName,
                            variation_name: newVarName
                        };
                    });
                    this.saveCartToStorage();
                }

                if (this.selectedDish) {
                    this.selectedDish = this.getLocalizedDish(this.selectedDish);
                    if (this.selectedVariation && this.selectedDish.variations) {
                        const matchingVar = this.selectedDish.variations.find(v => v.id === this.selectedVariation.id);
                        if (matchingVar) {
                            this.selectedVariation = matchingVar;
                        }
                    }
                }

                this.patchDomTranslations();

                try {
                    const url = new URL(window.location.href);
                    if (url.searchParams.get('lang') !== locale) {
                        url.searchParams.set('lang', locale);
                        window.history.replaceState({ lang: locale }, '', url.toString());
                    }
                } catch(e) {}
            },

            selectLanguage(locale) {
                this.selectStorefrontLanguage(locale);
            },

            async selectStorefrontLanguage(locale) {
                if (!locale) return;
                const normalized = String(locale).toLowerCase().trim();

                if (normalized === this.selectedLang && !this.needsLanguageSelection) {
                    this.showLanguageModal = false;
                    return;
                }

                this.showLanguageModal = false;
                this.selectedLang = normalized;

                try {
                    localStorage.setItem('qrmenu_locale_' + this.vendorSlug, normalized);
                    document.cookie = 'app_locale=' + encodeURIComponent(normalized) + ';path=/;max-age=31536000;SameSite=Lax';
                    document.cookie = 'locale=' + encodeURIComponent(normalized) + ';path=/;max-age=31536000;SameSite=Lax';
                } catch(e) {}

                this.saveCartToStorage();

                // If currently offline, apply offline cached bundle if possible
                if (this.isOffline) {
                    let bundle = this.localeBundles ? this.localeBundles[normalized] : null;
                    if (!bundle) {
                        try {
                            const cachedRaw = localStorage.getItem(`qrmenu_bundle_${this.vendorSlug}_${normalized}`);
                            if (cachedRaw) bundle = JSON.parse(cachedRaw);
                        } catch(e) {}
                    }
                    if (bundle) {
                        this.applyLocaleBundle(normalized, bundle);
                    }
                    return;
                }

                const targetUrl = new URL(window.location.href);
                targetUrl.searchParams.set('lang', normalized);

                try {
                    const setLangUrl = routes.setLanguage || `/api/m/${this.vendorSlug}/language`;
                    fetch(setLangUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            language: normalized,
                            location_id: this.locationId
                        }),
                        keepalive: true
                    }).catch(() => {});
                } catch (e) {}

                // Immediately navigate so 100% of Blade templates, modals, categories, and dishes reload cleanly
                window.location.href = targetUrl.toString();
            },

            changeLanguage(locale) {
                this.selectStorefrontLanguage(locale);
            },

            initAiWaiterWelcome() {
                if (this.showLanguageModal) {
                    return; // Wait for customer language selection first
                }

                if (vendor.ai_waiter_enabled) {
                    try {
                        const welcomed = sessionStorage.getItem('ai_waiter_welcomed_' + this.vendorSlug);
                        if (!welcomed) {
                            sessionStorage.setItem('ai_waiter_welcomed_' + this.vendorSlug, 'true');
                            if (this.entryContext === 'table_qr') {
                                // Table QR: never block the menu with fullscreen questionnaire!
                                if (this.timeAwareGreeting) {
                                    this.showToastNotification(this.timeAwareGreeting, 'info');
                                }
                            } else {
                                // General menu: show gentle welcome card
                                this.showWelcomeModal = true;
                            }
                        }
                    } catch(e) {}
                }
            },

            openAiWaiter(step = null) {
                this.showAiWaiter = true;
                if (step) {
                    this.aiStep = step;
                }
                if (!this.aiSessionId) {
                    this.startAiSession();
                }
            },

            closeAiWaiter() {
                this.showAiWaiter = false;
                try { sessionStorage.setItem('ai_waiter_welcomed_' + this.vendorSlug, 'true'); } catch(e) {}
            },

            skipToMenu() {
                this.closeAiWaiter();
                this.showWelcomeModal = false;
            },

            async startAiSession() {
                try {
                    const sessionUrl = routes.aiSessionStart || `/api/m/${this.vendorSlug}/ai-waiter/session/start`;
                    const res = await fetch(sessionUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            lang: this.selectedLang,
                            location_id: this.locationId,
                            table_number: this.tableNumber || null,
                            entry_context: this.entryContext
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.aiSessionId = data.session_id;
                        this.aiSessionToken = data.session_token;
                        if (data.allowed_languages && data.allowed_languages.length) {
                            this.aiAllowedLanguages = data.allowed_languages;
                        }
                        if (data.allowed_language_details && data.allowed_language_details.length) {
                            this.aiLanguageDetails = data.allowed_language_details;
                        }
                        if (data.greeting) {
                            this.timeAwareGreeting = data.greeting;
                        }
                        if (data.active_meal_period) {
                            this.activeMealPeriod = data.active_meal_period;
                        }
                        this.aiCurrentQuestion = data.next_question;
                    }
                } catch (e) {
                    console.error('Failed to start AI session:', e);
                }
            },

            getAiLanguagesList() {
                if (this.aiLanguageDetails && this.aiLanguageDetails.length > 0) {
                    return this.aiLanguageDetails.filter(l => this.aiAllowedLanguages.includes(l.code));
                }
                const fallbackNames = {
                    hy: { code: 'hy', name: 'Armenian', native_name: 'Հայերեն', flag: '🇦🇲' },
                    en: { code: 'en', name: 'English', native_name: 'English', flag: '🇬🇧' },
                    ru: { code: 'ru', name: 'Russian', native_name: 'Русский', flag: '🇷🇺' },
                    fr: { code: 'fr', name: 'French', native_name: 'Français', flag: '🇫🇷' },
                    de: { code: 'de', name: 'German', native_name: 'Deutsch', flag: '🇩🇪' },
                    es: { code: 'es', name: 'Spanish', native_name: 'Español', flag: '🇪🇸' },
                    it: { code: 'it', name: 'Italian', native_name: 'Italiano', flag: '🇮🇹' },
                    ka: { code: 'ka', name: 'Georgian', native_name: 'ქართული', flag: '🇬🇪' },
                    ar: { code: 'ar', name: 'Arabic', native_name: 'العربية', flag: '🇦🇪' },
                };
                return (this.aiAllowedLanguages || []).map(code => {
                    return fallbackNames[code] || { code: code, name: code.toUpperCase(), native_name: code.toUpperCase(), flag: '🌐' };
                });
            },

            async selectAiLanguage(lang) {
                this.selectedLang = lang;
                try { localStorage.setItem('qrmenu_locale_' + this.vendorSlug, lang); } catch(e) {}

                if (this.aiSessionId) {
                    try {
                        const base = routes.aiSessionBase || `/api/m/${this.vendorSlug}/ai-waiter/session`;
                        const url = base + '/' + this.aiSessionId + '/language';
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ language: lang })
                        });
                        const data = await res.json();
                        if (data.success && data.next_question) {
                            this.aiCurrentQuestion = data.next_question;
                        }
                    } catch (e) {
                        console.error('Failed to update AI session language:', e);
                    }
                }
                this.aiStep = 'greeting';
            },

            startAiQuestions() {
                if (!this.aiCurrentQuestion && this.aiSessionId) {
                    this.fetchNextQuestion();
                }
                this.aiStep = 'question';
            },

            async fetchNextQuestion() {
                if (!this.aiSessionId) return;
                try {
                    const base = routes.aiSessionBase || `/api/m/${this.vendorSlug}/ai-waiter/session`;
                    const url = base + '/' + this.aiSessionId + '/next-question';
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ lang: this.selectedLang })
                    });
                    const data = await res.json();
                    if (data.success) {
                        if (data.is_ready_for_recommendations || !data.next_question) {
                            await this.runAiAnalysisAnimation();
                        } else {
                            this.aiCurrentQuestion = data.next_question;
                        }
                    }
                } catch (e) {
                    console.error('Failed to fetch next question:', e);
                }
            },

            async submitAiAnswer(questionKey, optionValue = null, freeText = null) {
                if (!questionKey && this.aiCurrentQuestion) {
                    questionKey = this.aiCurrentQuestion.key;
                }
                const answerText = freeText || this.aiFreeTextInput;
                if (!optionValue && (!answerText || !answerText.trim())) {
                    return;
                }

                if (this.aiCurrentQuestion) {
                    this.aiQuestionHistory.push(JSON.parse(JSON.stringify(this.aiCurrentQuestion)));
                }

                this.aiFreeTextInput = '';
                this.aiLoading = true;

                try {
                    if (!this.aiSessionId) {
                        await this.startAiSession();
                    }

                    const base = routes.aiSessionBase || `/api/m/${this.vendorSlug}/ai-waiter/session`;
                    const url = base + '/' + this.aiSessionId + '/answer';
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            question_key: questionKey,
                            answer_value: optionValue,
                            free_text: answerText || null,
                            lang: this.selectedLang
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        if (data.is_ready_for_recommendations || !data.next_question) {
                            await this.runAiAnalysisAnimation();
                        } else {
                            this.aiCurrentQuestion = data.next_question;
                            this.aiStep = 'question';
                        }
                    } else {
                        await this.runAiAnalysisAnimation();
                    }
                } catch (e) {
                    console.error('Error submitting answer:', e);
                    await this.runAiAnalysisAnimation();
                } finally {
                    this.aiLoading = false;
                }
            },

            backAiQuestion() {
                if (this.aiQuestionHistory.length > 0) {
                    this.aiCurrentQuestion = this.aiQuestionHistory.pop();
                    this.aiStep = 'question';
                } else {
                    this.aiStep = 'greeting';
                }
            },

            skipAiQuestion() {
                if (this.aiCurrentQuestion) {
                    this.submitAiAnswer(this.aiCurrentQuestion.key, 'skip', null);
                } else {
                    this.runAiAnalysisAnimation();
                }
            },

            async runAiAnalysisAnimation() {
                this.aiStep = 'analyzing';
                this.aiAnalysisStep = 1;

                setTimeout(() => { this.aiAnalysisStep = 2; }, 350);
                setTimeout(() => { this.aiAnalysisStep = 3; }, 750);
                setTimeout(() => { this.aiAnalysisStep = 4; }, 1150);

                await this.fetchPersonalizedRecommendations();

                setTimeout(() => {
                    this.aiStep = 'recommendations';
                }, 1450);
            },

            async fetchPersonalizedRecommendations() {
                this.aiLoading = true;
                try {
                    if (!this.aiSessionId) {
                        await this.startAiSession();
                    }
                    const base = routes.aiSessionBase || `/api/m/${this.vendorSlug}/ai-waiter/session`;
                    const url = base + '/' + this.aiSessionId + '/recommendations';
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            lang: this.selectedLang,
                            location_id: this.locationId
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.aiCommentary = data.commentary || '';
                        this.aiMainRecommendations = data.main_recommendations || [];
                        this.aiSecondaryRecommendations = data.secondary_recommendations || [];
                        this.aiPairingDrink = data.pairing_drink || null;
                        this.aiBundle = data.bundle || null;
                        this.aiRecommendations = data.main_recommendations || [];
                    }
                } catch (e) {
                    console.error('Error fetching recommendations:', e);
                } finally {
                    this.aiLoading = false;
                }
            },

            async addAiDishToCart(dish) {
                if (!dish) return;
                const price = Number(dish.price || dish.base_price || 0);
                this.addToCart(dish.id, dish.name, price, 'Standard', null, 1);

                if (this.aiSessionId) {
                    try {
                        const base = routes.aiSessionBase || `/api/m/${this.vendorSlug}/ai-waiter/session`;
                        const url = base + '/' + this.aiSessionId + '/add-to-cart';
                        await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ product_id: dish.id })
                        });
                    } catch (e) {}
                }
            },

            async addAiBundleToCart() {
                if (!this.aiBundle || !this.aiBundle.items || this.aiBundle.items.length === 0) return;

                this.aiBundle.items.forEach(item => {
                    const price = Number(item.price || 0);
                    this.addToCart(item.id, item.name, price, 'Standard', null, 1);
                });

                if (this.aiSessionId) {
                    try {
                        const base = routes.aiSessionBase || `/api/m/${this.vendorSlug}/ai-waiter/session`;
                        const url = base + '/' + this.aiSessionId + '/add-to-cart';
                        await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ bundle: this.aiBundle })
                        });
                    } catch (e) {}
                }

                const bundleToast = this.selectedLang === 'en' 
                    ? '✨ Complete Smart Bundle added to cart!' 
                    : (this.selectedLang === 'ru' ? '✨ Полный комбо-набор добавлен в корзину!' : '✨ Ամբողջ առաջարկված փաթեթն ավելացվել է զամբյուղ:');
                this.triggerToast(bundleToast, 'success', 'fa-solid fa-wand-magic-sparkles');
            },

            openAiChat() {
                this.showAiChatDrawer = true;
            },

            closeAiChat() {
                this.showAiChatDrawer = false;
            },

            toggleVoiceInput() {
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                if (!SpeechRecognition) {
                    const noSupportMsg = this.selectedLang === 'en'
                        ? 'Voice input is not supported in this browser. Please use Chrome or Safari.'
                        : (this.selectedLang === 'ru' ? 'Голосовой ввод не поддерживается в этом браузере. Используйте Chrome или Safari.' : 'Ձայնային մուտքագրումը չի աջակցվում այս բրաուզերում: Խնդրում ենք օգտագործել Chrome կամ Safari:');
                    this.triggerToast(noSupportMsg, 'remove', 'fa-solid fa-microphone-slash');
                    return;
                }

                if (this.isVoiceListening) {
                    this.stopVoiceInput();
                    return;
                }

                try {
                    const recognition = new SpeechRecognition();
                    recognition.continuous = false;
                    recognition.interimResults = true;
                    
                    let bcp47 = 'hy-AM';
                    if (this.selectedLang === 'ru') bcp47 = 'ru-RU';
                    else if (this.selectedLang === 'en') bcp47 = 'en-US';
                    else if (this.selectedLang === 'fr') bcp47 = 'fr-FR';
                    else if (this.selectedLang === 'de') bcp47 = 'de-DE';
                    else if (this.selectedLang === 'es') bcp47 = 'es-ES';
                    else if (this.selectedLang === 'it') bcp47 = 'it-IT';
                    else if (this.selectedLang === 'ar') bcp47 = 'ar-SA';
                    else if (this.selectedLang === 'fa') bcp47 = 'fa-IR';
                    else if (this.selectedLang === 'ka') bcp47 = 'ka-GE';
                    recognition.lang = bcp47;

                    recognition.onstart = () => {
                        this.isVoiceListening = true;
                    };

                    recognition.onresult = (event) => {
                        let interim = '';
                        let final = '';
                        for (let i = event.resultIndex; i < event.results.length; ++i) {
                            if (event.results[i].isFinal) {
                                final += event.results[i][0].transcript;
                            } else {
                                interim += event.results[i][0].transcript;
                            }
                        }
                        const text = (final || interim || '').trim();
                        if (text) {
                            this.aiChatInput = text;
                        }
                    };

                    recognition.onerror = (event) => {
                        console.warn('Speech recognition error:', event.error);
                        this.isVoiceListening = false;
                        if (event.error === 'not-allowed') {
                            const deniedMsg = this.selectedLang === 'en'
                                ? 'Please allow microphone access to talk with the AI Waiter.'
                                : (this.selectedLang === 'ru' ? 'Пожалуйста, разрешите доступ к микрофону для разговора с AI-официантом.' : 'Խնդրում ենք թույլատրել խոսափողի (microphone) հասանելիությունը AI մատուցողի հետ խոսելու համար:');
                            this.triggerToast(deniedMsg, 'remove', 'fa-solid fa-microphone-slash');
                        }
                    };

                    recognition.onend = () => {
                        this.isVoiceListening = false;
                        if (this.aiChatInput && this.aiChatInput.trim().length > 1) {
                            this.sendAiChatMessage();
                        }
                    };

                    this.speechRecognition = recognition;
                    recognition.start();
                } catch (e) {
                    console.error('Failed to start speech recognition:', e);
                    this.isVoiceListening = false;
                }
            },

            stopVoiceInput() {
                if (this.speechRecognition) {
                    try {
                        this.speechRecognition.stop();
                    } catch (e) {}
                }
                this.isVoiceListening = false;
            },

            toggleSpeakAiMessage(text) {
                if (!window.speechSynthesis) {
                    const noTtsMsg = this.selectedLang === 'en'
                        ? 'Speech playback is not supported on this device.'
                        : (this.selectedLang === 'ru' ? 'Озвучивание не поддерживается на этом устройстве.' : 'Ձայնային վերարտադրումը չի աջակցվում այս սարքում:');
                    this.triggerToast(noTtsMsg, 'remove', 'fa-solid fa-volume-xmark');
                    return;
                }

                if (window.speechSynthesis.speaking) {
                    window.speechSynthesis.cancel();
                    if (this.currentlySpeakingText === text) {
                        this.currentlySpeakingText = null;
                        return;
                    }
                }

                if (!text) return;

                // Clean markdown artifacts or emoji soup for cleaner speech
                const cleanText = text
                    .replace(/[*#_~`«»]/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim();

                const utterance = new SpeechSynthesisUtterance(cleanText);

                let bcp47 = 'hy-AM';
                if (this.selectedLang === 'ru') bcp47 = 'ru-RU';
                else if (this.selectedLang === 'en') bcp47 = 'en-US';
                utterance.lang = bcp47;
                utterance.rate = 1.0;
                utterance.pitch = 1.0;

                // Attempt to pick a natural sounding voice if available
                const voices = window.speechSynthesis.getVoices();
                if (voices && voices.length) {
                    const langVoice = voices.find(v => v.lang && v.lang.toLowerCase().startsWith(this.selectedLang));
                    if (langVoice) {
                        utterance.voice = langVoice;
                    }
                }

                utterance.onstart = () => {
                    this.currentlySpeakingText = text;
                };
                utterance.onend = () => {
                    this.currentlySpeakingText = null;
                };
                utterance.onerror = () => {
                    this.currentlySpeakingText = null;
                };

                window.speechSynthesis.speak(utterance);
            },

            async sendAiChatMessage(customText = null) {
                const message = (customText || this.aiChatInput || '').trim();
                if (!message) return;

                if (this.isVoiceListening) {
                    this.stopVoiceInput();
                }

                // Prepare conversation history
                const history = (this.aiChatMessages || [])
                    .filter(m => m && m.text)
                    .slice(-8)
                    .map(m => ({ sender: m.sender, text: m.text }));

                this.aiChatMessages.push({
                    sender: 'user',
                    text: message,
                    time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                });

                this.aiChatInput = '';
                this.aiChatLoading = true;

                this.$nextTick(() => {
                    const chatBox = document.getElementById('aiChatMessagesBox');
                    if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
                });

                try {
                    if (!this.aiSessionId) {
                        await this.startAiSession();
                    }
                    const base = routes.aiSessionBase || `/api/m/${this.vendorSlug}/ai-waiter/session`;
                    const url = base + '/' + this.aiSessionId + '/chat';
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            message: message,
                            lang: this.selectedLang,
                            location_id: this.locationId,
                            history: history
                        })
                    });
                    const data = await res.json();
                    if (data.success || data.reply) {
                        this.aiChatMessages.push({
                            sender: 'ai',
                            text: data.reply,
                            products: data.suggested_products || [],
                            quick_replies: data.quick_replies || [],
                            action: data.action || null,
                            intent: data.intent || null,
                            time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                        });
                    } else {
                        this.aiChatMessages.push({
                            sender: 'ai',
                            text: this.selectedLang === 'en' 
                                ? 'I am here to help you choose delicious dishes from our menu! What would you like to taste?' 
                                : (this.selectedLang === 'ru' ? 'Я с радостью помогу вам с выбором блюд из меню! Что вам подсказать?' : 'Սիրով կօգնեմ ընտրել Ձեր ճաշակին համապատասխան ուտեստ մեր մենյուից:'),
                            products: [],
                            time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                        });
                    }
                } catch (e) {
                    console.error('Chat query error:', e);
                } finally {
                    this.aiChatLoading = false;
                    this.$nextTick(() => {
                        const chatBox = document.getElementById('aiChatMessagesBox');
                        if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
                    });
                }
            },

            resetAiQuiz() {
                this.aiQuestionHistory = [];
                this.aiPreferences = {};
                this.aiFreeTextInput = '';
                this.aiStep = 'intro';
                this.startAiSession();
            },

            // Backwards compatibility methods
            selectQuizOption(category, value) {
                if (this.aiPreferences[category] === value) {
                    this.aiPreferences[category] = null;
                } else {
                    this.aiPreferences[category] = value;
                }
                this.fetchAiRecommendations();
            },

            setPromptPreset(text) {
                this.aiPromptInput = text;
                this.submitAiPrompt();
            },

            submitAiPrompt() {
                this.fetchAiRecommendations();
            },

            async fetchAiRecommendations() {
                await this.fetchPersonalizedRecommendations();
            },

            selectPhoneCountry(code) {
                this.phoneCountryCode = code;
                this.showPhoneCountryDropdown = false;
                this.phoneErrorMsg = '';
                this.onPhoneInput();
            },

            onPhoneInput(event) {
                let raw = this.phoneNationalNumber || '';
                
                // Auto-detect country if user pasted a full international number with +
                if (raw.trim().startsWith('+')) {
                    const cleanPlus = raw.trim().replace(/[^\d+]/g, '');
                    if (cleanPlus.startsWith('+374')) {
                        this.phoneCountryCode = 'AM';
                        raw = cleanPlus.slice(4);
                    } else if (cleanPlus.startsWith('+7')) {
                        this.phoneCountryCode = 'RU';
                        raw = cleanPlus.slice(2);
                    } else if (cleanPlus.startsWith('+995')) {
                        this.phoneCountryCode = 'GE';
                        raw = cleanPlus.slice(4);
                    } else if (cleanPlus.startsWith('+1')) {
                        this.phoneCountryCode = 'US';
                        raw = cleanPlus.slice(2);
                    } else if (cleanPlus.startsWith('+33')) {
                        this.phoneCountryCode = 'FR';
                        raw = cleanPlus.slice(3);
                    } else if (cleanPlus.startsWith('+49')) {
                        this.phoneCountryCode = 'DE';
                        raw = cleanPlus.slice(3);
                    } else if (cleanPlus.startsWith('+971')) {
                        this.phoneCountryCode = 'AE';
                        raw = cleanPlus.slice(4);
                    } else if (cleanPlus.startsWith('+98')) {
                        this.phoneCountryCode = 'IR';
                        raw = cleanPlus.slice(3);
                    }
                }

                let digits = raw.replace(/\D/g, '');
                const country = this.selectedPhoneCountry;

                // Special handling for local trunk prefixes
                if (country.code === 'AM') {
                    if (digits.startsWith('0')) {
                        digits = digits.slice(1);
                    }
                    if (digits.length > 8) {
                        digits = digits.slice(0, 8);
                    }
                } else if (country.code === 'RU') {
                    if (digits.startsWith('8') && digits.length > 10) {
                        digits = digits.slice(1);
                    }
                    if (digits.length > 10) {
                        digits = digits.slice(0, 10);
                    }
                } else if (country.code === 'GE') {
                    if (digits.length > 9) digits = digits.slice(0, 9);
                } else if (country.code === 'US') {
                    if (digits.length > 10) digits = digits.slice(0, 10);
                } else if (country.code === 'FR') {
                    if (digits.startsWith('0')) digits = digits.slice(1);
                    if (digits.length > 9) digits = digits.slice(0, 9);
                } else if (country.code === 'DE') {
                    if (digits.startsWith('0')) digits = digits.slice(1);
                    if (digits.length > 11) digits = digits.slice(0, 11);
                } else if (country.code === 'AE') {
                    if (digits.startsWith('0')) digits = digits.slice(1);
                    if (digits.length > 9) digits = digits.slice(0, 9);
                } else if (country.code === 'IR') {
                    if (digits.startsWith('0')) digits = digits.slice(1);
                    if (digits.length > 10) digits = digits.slice(0, 10);
                } else {
                    if (digits.length > 15) digits = digits.slice(0, 15);
                }

                // Format display according to country
                let formatted = digits;
                if (country.code === 'AM') {
                    if (digits.length > 4) {
                        formatted = digits.slice(0, 2) + ' ' + digits.slice(2, 5) + (digits.length > 5 ? ' ' + digits.slice(5) : '');
                    } else if (digits.length > 2) {
                        formatted = digits.slice(0, 2) + ' ' + digits.slice(2);
                    }
                } else if (country.code === 'RU') {
                    if (digits.length > 7) {
                        formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6, 8) + '-' + digits.slice(8);
                    } else if (digits.length > 4) {
                        formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
                    } else if (digits.length > 3) {
                        formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
                    } else if (digits.length > 0) {
                        formatted = '(' + digits;
                    }
                } else if (country.code === 'US') {
                    if (digits.length > 6) {
                        formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
                    } else if (digits.length > 3) {
                        formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
                    } else if (digits.length > 0) {
                        formatted = '(' + digits;
                    }
                } else if (country.code === 'GE') {
                    if (digits.length > 5) {
                        formatted = digits.slice(0, 3) + ' ' + digits.slice(3, 5) + '-' + digits.slice(5, 7) + (digits.length > 7 ? '-' + digits.slice(7) : '');
                    } else if (digits.length > 3) {
                        formatted = digits.slice(0, 3) + ' ' + digits.slice(3);
                    }
                }

                this.phoneNationalNumber = formatted;
                this.customerPhone = digits.length > 0 ? (country.dial + digits) : '';

                if (this.isPhoneValid()) {
                    this.phoneErrorMsg = '';
                }
            },

            validatePhoneOnBlur() {
                const digits = this.phoneCleanDigits;
                const isRequired = (this.orderType === 'delivery' || this.orderType === 'takeaway');
                if (!digits) {
                    if (isRequired) {
                        this.phoneErrorMsg = (this.orderType === 'delivery' ? translations.delivery_phone_required : translations.takeaway_phone_required) || 'Հեռախոսահամարը պարտադիր է';
                    } else {
                        this.phoneErrorMsg = '';
                    }
                    return;
                }
                const country = this.selectedPhoneCountry;
                if (country.digits && digits.length !== country.digits) {
                    if (digits.length < country.digits) {
                        this.phoneErrorMsg = (translations.phone_missing_digits || 'Պակասում է :count նիշ').replace(':count', country.digits - digits.length) + ` (${digits.length}/${country.digits})`;
                    } else {
                        this.phoneErrorMsg = (translations.phone_exact_digits || 'Հեռախոսահամարը պետք է ունենա ճիշտ :digits նիշ').replace(':digits', country.digits);
                    }
                } else if (!country.digits && (digits.length < (country.minDigits || 7) || digits.length > (country.maxDigits || 15))) {
                    this.phoneErrorMsg = translations.invalid_phone_format || 'Անվավեր հեռախոսահամար';
                } else {
                    this.phoneErrorMsg = '';
                }
            },

            isPhoneValid() {
                const digits = this.phoneCleanDigits;
                const isRequired = (this.orderType === 'delivery' || this.orderType === 'takeaway');
                if (!digits) {
                    return !isRequired;
                }
                const country = this.selectedPhoneCountry;
                if (country.digits) {
                    return digits.length === country.digits;
                }
                const min = country.minDigits || 7;
                const max = country.maxDigits || 15;
                return digits.length >= min && digits.length <= max;
            },

            onEmailInput() {
                if (this.isEmailValid()) {
                    this.emailErrorMsg = '';
                }
            },

            validateEmailOnBlur() {
                const email = (this.customerEmail || '').trim();
                if (!email) {
                    this.emailErrorMsg = '';
                    return;
                }
                if (!this.isEmailValid()) {
                    this.emailErrorMsg = translations.invalid_email_format || 'Խնդրում ենք մուտքագրել վավեր էլ․ հասցե (օրինակ՝ name@example.com)';
                } else {
                    this.emailErrorMsg = '';
                }
            },

            isEmailValid() {
                const email = (this.customerEmail || '').trim();
                if (!email) {
                    return true;
                }
                const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/;
                if (!emailRegex.test(email)) return false;
                const parts = email.split('@');
                if (parts.length !== 2) return false;
                const domain = parts[1];
                if (!domain.includes('.')) return false;
                const tld = domain.split('.').pop();
                if (!tld || tld.length < 2) return false;
                return true;
            }
        };
    }

    // Expose globally on window
    window.createStorefrontApp = createStorefrontApp;
    window.modernBistroApp = function(cfg) { return createStorefrontApp(cfg); };
    window.storefrontApp = function(cfg) { return createStorefrontApp(cfg); };
    window.vibrantGlassApp = function(cfg) { return createStorefrontApp(cfg); };

    function bindAlpineComponents() {
        if (window.Alpine && typeof window.Alpine.data === 'function') {
            window.Alpine.data('storefrontApp', function(cfg) { return createStorefrontApp(cfg); });
            window.Alpine.data('modernBistroApp', function(cfg) { return createStorefrontApp(cfg); });
            window.Alpine.data('vibrantGlassApp', function(cfg) { return createStorefrontApp(cfg); });
            window.Alpine.data('createStorefrontApp', function(cfg) { return createStorefrontApp(cfg); });
        }
    }

    bindAlpineComponents();
    document.addEventListener('alpine:init', bindAlpineComponents);

    // Haptic feedback listener
    document.addEventListener('click', function(e) {
        const el = e.target.closest('button, a, [role="button"], input[type="submit"], input[type="button"], .payment-method-tile, .cart-type-btn, .quiz-chip-btn, .lang-pill-btn, .prompt-preset-chip, .ai-question-opt-btn, .ai-action-btn, .category-chip, .order-pill-open-btn');
        if (el && window.navigator && typeof window.navigator.vibrate === 'function') {
            try { window.navigator.vibrate(25); } catch(err) {}
        }
    }, { passive: true });

})(window);
