@extends('layouts.app')

@section('title', __('Inventory Turnover & Dead Stock') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <a href="{{ route('admin.inventory.reports.inventory.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px;">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                {{ __('Inventory Turnover & Dead Stock Analysis') }}
            </h1>
        </div>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0 2.5rem;">
            {{ __('Measure inventory turnover velocity, Days Inventory Outstanding (DIO), and identify idle capital trapped in slow-moving stock.') }}
        </p>
    </div>

    <!-- Time Window Selector -->
    <div style="display: flex; gap: 0.35rem; background: var(--bg-card); border: 1px solid var(--border-color); padding: 0.35rem; border-radius: 12px;">
        @foreach([15, 30, 60, 90] as $d)
            <a href="{{ route('admin.inventory.reports.inventory.turnover', ['days' => $d]) }}"
               class="btn btn-sm {{ $days === $d ? 'btn-primary' : 'btn-outline-secondary' }}"
               style="border-radius: 8px; font-weight: 700; border: none; padding: 0.4rem 0.85rem;">
                {{ $d }} {{ __('Days') }}
            </a>
        @endforeach
    </div>
</div>

<!-- Turnover Metrics -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
    <!-- COGS -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
        <small style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 0.25rem;">
            {{ __('Cost of Goods Consumed (COGS)') }}
        </small>
        <div style="font-size: 1.4rem; font-weight: 800; color: var(--text-main);">
            {{ number_format($turnover['cogs'], 2) }} {{ Auth::user()->vendor->currency }}
        </div>
        <small style="color: var(--text-muted);">{{ __('Last') }} {{ $days }} {{ __('days') }}</small>
    </div>

    <!-- Current Valuation -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
        <small style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 0.25rem;">
            {{ __('Average Stock Valuation') }}
        </small>
        <div style="font-size: 1.4rem; font-weight: 800; color: var(--text-main);">
            {{ number_format($turnover['inventory_value'], 2) }} {{ Auth::user()->vendor->currency }}
        </div>
        <small style="color: var(--text-muted);">{{ __('Across all warehouses') }}</small>
    </div>

    <!-- Turnover Ratio -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
        <small style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 0.25rem;">
            {{ __('Turnover Ratio') }}
        </small>
        <div style="font-size: 1.4rem; font-weight: 800; color: #10b981;">
            {{ $turnover['turnover_ratio'] }}x
        </div>
        <small style="color: var(--text-muted);">{{ __('Stock turns in period') }}</small>
    </div>

    <!-- Days Inventory Outstanding (DIO) -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
        <small style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 0.25rem;">
            {{ __('Days Inventory Outstanding (DIO)') }}
        </small>
        <div style="font-size: 1.4rem; font-weight: 800; color: #f59e0b;">
            {{ $turnover['days_inventory_outstanding'] }} {{ __('days') }}
        </div>
        <small style="color: var(--text-muted);">{{ __('Average days to deplete stock') }}</small>
    </div>
</div>

<!-- Dead Stock Section -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);">
    <div style="padding: 1.25rem 1.5rem; background: var(--bg-hover); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-hourglass-end" style="color: #ef4444;"></i> {{ __('Identified Dead Stock / Non-Moving Ingredients') }}
            </h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0.25rem 0 0;">
                {{ __('Items currently in physical stock that have had ZERO kitchen issues or recipe sales over the last :days days.', ['days' => $days]) }}
            </p>
        </div>
        <div style="text-align: right;">
            <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; font-size: 0.85rem; font-weight: 800; padding: 0.4rem 0.8rem; border-radius: 8px;">
                {{ $turnover['dead_stock_count'] }} {{ __('Idle Items') }} &bull; {{ number_format($turnover['dead_stock_value'], 2) }} {{ Auth::user()->vendor->currency }}
            </span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table" style="margin: 0; vertical-align: middle;">
            <thead style="background: var(--bg-hover); font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                <tr>
                    <th style="padding: 1rem 1.5rem;">{{ __('Ingredient') }}</th>
                    <th style="padding: 1rem; text-align: right;">{{ __('Trapped Physical Stock') }}</th>
                    <th style="padding: 1rem; text-align: right;">{{ __('Trapped Capital Value') }}</th>
                    <th style="padding: 1rem 1.5rem; text-align: center;">{{ __('Recommended Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($turnover['dead_stock_items'] as $item)
                    <tr style="border-bottom: 1px solid var(--table-row-border);">
                        <td style="padding: 1rem 1.5rem;">
                            <strong style="color: var(--text-main); display: block;">{{ $item['name'] }}</strong>
                            <small style="color: var(--text-muted); font-family: monospace;">{{ __('SKU') }}: {{ $item['sku'] ?? __('N/A') }}</small>
                        </td>
                        <td style="padding: 1rem; text-align: right; font-weight: 700; color: var(--text-main);">
                            {{ number_format($item['stock'], 2) }} {{ $item['unit'] }}
                        </td>
                        <td style="padding: 1rem; text-align: right; font-weight: 800; color: #ef4444;">
                            {{ number_format($item['value'], 2) }} {{ Auth::user()->vendor->currency }}
                        </td>
                        <td style="padding: 1rem 1.5rem; text-align: center;">
                            <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; font-weight: 700; font-size: 0.75rem; padding: 0.35rem 0.75rem; border-radius: 6px;">
                                {{ __('Run Chef Special / Feature on QR Menu') }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                            <i class="fa-solid fa-circle-check" style="font-size: 2rem; color: #10b981; margin-bottom: 0.5rem; display: block;"></i>
                            {{ __('Healthy stock velocity! Zero dead stock detected in this timeframe.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

