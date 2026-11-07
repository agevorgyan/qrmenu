@extends('layouts.app')

@section('title', __('Profit & Loss Statement') . ' - ' . config('app.name'))

@section('content')
<div style="max-width: 1040px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Header -->
    <div class="card" style="padding: 1.5rem; margin-bottom: 0;">
        <div class="page-header-flex" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
            <div>
                <a href="{{ route('admin.finance.reports.index') }}" class="btn btn-xs btn-secondary" style="margin-bottom: 0.65rem; display: inline-flex;">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>{{ __('Back to Reports') }}</span>
                </a>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <div>
                        <h1 style="font-size: clamp(1.25rem, 2.5vw, 1.65rem); font-weight: 800; margin: 0; color: var(--text-main); line-height: 1.2;">
                            {{ __('Profit & Loss Statement (Income Statement)') }}
                        </h1>
                        <p style="margin: 0.35rem 0 0; color: var(--text-muted); font-size: 0.875rem;">
                            {{ __('Period:') }} {{ $pnl['start_date'] }} {{ __('to') }} {{ $pnl['end_date'] }} | {{ __('Currency:') }} {{ $pnl['currency'] }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="page-header-actions" style="display: flex; align-items: center; gap: 0.75rem;">
                <a href="{{ route('admin.finance.reports.export', ['type' => 'profit-loss', 'start_date' => $startDate->toDateString(), 'end_date' => $endDate->toDateString(), 'location_id' => $locationId]) }}" class="btn btn-secondary">
                    <i class="fa-solid fa-download"></i>
                    <span>{{ __('Export CSV') }}</span>
                </a>
                <button type="button" onclick="window.print()" class="btn btn-primary">
                    <i class="fa-solid fa-print"></i>
                    <span>{{ __('Print Statement') }}</span>
                </button>
            </div>
        </div>

        <!-- Date & Location Form -->
        <form method="GET" action="{{ route('admin.finance.reports.profit_loss') }}" class="filter-bar-flex" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.25rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
            <select name="location_id" class="form-control" style="width: auto; font-size: 0.875rem;">
                <option value="">{{ __('All Branches / Consolidated') }}</option>
                @foreach($locations as $loc)
                    <option value="{{ $loc->id }}" {{ $locationId == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                @endforeach
            </select>

            <input type="date" name="start_date" class="form-control" style="width: auto; font-size: 0.875rem;" value="{{ $startDate->toDateString() }}">
            <input type="date" name="end_date" class="form-control" style="width: auto; font-size: 0.875rem;" value="{{ $endDate->toDateString() }}">
            <button type="submit" class="btn btn-sm btn-primary">{{ __('Update Report') }}</button>
        </form>
    </div>

    <!-- Statement Paper Card -->
    <div class="card" style="padding: clamp(1.25rem, 3vw, 2.5rem); background: var(--bg-card); font-family: system-ui, -apple-system, sans-serif; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-card);">
        <!-- Company Header -->
        <div style="text-align: center; border-bottom: 2px solid var(--border-color); padding-bottom: 1.5rem; margin-bottom: 2rem;">
            <h2 style="font-size: 1.4rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ auth()->user()?->vendor?->name }}</h2>
            <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-muted); margin-top: 0.25rem;">{{ __('STATEMENT OF PROFIT AND LOSS') }}</div>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('For the period from') }} {{ $startDate->format('M d, Y') }} {{ __('to') }} {{ $endDate->format('M d, Y') }}
            </div>
        </div>

        <!-- 1. Operating Revenue -->
        <div style="margin-bottom: 2rem;">
            <div style="font-size: 0.95rem; font-weight: 800; color: var(--text-main); text-transform: uppercase; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 0.75rem;">
                {{ __('1. Operating Revenue') }}
            </div>

            @foreach($pnl['revenue']['gross_accounts'] as $acc)
                <div style="display: flex; justify-content: space-between; padding: 0.4rem 0.5rem; font-size: 0.9rem;">
                    <span><span style="font-family: monospace; color: var(--text-muted);">{{ $acc['code'] }}</span> — {{ __($acc['name']) }}</span>
                    <span style="font-family: monospace; font-weight: 600;">{{ number_format($acc['net_amount'], 2) }}</span>
                </div>
            @endforeach

            <div style="display: flex; justify-content: space-between; padding: 0.5rem; font-weight: 700; font-size: 0.95rem; border-top: 1px dashed var(--border-color); margin-top: 0.4rem;">
                <span>{{ __('Total Gross Revenue') }}</span>
                <span style="font-family: monospace;">{{ number_format($pnl['revenue']['gross_revenue'], 2) }}</span>
            </div>

            <!-- Contra Revenue -->
            @if(!empty($pnl['revenue']['contra_accounts']))
                <div style="margin-top: 0.5rem; padding-left: 1rem;">
                    <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted);">{{ __('Less: Discounts & Sales Returns (Contra-Revenue)') }}</div>
                    @foreach($pnl['revenue']['contra_accounts'] as $acc)
                        <div style="display: flex; justify-content: space-between; padding: 0.3rem 0.5rem; font-size: 0.85rem; color: #ef4444;">
                            <span>{{ $acc['code'] }} — {{ __($acc['name']) }}</span>
                            <span style="font-family: monospace;">-{{ number_format(abs($acc['net_amount']), 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <div style="display: flex; justify-content: space-between; padding: 0.75rem 0.5rem; font-weight: 800; font-size: 1.05rem; background: rgba(16, 185, 129, 0.08); border-radius: 8px; margin-top: 0.5rem; color: #10b981;">
                <span>{{ __('NET REVENUE') }}</span>
                <span style="font-family: monospace;">{{ number_format($pnl['revenue']['net_revenue'], 2) }} {{ $pnl['currency'] }}</span>
            </div>
        </div>

        <!-- 2. Cost of Goods Sold -->
        <div style="margin-bottom: 2rem;">
            <div style="font-size: 0.95rem; font-weight: 800; color: var(--text-main); text-transform: uppercase; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 0.75rem;">
                {{ __('2. Cost of Goods Sold (COGS)') }}
            </div>

            @forelse($pnl['cogs']['accounts'] as $acc)
                <div style="display: flex; justify-content: space-between; padding: 0.4rem 0.5rem; font-size: 0.9rem;">
                    <span><span style="font-family: monospace; color: var(--text-muted);">{{ $acc['code'] }}</span> — {{ __($acc['name']) }}</span>
                    <span style="font-family: monospace; font-weight: 600;">{{ number_format($acc['net_amount'], 2) }}</span>
                </div>
            @empty
                <div style="padding: 0.4rem 0.5rem; font-size: 0.85rem; color: var(--text-muted); font-style: italic;">
                    {{ __('No inventory cost deductions booked in period.') }}
                </div>
            @endforelse

            <div style="display: flex; justify-content: space-between; padding: 0.5rem; font-weight: 700; font-size: 0.95rem; border-top: 1px dashed var(--border-color); margin-top: 0.4rem;">
                <span>{{ __('Total Cost of Goods Sold') }}</span>
                <span style="font-family: monospace;">-{{ number_format($pnl['cogs']['total_cogs'], 2) }}</span>
            </div>

            <!-- Gross Profit Summary -->
            <div style="display: flex; justify-content: space-between; padding: 0.75rem 0.5rem; font-weight: 800; font-size: 1.05rem; background: var(--bg-card-hover); border: 1px solid var(--border-color); border-radius: 8px; margin-top: 0.5rem;">
                <span>{{ __('GROSS PROFIT') }} <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">({{ __('Margin:') }} {{ $pnl['gross_margin_pct'] }}%)</span></span>
                <span style="font-family: monospace; color: {{ $pnl['gross_profit'] >= 0 ? '#10b981' : '#ef4444' }};">{{ number_format($pnl['gross_profit'], 2) }} {{ $pnl['currency'] }}</span>
            </div>
        </div>

        <!-- 3. Operating Expenses (OPEX) -->
        <div style="margin-bottom: 2rem;">
            <div style="font-size: 0.95rem; font-weight: 800; color: var(--text-main); text-transform: uppercase; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 0.75rem;">
                {{ __('3. Operating Expenses (OPEX)') }}
            </div>

            @forelse($pnl['operating_expenses']['accounts'] as $acc)
                <div style="display: flex; justify-content: space-between; padding: 0.4rem 0.5rem; font-size: 0.9rem;">
                    <span><span style="font-family: monospace; color: var(--text-muted);">{{ $acc['code'] }}</span> — {{ __($acc['name']) }}</span>
                    <span style="font-family: monospace; font-weight: 600;">{{ number_format($acc['net_amount'], 2) }}</span>
                </div>
            @empty
                <div style="padding: 0.4rem 0.5rem; font-size: 0.85rem; color: var(--text-muted); font-style: italic;">
                    {{ __('No operating expenses booked in period.') }}
                </div>
            @endforelse

            <div style="display: flex; justify-content: space-between; padding: 0.5rem; font-weight: 700; font-size: 0.95rem; border-top: 1px dashed var(--border-color); margin-top: 0.4rem;">
                <span>{{ __('Total Operating Expenses') }}</span>
                <span style="font-family: monospace;">-{{ number_format($pnl['operating_expenses']['total_opex'], 2) }}</span>
            </div>

            <!-- Operating Profit Summary -->
            <div style="display: flex; justify-content: space-between; padding: 0.75rem 0.5rem; font-weight: 800; font-size: 1.05rem; background: var(--bg-card-hover); border: 1px solid var(--border-color); border-radius: 8px; margin-top: 0.5rem;">
                <span>{{ __('OPERATING PROFIT (EBITDA)') }} <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">({{ __('Margin:') }} {{ $pnl['operating_margin_pct'] }}%)</span></span>
                <span style="font-family: monospace; color: {{ $pnl['operating_profit'] >= 0 ? '#10b981' : '#ef4444' }};">{{ number_format($pnl['operating_profit'], 2) }} {{ $pnl['currency'] }}</span>
            </div>
        </div>

        <!-- 4. Taxes & Net Profit -->
        <div style="border-top: 2px solid var(--border-color); padding-top: 1rem;">
            @if(!empty($pnl['taxes']['accounts']))
                @foreach($pnl['taxes']['accounts'] as $acc)
                    <div style="display: flex; justify-content: space-between; padding: 0.4rem 0.5rem; font-size: 0.9rem;">
                        <span>{{ $acc['code'] }} — {{ __($acc['name']) }}</span>
                        <span style="font-family: monospace;">-{{ number_format($acc['net_amount'], 2) }}</span>
                    </div>
                @endforeach
            @endif

            <div style="display: flex; justify-content: space-between; padding: 1rem 0.75rem; font-weight: 900; font-size: 1.25rem; background: {{ $pnl['net_profit'] >= 0 ? 'rgba(16, 185, 129, 0.12)' : 'rgba(239, 68, 68, 0.12)' }}; border-radius: 8px; margin-top: 0.75rem; color: {{ $pnl['net_profit'] >= 0 ? '#10b981' : '#ef4444' }};">
                <span>{{ __('NET PROFIT / (LOSS)') }} <span style="font-size: 0.9rem; font-weight: 700; color: var(--text-muted);">({{ __('Net Margin:') }} {{ $pnl['net_margin_pct'] }}%)</span></span>
                <span style="font-family: monospace;">{{ number_format($pnl['net_profit'], 2) }} {{ $pnl['currency'] }}</span>
            </div>
        </div>
    </div>
</div>
@endsection

