@extends('layouts.app')

@section('title', __('Event Planning Inquiry Settings') . ' — ' . $vendor->name)

@section('content')
<div style="max-width: 960px; margin: 0 auto; width: 100%; box-sizing: border-box; padding-bottom: 3rem;">
    <!-- Breadcrumb -->
    <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.75rem; flex-wrap: wrap;">
        <a href="{{ route('admin.dashboard') }}" style="color: var(--text-muted); text-decoration: none; display: flex; align-items: center; gap: 0.35rem;"><i class="fa-solid fa-gauge-high"></i> {{ __('Dashboard') }}</a>
        <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem; opacity: 0.6;"></i>
        <a href="{{ route('admin.settings.index', ['tab' => 'reservations']) }}" style="color: var(--text-muted); text-decoration: none;">{{ __('Settings') }}</a>
        <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem; opacity: 0.6;"></i>
        <a href="{{ route('admin.event_inquiries.index') }}" style="color: var(--text-muted); text-decoration: none;">{{ __('Event Planning Inquiries') }}</a>
        <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem; opacity: 0.6;"></i>
        <span style="color: var(--primary); font-weight: 700;">{{ __('Event Inquiry Settings') }}</span>
    </div>

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.75rem;">
        <div>
            <h1 style="font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.65rem;">
                <span style="background: rgba(168, 85, 247, 0.12); color: #a855f7; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-champagne-glasses"></i>
                </span>
                <span>{{ __('Event Planning Inquiry Settings') }}</span>
            </h1>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">
                {{ __('Configure public form fields, event types, guest limits, notifications, and customer messages.') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.event_inquiries.index') }}" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Inquiries') }}
            </a>
            <a href="{{ route('admin.settings.reservations') }}" class="btn btn-secondary">
                <i class="fa-solid fa-sliders" style="color: #8b5cf6;"></i> {{ __('Table Booking Rules') }}
            </a>
            <a href="{{ route('client.event_inquiry', ['vendor_slug' => $vendor->slug]) }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-up-right-from-square" style="color: var(--primary);"></i> {{ __('Public Form') }}
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

    <form method="POST" action="{{ route('admin.settings.reservations.events.update') }}">
        @csrf

        <!-- 1. Enable / Disable Feature Toggle -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                <div>
                    <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;">
                        {{ __('Enable Event Planning Inquiries') }}
                    </h3>
                    <p style="font-size: 0.88rem; color: var(--text-muted); margin: 0; max-width: 620px;">
                        {{ __('When enabled, guests can access the public event inquiry form to request organizing private celebrations, weddings, and corporate dinners. When disabled, the form is completely inaccessible.') }}
                    </p>
                </div>
                <div>
                    <input type="hidden" name="enabled" value="0">
                    <label style="position: relative; display: inline-block; width: 50px; height: 28px; cursor: pointer;">
                        <input type="checkbox" name="enabled" value="1" {{ !empty($settings['enabled']) ? 'checked' : '' }} style="opacity: 0; width: 0; height: 0;" onchange="this.nextElementSibling.style.background = this.checked ? 'var(--primary)' : '#cbd5e1'; this.nextElementSibling.firstElementChild.style.transform = this.checked ? 'translateX(22px)' : 'translateX(0)';">
                        <span style="position: absolute; cursor: pointer; inset: 0; background-color: {{ !empty($settings['enabled']) ? 'var(--primary)' : '#cbd5e1' }}; border-radius: 28px; transition: 0.25s;">
                            <span style="position: absolute; height: 22px; width: 22px; left: 3px; bottom: 3px; background-color: white; border-radius: 50%; transition: 0.25s; transform: {{ !empty($settings['enabled']) ? 'translateX(22px)' : 'translateX(0)' }}; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"></span>
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <!-- 2. Form Title & Description -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-heading" style="color: var(--primary);"></i>
                <span>{{ __('Public Form Title & Description') }}</span>
            </h3>

            <div style="margin-bottom: 1.15rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                    {{ __('Form Title (Leave empty for default)') }}
                </label>
                <input type="text" 
                       name="form_title" 
                       value="{{ old('form_title', $settings['form_title'] ?? '') }}" 
                       placeholder="{{ __('«Միջոցառման կազմակերպման հարցում»') }}" 
                       class="form-control" 
                       style="width: 100%; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 0;">
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                    {{ __('Form Subtitle / Description') }}
                </label>
                <textarea name="form_description" 
                          rows="2" 
                          class="form-control" 
                          placeholder="{{ __('Plan your private celebration, corporate dinner, birthday party or wedding with us...') }}" 
                          style="width: 100%; box-sizing: border-box;">{{ old('form_description', $settings['form_description'] ?? '') }}</textarea>
            </div>
        </div>

        <!-- 3. Configurable Optional Fields -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-list-check" style="color: #10b981;"></i>
                <span>{{ __('Optional Form Fields Display') }}</span>
            </h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                {{ __('Select which optional fields should be displayed on the public customer inquiry form.') }}
            </p>

            @php
                $opt = $settings['optional_fields'] ?? [];
                $fields = [
                    'email' => __('Email Address'),
                    'start_time' => __('Preferred Start Time'),
                    'end_time' => __('Preferred End Time / Duration'),
                    'budget' => __('Estimated Budget'),
                    'venue_arrangement' => __('Venue Arrangement (Indoor/Outdoor/VIP Room)'),
                    'food_beverage_preferences' => __('Food & Beverage Preferences'),
                    'additional_services' => __('Additional Services (Decoration, Music, etc.)'),
                    'comments' => __('Additional Requirements & Comments'),
                ];
            @endphp

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 0.75rem;">
                @foreach($fields as $fKey => $fLabel)
                    <input type="hidden" name="optional_fields[{{ $fKey }}]" value="0">
                    <label style="display: flex; align-items: center; gap: 0.6rem; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); cursor: pointer; background: var(--bg-color);">
                        <input type="checkbox" name="optional_fields[{{ $fKey }}]" value="1" {{ !empty($opt[$fKey]) ? 'checked' : '' }} style="width: 16px; height: 16px; accent-color: var(--primary);">
                        <span style="font-size: 0.88rem; font-weight: 600; color: var(--text-main);">{{ $fLabel }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <!-- 4. Guest Count Limits & Contact Methods -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-users" style="color: #06b6d4;"></i>
                <span>{{ __('Capacity Limits & Contact Methods') }}</span>
            </h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                <div>
                    <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                        {{ __('Minimum Guests') }}
                    </label>
                    <input type="number" 
                           name="min_guests" 
                           min="1" 
                           max="1000" 
                           value="{{ old('min_guests', $settings['min_guests'] ?? 1) }}" 
                           class="form-control" 
                           style="width: 100%; box-sizing: border-box;">
                </div>

                <div>
                    <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                        {{ __('Maximum Guests (Optional)') }}
                    </label>
                    <input type="number" 
                           name="max_guests" 
                           min="1" 
                           max="10000" 
                           value="{{ old('max_guests', $settings['max_guests'] ?? '') }}" 
                           placeholder="{{ __('No limit') }}" 
                           class="form-control" 
                           style="width: 100%; box-sizing: border-box;">
                </div>
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.5rem;">
                    {{ __('Allowed Customer Contact Methods') }}
                </label>
                @php $cm = $settings['contact_methods'] ?? ['phone', 'whatsapp', 'email']; @endphp
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="contact_methods[]" value="phone" {{ in_array('phone', $cm) ? 'checked' : '' }} style="width: 16px; height: 16px;">
                        <span style="font-size: 0.88rem; font-weight: 600;">{{ __('Phone Call') }}</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="contact_methods[]" value="whatsapp" {{ in_array('whatsapp', $cm) ? 'checked' : '' }} style="width: 16px; height: 16px;">
                        <span style="font-size: 0.88rem; font-weight: 600;">{{ __('WhatsApp') }}</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="contact_methods[]" value="email" {{ in_array('email', $cm) ? 'checked' : '' }} style="width: 16px; height: 16px;">
                        <span style="font-size: 0.88rem; font-weight: 600;">{{ __('Email') }}</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- 5. Notification Architecture -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-bell" style="color: #f59e0b;"></i>
                <span>{{ __('Notifications & Customer Acknowledgment') }}</span>
            </h3>

            <!-- Restaurant Notification Email Toggle -->
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                <div>
                    <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-main);">
                        {{ __('Notify Restaurant Staff on New Inquiry') }}
                    </div>
                    <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.15rem;">
                        {{ __('Send email notifications to designated restaurant managers whenever a new event inquiry arrives.') }}
                    </div>
                </div>
                <div>
                    <input type="hidden" name="email_notifications_enabled" value="0">
                    <input type="checkbox" name="email_notifications_enabled" value="1" {{ !empty($settings['email_notifications_enabled']) ? 'checked' : '' }} style="width: 20px; height: 20px; accent-color: var(--primary);">
                </div>
            </div>

            <!-- Recipient Emails -->
            <div style="margin-bottom: 1.25rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                    {{ __('Notification Recipient Emails (comma separated)') }}
                </label>
                @php
                    $recipients = $settings['notification_recipients'] ?? [];
                    $recStr = is_array($recipients) ? implode(', ', $recipients) : (string)$recipients;
                @endphp
                <input type="text" 
                       name="notification_recipients" 
                       value="{{ old('notification_recipients', $recStr) }}" 
                       placeholder="{{ __('events@yourrestaurant.com, manager@yourrestaurant.com') }}" 
                       class="form-control" 
                       style="width: 100%; box-sizing: border-box;">
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.3rem;">
                    {{ __('Defaults to your primary restaurant email (:email) if left empty.', ['email' => $vendor->email ?: 'N/A']) }}
                </div>
            </div>

            <!-- Customer Acknowledgment Email Toggle -->
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                <div>
                    <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-main);">
                        {{ __('Send Customer Acknowledgment Email') }}
                    </div>
                    <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.15rem;">
                        {{ __('Automatically send a confirmation email with reference code to the customer when email is provided.') }}
                    </div>
                </div>
                <div>
                    <input type="hidden" name="customer_acknowledgment_enabled" value="0">
                    <input type="checkbox" name="customer_acknowledgment_enabled" value="1" {{ !empty($settings['customer_acknowledgment_enabled']) ? 'checked' : '' }} style="width: 20px; height: 20px; accent-color: var(--primary);">
                </div>
            </div>

            <!-- Custom Confirmation Message -->
            <div>
                <label style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                    {{ __('Custom Success Confirmation Message') }}
                </label>
                <textarea name="confirmation_message" 
                          rows="2" 
                          class="form-control" 
                          placeholder="{{ __('Thank you for your inquiry! Our events team will contact you within 24 hours.') }}" 
                          style="width: 100%; box-sizing: border-box;">{{ old('confirmation_message', $settings['confirmation_message'] ?? '') }}</textarea>
            </div>
        </div>

        <!-- Submit Button -->
        <div style="display: flex; justify-content: flex-end;">
            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem; font-size: 1rem; font-weight: 700;">
                <i class="fa-solid fa-floppy-disk"></i> {{ __('Save Event Settings') }}
            </button>
        </div>
    </form>

</div>
@endsection
