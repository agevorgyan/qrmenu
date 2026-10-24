@if(isset($featuredDish) && $featuredDish)
    @php
        $dishPayload = [
            'id' => $featuredDish->id,
            'name' => $featuredDish->getTranslatedName($lang),
            'image' => $featuredDish->image ?: asset('images/default-dish.png'),
            'description' => $featuredDish->getTranslatedDescription($lang),
            'base_price' => (float)$featuredDish->getEffectivePrice($location?->id),
            'regular_price' => (float)$featuredDish->getRegularPrice($location?->id),
            'is_discount_active' => $featuredDish->isDiscountActive(),
            'discount_percentage' => $featuredDish->getDiscountPercentage(),
            'is_available' => (bool)$featuredDish->isAvailableAtLocation($location?->id),
            'is_time_available' => (bool)$featuredDish->isTimeAvailable(),
            'schedule_summary' => $featuredDish->getAvailabilityScheduleSummary($lang),
            'available_for_dine_in' => (bool)($featuredDish->available_for_dine_in ?? true),
            'available_for_takeaway' => (bool)($featuredDish->available_for_takeaway ?? true),
            'available_for_delivery' => (bool)($featuredDish->available_for_delivery ?? true),
            'variations' => $featuredDish->variations->map(fn($v) => [
                'id' => $v->id,
                'name' => $v->getTranslatedName($lang),
                'price' => (float)$v->getEffectivePrice(),
                'regular_price' => (float)$v->price,
                'is_default' => (bool)$v->is_default
            ])->values(),
        ];
        $isFeaturedDineIn = (bool)($featuredDish->available_for_dine_in ?? true);
        $isFeaturedTakeaway = (bool)($featuredDish->available_for_takeaway ?? true);
        $isFeaturedDelivery = (bool)($featuredDish->available_for_delivery ?? true);
        $isFeaturedTimeAvailable = $featuredDish->isTimeAvailable();
        $featuredScheduleSummary = $featuredDish->getAvailabilityScheduleSummary($lang);
        $badgeText = $vendor->featured_dish_badge ?: '⭐ ՕՐՎԱ ԱՌԱՋԱՐԿ';
        $subtitleText = $vendor->featured_dish_subtitle ?: ($featuredDish->getTranslatedDescription($lang) ?: 'Շեֆ խոհարարի հատուկ ընտրանի');
    @endphp

    <div class="featured-dish-banner-container"
         x-show='matchesSearch({!! json_encode(mb_strtolower($featuredDish->getTranslatedName($lang))) !!}) && isProductAvailableForCurrentOrderType({{ $isFeaturedDineIn ? 'true' : 'false' }}, {{ $isFeaturedTakeaway ? 'true' : 'false' }}, {{ $isFeaturedDelivery ? 'true' : 'false' }})'
         @click='selectDish({{ json_encode($dishPayload, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }})'
         style="margin-bottom: 1.75rem; cursor: pointer;">

        
        <div style="position: relative; border-radius: 20px; overflow: hidden; background: var(--bg-card); border: 2px solid var(--primary); box-shadow: 0 12px 32px -8px rgba(0, 0, 0, 0.35); transition: transform 0.25s ease, box-shadow 0.25s ease;">
            
            <!-- Glowing Accent Background Decoration -->
            <div style="position: absolute; -webkit-mask-image: radial-gradient(circle, rgba(0,0,0,1) 0%, rgba(0,0,0,0) 70%); mask-image: radial-gradient(circle, rgba(0,0,0,1) 0%, rgba(0,0,0,0) 70%); top: -40px; right: -40px; width: 180px; height: 180px; background: var(--primary); opacity: 0.18; border-radius: 50%; pointer-events: none;"></div>

            <!-- Banner Layout: Responsive Stack (Mobile) / Grid (Desktop) -->
            <div class="featured-banner-grid" style="display: grid; grid-template-columns: 1fr; position: relative; z-index: 2;">
                
                <!-- Hero Image Area with Badges -->
                <div style="position: relative; height: 210px; overflow: hidden; background: #000;">
                    <img src="{{ $featuredDish->image }}"
                         onerror="this.onerror=null;this.src='{{ asset('images/default-dish.png') }}';"
                         alt="{{ $featuredDish->getTranslatedName($lang) }}"
                         style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;"
                         onmouseover="this.style.transform='scale(1.05)'"
                         onmouseout="this.style.transform='scale(1)'">

                    <!-- Gradient Overlay on Image -->
                    <div style="position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.2) 50%, rgba(0,0,0,0.4) 100%);"></div>

                    <!-- Top Floating Badge: Dish of the Day -->
                    <div style="position: absolute; top: 1rem; left: 1rem; display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                        <span style="background: linear-gradient(135deg, #f59e0b, #ef4444); color: #ffffff; font-size: 0.78rem; font-weight: 800; padding: 0.35rem 0.85rem; border-radius: 9999px; letter-spacing: 0.03em; box-shadow: 0 4px 14px rgba(239, 68, 68, 0.45); display: inline-flex; align-items: center; gap: 0.4rem; text-transform: uppercase;">
                            <i class="fa-solid fa-fire-flame-curved" style="color: #fff;"></i>
                            <span>{{ $badgeText }}</span>
                        </span>

                        @if($featuredDish->isDiscountActive())
                            <span style="background: linear-gradient(135deg, #ef4444, #dc2626); color: #ffffff; font-size: 0.78rem; font-weight: 800; padding: 0.35rem 0.75rem; border-radius: 9999px; box-shadow: 0 4px 14px rgba(239, 68, 68, 0.45); display: inline-flex; align-items: center; gap: 0.3rem;">
                                <i class="fa-solid fa-tag"></i> -{{ $featuredDish->getDiscountPercentage() }}%
                            </span>
                        @endif

                        @if(!$isFeaturedTimeAvailable)
                            <span style="background: rgba(217, 119, 6, 0.9); backdrop-filter: blur(6px); color: #ffffff; font-size: 0.74rem; font-weight: 800; padding: 0.35rem 0.75rem; border-radius: 9999px; box-shadow: 0 4px 14px rgba(217, 119, 6, 0.45); display: inline-flex; align-items: center; gap: 0.35rem;">
                                <i class="fa-regular fa-clock"></i>
                                <span>{{ __('menu.only_available_hours_notice', ['time' => $featuredScheduleSummary]) }}</span>
                            </span>
                        @elseif($featuredScheduleSummary)
                            <span style="background: rgba(16, 185, 129, 0.85); backdrop-filter: blur(6px); color: #ffffff; font-size: 0.74rem; font-weight: 800; padding: 0.35rem 0.75rem; border-radius: 9999px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4); display: inline-flex; align-items: center; gap: 0.35rem;">
                                <i class="fa-regular fa-clock"></i>
                                <span>{{ $featuredScheduleSummary }}</span>
                            </span>
                        @endif
                        
                        @if($featuredDish->category)
                            <span style="background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(6px); color: #e2e8f0; font-size: 0.72rem; font-weight: 700; padding: 0.3rem 0.65rem; border-radius: 9999px; border: 1px solid rgba(255,255,255,0.15);">
                                {{ $featuredDish->category->getTranslatedName($lang) }}
                            </span>
                        @endif
                    </div>

                    <!-- Preparation Time or Calories if available -->
                    @if($featuredDish->preparation_time_min || $featuredDish->calories)
                        <div style="position: absolute; bottom: 0.75rem; right: 1rem; display: flex; gap: 0.4rem;">
                            @if($featuredDish->preparation_time_min)
                                <span style="background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); color: #f8fafc; font-size: 0.72rem; font-weight: 700; padding: 0.25rem 0.6rem; border-radius: 8px; display: inline-flex; align-items: center; gap: 0.3rem;">
                                    <i class="fa-regular fa-clock" style="color: var(--primary);"></i> {{ $featuredDish->preparation_time_min }} {{ __('menu.mins') }}
                                </span>
                            @endif
                            @if($featuredDish->calories)
                                <span style="background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); color: #f8fafc; font-size: 0.72rem; font-weight: 700; padding: 0.25rem 0.6rem; border-radius: 8px; display: inline-flex; align-items: center; gap: 0.3rem;">
                                    🔥 {{ $featuredDish->calories }} կկալ
                                </span>
                            @endif
                        </div>
                    @endif
                </div>

                <!-- Banner Content Area -->
                <div style="padding: 1.25rem 1.35rem 1.35rem;">
                    
                    <!-- Dietary / Specialty Tags -->
                    @if(!empty($featuredDish->dietary_tags))
                        <div style="display: flex; gap: 0.35rem; flex-wrap: wrap; margin-bottom: 0.5rem;">
                            @foreach($featuredDish->dietary_tags as $tag)
                                <span style="background: rgba(245, 158, 11, 0.12); color: var(--primary); border: 1px solid rgba(245, 158, 11, 0.25); padding: 0.15rem 0.45rem; border-radius: 6px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase;">
                                    {{ strtoupper(str_replace('_', ' ', $tag)) }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    <!-- Dish Title & Description -->
                    <div style="margin-bottom: 0.9rem;">
                        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.35rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.35rem 0; line-height: 1.3; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
                            <span>{{ $featuredDish->getTranslatedName($lang) }}</span>
                            <span style="color: var(--primary); font-size: 1.15rem;">★</span>
                        </h3>
                        
                        <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.45; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            {{ $subtitleText }}
                        </p>
                    </div>

                    <!-- Bottom Row: Price & Call-to-Action -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 0.75rem; border-top: 1px dashed var(--border-color); gap: 0.75rem;">
                        <div>
                            <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; display: block;">
                                {{ __('menu.price') }}
                            </span>
                            @if($featuredDish->isDiscountActive())
                                <div style="display: flex; align-items: baseline; gap: 0.4rem; flex-wrap: wrap;">
                                    <div style="font-family: 'Outfit', sans-serif; font-size: 1.35rem; font-weight: 800; color: #ef4444; display: flex; align-items: baseline; gap: 0.25rem;">
                                        <span>{{ number_format($featuredDish->getEffectivePrice($location?->id), 0, '.', ',') }}</span>
                                        <span style="font-size: 0.85rem; color: #ef4444; font-weight: 700;">{{ $vendor->currency ?? 'AMD' }}</span>
                                    </div>
                                    <span style="font-size: 0.85rem; text-decoration: line-through; color: var(--text-muted); font-weight: 600;">
                                        {{ number_format($featuredDish->getRegularPrice($location?->id), 0, '.', ',') }}
                                    </span>
                                </div>
                            @else
                                <div style="font-family: 'Outfit', sans-serif; font-size: 1.35rem; font-weight: 800; color: var(--text-main); display: flex; align-items: baseline; gap: 0.25rem;">
                                    <span>{{ number_format($featuredDish->getEffectivePrice($location?->id), 0, '.', ',') }}</span>
                                    <span style="font-size: 0.85rem; color: var(--primary); font-weight: 700;">{{ $vendor->currency ?? 'AMD' }}</span>
                                </div>
                            @endif
                            @if($vendor->isSecondaryCurrencyActive())
                                @php
                                    $secFeaturedFormatted = $vendor->formatSecondaryPrice($featuredDish->getEffectivePrice($location?->id), $lang);
                                @endphp
                                @if($secFeaturedFormatted)
                                    <div class="secondary-price" style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); opacity: 0.9; margin-top: 0.15rem; line-height: 1.1;">
                                        {{ $secFeaturedFormatted }}
                                    </div>
                                @endif
                            @endif
                        </div>

                        @if(!$isFeaturedTimeAvailable)
                            <button type="button"
                                    disabled
                                    class="btn"
                                    style="background: rgba(245, 158, 11, 0.18); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.4); padding: 0.65rem 1.15rem; border-radius: 12px; font-weight: 700; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 0.4rem; opacity: 0.85; cursor: not-allowed;"
                                    title="{{ __('menu.only_available_hours_notice', ['time' => $featuredScheduleSummary]) }}">
                                <i class="fa-regular fa-clock"></i>
                                <span>{{ __('menu.only_available_hours_notice', ['time' => $featuredScheduleSummary]) }}</span>
                            </button>
                        @else
                            <button type="button"
                                    class="btn btn-primary"
                                    style="background: var(--primary); color: #ffffff; border: none; padding: 0.65rem 1.25rem; border-radius: 12px; font-weight: 700; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 6px 18px rgba(0,0,0,0.15); cursor: pointer; transition: transform 0.15s ease;"
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

    </div>
@endif
