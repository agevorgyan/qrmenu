@extends('layouts.app')

@section('title', __('General Ledger Entries') . ' - ' . config('app.name'))

@section('content')
<div style="max-width: 1400px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Page Header -->
    <div class="card" style="padding: 1.5rem; margin-bottom: 0;">
        <div class="page-header-flex" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
            <div>
                <a href="{{ route('admin.finance.ledger.index') }}" class="btn btn-xs btn-secondary" style="margin-bottom: 0.65rem; display: inline-flex;">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>{{ __('Back to Chart of Accounts') }}</span>
                </a>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(139, 92, 246, 0.12); color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                        <i class="fa-solid fa-list-check"></i>
                    </div>
                    <div>
                        <h1 style="font-size: clamp(1.25rem, 2.5vw, 1.65rem); font-weight: 800; margin: 0; color: var(--text-main); line-height: 1.2;">
                            {{ __('General Ledger Journal Entries') }}
                        </h1>
                        <p style="margin: 0.35rem 0 0; color: var(--text-muted); font-size: 0.875rem;">
                            {{ __('Immutable financial audit ledger. Every entry strictly balances: Total Debit = Total Credit.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route('admin.finance.ledger.entries') }}" class="filter-bar-flex" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.25rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
            <input type="text" name="search" class="form-control" placeholder="{{ __('Search entry #, memo, description...') }}" value="{{ request('search') }}" style="max-width: 280px; font-size: 0.875rem;">

            <select name="status" class="form-control" style="width: auto; font-size: 0.875rem;" onchange="this.form.submit()">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="posted" {{ request('status') === 'posted' ? 'selected' : '' }}>{{ __('Posted') }}</option>
                <option value="reversed" {{ request('status') === 'reversed' ? 'selected' : '' }}>{{ __('Reversed') }}</option>
            </select>

            <select name="source_type" class="form-control" style="width: auto; font-size: 0.875rem;" onchange="this.form.submit()">
                <option value="">{{ __('All Source Types') }}</option>
                <option value="order_sale" {{ request('source_type') === 'order_sale' ? 'selected' : '' }}>{{ __('Order Sales') }}</option>
                <option value="pos_payment" {{ request('source_type') === 'pos_payment' ? 'selected' : '' }}>{{ __('Payment Settlements') }}</option>
                <option value="expense_payment" {{ request('source_type') === 'expense_payment' ? 'selected' : '' }}>{{ __('Expense Disbursements') }}</option>
                <option value="FinancialTransfer" {{ request('source_type') === 'FinancialTransfer' ? 'selected' : '' }}>{{ __('Asset Transfers') }}</option>
                <option value="payroll_accrual" {{ request('source_type') === 'payroll_accrual' ? 'selected' : '' }}>{{ __('Payroll Accruals') }}</option>
            </select>

            <input type="date" name="start_date" class="form-control" style="width: auto; font-size: 0.875rem;" value="{{ request('start_date') }}">
            <input type="date" name="end_date" class="form-control" style="width: auto; font-size: 0.875rem;" value="{{ request('end_date') }}">
            <button type="submit" class="btn btn-sm btn-primary">{{ __('Filter') }}</button>

            @if(request()->anyFilled(['search', 'status', 'source_type', 'start_date', 'end_date']))
                <a href="{{ route('admin.finance.ledger.entries') }}" class="btn btn-sm btn-secondary">{{ __('Reset') }}</a>
            @endif
        </form>
    </div>

    <!-- Journal Entries Table Card -->
    <div class="card card-flush" style="border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-card);">
        <div class="table-responsive" style="margin-bottom: 0; border: none; border-radius: 0;">
            <table class="table" style="min-width: 980px;">
                <thead>
                    <tr>
                        <th style="width: 150px;">{{ __('Entry #') }}</th>
                        <th style="width: 110px;">{{ __('Date') }}</th>
                        <th style="min-width: 160px;">{{ __('Source Type') }}</th>
                        <th style="min-width: 250px;">{{ __('Description') }}</th>
                        <th style="min-width: 130px; text-align: right;">{{ __('Total Debit') }}</th>
                        <th style="min-width: 130px; text-align: right;">{{ __('Total Credit') }}</th>
                        <th style="width: 110px; text-align: center;">{{ __('Status') }}</th>
                        <th style="min-width: 130px; text-align: right;">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                        <tr>
                            <td>
                                <a href="{{ route('admin.finance.ledger.entries.show', $entry) }}" style="text-decoration: none; font-family: monospace; font-weight: 800; font-size: 0.9rem; color: #3b82f6;">
                                    {{ $entry->entry_number }}
                                </a>
                                @if($entry->is_reversal)
                                    <span class="badge badge-amber" style="font-size: 0.65rem; margin-left: 0.35rem;">{{ __('REVERSAL') }}</span>
                                @endif
                            </td>
                            <td>
                                <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">
                                    {{ $entry->entry_date->format('Y-m-d') }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-secondary" style="font-weight: 600; font-size: 0.75rem;">
                                    {{ $entry->source_type }}
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 600; font-size: 0.875rem; color: var(--text-main);">
                                    {{ $entry->description }}
                                </div>
                            </td>
                            <td style="text-align: right; font-weight: 800; color: #10b981; font-family: monospace; font-size: 0.95rem;">
                                {{ number_format($entry->total_debit, 2) }}
                            </td>
                            <td style="text-align: right; font-weight: 800; color: #3b82f6; font-family: monospace; font-size: 0.95rem;">
                                {{ number_format($entry->total_credit, 2) }}
                            </td>
                            <td style="text-align: center;">
                                @if($entry->status === 'posted')
                                    <span class="badge badge-emerald">{{ __('Posted') }}</span>
                                @elseif($entry->status === 'reversed')
                                    <span class="badge badge-rose">{{ __('Reversed') }}</span>
                                @else
                                    <span class="badge badge-secondary">{{ $entry->status }}</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <a href="{{ route('admin.finance.ledger.entries.show', $entry) }}" class="btn btn-sm btn-secondary" style="padding: 0.4rem 0.75rem;">
                                    <i class="fa-solid fa-eye"></i>
                                    <span>{{ __('Details') }}</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 3.5rem 1.5rem;">
                                <div style="width: 54px; height: 54px; border-radius: 16px; background: rgba(139, 92, 246, 0.08); color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1rem;">
                                    <i class="fa-solid fa-list-check"></i>
                                </div>
                                <div style="font-weight: 700; font-size: 1rem; color: var(--text-main); margin-bottom: 0.25rem;">
                                    {{ __('No journal entries recorded.') }}
                                </div>
                                <p style="font-size: 0.85rem; margin: 0; color: var(--text-muted);">
                                    {{ __('Transactions will automatically post double-entry records here.') }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($entries->hasPages())
            <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); background: var(--bg-card);">
                {{ $entries->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
