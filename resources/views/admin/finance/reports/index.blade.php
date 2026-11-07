@extends('layouts.app')

@section('title', __('Financial Statements & Reports') . ' - ' . config('app.name'))

@section('content')
<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Header -->
    <div class="card" style="padding: 1.5rem;">
        <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
            <i class="fa-solid fa-file-invoice-dollar" style="color: #ec4899;"></i>
            {{ __('Financial Statements & Audit Reports') }}
        </h1>
        <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.85rem;">
            {{ __('Executive accounting statements generated directly from immutable double-entry journal entries.') }}
        </p>
    </div>

    <!-- Reports Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
        <!-- Profit & Loss -->
        <a href="{{ route('admin.finance.reports.profit_loss') }}" class="card" style="padding: 1.75rem; text-decoration: none; border-left: 4px solid #10b981; transition: transform 0.2s, box-shadow 0.2s;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: var(--text-main);">
                        {{ __('Profit & Loss (Income Statement)') }}
                    </h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.5rem 0 0;">
                        {{ __('Gross revenue, discounts, returns, cost of goods sold (COGS), operating expenses, and net profit margins.') }}
                    </p>
                </div>
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(16, 185, 129, 0.12); display: flex; align-items: center; justify-content: center; color: #10b981;">
                    <i class="fa-solid fa-chart-line" style="font-size: 1.2rem;"></i>
                </div>
            </div>
            <div style="margin-top: 1.25rem; font-weight: 700; color: #10b981; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
                {{ __('Open Statement') }} <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

        <!-- Balance Sheet -->
        <a href="{{ route('admin.finance.reports.balance_sheet') }}" class="card" style="padding: 1.75rem; text-decoration: none; border-left: 4px solid #3b82f6; transition: transform 0.2s, box-shadow 0.2s;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: var(--text-main);">
                        {{ __('Balance Sheet (Financial Position)') }}
                    </h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.5rem 0 0;">
                        {{ __('Statement of Assets, Liabilities, and Equity. Proves the accounting equation: Assets = Liabilities + Equity.') }}
                    </p>
                </div>
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(59, 130, 246, 0.12); display: flex; align-items: center; justify-content: center; color: #3b82f6;">
                    <i class="fa-solid fa-scale-balanced" style="font-size: 1.2rem;"></i>
                </div>
            </div>
            <div style="margin-top: 1.25rem; font-weight: 700; color: #3b82f6; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
                {{ __('Open Statement') }} <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

        <!-- Cash Flow Statement -->
        <a href="{{ route('admin.finance.reports.cash_flow') }}" class="card" style="padding: 1.75rem; text-decoration: none; border-left: 4px solid #06b6d4; transition: transform 0.2s, box-shadow 0.2s;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: var(--text-main);">
                        {{ __('Cash Flow Statement') }}
                    </h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.5rem 0 0;">
                        {{ __('Analysis of cash receipts and cash disbursements across Operating, Investing, and Financing activities.') }}
                    </p>
                </div>
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(6, 182, 212, 0.12); display: flex; align-items: center; justify-content: center; color: #06b6d4;">
                    <i class="fa-solid fa-money-bill-transfer" style="font-size: 1.2rem;"></i>
                </div>
            </div>
            <div style="margin-top: 1.25rem; font-weight: 700; color: #06b6d4; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
                {{ __('Open Statement') }} <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

        <!-- Trial Balance -->
        <a href="{{ route('admin.finance.reports.trial_balance') }}" class="card" style="padding: 1.75rem; text-decoration: none; border-left: 4px solid #8b5cf6; transition: transform 0.2s, box-shadow 0.2s;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: var(--text-main);">
                        {{ __('Trial Balance') }}
                    </h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.5rem 0 0;">
                        {{ __('Comprehensive audit listing of all ledger accounts verifying total debits equal total credits.') }}
                    </p>
                </div>
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(139, 92, 246, 0.12); display: flex; align-items: center; justify-content: center; color: #8b5cf6;">
                    <i class="fa-solid fa-book-journal-whills" style="font-size: 1.2rem;"></i>
                </div>
            </div>
            <div style="margin-top: 1.25rem; font-weight: 700; color: #8b5cf6; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
                {{ __('Open Statement') }} <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

        <!-- Sales Analytics -->
        <a href="{{ route('admin.finance.reports.sales_analytics') }}" class="card" style="padding: 1.75rem; text-decoration: none; border-left: 4px solid #f59e0b; transition: transform 0.2s, box-shadow 0.2s;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: var(--text-main);">
                        {{ __('Multi-Dimensional Sales Analytics') }}
                    </h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.5rem 0 0;">
                        {{ __('Sales revenue breakdown by branch location, payment settlement channels, order types, and trends.') }}
                    </p>
                </div>
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); display: flex; align-items: center; justify-content: center; color: #f59e0b;">
                    <i class="fa-solid fa-chart-pie" style="font-size: 1.2rem;"></i>
                </div>
            </div>
            <div style="margin-top: 1.25rem; font-weight: 700; color: #f59e0b; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
                {{ __('Open Analytics') }} <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>
    </div>
</div>
@endsection

