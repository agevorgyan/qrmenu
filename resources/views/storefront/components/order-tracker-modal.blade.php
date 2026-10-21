<!-- Order Real-Time Tracker Screen Modal (Full Screen) -->
<div x-show="showOrderTracker" 
     style="position: fixed; inset: 0; width: 100%; height: 100%; height: 100dvh; background: var(--bg-card); z-index: 200; display: flex; flex-direction: column; overflow: hidden;" 
     x-cloak
     x-transition:enter="transition ease-out duration-250"
     x-transition:enter-start="opacity-0 transform translate-y-4"
     x-transition:enter-end="opacity-100 transform translate-y-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 transform translate-y-0"
     x-transition:leave-end="opacity-0 transform translate-y-4">

    <div style="background: var(--bg-card); width: 100%; max-width: 640px; margin: 0 auto; height: 100%; height: 100dvh; display: flex; flex-direction: column; box-sizing: border-box;">
        
        <!-- Sticky Top Header -->
        <div style="padding: max(1rem, env(safe-area-inset-top)) 1.25rem 0.85rem 1.25rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); background: var(--bg-card); position: sticky; top: 0; z-index: 10;">
            <div>
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;">
                    <span class="live-pulse-dot"></span>
                    <span style="font-size: 0.75rem; font-weight: 700; color: #10b981; text-transform: uppercase; letter-spacing: 0.05em;">
                        {{ __('menu.live_status_badge') }}
                    </span>
                    <template x-if="activeOrder?.table_number">
                        <span style="font-size: 0.75rem; background: var(--bg-main); border: 1px solid var(--border-color); padding: 0.15rem 0.5rem; border-radius: 6px; color: var(--text-muted); font-weight: 600;" x-text="activeOrder.table_number"></span>
                    </template>
                </div>
                <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.35rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ __('menu.order_number_label') }}<span x-text="activeOrder?.order_number"></span>
                </h2>
            </div>
            <button type="button" @click="closeOrderTracker(false)" style="background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; cursor: pointer; flex-shrink: 0;">
                ✕
            </button>
        </div>

        <!-- Scrollable Content -->
        <div style="flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; padding: 1.25rem 1.25rem calc(2.5rem + env(safe-area-inset-bottom)) 1.25rem;">

        <!-- Dynamic Status Hero Card -->
        <div style="background: linear-gradient(135deg, rgba(var(--primary-rgb, 225, 29, 72), 0.12), rgba(var(--primary-rgb, 225, 29, 72), 0.03)); border: 1.5px solid var(--primary); border-radius: 20px; padding: 1.5rem 1.25rem; text-align: center; margin-bottom: 1.5rem; position: relative; overflow: hidden;">
            <div style="width: 72px; height: 72px; border-radius: 50%; background: var(--primary); color: #ffffff; display: inline-flex; align-items: center; justify-content: center; font-size: 1.9rem; margin-bottom: 0.85rem; box-shadow: 0 8px 25px rgba(var(--primary-rgb, 225, 29, 72), 0.4); animation: heroIconPulse 2.5s infinite;">
                <i :class="activeOrder?.status_icon || 'fa-solid fa-utensils'"></i>
            </div>
            
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.35rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.35rem;" x-text="activeOrder?.status_label"></h3>
            <p style="font-size: 0.9rem; color: var(--text-muted); max-width: 320px; margin: 0 auto; line-height: 1.45;" x-text="activeOrder?.status_desc"></p>

            <template x-if="activeOrder?.created_at_time">
                <div style="margin-top: 0.75rem; font-size: 0.78rem; color: var(--text-muted); display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                    <i class="fa-regular fa-clock"></i>
                    <span>{{ __('menu.order_time') }}: <strong x-text="activeOrder.created_at_time"></strong> (<span x-text="activeOrder.created_at_human"></span>)</span>
                </div>
            </template>
        </div>

        <!-- 4-Step Progress Stepper -->
        <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.25rem 1rem; margin-bottom: 1.5rem;">
            <div class="tracker-steps-container">
                <!-- Step 1: Գրանցված է (Pending) -->
                <div class="tracker-step" :class="{ 'completed': (activeOrder?.status_step || 1) >= 1, 'current': (activeOrder?.status_step || 1) === 1 }">
                    <div class="step-circle">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div class="step-text">{{ __('menu.status_pending') }}</div>
                </div>
                
                <div class="step-connector" :class="{ 'filled': (activeOrder?.status_step || 1) >= 2 }"></div>

                <!-- Step 2: Ընդունված է (Accepted) -->
                <div class="tracker-step" :class="{ 'completed': (activeOrder?.status_step || 1) >= 2, 'current': (activeOrder?.status_step || 1) === 2 }">
                    <div class="step-circle">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <div class="step-text">{{ __('menu.status_accepted') }}</div>
                </div>

                <div class="step-connector" :class="{ 'filled': (activeOrder?.status_step || 1) >= 3 }"></div>

                <!-- Step 3: Պատրաստվում է (Preparing) -->
                <div class="tracker-step" :class="{ 'completed': (activeOrder?.status_step || 1) >= 3, 'current': (activeOrder?.status_step || 1) === 3 }">
                    <div class="step-circle">
                        <i class="fa-solid fa-utensils"></i>
                    </div>
                    <div class="step-text">{{ __('menu.status_preparing') }}</div>
                </div>

                <div class="step-connector" :class="{ 'filled': (activeOrder?.status_step || 1) >= 4 }"></div>

                <!-- Step 4: Մոտենում է (Ready / Serving) -->
                <div class="tracker-step" :class="{ 'completed': (activeOrder?.status_step || 1) >= 4, 'current': (activeOrder?.status_step || 1) === 4 }">
                    <div class="step-circle">
                        <i class="fa-solid fa-bell-concierge"></i>
                    </div>
                    <div class="step-text">{{ __('menu.status_ready') }}</div>
                </div>
            </div>

            <!-- Cancelled Notice if applicable -->
            <template x-if="activeOrder?.status === 'cancelled'">
                <div style="margin-top: 1rem; padding: 0.75rem 1rem; background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; border-radius: 12px; color: #ef4444; font-size: 0.85rem; font-weight: 700; display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>{{ __('menu.status_desc_cancelled') }}</span>
                </div>
            </template>
        </div>

        <!-- Ordered Items Accordion / Summary -->
        <template x-if="activeOrder?.items && activeOrder.items.length > 0">
            <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.15rem; margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.6rem;">
                    <div style="font-size: 0.85rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-receipt" style="color: var(--primary);"></i>
                        <span>{{ __('menu.order_summary') }}</span>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-muted);" x-text="activeOrder.items.length + ' ուտեստ'"></div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.6rem;">
                    <template x-for="item in activeOrder.items" :key="item.id">
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 6px; padding: 0.1rem 0.4rem; font-weight: 700; font-size: 0.75rem; color: var(--primary);" x-text="item.quantity + 'x'"></span>
                                <div>
                                    <span style="font-weight: 600; color: var(--text-main);" x-text="item.name"></span>
                                    <template x-if="item.variation_name && item.variation_name !== 'Standard'">
                                        <span style="font-size: 0.75rem; color: var(--text-muted);" x-text="' (' + item.variation_name + ')'"></span>
                                    </template>
                                </div>
                            </div>
                            <span style="font-family: 'Outfit'; font-weight: 700; color: var(--accent);" x-text="Number(item.subtotal || (item.price * item.quantity)).toLocaleString() + ' ' + (activeOrder.currency || '{{ $vendor->currency }}')"></span>
                        </div>
                    </template>
                </div>

                <!-- Fees Breakdown (Service Fee or Delivery Fee) -->
                <template x-if="getActiveOrderServiceFee() > 0 || getActiveOrderDeliveryFee() > 0 || (activeOrder?.type || activeOrder?.order_type) === 'delivery'">
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.85rem; padding-top: 0.75rem; border-top: 1px dashed var(--border-color); font-size: 0.85rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; color: var(--text-muted);">
                            <span>{{ __('menu.subtotal') }}</span>
                            <span style="font-family: 'Outfit'; font-weight: 600;" x-text="Number(getActiveOrderSubtotal()).toLocaleString() + ' ' + (activeOrder?.currency || '{{ $vendor->currency }}')"></span>
                        </div>
                        <template x-if="getActiveOrderServiceFee() > 0">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: var(--text-muted); display: flex; align-items: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-bell-concierge" style="color: var(--primary); font-size: 0.8rem;"></i>
                                    <span>{{ __('menu.service_fee') }}</span>
                                </span>
                                <span style="font-family: 'Outfit'; font-weight: 700; color: #10b981;" x-text="'+' + Number(getActiveOrderServiceFee()).toLocaleString() + ' ' + (activeOrder?.currency || '{{ $vendor->currency }}')"></span>
                            </div>
                        </template>
                        <template x-if="(activeOrder?.type || activeOrder?.order_type) === 'delivery' || getActiveOrderDeliveryFee() > 0">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: var(--text-muted); display: flex; align-items: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-motorcycle" style="color: #3b82f6; font-size: 0.8rem;"></i>
                                    <span>{{ __('menu.delivery_fee') }}</span>
                                </span>
                                <template x-if="getActiveOrderDeliveryFee() === 0">
                                    <span style="font-size: 0.75rem; font-weight: 800; color: #10b981; background: rgba(16, 185, 129, 0.15); padding: 0.15rem 0.5rem; border-radius: 6px;">{{ __('menu.free_delivery') }}</span>
                                </template>
                                <template x-if="getActiveOrderDeliveryFee() > 0">
                                    <span style="font-family: 'Outfit'; font-weight: 700; color: var(--text-main);" x-text="'+' + Number(getActiveOrderDeliveryFee()).toLocaleString() + ' ' + (activeOrder?.currency || '{{ $vendor->currency }}')"></span>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.85rem; padding-top: 0.75rem; border-top: 1px dashed var(--border-color);">
                    <span style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">Ընդհանուր գումար</span>
                    <span style="font-family: 'Outfit'; font-weight: 800; font-size: 1.2rem; color: var(--primary);" x-text="Number(activeOrder?.total_amount || 0).toLocaleString() + ' ' + (activeOrder?.currency || '{{ $vendor->currency }}')"></span>
                </div>
            </div>
        </template>

        <!-- Actions -->
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            @if(!empty($table))
            <!-- Call Waiter from Tracker (Only when at table) -->
            <button type="button" 
                    @click="closeOrderTracker(false); openWaiterModal('call_waiter')" 
                    style="width: 100%; padding: 0.85rem; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 14px; font-weight: 700; font-size: 0.95rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                <i class="fa-solid fa-bell" style="color: var(--primary);"></i>
                <span>{{ __('menu.call_waiter') }}</span>
            </button>
            @endif

            <!-- Close and keep tracking -->
            <button type="button" 
                    @click="closeOrderTracker(false)" 
                    style="width: 100%; padding: 0.85rem; background: var(--primary); color: #ffffff; border: none; border-radius: 14px; font-weight: 800; font-size: 1rem; cursor: pointer; box-shadow: 0 4px 15px rgba(var(--primary-rgb, 225, 29, 72), 0.35);">
                {{ __('menu.close') }} (Շարունակել դիտել մենյուն)
            </button>

            <!-- Dismiss / Clear tracking (Shown only when order completed or cancelled) -->
            <template x-if="['completed', 'cancelled'].includes(activeOrder?.status)">
                <button type="button" 
                        @click="clearActiveOrder()" 
                        style="width: 100%; padding: 0.7rem; background: transparent; color: var(--text-muted); border: 1px dashed var(--border-color); border-radius: 14px; font-weight: 600; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.45rem; transition: all 0.2s;">
                    <i class="fa-solid fa-xmark" style="font-size: 0.85rem;"></i>
                    <span>Փակել ծանուցումը / Ավարտել հետևումը</span>
                </button>
            </template>
        </div>
    </div>
