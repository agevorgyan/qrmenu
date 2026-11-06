@extends('layouts.app')

@section('title', __('Critical Control Points (CCPs)') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="max-width: 1200px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-sliders"></i>
                </span>
                <span>{{ __('HACCP Critical Control Points (CCPs)') }}</span>
            </h1>
        </div>
        <div>
            @if($ccps->isEmpty())
                <form method="POST" action="{{ route('admin.inventory.haccp.ccps.seed') }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; background: #8b5cf6; border-color: #8b5cf6;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('Seed Standard HACCP CCPs') }}
                    </button>
                </form>
            @endif
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
        <a href="{{ route('admin.inventory.haccp.ccps') }}" class="btn btn-sm btn-primary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-sliders"></i> {{ __('Critical Control Points (CCPs)') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.batches') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-boxes-packing"></i> {{ __('Batches & Expiry (FEFO)') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.traceability') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-route"></i> {{ __('Traceability & Recall Audit') }}
        </a>
    </div>

    <!-- CCP Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.25rem;">
        @forelse($ccps as $ccp)
            <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card); display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span class="badge" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6; font-family: monospace; font-weight: 700;">
                            {{ $ccp->code }}
                        </span>
                        <span class="badge" style="background: rgba(107, 114, 128, 0.15); color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase;">
                            {{ $ccp->frequency }}
                        </span>
                    </div>

                    <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin: 0 0 0.5rem;">
                        {{ $ccp->name }}
                    </h3>

                    <!-- Limit Indicator -->
                    <div style="background: var(--bg-hover); border-radius: 10px; padding: 0.65rem 0.85rem; margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">
                            {{ __('Critical Limit') }}:
                        </span>
                        <span style="font-size: 1.1rem; font-weight: 800; color: #10b981;">
                            @if($ccp->min_value !== null && $ccp->max_value !== null)
                                {{ $ccp->min_value }}{{ $ccp->unit }} – {{ $ccp->max_value }}{{ $ccp->unit }}
                            @elseif($ccp->min_value !== null)
                                ≥ {{ $ccp->min_value }} {{ $ccp->unit }}
                            @elseif($ccp->max_value !== null)
                                ≤ {{ $ccp->max_value }} {{ $ccp->unit }}
                            @endif
                        </span>
                    </div>

                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0 0 0.75rem;">
                        {{ $ccp->critical_limit_description }}
                    </p>
                </div>

                @if($ccp->corrective_action_instructions)
                    <div style="background: rgba(245, 158, 11, 0.08); border-left: 3px solid #f59e0b; padding: 0.5rem 0.75rem; border-radius: 0 8px 8px 0; font-size: 0.8rem;">
                        <strong style="color: #f59e0b; display: block; margin-bottom: 0.2rem;">{{ __('Corrective Action') }}:</strong>
                        <span style="color: var(--text-main);">{{ $ccp->corrective_action_instructions }}</span>
                    </div>
                @endif
            </div>
        @empty
            <div class="card" style="grid-column: 1 / -1; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 3rem; text-align: center;">
                <i class="fa-solid fa-sliders" style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 1rem; display: block;"></i>
                <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">{{ __('No Critical Control Points Configured') }}</h3>
                <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto 1.5rem;">
                    {{ __('Initialize your restaurant safety plan with 1-click standard HACCP critical points covering receiving, walk-in cooling, deep freezing, thermal cooking core temps, hot holding, and blast chilling.') }}
                </p>
                <form method="POST" action="{{ route('admin.inventory.haccp.ccps.seed') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 700; background: #8b5cf6; border-color: #8b5cf6;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('Seed Standard HACCP CCPs') }}
                    </button>
                </form>
            </div>
        @endforelse
    </div>
</div>
@endsection

