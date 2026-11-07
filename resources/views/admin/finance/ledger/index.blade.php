@extends('layouts.app')

@section('title', __('Chart of Accounts') . ' - ' . config('app.name'))

@section('content')
<div style="max-width: 1400px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Page Header -->
    <div class="card" style="padding: 1.5rem; margin-bottom: 0;">
        <div class="page-header-flex" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(139, 92, 246, 0.12); color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                    <i class="fa-solid fa-book-journal-whills"></i>
                </div>
                <div>
                    <h1 style="font-size: clamp(1.25rem, 2.5vw, 1.65rem); font-weight: 800; margin: 0; color: var(--text-main); line-height: 1.2;">
                        {{ __('Chart of Accounts (COA)') }}
                    </h1>
                    <p style="margin: 0.35rem 0 0; color: var(--text-muted); font-size: 0.875rem;">
                        {{ __('Standard double-entry chart of accounts (1000 Assets, 2000 Liabilities, 3000 Equity, 4000 Revenue, 5000 Expenses).') }}
                    </p>
                </div>
            </div>

            <div class="page-header-actions" style="display: flex; align-items: center; gap: 0.75rem;">
                <a href="{{ route('admin.finance.ledger.entries') }}" class="btn btn-secondary" style="padding: 0.65rem 1.15rem;">
                    <i class="fa-solid fa-list-check" style="color: #3b82f6;"></i>
                    <span>{{ __('View General Ledger Entries') }}</span>
                </a>
                <form method="POST" action="{{ route('admin.finance.ledger.seed') }}" onsubmit="return confirm('{{ __('Initialize default Chart of Accounts?') }}')">
                    @csrf
                    <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.25rem;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                        <span>{{ __('Initialize Standard COA') }}</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Filter Tabs (Horizontal Scrollable Pills) -->
        <div style="display: flex; gap: 0.5rem; margin-top: 1.25rem; border-top: 1px solid var(--border-color); padding-top: 1rem; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none;">
            <a href="{{ route('admin.finance.ledger.index') }}" class="btn btn-sm {{ !request('type') ? 'btn-primary' : 'btn-secondary' }}" style="white-space: nowrap; flex-shrink: 0;">
                <i class="fa-solid fa-layer-group"></i>
                <span>{{ __('All Accounts') }}</span>
            </a>
            <a href="{{ route('admin.finance.ledger.index', ['type' => 'asset']) }}" class="btn btn-sm {{ request('type') === 'asset' ? 'btn-primary' : 'btn-secondary' }}" style="white-space: nowrap; flex-shrink: 0;">
                <i class="fa-solid fa-vault" style="color: #10b981;"></i>
                <span>{{ __('1000 Assets') }}</span>
            </a>
            <a href="{{ route('admin.finance.ledger.index', ['type' => 'liability']) }}" class="btn btn-sm {{ request('type') === 'liability' ? 'btn-primary' : 'btn-secondary' }}" style="white-space: nowrap; flex-shrink: 0;">
                <i class="fa-solid fa-file-invoice-dollar" style="color: #ef4444;"></i>
                <span>{{ __('2000 Liabilities') }}</span>
            </a>
            <a href="{{ route('admin.finance.ledger.index', ['type' => 'equity']) }}" class="btn btn-sm {{ request('type') === 'equity' ? 'btn-primary' : 'btn-secondary' }}" style="white-space: nowrap; flex-shrink: 0;">
                <i class="fa-solid fa-scale-balanced" style="color: #3b82f6;"></i>
                <span>{{ __('3000 Equity') }}</span>
            </a>
            <a href="{{ route('admin.finance.ledger.index', ['type' => 'revenue']) }}" class="btn btn-sm {{ request('type') === 'revenue' ? 'btn-primary' : 'btn-secondary' }}" style="white-space: nowrap; flex-shrink: 0;">
                <i class="fa-solid fa-chart-line" style="color: #10b981;"></i>
                <span>{{ __('4000 Revenue') }}</span>
            </a>
            <a href="{{ route('admin.finance.ledger.index', ['type' => 'expense']) }}" class="btn btn-sm {{ request('type') === 'expense' ? 'btn-primary' : 'btn-secondary' }}" style="white-space: nowrap; flex-shrink: 0;">
                <i class="fa-solid fa-arrow-trend-down" style="color: #f59e0b;"></i>
                <span>{{ __('5000 Expenses') }}</span>
            </a>
        </div>
    </div>

    <!-- Accounts Table Card -->
    <div class="card card-flush" style="border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-card);">
        <div class="table-responsive" style="margin-bottom: 0; border: none; border-radius: 0;">
            <table class="table" style="min-width: 920px;">
                <thead>
                    <tr>
                        <th style="width: 120px;">{{ __('Account Code') }}</th>
                        <th style="min-width: 250px;">{{ __('Account Name') }}</th>
                        <th style="width: 140px;">{{ __('Type') }}</th>
                        <th style="min-width: 150px;">{{ __('Subtype') }}</th>
                        <th style="width: 140px;">{{ __('Normal Balance') }}</th>
                        <th style="min-width: 150px; text-align: right;">{{ __('Current Balance') }}</th>
                        <th style="width: 90px; text-align: center;">{{ __('System') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $acc)
                        <tr>
                            <td>
                                <span style="font-family: monospace; font-weight: 800; font-size: 0.95rem; background: rgba(0,0,0,0.03); padding: 0.25rem 0.55rem; border-radius: 6px; letter-spacing: 0.05em; color: var(--text-main);">
                                    {{ $acc->code }}
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                    {{ __($acc->name) }}
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">
                                    {{ $acc->name }}
                                </div>
                            </td>
                            <td>
                                @if($acc->type === 'asset')
                                    <span class="badge badge-emerald">{{ __('Asset') }}</span>
                                @elseif($acc->type === 'liability')
                                    <span class="badge badge-rose">{{ __('Liability') }}</span>
                                @elseif($acc->type === 'equity')
                                    <span class="badge badge-blue">{{ __('Equity') }}</span>
                                @elseif($acc->type === 'revenue')
                                    <span class="badge badge-emerald">{{ __('Revenue') }}</span>
                                @else
                                    <span class="badge badge-amber">{{ __('Expense') }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-secondary" style="font-weight: 500; font-size: 0.75rem;">
                                    {{ $acc->subtype }}
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">
                                    {{ $acc->isDebitNormal() ? __('Debit (Dr)') : __('Credit (Cr)') }}
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <span style="font-weight: 800; font-size: 1.05rem; color: {{ $acc->current_balance < 0 ? '#ef4444' : 'var(--text-main)' }};">
                                    {{ number_format($acc->current_balance, 2) }} {{ $acc->currency }}
                                </span>
                            </td>
                            <td style="text-align: center;">
                                @if($acc->is_system)
                                    <span class="badge badge-secondary" title="{{ __('System Managed') }}" style="padding: 0.25rem 0.45rem;">
                                        <i class="fa-solid fa-lock" style="font-size: 0.75rem;"></i>
                                    </span>
                                @else
                                    <span style="color: var(--text-muted);">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 3.5rem 1.5rem;">
                                <div style="width: 54px; height: 54px; border-radius: 16px; background: rgba(139, 92, 246, 0.08); color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1rem;">
                                    <i class="fa-solid fa-book-journal-whills"></i>
                                </div>
                                <div style="font-weight: 700; font-size: 1rem; color: var(--text-main); margin-bottom: 0.25rem;">
                                    {{ __('No chart of accounts found.') }}
                                </div>
                                <p style="font-size: 0.85rem; margin: 0 0 1rem; color: var(--text-muted);">
                                    {{ __('Click Initialize Standard COA to generate standard accounting structure.') }}
                                </p>
                                <form method="POST" action="{{ route('admin.finance.ledger.seed') }}" onsubmit="return confirm('{{ __('Initialize default Chart of Accounts?') }}')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('Initialize Standard COA') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($accounts->hasPages())
            <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); background: var(--bg-card);">
                {{ $accounts->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
