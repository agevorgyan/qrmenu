@extends('layouts.app')

@section('title', __('User Profile & 2FA Security') . ' — ' . config('app.name', 'QRMenu'))

@section('content')
<div style="max-width: 1050px; margin: 0 auto; width: 100%; box-sizing: border-box;" x-data="profileSecurityModule()">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                <span class="badge badge-indigo">
                    <i class="fa-solid fa-user-shield"></i> {{ __('Security & 2FA') }}
                </span>
            </div>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; margin: 0; color: var(--text-main);">
                {{ __('User Profile & Security') }}
            </h1>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0 0;">
                {{ __('Manage two-factor authentication (2FA), password, email address, and personal information') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary" style="border-radius: 12px; font-weight: 600; font-size: 0.88rem;">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Dashboard') }}
            </a>
        </div>
    </div>

    @if (session('success'))
        <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; color: #10b981; display: flex; align-items: center; gap: 0.65rem; font-weight: 600;">
            <i class="fa-solid fa-circle-check" style="font-size: 1.2rem;"></i>
            <span>{{ __(session('success')) }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; color: #ef4444;">
            <div style="font-weight: 700; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.4rem;">
                <i class="fa-solid fa-circle-exclamation"></i> {{ __('Please check the submitted information') }}
            </div>
            <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.88rem;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- 1. 2FA SECURITY STATUS HERO BANNER -->
    <div class="card settings-card" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(245, 158, 11, 0.05) 100%); border: 1.5px solid {{ $user->hasTwoFactorEnabled() ? '#10b981' : 'var(--border-color)' }}; border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); margin-bottom: 1.75rem; box-shadow: var(--shadow-card);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 1.15rem;">
                <div style="width: 56px; height: 56px; border-radius: 16px; background: {{ $user->hasTwoFactorEnabled() ? 'rgba(16, 185, 129, 0.15)' : 'rgba(245, 158, 11, 0.15)' }}; color: {{ $user->hasTwoFactorEnabled() ? '#10b981' : '#f59e0b' }}; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; flex-shrink: 0; box-shadow: 0 4px 14px {{ $user->hasTwoFactorEnabled() ? 'rgba(16, 185, 129, 0.25)' : 'rgba(245, 158, 11, 0.25)' }};">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
                        <h2 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit';">
                            {{ __('Two-Factor Authentication (2FA)') }}
                        </h2>
                        @if($user->hasTwoFactorEnabled())
                            <span class="badge badge-emerald" style="font-weight: 700; font-size: 0.82rem; padding: 0.3rem 0.75rem;">
                                <i class="fa-solid fa-circle-check"></i> {{ __('Enabled') }} ({{ $user->two_factor_type === 'authenticator' ? 'Google Authenticator' : 'Email Code' }})
                            </span>
                        @else
                            <span class="badge badge-amber" style="font-weight: 700; font-size: 0.82rem; padding: 0.3rem 0.75rem;">
                                <i class="fa-solid fa-triangle-exclamation"></i> {{ __('Disabled') }}
                            </span>
                        @endif
                    </div>
                    <p style="margin: 0.35rem 0 0 0; font-size: 0.88rem; color: var(--text-muted); line-height: 1.45;">
                        {{ __('Two-factor authentication adds an extra layer of security when signing in, changing passwords, or updating emails.') }}
                    </p>
                </div>
            </div>

            <div>
                @if($user->hasTwoFactorEnabled())
                    <button type="button" @click="showDisableModal = true" class="btn btn-secondary" style="border-radius: 12px; font-weight: 700; color: #ef4444; border-color: rgba(239, 68, 68, 0.3); padding: 0.65rem 1.25rem;">
                        <i class="fa-solid fa-power-off"></i> {{ __('Disable 2FA') }}
                    </button>
                @else
                    <button type="button" @click="showEnableModal = true" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; padding: 0.65rem 1.4rem; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.3);">
                        <i class="fa-solid fa-lock"></i> {{ __('Enable 2FA Protection') }}
                    </button>
                @endif
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.75rem; margin-bottom: 2rem;">
        
        <!-- 2. CHANGE PASSWORD CARD (WITH 2FA) -->
        <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
                <span style="width: 38px; height: 38px; border-radius: 10px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                    <i class="fa-solid fa-lock"></i>
                </span>
                <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit';">
                    {{ __('Change Password') }}
                </h3>
            </div>

            <form action="{{ route('security.password.update') }}" method="POST">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 1.15rem;">
                    <div class="form-group">
                        <label class="form-label">{{ __('Current Password') }} *</label>
                        <input type="password" name="current_password" required class="form-control" placeholder="••••••••" style="width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem;">
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ __('New Password') }} * ({{ __('minimum 6 characters') }})</label>
                        <input type="password" name="password" required minlength="6" class="form-control" placeholder="••••••••" style="width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem;">
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ __('Confirm New Password') }} *</label>
                        <input type="password" name="password_confirmation" required minlength="6" class="form-control" placeholder="••••••••" style="width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem;">
                    </div>

                    <!-- 2FA Code Input -->
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                            <label class="form-label" style="margin-bottom: 0;">
                                {{ __('2FA Security Code') }} {{ $user->hasTwoFactorEnabled() ? '*' : '(' . __('if enabled') . ')' }}
                            </label>
                            <button type="button" @click="sendCode('password', $event)" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.65rem; border-radius: 8px;">
                                <i class="fa-solid fa-paper-plane" style="color: #f59e0b;"></i> <span x-text="countdownPass > 0 ? 'Wait ' + countdownPass + 's' : 'Send 2FA Code'">{{ __('Send 2FA Code') }}</span>
                            </button>
                        </div>
                        <input type="text" name="two_factor_code" {{ $user->hasTwoFactorEnabled() ? 'required' : '' }} class="form-control" placeholder="6-digit code (Email or Authenticator)" maxlength="8" autocomplete="one-time-code" style="width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem; font-family: monospace; font-size: 1.05rem;">
                        <div x-show="msgPass" x-text="msgPass" style="font-size: 0.78rem; color: #10b981; margin-top: 0.35rem; font-weight: 600;"></div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                        <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; padding: 0.65rem 1.4rem;">
                            <i class="fa-solid fa-key"></i> {{ __('Update Password') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- 3. CHANGE EMAIL CARD (WITH 2FA) -->
        <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); box-shadow: var(--shadow-card);">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
                <span style="width: 38px; height: 38px; border-radius: 10px; background: rgba(99, 102, 241, 0.15); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                    <i class="fa-solid fa-envelope"></i>
                </span>
                <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit';">
                    {{ __('Change Email Address') }}
                </h3>
            </div>

            <form action="{{ route('security.email.update') }}" method="POST">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 1.15rem;">
                    <div class="form-group">
                        <label class="form-label">{{ __('Current Email') }}</label>
                        <input type="text" value="{{ $user->email }}" disabled class="form-control" style="width: 100%; opacity: 0.7; cursor: not-allowed; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem;">
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ __('New Email Address') }} *</label>
                        <input type="email" name="email" required class="form-control" placeholder="new-email@example.com" style="width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem;">
                    </div>

                    <div class="form-group">
                        <label class="form-label">{{ __('Current Password') }} *</label>
                        <input type="password" name="current_password" required class="form-control" placeholder="••••••••" style="width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem;">
                    </div>

                    <!-- 2FA Code Input -->
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                            <label class="form-label" style="margin-bottom: 0;">
                                {{ __('2FA Security Code') }} {{ $user->hasTwoFactorEnabled() ? '*' : '(' . __('if enabled') . ')' }}
                            </label>
                            <button type="button" @click="sendCode('email', $event)" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.65rem; border-radius: 8px;">
                                <i class="fa-solid fa-paper-plane" style="color: #f59e0b;"></i> <span x-text="countdownEmail > 0 ? 'Wait ' + countdownEmail + 's' : 'Send 2FA Code'">{{ __('Send 2FA Code') }}</span>
                            </button>
                        </div>
                        <input type="text" name="two_factor_code" {{ $user->hasTwoFactorEnabled() ? 'required' : '' }} class="form-control" placeholder="6-digit code (Email or Authenticator)" maxlength="8" autocomplete="one-time-code" style="width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem; font-family: monospace; font-size: 1.05rem;">
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
    </div>

    <!-- 4. BASIC PROFILE INFORMATION CARD -->
    <div class="card settings-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.2rem, 3vw, 1.85rem); margin-bottom: 2rem; box-shadow: var(--shadow-card);">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
            <span style="width: 38px; height: 38px; border-radius: 10px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                <i class="fa-solid fa-user"></i>
            </span>
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit';">
                {{ __('Personal Information') }}
            </h3>
        </div>

        <form action="{{ route('admin.profile.update') }}" method="POST">
            @csrf
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem;">
                <div class="form-group">
                    <label class="form-label">{{ __('Full Name') }} *</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="form-control" style="width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem;">
                </div>

                <div class="form-group">
                    <label class="form-label">{{ __('Phone Number') }}</label>
                    <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control" placeholder="+374 99 000000" style="width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem;">
                </div>

                <div class="form-group">
                    <label class="form-label">{{ __('Role') }}</label>
                    <input type="text" value="{{ ucfirst(str_replace('_', ' ', $user->role)) }}" disabled class="form-control" style="width: 100%; opacity: 0.7; cursor: not-allowed; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem;">
                </div>

                @if($user->vendor)
                    <div class="form-group">
                        <label class="form-label">{{ __('Restaurant / Organization') }}</label>
                        <input type="text" value="{{ $user->vendor->name }}" disabled class="form-control" style="width: 100%; opacity: 0.7; cursor: not-allowed; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem;">
                    </div>
                @endif
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 1.25rem;">
                <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; padding: 0.65rem 1.4rem;">
                    <i class="fa-solid fa-floppy-disk"></i> {{ __('Save Changes') }}
                </button>
            </div>
        </form>
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
                    <i class="fa-solid fa-envelope"></i> Email Code
                </button>
                <button type="button" @click="enableType = 'authenticator'" :class="enableType === 'authenticator' ? 'btn btn-primary' : 'btn btn-secondary'" style="flex: 1; border-radius: 10px; font-size: 0.85rem; justify-content: center; padding: 0.55rem;">
                    <i class="fa-solid fa-mobile-screen-button"></i> Authenticator App
                </button>
            </div>

            <form action="{{ route('security.2fa.enable') }}" method="POST">
                @csrf
                <input type="hidden" name="type" :value="enableType">
                <input type="hidden" name="secret" value="{{ $setupSecret ?? '' }}">

                <!-- Authenticator Info & QR -->
                <div x-show="enableType === 'authenticator'" style="margin-bottom: 1.25rem; text-align: center;">
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">
                        Scan this QR code using <strong>Google Authenticator</strong> (or Authy) app:
                    </p>
                    <div style="display: inline-block; background: #ffffff; padding: 12px; border-radius: 16px; box-shadow: 0 4px 14px rgba(0,0,0,0.08); border: 1px solid var(--border-color); margin-bottom: 0.75rem;">
                        <img src="{{ $qrCodeUrl ?? '' }}" alt="2FA QR Code" style="width: 170px; height: 170px; display: block;">
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-muted);">
                        Or enter the secret key manually:
                        <div style="font-family: monospace; font-size: 0.95rem; font-weight: 700; color: #f59e0b; margin-top: 0.25rem; letter-spacing: 0.1em; user-select: all;">
                            {{ $setupSecret ?? '' }}
                        </div>
                    </div>
                </div>

                <!-- Email Info -->
                <div x-show="enableType === 'email'" style="margin-bottom: 1.25rem;">
                    <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 12px; padding: 0.85rem 1rem; font-size: 0.84rem; color: var(--text-secondary); margin-bottom: 1rem;">
                        <i class="fa-solid fa-circle-info" style="color: #f59e0b; margin-right: 0.35rem;"></i>
                        A verification code will be sent to <strong>{{ $user->maskedEmail() }}</strong>.
                    </div>
                    <button type="button" @click="sendCode('setup', $event)" class="btn btn-secondary" style="width: 100%; justify-content: center; border-radius: 10px; font-weight: 600; font-size: 0.86rem; margin-bottom: 0.5rem;">
                        <i class="fa-solid fa-paper-plane" style="color: #f59e0b;"></i> <span x-text="countdownSetup > 0 ? 'Wait ' + countdownSetup + 's' : 'Send Verification Code'">Send Verification Code</span>
                    </button>
                    <div x-show="msgSetup" x-text="msgSetup" style="font-size: 0.78rem; color: #10b981; font-weight: 600; text-align: center;"></div>
                </div>

                <!-- Test Code Input -->
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label">{{ __('Enter 6-digit Verification Code') }} *</label>
                    <input type="text" name="code" required class="form-control" placeholder="••••••" maxlength="8" style="font-family: monospace; font-size: 1.3rem; letter-spacing: 0.3em; text-align: center; width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem;">
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
                {{ __('Disabling two-factor authentication decreases your account security level. Please enter your current password to confirm.') }}
            </p>

            <form action="{{ route('security.2fa.disable') }}" method="POST">
                @csrf
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label">{{ __('Current Password') }} *</label>
                    <input type="password" name="current_password" required class="form-control" placeholder="••••••••" style="width: 100%; background: var(--bg-body); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 12px; padding: 0.75rem 1rem;">
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

<script>
function profileSecurityModule() {
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
</script>
@endsection
