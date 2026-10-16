@extends('layouts.app')

@section('title', 'Email Management Settings - QRMenu SuperAdmin')

@section('styles')
<style>
    .superadmin-hero {
        background: linear-gradient(135deg, rgba(148, 163, 184, 0.12) 0%, rgba(99, 102, 241, 0.08) 100%);
        border: 1px solid var(--border-color);
        border-radius: 24px;
        padding: 1.75rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1.25rem;
        box-shadow: var(--shadow-card);
    }
</style>
@endsection

@section('content')
<div>

    @include('superadmin.emails._nav', [
        'title' => __('Global Email Settings & Audit Trail'),
        'subtitle' => __('Configure retention rules, webhook secrets, rate limiting, and review configuration audit records.')
    ])

    @if(session('success'))
        <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981; padding: 0.9rem 1.25rem; border-radius: 14px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.65rem; font-size: 0.88rem; font-weight: 600;">
            <i class="fa-solid fa-circle-check"></i> {{ session('success') }}
        </div>
    @endif

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Settings Form -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-card);">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.2rem;">
                {{ __('Platform Email Settings') }}
            </h3>
            <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                {{ __('System-wide email retention and security policies.') }}
            </p>

            <form action="{{ route('superadmin.emails.settings.update') }}" method="POST">
                @csrf

                <div style="margin-bottom: 1.15rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                        {{ __('Log Retention Window (Days)') }} *
                    </label>
                    <input type="number" name="email_retention_days" value="{{ old('email_retention_days', $settings['email_retention_days']) }}" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" min="7" max="730" required>
                    <span style="font-size: 0.72rem; color: var(--text-muted);">
                        {{ __('Logs older than this threshold can be automatically pruned to save storage.') }}
                    </span>
                </div>

                <div style="margin-bottom: 1.15rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                        {{ __('Provider Webhook Secret Token') }}
                    </label>
                    <input type="text" name="email_webhook_secret" value="{{ old('email_webhook_secret', $settings['email_webhook_secret']) }}" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" placeholder="e.g. whsec_qrmenu_live_89421038">
                    <span style="font-size: 0.72rem; color: var(--text-muted);">
                        {{ __('Used to verify authenticity of callbacks sent to /api/webhooks/email/{provider}.') }}
                    </span>
                </div>

                <div style="margin-bottom: 1.15rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                        {{ __('Max Test Emails Per Minute Rate Limit') }} *
                    </label>
                    <input type="number" name="email_rate_limit_per_min" value="{{ old('email_rate_limit_per_min', $settings['email_rate_limit_per_min']) }}" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" min="5" max="1000" required>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label class="form-label" style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                        {{ __('Admin Failure Alert Email Recipient') }} *
                    </label>
                    <input type="email" name="email_admin_alert_recipient" value="{{ old('email_admin_alert_recipient', $settings['email_admin_alert_recipient']) }}" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.65rem 0.85rem;" required>
                </div>

                <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary" style="border-radius: 10px; padding: 0.65rem 1.5rem;">
                        <i class="fa-solid fa-floppy-disk"></i> {{ __('Save Settings') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Webhook Integration Card -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-card); display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.2rem;">
                    {{ __('Delivery Webhook Endpoints') }}
                </h3>
                <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                    {{ __('Register these callback URLs in your provider console to enable real verified delivery tracking.') }}
                </p>

                <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                    <div style="background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.85rem 1rem;">
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                            {{ __('Generic Webhook URL:') }}
                        </div>
                        <code style="font-size: 0.82rem; word-break: break-all; color: var(--primary);">
                            {{ url('/api/webhooks/email/generic') }}
                        </code>
                    </div>

                    <div style="background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.85rem 1rem;">
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                            {{ __('Resend Webhook URL:') }}
                        </div>
                        <code style="font-size: 0.82rem; word-break: break-all; color: var(--primary);">
                            {{ url('/api/webhooks/email/resend') }}
                        </code>
                    </div>

                    <div style="background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.85rem 1rem;">
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                            {{ __('Postmark Webhook URL:') }}
                        </div>
                        <code style="font-size: 0.82rem; word-break: break-all; color: var(--primary);">
                            {{ url('/api/webhooks/email/postmark') }}
                        </code>
                    </div>
                </div>
            </div>

            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color); font-size: 0.78rem; color: var(--text-muted);">
                <i class="fa-solid fa-circle-info" style="color: #3b82f6;"></i>
                {{ __('Webhooks update delivery status, bounce events, and complaint counts idempotently.') }}
            </div>
        </div>
    </div>

    <!-- Configuration Audit Trail Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-card);">
        <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.2rem;">
            {{ __('Recent Email Configuration Audit Records') }}
        </h3>
        <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 1.25rem;">
            {{ __('Tracks operator actions, connection updates, sender creations, and diagnostic test emails.') }}
        </p>

        <div class="responsive-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Timestamp') }}</th>
                        <th>{{ __('Operator / User') }}</th>
                        <th>{{ __('Action') }}</th>
                        <th>{{ __('Target Resource') }}</th>
                        <th>{{ __('IP Address') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentAudits as $audit)
                        <tr>
                            <td style="color: var(--text-muted); font-size: 0.78rem;">
                                {{ $audit->created_at?->diffForHumans() }}
                            </td>
                            <td>
                                <strong style="color: var(--text-main); font-size: 0.85rem;">{{ $audit->user?->name ?? 'System' }}</strong>
                            </td>
                            <td>
                                <span class="badge badge-indigo">
                                    {{ str_replace('_', ' ', $audit->action) }}
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 0.82rem; color: var(--text-main);">
                                    {{ $audit->target_name ?: $audit->target_type }}
                                </span>
                            </td>
                            <td style="font-size: 0.78rem; color: var(--text-muted); font-family: monospace;">
                                {{ $audit->ip_address ?: '127.0.0.1' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">
                                {{ __('No configuration audit events logged yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
