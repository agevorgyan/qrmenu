@extends('layouts.app')

@section('title', __('Settings') . ' - ' . $vendor->name)

@section('content')
<script>
window.settingsModule = function() {
    return {
        activeTab: 'profile',
        isDirty: false,
        init() {
            // Check query param first, then hash
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            const hash = window.location.hash.replace('#', '');
            const validTabs = ['profile', 'daily_offers', 'orders', 'reservations', 'payments', 'delivery', 'notifications', 'regional', 'ai', 'integrations', 'security'];

            if (tabParam && validTabs.includes(tabParam)) {
                this.activeTab = tabParam;
            } else if (hash && validTabs.includes(hash)) {
                this.activeTab = hash;
            }

            // Detect errors and navigate to erroneous tab automatically
            @if(isset($errors) && $errors->any())
                @if($errors->has('name') || $errors->has('address') || $errors->has('phone') || $errors->has('whatsapp_number') || $errors->has('wifi_ssid') || $errors->has('wifi_password') || $errors->has('working_hours') || $errors->has('legal_name') || $errors->has('tax_id') || $errors->has('operating_address') || $errors->has('director_name') || $errors->has('director_phone') || $errors->has('contact_person_name') || $errors->has('contact_person_phone'))
                    this.activeTab = 'profile';
                @elseif($errors->has('service_fee_type') || $errors->has('service_fee_value') || $errors->has('service_fee_min_order') || $errors->has('dine_in_start_time') || $errors->has('dine_in_end_time'))
                    this.activeTab = 'orders';
                @elseif($errors->has('delivery_fee') || $errors->has('delivery_min_amount') || $errors->has('delivery_free_from') || $errors->has('takeaway_min_amount') || $errors->has('delivery_start_time') || $errors->has('delivery_end_time'))
                    this.activeTab = 'delivery';
                @elseif($errors->has('timezone') || $errors->has('currency') || $errors->has('secondary_currency') || $errors->has('secondary_currency_exchange_rate') || $errors->has('secondary_currency_enabled') || $errors->has('weight_unit') || $errors->has('volume_unit'))
                    this.activeTab = 'regional';
                @elseif($errors->has('daily_offers') || $errors->has('featured_dish_enabled') || $errors->has('featured_product_id'))
                    this.activeTab = 'daily_offers';
                @elseif($errors->has('custom_domain'))
                    this.activeTab = 'integrations';
                @endif
            @endif

            // Track form dirty status
            this.$nextTick(() => {
                const form = document.getElementById('vendorSettingsForm');
                if (form) {
                    form.addEventListener('input', () => { this.isDirty = true; });
                    form.addEventListener('change', () => { this.isDirty = true; });
                    form.addEventListener('submit', () => { this.isDirty = false; });
                }
            });

            window.addEventListener('beforeunload', (e) => {
                if (this.isDirty) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            // Listen to tab switch events from main sidebar
            window.addEventListener('switch-settings-tab', (e) => {
                if (e.detail && validTabs.includes(e.detail)) {
                    this.setTab(e.detail);
                }
            });

            // Initial broadcast so sidebar active tab matches
            this.$nextTick(() => {
                window.dispatchEvent(new CustomEvent('settings-tab-changed', { detail: this.activeTab }));
            });
        },
        setTab(tab) {
            this.activeTab = tab;
            window.dispatchEvent(new CustomEvent('settings-tab-changed', { detail: tab }));
            if (window.history && window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.set('tab', tab);
                window.history.replaceState(null, '', url.pathname + url.search + '#' + tab);
            } else {
                window.location.hash = tab;
            }
        }
    };
};

if (window.Alpine) {
    Alpine.data('settingsModule', window.settingsModule);
} else {
    document.addEventListener('alpine:init', () => {
        Alpine.data('settingsModule', window.settingsModule);
    });
}
</script>

<div style="max-width: 1250px; margin: 0 auto; width: 100%; box-sizing: border-box;" x-data="settingsModule()">
    <!-- Breadcrumb & Header -->
    <div style="margin-bottom: 1.75rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">
            <a href="{{ route('admin.dashboard') }}" style="color: var(--text-muted); text-decoration: none; display: flex; align-items: center; gap: 0.35rem;"><i class="fa-solid fa-gauge-high"></i> {{ __('Dashboard') }}</a>
            <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem; opacity: 0.6;"></i>
            <span style="color: var(--primary); font-weight: 700;">{{ __('Settings') }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="min-width: 0; flex: 1;">
                <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                    <span style="background: rgba(245, 158, 11, 0.15); color: var(--primary); width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                        <i class="fa-solid fa-sliders"></i>
                    </span>
                    <span>{{ __('Settings') }}</span>
                </h1>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0 0; word-break: break-word;">
                    {{ __('Manage branch public information, legal details, service fee, and delivery parameters') }}
                </p>
            </div>
            <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                <div x-show="isDirty" x-cloak style="display: inline-flex; align-items: center; gap: 0.45rem; background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 10px; padding: 0.45rem 0.85rem; font-size: 0.82rem; font-weight: 700; color: #f59e0b;">
                    <span style="width: 7px; height: 7px; border-radius: 50%; background: #f59e0b;"></span>
                    <span>{{ __('Unsaved Changes') }}</span>
                </div>
                <a href="{{ route('client.menu', ['vendor_slug' => $vendor->slug]) }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.5rem; text-decoration: none; border-radius: 12px; font-weight: 600; font-size: 0.88rem;">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> {{ __('Live Storefront') }}
                </a>
                <button type="submit" form="vendorSettingsForm" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 700; padding: 0.65rem 1.4rem; font-size: 0.92rem; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.3);">
                    <i class="fa-solid fa-floppy-disk"></i> {{ __('Save Changes') }}
                </button>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; color: #ef4444;">
            <div style="font-weight: 700; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                <i class="fa-solid fa-circle-exclamation"></i> {{ __('Attention: There are errors in the submitted form') }}
            </div>
            <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.88rem;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($vendor->locations->count() > 1)
        <div style="background: var(--bg-card); border: 1.5px solid var(--border-color); border-radius: 16px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: var(--shadow-card);">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="width: 38px; height: 38px; border-radius: 10px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                    <i class="fa-solid fa-code-branch"></i>
                </span>
                <div>
                    <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);">{{ __('Current Branch:') }} <span style="color: var(--primary);">{{ $location?->name }}</span></div>
                    <div style="font-size: 0.8rem; color: var(--text-muted);">{{ __('Settings apply to the selected branch') }}</div>
                </div>
            </div>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                @foreach($vendor->locations as $loc)
                    <a href="{{ route('admin.settings.index', ['location_id' => $loc->id]) }}" class="btn {{ ($location?->id === $loc->id) ? 'btn-primary' : 'btn-secondary' }}" style="border-radius: 10px; font-size: 0.85rem; padding: 0.45rem 0.9rem; text-decoration: none; font-weight: 700;">
                        <i class="fa-solid fa-location-dot"></i> {{ $loc->name }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Mobile / Tablet Category Navigation Bar -->
    <div class="settings-mobile-tabs hide-on-desktop" role="tablist" aria-label="{{ __('Settings category navigation') }}">
        <button type="button" class="settings-mobile-tab-btn" :class="{ 'active': activeTab === 'profile' }" @click="setTab('profile')" role="tab" :aria-selected="activeTab === 'profile'">
            <i class="fa-solid fa-store" style="color: #10b981;"></i>
            <span>{{ __('Restaurant Profile') }}</span>
        </button>
        <button type="button" class="settings-mobile-tab-btn" :class="{ 'active': activeTab === 'daily_offers' }" @click="setTab('daily_offers')" role="tab" :aria-selected="activeTab === 'daily_offers'">
            <i class="fa-solid fa-fire-flame-curved" style="color: #ef4444;"></i>
            <span>{{ __('Daily Offers') }}</span>
        </button>
        <button type="button" class="settings-mobile-tab-btn" :class="{ 'active': activeTab === 'orders' }" @click="setTab('orders')" role="tab" :aria-selected="activeTab === 'orders'">
            <i class="fa-solid fa-bell-concierge" style="color: #ef4444;"></i>
            <span>{{ __('Orders & Kitchen') }}</span>
        </button>
        <button type="button" class="settings-mobile-tab-btn" :class="{ 'active': activeTab === 'reservations' }" @click="setTab('reservations')" role="tab" :aria-selected="activeTab === 'reservations'">
            <i class="fa-solid fa-calendar-check" style="color: #ec4899;"></i>
            <span>{{ __('Reservations & Booking Rules') }}</span>
        </button>
        <button type="button" class="settings-mobile-tab-btn" :class="{ 'active': activeTab === 'payments' }" @click="setTab('payments')" role="tab" :aria-selected="activeTab === 'payments'">
            <i class="fa-solid fa-credit-card" style="color: #3b82f6;"></i>
            <span>{{ __('Payments & Gateways') }}</span>
        </button>
        <button type="button" class="settings-mobile-tab-btn" :class="{ 'active': activeTab === 'delivery' }" @click="setTab('delivery')" role="tab" :aria-selected="activeTab === 'delivery'">
            <i class="fa-solid fa-truck" style="color: #f59e0b;"></i>
            <span>{{ __('Delivery & Pickup') }}</span>
        </button>
        <button type="button" class="settings-mobile-tab-btn" :class="{ 'active': activeTab === 'notifications' }" @click="setTab('notifications')" role="tab" :aria-selected="activeTab === 'notifications'">
            <i class="fa-solid fa-bell" style="color: #8b5cf6;"></i>
            <span>{{ __('Notifications & Email') }}</span>
        </button>
        <button type="button" class="settings-mobile-tab-btn" :class="{ 'active': activeTab === 'regional' }" @click="setTab('regional')" role="tab" :aria-selected="activeTab === 'regional'">
            <i class="fa-solid fa-globe" style="color: #06b6d4;"></i>
            <span>{{ __('Languages & Regional Preferences') }}</span>
        </button>
        <button type="button" class="settings-mobile-tab-btn" :class="{ 'active': activeTab === 'ai' }" @click="setTab('ai')" role="tab" :aria-selected="activeTab === 'ai'">
            <i class="fa-solid fa-wand-magic-sparkles" style="color: #a855f7;"></i>
            <span>{{ __('AI & Automation') }}</span>
        </button>
        <button type="button" class="settings-mobile-tab-btn" :class="{ 'active': activeTab === 'integrations' }" @click="setTab('integrations')" role="tab" :aria-selected="activeTab === 'integrations'">
            <i class="fa-solid fa-plug" style="color: #14b8a6;"></i>
            <span>{{ __('Integrations') }}</span>
        </button>
        <button type="button" class="settings-mobile-tab-btn" :class="{ 'active': activeTab === 'security' }" @click="setTab('security')" role="tab" :aria-selected="activeTab === 'security'">
            <i class="fa-solid fa-shield-halved" style="color: #10b981;"></i>
            <span>{{ __('Security & Access') }}</span>
        </button>
    </div>

    <div class="settings-layout-wrapper">
        <!-- Content Area (Full Width) -->
        <div class="settings-content-area">
            <form action="{{ route('admin.settings.update') }}" method="POST" id="vendorSettingsForm">
                @csrf
                @if($location)
                    <input type="hidden" name="location_id" value="{{ $location->id }}">
                @endif
                <input type="hidden" name="active_tab" :value="activeTab">

                @php
                    $paymentSettings = $vendor->getSafePaymentSettings();
                    $crmSettings = $vendor->getSafeCrmSettings();
                    $printerSettings = $vendor->getThermalPrinterSettings();
                @endphp

                <!-- 1. RESTAURANT PROFILE TAB -->
                <div x-show="activeTab === 'profile'" x-transition.opacity role="tabpanel" style="display: flex; flex-direction: column; gap: 1.75rem;">
                    <!-- 1. BRANCH & STOREFRONT PUBLIC INFO CARD -->
            <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.9rem;">
                        <span style="width: 42px; height: 42px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">
                            <i class="fa-solid fa-store"></i>
                        </span>
                        <div>
                            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                {{ __('Branch & Menu Information') }}
                            </h3>
                            <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-muted); word-break: break-word;">
                                {{ __('This information is available to customers in the "Info" tab of the menu') }}
                            </p>
                        </div>
                    </div>

                    <span style="font-size: 0.78rem; font-weight: 700; background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; padding: 0.3rem 0.65rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                        <i class="fa-solid fa-eye"></i> {{ __('Visible in Menu') }}
                    </span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 1.25rem;">
                    <!-- Brand Name -->
                    <div class="form-group">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                            {{ __('Brand Name') }} <span style="color: #ef4444;">*</span>
                        </label>
                        <div style="position: relative; display: flex; align-items: center;">
                            <input type="text" name="name" value="{{ old('name', $location?->name ?? $vendor->name) }}" required class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem 0.75rem 2.6rem; font-size: 0.95rem; font-weight: 600;">
                            <span style="position: absolute; left: 1rem; color: var(--text-muted); font-size: 1rem;">
                                <i class="fa-solid fa-utensils"></i>
                            </span>
                        </div>
                        <span style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                            {{ __('Public name of the restaurant or branch') }}
                        </span>
                    </div>

                    <!-- Address -->
                    <div class="form-group">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                            {{ __('Address') }}
                        </label>
                        <div style="position: relative; display: flex; align-items: center;">
                            <input type="text" name="address" value="{{ old('address', $location?->address ?? ($vendor->operating_address ?? $vendor->legal_address)) }}" class="form-control" placeholder="e.g. 18 Amiryan St, Yerevan" style="width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem 0.75rem 2.6rem; font-size: 0.95rem; font-weight: 600;">
                            <span style="position: absolute; left: 1rem; color: var(--text-muted); font-size: 1rem;">
                                <i class="fa-solid fa-location-dot"></i>
                            </span>
                        </div>
                        <span style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                            {{ __('Physical address of the branch') }}
                        </span>
                    </div>

                    <!-- Phone Number -->
                    <div class="form-group">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                            {{ __('Phone Number') }}
                        </label>
                        <div style="position: relative; display: flex; align-items: center;">
                            <input type="text" name="phone" value="{{ old('phone', $location?->phone ?? $vendor->phone) }}" class="form-control" placeholder="+374 10 123456" style="width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem 0.75rem 2.6rem; font-size: 0.95rem; font-weight: 600;">
                            <span style="position: absolute; left: 1rem; color: var(--text-muted); font-size: 1rem;">
                                <i class="fa-solid fa-phone"></i>
                            </span>
                        </div>
                        <span style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                            {{ __('Phone number for customer calls') }}
                        </span>
                    </div>

                    <!-- WhatsApp -->
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin: 0;">
                                WhatsApp
                            </label>
                            <label style="display: inline-flex; align-items: center; gap: 0.45rem; cursor: pointer; user-select: none;">
                                <input type="checkbox" name="allow_whatsapp_orders" value="1" {{ old('allow_whatsapp_orders', ($location?->allow_whatsapp_orders ?? $vendor->allow_whatsapp_orders ?? true)) ? 'checked' : '' }} style="width: 17px; height: 17px; accent-color: #22c55e; cursor: pointer;">
                                <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-main);">{{ __('Button Active') }}</span>
                            </label>
                        </div>
                        <div style="position: relative; display: flex; align-items: center;">
                            <input type="text" name="whatsapp_number" value="{{ old('whatsapp_number', $location?->whatsapp_number ?? $vendor->phone) }}" class="form-control" placeholder="+374 91 123456" style="width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem 0.75rem 2.6rem; font-size: 0.95rem; font-weight: 600;">
                            <span style="position: absolute; left: 1rem; color: #25d366; font-size: 1.1rem;">
                                <i class="fa-brands fa-whatsapp"></i>
                            </span>
                        </div>
                        <span style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                            {{ __('WhatsApp orders will be sent to this number (if disabled, button will not appear in cart)') }}
                        </span>
                    </div>

                    <!-- Wi-Fi SSID -->
                    <div class="form-group">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                            {{ __('Wi-Fi Network (SSID)') }}
                        </label>
                        <div style="position: relative; display: flex; align-items: center;">
                            <input type="text" name="wifi_ssid" value="{{ old('wifi_ssid', $location?->wifi_ssid ?? ($vendor->wifi_ssid ?? ($vendor->name . ' Guest'))) }}" class="form-control" placeholder="e.g. Bistro_Guest_WiFi" style="width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem 0.75rem 2.6rem; font-size: 0.95rem; font-weight: 600;">
                            <span style="position: absolute; left: 1rem; color: var(--primary); font-size: 1rem;">
                                <i class="fa-solid fa-wifi"></i>
                            </span>
                        </div>
                        <span style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                            {{ __('Guest Wi-Fi network name') }}
                        </span>
                    </div>

                    <!-- Wi-Fi Password -->
                    <div class="form-group">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                            {{ __('Wi-Fi Password') }}
                        </label>
                        <div style="position: relative; display: flex; align-items: center;">
                            <input type="text" name="wifi_password" value="{{ old('wifi_password', $location?->wifi_password ?? ($vendor->wifi_password ?? 'guest' . str_pad($vendor->id, 4, '0', STR_PAD_LEFT))) }}" class="form-control" placeholder="Password" style="width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem 0.75rem 2.6rem; font-size: 0.95rem; font-weight: 600;">
                            <span style="position: absolute; left: 1rem; color: var(--primary); font-size: 1rem;">
                                <i class="fa-solid fa-key"></i>
                            </span>
                        </div>
                        <span style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                            {{ __('Customers can copy it with a single tap') }}
                        </span>
                    </div>

                    <!-- Working Days & Hours -->
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                            {{ __('Working Days & Hours') }}
                        </label>
                        <div style="position: relative; display: flex; align-items: center;">
                            <input type="text" name="working_hours" value="{{ old('working_hours', $location?->working_hours ?? ($vendor->working_hours ?? '10:00 - 23:00 (Daily)')) }}" class="form-control" placeholder="e.g. 10:00 - 23:00 (Daily)" style="width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem 0.75rem 2.6rem; font-size: 0.95rem; font-weight: 600;">
                            <span style="position: absolute; left: 1rem; color: var(--primary); font-size: 1rem;">
                                <i class="fa-solid fa-clock"></i>
                            </span>
                        </div>
                        <span style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                            {{ __('e.g. "Mon-Sun: 10:00 - 23:00"') }}
                        </span>
                    </div>

                    <!-- Desktop Screen Max Width -->
                    <div class="form-group" style="grid-column: 1 / -1; border-top: 1px dashed var(--border-color); padding-top: 1.25rem; margin-top: 0.5rem;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">
                            <i class="fa-solid fa-desktop" style="color: var(--primary);"></i> {{ __('Desktop Frame Max-Width') }}
                        </label>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.75rem;">
                            @php
                                $dWidth = $vendor->getDesktopMaxWidth();
                            @endphp
                            <label style="display: flex; align-items: center; gap: 0.5rem; background: var(--bg-body); border: 1.5px solid {{ $dWidth === '480px' ? 'var(--primary)' : 'var(--border-color)' }}; border-radius: 10px; padding: 0.6rem 0.8rem; cursor: pointer;">
                                <input type="radio" name="desktop_max_width" value="480px" {{ $dWidth === '480px' ? 'checked' : '' }}>
                                <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-main);">{{ __('480px (Compact)') }}</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; background: var(--bg-body); border: 1.5px solid {{ $dWidth === '600px' ? 'var(--primary)' : 'var(--border-color)' }}; border-radius: 10px; padding: 0.6rem 0.8rem; cursor: pointer;">
                                <input type="radio" name="desktop_max_width" value="600px" {{ $dWidth === '600px' ? 'checked' : '' }}>
                                <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-main);">{{ __('600px (Recommended)') }}</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; background: var(--bg-body); border: 1.5px solid {{ $dWidth === '680px' ? 'var(--primary)' : 'var(--border-color)' }}; border-radius: 10px; padding: 0.6rem 0.8rem; cursor: pointer;">
                                <input type="radio" name="desktop_max_width" value="680px" {{ $dWidth === '680px' ? 'checked' : '' }}>
                                <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-main);">{{ __('680px (Large Mobile)') }}</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; background: var(--bg-body); border: 1.5px solid {{ $dWidth === '768px' ? 'var(--primary)' : 'var(--border-color)' }}; border-radius: 10px; padding: 0.6rem 0.8rem; cursor: pointer;">
                                <input type="radio" name="desktop_max_width" value="768px" {{ $dWidth === '768px' ? 'checked' : '' }}>
                                <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-main);">{{ __('768px (Tablet)') }}</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; background: var(--bg-body); border: 1.5px solid {{ $dWidth === '100%' ? 'var(--primary)' : 'var(--border-color)' }}; border-radius: 10px; padding: 0.6rem 0.8rem; cursor: pointer;">
                                <input type="radio" name="desktop_max_width" value="100%" {{ $dWidth === '100%' ? 'checked' : '' }}>
                                <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-main);">{{ __('100% (Full Width)') }}</span>
                            </label>
                        </div>
                        <span style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                            {{ __('When opened on desktop, the menu will be displayed in an elegant centered frame of the selected size. Default: 600px:') }}
                        </span>
                    </div>
                </div>
            </div>

            
                    <!-- 4. LEGAL & CONTACT INFORMATION CARD (SUPERADMIN) -->
            <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.9rem;">
                        <span style="width: 42px; height: 42px; border-radius: 12px; background: rgba(99, 102, 241, 0.15); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">
                            <i class="fa-solid fa-scale-balanced"></i>
                        </span>
                        <div>
                            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                {{ __('Legal & Contact Details') }}
                            </h3>
                            <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-muted); word-break: break-word;">
                                {{ __('Information accessible to platform SuperAdmin') }}
                            </p>
                        </div>
                    </div>

                    <span style="font-size: 0.78rem; font-weight: 700; background: rgba(99, 102, 241, 0.15); color: #6366f1; border: 1px solid rgba(99, 102, 241, 0.3); border-radius: 8px; padding: 0.3rem 0.65rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                        <i class="fa-solid fa-shield-halved"></i> {{ __('Visible to SuperAdmin') }}
                    </span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 1.25rem;">
                    <!-- Legal Business Name -->
                    <div class="form-group">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                            {{ __('Legal Business Name') }}
                        </label>
                        <div style="position: relative; display: flex; align-items: center;">
                            <input type="text" name="legal_name" value="{{ old('legal_name', $vendor->legal_name) }}" class="form-control" placeholder="{{ __('e.g. \"Bistro Group\" LLC') }}" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem 0.75rem 2.6rem; font-size: 0.95rem; font-weight: 600;">
                            <span style="position: absolute; left: 1rem; color: var(--text-muted); font-size: 1rem;">
                                <i class="fa-solid fa-building"></i>
                            </span>
                        </div>
                    </div>

                    <!-- Tax ID -->
                    <div class="form-group">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                            {{ __('Tax ID (TIN)') }}
                        </label>
                        <div style="position: relative; display: flex; align-items: center;">
                            <input type="text" name="tax_id" value="{{ old('tax_id', $vendor->tax_id) }}" class="form-control" placeholder="02589412" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem 0.75rem 2.6rem; font-size: 0.95rem; font-weight: 600;">
                            <span style="position: absolute; left: 1rem; color: var(--text-muted); font-size: 1rem;">
                                <i class="fa-solid fa-receipt"></i>
                            </span>
                        </div>
                    </div>

                    <!-- Operating Address -->
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                            {{ __('Operating Address') }}
                        </label>
                        <div style="position: relative; display: flex; align-items: center;">
                            <input type="text" name="operating_address" value="{{ old('operating_address', $vendor->operating_address ?? ($location?->address ?? $vendor->legal_address)) }}" class="form-control" placeholder="{{ __('Physical operating address') }}" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem 0.75rem 2.6rem; font-size: 0.95rem; font-weight: 600;">
                            <span style="position: absolute; left: 1rem; color: var(--text-muted); font-size: 1rem;">
                                <i class="fa-solid fa-map-pin"></i>
                            </span>
                        </div>
                    </div>

                    <!-- Director & Phone -->
                    <div class="form-group">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                            {{ __('Director (Full Name)') }}
                        </label>
                        <div style="position: relative; display: flex; align-items: center;">
                            <input type="text" name="director_name" value="{{ old('director_name', $vendor->director_name) }}" class="form-control" placeholder="{{ __("Director's Name") }}" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem 0.75rem 2.6rem; font-size: 0.95rem; font-weight: 600;">
                            <span style="position: absolute; left: 1rem; color: var(--text-muted); font-size: 1rem;">
                                <i class="fa-solid fa-user-tie"></i>
                            </span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                            {{ __("Director's Phone Number") }}
                        </label>
                        <div style="position: relative; display: flex; align-items: center;">
                            <input type="text" name="director_phone" value="{{ old('director_phone', $vendor->director_phone) }}" class="form-control" placeholder="+374 91 000000" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem 0.75rem 2.6rem; font-size: 0.95rem; font-weight: 600;">
                            <span style="position: absolute; left: 1rem; color: var(--text-muted); font-size: 1rem;">
                                <i class="fa-solid fa-mobile-screen"></i>
                            </span>
                        </div>
                    </div>

                    <!-- Manager & Phone -->
                    <div class="form-group">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                            {{ __('Manager / Contact Person') }}
                        </label>
                        <div style="position: relative; display: flex; align-items: center;">
                            <input type="text" name="contact_person_name" value="{{ old('contact_person_name', $vendor->contact_person_name) }}" class="form-control" placeholder="{{ __("Manager's Name") }}" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem 0.75rem 2.6rem; font-size: 0.95rem; font-weight: 600;">
                            <span style="position: absolute; left: 1rem; color: var(--text-muted); font-size: 1rem;">
                                <i class="fa-solid fa-user-gear"></i>
                            </span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                            {{ __("Manager's Phone Number") }}
                        </label>
                        <div style="position: relative; display: flex; align-items: center;">
                            <input type="text" name="contact_person_phone" value="{{ old('contact_person_phone', $vendor->contact_person_phone) }}" class="form-control" placeholder="+374 93 000000" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem 0.75rem 2.6rem; font-size: 0.95rem; font-weight: 600;">
                            <span style="position: absolute; left: 1rem; color: var(--text-muted); font-size: 1rem;">
                                <i class="fa-solid fa-mobile-screen"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            
                </div>

                <!-- 2. ORDERS & KITCHEN TAB -->
                <div x-show="activeTab === 'orders'" x-transition.opacity role="tabpanel" style="display: flex; flex-direction: column; gap: 1.75rem;">
                    <!-- 3. OPERATING HOURS, KITCHEN & ORDER SCHEDULE CARD -->
            @php
                $hasMultipleLocations = $vendor->locations->count() > 1;
                $locDineInEnabled = old('dine_in_schedule_enabled', $location ? (bool)$location->dine_in_schedule_enabled : (bool)$vendor->dine_in_schedule_enabled);
                $locDineInStart = old('dine_in_start_time', $location?->dine_in_start_time ?? ($hasMultipleLocations ? '10:00' : ($vendor->dine_in_start_time ?? '10:00')));
                $locDineInEnd = old('dine_in_end_time', $location?->dine_in_end_time ?? ($hasMultipleLocations ? '23:00' : ($vendor->dine_in_end_time ?? '23:00')));
                $locDineInDays = old('dine_in_days', $location?->dine_in_days ?? ($hasMultipleLocations ? ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] : ($vendor->dine_in_days ?? ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])));
                if (!is_array($locDineInDays)) $locDineInDays = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

                $locDeliveryEnabled = old('delivery_schedule_enabled', $location ? (bool)$location->delivery_schedule_enabled : (bool)$vendor->delivery_schedule_enabled);
                $locDeliveryStart = old('delivery_start_time', $location?->delivery_start_time ?? ($hasMultipleLocations ? '11:00' : ($vendor->delivery_start_time ?? '11:00')));
                $locDeliveryEnd = old('delivery_end_time', $location?->delivery_end_time ?? ($hasMultipleLocations ? '22:30' : ($vendor->delivery_end_time ?? '22:30')));
                $locDeliveryDays = old('delivery_days', $location?->delivery_days ?? ($hasMultipleLocations ? ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] : ($vendor->delivery_days ?? ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])));
                if (!is_array($locDeliveryDays)) $locDeliveryDays = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

                $locTakeawayEnabled = old('takeaway_schedule_enabled', $location ? (bool)$location->takeaway_schedule_enabled : (bool)$vendor->takeaway_schedule_enabled);
                $locTakeawayStart = old('takeaway_start_time', $location?->takeaway_start_time ?? ($hasMultipleLocations ? '10:00' : ($vendor->takeaway_start_time ?? '10:00')));
                $locTakeawayEnd = old('takeaway_end_time', $location?->takeaway_end_time ?? ($hasMultipleLocations ? '23:00' : ($vendor->takeaway_end_time ?? '23:00')));
                $locTakeawayDays = old('takeaway_days', $location?->takeaway_days ?? ($hasMultipleLocations ? ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] : ($vendor->takeaway_days ?? ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])));
                if (!is_array($locTakeawayDays)) $locTakeawayDays = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

                $locWarningEnabled = old('closing_warning_enabled', $location ? (bool)$location->closing_warning_enabled : (bool)($vendor->closing_warning_enabled ?? true));
                $locWarningMinutes = old('closing_warning_minutes', $location?->closing_warning_minutes ?? ($hasMultipleLocations ? 30 : ($vendor->closing_warning_minutes ?? 30)));
                $locWarningMessage = old('closing_warning_message', $location?->closing_warning_message ?? ($hasMultipleLocations ? null : $vendor->closing_warning_message));

                $dayNames = [
                    'mon' => 'Mon',
                    'tue' => 'Tue',
                    'wed' => 'Wed',
                    'thu' => 'Thu',
                    'fri' => 'Fri',
                    'sat' => 'Sat',
                    'sun' => 'Sun',
                ];
            @endphp

            <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.9rem;">
                        <span style="width: 42px; height: 42px; border-radius: 12px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </span>
                        <div>
                            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                {{ __('Working Hours, Kitchen & Order Schedules') }}
                            </h3>
                            <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-muted); word-break: break-word;">
                                {{ __('Configure hours for kitchen, delivery, and takeaway orders, as well as closing warnings') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem;">
                    <!-- A. KITCHEN & DINE-IN SCHEDULE -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
                            <div style="display: flex; align-items: center; gap: 0.65rem;">
                                <span style="font-size: 1.15rem; color: #ef4444;"><i class="fa-solid fa-utensils"></i></span>
                                <div>
                                    <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);">{{ __('Kitchen / Dine-in Hours') }}</div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);">{{ __('If enabled, dine-in orders will not be accepted after kitchen closing') }}</div>
                                </div>
                            </div>
                            <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; user-select: none;">
                                <input type="checkbox" name="dine_in_schedule_enabled" value="1" id="dineInScheduleToggle" {{ $locDineInEnabled ? 'checked' : '' }} onchange="toggleScheduleBlock('dineIn')" style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                                <span style="font-weight: 700; font-size: 0.88rem; color: var(--text-main);">{{ __('Limit Hours') }}</span>
                            </label>
                        </div>

                        <div id="dineInScheduleFields" style="{{ $locDineInEnabled ? '' : 'display: none;' }}">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                                        <i class="fa-regular fa-clock"></i> {{ __('Opens (Start)') }}
                                    </label>
                                    <input type="time" name="dine_in_start_time" value="{{ substr($locDineInStart, 0, 5) }}" class="form-control" style="width: 100%; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.95rem; font-weight: 700;">
                                </div>
                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                                        <i class="fa-regular fa-clock"></i> {{ __('Kitchen Closes (End)') }}
                                    </label>
                                    <input type="time" name="dine_in_end_time" value="{{ substr($locDineInEnd, 0, 5) }}" class="form-control" style="width: 100%; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.95rem; font-weight: 700;">
                                </div>
                            </div>

                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem;">
                                    <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin: 0;">{{ __('Operating Days') }}</label>
                                    <div style="display: flex; gap: 0.35rem;">
                                        <button type="button" onclick="setChannelDays('dine_in', 'all')" class="btn btn-secondary" style="padding: 0.15rem 0.5rem; font-size: 0.72rem; border-radius: 6px;">{{ __('All') }}</button>
                                        <button type="button" onclick="setChannelDays('dine_in', 'weekdays')" class="btn btn-secondary" style="padding: 0.15rem 0.5rem; font-size: 0.72rem; border-radius: 6px;">{{ __('Mon-Fri') }}</button>
                                        <button type="button" onclick="setChannelDays('dine_in', 'weekends')" class="btn btn-secondary" style="padding: 0.15rem 0.5rem; font-size: 0.72rem; border-radius: 6px;">{{ __('Sat-Sun') }}</button>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    @foreach($dayNames as $dayKey => $dayLabel)
                                        <label style="display: inline-flex; align-items: center; gap: 0.35rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.35rem 0.65rem; font-size: 0.82rem; font-weight: 700; color: var(--text-main); cursor: pointer;">
                                            <input type="checkbox" name="dine_in_days[]" value="{{ $dayKey }}" class="dine_in_day_cb" {{ in_array($dayKey, $locDineInDays) ? 'checked' : '' }} style="accent-color: var(--primary);">
                                            <span>{{ __($dayLabel) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- B. DELIVERY SCHEDULE -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
                            <div style="display: flex; align-items: center; gap: 0.65rem;">
                                <span style="font-size: 1.15rem; color: #3b82f6;"><i class="fa-solid fa-motorcycle"></i></span>
                                <div>
                                    <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);">{{ __('Delivery Service Hours') }}</div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);">{{ __('Schedule for accepting delivery orders') }}</div>
                                </div>
                            </div>
                            <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; user-select: none;">
                                <input type="checkbox" name="delivery_schedule_enabled" value="1" id="deliveryScheduleToggle" {{ $locDeliveryEnabled ? 'checked' : '' }} onchange="toggleScheduleBlock('delivery')" style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                                <span style="font-weight: 700; font-size: 0.88rem; color: var(--text-main);">{{ __('Limit Hours') }}</span>
                            </label>
                        </div>

                        <div id="deliveryScheduleFields" style="{{ $locDeliveryEnabled ? '' : 'display: none;' }}">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                                        <i class="fa-regular fa-clock"></i> {{ __('Delivery Start') }}
                                    </label>
                                    <input type="time" name="delivery_start_time" value="{{ substr($locDeliveryStart, 0, 5) }}" class="form-control" style="width: 100%; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.95rem; font-weight: 700;">
                                </div>
                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                                        <i class="fa-regular fa-clock"></i> {{ __('Delivery End') }}
                                    </label>
                                    <input type="time" name="delivery_end_time" value="{{ substr($locDeliveryEnd, 0, 5) }}" class="form-control" style="width: 100%; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.95rem; font-weight: 700;">
                                </div>
                            </div>

                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem;">
                                    <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin: 0;">{{ __('Delivery Operating Days') }}</label>
                                    <div style="display: flex; gap: 0.35rem;">
                                        <button type="button" onclick="setChannelDays('delivery', 'all')" class="btn btn-secondary" style="padding: 0.15rem 0.5rem; font-size: 0.72rem; border-radius: 6px;">{{ __('All') }}</button>
                                        <button type="button" onclick="setChannelDays('delivery', 'weekdays')" class="btn btn-secondary" style="padding: 0.15rem 0.5rem; font-size: 0.72rem; border-radius: 6px;">{{ __('Mon-Fri') }}</button>
                                        <button type="button" onclick="setChannelDays('delivery', 'weekends')" class="btn btn-secondary" style="padding: 0.15rem 0.5rem; font-size: 0.72rem; border-radius: 6px;">{{ __('Sat-Sun') }}</button>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    @foreach($dayNames as $dayKey => $dayLabel)
                                        <label style="display: inline-flex; align-items: center; gap: 0.35rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.35rem 0.65rem; font-size: 0.82rem; font-weight: 700; color: var(--text-main); cursor: pointer;">
                                            <input type="checkbox" name="delivery_days[]" value="{{ $dayKey }}" class="delivery_day_cb" {{ in_array($dayKey, $locDeliveryDays) ? 'checked' : '' }} style="accent-color: var(--primary);">
                                            <span>{{ __($dayLabel) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- C. TAKEAWAY SCHEDULE -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
                            <div style="display: flex; align-items: center; gap: 0.65rem;">
                                <span style="font-size: 1.15rem; color: #10b981;"><i class="fa-solid fa-bag-shopping"></i></span>
                                <div>
                                    <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);">{{ __('Takeaway Order Hours') }}</div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);">{{ __('Operating hours for self-pickup (takeaway) orders') }}</div>
                                </div>
                            </div>
                            <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; user-select: none;">
                                <input type="checkbox" name="takeaway_schedule_enabled" value="1" id="takeawayScheduleToggle" {{ $locTakeawayEnabled ? 'checked' : '' }} onchange="toggleScheduleBlock('takeaway')" style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                                <span style="font-weight: 700; font-size: 0.88rem; color: var(--text-main);">{{ __('Limit Hours') }}</span>
                            </label>
                        </div>

                        <div id="takeawayScheduleFields" style="{{ $locTakeawayEnabled ? '' : 'display: none;' }}">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                                        <i class="fa-regular fa-clock"></i> {{ __('Takeaway Start') }}
                                    </label>
                                    <input type="time" name="takeaway_start_time" value="{{ substr($locTakeawayStart, 0, 5) }}" class="form-control" style="width: 100%; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.95rem; font-weight: 700;">
                                </div>
                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                                        <i class="fa-regular fa-clock"></i> {{ __('Takeaway End') }}
                                    </label>
                                    <input type="time" name="takeaway_end_time" value="{{ substr($locTakeawayEnd, 0, 5) }}" class="form-control" style="width: 100%; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.95rem; font-weight: 700;">
                                </div>
                            </div>

                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem;">
                                    <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin: 0;">{{ __('Takeaway Operating Days') }}</label>
                                    <div style="display: flex; gap: 0.35rem;">
                                        <button type="button" onclick="setChannelDays('takeaway', 'all')" class="btn btn-secondary" style="padding: 0.15rem 0.5rem; font-size: 0.72rem; border-radius: 6px;">{{ __('All') }}</button>
                                        <button type="button" onclick="setChannelDays('takeaway', 'weekdays')" class="btn btn-secondary" style="padding: 0.15rem 0.5rem; font-size: 0.72rem; border-radius: 6px;">{{ __('Mon-Fri') }}</button>
                                        <button type="button" onclick="setChannelDays('takeaway', 'weekends')" class="btn btn-secondary" style="padding: 0.15rem 0.5rem; font-size: 0.72rem; border-radius: 6px;">{{ __('Sat-Sun') }}</button>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    @foreach($dayNames as $dayKey => $dayLabel)
                                        <label style="display: inline-flex; align-items: center; gap: 0.35rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.35rem 0.65rem; font-size: 0.82rem; font-weight: 700; color: var(--text-main); cursor: pointer;">
                                            <input type="checkbox" name="takeaway_days[]" value="{{ $dayKey }}" class="takeaway_day_cb" {{ in_array($dayKey, $locTakeawayDays) ? 'checked' : '' }} style="accent-color: var(--primary);">
                                            <span>{{ __($dayLabel) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- D. CLOSING NOTICE / WARNING -->
                    <div style="background: rgba(245, 158, 11, 0.08); border: 1.5px dashed rgba(245, 158, 11, 0.4); border-radius: 16px; padding: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
                            <div style="display: flex; align-items: center; gap: 0.65rem;">
                                <span style="font-size: 1.2rem; color: #f59e0b;"><i class="fa-solid fa-triangle-exclamation"></i></span>
                                <div>
                                    <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);">{{ __('Closing Warning') }}</div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);">{{ __('Notify customers in advance when the kitchen or restaurant is about to close') }}</div>
                                </div>
                            </div>
                            <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; user-select: none;">
                                <input type="checkbox" name="closing_warning_enabled" value="1" id="closingWarningToggle" {{ $locWarningEnabled ? 'checked' : '' }} style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                                <span style="font-weight: 700; font-size: 0.88rem; color: var(--text-main);">{{ __('Enabled') }}</span>
                            </label>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr)); gap: 1rem;">
                            <div>
                                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                                    {{ __('Show warning how many minutes in advance') }}
                                </label>
                                <select name="closing_warning_minutes" class="form-control" style="width: 100%; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.92rem; font-weight: 700;">
                                    <option value="15" {{ $locWarningMinutes == 15 ? 'selected' : '' }}>{{ __('15 minutes before') }}</option>
                                    <option value="30" {{ $locWarningMinutes == 30 ? 'selected' : '' }}>{{ __('30 minutes before (Recommended)') }}</option>
                                    <option value="45" {{ $locWarningMinutes == 45 ? 'selected' : '' }}>{{ __('45 minutes before') }}</option>
                                    <option value="60" {{ $locWarningMinutes == 60 ? 'selected' : '' }}>{{ __('60 minutes before (1 hour)') }}</option>
                                    <option value="90" {{ $locWarningMinutes == 90 ? 'selected' : '' }}>{{ __('90 minutes before (1.5 hours)') }}</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                                    {{ __('Custom Warning Message (Optional)') }}
                                </label>
                                <input type="text" name="closing_warning_message" value="{{ $locWarningMessage }}" placeholder="{{ __('e.g. The kitchen is closing soon, please finalize your order') }}" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.92rem; font-weight: 600;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            
                    <!-- 5. SERVICE FEE CARD -->
            <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.9rem;">
                        <span style="width: 42px; height: 42px; border-radius: 12px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">
                            <i class="fa-solid fa-bell-concierge"></i>
                        </span>
                        <div>
                            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                {{ __('Service Fee (Dine-in)') }}
                            </h3>
                            <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-muted); word-break: break-word;">
                                {{ __('Charged on orders placed by dine-in guests at tables') }}
                            </p>
                        </div>
                    </div>

                    <!-- Enable Switch -->
                    <label class="modern-switch-wrapper" style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; user-select: none;">
                        <span style="font-size: 0.9rem; font-weight: 700; color: var(--text-main);" id="serviceFeeStatusLabel">
                            {{ old('service_fee_enabled', $vendor->service_fee_enabled) ? __('Active') : __('Disabled') }}
                        </span>
                        <input type="checkbox" name="service_fee_enabled" value="1" id="serviceFeeToggle" {{ old('service_fee_enabled', $vendor->service_fee_enabled) ? 'checked' : '' }} onchange="toggleServiceFeeFields()" style="width: 20px; height: 20px; accent-color: var(--primary); cursor: pointer;">
                    </label>
                </div>

                <div id="serviceFeeContainer" style="{{ old('service_fee_enabled', $vendor->service_fee_enabled) ? '' : 'opacity: 0.55; pointer-events: none;' }}; transition: opacity 0.2s ease;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 1.25rem;">
                        <!-- Fee Type -->
                        <div class="form-group">
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                                {{ __('Fee Type') }}
                            </label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <label class="type-pill {{ old('service_fee_type', $vendor->service_fee_type ?? 'percent') === 'percent' ? 'selected' : '' }}" id="pillPercent" style="cursor: pointer; border: 1.5px solid var(--border-color); border-radius: 12px; padding: 0.75rem; display: flex; align-items: center; gap: 0.5rem; justify-content: center; font-weight: 700; font-size: 0.88rem; transition: all 0.2s;">
                                    <input type="radio" name="service_fee_type" value="percent" {{ old('service_fee_type', $vendor->service_fee_type ?? 'percent') === 'percent' ? 'checked' : '' }} onchange="handleFeeTypeChange('percent')" style="display: none;">
                                    <i class="fa-solid fa-percent"></i> {{ __('Percentage (%)') }}
                                </label>
                                <label class="type-pill {{ old('service_fee_type', $vendor->service_fee_type) === 'fixed' ? 'selected' : '' }}" id="pillFixed" style="cursor: pointer; border: 1.5px solid var(--border-color); border-radius: 12px; padding: 0.75rem; display: flex; align-items: center; gap: 0.5rem; justify-content: center; font-weight: 700; font-size: 0.88rem; transition: all 0.2s;">
                                    <input type="radio" name="service_fee_type" value="fixed" {{ old('service_fee_type', $vendor->service_fee_type) === 'fixed' ? 'checked' : '' }} onchange="handleFeeTypeChange('fixed')" style="display: none;">
                                    <i class="fa-solid fa-coins"></i> {{ __('Fixed Amount') }}
                                </label>
                            </div>
                        </div>

                        <!-- Fee Value -->
                        <div class="form-group">
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                                {{ __('Service Fee Amount') }} <span style="color: #ef4444;">*</span>
                            </label>
                            <div style="position: relative; display: flex; align-items: center;">
                                <input type="number" step="any" min="0" name="service_fee_value" id="serviceFeeValue" value="{{ old('service_fee_value', $vendor->service_fee_value ?? 10) }}" required class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem; font-size: 1rem; font-weight: 700;" oninput="updateCalculationsPreview()">
                                <span id="serviceFeeSuffix" style="position: absolute; right: 1rem; font-weight: 800; color: var(--primary); font-size: 0.95rem;">
                                    {{ old('service_fee_type', $vendor->service_fee_type ?? 'percent') === 'percent' ? '%' : $vendor->currency }}
                                </span>
                            </div>
                            <span style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                                {{ __('e.g. 10% or 500 ֏') }}
                            </span>
                        </div>

                        <!-- Minimum Order for Service Fee -->
                        <div class="form-group">
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                                {{ __('Minimum Order Amount') }}
                            </label>
                            <div style="position: relative; display: flex; align-items: center;">
                                <input type="number" step="any" min="0" name="service_fee_min_order" id="serviceFeeMinOrder" value="{{ old('service_fee_min_order', $vendor->service_fee_min_order) }}" placeholder="0" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem; font-size: 1rem; font-weight: 700;" oninput="updateCalculationsPreview()">
                                <span class="currency-label-display" style="position: absolute; right: 1rem; font-weight: 800; color: var(--text-muted); font-size: 0.85rem;">
                                    {{ $vendor->currency }}
                                </span>
                            </div>
                            <span style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                                {{ __('The fee applies only if the order exceeds this amount (empty = applies to all orders)') }}
                            </span>
                        </div>
                    </div>

                    <!-- Live Calculation Preview Card -->
                    <div style="margin-top: 1.25rem; background: var(--bg-body); border: 1px dashed var(--border-color); border-radius: 14px; padding: 1rem 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
                        <div style="display: flex; align-items: center; gap: 0.65rem;">
                            <span style="color: var(--primary); font-size: 1.1rem;"><i class="fa-solid fa-calculator"></i></span>
                            <span style="font-size: 0.88rem; color: var(--text-muted);">
                                {{ __('Calculation example for 15,000') }} <span class="currency-label-display">{{ $vendor->currency }}</span> {{ __('order:') }}
                            </span>
                        </div>
                        <div style="font-size: 0.95rem; font-weight: 800; color: var(--text-main);" id="serviceFeePreviewBox">
                            —
                        </div>
                    </div>
                </div>
            </div>

            
                    <!-- 6. ESC/POS THERMAL PRINTERS & RECEIPT STUDIO -->
            <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.9rem;">
                        <span style="width: 44px; height: 44px; border-radius: 14px; background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(217, 119, 6, 0.3)); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);">
                            <i class="fa-solid fa-receipt"></i>
                        </span>
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                    {{ __('Կտրոնի Դիզայն և ESC/POS Ջերմային Տպիչներ') }}
                                </h3>
                                <span style="font-size: 0.72rem; font-weight: 700; background: rgba(99, 102, 241, 0.12); color: #6366f1; border: 1px solid rgba(99, 102, 241, 0.25); padding: 0.15rem 0.55rem; border-radius: 20px;">
                                    Receipt Studio
                                </span>
                            </div>
                            <p style="margin: 0.25rem 0 0; font-size: 0.85rem; color: var(--text-muted); word-break: break-word;">
                                {{ __('Կարգավորեք կտրոնի մոդեռն տեսքը, չափսերը (80մմ / 58մմ), լեզուն, տպվող տարրերը, խոհանոցի/բարի IP տպիչը և email ծանուցումները') }}
                            </p>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted);">{{ __('Ավտոմատ տպում') }}</span>
                        <label class="switch" style="position: relative; display: inline-block; width: 48px; height: 26px;">
                            <input type="checkbox" name="thermal_printer_settings[auto_print_live_orders]" value="1" {{ !empty($printerSettings['auto_print_live_orders']) ? 'checked' : '' }}>
                            <span class="slider round" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 34px;"></span>
                        </label>
                    </div>
                </div>

                <!-- 2-COLUMN LAYOUT: SETTINGS ON LEFT, LIVE PREVIEW ON RIGHT -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 460px), 1fr)); gap: 1.75rem; align-items: start;">
                    
                    <!-- LEFT COLUMN: CONTROLS -->
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        
                        <!-- 1. Sizing & Typography -->
                        <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; color: var(--text-main); font-weight: 700; font-size: 0.95rem;">
                                <i class="fa-solid fa-sliders" style="color: #6366f1;"></i>
                                <span>{{ __('Չափսեր, Դիզայն և Լեզու') }}</span>
                            </div>

                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                                        {{ __('Թղթի Չափս (Paper Width)') }}
                                    </label>
                                    <select id="receiptPaperWidthSelect" name="thermal_printer_settings[paper_width]" onchange="updateReceiptLivePreview()" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.65rem 0.9rem; border-radius: 10px; font-weight: 600; font-size: 0.88rem;">
                                        <option value="80mm" {{ ($printerSettings['paper_width'] ?? '80mm') === '80mm' ? 'selected' : '' }}>80 mm (Ստանդարտ ռեստորանային)</option>
                                        <option value="58mm" {{ ($printerSettings['paper_width'] ?? '') === '58mm' ? 'selected' : '' }}>58 mm (Կոմպակտ մինի)</option>
                                    </select>
                                </div>

                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                                        {{ __('Դիզայնի Ոճ (Design Style)') }}
                                    </label>
                                    <select id="receiptDesignStyleSelect" name="thermal_printer_settings[design_style]" onchange="updateReceiptLivePreview()" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.65rem 0.9rem; border-radius: 10px; font-weight: 600; font-size: 0.88rem;">
                                        <option value="modern" {{ ($printerSettings['design_style'] ?? 'modern') === 'modern' ? 'selected' : '' }}>✨ Մոդեռն Մինիմալիստական (Modern Minimal)</option>
                                        <option value="classic" {{ ($printerSettings['design_style'] ?? '') === 'classic' ? 'selected' : '' }}>📟 Կլասիկ ESC/POS (Monospace)</option>
                                    </select>
                                </div>

                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                                        {{ __('Կտրոնի Լեզու (Receipt Language)') }}
                                    </label>
                                    <select id="receiptLanguageSelect" name="thermal_printer_settings[receipt_language]" onchange="updateReceiptLivePreview()" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.65rem 0.9rem; border-radius: 10px; font-weight: 600; font-size: 0.88rem;">
                                        <option value="bilingual" {{ ($printerSettings['receipt_language'] ?? 'bilingual') === 'bilingual' ? 'selected' : '' }}>🌐 Երկլեզու (Հայերեն + English)</option>
                                        <option value="hy" {{ ($printerSettings['receipt_language'] ?? '') === 'hy' ? 'selected' : '' }}>🇦🇲 Հայերեն միայն (Armenian)</option>
                                        <option value="en" {{ ($printerSettings['receipt_language'] ?? '') === 'en' ? 'selected' : '' }}>🇬🇧 English only</option>
                                        <option value="ru" {{ ($printerSettings['receipt_language'] ?? '') === 'ru' ? 'selected' : '' }}>🇷🇺 Русский</option>
                                    </select>
                                </div>

                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                                        {{ __('Տառաչափ (Font Size)') }}
                                    </label>
                                    <select id="receiptFontSizeSelect" name="thermal_printer_settings[font_size]" onchange="updateReceiptLivePreview()" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.65rem 0.9rem; border-radius: 10px; font-weight: 600; font-size: 0.88rem;">
                                        <option value="compact" {{ ($printerSettings['font_size'] ?? '') === 'compact' ? 'selected' : '' }}>Կոմպակտ / Խտացված</option>
                                        <option value="normal" {{ ($printerSettings['font_size'] ?? 'normal') === 'normal' ? 'selected' : '' }}>Ստանդարտ (Օպտիմալ)</option>
                                        <option value="large" {{ ($printerSettings['font_size'] ?? '') === 'large' ? 'selected' : '' }}>Խոշոր / Ընդարձակ</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Header & Footer texts -->
                        <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; color: var(--text-main); font-weight: 700; font-size: 0.95rem;">
                                <i class="fa-solid fa-heading" style="color: #10b981;"></i>
                                <span>{{ __('Վերնագիր և Տողատակ (Header & Footer)') }}</span>
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 0.9rem;">
                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                                        {{ __('Գլխագիր Վերնագիր (Ռեստորանի անվանում)') }}
                                    </label>
                                    <input type="text" id="receiptHeaderTitleInput" name="thermal_printer_settings[header_title]" value="{{ $printerSettings['header_title'] ?? $vendor->name }}" oninput="updateReceiptLivePreview()" placeholder="{{ $vendor->name }}" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.65rem 0.9rem; border-radius: 10px; font-weight: 600; font-size: 0.88rem;">
                                </div>

                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                                        {{ __('Ենթավերնագիր (Մասնաճյուղ, կարգախոս կամ հասցե)') }}
                                    </label>
                                    <input type="text" id="receiptHeaderSubtitleInput" name="thermal_printer_settings[header_subtitle]" value="{{ $printerSettings['header_subtitle'] ?? '' }}" oninput="updateReceiptLivePreview()" placeholder="օր. Կասկադ մասնաճյուղ / Cascade Branch" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.65rem 0.9rem; border-radius: 10px; font-weight: 600; font-size: 0.88rem;">
                                </div>

                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                                        {{ __('Տողատակի Տեքստ (Footer message)') }}
                                    </label>
                                    <input type="text" id="receiptFooterTextInput" name="thermal_printer_settings[footer_text]" value="{{ $printerSettings['footer_text'] ?? 'Շնորհակալություն այցելության համար!' }}" oninput="updateReceiptLivePreview()" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.65rem 0.9rem; border-radius: 10px; font-weight: 600; font-size: 0.88rem;">
                                </div>
                            </div>
                        </div>

                        <!-- 3. Element Visibility Toggles -->
                        <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-main); font-weight: 700; font-size: 0.95rem;">
                                    <i class="fa-solid fa-eye" style="color: #f59e0b;"></i>
                                    <span>{{ __('Ցուցադրվող Տարրեր (Show / Hide Elements)') }}</span>
                                </div>
                                <span style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Անհատական կարգավորում') }}</span>
                            </div>

                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 0.85rem;">
                                <label style="display: flex; align-items: center; gap: 0.6rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; color: var(--text-main); background: var(--bg-card); padding: 0.6rem 0.8rem; border-radius: 10px; border: 1px solid var(--border-color);">
                                    <input type="checkbox" id="toggleShowLogo" name="thermal_printer_settings[show_logo]" value="1" onchange="updateReceiptLivePreview()" {{ !empty($printerSettings['show_logo']) ? 'checked' : '' }} style="accent-color: #6366f1; width: 17px; height: 17px;">
                                    <span>{{ __('Ռեստորանի լոգո') }}</span>
                                </label>

                                <label style="display: flex; align-items: center; gap: 0.6rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; color: var(--text-main); background: var(--bg-card); padding: 0.6rem 0.8rem; border-radius: 10px; border: 1px solid var(--border-color);">
                                    <input type="checkbox" id="toggleCustomerInfo" name="thermal_printer_settings[print_customer_info]" value="1" onchange="updateReceiptLivePreview()" {{ !empty($printerSettings['print_customer_info']) ? 'checked' : '' }} style="accent-color: #6366f1; width: 17px; height: 17px;">
                                    <span>{{ __('Հաճախորդի տվյալներ') }}</span>
                                </label>

                                <label style="display: flex; align-items: center; gap: 0.6rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; color: var(--text-main); background: var(--bg-card); padding: 0.6rem 0.8rem; border-radius: 10px; border: 1px solid var(--border-color);">
                                    <input type="checkbox" id="toggleOrderType" name="thermal_printer_settings[print_order_type]" value="1" onchange="updateReceiptLivePreview()" {{ !empty($printerSettings['print_order_type']) ? 'checked' : '' }} style="accent-color: #6366f1; width: 17px; height: 17px;">
                                    <span>{{ __('Պատվերի տեսակ & Սեղան') }}</span>
                                </label>

                                <label style="display: flex; align-items: center; gap: 0.6rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; color: var(--text-main); background: var(--bg-card); padding: 0.6rem 0.8rem; border-radius: 10px; border: 1px solid var(--border-color);">
                                    <input type="checkbox" id="togglePaymentDetails" name="thermal_printer_settings[print_payment_details]" value="1" onchange="updateReceiptLivePreview()" {{ !empty($printerSettings['print_payment_details']) ? 'checked' : '' }} style="accent-color: #6366f1; width: 17px; height: 17px;">
                                    <span>{{ __('Վճարման եղանակ') }}</span>
                                </label>

                                <label style="display: flex; align-items: center; gap: 0.6rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; color: var(--text-main); background: var(--bg-card); padding: 0.6rem 0.8rem; border-radius: 10px; border: 1px solid var(--border-color);">
                                    <input type="checkbox" id="togglePrices" name="thermal_printer_settings[print_prices]" value="1" onchange="updateReceiptLivePreview()" {{ !empty($printerSettings['print_prices']) ? 'checked' : '' }} style="accent-color: #6366f1; width: 17px; height: 17px;">
                                    <span>{{ __('Ապրանքների գներ և ընդհանուր') }}</span>
                                </label>

                                <label style="display: flex; align-items: center; gap: 0.6rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; color: var(--text-main); background: var(--bg-card); padding: 0.6rem 0.8rem; border-radius: 10px; border: 1px solid var(--border-color);">
                                    <input type="checkbox" id="toggleItemNotes" name="thermal_printer_settings[print_item_notes]" value="1" onchange="updateReceiptLivePreview()" {{ !empty($printerSettings['print_item_notes']) ? 'checked' : '' }} style="accent-color: #6366f1; width: 17px; height: 17px;">
                                    <span>{{ __('Հաճախորդի նշումներ (Notes)') }}</span>
                                </label>

                                <label style="display: flex; align-items: center; gap: 0.6rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; color: var(--text-main); background: var(--bg-card); padding: 0.6rem 0.8rem; border-radius: 10px; border: 1px solid var(--border-color);">
                                    <input type="checkbox" id="toggleFeeBreakdown" name="thermal_printer_settings[print_fee_breakdown]" value="1" onchange="updateReceiptLivePreview()" {{ !empty($printerSettings['print_fee_breakdown']) ? 'checked' : '' }} style="accent-color: #6366f1; width: 17px; height: 17px;">
                                    <span>{{ __('Սպասարկման / Առաքման վճար') }}</span>
                                </label>

                                <label style="display: flex; align-items: center; gap: 0.6rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; color: var(--text-main); background: var(--bg-card); padding: 0.6rem 0.8rem; border-radius: 10px; border: 1px solid var(--border-color);">
                                    <input type="checkbox" id="toggleQrCode" name="thermal_printer_settings[print_qr_code]" value="1" onchange="updateReceiptLivePreview()" {{ !empty($printerSettings['print_qr_code']) ? 'checked' : '' }} style="accent-color: #6366f1; width: 17px; height: 17px;">
                                    <span>{{ __('Թվային կտրոնի QR կոդ') }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- 4. Network / IP Thermal Printer (Kitchen & Bar) -->
                        <div style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.05), rgba(99, 102, 241, 0.08)); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 16px; padding: 1.25rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
                                <div style="display: flex; align-items: center; gap: 0.6rem;">
                                    <span style="width: 32px; height: 32px; border-radius: 8px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                                        <i class="fa-solid fa-network-wired"></i>
                                    </span>
                                    <div>
                                        <div style="font-size: 0.95rem; font-weight: 800; color: var(--text-main);">
                                            {{ __('Խոհանոցի և Բարի Ցանցային Տպիչներ (IP Printer)') }}
                                        </div>
                                        <div style="font-size: 0.78rem; color: var(--text-muted);">
                                            {{ __('Ուղիղ TCP Socket տպում տեղային ցանցի (Wi-Fi/LAN) ջերմային տպիչին') }}
                                        </div>
                                    </div>
                                </div>

                                <label class="switch" style="position: relative; display: inline-block; width: 44px; height: 24px;">
                                    <input type="checkbox" name="thermal_printer_settings[network_printer_enabled]" value="1" {{ !empty($printerSettings['network_printer_enabled']) ? 'checked' : '' }} id="networkPrinterToggle">
                                    <span class="slider round" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 34px;"></span>
                                </label>
                            </div>

                            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 0.9rem; margin-bottom: 0.9rem;">
                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                                        {{ __('Տպիչի IP Հասցե (IP Address)') }}
                                    </label>
                                    <div style="position: relative;">
                                        <i class="fa-solid fa-server" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem;"></i>
                                        <input type="text" id="printerIpInput" name="thermal_printer_settings[network_printer_ip]" value="{{ $printerSettings['network_printer_ip'] ?? '' }}" placeholder="օր. 192.168.1.200" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.65rem 0.9rem 0.65rem 2.25rem; border-radius: 10px; font-weight: 600; font-size: 0.88rem; font-family: monospace;">
                                    </div>
                                </div>

                                <div>
                                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                                        {{ __('Պորտ (Port)') }}
                                    </label>
                                    <input type="number" id="printerPortInput" name="thermal_printer_settings[network_printer_port]" value="{{ $printerSettings['network_printer_port'] ?? 9100 }}" placeholder="9100" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.65rem 0.9rem; border-radius: 10px; font-weight: 600; font-size: 0.88rem; font-family: monospace;">
                                </div>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                                <label style="display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.83rem; font-weight: 600; cursor: pointer; color: var(--text-main);">
                                    <input type="checkbox" name="thermal_printer_settings[auto_print_on_network]" value="1" {{ !empty($printerSettings['auto_print_on_network']) ? 'checked' : '' }} style="accent-color: #3b82f6;">
                                    <span>{{ __('Պատվեր ստանալիս ավտոմատ ուղարկել IP տպիչին') }}</span>
                                </label>

                                <button type="button" id="testPrinterBtn" onclick="testPrinterConnection()" class="btn" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; border: none; padding: 0.55rem 1rem; border-radius: 10px; font-weight: 700; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 0.45rem; cursor: pointer; box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);">
                                    <i class="fa-solid fa-plug"></i>
                                    <span>{{ __('Ստուգել կապը և Տպել թեստ') }}</span>
                                </button>
                            </div>

                            <div id="printerTestResult" style="display: none; margin-top: 0.9rem; padding: 0.75rem 1rem; border-radius: 10px; font-size: 0.85rem; font-weight: 600;"></div>
                        </div>

                        <!-- 5. Automatic Email Receipt Upon Order Close -->
                        <div style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.05), rgba(5, 150, 105, 0.08)); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 16px; padding: 1.25rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.9rem; flex-wrap: wrap; gap: 0.75rem;">
                                <div style="display: flex; align-items: center; gap: 0.6rem;">
                                    <span style="width: 32px; height: 32px; border-radius: 8px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                                        <i class="fa-solid fa-paper-plane"></i>
                                    </span>
                                    <div>
                                        <div style="font-size: 0.95rem; font-weight: 800; color: var(--text-main);">
                                            {{ __('Էլ. Փոստով Կտրոնի Ուղարկում (Email Receipts)') }}
                                        </div>
                                        <div style="font-size: 0.78rem; color: var(--text-muted);">
                                            {{ __('Պատվերի փակումից հետո (Completed) կտրոնն ավտոմատ ուղարկվում է հաճախորդին') }}
                                        </div>
                                    </div>
                                </div>

                                <label class="switch" style="position: relative; display: inline-block; width: 44px; height: 24px;">
                                    <input type="checkbox" name="thermal_printer_settings[email_receipt_enabled]" value="1" {{ !empty($printerSettings['email_receipt_enabled']) ? 'checked' : '' }}>
                                    <span class="slider round" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 34px;"></span>
                                </label>
                            </div>

                            <div>
                                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem;">
                                    {{ __('Էլ. Փոստի Կտրոնի Լեզու') }}
                                </label>
                                <select name="thermal_printer_settings[email_receipt_language]" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.65rem 0.9rem; border-radius: 10px; font-weight: 600; font-size: 0.88rem;">
                                    <option value="bilingual" {{ ($printerSettings['email_receipt_language'] ?? 'bilingual') === 'bilingual' ? 'selected' : '' }}>🌐 Երկլեզու՝ Հայերեն և English (Խորհուրդ է տրվում)</option>
                                    <option value="hy" {{ ($printerSettings['email_receipt_language'] ?? '') === 'hy' ? 'selected' : '' }}>🇦🇲 Հայերեն տարբերակ (Armenian only)</option>
                                    <option value="en" {{ ($printerSettings['email_receipt_language'] ?? '') === 'en' ? 'selected' : '' }}>🇬🇧 English version only</option>
                                </select>
                            </div>
                        </div>

                    </div>

                    <!-- RIGHT COLUMN: INTERACTIVE LIVE PREVIEW -->
                    <div style="position: sticky; top: 1.5rem; display: flex; flex-direction: column; align-items: center;">
                        <div style="width: 100%; display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; padding: 0 0.5rem;">
                            <span style="font-size: 0.82rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">
                                <i class="fa-solid fa-eye" style="margin-right: 0.35rem; color: #6366f1;"></i> {{ __('Կենդանի Նախադիտում (Live Preview)') }}
                            </span>
                            <span id="previewWidthBadge" style="font-size: 0.75rem; font-weight: 700; background: rgba(99, 102, 241, 0.12); color: #6366f1; padding: 0.15rem 0.55rem; border-radius: 12px;">
                                80 mm
                            </span>
                        </div>

                        <!-- RECEIPT SLIP PAPER WRAPPER -->
                        <div id="receiptSlipContainer" style="width: 100%; max-width: 380px; background: #ffffff; color: #111827; border-radius: 16px; box-shadow: 0 20px 45px rgba(0,0,0,0.18), 0 4px 12px rgba(0,0,0,0.08); border: 1px solid rgba(0,0,0,0.06); padding: 24px 20px 28px; position: relative; transition: all 0.25s ease; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            
                            <!-- HEADER -->
                            <div style="text-align: center; margin-bottom: 14px;">
                                @if(!empty($vendor->logo))
                                    <div id="previewLogo" style="margin-bottom: 8px; {{ empty($printerSettings['show_logo']) ? 'display: none;' : '' }}">
                                        <img src="{{ asset('storage/' . $vendor->logo) }}" alt="Logo" style="max-height: 48px; max-width: 120px; object-fit: contain; filter: grayscale(100%) contrast(140%);">
                                    </div>
                                @endif
                                <h4 id="previewTitle" style="margin: 0; font-size: 16px; font-weight: 800; color: #000; letter-spacing: -0.3px;">
                                    {{ $printerSettings['header_title'] ?? $vendor->name }}
                                </h4>
                                <div id="previewSubtitle" style="font-size: 11px; color: #6b7280; margin-top: 2px;">
                                    {{ $printerSettings['header_subtitle'] ?? 'Կասկադ մասնաճյուղ • Yerevan' }}
                                </div>
                            </div>

                            <div style="border-top: 1px dashed #d1d5db; margin: 12px 0;"></div>

                            <!-- ORDER METADATA -->
                            <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
                                <span style="font-weight: 700; color: #111;">ՊԱՏՎԵՐ #ORD-1042</span>
                                <span style="color: #6b7280;">{{ date('d.m.Y H:i') }}</span>
                            </div>

                            <div id="previewOrderTypeRow" style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px; {{ empty($printerSettings['print_order_type']) ? 'display: none;' : '' }}">
                                <span style="color: #6b7280;">Տեսակ / Սեղան:</span>
                                <span style="font-weight: 600; color: #111;">Սրահ (Dine-in) • Սեղան #4</span>
                            </div>

                            <div id="previewCustomerRow" style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px; {{ empty($printerSettings['print_customer_info']) ? 'display: none;' : '' }}">
                                <span style="color: #6b7280;">Հաճախորդ:</span>
                                <span style="font-weight: 600; color: #111;">Գագիկ Հովհաննիսյան (+37494112233)</span>
                            </div>

                            <div id="previewPaymentRow" style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px; {{ empty($printerSettings['print_payment_details']) ? 'display: none;' : '' }}">
                                <span style="color: #6b7280;">Վճարում:</span>
                                <span style="font-weight: 700; color: #059669;">Կանխիկ (Վճարված)</span>
                            </div>

                            <div style="border-top: 1px dashed #d1d5db; margin: 12px 0;"></div>

                            <!-- ITEMS TABLE -->
                            <table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 8px;">
                                <thead>
                                    <tr style="border-bottom: 1px solid #e5e7eb; color: #6b7280; font-size: 11px;">
                                        <th style="text-align: left; padding: 4px 0; font-weight: 700;">ԱՊՐԱՆՔ</th>
                                        <th style="text-align: center; padding: 4px 0; font-weight: 700; width: 35px;">ՔԱՆ.</th>
                                        <th class="previewPriceCol" style="text-align: right; padding: 4px 0; font-weight: 700; {{ empty($printerSettings['print_prices']) ? 'display: none;' : '' }}">ԳԻՆ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding: 6px 0; font-weight: 600; color: #111;">
                                            Truffle Burger
                                            <div id="previewItemNotes" style="font-size: 10px; color: #d97706; margin-top: 2px; {{ empty($printerSettings['print_item_notes']) ? 'display: none;' : '' }}">
                                                ↳ Առանց սոխ, medium rare
                                            </div>
                                        </td>
                                        <td style="text-align: center; padding: 6px 0; color: #374151;">x2</td>
                                        <td class="previewPriceCol" style="text-align: right; padding: 6px 0; font-weight: 600; color: #111; {{ empty($printerSettings['print_prices']) ? 'display: none;' : '' }}">7,000 ֏</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 6px 0; font-weight: 600; color: #111;">
                                            Parmesan Fries
                                        </td>
                                        <td style="text-align: center; padding: 6px 0; color: #374151;">x1</td>
                                        <td class="previewPriceCol" style="text-align: right; padding: 6px 0; font-weight: 600; color: #111; {{ empty($printerSettings['print_prices']) ? 'display: none;' : '' }}">1,800 ֏</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 6px 0; font-weight: 600; color: #111;">
                                            Berry Lemonade
                                        </td>
                                        <td style="text-align: center; padding: 6px 0; color: #374151;">x2</td>
                                        <td class="previewPriceCol" style="text-align: right; padding: 6px 0; font-weight: 600; color: #111; {{ empty($printerSettings['print_prices']) ? 'display: none;' : '' }}">2,400 ֏</td>
                                    </tr>
                                </tbody>
                            </table>

                            <div style="border-top: 1px dashed #d1d5db; margin: 10px 0;"></div>

                            <!-- TOTALS & FEES -->
                            <div id="previewFeeBreakdown" style="{{ empty($printerSettings['print_fee_breakdown']) ? 'display: none;' : '' }}">
                                <div style="display: flex; justify-content: space-between; font-size: 11px; color: #6b7280; margin-bottom: 3px;">
                                    <span>Միջանկյալ / Subtotal:</span>
                                    <span>11,200 ֏</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-size: 11px; color: #6b7280; margin-bottom: 3px;">
                                    <span>Սպասարկում / Service (10%):</span>
                                    <span>1,120 ֏</span>
                                </div>
                            </div>

                            <div class="previewPriceCol" style="display: flex; justify-content: space-between; align-items: baseline; margin-top: 6px; padding-top: 6px; border-top: 1px solid #111; {{ empty($printerSettings['print_prices']) ? 'display: none;' : '' }}">
                                <span style="font-size: 14px; font-weight: 800; color: #000; letter-spacing: -0.2px;">ԸՆԴԱՄԵՆԸ / TOTAL:</span>
                                <span style="font-size: 17px; font-weight: 900; color: #000;">12,320 ֏</span>
                            </div>

                            <!-- QR CODE -->
                            <div id="previewQrBlock" style="text-align: center; margin-top: 16px; padding-top: 12px; border-top: 1px dashed #d1d5db; {{ empty($printerSettings['print_qr_code']) ? 'display: none;' : '' }}">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=https%3A%2F%2Fqrmenu.test%2Forder%2FORD-1042" alt="QR" style="width: 80px; height: 80px; margin: 0 auto; display: block; border-radius: 6px;">
                                <div style="font-size: 10px; color: #6b7280; margin-top: 4px;">Սկանավորեք թվային կտրոնի համար</div>
                            </div>

                            <!-- FOOTER TEXT -->
                            <div id="previewFooter" style="text-align: center; font-size: 11px; color: #4b5563; font-style: italic; margin-top: 14px;">
                                {{ $printerSettings['footer_text'] ?? 'Շնորհակալություն այցելության համար!' }}
                            </div>

                            <!-- ZIGZAG PAPER TEAR BOTTOM EFFECT -->
                            <div style="position: absolute; bottom: -8px; left: 0; right: 0; height: 8px; background: radial-gradient(circle, transparent, transparent 50%, #ffffff 50%, #ffffff 100%); background-size: 12px 12px;"></div>
                        </div>

                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 1.25rem; text-align: center;">
                            <i class="fa-solid fa-circle-info"></i> {{ __('Փոփոխություններն ավտոմատ արտացոլվում են նախադիտման մեջ:') }}
                        </div>
                    </div>

                </div>
            </div>

            
                </div>

                <!-- 3. RESERVATIONS TAB -->
                <div x-show="activeTab === 'reservations'" x-transition.opacity role="tabpanel" style="display: flex; flex-direction: column; gap: 1.75rem;">
                    <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                            <div style="display: flex; align-items: center; gap: 0.9rem;">
                                <span style="width: 42px; height: 42px; border-radius: 12px; background: rgba(236, 72, 153, 0.15); color: #ec4899; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">
                                    <i class="fa-solid fa-calendar-check"></i>
                                </span>
                                <div>
                                    <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                        {{ __('Reservations & Booking Rules') }}
                                    </h3>
                                    <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-muted); word-break: break-word;">
                                        {{ __('Manage table booking availability, advance windows, table holding duration, and reservation reminders') }}
                                    </p>
                                </div>
                            </div>

                            @if($vendor->hasFeature('reservations'))
                                <span style="font-size: 0.78rem; font-weight: 700; background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; padding: 0.3rem 0.65rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-circle-check"></i> {{ __('Active') }}
                                </span>
                            @else
                                <span style="font-size: 0.78rem; font-weight: 700; background: rgba(245, 158, 11, 0.2); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.4); border-radius: 8px; padding: 0.3rem 0.65rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                                    🔒 PRO
                                </span>
                            @endif
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap: 1.25rem;">
                            <!-- Card 1: Capacity & Rules -->
                            <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between;">
                                <div>
                                    <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.4rem; display: flex; align-items: center; gap: 0.4rem;">
                                        <i class="fa-solid fa-sliders" style="color: #8b5cf6;"></i> {{ __('Booking Rules & Capacity') }}
                                    </div>
                                    <div style="font-size: 1rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.35rem;">
                                        {{ __('Table Capacity & Durations') }}
                                    </div>
                                    <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0 0 1rem 0; line-height: 1.4;">
                                        {{ __('Configure time slots, advance booking window, minimum notice, buffer intervals, and guest confirmation rules.') }}
                                    </p>
                                </div>
                                <a href="{{ route('admin.settings.reservations') }}" class="btn btn-secondary" style="font-size: 0.82rem; padding: 0.5rem 0.95rem; border-radius: 10px; width: 100%; justify-content: center; text-decoration: none; font-weight: 700;">
                                    <i class="fa-solid fa-arrow-up-right-from-square" style="color: #8b5cf6;"></i> {{ __('Configure Booking Rules') }}
                                </a>
                            </div>

                            <!-- Card 2: Notifications -->
                            <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between;">
                                <div>
                                    <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.4rem; display: flex; align-items: center; gap: 0.4rem;">
                                        <i class="fa-solid fa-envelope" style="color: #ec4899;"></i> {{ __('Notifications & Email') }}
                                    </div>
                                    <div style="font-size: 1rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.35rem;">
                                        {{ __('Customer Confirmations') }}
                                    </div>
                                    <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0 0 1rem 0; line-height: 1.4;">
                                        {{ __('Customized email templates for booking confirmation, arrival reminders, status change notices, and cancelation.') }}
                                    </p>
                                </div>
                                <a href="{{ route('admin.settings.reservations.notifications') }}" class="btn btn-secondary" style="font-size: 0.82rem; padding: 0.5rem 0.95rem; border-radius: 10px; width: 100%; justify-content: center; text-decoration: none; font-weight: 700;">
                                    <i class="fa-solid fa-arrow-up-right-from-square" style="color: #ec4899;"></i> {{ __('Edit Email Templates') }}
                                </a>
                            </div>

                            <!-- Card 3: Tables & Map -->
                            <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between;">
                                <div>
                                    <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.4rem; display: flex; align-items: center; gap: 0.4rem;">
                                        <i class="fa-solid fa-chair" style="color: #06b6d4;"></i> {{ __('Dining Room & Tables') }}
                                    </div>
                                    <div style="font-size: 1rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.35rem;">
                                        {{ __('Tables & Floor Plan') }}
                                    </div>
                                    <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0 0 1rem 0; line-height: 1.4;">
                                        {{ __('Add and organize restaurant dining tables, assign seating capacities, and arrange the interactive floor plan.') }}
                                    </p>
                                </div>
                                <div style="display: flex; gap: 0.5rem;">
                                    <a href="{{ route('admin.tables.index') }}" class="btn btn-secondary" style="font-size: 0.82rem; padding: 0.5rem 0.65rem; border-radius: 10px; flex: 1; justify-content: center; text-decoration: none; font-weight: 700;">
                                        <i class="fa-solid fa-chair" style="color: #06b6d4;"></i> {{ __('Tables') }}
                                    </a>
                                    <a href="{{ route('admin.floor_plan.index') }}" class="btn btn-secondary" style="font-size: 0.82rem; padding: 0.5rem 0.65rem; border-radius: 10px; flex: 1; justify-content: center; text-decoration: none; font-weight: 700;">
                                        <i class="fa-solid fa-map" style="color: #3b82f6;"></i> {{ __('Floor Plan') }}
                                    </a>
                                </div>
                            </div>

                            <!-- Card 4: Event Planning Inquiries -->
                            <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between;">
                                <div>
                                    <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.4rem; display: flex; align-items: center; gap: 0.4rem;">
                                        <i class="fa-solid fa-champagne-glasses" style="color: #a855f7;"></i> {{ __('Private Events & Banquets') }}
                                    </div>
                                    <div style="font-size: 1rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.35rem;">
                                        {{ __('Event Planning Inquiries') }}
                                    </div>
                                    <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0 0 1rem 0; line-height: 1.4;">
                                        {{ __('Configure public inquiry form, event types, guest limits, optional fields, and email notifications.') }}
                                    </p>
                                </div>
                                <a href="{{ route('admin.settings.reservations.events') }}" class="btn btn-secondary" style="font-size: 0.82rem; padding: 0.5rem 0.95rem; border-radius: 10px; width: 100%; justify-content: center; text-decoration: none; font-weight: 700;">
                                    <i class="fa-solid fa-arrow-up-right-from-square" style="color: #a855f7;"></i> {{ __('Configure Event Inquiries') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. PAYMENTS & GATEWAYS TAB -->
                <div x-show="activeTab === 'payments'" x-transition.opacity role="tabpanel" style="display: flex; flex-direction: column; gap: 1.75rem;">
                    <!-- 5. ONLINE PAYMENTS & GATEWAYS -->
            <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.9rem;">
                        <span style="width: 42px; height: 42px; border-radius: 12px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">
                            <i class="fa-solid fa-credit-card"></i>
                        </span>
                        <div>
                            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                {{ __('Payment Systems (Local & International Payments)') }}
                            </h3>
                            <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-muted); word-break: break-word;">
                                {{ __('Manage online and on-premise payment methods for customers') }}
                            </p>
                        </div>
                    </div>

                    <label class="switch" style="position: relative; display: inline-block; width: 48px; height: 26px;">
                        <input type="checkbox" name="payment_settings[online_enabled]" value="1" {{ !empty($paymentSettings['online_enabled']) ? 'checked' : '' }} id="onlinePaymentsToggle" onchange="toggleOnlinePaymentsFields()">
                        <span class="slider round" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 34px;"></span>
                    </label>
                </div>

                <!-- Payment Methods Choice (Cash / Terminal) -->
                <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
                    <div style="font-size: 0.88rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.75rem;">
                        {{ __('General Payment Methods in Cart') }}
                    </div>
                    <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                        <label style="display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; font-weight: 600; cursor: pointer; color: var(--text-main);">
                            <input type="checkbox" name="payment_settings[cash_enabled]" value="1" {{ !empty($paymentSettings['cash_enabled']) ? 'checked' : '' }} style="width: 18px; height: 18px; accent-color: var(--primary);">
                            <span>💵 {{ __('Cash payment on-site') }}</span>
                        </label>
                        <label style="display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; font-weight: 600; cursor: pointer; color: var(--text-main);">
                            <input type="checkbox" name="payment_settings[pos_terminal_enabled]" value="1" {{ !empty($paymentSettings['pos_terminal_enabled']) ? 'checked' : '' }} style="width: 18px; height: 18px; accent-color: var(--primary);">
                            <span>💳 {{ __('POS terminal payment on-site') }}</span>
                        </label>
                    </div>
                </div>

                <div id="onlineGatewaysContainer" style="{{ empty($paymentSettings['online_enabled']) ? 'opacity: 0.55; pointer-events: none;' : '' }}; transition: all 0.25s ease;">
                    <div style="font-size: 0.92rem; font-weight: 800; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <span>🇦🇲 {{ __('Armenian Payment Gateways') }}</span>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
                        <!-- Idram -->
                        <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem 1.25rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; color: var(--text-main);">
                                    <span style="background: #ff6f00; color: #fff; width: 26px; height: 26px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 900;">Id</span>
                                    <span>Idram</span>
                                </div>
                                <label class="switch" style="position: relative; display: inline-block; width: 40px; height: 22px;">
                                    <input type="checkbox" name="payment_settings[gateways][idram][enabled]" value="1" {{ !empty($paymentSettings['gateways']['idram']['enabled']) ? 'checked' : '' }}>
                                    <span class="slider round" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 34px;"></span>
                                </label>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                                <input type="text" name="payment_settings[gateways][idram][merchant_id]" value="{{ $paymentSettings['gateways']['idram']['merchant_id'] ?? '' }}" placeholder="Receiver / Merchant ID" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.88rem; font-weight: 600; padding: 0.65rem 0.85rem; border-radius: 10px;">
                                <input type="password" name="payment_settings[gateways][idram][secret_key]" value="" placeholder="{{ !empty($paymentSettings['gateways']['idram']['configured']) ? '•••••••• (' . ($paymentSettings['gateways']['idram']['masked'] ?? 'Configured') . ')' : 'Secret Key' }}" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.88rem; font-weight: 600; padding: 0.65rem 0.85rem; border-radius: 10px;">
                                <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; color: var(--text-muted); cursor: pointer;">
                                    <input type="checkbox" name="payment_settings[gateways][idram][sandbox]" value="1" {{ !empty($paymentSettings['gateways']['idram']['sandbox']) ? 'checked' : '' }} style="accent-color: #ff6f00;">
                                    <span>{{ __('Sandbox / Test Mode') }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- Telcell Wallet -->
                        <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem 1.25rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; color: var(--text-main);">
                                    <span style="background: #e11d48; color: #fff; width: 26px; height: 26px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 900;">TC</span>
                                    <span>Telcell Wallet</span>
                                </div>
                                <label class="switch" style="position: relative; display: inline-block; width: 40px; height: 22px;">
                                    <input type="checkbox" name="payment_settings[gateways][telcell][enabled]" value="1" {{ !empty($paymentSettings['gateways']['telcell']['enabled']) ? 'checked' : '' }}>
                                    <span class="slider round" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 34px;"></span>
                                </label>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                                <input type="text" name="payment_settings[gateways][telcell][shop_id]" value="{{ $paymentSettings['gateways']['telcell']['shop_id'] ?? '' }}" placeholder="Shop ID" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.88rem; font-weight: 600; padding: 0.65rem 0.85rem; border-radius: 10px;">
                                <input type="password" name="payment_settings[gateways][telcell][key]" value="" placeholder="{{ !empty($paymentSettings['gateways']['telcell']['configured']) ? '•••••••• (' . ($paymentSettings['gateways']['telcell']['masked'] ?? 'Configured') . ')' : 'Security Key' }}" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.88rem; font-weight: 600; padding: 0.65rem 0.85rem; border-radius: 10px;">
                                <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; color: var(--text-muted); cursor: pointer;">
                                    <input type="checkbox" name="payment_settings[gateways][telcell][sandbox]" value="1" {{ !empty($paymentSettings['gateways']['telcell']['sandbox']) ? 'checked' : '' }} style="accent-color: #e11d48;">
                                    <span>{{ __('Sandbox / Test Mode') }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- FastShift -->
                        <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem 1.25rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; color: var(--text-main);">
                                    <span style="background: #2563eb; color: #fff; width: 26px; height: 26px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 900;">FS</span>
                                    <span>FastShift</span>
                                </div>
                                <label class="switch" style="position: relative; display: inline-block; width: 40px; height: 22px;">
                                    <input type="checkbox" name="payment_settings[gateways][fastshift][enabled]" value="1" {{ !empty($paymentSettings['gateways']['fastshift']['enabled']) ? 'checked' : '' }}>
                                    <span class="slider round" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 34px;"></span>
                                </label>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                                <input type="text" name="payment_settings[gateways][fastshift][merchant_id]" value="{{ $paymentSettings['gateways']['fastshift']['merchant_id'] ?? '' }}" placeholder="Merchant ID" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.88rem; font-weight: 600; padding: 0.65rem 0.85rem; border-radius: 10px;">
                                <input type="password" name="payment_settings[gateways][fastshift][api_key]" value="" placeholder="{{ !empty($paymentSettings['gateways']['fastshift']['configured']) ? '•••••••• (' . ($paymentSettings['gateways']['fastshift']['masked'] ?? 'Configured') . ')' : 'API Key' }}" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.88rem; font-weight: 600; padding: 0.65rem 0.85rem; border-radius: 10px;">
                                <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; color: var(--text-muted); cursor: pointer;">
                                    <input type="checkbox" name="payment_settings[gateways][fastshift][sandbox]" value="1" {{ !empty($paymentSettings['gateways']['fastshift']['sandbox']) ? 'checked' : '' }} style="accent-color: #2563eb;">
                                    <span>{{ __('Sandbox / Test Mode') }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- ArCa / Ameriabank vPOS -->
                        <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem 1.25rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; color: var(--text-main);">
                                    <span style="background: #059669; color: #fff; width: 26px; height: 26px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 900;">AC</span>
                                    <span>ArCa / Ameriabank vPOS</span>
                                </div>
                                <label class="switch" style="position: relative; display: inline-block; width: 40px; height: 22px;">
                                    <input type="checkbox" name="payment_settings[gateways][arca][enabled]" value="1" {{ !empty($paymentSettings['gateways']['arca']['enabled']) ? 'checked' : '' }}>
                                    <span class="slider round" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 34px;"></span>
                                </label>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                                <input type="text" name="payment_settings[gateways][arca][merchant_id]" value="{{ $paymentSettings['gateways']['arca']['merchant_id'] ?? '' }}" placeholder="Merchant / Client ID" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.88rem; font-weight: 600; padding: 0.65rem 0.85rem; border-radius: 10px;">
                                <input type="text" name="payment_settings[gateways][arca][terminal_id]" value="{{ $paymentSettings['gateways']['arca']['terminal_id'] ?? '' }}" placeholder="Terminal ID" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.88rem; font-weight: 600; padding: 0.65rem 0.85rem; border-radius: 10px;">
                                <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; color: var(--text-muted); cursor: pointer;">
                                    <input type="checkbox" name="payment_settings[gateways][arca][sandbox]" value="1" {{ !empty($paymentSettings['gateways']['arca']['sandbox']) ? 'checked' : '' }} style="accent-color: #059669;">
                                    <span>{{ __('Sandbox / Test Mode') }}</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div style="font-size: 0.92rem; font-weight: 800; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <span>🌍 {{ __('International Payment Gateways') }}</span>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr)); gap: 1.25rem;">
                        <!-- Stripe -->
                        <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem 1.25rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; color: var(--text-main);">
                                    <span style="background: #6366f1; color: #fff; width: 26px; height: 26px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 900;">S</span>
                                    <span>Stripe (Visa, Mastercard, Apple Pay)</span>
                                </div>
                                <label class="switch" style="position: relative; display: inline-block; width: 40px; height: 22px;">
                                    <input type="checkbox" name="payment_settings[gateways][stripe][enabled]" value="1" {{ !empty($paymentSettings['gateways']['stripe']['enabled']) ? 'checked' : '' }}>
                                    <span class="slider round" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 34px;"></span>
                                </label>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                                <input type="text" name="payment_settings[gateways][stripe][publishable_key]" value="{{ $paymentSettings['gateways']['stripe']['publishable_key'] ?? '' }}" placeholder="Publishable Key (pk_test_...)" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.88rem; font-weight: 600; padding: 0.65rem 0.85rem; border-radius: 10px;">
                                <input type="password" name="payment_settings[gateways][stripe][secret_key]" value="" placeholder="{{ !empty($paymentSettings['gateways']['stripe']['configured']) ? '•••••••• (' . ($paymentSettings['gateways']['stripe']['masked'] ?? 'Configured') . ')' : 'Secret Key (sk_test_...)' }}" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.88rem; font-weight: 600; padding: 0.65rem 0.85rem; border-radius: 10px;">
                                <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; color: var(--text-muted); cursor: pointer;">
                                    <input type="checkbox" name="payment_settings[gateways][stripe][sandbox]" value="1" {{ !empty($paymentSettings['gateways']['stripe']['sandbox']) ? 'checked' : '' }} style="accent-color: #6366f1;">
                                    <span>{{ __('Test Mode (pk_test / sk_test)') }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            
                </div>

                <!-- 5. DELIVERY & PICKUP TAB -->
                <div x-show="activeTab === 'delivery'" x-transition.opacity role="tabpanel" style="display: flex; flex-direction: column; gap: 1.75rem;">
                    <!-- 6. DELIVERY SETTINGS CARD -->
            <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.9rem;">
                        <span style="width: 42px; height: 42px; border-radius: 12px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">
                            <i class="fa-solid fa-motorcycle"></i>
                        </span>
                        <div>
                            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                {{ __('Delivery Service Settings') }}
                            </h3>
                            <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-muted); word-break: break-word;">
                                {{ __('Configure delivery fee, minimum order amount, and free delivery threshold') }}
                            </p>
                        </div>
                    </div>

                    <!-- Enable Switch -->
                    <label class="modern-switch-wrapper" style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; user-select: none;">
                        <span style="font-size: 0.9rem; font-weight: 700; color: var(--text-main);" id="deliveryStatusLabel">
                            {{ old('delivery_enabled', $vendor->delivery_enabled) ? __('Active') : __('Disabled') }}
                        </span>
                        <input type="checkbox" name="delivery_enabled" value="1" id="deliveryToggle" {{ old('delivery_enabled', $vendor->delivery_enabled) ? 'checked' : '' }} onchange="toggleDeliveryFields()" style="width: 20px; height: 20px; accent-color: #3b82f6; cursor: pointer;">
                    </label>
                </div>

                <div id="deliveryContainer" style="{{ old('delivery_enabled', $vendor->delivery_enabled) ? '' : 'opacity: 0.55; pointer-events: none;' }}; transition: opacity 0.2s ease;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 1.25rem;">
                        <!-- Delivery Fee -->
                        <div class="form-group">
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                                {{ __('Delivery Fee') }} <span style="color: #ef4444;">*</span>
                            </label>
                            <div style="position: relative; display: flex; align-items: center;">
                                <input type="number" step="any" min="0" name="delivery_fee" id="deliveryFeeInput" value="{{ old('delivery_fee', $vendor->delivery_fee ?? 0) }}" required class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem; font-size: 1rem; font-weight: 700;" oninput="updateCalculationsPreview()">
                                <span class="currency-label-display" style="position: absolute; right: 1rem; font-weight: 800; color: #3b82f6; font-size: 0.85rem;">
                                    {{ $vendor->currency }}
                                </span>
                            </div>
                            <span style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                                {{ __('Standard fixed fee for delivery (0 = free)') }}
                            </span>
                        </div>

                        <!-- Minimum Order for Delivery -->
                        <div class="form-group">
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                                {{ __('Minimum Order Amount') }} <span style="color: #ef4444;">*</span>
                            </label>
                            <div style="position: relative; display: flex; align-items: center;">
                                <input type="number" step="any" min="0" name="delivery_min_amount" id="deliveryMinAmount" value="{{ old('delivery_min_amount', $vendor->delivery_min_amount ?? 0) }}" required class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem; font-size: 1rem; font-weight: 700;" oninput="updateCalculationsPreview()">
                                <span class="currency-label-display" style="position: absolute; right: 1rem; font-weight: 800; color: var(--text-muted); font-size: 0.85rem;">
                                    {{ $vendor->currency }}
                                </span>
                            </div>
                            <span style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                                {{ __('Minimum order threshold for delivery (0 = no limit)') }}
                            </span>
                        </div>

                        <!-- Free Delivery From Threshold -->
                        <div class="form-group">
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                                {{ __('Free Delivery From') }}
                            </label>
                            <div style="position: relative; display: flex; align-items: center;">
                                <input type="number" step="any" min="0" name="delivery_free_from" id="deliveryFreeFrom" value="{{ old('delivery_free_from', $vendor->delivery_free_from) }}" placeholder="No free threshold" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem; font-size: 1rem; font-weight: 700;" oninput="updateCalculationsPreview()">
                                <span class="currency-label-display" style="position: absolute; right: 1rem; font-weight: 800; color: #10b981; font-size: 0.85rem;">
                                    {{ $vendor->currency }}
                                </span>
                            </div>
                            <span style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                                {{ __('If order exceeds this amount, delivery becomes free (empty = always paid)') }}
                            </span>
                        </div>
                    </div>

                    <!-- Storefront Motivation Banner Preview -->
                    <div style="margin-top: 1.25rem; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 14px; padding: 1rem 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <span style="width: 32px; height: 32px; border-radius: 8px; background: rgba(16, 185, 129, 0.2); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0;">
                                <i class="fa-solid fa-gift"></i>
                            </span>
                            <div>
                                <div style="font-size: 0.88rem; font-weight: 700; color: var(--text-main);" id="deliveryPreviewText">
                                    {{ __('Free delivery indicator in cart') }}
                                </div>
                                <div style="font-size: 0.78rem; color: var(--text-muted);" id="deliveryPreviewSubtext">
                                    {{ __('Customers will see a progress bar encouraging higher cart totals') }}
                                </div>
                            </div>
                        </div>
                        <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #10b981; font-weight: 800; border-radius: 8px; padding: 0.35rem 0.75rem; font-size: 0.8rem;">
                            <i class="fa-solid fa-sparkles"></i> {{ __('Upsell Feature') }}
                        </span>
                    </div>
                </div>
            </div>

            
                    <!-- 7. TAKEAWAY SETTINGS CARD -->
            <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.9rem;">
                        <span style="width: 42px; height: 42px; border-radius: 12px; background: rgba(139, 92, 246, 0.15); color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">
                            <i class="fa-solid fa-bag-shopping"></i>
                        </span>
                        <div>
                            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                {{ __('Takeaway Service Settings') }}
                            </h3>
                            <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-muted); word-break: break-word;">
                                {{ __('Allow customers to pre-order and pick up in person') }}
                            </p>
                        </div>
                    </div>

                    <!-- Enable Switch -->
                    <label class="modern-switch-wrapper" style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; user-select: none;">
                        <span style="font-size: 0.9rem; font-weight: 700; color: var(--text-main);" id="takeawayStatusLabel">
                            {{ old('takeaway_enabled', $vendor->takeaway_enabled) ? __('Active') : __('Disabled') }}
                        </span>
                        <input type="checkbox" name="takeaway_enabled" value="1" id="takeawayToggle" {{ old('takeaway_enabled', $vendor->takeaway_enabled) ? 'checked' : '' }} onchange="toggleTakeawayFields()" style="width: 20px; height: 20px; accent-color: #8b5cf6; cursor: pointer;">
                    </label>
                </div>

                <div id="takeawayContainer" style="{{ old('takeaway_enabled', $vendor->takeaway_enabled) ? '' : 'opacity: 0.55; pointer-events: none;' }}; transition: opacity 0.2s ease;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 1.25rem;">
                        <!-- Minimum Order for Takeaway -->
                        <div class="form-group">
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                                {{ __('Minimum Order Amount') }}
                            </label>
                            <div style="position: relative; display: flex; align-items: center;">
                                <input type="number" step="any" min="0" name="takeaway_min_amount" id="takeawayMinAmount" value="{{ old('takeaway_min_amount', $vendor->takeaway_min_amount ?? 0) }}" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem; font-size: 1rem; font-weight: 700;" oninput="updateCalculationsPreview()">
                                <span class="currency-label-display" style="position: absolute; right: 1rem; font-weight: 800; color: var(--text-muted); font-size: 0.85rem;">
                                    {{ $vendor->currency }}
                                </span>
                            </div>
                            <span style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                                {{ __('Minimum order threshold for takeaway (0 = no limit)') }}
                            </span>
                        </div>
                    </div>

                    <!-- Storefront Takeaway Banner Preview -->
                    <div style="margin-top: 1.25rem; background: rgba(139, 92, 246, 0.08); border: 1px solid rgba(139, 92, 246, 0.25); border-radius: 14px; padding: 1rem 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <span style="width: 32px; height: 32px; border-radius: 8px; background: rgba(139, 92, 246, 0.2); color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0;">
                                <i class="fa-solid fa-bag-shopping"></i>
                            </span>
                            <div>
                                <div style="font-size: 0.88rem; font-weight: 700; color: var(--text-main);" id="takeawayPreviewText">
                                    {{ __('Takeaway pickup option is available') }}
                                </div>
                                <div style="font-size: 0.78rem; color: var(--text-muted);" id="takeawayPreviewSubtext">
                                    {{ __('Customers can pre-order without a table QR code') }}
                                </div>
                            </div>
                        </div>
                        <span class="badge" style="background: rgba(139, 92, 246, 0.2); color: #8b5cf6; font-weight: 800; border-radius: 8px; padding: 0.35rem 0.75rem; font-size: 0.8rem;">
                            <i class="fa-solid fa-store"></i> {{ __('Takeaway Mode') }}
                        </span>
                    </div>
                </div>
            </div>

            @php
                $paymentSettings = $vendor->getSafePaymentSettings();
                $crmSettings = $vendor->getSafeCrmSettings();
                $printerSettings = $vendor->getThermalPrinterSettings();
            @endphp

            
                </div>

                <!-- 6. NOTIFICATIONS & EMAIL TAB -->
                <div x-show="activeTab === 'notifications'" x-transition.opacity role="tabpanel" style="display: flex; flex-direction: column; gap: 1.75rem;">
                    <!-- 8. TELEGRAM NOTIFICATIONS CARD -->
            @php
                $tgSettings = $vendor->telegram_settings ?? [];
                $tgEnabled = !empty($tgSettings['enabled']);
                $tgConfigured = app(\App\Services\CredentialService::class)->has($vendor, 'telegram', 'bot_token');
                $tgMasked = app(\App\Services\CredentialService::class)->mask($vendor, 'telegram', 'bot_token');
                $tgChatId = $tgSettings['chat_id'] ?? '';
                $tgTopicId = $tgSettings['topic_id'] ?? '';
                $tgNotifyOrders = !empty($tgSettings['notify_orders']) || !isset($tgSettings['notify_orders']);
                $tgNotifyWaiters = !empty($tgSettings['notify_waiter_calls']) || !isset($tgSettings['notify_waiter_calls']);
                $tgNotifyPayments = !empty($tgSettings['notify_payments']) || !isset($tgSettings['notify_payments']);
                $tgNotifyReservations = !empty($tgSettings['notify_reservations']) || !isset($tgSettings['notify_reservations']);
                $platformBotConfigured = !empty(\App\Models\SystemSetting::get('telegram_bot_token')) || !empty(config('services.telegram.bot_token'));
            @endphp
            <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.9rem;">
                        <span style="width: 44px; height: 44px; border-radius: 12px; background: rgba(34, 158, 217, 0.15); color: #229ed9; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                            <i class="fa-brands fa-telegram"></i>
                        </span>
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
                                <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                    {{ __('Telegram Notification System') }}
                                </h3>
                                @if($tgEnabled && !empty($tgChatId))
                                    <span class="badge badge-emerald" style="font-weight: 700; font-size: 0.75rem;">
                                        <i class="fa-solid fa-circle-check"></i> {{ __('Active') }}
                                    </span>
                                @elseif($tgEnabled)
                                    <span class="badge badge-amber" style="font-weight: 700; font-size: 0.75rem;">
                                        <i class="fa-solid fa-triangle-exclamation"></i> {{ __('Enter Chat ID') }}
                                    </span>
                                @else
                                    <span class="badge badge-secondary" style="font-weight: 700; font-size: 0.75rem;">
                                        {{ __('Disabled') }}
                                    </span>
                                @endif
                            </div>
                            <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-muted); word-break: break-word;">
                                {{ __('Receive instant notifications in your Telegram group, channel, or direct chat') }}
                            </p>
                        </div>
                    </div>

                    <label class="switch" style="position: relative; display: inline-block; width: 48px; height: 26px;">
                        <input type="checkbox" name="telegram_settings[enabled]" value="1" {{ $tgEnabled ? 'checked' : '' }} id="telegramToggle" onchange="toggleTelegramContainer()">
                        <span class="slider round" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 34px;"></span>
                    </label>
                </div>

                <div id="telegramContainer" style="{{ ! $tgEnabled ? 'opacity: 0.55; pointer-events: none;' : '' }}; transition: all 0.25s ease;">
                    <!-- Bot Credentials & Target Chat Grid -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
                        <!-- Chat ID (Required) -->
                        <div>
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                                {{ __('Telegram Chat ID / Group ID / Channel') }} <span style="color: #ef4444;">*</span>
                            </label>
                            <div style="position: relative; display: flex; align-items: center;">
                                <input type="text" name="telegram_settings[chat_id]" id="telegramChatId" value="{{ old('telegram_settings.chat_id', $tgChatId) }}" placeholder="e.g. -100123456789 or @restaurant_alerts" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.7rem 1rem 0.7rem 2.4rem; border-radius: 12px; font-weight: 600; font-family: monospace; font-size: 0.92rem;">
                                <span style="position: absolute; left: 0.85rem; color: #229ed9; font-size: 1rem;">
                                    <i class="fa-solid fa-hashtag"></i>
                                </span>
                            </div>
                            <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                                {{ __('Group or channel ID (starts with -100) or channel @username. The bot must be added as an administrator.') }}
                            </span>
                        </div>

                        <!-- Custom Bot Token (Optional) -->
                        <div>
                            <label style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                                <span>{{ __('Custom Bot Token (Optional)') }}</span>
                                @if($tgConfigured)
                                    <span style="font-size: 0.72rem; color: #10b981; font-weight: 700;">
                                        ✓ {{ __('Configured') }}
                                    </span>
                                @elseif($platformBotConfigured)
                                    <span style="font-size: 0.72rem; color: #10b981; font-weight: 600;">
                                        <i class="fa-solid fa-check"></i> {{ __('Platform default bot available') }}
                                    </span>
                                @endif
                            </label>
                            <div style="position: relative; display: flex; align-items: center;">
                                <input type="password" name="telegram_settings[bot_token]" id="telegramBotToken" value="" placeholder="{{ $tgConfigured ? '•••••••• (' . ($tgMasked ?: 'Configured') . ')' : ($platformBotConfigured ? 'Default QR Menu bot is used' : '123456789:AA... (@BotFather)') }}" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.7rem 1rem 0.7rem 2.4rem; border-radius: 12px; font-size: 0.88rem; font-family: monospace;">
                                <span style="position: absolute; left: 0.85rem; color: var(--text-muted); font-size: 1rem;">
                                    <i class="fa-solid fa-robot"></i>
                                </span>
                            </div>
                            <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                                {{ __('Leave blank to keep existing or use platform bot, or enter a new Token from @BotFather.') }}
                            </span>
                        </div>

                        <!-- Forum Topic ID (Optional) -->
                        <div>
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                                {{ __('Forum Topic / Thread ID (Optional)') }}
                            </label>
                            <div style="position: relative; display: flex; align-items: center;">
                                <input type="number" name="telegram_settings[topic_id]" id="telegramTopicId" value="{{ old('telegram_settings.topic_id', $tgTopicId) }}" placeholder="e.g. 42" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.7rem 1rem 0.7rem 2.4rem; border-radius: 12px; font-weight: 600; font-size: 0.92rem;">
                                <span style="position: absolute; left: 0.85rem; color: var(--text-muted); font-size: 1rem;">
                                    <i class="fa-solid fa-comments"></i>
                                </span>
                            </div>
                            <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                                {{ __('If your Telegram group uses forum topics, specify the topic thread ID.') }}
                            </span>
                        </div>
                    </div>

                    <!-- Branch-specific chat ID info -->
                    @if($location)
                        <div style="background: rgba(34, 158, 217, 0.05); border: 1px dashed rgba(34, 158, 217, 0.3); border-radius: 14px; padding: 0.85rem 1.15rem; margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
                            <div style="display: flex; align-items: center; gap: 0.65rem;">
                                <i class="fa-solid fa-code-branch" style="color: #229ed9;"></i>
                                <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">
                                    {{ __('Specific Chat ID for this branch (Optional)') }}: <strong>{{ $location->name }}</strong>
                                </span>
                            </div>
                            <div style="flex: 1; max-width: 320px; min-width: 200px;">
                                <input type="text" name="telegram_chat_id" value="{{ old('telegram_chat_id', $location->telegram_chat_id) }}" placeholder="{{ __('Leave blank to use global chat ID') }}" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.85rem; padding: 0.5rem 0.85rem; border-radius: 10px; font-family: monospace;">
                            </div>
                        </div>
                    @endif

                    <!-- Event checkboxes -->
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.75rem;">
                            {{ __('Notification Categories') }}
                        </label>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 0.85rem;">
                            <label style="display: flex; align-items: center; gap: 0.65rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.75rem 1rem; cursor: pointer; transition: border-color 0.2s;">
                                <input type="checkbox" name="telegram_settings[notify_orders]" value="1" {{ $tgNotifyOrders ? 'checked' : '' }} style="accent-color: #229ed9; width: 18px; height: 18px;">
                                <div style="font-size: 0.85rem;">
                                    <strong style="color: var(--text-main); display: block;">🔔 {{ __('New Orders') }}</strong>
                                    <span style="color: var(--text-muted); font-size: 0.78rem;">{{ __('Dine-in, takeaway, and delivery orders') }}</span>
                                </div>
                            </label>

                            <label style="display: flex; align-items: center; gap: 0.65rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.75rem 1rem; cursor: pointer; transition: border-color 0.2s;">
                                <input type="checkbox" name="telegram_settings[notify_waiter_calls]" value="1" {{ $tgNotifyWaiters ? 'checked' : '' }} style="accent-color: #229ed9; width: 18px; height: 18px;">
                                <div style="font-size: 0.85rem;">
                                    <strong style="color: var(--text-main); display: block;">🛎️ {{ __('Waiter Call') }}</strong>
                                    <span style="color: var(--text-muted); font-size: 0.78rem;">{{ __('Call waiter and bill requests (card/cash)') }}</span>
                                </div>
                            </label>

                            <label style="display: flex; align-items: center; gap: 0.65rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.75rem 1rem; cursor: pointer; transition: border-color 0.2s;">
                                <input type="checkbox" name="telegram_settings[notify_payments]" value="1" {{ $tgNotifyPayments ? 'checked' : '' }} style="accent-color: #229ed9; width: 18px; height: 18px;">
                                <div style="font-size: 0.85rem;">
                                    <strong style="color: var(--text-main); display: block;">✅ {{ __('Online Payments') }}</strong>
                                    <span style="color: var(--text-muted); font-size: 0.78rem;">{{ __('Idram, Telcell, ArCa, Stripe confirmations') }}</span>
                                </div>
                            </label>

                            <label style="display: flex; align-items: center; gap: 0.65rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.75rem 1rem; cursor: pointer; transition: border-color 0.2s;">
                                <input type="checkbox" name="telegram_settings[notify_reservations]" value="1" {{ $tgNotifyReservations ? 'checked' : '' }} style="accent-color: #229ed9; width: 18px; height: 18px;">
                                <div style="font-size: 0.85rem;">
                                    <strong style="color: var(--text-main); display: block;">📅 {{ __('Table Reservations') }}</strong>
                                    <span style="color: var(--text-muted); font-size: 0.78rem;">{{ __('Booking requests, modifications, cancellations') }}</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Test Connection Box -->
                    <div style="background: rgba(34, 158, 217, 0.08); border: 1px solid rgba(34, 158, 217, 0.25); border-radius: 16px; padding: 1.15rem 1.35rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <strong style="color: var(--text-main); font-size: 0.95rem; display: block; margin-bottom: 0.2rem;">
                                <i class="fa-solid fa-paper-plane" style="color: #229ed9;"></i> {{ __('Test Connection & Send Message') }}
                            </strong>
                            <span style="font-size: 0.8rem; color: var(--text-muted);">
                                {{ __('Click button to send a test message to the specified Chat ID') }}
                            </span>
                        </div>

                        <button type="button" id="btnTestTelegram" onclick="testTelegramConnection()" class="btn" style="background: #229ed9; color: #fff; font-weight: 700; border-radius: 12px; padding: 0.65rem 1.4rem; display: inline-flex; align-items: center; gap: 0.5rem; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(34, 158, 217, 0.35); transition: transform 0.15s ease;">
                            <i class="fa-solid fa-paper-plane"></i>
                            <span>{{ __('Send Test') }}</span>
                        </button>
                    </div>

                    <!-- Ajax Status Feedback Alert -->
                    <div id="telegramTestResult" style="display: none; margin-top: 1rem; border-radius: 12px; padding: 0.85rem 1.15rem; font-size: 0.88rem; font-weight: 600;"></div>
                </div>
            </div>

            
                    <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                            <div style="display: flex; align-items: center; gap: 0.9rem;">
                                <span style="width: 44px; height: 44px; border-radius: 12px; background: rgba(99, 102, 241, 0.15); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                                    <i class="fa-solid fa-envelope-circle-check"></i>
                                </span>
                                <div>
                                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                        {{ __('Reservation & Pre-Order Email Notifications') }}
                                    </h3>
                                    <p style="margin: 0.25rem 0 0; font-size: 0.85rem; color: var(--text-muted);">
                                        {{ __('Configure automated customer email confirmations, kitchen alerts, and 2-hour arrival reminders.') }}
                                    </p>
                                </div>
                            </div>
                            <a href="{{ route('admin.settings.reservations.notifications') }}" class="btn btn-secondary" style="text-decoration: none; font-weight: 700; border-radius: 12px; font-size: 0.88rem;">
                                <i class="fa-solid fa-arrow-up-right-from-square" style="color: #6366f1;"></i> {{ __('Manage Email Templates') }}
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 7. REGIONAL & LANGUAGES TAB -->
                <div x-show="activeTab === 'regional'" x-transition.opacity role="tabpanel" style="display: flex; flex-direction: column; gap: 1.75rem;">
                    <!-- 2. REGIONAL & LOCALIZATION SETTINGS CARD -->
            @php
                $currentTz = old('timezone', $vendor->timezone ?? 'Asia/Yerevan');
                $currentCurrency = \App\Models\Vendor::normalizeCurrencyCode(old('currency', $vendor->getRawOriginal('currency') ?? 'AMD'));
                $currentWeightUnit = old('weight_unit', $vendor->weight_unit ?? 'g');
                $currentVolumeUnit = old('volume_unit', $vendor->volume_unit ?? 'ml');
            @endphp

            <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.9rem;">
                        <span style="width: 42px; height: 42px; border-radius: 12px; background: rgba(14, 165, 233, 0.15); color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                            <i class="fa-solid fa-earth-americas"></i>
                        </span>
                        <div>
                            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                {{ __('Տարածաշրջանային և տեղայնացման կարգավորումներ') }}
                            </h3>
                            <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-muted); word-break: break-word;">
                                {{ __('Սահմանեք ռեստորանի ժամային գոտին, հիմնական արժույթը և ճաշատեսակների/խմիչքների չափման միավորները') }}
                            </p>
                        </div>
                    </div>

                    <span style="font-size: 0.78rem; font-weight: 700; background: rgba(14, 165, 233, 0.15); color: #0284c7; border: 1px solid rgba(14, 165, 233, 0.3); border-radius: 8px; padding: 0.3rem 0.65rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                        <i class="fa-solid fa-globe"></i> {{ __('Տեղայնացում') }}
                    </span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 230px), 1fr)); gap: 1.25rem;">
                    <!-- A. Timezone -->
                    <div class="form-group">
                        <label for="vendor_timezone" class="form-label" style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; font-size: 0.9rem; color: var(--text-main); margin-bottom: 0.5rem;">
                            <i class="fa-solid fa-clock" style="color: #0284c7;"></i>
                            <span>{{ __('Ժամային գոտի (Timezone)') }}</span>
                            <span style="color: #ef4444;">*</span>
                        </label>
                        <select name="timezone" id="vendor_timezone" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.88rem; background: var(--bg-body); border: 1.5px solid var(--border-color); color: var(--text-main); cursor: pointer;">
                            <optgroup label="⭐ {{ __('Հիմնական ժամային գոտիներ') }}">
                                @foreach($popularTimezones ?? [] as $tzKey => $tzLabel)
                                    <option value="{{ $tzKey }}" {{ $currentTz === $tzKey ? 'selected' : '' }}>
                                        {{ $tzLabel }}
                                    </option>
                                @endforeach
                            </optgroup>
                            @if(!empty($groupedTimezones))
                                @foreach($groupedTimezones as $region => $tzList)
                                    <optgroup label="🌐 {{ $region }}">
                                        @foreach($tzList as $tz)
                                            <option value="{{ $tz }}" {{ $currentTz === $tz ? 'selected' : '' }}>
                                                {{ $tz }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            @endif
                        </select>
                        <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                            {{ __('Օգտագործվում է պատվերների ժամերի, խոհանոցի գրաֆիկի և վիճակագրության համար:') }}
                        </span>
                    </div>

                    <!-- B. Primary Currency -->
                    <div class="form-group">
                        <label for="vendor_currency" class="form-label" style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; font-size: 0.9rem; color: var(--text-main); margin-bottom: 0.5rem;">
                            <i class="fa-solid fa-coins" style="color: #f59e0b;"></i>
                            <span>{{ __('Հիմնական արժույթ') }}</span>
                            <span style="color: #ef4444;">*</span>
                        </label>
                        <select name="currency" id="vendor_currency" class="form-input" onchange="onCurrencyChanged(this.value)" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.88rem; background: var(--bg-body); border: 1.5px solid var(--border-color); color: var(--text-main); cursor: pointer;">
                            @php
                                $currencyOptions = [
                                    'AMD' => '֏ (' . __('Armenian Dram') . ')',
                                    'USD' => 'USD ($ - ' . __('US Dollar') . ')',
                                    'EUR' => 'EUR (€ - ' . __('Euro') . ')',
                                    'RUB' => 'RUB (₽ - ' . __('Russian Ruble') . ')',
                                    'GEL' => 'GEL (₾ - ' . __('Georgian Lari') . ')',
                                    'GBP' => 'GBP (£ - ' . __('British Pound') . ')',
                                    'AED' => 'AED (د.إ - ' . __('UAE Dirham') . ')',
                                ];
                            @endphp
                            @foreach($currencyOptions as $currCode => $currLabel)
                                <option value="{{ $currCode }}" {{ strtoupper($currentCurrency) === $currCode ? 'selected' : '' }}>
                                    {{ $currLabel }}
                                </option>
                            @endforeach
                        </select>
                        <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                            {{ __('Արտացոլվում է ճաշացանկի գներում, հաշիվներում և վճարումներում:') }}
                        </span>
                    </div>

                    <!-- C. Weight Unit -->
                    <div class="form-group">
                        <label for="vendor_weight_unit" class="form-label" style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; font-size: 0.9rem; color: var(--text-main); margin-bottom: 0.5rem;">
                            <i class="fa-solid fa-weight-scale" style="color: #10b981;"></i>
                            <span>{{ __('Քաշի չափման միավոր') }}</span>
                        </label>
                        <select name="weight_unit" id="vendor_weight_unit" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.88rem; background: var(--bg-body); border: 1.5px solid var(--border-color); color: var(--text-main); cursor: pointer;">
                            @php
                                $weightUnitOptions = [
                                    'g' => __('Gram (g)'),
                                    'kg' => __('Kilogram (kg)'),
                                    'oz' => __('Ounce (oz)'),
                                    'lb' => __('Pound (lb)'),
                                ];
                            @endphp
                            @foreach($weightUnitOptions as $wKey => $wLabel)
                                <option value="{{ $wKey }}" {{ $currentWeightUnit === $wKey ? 'selected' : '' }}>
                                    {{ $wLabel }}
                                </option>
                            @endforeach
                        </select>
                        <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                            {{ __('Կերակրատեսակների չափաբաժնի և քաշի հիմնական միավոր:') }}
                        </span>
                    </div>

                    <!-- D. Volume Unit -->
                    <div class="form-group">
                        <label for="vendor_volume_unit" class="form-label" style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; font-size: 0.9rem; color: var(--text-main); margin-bottom: 0.5rem;">
                            <i class="fa-solid fa-glass-water" style="color: #6366f1;"></i>
                            <span>{{ __('Ծավալի չափման միավոր') }}</span>
                        </label>
                        <select name="volume_unit" id="vendor_volume_unit" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.88rem; background: var(--bg-body); border: 1.5px solid var(--border-color); color: var(--text-main); cursor: pointer;">
                            @php
                                $volumeUnitOptions = [
                                    'ml' => __('Milliliter (ml)'),
                                    'l' => __('Liter (l)'),
                                    'fl_oz' => __('Fluid Ounce (fl oz)'),
                                ];
                            @endphp
                            @foreach($volumeUnitOptions as $vKey => $vLabel)
                                <option value="{{ $vKey }}" {{ $currentVolumeUnit === $vKey ? 'selected' : '' }}>
                                    {{ $vLabel }}
                                </option>
                            @endforeach
                        </select>
                        <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                            {{ __('Խմիչքների, կոկտեյլների և հեղուկների չափման հիմնական միավոր:') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- 2.1 SECONDARY CURRENCY & PRICING CARD -->
            @php
                $secEnabled = old('secondary_currency_enabled', $vendor->secondary_currency_enabled ?? false);
                $secCurrency = old('secondary_currency', $vendor->secondary_currency ?? 'USD');
                $secRateMode = old('secondary_currency_rate_mode', $vendor->secondary_currency_rate_mode ?? 'manual');
                $secRate = old('secondary_currency_exchange_rate', $vendor->secondary_currency_exchange_rate ?? '350');
                $secDecimals = old('secondary_currency_decimal_places', $vendor->secondary_currency_decimal_places ?? 2);
                $secPrefix = old('secondary_currency_prefix', $vendor->secondary_currency_prefix ?? '≈');
            @endphp
            <div class="card settings-card" 
                 id="secondary-currency-card"
                 style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);"
                 x-data="{
                     enabled: {{ $secEnabled ? 'true' : 'false' }},
                     primaryCurrency: '{{ old('currency', $vendor->currency ?? '֏') }}',
                     secondaryCurrency: '{{ strtoupper($secCurrency) }}',
                     rateMode: '{{ $secRateMode }}',
                     exchangeRate: '{{ $secRate }}',
                     decimalPlaces: {{ (int) $secDecimals }},
                     prefix: '{{ addslashes($secPrefix) }}',
                     isLoadingRate: false,
                     rateFetchStatus: '',
                     symbols: {
                         'USD': '$',
                         'EUR': '€',
                         'RUB': '₽',
                         'GEL': '₾',
                         'GBP': '£',
                         'AED': 'د.إ',
                         'AMD': '֏',
                         'CNY': '¥',
                         'TRY': '₺',
                         'KZT': '₸',
                         'IRR': '﷼'
                     },
                     get isSameAsPrimary() {
                         const norm = (c) => (c === '֏' || c === 'AMD') ? 'AMD' : (c || '').toUpperCase();
                         return this.secondaryCurrency && this.primaryCurrency && norm(this.secondaryCurrency) === norm(this.primaryCurrency);
                     },
                     get secondarySymbol() {
                         return this.symbols[this.secondaryCurrency] || this.secondaryCurrency;
                     },
                     get primarySymbol() {
                         return this.symbols[this.primaryCurrency] || this.primaryCurrency;
                     },
                     formatPreview(price) {
                         const r = parseFloat(this.exchangeRate);
                         if (!r || r <= 0 || isNaN(r)) return '—';
                         const converted = price / r;
                         const dec = Math.max(0, Math.min(4, parseInt(this.decimalPlaces) || 0));
                         const pfx = (this.prefix || '').trim();
                         const sym = this.secondarySymbol;
                         const formatted = converted.toLocaleString(undefined, { minimumFractionDigits: dec, maximumFractionDigits: dec });
                         const beforeSymbols = ['$', '€', '£', '¥', '₺'];
                         const isBefore = beforeSymbols.includes(sym);
                         const priceStr = isBefore ? (sym + formatted) : (formatted + ' ' + sym);
                         return (pfx ? pfx + ' ' : '') + priceStr;
                     },
                     fetchLiveRate() {
                         if (this.isSameAsPrimary) return;
                         this.isLoadingRate = true;
                         this.rateFetchStatus = '';
                         const url = `{{ route('admin.settings.currency.live_rate') }}?currency=${encodeURIComponent(this.primaryCurrency)}&secondary_currency=${encodeURIComponent(this.secondaryCurrency)}`;
                         fetch(url)
                             .then(res => res.json())
                             .then(data => {
                                 this.isLoadingRate = false;
                                 if (data && data.success && data.rate) {
                                     this.exchangeRate = data.rate;
                                     this.rateFetchStatus = 'success';
                                     setTimeout(() => { this.rateFetchStatus = ''; }, 3500);
                                 } else {
                                     this.rateFetchStatus = 'error';
                                 }
                             })
                             .catch(() => {
                                 this.isLoadingRate = false;
                                 this.rateFetchStatus = 'error';
                             });
                     },
                     init() {
                         this.$watch('secondaryCurrency', (val) => {
                             if (this.rateMode === 'automatic') {
                                 this.fetchLiveRate();
                             }
                         });
                         this.$watch('rateMode', (val) => {
                             if (val === 'automatic' && (!this.exchangeRate || parseFloat(this.exchangeRate) <= 0 || this.exchangeRate === '350')) {
                                 this.fetchLiveRate();
                             }
                         });
                     }
                 }"
                 @primary-currency-changed.window="primaryCurrency = $event.detail">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.9rem;">
                        <span style="width: 42px; height: 42px; border-radius: 12px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                            <i class="fa-solid fa-money-bill-transfer"></i>
                        </span>
                        <div>
                            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                <span>{{ __('Երկրորդային արժույթ և փոխարժեք (Secondary Currency)') }}</span>
                                <span style="font-size: 0.72rem; font-weight: 700; background: rgba(245, 158, 11, 0.2); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 6px; padding: 0.15rem 0.5rem;">
                                    OPTIONAL DISPLAY
                                </span>
                            </h3>
                            <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-muted); word-break: break-word;">
                                {{ __('Ցուցադրել մոտավոր գները երկրորդ արժույթով (օր.՝ USD, EUR, RUB) հանրային ճաշացանկում՝ հիմնական գնի տակ') }}
                            </p>
                        </div>
                    </div>

                    <!-- Feature Enable/Disable Switch -->
                    <label style="display: inline-flex; align-items: center; gap: 0.75rem; cursor: pointer; background: var(--bg-body); padding: 0.5rem 1rem; border-radius: 14px; border: 1.5px solid var(--border-color); transition: all 0.2s ease;">
                        <input type="checkbox" 
                               name="secondary_currency_enabled" 
                               value="1" 
                               id="secondaryCurrencyToggle" 
                               x-model="enabled" 
                               style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                        <span style="font-size: 0.88rem; font-weight: 700; color: var(--text-main);">
                            {{ __('Show prices in a secondary currency') }}
                        </span>
                        <span :style="enabled ? 'background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);' : 'background: rgba(148, 163, 184, 0.15); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.3);'" 
                              style="font-size: 0.72rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 6px;"
                              x-text="enabled ? '{{ __('Ակտիվ (Active)') }}' : '{{ __('Անջատված է (Disabled)') }}'">
                        </span>
                    </label>
                </div>

                <!-- Informational Warning Banner -->
                <div style="background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 1.25rem; display: flex; align-items: flex-start; gap: 0.75rem; font-size: 0.84rem; color: var(--text-muted); line-height: 1.45;">
                    <i class="fa-solid fa-circle-info" style="color: #3b82f6; font-size: 1.1rem; margin-top: 0.1rem; flex-shrink: 0;"></i>
                    <div>
                        <strong style="color: var(--text-main);">{{ __('Ցուցադրական կարգավիճակ (Display-Only):') }}</strong>
                        {{ __('Երկրորդային արժույթը կրում է բացառապես տեղեկատվական բնույթ։ Ճաշատեսակների հիմնական գները, զամբյուղի հաշվարկը, վճարումները, հաշիվ-ապրանքագրերը և ֆինանսական հաշվետվությունները մնում են հիմնական արժույթով') }} (<strong>{{ $vendor->currency }}</strong>)։
                    </div>
                </div>

                <!-- Conditional Configuration Fields (Visible only when toggle is enabled) -->
                <div x-show="enabled" 
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 transform -translate-y-2"
                     x-transition:enter-end="opacity-100 transform translate-y-0"
                     style="display: flex; flex-direction: column; gap: 1.5rem; border-top: 1px dashed var(--border-color); padding-top: 1.5rem;">
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr)); gap: 1.25rem;">
                        <!-- 1. Secondary Currency / Fallback -->
                        <div class="form-group">
                            <label for="secondary_currency" class="form-label" style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; font-size: 0.9rem; color: var(--text-main); margin-bottom: 0.5rem;">
                                <i class="fa-solid fa-globe" style="color: #0284c7;"></i>
                                <span x-show="rateMode !== 'auto_by_language'">{{ __('Երկրորդային արժույթ (Secondary Currency)') }}</span>
                                <span x-show="rateMode === 'auto_by_language'" x-cloak>{{ __('Պահուստային արժույթ (Fallback Currency)') }}</span>
                                <span style="color: #ef4444;">*</span>
                            </label>
                            <select name="secondary_currency" 
                                    id="secondary_currency" 
                                    class="form-input" 
                                    x-model="secondaryCurrency"
                                    style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.88rem; background: var(--bg-body); border: 1.5px solid var(--border-color); color: var(--text-main); cursor: pointer;">
                                @php
                                    $secondaryCurrenciesList = $secondaryCurrencies ?? [
                                        'USD' => 'USD ($ - US Dollar)',
                                        'EUR' => 'EUR (€ - Euro)',
                                        'RUB' => 'RUB (₽ - Russian Ruble)',
                                        'GEL' => 'GEL (₾ - Georgian Lari)',
                                        'GBP' => 'GBP (£ - British Pound)',
                                        'AED' => 'AED (د.إ - UAE Dirham)',
                                        'AMD' => '֏ (Armenian Dram)',
                                        'CNY' => 'CNY (¥ - Chinese Yuan)',
                                        'TRY' => 'TRY (₺ - Turkish Lira)',
                                        'KZT' => 'KZT (₸ - Kazakhstani Tenge)',
                                    ];
                                @endphp
                                @foreach($secondaryCurrenciesList as $sCode => $sLabel)
                                    <option value="{{ $sCode }}">
                                        {{ $sLabel }}
                                    </option>
                                @endforeach
                            </select>
                            <div x-show="isSameAsPrimary && rateMode !== 'auto_by_language'" x-cloak style="color: #ef4444; font-size: 0.78rem; font-weight: 700; margin-top: 0.35rem; display: flex; align-items: center; gap: 0.35rem;">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <span>{{ __('Երկրորդային արժույթը պետք է տարբերվի հիմնական արժույթից') }} (<span x-text="primaryCurrency"></span>)</span>
                            </div>
                            <span x-show="rateMode === 'auto_by_language'" x-cloak style="font-size: 0.75rem; color: #0284c7; margin-top: 0.35rem; display: block; font-weight: 600;">
                                {{ __('Ծառայում է որպես լռելյայն պահուստային արժույթ այն լեզուների համար, որոնք առանձին քարտեզագրված չեն:') }}
                            </span>
                            @error('secondary_currency')
                                <span style="color: #ef4444; font-size: 0.78rem; font-weight: 700; margin-top: 0.35rem; display: block;">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- 2. Exchange Rate Mode -->
                        <div class="form-group">
                            <label for="secondary_currency_rate_mode" class="form-label" style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; font-size: 0.9rem; color: var(--text-main); margin-bottom: 0.5rem;">
                                <i class="fa-solid fa-calculator" style="color: #f59e0b;"></i>
                                <span>{{ __('Փոխարժեքի ռեժիմ (Exchange Rate Mode)') }}</span>
                            </label>
                            <select name="secondary_currency_rate_mode" 
                                    id="secondary_currency_rate_mode" 
                                    class="form-input" 
                                    x-model="rateMode"
                                    style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.88rem; background: var(--bg-body); border: 1.5px solid var(--border-color); color: var(--text-main); cursor: pointer;">
                                <option value="manual">✍️ {{ __('Ձեռքով մուտքագրում (Manual Entry)') }}</option>
                                <option value="automatic">🤖 {{ __('Ավտոմատ փոխարժեք (Live Auto Rate)') }}</option>
                                <option value="auto_by_language">🌐 {{ __('Ամբողջական ավտոմատ՝ ըստ լեզվի (Full Auto: Dynamic by Menu Language)') }}</option>
                            </select>
                            <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                                <span x-show="rateMode === 'manual'">{{ __('Մուտքագրեք ընթացիկ փոխարժեքը ձեռքով:') }}</span>
                                <span x-show="rateMode === 'automatic'" x-cloak>{{ __('Փոխարժեքը ստացվում է ուղիղ եթերում API-ից (ExchangeRate-API, 6ժ քեշավորմամբ):') }}</span>
                                <span x-show="rateMode === 'auto_by_language'" x-cloak>{{ __('Արժույթը և փոխարժեքը փոխվում են ավտոմատ՝ ըստ հաճախորդի ընտրած լեզվի:') }}</span>
                            </span>
                        </div>

                        <!-- 3. Approximation Prefix -->
                        <div class="form-group">
                            <label for="secondary_currency_prefix" class="form-label" style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; font-size: 0.9rem; color: var(--text-main); margin-bottom: 0.5rem;">
                                <i class="fa-solid fa-wave-square" style="color: #8b5cf6;"></i>
                                <span>{{ __('Մոտավորության նախածանց (Prefix)') }}</span>
                            </label>
                            <input type="text" 
                                   name="secondary_currency_prefix" 
                                   id="secondary_currency_prefix" 
                                   class="form-input" 
                                   x-model="prefix"
                                   placeholder="≈"
                                   maxlength="10"
                                   style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.88rem; background: var(--bg-body); border: 1.5px solid var(--border-color); color: var(--text-main);">
                            <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                                {{ __('Լռելյայն նախածանց (օր.՝ ≈, ~, ca.):') }}
                            </span>
                        </div>

                        <!-- 4. Decimal Precision -->
                        <div class="form-group">
                            <label for="secondary_currency_decimal_places" class="form-label" style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; font-size: 0.9rem; color: var(--text-main); margin-bottom: 0.5rem;">
                                <i class="fa-solid fa-hashtag" style="color: #10b981;"></i>
                                <span>{{ __('Կլորացման ճշգրտություն (Precision)') }}</span>
                            </label>
                            <select name="secondary_currency_decimal_places" 
                                    id="secondary_currency_decimal_places" 
                                    class="form-input" 
                                    x-model="decimalPlaces"
                                    style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.88rem; background: var(--bg-body); border: 1.5px solid var(--border-color); color: var(--text-main); cursor: pointer;">
                                <option value="0">0 նիշ (օր.՝ 10)</option>
                                <option value="1">1 նիշ (օր.՝ 10.0)</option>
                                <option value="2">2 նիշ (օր.՝ 10.00 - Ստանդարտ)</option>
                                <option value="3">3 նիշ (օր.՝ 10.000)</option>
                                <option value="4">4 նիշ (օր.՝ 10.0000)</option>
                            </select>
                            <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                                {{ __('Ստորակետից հետո թվանշանների քանակը (լռելյայն՝ 2):') }}
                            </span>
                        </div>
                    </div>

                    <!-- 🌐 Dynamic Language-to-Currency Map Banner (Visible when rateMode === 'auto_by_language') -->
                    <div x-show="rateMode === 'auto_by_language'" 
                         x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 transform -translate-y-1"
                         x-transition:enter-end="opacity-100 transform translate-y-0"
                         style="background: linear-gradient(135deg, rgba(14, 165, 233, 0.08) 0%, rgba(99, 102, 241, 0.08) 100%); border: 1.5px solid rgba(14, 165, 233, 0.3); border-radius: 16px; padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.6rem;">
                                <span style="font-size: 1.35rem;">🌐</span>
                                <div>
                                    <h4 style="margin: 0; font-size: 0.96rem; font-weight: 800; color: var(--text-main);">
                                        {{ __('Ամբողջական ավտոմատ ճաշացանկ՝ ըստ ընտրված լեզվի') }}
                                    </h4>
                                    <p style="margin: 0.15rem 0 0; font-size: 0.8rem; color: var(--text-muted);">
                                        {{ __('Հյուրի կողմից լեզուն փոխելիս երկրորդային արժույթը և փոխարժեքը դինամիկ կերպով կհամապատասխանեցվեն:') }}
                                    </p>
                                </div>
                            </div>
                            <span style="font-size: 0.72rem; font-weight: 700; background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 6px; padding: 0.2rem 0.6rem;">
                                LIVE MULTI-CURRENCY
                            </span>
                        </div>

                        <!-- Language Badges Grid -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 190px), 1fr)); gap: 0.65rem;">
                            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; padding: 0.6rem 0.85rem; display: flex; align-items: center; gap: 0.6rem;">
                                <span style="font-size: 1.2rem;">🇷🇺</span>
                                <div style="min-width: 0;">
                                    <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-main);">{{ __('Ռուսերեն (ru)') }}</div>
                                    <div style="font-size: 0.74rem; font-weight: 800; color: #0284c7;">RUB (₽ - Ռուբլի)</div>
                                </div>
                            </div>

                            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; padding: 0.6rem 0.85rem; display: flex; align-items: center; gap: 0.6rem;">
                                <span style="font-size: 1.2rem;">🇺🇸</span>
                                <div style="min-width: 0;">
                                    <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-main);">{{ __('Անգլերեն (en)') }}</div>
                                    <div style="font-size: 0.74rem; font-weight: 800; color: #0284c7;">USD ($ - Դոլար)</div>
                                </div>
                            </div>

                            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; padding: 0.6rem 0.85rem; display: flex; align-items: center; gap: 0.6rem;">
                                <span style="font-size: 1.2rem;">🇩🇪 🇫🇷 🇪🇸 🇮🇹</span>
                                <div style="min-width: 0;">
                                    <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-main);">{{ __('de / fr / es / it') }}</div>
                                    <div style="font-size: 0.74rem; font-weight: 800; color: #0284c7;">EUR (€ - Եվրո)</div>
                                </div>
                            </div>

                            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; padding: 0.6rem 0.85rem; display: flex; align-items: center; gap: 0.6rem;">
                                <span style="font-size: 1.2rem;">🇬🇪</span>
                                <div style="min-width: 0;">
                                    <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-main);">{{ __('Վրացերեն (ge / ka)') }}</div>
                                    <div style="font-size: 0.74rem; font-weight: 800; color: #0284c7;">GEL (₾ - Լարի)</div>
                                </div>
                            </div>

                            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; padding: 0.6rem 0.85rem; display: flex; align-items: center; gap: 0.6rem;">
                                <span style="font-size: 1.2rem;">🇦🇪</span>
                                <div style="min-width: 0;">
                                    <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-main);">{{ __('Արաբերեն (ar)') }}</div>
                                    <div style="font-size: 0.74rem; font-weight: 800; color: #0284c7;">AED (د.إ - Դիրհամ)</div>
                                </div>
                            </div>

                            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; padding: 0.6rem 0.85rem; display: flex; align-items: center; gap: 0.6rem;">
                                <span style="font-size: 1.2rem;">🇨🇳</span>
                                <div style="min-width: 0;">
                                    <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-main);">{{ __('Չինարեն (zh)') }}</div>
                                    <div style="font-size: 0.74rem; font-weight: 800; color: #0284c7;">CNY (¥ - Յուան)</div>
                                </div>
                            </div>
                        </div>

                        <div style="font-size: 0.78rem; color: var(--text-muted); line-height: 1.45;">
                            💡 <strong>{{ __('Նշում:') }}</strong> {{ __('Հայերենի (hy) կամ չքարտեզագրված լեզուների համար կօգտագործվի նշված պահուստային արժույթը') }} (<strong style="color: var(--text-main);" x-text="secondaryCurrency">USD</strong>)։ {{ __('Բոլոր փոխարժեքները թարմացվում են ինքնաշխատորեն ExchangeRate-API միջոցով (6 ժամ քեշավորմամբ):') }}
                        </div>
                    </div>

                    <!-- Exchange Rate Direction & Value Input Box -->
                    <div style="background: var(--bg-body); border: 1.5px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; flex-direction: column; gap: 0.75rem;">
                        <label for="secondary_currency_exchange_rate" style="font-weight: 700; font-size: 0.9rem; color: var(--text-main); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                            <span style="display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fa-solid fa-arrow-right-arrow-left" style="color: var(--primary);"></i>
                                <span x-show="rateMode === 'manual'">{{ __('Փոխարժեք և ուղղություն (Manual Exchange Rate)') }}</span>
                                <span x-show="rateMode === 'automatic'" x-cloak>{{ __('Ընթացիկ ավտոմատ փոխարժեք (Live Auto Rate)') }}</span>
                                <span x-show="rateMode === 'auto_by_language'" x-cloak>{{ __('Պահուստային արժույթի փոխարժեք (Fallback Exchange Rate)') }}</span>
                                <span style="color: #ef4444;" x-show="rateMode === 'manual'">*</span>
                            </span>
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <!-- Live Rate Fetch Button -->
                                <button type="button" 
                                        @click="fetchLiveRate()" 
                                        :disabled="isLoadingRate || isSameAsPrimary"
                                        style="font-size: 0.78rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.3rem 0.75rem; border-radius: 8px; border: 1px solid rgba(14, 165, 233, 0.4); background: rgba(14, 165, 233, 0.1); color: #0284c7; cursor: pointer; transition: all 0.2s ease;">
                                    <i class="fa-solid fa-arrows-rotate" :class="{ 'fa-spin': isLoadingRate }"></i>
                                    <span x-text="isLoadingRate ? '{{ __('Ստացվում է...') }}' : '{{ __('Թարմացնել API-ից') }}'"></span>
                                </button>

                                @if(!empty($vendor->secondary_currency_rate_updated_at))
                                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">
                                        <i class="fa-regular fa-clock"></i> {{ __('Վերջին թարմացում:') }} {{ $vendor->secondary_currency_rate_updated_at->format('d.m.Y H:i') }}
                                    </span>
                                @endif
                            </div>
                        </label>

                        <!-- Feedback banner for AJAX live fetch -->
                        <div x-show="rateFetchStatus === 'success'" x-cloak style="font-size: 0.8rem; font-weight: 700; color: #10b981; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; padding: 0.4rem 0.75rem; display: flex; align-items: center; gap: 0.4rem;">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>{{ __('Փոխարժեքը հաջողությամբ թարմացվեց ExchangeRate-API մատակարարից:') }}</span>
                        </div>
                        <div x-show="rateFetchStatus === 'error'" x-cloak style="font-size: 0.8rem; font-weight: 700; color: #ef4444; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 0.4rem 0.75rem; display: flex; align-items: center; gap: 0.4rem;">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span>{{ __('Չհաջողվեց կապ հաստատել API-ի հետ։ Ստուգեք կապը կամ մուտքագրեք փոխարժեքը ձեռքով:') }}</span>
                        </div>

                        <!-- Direction Formula Box -->
                        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.85rem 1.25rem;">
                            <div style="font-size: 1.05rem; font-weight: 800; color: var(--text-main); display: inline-flex; align-items: center; gap: 0.35rem;">
                                <span>1</span>
                                <span style="color: var(--primary);" x-text="secondaryCurrency">USD</span>
                                <span>=</span>
                            </div>

                            <div style="position: relative; display: inline-flex; align-items: center;">
                                <input type="number" 
                                       step="any" 
                                       min="0.000001" 
                                       max="100000000"
                                       name="secondary_currency_exchange_rate" 
                                       id="secondary_currency_exchange_rate" 
                                       class="form-input" 
                                       x-model="exchangeRate"
                                       placeholder="350"
                                       :readonly="rateMode === 'auto_by_language'"
                                       :style="rateMode === 'auto_by_language' ? 'opacity: 0.85; background: var(--bg-body); cursor: not-allowed;' : ''"
                                       style="width: 150px; font-size: 1.15rem; font-weight: 800; padding: 0.55rem 0.85rem; border-radius: 10px; background: var(--bg-body); border: 2px solid var(--primary); color: var(--text-main); text-align: center;">
                            </div>

                            <div style="font-size: 1.05rem; font-weight: 800; color: var(--text-main); display: inline-flex; align-items: center; gap: 0.35rem;">
                                <span style="color: #10b981;" x-text="primarySymbol">֏</span>
                            </div>

                            <span x-show="rateMode === 'automatic'" x-cloak style="font-size: 0.75rem; font-weight: 700; background: rgba(14, 165, 233, 0.15); color: #0284c7; border: 1px solid rgba(14, 165, 233, 0.3); padding: 0.2rem 0.5rem; border-radius: 6px;">
                                LIVE RATE
                            </span>
                            <span x-show="rateMode === 'auto_by_language'" x-cloak style="font-size: 0.75rem; font-weight: 700; background: rgba(99, 102, 241, 0.15); color: #6366f1; border: 1px solid rgba(99, 102, 241, 0.3); padding: 0.2rem 0.5rem; border-radius: 6px;">
                                DYNAMIC PER LANGUAGE
                            </span>
                        </div>

                        <div style="font-size: 0.78rem; color: var(--text-muted); line-height: 1.45;">
                            {{ __('Հաշվարկման կարգ. Երկրորդային գին = Հիմնական գին / Փոխարժեք։ Օրինակ՝ եթե հիմնական գինը 3,500 ֏ է և 1 USD = 350 ֏, ապա հաճախորդը կտեսնի') }}
                            <strong style="color: var(--text-main);">≈ $10.00</strong>։
                        </div>

                        @error('secondary_currency_exchange_rate')
                            <span style="color: #ef4444; font-size: 0.82rem; font-weight: 700; display: block;">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Live Calculation Preview on Menu Card -->
                    <div style="background: var(--bg-body); border: 1.5px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
                        <div style="font-size: 0.82rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
                            <i class="fa-solid fa-eye" style="color: #3b82f6;"></i>
                            <span>{{ __('Հանրային ճաշացանկի նախադիտում (Live Menu Card Preview)') }}</span>
                        </div>

                        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem 1.25rem; max-width: 380px; box-shadow: var(--shadow-card);">
                            <div style="display: flex; gap: 0.85rem; align-items: center;">
                                <div style="width: 54px; height: 54px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #f59e0b; flex-shrink: 0;">
                                    🥩
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        {{ __('Հորթի խորոված (Grilled Veal)') }}
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                        {{ __('Մատուցվում է բանջարեղենով') }}
                                    </div>
                                    <div style="margin-top: 0.4rem;">
                                        <!-- Primary Price -->
                                        <div style="font-family: 'Outfit', sans-serif; font-size: 1.05rem; font-weight: 800; color: var(--text-main); display: inline-flex; align-items: baseline; gap: 0.25rem;">
                                            <span>3,500</span>
                                            <span style="font-size: 0.85rem; color: var(--primary); font-weight: 700;" x-text="primarySymbol">֏</span>
                                        </div>

                                        <!-- Converted Secondary Price Underneath -->
                                        <div style="font-size: 0.76rem; font-weight: 600; color: var(--text-muted); margin-top: 0.1rem; display: flex; align-items: center; gap: 0.3rem;"
                                             x-show="enabled">
                                            <span style="background: rgba(14, 165, 233, 0.12); color: #0284c7; padding: 0.1rem 0.4rem; border-radius: 4px; font-weight: 700;"
                                                  x-text="formatPreview(3500)">
                                                ≈ $10.00
                                            </span>
                                            <span style="font-size: 0.7rem; opacity: 0.7;">({{ __('մոտավոր') }})</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            
                </div>

                <!-- DAILY OFFERS & CAROUSEL TAB -->
                <div x-show="activeTab === 'daily_offers'" x-transition.opacity role="tabpanel" style="display: flex; flex-direction: column; gap: 1.75rem;">
            @php
                $productsJson = $products->map(function ($p) {
                    return [
                        'id' => (int) $p->id,
                        'name' => $p->name,
                        'price' => (float) $p->price,
                        'category_id' => $p->category_id ? (int) $p->category_id : null,
                        'category_name' => $p->category?->name ?? __('Uncategorized'),
                        'image' => $p->image ?: asset('images/default-dish.png'),
                        'description' => $p->description ?? '',
                    ];
                })->values();

                $dailyOffersJson = $dailyOffers->map(function ($off) use ($vendor) {
                    $p = $off->product;
                    return [
                        'id' => (int) $off->id,
                        'product_id' => (int) $off->product_id,
                        'product_name' => $p ? $p->name : 'Deleted Dish',
                        'product_image' => ($p && $p->image) ? $p->image : asset('images/default-dish.png'),
                        'category_name' => ($p && $p->category) ? $p->category->name : __('Uncategorized'),
                        'regular_price' => $p ? (float) $p->price : 0,
                        'badge' => $off->badge ?: ($vendor->featured_dish_badge ?: "⭐ TODAY'S SPECIAL"),
                        'subtitle' => $off->subtitle ?: ($vendor->featured_dish_subtitle ?: ($p?->description ?: "Chef's special selection")),
                        'discount_price' => $off->discount_price !== null ? (float) $off->discount_price : null,
                        'is_active' => (bool) $off->is_active,
                        'sort_order' => (int) $off->sort_order,
                    ];
                })->values();
            @endphp

            <!-- 3. MULTI-PRODUCT DAILY OFFERS CAROUSEL CARD -->
            <div class="card settings-card"
                 id="dailyOffersSettingsCard"
                 x-data="dailyOffersAdminApp({{ json_encode($productsJson) }}, {{ json_encode($dailyOffersJson) }}, '{{ route('admin.settings.daily_offers.update') }}', '{{ csrf_token() }}', '{{ $vendor->currency ?? '֏' }}')"
                 style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">

                <!-- Header Row -->
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.9rem;">
                        <span style="width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(239, 68, 68, 0.2)); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);">
                            <i class="fa-solid fa-fire-flame-curved"></i>
                        </span>
                        <div>
                            <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                <span>{{ __('Dish of the Day Banner') }}</span>
                                <span style="font-size: 0.72rem; font-weight: 700; background: linear-gradient(135deg, #f59e0b, #ef4444); color: #fff; padding: 0.2rem 0.6rem; border-radius: 6px; letter-spacing: 0.03em;">
                                    MULTI-PRODUCT CAROUSEL
                                </span>
                            </h3>
                            <p style="margin: 0.25rem 0 0; font-size: 0.86rem; color: var(--text-muted); word-break: break-word;">
                                {{ __('Select multiple dishes from your menu to showcase in a modern, responsive horizontal slider at the top of your menu.') }}
                            </p>
                        </div>
                    </div>

                    <!-- Master Enable/Disable Switch -->
                    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                        <label style="display: inline-flex; align-items: center; gap: 0.75rem; cursor: pointer; background: var(--bg-body); padding: 0.55rem 1.1rem; border-radius: 14px; border: 1.5px solid var(--border-color); transition: border-color 0.2s ease;">
                            <input type="checkbox"
                                   name="featured_dish_enabled"
                                   value="1"
                                   id="featuredDishToggle"
                                   x-model="isEnabled"
                                   @change="syncLegacyInputs()"
                                   {{ old('featured_dish_enabled', $vendor->featured_dish_enabled) ? 'checked' : '' }}
                                   style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                            <span style="font-size: 0.88rem; font-weight: 700; color: var(--text-main);">
                                {{ __('Activate in Menu') }}
                            </span>
                        </label>

                        <!-- Instant AJAX Save Button -->
                        <button type="button"
                                @click="saveOffersAjax()"
                                :disabled="isSaving"
                                class="btn btn-primary"
                                style="background: var(--primary); color: #fff; border: none; padding: 0.55rem 1.15rem; border-radius: 14px; font-weight: 700; font-size: 0.86rem; display: inline-flex; align-items: center; gap: 0.45rem; cursor: pointer; box-shadow: 0 4px 14px rgba(0,0,0,0.12);">
                            <i class="fa-solid" :class="isSaving ? 'fa-spinner fa-spin' : 'fa-floppy-disk'"></i>
                            <span x-text="isSaving ? '{{ __('Saving...') }}' : '{{ __('Save Offers') }}'"></span>
                        </button>
                    </div>
                </div>

                <!-- Toast Notification Box -->
                <div x-show="saveMessage"
                     x-transition.opacity
                     :style="saveMessageType === 'success' ? 'background: rgba(16, 185, 129, 0.12); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);' : 'background: rgba(239, 68, 68, 0.12); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);'"
                     style="padding: 0.75rem 1rem; border-radius: 12px; font-size: 0.88rem; font-weight: 700; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid" :class="saveMessageType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'"></i>
                    <span x-text="saveMessage"></span>
                </div>

                <!-- Hidden inputs for traditional form submission -->
                <input type="hidden" name="daily_offers_json" id="dailyOffersJsonInput" value="{{ json_encode($dailyOffersJson) }}" :value="JSON.stringify(offers)">
                <template x-for="(off, idx) in offers" :key="off.product_id">
                    <div>
                        <input type="hidden" :name="'daily_offers[' + idx + '][product_id]'" :value="off.product_id">
                        <input type="hidden" :name="'daily_offers[' + idx + '][sort_order]'" :value="idx">
                        <input type="hidden" :name="'daily_offers[' + idx + '][badge]'" :value="off.badge || ''">
                        <input type="hidden" :name="'daily_offers[' + idx + '][subtitle]'" :value="off.subtitle || ''">
                        <input type="hidden" :name="'daily_offers[' + idx + '][discount_price]'" :value="off.discount_price || ''">
                        <input type="hidden" :name="'daily_offers[' + idx + '][is_active]'" :value="off.is_active ? 1 : 0">
                    </div>
                </template>

                <!-- Hidden Legacy Fallbacks for Feature Tests -->
                <div style="display: none;">
                    <select name="featured_product_id" id="featuredProductSelect">
                        <option value="">-- {{ __('Select a dish from the list') }} --</option>
                        @foreach($products as $prod)
                            <option value="{{ $prod->id }}"
                                    data-name="{{ $prod->name }}"
                                    data-image="{{ $prod->image }}"
                                    data-price="{{ number_format($prod->price, 0) }}"
                                    data-desc="{{ $prod->description }}"
                                    {{ old('featured_product_id', $vendor->featured_product_id) == $prod->id ? 'selected' : '' }}>
                                🍽️ {{ $prod->name }} — {{ number_format($prod->price, 0) }} {{ $vendor->currency ?? '֏' }} ({{ $prod->category?->name ?? 'Uncategorized' }})
                            </option>
                        @endforeach
                    </select>
                    <input type="text" name="featured_dish_badge" id="featuredDishBadgeInput" value="{{ old('featured_dish_badge', $vendor->featured_dish_badge ?? "⭐ TODAY'S SPECIAL") }}">
                    <input type="text" name="featured_dish_subtitle" id="featuredDishSubtitleInput" value="{{ old('featured_dish_subtitle', $vendor->featured_dish_subtitle) }}">
                </div>

                <!-- Main Content Grid: Selected Offers (Left) & Dish Picker (Right) -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 360px), 1fr)); gap: 1.5rem; margin-bottom: 1.75rem;">

                    <!-- Left Column: Selected Offers Reorderable List -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px dashed var(--border-color); padding-bottom: 0.75rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fa-solid fa-layer-group" style="color: var(--primary);"></i>
                                <span style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);">
                                    {{ __('Selected Daily Offers') }}
                                </span>
                            </div>
                            <span style="font-size: 0.75rem; font-weight: 800; background: rgba(245, 158, 11, 0.15); color: #d97706; padding: 0.15rem 0.6rem; border-radius: 9999px;"
                                  x-text="offers.length + ' {{ __('dishes') }}'"></span>
                        </div>

                        <!-- Empty State -->
                        <div x-show="offers.length === 0"
                             style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
                            <div style="font-size: 2.2rem; opacity: 0.35; margin-bottom: 0.75rem;">
                                <i class="fa-solid fa-bowl-food"></i>
                            </div>
                            <p style="margin: 0 0 0.4rem; font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                {{ __('No daily offers selected yet') }}
                            </p>
                            <span style="font-size: 0.8rem; line-height: 1.4; display: block;">
                                {{ __('Use the dish picker on the right to search and select products for your promo carousel.') }}
                            </span>
                        </div>

                        <!-- Offers List -->
                        <div style="display: flex; flex-direction: column; gap: 0.85rem;" x-show="offers.length > 0">
                            <template x-for="(item, index) in offers" :key="item.product_id">
                                <div style="background: var(--bg-card); border: 1.5px solid var(--border-color); border-radius: 14px; padding: 0.9rem; transition: all 0.2s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                    <!-- Offer Header Row -->
                                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.75rem;">
                                        <div style="display: flex; align-items: center; gap: 0.65rem; min-width: 0;">
                                            <span style="width: 24px; height: 24px; border-radius: 50%; background: rgba(245, 158, 11, 0.18); color: #d97706; font-size: 0.72rem; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;"
                                                  x-text="'#' + (index + 1)"></span>
                                            <img :src="item.product_image"
                                                 onerror="this.src='{{ asset('images/default-dish.png') }}';"
                                                 style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover; flex-shrink: 0; border: 1px solid var(--border-color);">
                                            <div style="min-width: 0;">
                                                <h4 style="margin: 0; font-size: 0.92rem; font-weight: 800; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                                                    x-text="item.product_name"></h4>
                                                <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;"
                                                      x-text="(item.regular_price ? Number(item.regular_price).toLocaleString() : '') + ' ' + currency + ' · ' + (item.category_name || '')"></span>
                                            </div>
                                        </div>

                                        <!-- Reordering & Remove Controls -->
                                        <div style="display: flex; align-items: center; gap: 0.3rem; flex-shrink: 0;">
                                            <button type="button"
                                                    @click="moveUp(index)"
                                                    :disabled="index === 0"
                                                    title="{{ __('Move up') }}"
                                                    style="width: 28px; height: 28px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-body); color: var(--text-main); display: inline-flex; align-items: center; justify-content: center; cursor: pointer;"
                                                    :style="index === 0 ? 'opacity: 0.3; cursor: not-allowed;' : 'opacity: 1;'">
                                                <i class="fa-solid fa-arrow-up" style="font-size: 0.75rem;"></i>
                                            </button>
                                            <button type="button"
                                                    @click="moveDown(index)"
                                                    :disabled="index === offers.length - 1"
                                                    title="{{ __('Move down') }}"
                                                    style="width: 28px; height: 28px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-body); color: var(--text-main); display: inline-flex; align-items: center; justify-content: center; cursor: pointer;"
                                                    :style="index === offers.length - 1 ? 'opacity: 0.3; cursor: not-allowed;' : 'opacity: 1;'">
                                                <i class="fa-solid fa-arrow-down" style="font-size: 0.75rem;"></i>
                                            </button>
                                            <button type="button"
                                                    @click="removeOffer(index)"
                                                    title="{{ __('Remove from offers') }}"
                                                    style="width: 28px; height: 28px; border-radius: 8px; border: 1px solid rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.1); color: #ef4444; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.15s ease;">
                                                <i class="fa-solid fa-trash-can" style="font-size: 0.75rem;"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Editable Offer Details -->
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem; background: var(--bg-body); padding: 0.65rem; border-radius: 10px; border: 1px solid var(--border-color);">
                                        <div>
                                            <label style="display: block; font-size: 0.72rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.2rem;">
                                                {{ __('Badge Text') }}
                                            </label>
                                            <input type="text"
                                                   x-model="item.badge"
                                                   @input="syncLegacyInputs()"
                                                   placeholder="⭐ TODAY'S SPECIAL"
                                                   style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 8px; padding: 0.35rem 0.55rem; font-size: 0.8rem; font-weight: 600;">
                                        </div>

                                        <div>
                                            <label style="display: block; font-size: 0.72rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.2rem;">
                                                {{ __('Promo Discount Price (optional)') }}
                                            </label>
                                            <input type="number"
                                                   step="any"
                                                   x-model="item.discount_price"
                                                   :placeholder="'e.g. ' + Math.round(item.regular_price * 0.9)"
                                                   style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 8px; padding: 0.35rem 0.55rem; font-size: 0.8rem; font-weight: 600;">
                                        </div>

                                        <div style="grid-column: 1 / -1;">
                                            <label style="display: block; font-size: 0.72rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.2rem;">
                                                {{ __('Promo Subtitle / Short Description') }}
                                            </label>
                                            <input type="text"
                                                   x-model="item.subtitle"
                                                   @input="syncLegacyInputs()"
                                                   placeholder="{{ __('Chef recommendation...') }}"
                                                   style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 8px; padding: 0.35rem 0.55rem; font-size: 0.8rem; font-weight: 600;">
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Right Column: Dish Picker & Search -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; flex-direction: column;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px dashed var(--border-color); padding-bottom: 0.75rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fa-solid fa-utensils" style="color: var(--primary);"></i>
                                <span style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);">
                                    {{ __('Menu Dishes Catalog') }}
                                </span>
                            </div>
                            <span style="font-size: 0.75rem; color: var(--text-muted);"
                                  x-text="filteredProducts.length + ' {{ __('available') }}'"></span>
                        </div>

                        <!-- Search & Category Filter Controls -->
                        <div style="display: flex; flex-direction: column; gap: 0.65rem; margin-bottom: 1rem;">
                            <!-- Search Bar -->
                            <div style="position: relative; display: flex; align-items: center;">
                                <input type="text"
                                       x-model="searchQuery"
                                       placeholder="{{ __('Search dishes by name...') }}"
                                       style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 10px; padding: 0.55rem 0.85rem 0.55rem 2.2rem; font-size: 0.86rem; font-weight: 600;">
                                <span style="position: absolute; left: 0.75rem; color: var(--text-muted); font-size: 0.85rem; pointer-events: none;">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </span>
                                <button type="button"
                                        x-show="searchQuery"
                                        @click="searchQuery = ''"
                                        style="position: absolute; right: 0.65rem; border: none; background: transparent; color: var(--text-muted); cursor: pointer; font-size: 0.85rem;">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>

                            <!-- Category Filter Dropdown -->
                            <div style="position: relative; display: flex; align-items: center;">
                                <select x-model="selectedCategory"
                                        style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 10px; padding: 0.55rem 0.85rem 0.55rem 2.2rem; font-size: 0.86rem; font-weight: 600;">
                                    <option value="all">📂 {{ __('All Categories') }}</option>
                                    <template x-for="cat in categories" :key="cat.id">
                                        <option :value="cat.id" x-text="cat.name"></option>
                                    </template>
                                </select>
                                <span style="position: absolute; left: 0.75rem; color: var(--text-muted); font-size: 0.85rem; pointer-events: none;">
                                    <i class="fa-solid fa-filter"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Catalog Dishes List -->
                        <div style="display: flex; flex-direction: column; gap: 0.6rem; max-height: 420px; overflow-y: auto; padding-right: 0.25rem;">
                            <template x-for="prod in filteredProducts" :key="prod.id">
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.65rem; padding: 0.65rem 0.8rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; transition: border-color 0.15s ease;">
                                    <div style="display: flex; align-items: center; gap: 0.65rem; min-width: 0;">
                                        <img :src="prod.image"
                                             onerror="this.src='{{ asset('images/default-dish.png') }}';"
                                             style="width: 38px; height: 38px; border-radius: 8px; object-fit: cover; flex-shrink: 0; border: 1px solid var(--border-color);">
                                        <div style="min-width: 0;">
                                            <div style="font-size: 0.88rem; font-weight: 700; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                                                 x-text="prod.name"></div>
                                            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">
                                                <span x-text="Number(prod.price).toLocaleString() + ' ' + currency"></span>
                                                <span> · </span>
                                                <span x-text="prod.category_name"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <button type="button"
                                                x-show="!isProductSelected(prod.id)"
                                                @click="addOffer(prod)"
                                                style="border: 1px solid var(--primary); background: rgba(245, 158, 11, 0.1); color: var(--primary); padding: 0.35rem 0.75rem; border-radius: 8px; font-weight: 700; font-size: 0.78rem; cursor: pointer; white-space: nowrap; display: inline-flex; align-items: center; gap: 0.3rem;">
                                            <i class="fa-solid fa-plus"></i>
                                            <span>{{ __('Add') }}</span>
                                        </button>
                                        <span x-show="isProductSelected(prod.id)"
                                              style="background: rgba(16, 185, 129, 0.12); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.35rem 0.65rem; border-radius: 8px; font-weight: 700; font-size: 0.75rem; white-space: nowrap; display: inline-flex; align-items: center; gap: 0.3rem;">
                                            <i class="fa-solid fa-check"></i>
                                            <span>{{ __('Added') }}</span>
                                        </span>
                                    </div>
                                </div>
                            </template>

                            <div x-show="filteredProducts.length === 0"
                                 style="text-align: center; padding: 2rem 1rem; color: var(--text-muted); font-size: 0.85rem;">
                                {{ __('No matching dishes found.') }}
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Live Preview in Admin (Matches Storefront Horizontal Carousel Layout) -->
                <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px dashed var(--border-color);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                        <div style="font-size: 0.88rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-eye" style="color: var(--primary);"></i>
                            <span>{{ __('Live Storefront Carousel Preview') }}</span>
                            <span style="font-size: 0.72rem; font-weight: 700; background: rgba(16, 185, 129, 0.15); color: #10b981; padding: 0.15rem 0.5rem; border-radius: 6px;">
                                {{ __('Real-Time') }}
                            </span>
                        </div>
                        <span style="font-size: 0.78rem; color: var(--text-muted);"
                              x-show="!isEnabled">
                            ⚠️ {{ __('Notice: Daily offers are currently inactive. Activate switch above to display in menu.') }}
                        </span>
                    </div>

                    <!-- Preview Container with Opacity Reflection -->
                    <div id="featuredDishPreviewCard"
                         :style="isEnabled ? 'opacity: 1; filter: none;' : 'opacity: 0.45; filter: grayscale(0.5);'"
                         style="transition: all 0.25s ease;">

                        <!-- If offers > 0: Render preview cards -->
                        <div x-show="offers.length > 0"
                             style="display: flex; gap: 1rem; overflow-x: auto; padding-bottom: 0.75rem; scrollbar-width: thin;">
                            <template x-for="(off, idx) in offers" :key="off.product_id">
                                <div style="flex: 0 0 clamp(260px, 45vw, 320px); border-radius: 16px; overflow: hidden; background: var(--bg-body); border: 2px solid var(--primary); box-shadow: 0 8px 24px rgba(0,0,0,0.15); display: flex; flex-direction: column;">
                                    <div style="position: relative; height: 135px; background: #000; overflow: hidden;">
                                        <img :id="idx === 0 ? 'previewDishImg' : ''"
                                             :src="off.product_image"
                                             onerror="this.src='{{ asset('images/default-dish.png') }}';"
                                             style="width: 100%; height: 100%; object-fit: cover;">
                                        <div style="position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);"></div>
                                        <span :id="idx === 0 ? 'previewDishBadge' : ''"
                                              style="position: absolute; top: 0.65rem; left: 0.65rem; background: linear-gradient(135deg, #f59e0b, #ef4444); color: #fff; font-size: 0.68rem; font-weight: 800; padding: 0.2rem 0.55rem; border-radius: 9999px; text-transform: uppercase;"
                                              x-text="off.badge || defaultBadge"></span>
                                        <span x-show="off.discount_price && Number(off.discount_price) < Number(off.regular_price)"
                                              style="position: absolute; top: 0.65rem; right: 0.65rem; background: #ef4444; color: #fff; font-size: 0.68rem; font-weight: 800; padding: 0.2rem 0.5rem; border-radius: 9999px;"
                                              x-text="'-' + Math.round(((off.regular_price - off.discount_price) / off.regular_price) * 100) + '%'"></span>
                                    </div>
                                    <div style="padding: 0.85rem; display: flex; flex-direction: column; justify-content: space-between; flex-grow: 1;">
                                        <div>
                                            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.25rem;">
                                                <h4 :id="idx === 0 ? 'previewDishName' : ''"
                                                    style="margin: 0; font-size: 1rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit';"
                                                    x-text="off.product_name"></h4>
                                            </div>
                                            <p :id="idx === 0 ? 'previewDishDesc' : ''"
                                               style="margin: 0 0 0.5rem; font-size: 0.78rem; color: var(--text-muted); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"
                                               x-text="off.subtitle || defaultSubtitle"></p>
                                        </div>

                                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed var(--border-color); padding-top: 0.5rem; margin-top: auto;">
                                            <div>
                                                <template x-if="off.discount_price && Number(off.discount_price) > 0">
                                                    <div style="display: flex; align-items: baseline; gap: 0.35rem;">
                                                        <span :id="idx === 0 ? 'previewDishPrice' : ''"
                                                              style="font-size: 1rem; font-weight: 800; color: #ef4444;"
                                                              x-text="Number(off.discount_price).toLocaleString() + ' ' + currency"></span>
                                                        <span style="font-size: 0.75rem; text-decoration: line-through; color: var(--text-muted);"
                                                              x-text="Number(off.regular_price).toLocaleString()"></span>
                                                    </div>
                                                </template>
                                                <template x-if="!off.discount_price || Number(off.discount_price) <= 0">
                                                    <span :id="idx === 0 ? 'previewDishPrice' : ''"
                                                          style="font-size: 1rem; font-weight: 800; color: var(--primary);"
                                                          x-text="Number(off.regular_price).toLocaleString() + ' ' + currency"></span>
                                                </template>
                                            </div>
                                            <span style="background: var(--primary); color: #fff; font-size: 0.75rem; font-weight: 700; padding: 0.3rem 0.65rem; border-radius: 8px;">
                                                {{ __('menu.add') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- If offers is empty: Placeholder preview card -->
                        <div x-show="offers.length === 0"
                             style="max-width: 380px; border-radius: 16px; overflow: hidden; background: var(--bg-body); border: 2px dashed var(--border-color); padding: 2rem 1rem; text-align: center; color: var(--text-muted);">
                            <i class="fa-solid fa-sparkles" style="font-size: 1.8rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                            <p style="margin: 0; font-size: 0.88rem; font-weight: 700;">
                                {{ __('Add dishes to preview the carousel cards') }}
                            </p>
                        </div>
                    </div>
                </div>

            </div>
                </div>

                <!-- 8. AI & AUTOMATION TAB -->
                <div x-show="activeTab === 'ai'" x-transition.opacity role="tabpanel" style="display: flex; flex-direction: column; gap: 1.75rem;">
                    <!-- Shortcut to Daily Offers -->
                    <div style="background: rgba(245, 158, 11, 0.08); border: 1px dashed rgba(245, 158, 11, 0.35); border-radius: 16px; padding: 1.1rem 1.4rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.85rem;">
                            <span style="width: 38px; height: 38px; border-radius: 10px; background: rgba(239, 68, 68, 0.15); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                                <i class="fa-solid fa-fire-flame-curved"></i>
                            </span>
                            <div>
                                <strong style="font-size: 0.95rem; color: var(--text-main);">{{ __('Daily Offers & Dish of the Day Carousel') }}</strong>
                                <p style="margin: 0.2rem 0 0; font-size: 0.82rem; color: var(--text-muted);">
                                    {{ __('Manage multiple dishes of the day in their dedicated tab.') }}
                                </p>
                            </div>
                        </div>
                        <button type="button" @click="setTab('daily_offers')" class="btn btn-secondary" style="font-size: 0.82rem; font-weight: 700; border-color: rgba(239, 68, 68, 0.4); color: #ef4444; cursor: pointer;">
                            <i class="fa-solid fa-arrow-right"></i> {{ __('Open Daily Offers Tab') }}
                        </button>
                    </div>

                    <!-- 3. AI CONTROL CENTER BANNER CARD -->
            <div class="card settings-card" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.08), rgba(236, 72, 153, 0.05)); border: 1px solid rgba(139, 92, 246, 0.3); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card); position: relative; overflow: hidden;">
                <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #8b5cf6, #ec4899, #f59e0b);"></div>

                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
                    <div style="display: flex; align-items: center; gap: 1.1rem; min-width: 0; flex: 1;">
                        <span style="width: 52px; height: 52px; border-radius: 14px; background: linear-gradient(135deg, #8b5cf6, #ec4899); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35);">
                            <i class="fa-solid fa-robot"></i>
                        </span>
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                    {{ __('AI Control Center & AI Waiter') }}
                                </h3>
                                <span style="font-size: 0.72rem; font-weight: 700; background: linear-gradient(135deg, #8b5cf6, #d946ef); color: #fff; padding: 0.15rem 0.5rem; border-radius: 6px;">
                                    NEW DEDICATED HUB
                                </span>
                            </div>
                            <p style="margin: 0.35rem 0 0; font-size: 0.88rem; color: var(--text-muted); line-height: 1.45;">
                                {{ __('AI Waiter, AI providers (Gemini, OpenAI, Claude, DeepSeek, Groq), API keys, and model settings have been moved to the dedicated AI section:') }}
                            </p>

                            <div style="display: flex; gap: 0.75rem; align-items: center; margin-top: 0.75rem; flex-wrap: wrap;">
                                <span style="font-size: 0.78rem; font-weight: 700; color: #8b5cf6; background: rgba(139, 92, 246, 0.12); padding: 0.2rem 0.55rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-microchip"></i> {{ strtoupper($vendor->getAiProvider()) }} ({{ $vendor->getAiModel() }})
                                </span>
                                @if($vendor->ai_waiter_enabled)
                                    <span style="font-size: 0.78rem; font-weight: 700; color: #10b981; background: rgba(16, 185, 129, 0.12); padding: 0.2rem 0.55rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.35rem;">
                                        <i class="fa-solid fa-circle-check"></i> {{ __('AI Waiter: Active') }}
                                    </span>
                                @else
                                    <span style="font-size: 0.78rem; font-weight: 700; color: #64748b; background: rgba(100, 116, 139, 0.12); padding: 0.2rem 0.55rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.35rem;">
                                        <i class="fa-solid fa-pause"></i> {{ __('AI Waiter: Disabled') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div>
                        <a href="{{ route('admin.settings.ai') }}" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 0.92rem; padding: 0.75rem 1.4rem; background: linear-gradient(135deg, #8b5cf6, #7c3aed); border: none; color: #fff; box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35); white-space: nowrap;">
                            <i class="fa-solid fa-gear"></i> {{ __('Open AI Settings') }}
                            <i class="fa-solid fa-arrow-right" style="font-size: 0.8rem;"></i>
                        </a>
                    </div>
                </div>
            </div>

            
                    <!-- 7. CRM AUTOMATION & BIRTHDAY DISCOUNTS -->
            <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.9rem;">
                        <span style="width: 42px; height: 42px; border-radius: 12px; background: rgba(236, 72, 153, 0.15); color: #ec4899; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">
                            <i class="fa-solid fa-cake-candles"></i>
                        </span>
                        <div>
                            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                {{ __('CRM Automation & Birthday Discounts') }}
                            </h3>
                            <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-muted); word-break: break-word;">
                                {{ __('Automated birthday discounts on orders and SMS notifications (Mobipace, SMS.am, Twilio)') }}
                            </p>
                        </div>
                    </div>

                    <label class="switch" style="position: relative; display: inline-block; width: 48px; height: 26px;">
                        <input type="checkbox" name="crm_settings[birthday_discount_enabled]" value="1" {{ !empty($crmSettings['birthday_discount_enabled']) ? 'checked' : '' }} id="crmBirthdayToggle" onchange="toggleCrmFields()">
                        <span class="slider round" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 34px;"></span>
                    </label>
                </div>

                <div id="crmContainer" style="{{ empty($crmSettings['birthday_discount_enabled']) ? 'opacity: 0.55; pointer-events: none;' : '' }}; transition: all 0.25s ease;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
                        <div>
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                                {{ __('Birthday Discount (%)') }}
                            </label>
                            <div style="position: relative; display: flex; align-items: center;">
                                <input type="number" min="0" max="100" step="1" name="crm_settings[birthday_discount_percent]" value="{{ $crmSettings['birthday_discount_percent'] ?? 15 }}" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: #10b981; padding: 0.7rem 1rem 0.7rem 2.2rem; border-radius: 12px; font-weight: 700; font-size: 0.95rem;">
                                <span style="position: absolute; left: 0.9rem; color: var(--text-muted); font-weight: 700;">%</span>
                            </div>
                        </div>

                        <div>
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                                {{ __('Validity Period (days)') }}
                            </label>
                            <input type="number" min="0" max="30" name="crm_settings[birthday_validity_days]" value="{{ $crmSettings['birthday_validity_days'] ?? 3 }}" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.7rem 1rem; border-radius: 12px; font-weight: 600; font-size: 0.92rem;">
                            <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; display: block;">{{ __('Discount is valid ± specified days around birthday') }}</span>
                        </div>

                        <div>
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                                {{ __('SMS Provider') }}
                            </label>
                            <select name="crm_settings[sms_provider]" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.7rem 1rem; border-radius: 12px; font-weight: 600; font-size: 0.92rem;">
                                <option value="mobipace" {{ ($crmSettings['sms_provider'] ?? '') === 'mobipace' ? 'selected' : '' }}>Mobipace SMS (Armenia)</option>
                                <option value="smsam" {{ ($crmSettings['sms_provider'] ?? '') === 'smsam' ? 'selected' : '' }}>SMS.am (Armenia)</option>
                                <option value="twilio" {{ ($crmSettings['sms_provider'] ?? '') === 'twilio' ? 'selected' : '' }}>Twilio (International)</option>
                                <option value="log" {{ ($crmSettings['sms_provider'] ?? '') === 'log' ? 'selected' : '' }}>Log / Test Mode (no SMS sent)</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
                        <div>
                            <label style="display: flex; align-items: center; justify-content: space-between; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                                <span>{{ __('SMS API Key / Token') }}</span>
                                @if(!empty($crmSettings['configured']))
                                    <span style="font-size: 0.72rem; color: #10b981; font-weight: 700;">✓ {{ __('Configured') }}</span>
                                @endif
                            </label>
                            <input type="password" name="crm_settings[sms_api_key]" value="" placeholder="{{ !empty($crmSettings['configured']) ? '•••••••• (' . ($crmSettings['masked'] ?? 'Configured') . ')' : 'API Key' }}" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.7rem 1rem; border-radius: 12px; font-size: 0.92rem;">
                        </div>

                        <div>
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                                {{ __('SMS Sender ID') }}
                            </label>
                            <input type="text" name="crm_settings[sms_sender_id]" value="{{ $crmSettings['sms_sender_id'] ?? 'QRMENU' }}" placeholder="e.g. QRMENU or Restaurant" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.7rem 1rem; border-radius: 12px; font-weight: 600; font-size: 0.92rem;">
                        </div>

                        <div style="display: flex; align-items: flex-end; padding-bottom: 0.5rem;">
                            <label style="display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.88rem; font-weight: 600; cursor: pointer;">
                                <input type="checkbox" name="crm_settings[birthday_sms_enabled]" value="1" {{ !empty($crmSettings['birthday_sms_enabled']) ? 'checked' : '' }} style="accent-color: var(--primary);">
                                <span>{{ __('Enable automated birthday greeting SMS') }}</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                            {{ __('Birthday SMS Message Template') }}
                        </label>
                        <textarea name="crm_settings[birthday_sms_template]" rows="2" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.7rem 1rem; border-radius: 12px; font-size: 0.9rem; resize: vertical;">{{ $crmSettings['birthday_sms_template'] ?? 'Happy Birthday {NAME}! Enjoy a {DISCOUNT}% discount at {VENDOR}.' }}</textarea>
                        <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; display: block;">{{ __('Available placeholders: {NAME} - customer name, {DISCOUNT} - discount %, {VENDOR} - restaurant name') }}</span>
                    </div>
                </div>
            </div>

            
                </div>

                <!-- 9. INTEGRATIONS TAB -->
                <div x-show="activeTab === 'integrations'" x-transition.opacity role="tabpanel" style="display: flex; flex-direction: column; gap: 1.75rem;">
                    <!-- 3. CUSTOM DOMAIN & BRANDING URL -->
            <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.9rem;">
                        <span style="width: 42px; height: 42px; border-radius: 12px; background: rgba(6, 182, 212, 0.15); color: #06b6d4; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">
                            <i class="fa-solid fa-globe"></i>
                        </span>
                        <div>
                            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                {{ __('Custom Domain & White-Label') }}
                            </h3>
                            <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-muted); word-break: break-word;">
                                {{ __('Connect your custom domain so the menu opens on your domain (e.g. menu.restaurant.com) and admins access yourdomain/admin') }}
                            </p>
                        </div>
                    </div>

                    @php
                        $primaryDomain = $vendor->primaryCustomDomain;
                    @endphp
                    @if($primaryDomain && $primaryDomain->isActive())
                        <span style="font-size: 0.78rem; font-weight: 700; background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; padding: 0.3rem 0.65rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                            <i class="fa-solid fa-circle-check"></i> {{ __('Active & Connected') }}
                        </span>
                    @elseif($primaryDomain && $primaryDomain->isVerified())
                        <span style="font-size: 0.78rem; font-weight: 700; background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); border-radius: 8px; padding: 0.3rem 0.65rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                            <i class="fa-solid fa-shield-halved"></i> {{ __('Ownership Verified (Awaiting DNS)') }}
                        </span>
                    @elseif($primaryDomain && $primaryDomain->isDnsDetected())
                        <span style="font-size: 0.78rem; font-weight: 700; background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 8px; padding: 0.3rem 0.65rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                            <i class="fa-solid fa-bolt"></i> {{ __('DNS Detected (Awaiting Verification)') }}
                        </span>
                    @elseif($vendor->hasCustomDomain())
                        <span style="font-size: 0.78rem; font-weight: 700; background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 8px; padding: 0.3rem 0.65rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                            <i class="fa-solid fa-hourglass-half"></i> {{ __('Pending Configuration') }}
                        </span>
                    @else
                        <span style="font-size: 0.78rem; font-weight: 700; background: rgba(148, 163, 184, 0.15); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.3); border-radius: 8px; padding: 0.3rem 0.65rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                            <i class="fa-solid fa-circle-minus"></i> {{ __('Disabled') }}
                        </span>
                    @endif
                </div>

                <div style="background: rgba(15, 23, 42, 0.6); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.25rem;">
                    <div style="display: grid; grid-template-columns: 1fr auto; gap: 1rem; align-items: end; flex-wrap: wrap;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                                {{ __('Custom Domain FQDN') }}
                            </label>
                            <div style="position: relative; display: flex; align-items: center;">
                                <input type="text" name="custom_domain" id="customDomainInput" value="{{ old('custom_domain', $vendor->custom_domain) }}" class="form-control" placeholder="e.g. menu.restaurant.com or restaurant.com" style="width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem 0.75rem 2.6rem; font-size: 0.95rem; font-weight: 600;">
                                <span style="position: absolute; left: 1rem; color: #06b6d4; font-size: 1rem;">
                                    <i class="fa-solid fa-link"></i>
                                </span>
                            </div>
                        </div>

                        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                            <button type="button" id="btnVerifyDomain" class="btn btn-secondary" style="padding: 0.75rem 1rem; font-size: 0.88rem; font-weight: 700; border-radius: 12px; display: inline-flex; align-items: center; gap: 0.5rem; border-color: rgba(16, 185, 129, 0.4); color: #10b981; white-space: nowrap;">
                                <i class="fa-solid fa-shield-check"></i> {{ __('Verify Ownership') }}
                            </button>
                            <button type="button" id="btnCheckDomainDns" class="btn btn-secondary" style="padding: 0.75rem 1rem; font-size: 0.88rem; font-weight: 700; border-radius: 12px; display: inline-flex; align-items: center; gap: 0.5rem; border-color: rgba(6, 182, 212, 0.4); color: #06b6d4; white-space: nowrap;">
                                <i class="fa-solid fa-bolt"></i> {{ __('Check DNS') }}
                            </button>
                        </div>
                    </div>

                    <span style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.5rem; display: block;">
                        {{ __('Enter domain without http:// or https:// (e.g. menu.restaurant.com). Leave blank to disable custom domain:') }}
                    </span>

                    <!-- Live Check / Verification Result Alert Box -->
                    <div id="dnsCheckResultBox" style="display: none; margin-top: 1rem; padding: 0.85rem 1.1rem; border-radius: 12px; font-size: 0.86rem; font-weight: 600;"></div>

                    @if($primaryDomain)
                        <!-- TXT Challenge Info Box -->
                        <div style="margin-top: 1rem; padding: 0.9rem 1.1rem; border-radius: 12px; background: rgba(30, 41, 59, 0.5); border: 1px solid rgba(255, 255, 255, 0.08); font-size: 0.84rem;">
                            <div style="font-weight: 700; color: #38bdf8; margin-bottom: 0.45rem; display: flex; align-items: center; justify-content: space-between;">
                                <span><i class="fa-solid fa-key"></i> {{ __('DNS TXT Record for Ownership Verification') }}</span>
                                @if($primaryDomain->isVerified())
                                    <span style="color: #10b981; font-size: 0.76rem;"><i class="fa-solid fa-circle-check"></i> {{ __('Verified') }} ({{ $primaryDomain->verified_at?->format('d.m.Y H:i') }})</span>
                                @else
                                    <span style="color: #f59e0b; font-size: 0.76rem;"><i class="fa-solid fa-clock"></i> {{ __('Waiting for TXT record') }}</span>
                                @endif
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr; gap: 0.4rem; font-family: monospace; font-size: 0.8rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.25); padding: 0.35rem 0.6rem; border-radius: 6px;">
                                    <span style="color: var(--text-muted);">Host/Name: <strong style="color: var(--text-main);">{{ $primaryDomain->getChallengeHost() }}</strong></span>
                                    <button type="button" onclick="navigator.clipboard.writeText('{{ $primaryDomain->getChallengeHost() }}'); alert('Host copied');" style="background: none; border: none; color: #38bdf8; cursor: pointer;" title="Copy"><i class="fa-solid fa-copy"></i></button>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.25); padding: 0.35rem 0.6rem; border-radius: 6px;">
                                    <span style="color: var(--text-muted); word-break: break-all;">TXT Value: <strong style="color: #a5f3fc;">{{ $primaryDomain->verification_token }}</strong></span>
                                    <button type="button" onclick="navigator.clipboard.writeText('{{ $primaryDomain->verification_token }}'); alert('Token copied');" style="background: none; border: none; color: #38bdf8; cursor: pointer;" title="Copy"><i class="fa-solid fa-copy"></i></button>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Action Links if domain exists -->
                    @if($vendor->hasCustomDomain())
                        <div style="margin-top: 1.1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.06); display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                            <a href="{{ $vendor->getStorefrontUrl() }}" target="_blank" class="btn btn-secondary" style="font-size: 0.82rem; font-weight: 600; border-radius: 10px; display: inline-flex; align-items: center; gap: 0.45rem;">
                                <i class="fa-solid fa-arrow-up-right-from-square" style="color: #06b6d4;"></i> {{ __('Open Menu (Storefront)') }}
                            </a>
                            <a href="{{ $vendor->getAdminUrl() }}" target="_blank" class="btn btn-secondary" style="font-size: 0.82rem; font-weight: 600; border-radius: 10px; display: inline-flex; align-items: center; gap: 0.45rem;">
                                <i class="fa-solid fa-shield-halved" style="color: #f59e0b;"></i> {{ __('Open Admin Panel') }}
                            </a>
                        </div>
                    @endif
                </div>

                <!-- DNS & cPanel Setup Instructions Box -->
                <div style="background: rgba(30, 41, 59, 0.4); border: 1px dashed rgba(6, 182, 212, 0.35); border-radius: 16px; padding: 1.25rem;">
                    <div style="font-size: 0.92rem; font-weight: 800; color: #38bdf8; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-circle-info"></i> {{ __('How to connect your domain (DNS & cPanel steps)') }}
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; font-size: 0.84rem; color: var(--text-muted);">
                        <!-- Step 1: TXT Verification -->
                        <div style="background: rgba(15, 23, 42, 0.5); padding: 1rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                            <div style="font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                                {{ __('1. Ownership Verification (TXT Record)') }}
                            </div>
                            <p style="margin: 0 0 0.5rem 0; line-height: 1.4;">
                                {{ __('Add a TXT Record in your domain DNS with the Host and Value above, then click "Verify Ownership":') }}
                            </p>
                            <div style="font-size: 0.78rem; color: #a5f3fc;">
                                <i class="fa-solid fa-lock"></i> {{ __('This ensures that only the verified domain owner can link it to their restaurant.') }}
                            </div>
                        </div>

                        <!-- Step 2: DNS Routing -->
                        <div style="background: rgba(15, 23, 42, 0.5); padding: 1rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                            <div style="font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                                {{ __('2. DNS Routing (A or CNAME)') }}
                            </div>
                            <p style="margin: 0 0 0.5rem 0; line-height: 1.4;">
                                {{ __('Add an A Record in your domain DNS pointing to:') }}
                            </p>
                            <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.3); padding: 0.4rem 0.65rem; border-radius: 6px; font-family: monospace; color: #a5f3fc; font-size: 0.82rem;">
                                <span>A &rarr; {{ $_SERVER['SERVER_ADDR'] ?? gethostbyname('menu.elab.am') }}</span>
                                <button type="button" onclick="navigator.clipboard.writeText('{{ $_SERVER['SERVER_ADDR'] ?? gethostbyname('menu.elab.am') }}'); alert('IP copied');" style="background: none; border: none; color: #38bdf8; cursor: pointer; padding: 2px 4px;" title="Copy IP">
                                    <i class="fa-solid fa-copy"></i>
                                </button>
                            </div>
                            <div style="margin-top: 0.4rem; font-size: 0.78rem;">
                                {{ __('or CNAME to') }} <code>menu.elab.am</code>
                            </div>
                        </div>

                        <!-- Step 3: cPanel / SSL -->
                        <div style="background: rgba(15, 23, 42, 0.5); padding: 1rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                            <div style="font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                                {{ __('3. cPanel & SSL Certificate') }}
                            </div>
                            <p style="margin: 0 0 0.5rem 0; line-height: 1.4;">
                                {{ __('In cPanel create a new Domain/Alias with the same Document Root (e.g. menu.elab.am/public).') }}
                            </p>
                            <div style="font-size: 0.78rem; color: #a5f3fc;">
                                <i class="fa-solid fa-shield"></i> {{ __('In cPanel SSL/TLS Status, run AutoSSL for a free HTTPS certificate.') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            
                    <!-- Network Thermal Printer Integration -->
                    <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
                            <div style="display: flex; align-items: center; gap: 0.9rem;">
                                <span style="width: 44px; height: 44px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                                    <i class="fa-solid fa-print"></i>
                                </span>
                                <div>
                                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                        {{ __('Thermal Printers & ESC/POS') }}
                                    </h3>
                                    <p style="margin: 0.25rem 0 0; font-size: 0.85rem; color: var(--text-muted);">
                                        {{ __('Direct TCP/IP socket printing for kitchen, bar, and cashier thermal receipt printers') }}
                                    </p>
                                </div>
                            </div>
                            <button type="button" @click="setTab('orders')" class="btn btn-secondary" style="font-size: 0.85rem; border-radius: 12px; font-weight: 700;">
                                <i class="fa-solid fa-sliders"></i> {{ __('Configure in Orders') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 10. SECURITY & ACCESS TAB -->
                <div x-show="activeTab === 'security'" x-transition.opacity role="tabpanel" style="display: flex; flex-direction: column; gap: 1.75rem;">
                    <!-- 9. USER PROFILE & 2FA SECURITY CARD -->
            <div class="card settings-card" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(245, 158, 11, 0.05) 100%); border: 1.5px solid {{ Auth::user()->hasTwoFactorEnabled() ? '#10b981' : 'var(--border-color)' }}; border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <span style="width: 48px; height: 48px; border-radius: 14px; background: {{ Auth::user()->hasTwoFactorEnabled() ? 'rgba(16, 185, 129, 0.15)' : 'rgba(245, 158, 11, 0.15)' }}; color: {{ Auth::user()->hasTwoFactorEnabled() ? '#10b981' : '#f59e0b' }}; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                            <i class="fa-solid fa-shield-halved"></i>
                        </span>
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
                                <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                    {{ __('Personal Security & 2FA (Profile)') }}
                                </h3>
                                @if(Auth::user()->hasTwoFactorEnabled())
                                    <span class="badge badge-emerald" style="font-weight: 700;">
                                        <i class="fa-solid fa-circle-check"></i> {{ __('Active') }} ({{ Auth::user()->two_factor_type === 'authenticator' ? 'Google Authenticator' : 'Email Code' }})
                                    </span>
                                @else
                                    <span class="badge badge-amber" style="font-weight: 700;">
                                        <i class="fa-solid fa-triangle-exclamation"></i> {{ __('Disabled') }}
                                    </span>
                                @endif
                            </div>
                            <p style="margin: 0.25rem 0 0; font-size: 0.85rem; color: var(--text-muted); word-break: break-word;">
                                {{ __('Two-factor authentication (2FA) protects account login, password changes, and email updates.') }}
                            </p>
                        </div>
                    </div>

                    <a href="{{ route('admin.profile') }}" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; text-decoration: none; border-radius: 12px; font-weight: 700; padding: 0.65rem 1.3rem;">
                        <i class="fa-solid fa-key"></i> {{ __('Manage 2FA & Password') }}
                    </a>
                </div>
            </div>

            
                    <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <span style="width: 48px; height: 48px; border-radius: 14px; background: rgba(14, 165, 233, 0.15); color: #0ea5e9; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                                    <i class="fa-solid fa-users"></i>
                                </span>
                                <div>
                                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                        {{ __('Team & Access Policies') }}
                                    </h3>
                                    <p style="margin: 0.25rem 0 0; font-size: 0.85rem; color: var(--text-muted);">
                                        {{ __('Manage team roles (manager, staff, chef, cashier) and location-specific permissions.') }}
                                    </p>
                                </div>
                            </div>
                            <a href="{{ route('admin.team.index') }}" class="btn btn-secondary" style="text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 0.88rem;">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i> {{ __('Team & Employees') }}
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Bottom Save Bar (Static, non-floating) -->
                <div class="settings-save-bar" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1rem 1.35rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-top: 2.5rem; margin-bottom: 2rem;">
                    <div style="display: flex; align-items: center; gap: 0.6rem; color: var(--text-muted); font-size: 0.85rem;">
                        <i class="fa-solid fa-cloud-arrow-up" style="color: var(--primary);"></i>
                        <span>{{ __('All changes will be saved for the current branch') }}</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <button type="button" x-show="isDirty" x-cloak @click="window.location.reload()" class="btn btn-secondary" style="border-radius: 12px; font-size: 0.88rem; font-weight: 600;">
                            {{ __('Discard') }}
                        </button>
                        <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.6rem; border-radius: 14px; font-weight: 700; padding: 0.75rem 2rem; font-size: 0.95rem; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35);">
                            <i class="fa-solid fa-check"></i> {{ __('Save All Settings') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Enterprise Settings Layout Styles */
.settings-layout-wrapper {
    display: block;
    width: 100%;
}
.settings-content-area {
    width: 100%;
}
.settings-mobile-tabs {
    display: flex;
    gap: 0.5rem;
    overflow-x: auto;
    padding-bottom: 0.65rem;
    margin-bottom: 1.25rem;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}
.settings-mobile-tabs::-webkit-scrollbar {
    display: none;
}
.settings-mobile-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 0.95rem;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    color: var(--text-muted);
    font-size: 0.82rem;
    font-weight: 600;
    white-space: nowrap;
    cursor: pointer;
    flex-shrink: 0;
}
.settings-mobile-tab-btn.active {
    background: var(--primary-gradient);
    color: #ffffff;
    border-color: transparent;
    box-shadow: 0 4px 12px var(--primary-glow);
}
@media (min-width: 993px) {
    .hide-on-desktop {
        display: none !important;
    }
}
[x-cloak] {
    display: none !important;
}

.settings-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}
.settings-card:hover {
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
}
.form-control {
    background: var(--bg-body);
    border: 1px solid var(--border-color);
    color: var(--text-main);
    box-sizing: border-box;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.form-control:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15) !important;
    outline: none;
}
.type-pill:hover {
    border-color: var(--primary) !important;
}
.type-pill.selected {
    border-color: var(--primary) !important;
    background: rgba(245, 158, 11, 0.12) !important;
    color: var(--primary) !important;
}

/* Modern iOS-style toggle switches */
.switch {
    position: relative;
    display: inline-block;
    width: 46px;
    height: 25px;
    flex-shrink: 0;
}
.switch input {
    opacity: 0;
    width: 0;
    height: 0;
    position: absolute;
}
.slider.round {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background-color: var(--border-color, #cbd5e1);
    transition: 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    border-radius: 34px;
    border: 1px solid var(--border-color);
}
.slider.round:before {
    position: absolute;
    content: "";
    height: 19px;
    width: 19px;
    left: 2px;
    bottom: 2px;
    background-color: #ffffff;
    transition: 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.25);
}
.switch input:checked + .slider.round {
    background-color: #10b981;
    border-color: #10b981;
}
.switch input:checked + .slider.round:before {
    transform: translateX(21px);
}

</style>

<script>
let CURRENCY = "{{ $vendor->currency }}";

function onCurrencyChanged(newCurrency) {
    CURRENCY = newCurrency === 'AMD' ? '֏' : newCurrency;
    document.querySelectorAll('.currency-label-display').forEach(el => el.textContent = CURRENCY);
    window.dispatchEvent(new CustomEvent('primary-currency-changed', { detail: CURRENCY }));
    const suffix = document.getElementById('serviceFeeSuffix');
    const serviceFeeType = document.querySelector('input[name="service_fee_type"]:checked')?.value || 'percent';
    if (suffix && serviceFeeType === 'fixed') {
        suffix.textContent = CURRENCY;
    }
    updateCalculationsPreview();
}

function toggleServiceFeeFields() {
    const isChecked = document.getElementById('serviceFeeToggle').checked;
    const container = document.getElementById('serviceFeeContainer');
    const label = document.getElementById('serviceFeeStatusLabel');
    if (isChecked) {
        container.style.opacity = '1';
        container.style.pointerEvents = 'auto';
        label.textContent = "Active";
    } else {
        container.style.opacity = '0.55';
        container.style.pointerEvents = 'none';
        label.textContent = "Disabled";
    }
    updateCalculationsPreview();
}

function toggleDeliveryFields() {
    const isChecked = document.getElementById('deliveryToggle').checked;
    const container = document.getElementById('deliveryContainer');
    const label = document.getElementById('deliveryStatusLabel');
    if (isChecked) {
        container.style.opacity = '1';
        container.style.pointerEvents = 'auto';
        label.textContent = "Active";
    } else {
        container.style.opacity = '0.55';
        container.style.pointerEvents = 'none';
        label.textContent = "Disabled";
    }
    updateCalculationsPreview();
}

function toggleTakeawayFields() {
    const isChecked = document.getElementById('takeawayToggle').checked;
    const container = document.getElementById('takeawayContainer');
    const label = document.getElementById('takeawayStatusLabel');
    if (isChecked) {
        container.style.opacity = '1';
        container.style.pointerEvents = 'auto';
        label.textContent = "Active";
    } else {
        container.style.opacity = '0.55';
        container.style.pointerEvents = 'none';
        label.textContent = "Disabled";
    }
    updateCalculationsPreview();
}

function toggleOnlinePaymentsFields() {
    const isChecked = document.getElementById('onlinePaymentsToggle').checked;
    const container = document.getElementById('onlineGatewaysContainer');
    if (container) {
        if (isChecked) {
            container.style.opacity = '1';
            container.style.pointerEvents = 'auto';
        } else {
            container.style.opacity = '0.55';
            container.style.pointerEvents = 'none';
        }
    }
}

function toggleCrmFields() {
    const isChecked = document.getElementById('crmBirthdayToggle').checked;
    const container = document.getElementById('crmContainer');
    if (container) {
        if (isChecked) {
            container.style.opacity = '1';
            container.style.pointerEvents = 'auto';
        } else {
            container.style.opacity = '0.55';
            container.style.pointerEvents = 'none';
        }
    }
}

function handleFeeTypeChange(type) {
    const pillPercent = document.getElementById('pillPercent');
    const pillFixed = document.getElementById('pillFixed');
    const suffix = document.getElementById('serviceFeeSuffix');

    if (type === 'percent') {
        pillPercent.classList.add('selected');
        pillFixed.classList.remove('selected');
        suffix.textContent = '%';
    } else {
        pillFixed.classList.add('selected');
        pillPercent.classList.remove('selected');
        suffix.textContent = CURRENCY;
    }
    updateCalculationsPreview();
}

function updateCalculationsPreview() {
    // Service fee calculation for sample 15,000 AMD
    const sampleSubtotal = 15000;
    const isServiceEnabled = document.getElementById('serviceFeeToggle').checked;
    const serviceFeeType = document.querySelector('input[name="service_fee_type"]:checked')?.value || 'percent';
    const feeVal = parseFloat(document.getElementById('serviceFeeValue').value) || 0;
    const minOrder = parseFloat(document.getElementById('serviceFeeMinOrder').value) || 0;
    const previewBox = document.getElementById('serviceFeePreviewBox');

    if (!isServiceEnabled) {
        previewBox.innerHTML = '<span style="color: var(--text-muted);">Disabled</span>';
    } else if (minOrder > 0 && sampleSubtotal < minOrder) {
        previewBox.innerHTML = `<span style="color: #f59e0b;">0 ${CURRENCY} (Min threshold: ${minOrder.toLocaleString()} ${CURRENCY})</span>`;
    } else {
        let calc = 0;
        if (serviceFeeType === 'percent') {
            calc = Math.round((sampleSubtotal * feeVal) / 100);
            previewBox.innerHTML = `<span style="color: #10b981;">+${calc.toLocaleString()} ${CURRENCY} (${feeVal}%)</span> &rarr; Total: ${(sampleSubtotal + calc).toLocaleString()} ${CURRENCY}`;
        } else {
            calc = feeVal;
            previewBox.innerHTML = `<span style="color: #10b981;">+${calc.toLocaleString()} ${CURRENCY} (Fixed)</span> &rarr; Total: ${(sampleSubtotal + calc).toLocaleString()} ${CURRENCY}`;
        }
    }

    // Delivery preview
    const isDeliveryEnabled = document.getElementById('deliveryToggle').checked;
    const deliveryFee = parseFloat(document.getElementById('deliveryFeeInput').value) || 0;
    const deliveryMin = parseFloat(document.getElementById('deliveryMinAmount').value) || 0;
    const freeFrom = parseFloat(document.getElementById('deliveryFreeFrom').value) || 0;

    const previewText = document.getElementById('deliveryPreviewText');
    const previewSubtext = document.getElementById('deliveryPreviewSubtext');

    if (!isDeliveryEnabled) {
        previewText.textContent = "Delivery service is currently disabled";
        previewSubtext.textContent = "Customers will not be able to select delivery in the cart";
    } else {
        let minText = deliveryMin > 0 ? `Minimum order: ${deliveryMin.toLocaleString()} ${CURRENCY}: ` : '';
        if (freeFrom > 0) {
            previewText.textContent = `${minText}Delivery fee: ${deliveryFee.toLocaleString()} ${CURRENCY}, and FREE from ${freeFrom.toLocaleString()} ${CURRENCY} 🎉`;
            previewSubtext.textContent = "A progress bar will be shown in customer's cart";
        } else {
            previewText.textContent = `${minText}Fixed delivery fee: ${deliveryFee.toLocaleString()} ${CURRENCY}`;
            previewSubtext.textContent = "Delivery is always paid";
        }
    }

    // Takeaway preview
    const isTakeawayEnabled = document.getElementById('takeawayToggle')?.checked;
    const takeawayMin = parseFloat(document.getElementById('takeawayMinAmount')?.value) || 0;
    const takeawayPreviewText = document.getElementById('takeawayPreviewText');
    const takeawayPreviewSubtext = document.getElementById('takeawayPreviewSubtext');

    if (takeawayPreviewText && takeawayPreviewSubtext) {
        if (!isTakeawayEnabled) {
            takeawayPreviewText.textContent = "Takeaway service is currently disabled";
            takeawayPreviewSubtext.textContent = "Customers will not be able to select Takeaway";
        } else {
            let minText = takeawayMin > 0 ? `Minimum order: ${takeawayMin.toLocaleString()} ${CURRENCY}: ` : '';
            takeawayPreviewText.textContent = `${minText}Takeaway orders are active`;
            takeawayPreviewSubtext.textContent = "Customers can order without being at the restaurant/table";
        }
    }

    // Featured Dish Preview
    updateFeaturedDishPreview();
}

function updateFeaturedDishPreview() {
    const select = document.getElementById('featuredProductSelect');
    const badgeInput = document.getElementById('featuredDishBadgeInput');
    const subtitleInput = document.getElementById('featuredDishSubtitleInput');
    const toggle = document.getElementById('featuredDishToggle');
    const previewCard = document.getElementById('featuredDishPreviewCard');

    if (!select || !previewCard) return;

    if (toggle && !toggle.checked) {
        previewCard.style.opacity = '0.4';
        previewCard.style.filter = 'grayscale(0.6)';
    } else {
        previewCard.style.opacity = '1';
        previewCard.style.filter = 'none';
    }

    const opt = select.options[select.selectedIndex];
    const nameEl = document.getElementById('previewDishName');
    const priceEl = document.getElementById('previewDishPrice');
    const imgEl = document.getElementById('previewDishImg');
    const descEl = document.getElementById('previewDishDesc');
    const badgeEl = document.getElementById('previewDishBadge');

    if (opt && opt.value) {
        if (nameEl) nameEl.textContent = opt.dataset.name || opt.text;
        if (priceEl) priceEl.textContent = (opt.dataset.price || '') + ' ' + '{{ $vendor->currency ?? "֏" }}';
        if (imgEl && opt.dataset.image) imgEl.src = opt.dataset.image;
        if (descEl) descEl.textContent = subtitleInput.value.trim() || opt.dataset.desc || "Chef's special selection";
    } else {
        if (nameEl) nameEl.textContent = 'Select dish';
        if (priceEl) priceEl.textContent = '';
        if (descEl) descEl.textContent = 'No dish selected';
    }

    if (badgeEl) {
        badgeEl.textContent = badgeInput.value.trim() || "⭐ TODAY'S SPECIAL";
    }
}

function dailyOffersAdminApp(allProducts, initialOffers, updateUrl, csrfToken, currency) {
    return {
        allProducts: allProducts || [],
        offers: initialOffers || [],
        categories: [],
        selectedCategory: 'all',
        searchQuery: '',
        isEnabled: true,
        currency: (!currency || currency === 'AMD') ? '֏' : currency,
        isSaving: false,
        saveMessage: '',
        saveMessageType: 'success',
        defaultBadge: "⭐ TODAY'S SPECIAL",
        defaultSubtitle: "Chef's special selection",

        init() {
            const toggleEl = document.getElementById('featuredDishToggle');
            if (toggleEl) {
                this.isEnabled = toggleEl.checked;
            }

            const cats = {};
            this.allProducts.forEach(p => {
                if (p.category_id && p.category_name) {
                    cats[p.category_id] = p.category_name;
                }
            });
            this.categories = Object.keys(cats).map(id => ({ id: id, name: cats[id] }));

            this.reindexOffers();
            this.syncLegacyInputs();
        },

        get filteredProducts() {
            const q = this.searchQuery.toLowerCase().trim();
            return this.allProducts.filter(p => {
                const matchesCat = this.selectedCategory === 'all' || String(p.category_id) === String(this.selectedCategory);
                const matchesQuery = !q || (p.name && p.name.toLowerCase().includes(q)) || (p.category_name && p.category_name.toLowerCase().includes(q));
                return matchesCat && matchesQuery;
            });
        },

        isProductSelected(productId) {
            return this.offers.some(o => Number(o.product_id) === Number(productId));
        },

        addOffer(product) {
            if (this.isProductSelected(product.id)) return;

            this.offers.push({
                product_id: Number(product.id),
                product_name: product.name,
                product_image: product.image || '{{ asset('images/default-dish.png') }}',
                category_name: product.category_name || '',
                regular_price: Number(product.price) || 0,
                badge: this.defaultBadge,
                subtitle: product.description || this.defaultSubtitle,
                discount_price: null,
                is_active: true,
                sort_order: this.offers.length
            });

            this.reindexOffers();
            this.syncLegacyInputs();
        },

        removeOffer(index) {
            this.offers.splice(index, 1);
            this.reindexOffers();
            this.syncLegacyInputs();
        },

        moveUp(index) {
            if (index <= 0) return;
            const temp = this.offers[index];
            this.offers[index] = this.offers[index - 1];
            this.offers[index - 1] = temp;
            this.reindexOffers();
            this.syncLegacyInputs();
        },

        moveDown(index) {
            if (index >= this.offers.length - 1) return;
            const temp = this.offers[index];
            this.offers[index] = this.offers[index + 1];
            this.offers[index + 1] = temp;
            this.reindexOffers();
            this.syncLegacyInputs();
        },

        reindexOffers() {
            this.offers.forEach((o, idx) => {
                o.sort_order = idx;
            });
        },

        syncLegacyInputs() {
            const jsonInput = document.getElementById('dailyOffersJsonInput');
            if (jsonInput) {
                jsonInput.value = JSON.stringify(this.offers);
            }
            const firstOffer = this.offers[0];
            const featSelect = document.getElementById('featuredProductSelect');
            if (featSelect) {
                featSelect.value = firstOffer ? firstOffer.product_id : '';
            }
            const featBadge = document.getElementById('featuredDishBadgeInput');
            if (featBadge && firstOffer) {
                featBadge.value = firstOffer.badge || this.defaultBadge;
            }
            const featSub = document.getElementById('featuredDishSubtitleInput');
            if (featSub && firstOffer) {
                featSub.value = firstOffer.subtitle || this.defaultSubtitle;
            }
            const featToggle = document.getElementById('featuredDishToggle');
            if (featToggle) {
                featToggle.checked = this.isEnabled;
            }
            if (typeof updateFeaturedDishPreview === 'function') {
                updateFeaturedDishPreview();
            }
        },

        async saveOffersAjax() {
            this.isSaving = true;
            this.saveMessage = '';

            try {
                const payload = {
                    featured_dish_enabled: this.isEnabled ? 1 : 0,
                    daily_offers: this.offers.map((o, idx) => ({
                        product_id: Number(o.product_id),
                        sort_order: idx,
                        badge: o.badge,
                        subtitle: o.subtitle,
                        discount_price: o.discount_price ? Number(o.discount_price) : null,
                        is_active: o.is_active ? 1 : 0
                    }))
                };

                const res = await fetch(updateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.saveMessageType = 'success';
                    this.saveMessage = data.message || '{{ __('Daily offers updated successfully.') }}';
                } else {
                    this.saveMessageType = 'error';
                    this.saveMessage = data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : '{{ __('Failed to save daily offers.') }}');
                }
            } catch (err) {
                this.saveMessageType = 'error';
                this.saveMessage = err.message || 'Network error.';
            } finally {
                this.isSaving = false;
                setTimeout(() => { this.saveMessage = ''; }, 4500);
            }
        }
    };
}


document.addEventListener('DOMContentLoaded', () => {
    updateCalculationsPreview();

    const featSelect = document.getElementById('featuredProductSelect');
    if (featSelect) featSelect.addEventListener('change', updateFeaturedDishPreview);
    const featBadge = document.getElementById('featuredDishBadgeInput');
    if (featBadge) featBadge.addEventListener('input', updateFeaturedDishPreview);
    const featSub = document.getElementById('featuredDishSubtitleInput');
    if (featSub) featSub.addEventListener('input', updateFeaturedDishPreview);
    const featToggle = document.getElementById('featuredDishToggle');
    if (featToggle) featToggle.addEventListener('change', updateFeaturedDishPreview);

    const aiWaiterToggle = document.getElementById('aiWaiterToggle');
    const aiWaiterOptionsBlock = document.getElementById('aiWaiterOptionsBlock');
    if (aiWaiterToggle && aiWaiterOptionsBlock) {
        aiWaiterToggle.addEventListener('change', () => {
            aiWaiterOptionsBlock.style.display = aiWaiterToggle.checked ? 'block' : 'none';
        });
    }

    const btnVerifyDomain = document.getElementById('btnVerifyDomain');
    const btnCheckDomainDns = document.getElementById('btnCheckDomainDns');
    const customDomainInput = document.getElementById('customDomainInput');
    const dnsCheckResultBox = document.getElementById('dnsCheckResultBox');

    if (btnVerifyDomain && customDomainInput && dnsCheckResultBox) {
        btnVerifyDomain.addEventListener('click', async () => {
            const domain = customDomainInput.value.trim();
            if (!domain) {
                dnsCheckResultBox.style.display = 'block';
                dnsCheckResultBox.style.background = 'rgba(239, 68, 68, 0.15)';
                dnsCheckResultBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
                dnsCheckResultBox.style.color = '#ef4444';
                dnsCheckResultBox.textContent = 'Please enter the domain address.';
                return;
            }

            btnVerifyDomain.disabled = true;
            btnVerifyDomain.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifying...';

            try {
                const res = await fetch('{{ route("admin.settings.domain.verify") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ domain }),
                });

                const data = await res.json();
                dnsCheckResultBox.style.display = 'block';

                if (data.verified) {
                    dnsCheckResultBox.style.background = 'rgba(16, 185, 129, 0.15)';
                    dnsCheckResultBox.style.border = '1px solid rgba(16, 185, 129, 0.3)';
                    dnsCheckResultBox.style.color = '#10b981';
                } else {
                    dnsCheckResultBox.style.background = 'rgba(245, 158, 11, 0.15)';
                    dnsCheckResultBox.style.border = '1px solid rgba(245, 158, 11, 0.3)';
                    dnsCheckResultBox.style.color = '#f59e0b';
                }
                dnsCheckResultBox.textContent = data.message;
            } catch (err) {
                dnsCheckResultBox.style.display = 'block';
                dnsCheckResultBox.style.background = 'rgba(239, 68, 68, 0.15)';
                dnsCheckResultBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
                dnsCheckResultBox.style.color = '#ef4444';
                dnsCheckResultBox.textContent = 'An error occurred during verification. Please try again.';
            } finally {
                btnVerifyDomain.disabled = false;
                btnVerifyDomain.innerHTML = '<i class="fa-solid fa-shield-check"></i> Verify Ownership';
            }
        });
    }

    if (btnCheckDomainDns && customDomainInput && dnsCheckResultBox) {
        btnCheckDomainDns.addEventListener('click', async () => {
            const domain = customDomainInput.value.trim();
            if (!domain) {
                dnsCheckResultBox.style.display = 'block';
                dnsCheckResultBox.style.background = 'rgba(239, 68, 68, 0.15)';
                dnsCheckResultBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
                dnsCheckResultBox.style.color = '#ef4444';
                dnsCheckResultBox.textContent = 'Please enter the domain address.';
                return;
            }

            btnCheckDomainDns.disabled = true;
            btnCheckDomainDns.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking...';

            try {
                const res = await fetch('{{ route("admin.settings.domain.check") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ domain }),
                });

                const data = await res.json();
                dnsCheckResultBox.style.display = 'block';

                if (data.is_pointing) {
                    dnsCheckResultBox.style.background = 'rgba(16, 185, 129, 0.15)';
                    dnsCheckResultBox.style.border = '1px solid rgba(16, 185, 129, 0.3)';
                    dnsCheckResultBox.style.color = '#10b981';
                } else {
                    dnsCheckResultBox.style.background = 'rgba(245, 158, 11, 0.15)';
                    dnsCheckResultBox.style.border = '1px solid rgba(245, 158, 11, 0.3)';
                    dnsCheckResultBox.style.color = '#f59e0b';
                }
                dnsCheckResultBox.textContent = data.message;
            } catch (err) {
                dnsCheckResultBox.style.display = 'block';
                dnsCheckResultBox.style.background = 'rgba(239, 68, 68, 0.15)';
                dnsCheckResultBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
                dnsCheckResultBox.style.color = '#ef4444';
                dnsCheckResultBox.textContent = 'An error occurred during verification. Please try again.';
            } finally {
                btnCheckDomainDns.disabled = false;
                btnCheckDomainDns.innerHTML = '<i class="fa-solid fa-bolt"></i> Check DNS';
            }
        });
    }
});

function toggleTelegramContainer() {
    const toggle = document.getElementById('telegramToggle');
    const container = document.getElementById('telegramContainer');
    if (!toggle || !container) return;
    if (toggle.checked) {
        container.style.opacity = '1';
        container.style.pointerEvents = 'auto';
    } else {
        container.style.opacity = '0.55';
        container.style.pointerEvents = 'none';
    }
}

function testTelegramConnection() {
    const btn = document.getElementById('btnTestTelegram');
    const resultBox = document.getElementById('telegramTestResult');
    const chatId = document.getElementById('telegramChatId')?.value;
    const botToken = document.getElementById('telegramBotToken')?.value;
    const topicId = document.getElementById('telegramTopicId')?.value;

    if (!chatId || !chatId.trim()) {
        resultBox.style.display = 'block';
        resultBox.style.background = 'rgba(239, 68, 68, 0.12)';
        resultBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
        resultBox.style.color = '#ef4444';
        resultBox.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Please fill in the Telegram Chat ID field.';
        return;
    }

    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';
    resultBox.style.display = 'none';

    fetch('{{ route("admin.settings.telegram.test") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            chat_id: chatId.trim(),
            bot_token: botToken ? botToken.trim() : null,
            topic_id: topicId ? topicId.trim() : null,
        }),
    })
    .then(res => res.json())
    .then(data => {
        resultBox.style.display = 'block';
        if (data.success) {
            resultBox.style.background = 'rgba(16, 185, 129, 0.12)';
            resultBox.style.border = '1px solid rgba(16, 185, 129, 0.3)';
            resultBox.style.color = '#10b981';
            resultBox.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + (data.message || 'Test message sent successfully.');
        } else {
            resultBox.style.background = 'rgba(239, 68, 68, 0.12)';
            resultBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
            resultBox.style.color = '#ef4444';
            resultBox.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> ' + (data.message || 'Error: failed to send message.');
        }
    })
    .catch(err => {
        resultBox.style.display = 'block';
        resultBox.style.background = 'rgba(239, 68, 68, 0.12)';
        resultBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
        resultBox.style.color = '#ef4444';
        resultBox.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Connection error: ' + err.message;
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
}

function toggleScheduleBlock(channel) {
    const el = document.getElementById(channel + 'ScheduleFields');
    const cb = document.getElementById(channel + 'ScheduleToggle');
    if (el && cb) {
        el.style.display = cb.checked ? 'block' : 'none';
    }
}

function setChannelDays(channel, preset) {
    const checkboxes = document.querySelectorAll('.' + channel + '_day_cb');
    const weekdays = ['mon', 'tue', 'wed', 'thu', 'fri'];
    const weekends = ['sat', 'sun'];

    checkboxes.forEach(cb => {
        if (preset === 'all') {
            cb.checked = true;
        } else if (preset === 'weekdays') {
            cb.checked = weekdays.includes(cb.value);
        } else if (preset === 'weekends') {
            cb.checked = weekends.includes(cb.value);
        }
    });
}

function updateReceiptLivePreview() {
    const container = document.getElementById('receiptSlipContainer');
    if (!container) return;

    const widthSelect = document.getElementById('receiptPaperWidthSelect');
    const widthBadge = document.getElementById('previewWidthBadge');
    if (widthSelect && widthBadge) {
        if (widthSelect.value === '58mm') {
            container.style.maxWidth = '290px';
            container.style.padding = '16px 14px 22px';
            widthBadge.innerText = '58 mm';
        } else {
            container.style.maxWidth = '380px';
            container.style.padding = '24px 20px 28px';
            widthBadge.innerText = '80 mm';
        }
    }

    const styleSelect = document.getElementById('receiptDesignStyleSelect');
    if (styleSelect) {
        if (styleSelect.value === 'classic') {
            container.style.fontFamily = 'monospace, "Courier New", Courier';
            container.style.background = '#fafafa';
        } else {
            container.style.fontFamily = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
            container.style.background = '#ffffff';
        }
    }

    const titleInput = document.getElementById('receiptHeaderTitleInput');
    const previewTitle = document.getElementById('previewTitle');
    if (titleInput && previewTitle) {
        previewTitle.innerText = titleInput.value.trim() || 'Restaurant Name';
    }

    const subtitleInput = document.getElementById('receiptHeaderSubtitleInput');
    const previewSubtitle = document.getElementById('previewSubtitle');
    if (subtitleInput && previewSubtitle) {
        previewSubtitle.innerText = subtitleInput.value.trim();
        previewSubtitle.style.display = subtitleInput.value.trim() ? 'block' : 'none';
    }

    const footerInput = document.getElementById('receiptFooterTextInput');
    const previewFooter = document.getElementById('previewFooter');
    if (footerInput && previewFooter) {
        previewFooter.innerText = footerInput.value.trim();
        previewFooter.style.display = footerInput.value.trim() ? 'block' : 'none';
    }

    const toggleLogo = document.getElementById('toggleShowLogo');
    const previewLogo = document.getElementById('previewLogo');
    if (toggleLogo && previewLogo) {
        previewLogo.style.display = toggleLogo.checked ? 'block' : 'none';
    }

    const toggleCust = document.getElementById('toggleCustomerInfo');
    const previewCust = document.getElementById('previewCustomerRow');
    if (toggleCust && previewCust) {
        previewCust.style.display = toggleCust.checked ? 'flex' : 'none';
    }

    const toggleOrdType = document.getElementById('toggleOrderType');
    const previewOrdType = document.getElementById('previewOrderTypeRow');
    if (toggleOrdType && previewOrdType) {
        previewOrdType.style.display = toggleOrdType.checked ? 'flex' : 'none';
    }

    const togglePayment = document.getElementById('togglePaymentDetails');
    const previewPayment = document.getElementById('previewPaymentRow');
    if (togglePayment && previewPayment) {
        previewPayment.style.display = togglePayment.checked ? 'flex' : 'none';
    }

    const togglePrices = document.getElementById('togglePrices');
    const priceCols = document.querySelectorAll('.previewPriceCol');
    if (togglePrices) {
        priceCols.forEach(col => {
            col.style.display = togglePrices.checked ? (col.tagName === 'DIV' ? 'flex' : '') : 'none';
        });
    }

    const toggleNotes = document.getElementById('toggleItemNotes');
    const previewNotes = document.getElementById('previewItemNotes');
    if (toggleNotes && previewNotes) {
        previewNotes.style.display = toggleNotes.checked ? 'block' : 'none';
    }

    const toggleFee = document.getElementById('toggleFeeBreakdown');
    const previewFee = document.getElementById('previewFeeBreakdown');
    if (toggleFee && previewFee) {
        previewFee.style.display = toggleFee.checked ? 'block' : 'none';
    }

    const toggleQr = document.getElementById('toggleQrCode');
    const previewQr = document.getElementById('previewQrBlock');
    if (toggleQr && previewQr) {
        previewQr.style.display = toggleQr.checked ? 'block' : 'none';
    }
}

function testPrinterConnection() {
    const ip = document.getElementById('printerIpInput').value.trim();
    const port = document.getElementById('printerPortInput').value.trim() || 9100;
    const btn = document.getElementById('testPrinterBtn');
    const resultBox = document.getElementById('printerTestResult');

    if (!ip) {
        alert('{{ __("Խնդրում ենք նշել տպիչի IP հասցեն:") }}');
        document.getElementById('printerIpInput').focus();
        return;
    }

    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> {{ __("Ստուգում...") }}';
    resultBox.style.display = 'none';

    fetch('{{ route("admin.settings.printer.test") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ ip: ip, port: port })
    })
    .then(res => res.json())
    .then(data => {
        resultBox.style.display = 'block';
        if (data.success) {
            resultBox.style.background = 'rgba(16, 185, 129, 0.12)';
            resultBox.style.border = '1px solid rgba(16, 185, 129, 0.3)';
            resultBox.style.color = '#10b981';
            resultBox.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + (data.message || '{{ __("Կապը հաջողությամբ հաստատվեց:") }}');
        } else {
            resultBox.style.background = 'rgba(239, 68, 68, 0.12)';
            resultBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
            resultBox.style.color = '#ef4444';
            resultBox.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> ' + (data.message || '{{ __("Չհաջողվեց միանալ տպիչին:") }}');
        }
    })
    .catch(err => {
        resultBox.style.display = 'block';
        resultBox.style.background = 'rgba(239, 68, 68, 0.12)';
        resultBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
        resultBox.style.color = '#ef4444';
        resultBox.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Սխալ: ' + err.message;
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
    });
}

document.addEventListener('DOMContentLoaded', function() {
    updateReceiptLivePreview();
});

</script>

@endsection
