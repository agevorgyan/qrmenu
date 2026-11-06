@extends('layouts.print_document')

@section('title', __('Purchase Order') . ' #' . $purchaseOrder->po_number)
@section('toolbar-title', __('Purchase Order') . ' #' . $purchaseOrder->po_number)

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
            @if($vendor->email)
                <div>{{ __('Email:') }} {{ $vendor->email }}</div>
            @endif
            @if($vendor->tax_id)
                <div>{{ __('Tax ID (TIN):') }} <strong>{{ $vendor->tax_id }}</strong></div>
            @endif
        </div>
    </div>
    <div class="doc-title-block">
        <div class="doc-title">{{ __('Purchase Order') }}</div>
        <div class="doc-number">#{{ $purchaseOrder->po_number }}</div>
        <div class="doc-date">
            {{ __('Date:') }} {{ $purchaseOrder->order_date?->toVendorTimezone()->format('Y-m-d') }}
        </div>
    </div>
</div>

<!-- Metadata Grid -->
<div class="doc-meta-grid">
    <div class="doc-meta-item">
        <label>{{ __('Supplier') }}</label>
        <span>{{ $purchaseOrder->supplier?->name ?? '—' }}</span>
        @if($purchaseOrder->supplier?->tax_id)
            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem;">
                {{ __('Tax ID:') }} {{ $purchaseOrder->supplier->tax_id }}
            </div>
        @endif
        @if($purchaseOrder->supplier?->phone)
            <div style="font-size: 0.75rem; color: #64748b;">
                {{ __('Phone:') }} {{ $purchaseOrder->supplier->phone }}
            </div>
        @endif
    </div>

    <div class="doc-meta-item">
        <label>{{ __('Target Warehouse') }}</label>
        <span>{{ $purchaseOrder->warehouse?->name ?? '—' }}</span>
        @if($purchaseOrder->warehouse?->address)
            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem;">
                {{ $purchaseOrder->warehouse->address }}
            </div>
        @endif
    </div>

    <div class="doc-meta-item">
        <label>{{ __('Expected Delivery') }}</label>
        <span>{{ $purchaseOrder->expected_delivery_date?->toVendorTimezone()->format('Y-m-d') ?: '—' }}</span>
    </div>

    <div class="doc-meta-item">
        <label>{{ __('Status') }}</label>
        <span style="text-transform: uppercase;">{{ str_replace('_', ' ', $purchaseOrder->status) }}</span>
    </div>
</div>

<!-- Items Table -->
<table class="doc-table">
    <thead>
        <tr>
            <th class="text-center" style="width: 35px;">#</th>
            <th>{{ __('Ingredient / Product') }}</th>
            <th style="width: 110px;">{{ __('SKU') }}</th>
            <th class="text-center" style="width: 70px;">{{ __('Unit') }}</th>
            <th class="text-right" style="width: 85px;">{{ __('Quantity') }}</th>
            <th class="text-right" style="width: 100px;">{{ __('Price') }}</th>
            <th class="text-right" style="width: 80px;">{{ __('Discount %') }}</th>
            <th class="text-right" style="width: 70px;">{{ __('Tax %') }}</th>
            <th class="text-right" style="width: 115px;">{{ __('Total') }} ({{ $vendor->currency }})</th>
        </tr>
    </thead>
    <tbody>
        @forelse($purchaseOrder->items as $index => $item)
            @php
                $itemSubtotal = $item->quantity_ordered * $item->unit_price;
                $discAmount = $itemSubtotal * (($item->discount_percent ?? 0) / 100);
                $taxAmount = ($itemSubtotal - $discAmount) * (($item->tax_percent ?? 0) / 100);
                $lineTotal = ($itemSubtotal - $discAmount) + $taxAmount;
            @endphp
            <tr>
                <td class="text-center mono">{{ $index + 1 }}</td>
                <td>
                    <strong>{{ $item->ingredient?->name }}</strong>
                </td>
                <td class="mono" style="font-size: 0.78rem; color: #64748b;">
                    {{ $item->supplier_sku ?: ($item->ingredient?->sku ?: '—') }}
                </td>
                <td class="text-center">
                    {{ $item->unit?->code ?? ($item->ingredient?->unit?->code ?? '') }}
                </td>
                <td class="text-right mono">
                    <strong>{{ (float) $item->quantity_ordered }}</strong>
                </td>
                <td class="text-right mono">
                    {{ number_format($item->unit_price, 2) }}
                </td>
                <td class="text-right mono">
                    {{ (float) $item->discount_percent > 0 ? (float) $item->discount_percent . '%' : '—' }}
                </td>
                <td class="text-right mono">
                    {{ (float) $item->tax_percent > 0 ? (float) $item->tax_percent . '%' : '—' }}
                </td>
                <td class="text-right mono">
                    <strong>{{ number_format($lineTotal, 2) }}</strong>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="text-center" style="padding: 2rem; color: #94a3b8;">
                    {{ __('No items found in this purchase order.') }}
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

