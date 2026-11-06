@extends('layouts.app')

@section('title', __('Inventory Waste & Loss Management') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 0; flex: 1;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(239, 68, 68, 0.15); color: #ef4444; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fa-solid fa-trash-can"></i>
            </span>
            <span>{{ __('Waste & Loss Management') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
            {{ __('Track spoilage, expired items, kitchen prep mistakes, and financial loss with automatic stock ledger depletion.') }}
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        @can('inventory.waste')
            <a href="{{ route('admin.inventory.waste.create') }}" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 700; background: #ef4444; border-color: #ef4444;">
                <i class="fa-solid fa-plus"></i> {{ __('Record Waste Write-Off') }}
            </a>
        @endcan
    </div>
</div>

<!-- KPI Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
    <!-- Total Financial Loss -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(239, 68, 68, 0.12); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-money-bill-trend-up"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Total Loss Value') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #ef4444;">
                {{ number_format($summary['total_loss_cost'], 2) }} {{ Auth::user()->vendor->currency }}
            </div>
        </div>
    </div>

    <!-- Total Records -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(249, 115, 22, 0.12); color: #f97316; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-file-invoice"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Write-Off Logs') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: var(--text-main);">{{ $summary['total_records'] }}</div>
        </div>
    </div>

    <!-- Spoilage Loss -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(168, 85, 247, 0.12); color: #a855f7; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-biohazard"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Spoilage & Expired') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #a855f7;">
                {{ number_format(($summary['by_reason']['spoilage']['cost'] ?? 0) + ($summary['by_reason']['expiration']['cost'] ?? 0), 2) }} {{ Auth::user()->vendor->currency }}
            </div>
        </div>
    </div>

    <!-- Prep & Kitchen Mistakes -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(234, 179, 8, 0.12); color: #eab308; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-kitchen-set"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Prep & Burn Mistakes') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #eab308;">
                {{ number_format(($summary['by_reason']['prep_mistake']['cost'] ?? 0) + ($summary['by_reason']['burn_spill']['cost'] ?? 0), 2) }} {{ Auth::user()->vendor->currency }}
            </div>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
    <form method="GET" action="{{ route('admin.inventory.waste.index') }}" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 160px;">
            <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Reason') }}</label>
            <select name="reason" class="form-select" style="border-radius: 10px;">
                <option value="">{{ __('All Reasons') }}</option>
                <option value="spoilage" {{ request('reason') === 'spoilage' ? 'selected' : '' }}>{{ __('Spoilage') }}</option>
                <option value="expiration" {{ request('reason') === 'expiration' ? 'selected' : '' }}>{{ __('Expiration') }}</option>
                <option value="prep_mistake" {{ request('reason') === 'prep_mistake' ? 'selected' : '' }}>{{ __('Kitchen Prep Mistake') }}</option>
                <option value="burn_spill" {{ request('reason') === 'burn_spill' ? 'selected' : '' }}>{{ __('Burn / Spill') }}</option>
                <option value="damage" {{ request('reason') === 'damage' ? 'selected' : '' }}>{{ __('Damaged Packaging') }}</option>
                <option value="storage_failure" {{ request('reason') === 'storage_failure' ? 'selected' : '' }}>{{ __('Storage / Cooler Failure') }}</option>
                <option value="customer_complaint" {{ request('reason') === 'customer_complaint' ? 'selected' : '' }}>{{ __('Customer Complaint') }}</option>
                <option value="other" {{ request('reason') === 'other' ? 'selected' : '' }}>{{ __('Other') }}</option>
            </select>
        </div>

        <div style="flex: 1; min-width: 160px;">
            <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Warehouse') }}</label>
            <select name="warehouse_id" class="form-select" style="border-radius: 10px;">
                <option value="">{{ __('All Warehouses') }}</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                @endforeach
            </select>
        </div>

        <div style="flex: 1; min-width: 140px;">
            <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('From Date') }}</label>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control" style="border-radius: 10px;">
        </div>

        <div style="flex: 1; min-width: 140px;">
            <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('To Date') }}</label>
            <input type="date" name="to" value="{{ request('to') }}" class="form-control" style="border-radius: 10px;">
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 600; padding: 0.55rem 1.25rem;">
                <i class="fa-solid fa-filter"></i> {{ __('Filter') }}
            </button>
            <a href="{{ route('admin.inventory.waste.index') }}" class="btn btn-outline-secondary" style="border-radius: 10px; font-weight: 600;">
                {{ __('Reset') }}
            </a>
        </div>
    </form>
