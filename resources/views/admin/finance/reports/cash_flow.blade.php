@extends('layouts.app')

@section('title', __('Cash Flow Statement') . ' - ' . config('app.name'))

@section('styles')
<style>
    @media print {
        .no-print {
            display: none !important;
        }
        .printable-statement {
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            background: #ffffff !important;
            color: #000000 !important;
            width: 100% !important;
            max-width: 100% !important;
        }
    }
</style>
@endsection

@section('content')
<div style="max-width: 960px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Header -->
    <div class="card no-print" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <a href="{{ route('admin.finance.reports.index') }}" style="color: var(--text-muted); font-size: 0.85rem; text-decoration: none; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.5rem;">
                    <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Reports') }}
                </a>
                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-money-bill-transfer" style="color: #06b6d4;"></i>
                    {{ __('Cash Flow Statement (Direct Method)') }}
                </h1>
                <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.85rem;">
                    {{ __('Period:') }} {{ $cashFlow['start_date'] }} {{ __('to') }} {{ $cashFlow['end_date'] }} | {{ __('Currency:') }} {{ $cashFlow['currency'] }}
                </p>
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <button type="button" onclick="window.print()" class="btn btn-primary">
                    <i class="fa-solid fa-print"></i> {{ __('Print Statement') }}
                </button>
            </div>
        </div>

        <!-- Date & Location Form -->
        <form method="GET" action="{{ route('admin.finance.reports.cash_flow') }}" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.25rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
            <select name="location_id" class="form-control" style="width: auto;">
                <option value="">{{ __('All Branches / Consolidated') }}</option>
                @foreach($locations as $loc)
                    <option value="{{ $loc->id }}" {{ $locationId == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                @endforeach
            </select>

            <input type="date" name="start_date" class="form-control" style="width: auto;" value="{{ $startDate->toDateString() }}">
            <input type="date" name="end_date" class="form-control" style="width: auto;" value="{{ $endDate->toDateString() }}">
            <button type="submit" class="btn btn-secondary">{{ __('Update Report') }}</button>
        </form>
    </div>

    <!-- Paper Card -->
    <div class="card printable-statement" style="padding: 2.5rem; background: var(--card-bg);">
        <div class="statement-header" style="text-align: center; border-bottom: 2px solid var(--border-color); padding-bottom: 1.5rem; margin-bottom: 2rem;">
            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ auth()->user()?->vendor?->name }}</h2>
            <div class="statement-title" style="font-size: 1.15rem; font-weight: 700; color: var(--text-muted); margin-top: 0.25rem;">{{ __('STATEMENT OF CASH FLOWS') }}</div>
            <div class="statement-subtitle" style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('For the period from') }} {{ $startDate->format('M d, Y') }} {{ __('to') }} {{ $endDate->format('M d, Y') }} | {{ __('Currency:') }} {{ $cashFlow['currency'] }}
            </div>
            <div class="print-only" style="font-size: 0.75rem; color: #666; margin-top: 0.35rem;">
                {{ __('Generated on:') }} {{ now()->format('Y-m-d H:i') }} | {{ __('Accountant:') }} {{ auth()->user()?->name }}
            </div>
        </div>

        <!-- Beginning Balance -->
        <div style="display: flex; justify-content: space-between; padding: 0.75rem 0.5rem; font-weight: 800; font-size: 1.05rem; background: var(--bg-surface); border-radius: 6px; margin-bottom: 1.5rem; border: 1px solid var(--border-color);">
            <span>{{ __('CASH & BANK BALANCE AT BEGINNING OF PERIOD') }}</span>
            <span style="font-family: monospace;">{{ number_format($cashFlow['beginning_cash_balance'], 2) }} {{ $cashFlow['currency'] }}</span>
        </div>

        <!-- 1. Operating Activities -->
        <div style="margin-bottom: 2rem;">
            <div style="font-size: 1rem; font-weight: 800; color: var(--text-main); text-transform: uppercase; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 0.75rem;">
                {{ __('1. Cash Flows from Operating Activities') }}
            </div>

            <div style="display: flex; justify-content: space-between; padding: 0.35rem 0.5rem; font-size: 0.9rem;">
                <span>{{ __('Cash receipts from customers (Sales settlements)') }}</span>
                <span style="font-family: monospace; font-weight: 600; color: #10b981;">+{{ number_format($cashFlow['operating_activities']['customer_receipts'], 2) }}</span>
            </div>

            @if($cashFlow['operating_activities']['other_inflows'] > 0)
                <div style="display: flex; justify-content: space-between; padding: 0.35rem 0.5rem; font-size: 0.9rem;">
                    <span>{{ __('Other operating cash inflows') }}</span>
                    <span style="font-family: monospace; font-weight: 600; color: #10b981;">+{{ number_format($cashFlow['operating_activities']['other_inflows'], 2) }}</span>
                </div>
            @endif

            <div style="display: flex; justify-content: space-between; padding: 0.35rem 0.5rem; font-size: 0.9rem;">
                <span>{{ __('Cash paid to suppliers (Procurement disbursements)') }}</span>
                <span style="font-family: monospace; font-weight: 600; color: #ef4444;">{{ number_format($cashFlow['operating_activities']['supplier_payments'], 2) }}</span>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 0.35rem 0.5rem; font-size: 0.9rem;">
                <span>{{ __('Cash paid for operating expenses & utilities') }}</span>
                <span style="font-family: monospace; font-weight: 600; color: #ef4444;">{{ number_format($cashFlow['operating_activities']['operating_expense_payments'], 2) }}</span>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 0.35rem 0.5rem; font-size: 0.9rem;">
                <span>{{ __('Cash paid to employees (Payroll disbursements & advances)') }}</span>
                <span style="font-family: monospace; font-weight: 600; color: #ef4444;">{{ number_format($cashFlow['operating_activities']['payroll_payments'], 2) }}</span>
            </div>

            @if(abs($cashFlow['operating_activities']['customer_refunds']) > 0)
                <div style="display: flex; justify-content: space-between; padding: 0.35rem 0.5rem; font-size: 0.9rem;">
                    <span>{{ __('Customer refunds paid out') }}</span>
                    <span style="font-family: monospace; font-weight: 600; color: #ef4444;">{{ number_format($cashFlow['operating_activities']['customer_refunds'], 2) }}</span>
                </div>
            @endif

            <div style="display: flex; justify-content: space-between; padding: 0.6rem 0.5rem; font-weight: 800; font-size: 0.95rem; background: var(--bg-surface); border-radius: 6px; margin-top: 0.5rem; border: 1px solid var(--border-color);">
                <span>{{ __('Net Cash from Operating Activities') }}</span>
                <span style="font-family: monospace; color: {{ $cashFlow['operating_activities']['net_operating_cash'] >= 0 ? '#10b981' : '#ef4444' }};">
                    {{ number_format($cashFlow['operating_activities']['net_operating_cash'], 2) }} {{ $cashFlow['currency'] }}
                </span>
            </div>
        </div>

        <!-- 2. Investing Activities -->
        <div style="margin-bottom: 2rem;">
            <div style="font-size: 1rem; font-weight: 800; color: var(--text-main); text-transform: uppercase; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 0.75rem;">
                {{ __('2. Cash Flows from Investing Activities') }}
            </div>

            <div style="display: flex; justify-content: space-between; padding: 0.35rem 0.5rem; font-size: 0.9rem;">
                <span>{{ __('Purchase of kitchen & POS hardware assets (CapEx)') }}</span>
                <span style="font-family: monospace; font-weight: 600;">{{ number_format($cashFlow['investing_activities']['capital_expenditures'], 2) }}</span>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 0.6rem 0.5rem; font-weight: 800; font-size: 0.95rem; background: var(--bg-surface); border-radius: 6px; margin-top: 0.5rem; border: 1px solid var(--border-color);">
                <span>{{ __('Net Cash from Investing Activities') }}</span>
                <span style="font-family: monospace;">{{ number_format($cashFlow['investing_activities']['net_investing_cash'], 2) }} {{ $cashFlow['currency'] }}</span>
            </div>
        </div>

        <!-- 3. Financing Activities -->
        <div style="margin-bottom: 2rem;">
            <div style="font-size: 1rem; font-weight: 800; color: var(--text-main); text-transform: uppercase; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 0.75rem;">
                {{ __('3. Cash Flows from Financing Activities') }}
            </div>

            <div style="display: flex; justify-content: space-between; padding: 0.35rem 0.5rem; font-size: 0.9rem;">
                <span>{{ __('Capital contributions & loans received') }}</span>
                <span style="font-family: monospace; font-weight: 600;">{{ number_format($cashFlow['financing_activities']['capital_and_loans_received'], 2) }}</span>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 0.35rem 0.5rem; font-size: 0.9rem;">
                <span>{{ __('Loan repayments & shareholder distributions') }}</span>
                <span style="font-family: monospace; font-weight: 600;">{{ number_format($cashFlow['financing_activities']['loan_repayments_and_dividends'], 2) }}</span>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 0.6rem 0.5rem; font-weight: 800; font-size: 0.95rem; background: var(--bg-surface); border-radius: 6px; margin-top: 0.5rem; border: 1px solid var(--border-color);">
                <span>{{ __('Net Cash from Financing Activities') }}</span>
                <span style="font-family: monospace;">{{ number_format($cashFlow['financing_activities']['net_financing_cash'], 2) }} {{ $cashFlow['currency'] }}</span>
            </div>
        </div>

        <!-- Net Cash Change & Ending Balance -->
        <div style="border-top: 2px solid var(--border-color); padding-top: 1rem;">
            <div style="display: flex; justify-content: space-between; padding: 0.6rem 0.5rem; font-size: 1rem; font-weight: 700;">
                <span>{{ __('NET INCREASE / (DECREASE) IN CASH') }}</span>
                <span style="font-family: monospace; color: {{ $cashFlow['net_cash_change'] >= 0 ? '#10b981' : '#ef4444' }};">
                    {{ $cashFlow['net_cash_change'] >= 0 ? '+' : '' }}{{ number_format($cashFlow['net_cash_change'], 2) }}
                </span>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 1rem 0.75rem; font-weight: 900; font-size: 1.25rem; background: rgba(6, 182, 212, 0.12); border-radius: 8px; margin-top: 0.75rem; color: #06b6d4;">
                <span>{{ __('CASH & BANK BALANCE AT END OF PERIOD') }}</span>
                <span style="font-family: monospace;">{{ number_format($cashFlow['ending_cash_balance'], 2) }} {{ $cashFlow['currency'] }}</span>
            </div>
        </div>

        <!-- Signatures Block for Print -->
        <div class="print-signatures statement-signatures">
            <div class="sig-col">
                <div class="sig-line"></div>
                <div class="sig-label">{{ __('Prepared by (Accountant):') }} {{ auth()->user()?->name }}</div>
            </div>
            <div class="sig-col">
                <div class="sig-line"></div>
                <div class="sig-label">{{ __('Approved by (Director / General Manager)') }}</div>
            </div>
        </div>
    </div>
</div>
@endsection

