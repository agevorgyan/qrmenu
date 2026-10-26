@php
    $offersList = isset($dailyOffers) && $dailyOffers instanceof \Illuminate\Support\Collection && $dailyOffers->isNotEmpty()
        ? $dailyOffers
        : (isset($featuredDish) && $featuredDish ? collect([(object)[
            'id' => 0,
            'offer_id' => null,
            'product_id' => $featuredDish->id,
            'product' => $featuredDish,
            'sort_order' => 0,
            'badge' => $vendor->featured_dish_badge ?: __('menu.special_badge_default'),
            'subtitle' => $vendor->featured_dish_subtitle ?: ($featuredDish->getTranslatedDescription($lang) ?: __('menu.chef_selection_default')),
            'effective_price' => (float)$featuredDish->getEffectivePrice($location?->id),
            'regular_price' => (float)$featuredDish->getRegularPrice($location?->id),
            'is_discount_active' => $featuredDish->isDiscountActive(),
            'discount_percentage' => $featuredDish->getDiscountPercentage(),
            'discount_price' => null,
        ]]) : collect());

    $totalOffers = $offersList->count();
@endphp

@if($totalOffers > 0)
    <div class="featured-dish-banner-container daily-offers-section"
         role="region"
         aria-label="{{ $totalOffers > 1 ? __('menu.daily_offers_title') : __('menu.daily_offer_single_title') }}"
         x-data="dailyOffersCarousel({{ $totalOffers }})"
         x-init="initCarousel()"
         style="margin-bottom: 2rem; position: relative;">

        <!-- Carousel Section Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; padding: 0 0.25rem;">
            <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(239, 68, 68, 0.2)); color: #ef4444; font-size: 1.05rem; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);">
                    <i class="fa-solid fa-fire-flame-curved"></i>
                </span>
                <div>
                    <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0; line-height: 1.2; letter-spacing: -0.01em;">
                        {{ $totalOffers > 1 ? __('menu.daily_offers_title') : __('menu.daily_offer_single_title') }}
                    </h2>
                </div>
                @if($totalOffers > 1)
                    <span style="font-size: 0.72rem; font-weight: 700; background: rgba(245, 158, 11, 0.15); color: #d97706; padding: 0.2rem 0.6rem; border-radius: 9999px; letter-spacing: 0.02em;">
                        {{ __('menu.offers_count', ['count' => $totalOffers]) }}
                    </span>
                @endif
            </div>

            <!-- Modern Navigation Pill Controls (Header) -->
            @if($totalOffers > 1)
                <div class="carousel-nav-pill" role="group" aria-label="{{ __('menu.daily_offers_title') }}">
                    <button type="button"
                            class="carousel-nav-btn prev-btn"
                            @click="scrollPrev()"
                            :disabled="isAtStart"
                            aria-label="{{ __('menu.prev_offer') }}">
                        <svg viewBox="0 0 24 24" class="carousel-arrow-icon" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                    </button>

                    <div class="carousel-counter-badge" aria-live="polite">
                        <span x-text="currentIndex + 1" class="carousel-counter-current"></span>
                        <span class="carousel-counter-divider">/</span>
                        <span class="carousel-counter-total">{{ $totalOffers }}</span>
                    </div>

                    <button type="button"
                            class="carousel-nav-btn next-btn"
                            @click="scrollNext()"
                            :disabled="isAtEnd"
                            aria-label="{{ __('menu.next_offer') }}">
                        <svg viewBox="0 0 24 24" class="carousel-arrow-icon" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </button>
                </div>
            @endif
        </div>

        <!-- Carousel Stage with Floating Modern Side Arrows -->
        <div class="daily-offers-carousel-stage" style="position: relative;">
            @if($totalOffers > 1)
                <!-- Left Floating Arrow Button -->
                <button type="button"
                        class="carousel-floating-arrow carousel-floating-prev"
                        @click="scrollPrev()"
                        :class="{ 'hidden-arrow': isAtStart }"
                        :disabled="isAtStart"
                        aria-label="{{ __('menu.prev_offer') }}">
                    <svg viewBox="0 0 24 24" class="carousel-floating-icon" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
            @endif

            <!-- Carousel Track with CSS Scroll Snap -->
            <div class="daily-offers-track"
                 x-ref="track"
                 @scroll.passive="onScroll()"
                 @keydown.arrow-left.prevent="scrollPrev()"
                 @keydown.arrow-right.prevent="scrollNext()"
                 tabindex="0"
                 role="list"
                 style="display: flex; gap: 1.15rem; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; -ms-overflow-style: none; -webkit-overflow-scrolling: touch; padding: 0.35rem 0.25rem 0.85rem; margin: 0 -0.25rem; outline: none;">

            @foreach($offersList as $index => $item)
                @php
                    $prod = $item->product;
                    $dishPayload = [
                        'id' => $prod->id,
                        'name' => $prod->getTranslatedName($lang),
                        'image' => $prod->image ?: asset('images/default-dish.png'),
                        'description' => $prod->getTranslatedDescription($lang),
                        'base_price' => (float)$item->effective_price,
                        'regular_price' => (float)$item->regular_price,
                        'is_discount_active' => (bool)$item->is_discount_active,
                        'discount_percentage' => $item->discount_percentage,
                        'is_available' => (bool)$prod->isAvailableAtLocation($location?->id),
                        'is_time_available' => (bool)$prod->isTimeAvailable(),
                        'schedule_summary' => $prod->getAvailabilityScheduleSummary($lang),
                        'available_for_dine_in' => (bool)($prod->available_for_dine_in ?? true),
                        'available_for_takeaway' => (bool)($prod->available_for_takeaway ?? true),
                        'available_for_delivery' => (bool)($prod->available_for_delivery ?? true),
                        'variations' => $prod->variations->map(fn($v) => [
                            'id' => $v->id,
                            'name' => $v->getTranslatedName($lang),
                            'price' => (float)$v->getEffectivePrice(),
                            'regular_price' => (float)$v->price,
                            'is_default' => (bool)$v->is_default
                        ])->values(),
                    ];
                    $isDineIn = (bool)($prod->available_for_dine_in ?? true);
                    $isTakeaway = (bool)($prod->available_for_takeaway ?? true);
                    $isDelivery = (bool)($prod->available_for_delivery ?? true);
                    $isTimeAvailable = $prod->isTimeAvailable();
                    $scheduleSummary = $prod->getAvailabilityScheduleSummary($lang);
                    $badgeText = $item->badge ?: ($vendor->featured_dish_badge ?: __('menu.special_badge_default'));
                    $subtitleText = $item->subtitle ?: ($vendor->featured_dish_subtitle ?: ($prod->getTranslatedDescription($lang) ?: __('menu.chef_selection_default')));
                @endphp

                <div class="daily-offer-slide"
                     role="listitem"
                     data-index="{{ $index }}"
                     x-show='matchesSearch({!! json_encode(mb_strtolower($prod->getTranslatedName($lang))) !!}) && isProductAvailableForCurrentOrderType({{ $isDineIn ? 'true' : 'false' }}, {{ $isTakeaway ? 'true' : 'false' }}, {{ $isDelivery ? 'true' : 'false' }})'
                     @click='selectDish({{ json_encode($dishPayload, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }})'
                     style="{{ $totalOffers > 1 ? 'flex: 0 0 clamp(285px, 80vw, 360px);' : 'flex: 1 1 100%; max-width: 100%;' }} scroll-snap-align: start; cursor: pointer; user-select: none;">

                    <div class="daily-offer-card"
                         style="position: relative; height: 100%; border-radius: 18px; overflow: hidden; background: var(--bg-card); border: 1.5px solid var(--border-color); box-shadow: 0 10px 28px -6px rgba(0, 0, 0, 0.22); transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease; display: flex; flex-direction: column;"
                         onmouseover="this.style.transform='translateY(-3px)'; this.style.borderColor='var(--primary)';"
                         onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='var(--border-color)';">

                        <!-- Glowing Accent Background Decoration -->
                        <div style="position: absolute; top: -35px; right: -35px; width: 140px; height: 140px; background: var(--primary); opacity: 0.12; border-radius: 50%; pointer-events: none; filter: blur(20px);"></div>

                        <!-- Card Image Area -->
                        <div style="position: relative; height: 165px; overflow: hidden; background: #0b0f19;">
                            <img src="{{ $prod->image }}"
                                 onerror="this.onerror=null;this.src='{{ asset('images/default-dish.png') }}';"
                                 alt="{{ $prod->getTranslatedName($lang) }}"
                                 loading="lazy"
                                 style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.45s ease;"
                                 onmouseover="this.style.transform='scale(1.06)'"
                                 onmouseout="this.style.transform='scale(1)'">

                            <!-- Image Gradient Overlay -->
                            <div style="position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.2) 55%, rgba(0,0,0,0.45) 100%);"></div>

                            <!-- Top Badges Row -->
                            <div style="position: absolute; top: 0.75rem; left: 0.75rem; right: 0.75rem; display: flex; justify-content: space-between; align-items: flex-start; gap: 0.4rem; pointer-events: none;">
                                <div style="display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap;">
                                    <span style="background: linear-gradient(135deg, #f59e0b, #ef4444); color: #ffffff; font-size: 0.72rem; font-weight: 800; padding: 0.3rem 0.7rem; border-radius: 9999px; letter-spacing: 0.03em; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4); display: inline-flex; align-items: center; gap: 0.35rem; text-transform: uppercase;">
                                        <i class="fa-solid fa-fire-flame-curved" style="color: #fff; font-size: 0.75rem;"></i>
                                        <span>{{ $badgeText }}</span>
                                    </span>

                                    @if($item->is_discount_active && $item->discount_percentage)
                                        <span style="background: linear-gradient(135deg, #ef4444, #dc2626); color: #ffffff; font-size: 0.72rem; font-weight: 800; padding: 0.3rem 0.65rem; border-radius: 9999px; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4); display: inline-flex; align-items: center; gap: 0.25rem;">
                                            <i class="fa-solid fa-tag"></i> -{{ $item->discount_percentage }}%
                                        </span>
                                    @endif
                                </div>

                                @if($prod->category)
                                    <span style="background: rgba(0, 0, 0, 0.65); backdrop-filter: blur(8px); color: #f1f5f9; font-size: 0.68rem; font-weight: 700; padding: 0.25rem 0.6rem; border-radius: 9999px; border: 1px solid rgba(255,255,255,0.18); white-space: nowrap;">
                                        {{ $prod->category->getTranslatedName($lang) }}
                                    </span>
                                @endif
                            </div>

                            <!-- Bottom Image Chips (Preparation time, calories, time restriction) -->
                            <div style="position: absolute; bottom: 0.65rem; left: 0.75rem; right: 0.75rem; display: flex; justify-content: space-between; align-items: center; gap: 0.4rem; pointer-events: none;">
                                @if(!$isTimeAvailable)
                                    <span style="background: rgba(217, 119, 6, 0.92); backdrop-filter: blur(6px); color: #ffffff; font-size: 0.68rem; font-weight: 800; padding: 0.25rem 0.6rem; border-radius: 8px; display: inline-flex; align-items: center; gap: 0.3rem;">
                                        <i class="fa-regular fa-clock"></i>
                                        <span>{{ __('menu.only_available_hours_notice', ['time' => $scheduleSummary]) }}</span>
                                    </span>
                                @elseif($scheduleSummary)
                                    <span style="background: rgba(16, 185, 129, 0.9); backdrop-filter: blur(6px); color: #ffffff; font-size: 0.68rem; font-weight: 800; padding: 0.25rem 0.6rem; border-radius: 8px; display: inline-flex; align-items: center; gap: 0.3rem;">
                                        <i class="fa-regular fa-clock"></i>
                                        <span>{{ $scheduleSummary }}</span>
                                    </span>
                                @else
                                    <span></span>
                                @endif

                                <div style="display: flex; gap: 0.35rem; align-items: center;">
                                    @if($prod->preparation_time_min)
                                        <span style="background: rgba(0,0,0,0.72); backdrop-filter: blur(6px); color: #f8fafc; font-size: 0.68rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.25rem;">
                                            <i class="fa-regular fa-clock" style="color: var(--primary);"></i> {{ $prod->preparation_time_min }} {{ __('menu.mins') }}
                                        </span>
                                    @endif
                                    @if($prod->calories)
                                        <span style="background: rgba(0,0,0,0.72); backdrop-filter: blur(6px); color: #f8fafc; font-size: 0.68rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px;">
                                            🔥 {{ $prod->calories }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Card Content Area -->
                        <div style="padding: 1rem 1.15rem 1.15rem; display: flex; flex-direction: column; flex-grow: 1; justify-content: space-between;">
                            <div>
                                <!-- Dish Title -->
                                <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.18rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.35rem 0; line-height: 1.3; display: flex; align-items: baseline; justify-content: space-between; gap: 0.5rem;">
                                    <span style="display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;">{{ $prod->getTranslatedName($lang) }}</span>
                                    <span style="color: var(--primary); font-size: 1rem; flex-shrink: 0;">★</span>
                                </h3>

                                <!-- Subtitle / Description -->
                                <p style="font-size: 0.84rem; color: var(--text-muted); line-height: 1.4; margin: 0 0 0.85rem 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.35rem;">
                                    {{ $subtitleText }}
                                </p>
                            </div>

                            <!-- Bottom Row: Price & Call-to-Action -->
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 0.65rem; border-top: 1px dashed var(--border-color); gap: 0.5rem;">
                                <div>
                                    <span style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 0.1rem;">
                                        {{ __('menu.price') }}
                                    </span>

                                    @php
                                        $bannerCurrencySymbol = ($vendor->currency === 'AMD' || empty($vendor->currency)) ? '֏' : $vendor->currency;
                                    @endphp
                                    @if($item->is_discount_active)
                                        <div style="display: flex; align-items: baseline; gap: 0.35rem; flex-wrap: wrap;">
                                            <div style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: #ef4444; display: flex; align-items: baseline; gap: 0.2rem;">
                                                <span>{{ number_format($item->effective_price, 0, '.', ',') }}</span>
                                                <span style="font-size: 0.8rem; color: #ef4444; font-weight: 700;">{{ $bannerCurrencySymbol }}</span>
                                            </div>
                                            <span style="font-size: 0.8rem; text-decoration: line-through; color: var(--text-muted); font-weight: 600;">
                                                {{ number_format($item->regular_price, 0, '.', ',') }}
                                            </span>
                                        </div>
                                    @else
                                        <div style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); display: flex; align-items: baseline; gap: 0.2rem;">
                                            <span>{{ number_format($item->effective_price, 0, '.', ',') }}</span>
                                            <span style="font-size: 0.8rem; color: var(--primary); font-weight: 700;">{{ $bannerCurrencySymbol }}</span>
                                        </div>
                                    @endif

                                    @if($vendor->isSecondaryCurrencyActive())
                                        @php
                                            $secPrice = $vendor->formatSecondaryPrice($item->effective_price, $lang);
                                        @endphp
                                        @if($secPrice)
                                            <div class="secondary-price" style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); opacity: 0.9; margin-top: 0.1rem; line-height: 1.1;">
                                                {{ $secPrice }}
                                            </div>
                                        @endif
                                    @endif
                                </div>

                                @if(!$isTimeAvailable)
                                    <button type="button"
                                            disabled
                                            class="btn"
                                            style="background: rgba(245, 158, 11, 0.16); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.35); padding: 0.55rem 0.9rem; border-radius: 12px; font-weight: 700; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 0.35rem; cursor: not-allowed;"
                                            title="{{ __('menu.only_available_hours_notice', ['time' => $scheduleSummary]) }}">
                                        <i class="fa-regular fa-clock"></i>
                                        <span>{{ __('menu.only_available_hours_notice', ['time' => $scheduleSummary]) }}</span>
                                    </button>
                                @else
                                    <button type="button"
                                            class="btn btn-primary"
                                            style="background: var(--primary); color: #ffffff; border: none; padding: 0.55rem 1rem; border-radius: 12px; font-weight: 700; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 4px 14px rgba(0,0,0,0.18); cursor: pointer; transition: transform 0.15s ease;"
                                            onmouseover="this.style.transform='translateY(-2px)'"
                                            onmouseout="this.style.transform='translateY(0)'">
                                        <i class="fa-solid fa-cart-plus"></i>
                                        <span>{{ __('menu.add') }}</span>
                                    </button>
                                @endif
                            </div>
                        </div>

                    </div>
                </div>
            @endforeach

            </div>

            @if($totalOffers > 1)
                <!-- Right Floating Arrow Button -->
                <button type="button"
                        class="carousel-floating-arrow carousel-floating-next"
                        @click="scrollNext()"
                        :class="{ 'hidden-arrow': isAtEnd }"
                        :disabled="isAtEnd"
                        aria-label="{{ __('menu.next_offer') }}">
                    <svg viewBox="0 0 24 24" class="carousel-floating-icon" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
            @endif
        </div>

        <!-- Carousel Pagination Dots (When multiple offers) -->
        @if($totalOffers > 1)
            <div class="carousel-dots-wrapper"
                 style="display: flex; justify-content: center; align-items: center; gap: 0.45rem; margin-top: 0.75rem;"
                 role="tablist"
                 aria-label="{{ __('menu.daily_offers_title') }}">
                <template x-for="idx in totalSlides" :key="idx">
                    <button type="button"
                            role="tab"
                            :aria-selected="currentIndex === idx - 1"
                            :aria-label="'Slide ' + idx"
                            @click="scrollTo(idx - 1)"
                            class="carousel-dot-btn"
                            :class="{ 'active': currentIndex === idx - 1 }"
                            style="padding: 0; border: none; cursor: pointer; border-radius: 9999px; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);"
                            :style="currentIndex === idx - 1 ? 'width: 26px; height: 6px; background: var(--primary); box-shadow: 0 2px 8px rgba(225, 29, 72, 0.4);' : 'width: 6px; height: 6px; background: var(--border-color); opacity: 0.6;'">
                    </button>
                </template>
            </div>
        @endif

        <script>
            function dailyOffersCarousel(total) {
                return {
                    totalSlides: total,
                    currentIndex: 0,
                    isAtStart: true,
                    isAtEnd: false,
                    initCarousel() {
                        this.updateBounds();
                    },
                    scrollPrev() {
                        const track = this.$refs.track;
                        if (!track) return;
                        const card = track.querySelector('.daily-offer-slide');
                        const cardWidth = card ? card.offsetWidth + 18 : 300;
                        track.scrollBy({ left: -cardWidth, behavior: 'smooth' });
                    },
                    scrollNext() {
                        const track = this.$refs.track;
                        if (!track) return;
                        const card = track.querySelector('.daily-offer-slide');
                        const cardWidth = card ? card.offsetWidth + 18 : 300;
                        track.scrollBy({ left: cardWidth, behavior: 'smooth' });
                    },
                    scrollTo(index) {
                        const track = this.$refs.track;
                        if (!track) return;
                        const cards = track.querySelectorAll('.daily-offer-slide');
                        if (cards[index]) {
                            cards[index].scrollIntoView({ behavior: 'smooth', inline: 'start', block: 'nearest' });
                        }
                    },
                    onScroll() {
                        this.updateBounds();
                    },
                    updateBounds() {
                        const track = this.$refs.track;
                        if (!track) return;
                        const card = track.querySelector('.daily-offer-slide');
                        const cardWidth = card ? card.offsetWidth + 18 : 300;
                        const idx = Math.round(track.scrollLeft / cardWidth);
                        this.currentIndex = Math.max(0, Math.min(this.totalSlides - 1, idx));
                        this.isAtStart = track.scrollLeft <= 5;
                        this.isAtEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 5;
                    }
                };
            }
        </script>

        <style>
            .daily-offers-track::-webkit-scrollbar {
                display: none;
            }

            /* Modern Header Navigation Pill */
            .carousel-nav-pill {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 3px 4px;
                background: var(--bg-card);
                border: 1px solid var(--border-color);
                border-radius: 9999px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
                backdrop-filter: blur(14px);
                -webkit-backdrop-filter: blur(14px);
                user-select: none;
            }
            .carousel-nav-btn {
                width: 34px;
                height: 34px;
                border-radius: 50%;
                border: 1px solid transparent;
                background: transparent;
                color: var(--text-main);
                display: inline-flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
                padding: 0;
            }
            .carousel-arrow-icon {
                width: 15px;
                height: 15px;
                transition: transform 0.2s ease;
            }
            .carousel-nav-btn.prev-btn:hover:not(:disabled) .carousel-arrow-icon {
                transform: translateX(-1.5px);
            }
            .carousel-nav-btn.next-btn:hover:not(:disabled) .carousel-arrow-icon {
                transform: translateX(1.5px);
            }
            .carousel-nav-btn:hover:not(:disabled) {
                background: var(--primary);
                color: #ffffff;
                box-shadow: 0 4px 14px rgba(225, 29, 72, 0.35);
                transform: scale(1.06);
            }
            .carousel-nav-btn:active:not(:disabled) {
                transform: scale(0.92);
            }
            .carousel-nav-btn:disabled {
                opacity: 0.25;
                cursor: not-allowed;
            }
            .carousel-counter-badge {
                padding: 0 0.5rem;
                font-family: 'Outfit', sans-serif;
                font-size: 0.8rem;
                font-weight: 700;
                color: var(--text-muted);
                display: inline-flex;
                align-items: center;
                line-height: 1;
            }
            .carousel-counter-current {
                font-weight: 800;
                color: var(--primary);
                font-size: 0.88rem;
            }
            .carousel-counter-divider {
                opacity: 0.4;
                margin: 0 3px;
                font-size: 0.72rem;
            }
            .carousel-counter-total {
                opacity: 0.75;
            }

            /* Modern Floating Side Arrows on Track */
            .carousel-floating-arrow {
                position: absolute;
                top: 50%;
                transform: translateY(-50%);
                z-index: 15;
                width: 44px;
                height: 44px;
                border-radius: 50%;
                background: var(--bg-card);
                border: 1px solid var(--border-color);
                color: var(--text-main);
                display: inline-flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                box-shadow: 0 10px 25px -4px rgba(0, 0, 0, 0.22), 0 4px 10px rgba(0, 0, 0, 0.08);
                backdrop-filter: blur(16px);
                -webkit-backdrop-filter: blur(16px);
                transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            }
            .carousel-floating-prev {
                left: -14px;
            }
            .carousel-floating-next {
                right: -14px;
            }
            .carousel-floating-icon {
                width: 18px;
                height: 18px;
                transition: transform 0.2s ease;
            }
            .carousel-floating-prev:hover:not(:disabled) .carousel-floating-icon {
                transform: translateX(-1.5px);
            }
            .carousel-floating-next:hover:not(:disabled) .carousel-floating-icon {
                transform: translateX(1.5px);
            }
            .carousel-floating-arrow:hover:not(:disabled) {
                background: var(--primary);
                color: #ffffff;
                border-color: var(--primary);
                transform: translateY(-50%) scale(1.1);
                box-shadow: 0 12px 30px rgba(0, 0, 0, 0.28), 0 0 0 3px rgba(225, 29, 72, 0.2);
            }
            .carousel-floating-arrow:active:not(:disabled) {
                transform: translateY(-50%) scale(0.95);
            }
            .carousel-floating-arrow.hidden-arrow {
                opacity: 0;
                pointer-events: none;
                transform: translateY(-50%) scale(0.75);
            }

            @media (max-width: 768px) {
                .carousel-floating-arrow {
                    display: none !important;
                }
                .daily-offer-slide {
                    flex: 0 0 calc(84vw) !important;
                    max-width: 320px;
                }
            }
        </style>
    </div>
@endif
