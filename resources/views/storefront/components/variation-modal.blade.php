<!-- Modern Variation & Option Selection Modal Component -->
<div x-show="showVariationModal" 
     x-cloak
     @click.self="showVariationModal = false"
     x-transition:enter="variation-modal-enter"
     x-transition:enter-start="variation-modal-enter-start"
     x-transition:enter-end="variation-modal-enter-end"
     x-transition:leave="variation-modal-leave"
     x-transition:leave-start="variation-modal-enter-end"
     x-transition:leave-end="variation-modal-enter-start"
     class="variation-modal-overlay">
    
    <div class="variation-modal-sheet"
         x-show="showVariationModal"
         x-transition:enter="variation-sheet-enter"
         x-transition:enter-start="variation-sheet-enter-start"
         x-transition:enter-end="variation-sheet-enter-end"
         x-transition:leave="variation-sheet-leave"
         x-transition:leave-start="variation-sheet-enter-end"
         x-transition:leave-end="variation-sheet-enter-start">
        
        <!-- Drag Handle Indicator -->
        <div class="variation-drag-handle" @click="showVariationModal = false"></div>

        <!-- Header: Dish Information & Close Action -->
        <div class="variation-header-row">
            <div class="variation-dish-info">
                <img :src="selectedDish?.image || '{{ asset('images/default-dish.png') }}'" 
                     x-on:error="$event.target.src = '{{ asset('images/default-dish.png') }}'"
                     class="variation-dish-thumb" 
                     :alt="selectedDish?.name || 'Dish'">
                <div class="variation-dish-meta">
                    <h3 class="variation-dish-title" x-text="selectedDish?.name"></h3>
                    <p class="variation-dish-desc" x-text="selectedDish?.description"></p>
                </div>
            </div>
            <button type="button" 
                    @click="showVariationModal = false" 
                    class="variation-close-btn" 
                    aria-label="{{ __('menu.close') }}"
                    title="{{ __('menu.close') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Section Title: Choose Portion / Option -->
        <div class="variation-section-bar">
            <div class="variation-section-title">
                <i class="fa-solid fa-sliders"></i>
                <span>{{ __('menu.choose_portion') }}</span>
            </div>
            <span class="variation-count-badge" 
                  x-show="selectedDish?.variations?.length > 0"
                  x-text="(selectedDish?.variations?.length || 0) + ' {{ __('menu.options_available') }}'">
            </span>
        </div>

        <!-- Variations List -->
        <div class="variation-options-stack">
            <template x-for="v in selectedDish?.variations" :key="v.id">
                <div @click="selectedVariation = v"
                     class="variation-option-card"
                     :class="{ 'is-selected': selectedVariation?.id === v.id }">
                    <div class="variation-card-main">
                        <div class="variation-radio-indicator">
                            <i class="fa-solid fa-check" x-show="selectedVariation?.id === v.id"></i>
                        </div>
                        <span class="variation-name-text" x-text="v.name"></span>
                    </div>
                    <div class="variation-price-display" style="display: flex; flex-direction: column; align-items: flex-end;">
                        <div>
                            <template x-if="v.regular_price && v.regular_price > v.price">
                                <span style="font-size: 0.8rem; text-decoration: line-through; color: var(--text-muted); margin-right: 0.35rem; font-weight: 500;" x-text="Number(v.regular_price).toLocaleString()"></span>
                            </template>
                            <span class="variation-price-num" :style="v.regular_price && v.regular_price > v.price ? 'color: #ef4444;' : ''" x-text="Number(v.price).toLocaleString()"></span>
                            <span class="variation-price-currency" :style="v.regular_price && v.regular_price > v.price ? 'color: #ef4444;' : ''">{{ $vendor->currency }}</span>
                        </div>
                        @if($vendor->isSecondaryCurrencyActive())
                            <template x-if="v.secondary_price">
                                <span style="font-size: 0.72rem; font-weight: 600; color: var(--text-muted); opacity: 0.85; margin-top: 0.1rem;" x-text="v.secondary_price"></span>
                            </template>
                        @endif
                    </div>
                </div>
            </template>
        </div>

        <!-- Quantity Stepper Bar -->
        <div class="variation-qty-bar">
            <div class="variation-qty-label-wrap">
                <i class="fa-solid fa-cubes-stacked variation-qty-icon"></i>
                <span class="variation-qty-label">{{ __('menu.quantity') }}</span>
            </div>
            <div class="variation-qty-stepper">
                <button type="button" 
                        @click="if(variationQty > 1) variationQty--" 
                        class="variation-qty-btn"
                        aria-label="Decrease quantity">
                    <i class="fa-solid fa-minus"></i>
                </button>
                <span class="variation-qty-value" x-text="variationQty">1</span>
                <button type="button" 
                        @click="variationQty++" 
                        class="variation-qty-btn variation-qty-btn-plus"
                        aria-label="Increase quantity">
                    <i class="fa-solid fa-plus"></i>
                </button>
            </div>
        </div>

        <!-- Add to Order Button (CTA) -->
        <button type="button" 
                @click="addSelectedVariationToCart()" 
                :disabled="selectedDish?.is_time_available === false"
                :style="selectedDish?.is_time_available === false ? 'opacity: 0.85; cursor: not-allowed; background: #d97706; box-shadow: none;' : ''"
                class="variation-cta-btn">
            <template x-if="selectedDish?.is_time_available === false">
                <div class="variation-cta-left" style="display: flex; align-items: center; gap: 0.5rem; justify-content: center; width: 100%;">
                    <i class="fa-regular fa-clock"></i>
                    <span x-text="selectedDish?.schedule_summary ? ('{{ __('menu.only_available_hours_notice', ['time' => ':time']) }}'.replace(':time', selectedDish.schedule_summary)) : '{{ __('menu.closed_hours') }}'"></span>
                </div>
            </template>
            <template x-if="selectedDish?.is_time_available !== false">
                <div class="variation-cta-left">
                    <i class="fa-solid fa-cart-plus"></i>
                    <span>{{ __('menu.add_to_cart') }}</span>
                </div>
            </template>
            <div class="variation-cta-price" x-show="selectedDish?.is_time_available !== false">
                <span x-text="Number((selectedVariation?.price || selectedDish?.base_price || 0) * variationQty).toLocaleString()"></span>
                <span class="variation-cta-curr">{{ $vendor->currency }}</span>
            </div>
        </button>
    </div>
