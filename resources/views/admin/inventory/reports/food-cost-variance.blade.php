@extends('layouts.app')

@section('title', __('Actual vs Theoretical Food Cost') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <a href="{{ route('admin.inventory.reports.inventory.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px;">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                {{ __('Actual vs. Theoretical Food Cost Variance') }}
            </h1>
        </div>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0 2.5rem;">
            {{ __('Period') }}: {{ $report['period']['from'] }} — {{ $report['period']['to'] }}
        </p>
    </div>

    <!-- Date Range Filter -->
    <form method="GET" action="{{ route('admin.inventory.reports.inventory.food-cost') }}" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <input type="date" name="from" value="{{ $report['period']['from'] }}" class="form-control" style="border-radius: 10px; font-size: 0.85rem; width: 145px;">
        <span style="color: var(--text-muted); font-size: 0.85rem;">{{ __('to') }}</span>
        <input type="date" name="to" value="{{ $report['period']['to'] }}" class="form-control" style="border-radius: 10px; font-size: 0.85rem; width: 145px;">
        <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 600; padding: 0.5rem 1rem;">
            <i class="fa-solid fa-rotate"></i> {{ __('Apply') }}
        </button>
    </form>
</div>

<!-- Comparison KPIs -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
    <!-- Restaurant Revenue -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
        <small style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 0.25rem;">
            {{ __('Food & Beverage Sales') }}
        </small>
        <div style="font-size: 1.4rem; font-weight: 800; color: var(--text-main);">
            {{ number_format($report['total_revenue'], 2) }} {{ Auth::user()->vendor->currency }}
        </div>
    </div>

    <!-- Theoretical Food Cost -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
        <small style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 0.25rem;">
            {{ __('Theoretical Cost (Standard BOM)') }}
        </small>
        <div style="font-size: 1.4rem; font-weight: 800; color: #3b82f6;">
            {{ number_format($report['theoretical_food_cost'], 2) }} {{ Auth::user()->vendor->currency }}
        </div>
        <small style="color: var(--text-muted);">{{ $report['theoretical_cost_percentage'] }}% {{ __('of sales') }}</small>
    </div>

    <!-- Actual Consumed Food Cost -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
        <small style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 0.25rem;">
            {{ __('Actual Consumed (Ledger)') }}
        </small>
        <div style="font-size: 1.4rem; font-weight: 800; color: {{ $report['cost_variance'] > 0 ? '#ef4444' : '#10b981' }};">
            {{ number_format($report['actual_food_cost'], 2) }} {{ Auth::user()->vendor->currency }}
        </div>
        <small style="color: var(--text-muted);">{{ $report['actual_cost_percentage'] }}% {{ __('of sales') }}</small>
    </div>

    <!-- Variance (AMD and %) -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
        <small style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 0.25rem;">
            {{ __('Variance (Over / Under)') }}
        </small>
        <div style="font-size: 1.4rem; font-weight: 800; color: {{ $report['cost_variance'] > 0 ? '#ef4444' : '#10b981' }};">
            {{ $report['cost_variance'] > 0 ? '+' : '' }}{{ number_format($report['cost_variance'], 2) }} {{ Auth::user()->vendor->currency }}
        </div>
        <small style="font-weight: 700; color: {{ $report['cost_variance'] > 0 ? '#ef4444' : '#10b981' }};">
            {{ $report['cost_variance_percentage'] > 0 ? '+' : '' }}{{ $report['cost_variance_percentage'] }}%
        </small>
    </div>
</div>

<!-- Cost Variance Insight Banner -->
@if($report['cost_variance'] > 0)
    <div class="alert alert-warning" style="border-radius: 14px; margin-bottom: 2rem; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); color: var(--text-main);">
        <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
            <i class="fa-solid fa-triangle-exclamation" style="color: #ef4444; font-size: 1.25rem; margin-top: 0.15rem;"></i>
            <div>
                <strong style="color: #ef4444; display: block; margin-bottom: 0.25rem;">{{ __('Food Cost Overrun Detected') }}</strong>
                <span style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.5;">
                    {{ __('Actual inventory consumption is :amount :currency higher than standard technical card expectations. Recommended checks: verify portion weights in kitchen prep, check for unrecorded cooking spills or staff meals, and inspect trash bins for unrecorded waste write-offs.', ['amount' => number_format($report['cost_variance'], 2), 'currency' => Auth::user()->vendor->currency]) }}
                </span>
            </div>
        </div>
    </div>
@else
    <div class="alert alert-success" style="border-radius: 14px; margin-bottom: 2rem; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); color: var(--text-main);">
        <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
            <i class="fa-solid fa-circle-check" style="color: #10b981; font-size: 1.25rem; margin-top: 0.15rem;"></i>
            <div>
                <strong style="color: #10b981; display: block; margin-bottom: 0.25rem;">{{ __('Food Cost Variance Within Strict Target') }}</strong>
                <span style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.5;">
                    {{ __('Your actual kitchen inventory consumption closely adheres to technical recipe cards with no excessive shrinkage detected.') }}
                </span>
            </div>
        </div>
    </div>
@endif

<!-- Top Dishes Sold & Theoretical Cost Breakdown -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);">
    <div style="padding: 1.25rem 1.5rem; background: var(--bg-hover); border-bottom: 1px solid var(--border-color);">
        <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-utensils" style="color: #3b82f6;"></i> {{ __('Top Dishes by Consumption & Theoretical Food Cost') }}
        </h3>
    </div>

    <div class="table-responsive">
        <table class="table" style="margin: 0; vertical-align: middle;">
            <thead style="background: var(--bg-hover); font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                <tr>
                    <th style="padding: 1rem 1.5rem;">{{ __('Dish / Menu Item') }}</th>
                    <th style="padding: 1rem; text-align: center;">{{ __('Portions Sold') }}</th>
                    <th style="padding: 1rem; text-align: right;">{{ __('Sales Revenue') }}</th>
                    <th style="padding: 1rem; text-align: right;">{{ __('Theoretical Cost') }}</th>
                    <th style="padding: 1rem 1.5rem; text-align: right;">{{ __('Food Cost %') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($report['top_variance_dishes'] as $dish)
                    <tr style="border-bottom: 1px solid var(--table-row-border);">
                        <td style="padding: 1rem 1.5rem; font-weight: 700; color: var(--text-main);">
                            {{ $dish['name'] }}
                        </td>
                        <td style="padding: 1rem; text-align: center; font-weight: 700;">
                            {{ $dish['sold_count'] }}
                        </td>
                        <td style="padding: 1rem; text-align: right; font-weight: 600; color: var(--text-main);">
                            {{ number_format($dish['revenue'], 2) }} {{ Auth::user()->vendor->currency }}
                        </td>
                        <td style="padding: 1rem; text-align: right; font-weight: 700; color: #3b82f6;">
                            {{ number_format($dish['theoretical_cost'], 2) }} {{ Auth::user()->vendor->currency }}
                        </td>
                        <td style="padding: 1rem 1.5rem; text-align: right; font-weight: 800;">
                            @php
                                $fcPercent = $dish['revenue'] > 0 ? round(($dish['theoretical_cost'] / $dish['revenue']) * 100, 1) : 0;
                            @endphp
                            <span style="color: {{ $fcPercent > 35 ? '#ef4444' : ($fcPercent > 28 ? '#f59e0b' : '#10b981') }};">
                                {{ $fcPercent }}%
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                            {{ __('No menu item sales recorded in this period.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

