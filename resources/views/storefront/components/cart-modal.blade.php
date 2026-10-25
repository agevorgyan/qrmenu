<!-- Modern Cart & Checkout Modal Component -->
<div x-show="showCartModal" 
     x-cloak
     @click.self="showCartModal = false"
     x-transition:enter="cart-modal-fade-enter"
     x-transition:enter-start="cart-modal-fade-enter-start"
     x-transition:enter-end="cart-modal-fade-enter-end"
     x-transition:leave="cart-modal-fade-leave"
     x-transition:leave-start="cart-modal-fade-enter-end"
     x-transition:leave-end="cart-modal-fade-enter-start"
     class="cart-modal-overlay">
    
    <div class="cart-modal-sheet"
         x-show="showCartModal"
         x-transition:enter="cart-sheet-slide-enter"
         x-transition:enter-start="cart-sheet-slide-enter-start"
         x-transition:enter-end="cart-sheet-slide-enter-end"
         x-transition:leave="cart-sheet-slide-leave"
         x-transition:leave-start="cart-sheet-slide-enter-end"
         x-transition:leave-end="cart-sheet-slide-enter-start">
        
        <!-- Drag Handle on Mobile -->
        <div class="cart-drag-handle" @click="showCartModal = false"></div>

        <!-- Sticky Header -->
        <div class="cart-modal-header">
            <div class="cart-header-left">
                <div class="cart-header-icon-wrap">
                    <i class="fa-solid fa-basket-shopping"></i>
                </div>
                <div>
                    <h3 class="cart-header-title">{{ __('menu.order_summary') }}</h3>
                    <div class="cart-header-subtitle" x-show="cart.length > 0">
                        <span x-text="cartTotalCount"></span>
                        <span x-text="' ' + (cartTotalCount === 1 ? '{{ __('menu.items_count') }}' : '{{ __('menu.items_count') }}')"></span>
                    </div>
                </div>
            </div>
            <button type="button" 
                    @click="showCartModal = false" 
                    class="cart-close-btn"
                    aria-label="{{ __('menu.close') }}"
                    title="{{ __('menu.close') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Scrollable Modal Body -->
        <div class="cart-modal-body">

            <!-- Empty Cart State -->
            <div x-show="cart.length === 0" class="cart-empty-state">
                <div class="cart-empty-icon-circle">
                    <i class="fa-solid fa-basket-shopping"></i>
                </div>
                <h4 class="cart-empty-title">{{ __('menu.cart_empty') }}</h4>
                <p class="cart-empty-desc">{{ __('menu.cart_empty_desc') }}</p>
                <button type="button" @click="showCartModal = false" class="cart-empty-cta-btn">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>{{ __('menu.browse_menu') }}</span>
                </button>
            </div>

            <!-- Active Cart Items and Checkout Form -->
            <div x-show="cart.length > 0" class="cart-content-stack">
                
                <!-- Active Order Appending Notice -->
                <template x-if="activeOrder && !['completed', 'cancelled'].includes(activeOrder.status)">
                    <div class="cart-active-order-notice" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.14), rgba(16, 185, 129, 0.05)); border: 1.5px solid #10b981; border-radius: 16px; padding: 0.85rem 1rem; display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1rem; box-shadow: 0 4px 16px rgba(16, 185, 129, 0.12);">
                        <div style="width: 34px; height: 34px; border-radius: 50%; background: #10b981; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);">
                            <i class="fa-solid fa-circle-plus"></i>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; margin-bottom: 0.25rem;">
                                <span style="font-weight: 800; font-size: 0.88rem; color: var(--text-main);">
                                    {{ __('menu.active_order_notice_title') }}
                                </span>
                                <span style="background: #10b981; color: #ffffff; font-size: 0.72rem; font-weight: 800; padding: 0.1rem 0.45rem; border-radius: 6px;" x-text="activeOrder.order_number"></span>
                                <span style="font-size: 0.75rem; font-weight: 700; color: #10b981;" x-text="'(' + activeOrder.status_label + ')'"></span>
                            </div>
                            <div style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.4;">
                                {{ __('menu.active_order_notice_desc') }}
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Items List Stack -->
                <div class="cart-items-container">
                    <div class="cart-section-badge">
                        <i class="fa-solid fa-utensils"></i>
                        <span>{{ __('menu.cart') }}</span>
                        <span class="cart-pill-count" x-text="cartTotalCount"></span>
                    </div>

                    <div class="cart-items-list">
                        <template x-for="(item, idx) in cart" :key="idx">
                            <div class="cart-item-card">
                                <div class="cart-item-info">
                                    <div class="cart-item-name" x-text="item.name"></div>
                                    <template x-if="item.variation_name && item.variation_name !== 'Standard' && item.variation_name !== 'Standard Portion'">
                                        <div class="cart-item-variation">
                                            <span class="cart-item-var-dot"></span>
                                            <span x-text="item.variation_name"></span>
                                        </div>
                                    </template>
                                    <div class="cart-item-price-unit">
                                        <span x-text="Number(item.price).toLocaleString()"></span>
                                        <span class="cart-curr">{{ $vendor->currency }}</span>
                                    </div>
                                    <template x-if="(orderType === 'dine_in' && item.available_for_dine_in === false) || (orderType === 'takeaway' && item.available_for_takeaway === false) || (orderType === 'delivery' && item.available_for_delivery === false)">
                                        <div style="background: rgba(239, 68, 68, 0.12); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.72rem; font-weight: 700; margin-top: 0.35rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                                            <i class="fa-solid fa-triangle-exclamation"></i>
                                            <span>{{ __('menu.item_unavailable_for_channel') }}</span>
                                        </div>
                                    </template>
                                </div>


                                <div class="cart-item-controls">
                                    <!-- Stepper -->
                                    <div class="cart-stepper">
                                        <button type="button" 
                                                @click="changeQty(idx, -1)" 
                                                class="cart-stepper-btn"
                                                :class="{ 'is-remove': item.qty === 1 }"
                                                :title="item.qty === 1 ? '{{ __('menu.removed_from_cart') }}' : 'Decrease'"
                                                aria-label="Decrease quantity">
                                            <template x-if="item.qty === 1">
                                                <i class="fa-solid fa-trash-can cart-trash-icon"></i>
                                            </template>
                                            <template x-if="item.qty > 1">
                                                <i class="fa-solid fa-minus"></i>
                                            </template>
                                        </button>
                                        <span class="cart-stepper-val" x-text="item.qty"></span>
                                        <button type="button" 
                                                @click="changeQty(idx, 1)" 
                                                class="cart-stepper-btn cart-stepper-btn-plus"
                                                title="Increase"
                                                aria-label="Increase quantity">
                                            <i class="fa-solid fa-plus"></i>
                                        </button>
                                    </div>

                                    <!-- Item Subtotal -->
                                    <div class="cart-item-subtotal">
                                        <span x-text="Number(item.price * item.qty).toLocaleString()"></span>
                                        <span class="cart-subtotal-curr">{{ $vendor->currency }}</span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- AI Smart Recommendations / Cross-Selling & Up-Selling -->
                <template x-if="cartRecommendations && cartRecommendations.length > 0">
                    <div class="cart-ai-recs-container">
                        <div class="cart-ai-recs-header">
                            <div class="cart-ai-recs-title-wrap">
                                <span class="cart-ai-recs-badge">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i> AI Pairings
                                </span>
                                <span class="cart-ai-recs-title">{{ __('Հաճախ պատվիրում են միասին') }}</span>
                            </div>
                            <span class="cart-ai-recs-subtitle">{{ __('Կատարյալ համադրություններ Ձեր զամբյուղի հետ') }}</span>
                        </div>

                        <div class="cart-ai-recs-scroll">
                            <template x-for="rec in cartRecommendations" :key="rec.id">
                                <div class="cart-ai-rec-card">
                                    <template x-if="rec.image">
                                        <img :src="rec.image" :alt="rec.name" class="cart-ai-rec-img" loading="lazy">
                                    </template>
                                    <template x-if="!rec.image">
                                        <div class="cart-ai-rec-img-placeholder">
                                            <i class="fa-solid fa-utensils"></i>
                                        </div>
                                    </template>

                                    <div class="cart-ai-rec-body">
                                        <div class="cart-ai-rec-name" x-text="rec.name"></div>
                                        <div class="cart-ai-rec-reason" x-text="rec.reason"></div>

                                        <div class="cart-ai-rec-foot">
                                            <div class="cart-ai-rec-price">
                                                <span x-text="Number(rec.price).toLocaleString()"></span>
                                                <small style="font-size: 0.72rem; font-weight: 600;">{{ $vendor->currency }}</small>
                                            </div>

                                            <button type="button" 
                                                    @click="quickAddRec(rec)" 
                                                    class="cart-ai-rec-add-btn" 
                                                    title="{{ __('Ավելացնել') }}">
                                                <i class="fa-solid fa-plus"></i>
                                                <span>{{ __('Ավելացնել') }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <!-- Pre-Order Reservation Mode Banner in Cart -->
                <template x-if="isPreOrderMode">
                    <div style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.12), rgba(37, 99, 235, 0.05)); border: 1.5px solid var(--primary); border-radius: 14px; padding: 0.85rem 1rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; box-shadow: 0 2px 8px rgba(59, 130, 246, 0.35);">
                            <i class="fa-solid fa-calendar-days"></i>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-weight: 800; font-size: 0.9rem; color: var(--text-main);">
                                {{ __('menu.preorder_for_reservation') }}
                            </div>
                            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;" x-text="preOrderSummaryText || '{{ __('menu.items_prepared_on_arrival') }}'"></div>
                        </div>
                    </div>
                </template>

                <!-- Order Type Switcher (Dine-In vs Takeaway vs Delivery) -->
                <div class="cart-type-toggle-wrap" x-show="!isPreOrderMode && (isTableFixed || takeawayEnabled || deliveryEnabled)">
                    <template x-if="isTableFixed">
                        <button type="button" 
                                @click="orderType = 'dine_in'" 
                                :class="{ 'is-active': orderType === 'dine_in' }"
                                class="cart-type-btn">
                            <i class="fa-solid fa-utensils"></i>
                            <span>{{ __('menu.dine_in') }}</span>
                        </button>
                    </template>
                    <template x-if="!isTableFixed && takeawayEnabled">
                        <button type="button" 
                                @click="setOrderType('takeaway')" 
                                :class="{ 'is-active': orderType === 'takeaway' }"
                                class="cart-type-btn">
                            <i class="fa-solid fa-bag-shopping"></i>
                            <span>{{ __('menu.takeaway') }}</span>
                        </button>
                    </template>
                    <template x-if="!isTableFixed && deliveryEnabled">
                        <button type="button" 
                                @click="setOrderType('delivery')" 
                                :class="{ 'is-active': orderType === 'delivery' }"
                                class="cart-type-btn">
                            <i class="fa-solid fa-motorcycle"></i>
                            <span>{{ __('menu.delivery') }}</span>
                        </button>
                    </template>
                </div>


                <!-- Channel Operating Hours & Closing Alert Banner -->
                <template x-if="currentSchedule && currentSchedule.enabled">
                    <div style="margin-top: 0.75rem;">
                        <!-- Closed Alert -->
                        <template x-if="!isCurrentChannelOpen">
                            <div style="background: rgba(239, 68, 68, 0.12); border: 1.5px solid rgba(239, 68, 68, 0.35); border-radius: 14px; padding: 0.85rem 1rem; display: flex; align-items: center; gap: 0.75rem; color: #ef4444; font-size: 0.85rem;">
                                <i class="fa-solid fa-clock" style="font-size: 1.25rem; flex-shrink: 0;"></i>
                                <div style="flex: 1;">
                                    <div style="font-weight: 800; font-size: 0.88rem;">
                                        <span x-text="currentSchedule.notice_title || (orderType === 'delivery' ? '{{ __('menu.delivery_closed') }}' : (orderType === 'takeaway' ? '{{ __('menu.takeaway_closed') }}' : '{{ __('menu.kitchen_closed') }}'))"></span>
                                    </div>
                                    <div style="font-size: 0.78rem; opacity: 0.9; margin-top: 0.2rem;">
                                        {{ __('menu.operating_hours') }}: <strong x-text="currentSchedule.start_time + ' - ' + currentSchedule.end_time"></strong>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Closing Soon Warning -->
                        <template x-if="isCurrentChannelOpen && isCurrentChannelClosingSoon">
                            <div style="background: rgba(245, 158, 11, 0.12); border: 1.5px solid rgba(245, 158, 11, 0.35); border-radius: 14px; padding: 0.85rem 1rem; display: flex; align-items: center; gap: 0.75rem; color: #d97706; font-size: 0.85rem;">
                                <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.25rem; flex-shrink: 0; color: #f59e0b;"></i>
                                <div style="flex: 1;">
                                    <div style="font-weight: 800; font-size: 0.88rem; color: #b45309;" x-text="closingNotice.notice_title || closingNotice.message || '{{ __('menu.closing_soon_notice_title') }}'"></div>
                                    <div style="font-size: 0.78rem; opacity: 0.9; margin-top: 0.2rem; color: #b45309;">
                                        {{ __('menu.closing_time') }}: <strong x-text="currentSchedule.end_time"></strong> (<span x-text="closingNotice.minutes_left + ' {{ __('menu.minutes_left') }}'"></span>)
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Takeaway Notifications & Min Order Warning -->
                <template x-if="orderType === 'takeaway'">
                    <div style="display: flex; flex-direction: column; gap: 0.65rem; margin-top: 0.75rem;">
                        <!-- Min Takeaway Order Warning Banner -->
                        <template x-if="isBelowTakeawayMin">
                            <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 14px; padding: 0.85rem 1rem; display: flex; align-items: center; gap: 0.75rem; color: #ef4444; font-size: 0.85rem;">
                                <i class="fa-solid fa-circle-exclamation" style="font-size: 1.1rem; flex-shrink: 0;"></i>
                                <div style="flex: 1;">
                                    <div style="font-weight: 700;">
                                        {{ __('menu.min_takeaway_order_warning') }} <span x-text="Number(takeawayMinAmount).toLocaleString() + ' {{ $vendor->currency }}'"></span>
                                    </div>
                                    <div style="font-size: 0.78rem; opacity: 0.9; margin-top: 0.15rem;">
                                        {{ __('menu.add_more_for_free_delivery') }} <strong x-text="Number(takeawayMinRemaining).toLocaleString() + ' {{ $vendor->currency }}'"></strong>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Takeaway Pickup Branch Notice -->
                        <div style="background: rgba(139, 92, 246, 0.08); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 14px; padding: 0.75rem 1rem; display: flex; align-items: center; gap: 0.65rem; font-size: 0.82rem; color: var(--text-main);">
                            <i class="fa-solid fa-store" style="color: #8b5cf6; font-size: 1rem; flex-shrink: 0;"></i>
                            <div>
                                <span style="font-weight: 700;">{{ $location->name ?? __('menu.branch') }}</span> — {{ __('menu.takeaway_ready_notice') }}
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Delivery Notifications & Progress -->
                <template x-if="orderType === 'delivery'">
                    <div style="display: flex; flex-direction: column; gap: 0.65rem; margin-top: 0.75rem;">
                        <!-- Min Delivery Order Warning Banner -->
                        <template x-if="isBelowDeliveryMin">
                            <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 14px; padding: 0.85rem 1rem; display: flex; align-items: center; gap: 0.75rem; color: #ef4444; font-size: 0.85rem;">
                                <i class="fa-solid fa-circle-exclamation" style="font-size: 1.1rem; flex-shrink: 0;"></i>
                                <div style="flex: 1;">
                                    <div style="font-weight: 700;">
                                        {{ __('menu.min_delivery_order_warning') }} <span x-text="Number(deliveryMinAmount).toLocaleString() + ' {{ $vendor->currency }}'"></span>
                                    </div>
                                    <div style="font-size: 0.78rem; opacity: 0.9; margin-top: 0.15rem;">
                                        {{ __('menu.add_more_for_free_delivery') }} <strong x-text="Number(deliveryMinRemaining).toLocaleString() + ' {{ $vendor->currency }}'"></strong>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Free Delivery Motivation Threshold Progress Bar -->
                        <template x-if="deliveryFreeFrom !== null && deliveryFreeFrom > 0">
                            <div style="background: var(--bg-card, rgba(255,255,255,0.04)); border: 1px solid var(--border-color, rgba(255,255,255,0.08)); border-radius: 14px; padding: 0.85rem 1rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem; font-size: 0.82rem;">
                                    <template x-if="isDeliveryFree">
                                        <span style="font-weight: 800; color: #10b981; display: flex; align-items: center; gap: 0.4rem;">
                                            <i class="fa-solid fa-gift"></i> {{ __('menu.free_delivery_unlocked') }}
                                        </span>
                                    </template>
                                    <template x-if="!isDeliveryFree">
                                        <span style="color: var(--text-muted); display: flex; align-items: center; gap: 0.4rem;">
                                            <i class="fa-solid fa-truck-fast" style="color: #3b82f6;"></i>
                                            <span>{{ __('menu.add_more_for_free_delivery') }} <strong style="color: var(--text-main);" x-text="Number(freeDeliveryRemaining).toLocaleString() + ' {{ $vendor->currency }}'"></strong> {{ __('menu.for_free_delivery') }}</span>
                                        </span>
                                    </template>
                                    <span style="font-weight: 800; font-size: 0.75rem; color: var(--primary);" x-text="freeDeliveryProgress + '%'"></span>
                                </div>
                                <div style="width: 100%; height: 6px; background: rgba(150, 150, 150, 0.2); border-radius: 999px; overflow: hidden;">
                                    <div :style="'width: ' + freeDeliveryProgress + '%; background: linear-gradient(90deg, var(--primary), #10b981); height: 100%; border-radius: 999px; transition: width 0.4s ease;'"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Kitchen Notes Card -->
                <div class="cart-card-group">
                    <label class="cart-group-label">
                        <i class="fa-regular fa-comment-dots cart-label-icon"></i>
                        <span>{{ __('menu.order_notes') }}</span>
                    </label>
                    <div class="cart-textarea-wrap">
                        <textarea x-model="orderNotes" 
                                  class="cart-textarea" 
                                  rows="2" 
                                  placeholder="{{ __('menu.order_notes_placeholder') }}"></textarea>
                    </div>
                </div>

                <!-- Customer Details Card -->
                <div class="cart-card-group">
                    <label class="cart-group-label">
                        <i class="fa-regular fa-id-card cart-label-icon"></i>
                        <span>{{ __('menu.customer_details') }}</span>
                    </label>

                    <div class="cart-inputs-grid">
                        <!-- Name & Phone -->
                        <div class="cart-input-field-wrap">
                            <i class="fa-regular fa-user cart-input-prefix-icon"></i>
                            <input type="text" 
                                   x-model="customerName" 
                                   class="cart-input" 
                                   placeholder="{{ __('menu.full_name_placeholder') }}">
                        </div>

                        <!-- Phone with Country Flag & Strict Digit Validation -->
                        <div class="cart-phone-field-wrapper">
                            <div class="cart-phone-input-box" :class="{ 'has-error': phoneErrorMsg, 'is-valid': isPhoneValid() && phoneCleanDigits.length > 0 }">
                                <!-- Country Selector Pill -->
                                <button type="button" 
                                        @click="showPhoneCountryDropdown = !showPhoneCountryDropdown" 
                                        class="cart-phone-country-btn"
                                        title="{{ __('menu.phone_country') ?? 'Երկիր' }}">
                                    <span class="cart-flag-icon" x-text="selectedPhoneCountry.flag"></span>
                                    <span class="cart-dial-code" x-text="selectedPhoneCountry.dial"></span>
                                    <i class="fa-solid fa-chevron-down cart-country-caret" :class="{ 'is-open': showPhoneCountryDropdown }"></i>
                                </button>

                                <!-- National Number Input -->
                                <input type="tel" 
                                       id="cartCustomerPhoneInput"
                                       x-model="phoneNationalNumber" 
                                       @input="onPhoneInput($event)"
                                       @blur="validatePhoneOnBlur()"
                                       class="cart-phone-native-input" 
                                       :placeholder="(orderType === 'delivery' || orderType === 'takeaway') ? ('* ' + selectedPhoneCountry.placeholder) : selectedPhoneCountry.placeholder"
                                       :required="orderType === 'delivery' || orderType === 'takeaway'">

                                <!-- Live Digit Counter / Checkmark Status -->
                                <div class="cart-phone-status-indicator">
                                    <template x-if="isPhoneValid() && phoneCleanDigits.length > 0">
                                        <span class="cart-status-badge is-valid" title="Համարը վավեր է">
                                            <i class="fa-solid fa-circle-check"></i>
                                        </span>
                                    </template>
                                    <template x-if="phoneCleanDigits.length > 0 && !isPhoneValid()">
                                        <span class="cart-status-badge is-incomplete" :title="'Պահանջվում է ' + (selectedPhoneCountry.digits || '7-15') + ' նիշ'">
                                            <span x-text="phoneCleanDigits.length + '/' + (selectedPhoneCountry.digits || '10')"></span>
                                        </span>
                                    </template>
                                </div>
                            </div>

                            <!-- Country Selection Dropdown Popup -->
                            <div x-show="showPhoneCountryDropdown" 
                                 @click.outside="showPhoneCountryDropdown = false"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 transform scale-95 -translate-y-1"
                                 x-transition:enter-end="opacity-100 transform scale-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 transform scale-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 transform scale-95 -translate-y-1"
                                 class="cart-country-dropdown"
                                 style="display: none;">
                                <template x-for="item in phoneCountries" :key="item.code">
                                    <button type="button" 
                                            @click="selectPhoneCountry(item.code)" 
                                            class="cart-country-item"
                                            :class="{ 'is-selected': phoneCountryCode === item.code }">
                                        <span class="cart-item-flag" x-text="item.flag"></span>
                                        <span class="cart-item-name" x-text="item.name"></span>
                                        <span class="cart-item-dial" x-text="item.dial"></span>
                                        <span class="cart-item-digits" x-show="item.digits" x-text="'(' + item.digits + ' նիշ)'"></span>
                                    </button>
                                </template>
                            </div>

                            <!-- Phone Error Message -->
                            <template x-if="phoneErrorMsg">
                                <div class="cart-input-feedback-msg is-error">
                                    <i class="fa-solid fa-circle-exclamation"></i>
                                    <span x-text="phoneErrorMsg"></span>
                                </div>
                            </template>
                        </div>

                        <!-- Email with Live Validation -->
                        <div class="cart-input-field-wrap" style="position: relative;">
                            <i class="fa-regular fa-envelope cart-input-prefix-icon"></i>
                            <input type="email" 
                                   id="cartCustomerEmailInput"
                                   x-model="customerEmail" 
                                   @input="onEmailInput($event)"
                                   @blur="validateEmailOnBlur()"
                                   class="cart-input" 
                                   :class="{ 'has-error': emailErrorMsg, 'is-valid': isEmailValid() && customerEmail.trim().length > 0 }"
                                   placeholder="{{ __('menu.email_placeholder') }}">
                            
                            <!-- Email Status Indicator -->
                            <div class="cart-email-status-indicator" style="position: absolute; right: 0.85rem; display: flex; align-items: center; pointer-events: none;">
                                <template x-if="isEmailValid() && customerEmail.trim().length > 0">
                                    <i class="fa-solid fa-circle-check" style="color: #10b981; font-size: 0.95rem;"></i>
                                </template>
                                <template x-if="!isEmailValid() && customerEmail.trim().length > 0">
                                    <i class="fa-solid fa-circle-exclamation" style="color: #ef4444; font-size: 0.95rem;"></i>
                                </template>
                            </div>

                            <!-- Email Error Message -->
                            <template x-if="emailErrorMsg">
                                <div class="cart-input-feedback-msg is-error" style="position: absolute; top: calc(100% + 2px); left: 0;">
                                    <i class="fa-solid fa-circle-exclamation"></i>
                                    <span x-text="emailErrorMsg"></span>
                                </div>
                            </template>
                        </div>

                        <div class="cart-input-field-wrap">
                            <i class="fa-solid fa-cake-candles cart-input-prefix-icon"></i>
                            <input type="date" 
                                   x-model="customerBirthdate" 
                                   class="cart-input" 
                                   title="{{ __('menu.birthdate_placeholder') }}"
                                   placeholder="{{ __('menu.birthdate_placeholder') }}">
                        </div>
                    </div>

                    <!-- Delivery Address Input (Only when orderType === 'delivery') -->
                    <template x-if="orderType === 'delivery'">
                        <div class="cart-delivery-field-block">
                            <label class="cart-subfield-label">
                                <i class="fa-solid fa-location-dot"></i>
                                <span>{{ __('menu.delivery_address') }} <strong style="color: #ef4444;">*</strong></span>
                            </label>
                            <div class="cart-input-field-wrap">
                                <i class="fa-solid fa-motorcycle cart-input-prefix-icon" style="color: #f59e0b;"></i>
                                <input type="text" 
                                       x-model="deliveryAddress" 
                                       class="cart-input cart-delivery-input" 
                                       placeholder="{{ __('menu.delivery_address_placeholder') }}">
                            </div>
                        </div>
                    </template>

                    <!-- Table Selection (Only when orderType === 'dine_in') -->
                    <template x-if="orderType === 'dine_in'">
                        <div>
                            <div class="cart-table-wrap" :class="{ 'is-locked': isTableFixed }">
                                <div class="cart-table-field">
                                    <i class="fa-solid fa-chair cart-input-prefix-icon"></i>
                                    <input type="text" 
                                           x-model="tableNumber" 
                                           :readonly="isTableFixed"
                                           class="cart-input cart-table-input" 
                                           placeholder="{{ __('menu.table') }} {{ $table ?? '4' }}">
                                </div>
                                <template x-if="isTableFixed">
                                    <div class="cart-table-badge">
                                        <i class="fa-solid fa-lock"></i>
                                        <span>{{ __('menu.fixed_qr') }}</span>
                                    </div>
                                </template>
                            </div>

                            <template x-if="isTableFixed">
                                <div class="cart-table-hint">
                                    <i class="fa-solid fa-circle-check"></i>
                                    <span>{{ __('menu.fixed_qr_notice') }}</span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Payment Method Selection -->
                @php
                    $vendorPaymentSettings = $vendor->getPaymentSettings();
                    $cashEnabled = !empty($vendorPaymentSettings['cash_enabled']);
                    $posEnabled = !empty($vendorPaymentSettings['pos_terminal_enabled']);
                    $onlineEnabled = !empty($vendorPaymentSettings['online_enabled']);
                    $gateways = $vendorPaymentSettings['gateways'] ?? [];
                @endphp

                <div class="cart-card-group">
                    <label class="cart-group-label">
                        <i class="fa-solid fa-credit-card cart-label-icon"></i>
                        <span>{{ __('menu.payment_method') ?? 'Վճարման Եղանակ' }}</span>
                    </label>

                    <div class="cart-payment-grid">
                        @if($cashEnabled)
                        <div class="payment-method-tile" :class="{ 'is-active': paymentMethod === 'cash' }" @click="paymentMethod = 'cash'">
                            <span class="payment-tile-icon-wrap" style="color: #10b981; background: rgba(16, 185, 129, 0.12);">
                                <i class="fa-solid fa-money-bill-wave"></i>
                            </span>
                            <span class="payment-tile-label">{{ __('Կանխիկ տեղում') }}</span>
                            <template x-if="paymentMethod === 'cash'">
                                <span class="payment-tile-check"><i class="fa-solid fa-check"></i></span>
                            </template>
                        </div>
                        @endif

                        @if($posEnabled)
                        <div class="payment-method-tile" :class="{ 'is-active': paymentMethod === 'pos_terminal' }" @click="paymentMethod = 'pos_terminal'">
                            <span class="payment-tile-icon-wrap" style="color: #3b82f6; background: rgba(59, 130, 246, 0.12);">
                                <i class="fa-solid fa-credit-card"></i>
                            </span>
                            <span class="payment-tile-label">{{ __('POS Տերմինալ') }}</span>
                            <template x-if="paymentMethod === 'pos_terminal'">
                                <span class="payment-tile-check"><i class="fa-solid fa-check"></i></span>
                            </template>
                        </div>
                        @endif

                        @if($onlineEnabled)
                            @if(!empty($gateways['idram']['enabled']))
                            <div class="payment-method-tile" :class="{ 'is-active': paymentMethod === 'idram' }" @click="paymentMethod = 'idram'">
                                <span class="payment-tile-icon-wrap" style="color: #ffffff; background: #ff6f00; font-weight: 900; font-size: 0.8rem;">
                                    Id
                                </span>
                                <span class="payment-tile-label">Idram</span>
                                <template x-if="paymentMethod === 'idram'">
                                    <span class="payment-tile-check"><i class="fa-solid fa-check"></i></span>
                                </template>
                            </div>
                            @endif

                            @if(!empty($gateways['telcell']['enabled']))
                            <div class="payment-method-tile" :class="{ 'is-active': paymentMethod === 'telcell' }" @click="paymentMethod = 'telcell'">
                                <span class="payment-tile-icon-wrap" style="color: #ffffff; background: #e11d48; font-weight: 900; font-size: 0.8rem;">
                                    Tc
                                </span>
                                <span class="payment-tile-label">Telcell</span>
                                <template x-if="paymentMethod === 'telcell'">
                                    <span class="payment-tile-check"><i class="fa-solid fa-check"></i></span>
                                </template>
                            </div>
                            @endif

                            @if(!empty($gateways['fastshift']['enabled']))
                            <div class="payment-method-tile" :class="{ 'is-active': paymentMethod === 'fastshift' }" @click="paymentMethod = 'fastshift'">
                                <span class="payment-tile-icon-wrap" style="color: #ffffff; background: #2563eb; font-weight: 900; font-size: 0.8rem;">
                                    Fs
                                </span>
                                <span class="payment-tile-label">FastShift</span>
                                <template x-if="paymentMethod === 'fastshift'">
                                    <span class="payment-tile-check"><i class="fa-solid fa-check"></i></span>
                                </template>
                            </div>
                            @endif

                            @if(!empty($gateways['arca']['enabled']))
                            <div class="payment-method-tile" :class="{ 'is-active': paymentMethod === 'arca' }" @click="paymentMethod = 'arca'">
                                <span class="payment-tile-icon-wrap" style="color: #ffffff; background: #059669; font-weight: 900; font-size: 0.8rem;">
                                    Ar
                                </span>
                                <span class="payment-tile-label">ArCa / Bank</span>
                                <template x-if="paymentMethod === 'arca'">
                                    <span class="payment-tile-check"><i class="fa-solid fa-check"></i></span>
                                </template>
                            </div>
                            @endif

                            @if(!empty($gateways['stripe']['enabled']))
                            <div class="payment-method-tile" :class="{ 'is-active': paymentMethod === 'stripe' }" @click="paymentMethod = 'stripe'">
                                <span class="payment-tile-icon-wrap" style="color: #ffffff; background: #6366f1; font-size: 0.95rem;">
                                    <i class="fa-brands fa-stripe-s"></i>
                                </span>
                                <span class="payment-tile-label">Stripe</span>
                                <template x-if="paymentMethod === 'stripe'">
                                    <span class="payment-tile-check"><i class="fa-solid fa-check"></i></span>
                                </template>
                            </div>
                            @endif
                        @endif
                    </div>
                </div>

                <!-- Pricing Breakdown Summary Card -->
                <div class="cart-summary-card">
                    <div class="cart-summary-row">
                        <span class="cart-summary-label">{{ __('menu.subtotal') }}</span>
                        <span class="cart-summary-val">
                            <strong x-text="Number(cartSubtotal).toLocaleString()"></strong>
                            <span class="cart-summary-curr">{{ $vendor->currency }}</span>
                        </span>
                    </div>

                    <!-- Birthday Discount Row -->
                    <template x-if="birthdayDiscountAmount > 0">
                        <div class="cart-summary-row" style="background: rgba(236, 72, 153, 0.1); padding: 0.4rem 0.6rem; border-radius: 8px; margin: 0.35rem 0;">
                            <span class="cart-summary-label" style="color: #ec4899; font-weight: 700; display: flex; align-items: center; gap: 0.35rem;">
                                <i class="fa-solid fa-cake-candles"></i>
                                <span>{{ __('menu.birthday_discount') ?? 'Ծննդյան զեղչ' }}</span>
                            </span>
                            <span class="cart-summary-val" style="color: #ec4899; font-weight: 800;">
                                -<span x-text="Number(birthdayDiscountAmount).toLocaleString()"></span>
                                <span class="cart-summary-curr">{{ $vendor->currency }}</span>
                            </span>
                        </div>
                    </template>

                    <!-- Service Fee Row (Dine-in) -->
                    <template x-if="orderType === 'dine_in' && calculatedServiceFee > 0">
                        <div class="cart-summary-row">
                            <span class="cart-summary-label" style="display: flex; align-items: center; gap: 0.35rem;">
                                <i class="fa-solid fa-bell-concierge" style="color: var(--primary); font-size: 0.8rem;"></i>
                                <span>{{ __('menu.service_fee') }}</span>
                                <template x-if="serviceFeeType === 'percent'">
                                    <span style="font-size: 0.75rem; color: var(--text-muted);" x-text="'(' + serviceFeeValue + '%)'"></span>
                                </template>
                            </span>
                            <span class="cart-summary-val">
                                <span style="color: #10b981; font-weight: 700;">+<span x-text="Number(calculatedServiceFee).toLocaleString()"></span></span>
                                <span class="cart-summary-curr">{{ $vendor->currency }}</span>
                            </span>
                        </div>
                    </template>

                    <!-- Delivery Fee Row (Delivery) -->
                    <template x-if="orderType === 'delivery'">
                        <div class="cart-summary-row">
                            <span class="cart-summary-label" style="display: flex; align-items: center; gap: 0.35rem;">
                                <i class="fa-solid fa-motorcycle" style="color: #3b82f6; font-size: 0.8rem;"></i>
                                <span>{{ __('menu.delivery_fee') }}</span>
                            </span>
                            <span class="cart-summary-val">
                                <template x-if="calculatedDeliveryFee === 0">
                                    <span style="color: #10b981; font-weight: 800; font-size: 0.82rem; background: rgba(16, 185, 129, 0.15); padding: 0.15rem 0.5rem; border-radius: 6px;">
                                        {{ __('menu.free_delivery') }}
                                    </span>
                                </template>
                                <template x-if="calculatedDeliveryFee > 0">
                                    <span>
                                        <span style="color: var(--text-main); font-weight: 700;">+<span x-text="Number(calculatedDeliveryFee).toLocaleString()"></span></span>
                                        <span class="cart-summary-curr">{{ $vendor->currency }}</span>
                                    </span>
                                </template>
                            </span>
                        </div>
                    </template>

                    <!-- Dine-in Table Row -->
                    <template x-if="orderType === 'dine_in'">
                        <div class="cart-summary-row">
                            <span class="cart-summary-label">{{ __('menu.table') }}</span>
                            <span class="cart-summary-val" x-text="tableNumber || '{{ $table ?? "Table 4" }}'"></span>
                        </div>
                    </template>

                    <!-- Takeaway Row -->
                    <template x-if="orderType === 'takeaway'">
                        <div class="cart-summary-row">
                            <span class="cart-summary-label">{{ __('menu.order_type') }}</span>
                            <span class="cart-summary-val" style="color: #8b5cf6; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                                <i class="fa-solid fa-bag-shopping"></i> {{ __('menu.takeaway') }}
                            </span>
                        </div>
                    </template>

                    <!-- Delivery Type & Address Row -->
                    <template x-if="orderType === 'delivery'">
                        <div>
                            <div class="cart-summary-row">
                                <span class="cart-summary-label">{{ __('menu.order_type') }}</span>
                                <span class="cart-summary-val" style="color: #f59e0b; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-motorcycle"></i> {{ __('menu.delivery') }}
                                </span>
                            </div>
                            <template x-if="deliveryAddress">
                                <div class="cart-summary-row" style="margin-top: 0.25rem;">
                                    <span class="cart-summary-label">{{ __('menu.delivery_address') }}</span>
                                    <span class="cart-summary-val cart-summary-addr" x-text="deliveryAddress"></span>
                                </div>
                            </template>
                        </div>
                    </template>

                    <div class="cart-summary-divider"></div>
                    <div class="cart-summary-row cart-total-row">
                        <span class="cart-total-label">{{ __('menu.total_to_pay') }}</span>
                        <div class="cart-total-price-wrap">
                            <span class="cart-total-number" x-text="Number(cartFinalTotal).toLocaleString()"></span>
                            <span class="cart-total-curr">{{ $vendor->currency }}</span>
                        </div>
                    </div>
                </div>

                <!-- GDPR & Consent Opt-In -->
                <label class="cart-consent-card">
                    <input type="checkbox" x-model="marketingOptIn" class="cart-consent-checkbox">
                    <span class="cart-consent-text">
                        {!! __('menu.privacy_consent', [
                            'privacy_link' => '<a href="'.route('legal.privacy').'" target="_blank" class="cart-legal-link">'.__('menu.privacy_policy').'</a>',
                            'terms_link' => '<a href="'.route('legal.terms').'" target="_blank" class="cart-legal-link">'.__('menu.terms_of_service').'</a>'
                        ]) !!}
                    </span>
                </label>

                <!-- Order Action Buttons -->
                <template x-if="isPreOrderMode">
                    <div class="cart-actions-column">
                        <button type="button" 
                                @click="confirmPreOrderAndReturn()" 
                                class="cart-submit-btn"
                                style="background: var(--primary); box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);">
                            <div class="cart-submit-left">
                                <i class="fa-solid fa-calendar-days"></i>
                                <span>{{ __('menu.confirm_preorder_return') }}</span>
                            </div>
                            <div class="cart-submit-price-pill">
                                <span x-text="Number(cartSubtotal).toLocaleString()"></span>
                                <span class="cart-submit-curr">{{ $vendor->currency }}</span>
                            </div>
                        </button>
                    </div>
                </template>

                <template x-if="!isPreOrderMode">
                    <div class="cart-actions-column">
                        <!-- Main Order Button -->
                        <button type="button" 
                                @click="submitOrder(orderType)" 
                                class="cart-submit-btn"
                                :disabled="!isCurrentChannelOpen || (orderType === 'delivery' && isBelowDeliveryMin) || (orderType === 'takeaway' && isBelowTakeawayMin)"
                                :style="(!isCurrentChannelOpen || (orderType === 'delivery' && isBelowDeliveryMin) || (orderType === 'takeaway' && isBelowTakeawayMin)) ? 'opacity: 0.55; cursor: not-allowed;' : ''"
                                :class="{ 'is-delivery': orderType === 'delivery', 'is-takeaway': orderType === 'takeaway', 'is-closed': !isCurrentChannelOpen }">
                            <div class="cart-submit-left">
                                <template x-if="!isCurrentChannelOpen">
                                    <i class="fa-solid fa-clock" style="color: #ef4444;"></i>
                                </template>
                                <template x-if="isCurrentChannelOpen && activeOrder && !['completed', 'cancelled'].includes(activeOrder.status)">
                                    <i class="fa-solid fa-circle-plus" style="color: #10b981;"></i>
                                </template>
                                <template x-if="isCurrentChannelOpen && (!activeOrder || ['completed', 'cancelled'].includes(activeOrder.status))">
                                    <span>
                                        <template x-if="orderType === 'delivery'">
                                            <i class="fa-solid fa-motorcycle"></i>
                                        </template>
                                        <template x-if="orderType === 'takeaway'">
                                            <i class="fa-solid fa-bag-shopping"></i>
                                        </template>
                                        <template x-if="orderType === 'dine_in'">
                                            <i class="fa-solid fa-bell-concierge"></i>
                                        </template>
                                    </span>
                                </template>
                                <span x-text="!isCurrentChannelOpen
                                    ? (orderType === 'delivery' ? '{{ __('menu.delivery_closed') }}' : (orderType === 'takeaway' ? '{{ __('menu.takeaway_closed') }}' : '{{ __('menu.kitchen_closed') }}'))
                                    : ((activeOrder && !['completed', 'cancelled'].includes(activeOrder.status))
                                        ? ('{{ __('menu.append_to_active_order') }} (' + activeOrder.order_number + ')')
                                        : (orderType === 'delivery' ? '{{ __('menu.order_delivery') }}' : (orderType === 'takeaway' ? '{{ __('menu.order_takeaway') }}' : '{{ __('menu.checkout') }}')))"></span>
                            </div>
                            <div class="cart-submit-price-pill">
                                <span x-text="Number(cartFinalTotal).toLocaleString()"></span>
                                <span class="cart-submit-curr">{{ $vendor->currency }}</span>
                            </div>
                        </button>

                        <!-- WhatsApp Order Button -->
                        @if($vendor->hasWhatsAppOrdersEnabled($location ?? null))
                        <button type="button" 
                                @click="submitOrder('whatsapp')" 
                                :disabled="!isCurrentChannelOpen || (orderType === 'delivery' && isBelowDeliveryMin) || (orderType === 'takeaway' && isBelowTakeawayMin)"
                                :style="(!isCurrentChannelOpen || (orderType === 'delivery' && isBelowDeliveryMin) || (orderType === 'takeaway' && isBelowTakeawayMin)) ? 'opacity: 0.55; cursor: not-allowed;' : ''"
                                class="cart-whatsapp-btn cart-whatsapp-order-btn">
                            <i class="fa-brands fa-whatsapp cart-wa-icon"></i>
                            <span x-text="!isCurrentChannelOpen
                                ? '{{ __('menu.orders_closed') }}'
                                : ((activeOrder && !['completed', 'cancelled'].includes(activeOrder.status))
                                    ? '{{ __('menu.append_to_active_order_wa') }}'
                                    : (orderType === 'delivery' ? '{{ __('menu.order_delivery_via_whatsapp') }}' : (orderType === 'takeaway' ? '{{ __('menu.order_takeaway_via_whatsapp') }}' : '{{ __('menu.order_via_whatsapp') }}')))"></span>
                        </button>
                        @endif

                        @if($vendor->isReservationFeatureEnabled() && $vendor->isPreOrderFeatureEnabled())
                        <!-- Reserve Table with Current Cart Option -->
                        <div style="margin-top: 0.75rem; text-align: center;">
                            <button type="button" 
                                    @click="startReservationWithCart()" 
                                    style="background: transparent; border: 1.5px dashed var(--border-color); color: var(--text-main); font-size: 0.85rem; font-weight: 700; padding: 0.65rem 1rem; border-radius: 12px; cursor: pointer; width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem; transition: all 0.2s;">
                                <i class="fa-solid fa-calendar-plus" style="color: var(--primary);"></i>
                                <span>{{ __('menu.reserve_table_with_this_order') }}</span>
                            </button>
                        </div>
                        @endif
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<style>
    /* AI Cart Recommendations */
    .cart-ai-recs-container {
        margin-top: 1.15rem;
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.06), rgba(245, 158, 11, 0.02));
        border: 1px dashed rgba(245, 158, 11, 0.35);
        border-radius: 18px;
        padding: 0.95rem;
    }

    .cart-ai-recs-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.35rem;
        margin-bottom: 0.75rem;
    }

    .cart-ai-recs-title-wrap {
        display: flex;
        align-items: center;
        gap: 0.45rem;
    }

    .cart-ai-recs-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: #ffffff;
        font-size: 0.68rem;
        font-weight: 800;
        padding: 0.2rem 0.5rem;
        border-radius: 6px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .cart-ai-recs-title {
        font-size: 0.88rem;
        font-weight: 800;
        color: var(--text-main);
    }

    .cart-ai-recs-subtitle {
        font-size: 0.74rem;
        color: var(--text-muted);
    }

    .cart-ai-recs-scroll {
        display: flex;
        gap: 0.75rem;
        overflow-x: auto;
        padding-bottom: 0.35rem;
        scrollbar-width: none;
    }

    .cart-ai-recs-scroll::-webkit-scrollbar {
        display: none;
    }

    .cart-ai-rec-card {
        flex: 0 0 200px;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .cart-ai-rec-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 14px rgba(0,0,0,0.08);
    }

    .cart-ai-rec-img {
        width: 100%;
        height: 95px;
        object-fit: cover;
        background: rgba(150, 150, 150, 0.08);
    }

    .cart-ai-rec-img-placeholder {
        width: 100%;
        height: 95px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(150, 150, 150, 0.08);
        color: var(--text-muted);
        font-size: 1.5rem;
    }

    .cart-ai-rec-body {
        padding: 0.65rem 0.75rem 0.75rem;
        display: flex;
        flex-direction: column;
        flex: 1;
        justify-content: space-between;
    }

    .cart-ai-rec-name {
        font-weight: 700;
        font-size: 0.85rem;
        color: var(--text-main);
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .cart-ai-rec-reason {
        font-size: 0.72rem;
        color: #d97706;
        font-weight: 600;
        margin: 0.2rem 0 0.5rem;
        line-height: 1.25;
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .cart-ai-rec-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: auto;
    }

    .cart-ai-rec-price {
        font-weight: 800;
        font-size: 0.92rem;
        color: var(--text-main);
    }

    .cart-ai-rec-add-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.35rem 0.65rem;
        border-radius: 8px;
        background: var(--primary);
        color: #ffffff;
        border: none;
        font-size: 0.76rem;
        font-weight: 700;
        cursor: pointer;
        transition: opacity 0.15s, transform 0.15s;
    }

    .cart-ai-rec-add-btn:hover {
        opacity: 0.9;
    }

    .cart-ai-rec-add-btn:active {
        transform: scale(0.94);
    }

    /* Cart Modal Backdrop Overlay */
    .cart-modal-overlay {
        position: fixed;
        inset: 0;
        z-index: 150;
        background: rgba(0, 0, 0, 0.74);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        align-items: center;
        box-sizing: border-box;
    }

    /* Modal Animation Transitions */
    .cart-modal-fade-enter {
        transition: opacity 0.28s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .cart-modal-fade-enter-start {
        opacity: 0;
    }
    .cart-modal-fade-enter-end {
        opacity: 1;
    }
    .cart-modal-fade-leave {
        transition: opacity 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .cart-sheet-slide-enter {
        transition: transform 0.34s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.28s ease;
    }
    .cart-sheet-slide-enter-start {
        transform: translateY(100%);
        opacity: 0;
    }
    .cart-sheet-slide-enter-end {
        transform: translateY(0);
        opacity: 1;
    }
    .cart-sheet-slide-leave {
        transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.18s ease;
    }

    /* Main Bottom-Sheet Container */
    .cart-modal-sheet {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-bottom: none;
        border-radius: 28px 28px 0 0;
        width: 100%;
        max-width: var(--desktop-max-width, 600px);
        height: 92vh;
        height: 92dvh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 -12px 48px rgba(0, 0, 0, 0.5);
        box-sizing: border-box;
        overflow: hidden;
        position: relative;
    }

    /* Drag Handle Pill */
    .cart-drag-handle {
        width: 44px;
        height: 4.5px;
        border-radius: 9999px;
        background: var(--border-color);
        margin: 0.65rem auto 0.25rem auto;
        cursor: pointer;
        opacity: 0.85;
        transition: opacity 0.2s;
        flex-shrink: 0;
    }
    .cart-drag-handle:hover {
        opacity: 1;
    }

    /* Modal Header */
    .cart-modal-header {
        padding: 0.85rem 1.35rem 0.85rem 1.35rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid var(--border-color);
        background: var(--bg-card);
        flex-shrink: 0;
        z-index: 10;
    }

    .cart-header-left {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 0;
    }

    .cart-header-icon-wrap {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: color-mix(in srgb, var(--primary) 15%, transparent);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
        border: 1px solid color-mix(in srgb, var(--primary) 25%, transparent);
        box-shadow: 0 2px 10px color-mix(in srgb, var(--primary) 20%, transparent);
    }

    .cart-header-title {
        font-family: 'Outfit', sans-serif;
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--text-main);
        margin: 0;
        line-height: 1.2;
        letter-spacing: -0.01em;
    }

    .cart-header-subtitle {
        font-size: 0.76rem;
        font-weight: 700;
        color: var(--primary);
        margin-top: 0.15rem;
    }

    .cart-close-btn {
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
    .cart-close-btn:hover {
        color: var(--text-main);
        border-color: rgba(255, 255, 255, 0.25);
        transform: rotate(90deg);
    }
    .cart-close-btn:active {
        transform: scale(0.92);
    }

    /* Scrollable Body */
    .cart-modal-body {
        flex: 1;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        padding: 1.15rem 1.35rem calc(2rem + env(safe-area-inset-bottom, 0.5rem)) 1.35rem;
        box-sizing: border-box;
    }

    /* Empty Cart State */
    .cart-empty-state {
        text-align: center;
        padding: 3rem 1rem 2rem;
    }

    .cart-empty-icon-circle {
        width: 82px;
        height: 82px;
        border-radius: 50%;
        background: var(--bg-main);
        border: 1.5px solid var(--border-color);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.25rem;
        color: var(--text-muted);
        font-size: 2.2rem;
        opacity: 0.7;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
    }

    .cart-empty-title {
        font-family: 'Outfit', sans-serif;
        font-size: 1.35rem;
        font-weight: 800;
        color: var(--text-main);
        margin: 0 0 0.4rem 0;
    }

    .cart-empty-desc {
        font-size: 0.88rem;
        color: var(--text-muted);
        max-width: 300px;
        margin: 0 auto 1.6rem auto;
        line-height: 1.45;
    }

    .cart-empty-cta-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
        background: var(--primary);
        color: #ffffff;
        border: none;
        padding: 0.85rem 1.85rem;
        border-radius: 14px;
        font-weight: 800;
        font-size: 0.95rem;
        cursor: pointer;
        box-shadow: 0 8px 20px -4px color-mix(in srgb, var(--primary) 40%, transparent);
        transition: all 0.2s ease;
    }
    .cart-empty-cta-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px -4px color-mix(in srgb, var(--primary) 50%, transparent);
    }

    /* Cart Content Stack */
    .cart-content-stack {
        display: flex;
        flex-direction: column;
        gap: 1.15rem;
    }

    /* Items Section */
    .cart-items-container {
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        border-radius: 18px;
        padding: 0.85rem 1rem;
    }

    .cart-section-badge {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        font-size: 0.78rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--border-color);
    }

    .cart-section-badge i {
        color: var(--primary);
    }

    .cart-pill-count {
        background: color-mix(in srgb, var(--primary) 15%, transparent);
        color: var(--primary);
        padding: 0.05rem 0.45rem;
        border-radius: 9999px;
        font-size: 0.72rem;
        font-weight: 800;
    }

    .cart-items-list {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
    }

    .cart-item-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 0.85rem;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        gap: 0.85rem;
        transition: border-color 0.2s;
    }
    .cart-item-card:hover {
        border-color: color-mix(in srgb, var(--primary) 30%, var(--border-color));
    }

    .cart-item-info {
        min-width: 0;
        flex: 1;
    }

    .cart-item-name {
        font-weight: 700;
        font-size: 0.96rem;
        color: var(--text-main);
        line-height: 1.3;
        word-break: break-word;
    }

    .cart-item-variation {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.76rem;
        font-weight: 700;
        color: var(--primary);
        background: color-mix(in srgb, var(--primary) 12%, transparent);
        border: 1px solid color-mix(in srgb, var(--primary) 22%, transparent);
        padding: 0.1rem 0.5rem;
        border-radius: 6px;
        margin-top: 0.25rem;
        word-break: break-word;
    }

    .cart-item-var-dot {
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: var(--primary);
    }

    .cart-item-price-unit {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-top: 0.25rem;
        font-family: 'Outfit', sans-serif;
    }

    .cart-curr {
        font-size: 0.74rem;
    }

    .cart-item-controls {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.35rem;
        flex-shrink: 0;
    }

    /* Stepper */
    .cart-stepper {
        display: flex;
        align-items: center;
        gap: 0.3rem;
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        border-radius: 9999px;
        padding: 0.2rem 0.3rem;
    }

    .cart-stepper-btn {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        border: none;
        background: transparent;
        color: var(--text-main);
        font-weight: 800;
        font-size: 0.85rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s ease;
        padding: 0;
    }
    .cart-stepper-btn:hover {
        background: var(--border-color);
    }
    .cart-stepper-btn:active {
        transform: scale(0.9);
    }

    .cart-stepper-btn.is-remove:hover {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
    }
    .cart-trash-icon {
        font-size: 0.75rem;
        color: var(--text-muted);
    }
    .cart-stepper-btn.is-remove:hover .cart-trash-icon {
        color: #ef4444;
    }

    .cart-stepper-btn-plus {
        background: var(--primary);
        color: #ffffff;
    }
    .cart-stepper-btn-plus:hover {
        background: var(--primary);
        transform: scale(1.08);
    }

    .cart-stepper-val {
        min-width: 24px;
        text-align: center;
        font-family: 'Outfit', sans-serif;
        font-weight: 800;
        font-size: 0.95rem;
        color: var(--text-main);
    }

    .cart-item-subtotal {
        font-family: 'Outfit', sans-serif;
        font-size: 0.92rem;
        font-weight: 800;
        color: var(--accent);
    }
    .cart-subtotal-curr {
        font-size: 0.72rem;
        color: var(--text-muted);
    }

    /* Order Type Toggle (Dine-in vs Takeaway vs Delivery) */
    .cart-type-toggle-wrap {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
        gap: 0.4rem;
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        padding: 0.35rem;
        border-radius: 16px;
    }

    .cart-type-btn {
        background: transparent;
        border: none;
        color: var(--text-muted);
        padding: 0.65rem 0.9rem;
        border-radius: 12px;
        font-family: 'Outfit', sans-serif;
        font-size: 0.9rem;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .cart-type-btn:hover {
        color: var(--text-main);
    }
    .cart-type-btn.is-active {
        background: var(--primary);
        color: #ffffff;
        box-shadow: 0 4px 15px color-mix(in srgb, var(--primary) 35%, transparent);
    }

    /* Modern Payment Method Tiles */
    .cart-payment-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 0.65rem;
    }

    .payment-method-tile {
        position: relative;
        background: var(--bg-body);
        border: 1.5px solid var(--border-color);
        border-radius: 14px;
        padding: 0.85rem 0.65rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        cursor: pointer;
        user-select: none;
        transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        text-align: center;
    }

    .payment-method-tile:hover {
        border-color: color-mix(in srgb, var(--primary) 40%, var(--border-color));
        transform: translateY(-1px);
    }

    .payment-method-tile.is-active {
        border-color: var(--primary) !important;
        background: color-mix(in srgb, var(--primary) 9%, var(--bg-card)) !important;
        box-shadow: 0 4px 14px color-mix(in srgb, var(--primary) 22%, transparent);
    }

    .payment-tile-icon-wrap {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        transition: transform 0.2s ease;
        flex-shrink: 0;
    }

    .payment-method-tile.is-active .payment-tile-icon-wrap {
        transform: scale(1.08);
    }

    .payment-tile-label {
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--text-main);
        text-align: center;
        line-height: 1.25;
    }

    .payment-tile-check {
        position: absolute;
        top: 6px;
        right: 6px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: var(--primary);
        color: #ffffff;
        font-size: 0.65rem;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 6px rgba(0,0,0,0.18);
        animation: popCheck 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes popCheck {
        0% { transform: scale(0); opacity: 0; }
        100% { transform: scale(1); opacity: 1; }
    }

    /* Delivery Address Field */
    .cart-delivery-field-block {
        margin-top: 0.4rem;
        padding-top: 0.75rem;
        border-top: 1px dashed var(--border-color);
    }

    .cart-subfield-label {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.8rem;
        font-weight: 700;
        color: #f59e0b;
        margin-bottom: 0.45rem;
    }

    .cart-delivery-input {
        border-color: rgba(245, 158, 11, 0.3);
    }
    .cart-delivery-input:focus {
        border-color: #f59e0b;
        box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
    }

    .cart-summary-addr {
        max-width: 170px;
        text-align: right;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 0.82rem;
        color: var(--text-main);
    }

    .cart-submit-btn.is-delivery {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        box-shadow: 0 4px 18px rgba(245, 158, 11, 0.35);
    }
    .cart-submit-btn.is-delivery:hover {
        box-shadow: 0 6px 24px rgba(245, 158, 11, 0.5);
    }

    .cart-submit-btn.is-takeaway {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        box-shadow: 0 4px 18px rgba(139, 92, 246, 0.35);
    }
    .cart-submit-btn.is-takeaway:hover {
        box-shadow: 0 6px 24px rgba(139, 92, 246, 0.5);
    }

    /* Section Cards (Notes & Customer Info) */
    .cart-card-group {
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        border-radius: 18px;
        padding: 0.9rem 1.1rem;
    }

    .cart-group-label {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        font-size: 0.82rem;
        font-weight: 800;
        color: var(--text-main);
        margin-bottom: 0.65rem;
    }

    .cart-label-icon {
        color: var(--primary);
        font-size: 0.95rem;
    }

    .cart-textarea-wrap {
        position: relative;
    }

    .cart-textarea {
        width: 100%;
        box-sizing: border-box;
        padding: 0.75rem 0.95rem;
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        color: var(--text-main);
        font-size: 0.88rem;
        line-height: 1.4;
        resize: vertical;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .cart-textarea:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 20%, transparent);
    }

    /* Customer Form Grid */
    .cart-inputs-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.65rem;
        margin-bottom: 0.65rem;
    }

    @media (max-width: 480px) {
        .cart-inputs-grid {
            grid-template-columns: 1fr;
        }
    }

    .cart-input-field-wrap {
        position: relative;
        display: flex;
        align-items: center;
    }

    .cart-input-prefix-icon {
        position: absolute;
        left: 0.9rem;
        color: var(--text-muted);
        font-size: 0.9rem;
        pointer-events: none;
    }

    .cart-input {
        width: 100%;
        box-sizing: border-box;
        padding: 0.72rem 0.85rem 0.72rem 2.35rem;
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        color: var(--text-main);
        font-size: 0.86rem;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .cart-input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 20%, transparent);
    }
    .cart-input.has-error {
        border-color: #ef4444 !important;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2) !important;
    }
    .cart-input.is-valid {
        border-color: #10b981;
    }

    /* Phone Input Container & Country Picker */
    .cart-phone-field-wrapper {
        position: relative;
        width: 100%;
    }

    .cart-phone-input-box {
        display: flex;
        align-items: center;
        width: 100%;
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        transition: border-color 0.2s, box-shadow 0.2s;
        box-sizing: border-box;
    }

    .cart-phone-input-box:focus-within {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 20%, transparent);
    }

    .cart-phone-input-box.has-error {
        border-color: #ef4444 !important;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2) !important;
    }

    .cart-phone-input-box.is-valid {
        border-color: #10b981;
    }

    .cart-phone-country-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.72rem 0.65rem 0.72rem 0.85rem;
        background: color-mix(in srgb, var(--text-main) 6%, transparent);
        border: none;
        border-right: 1px solid var(--border-color);
        border-top-left-radius: 11px;
        border-bottom-left-radius: 11px;
        color: var(--text-main);
        font-size: 0.88rem;
        font-weight: 600;
        cursor: pointer;
        user-select: none;
        white-space: nowrap;
        transition: background 0.15s;
        flex-shrink: 0;
    }

    .cart-phone-country-btn:hover {
        background: color-mix(in srgb, var(--text-main) 12%, transparent);
    }

    .cart-flag-icon {
        font-size: 1.15rem;
        line-height: 1;
    }

    .cart-dial-code {
        font-family: inherit;
        font-size: 0.88rem;
        letter-spacing: -0.2px;
    }

    .cart-country-caret {
        font-size: 0.65rem;
        color: var(--text-muted);
        transition: transform 0.2s ease;
    }

    .cart-country-caret.is-open {
        transform: rotate(180deg);
    }

    .cart-phone-native-input {
        flex: 1;
        min-width: 0;
        padding: 0.72rem 0.65rem;
        background: transparent;
        border: none;
        outline: none;
        color: var(--text-main);
        font-size: 0.92rem;
        font-weight: 600;
        letter-spacing: 0.3px;
        box-sizing: border-box;
    }

    .cart-phone-native-input::placeholder {
        color: var(--text-muted);
        font-weight: 400;
        font-size: 0.84rem;
    }

    .cart-phone-status-indicator {
        padding-right: 0.75rem;
        display: flex;
        align-items: center;
        flex-shrink: 0;
    }

    .cart-status-badge {
        font-size: 0.72rem;
        font-weight: 700;
        padding: 0.18rem 0.45rem;
        border-radius: 6px;
        line-height: 1.2;
    }

    .cart-status-badge.is-incomplete {
        background: rgba(245, 158, 11, 0.15);
        color: #f59e0b;
        border: 1px solid rgba(245, 158, 11, 0.3);
    }

    .cart-status-badge.is-valid {
        background: rgba(16, 185, 129, 0.15);
        color: #10b981;
        font-size: 0.95rem;
        padding: 0.1rem 0.25rem;
    }

    /* Country Dropdown Popup */
    .cart-country-dropdown {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        width: 100%;
        max-width: 320px;
        max-height: 240px;
        overflow-y: auto;
        background: var(--bg-card, #1c1c1e);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.5);
        z-index: 100;
        padding: 0.35rem;
        backdrop-filter: blur(12px);
    }

    .cart-country-item {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        width: 100%;
        padding: 0.6rem 0.75rem;
        border: none;
        background: transparent;
        color: var(--text-main);
        font-size: 0.85rem;
        border-radius: 8px;
        cursor: pointer;
        text-align: left;
        transition: background 0.15s;
        box-sizing: border-box;
    }

    .cart-country-item:hover, .cart-country-item.is-selected {
        background: color-mix(in srgb, var(--primary) 18%, transparent);
        color: var(--text-main);
    }

    .cart-item-flag {
        font-size: 1.15rem;
        line-height: 1;
    }

    .cart-item-name {
        flex: 1;
        font-weight: 500;
    }

    .cart-item-dial {
        font-weight: 600;
        color: var(--primary);
        font-size: 0.82rem;
    }

    .cart-item-digits {
        font-size: 0.72rem;
        color: var(--text-muted);
    }

    /* Feedback error text */
    .cart-input-feedback-msg {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.75rem;
        margin-top: 0.35rem;
        padding-left: 0.3rem;
    }

    .cart-input-feedback-msg.is-error {
        color: #ef4444;
        font-weight: 500;
    }

    /* Table Input */
    .cart-table-wrap {
        position: relative;
        display: flex;
        align-items: center;
    }

    .cart-table-field {
        position: relative;
        width: 100%;
        display: flex;
        align-items: center;
    }

    .cart-table-input {
        padding-right: 7rem;
    }

    .cart-table-wrap.is-locked .cart-table-input {
        background: color-mix(in srgb, var(--input-bg) 70%, #000);
        cursor: not-allowed;
    }

    .cart-table-badge {
        position: absolute;
        right: 0.65rem;
        display: flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.74rem;
        font-weight: 700;
        color: #10b981;
        background: rgba(16, 185, 129, 0.12);
        border: 1px solid rgba(16, 185, 129, 0.25);
        padding: 0.2rem 0.55rem;
        border-radius: 8px;
        pointer-events: none;
    }

    .cart-table-hint {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.72rem;
        color: #10b981;
        margin-top: 0.4rem;
        padding: 0 0.2rem;
    }

    /* Pricing Breakdown Card */
    .cart-summary-card {
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        border-radius: 18px;
        padding: 1rem 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .cart-summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.88rem;
    }

    .cart-summary-label {
        color: var(--text-muted);
    }

    .cart-summary-val {
        color: var(--text-main);
        font-family: 'Outfit', sans-serif;
    }
    .cart-summary-curr {
        font-size: 0.76rem;
        color: var(--text-muted);
    }

    .cart-summary-divider {
        height: 1px;
        background: var(--border-color);
        margin: 0.35rem 0;
    }

    .cart-total-row {
        align-items: baseline;
    }

    .cart-total-label {
        font-size: 1.05rem;
        font-weight: 800;
        color: var(--text-main);
        letter-spacing: -0.01em;
    }

    .cart-total-price-wrap {
        display: flex;
        align-items: baseline;
        gap: 0.35rem;
    }

    .cart-total-number {
        font-family: 'Outfit', sans-serif;
        font-size: 1.45rem;
        font-weight: 800;
        color: var(--accent);
        letter-spacing: -0.02em;
    }

    .cart-total-curr {
        font-size: 0.88rem;
        font-weight: 800;
        color: var(--text-muted);
    }

    /* Consent Card */
    .cart-consent-card {
        display: flex;
        align-items: flex-start;
        gap: 0.65rem;
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        padding: 0.75rem 0.95rem;
        cursor: pointer;
        transition: border-color 0.2s;
    }
    .cart-consent-card:hover {
        border-color: color-mix(in srgb, var(--primary) 30%, var(--border-color));
    }

    .cart-consent-checkbox {
        margin-top: 0.2rem;
        width: 16px;
        height: 16px;
        accent-color: var(--primary);
        cursor: pointer;
        flex-shrink: 0;
    }

    .cart-consent-text {
        font-size: 0.78rem;
        line-height: 1.45;
        color: var(--text-muted);
    }

    .cart-legal-link {
        color: var(--primary);
        font-weight: 700;
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    /* Actions Column */
    .cart-actions-column {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        margin-top: 0.35rem;
    }

    .cart-submit-btn {
        width: 100%;
        padding: 1rem 1.25rem;
        background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 85%, #000));
        color: #ffffff;
        font-weight: 800;
        font-size: 1.05rem;
        border: none;
        border-radius: 16px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 8px 24px -4px color-mix(in srgb, var(--primary) 40%, transparent);
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        box-sizing: border-box;
    }
    .cart-submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 28px -4px color-mix(in srgb, var(--primary) 50%, transparent);
    }
    .cart-submit-btn:active {
        transform: scale(0.985);
    }

    .cart-submit-left {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        font-size: 1.02rem;
    }
    .cart-submit-left i {
        font-size: 1.15rem;
    }

    .cart-submit-price-pill {
        display: flex;
        align-items: baseline;
        gap: 0.3rem;
        background: rgba(0, 0, 0, 0.22);
        padding: 0.28rem 0.75rem;
        border-radius: 10px;
        font-family: 'Outfit', sans-serif;
        font-size: 1.15rem;
        font-weight: 800;
    }

    .cart-submit-curr {
        font-size: 0.8rem;
        opacity: 0.9;
    }

    .cart-whatsapp-btn {
        width: 100%;
        padding: 0.88rem 1.25rem;
        background: linear-gradient(135deg, #25D366, #128C7E);
        color: #ffffff;
        font-weight: 800;
        font-size: 0.96rem;
        border: none;
        border-radius: 16px;
        cursor: pointer;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.65rem;
        box-shadow: 0 6px 20px -3px rgba(37, 211, 102, 0.35);
        transition: all 0.22s ease;
        box-sizing: border-box;
    }
    .cart-whatsapp-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 24px -3px rgba(37, 211, 102, 0.45);
    }
    .cart-whatsapp-btn:active {
        transform: scale(0.985);
    }

    .cart-wa-icon {
        font-size: 1.25rem;
    }
</style>
