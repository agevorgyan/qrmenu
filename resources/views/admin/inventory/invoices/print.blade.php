@extends('layouts.print_document')

@section('title', __('Goods Receipt Act / Invoice') . ' #' . $supplierInvoice->invoice_number)
@section('toolbar-title', __('Goods Receipt Act / Invoice') . ' #' . $supplierInvoice->invoice_number)

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
            @if($vendor->tax_id)
                <div>{{ __('Tax ID (TIN):') }} <strong>{{ $vendor->tax_id }}</strong></div>
            @endif
        </div>
    </div>
    <div class="doc-title-block">
        <div class="doc-title">{{ __('Goods Receipt Act & Invoice') }}</div>
        <div class="doc-number">#{{ $supplierInvoice->invoice_number }}</div>
        @if($supplierInvoice->series || $supplierInvoice->number)
            <div style="font-size: 0.82rem; font-family: monospace; color: #4338ca; margin-top: 0.15rem;">
                {{ __('Tax Invoice (e-Invoicing):') }} <strong>{{ $supplierInvoice->series }}{{ $supplierInvoice->number }}</strong>
            </div>
        @endif
        <div class="doc-date">
            {{ __('Invoice Date:') }} {{ $supplierInvoice->invoice_date?->toVendorTimezone()->format('Y-m-d') }}
        </div>
    </div>
</div>

<!-- Metadata Grid -->
<div class="doc-meta-grid">
    <div class="doc-meta-item">
        <label>{{ __('Seller / Supplier') }}</label>
        <span>{{ $supplierInvoice->supplier?->name ?? '—' }}</span>
        @if($supplierInvoice->supplier?->tax_id)
            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem;">
                {{ __('Tax ID:') }} {{ $supplierInvoice->supplier->tax_id }}
            </div>
        @endif
        @if($supplierInvoice->supplier?->phone)
            <div style="font-size: 0.75rem; color: #64748b;">
                {{ __('Phone:') }} {{ $supplierInvoice->supplier->phone }}
            </div>
        @endif
    </div>

    <div class="doc-meta-item">
        <label>{{ __('Buyer / Warehouse') }}</label>
        <span>{{ $supplierInvoice->warehouse?->name ?? '—' }}</span>
        @if($supplierInvoice->warehouse?->address)
            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem;">
                {{ $supplierInvoice->warehouse->address }}
            </div>
        @endif
    </div>

    <div class="doc-meta-item">
        <label>{{ __('Linked Purchase Order') }}</label>
        <span class="mono">{{ $supplierInvoice->purchaseOrder ? '#' . $supplierInvoice->purchaseOrder->po_number : '—' }}</span>
        <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem;">
            {{ __('Payment Terms:') }} {{ str_replace('_', ' ', $supplierInvoice->payment_terms ?: 'cash') }}
        </div>
    </div>

    <div class="doc-meta-item">
        <label>{{ __('Payment Status') }}</label>
        <span style="text-transform: uppercase;">{{ str_replace('_', ' ', $supplierInvoice->payment_status) }}</span>
        @if($supplierInvoice->due_date)
            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem;">
                {{ __('Due Date:') }} {{ $supplierInvoice->due_date?->toVendorTimezone()->format('Y-m-d') }}
            </div>
        @endif
    </div>
</div>

