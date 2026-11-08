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
                <div style="margin-bottom: 0.75rem;">
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem;">{{ __('Default Fulfillment Warehouse') }}</div>
                    @if($product->defaultWarehouse)
                        <div style="display: inline-flex; align-items: center; gap: 0.4rem; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.35rem 0.65rem; font-size: 0.85rem; font-weight: 700; color: var(--text-main);">
                            <i class="fa-solid fa-warehouse" style="color: #6366f1;"></i>
                            <span>{{ $product->defaultWarehouse->name }} ({{ $product->defaultWarehouse->code ?: 'WH-'.$product->defaultWarehouse->id }})</span>
                        </div>
                    @else
                        <span style="font-size: 0.85rem; color: var(--text-muted); font-style: italic;">
                            {{ __('Central / System Default Warehouse') }}
                        </span>
                    @endif
                </div>

                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem;">{{ __('Core Inventory Link') }}</div>
                @if($product->physicalStockIngredient)
                    <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 8px; padding: 0.4rem 0.75rem; font-size: 0.85rem; font-weight: 700; color: #6366f1;">
                        <i class="fa-solid fa-link"></i>
                        <span>{{ $product->physicalStockIngredient->name }} [SKU: {{ $product->physicalStockIngredient->sku ?? 'N/A' }}] ({{ __('Stock:') }} {{ number_format($product->physicalStockIngredient->total_stock_on_hand, 2) }})</span>
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

    <!-- Technological Card (Recipe / BOM) & Production Integration Section -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.75rem; margin-bottom: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.02);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; background: rgba(99, 102, 241, 0.15); color: #6366f1;">
                        <i class="fa-solid fa-scroll"></i>
                    </span>
                    <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0;">
                        {{ __('Տեխնոլոգիական Քարտ (Recipe / BOM) և Արտադրություն') }}
                    </h2>
                </div>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                    {{ __('Արտադրական բաղադրատոմս, հումքերի ծախս, ինքնարժեք և ինտեգրում Պահեստ և Արտադրություն մոդուլի հետ:') }}
                </p>
            </div>

            @if($product->recipe)
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <a href="{{ route('admin.inventory.recipes.show', $product->recipe) }}" target="_blank"
                       style="padding: 0.5rem 0.9rem; border-radius: 8px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.82rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> {{ __('Դիտել Տեխ․ Քարտը') }}
                    </a>
                    <a href="{{ route('admin.inventory.production.create', ['recipe_id' => $product->recipe->id, 'destination_warehouse_id' => $product->default_warehouse_id]) }}" target="_blank"
                       style="padding: 0.5rem 1rem; border-radius: 8px; background: #10b981; color: #fff; font-size: 0.82rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                        <i class="fa-solid fa-industry"></i> {{ __('Սկսել Արտադրություն') }}
                    </a>
                </div>
            @endif
        </div>

        @if($product->recipe)
            @php
                $recipe = $product->recipe;
                $cost = $recipe->latestCost;
                $unitCost = $cost ? ($cost->cost_per_portion ?: $cost->total_cost) : 0;
                $marginPercent = $product->base_wholesale_price > 0 && $unitCost > 0
                    ? round((($product->base_wholesale_price - $unitCost) / $product->base_wholesale_price) * 100, 1)
                    : null;
            @endphp

            <!-- Recipe Summary Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem;">
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Քարտի Անվանում և Կոդ') }}</div>
                    <div style="font-size: 1rem; font-weight: 800; color: var(--text-main); margin-top: 0.25rem;">{{ $recipe->name }}</div>
                    <div style="font-size: 0.75rem; font-family: monospace; color: #6366f1; margin-top: 0.15rem;">{{ $recipe->code }} &bull; v{{ $recipe->current_version }}</div>
                </div>

                <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem;">
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Ելքի Քանակ (Yield)') }}</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: #10b981; margin-top: 0.25rem;">
                        {{ (float)$recipe->yield_quantity }} <span style="font-size: 0.85rem; font-weight: 600;">{{ $recipe->yieldUnit?->name ?? 'unit' }}</span>
                    </div>
                    @if($recipe->portion_size)
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem;">
                            {{ __('Բաժին:') }} {{ (float)$recipe->portion_size }} {{ $recipe->portionUnit?->code ?? '' }}
                        </div>
                    @endif
                </div>

                <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem;">
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Հաշվարկված Ինքնարժեք') }}</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-top: 0.25rem;">
                        {{ number_format($unitCost, 2) }} <span style="font-size: 0.85rem; font-weight: 600;">AMD</span>
                    </div>
                    @if($marginPercent !== null)
                        <div style="font-size: 0.75rem; font-weight: 700; color: {{ $marginPercent > 0 ? '#10b981' : '#ef4444' }}; margin-top: 0.15rem;">
                            {{ __('Մարժա:') }} {{ $marginPercent }}%
                        </div>
                    @endif
                </div>

                <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem;">
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Կարգավիճակ') }}</div>
                    <div style="margin-top: 0.4rem;">
                        <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; background: rgba(16, 185, 129, 0.12); color: #10b981;">
                            <i class="fa-solid fa-circle-check"></i> {{ ucfirst($recipe->status) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Ingredients / BOM Table -->
            @if($recipe->items->count() > 0)
                <div style="font-weight: 700; font-size: 0.88rem; color: var(--text-main); margin-bottom: 0.75rem;">
                    <i class="fa-solid fa-list-check" style="color: #6366f1; margin-right: 0.4rem;"></i>
                    {{ __('Բաղադրիչների և Հումքերի Ցանկ (BOM Items)') }} ({{ $recipe->items->count() }})
                </div>
                <div style="overflow-x: auto; margin-bottom: 1.5rem; border: 1px solid var(--border-color); border-radius: 10px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                        <thead>
                            <tr style="background: var(--bg-main); border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                <th style="padding: 0.65rem 0.85rem;">{{ __('Բաղադրիչ / Հումք') }}</th>
                                <th style="padding: 0.65rem 0.85rem;">{{ __('Բրուտտո Քանակ') }}</th>
                                <th style="padding: 0.65rem 0.85rem;">{{ __('Նետտո Քանակ') }}</th>
                                <th style="padding: 0.65rem 0.85rem;">{{ __('Կորուստ (%)') }}</th>
                                <th style="padding: 0.65rem 0.85rem;">{{ __('Միավոր') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recipe->items as $item)
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 0.65rem 0.85rem; font-weight: 600; color: var(--text-main);">
                                        @if($item->ingredient)
                                            <i class="fa-solid fa-seedling" style="color: #10b981; margin-right: 0.35rem; font-size: 0.75rem;"></i>
                                            {{ $item->ingredient->name }}
                                        @elseif($item->subRecipe)
                                            <i class="fa-solid fa-cubes-stacked" style="color: #8b5cf6; margin-right: 0.35rem; font-size: 0.75rem;"></i>
                                            {{ $item->subRecipe->name }} [{{ __('Կիսաֆաբրիկատ') }}]
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td style="padding: 0.65rem 0.85rem; font-family: monospace; font-weight: 700; color: var(--text-main);">
                                        {{ (float)$item->gross_quantity }}
                                    </td>
                                    <td style="padding: 0.65rem 0.85rem; font-family: monospace; color: var(--text-muted);">
                                        {{ (float)$item->net_quantity }}
                                    </td>
                                    <td style="padding: 0.65rem 0.85rem; color: var(--text-muted);">
                                        {{ (float)$item->waste_percent > 0 ? (float)$item->waste_percent . '%' : '0%' }}
                                    </td>
                                    <td style="padding: 0.65rem 0.85rem; color: var(--text-muted);">
                                        {{ $item->unit?->name ?? $item->unit?->code ?? ($item->ingredient?->unit?->code ?? '') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @else
            <!-- Empty state when no recipe is linked -->
            <div style="text-align: center; padding: 2rem; background: var(--bg-main); border-radius: 12px; border: 1px dashed var(--border-color);">
                <i class="fa-solid fa-scroll" style="font-size: 2.2rem; opacity: 0.3; margin-bottom: 0.5rem; display: block; color: #6366f1;"></i>
                <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-main); margin-bottom: 0.25rem;">
                    {{ __('Ապրանքին կցված չէ Տեխնոլոգիական Քարտ') }}
                </div>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0 0 1rem 0; max-width: 550px; margin-left: auto; margin-right: auto;">
                    {{ __('Կցեք գոյություն ունեցող կամ ստեղծեք նոր Տեխնոլոգիական Քարտ (Recipe / BOM)՝ հումքերի դուրսգրումը, ինքնարժեքը և արտադրության պատվերներն ավտոմատացնելու համար:') }}
                </p>
                <div style="display: inline-flex; gap: 0.75rem;">
                    <a href="{{ route('admin.wholesale.products.edit', $product) }}" 
                       style="padding: 0.55rem 1.15rem; border-radius: 8px; background: #6366f1; color: #fff; font-size: 0.85rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
                        <i class="fa-solid fa-link"></i> {{ __('Կցել Տեխնոլոգիական Քարտ') }}
                    </a>
                    <a href="{{ route('admin.inventory.recipes.create') }}" target="_blank"
                       style="padding: 0.55rem 1.15rem; border-radius: 8px; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.85rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
                        <i class="fa-solid fa-plus"></i> {{ __('Ստեղծել նոր Քարտ') }}
                    </a>
                </div>
            </div>
        @endif
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

