@php
    $displayName = $location?->name ?: $vendor->name;
    $wifiSsid = $location?->wifi_ssid ?: ($vendor->wifi_ssid ?: ($vendor->name . ' Guest'));
    $wifiPassword = $location?->wifi_password ?: ($vendor->wifi_password ?: 'guest' . str_pad($vendor->id, 4, '0', STR_PAD_LEFT));
    $displayAddress = $location?->address ?: ($vendor->operating_address ?: ($vendor->legal_address ?: 'Yerevan, Armenia'));
    $contactPhone = $location?->phone ?: ($vendor->phone ?: '+374 10 000000');
    $rawPhone = preg_replace('/[^0-9+]/', '', $contactPhone);
    $whatsappSource = $location?->whatsapp_number ?: ($vendor->phone ?: '');
    $whatsappNum = preg_replace('/[^0-9]/', '', $whatsappSource);
    $displayHours = $location?->working_hours ?: ($vendor->working_hours ?: ('10:00 - 23:00 (' . __('menu.every_day') . ')'));
@endphp

<!-- Restaurant Info & WiFi Modal (Modern Glass Bottom Sheet) -->
<div x-show="showInfoModal" 
     role="dialog"
     aria-modal="true"
     aria-label="{{ __('menu.restaurant_info') }}"
     class="info-modal-backdrop"
     x-cloak
     @click.self="showInfoModal = false"
     x-transition:enter="transition ease-out duration-250"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
     
    <div class="info-sheet-container"
         x-show="showInfoModal"
         x-transition:enter="transition cubic-bezier(0.16, 1, 0.3, 1) duration-300 transform"
         x-transition:enter-start="translate-y-full md:translate-y-6 md:scale-95 opacity-0"
         x-transition:enter-end="translate-y-0 md:scale-100 opacity-100"
         x-transition:leave="transition cubic-bezier(0.16, 1, 0.3, 1) duration-200 transform"
         x-transition:leave-start="translate-y-0 md:scale-100 opacity-100"
         x-transition:leave-end="translate-y-full md:translate-y-6 md:scale-95 opacity-0">
         
        <!-- Mobile Top Pull Bar -->
        <div class="info-drag-handle" @click="showInfoModal = false"></div>

        <!-- Header -->
        <div class="info-sheet-header">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div class="info-header-icon-box">
                    <i class="fa-solid fa-circle-info"></i>
                </div>
                <div>
                    <h3 class="info-sheet-title">
                        {{ __('menu.restaurant_info') }}
                    </h3>
                    <p class="info-sheet-subtitle">
                        <i class="fa-solid fa-location-dot" style="font-size: 0.72rem; color: var(--primary);"></i>
                        <span>{{ $displayName }}</span>
                    </p>
                </div>
            </div>
            <button type="button" 
                    @click="showInfoModal = false" 
                    class="info-close-round-btn"
                    aria-label="{{ __('menu.close') }}"
                    title="{{ __('menu.close') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Scrollable Content -->
        <div class="info-sheet-body">
            
            <!-- Wi-Fi Card: Premium Glassmorphism Widget -->
            <div class="info-wifi-card">
                <!-- Background Ambient Glow -->
                <div class="info-wifi-glow"></div>

                <!-- Wi-Fi Card Top Row -->
                <div class="info-wifi-top-row">
                    <div style="display: flex; align-items: center; gap: 0.65rem;">
                        <div class="info-wifi-icon-badge">
                            <i class="fa-solid fa-wifi"></i>
                        </div>
                        <div>
                            <div class="info-wifi-title">{{ __('menu.wifi_network') }}</div>
                            <div class="info-wifi-subtitle">{{ __('menu.free_guest_wifi') }}</div>
                        </div>
                    </div>
                    <span class="info-wifi-status-tag">
                        <span class="info-pulse-dot"></span>
                        <span>{{ __('menu.fast_wifi') }}</span>
                    </span>
                </div>
                
                <!-- SSID Row -->
                <div class="info-wifi-detail-row">
                    <div>
                        <div class="info-detail-label">{{ __('menu.wifi_ssid_label') }}</div>
                        <div class="info-detail-value">{{ $wifiSsid }}</div>
                    </div>
                    <div class="info-wifi-signal-icon" title="{{ __('menu.fast_wifi') }}">
                        <i class="fa-solid fa-signal"></i>
                    </div>
                </div>

                <!-- Password Row with Quick Copy -->
                <div class="info-wifi-detail-row" style="margin-top: 0.5rem;">
                    <div style="min-width: 0; flex: 1;">
                        <div class="info-detail-label">{{ __('menu.wifi_password') }}</div>
                        <div class="info-password-value">{{ $wifiPassword }}</div>
                    </div>
                    <button type="button" 
                            @click="copyWifiPassword('{{ $wifiPassword }}')"
                            class="info-wifi-copy-btn"
                            :class="{ 'is-copied': wifiCopied }">
                        <i :class="wifiCopied ? 'fa-solid fa-check text-emerald-400' : 'fa-regular fa-copy'"></i>
                        <span x-text="wifiCopied ? '{{ __('menu.wifi_copied') }}' : '{{ __('menu.wifi_copy') }}'"></span>
                    </button>
                </div>
            </div>

            <!-- Contact & Location Card -->
            <div class="info-details-stack">
                
                <!-- Address -->
                <div class="info-detail-item">
                    <div class="info-icon-square icon-indigo">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>
                    <div class="info-item-content">
                        <div class="info-item-label">{{ __('menu.address') }}</div>
                        <div class="info-item-value">{{ $displayAddress }}</div>
                    </div>
                    <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($displayAddress) }}" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       class="info-action-pill"
                       title="{{ __('menu.view_map') }}">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        <span>{{ __('menu.view_map') }}</span>
                    </a>
                </div>

                <!-- Phone -->
                <div class="info-detail-item">
                    <div class="info-icon-square icon-sky">
                        <i class="fa-solid fa-phone"></i>
                    </div>
                    <div class="info-item-content">
                        <div class="info-item-label">{{ __('menu.phone') }}</div>
                        <div class="info-item-value">{{ $contactPhone }}</div>
                    </div>
                    @if($rawPhone)
                        <a href="tel:{{ $rawPhone }}" class="info-action-pill" title="{{ __('menu.call_phone') }}">
                            <i class="fa-solid fa-phone-flip"></i>
                            <span>{{ __('menu.call_phone') }}</span>
                        </a>
                    @endif
                </div>

                <!-- WhatsApp Direct Chat -->
                @if($whatsappNum)
                    <div class="info-detail-item">
                        <div class="info-icon-square icon-whatsapp">
                            <i class="fa-brands fa-whatsapp"></i>
                        </div>
                        <div class="info-item-content">
                            <div class="info-item-label">WhatsApp</div>
                            <div class="info-item-value">+{{ $whatsappNum }}</div>
                        </div>
                        <a href="https://wa.me/{{ $whatsappNum }}" 
                           target="_blank" 
                           rel="noopener noreferrer"
                           class="info-action-pill info-action-whatsapp"
                           title="{{ __('menu.contact_whatsapp') }}">
                            <i class="fa-brands fa-whatsapp"></i>
                            <span>{{ __('menu.chat') }}</span>
                        </a>
                    </div>
                @endif

                <!-- Working Hours -->
                <div class="info-detail-item">
                    <div class="info-icon-square icon-amber">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div class="info-item-content">
                        <div class="info-item-label">{{ __('menu.working_hours') }}</div>
                        <div class="info-item-value">{{ $displayHours }}</div>
                    </div>
                    <span class="info-open-badge">
                        <span class="info-pulse-dot" style="background: #10b981;"></span>
                        <span>{{ __('menu.open_now') }}</span>
                    </span>
                </div>

                <!-- Table seated info if available -->
                <template x-if="tableNumber || '{{ $table ?? '' }}'">
                    <div class="info-detail-item" style="border-top: 1px dashed var(--border-color); padding-top: 0.65rem; margin-top: 0.1rem;">
                        <div class="info-icon-square icon-teal">
                            <i class="fa-solid fa-chair"></i>
                        </div>
                        <div class="info-item-content">
                            <div class="info-item-label">{{ __('menu.table') }}</div>
                            <div class="info-item-value" x-text="tableNumber || '{{ __('menu.table') }} {{ $table ?? '' }}'"></div>
                        </div>
                        <span class="info-table-badge" x-text="tableNumber || '{{ __('menu.table') }} {{ $table ?? '' }}'"></span>
                    </div>
                </template>

                <!-- Event Planning Inquiries -->
                @if($vendor->isEventInquiryFeatureEnabled())
                    <div class="info-detail-item">
                        <div class="info-icon-square" style="background: rgba(168, 85, 247, 0.15); color: #a855f7;">
                            <i class="fa-solid fa-champagne-glasses"></i>
                        </div>
                        <div class="info-item-content">
                            <div class="info-item-label">{{ __('Private Events') }}</div>
                            <div class="info-item-value">{{ __('Event Planning Inquiry') }}</div>
                        </div>
                        <a href="{{ route('client.event_inquiry', ['vendor_slug' => $vendor->slug, 'location_slug' => $location?->slug]) }}" 
                           class="info-action-pill"
                           style="background: #a855f7; color: #ffffff; border-color: #a855f7;"
                           title="{{ __('Event Planning Inquiry') }}">
                            <i class="fa-solid fa-arrow-right"></i>
                            <span>{{ __('Inquiry') }}</span>
                        </a>
                    </div>
                @endif

            </div>

            <!-- Bottom Close Button -->
            <button type="button" 
                    @click="showInfoModal = false" 
                    class="info-modal-dismiss-btn">
                <i class="fa-solid fa-xmark"></i>
                <span>{{ __('menu.close') }}</span>
            </button>

        </div>
    </div>
