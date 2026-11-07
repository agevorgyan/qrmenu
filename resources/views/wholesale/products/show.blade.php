@extends('layouts.app')

@section('title', __('Wholesale Product: :name', ['name' => $product->name]))

@section('content')
<div class="content-wrapper">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="{{ route('admin.wholesale.products.index') }}" style="color: #6366f1; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-arrow-left"></i>
                <span>{{ __('Back to Wholesale Catalog') }}</span>
            </a>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ $product->name }}
                </h1>
                @if($product->is_active)
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: rgba(16, 185, 129, 0.12); color: #10b981;">
                        <i class="fa-solid fa-check-circle"></i> {{ __('Active') }}
                    </span>
                @else
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: rgba(239, 68, 68, 0.12); color: #ef4444;">
                        <i class="fa-solid fa-ban"></i> {{ __('Inactive') }}
                    </span>
                @endif
            </div>
            <div style="color: var(--text-muted); font-size: 0.88rem; margin-top: 0.35rem; font-family: monospace;">
                SKU: <strong style="color: var(--text-main);">{{ $product->sku }}</strong>
                @if($product->barcode)
                    &bull; {{ __('Barcode') }}: {{ $product->barcode }}
                @endif
                @if($product->category)
                    &bull; {{ __('Category') }}: {{ $product->category->name }}
                @endif
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <a href="{{ route('admin.wholesale.products.edit', $product) }}"
               style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #6366f1; color: #fff; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3);">
                <i class="fa-solid fa-pen-to-square"></i>
                <span>{{ __('Edit Product') }}</span>
            </a>
        </div>
    </div>

    <!-- Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Card 1: Pricing & Tax -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem;">
            <div style="font-size: 0.85rem; font-weight: 800; color: #6366f1; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-tag"></i>
                <span>{{ __('Pricing & Tax Profile') }}</span>
            </div>
            
            <div style="margin-bottom: 1rem;">
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">{{ __('Base Wholesale Price') }}</div>
                <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main);">
                    {{ number_format($product->base_wholesale_price, 2) }} AMD
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Min Price Floor') }}</div>
                    <div style="font-size: 0.95rem; font-weight: 700; color: #f59e0b;">
                        {{ $product->min_wholesale_price ? number_format($product->min_wholesale_price, 2) . ' AMD' : __('None') }}
                    </div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Cost Price (COGS)') }}</div>
                    <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-main);">
                        {{ $product->cost_price ? number_format($product->cost_price, 2) . ' AMD' : '—' }}
                    </div>
                </div>
            </div>

            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.85rem; color: var(--text-muted);">{{ __('VAT Status') }}</span>
                @if($product->is_vat_applicable)
                    <span style="font-size: 0.85rem; font-weight: 700; color: #10b981;">
                        {{ __('Taxable (:rate% VAT)', ['rate' => (float)$product->vat_rate]) }}
                    </span>
                @else
                    <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted);">
                        {{ __('Non-VAT / Exempt') }}
                    </span>
                @endif
            </div>
        </div>

        <!-- Card 2: ATGAA Classification -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem;">
            <div style="font-size: 0.85rem; font-weight: 800; color: #10b981; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-file-invoice"></i>
                <span>{{ __('Official PEK / ATGAA Classification') }}</span>
            </div>

            <div style="margin-bottom: 0.75rem;">
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">{{ __('FEACN / ATGAA Code') }}</div>
                <div style="font-size: 1.4rem; font-weight: 800; font-family: monospace; color: #10b981;">
                    {{ $product->classifier_code }}
                </div>
            </div>

            <div style="background: var(--bg-main); border-radius: 10px; padding: 0.85rem; border: 1px solid var(--border-color); font-size: 0.88rem; color: var(--text-main); margin-bottom: 1rem;">
                {{ $product->atgaaClassification?->name_hy ?? __('Standard Classification') }}
                @if($product->atgaaClassification?->name_en)
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                        {{ $product->atgaaClassification->name_en }}
                    </div>
                @endif
            </div>

            <div style="font-size: 0.78rem; color: var(--text-muted);">
                <i class="fa-solid fa-circle-info" style="color: #6366f1;"></i>
                {{ __('This classifier code is strictly injected into PEK Invoice (<ExportedData>) and Accounting Document (<ExportedAccDocData>) XML files.') }}
            </div>
        </div>

        <!-- Card 3: Stock & Fulfillment -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem;">
            <div style="font-size: 0.85rem; font-weight: 800; color: #ec4899; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-boxes-stacked"></i>
                <span>{{ __('Fulfillment & Physical Stock') }}</span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Base Unit') }}</div>
                    <div style="font-size: 1rem; font-weight: 700; color: var(--text-main);">
                        {{ $product->unit?->name ?? '—' }} ({{ $product->unit?->code ?? '—' }})
                    </div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">{{ __('MOQ / Multiple') }}</div>
                    <div style="font-size: 1rem; font-weight: 700; color: var(--text-main);">
                        {{ (float)$product->min_order_quantity }} / {{ (float)$product->order_quantity_multiple }}
                    </div>
                </div>
            </div>

            <div style="padding-top: 1rem; border-top: 1px solid var(--border-color);">
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem;">{{ __('Core Inventory Link') }}</div>
                @if($product->physicalStockIngredient)
                    <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 8px; padding: 0.4rem 0.75rem; font-size: 0.85rem; font-weight: 700; color: #6366f1;">
                        <i class="fa-solid fa-link"></i>
                        <span>{{ $product->physicalStockIngredient->name }} [SKU: {{ $product->physicalStockIngredient->sku ?? 'N/A' }}]</span>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.4rem;">
                        {{ __('Deliveries will automatically deplete stock balances in core warehouse storage.') }}
                    </div>
                @else
                    <span style="font-size: 0.85rem; color: var(--text-muted); font-style: italic;">
                        {{ __('No physical stock link (Pure wholesale / drop-ship catalog item)') }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- Packaging Tiers Section -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.75rem; margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div>
                <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ __('Packaging Hierarchy & Bulk Units') }}
                </h2>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.2rem 0 0 0;">
                    {{ __('Define boxes, crates, cartons, or pallets for fast barcode scanning in Wholesale POS.') }}
                </p>
            </div>
        </div>

        @if($product->packagings->count() > 0)
            <div style="overflow-x: auto; margin-bottom: 1.5rem;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                            <th style="padding: 0.75rem 1rem;">{{ __('Packaging Type') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('Units Included') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('Packaging Barcode') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('Gross Weight') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('Dimensions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($product->packagings as $pkg)
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.85rem 1rem; font-weight: 700; color: var(--text-main);">
                                    <i class="fa-solid fa-box" style="color: #6366f1; margin-right: 0.4rem;"></i>
                                    {{ $pkg->packaging_type }}
                                </td>
                                <td style="padding: 0.85rem 1rem; font-weight: 700; color: #10b981;">
                                    {{ (float)$pkg->units_count }} {{ $product->unit?->code }}
                                </td>
                                <td style="padding: 0.85rem 1rem; font-family: monospace;">
                                    {{ $pkg->packaging_barcode ?? '—' }}
                                </td>
                                <td style="padding: 0.85rem 1rem; color: var(--text-muted);">
                                    {{ $pkg->gross_weight_kg ? $pkg->gross_weight_kg . ' kg' : '—' }}
                                </td>
                                <td style="padding: 0.85rem 1rem; color: var(--text-muted);">
                                    {{ $pkg->dimensions ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div style="text-align: center; padding: 2rem; background: var(--bg-main); border-radius: 12px; border: 1px dashed var(--border-color); margin-bottom: 1.5rem;">
                <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0;">
                    {{ __('No packaging tiers defined yet. Add packaging configurations below for master cartons or pallets.') }}
                </p>
            </div>
        @endif

        <!-- Quick Add Packaging Form -->
        <form method="POST" action="{{ route('admin.wholesale.products.packaging.store', $product) }}" 
              style="background: var(--bg-main); border-radius: 12px; padding: 1.25rem; border: 1px solid var(--border-color);">
            @csrf
            <div style="font-weight: 700; font-size: 0.85rem; color: var(--text-main); margin-bottom: 0.75rem;">
                {{ __('Add Packaging Tier') }}
            </div>
            <div style="display: grid; grid-template-columns: 2fr 1.5fr 2fr 1.5fr 1fr auto; gap: 0.75rem; align-items: end;">
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.25rem;">
                        {{ __('Package Type') }} *
                    </label>
                    <input type="text" name="packaging_type" placeholder="Box (12 pcs)" required
                           style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.85rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.25rem;">
                        {{ __('Units Count') }} *
                    </label>
                    <input type="number" step="1" min="1" name="units_count" placeholder="12" required
                           style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.85rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.25rem;">
                        {{ __('Packaging Barcode') }}
                    </label>
                    <input type="text" name="packaging_barcode" placeholder="4850000000000"
                           style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.85rem; font-family: monospace;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.25rem;">
                        {{ __('Gross Wt (kg)') }}
                    </label>
                    <input type="number" step="0.01" name="gross_weight_kg" placeholder="6.2"
                           style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.85rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.25rem;">
                        {{ __('Dims') }}
                    </label>
                    <input type="text" name="dimensions" placeholder="30x20x15"
                           style="width: 100%; padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.85rem;">
                </div>
                <div>
                    <button type="submit" 
                            style="padding: 0.5rem 1rem; border-radius: 8px; background: #6366f1; color: #fff; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.85rem;">
                        <i class="fa-solid fa-plus"></i> {{ __('Add') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

