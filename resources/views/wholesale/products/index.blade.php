@extends('layouts.app')

@section('title', __('Independent Wholesale Product Catalog'))

@section('content')
<div class="content-wrapper">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 12px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; font-size: 1.25rem;">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </span>
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ __('Independent Wholesale Catalog') }}
                </h1>
            </div>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">
                {{ __('Independent wholesale SKUs, ATGAA classifications, multi-tier packaging, pricing floors, and physical inventory stock mapping.') }}
            </p>
        </div>

        <a href="{{ route('admin.wholesale.products.create') }}" 
           style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #6366f1; color: #fff; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">
            <i class="fa-solid fa-plus"></i>
            <span>{{ __('Add Wholesale Product') }}</span>
        </a>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 12px; padding: 1rem 1.25rem; color: #10b981; font-weight: 600; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Search & Filters -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem;">
        <form method="GET" action="{{ route('admin.wholesale.products.index') }}" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px; position: relative;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search by product name, SKU, barcode, ATGAA code...') }}"
                       style="width: 100%; padding: 0.65rem 1rem 0.65rem 2.5rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>

            <div style="min-width: 200px;">
                <select name="category_id" style="width: 100%; padding: 0.65rem 1rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                    <option value="">{{ __('All Categories') }}</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ ($filters['category_id'] ?? '') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #6366f1; color: #fff; font-weight: 700; border: none; cursor: pointer;">
                {{ __('Filter') }}
            </button>
        </form>
    </div>

    <!-- Products Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.88rem;">
            <thead>
                <tr style="background: var(--bg-main); border-bottom: 1px solid var(--border-color); color: var(--text-muted); text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.05em;">
                    <th style="padding: 1rem 1.25rem;">{{ __('Product & SKU') }}</th>
                    <th style="padding: 1rem 1rem;">{{ __('ATGAA / FEACN') }}</th>
                    <th style="padding: 1rem 1rem;">{{ __('Base Price') }}</th>
                    <th style="padding: 1rem 1rem;">{{ __('Min Price Floor') }}</th>
                    <th style="padding: 1rem 1rem;">{{ __('Packaging') }}</th>
                    <th style="padding: 1rem 1rem;">{{ __('Stock Link') }}</th>
                    <th style="padding: 1rem 1.25rem; text-align: right;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.15s ease;">
                        <td style="padding: 1rem 1.25rem;">
                            <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">{{ $product->name }}</div>
                            <div style="font-size: 0.78rem; color: var(--text-muted); font-family: monospace;">
                                SKU: <strong style="color: var(--text-main);">{{ $product->sku }}</strong> 
                                @if($product->barcode) • Barcode: {{ $product->barcode }} @endif
                            </div>
                        </td>

                        <td style="padding: 1rem 1rem;">
                            <span style="font-family: monospace; font-weight: 800; color: #6366f1; background: rgba(99, 102, 241, 0.12); padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.82rem;">
                                {{ $product->classifier_code }}
                            </span>
                            @if($product->atgaaClassification)
                                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.25rem; max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $product->atgaaClassification->name_hy }}">
                                    {{ $product->atgaaClassification->name_hy }}
                                </div>
                            @endif
                        </td>

                        <td style="padding: 1rem 1rem;">
                            <div style="font-weight: 800; color: var(--text-main); font-size: 0.95rem;">
                                {{ number_format((float) $product->base_wholesale_price, 2) }} ֏
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">
                                / {{ $product->unit?->name ?? 'unit' }} {{ $product->is_vat_applicable ? '+ 20% VAT' : 'Exempt' }}
                            </div>
                        </td>

                        <td style="padding: 1rem 1rem;">
                            <span style="font-weight: 700; color: #ef4444; font-size: 0.88rem;">
                                {{ number_format((float) $product->min_wholesale_price, 2) }} ֏
                            </span>
                        </td>

                        <td style="padding: 1rem 1rem;">
                            <div style="font-size: 0.82rem; color: var(--text-main);">
                                {{ (float) $product->units_per_package }} {{ $product->unit?->code ?? 'units' }} / {{ $product->packagingUnit?->name ?? __('Package') }}
                            </div>
                            <div style="font-size: 0.72rem; color: var(--text-muted);">
                                MOQ: {{ (float) $product->min_order_quantity }} • Multiple: {{ (float) $product->order_quantity_multiple }}
                            </div>
                        </td>

                        <td style="padding: 1rem 1rem;">
                            @if($product->hasPhysicalInventory())
                                <span style="display: inline-flex; align-items: center; gap: 0.3rem; color: #10b981; font-weight: 700; font-size: 0.75rem; background: rgba(16, 185, 129, 0.12); padding: 0.2rem 0.55rem; border-radius: 9999px;">
                                    <i class="fa-solid fa-link"></i>
                                    {{ $product->physicalStockIngredient->name }}
                                </span>
                            @else
                                <span style="color: var(--text-muted); font-size: 0.78rem;">
                                    {{ __('Unlinked (Virtual)') }}
                                </span>
                            @endif
                        </td>

                        <td style="padding: 1rem 1.25rem; text-align: right;">
                            <div style="display: inline-flex; gap: 0.4rem; align-items: center;">
                                <a href="{{ route('admin.wholesale.products.show', $product) }}" 
                                   style="padding: 0.4rem 0.75rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; background: var(--bg-main); color: var(--text-main); border: 1px solid var(--border-color); text-decoration: none;"
                                   title="{{ __('View Details & Packaging') }}">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <a href="{{ route('admin.wholesale.products.edit', $product) }}" 
                                   style="padding: 0.4rem 0.75rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; background: #6366f1; color: #fff; border: none; text-decoration: none;"
                                   title="{{ __('Edit Product') }}">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                            <i class="fa-solid fa-boxes-stacked" style="font-size: 2rem; margin-bottom: 0.75rem; opacity: 0.5;"></i>
                            <div>{{ __('No wholesale products found.') }}</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($products->hasPages())
            <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color); background: var(--bg-main);">
                {{ $products->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

