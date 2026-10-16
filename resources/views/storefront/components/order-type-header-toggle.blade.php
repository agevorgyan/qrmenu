<!-- Persistent Order Type Header Switcher / Table Banner -->
<div id="storefront-order-type-toggle" class="storefront-order-type-bar" x-cloak>
    <!-- Direct Visitor Mode: Delivery vs Takeaway Segmented Toggle -->
    <template x-if="!isTableFixed && (deliveryEnabled || takeawayEnabled)">
        <div class="order-channel-segmented-control" role="tablist">
            <template x-if="deliveryEnabled">
                <button type="button" 
                        role="tab"
                        :aria-selected="orderType === 'delivery'"
                        @click="setOrderType('delivery')"
                        class="channel-tab-btn"
                        :class="{ 'is-active': orderType === 'delivery' }"
                        id="channel-tab-delivery">
                    <i class="fa-solid fa-motorcycle channel-tab-icon"></i>
                    <span class="channel-tab-label">{{ __('menu.delivery') }}</span>
                    @if(($vendor->delivery_fee ?? 0) === 0)
                        <span class="channel-mini-badge">{{ __('menu.free_delivery') }}</span>
                    @endif
                </button>
            </template>

            <template x-if="takeawayEnabled">
                <button type="button" 
                        role="tab"
                        :aria-selected="orderType === 'takeaway'"
                        @click="setOrderType('takeaway')"
                        class="channel-tab-btn"
                        :class="{ 'is-active': orderType === 'takeaway' }"
                        id="channel-tab-takeaway">
                    <i class="fa-solid fa-bag-shopping channel-tab-icon"></i>
                    <span class="channel-tab-label">{{ __('menu.takeaway') }}</span>
                </button>
            </template>
        </div>
    </template>

    <!-- Table QR Scanned Mode: Locked Dine-In Stand Identifier -->
    <template x-if="isTableFixed">
        <div class="table-fixed-indicator">
            <span class="table-fixed-dot"></span>
            <i class="fa-solid fa-utensils"></i>
            <span class="table-fixed-text">
                {{ __('menu.dine_in') }} • <strong x-text="tableNumber"></strong>
            </span>
            <span class="table-fixed-qr-pill">
                <i class="fa-solid fa-qrcode"></i> QR
            </span>
        </div>
    </template>
</div>

<style>
.storefront-order-type-bar {
    padding: 0.75rem 1.25rem 0.25rem;
    display: flex;
    justify-content: center;
    position: relative;
    z-index: 10;
}

.order-channel-segmented-control {
    width: 100%;
    max-width: 420px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 4px;
    display: flex;
    gap: 4px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}

.channel-tab-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.65rem 0.9rem;
    border: none;
    border-radius: 10px;
    background: transparent;
    color: var(--text-muted);
    font-family: 'Outfit', sans-serif;
    font-size: 0.88rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    user-select: none;
}

.channel-tab-btn:hover {
    color: var(--text-main);
    background: rgba(255, 255, 255, 0.04);
}

.channel-tab-btn.is-active {
    background: var(--primary);
    color: #ffffff;
    box-shadow: 0 4px 14px var(--primary-glow, rgba(16, 185, 129, 0.35));
}

.channel-tab-icon {
    font-size: 1rem;
}

.channel-mini-badge {
    font-size: 0.65rem;
    font-weight: 800;
    padding: 0.1rem 0.4rem;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
    letter-spacing: 0.02em;
}

.table-fixed-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.5rem 1.1rem;
    background: var(--bg-card);
    border: 1.5px solid var(--primary);
    border-radius: 9999px;
    color: var(--text-main);
    font-size: 0.88rem;
    font-weight: 700;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}

.table-fixed-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--primary);
    box-shadow: 0 0 10px var(--primary);
    animation: tablePulse 2s infinite ease-in-out;
}

@keyframes tablePulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(1.3); }
}

.table-fixed-qr-pill {
    font-size: 0.68rem;
    font-weight: 800;
    background: rgba(16, 185, 129, 0.15);
    color: var(--primary);
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
</style>
