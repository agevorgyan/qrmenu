@extends('layouts.print_document')

@section('title', __('Technological Card') . ' - ' . $recipe->name)
@section('toolbar-title', __('Technological Card') . ' - ' . $recipe->name)

@section('content')
@php
    $vendor = Auth::user()->vendor;
@endphp

<!-- Document Header -->
<div class="doc-header">
    <div class="doc-company">
        <div class="doc-company-name">{{ $vendor->name }}</div>
        <div class="doc-company-details">
            @if($vendor->address)
                <div>{{ $vendor->address }}</div>
            @endif
            @if($vendor->phone)
                <div>{{ __('Phone:') }} {{ $vendor->phone }}</div>
            @endif
        </div>
    </div>
    <div class="doc-title-block">
        <div class="doc-title">{{ __('Technological Card') }}</div>
        <div class="doc-number">№ {{ $recipe->code ?: ('REC-' . str_pad($recipe->id, 5, '0', STR_PAD_LEFT)) }}</div>
        <div class="doc-date">
            {{ __('Version:') }} v{{ $recipe->version }} • {{ $recipe->updated_at?->toVendorTimezone()->format('Y-m-d') }}
        </div>
    </div>
</div>

<!-- Metadata Grid -->
<div class="doc-meta-grid">
    <div class="doc-meta-item">
        <label>{{ __('Dish / Output Item') }}</label>
        <span style="font-size: 1.05rem;">{{ $recipe->name }}</span>
        @if($recipe->product)
            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem;">
                {{ __('Menu Item:') }} {{ $recipe->product->name }}
            </div>
        @endif
    </div>

    <div class="doc-meta-item">
        <label>{{ __('Category') }}</label>
        <span>{{ $recipe->category?->name ?: '—' }}</span>
        <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem;">
            {{ $recipe->type === 'semi_finished' ? __('Semi-Finished Item') : __('Final Menu Dish') }}
        </div>
    </div>

    <div class="doc-meta-item">
        <label>{{ __('Standard Yield / Batch') }}</label>
        <span>
            {{ (float) $recipe->yield_quantity }} {{ $recipe->yieldUnit?->name ?: $recipe->yieldUnit?->code }}
            @if($recipe->portion_size > 0)
                <span style="color: #64748b; font-size: 0.8rem;">({{ (float) $recipe->portion_size }} {{ $recipe->portionUnit?->code }}/{{ __('portion') }})</span>
            @endif
        </span>
    </div>

    <div class="doc-meta-item">
        <label>{{ __('Timing & Status') }}</label>
        <span>
            @if($recipe->prep_time_minutes || $recipe->cook_time_minutes)
                {{ ($recipe->prep_time_minutes + $recipe->cook_time_minutes) }} {{ __('min') }}
            @else
                —
            @endif
        </span>
        <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: {{ $recipe->status === 'approved' ? '#10b981' : '#f59e0b' }}; margin-top: 0.15rem;">
            {{ strtoupper($recipe->status) }}
        </div>
    </div>
</div>

<!-- Recipe Ingredients Table -->
<div class="doc-section-title">
    <i class="fa-solid fa-mortar-pestle"></i>
    <span>{{ __('Ingredients & Raw Materials Specification') }}</span>
</div>

<table class="doc-table">
    <thead>
        <tr>
            <th class="text-center" style="width: 35px;">#</th>
            <th>{{ __('Ingredient / Sub-Recipe') }}</th>
            <th class="text-center" style="width: 65px;">{{ __('Unit') }}</th>
            <th class="text-right" style="width: 85px;">{{ __('Gross Qty') }}</th>
            <th class="text-right" style="width: 75px;">{{ __('Loss %') }}</th>
            <th class="text-right" style="width: 85px;">{{ __('Net Qty') }}</th>
            @if($canViewCost)
                <th class="text-right" style="width: 95px;">{{ __('Cost') }}</th>
                <th class="text-right" style="width: 105px;">{{ __('Total') }} ({{ $vendor->currency }})</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @forelse($recipe->items as $index => $item)
            @php
                $itemLoss = (float) ($item->loss_percent_cold ?? 0);
            @endphp
            <tr>
                <td class="text-center mono">{{ $index + 1 }}</td>
                <td>
                    <strong>{{ $item->ingredient?->name ?? $item->subRecipe?->name }}</strong>
                    @if($item->is_sub_recipe)
                        <span style="font-size: 0.7rem; background: #e0e7ff; color: #4338ca; padding: 0.1rem 0.35rem; border-radius: 4px; font-weight: 700; margin-left: 0.3rem;">
                            {{ __('Sub-Recipe') }}
                        </span>
                    @endif
                </td>
                <td class="text-center">
                    {{ $item->unit?->code ?? ($item->ingredient?->unit?->code ?? '') }}
                </td>
                <td class="text-right mono">
                    {{ (float) $item->quantity_gross }}
                </td>
                <td class="text-right mono">
                    {{ $itemLoss > 0 ? $itemLoss . '%' : '—' }}
                </td>
                <td class="text-right mono">
                    <strong>{{ (float) $item->quantity_net }}</strong>
                </td>
                @if($canViewCost)
                    <td class="text-right mono">
                        {{ number_format($item->unit_cost, 2) }}
                    </td>
                    <td class="text-right mono">
                        <strong>{{ number_format($item->total_cost, 2) }}</strong>
                    </td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="{{ $canViewCost ? 8 : 6 }}" class="text-center" style="padding: 1.5rem; color: #94a3b8;">
                    {{ __('No ingredients recorded for this recipe.') }}
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

<!-- Packaging Materials Table (if any) -->
@if($recipe->packagings->isNotEmpty())
    <div class="doc-section-title">
        <i class="fa-solid fa-box"></i>
        <span>{{ __('Takeaway & Delivery Packaging Materials') }}</span>
    </div>

    <table class="doc-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 35px;">#</th>
                <th>{{ __('Packaging Item') }}</th>
                <th class="text-center" style="width: 70px;">{{ __('Unit') }}</th>
                <th class="text-right" style="width: 100px;">{{ __('Quantity') }}</th>
                @if($canViewCost)
                    <th class="text-right" style="width: 100px;">{{ __('Cost') }}</th>
                    <th class="text-right" style="width: 115px;">{{ __('Total') }} ({{ $vendor->currency }})</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($recipe->packagings as $pIdx => $pkg)
                <tr>
                    <td class="text-center mono">{{ $pIdx + 1 }}</td>
                    <td><strong>{{ $pkg->ingredient?->name }}</strong></td>
                    <td class="text-center">{{ $pkg->unit?->code ?? ($pkg->ingredient?->unit?->code ?? '') }}</td>
                    <td class="text-right mono"><strong>{{ (float) $pkg->quantity }}</strong></td>
                    @if($canViewCost)
                        <td class="text-right mono">{{ number_format($pkg->unit_cost, 2) }}</td>
                        <td class="text-right mono"><strong>{{ number_format($pkg->total_cost, 2) }}</strong></td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<!-- Preparation Process & Technology -->
@if(!empty($recipe->instructions))
    <div class="doc-section-title">
        <i class="fa-solid fa-list-ol"></i>
        <span>{{ __('Culinary Preparation Technology & Instructions') }}</span>
    </div>
    <div class="doc-notes" style="white-space: pre-line; line-height: 1.6;">
        {{ $recipe->instructions }}
    </div>
@endif

<!-- Allergens & HACCP Storage Notes -->
@if(!empty($recipe->allergens) || !empty($recipe->notes))
    <div class="doc-meta-grid" style="margin-top: 1rem;">
        @if(!empty($recipe->allergens))
            <div class="doc-meta-item">
                <label style="color: #ef4444;">{{ __('Allergens Present:') }}</label>
                <span style="color: #b91c1c;">
                    {{ is_array($recipe->allergens) ? implode(', ', $recipe->allergens) : $recipe->allergens }}
                </span>
            </div>
        @endif

        @if(!empty($recipe->notes))
            <div class="doc-meta-item" style="grid-column: span 2;">
                <label>{{ __('Quality / Storage Rules:') }}</label>
                <span>{{ $recipe->notes }}</span>
            </div>
        @endif
    </div>
@endif

<!-- Cost & Financial Summary (if allowed) -->
@if($canViewCost && $recipe->latestCost)
    @php
        $cost = $recipe->latestCost;
        $portionCount = (float) $recipe->yield_quantity;
        if ($recipe->portion_size > 0 && $recipe->yield_quantity > 0) {
            $portionCount = $recipe->yield_quantity / $recipe->portion_size;
        }
        $costPerPortion = $portionCount > 0 ? ($cost->total_cost / $portionCount) : $cost->total_cost;
    @endphp
    <div class="doc-totals">
        <div class="doc-total-row">
            <span>{{ __('Food Cost (Raw Materials):') }}</span>
            <span class="mono">{{ number_format($cost->food_cost, 2) }} {{ $vendor->currency }}</span>
        </div>
        @if((float) $cost->packaging_cost > 0)
            <div class="doc-total-row">
                <span>{{ __('Packaging Cost:') }}</span>
                <span class="mono">{{ number_format($cost->packaging_cost, 2) }} {{ $vendor->currency }}</span>
            </div>
        @endif
        <div class="doc-total-row grand-total">
            <span>{{ __('Total Cost per Batch:') }}</span>
            <span class="mono">{{ number_format($cost->total_cost, 2) }} {{ $vendor->currency }}</span>
        </div>
        @if($portionCount > 1)
            <div class="doc-total-row" style="color: #4f46e5; font-weight: 700; margin-top: 0.35rem; border-top: 1px dashed #cbd5e1; padding-top: 0.35rem;">
                <span>{{ __('Cost per 1 Portion:') }}</span>
                <span class="mono">{{ number_format($costPerPortion, 2) }} {{ $vendor->currency }}</span>
            </div>
        @endif
        @if($recipe->product && $recipe->product->price > 0)
            <div class="doc-total-row" style="color: #10b981; font-weight: 700;">
                <span>{{ __('Menu Selling Price:') }}</span>
                <span class="mono">{{ number_format($recipe->product->price, 2) }} {{ $vendor->currency }}</span>
            </div>
        @endif
    </div>
@endif

<!-- Official Culinary Signatures Block -->
<div class="doc-signatures">
    <div class="doc-sig-box">
        <div class="doc-sig-title">{{ __('Technologist / Calculator:') }}</div>
        <div class="doc-sig-line"></div>
        <div class="doc-sig-sub">{{ $recipe->creator?->name ?? '________________' }}</div>
    </div>
    <div class="doc-sig-box">
        <div class="doc-sig-title">{{ __('Executive Chef:') }}</div>
        <div class="doc-sig-line"></div>
        <div class="doc-sig-sub">{{ __('Signature & Date') }}</div>
    </div>
    <div class="doc-sig-box">
        <div class="doc-sig-title">{{ __('Approved by Director:') }}</div>
        <div class="doc-sig-line"></div>
        <div class="doc-sig-sub">{{ $recipe->approver?->name ?? '________________' }} (Կ.Տ.)</div>
    </div>
</div>
@endsection