<!-- Items Table -->
<table class="doc-table">
    <thead>
        <tr>
            <th class="text-center" style="width: 35px;">#</th>
            <th>{{ __('Product / Item Description') }}</th>
            <th style="width: 100px;">{{ __('Classifier Code') }}</th>
            <th class="text-center" style="width: 65px;">{{ __('Unit') }}</th>
            <th class="text-right" style="width: 80px;">{{ __('Quantity') }}</th>
            <th class="text-right" style="width: 95px;">{{ __('Price (Net)') }}</th>
            <th class="text-right" style="width: 70px;">{{ __('VAT %') }}</th>
            <th class="text-right" style="width: 85px;">{{ __('VAT Amount') }}</th>
            <th class="text-right" style="width: 110px;">{{ __('Total (Gross)') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($supplierInvoice->items as $index => $item)
            <tr>
                <td class="text-center mono">{{ $index + 1 }}</td>
                <td>
                    <strong>{{ $item->description }}</strong>
                    @if($item->is_converted && $item->ingredient)
                        <div style="font-size: 0.72rem; color: #4338ca; margin-top: 0.15rem;">
                            <i class="fa-solid fa-arrows-rotate"></i>
                            {{ __('Converted to kitchen ingredient:') }} <strong>{{ $item->ingredient->name }}</strong>
                            ({{ (float)$item->quantity }} {{ $item->unit_name }})
                        </div>
                    @endif
                </td>
                <td class="mono" style="font-size: 0.78rem; color: #64748b;">
                    {{ $item->classifier_code ?: '—' }}
                </td>
                <td class="text-center">
                    {{ $item->original_unit_name ?: ($item->unit_name ?: ($item->unit?->code ?? '')) }}
                </td>
                <td class="text-right mono">
                    <strong>{{ (float) ($item->original_quantity ?: $item->quantity) }}</strong>
                </td>
                <td class="text-right mono">
                    {{ number_format($item->unit_price, 2) }}
                </td>
                <td class="text-right mono">
                    {{ (float) $item->vat_rate > 0 ? (float) $item->vat_rate . '%' : '0%' }}
                </td>
                <td class="text-right mono">
                    {{ number_format($item->vat_amount, 2) }}
                </td>
                <td class="text-right mono">
                    <strong>{{ number_format($item->total_price, 2) }}</strong>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="text-center" style="padding: 2rem; color: #94a3b8;">
                    {{ __('No items found in this invoice.') }}
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

<!-- Financial Totals Block -->
<div class="doc-totals">
    <div class="doc-total-row">
        <span>{{ __('Subtotal (excl. VAT):') }}</span>
        <span class="mono">{{ number_format($supplierInvoice->subtotal, 2) }} {{ $vendor->currency }}</span>
    </div>
    <div class="doc-total-row">
        <span>{{ __('Total VAT Amount:') }}</span>
        <span class="mono">+{{ number_format($supplierInvoice->tax_amount, 2) }} {{ $vendor->currency }}</span>
    </div>
    <div class="doc-total-row grand-total">
        <span>{{ __('Total Payable (incl. VAT):') }}</span>
        <span class="mono">{{ number_format($supplierInvoice->total_amount, 2) }} {{ $vendor->currency }}</span>
    </div>
    <div class="doc-total-row" style="margin-top: 0.4rem; padding-top: 0.4rem; border-top: 1px dashed #cbd5e1;">
        <span>{{ __('Paid to Date:') }}</span>
        <span class="mono" style="color: #10b981; font-weight: 700;">{{ number_format($supplierInvoice->paid_amount, 2) }} {{ $vendor->currency }}</span>
    </div>
    <div class="doc-total-row" style="font-weight: 700; color: {{ $supplierInvoice->remaining_balance > 0 ? '#ef4444' : '#10b981' }};">
        <span>{{ __('Outstanding Balance:') }}</span>
        <span class="mono">{{ number_format($supplierInvoice->remaining_balance, 2) }} {{ $vendor->currency }}</span>
    </div>
</div>

<!-- Notes -->
@if(!empty($supplierInvoice->notes))
    <div class="doc-notes">
        <strong style="display: block; margin-bottom: 0.25rem; color: #0f172a;">{{ __('Notes / Acceptance Remarks:') }}</strong>
        {{ $supplierInvoice->notes }}
    </div>
@endif

<!-- Official Signatures Block -->
<div class="doc-signatures">
    <div class="doc-sig-box">
        <div class="doc-sig-title">{{ __('Delivered by (Supplier):') }}</div>
        <div class="doc-sig-line"></div>
        <div class="doc-sig-sub">{{ __('Signature & Date') }}</div>
    </div>
    <div class="doc-sig-box">
        <div class="doc-sig-title">{{ __('Received into Stock (Warehouse):') }}</div>
        <div class="doc-sig-line"></div>
        <div class="doc-sig-sub">{{ $supplierInvoice->receiver?->name ?? '________________' }}</div>
    </div>
    <div class="doc-sig-box">
        <div class="doc-sig-title">{{ __('Accountant / Chief Storekeeper:') }}</div>
        <div class="doc-sig-line"></div>
        <div class="doc-sig-sub">{{ __('Signature & Stamp (Կ.Տ.)') }}</div>
    </div>
</div>
@endsection
