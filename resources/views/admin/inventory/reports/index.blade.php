@extends('layouts.app')

@section('title', __('Inventory & Costing Reports') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 0; flex: 1;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(99, 102, 241, 0.15); color: #6366f1; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fa-solid fa-chart-pie"></i>
            </span>
            <span>{{ __('Inventory & Costing Intelligence') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
            {{ __('Executive analytics: Actual vs Theoretical food cost variance, ABC Pareto classification, stock turnover, and waste attribution.') }}
        </p>
    </div>
</div>

<!-- Quick Navigation Report Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- 1. Actual vs Theoretical Food Cost -->
    <a href="{{ route('admin.inventory.reports.inventory.food-cost') }}" class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; text-decoration: none; transition: transform 0.2s, border-color 0.2s; box-shadow: var(--shadow-card);" onmouseover="this.style.borderColor='#6366f1'" onmouseout="this.style.borderColor='var(--border-color)'">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(99, 102, 241, 0.12); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                <i class="fa-solid fa-scale-balanced"></i>
            </div>
            <span class="badge" style="background: rgba(99, 102, 241, 0.12); color: #6366f1; font-weight: 700;">
                {{ __('Cost Variance') }}
            </span>
        </div>
        <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">
            {{ __('Actual vs. Theoretical Food Cost') }}
        </h3>
        <p style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.5; margin-bottom: 1rem;">
            {{ __('Compare standard BOM recipe costs against actual physical stock ledger deductions to detect portioning drift, over-pouring, or unrecorded waste.') }}
        </p>
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; font-weight: 700; color: #6366f1;">
            <span>{{ __('View Cost Variance') }}</span>
            <i class="fa-solid fa-arrow-right"></i>
        </div>
    </a>

    <!-- 2. ABC Inventory Analysis -->
    <a href="{{ route('admin.inventory.reports.inventory.abc-analysis') }}" class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; text-decoration: none; transition: transform 0.2s, border-color 0.2s; box-shadow: var(--shadow-card);" onmouseover="this.style.borderColor='#10b981'" onmouseout="this.style.borderColor='var(--border-color)'">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                <i class="fa-solid fa-chart-column"></i>
            </div>
            <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #10b981; font-weight: 700;">
                {{ __('Pareto 80/20') }}
            </span>
        </div>
        <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">
            {{ __('ABC Inventory Classification') }}
        </h3>
        <p style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.5; margin-bottom: 1rem;">
            {{ __('Categorize ingredients into Class A (high value, top 80%), Class B (15%), and Class C (5%) to optimize procurement controls and cycle counts.') }}
        </p>
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; font-weight: 700; color: #10b981;">
            <span>{{ __('View ABC Classification') }}</span>
            <i class="fa-solid fa-arrow-right"></i>
        </div>
    </a>

    <!-- 3. Turnover & Dead Stock -->
    <a href="{{ route('admin.inventory.reports.inventory.turnover') }}" class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; text-decoration: none; transition: transform 0.2s, border-color 0.2s; box-shadow: var(--shadow-card);" onmouseover="this.style.borderColor='#f59e0b'" onmouseout="this.style.borderColor='var(--border-color)'">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                <i class="fa-solid fa-arrows-rotate"></i>
            </div>
            <span class="badge" style="background: rgba(245, 158, 11, 0.12); color: #f59e0b; font-weight: 700;">
                {{ __('Turnover & DIO') }}
            </span>
        </div>
        <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">
            {{ __('Turnover & Dead Stock') }}
        </h3>
        <p style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.5; margin-bottom: 1rem;">
            {{ __('Measure inventory velocity, Days Inventory Outstanding (DIO), and pinpoint dead stock tying up operating cash.') }}
        </p>
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; font-weight: 700; color: #f59e0b;">
            <span>{{ __('View Velocity & Dead Stock') }}</span>
            <i class="fa-solid fa-arrow-right"></i>
        </div>
    </a>

    <!-- 4. Waste & Spoilage Analysis -->
    <a href="{{ route('admin.inventory.reports.inventory.waste') }}" class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; text-decoration: none; transition: transform 0.2s, border-color 0.2s; box-shadow: var(--shadow-card);" onmouseover="this.style.borderColor='#ef4444'" onmouseout="this.style.borderColor='var(--border-color)'">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(239, 68, 68, 0.12); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                <i class="fa-solid fa-trash-can"></i>
            </div>
            <span class="badge" style="background: rgba(239, 68, 68, 0.12); color: #ef4444; font-weight: 700;">
                {{ __('Waste Breakdown') }}
            </span>
        </div>
        <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">
            {{ __('Waste & Spoilage Breakdown') }}
        </h3>
        <p style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.5; margin-bottom: 1rem;">
            {{ __('Analyze financial losses by reason (spoilage, expiration, burns, prep mistakes) and identify the top 5 wasted raw materials.') }}
        </p>
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; font-weight: 700; color: #ef4444;">
            <span>{{ __('View Waste Analysis') }}</span>
            <i class="fa-solid fa-arrow-right"></i>
        </div>
    </a>
</div>

<!-- Snapshot KPI Overview -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; box-shadow: var(--shadow-card);">
    <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fa-solid fa-bolt" style="color: #6366f1;"></i> {{ __('Current Month Executive Snapshot') }}
    </h3>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem;">
        <div style="background: var(--bg-hover); padding: 1.25rem; border-radius: 12px;">
            <small style="color: var(--text-muted); font-weight: 700; text-transform: uppercase; font-size: 0.75rem; display: block; margin-bottom: 0.25rem;">{{ __('Total Inventory Valuation') }}</small>
            <div style="font-size: 1.4rem; font-weight: 800; color: var(--text-main);">
                {{ number_format($turnover['inventory_value'], 2) }} {{ Auth::user()->vendor->currency }}
            </div>
        </div>

        <div style="background: var(--bg-hover); padding: 1.25rem; border-radius: 12px;">
            <small style="color: var(--text-muted); font-weight: 700; text-transform: uppercase; font-size: 0.75rem; display: block; margin-bottom: 0.25rem;">{{ __('Theoretical Cost') }}</small>
            <div style="font-size: 1.4rem; font-weight: 800; color: #3b82f6;">
                {{ number_format($variance['theoretical_food_cost'], 2) }} {{ Auth::user()->vendor->currency }}
            </div>
            <small style="color: var(--text-muted);">{{ __(':percent% of revenue', ['percent' => $variance['theoretical_cost_percentage']]) }}</small>
        </div>

        <div style="background: var(--bg-hover); padding: 1.25rem; border-radius: 12px;">
            <small style="color: var(--text-muted); font-weight: 700; text-transform: uppercase; font-size: 0.75rem; display: block; margin-bottom: 0.25rem;">{{ __('Actual Consumed Cost') }}</small>
            <div style="font-size: 1.4rem; font-weight: 800; color: {{ $variance['cost_variance'] > 0 ? '#ef4444' : '#10b981' }};">
                {{ number_format($variance['actual_food_cost'], 2) }} {{ Auth::user()->vendor->currency }}
            </div>
            <small style="color: var(--text-muted);">{{ __(':percent% of revenue', ['percent' => $variance['actual_cost_percentage']]) }}</small>
        </div>

        <div style="background: var(--bg-hover); padding: 1.25rem; border-radius: 12px;">
            <small style="color: var(--text-muted); font-weight: 700; text-transform: uppercase; font-size: 0.75rem; display: block; margin-bottom: 0.25rem;">{{ __('Total Recorded Waste') }}</small>
            <div style="font-size: 1.4rem; font-weight: 800; color: #ef4444;">
                {{ number_format($waste['total_loss_cost'], 2) }} {{ Auth::user()->vendor->currency }}
            </div>
            <small style="color: var(--text-muted);">{{ __(':count write-off incidents', ['count' => $waste['total_records']]) }}</small>
        </div>
    </div>
</div>
@endsection

