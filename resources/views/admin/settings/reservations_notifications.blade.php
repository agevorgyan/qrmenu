@extends('layouts.app')

@section('title', __('Reservations & Pre-Orders Notifications') . ' - ' . $vendor->name)

@section('content')
<div style="max-width: 1100px; margin: 0 auto; width: 100%; box-sizing: border-box; padding-bottom: 3rem;">
    <!-- Breadcrumb & Header -->
    <div style="margin-bottom: 1.75rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem; flex-wrap: wrap;">
            <a href="{{ route('admin.settings.index') }}" style="color: var(--text-muted); text-decoration: none;">{{ __('Settings') }}</a>
            <i class="fa-solid fa-chevron-right" style="font-size: 0.7rem;"></i>
            <span>{{ __('Reservations & Pre-Orders') }}</span>
            <i class="fa-solid fa-chevron-right" style="font-size: 0.7rem;"></i>
            <span style="color: var(--primary); font-weight: 700;">{{ __('Notifications') }}</span>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.75rem;">
                    <span style="background: rgba(245, 158, 11, 0.15); color: var(--primary); width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="fa-solid fa-bell"></i>
                    </span>
                    <span>{{ __('Reservation & Pre-Order Notifications') }}</span>
                </h1>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0 0;">
                    {{ __('Configure customer confirmations, kitchen alerts, upcoming arrival reminders, and language templates') }}
                </p>
            </div>

            <div style="display: flex; gap: 0.75rem; align-items: center;">
                <a href="{{ route('admin.settings.index') }}" class="btn btn-secondary" style="border-radius: 12px; font-weight: 600; font-size: 0.88rem; text-decoration: none;">
                    <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Settings') }}
                </a>
                <button type="submit" form="notificationsForm" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 700; padding: 0.65rem 1.4rem; font-size: 0.92rem; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.3);">
                    <i class="fa-solid fa-floppy-disk"></i> {{ __('Save Settings') }}
                </button>
            </div>
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if(session('success'))
        <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; color: #10b981; font-weight: 600; display: flex; align-items: center; gap: 0.6rem;">
            <i class="fa-solid fa-circle-check"></i> {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; color: #ef4444;">
            <div style="font-weight: 700; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                <i class="fa-solid fa-circle-exclamation"></i> {{ __('Please correct the following errors:') }}
            </div>
            <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.88rem;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Super Admin Restrictions Notice (If any restriction is active) -->
    @php
        $hasRestrictions = !($restrictions['reservations_enabled'] ?? true)
            || !($restrictions['preorders_enabled'] ?? true)
            || !($restrictions['reservation_emails_enabled'] ?? true)
            || !($restrictions['preorder_emails_enabled'] ?? true)
            || !($restrictions['customer_reminders_enabled'] ?? true)
            || !($restrictions['restaurant_reminders_enabled'] ?? true);
    @endphp

    @if($hasRestrictions)
        <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 16px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; color: #f59e0b; display: flex; align-items: flex-start; gap: 0.85rem;">
            <i class="fa-solid fa-shield-halved" style="font-size: 1.3rem; margin-top: 0.15rem; flex-shrink: 0;"></i>
            <div>
                <div style="font-weight: 800; font-size: 0.95rem;">{{ __('Super Admin Policy Notice') }}</div>
                <div style="font-size: 0.85rem; margin-top: 0.25rem; color: var(--text-muted);">
                    {{ __('Some notification features are centrally regulated by the platform administrator. Restricted options will be automatically bypassed during dispatch.') }}
                </div>
            </div>
        </div>
    @endif

    <form action="{{ route('admin.settings.reservations.notifications.update') }}" method="POST" id="notificationsForm">
        @csrf

        <div style="display: grid; grid-template-columns: 1fr; gap: 1.75rem;">

            <!-- 1. CUSTOMER NOTIFICATIONS CARD -->
            <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.9rem;">
                        <span style="width: 44px; height: 44px; border-radius: 12px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                            <i class="fa-solid fa-envelope-open-text"></i>
                        </span>
                        <div>
                            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                {{ __('Customer Email Notifications') }}
                            </h3>
                            <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-muted);">
                                {{ __('Guests receive notifications in their chosen language (Armenian, English, or Russian)') }}
                            </p>
                        </div>
                    </div>

                    <label class="switch">
                        <input type="checkbox" name="customer_notifications_enabled" value="1" {{ ($settings['customer_notifications_enabled'] ?? true) ? 'checked' : '' }} id="customerNotifyToggle" onchange="toggleContainer('customerContainer', this.checked)">
                        <span class="slider round"></span>
                    </label>
                </div>

                <div id="customerContainer" style="{{ !($settings['customer_notifications_enabled'] ?? true) ? 'opacity: 0.5; pointer-events: none;' : '' }}; transition: opacity 0.2s ease;">
                    <!-- Events List -->
                    <div style="font-size: 0.88rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.85rem;">
                        {{ __('Select Active Customer Triggers:') }}
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr)); gap: 0.85rem; margin-bottom: 1.5rem;">
                        @foreach($customerEvents as $eventKey => $meta)
                            @php
                                $isChecked = in_array($eventKey, $settings['customer_events'] ?? [], true);
                            @endphp
                            <label style="display: flex; align-items: flex-start; gap: 0.75rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.85rem; cursor: pointer; transition: border-color 0.2s;">
                                <input type="checkbox" name="customer_events[]" value="{{ $eventKey }}" {{ $isChecked ? 'checked' : '' }} style="margin-top: 0.25rem; accent-color: var(--primary);">
                                <div style="min-width: 0;">
                                    <div style="font-size: 0.88rem; font-weight: 700; color: var(--text-main);">{{ __($meta['name']) }}</div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">{{ __($meta['desc']) }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>

                    <!-- Customer Arrival Reminders Sub-section -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
                            <div style="display: flex; align-items: center; gap: 0.65rem;">
                                <i class="fa-solid fa-stopwatch" style="color: #f59e0b; font-size: 1.1rem;"></i>
                                <span style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);">
                                    {{ __('Automatic Customer Arrival Reminders') }}
                                </span>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="customer_reminder_enabled" value="1" {{ ($settings['customer_reminder_enabled'] ?? true) ? 'checked' : '' }}>
                                <span class="slider round"></span>
                            </label>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr)); gap: 1rem;">
                            <div>
                                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                                    {{ __('Reminder Timing (Minutes before arrival)') }}
                                </label>
                                <input type="text" name="customer_reminder_intervals" value="{{ implode(', ', $settings['customer_reminder_intervals'] ?? [1440, 180]) }}" placeholder="e.g. 1440, 180 (24h, 3h)" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.9rem; font-weight: 700;">
                                <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; display: block;">
                                    {{ __('1440 = 24 hours, 180 = 3 hours, 60 = 1 hour before arrival. Multiple intervals supported.') }}
                                </span>
                            </div>

                            <div style="display: flex; align-items: center;">
                                <label style="display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 600; color: var(--text-main); cursor: pointer;">
                                    <input type="checkbox" name="customer_reminder_only_confirmed" value="1" {{ ($settings['customer_reminder_only_confirmed'] ?? true) ? 'checked' : '' }} style="accent-color: var(--primary);">
                                    <span>{{ __('Send reminders only for confirmed reservations') }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. RESTAURANT NOTIFICATIONS CARD -->
            <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.9rem;">
                        <span style="width: 44px; height: 44px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                            <i class="fa-solid fa-utensils"></i>
                        </span>
                        <div>
                            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                {{ __('Restaurant & Staff Notifications') }}
                            </h3>
                            <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-muted);">
                                {{ __('Alerts dispatched to managers, hostesses, and kitchen staff in the restaurant admin language') }}
                            </p>
                        </div>
                    </div>

                    <label class="switch">
                        <input type="checkbox" name="restaurant_notifications_enabled" value="1" {{ ($settings['restaurant_notifications_enabled'] ?? true) ? 'checked' : '' }} id="restaurantNotifyToggle" onchange="toggleContainer('restaurantContainer', this.checked)">
                        <span class="slider round"></span>
                    </label>
                </div>

                <div id="restaurantContainer" style="{{ !($settings['restaurant_notifications_enabled'] ?? true) ? 'opacity: 0.5; pointer-events: none;' : '' }}; transition: opacity 0.2s ease;">
                    <!-- Recipients Input -->
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                            {{ __('Authorized Notification Recipient Addresses') }} <span style="color: #ef4444;">*</span>
                        </label>
                        <textarea name="restaurant_recipients" rows="2" placeholder="manager@restaurant.am, host@restaurant.am" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem; font-size: 0.92rem; font-family: monospace; font-weight: 600;">{{ implode(', ', $settings['restaurant_recipients'] ?? ($vendor->email ? [$vendor->email] : [])) }}</textarea>
                        <span style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
                            {{ __('Separate multiple email addresses with commas or line breaks. Every address is strictly validated on save.') }}
                        </span>
                    </div>

                    <!-- Staff Events List -->
                    <div style="font-size: 0.88rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.85rem;">
                        {{ __('Select Operational Triggers for Restaurant Staff:') }}
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr)); gap: 0.85rem; margin-bottom: 1.5rem;">
                        @foreach($restaurantEvents as $eventKey => $meta)
                            @php
                                $isChecked = in_array($eventKey, $settings['restaurant_events'] ?? [], true);
                            @endphp
                            <label style="display: flex; align-items: flex-start; gap: 0.75rem; background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.85rem; cursor: pointer; transition: border-color 0.2s;">
                                <input type="checkbox" name="restaurant_events[]" value="{{ $eventKey }}" {{ $isChecked ? 'checked' : '' }} style="margin-top: 0.25rem; accent-color: var(--primary);">
                                <div style="min-width: 0;">
                                    <div style="font-size: 0.88rem; font-weight: 700; color: var(--text-main);">{{ __($meta['name']) }}</div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">{{ __($meta['desc']) }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>

                    <!-- Staff Reminders Box -->
                    <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
                            <div style="display: flex; align-items: center; gap: 0.65rem;">
                                <i class="fa-solid fa-bell-concierge" style="color: #10b981; font-size: 1.1rem;"></i>
                                <span style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);">
                                    {{ __('Staff Shift Arrival Reminders') }}
                                </span>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="restaurant_reminder_enabled" value="1" {{ ($settings['restaurant_reminder_enabled'] ?? true) ? 'checked' : '' }}>
                                <span class="slider round"></span>
                            </label>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr)); gap: 1rem;">
                            <div>
                                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                                    {{ __('Staff Timing (Minutes before arrival)') }}
                                </label>
                                <input type="text" name="restaurant_reminder_intervals" value="{{ implode(', ', $settings['restaurant_reminder_intervals'] ?? [180, 60]) }}" placeholder="e.g. 180, 60 (3h, 1h)" class="form-control" style="width: 100%; box-sizing: border-box; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.9rem; font-weight: 700;">
                                <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; display: block;">
                                    {{ __('Minutes before guest arrival to send alert to floor/kitchen staff.') }}
                                </span>
                            </div>

                            <div style="display: flex; align-items: center;">
                                <label style="display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 600; color: var(--text-main); cursor: pointer;">
                                    <input type="checkbox" name="restaurant_reminder_only_confirmed" value="1" {{ ($settings['restaurant_reminder_only_confirmed'] ?? true) ? 'checked' : '' }} style="accent-color: var(--primary);">
                                    <span>{{ __('Only alert staff for confirmed reservations') }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. TEMPLATE PREVIEW & DIAGNOSTIC TEST EMAIL CARD -->
            <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);" x-data="templatePreview()">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.9rem;">
                        <span style="width: 44px; height: 44px; border-radius: 12px; background: rgba(139, 92, 246, 0.15); color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                            <i class="fa-solid fa-eye"></i>
                        </span>
                        <div>
                            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                {{ __('Template Inspector & Test Email Dispatch') }}
                            </h3>
                            <p style="margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-muted);">
                                {{ __('Inspect live rendered templates across Armenian, English, and Russian; test your mail deliverability') }}
                            </p>
                        </div>
                    </div>

                    <!-- Language Switcher for Preview -->
                    <div style="display: flex; gap: 0.4rem; background: var(--bg-body); padding: 0.3rem; border-radius: 10px; border: 1px solid var(--border-color);">
                        <button type="button" class="btn" :class="{ 'btn-primary': locale === 'hy', 'btn-secondary': locale !== 'hy' }" style="padding: 0.35rem 0.75rem; font-size: 0.8rem; border-radius: 8px; font-weight: 700;" @click="setLocale('hy')">
                            🇦🇲 Հայերեն
                        </button>
                        <button type="button" class="btn" :class="{ 'btn-primary': locale === 'en', 'btn-secondary': locale !== 'en' }" style="padding: 0.35rem 0.75rem; font-size: 0.8rem; border-radius: 8px; font-weight: 700;" @click="setLocale('en')">
                            🇬🇧 English
                        </button>
                        <button type="button" class="btn" :class="{ 'btn-primary': locale === 'ru', 'btn-secondary': locale !== 'ru' }" style="padding: 0.35rem 0.75rem; font-size: 0.8rem; border-radius: 8px; font-weight: 700;" @click="setLocale('ru')">
                            🇷🇺 Русский
                        </button>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                            {{ __('Select Notification Template to Preview') }}
                        </label>
                        <select class="form-control" x-model="selectedKey" @change="loadPreview()" style="width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.9rem; font-weight: 700;">
                            <optgroup label="{{ __('Customer Templates') }}">
                                <option value="reservation_request_received">{{ __('Reservation Request Received') }}</option>
                                <option value="reservation_confirmed">{{ __('Reservation Confirmed') }}</option>
                                <option value="reservation_rejected">{{ __('Reservation Declined') }}</option>
                                <option value="reservation_modified">{{ __('Reservation Modified') }}</option>
                                <option value="reservation_cancelled">{{ __('Reservation Cancelled') }}</option>
                                <option value="reservation_reminder">{{ __('Customer Arrival Reminder') }}</option>
                                <option value="preorder_received">{{ __('Pre-Order Received') }}</option>
                                <option value="preorder_confirmed">{{ __('Pre-Order Confirmed') }}</option>
                                <option value="preorder_status_changed">{{ __('Pre-Order Status Changed') }}</option>
                                <option value="preorder_cancelled">{{ __('Pre-Order Cancelled') }}</option>
                            </optgroup>
                            <optgroup label="{{ __('Restaurant Staff Templates') }}">
                                <option value="restaurant_reservation_received">{{ __('New Reservation (Staff)') }}</option>
                                <option value="restaurant_reservation_awaiting_confirmation">{{ __('Awaiting Confirmation (Staff)') }}</option>
                                <option value="restaurant_reservation_action">{{ __('Customer Action (Staff)') }}</option>
                                <option value="restaurant_reservation_modified">{{ __('Reservation Modified (Staff)') }}</option>
                                <option value="restaurant_preorder_received">{{ __('New Pre-Order (Staff)') }}</option>
                                <option value="restaurant_preorder_updated">{{ __('Pre-Order Updated (Staff)') }}</option>
                                <option value="restaurant_reservation_reminder">{{ __('Shift Reminder (Staff)') }}</option>
                            </optgroup>
                        </select>
                    </div>

                    <!-- Send Authorized Diagnostic Test Email -->
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                            {{ __('Send Diagnostic Test Notification') }}
                        </label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="email" x-model="testEmail" placeholder="test@restaurant.am" class="form-control" style="flex: 1; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 10px; padding: 0.65rem 0.85rem; font-size: 0.9rem;">
                            <button type="button" @click="sendTest()" class="btn btn-secondary" :disabled="sendingTest" style="border-radius: 10px; font-weight: 700; white-space: nowrap;">
                                <span x-show="!sendingTest"><i class="fa-solid fa-paper-plane"></i> {{ __('Send Test') }}</span>
                                <span x-show="sendingTest"><i class="fa-solid fa-spinner fa-spin"></i> {{ __('Sending...') }}</span>
                            </button>
                        </div>
                        <div x-show="testMessage" :style="{ color: testSuccess ? '#10b981' : '#ef4444' }" style="font-size: 0.8rem; margin-top: 0.35rem; font-weight: 600;" x-text="testMessage"></div>
                    </div>
                </div>

                <!-- Preview Display Box -->
                <div style="background: var(--bg-body); border: 1.5px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
                    <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem; margin-bottom: 1rem;">
                        <div style="font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Subject Line') }}</div>
                        <div style="font-size: 1rem; font-weight: 800; color: var(--text-main); margin-top: 0.2rem;" x-text="previewData.subject || 'Loading subject...'"></div>
                    </div>

                    <div>
                        <div style="font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 0.35rem;">{{ __('Message Intro') }}</div>
                        <div style="font-size: 0.92rem; line-height: 1.6; color: var(--text-main); white-space: pre-line;" x-text="previewData.intro || 'Loading body...'"></div>
                    </div>

                    <div x-show="previewData.cta_text" style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px dashed var(--border-color);">
                        <span style="font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Call to Action') }}:</span>
                        <span class="badge badge-primary" style="margin-left: 0.5rem; font-weight: 700;" x-text="previewData.cta_text"></span>
                    </div>
                </div>

                <!-- Template Variables Reference Accordion -->
                <div style="margin-top: 1.25rem;">
                    <div style="font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem;">
                        <i class="fa-solid fa-code"></i> {{ __('Supported Interpolation Variables:') }}
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.4rem; font-family: monospace; font-size: 0.78rem;">
                        <span class="badge" style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--primary);">@{{customer_name}}</span>
                        <span class="badge" style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--primary);">@{{restaurant_name}}</span>
                        <span class="badge" style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--primary);">@{{reservation_reference}}</span>
                        <span class="badge" style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--primary);">@{{reservation_date}}</span>
                        <span class="badge" style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--primary);">@{{reservation_time}}</span>
                        <span class="badge" style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--primary);">@{{guest_count}}</span>
                        <span class="badge" style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--primary);">@{{table_name}}</span>
                        <span class="badge" style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--primary);">@{{reservation_status}}</span>
                        <span class="badge" style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--primary);">@{{preorder_summary}}</span>
                        <span class="badge" style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--primary);">@{{reservation_total}}</span>
                        <span class="badge" style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--primary);">@{{restaurant_address}}</span>
                        <span class="badge" style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--primary);">@{{restaurant_phone}}</span>
                        <span class="badge" style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--primary);">@{{cancellation_policy}}</span>
                    </div>
                </div>
            </div>

            <!-- 4. RECENT NOTIFICATION DELIVERY LOGS CARD -->
            <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <span style="width: 38px; height: 38px; border-radius: 10px; background: rgba(148, 163, 184, 0.15); color: var(--text-muted); display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </span>
                        <div>
                            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                {{ __('Recent Delivery Logs & Outcomes') }}
                            </h3>
                            <p style="margin: 0.15rem 0 0; font-size: 0.8rem; color: var(--text-muted);">
                                {{ __('Auditable log of outgoing reservation confirmations, pre-orders, and automated reminders') }}
                            </p>
                        </div>
                    </div>
                </div>

                @if($logs->isEmpty())
                    <div style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
                        <i class="fa-solid fa-inbox" style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 0.75rem;"></i>
                        <div style="font-weight: 700; font-size: 0.95rem;">{{ __('No reservation notifications sent yet') }}</div>
                        <div style="font-size: 0.82rem; margin-top: 0.25rem;">{{ __('Outgoing emails will be tracked and displayed here with verified delivery states.') }}</div>
                    </div>
                @else
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
                            <thead>
                                <tr style="border-bottom: 1.5px solid var(--border-color); color: var(--text-muted);">
                                    <th style="padding: 0.75rem 0.5rem; font-weight: 700;">{{ __('Status') }}</th>
                                    <th style="padding: 0.75rem 0.5rem; font-weight: 700;">{{ __('Recipient') }}</th>
                                    <th style="padding: 0.75rem 0.5rem; font-weight: 700;">{{ __('Subject') }}</th>
                                    <th style="padding: 0.75rem 0.5rem; font-weight: 700;">{{ __('Event') }}</th>
                                    <th style="padding: 0.75rem 0.5rem; font-weight: 700;">{{ __('Sent At') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($logs as $log)
                                    @php $badge = $log->getStatusBadge(); @endphp
                                    <tr style="border-bottom: 1px solid var(--border-color-light);">
                                        <td style="padding: 0.75rem 0.5rem;">
                                            <span class="badge {{ $badge['class'] }}" style="font-size: 0.75rem; font-weight: 700;">
                                                <i class="fa-solid {{ $badge['icon'] }}"></i> {{ $badge['label'] }}
                                            </span>
                                        </td>
                                        <td style="padding: 0.75rem 0.5rem; font-family: monospace; font-size: 0.82rem; color: var(--text-main);">
                                            {{ $log->recipient_email }}
                                        </td>
                                        <td style="padding: 0.75rem 0.5rem; font-weight: 600; color: var(--text-main); max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            {{ $log->subject }}
                                        </td>
                                        <td style="padding: 0.75rem 0.5rem; color: var(--text-muted);">
                                            {{ $log->metadata['event'] ?? $log->category }}
                                        </td>
                                        <td style="padding: 0.75rem 0.5rem; color: var(--text-muted); font-size: 0.8rem; white-space: nowrap;">
                                            {{ $log->created_at->format('Y-m-d H:i') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </form>
</div>

<script>
    function toggleContainer(containerId, isChecked) {
        const el = document.getElementById(containerId);
        if (el) {
            el.style.opacity = isChecked ? '1' : '0.5';
            el.style.pointerEvents = isChecked ? 'auto' : 'none';
        }
    }

    function templatePreview() {
        return {
            selectedKey: 'reservation_confirmed',
            locale: '{{ app()->getLocale() ?: "hy" }}',
            testEmail: '{{ $vendor->email ?? "" }}',
            sendingTest: false,
            testSuccess: false,
            testMessage: '',
            previewData: {
                subject: '',
                intro: '',
                cta_text: '',
            },
            init() {
                this.loadPreview();
            },
            setLocale(loc) {
                this.locale = loc;
                this.loadPreview();
            },
            async loadPreview() {
                try {
                    const res = await fetch(`{{ route('admin.settings.reservations.notifications.preview') }}?key=${encodeURIComponent(this.selectedKey)}&locale=${encodeURIComponent(this.locale)}`);
                    const data = await res.json();
                    if (data.success) {
                        this.previewData = data.template;
                    }
                } catch (e) {
                    console.error('Preview error', e);
                }
            },
            async sendTest() {
                if (!this.testEmail) {
                    alert('{{ __("Please enter a recipient email address.") }}');
                    return;
                }
                this.sendingTest = true;
                this.testMessage = '';
                try {
                    const res = await fetch(`{{ route('admin.settings.reservations.notifications.test') }}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            recipient: this.testEmail,
                            event: 'received'
                        })
                    });
                    const data = await res.json();
                    this.testSuccess = data.success;
                    this.testMessage = data.message;
                } catch (e) {
                    this.testSuccess = false;
                    this.testMessage = '{{ __("Network error sending test email.") }}';
                } finally {
                    this.sendingTest = false;
                }
            }
        };
    }
</script>
@endsection
