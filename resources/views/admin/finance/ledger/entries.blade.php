@extends('layouts.app')

@section('title', __('General Ledger Entries') . ' - ' . config('app.name'))

@section('content')
<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Header -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <a href="{{ route('admin.finance.ledger.index') }}" style="color: var(--text-muted); font-size: 0.85rem; text-decoration: none; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.5rem;">
                    <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Chart of Accounts') }}
                </a>
                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-list-check" style="color: #8b5cf6;"></i>
                    {{ __('General Ledger Journal Entries') }}
                </h1>
                <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.85rem;">
                    {{ __('Immutable financial audit ledger. Every entry strictly balances: Total Debit = Total Credit.') }}
                </p>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route('admin.finance.ledger.entries') }}" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.25rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
            <input type="text" name="search" class="form-control" placeholder="{{ __('Search entry #, memo, description...') }}" value="{{ request('search') }}" style="max-width: 260px;">

            <select name="status" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="posted" {{ request('status') === 'posted' ? 'selected' : '' }}>{{ __('Posted') }}</option>
                <option value="reversed" {{ request('status') === 'reversed' ? 'selected' : '' }}>{{ __('Reversed') }}</option>
            </select>

            <select name="source_type" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">{{ __('All Source Types') }}</option>
                <option value="order_sale" {{ request('source_type') === 'order_sale' ? 'selected' : '' }}>{{ __('Order Sales') }}</option>
                <option value="pos_payment" {{ request('source_type') === 'pos_payment' ? 'selected' : '' }}>{{ __('Payment Settlements') }}</option>
                <option value="expense_payment" {{ request('source_type') === 'expense_payment' ? 'selected' : '' }}>{{ __('Expense Disbursements') }}</option>
                <option value="FinancialTransfer" {{ request('source_type') === 'FinancialTransfer' ? 'selected' : '' }}>{{ __('Asset Transfers') }}</option>
                <option value="payroll_accrual" {{ request('source_type') === 'payroll_accrual' ? 'selected' : '' }}>{{ __('Payroll Accruals') }}</option>
            </select>

            <input type="date" name="start_date" class="form-control" style="width: auto;" value="{{ request('start_date') }}">
            <input type="date" name="end_date" class="form-control" style="width: auto;" value="{{ request('end_date') }}">
            <button type="submit" class="btn btn-sm btn-secondary">{{ __('Filter') }}</button>

            @if(request()->anyFilled(['search', 'status', 'source_type', 'start_date', 'end_date']))
                <a href="{{ route('admin.finance.ledger.entries') }}" class="btn btn-sm btn-secondary">{{ __('Reset') }}</a>
            @endif
        </form>
    </div>

    <!-- Journal Entries Table -->
    <div class="card" style="padding: 1.5rem;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Entry #') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Source Type') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th style="text-align: right;">{{ __('Total Debit') }}</th>
                        <th style="text-align: right;">{{ __('Total Credit') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th style="text-align: right;">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                        <tr>
                            <td style="font-family: monospace; font-weight: 800;">
                                <a href="{{ route('admin.finance.ledger.entries.show', $entry) }}" style="text-decoration: none; color: var(--text-main);">
                                    {{ $entry->entry_number }}
                                </a>
                                @if($entry->is_reversal)
                                    <span class="badge badge-amber" style="font-size: 0.65rem;">{{ __('REVERSAL') }}</span>
                                @endif
                            </td>
                            <td style="font-size: 0.85rem;">{{ $entry->entry_date->format('Y-m-d') }}</td>
                            <td><span class="badge badge-secondary">{{ $entry->source_type }}</span></td>
                            <td>{{ $entry->description }}</td>
                            <td style="text-align: right; font-weight: 800; color: #10b981; font-family: monospace;">
                                {{ number_format($entry->total_debit, 2) }}
                            </td>
                            <td style="text-align: right; font-weight: 800; color: #3b82f6; font-family: monospace;">
                                {{ number_format($entry->total_credit, 2) }}
                            </td>
                            <td>
                                @if($entry->status === 'posted')
                                    <span class="badge badge-emerald">{{ __('Posted') }}</span>
                                @elseif($entry->status === 'reversed')
                                    <span class="badge badge-rose">{{ __('Reversed') }}</span>
                                @else
                                    <span class="badge badge-secondary">{{ $entry->status }}</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <a href="{{ route('admin.finance.ledger.entries.show', $entry) }}" class="btn btn-xs btn-secondary">
                                    {{ __('Lines & Reversal') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                                {{ __('No journal entries matching criteria.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1rem;">
            {{ $entries->links() }}
        </div>
    </div>
</div>
@endsection

