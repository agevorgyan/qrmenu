<!-- Initial Order Type Selection Modal (Direct visits without table QR) -->
<div id="order-type-modal"
     x-show="showOrderTypeModal" 
     x-cloak
     @click.self="showOrderTypeModal = false"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="order-type-modal-overlay">
    
    <div class="order-type-modal-card"
         x-show="showOrderTypeModal"
         x-transition:enter="transition cubic-bezier(0.16, 1, 0.3, 1) duration-350"
         x-transition:enter-start="opacity-0 translate-y-8 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-8 scale-95">
        
        <!-- Modal Header -->
        <div class="order-type-modal-header">
            <div class="order-type-badge-icon">
                <i class="fa-solid fa-utensils"></i>
            </div>
            <div>
                <h3 class="order-type-title">{{ __('menu.choose_order_type') }}</h3>
                <p class="order-type-subtitle">{{ __('menu.choose_order_type_desc') }}</p>
            </div>
            <button type="button" 
                    @click="showOrderTypeModal = false" 
                    class="order-type-close-btn"
                    aria-label="{{ __('menu.close') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Options Container -->
        <div class="order-type-options-grid">
            <!-- Option 1: Delivery -->
            <template x-if="deliveryEnabled">
                <div class="order-type-card" 
                     :class="{ 'is-selected': orderType === 'delivery' }"
                     @click="selectInitialOrderType('delivery')">
                    <div class="order-type-card-glow"></div>
                    <div class="order-type-card-icon delivery-icon">
                        <i class="fa-solid fa-motorcycle"></i>
                    </div>
                    <div class="order-type-card-info">
                        <div class="order-type-card-header">
                            <span class="order-type-name">{{ __('menu.delivery') }}</span>
                            <span class="order-type-status-pill delivery-pill" x-show="orderType === 'delivery'">
                                <i class="fa-solid fa-check"></i>
                            </span>
                        </div>
                        <p class="order-type-card-desc">{{ __('menu.delivery_desc') }}</p>
                        
                        <div class="order-type-card-meta">
                            @if(($vendor->delivery_fee ?? 0) > 0)
                                <span class="order-type-fee-tag">
                                    <i class="fa-solid fa-receipt"></i>
                                    {{ number_format($vendor->delivery_fee) }} {{ $vendor->currency }}
                                </span>
                            @else
                                <span class="order-type-fee-tag is-free">
                                    <i class="fa-solid fa-bolt"></i>
                                    {{ __('menu.free_delivery') }}
                                </span>
                            @endif

                            @if($vendor->delivery_free_from)
                                <span class="order-type-meta-hint">
                                    {{ __('menu.free_delivery') }} > {{ number_format($vendor->delivery_free_from) }} {{ $vendor->currency }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="order-type-arrow">
                        <i class="fa-solid fa-chevron-right"></i>
                    </div>
                </div>
            </template>

            <!-- Option 2: Takeaway / Pickup -->
            <template x-if="takeawayEnabled">
                <div class="order-type-card" 
                     :class="{ 'is-selected': orderType === 'takeaway' }"
                     @click="selectInitialOrderType('takeaway')">
                    <div class="order-type-card-glow"></div>
                    <div class="order-type-card-icon takeaway-icon">
                        <i class="fa-solid fa-bag-shopping"></i>
                    </div>
                    <div class="order-type-card-info">
                        <div class="order-type-card-header">
                            <span class="order-type-name">{{ __('menu.takeaway') }}</span>
                            <span class="order-type-status-pill takeaway-pill" x-show="orderType === 'takeaway'">
                                <i class="fa-solid fa-check"></i>
                            </span>
                        </div>
                        <p class="order-type-card-desc">{{ __('menu.takeaway_desc') }}</p>

                        <div class="order-type-card-meta">
                            <span class="order-type-fee-tag is-free">
                                <i class="fa-solid fa-store"></i>
                                {{ $location?->name ?? 'Main Branch' }}
                            </span>
                        </div>
                    </div>
                    <div class="order-type-arrow">
                        <i class="fa-solid fa-chevron-right"></i>
                    </div>
                </div>
            </template>
        </div>

        <div class="order-type-modal-footer">
            <span class="order-type-footer-note">
                <i class="fa-solid fa-shield-halved"></i>
                {{ __('menu.change_order_type') }} - {{ __('menu.order_summary') }}
            </span>
        </div>
    </div>
</div>

<style>
.order-type-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 99990;
    background: rgba(15, 23, 42, 0.72);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    box-sizing: border-box;
}

.order-type-modal-card {
    width: 100%;
    max-width: 440px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 24px;
    box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.5);
    overflow: hidden;
    position: relative;
}

.order-type-modal-header {
    padding: 1.5rem 1.5rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    position: relative;
    border-bottom: 1px solid var(--border-color);
}

.order-type-badge-icon {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: rgba(16, 185, 129, 0.14);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.order-type-title {
    font-family: 'Outfit', sans-serif;
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--text-main);
    margin: 0;
    line-height: 1.25;
}

.order-type-subtitle {
    font-size: 0.82rem;
    color: var(--text-muted);
    margin: 0.2rem 0 0 0;
}

.order-type-close-btn {
    margin-left: auto;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    border: 1px solid var(--border-color);
    background: var(--bg-main);
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.order-type-close-btn:hover {
    color: var(--text-main);
    border-color: var(--text-muted);
    transform: scale(1.05);
}

.order-type-options-grid {
    padding: 1.25rem 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.order-type-card {
    position: relative;
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.15rem 1.1rem;
    background: var(--bg-main);
    border: 1.5px solid var(--border-color);
    border-radius: 18px;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    user-select: none;
    overflow: hidden;
}

.order-type-card:hover {
    transform: translateY(-2px);
    border-color: var(--primary);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.order-type-card.is-selected {
    border-color: var(--primary);
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), rgba(16, 185, 129, 0.02));
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.18);
}

.order-type-card-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}

.delivery-icon {
    background: rgba(59, 130, 246, 0.14);
    color: #3b82f6;
}

.takeaway-icon {
    background: rgba(245, 158, 11, 0.14);
    color: #f59e0b;
}

.order-type-card-info {
    flex: 1;
    min-width: 0;
}

.order-type-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.2rem;
}

.order-type-name {
    font-family: 'Outfit', sans-serif;
    font-weight: 800;
    font-size: 1.05rem;
    color: var(--text-main);
}

.order-type-status-pill {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    color: #ffffff;
    background: var(--primary);
}

.order-type-card-desc {
    font-size: 0.78rem;
    color: var(--text-muted);
    margin: 0 0 0.45rem 0;
    line-height: 1.35;
}

.order-type-card-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.order-type-fee-tag {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.15rem 0.55rem;
    border-radius: 8px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    color: var(--text-main);
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.order-type-fee-tag.is-free {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    border-color: rgba(16, 185, 129, 0.35);
}

.order-type-meta-hint {
    font-size: 0.7rem;
    color: var(--text-muted);
}

.order-type-arrow {
    color: var(--text-muted);
    font-size: 0.85rem;
    transition: transform 0.2s ease;
}

.order-type-card:hover .order-type-arrow {
    transform: translateX(3px);
    color: var(--primary);
}

.order-type-modal-footer {
    padding: 0.75rem 1.5rem 1.25rem;
    text-align: center;
}

.order-type-footer-note {
    font-size: 0.75rem;
    color: var(--text-muted);
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
</style>