</div>

<!-- Waste Logs Table -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);">
    <div class="table-responsive">
        <table class="table" style="margin: 0; vertical-align: middle;">
            <thead style="background: var(--bg-hover); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);">
                <tr>
                    <th style="padding: 1rem 1.25rem;">{{ __('Waste #') }}</th>
                    <th style="padding: 1rem;">{{ __('Item / Raw Material') }}</th>
                    <th style="padding: 1rem;">{{ __('Warehouse') }}</th>
                    <th style="padding: 1rem; text-align: right;">{{ __('Quantity') }}</th>
                    <th style="padding: 1rem; text-align: right;">{{ __('Total Loss') }}</th>
                    <th style="padding: 1rem; text-align: center;">{{ __('Reason') }}</th>
                    <th style="padding: 1rem; text-align: center;">{{ __('Action Taken') }}</th>
                    <th style="padding: 1rem 1.25rem; text-align: right;">{{ __('Logged At') }}</th>
                    <th style="padding: 1rem; text-align: center;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr style="border-bottom: 1px solid var(--table-row-border);">
                        <td style="padding: 1rem 1.25rem;">
                            <a href="{{ route('admin.inventory.waste.show', $log) }}" style="font-family: monospace; font-weight: 700; color: #ef4444; text-decoration: none;">
                                {{ $log->waste_number }}
                            </a>
                        </td>
                        <td style="padding: 1rem;">
                            <strong style="color: var(--text-main); display: block;">{{ $log->ingredient->name }}</strong>
                            @if($log->batch)
                                <small style="color: var(--text-muted); font-family: monospace;">Lot: {{ $log->batch->batch_number }}</small>
                            @endif
                        </td>
                        <td style="padding: 1rem; color: var(--text-muted);">
                            {{ $log->warehouse->name }}
                        </td>
                        <td style="padding: 1rem; text-align: right; font-weight: 700; color: var(--text-main);">
                            {{ number_format((float) $log->quantity, 2) }} {{ $log->unit?->symbol ?? 'kg' }}
                        </td>
                        <td style="padding: 1rem; text-align: right; font-weight: 800; color: #ef4444;">
                            {{ number_format((float) $log->total_cost, 2) }} {{ Auth::user()->vendor->currency }}
                        </td>
                        <td style="padding: 1rem; text-align: center;">
                            @php
                                $badgeBg = match($log->reason) {
                                    'spoilage', 'expiration' => 'rgba(239, 68, 68, 0.15)',
                                    'prep_mistake', 'burn_spill' => 'rgba(234, 179, 8, 0.15)',
                                    'damage' => 'rgba(249, 115, 22, 0.15)',
                                    default => 'rgba(100, 116, 139, 0.15)',
                                };
                                $badgeColor = match($log->reason) {
                                    'spoilage', 'expiration' => '#ef4444',
                                    'prep_mistake', 'burn_spill' => '#eab308',
                                    'damage' => '#f97316',
                                    default => '#94a3b8',
                                };
                            @endphp
                            <span class="badge" style="background: {{ $badgeBg }}; color: {{ $badgeColor }}; text-transform: uppercase; font-size: 0.7rem; font-weight: 700; padding: 0.35rem 0.65rem; border-radius: 6px;">
                                {{ str_replace('_', ' ', $log->reason) }}
                            </span>
                        </td>
                        <td style="padding: 1rem; text-align: center;">
                            <span class="badge" style="background: var(--bg-hover); color: var(--text-muted); font-size: 0.7rem; text-transform: capitalize;">
                                {{ str_replace('_', ' ', $log->action_taken) }}
                            </span>
                        </td>
                        <td style="padding: 1rem 1.25rem; text-align: right; color: var(--text-muted); font-size: 0.85rem;">
                            {{ $log->logged_at ? $log->logged_at->format('M d, Y H:i') : $log->created_at->format('M d, Y H:i') }}
                        </td>
                        <td style="padding: 1rem; text-align: center;">
                            <a href="{{ route('admin.inventory.waste.show', $log) }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px;">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                            <i class="fa-solid fa-shield-halved" style="font-size: 2rem; color: #10b981; margin-bottom: 0.5rem; display: block;"></i>
                            {{ __('No waste write-offs recorded in this time range.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
        <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color);">
            {{ $logs->links() }}
        </div>
    @endif
</div>
@endsection

