<!-- Waiter Call & Bill Request Service Modal (Full Screen) -->
<div x-show="showWaiterModal" 
     x-cloak
     role="dialog"
     aria-modal="true"
     aria-label="{{ __('menu.service_modal_title') }}"
     x-transition:enter="transition ease-out duration-250"
     x-transition:enter-start="opacity-0 transform translate-y-4"
     x-transition:enter-end="opacity-100 transform translate-y-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 transform translate-y-0"
     x-transition:leave-end="opacity-0 transform translate-y-4"
     style="position: fixed; inset: 0; width: 100%; height: 100%; height: 100dvh; background: var(--bg-card); z-index: 200; display: flex; flex-direction: column; overflow: hidden;">
    
    <div style="background: var(--bg-card); width: 100%; max-width: 640px; margin: 0 auto; height: 100%; height: 100dvh; display: flex; flex-direction: column; box-sizing: border-box;">
        
        <!-- Sticky Top Header -->
        <div style="padding: max(1rem, env(safe-area-inset-top)) 1.25rem 0.85rem 1.25rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); background: var(--bg-card); position: sticky; top: 0; z-index: 10;">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <div style="width: 38px; height: 38px; border-radius: 12px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0;">
                    <i class="fa-solid fa-bell-concierge"></i>
                </div>
                <div>
                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.35rem; font-weight: 800; color: var(--text-main); margin: 0; line-height: 1.2;">
                        {{ __('menu.service_modal_title') }}
                    </h3>
                    <p style="font-size: 0.78rem; color: var(--text-muted); margin: 0.15rem 0 0 0;">
                        {{ __('menu.service_modal_desc') }}
                    </p>
                </div>
            </div>
            <button type="button" @click="showWaiterModal = false" aria-label="{{ __('menu.close') }}" title="{{ __('menu.close') }}" style="background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; cursor: pointer; flex-shrink: 0;">
                ✕
            </button>
        </div>

        <!-- Scrollable Content -->
        <div style="flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; padding: 1.25rem 1.25rem calc(2.5rem + env(safe-area-inset-bottom)) 1.25rem; display: flex; flex-direction: column;">
            
            <!-- Service Choice Section Heading -->
            <div style="margin-bottom: 0.75rem; display: flex; align-items: center; justify-content: space-between;">
                <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
                    {{ __('menu.choose_service_type') }}
                </span>
                <span style="font-size: 0.75rem; color: var(--primary); font-weight: 600;">
                    {{ __('menu.three_options') }}
                </span>
            </div>

            <!-- Premium Service Choice Cards -->
            <div class="service-choices-grid">
                
                <!-- 1. Call Waiter Card -->
                <div class="service-choice-card" 
                     :class="{ 'is-selected is-waiter': serviceType === 'call_waiter' }"
                     @click="serviceType = 'call_waiter'">
                    <div class="service-card-left">
                        <div class="service-icon-box waiter-icon">
                            <i class="fa-solid fa-bell"></i>
                        </div>
                        <div class="service-card-text">
                            <div class="service-title-row">
                                <span class="service-title">{{ __('menu.call_waiter') }}</span>
                                <span class="service-mini-tag tag-amber">
                                    <i class="fa-solid fa-bolt"></i> {{ __('menu.fast_tag') }}
                                </span>
                            </div>
                            <div class="service-desc">
                                {{ __('menu.waiter_will_approach_table') }}
                            </div>
                        </div>
                    </div>
                    <div class="service-radio-pill" :class="{ 'active': serviceType === 'call_waiter' }">
                        <i class="fa-solid fa-check" x-show="serviceType === 'call_waiter'"></i>
                    </div>
                </div>

                <!-- 2. Bill: Cash Card -->
                <div class="service-choice-card" 
                     :class="{ 'is-selected is-cash': serviceType === 'bill_cash' }"
                     @click="serviceType = 'bill_cash'">
                    <div class="service-card-left">
                        <div class="service-icon-box cash-icon">
                            <i class="fa-solid fa-money-bill-wave"></i>
                        </div>
                        <div class="service-card-text">
                            <div class="service-title-row">
                                <span class="service-title">{{ __('menu.request_bill_cash') }}</span>
                                <span class="service-mini-tag tag-emerald">
                                    <i class="fa-solid fa-coins"></i> {{ __('menu.cash_tag') }}
                                </span>
                            </div>
                            <div class="service-desc">
                                {{ __('menu.pay_with_cash_desc') }}
                            </div>
                        </div>
                    </div>
                    <div class="service-radio-pill" :class="{ 'active': serviceType === 'bill_cash' }">
                        <i class="fa-solid fa-check" x-show="serviceType === 'bill_cash'"></i>
                    </div>
                </div>

                <!-- 3. Bill: Card Card -->
                <div class="service-choice-card" 
                     :class="{ 'is-selected is-card': serviceType === 'bill_card' }"
                     @click="serviceType = 'bill_card'">
                    <div class="service-card-left">
                        <div class="service-icon-box card-icon">
                            <i class="fa-solid fa-credit-card"></i>
                        </div>
                        <div class="service-card-text">
                            <div class="service-title-row">
                                <span class="service-title">{{ __('menu.request_bill_card') }}</span>
                                <span class="service-mini-tag tag-blue">
                                    <i class="fa-solid fa-wifi"></i> {{ __('menu.pay_pos_terminal') }}
                                </span>
                            </div>
                            <div class="service-desc">
                                {{ __('menu.pay_with_pos_desc') }}
                            </div>
                        </div>
                    </div>
                    <div class="service-radio-pill" :class="{ 'active': serviceType === 'bill_card' }">
                        <i class="fa-solid fa-check" x-show="serviceType === 'bill_card'"></i>
                    </div>
                </div>
            </div>

            <!-- Table Number Card Section (Locked to Scanned QR Table) -->
            <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.15rem; margin: 1.25rem 0 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.65rem; flex-wrap: wrap; gap: 0.35rem;">
                    <label style="font-size: 0.88rem; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 0.45rem;">
                        <i class="fa-solid fa-chair" style="color: var(--primary);"></i>
                        <span>{{ __('menu.specify_table') }}</span>
                    </label>
                    <span style="font-size: 0.72rem; color: #10b981; font-weight: 700; background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.2rem 0.6rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.35rem;">
                        <i class="fa-solid fa-lock"></i> {{ __('menu.fixed_qr') }}
                    </span>
                </div>
                
                <div style="position: relative;">
                    <input type="text" 
                           x-model="serviceTable" 
                           readonly
                           disabled
                           class="service-table-input is-locked"
                           style="width: 100%; padding: 0.85rem 2.5rem 0.85rem 2.5rem; border-radius: 12px; border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 1rem; font-weight: 700; outline: none; transition: all 0.2s ease; box-sizing: border-box; cursor: not-allowed; pointer-events: none; opacity: 0.95;">
                    <div style="position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.95rem; pointer-events: none;">
                        #
                    </div>
                    <div style="position: absolute; right: 0.9rem; top: 50%; transform: translateY(-50%); color: #10b981; font-size: 0.95rem; pointer-events: none;" title="{{ __('menu.fixed_qr') }}">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                </div>
                <div style="font-size: 0.75rem; color: #10b981; margin-top: 0.45rem; line-height: 1.35; display: flex; align-items: center; gap: 0.35rem;">
                    <i class="fa-solid fa-qrcode"></i>
                    <span>{{ __('menu.fixed_qr_notice') }}</span>
                </div>
            </div>

            <!-- Action Buttons (Submit & Cancel) -->
            <div style="margin-top: auto; display: flex; flex-direction: column; gap: 0.75rem;">
                <button type="button" 
                        @click="submitServiceCall()" 
                        :disabled="isCallingService"
                        class="service-submit-btn"
                        :class="{
                            'btn-waiter': serviceType === 'call_waiter',
                            'btn-cash': serviceType === 'bill_cash',
                            'btn-card': serviceType === 'bill_card'
                        }">
                    <i x-show="!isCallingService" :class="{
                        'fa-solid fa-bell': serviceType === 'call_waiter',
                        'fa-solid fa-money-bill-wave': serviceType === 'bill_cash',
                        'fa-solid fa-credit-card': serviceType === 'bill_card'
                    }"></i>
                    <i x-show="isCallingService" class="fa-solid fa-spinner fa-spin"></i>
                    <span x-text="serviceType === 'call_waiter' ? '{{ __('menu.call_waiter') }}' : (serviceType === 'bill_cash' ? '{{ __('menu.request_bill_cash') }}' : '{{ __('menu.request_bill_card') }}')"></span>
                </button>

                <button type="button" 
                        @click="showWaiterModal = false" 
                        style="width: 100%; padding: 0.85rem; border-radius: 14px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-muted); font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s;">
                    {{ __('menu.cancel') }} ({{ __('menu.continue_browsing_menu') }})
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Service Choices Grid & Card Styling */
    .service-choices-grid {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .service-choice-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 1.15rem;
        border-radius: 18px;
        border: 2px solid var(--border-color);
        background: var(--bg-main);
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
        overflow: hidden;
        user-select: none;
        -webkit-tap-highlight-color: transparent;
    }

    .service-choice-card:hover {
        transform: translateY(-2px);
        border-color: rgba(255, 255, 255, 0.2);
    }

    .service-choice-card:active {
        transform: scale(0.98);
    }

    /* Selected States with Theme Colors */
    .service-choice-card.is-selected.is-waiter {
        border-color: #f59e0b;
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.14), rgba(245, 158, 11, 0.03));
        box-shadow: 0 8px 24px -4px rgba(245, 158, 11, 0.25);
    }

    .service-choice-card.is-selected.is-cash {
        border-color: #10b981;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.14), rgba(16, 185, 129, 0.03));
        box-shadow: 0 8px 24px -4px rgba(16, 185, 129, 0.25);
    }

    .service-choice-card.is-selected.is-card {
        border-color: #3b82f6;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.14), rgba(59, 130, 246, 0.03));
        box-shadow: 0 8px 24px -4px rgba(59, 130, 246, 0.25);
    }

    .service-card-left {
        display: flex;
        align-items: center;
        gap: 0.9rem;
        flex: 1;
    }

    .service-icon-box {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
        transition: transform 0.25s ease;
    }

    .service-choice-card.is-selected .service-icon-box {
        transform: scale(1.08);
    }

    .waiter-icon {
        background: rgba(245, 158, 11, 0.18);
        color: #f59e0b;
        border: 1px solid rgba(245, 158, 11, 0.3);
    }

    .cash-icon {
        background: rgba(16, 185, 129, 0.18);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, 0.3);
    }

    .card-icon {
        background: rgba(59, 130, 246, 0.18);
        color: #3b82f6;
        border: 1px solid rgba(59, 130, 246, 0.3);
    }

    .service-card-text {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
    }

    .service-title-row {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .service-title {
        font-family: 'Outfit', sans-serif;
        font-size: 1.05rem;
        font-weight: 800;
        color: var(--text-main);
    }

    .service-mini-tag {
        font-size: 0.68rem;
        font-weight: 700;
        padding: 0.15rem 0.45rem;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .tag-amber {
        background: rgba(245, 158, 11, 0.15);
        color: #f59e0b;
        border: 1px solid rgba(245, 158, 11, 0.3);
    }

    .tag-emerald {
        background: rgba(16, 185, 129, 0.15);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, 0.3);
    }

    .tag-blue {
        background: rgba(59, 130, 246, 0.15);
        color: #3b82f6;
        border: 1px solid rgba(59, 130, 246, 0.3);
    }

    .service-desc {
        font-size: 0.78rem;
        color: var(--text-muted);
        line-height: 1.35;
    }

    /* Radio Indicator Pill */
    .service-radio-pill {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        border: 2px solid var(--border-color);
        background: var(--bg-card);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 0.8rem;
        flex-shrink: 0;
        transition: all 0.25s ease;
        margin-left: 0.75rem;
    }

    .service-choice-card.is-selected.is-waiter .service-radio-pill {
        border-color: #f59e0b;
        background: #f59e0b;
        box-shadow: 0 2px 10px rgba(245, 158, 11, 0.4);
    }

    .service-choice-card.is-selected.is-cash .service-radio-pill {
        border-color: #10b981;
        background: #10b981;
        box-shadow: 0 2px 10px rgba(16, 185, 129, 0.4);
    }

    .service-choice-card.is-selected.is-card .service-radio-pill {
        border-color: #3b82f6;
        background: #3b82f6;
        box-shadow: 0 2px 10px rgba(59, 130, 246, 0.4);
    }

    /* Input Focus Styling */
    .service-table-input:focus {
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb, 225, 29, 72), 0.2);
    }

    .service-table-input.is-locked {
        cursor: not-allowed !important;
        background: rgba(16, 185, 129, 0.07) !important;
        border-color: rgba(16, 185, 129, 0.35) !important;
        color: var(--text-main) !important;
        user-select: none !important;
    }

    /* Primary Submit Button Styling */
    .service-submit-btn {
        width: 100%;
        padding: 1rem 1.25rem;
        border-radius: 16px;
        border: none;
        color: #ffffff;
        font-family: 'Outfit', sans-serif;
        font-weight: 800;
        font-size: 1.05rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.65rem;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
    }

    .service-submit-btn:hover {
        opacity: 0.95;
        transform: translateY(-1px);
    }

    .service-submit-btn:active {
        transform: scale(0.98);
    }

    .service-submit-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .service-submit-btn.btn-waiter {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        box-shadow: 0 6px 20px rgba(245, 158, 11, 0.35);
    }

    .service-submit-btn.btn-cash {
        background: linear-gradient(135deg, #10b981, #059669);
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
    }

    .service-submit-btn.btn-card {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.35);
    }
</style>
