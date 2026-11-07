@extends('layouts.app')

@section('title', __('Accounts Receivable & Aging Analysis'))

@section('content')
<div class="content-wrapper">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6366f1;">
                    {{ __('Wholesale Subsystem') }}
                </span>
            </div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
                {{ __('Accounts Receivable (AR) & Aging') }}
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                {{ __('Monitor corporate client debts, overdue credit buckets, and outstanding balance aging.') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <a href="{{ route('admin.wholesale.payments.create') }}" 
               style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #6366f1; color: #fff; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">
                <i class="fa-solid fa-money-bill-wave"></i>
                <span>{{ __('Record Collection') }}</span>
            </a>
        </div>
    </div>

    <!-- Aging Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.35rem;">
                {{ __('Total Receivables (AR)') }}
            </div>
            <div style="font-size: 1.5rem; font-weight: 800; color: {{ $summary['total_receivables'] > 0 ? '#ef4444' : 'var(--text-main)' }};">
                {{ number_format($summary['total_receivables'], 2) }} AMD
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __(':count active corporate debtors', ['count' => $summary['active_debtors_count']]) }}
            </div>
        </div>

        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #10b981; margin-bottom: 0.35rem;">
                {{ __('Current (0-30 Days)') }}
            </div>
            <div style="font-size: 1.4rem; font-weight: 800; color: var(--text-main);">
                {{ number_format($summary['current_bucket'], 2) }} AMD
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Within agreed payment terms') }}
            </div>
        </div>

        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #f59e0b; margin-bottom: 0.35rem;">
                {{ __('1 - 30 Days Overdue') }}
            </div>
            <div style="font-size: 1.4rem; font-weight: 800; color: #f59e0b;">
                {{ number_format($summary['overdue_30'], 2) }} AMD
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('First reminder bucket') }}
            </div>
        </div>

        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #ef4444; margin-bottom: 0.35rem;">
                {{ __('31 - 60+ Days Overdue') }}
            </div>
            <div style="font-size: 1.4rem; font-weight: 800; color: #ef4444;">
                {{ number_format($summary['overdue_60'] + $summary['overdue_90_plus'], 2) }} AMD
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Critical collection risk') }}
            </div>
        </div>
    </div>

    <!-- Debtors Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.02);">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-main); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">
                    <th style="padding: 0.85rem 1.25rem;">{{ __('Customer / Legal Entity') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('TIN (ՀՎՀՀ)') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Credit Terms') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Credit Limit') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Outstanding Balance') }}</th>
                    <th style="padding: 0.85rem 1.25rem; text-align: right;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($debtors as $debtor)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-main);">
                            <a href="{{ route('admin.wholesale.customers.show', $debtor) }}" style="color: inherit; text-decoration: none;">
                                {{ $debtor->name }}
                            </a>
                        </td>
                        <td style="padding: 1rem 1rem; font-family: monospace;">
                            {{ $debtor->tin ?? '—' }}
                        </td>
                        <td style="padding: 1rem 1rem; font-size: 0.85rem;">
                            {{ $debtor->payment_term_days > 0 ? __(':days Days Net', ['days' => $debtor->payment_term_days]) : __('Immediate') }}
                        </td>
                        <td style="padding: 1rem 1rem;">
                            {{ (float)$debtor->credit_limit > 0 ? number_format($debtor->credit_limit, 2) . ' AMD' : __('Unlimited') }}
                        </td>
                        <td style="padding: 1rem 1rem; font-weight: 800; color: #ef4444;">
                            {{ number_format($debtor->current_balance, 2) }} AMD
                        </td>
                        <td style="padding: 1rem 1.25rem; text-align: right;">
                            <a href="{{ route('admin.wholesale.payments.create', ['customer_id' => $debtor->id]) }}" 
                               style="padding: 0.4rem 0.75rem; border-radius: 8px; background: #6366f1; color: #fff; font-size: 0.82rem; font-weight: 600; text-decoration: none;">
                                <i class="fa-solid fa-plus"></i> {{ __('Collect') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding: 3rem 1rem; text-align: center; color: var(--text-muted);">
                            <i class="fa-solid fa-circle-check" style="font-size: 2rem; margin-bottom: 0.75rem; color: #10b981; display: block;"></i>
                            {{ __('All accounts receivable are fully settled. No outstanding customer debts.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1.5rem;">
        {{ $debtors->links() }}
    </div>
</div>
@endsection