</div>
</div>

<style>
    .tracker-steps-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        padding: 0.5rem 0;
    }

    .tracker-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.4rem;
        z-index: 2;
        flex: 1;
    }

    .step-circle {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: var(--bg-card);
        border: 2px solid var(--border-color);
        color: var(--text-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .tracker-step.completed .step-circle {
        background: var(--primary);
        border-color: var(--primary);
        color: #ffffff;
    }

    .tracker-step.current .step-circle {
        background: var(--primary);
        border-color: var(--primary);
        color: #ffffff;
        box-shadow: 0 0 0 5px rgba(var(--primary-rgb, 225, 29, 72), 0.25);
        animation: currentStepBounce 1.5s infinite alternate;
    }

    .step-text {
        font-size: 0.68rem;
        font-weight: 700;
        color: var(--text-muted);
        text-align: center;
        line-height: 1.15;
    }

    .tracker-step.completed .step-text,
    .tracker-step.current .step-text {
        color: var(--text-main);
    }

    .step-connector {
        flex: 1;
        height: 3px;
        background: var(--border-color);
        margin: 0 -4px 1.4rem -4px;
        z-index: 1;
        transition: background 0.3s ease;
    }

    .step-connector.filled {
        background: var(--primary);
    }

    @keyframes currentStepBounce {
        from { transform: scale(1); }
        to { transform: scale(1.08); }
    }

    @keyframes heroIconPulse {
        0%, 100% { transform: scale(1); box-shadow: 0 8px 25px rgba(var(--primary-rgb, 225, 29, 72), 0.4); }
        50% { transform: scale(1.05); box-shadow: 0 12px 30px rgba(var(--primary-rgb, 225, 29, 72), 0.6); }
    }
</style>
