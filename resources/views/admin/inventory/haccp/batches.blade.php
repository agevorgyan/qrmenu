@extends('layouts.app')

@section('title', __('Inventory Batches & FEFO Expiry') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="max-width: 1200px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(99, 102, 241, 0.15); color: #6366f1; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-boxes-packing"></i>
                </span>
                <span>{{ __('Inventory Batches & Expiry (FEFO / FIFO)') }}</span>
            </h1>
        </div>
    </div>

    <!-- HACCP Sub-Navigation Tabs -->
    <div style="display: flex; gap: 0.5rem; border-bottom: 1px solid var(--border-color); margin-bottom: 1.5rem; overflow-x: auto; padding-bottom: 0.5rem;">
        <a href="{{ route('admin.inventory.haccp.dashboard') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-gauge-high"></i> {{ __('Dashboard') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.temperatures') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-temperature-half"></i> {{ __('Temperature Logs') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.checklists') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-clipboard-check"></i> {{ __('Sanitation Checklists') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.ccps') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-sliders"></i> {{ __('Critical Control Points (CCPs)') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.batches') }}" class="btn btn-sm btn-primary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-boxes-packing"></i> {{ __('Batches & Expiry (FEFO)') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.traceability') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-route"></i> {{ __('Traceability & Recall Audit') }}
        </a>
    </div>

    <!-- Filter Card -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
        <form method="GET" action="{{ route('admin.inventory.haccp.batches') }}" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 220px; position: relative;">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by batch # or ingredient...') }}" class="form-control" style="border-radius: 10px; padding-left: 2.25rem;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
            </div>

            <div style="min-width: 170px;">
                <select name="filter" class="form-select" style="border-radius: 10px;" onchange="this.form.submit()">
                    <option value="">{{ __('All Expiry Statuses') }}</option>
                    <option value="expired" {{ request('filter') === 'expired' ? 'selected' : '' }}>{{ __('Expired (Action Required)') }}</option>
                    <option value="critical" {{ request('filter') === 'critical' ? 'selected' : '' }}>{{ __('Expiring in ≤ 3 Days') }}</option>
                    <option value="warning" {{ request('filter') === 'warning' ? 'selected' : '' }}>{{ __('Expiring in ≤ 7 Days') }}</option>
                </select>
            </div>

            <div style="min-width: 140px;">
                <select name="status" class="form-select" style="border-radius: 10px;" onchange="this.form.submit()">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                    <option value="quarantined" {{ request('status') === 'quarantined' ? 'selected' : '' }}>{{ __('Quarantined') }}</option>
                    <option value="depleted" {{ request('status') === 'depleted' ? 'selected' : '' }}>{{ __('Depleted') }}</option>
                </select>
            </div>

            @if(request()->hasAny(['search', 'filter', 'status']))
                <a href="{{ route('admin.inventory.haccp.batches') }}" class="btn btn-outline-secondary" style="border-radius: 10px;">
                    <i class="fa-solid fa-rotate-left"></i> {{ __('Clear') }}
                </a>
            @endif
        </form>
    </div>

    <!-- Batches Table -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);">
        <div class="table-responsive">
            <table class="table" style="margin: 0; vertical-align: middle;">
                <thead style="background: var(--bg-hover); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);">
                    <tr>
                        <th style="padding: 1rem 1.25rem;">{{ __('Batch #') }}</th>
                        <th style="padding: 1rem;">{{ __('Ingredient / Item') }}</th>
                        <th style="padding: 1rem;">{{ __('Warehouse') }}</th>
                        <th style="padding: 1rem; text-align: right;">{{ __('Remaining / Initial') }}</th>
                        <th style="padding: 1rem; text-align: center;">{{ __('Production Date') }}</th>
                        <th style="padding: 1rem; text-align: center;">{{ __('Expiry Date') }}</th>
                        <th style="padding: 1rem;">{{ __('Source') }}</th>
                        <th style="padding: 1rem 1.25rem; text-align: right;">{{ __('Trace') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batches as $batch)
                        @php
                            $isExpired = $batch->expiry_date && $batch->expiry_date->isPast();
                            $isCritical = $batch->expiry_date && !$isExpired && $batch->expiry_date->diffInDays(now()) <= 3;
                        @endphp
                        <tr style="border-top: 1px solid var(--border-color); background: {{ $isExpired ? 'rgba(239, 68, 68, 0.04)' : ($isCritical ? 'rgba(245, 158, 11, 0.04)' : 'transparent') }};">
                            <td style="padding: 1rem 1.25rem;">
                                <a href="{{ route('admin.inventory.haccp.traceability', ['batch_number' => $batch->batch_number]) }}" style="font-family: monospace; font-weight: 700; color: #6366f1; text-decoration: none;">
                                    {{ $batch->batch_number }}
                                </a>
                            </td>
                            <td style="padding: 1rem;">
                                <div style="font-weight: 600; color: var(--text-main);">{{ $batch->ingredient->name }}</div>
                                <small style="color: var(--text-muted);">{{ $batch->ingredient->sku }}</small>
                            </td>
                            <td style="padding: 1rem;">
                                <div style="font-size: 0.85rem; color: var(--text-main);">{{ $batch->warehouse->name }}</div>
                            </td>
                            <td style="padding: 1rem; text-align: right;">
                                <div style="font-weight: 700; color: var(--text-main);">
                                    {{ number_format((float)$batch->remaining_quantity, 2) }} {{ $batch->unit->code }}
                                </div>
                                <small style="color: var(--text-muted);">
                                    / {{ number_format((float)$batch->initial_quantity, 2) }}
                                </small>
                            </td>
                            <td style="padding: 1rem; text-align: center; font-size: 0.85rem; color: var(--text-muted);">
                                {{ $batch->production_date ? $batch->production_date->toVendorTimezone()->format('M d, Y') : '-' }}
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                @if($batch->expiry_date)
                                    <span class="badge" style="background: {{ $isExpired ? 'rgba(239, 68, 68, 0.15)' : ($isCritical ? 'rgba(245, 158, 11, 0.15)' : 'rgba(16, 185, 129, 0.15)') }}; color: {{ $isExpired ? '#ef4444' : ($isCritical ? '#f59e0b' : '#10b981') }}; font-weight: 700;">
                                        {{ $batch->expiry_date->toVendorTimezone()->format('M d, Y') }}
                                        ({{ $isExpired ? __('EXPIRED') : $batch->expiry_date->diffForHumans() }})
                                    </span>
                                @else
                                    <span style="color: var(--text-muted);">-</span>
                                @endif
                            </td>
                            <td style="padding: 1rem;">
                                <span class="badge" style="background: rgba(107, 114, 128, 0.15); color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase;">
                                    {{ $batch->source_type }}
                                </span>
                            </td>
                            <td style="padding: 1rem 1.25rem; text-align: right;">
                                <a href="{{ route('admin.inventory.haccp.traceability', ['batch_number' => $batch->batch_number]) }}" class="btn btn-sm btn-outline-primary" style="border-radius: 8px;">
                                    <i class="fa-solid fa-route"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                                <i class="fa-solid fa-boxes-packing" style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 1rem; display: block;"></i>
                                <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-main);">{{ __('No batches found') }}</div>
                                <p style="margin: 0.5rem 0 1.25rem;">{{ __('Batches are generated automatically upon purchasing goods or completing production orders.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($batches->hasPages())
            <div style="padding: 1rem; border-top: 1px solid var(--border-color);">
                {{ $batches->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