<!-- Financial Totals Block -->
<div class="doc-totals">
    @php
        $calcSubtotal = 0;
        $calcItemDiscounts = 0;
        $calcTaxes = 0;
        foreach($purchaseOrder->items as $it) {
            $base = $it->quantity_ordered * $it->unit_price;
            $calcSubtotal += $base;
            $disc = $base * (($it->discount_percent ?? 0) / 100);
            $calcItemDiscounts += $disc;
            $tax = ($base - $disc) * (($it->tax_percent ?? 0) / 100);
            $calcTaxes += $tax;
        }
        $netAfterItemDisc = max(0, $calcSubtotal - $calcItemDiscounts);
        $orderDisc = $netAfterItemDisc * (($purchaseOrder->discount_percent ?? 0) / 100);
        $totalDiscount = $calcItemDiscounts + $orderDisc;
        $grandTotal = ($calcSubtotal - $totalDiscount) + $calcTaxes;
    @endphp

    <div class="doc-total-row">
        <span>{{ __('Subtotal:') }}</span>
        <span class="mono">{{ number_format($calcSubtotal, 2) }} {{ $vendor->currency }}</span>
    </div>

    @if($totalDiscount > 0)
        <div class="doc-total-row" style="color: #10b981;">
            <span>{{ __('Total Discount:') }}</span>
            <span class="mono">-{{ number_format($totalDiscount, 2) }} {{ $vendor->currency }}</span>
        </div>
    @endif

    @if($calcTaxes > 0)
        <div class="doc-total-row">
            <span>{{ __('Tax / VAT:') }}</span>
            <span class="mono">+{{ number_format($calcTaxes, 2) }} {{ $vendor->currency }}</span>
        </div>
    @endif

    <div class="doc-total-row grand-total">
        <span>{{ __('Grand Total:') }}</span>
        <span class="mono">{{ number_format($grandTotal, 2) }} {{ $vendor->currency }}</span>
    </div>
</div>

<!-- Notes / Instructions -->
@if(!empty($purchaseOrder->notes))
    <div class="doc-notes">
        <strong style="display: block; margin-bottom: 0.25rem; color: #0f172a;">{{ __('Order Notes / Instructions:') }}</strong>
        {{ $purchaseOrder->notes }}
    </div>
@endif

<!-- Official Signatures Block -->
<div class="doc-signatures">
    <div class="doc-sig-box">
        <div class="doc-sig-title">{{ __('Ordered by (Procurement):') }}</div>
        <div class="doc-sig-line"></div>
        <div class="doc-sig-sub">{{ $purchaseOrder->creator?->name ?? '________________' }}</div>
    </div>
    <div class="doc-sig-box">
        <div class="doc-sig-title">{{ __('Approved by:') }}</div>
        <div class="doc-sig-line"></div>
        <div class="doc-sig-sub">{{ $purchaseOrder->approver?->name ?? '________________' }}</div>
    </div>
    <div class="doc-sig-box">
        <div class="doc-sig-title">{{ __('Supplier Acceptance / Stamp:') }}</div>
        <div class="doc-sig-line"></div>
        <div class="doc-sig-sub">{{ __('Signature & Date') }}</div>
    </div>
</div>
@endsection

