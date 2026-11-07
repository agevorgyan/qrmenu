@extends('layouts.app')

@section('title', __('Double-Entry Integrity Audit') . ' - ' . config('app.name'))

@section('content')
<div style="max-width: 900px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Header -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <a href="{{ route('admin.finance.audit.index') }}" style="color: var(--text-muted); font-size: 0.85rem; text-decoration: none; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.5rem;">
                    <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Audit Trail') }}
                </a>
                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-shield-check" style="color: #10b981;"></i>
                    {{ __('Mathematical Double-Entry Ledger Verification') }}
                </h1>
                <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.85rem;">
                    {{ __('Deep integrity analysis verifying journal entry zero-sum balance, line sums, and Chart of Accounts balance consistency.') }}
                </p>
            </div>

            <div>
                <a href="{{ route('admin.finance.audit.verify') }}" class="btn btn-secondary">
                    <i class="fa-solid fa-rotate"></i> {{ __('Re-run Verification') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Status Banner -->
    <div class="card" style="padding: 2rem; border-left: 6px solid {{ $integrityResult['is_valid'] ? '#10b981' : '#ef4444' }};">
        <div style="display: flex; align-items: center; gap: 1.25rem;">
            <div style="width: 56px; height: 56px; border-radius: 16px; background: {{ $integrityResult['is_valid'] ? 'rgba(16, 185, 129, 0.12)' : 'rgba(239, 68, 68, 0.12)' }}; display: flex; align-items: center; justify-content: center; color: {{ $integrityResult['is_valid'] ? '#10b981' : '#ef4444' }}; font-size: 1.8rem; flex-shrink: 0;">
                <i class="fa-solid {{ $integrityResult['is_valid'] ? 'fa-circle-check' : 'fa-triangle-exclamation' }}"></i>
            </div>
            <div>
                <h2 style="margin: 0; font-size: 1.35rem; font-weight: 800; color: var(--text-main);">
                    {{ $integrityResult['is_valid'] ? __('Ledger Integrity: 100% HEALTHY & BALANCED') : __('Ledger Integrity: COMPROMISED / DISCREPANCY DETECTED') }}
                </h2>
                <p style="margin: 0.35rem 0 0; color: var(--text-muted); font-size: 0.9rem;">
                    {{ $integrityResult['is_valid'] ? __('All journal entries strictly balance (Sum Dr = Sum Cr). Stored account balances exactly reconcile with historical journal lines.') : __('Discrepancies found between journal lines and stored balances.') }}
                </p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Journal Entries Verified') }}</div>
                <div style="font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin-top: 0.25rem;">
                    {{ number_format($integrityResult['entries_checked']) }}
                </div>
            </div>

            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Ledger Accounts Audited') }}</div>
                <div style="font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin-top: 0.25rem;">
                    {{ number_format($integrityResult['accounts_checked']) }}
                </div>
            </div>

            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Anomalies / Issues') }}</div>
                <div style="font-size: 1.3rem; font-weight: 800; color: {{ $integrityResult['issues_count'] === 0 ? '#10b981' : '#ef4444' }}; margin-top: 0.25rem;">
                    {{ $integrityResult['issues_count'] }}
                </div>
            </div>

            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Verified Timestamp') }}</div>
                <div style="font-size: 0.85rem; font-family: monospace; color: var(--text-muted); margin-top: 0.5rem;">
                    {{ $integrityResult['verified_at'] }}
                </div>
            </div>
        </div>
    </div>

    <!-- Issues List (If Any) -->
    @if(!$integrityResult['is_valid'])
        <div class="card" style="padding: 1.5rem;">
            <h3 style="margin-top: 0; font-size: 1.15rem; font-weight: 800; color: #ef4444;">
                <i class="fa-solid fa-triangle-exclamation" style="margin-right: 0.5rem;"></i>
                {{ __('Detected Accounting Inconsistencies') }}
            </h3>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Severity') }}</th>
                            <th>{{ __('Anomaly Type') }}</th>
                            <th>{{ __('Diagnostic Message') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($integrityResult['issues'] as $issue)
                            <tr>
                                <td><span class="badge badge-rose">{{ strtoupper($issue['severity']) }}</span></td>
                                <td style="font-family: monospace;">{{ $issue['type'] }}</td>
                                <td>{{ $issue['message'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

