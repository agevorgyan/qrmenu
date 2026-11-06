@extends('layouts.app')

@section('title', __('HACCP Temperature Logs') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="max-width: 1200px; margin: 0 auto;" x-data="{ logModalOpen: false }">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(6, 182, 212, 0.15); color: #06b6d4; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-temperature-half"></i>
                </span>
                <span>{{ __('HACCP Temperature Monitoring Logs') }}</span>
            </h1>
        </div>
        <div>
            @can('haccp.log')
                <button type="button" @click="logModalOpen = true" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 700; background: #06b6d4; border-color: #06b6d4;">
                    <i class="fa-solid fa-plus"></i> {{ __('Record Temperature Reading') }}
                </button>
            @endcan
        </div>
    </div>

    <!-- HACCP Sub-Navigation Tabs -->
    <div style="display: flex; gap: 0.5rem; border-bottom: 1px solid var(--border-color); margin-bottom: 1.5rem; overflow-x: auto; padding-bottom: 0.5rem;">
        <a href="{{ route('admin.inventory.haccp.dashboard') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-gauge-high"></i> {{ __('Dashboard') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.temperatures') }}" class="btn btn-sm btn-primary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-temperature-half"></i> {{ __('Temperature Logs') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.checklists') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-clipboard-check"></i> {{ __('Sanitation Checklists') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.ccps') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-sliders"></i> {{ __('Critical Control Points (CCPs)') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.batches') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-boxes-packing"></i> {{ __('Batches & Expiry (FEFO)') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.traceability') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-route"></i> {{ __('Traceability & Recall Audit') }}
        </a>
    </div>

    <!-- Filter Card -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
        <form method="GET" action="{{ route('admin.inventory.haccp.temperatures') }}" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
            <div style="min-width: 200px;">
                <select name="ccp_id" class="form-select" style="border-radius: 10px;" onchange="this.form.submit()">
                    <option value="">{{ __('All CCPs') }}</option>
                    @foreach($ccps as $ccp)
                        <option value="{{ $ccp->id }}" {{ (string)request('ccp_id') === (string)$ccp->id ? 'selected' : '' }}>
                            {{ $ccp->code }} - {{ $ccp->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="min-width: 170px;">
                <select name="is_breached" class="form-select" style="border-radius: 10px;" onchange="this.form.submit()">
                    <option value="">{{ __('All Results') }}</option>
                    <option value="1" {{ request('is_breached') === '1' ? 'selected' : '' }}>{{ __('Breaches Only') }}</option>
                    <option value="0" {{ request('is_breached') === '0' ? 'selected' : '' }}>{{ __('Compliant Only') }}</option>
                </select>
            </div>

            @if(request()->hasAny(['ccp_id', 'is_breached']))
                <a href="{{ route('admin.inventory.haccp.temperatures') }}" class="btn btn-outline-secondary" style="border-radius: 10px;">
                    <i class="fa-solid fa-rotate-left"></i> {{ __('Clear') }}
                </a>
            @endif
        </form>
    </div>

    <!-- Table -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);">
        <div class="table-responsive">
            <table class="table" style="margin: 0; vertical-align: middle;">
                <thead style="background: var(--bg-hover); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);">
                    <tr>
                        <th style="padding: 1rem 1.25rem;">{{ __('Target / Equipment') }}</th>
                        <th style="padding: 1rem;">{{ __('Associated CCP') }}</th>
                        <th style="padding: 1rem; text-align: right;">{{ __('Reading') }}</th>
                        <th style="padding: 1rem; text-align: center;">{{ __('Status') }}</th>
                        <th style="padding: 1rem;">{{ __('Corrective Action Taken') }}</th>
                        <th style="padding: 1rem;">{{ __('Checked By') }}</th>
                        <th style="padding: 1rem 1.25rem; text-align: right;">{{ __('Date & Time') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr style="border-top: 1px solid var(--border-color); background: {{ $log->is_breached ? 'rgba(239, 68, 68, 0.03)' : 'transparent' }};">
                            <td style="padding: 1rem 1.25rem;">
                                <div style="font-weight: 600; color: var(--text-main);">
                                    {{ $log->equipment_name ?: ($log->warehouse ? $log->warehouse->name : __('General Equipment')) }}
                                </div>
                                @if($log->batch)
                                    <small style="color: var(--text-muted); font-family: monospace;">{{ $log->batch->batch_number }}</small>
                                @endif
                            </td>
                            <td style="padding: 1rem;">
                                @if($log->ccp)
                                    <div style="font-weight: 600; font-size: 0.85rem; color: var(--text-main);">{{ $log->ccp->code }}</div>
                                    <small style="color: var(--text-muted);">{{ $log->ccp->name }}</small>
                                @else
                                    <span style="color: var(--text-muted);">-</span>
                                @endif
                            </td>
                            <td style="padding: 1rem; text-align: right; font-weight: 700;">
                                <span style="font-size: 1.05rem; color: {{ $log->is_breached ? '#ef4444' : '#10b981' }};">
                                    {{ $log->measured_value }} {{ $log->unit }}
                                </span>
                            </td>
                            <td style="padding: 1rem; text-align: center;">
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
                            <td style="padding: 1rem; font-size: 0.85rem;">
                                @if($log->corrective_action_taken)
                                    <span style="color: #f59e0b; font-weight: 600;">{{ $log->corrective_action_taken }}</span>
                                @else
                                    <span style="color: var(--text-muted);">-</span>
                                @endif
                            </td>
                            <td style="padding: 1rem; font-size: 0.85rem; color: var(--text-muted);">
                                {{ $log->loggedBy?->name ?? __('System') }}
                            </td>
                            <td style="padding: 1rem 1.25rem; text-align: right; font-size: 0.85rem; color: var(--text-muted);">
                                {{ $log->logged_at->toVendorTimezone()->format('M d, Y H:i') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                                <i class="fa-solid fa-temperature-empty" style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 1rem; display: block;"></i>
                                <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-main);">{{ __('No temperature logs found') }}</div>
                                <p style="margin: 0.5rem 0 1.25rem;">{{ __('Begin recording daily temperature checks across food storage, cooking, and holding points.') }}</p>
                                @can('haccp.log')
                                    <button type="button" @click="logModalOpen = true" class="btn btn-primary" style="border-radius: 10px; background: #06b6d4; border-color: #06b6d4;">
                                        <i class="fa-solid fa-plus"></i> {{ __('Record Temperature Reading') }}
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div style="padding: 1rem; border-top: 1px solid var(--border-color);">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

    <!-- Record Temperature Reading Modal -->
    <div x-show="logModalOpen" x-cloak style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div class="card" style="background: var(--bg-card); border-radius: 16px; max-width: 500px; width: 100%; padding: 1.75rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-temperature-half" style="color: #06b6d4;"></i> {{ __('Record Temperature Reading') }}
                </h3>
                <button type="button" @click="logModalOpen = false" class="btn-close" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">
                    &times;
                </button>
            </div>

            <form method="POST" action="{{ route('admin.inventory.haccp.temperatures.store') }}">
                @csrf

                <div style="margin-bottom: 1rem;">
                    <label class="form-label" style="font-weight: 600;">{{ __('Critical Control Point (CCP)') }}</label>
                    <select name="ccp_id" class="form-select" style="border-radius: 10px;">
                        <option value="">{{ __('-- Optional CCP --') }}</option>
                        @foreach($ccps as $ccp)
                            <option value="{{ $ccp->id }}">
                                {{ $ccp->code }} - {{ $ccp->name }}
                                ({{ $ccp->min_value !== null ? '>=' . $ccp->min_value . $ccp->unit : '' }} {{ $ccp->max_value !== null ? '<=' . $ccp->max_value . $ccp->unit : '' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label class="form-label" style="font-weight: 600;">{{ __('Equipment / Cooler / Station Name') }}</label>
                    <input type="text" name="equipment_name" class="form-control" style="border-radius: 10px;" placeholder="{{ __('e.g., Walk-in Fridge #1, Hot Holding Table...') }}">
                </div>

                <div style="margin-bottom: 1rem;">
                    <label class="form-label" style="font-weight: 600;">{{ __('Storage Warehouse') }}</label>
                    <select name="warehouse_id" class="form-select" style="border-radius: 10px;">
                        <option value="">{{ __('-- Optional Warehouse --') }}</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label class="form-label" style="font-weight: 600;">{{ __('Measured Temperature Reading (°C)') }} <span style="color: #ef4444;">*</span></label>
                    <input type="number" step="0.1" name="measured_value" class="form-control" style="border-radius: 10px; font-size: 1.2rem; font-weight: 700;" required placeholder="e.g. 3.5">
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label class="form-label" style="font-weight: 600;">{{ __('Corrective Action (if limit breached)') }}</label>
                    <textarea name="corrective_action_taken" rows="2" class="form-control" style="border-radius: 10px;" placeholder="{{ __('e.g., Adjusted thermostat, discarded thawed item...') }}"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" @click="logModalOpen = false" class="btn btn-outline-secondary" style="border-radius: 10px;">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; background: #06b6d4; border-color: #06b6d4;">
                        <i class="fa-solid fa-check"></i> {{ __('Save Reading') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

