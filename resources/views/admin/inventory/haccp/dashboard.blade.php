@extends('layouts.app')

@section('title', __('HACCP & Food Safety Management') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 0; flex: 1;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fa-solid fa-shield-virus"></i>
            </span>
            <span>{{ __('HACCP & Food Safety Management') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
            {{ __('Critical Control Points (CCPs), digital temperature monitoring, FEFO expiry tracking, sanitation checklists and bidirectional product traceability.') }}
        </p>
    </div>
</div>

<!-- HACCP Sub-Navigation Tabs -->
<div style="display: flex; gap: 0.5rem; border-bottom: 1px solid var(--border-color); margin-bottom: 1.5rem; overflow-x: auto; padding-bottom: 0.5rem;">
    <a href="{{ route('admin.inventory.haccp.dashboard') }}" class="btn btn-sm {{ request()->routeIs('admin.inventory.haccp.dashboard') ? 'btn-primary' : 'btn-outline-secondary' }}" style="border-radius: 8px; font-weight: 600;">
        <i class="fa-solid fa-gauge-high"></i> {{ __('Dashboard') }}
    </a>
    <a href="{{ route('admin.inventory.haccp.temperatures') }}" class="btn btn-sm {{ request()->routeIs('admin.inventory.haccp.temperatures*') ? 'btn-primary' : 'btn-outline-secondary' }}" style="border-radius: 8px; font-weight: 600;">
        <i class="fa-solid fa-temperature-half"></i> {{ __('Temperature Logs') }}
    </a>
    <a href="{{ route('admin.inventory.haccp.checklists') }}" class="btn btn-sm {{ request()->routeIs('admin.inventory.haccp.checklists*') ? 'btn-primary' : 'btn-outline-secondary' }}" style="border-radius: 8px; font-weight: 600;">
        <i class="fa-solid fa-clipboard-check"></i> {{ __('Sanitation Checklists') }}
    </a>
    <a href="{{ route('admin.inventory.haccp.ccps') }}" class="btn btn-sm {{ request()->routeIs('admin.inventory.haccp.ccps*') ? 'btn-primary' : 'btn-outline-secondary' }}" style="border-radius: 8px; font-weight: 600;">
        <i class="fa-solid fa-sliders"></i> {{ __('Critical Control Points (CCPs)') }}
    </a>
    <a href="{{ route('admin.inventory.haccp.batches') }}" class="btn btn-sm {{ request()->routeIs('admin.inventory.haccp.batches*') ? 'btn-primary' : 'btn-outline-secondary' }}" style="border-radius: 8px; font-weight: 600;">
        <i class="fa-solid fa-boxes-packing"></i> {{ __('Batches & Expiry (FEFO)') }}
    </a>
    <a href="{{ route('admin.inventory.haccp.traceability') }}" class="btn btn-sm {{ request()->routeIs('admin.inventory.haccp.traceability*') ? 'btn-primary' : 'btn-outline-secondary' }}" style="border-radius: 8px; font-weight: 600;">
        <i class="fa-solid fa-route"></i> {{ __('Traceability & Recall Audit') }}
    </a>
</div>

<!-- Alert Banners -->
@if(session('success'))
    <div class="alert alert-success" style="border-radius: 12px; margin-bottom: 1.5rem;">
        <i class="fa-solid fa-circle-check" style="margin-right: 0.5rem;"></i> {{ session('success') }}
    </div>
@endif
@if(session('warning'))
    <div class="alert alert-warning" style="border-radius: 12px; margin-bottom: 1.5rem;">
        <i class="fa-solid fa-triangle-exclamation" style="margin-right: 0.5rem;"></i> {{ session('warning') }}
    </div>
@endif

<!-- KPI Metrics Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
    <!-- Active CCPs -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(59, 130, 246, 0.12); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-sliders"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Active CCPs') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: var(--text-main);">{{ $ccpsCount }}</div>
        </div>
    </div>

    <!-- Expired Batches -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(239, 68, 68, 0.12); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-skull-crossbones"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Expired Batches') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: {{ $expiring['expired']->count() > 0 ? '#ef4444' : 'var(--text-main)' }};">
                {{ $expiring['expired']->count() }}
            </div>
        </div>
    </div>

    <!-- Critical Expiring (<=3 Days) -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-hourglass-half"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Expiring ≤ 3 Days') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #f59e0b;">
                {{ $expiring['critical']->count() }}
            </div>
        </div>
    </div>

    <!-- Temperature Breaches -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(239, 68, 68, 0.12); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Breaches (7 Days)') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: {{ $breachesCount > 0 ? '#ef4444' : '#10b981' }};">
                {{ $breachesCount }}
            </div>
        </div>
    </div>
</div>

<!-- Two-Column Layout -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Left: Expiring Batches Action List -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);">
        <div style="padding: 1rem 1.25rem; background: var(--bg-hover); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-triangle-exclamation" style="color: #f59e0b;"></i> {{ __('Expiry Alert & FEFO Action List') }}
            </h3>
            <a href="{{ route('admin.inventory.haccp.batches') }}" style="font-size: 0.8rem; font-weight: 600; color: #10b981; text-decoration: none;">
                {{ __('View All Batches') }} <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <div class="table-responsive">
            <table class="table" style="margin: 0; font-size: 0.85rem; vertical-align: middle;">
                <thead style="background: var(--bg-hover); color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase;">
                    <tr>
                        <th style="padding: 0.75rem 1rem;">{{ __('Batch #') }}</th>
                        <th style="padding: 0.75rem;">{{ __('Item') }}</th>
                        <th style="padding: 0.75rem; text-align: right;">{{ __('Remaining') }}</th>
                        <th style="padding: 0.75rem; text-align: center;">{{ __('Expiry Date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expiring['expired']->concat($expiring['critical']) as $batch)
                        @php $isExpired = $batch->expiry_date->isPast(); @endphp
                        <tr style="border-top: 1px solid var(--border-color); background: {{ $isExpired ? 'rgba(239, 68, 68, 0.05)' : 'rgba(245, 158, 11, 0.05)' }};">
                            <td style="padding: 0.75rem 1rem;">
                                <a href="{{ route('admin.inventory.haccp.traceability', ['batch_number' => $batch->batch_number]) }}" style="font-family: monospace; font-weight: 700; color: inherit; text-decoration: none;">
                                    {{ $batch->batch_number }}
                                </a>
                            </td>
                            <td style="padding: 0.75rem;">
                                <div style="font-weight: 600; color: var(--text-main);">{{ $batch->ingredient->name }}</div>
                                <small style="color: var(--text-muted);">{{ $batch->warehouse->name }}</small>
                            </td>
                            <td style="padding: 0.75rem; text-align: right; font-weight: 700;">
                                {{ number_format((float)$batch->remaining_quantity, 2) }} {{ $batch->unit->code }}
                            </td>
                            <td style="padding: 0.75rem; text-align: center;">
                                <span class="badge" style="background: {{ $isExpired ? 'rgba(239, 68, 68, 0.15)' : 'rgba(245, 158, 11, 0.15)' }}; color: {{ $isExpired ? '#ef4444' : '#f59e0b' }}; font-weight: 700;">
                                    {{ $batch->expiry_date->format('M d, Y') }}
                                    ({{ $isExpired ? __('EXPIRED') : $batch->expiry_date->diffForHumans() }})
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                                <i class="fa-solid fa-circle-check" style="color: #10b981; font-size: 1.5rem; margin-bottom: 0.5rem; display: block;"></i>
                                {{ __('All warehouse stock is in fresh, compliant condition. No expired or critical batches.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right: Recent Temperature Logs -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);">
        <div style="padding: 1rem 1.25rem; background: var(--bg-hover); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-temperature-half" style="color: #06b6d4;"></i> {{ __('Recent Temperature Logs') }}
            </h3>
            <a href="{{ route('admin.inventory.haccp.temperatures') }}" style="font-size: 0.8rem; font-weight: 600; color: #10b981; text-decoration: none;">
                {{ __('View Log History') }} <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <div class="table-responsive">
            <table class="table" style="margin: 0; font-size: 0.85rem; vertical-align: middle;">
                <thead style="background: var(--bg-hover); color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase;">
                    <tr>
                        <th style="padding: 0.75rem 1rem;">{{ __('Target / Location') }}</th>
                        <th style="padding: 0.75rem; text-align: right;">{{ __('Reading') }}</th>
                        <th style="padding: 0.75rem; text-align: center;">{{ __('Status') }}</th>
                        <th style="padding: 0.75rem; text-align: right;">{{ __('Time') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentLogs as $log)
                        <tr style="border-top: 1px solid var(--border-color);">
                            <td style="padding: 0.75rem 1rem;">
                                <div style="font-weight: 600; color: var(--text-main);">
                                    {{ $log->equipment_name ?: ($log->warehouse ? $log->warehouse->name : ($log->ccp ? $log->ccp->name : __('General Check'))) }}
                                </div>
                                @if($log->ccp)
                                    <small style="color: var(--text-muted);">{{ $log->ccp->code }}</small>
                                @endif
                            </td>
                            <td style="padding: 0.75rem; text-align: right; font-weight: 700;">
                                <span style="color: {{ $log->is_breached ? '#ef4444' : '#10b981' }}; font-size: 0.95rem;">
                                    {{ $log->measured_value }} {{ $log->unit }}
                                </span>
                            </td>
                            <td style="padding: 0.75rem; text-align: center;">
                                @if($log->is_breached)
                                    <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; font-weight: 700;">
                                        <i class="fa-solid fa-triangle-exclamation"></i> {{ __('Breached') }}
                                    </span>
                                @else
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700;">
                                        <i class="fa-solid fa-check"></i> {{ __('Compliant') }}
                                    </span>
                                @endif
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: right; color: var(--text-muted); font-size: 0.75rem;">
                                {{ $log->logged_at->format('H:i') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                                {{ __('No temperature logs recorded yet today.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

