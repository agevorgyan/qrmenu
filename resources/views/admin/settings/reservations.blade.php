@extends('layouts.app')

@section('title', __('Reservation Rules & Capacity Settings') . ' — ' . $vendor->name)

@section('content')
<div style="max-width: 960px; margin: 0 auto; width: 100%; box-sizing: border-box; padding-bottom: 3rem;">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.75rem;">
        <div>
            <h1 style="font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.65rem;">
                <span style="background: rgba(139, 92, 246, 0.12); color: #8b5cf6; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-sliders"></i>
                </span>
                <span>{{ __('Reservation Rules & Capacity Settings') }}</span>
            </h1>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">
                {{ __('Define booking duration, operating hours, advance window, party size limits, and pre-order policies.') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.reservations.index') }}" class="btn btn-secondary">
                <i class="fa-solid fa-calendar-check" style="color: #ec4899;"></i> {{ __('Table Reservations') }}
            </a>
            <a href="{{ route('admin.settings.reservations.notifications') }}" class="btn btn-secondary">
                <i class="fa-solid fa-envelope" style="color: #f59e0b;"></i> {{ __('Email Notifications') }}
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success" style="padding: 1rem 1.25rem; border-radius: 14px; margin-bottom: 1.5rem; background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981; font-weight: 600; display: flex; align-items: center; gap: 0.6rem;">
            <i class="fa-solid fa-circle-check"></i> {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger" style="padding: 1rem 1.25rem; border-radius: 14px; margin-bottom: 1.5rem; background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444;">
            <div style="font-weight: 700; margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-triangle-exclamation"></i> {{ __('Please correct the following errors:') }}
            </div>
            <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.88rem;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $resAllowed = !in_array($reservationDisabledReason ?? null, ['subscription', 'superadmin'], true);
        $preAllowed = $resAllowed && !in_array($preOrderDisabledReason ?? null, ['subscription', 'superadmin'], true);
        $currentMode = $settings['mode'] ?? ($settings['enabled'] ? ($settings['pre_orders_mode'] === 'disabled' ? 'reservation_only' : 'reservation_and_preorder') : 'disabled');
    @endphp

    <form method="POST" action="{{ route('admin.settings.reservations.update') }}">
        @csrf

        <!-- Feature Mode & Entitlements Card -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem; border-left: 4px solid var(--primary, #8b5cf6);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem;">
                <div>
                    <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-cubes" style="color: #6366f1;"></i>
                        <span>{{ __('Operating Mode & Feature Status') }}</span>
                    </h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                        {{ __('Select how table reservations and pre-orders operate on your public storefront.') }}
                    </p>
                </div>
            </div>

            <!-- Restriction Notices -->
            @if(($reservationDisabledReason ?? null) === 'subscription')
                <div style="padding: 0.85rem 1rem; border-radius: 12px; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; font-size: 0.88rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-lock" style="font-size: 1.1rem; color: #d97706;"></i>
                    <div>
                        <strong>{{ __('Subscription Plan Restriction:') }}</strong>
                        {{ __('Table reservations are not included in your active subscription plan. Please upgrade your plan to unlock reservations.') }}
                    </div>
                </div>
            @elseif(($reservationDisabledReason ?? null) === 'superadmin')
                <div style="padding: 0.85rem 1rem; border-radius: 12px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; font-size: 0.88rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-shield-halved" style="font-size: 1.1rem; color: #dc2626;"></i>
                    <div>
                        <strong>{{ __('Super Admin Restriction:') }}</strong>
                        {{ __('Table reservations are disabled by the platform Super Admin. This feature cannot be enabled from the partner panel.') }}
                    </div>
                </div>
            @endif

            @if($resAllowed && ($preOrderDisabledReason ?? null) === 'subscription')
                <div style="padding: 0.85rem 1rem; border-radius: 12px; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; font-size: 0.88rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-circle-info" style="font-size: 1.1rem; color: #3b82f6;"></i>
                    <div>
                        <strong>{{ __('Subscription Plan Notice:') }}</strong>
                        {{ __('Menu pre-orders with reservations are not included in your plan. You can operate in Reservation Only mode.') }}
                    </div>
                </div>
            @elseif($resAllowed && ($preOrderDisabledReason ?? null) === 'superadmin')
                <div style="padding: 0.85rem 1rem; border-radius: 12px; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; font-size: 0.88rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-shield-halved" style="font-size: 1.1rem; color: #d97706;"></i>
                    <div>
                        <strong>{{ __('Super Admin Notice:') }}</strong>
                        {{ __('Pre-orders are disabled for your restaurant by platform Super Admin. You can operate in Reservation Only mode.') }}
                    </div>
                </div>
            @endif

            <!-- Mode Selector Radio Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 1rem;">
                <!-- 1. Disabled -->
                <label style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 1.15rem; border-radius: 14px; border: 2px solid {{ $currentMode === 'disabled' ? 'var(--primary, #8b5cf6)' : 'var(--border-color)' }}; background: {{ $currentMode === 'disabled' ? 'rgba(139, 92, 246, 0.05)' : 'var(--bg-card)' }}; cursor: pointer; transition: all 0.2s;">
                    <input type="radio" name="mode" value="disabled" {{ $currentMode === 'disabled' ? 'checked' : '' }} style="margin-top: 0.25rem; accent-color: var(--primary);">
                    <div>
                        <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-main); display: flex; align-items: center; gap: 0.4rem;">
                            <i class="fa-solid fa-ban" style="color: #ef4444;"></i>
                            {{ __('Disabled') }}
                        </div>
                        <p style="font-size: 0.78rem; color: var(--text-muted); margin: 0.35rem 0 0 0; line-height: 1.4;">
                            {{ __('Both table reservations and pre-orders are disabled and hidden from the storefront.') }}
                        </p>
                    </div>
                </label>

                <!-- 2. Reservation Only -->
                <label style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 1.15rem; border-radius: 14px; border: 2px solid {{ $currentMode === 'reservation_only' ? 'var(--primary, #8b5cf6)' : 'var(--border-color)' }}; background: {{ $currentMode === 'reservation_only' ? 'rgba(139, 92, 246, 0.05)' : 'var(--bg-card)' }}; cursor: {{ $resAllowed ? 'pointer' : 'not-allowed' }}; opacity: {{ $resAllowed ? '1' : '0.5' }}; transition: all 0.2s;">
                    <input type="radio" name="mode" value="reservation_only" {{ $currentMode === 'reservation_only' ? 'checked' : '' }} {{ ! $resAllowed ? 'disabled' : '' }} style="margin-top: 0.25rem; accent-color: var(--primary);">
                    <div>
                        <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-main); display: flex; align-items: center; gap: 0.4rem;">
                            <i class="fa-solid fa-calendar-check" style="color: #f59e0b;"></i>
                            {{ __('Reservation Only') }}
                        </div>
                        <p style="font-size: 0.78rem; color: var(--text-muted); margin: 0.35rem 0 0 0; line-height: 1.4;">
                            {{ __('Customers can reserve tables. Pre-ordering menu items through the reservation is disabled.') }}
                        </p>
                        @if(! $resAllowed)
                            <div style="font-size: 0.72rem; color: #ef4444; font-weight: 700; margin-top: 0.35rem;">
                                <i class="fa-solid fa-lock"></i> {{ __('Not permitted by Super Admin or Plan') }}
                            </div>
                        @endif
                    </div>
                </label>

                <!-- 3. Reservation and Pre-Order -->
                <label style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 1.15rem; border-radius: 14px; border: 2px solid {{ $currentMode === 'reservation_and_preorder' ? 'var(--primary, #8b5cf6)' : 'var(--border-color)' }}; background: {{ $currentMode === 'reservation_and_preorder' ? 'rgba(139, 92, 246, 0.05)' : 'var(--bg-card)' }}; cursor: {{ $preAllowed ? 'pointer' : 'not-allowed' }}; opacity: {{ $preAllowed ? '1' : '0.5' }}; transition: all 0.2s;">
                    <input type="radio" name="mode" value="reservation_and_preorder" {{ $currentMode === 'reservation_and_preorder' ? 'checked' : '' }} {{ ! $preAllowed ? 'disabled' : '' }} style="margin-top: 0.25rem; accent-color: var(--primary);">
                    <div>
                        <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-main); display: flex; align-items: center; gap: 0.4rem;">
                            <i class="fa-solid fa-utensils" style="color: #10b981;"></i>
                            {{ __('Reservation & Pre-Order') }}
                        </div>
                        <p style="font-size: 0.78rem; color: var(--text-muted); margin: 0.35rem 0 0 0; line-height: 1.4;">
                            {{ __('Both reservations and menu pre-orders are enabled and linked together.') }}
                        </p>
                        @if(! $preAllowed)
                            <div style="font-size: 0.72rem; color: #ef4444; font-weight: 700; margin-top: 0.35rem;">
                                <i class="fa-solid fa-lock"></i> {{ __('Pre-orders not permitted') }}
                            </div>
                        @endif
                    </div>
                </label>
            </div>
        </div>

        <!-- Restaurant Administration Language Card -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 0.35rem; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-language" style="color: #3b82f6;"></i>
                <span>{{ __('Restaurant Administration Language') }}</span>
            </h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                {{ __('Select the management language for your staff dashboard, operational emails, and notifications. This operates independently of the customer menu language.') }}
            </p>

            <div style="max-width: 420px;">
                <label class="form-label">
                    {{ __('Partner Admin Interface Locale') }} *
                </label>
                <select name="admin_locale" class="form-control" style="font-weight: 600;">
                    <option value="hy" {{ ($adminLocale ?? $vendor->admin_locale ?? 'hy') === 'hy' ? 'selected' : '' }}>🇦🇲 Հայերեն (Armenian)</option>
                    <option value="en" {{ ($adminLocale ?? $vendor->admin_locale ?? 'hy') === 'en' ? 'selected' : '' }}>🇬🇧 English</option>
                    <option value="ru" {{ ($adminLocale ?? $vendor->admin_locale ?? 'hy') === 'ru' ? 'selected' : '' }}>🇷🇺 Русский (Russian)</option>
                </select>
                <small style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 0.35rem;">
                    {{ __('Customer reservations and email confirmations will always use the customer\'s chosen language.') }}
                </small>
            </div>
        </div>

        <!-- General Availability Card -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 1rem; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-clock" style="color: #f59e0b;"></i>
                <span>{{ __('General Booking Availability & Hours') }}</span>
            </h3>

            <!-- Instant Auto Confirmation Toggle -->
            <div style="margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-radius: 14px; background: var(--bg-body); border: 1px solid var(--border-color); flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-main);">{{ __('Instant Auto-Confirmation') }}</div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.15rem;">{{ __('Automatically confirm bookings when an eligible table is free.') }}</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="auto_confirm" value="1" {{ ($settings['auto_confirm'] ?? false) ? 'checked' : '' }}>
                    <span class="slider round"></span>
                </label>
            </div>

            <!-- Opening & Closing Time -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">{{ __('Opening Time') }} *</label>
                    <input type="time" name="opening_time" class="form-control" value="{{ $settings['opening_time'] ?? '10:00' }}" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">{{ __('Closing Time') }} *</label>
                    <input type="time" name="closing_time" class="form-control" value="{{ $settings['closing_time'] ?? '23:00' }}" required>
                </div>
            </div>

            <!-- Operating Days of the Week -->
            <div>
                <label class="form-label" style="margin-bottom: 0.5rem;">{{ __('Operating Days for Reservations') }}</label>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    @php
                        $days = [
                            1 => __('Monday'),
                            2 => __('Tuesday'),
                            3 => __('Wednesday'),
                            4 => __('Thursday'),
                            5 => __('Friday'),
                            6 => __('Saturday'),
                            0 => __('Sunday'),
                        ];
                        $activeDays = (array) ($settings['operating_days'] ?? [0, 1, 2, 3, 4, 5, 6]);
                    @endphp
                    @foreach($days as $num => $label)
                        @php $isActiveDay = in_array($num, $activeDays, false); @endphp
                        <label style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.55rem 0.9rem; border-radius: 12px; border: 1px solid {{ $isActiveDay ? 'var(--primary)' : 'var(--border-color)' }}; background: {{ $isActiveDay ? 'rgba(37, 99, 235, 0.08)' : 'var(--bg-card)' }}; color: {{ $isActiveDay ? 'var(--primary)' : 'var(--text-main)' }}; cursor: pointer; font-size: 0.88rem; font-weight: 600; transition: all 0.2s;">
                            <input type="checkbox" name="operating_days[]" value="{{ $num }}" {{ $isActiveDay ? 'checked' : '' }} style="accent-color: var(--primary);">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Duration & Buffer Timing Card -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 1rem; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-hourglass-half" style="color: #ec4899;"></i>
                <span>{{ __('Duration & Buffer Timing') }}</span>
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">
                        {{ __('Default Seating Duration (Minutes)') }} *
                    </label>
                    <input type="number" name="default_duration_minutes" class="form-control" value="{{ $settings['default_duration_minutes'] ?? 90 }}" min="30" max="360" required>
                    <small style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.25rem; display: block;">
                        {{ __('Average table occupancy per booking (e.g. 90 or 120 mins).') }}
                    </small>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">
                        {{ __('Buffer Time Between Bookings (Minutes)') }} *
                    </label>
                    <input type="number" name="buffer_minutes" class="form-control" value="{{ $settings['buffer_minutes'] ?? 15 }}" min="0" max="120" required>
                    <small style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.25rem; display: block;">
                        {{ __('Time needed to clean and reset table before next guest.') }}
                    </small>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">
                        {{ __('Time Slot Interval') }} *
                    </label>
                    <select name="slot_interval_minutes" class="form-control" required>
                        <option value="15" {{ ($settings['slot_interval_minutes'] ?? 30) == 15 ? 'selected' : '' }}>15 {{ __('Minutes') }}</option>
                        <option value="30" {{ ($settings['slot_interval_minutes'] ?? 30) == 30 ? 'selected' : '' }}>30 {{ __('Minutes (Recommended)') }}</option>
                        <option value="60" {{ ($settings['slot_interval_minutes'] ?? 30) == 60 ? 'selected' : '' }}>60 {{ __('Minutes') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Party Size & Booking Windows Card -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 1rem; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-users" style="color: #10b981;"></i>
                <span>{{ __('Party Size Limits & Booking Windows') }}</span>
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">{{ __('Min Guests') }} *</label>
                    <input type="number" name="min_party_size" class="form-control" value="{{ $settings['min_party_size'] ?? 1 }}" min="1" max="50" required>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">{{ __('Max Guests') }} *</label>
                    <input type="number" name="max_party_size" class="form-control" value="{{ $settings['max_party_size'] ?? 20 }}" min="1" max="100" required>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">{{ __('Advance Window (Days)') }} *</label>
                    <input type="number" name="advance_booking_days" class="form-control" value="{{ $settings['advance_booking_days'] ?? 30 }}" min="1" max="180" required>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">{{ __('Booking Cutoff (Minutes)') }} *</label>
                    <input type="number" name="booking_cutoff_minutes" class="form-control" value="{{ $settings['booking_cutoff_minutes'] ?? 60 }}" min="0" max="1440" required>
                </div>
            </div>
        </div>

        <!-- Pre-Order & Cancellation Policies Card -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 1rem; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-file-contract" style="color: #6366f1;"></i>
                <span>{{ __('Pre-Order Policy & Cancellation Rules') }}</span>
            </h3>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label">{{ __('Pre-Order Policy') }} *</label>
                <select name="pre_orders_mode" class="form-control" required {{ ! $preAllowed ? 'disabled' : '' }}>
                    <option value="optional" {{ ($settings['pre_orders_mode'] ?? 'optional') === 'optional' ? 'selected' : '' }}>🍽️ {{ __('Optional — Guests may pre-order food & drinks before arrival') }}</option>
                    <option value="mandatory" {{ ($settings['pre_orders_mode'] ?? 'optional') === 'mandatory' ? 'selected' : '' }}>⚡ {{ __('Mandatory — A pre-order is required with every reservation') }}</option>
                    <option value="disabled" {{ ($settings['pre_orders_mode'] ?? 'optional') === 'disabled' || ! $preAllowed ? 'selected' : '' }}>🔒 {{ __('Disabled — Pre-ordering is not allowed') }}</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label">
                    {{ __('Online Cancellation Cutoff (Hours Before Arrival)') }} *
                </label>
                <input type="number" name="cancellation_cutoff_hours" class="form-control" value="{{ $settings['cancellation_cutoff_hours'] ?? 2 }}" min="0" max="72" required>
                <small style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.25rem; display: block;">
                    {{ __('Customers cannot cancel online if fewer hours remain before their reservation.') }}
                </small>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label">{{ __('Cancellation Policy Notice') }}</label>
                <textarea name="cancellation_policy" class="form-control" rows="2" placeholder="{{ __('e.g. Please cancel at least 2 hours in advance. Large parties may be subject to a cancellation fee.') }}">{{ $settings['cancellation_policy'] ?? '' }}</textarea>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">{{ __('Special Reservation Notes for Guests') }}</label>
                <textarea name="reservation_notes" class="form-control" rows="2" placeholder="{{ __('e.g. Tables will be held for up to 15 minutes past reservation time.') }}">{{ $settings['reservation_notes'] ?? '' }}</textarea>
            </div>
        </div>

        <!-- Submit Button -->
        <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-bottom: 2rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.85rem; font-size: 1rem; font-weight: 700; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);">
                <i class="fa-solid fa-floppy-disk"></i> {{ __('Save Settings') }}
            </button>
        </div>
    </form>
</div>
@endsection
