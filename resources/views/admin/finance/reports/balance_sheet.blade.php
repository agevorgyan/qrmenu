@extends('layouts.app')

@section('title', __('Balance Sheet') . ' - ' . config('app.name'))

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
        <div class="page-header-flex" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <a href="{{ route('admin.finance.reports.index') }}" style="color: var(--text-muted); font-size: 0.85rem; text-decoration: none; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.5rem;">
                    <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Reports') }}
                </a>
                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-scale-balanced" style="color: #3b82f6;"></i>
                    {{ __('Balance Sheet (Statement of Financial Position)') }}
                </h1>
                <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.85rem;">
                    {{ __('As of:') }} {{ $balanceSheet['as_of_date'] }} | {{ __('Currency:') }} {{ $balanceSheet['currency'] }}
                </p>
            </div>

            <div class="page-header-actions" style="display: flex; align-items: center; gap: 0.75rem;">
                <button type="button" onclick="window.print()" class="btn btn-primary">
                    <i class="fa-solid fa-print"></i> {{ __('Print Statement') }}
                </button>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route('admin.finance.reports.balance_sheet') }}" class="filter-bar-flex" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.25rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
            <select name="location_id" class="form-control" style="width: auto;">
                <option value="">{{ __('All Branches / Consolidated') }}</option>
                @foreach($locations as $loc)
                    <option value="{{ $loc->id }}" {{ $locationId == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                @endforeach
            </select>

            <input type="date" name="as_of_date" class="form-control" style="width: auto;" value="{{ $asOfDate->toDateString() }}">
            <button type="submit" class="btn btn-secondary">{{ __('Update As-Of Date') }}</button>
        </form>
    </div>

    <!-- Balance Sheet Paper Card -->
    <div class="card printable-statement" style="padding: 2.5rem; background: var(--card-bg);">
        <div class="statement-header" style="text-align: center; border-bottom: 2px solid var(--border-color); padding-bottom: 1.5rem; margin-bottom: 2rem;">
            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ auth()->user()?->vendor?->name }}</h2>
            <div class="statement-title" style="font-size: 1.15rem; font-weight: 700; color: var(--text-muted); margin-top: 0.25rem;">{{ __('STATEMENT OF FINANCIAL POSITION') }}</div>
            <div class="statement-subtitle" style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('As of') }} {{ $asOfDate->format('F d, Y') }} | {{ __('Currency:') }} {{ $balanceSheet['currency'] }}
            </div>
            <div class="print-only" style="font-size: 0.75rem; color: #666; margin-top: 0.35rem;">
                {{ __('Generated on:') }} {{ now()->format('Y-m-d H:i') }} | {{ __('Accountant:') }} {{ auth()->user()?->name }}
            </div>
        </div>

        <!-- ASSETS -->
        <div style="margin-bottom: 2rem;">
            <div style="font-size: 1.1rem; font-weight: 800; color: #10b981; text-transform: uppercase; border-bottom: 2px solid #10b981; padding-bottom: 0.5rem; margin-bottom: 1rem;">
                {{ __('1. ASSETS') }}
            </div>

            <!-- Current Assets -->
            <div style="margin-bottom: 1.5rem;">
                <div style="font-size: 0.9rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">
                    {{ __('Current Assets (Cash, Receivables, Inventory)') }}
                </div>
                @foreach($balanceSheet['assets']['current'] as $acc)
                    <div style="display: flex; justify-content: space-between; padding: 0.35rem 0.5rem; font-size: 0.9rem;">
                        <span><span style="font-family: monospace; color: var(--text-muted);">{{ $acc['code'] }}</span> — {{ __($acc['name']) }}</span>
                        <span style="font-family: monospace; font-weight: 600;">{{ number_format($acc['balance'], 2) }}</span>
                    </div>
                @endforeach
                <div style="display: flex; justify-content: space-between; padding: 0.4rem 0.5rem; font-weight: 700; border-top: 1px dashed var(--border-color); margin-top: 0.4rem;">
                    <span>{{ __('Total Current Assets') }}</span>
                    <span style="font-family: monospace;">{{ number_format($balanceSheet['assets']['total_current'], 2) }}</span>
                </div>
            </div>

            <!-- Non-Current Assets -->
            <div style="margin-bottom: 1.5rem;">
                <div style="font-size: 0.9rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">
                    {{ __('Non-Current Assets (Equipment & Property)') }}
                </div>
                @foreach($balanceSheet['assets']['non_current'] as $acc)
                    <div style="display: flex; justify-content: space-between; padding: 0.35rem 0.5rem; font-size: 0.9rem;">
                        <span><span style="font-family: monospace; color: var(--text-muted);">{{ $acc['code'] }}</span> — {{ __($acc['name']) }}</span>
                        <span style="font-family: monospace; font-weight: 600;">{{ number_format($acc['balance'], 2) }}</span>
                    </div>
                @endforeach
                <div style="display: flex; justify-content: space-between; padding: 0.4rem 0.5rem; font-weight: 700; border-top: 1px dashed var(--border-color); margin-top: 0.4rem;">
                    <span>{{ __('Total Non-Current Assets') }}</span>
                    <span style="font-family: monospace;">{{ number_format($balanceSheet['assets']['total_non_current'], 2) }}</span>
                </div>
            </div>

            <!-- TOTAL ASSETS -->
            <div style="display: flex; justify-content: space-between; padding: 0.75rem 0.5rem; font-weight: 900; font-size: 1.15rem; background: rgba(16, 185, 129, 0.12); border-radius: 8px; color: #10b981;">
                <span>{{ __('TOTAL ASSETS') }}</span>
                <span style="font-family: monospace;">{{ number_format($balanceSheet['assets']['total_assets'], 2) }} {{ $balanceSheet['currency'] }}</span>
            </div>
        </div>

        <!-- LIABILITIES -->
        <div style="margin-bottom: 2rem;">
            <div style="font-size: 1.1rem; font-weight: 800; color: #ef4444; text-transform: uppercase; border-bottom: 2px solid #ef4444; padding-bottom: 0.5rem; margin-bottom: 1rem;">
                {{ __('2. LIABILITIES') }}
            </div>

            <!-- Current Liabilities -->
            <div style="margin-bottom: 1.5rem;">
                <div style="font-size: 0.9rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">
                    {{ __('Current Liabilities (Accounts Payable, Accrued Payroll, Taxes)') }}
                </div>
                @foreach($balanceSheet['liabilities']['current'] as $acc)
                    <div style="display: flex; justify-content: space-between; padding: 0.35rem 0.5rem; font-size: 0.9rem;">
                        <span><span style="font-family: monospace; color: var(--text-muted);">{{ $acc['code'] }}</span> — {{ __($acc['name']) }}</span>
                        <span style="font-family: monospace; font-weight: 600;">{{ number_format($acc['balance'], 2) }}</span>
                    </div>
                @endforeach
                <div style="display: flex; justify-content: space-between; padding: 0.4rem 0.5rem; font-weight: 700; border-top: 1px dashed var(--border-color); margin-top: 0.4rem;">
                    <span>{{ __('Total Current Liabilities') }}</span>
                    <span style="font-family: monospace;">{{ number_format($balanceSheet['liabilities']['total_current'], 2) }}</span>
                </div>
            </div>

            <!-- TOTAL LIABILITIES -->
            <div style="display: flex; justify-content: space-between; padding: 0.75rem 0.5rem; font-weight: 800; font-size: 1.05rem; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 6px;">
                <span>{{ __('TOTAL LIABILITIES') }}</span>
                <span style="font-family: monospace;">{{ number_format($balanceSheet['liabilities']['total_liabilities'], 2) }} {{ $balanceSheet['currency'] }}</span>
            </div>
        </div>

        <!-- EQUITY -->
        <div style="margin-bottom: 2rem;">
            <div style="font-size: 1.1rem; font-weight: 800; color: #3b82f6; text-transform: uppercase; border-bottom: 2px solid #3b82f6; padding-bottom: 0.5rem; margin-bottom: 1rem;">
                {{ __('3. OWNER\'S EQUITY') }}
            </div>

            @foreach($balanceSheet['equity']['accounts'] as $acc)
                <div style="display: flex; justify-content: space-between; padding: 0.35rem 0.5rem; font-size: 0.9rem;">
                    <span><span style="font-family: monospace; color: var(--text-muted);">{{ $acc['code'] }}</span> — {{ __($acc['name']) }}</span>
                    <span style="font-family: monospace; font-weight: 600;">{{ number_format($acc['balance'], 2) }}</span>
                </div>
            @endforeach

            <!-- Retained Earnings -->
            <div style="display: flex; justify-content: space-between; padding: 0.35rem 0.5rem; font-size: 0.9rem;">
                <span>{{ __('Current Period Net Retained Income') }}</span>
                <span style="font-family: monospace; font-weight: 700; color: {{ $balanceSheet['equity']['retained_earnings_current_period'] >= 0 ? '#10b981' : '#ef4444' }};">
                    {{ number_format($balanceSheet['equity']['retained_earnings_current_period'], 2) }}
                </span>
            </div>

            <!-- TOTAL EQUITY -->
            <div style="display: flex; justify-content: space-between; padding: 0.75rem 0.5rem; font-weight: 800; font-size: 1.05rem; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 6px; margin-top: 0.5rem;">
                <span>{{ __('TOTAL OWNER\'S EQUITY') }}</span>
                <span style="font-family: monospace;">{{ number_format($balanceSheet['equity']['total_equity'], 2) }} {{ $balanceSheet['currency'] }}</span>
            </div>
        </div>

        <!-- TOTAL LIABILITIES & EQUITY PROOF -->
        <div style="border-top: 2px solid var(--border-color); padding-top: 1rem;">
            <div style="display: flex; justify-content: space-between; padding: 1rem 0.75rem; font-weight: 900; font-size: 1.25rem; background: rgba(59, 130, 246, 0.12); border-radius: 8px; color: #3b82f6;">
                <span>{{ __('TOTAL LIABILITIES & EQUITY') }}</span>
                <span style="font-family: monospace;">{{ number_format($balanceSheet['total_liabilities_and_equity'], 2) }} {{ $balanceSheet['currency'] }}</span>
            </div>

            <div style="margin-top: 1rem; display: flex; align-items: center; justify-content: space-between; font-size: 0.85rem; padding: 0.5rem;">
                <span style="font-weight: 700; color: {{ $balanceSheet['is_balanced'] ? '#10b981' : '#ef4444' }};">
                    <i class="fa-solid {{ $balanceSheet['is_balanced'] ? 'fa-circle-check' : 'fa-circle-xmark' }}"></i>
                    {{ $balanceSheet['is_balanced'] ? __('Accounting Equation Balanced: Assets = Liabilities + Equity') : __('Unbalanced Equation!') }}
                </span>
                <span style="color: var(--text-muted);">{{ __('Discrepancy:') }} {{ number_format($balanceSheet['discrepancy'], 2) }} {{ $balanceSheet['currency'] }}</span>
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

