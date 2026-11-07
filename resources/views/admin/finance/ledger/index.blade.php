@extends('layouts.app')

@section('title', __('Chart of Accounts') . ' - ' . config('app.name'))

@section('content')
<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Header -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-book-journal-whills" style="color: #8b5cf6;"></i>
                    {{ __('Chart of Accounts (COA)') }}
                </h1>
                <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.85rem;">
                    {{ __('Standard double-entry chart of accounts (1000 Assets, 2000 Liabilities, 3000 Equity, 4000 Revenue, 5000 Expenses).') }}
                </p>
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <a href="{{ route('admin.finance.ledger.entries') }}" class="btn btn-secondary">
                    <i class="fa-solid fa-list-check"></i> {{ __('View General Ledger Entries') }}
                </a>
                <form method="POST" action="{{ route('admin.finance.ledger.seed') }}" onsubmit="return confirm('Initialize default Chart of Accounts?')">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('Initialize Standard COA') }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div style="display: flex; gap: 0.5rem; margin-top: 1.25rem; border-top: 1px solid var(--border-color); padding-top: 1rem; overflow-x: auto;">
            <a href="{{ route('admin.finance.ledger.index') }}" class="btn btn-sm {{ !request('type') ? 'btn-primary' : 'btn-secondary' }}">
                {{ __('All Accounts') }}
            </a>
            <a href="{{ route('admin.finance.ledger.index', ['type' => 'asset']) }}" class="btn btn-sm {{ request('type') === 'asset' ? 'btn-primary' : 'btn-secondary' }}">
                {{ __('1000 Assets') }}
            </a>
            <a href="{{ route('admin.finance.ledger.index', ['type' => 'liability']) }}" class="btn btn-sm {{ request('type') === 'liability' ? 'btn-primary' : 'btn-secondary' }}">
                {{ __('2000 Liabilities') }}
            </a>
            <a href="{{ route('admin.finance.ledger.index', ['type' => 'equity']) }}" class="btn btn-sm {{ request('type') === 'equity' ? 'btn-primary' : 'btn-secondary' }}">
                {{ __('3000 Equity') }}
            </a>
            <a href="{{ route('admin.finance.ledger.index', ['type' => 'revenue']) }}" class="btn btn-sm {{ request('type') === 'revenue' ? 'btn-primary' : 'btn-secondary' }}">
                {{ __('4000 Revenue') }}
            </a>
            <a href="{{ route('admin.finance.ledger.index', ['type' => 'expense']) }}" class="btn btn-sm {{ request('type') === 'expense' ? 'btn-primary' : 'btn-secondary' }}">
                {{ __('5000 Expenses') }}
            </a>
        </div>
    </div>

    <!-- Accounts Table -->
    <div class="card" style="padding: 1.5rem;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Account Code') }}</th>
                        <th>{{ __('Account Name') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Subtype') }}</th>
                        <th>{{ __('Normal Balance') }}</th>
                        <th style="text-align: right;">{{ __('Current Balance') }}</th>
                        <th>{{ __('System') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $acc)
                        <tr>
                            <td style="font-family: monospace; font-weight: 800; font-size: 0.95rem;">{{ $acc->code }}</td>
                            <td style="font-weight: 700; color: var(--text-main);">{{ $acc->name }}</td>
                            <td>
                                @if($acc->type === 'asset')
                                    <span class="badge badge-emerald">{{ __('Asset') }}</span>
                                @elseif($acc->type === 'liability')
                                    <span class="badge badge-rose">{{ __('Liability') }}</span>
                                @elseif($acc->type === 'equity')
                                    <span class="badge badge-primary">{{ __('Equity') }}</span>
                                @elseif($acc->type === 'revenue')
                                    <span class="badge badge-emerald">{{ __('Revenue') }}</span>
                                @else
                                    <span class="badge badge-amber">{{ __('Expense') }}</span>
                                @endif
                            </td>
                            <td><span style="font-size: 0.8rem; color: var(--text-muted);">{{ $acc->subtype }}</span></td>
                            <td style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">
                                {{ $acc->isDebitNormal() ? __('Debit (Dr)') : __('Credit (Cr)') }}
                            </td>
                            <td style="text-align: right; font-weight: 800; font-size: 1.05rem;">
                                {{ number_format($acc->current_balance, 2) }} {{ $acc->currency }}
                            </td>
                            <td>
                                @if($acc->is_system)
                                    <span class="badge badge-secondary" title="{{ __('System Managed') }}"><i class="fa-solid fa-lock"></i></span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                                {{ __('No chart of accounts found. Click Initialize Standard COA to generate standard accounting structure.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1rem;">
            {{ $accounts->links() }}
        </div>
    </div>
</div>
@endsection

