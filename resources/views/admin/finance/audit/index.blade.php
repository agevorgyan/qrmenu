@extends('layouts.app')

@section('title', __('Financial Audit Trail') . ' - ' . config('app.name'))

@section('content')
<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Header -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-shield-halved" style="color: #64748b;"></i>
                    {{ __('Immutable Financial Audit Trail') }}
                </h1>
                <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.85rem;">
                    {{ __('Tamper-proof financial event telemetry capturing every journal posting, disbursement, and drawer kick.') }}
                </p>
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <a href="{{ route('admin.finance.audit.verify') }}" class="btn btn-primary" style="gap: 0.5rem;">
                    <i class="fa-solid fa-shield-check"></i>
                    <span>{{ __('Run Double-Entry Tamper Verification') }}</span>
                </a>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route('admin.finance.audit.index') }}" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.25rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
            <select name="location_id" class="form-control" style="width: auto;">
                <option value="">{{ __('All Branches') }}</option>
                @foreach($locations as $loc)
                    <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                @endforeach
            </select>

            <select name="action" class="form-control" style="width: auto;">
                <option value="">{{ __('All Actions') }}</option>
                <option value="journal_entry_posted" {{ request('action') === 'journal_entry_posted' ? 'selected' : '' }}>{{ __('Journal Entry Posted') }}</option>
                <option value="journal_entry_reversed" {{ request('action') === 'journal_entry_reversed' ? 'selected' : '' }}>{{ __('Journal Entry Reversed') }}</option>
                <option value="expense_created" {{ request('action') === 'expense_created' ? 'selected' : '' }}>{{ __('Expense Created') }}</option>
                <option value="expense_payment_disbursed" {{ request('action') === 'expense_payment_disbursed' ? 'selected' : '' }}>{{ __('Expense Payment Disbursed') }}</option>
                <option value="transfer_completed" {{ request('action') === 'transfer_completed' ? 'selected' : '' }}>{{ __('Asset Transfer Completed') }}</option>
                <option value="cash_shift_closed" {{ request('action') === 'cash_shift_closed' ? 'selected' : '' }}>{{ __('Cash Shift Closed') }}</option>
                <option value="drawer_hardware_kick" {{ request('action') === 'drawer_hardware_kick' ? 'selected' : '' }}>{{ __('Drawer Hardware Kick') }}</option>
            </select>

            <input type="date" name="start_date" class="form-control" style="width: auto;" value="{{ request('start_date') }}">
            <input type="date" name="end_date" class="form-control" style="width: auto;" value="{{ request('end_date') }}">
            <button type="submit" class="btn btn-secondary">{{ __('Filter') }}</button>
        </form>
    </div>

    <!-- Audit Logs Table -->
    <div class="card" style="padding: 1.5rem;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Timestamp') }}</th>
                        <th>{{ __('Action') }}</th>
                        <th>{{ __('Entity') }}</th>
                        <th>{{ __('User / Operator') }}</th>
                        <th>{{ __('Branch') }}</th>
                        <th style="text-align: right;">{{ __('Amount') }}</th>
                        <th>{{ __('Telemetry Details') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td style="font-size: 0.85rem; font-family: monospace;">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                            <td>
                                <span class="badge badge-secondary">{{ $log->action }}</span>
                            </td>
                            <td>
                                <span style="font-family: monospace; font-size: 0.8rem;">
                                    {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                                </span>
                            </td>
                            <td style="font-weight: 700;">{{ $log->user?->name ?? 'System' }}</td>
                            <td>{{ $log->location?->name ?? '—' }}</td>
                            <td style="text-align: right; font-weight: 800; font-family: monospace;">
                                {{ $log->amount ? number_format($log->amount, 2) . ' ' . $log->currency : '—' }}
                            </td>
                            <td style="font-size: 0.8rem; font-family: monospace; color: var(--text-muted); max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ json_encode($log->new_values) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                                {{ __('No audit trail entries recorded matching criteria.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1rem;">
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection

