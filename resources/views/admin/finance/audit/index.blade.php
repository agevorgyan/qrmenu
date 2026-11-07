@extends('layouts.app')

@section('title', __('Financial Audit Trail') . ' - ' . config('app.name'))

@section('content')
<div style="max-width: 1400px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Page Header -->
    <div class="card" style="padding: 1.5rem; margin-bottom: 0;">
        <div class="page-header-flex" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(100, 116, 139, 0.12); color: #64748b; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <h1 style="font-size: clamp(1.25rem, 2.5vw, 1.65rem); font-weight: 800; margin: 0; color: var(--text-main); line-height: 1.2;">
                        {{ __('Immutable Financial Audit Trail') }}
                    </h1>
                    <p style="margin: 0.35rem 0 0; color: var(--text-muted); font-size: 0.875rem;">
                        {{ __('Tamper-proof financial event telemetry capturing every journal posting, disbursement, and drawer kick.') }}
                    </p>
                </div>
            </div>

            <div class="page-header-actions" style="display: flex; align-items: center; gap: 0.75rem;">
                <a href="{{ route('admin.finance.audit.verify') }}" class="btn btn-primary" style="padding: 0.65rem 1.25rem;">
                    <i class="fa-solid fa-shield-check"></i>
                    <span>{{ __('Run Double-Entry Tamper Verification') }}</span>
                </a>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route('admin.finance.audit.index') }}" class="filter-bar-flex" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.25rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
            <select name="location_id" class="form-control" style="width: auto; font-size: 0.875rem;">
                <option value="">{{ __('All Branches') }}</option>
                @foreach($locations as $loc)
                    <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                @endforeach
            </select>

            <select name="action" class="form-control" style="width: auto; font-size: 0.875rem;">
                <option value="">{{ __('All Actions') }}</option>
                <option value="journal_entry_posted" {{ request('action') === 'journal_entry_posted' ? 'selected' : '' }}>{{ __('Journal Entry Posted') }}</option>
                <option value="journal_entry_reversed" {{ request('action') === 'journal_entry_reversed' ? 'selected' : '' }}>{{ __('Journal Entry Reversed') }}</option>
                <option value="expense_created" {{ request('action') === 'expense_created' ? 'selected' : '' }}>{{ __('Expense Created') }}</option>
                <option value="expense_payment_disbursed" {{ request('action') === 'expense_payment_disbursed' ? 'selected' : '' }}>{{ __('Expense Payment Disbursed') }}</option>
                <option value="transfer_completed" {{ request('action') === 'transfer_completed' ? 'selected' : '' }}>{{ __('Asset Transfer Completed') }}</option>
                <option value="cash_shift_closed" {{ request('action') === 'cash_shift_closed' ? 'selected' : '' }}>{{ __('Cash Shift Closed') }}</option>
                <option value="drawer_hardware_kick" {{ request('action') === 'drawer_hardware_kick' ? 'selected' : '' }}>{{ __('Drawer Hardware Kick') }}</option>
            </select>

            <input type="date" name="start_date" class="form-control" style="width: auto; font-size: 0.875rem;" value="{{ request('start_date') }}">
            <input type="date" name="end_date" class="form-control" style="width: auto; font-size: 0.875rem;" value="{{ request('end_date') }}">
            <button type="submit" class="btn btn-sm btn-primary">{{ __('Filter') }}</button>
        </form>
    </div>

    <!-- Audit Logs Table Card -->
    <div class="card card-flush" style="border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-card);">
        <div class="table-responsive" style="margin-bottom: 0; border: none; border-radius: 0;">
            <table class="table" style="min-width: 980px;">
                <thead>
                    <tr>
                        <th style="width: 150px;">{{ __('Timestamp') }}</th>
                        <th style="min-width: 170px;">{{ __('Action') }}</th>
                        <th style="min-width: 160px;">{{ __('Entity') }}</th>
                        <th style="min-width: 140px;">{{ __('User / Operator') }}</th>
                        <th style="min-width: 130px;">{{ __('Branch') }}</th>
                        <th style="min-width: 130px; text-align: right;">{{ __('Amount') }}</th>
                        <th style="min-width: 250px;">{{ __('Telemetry Details') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>
                                <span style="font-size: 0.825rem; font-family: monospace; color: var(--text-muted); font-weight: 500;">
                                    {{ $log->created_at->format('Y-m-d H:i:s') }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-secondary" style="font-weight: 600; font-size: 0.75rem;">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td>
                                <span style="font-family: monospace; font-size: 0.825rem; color: var(--text-main); font-weight: 700;">
                                    {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem;">
                                    <div style="width: 22px; height: 22px; border-radius: 50%; background: rgba(100, 116, 139, 0.15); color: #64748b; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700;">
                                        {{ mb_substr($log->user?->name ?? 'S', 0, 1) }}
                                    </div>
                                    <span style="font-weight: 600;">{{ $log->user?->name ?? 'System' }}</span>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.85rem; color: var(--text-main);">
                                    <i class="fa-solid fa-location-dot" style="font-size: 0.75rem; color: #06b6d4;"></i>
                                    <span>{{ $log->location?->name ?? '—' }}</span>
                                </div>
                            </td>
                            <td style="text-align: right; font-weight: 800; font-family: monospace; font-size: 0.95rem;">
                                {{ $log->amount ? number_format($log->amount, 2) . ' ' . $log->currency : '—' }}
                            </td>
                            <td>
                                <div style="font-size: 0.775rem; font-family: monospace; color: var(--text-muted); max-width: 340px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; background: rgba(0,0,0,0.02); padding: 0.2rem 0.5rem; border-radius: 4px;">
                                    {{ json_encode($log->new_values) }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 3.5rem 1.5rem;">
                                <div style="width: 54px; height: 54px; border-radius: 16px; background: rgba(100, 116, 139, 0.08); color: #64748b; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1rem;">
                                    <i class="fa-solid fa-shield-halved"></i>
                                </div>
                                <div style="font-weight: 700; font-size: 1rem; color: var(--text-main); margin-bottom: 0.25rem;">
                                    {{ __('No audit trail entries recorded matching criteria.') }}
                                </div>
                                <p style="font-size: 0.85rem; margin: 0; color: var(--text-muted);">
                                    {{ __('Financial operations telemetry will be recorded here automatically.') }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); background: var(--bg-card);">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