</div>

<style>
    /* Variation Modal Backdrop Overlay */
    .variation-modal-overlay {
        position: fixed;
        inset: 0;
        z-index: 150;
        background: rgba(0, 0, 0, 0.72);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        align-items: center;
        box-sizing: border-box;
    }

    /* Modal Animation Transitions */
    .variation-modal-enter {
        transition: opacity 0.28s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .variation-modal-enter-start {
        opacity: 0;
    }
    .variation-modal-enter-end {
        opacity: 1;
    }
    .variation-modal-leave {
        transition: opacity 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .variation-sheet-enter {
        transition: transform 0.32s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.28s ease;
    }
    .variation-sheet-enter-start {
        transform: translateY(100%);
        opacity: 0;
    }
    .variation-sheet-enter-end {
        transform: translateY(0);
        opacity: 1;
    }
    .variation-sheet-leave {
        transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.18s ease;
    }

    /* Variation Bottom Sheet Card */
    .variation-modal-sheet {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-bottom: none;
        border-radius: 28px 28px 0 0;
        padding: 0.85rem 1.35rem calc(1.5rem + env(safe-area-inset-bottom, 0.5rem)) 1.35rem;
        max-height: 88vh;
        max-height: 88dvh;
        width: 100%;
        max-width: 580px;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        box-shadow: 0 -12px 48px rgba(0, 0, 0, 0.45);
        box-sizing: border-box;
        position: relative;
    }

    /* Drag Handle */
    .variation-drag-handle {
        width: 42px;
        height: 4.5px;
        border-radius: 9999px;
        background: var(--border-color);
        margin: 0 auto 1.15rem auto;
        cursor: pointer;
        opacity: 0.85;
        transition: opacity 0.2s;
    }
    .variation-drag-handle:hover {
        opacity: 1;
    }

    /* Header Dish Meta Row */
    .variation-header-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 0.85rem;
        margin-bottom: 1.15rem;
    }

    .variation-dish-info {
        display: flex;
        align-items: center;
        gap: 0.95rem;
        min-width: 0;
        flex: 1;
    }

    .variation-dish-thumb {
        width: 68px;
        height: 68px;
        border-radius: 16px;
        object-fit: cover;
        border: 1.5px solid var(--border-color);
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.16);
        flex-shrink: 0;
        background: var(--bg-main);
    }

    .variation-dish-meta {
        min-width: 0;
        flex: 1;
    }

    .variation-dish-title {
        font-family: 'Outfit', sans-serif;
        font-size: 1.22rem;
        font-weight: 800;
        color: var(--text-main);
        line-height: 1.25;
        margin: 0 0 0.25rem 0;
        word-break: break-word;
        letter-spacing: -0.01em;
    }

    .variation-dish-desc {
        font-size: 0.82rem;
        color: var(--text-muted);
        line-height: 1.42;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        margin: 0;
    }

    .variation-close-btn {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        color: var(--text-muted);
        font-size: 1rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        padding: 0;
    }
    .variation-close-btn:hover {
        color: var(--text-main);
        border-color: rgba(255, 255, 255, 0.25);
        transform: rotate(90deg);
    }
    .variation-close-btn:active {
        transform: scale(0.92);
    }

    /* Section Bar */
    .variation-section-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
    }

    .variation-section-title {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.82rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
    }
    .variation-section-title i {
        color: var(--primary);
        font-size: 0.92rem;
    }

    .variation-count-badge {
        font-size: 0.74rem;
        font-weight: 700;
        color: var(--primary);
        background: color-mix(in srgb, var(--primary) 12%, transparent);
        border: 1px solid color-mix(in srgb, var(--primary) 25%, transparent);
        padding: 0.15rem 0.6rem;
        border-radius: 9999px;
    }

    /* Options Stack */
    .variation-options-stack {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
    }

    .variation-option-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.95rem 1.15rem;
        border-radius: 16px;
        border: 1.5px solid var(--border-color);
        background: var(--bg-main);
        cursor: pointer;
        transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        user-select: none;
        -webkit-tap-highlight-color: transparent;
        position: relative;
        overflow: hidden;
        gap: 0.85rem;
        box-sizing: border-box;
    }

    .variation-option-card:hover {
        border-color: color-mix(in srgb, var(--primary) 40%, var(--border-color));
        transform: translateY(-1px);
    }

    .variation-option-card:active {
        transform: scale(0.985);
    }

    .variation-option-card.is-selected {
        border-color: var(--primary);
        background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 15%, var(--bg-card)), color-mix(in srgb, var(--primary) 5%, var(--bg-main)));
        box-shadow: 0 6px 20px -2px color-mix(in srgb, var(--primary) 30%, transparent);
    }

    .variation-card-main {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        min-width: 0;
        flex: 1;
    }

    .variation-radio-indicator {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        border: 2px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: var(--bg-card);
        color: #ffffff;
        font-size: 0.68rem;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .variation-option-card.is-selected .variation-radio-indicator {
        border-color: var(--primary);
        background: var(--primary);
        box-shadow: 0 0 10px color-mix(in srgb, var(--primary) 60%, transparent);
    }

    .variation-name-text {
        font-size: 0.98rem;
        font-weight: 700;
        color: var(--text-main);
        line-height: 1.35;
        word-break: break-word;
    }

    .variation-price-display {
        display: flex;
        align-items: baseline;
        gap: 0.3rem;
        flex-shrink: 0;
    }

    .variation-price-num {
        font-family: 'Outfit', sans-serif;
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--accent);
        letter-spacing: -0.01em;
    }

    .variation-price-currency {
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--text-muted);
    }

    /* Quantity Stepper Bar */
    .variation-qty-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 1.15rem;
        padding: 0.8rem 1.15rem;
        background: var(--bg-main);
        border: 1.5px solid var(--border-color);
        border-radius: 16px;
        box-sizing: border-box;
    }

    .variation-qty-label-wrap {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        color: var(--text-main);
        font-size: 0.92rem;
        font-weight: 700;
    }

    .variation-qty-icon {
        color: var(--primary);
        font-size: 0.95rem;
    }

    .variation-qty-stepper {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 9999px;
        padding: 0.25rem 0.35rem;
    }

    .variation-qty-btn {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        border: none;
        background: transparent;
        color: var(--text-main);
        font-weight: 800;
        font-size: 0.95rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s ease;
        padding: 0;
    }
    .variation-qty-btn:hover {
        background: var(--border-color);
    }
    .variation-qty-btn:active {
        transform: scale(0.9);
    }

    .variation-qty-btn-plus {
        background: var(--primary);
        color: #ffffff;
        box-shadow: 0 2px 8px color-mix(in srgb, var(--primary) 35%, transparent);
    }
    .variation-qty-btn-plus:hover {
        background: var(--primary);
        transform: scale(1.06);
    }
    .variation-qty-btn-plus:active {
        transform: scale(0.92);
    }

    .variation-qty-value {
        min-width: 32px;
        text-align: center;
        font-family: 'Outfit', sans-serif;
        font-weight: 800;
        font-size: 1.15rem;
        color: var(--text-main);
    }

    /* Add to Order CTA Button */
    .variation-cta-btn {
        width: 100%;
        margin-top: 1.15rem;
        padding: 1rem 1.35rem;
        background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 82%, #000));
        color: #ffffff;
        border: none;
        border-radius: 16px;
        font-weight: 800;
        font-size: 1.05rem;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 8px 24px -4px color-mix(in srgb, var(--primary) 40%, transparent);
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        box-sizing: border-box;
    }

    .variation-cta-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 28px -4px color-mix(in srgb, var(--primary) 50%, transparent);
    }

    .variation-cta-btn:active {
        transform: scale(0.98);
    }

    .variation-cta-left {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        font-size: 1.02rem;
    }

    .variation-cta-left i {
        font-size: 1.15rem;
    }

    .variation-cta-price {
        display: flex;
        align-items: baseline;
        gap: 0.3rem;
        font-family: 'Outfit', sans-serif;
        font-size: 1.18rem;
        font-weight: 800;
        letter-spacing: -0.01em;
        background: rgba(0, 0, 0, 0.2);
        padding: 0.25rem 0.65rem;
        border-radius: 10px;
    }

    .variation-cta-curr {
        font-size: 0.85rem;
        font-weight: 700;
        opacity: 0.9;
    }
</style>
