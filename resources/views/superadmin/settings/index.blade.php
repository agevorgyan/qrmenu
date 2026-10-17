@extends('layouts.app')

@section('title', 'Լենդինգի & Կոնտակտների Կարգավորումներ - SuperAdmin')

@section('styles')
<style>
    .settings-container {
        max-width: 1000px;
        margin: 0 auto;
    }
    .settings-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-card);
    }
    .settings-section-title {
        font-family: 'Outfit', sans-serif;
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--text-main);
        margin-bottom: 1.25rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--border-color-light);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.25rem;
    }
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
    }
    .form-group.full-width {
        grid-column: 1 / -1;
    }
    .form-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }
    .form-input, .form-textarea {
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        color: var(--text-main);
        font-size: 0.92rem;
        outline: none;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .form-input:focus, .form-textarea:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px var(--primary-glow);
    }
    .input-hint {
        font-size: 0.75rem;
        color: var(--text-subtle);
    }
    .btn-save {
        background: var(--primary-gradient);
        color: #ffffff;
        font-weight: 600;
        padding: 0.85rem 2rem;
        border-radius: 12px;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
        box-shadow: 0 4px 14px var(--primary-glow);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px var(--primary-glow);
    }
    .preview-box {
        background: rgba(245, 158, 11, 0.08);
        border: 1px dashed rgba(245, 158, 11, 0.3);
        border-radius: 16px;
        padding: 1.25rem;
        margin-top: 1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .branding-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.2s ease, border-color 0.2s ease;
    }
    .branding-card:hover {
        border-color: var(--primary);
    }
    .branding-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
    }
    .branding-preview-box {
        height: 100px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.75rem;
        position: relative;
        overflow: hidden;
    }
    .branding-preview-box.light-bg {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.04);
    }
    .branding-preview-box.dark-bg {
        background: #070913;
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.4);
    }
    .branding-preview-box.tab-mockup {
        background: #1e293b;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .browser-tab-preview {
        background: #0f172a;
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 8px 8px 0 0;
        padding: 0.5rem 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    }
    .social-preview-mockup {
        background: #1e293b;
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 10px 25px rgba(0,0,0,0.35);
    }
    .social-preview-img-wrap {
        width: 100%;
        height: 160px;
        background: #0b0f19;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .social-preview-img-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .social-preview-body {
        padding: 0.85rem 1rem;
    }
    .social-preview-domain {
        font-size: 0.72rem;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 700;
        display: block;
        margin-bottom: 0.25rem;
    }
    .social-preview-title {
        font-size: 0.95rem;
        font-weight: 800;
        color: #f8fafc;
        margin-bottom: 0.25rem;
        line-height: 1.35;
    }
    .social-preview-desc {
        font-size: 0.78rem;
        color: #94a3b8;
        line-height: 1.45;
        margin: 0;
    }
    .settings-nav-tabs {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: var(--bg-card);
        padding: 0.45rem;
        border-radius: 16px;
        border: 1px solid var(--border-color);
        margin-bottom: 2rem;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }
    .settings-tab-btn {
        background: transparent;
        border: none;
        color: var(--text-muted);
        font-family: 'Outfit', sans-serif;
        font-weight: 600;
        font-size: 0.88rem;
        padding: 0.7rem 1.2rem;
        border-radius: 12px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .settings-tab-btn:hover {
        color: var(--text-main);
        background: var(--bg-card-hover);
    }
    .settings-tab-btn.active {
        color: #ffffff;
        background: var(--primary-gradient);
        box-shadow: 0 4px 14px var(--primary-glow);
    }
    .cms-sub-card {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid var(--border-color-light);
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    .cms-sub-title {
        font-family: 'Outfit', sans-serif;
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--text-main);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
</style>
@endsection

@section('content')
<div class="settings-container" x-data="{ activeTab: (window.location.hash ? window.location.hash.substring(1) : 'landing') }" @hashchange.window="activeTab = window.location.hash ? window.location.hash.substring(1) : 'landing'">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                <span class="badge badge-amber">
                    <i class="fa-solid fa-sliders"></i> {{ __('System Settings') }}
                </span>
            </div>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.75rem; font-weight: 800; color: var(--text-main);">
                {{ __('Landing Page & System Management') }}
            </h1>
            <p style="color: var(--text-muted); font-size: 0.88rem;">
                {{ __('Manage landing page sections, branding, SEO, official contacts, and system security.') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <a href="{{ route('landing') }}" target="_blank" class="btn" style="background: var(--bg-card-hover); border: 1px solid var(--border-color); color: var(--text-main); padding: 0.75rem 1.25rem; border-radius: 12px; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none;">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> {{ __('View Landing') }}
            </a>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="settings-nav-tabs">
        <button type="button" class="settings-tab-btn" :class="activeTab === 'landing' ? 'active' : ''" @click="activeTab = 'landing'; window.location.hash = 'landing'">
            <i class="fa-solid fa-rocket" style="color: #f59e0b;"></i>
            <span>{{ __('Landing Page (CMS)') }}</span>
        </button>
        <button type="button" class="settings-tab-btn" :class="activeTab === 'branding' ? 'active' : ''" @click="activeTab = 'branding'; window.location.hash = 'branding'">
            <i class="fa-solid fa-palette" style="color: #f59e0b;"></i>
            <span>{{ __('System Branding') }}</span>
        </button>
        <button type="button" class="settings-tab-btn" :class="activeTab === 'seo' ? 'active' : ''" @click="activeTab = 'seo'; window.location.hash = 'seo'">
            <i class="fa-solid fa-magnifying-glass-chart" style="color: #06b6d4;"></i>
            <span>{{ __('SEO Optimization') }}</span>
        </button>
        <button type="button" class="settings-tab-btn" :class="activeTab === 'contacts' ? 'active' : ''" @click="activeTab = 'contacts'; window.location.hash = 'contacts'">
            <i class="fa-solid fa-headset" style="color: #10b981;"></i>
            <span>{{ __('Contacts & Socials') }}</span>
        </button>
        <button type="button" class="settings-tab-btn" :class="activeTab === 'telegram' ? 'active' : ''" @click="activeTab = 'telegram'; window.location.hash = 'telegram'">
            <i class="fa-brands fa-telegram" style="color: #229ed9;"></i>
            <span>{{ __('Telegram Notifications') }}</span>
        </button>
        <button type="button" class="settings-tab-btn" :class="activeTab === 'security' ? 'active' : ''" @click="activeTab = 'security'; window.location.hash = 'security'">
            <i class="fa-solid fa-shield-halved" style="color: #6366f1;"></i>
            <span>{{ __('Security & 2FA') }}</span>
        </button>
    </div>

    @if(session('success'))
        <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ __(session('success')) }}</span>
        </div>
    @endif

    @if($errors->any())
        <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem;">
            <ul style="margin: 0; padding-left: 1.25rem;">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('superadmin.settings.update') }}" method="POST" enctype="multipart/form-data" x-data="{ isSubmitting: false, validate() { if(this.$el.checkValidity()) { this.isSubmitting = true; return true; } else { this.$el.reportValidity(); return false; } } }" @submit.prevent="if(validate()) $el.submit()">
        @csrf

        <!-- ============================================== -->
        <!-- TAB 1: LANDING PAGE CMS -->
        <!-- ============================================== -->
        <x-superadmin.settings.landing-tab :settings="$settings" />

        <!-- ============================================== -->
        <!-- TAB 2: BRAND IDENTITY & LOGOS -->
        <!-- ============================================== -->
        <x-superadmin.settings.branding-tab :settings="$settings" />

        <!-- ============================================== -->
        <!-- TAB 3: SEO & SOCIAL META (OPENGRAPH) -->
        <!-- ============================================== -->
        <x-superadmin.settings.seo-tab :settings="$settings" />

        <!-- ============================================== -->
        <!-- TAB 4: OFFICIAL CONTACTS & SOCIALS -->
        <!-- ============================================== -->
        <x-superadmin.settings.contacts-tab :settings="$settings" />

        <!-- ============================================== -->
        <!-- TAB 5: TELEGRAM NOTIFICATIONS -->
        <!-- ============================================== -->
        <x-superadmin.settings.telegram-tab :settings="$settings" />

        <!-- Submit Button (shown across all config tabs) -->
        <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem; margin-bottom: 2.5rem;" x-show="activeTab !== 'security'">
            <button type="submit" class="btn-save">
                <i class="fa-solid fa-floppy-disk"></i> {{ __('Save All Settings') }}
            </button>
        </div>
    </form>

    <!-- 5. Admin Profile & Security Settings (2FA, Email & Password Change) -->
    <div id="security-section" x-show="activeTab === 'security'" x-cloak style="margin-top: 1.5rem; padding-top: 2rem; border-top: 2px solid var(--border-color); margin-bottom: 3rem;" x-data="securityModule()">
        <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                <span class="badge badge-indigo">
                    <i class="fa-solid fa-shield-halved"></i> {{ __('Security & 2FA') }}
                </span>
            </div>
            <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin: 0;">
                {{ __('Administrator Security & 2FA') }}
            </h2>
            <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0.25rem 0 0 0;">
                {{ __('Manage two-factor authentication (2FA), update login email, and change password.') }}
            </p>
        </div>

        <!-- 2FA Management Banner / Card -->
        <div class="settings-card" style="margin-bottom: 1.75rem; background: linear-gradient(135deg, rgba(99, 102, 241, 0.04) 0%, rgba(245, 158, 11, 0.04) 100%); border: 1.5px solid {{ auth()->user()->hasTwoFactorEnabled() ? '#10b981' : 'var(--border-color)' }};">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 52px; height: 52px; border-radius: 14px; background: {{ auth()->user()->hasTwoFactorEnabled() ? 'rgba(16, 185, 129, 0.15)' : 'rgba(245, 158, 11, 0.15)' }}; color: {{ auth()->user()->hasTwoFactorEnabled() ? '#10b981' : '#f59e0b' }}; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
                            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit';">
                                {{ __('Two-Factor Authentication (2FA)') }}
                            </h3>
                            @if(auth()->user()->hasTwoFactorEnabled())
                                <span class="badge badge-emerald" style="font-weight: 700;">
                                    <i class="fa-solid fa-circle-check"></i> {{ __('Active') }} ({{ auth()->user()->two_factor_type === 'authenticator' ? 'Google Authenticator' : 'Email Code' }})
                                </span>
                            @else
                                <span class="badge badge-amber" style="font-weight: 700;">
                                    <i class="fa-solid fa-triangle-exclamation"></i> {{ __('Disabled') }}
                                </span>
                            @endif
                        </div>
                        <p style="margin: 0.3rem 0 0 0; font-size: 0.85rem; color: var(--text-muted); line-height: 1.45;">
                            {{ __('Protects your account during login, password change, and email update operations.') }}
                        </p>
                    </div>
                </div>

                <div>
                    @if(auth()->user()->hasTwoFactorEnabled())
                        <button type="button" @click="showDisableModal = true" class="btn btn-secondary" style="border-radius: 12px; font-weight: 700; color: #ef4444; border-color: rgba(239, 68, 68, 0.3);">
                            <i class="fa-solid fa-power-off"></i> {{ __('Disable 2FA') }}
                        </button>
                    @else
                        <button type="button" @click="showEnableModal = true" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; padding: 0.65rem 1.4rem;">
                            <i class="fa-solid fa-lock"></i> {{ __('Enable 2FA Protection') }}
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.75rem;">
            <!-- Change Email Form -->
            <div class="settings-card" style="margin-bottom: 0;">
                <div class="settings-section-title">
                    <i class="fa-solid fa-envelope" style="color: #6366f1;"></i>
                    <span>{{ __('Change Email') }} (2FA)</span>
                </div>
                <form action="{{ route('superadmin.settings.security') }}" method="POST" x-data="{ isSubmitting: false, validate() { if(this.$el.checkValidity()) { this.isSubmitting = true; return true; } else { this.$el.reportValidity(); return false; } } }" @submit.prevent="if(validate()) $el.submit()">
                    @csrf
                    <input type="hidden" name="action_type" value="email">

                    <div style="display: flex; flex-direction: column; gap: 1.15rem;">
                        <div class="form-group">
                            <label class="form-label">{{ __('Current Email') }}</label>
                            <input type="text" value="{{ auth()->user()->email }}" disabled class="form-input" style="opacity: 0.7; cursor: not-allowed;">
                        </div>

                        <div class="form-group">
                            <label class="form-label">{{ __('New Email') }} *</label>
                            <input type="email" name="email" required class="form-input" placeholder="new-admin@example.com" value="{{ old('action_type') === 'email' ? old('email') : '' }}">
                        </div>

                        <div class="form-group">
                            <label class="form-label">{{ __('Current Password') }} *</label>
                            <input type="password" name="current_password" required class="form-input" placeholder="••••••••">
                        </div>

                        <!-- 2FA Code Field -->
                        <div class="form-group">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                                <label class="form-label" style="margin-bottom: 0;">
                                    {{ __('2FA Security Code') }} {{ auth()->user()->hasTwoFactorEnabled() ? '*' : '(' . __('optional') . ')' }}
                                </label>
                                <button type="button" @click="sendCode('email', $event)" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.65rem; border-radius: 8px;">
                                    <i class="fa-solid fa-paper-plane" style="color: #f59e0b;"></i> <span x-text="countdownEmail > 0 ? 'Wait ' + countdownEmail + 's' : '{{ __('Send 2FA code') }}'">{{ __('Send 2FA code') }}</span>
                                </button>
                            </div>
                            <input type="text" name="two_factor_code" {{ auth()->user()->hasTwoFactorEnabled() ? 'required' : '' }} class="form-input" placeholder="{{ __('6-digit code') }}" maxlength="8" autocomplete="one-time-code">
                            <div x-show="msgEmail" x-text="msgEmail" style="font-size: 0.78rem; color: #10b981; margin-top: 0.35rem; font-weight: 600;"></div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                            <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; padding: 0.65rem 1.4rem;">
                                <i class="fa-solid fa-check"></i> {{ __('Update Email') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Change Password Form -->
            <div class="settings-card" style="margin-bottom: 0;">
                <div class="settings-section-title">
                    <i class="fa-solid fa-lock" style="color: #10b981;"></i>
                    <span>{{ __('Change Password') }} (2FA)</span>
                </div>
                <form action="{{ route('superadmin.settings.security') }}" method="POST" x-data="{ isSubmitting: false, validate() { if(this.$el.checkValidity()) { this.isSubmitting = true; return true; } else { this.$el.reportValidity(); return false; } } }" @submit.prevent="if(validate()) $el.submit()">
                    @csrf
                    <input type="hidden" name="action_type" value="password">

                    <div style="display: flex; flex-direction: column; gap: 1.15rem;">
                        <div class="form-group">
                            <label class="form-label">{{ __('Current Password') }} *</label>
                            <input type="password" name="current_password" required class="form-input" placeholder="••••••••">
                        </div>

                        <div class="form-group">
                            <label class="form-label">{{ __('New Password') }} * ({{ __('min. 6 characters') }})</label>
                            <input type="password" name="password" required minlength="6" class="form-input" placeholder="••••••••">
                        </div>

                        <div class="form-group">
                            <label class="form-label">{{ __('Confirm New Password') }} *</label>
                            <input type="password" name="password_confirmation" required minlength="6" class="form-input" placeholder="••••••••">
                        </div>

                        <!-- 2FA Code Field -->
                        <div class="form-group">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                                <label class="form-label" style="margin-bottom: 0;">
                                    {{ __('2FA Security Code') }} {{ auth()->user()->hasTwoFactorEnabled() ? '*' : '(' . __('optional') . ')' }}
                                </label>
                                <button type="button" @click="sendCode('password', $event)" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.65rem; border-radius: 8px;">
                                    <i class="fa-solid fa-paper-plane" style="color: #f59e0b;"></i> <span x-text="countdownPass > 0 ? 'Wait ' + countdownPass + 's' : '{{ __('Send 2FA code') }}'">{{ __('Send 2FA code') }}</span>
                                </button>
                            </div>
                            <input type="text" name="two_factor_code" {{ auth()->user()->hasTwoFactorEnabled() ? 'required' : '' }} class="form-input" placeholder="{{ __('6-digit code') }}" maxlength="8" autocomplete="one-time-code">
                            <div x-show="msgPass" x-text="msgPass" style="font-size: 0.78rem; color: #10b981; margin-top: 0.35rem; font-weight: 600;"></div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                            <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; padding: 0.65rem 1.4rem;">
                                <i class="fa-solid fa-key"></i> {{ __('Change Password') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: ENABLE 2FA -->
        <div x-show="showEnableModal" x-transition.opacity class="modern-modal-overlay" style="display: none;">
            <div class="modern-modal-box" @click.outside="showEnableModal = false" style="max-width: 500px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.85rem; border-bottom: 1px solid var(--border-color);">
                    <div style="display: flex; align-items: center; gap: 0.6rem;">
                        <span style="width: 38px; height: 38px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                            <i class="fa-solid fa-shield-halved"></i>
                        </span>
                        <h3 style="font-family: 'Outfit'; font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0;">
                            {{ __('Enable 2FA Protection') }}
                        </h3>
                    </div>
                    <button type="button" @click="showEnableModal = false" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <!-- 2FA Type Switcher -->
                <div style="display: flex; gap: 0.5rem; background: var(--bg-body); padding: 0.35rem; border-radius: 12px; margin-bottom: 1.25rem;">
                    <button type="button" @click="enableType = 'email'" :class="enableType === 'email' ? 'btn btn-primary' : 'btn btn-secondary'" style="flex: 1; border-radius: 10px; font-size: 0.85rem; justify-content: center; padding: 0.55rem;">
                        <i class="fa-solid fa-envelope"></i> {{ __('Email Code') }}
                    </button>
                    <button type="button" @click="enableType = 'authenticator'" :class="enableType === 'authenticator' ? 'btn btn-primary' : 'btn btn-secondary'" style="flex: 1; border-radius: 10px; font-size: 0.85rem; justify-content: center; padding: 0.55rem;">
                        <i class="fa-solid fa-mobile-screen-button"></i> Authenticator App
                    </button>
                </div>

                <form action="{{ route('superadmin.settings.security') }}" method="POST" x-data="{ isSubmitting: false, validate() { if(this.$el.checkValidity()) { this.isSubmitting = true; return true; } else { this.$el.reportValidity(); return false; } } }" @submit.prevent="if(validate()) $el.submit()">
                    @csrf
                    <input type="hidden" name="action_type" value="2fa_enable">
                    <input type="hidden" name="type" :value="enableType">
                    <input type="hidden" name="secret" value="{{ $setupSecret ?? '' }}">

                    <!-- Authenticator Info & QR -->
                    <div x-show="enableType === 'authenticator'" style="margin-bottom: 1.25rem; text-align: center;">
                        <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">
                            {{ __('Scan this QR code with Google Authenticator or Authy:') }}
                        </p>
                        <div style="display: inline-block; background: #ffffff; padding: 12px; border-radius: 16px; box-shadow: 0 4px 14px rgba(0,0,0,0.08); border: 1px solid var(--border-color); margin-bottom: 0.75rem;">
                            <img src="{{ $qrCodeUrl ?? '' }}" alt="2FA QR Code" style="width: 170px; height: 170px; display: block;">
                        </div>
                        <div style="font-size: 0.78rem; color: var(--text-muted);">
                            {{ __('Or manually enter the secret key:') }}
                            <div style="font-family: monospace; font-size: 0.95rem; font-weight: 700; color: #f59e0b; margin-top: 0.25rem; letter-spacing: 0.1em; user-select: all;">
                                {{ $setupSecret ?? '' }}
                            </div>
                        </div>
                    </div>

                    <!-- Email Info -->
                    <div x-show="enableType === 'email'" style="margin-bottom: 1.25rem;">
                        <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 12px; padding: 0.85rem 1rem; font-size: 0.84rem; color: var(--text-secondary); margin-bottom: 1rem;">
                            <i class="fa-solid fa-circle-info" style="color: #f59e0b; margin-right: 0.35rem;"></i>
                            {{ __('Confirmation code will be sent to:') }} <strong>{{ auth()->user()->maskedEmail() }}</strong>
                        </div>
                        <button type="button" @click="sendCode('setup', $event)" class="btn btn-secondary" style="width: 100%; justify-content: center; border-radius: 10px; font-weight: 600; font-size: 0.86rem; margin-bottom: 0.5rem;">
                            <i class="fa-solid fa-paper-plane" style="color: #f59e0b;"></i> <span x-text="countdownSetup > 0 ? 'Wait ' + countdownSetup + 's' : '{{ __('Send Verification Code') }}'">{{ __('Send Verification Code') }}</span>
                        </button>
                        <div x-show="msgSetup" x-text="msgSetup" style="font-size: 0.78rem; color: #10b981; font-weight: 600; text-align: center;"></div>
                    </div>

                    <!-- Test Code Input -->
                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label class="form-label">{{ __('Enter 6-Digit Verification Code') }} *</label>
                        <input type="text" name="code" required class="form-input" placeholder="••••••" maxlength="8" style="font-family: monospace; font-size: 1.3rem; letter-spacing: 0.3em; text-align: center;">
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                        <button type="button" class="btn btn-secondary" @click="showEnableModal = false">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary" style="font-weight: 700;">
                            <i class="fa-solid fa-shield-check"></i> {{ __('Verify & Enable') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: DISABLE 2FA -->
        <div x-show="showDisableModal" x-transition.opacity class="modern-modal-overlay" style="display: none;">
            <div class="modern-modal-box" @click.outside="showDisableModal = false" style="max-width: 440px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.85rem; border-bottom: 1px solid var(--border-color);">
                    <h3 style="font-family: 'Outfit'; font-size: 1.2rem; font-weight: 800; color: #ef4444; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-triangle-exclamation"></i> {{ __('Disable 2FA') }}
                    </h3>
                    <button type="button" @click="showDisableModal = false" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <p style="font-size: 0.86rem; color: var(--text-secondary); line-height: 1.5; margin-bottom: 1.25rem;">
                    {{ __('Disabling two-factor authentication reduces your account protection level. Please enter your password to confirm:') }}
                </p>

                <form action="{{ route('superadmin.settings.security') }}" method="POST" x-data="{ isSubmitting: false, validate() { if(this.$el.checkValidity()) { this.isSubmitting = true; return true; } else { this.$el.reportValidity(); return false; } } }" @submit.prevent="if(validate()) $el.submit()">
                    @csrf
                    <input type="hidden" name="action_type" value="2fa_disable">

                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label class="form-label">{{ __('Current Password') }} *</label>
                        <input type="password" name="current_password" required class="form-input" placeholder="••••••••">
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                        <button type="button" class="btn btn-secondary" @click="showDisableModal = false">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary" style="background: #ef4444; border-color: #ef4444; font-weight: 700;">
                            {{ __('Yes, Disable 2FA') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function securityModule() {
    return {
        showEnableModal: false,
        showDisableModal: false,
        enableType: 'email',
        countdownEmail: 0,
        countdownPass: 0,
        countdownSetup: 0,
        msgEmail: '',
        msgPass: '',
        msgSetup: '',

        async sendCode(action, event) {
            try {
                const res = await fetch('{{ route('security.2fa.send_code') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ action: action })
                });
                const data = await res.json();
                if (data.success) {
                    if (action === 'email') {
                        this.msgEmail = data.message;
                        this.startTimer('email');
                    } else if (action === 'password') {
                        this.msgPass = data.message;
                        this.startTimer('pass');
                    } else if (action === 'setup') {
                        this.msgSetup = data.message;
                        this.startTimer('setup');
                    }
                }
            } catch (err) {
                console.error(err);
            }
        },

        startTimer(type) {
            let count = 60;
            if (type === 'email') this.countdownEmail = count;
            if (type === 'pass') this.countdownPass = count;
            if (type === 'setup') this.countdownSetup = count;

            const timer = setInterval(() => {
                count--;
                if (type === 'email') this.countdownEmail = count;
                if (type === 'pass') this.countdownPass = count;
                if (type === 'setup') this.countdownSetup = count;

                if (count <= 0) {
                    clearInterval(timer);
                }
            }, 1000);
        }
    }
}

function testSuperAdminTelegram() {
    const btn = document.getElementById('btnTestSaTelegram');
    const resultBox = document.getElementById('saTelegramTestResult');
    const chatId = document.getElementById('saTelegramChatId')?.value;
    const botToken = document.getElementById('saTelegramBotToken')?.value;

    if (!chatId || !chatId.trim()) {
        resultBox.style.display = 'block';
        resultBox.style.background = 'rgba(239, 68, 68, 0.12)';
        resultBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
        resultBox.style.color = '#ef4444';
        resultBox.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Խնդրում ենք լրացնել SuperAdmin Chat ID դաշտը։';
        return;
    }

    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Ուղարկվում է...';
    resultBox.style.display = 'none';

    fetch('{{ route("superadmin.settings.telegram.test") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            chat_id: chatId.trim(),
            bot_token: botToken ? botToken.trim() : null,
        }),
    })
    .then(res => res.json())
    .then(data => {
        resultBox.style.display = 'block';
        if (data.success) {
            resultBox.style.background = 'rgba(16, 185, 129, 0.12)';
            resultBox.style.border = '1px solid rgba(16, 185, 129, 0.3)';
            resultBox.style.color = '#10b981';
            resultBox.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + (data.message || 'Թեստային հաղորդագրությունը հաջողությամբ ուղարկվեց։');
        } else {
            resultBox.style.background = 'rgba(239, 68, 68, 0.12)';
            resultBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
            resultBox.style.color = '#ef4444';
            resultBox.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> ' + (data.message || 'Սխալ՝ չհաջողվեց ուղարկել հաղորդագրությունը։');
        }
    })
    .catch(err => {
        resultBox.style.display = 'block';
        resultBox.style.background = 'rgba(239, 68, 68, 0.12)';
        resultBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
        resultBox.style.color = '#ef4444';
        resultBox.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Կապի խափանում. ' + err.message;
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
}

function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById(previewId);
            if (preview) {
                preview.src = e.target.result;
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function updateLiveSocialPreview() {
    const titleInput = document.getElementById('input_seo_title');
    const descInput = document.getElementById('input_seo_desc');
    const mockupTitle = document.getElementById('mockup_title');
    const mockupDesc = document.getElementById('mockup_desc');

    if (titleInput && mockupTitle) {
        mockupTitle.innerText = titleInput.value.trim() || '{{ \App\Models\SystemSetting::getSeoTitle() }}';
    }
    if (descInput && mockupDesc) {
        mockupDesc.innerText = descInput.value.trim() || '{{ Str::limit(\App\Models\SystemSetting::getSeoDescription(), 110) }}';
    }
}
</script>
@endsection
