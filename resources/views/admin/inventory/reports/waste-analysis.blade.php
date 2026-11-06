@extends('layouts.app')

@section('title', __('Waste & Spoilage Analysis') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <a href="{{ route('admin.inventory.reports.inventory.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px;">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                {{ __('Waste & Spoilage Financial Breakdown') }}
            </h1>
        </div>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0 2.5rem;">
            {{ __('Period') }}: {{ $from->toDateString() }} — {{ $to->toDateString() }}
        </p>
    </div>

    <!-- Date Filter -->
    <form method="GET" action="{{ route('admin.inventory.reports.inventory.waste') }}" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control" style="border-radius: 10px; font-size: 0.85rem; width: 145px;">
        <span style="color: var(--text-muted); font-size: 0.85rem;">{{ __('to') }}</span>
        <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control" style="border-radius: 10px; font-size: 0.85rem; width: 145px;">
        <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 600; padding: 0.5rem 1rem;">
            <i class="fa-solid fa-rotate"></i> {{ __('Apply') }}
        </button>
    </form>
</div>

<!-- Total Loss KPI -->
<div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div>
        <small style="color: #ef4444; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; display: block; margin-bottom: 0.25rem;">
            {{ __('Total Recorded Shrinkage & Waste') }}
        </small>
        <div style="font-size: 2rem; font-weight: 900; color: #ef4444;">
            {{ number_format($waste['total_loss_cost'], 2) }} {{ Auth::user()->vendor->currency }}
        </div>
        <span style="color: var(--text-muted); font-size: 0.85rem;">
            {{ __('From :count registered write-off incident logs', ['count' => $waste['total_records']]) }}
        </span>
    </div>
    <div>
        <a href="{{ route('admin.inventory.waste.create') }}" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; background: #ef4444; border-color: #ef4444; padding: 0.65rem 1.5rem;">
            <i class="fa-solid fa-plus"></i> {{ __('Log New Waste') }}
        </a>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Waste Breakdown by Reason -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);">
        <div style="padding: 1.25rem 1.5rem; background: var(--bg-hover); border-bottom: 1px solid var(--border-color);">
            <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-chart-pie" style="color: #f59e0b;"></i> {{ __('Loss Distribution by Cause') }}
            </h3>
        </div>

        <div style="padding: 1.5rem;">
            @foreach($waste['by_reason'] as $reason => $data)
                <div style="margin-bottom: 1.25rem;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.35rem;">
                        <span style="font-weight: 700; color: var(--text-main); text-transform: capitalize;">
                            {{ str_replace('_', ' ', $reason) }} ({{ $data['count'] }})
                        </span>
                        <span style="font-weight: 800; color: #ef4444;">
                            {{ number_format($data['cost'], 2) }} {{ Auth::user()->vendor->currency }} ({{ $data['percentage'] }}%)
                        </span>
                    </div>
                    <div style="height: 8px; background: var(--bg-hover); border-radius: 4px; overflow: hidden;">
                        <div style="width: {{ $data['percentage'] }}%; height: 100%; background: #ef4444; border-radius: 4px;"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Top 5 Wasted Ingredients -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);">
        <div style="padding: 1.25rem 1.5rem; background: var(--bg-hover); border-bottom: 1px solid var(--border-color);">
            <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-arrow-trend-up" style="color: #ef4444;"></i> {{ __('Top 5 Wasted Raw Materials') }}
            </h3>
        </div>

        <div class="table-responsive">
            <table class="table" style="margin: 0; vertical-align: middle;">
                <thead style="background: var(--bg-hover); font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                    <tr>
                        <th style="padding: 1rem 1.5rem;">{{ __('Item') }}</th>
                        <th style="padding: 1rem; text-align: right;">{{ __('Wasted Qty') }}</th>
                        <th style="padding: 1rem 1.5rem; text-align: right;">{{ __('Financial Cost') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($waste['top_wasted_ingredients'] as $top)
                        <tr style="border-bottom: 1px solid var(--table-row-border);">
                            <td style="padding: 1rem 1.5rem; font-weight: 700; color: var(--text-main);">
                                {{ $top['name'] }}
                            </td>
                            <td style="padding: 1rem; text-align: right; color: var(--text-muted);">
                                {{ number_format($top['quantity'], 2) }} {{ $top['unit'] }}
                            </td>
                            <td style="padding: 1rem 1.5rem; text-align: right; font-weight: 800; color: #ef4444;">
                                {{ number_format($top['cost'], 2) }} {{ Auth::user()->vendor->currency }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" style="padding: 2.5rem; text-align: center; color: var(--text-muted);">
                                {{ __('No waste data recorded for this period.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

