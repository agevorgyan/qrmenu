@extends('layouts.app')

@section('title', __('Warehouses & Storage Facilities') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 0; flex: 1;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fa-solid fa-warehouse"></i>
            </span>
            <span>{{ __('Warehouses & Storage Facilities') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
            {{ __('Manage main warehouses, kitchens, cold rooms, freezers, and bar stock locations.') }}
        </p>
    </div>
    @can('inventory.view')
        <a href="{{ route('admin.inventory.warehouses.create') }}" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 700;">
            <i class="fa-solid fa-plus"></i> {{ __('Add Warehouse') }}
        </a>
    @endcan
</div>

@if($warehouses->isEmpty())
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; text-align: center; padding: 4rem 1.5rem; box-shadow: var(--shadow-card); color: var(--text-muted);">
        <i class="fa-solid fa-warehouse" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
        <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">{{ __('No warehouses registered') }}</h3>
        <p style="margin-bottom: 1.5rem;">{{ __('Create your primary storage facility to begin tracking stock receipts and movements.') }}</p>
        <a href="{{ route('admin.inventory.warehouses.create') }}" class="btn btn-primary" style="border-radius: 10px; font-weight: 700;">
            <i class="fa-solid fa-plus"></i> {{ __('Create First Warehouse') }}
        </a>
    </div>
@else
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
        @foreach($warehouses as $wh)
            <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-card); position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between;">
                <div style="position: absolute; top: 0; left: 0; width: 5px; height: 100%; background: {{ $wh->is_default ? '#10b981' : '#3b82f6' }};"></div>

                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0;">{{ $wh->name }}</h3>
                                @if($wh->is_default)
                                    <span style="font-size: 0.68rem; font-weight: 800; padding: 2px 8px; border-radius: 9999px; background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);">
                                        {{ __('DEFAULT') }}
                                    </span>
                                @endif
                            </div>
                            @if($wh->code)
                                <span style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">{{ $wh->code }}</span>
                            @endif
                        </div>

                        <span style="font-size: 0.72rem; font-weight: 700; padding: 3px 8px; border-radius: 6px; background: rgba(0,0,0,0.05); text-transform: uppercase;">
                            {{ str_replace('_', ' ', $wh->type) }}
                        </span>
                    </div>

                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                            <i class="fa-solid fa-location-dot" style="width: 16px; color: #f59e0b;"></i>
                            <span>{{ $wh->location?->name ?: __('All Branches') }}</span>
                        </div>
                        @if($wh->responsibleUser)
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                                <i class="fa-solid fa-user-tie" style="width: 16px; color: #8b5cf6;"></i>
                                <span>{{ $wh->responsibleUser->name }}</span>
                            </div>
                        @endif
                        @if($wh->temperature_min !== null || $wh->temperature_max !== null)
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fa-solid fa-temperature-half" style="width: 16px; color: #06b6d4;"></i>
                                <span>{{ $wh->temperature_min }}°C &sim; {{ $wh->temperature_max }}°C</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div style="border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: 0.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <div>
                            <span style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Items') }}</span>
                            <div style="font-weight: 800; font-size: 1.1rem; color: var(--text-main);">{{ $wh->balances_count }}</div>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Valuation') }}</span>
                            <div style="font-weight: 800; font-size: 1.1rem; color: #10b981;">
                                {{ number_format((float) ($values[$wh->id] ?? 0), 2) }}
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                        @if(!$wh->is_default)
                            <form method="POST" action="{{ route('admin.inventory.warehouses.default', $wh) }}" style="display: inline;">
                                @csrf
                                <button type="submit" class="btn btn-secondary" style="padding: 0.4rem 0.65rem; font-size: 0.78rem;" title="{{ __('Make Default') }}">
                                    <i class="fa-solid fa-star"></i>
                                </button>
                            </form>
                        @endif
                        <a href="{{ route('admin.inventory.stocks.overview', ['warehouse_id' => $wh->id]) }}" class="btn btn-secondary" style="padding: 0.4rem 0.75rem; font-size: 0.8rem; font-weight: 600;">
                            <i class="fa-solid fa-cubes-stacked"></i> {{ __('Stock') }}
                        </a>
                        <a href="{{ route('admin.inventory.warehouses.edit', $wh) }}" class="btn btn-secondary" style="padding: 0.4rem 0.65rem; font-size: 0.8rem;">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </a>
                        <form method="POST" action="{{ route('admin.inventory.warehouses.destroy', $wh) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this warehouse?') }}')" style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-secondary" style="padding: 0.4rem 0.65rem; font-size: 0.8rem; color: #ef4444;">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection

