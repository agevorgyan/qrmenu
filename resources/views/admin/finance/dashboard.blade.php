@extends('layouts.app')

@section('title', __('Financial Dashboard') . ' - ' . config('app.name'))

@section('content')
<div style="max-width: 1400px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Header & Period Filter -->
    <div class="card" style="padding: 1.5rem; margin-bottom: 0;">
        <div class="page-header-flex" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                    <i class="fa-solid fa-coins"></i>
                </div>
                <div>
                    <h1 style="font-size: clamp(1.25rem, 2.5vw, 1.65rem); font-weight: 800; margin: 0; color: var(--text-main); line-height: 1.2;">
                        {{ __('Financial Operations & Cash Management') }}
                    </h1>
                    <p style="margin: 0.35rem 0 0; color: var(--text-muted); font-size: 0.875rem;">
                        {{ __('Double-entry ledger, cash registers, shift blind counting, expenses, and bank reconciliation.') }}
                    </p>
                </div>
            </div>

            <!-- Branch & Period Controls -->
            <form method="GET" action="{{ route('admin.finance.dashboard') }}" class="filter-bar-flex" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <select name="location_id" class="form-control" style="width: auto; font-size: 0.85rem;" onchange="this.form.submit()">
                    <option value="">{{ __('All Branches / Consolidated') }}</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ $locationId == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                    @endforeach
                </select>

                <select name="period" class="form-control" style="width: auto; font-size: 0.85rem;" onchange="this.form.submit()">
                    <option value="today" {{ $period === 'today' ? 'selected' : '' }}>{{ __('Today') }}</option>
                    <option value="yesterday" {{ $period === 'yesterday' ? 'selected' : '' }}>{{ __('Yesterday') }}</option>
                    <option value="last_7_days" {{ $period === 'last_7_days' ? 'selected' : '' }}>{{ __('Last 7 Days') }}</option>
                    <option value="this_month" {{ $period === 'this_month' ? 'selected' : '' }}>{{ __('This Month') }}</option>
                    <option value="last_month" {{ $period === 'last_month' ? 'selected' : '' }}>{{ __('Last Month') }}</option>
                </select>
            </form>
        </div>
    </div>

    <!-- Top Tier Primary Financial KPIs -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem;">
        <!-- Gross Sales -->
        <div class="card" style="padding: 1.25rem; border-left: 4px solid #6366f1;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Gross Sales') }}</div>
                    <div style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin-top: 0.25rem;">
                        {{ number_format($summary['kpis']['gross_sales']['value'], 2) }} <span style="font-size: 0.9rem; font-weight: 600;">{{ $summary['currency'] }}</span>
                    </div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(99, 102, 241, 0.12); display: flex; align-items: center; justify-content: center; color: #6366f1;">
                    <i class="fa-solid fa-cart-shopping"></i>
                </div>
            </div>
            <div style="margin-top: 0.75rem; font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem;">
                <span class="badge {{ $summary['kpis']['gross_sales']['delta_pct'] >= 0 ? 'badge-emerald' : 'badge-rose' }}">
                    {{ $summary['kpis']['gross_sales']['delta_pct'] >= 0 ? '+' : '' }}{{ $summary['kpis']['gross_sales']['delta_pct'] }}%
                </span>
                <span>{{ __('vs previous period') }}</span>
            </div>
        </div>

        <!-- Net Revenue -->
        <div class="card" style="padding: 1.25rem; border-left: 4px solid #10b981;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Net Revenue') }}</div>
                    <div style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin-top: 0.25rem;">
                        {{ number_format($summary['kpis']['net_revenue']['value'], 2) }} <span style="font-size: 0.9rem; font-weight: 600;">{{ $summary['currency'] }}</span>
                    </div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(16, 185, 129, 0.12); display: flex; align-items: center; justify-content: center; color: #10b981;">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>
            <div style="margin-top: 0.75rem; font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem;">
                <span class="badge {{ $summary['kpis']['net_revenue']['delta_pct'] >= 0 ? 'badge-emerald' : 'badge-rose' }}">
                    {{ $summary['kpis']['net_revenue']['delta_pct'] >= 0 ? '+' : '' }}{{ $summary['kpis']['net_revenue']['delta_pct'] }}%
                </span>
                <span>{{ __('after discounts & refunds') }}</span>
            </div>
        </div>

        <!-- Total Expenses -->
        <div class="card" style="padding: 1.25rem; border-left: 4px solid #ef4444;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Operating Expenses') }}</div>
                    <div style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin-top: 0.25rem;">
                        {{ number_format($summary['kpis']['total_expenses']['value'], 2) }} <span style="font-size: 0.9rem; font-weight: 600;">{{ $summary['currency'] }}</span>
                    </div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(239, 68, 68, 0.12); display: flex; align-items: center; justify-content: center; color: #ef4444;">
                    <i class="fa-solid fa-receipt"></i>
                </div>
            </div>
            <div style="margin-top: 0.75rem; font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem;">
                <span class="badge {{ $summary['kpis']['total_expenses']['delta_pct'] <= 0 ? 'badge-emerald' : 'badge-rose' }}">
                    {{ $summary['kpis']['total_expenses']['delta_pct'] >= 0 ? '+' : '' }}{{ $summary['kpis']['total_expenses']['delta_pct'] }}%
                </span>
                <span>{{ __('OPEX + recognized costs') }}</span>
            </div>
        </div>

        <!-- Operating Profit -->
        <div class="card" style="padding: 1.25rem; border-left: 4px solid #f59e0b;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Operating Profit (EBITDA)') }}</div>
                    <div style="font-size: 1.6rem; font-weight: 800; color: {{ $summary['kpis']['operating_profit']['value'] >= 0 ? 'var(--text-main)' : '#ef4444' }}; margin-top: 0.25rem;">
                        {{ number_format($summary['kpis']['operating_profit']['value'], 2) }} <span style="font-size: 0.9rem; font-weight: 600;">{{ $summary['currency'] }}</span>
                    </div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(245, 158, 11, 0.12); display: flex; align-items: center; justify-content: center; color: #f59e0b;">
                    <i class="fa-solid fa-scale-balanced"></i>
                </div>
            </div>
            <div style="margin-top: 0.75rem; font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem;">
                <span class="badge {{ $summary['kpis']['operating_profit']['value'] >= 0 ? 'badge-emerald' : 'badge-rose' }}">
                    {{ $summary['kpis']['operating_profit']['delta_pct'] >= 0 ? '+' : '' }}{{ $summary['kpis']['operating_profit']['delta_pct'] }}%
                </span>
                <span>{{ __('net operating margin') }}</span>
            </div>
        </div>
    </div>

    <!-- Secondary Tier: Liquidity & Reconciliation Health -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem;">
        <!-- Live Cash in Registers -->
        <div class="card" style="padding: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(245, 158, 11, 0.12); display: flex; align-items: center; justify-content: center; color: #f59e0b;">
                    <i class="fa-solid fa-cash-register"></i>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Live Cash in Till/Drawers') }}</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                        {{ number_format($summary['kpis']['live_registers_cash'], 2) }} {{ $summary['currency'] }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Bank Balances -->
        <div class="card" style="padding: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(59, 130, 246, 0.12); display: flex; align-items: center; justify-content: center; color: #3b82f6;">
                    <i class="fa-solid fa-building-columns"></i>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Corporate Bank Balances') }}</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                        {{ number_format($summary['kpis']['live_bank_balance'], 2) }} {{ $summary['currency'] }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Accounts Payable Outstanding -->
        <div class="card" style="padding: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(239, 68, 68, 0.12); display: flex; align-items: center; justify-content: center; color: #ef4444;">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Outstanding Payables (AP)') }}</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                        {{ number_format($summary['kpis']['accounts_payable'], 2) }} {{ $summary['currency'] }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Unreconciled Transactions -->
        <div class="card" style="padding: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(139, 92, 246, 0.12); display: flex; align-items: center; justify-content: center; color: #8b5cf6;">
                    <i class="fa-solid fa-circle-question"></i>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Unreconciled Bank Lines') }}</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                        {{ $summary['kpis']['unreconciled_transactions']['count'] }} <span style="font-size: 0.85rem; font-weight: 600;">({{ number_format($summary['kpis']['unreconciled_transactions']['amount'], 2) }} {{ $summary['currency'] }})</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Settlement Breakdown & Quick Navigation -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
        <!-- Settlement Methods Card -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 0;">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-top: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-money-check-dollar" style="color: #6366f1;"></i>
                {{ __('Sales Settlements by Payment Channel') }}
            </h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                {{ __('Realized collections during selected period. Note: internal asset transfers are strictly excluded.') }}
            </p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem;">
                <div style="padding: 1rem; border-radius: 12px; background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.15);">
                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">{{ __('Cash Received') }}</div>
                    <div style="font-size: 1.3rem; font-weight: 800; color: #10b981; margin-top: 0.25rem;">
                        {{ number_format($summary['kpis']['cash_received'], 2) }} <span style="font-size: 0.8rem; font-weight: 600;">{{ $summary['currency'] }}</span>
                    </div>
                </div>

                <div style="padding: 1rem; border-radius: 12px; background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.15);">
                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">{{ __('Card Terminals') }}</div>
                    <div style="font-size: 1.3rem; font-weight: 800; color: #3b82f6; margin-top: 0.25rem;">
                        {{ number_format($summary['kpis']['card_payments'], 2) }} <span style="font-size: 0.8rem; font-weight: 600;">{{ $summary['currency'] }}</span>
                    </div>
                </div>

                <div style="padding: 1rem; border-radius: 12px; background: rgba(139, 92, 246, 0.05); border: 1px solid rgba(139, 92, 246, 0.15);">
                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">{{ __('Online / QR') }}</div>
                    <div style="font-size: 1.3rem; font-weight: 800; color: #8b5cf6; margin-top: 0.25rem;">
                        {{ number_format($summary['kpis']['online_payments'], 2) }} <span style="font-size: 0.8rem; font-weight: 600;">{{ $summary['currency'] }}</span>
                    </div>
                </div>

                <div style="padding: 1rem; border-radius: 12px; background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.15);">
                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">{{ __('Customer Refunds') }}</div>
                    <div style="font-size: 1.3rem; font-weight: 800; color: #ef4444; margin-top: 0.25rem;">
                        -{{ number_format($summary['kpis']['total_refunds'], 2) }} <span style="font-size: 0.8rem; font-weight: 600;">{{ $summary['currency'] }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Operations Hub -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 0;">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-top: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-bolt" style="color: #f59e0b;"></i>
                {{ __('Quick Operations') }}
            </h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                {{ __('Direct shortcuts to frequent restaurant financial tasks.') }}
            </p>

            <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                <a href="{{ route('admin.finance.expenses.create') }}" class="btn btn-primary" style="justify-content: flex-start; gap: 0.75rem; padding: 0.65rem 1rem;">
                    <i class="fa-solid fa-plus" style="width: 16px;"></i>
                    <span>{{ __('Record New Expense') }}</span>
                </a>
                <a href="{{ route('admin.finance.shifts.index') }}" class="btn btn-secondary" style="justify-content: flex-start; gap: 0.75rem; padding: 0.65rem 1rem;">
                    <i class="fa-solid fa-business-time" style="width: 16px; color: #6366f1;"></i>
                    <span>{{ __('Manage Cash Shifts') }}</span>
                </a>
                <a href="{{ route('admin.finance.transfers.index') }}" class="btn btn-secondary" style="justify-content: flex-start; gap: 0.75rem; padding: 0.65rem 1rem;">
                    <i class="fa-solid fa-money-bill-transfer" style="width: 16px; color: #06b6d4;"></i>
                    <span>{{ __('Safe Drop / Asset Transfer') }}</span>
                </a>
                <a href="{{ route('admin.finance.reports.profit_loss') }}" class="btn btn-secondary" style="justify-content: flex-start; gap: 0.75rem; padding: 0.65rem 1rem;">
                    <i class="fa-solid fa-file-invoice-dollar" style="width: 16px; color: #10b981;"></i>
                    <span>{{ __('Profit & Loss Statement') }}</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Bottom Lists: Cash Registers & Pending Approvals -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem;">
        <!-- Cash Registers Live Status -->
        <div class="card card-flush" style="border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-card);">
            <div style="padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); background: var(--bg-card);">
                <h3 style="font-size: 1.05rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-cash-register" style="color: #f59e0b;"></i>
                    <span>{{ __('Cash Registers & Live Tills') }}</span>
                </h3>
                <a href="{{ route('admin.finance.registers.index') }}" class="btn btn-xs btn-secondary">{{ __('View All') }}</a>
            </div>

            <div class="table-responsive" style="margin-bottom: 0; border: none; border-radius: 0;">
                <table class="table" style="min-width: 480px;">
                    <thead>
                        <tr>
                            <th>{{ __('Register') }}</th>
                            <th>{{ __('Branch') }}</th>
                            <th>{{ __('Active Shift') }}</th>
                            <th style="text-align: right;">{{ __('Live Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($registers as $reg)
                            <tr>
                                <td style="font-weight: 700;">{{ $reg->name }} <span style="font-size: 0.75rem; font-family: monospace; color: var(--text-muted);">({{ $reg->code }})</span></td>
                                <td>{{ $reg->location?->name ?? '—' }}</td>
                                <td>
                                    @if($reg->activeShift)
                                        <span class="badge badge-emerald">{{ __('Open') }}: {{ $reg->activeShift->user?->name }}</span>
                                    @else
                                        <span class="badge badge-secondary">{{ __('Closed') }}</span>
                                    @endif
                                </td>
                                <td style="text-align: right; font-weight: 800; color: #10b981;">
                                    {{ number_format($reg->current_balance, 2) }} {{ $reg->currency }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                    {{ __('No cash registers configured yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Corporate Bank Accounts -->
        <div class="card card-flush" style="border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-card);">
            <div style="padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); background: var(--bg-card);">
                <h3 style="font-size: 1.05rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-building-columns" style="color: #3b82f6;"></i>
                    <span>{{ __('Corporate Bank Accounts') }}</span>
                </h3>
                <a href="{{ route('admin.finance.bank_accounts.index') }}" class="btn btn-xs btn-secondary">{{ __('View All') }}</a>
            </div>

            <div class="table-responsive" style="margin-bottom: 0; border: none; border-radius: 0;">
                <table class="table" style="min-width: 480px;">
                    <thead>
                        <tr>
                            <th>{{ __('Bank / Account') }}</th>
                            <th>{{ __('Account Number') }}</th>
                            <th style="text-align: right;">{{ __('Ledger Balance') }}</th>
                            <th style="text-align: right;">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bankAccounts as $bank)
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-main);">{{ $bank->bank_name }}</div>
                                    <div style="font-size: 0.775rem; color: var(--text-muted);">{{ $bank->account_name }}</div>
                                </td>
                                <td style="font-family: monospace; font-size: 0.85rem;">{{ $bank->account_number }}</td>
                                <td style="text-align: right; font-weight: 800; color: #3b82f6;">
                                    {{ number_format($bank->current_balance, 2) }} {{ $bank->currency }}
                                </td>
                                <td style="text-align: right;">
                                    <a href="{{ route('admin.finance.bank_accounts.show', $bank) }}" class="btn btn-xs btn-secondary">
                                        <i class="fa-solid fa-file-invoice"></i>
                                        <span>{{ __('Statement') }}</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                    {{ __('No bank accounts added yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

