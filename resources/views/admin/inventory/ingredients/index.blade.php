@extends('layouts.app')

@section('title', __('Ingredients & Items Catalog') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 0; flex: 1;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fa-solid fa-wheat-awn"></i>
            </span>
            <span>{{ __('Ingredients & Catalog') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
            {{ __('Manage raw materials, ingredients, semi-finished goods, packaging, and consumables.') }}
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <a href="{{ route('admin.inventory.ingredients.export') }}" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 600;">
            <i class="fa-solid fa-file-csv"></i> {{ __('Export CSV') }}
        </a>
        @can('ingredients.create')
            <a href="{{ route('admin.inventory.ingredients.create') }}" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 700;">
                <i class="fa-solid fa-plus"></i> {{ __('Add Ingredient') }}
            </a>
        @endcan
    </div>
</div>

<!-- Search & Filter Bar -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
    <form method="GET" action="{{ route('admin.inventory.ingredients.index') }}" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 2; min-width: 220px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Search') }}</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search name, SKU, barcode...') }}" class="form-input" style="width: 100%; border-radius: 10px;">
        </div>

        <div style="flex: 1; min-width: 160px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Category') }}</label>
            <select name="category_id" class="form-select" style="width: 100%; border-radius: 10px;">
                <option value="">{{ __('All Categories') }}</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ (string) request('category_id') === (string) $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="flex: 1; min-width: 160px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Item Type') }}</label>
            <select name="type" class="form-select" style="width: 100%; border-radius: 10px;">
                <option value="">{{ __('All Types') }}</option>
                <option value="raw_material" {{ request('type') === 'raw_material' ? 'selected' : '' }}>{{ __('Raw Material') }}</option>
                <option value="ingredient" {{ request('type') === 'ingredient' ? 'selected' : '' }}>{{ __('Ingredient') }}</option>
                <option value="semi_finished" {{ request('type') === 'semi_finished' ? 'selected' : '' }}>{{ __('Semi-Finished') }}</option>
                <option value="packaging" {{ request('type') === 'packaging' ? 'selected' : '' }}>{{ __('Packaging') }}</option>
                <option value="consumable" {{ request('type') === 'consumable' ? 'selected' : '' }}>{{ __('Consumable') }}</option>
            </select>
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem; padding-bottom: 0.5rem;">
            <label style="display: flex; align-items: center; gap: 0.4rem; cursor: pointer; font-size: 0.88rem; font-weight: 600; color: var(--text-main);">
                <input type="checkbox" name="low_stock" value="1" {{ request('low_stock') ? 'checked' : '' }} onchange="this.form.submit()">
                <span>{{ __('Low Stock Only') }}</span>
            </label>
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; padding: 0.55rem 1.25rem;">
                <i class="fa-solid fa-filter"></i> {{ __('Filter') }}
            </button>
            @if(request()->hasAny(['search', 'category_id', 'type', 'low_stock']))
                <a href="{{ route('admin.inventory.ingredients.index') }}" class="btn btn-secondary" style="border-radius: 10px; padding: 0.55rem 1rem;">
                    {{ __('Reset') }}
                </a>
            @endif
        </div>
    </form>
</div>

<!-- Ingredients Table -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);">
    @if($ingredients->isEmpty())
        <div style="text-align: center; padding: 3.5rem 1rem; color: var(--text-muted);">
            <i class="fa-solid fa-wheat-awn" style="font-size: 2.5rem; color: var(--text-muted); margin-bottom: 0.75rem;"></i>
            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">{{ __('No ingredients found') }}</h3>
            <p style="margin: 0;">{{ __('Get started by adding your first ingredient or adjusting your filter criteria.') }}</p>
        </div>
    @else
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.02); color: var(--text-muted); text-align: left;">
                        <th style="padding: 0.85rem 1rem;">{{ __('Item & SKU') }}</th>
                        <th style="padding: 0.85rem 1rem;">{{ __('Type') }}</th>
                        <th style="padding: 0.85rem 1rem;">{{ __('Category') }}</th>
                        <th style="padding: 0.85rem 1rem;">{{ __('Unit') }}</th>
                        <th style="padding: 0.85rem 1rem; text-align: right;">{{ __('Current Cost') }}</th>
                        <th style="padding: 0.85rem 1rem; text-align: right;">{{ __('Stock Available') }}</th>
                        <th style="padding: 0.85rem 1rem; text-align: center;">{{ __('Status') }}</th>
                        <th style="padding: 0.85rem 1rem; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ingredients as $ing)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.85rem 1rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    @if($ing->image)
                                        <img src="{{ $ing->image }}" alt="{{ $ing->name }}" style="width: 38px; height: 38px; border-radius: 8px; object-fit: cover; border: 1px solid var(--border-color);">
                                    @else
                                        <div style="width: 38px; height: 38px; border-radius: 8px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                                            <i class="fa-solid fa-cube"></i>
                                        </div>
                                    @endif
                                    <div>
                                        <div style="font-weight: 700; color: var(--text-main);">{{ $ing->name }}</div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">
                                            {{ $ing->sku ?: '-' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 0.85rem 1rem;">
                                <span style="font-size: 0.75rem; font-weight: 600; padding: 2px 7px; border-radius: 6px; background: rgba(0,0,0,0.05); color: var(--text-main); text-transform: capitalize;">
                                    {{ str_replace('_', ' ', $ing->type) }}
                                </span>
                            </td>
                            <td style="padding: 0.85rem 1rem; color: var(--text-muted);">
                                {{ $ing->category?->name ?: '-' }}
                            </td>
                            <td style="padding: 0.85rem 1rem; font-weight: 600;">
                                {{ $ing->unit?->code }}
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: right; font-weight: 700; color: var(--text-main);">
                                {{ number_format((float) $ing->current_cost, 2) }}
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: right;">
                                <span style="font-weight: 800; color: {{ $ing->isLowStock() ? '#ef4444' : '#10b981' }};">
                                    {{ number_format($ing->total_stock_available, 2) }}
                                </span>
                                @if($ing->isLowStock())
                                    <span style="font-size: 0.7rem; color: #ef4444; display: block; font-weight: 600;">
                                        {{ __('Low Stock') }}
                                    </span>
                                @endif
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: center;">
                                <span style="width: 8px; height: 8px; border-radius: 50%; display: inline-block; background: {{ $ing->is_active ? '#10b981' : '#94a3b8' }};"></span>
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: right;">
                                <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                                    @can('ingredients.update')
                                        <a href="{{ route('admin.inventory.ingredients.edit', $ing) }}" class="btn btn-secondary" style="padding: 0.4rem 0.65rem; border-radius: 8px;" title="{{ __('Edit') }}">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                    @endcan
                                    @can('ingredients.delete')
                                        <form method="POST" action="{{ route('admin.inventory.ingredients.destroy', $ing) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this ingredient?') }}')" style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-secondary" style="padding: 0.4rem 0.65rem; border-radius: 8px; color: #ef4444;" title="{{ __('Delete') }}">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($ingredients->hasPages())
            <div style="padding: 1rem; border-top: 1px solid var(--border-color);">
                {{ $ingredients->links() }}
            </div>
        @endif
    @endif
</div>
@endsection

