@extends('layouts.app')

@section('title', __('Inventory Movements Ledger') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 0; flex: 1;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(236, 72, 153, 0.15); color: #ec4899; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fa-solid fa-timeline"></i>
            </span>
            <span>{{ __('Inventory Ledger & Movements History') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
            {{ __('Immutable audit trail of all receipts, issues, transfers, waste, and count adjustments.') }}
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="{{ route('admin.inventory.stocks.overview') }}" class="btn btn-secondary" style="border-radius: 12px; font-weight: 600;">
            &larr; {{ __('Current Balances') }}
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
    <form method="GET" action="{{ route('admin.inventory.stocks.movements') }}" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 160px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Transaction Type') }}</label>
            <select name="type" class="form-select" style="width: 100%; border-radius: 10px;">
                <option value="">{{ __('All Types') }}</option>
                <option value="receipt" {{ request('type') === 'receipt' ? 'selected' : '' }}>{{ __('Receipt') }}</option>
                <option value="issue" {{ request('type') === 'issue' ? 'selected' : '' }}>{{ __('Issue') }}</option>
                <option value="transfer" {{ request('type') === 'transfer' ? 'selected' : '' }}>{{ __('Transfer') }}</option>
                <option value="adjustment" {{ request('type') === 'adjustment' ? 'selected' : '' }}>{{ __('Adjustment') }}</option>
                <option value="waste" {{ request('type') === 'waste' ? 'selected' : '' }}>{{ __('Waste / Loss') }}</option>
            </select>
        </div>

        <div style="flex: 1; min-width: 160px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Warehouse') }}</label>
            <select name="warehouse_id" class="form-select" style="width: 100%; border-radius: 10px;">
                <option value="">{{ __('All Warehouses') }}</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ (string) request('warehouse_id') === (string) $wh->id ? 'selected' : '' }}>
                        {{ $wh->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="flex: 1; min-width: 140px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('From Date') }}</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-input" style="width: 100%; border-radius: 10px;">
        </div>

        <div style="flex: 1; min-width: 140px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('To Date') }}</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-input" style="width: 100%; border-radius: 10px;">
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; padding: 0.55rem 1.25rem;">
                <i class="fa-solid fa-filter"></i> {{ __('Filter') }}
            </button>
            @if(request()->hasAny(['type', 'warehouse_id', 'date_from', 'date_to']))
                <a href="{{ route('admin.inventory.stocks.movements') }}" class="btn btn-secondary" style="border-radius: 10px; padding: 0.55rem 1rem;">
                    {{ __('Reset') }}
                </a>
            @endif
        </div>
    </form>
</div>

<!-- Ledger Transactions Table -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);">
    @if($transactions->isEmpty())
        <div style="text-align: center; padding: 3.5rem 1rem; color: var(--text-muted);">
            <i class="fa-solid fa-timeline" style="font-size: 2.5rem; color: var(--text-muted); margin-bottom: 0.75rem;"></i>
            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">{{ __('No transactions found') }}</h3>
            <p style="margin: 0;">{{ __('No inventory movements match the specified filter criteria.') }}</p>
        </div>
    @else
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.02); color: var(--text-muted); text-align: left;">
                        <th style="padding: 0.85rem 1rem;">{{ __('Date') }}</th>
                        <th style="padding: 0.85rem 1rem;">{{ __('Tx Number') }}</th>
                        <th style="padding: 0.85rem 1rem;">{{ __('Type') }}</th>
                        <th style="padding: 0.85rem 1rem;">{{ __('Facility / Direction') }}</th>
                        <th style="padding: 0.85rem 1rem;">{{ __('Items Breakdown') }}</th>
                        <th style="padding: 0.85rem 1rem;">{{ __('User') }}</th>
                        <th style="padding: 0.85rem 1rem; text-align: right;">{{ __('Total Value') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactions as $tx)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.85rem 1rem; color: var(--text-muted); font-size: 0.82rem; white-space: nowrap;">
                                {{ $tx->date ? $tx->date->toVendorTimezone()->format('Y-m-d') : '' }}
                            </td>
                            <td style="padding: 0.85rem 1rem; font-family: monospace; font-weight: 700; color: var(--text-main);">
                                {{ $tx->transaction_number }}
                            </td>
                            <td style="padding: 0.85rem 1rem;">
                                <span style="display: inline-block; padding: 3px 8px; border-radius: 9999px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
                                    background: {{ $tx->type === 'receipt' ? 'rgba(16, 185, 129, 0.15)' : ($tx->type === 'issue' || $tx->type === 'waste' ? 'rgba(239, 68, 68, 0.15)' : 'rgba(59, 130, 246, 0.15)') }};
                                    color: {{ $tx->type === 'receipt' ? '#10b981' : ($tx->type === 'issue' || $tx->type === 'waste' ? '#ef4444' : '#3b82f6') }};">
                                    {{ $tx->type }}
                                </span>
                            </td>
                            <td style="padding: 0.85rem 1rem;">
                                <div style="font-weight: 600; color: var(--text-main);">{{ $tx->warehouse?->name }}</div>
                                @if($tx->targetWarehouse)
                                    <div style="font-size: 0.75rem; color: #3b82f6; display: flex; align-items: center; gap: 0.25rem;">
                                        &rarr; {{ $tx->targetWarehouse->name }}
                                    </div>
                                @endif
                            </td>
                            <td style="padding: 0.85rem 1rem;">
                                @foreach($tx->items as $item)
                                    <div style="font-size: 0.82rem; color: var(--text-main); line-height: 1.4;">
                                        &bull; <strong>{{ $item->ingredient?->name }}</strong>:
                                        <span style="font-family: monospace;">{{ $item->quantity > 0 ? '+'.$item->quantity : $item->quantity }} {{ $item->unit?->code }}</span>
                                        @if((float) $item->unit_cost > 0)
                                            <span style="font-size: 0.75rem; color: var(--text-muted);">(@ {{ number_format((float) $item->unit_cost, 2) }} {{ Auth::user()->vendor->currency }})</span>
                                        @endif
                                    </div>
                                @endforeach
                                @if($tx->notes)
                                    <div style="font-size: 0.75rem; color: var(--text-muted); font-style: italic; margin-top: 0.25rem;">
                                        {{ $tx->notes }}
                                    </div>
                                @endif
                            </td>
                            <td style="padding: 0.85rem 1rem; color: var(--text-muted); font-size: 0.82rem;">
                                {{ $tx->user?->name ?: __('System') }}
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: right; font-weight: 800; color: var(--text-main);">
                                {{ number_format((float) $tx->total_amount, 2) }} {{ Auth::user()->vendor->currency }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($transactions->hasPages())
            <div style="padding: 1rem; border-top: 1px solid var(--border-color);">
                {{ $transactions->links() }}
            </div>
        @endif
    @endif
</div>
@endsection