</div>

<style>
    /* Backdrop */
    .info-modal-backdrop {
        position: fixed;
        inset: 0;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100%;
        height: 100%;
        height: 100dvh;
        z-index: 250;
        background: rgba(0, 0, 0, 0.78);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        align-items: center;
        box-sizing: border-box;
        overflow: hidden;
    }

    /* Info Sheet Container: anchored at bottom on mobile with zero bottom gap */
    .info-sheet-container {
        background: var(--bg-card);
        width: 100%;
        max-width: var(--desktop-max-width, 560px);
        margin: 0 auto;
        margin-top: auto !important;
        margin-bottom: 0 !important;
        max-height: 92vh;
        max-height: 92dvh;
        display: flex;
        flex-direction: column;
        border-radius: 26px 26px 0 0 !important;
        border: 1px solid var(--border-color);
        border-bottom: none !important;
        box-shadow: 0 -16px 48px -8px rgba(0, 0, 0, 0.5);
        box-sizing: border-box;
        overflow: hidden;
        position: relative;
    }

    @media (max-width: 768px) {
        .info-modal-backdrop {
            justify-content: flex-end !important;
            padding: 0 !important;
        }

        .info-sheet-container {
            width: 100% !important;
            max-width: 100% !important;
            margin-top: auto !important;
            margin-bottom: 0 !important;
            border-bottom-left-radius: 0 !important;
            border-bottom-right-radius: 0 !important;
            border-bottom: none !important;
            border-left: none !important;
            border-right: none !important;
            padding-bottom: env(safe-area-inset-bottom, 0) !important;
        }
    }

    @media (min-width: 769px) {
        .info-modal-backdrop {
            padding: 1.5rem;
            justify-content: center;
        }

        .info-sheet-container {
            margin: auto !important;
            border-radius: 22px !important;
            border: 1px solid var(--border-color) !important;
            max-height: 85vh;
            max-height: 85dvh;
            box-shadow: 0 20px 60px -15px rgba(0, 0, 0, 0.65);
        }
    }

    .info-drag-handle {
        width: 38px;
        height: 4px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 9999px;
        margin: 0.65rem auto 0.2rem;
        cursor: pointer;
        transition: background 0.2s ease;
    }
    .info-drag-handle:hover {
        background: rgba(255, 255, 255, 0.35);
    }

    /* Header */
    .info-sheet-header {
        padding: 0.75rem 1.15rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid var(--border-color);
        background: var(--bg-card);
        flex-shrink: 0;
    }

    .info-header-icon-box {
        width: 38px;
        height: 38px;
        border-radius: 11px;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.18), rgba(6, 182, 212, 0.12));
        border: 1px solid rgba(16, 185, 129, 0.25);
        color: #10b981;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .info-sheet-title {
        font-family: 'Outfit', sans-serif;
        font-size: 1.2rem;
        font-weight: 800;
        color: var(--text-main);
        margin: 0;
        line-height: 1.2;
    }

    .info-sheet-subtitle {
        font-size: 0.78rem;
        color: var(--text-muted);
        margin: 0.15rem 0 0 0;
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }

    .info-close-round-btn {
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        color: var(--text-muted);
        width: 34px;
        height: 34px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        cursor: pointer;
        flex-shrink: 0;
        transition: all 0.2s ease;
    }
    .info-close-round-btn:hover {
        color: var(--text-main);
        border-color: rgba(255, 255, 255, 0.25);
        transform: scale(1.05);
    }

    /* Scrollable Body */
    .info-sheet-body {
        flex: 1;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        padding: 0.9rem 1.15rem calc(1.75rem + env(safe-area-inset-bottom, 0.75rem)) 1.15rem;
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
    }

    /* Wi-Fi Card */
    .info-wifi-card {
        flex-shrink: 0;
        background: linear-gradient(145deg, rgba(255, 255, 255, 0.04) 0%, rgba(255, 255, 255, 0.01) 100%);
        border: 1px solid var(--border-color);
        border-radius: 18px;
        padding: 0.95rem 1rem;
        position: relative;
        overflow: hidden;
        box-shadow: 0 8px 24px -6px rgba(0, 0, 0, 0.25);
    }

    .info-wifi-glow {
        position: absolute;
        top: -35px;
        right: -35px;
        width: 120px;
        height: 120px;
        background: #10b981;
        opacity: 0.12;
        filter: blur(35px);
        border-radius: 50%;
        pointer-events: none;
    }

    .info-wifi-top-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
        position: relative;
        z-index: 2;
    }

    .info-wifi-icon-badge {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        background: linear-gradient(135deg, #10b981, #059669);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
        flex-shrink: 0;
    }

    .info-wifi-title {
        font-family: 'Outfit', sans-serif;
        font-weight: 800;
        font-size: 0.95rem;
        color: var(--text-main);
        line-height: 1.2;
    }

    .info-wifi-subtitle {
        font-size: 0.72rem;
        color: var(--text-muted);
        margin-top: 0.1rem;
    }

    .info-wifi-status-tag {
        font-size: 0.7rem;
        background: rgba(16, 185, 129, 0.14);
        border: 1px solid rgba(16, 185, 129, 0.3);
        color: #34d399;
        padding: 0.2rem 0.55rem;
        border-radius: 9999px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    .info-pulse-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #10b981;
        box-shadow: 0 0 8px #10b981;
        animation: infoPulse 2s infinite;
    }

    @keyframes infoPulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.45; transform: scale(0.85); }
    }

    .info-wifi-detail-row {
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 0.6rem 0.85rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
        z-index: 2;
    }

    .info-detail-label {
        font-size: 0.68rem;
        text-transform: uppercase;
        font-weight: 700;
        color: var(--text-muted);
        letter-spacing: 0.04em;
        margin-bottom: 0.1rem;
    }

    .info-detail-value {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 0.92rem;
        color: var(--text-main);
        word-break: break-all;
    }

    .info-password-value {
        font-family: 'Outfit', -apple-system, monospace;
        font-weight: 700;
        font-size: 0.98rem;
        color: var(--text-main);
        letter-spacing: 0.04em;
        word-break: break-all;
    }

    .info-wifi-signal-icon {
        color: var(--text-muted);
        font-size: 0.82rem;
        opacity: 0.6;
    }

    .info-wifi-copy-btn {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.14);
        color: var(--text-main);
        padding: 0.4rem 0.75rem;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }
    .info-wifi-copy-btn:hover {
        background: rgba(255, 255, 255, 0.14);
        border-color: rgba(255, 255, 255, 0.24);
    }
    .info-wifi-copy-btn.is-copied {
        background: rgba(16, 185, 129, 0.18);
        border-color: rgba(16, 185, 129, 0.4);
        color: #34d399;
    }

    /* Details Stack */
    .info-details-stack {
        flex-shrink: 0;
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        border-radius: 18px;
        padding: 0.4rem 0.95rem;
        display: flex;
        flex-direction: column;
    }

    .info-detail-item {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        padding: 0.55rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }
    .info-detail-item:last-child {
        border-bottom: none;
    }

    .info-icon-square {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        flex-shrink: 0;
    }
    .icon-indigo {
        background: rgba(99, 102, 241, 0.12);
        border: 1px solid rgba(99, 102, 241, 0.25);
        color: #818cf8;
    }
    .icon-sky {
        background: rgba(14, 165, 233, 0.12);
        border: 1px solid rgba(14, 165, 233, 0.25);
        color: #38bdf8;
    }
    .icon-whatsapp {
        background: rgba(37, 211, 102, 0.12);
        border: 1px solid rgba(37, 211, 102, 0.25);
        color: #25d366;
        font-size: 1.1rem;
    }
    .icon-amber {
        background: rgba(245, 158, 11, 0.12);
        border: 1px solid rgba(245, 158, 11, 0.25);
        color: #fbbf24;
    }
    .icon-teal {
        background: rgba(20, 184, 166, 0.12);
        border: 1px solid rgba(20, 184, 166, 0.25);
        color: #2dd4bf;
    }

    .info-item-content {
        flex: 1;
        min-width: 0;
    }

    .info-item-label {
        font-size: 0.7rem;
        color: var(--text-muted);
        font-weight: 600;
        margin-bottom: 0.08rem;
    }

    .info-item-value {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-main);
        word-break: break-word;
    }

    .info-action-pill {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        color: var(--text-main);
        padding: 0.35rem 0.7rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        flex-shrink: 0;
        transition: all 0.2s ease;
    }
    .info-action-pill:hover {
        border-color: rgba(255, 255, 255, 0.25);
        background: rgba(255, 255, 255, 0.08);
        transform: translateY(-1px);
    }

    .info-action-whatsapp {
        background: linear-gradient(135deg, #25d366, #128c7e);
        border: none;
        color: #ffffff !important;
        box-shadow: 0 3px 10px rgba(37, 211, 102, 0.3);
    }
    .info-action-whatsapp:hover {
        background: linear-gradient(135deg, #2ae06e, #149c8c);
        box-shadow: 0 4px 14px rgba(37, 211, 102, 0.45);
    }

    .info-open-badge {
        font-size: 0.7rem;
        background: rgba(16, 185, 129, 0.12);
        border: 1px solid rgba(16, 185, 129, 0.25);
        color: #34d399;
        padding: 0.2rem 0.55rem;
        border-radius: 9999px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        flex-shrink: 0;
    }

    .info-table-badge {
        font-size: 0.72rem;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        color: var(--text-main);
        padding: 0.2rem 0.55rem;
        border-radius: 8px;
        font-weight: 700;
        flex-shrink: 0;
    }

    /* Dismiss Button */
    .info-modal-dismiss-btn {
        flex-shrink: 0;
        width: 100%;
        padding: 0.75rem;
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        color: var(--text-main);
        border-radius: 12px;
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 0.92rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        transition: all 0.2s ease;
        margin-top: 0.15rem;
    }
    .info-modal-dismiss-btn:hover {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(255, 255, 255, 0.25);
        transform: translateY(-1px);
    }
</style>
