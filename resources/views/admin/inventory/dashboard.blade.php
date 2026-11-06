@extends('layouts.app')

@section('title', __('Inventory & Production Dashboard') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 0; flex: 1;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fa-solid fa-boxes-stacked"></i>
            </span>
            <span>{{ __('Inventory & Production') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
            {{ __('Real-time tracking of ingredients, stock balances, multi-warehouse movements, and valuation.') }}
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <a href="{{ route('admin.inventory.stocks.overview') }}" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 700;">
            <i class="fa-solid fa-arrow-right-arrow-left"></i> {{ __('Record Movement') }}
        </a>
        <a href="{{ route('admin.inventory.ingredients.create') }}" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 700;">
            <i class="fa-solid fa-plus"></i> {{ __('New Ingredient') }}
        </a>
    </div>
</div>

<!-- Key Stat Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
    <!-- Total Valuation -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card); position: relative; overflow: hidden;">
        <div style="position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: #10b981;"></div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Total Inventory Value') }}</span>
            <span style="width: 36px; height: 36px; border-radius: 10px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-sack-dollar"></i>
            </span>
        </div>
        <div style="font-size: 1.65rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
            {{ number_format($totalStockValue, 2) }} {{ Auth::user()->vendor->currency ?? 'AMD' }}
        </div>
        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem;">
            {{ __('Across all active warehouses') }}
        </div>
    </div>

    <!-- Total Ingredients -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card); position: relative; overflow: hidden;">
        <div style="position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: #3b82f6;"></div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Active Items & Ingredients') }}</span>
            <span style="width: 36px; height: 36px; border-radius: 10px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-wheat-awn"></i>
            </span>
        </div>
        <div style="font-size: 1.65rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
            {{ $totalIngredients }}
        </div>
        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem;">
            <a href="{{ route('admin.inventory.ingredients.index') }}" style="color: #3b82f6; text-decoration: none; font-weight: 600;">
                {{ __('View Catalog') }} &rarr;
            </a>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card); position: relative; overflow: hidden;">
        <div style="position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: {{ $lowStockCount > 0 ? '#ef4444' : '#10b981' }};"></div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Low Stock Alerts') }}</span>
            <span style="width: 36px; height: 36px; border-radius: 10px; background: {{ $lowStockCount > 0 ? 'rgba(239, 68, 68, 0.15)' : 'rgba(16, 185, 129, 0.15)' }}; color: {{ $lowStockCount > 0 ? '#ef4444' : '#10b981' }}; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </span>
        </div>
        <div style="font-size: 1.65rem; font-weight: 800; color: {{ $lowStockCount > 0 ? '#ef4444' : 'var(--text-main)' }}; font-family: 'Outfit', sans-serif;">
            {{ $lowStockCount }}
        </div>
        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem;">
            @if($lowStockCount > 0)
                <a href="{{ route('admin.inventory.stocks.overview', ['low_stock' => 1]) }}" style="color: #ef4444; font-weight: 700; text-decoration: none;">
                    {{ __('Items requiring restock') }} &rarr;
                </a>
            @else
                <span style="color: #10b981; font-weight: 600;">{{ __('All stock levels normal') }}</span>
            @endif
        </div>
    </div>

    <!-- Warehouses -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card); position: relative; overflow: hidden;">
        <div style="position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: #8b5cf6;"></div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Storage Facilities') }}</span>
            <span style="width: 36px; height: 36px; border-radius: 10px; background: rgba(139, 92, 246, 0.15); color: #8b5cf6; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-warehouse"></i>
            </span>
        </div>
        <div style="font-size: 1.65rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif;">
            {{ $totalWarehouses }}
        </div>
        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem;">
            <a href="{{ route('admin.inventory.warehouses.index') }}" style="color: #8b5cf6; text-decoration: none; font-weight: 600;">
                {{ __('Manage Warehouses') }} &rarr;
            </a>
        </div>
    </div>
</div>

