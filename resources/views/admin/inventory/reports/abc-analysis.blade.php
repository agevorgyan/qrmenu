@extends('layouts.app')

@section('title', __('ABC Inventory Analysis (Pareto)') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <a href="{{ route('admin.inventory.reports.inventory.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px;">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                {{ __('ABC Inventory Analysis (Pareto 80/20 Rule)') }}
            </h1>
        </div>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0 2.5rem;">
            {{ __('Classify raw materials by capital impact to focus management attention on the 20% of ingredients representing 80% of consumption value.') }}
        </p>
    </div>
</div>

<!-- ABC Classification Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
    <!-- Class A -->
    <div class="card" style="background: var(--bg-card); border: 2px solid #10b981; border-radius: 16px; padding: 1.5rem; box-shadow: var(--shadow-card);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #10b981; font-size: 0.9rem; font-weight: 800; padding: 0.35rem 0.75rem; border-radius: 8px;">
                {{ __('Class A — High Value') }}
            </span>
            <strong style="color: #10b981; font-size: 1.1rem;">{{ $abc['class_a']['percentage'] }}% {{ __('of value') }}</strong>
        </div>
        <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;">
            {{ number_format($abc['class_a']['value'], 2) }} {{ Auth::user()->vendor->currency }}
        </div>
        <div style="color: var(--text-muted); font-size: 0.85rem;">
            {{ $abc['class_a']['count'] }} {{ __('items') }} &bull; {{ __('Tight daily/weekly stock counts') }}
        </div>
    </div>

    <!-- Class B -->
    <div class="card" style="background: var(--bg-card); border: 2px solid #f59e0b; border-radius: 16px; padding: 1.5rem; box-shadow: var(--shadow-card);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <span class="badge" style="background: rgba(245, 158, 11, 0.2); color: #f59e0b; font-size: 0.9rem; font-weight: 800; padding: 0.35rem 0.75rem; border-radius: 8px;">
                {{ __('Class B — Moderate Value') }}
            </span>
            <strong style="color: #f59e0b; font-size: 1.1rem;">{{ $abc['class_b']['percentage'] }}% {{ __('of value') }}</strong>
        </div>
        <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;">
            {{ number_format($abc['class_b']['value'], 2) }} {{ Auth::user()->vendor->currency }}
        </div>
        <div style="color: var(--text-muted); font-size: 0.85rem;">
            {{ $abc['class_b']['count'] }} {{ __('items') }} &bull; {{ __('Bi-weekly automated reorder checks') }}
        </div>
    </div>

    <!-- Class C -->
    <div class="card" style="background: var(--bg-card); border: 2px solid #64748b; border-radius: 16px; padding: 1.5rem; box-shadow: var(--shadow-card);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <span class="badge" style="background: rgba(100, 116, 139, 0.2); color: #94a3b8; font-size: 0.9rem; font-weight: 800; padding: 0.35rem 0.75rem; border-radius: 8px;">
                {{ __('Class C — Low Value') }}
            </span>
            <strong style="color: #94a3b8; font-size: 1.1rem;">{{ $abc['class_c']['percentage'] }}% {{ __('of value') }}</strong>
        </div>
        <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;">
            {{ number_format($abc['class_c']['value'], 2) }} {{ Auth::user()->vendor->currency }}
        </div>
        <div style="color: var(--text-muted); font-size: 0.85rem;">
            {{ $abc['class_c']['count'] }} {{ __('items') }} &bull; {{ __('Bulk ordering, monthly spot-checks') }}
        </div>
    </div>
</div>

<!-- Classified Items Table with Search -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);"
     x-data="{
         search: '',
         classFilter: '',
         items: @js($abc['items']),
         get filteredItems() {
             return this.items.filter(item => {
                 const matchesSearch = !this.search || item.name.toLowerCase().includes(this.search.toLowerCase()) || (item.sku && item.sku.toLowerCase().includes(this.search.toLowerCase()));
                 const matchesClass = !this.classFilter || item.abc_class === this.classFilter;
                 return matchesSearch && matchesClass;
             });
         }
     }">
    <div style="padding: 1.25rem 1.5rem; background: var(--bg-hover); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-list-ol" style="color: #10b981;"></i> {{ __('Ranked Inventory Items') }}
            <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 400;" x-text="`(${filteredItems.length} items)`"></span>
        </h3>

        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <select class="form-select form-select-sm" x-model="classFilter" style="border-radius: 8px; width: 140px;">
                <option value="">{{ __('All Classes') }}</option>
                <option value="A">{{ __('Class A Only') }}</option>
                <option value="B">{{ __('Class B Only') }}</option>
                <option value="C">{{ __('Class C Only') }}</option>
            </select>
            <input type="text" x-model="search" placeholder="{{ __('Search ingredient or SKU...') }}" class="form-control form-control-sm" style="border-radius: 8px; width: 200px;">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table" style="margin: 0; vertical-align: middle;">
            <thead style="background: var(--bg-hover); font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                <tr>
                    <th style="padding: 1rem 1.5rem; text-align: center;">{{ __('Class') }}</th>
                    <th style="padding: 1rem;">{{ __('Ingredient') }}</th>
                    <th style="padding: 1rem;">{{ __('Category') }}</th>
                    <th style="padding: 1rem; text-align: right;">{{ __('Stock On Hand') }}</th>
                    <th style="padding: 1rem; text-align: right;">{{ __('Unit Cost') }}</th>
                    <th style="padding: 1rem; text-align: right;">{{ __('Valuation') }}</th>
                    <th style="padding: 1rem 1.5rem; text-align: right;">{{ __('Cumulative %') }}</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="item in filteredItems" :key="item.id">
                    <tr style="border-bottom: 1px solid var(--table-row-border);">
                        <td style="padding: 1rem 1.5rem; text-align: center;">
                            <span class="badge"
                                  :style="item.abc_class === 'A' ? 'background: rgba(16, 185, 129, 0.2); color: #10b981; font-weight: 800; font-size: 0.85rem;' : (item.abc_class === 'B' ? 'background: rgba(245, 158, 11, 0.2); color: #f59e0b; font-weight: 800; font-size: 0.85rem;' : 'background: rgba(100, 116, 139, 0.2); color: #94a3b8; font-weight: 800; font-size: 0.85rem;')"
                                  x-text="`Class ${item.abc_class}`">
                            </span>
                        </td>
                        <td style="padding: 1rem;">
                            <strong style="color: var(--text-main); display: block;" x-text="item.name"></strong>
                            <small style="color: var(--text-muted); font-family: monospace;" x-text="item.sku || 'NO-SKU'"></small>
                        </td>
                        <td style="padding: 1rem; color: var(--text-muted);" x-text="item.category"></td>
                        <td style="padding: 1rem; text-align: right; font-weight: 600; color: var(--text-main);" x-text="`${Number(item.current_stock).toFixed(2)} ${item.unit}`"></td>
                        <td style="padding: 1rem; text-align: right; color: var(--text-muted);" x-text="`${Number(item.unit_cost).toFixed(2)}`"></td>
                        <td style="padding: 1rem; text-align: right; font-weight: 800; color: var(--text-main);" x-text="`${Number(item.annual_value).toFixed(2)} {{ Auth::user()->vendor->currency }}`"></td>
                        <td style="padding: 1rem 1.5rem; text-align: right; font-weight: 700; color: var(--text-muted);" x-text="`${item.cumulative_percent}%`"></td>
                    </tr>
                </template>
                <tr x-show="filteredItems.length === 0">
                    <td colspan="7" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                        {{ __('No ingredients match the search or class filter.') }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection

