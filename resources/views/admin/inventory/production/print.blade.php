@extends('layouts.print_document')

@section('title', __('Production Act') . ' #' . $productionOrder->batch_number)
@section('toolbar-title', __('Production Act') . ' #' . $productionOrder->batch_number)

@section('content')
@php
    $vendor = Auth::user()->vendor;
    $outputName = $productionOrder->recipe?->name ?? ($productionOrder->producedIngredient?->name ?? __('Production Item'));
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
        <div class="doc-title">{{ __('Production Act & Output') }}</div>
        <div class="doc-number">№ {{ $productionOrder->batch_number }}</div>
        <div class="doc-date">
            {{ __('Date:') }} {{ ($productionOrder->completed_at ?? $productionOrder->scheduled_date)?->toVendorTimezone()->format('Y-m-d') }}
        </div>
    </div>
</div>

<!-- Metadata Grid -->
<div class="doc-meta-grid">
    <div class="doc-meta-item">
        <label>{{ __('Produced Item') }}</label>
        <span style="font-size: 1.05rem;">{{ $outputName }}</span>
        @if($productionOrder->recipe)
            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem;">
                {{ __('Recipe Code:') }} {{ $productionOrder->recipe->code ?: ('REC-' . $productionOrder->recipe->id) }}
            </div>
        @endif
    </div>

    <div class="doc-meta-item">
        <label>{{ __('Quantity Produced') }}</label>
        <span>
            <strong>{{ (float) ($productionOrder->actual_quantity ?: $productionOrder->planned_quantity) }}</strong>
            {{ $productionOrder->unit?->code }}
        </span>
        <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem;">
            {{ __('Planned:') }} {{ (float) $productionOrder->planned_quantity }} {{ $productionOrder->unit?->code }}
        </div>
    </div>

    <div class="doc-meta-item">
        <label>{{ __('Warehouse Transfer') }}</label>
        <span>{{ $productionOrder->sourceWarehouse?->name }} <i class="fa-solid fa-arrow-right" style="font-size: 0.7rem; color: #94a3b8;"></i> {{ $productionOrder->destinationWarehouse?->name }}</span>
    </div>

    <div class="doc-meta-item">
        <label>{{ __('Status & Operator') }}</label>
        <span style="text-transform: uppercase;">{{ str_replace('_', ' ', $productionOrder->status) }}</span>
        <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem;">
            {{ __('Chef:') }} {{ $productionOrder->producedBy?->name ?? '—' }}
        </div>
    </div>
</div>

<!-- Consumed Raw Materials Table -->
<div class="doc-section-title">
    <i class="fa-solid fa-boxes-stacked"></i>
    <span>{{ __('Raw Materials & Ingredients Consumed') }}</span>
</div>

<table class="doc-table">
    <thead>
        <tr>
            <th class="text-center" style="width: 35px;">#</th>
            <th>{{ __('Ingredient / Raw Material') }}</th>
            <th class="text-center" style="width: 65px;">{{ __('Unit') }}</th>
            <th class="text-right" style="width: 95px;">{{ __('Planned Qty') }}</th>
            <th class="text-right" style="width: 95px;">{{ __('Actual Qty') }}</th>
            <th class="text-right" style="width: 100px;">{{ __('Unit Cost') }}</th>
            <th class="text-right" style="width: 120px;">{{ __('Total Cost') }} ({{ $vendor->currency }})</th>
        </tr>
    </thead>
    <tbody>
        @forelse($productionOrder->items as $index => $item)
            <tr>
                <td class="text-center mono">{{ $index + 1 }}</td>
                <td>
                    <strong>{{ $item->ingredient?->name ?? $item->subRecipe?->name }}</strong>
                    @if($item->ingredient?->sku)
                        <span style="font-size: 0.72rem; color: #64748b; margin-left: 0.3rem;">({{ $item->ingredient->sku }})</span>
                    @endif
                </td>
                <td class="text-center">
                    {{ $item->unit?->code ?? ($item->ingredient?->unit?->code ?? '') }}
                </td>
                <td class="text-right mono">
                    {{ (float) $item->planned_quantity }}
                </td>
                <td class="text-right mono">
                    <strong>{{ (float) ($item->actual_quantity ?? $item->planned_quantity) }}</strong>
                </td>
                <td class="text-right mono">
                    {{ number_format($item->unit_cost, 2) }}
                </td>
                <td class="text-right mono">
                    <strong>{{ number_format($item->total_cost, 2) }}</strong>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center" style="padding: 2rem; color: #94a3b8;">
                    {{ __('No consumed materials recorded.') }}
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

<!-- Financial Totals Block -->
<div class="doc-totals">
    <div class="doc-total-row">
        <span>{{ __('Output Quantity:') }}</span>
        <span class="mono">{{ (float) ($productionOrder->actual_quantity ?: $productionOrder->planned_quantity) }} {{ $productionOrder->unit?->code }}</span>
    </div>
    <div class="doc-total-row grand-total">
        <span>{{ __('Total Production Cost:') }}</span>
        <span class="mono">{{ number_format($productionOrder->total_cost, 2) }} {{ $vendor->currency }}</span>
    </div>
    @if(($productionOrder->actual_quantity ?: $productionOrder->planned_quantity) > 0)
        @php
            $realQty = (float) ($productionOrder->actual_quantity ?: $productionOrder->planned_quantity);
            $realUnitCost = $realQty > 0 ? ($productionOrder->total_cost / $realQty) : $productionOrder->unit_cost;
        @endphp
        <div class="doc-total-row" style="color: #4f46e5; font-weight: 700; margin-top: 0.35rem; border-top: 1px dashed #cbd5e1; padding-top: 0.35rem;">
            <span>{{ __('Calculated Unit Cost:') }}</span>
            <span class="mono">{{ number_format($realUnitCost, 2) }} {{ $vendor->currency }} / {{ $productionOrder->unit?->code }}</span>
        </div>
    @endif
</div>

<!-- Notes -->
@if(!empty($productionOrder->notes))
    <div class="doc-notes">
        <strong style="display: block; margin-bottom: 0.25rem; color: #0f172a;">{{ __('Production & Quality Notes:') }}</strong>
        {{ $productionOrder->notes }}
    </div>
@endif

<!-- Official Signatures Block -->
<div class="doc-signatures">
    <div class="doc-sig-box">
        <div class="doc-sig-title">{{ __('Prepared / Chef:') }}</div>
        <div class="doc-sig-line"></div>
        <div class="doc-sig-sub">{{ $productionOrder->producedBy?->name ?? '________________' }}</div>
    </div>
    <div class="doc-sig-box">
        <div class="doc-sig-title">{{ __('Issued from Warehouse:') }}</div>
        <div class="doc-sig-line"></div>
        <div class="doc-sig-sub">{{ __('Signature & Date') }}</div>
    </div>
    <div class="doc-sig-box">
        <div class="doc-sig-title">{{ __('Received into Stock (Target):') }}</div>
        <div class="doc-sig-line"></div>
        <div class="doc-sig-sub">{{ __('Signature & Stamp') }}</div>
    </div>
</div>
@endsection