<!-- Two Columns: Low Stock Table & Recent Ledger Transactions -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 1.5rem;">
    <!-- Critical Items List -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; box-shadow: var(--shadow-card);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-triangle-exclamation" style="color: #ef4444;"></i>
                <span>{{ __('Critical / Low Stock Items') }}</span>
            </h3>
            <a href="{{ route('admin.inventory.stocks.overview', ['low_stock' => 1]) }}" style="font-size: 0.82rem; color: var(--primary); font-weight: 700; text-decoration: none;">
                {{ __('View All') }}
            </a>
        </div>

        @if($lowStockItems->isEmpty())
            <div style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
                <i class="fa-solid fa-circle-check" style="font-size: 2rem; color: #10b981; margin-bottom: 0.5rem;"></i>
                <p style="margin: 0; font-weight: 600;">{{ __('No low stock alerts at this moment.') }}</p>
            </div>
        @else
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-muted); text-align: left;">
                            <th style="padding: 0.6rem 0.5rem;">{{ __('Item') }}</th>
                            <th style="padding: 0.6rem 0.5rem; text-align: right;">{{ __('Available') }}</th>
                            <th style="padding: 0.6rem 0.5rem; text-align: right;">{{ __('Min Threshold') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lowStockItems as $item)
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.75rem 0.5rem; font-weight: 600; color: var(--text-main);">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span>{{ $item->name }}</span>
                                        @if($item->sku)
                                            <span style="font-size: 0.7rem; color: var(--text-muted); background: var(--border-color); padding: 1px 5px; border-radius: 4px;">{{ $item->sku }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: right; color: #ef4444; font-weight: 800;">
                                    {{ number_format($item->total_stock_available, 2) }} {{ $item->unit?->code }}
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: right; color: var(--text-muted);">
                                    {{ number_format((float) $item->min_stock, 2) }} {{ $item->unit?->code }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Recent Stock Movements -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; box-shadow: var(--shadow-card);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-clock-rotate-left" style="color: #6366f1;"></i>
                <span>{{ __('Recent Stock Movements') }}</span>
            </h3>
            <a href="{{ route('admin.inventory.stocks.movements') }}" style="font-size: 0.82rem; color: var(--primary); font-weight: 700; text-decoration: none;">
                {{ __('Full Ledger') }}
            </a>
        </div>

        @if($recentTransactions->isEmpty())
            <div style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
                <i class="fa-solid fa-inbox" style="font-size: 2rem; color: var(--text-muted); margin-bottom: 0.5rem;"></i>
                <p style="margin: 0;">{{ __('No stock movements recorded yet.') }}</p>
            </div>
        @else
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-muted); text-align: left;">
                            <th style="padding: 0.6rem 0.5rem;">{{ __('Tx Number') }}</th>
                            <th style="padding: 0.6rem 0.5rem;">{{ __('Type') }}</th>
                            <th style="padding: 0.6rem 0.5rem;">{{ __('Warehouse') }}</th>
                            <th style="padding: 0.6rem 0.5rem; text-align: right;">{{ __('Value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentTransactions as $tx)
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.75rem 0.5rem; font-weight: 700; color: var(--text-main); font-family: monospace;">
                                    {{ $tx->transaction_number }}
                                </td>
                                <td style="padding: 0.75rem 0.5rem;">
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
                                        background: {{ $tx->type === 'receipt' ? 'rgba(16, 185, 129, 0.15)' : ($tx->type === 'issue' || $tx->type === 'waste' ? 'rgba(239, 68, 68, 0.15)' : 'rgba(59, 130, 246, 0.15)') }};
                                        color: {{ $tx->type === 'receipt' ? '#10b981' : ($tx->type === 'issue' || $tx->type === 'waste' ? '#ef4444' : '#3b82f6') }};">
                                        {{ $tx->type }}
                                    </span>
                                </td>
                                <td style="padding: 0.75rem 0.5rem; color: var(--text-muted);">
                                    {{ $tx->warehouse?->name }}
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 700; color: var(--text-main);">
                                    {{ number_format((float) $tx->total_amount, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

