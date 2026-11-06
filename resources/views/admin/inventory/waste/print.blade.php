@extends('layouts.print_document')

@section('title', __('Waste Write-Off Act') . ' #' . $wasteLog->waste_number)
@section('toolbar-title', __('Waste Write-Off Act') . ' #' . $wasteLog->waste_number)

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
        <div class="doc-title">{{ __('Waste & Spoilage Write-Off Act') }}</div>
        <div class="doc-number">№ {{ $wasteLog->waste_number }}</div>
        <div class="doc-date">
            {{ __('Date:') }} {{ ($wasteLog->logged_at ?? $wasteLog->created_at)?->toVendorTimezone()->format('Y-m-d H:i') }}
        </div>
    </div>
</div>

<!-- Metadata Grid -->
<div class="doc-meta-grid">
    <div class="doc-meta-item">
        <label>{{ __('Warehouse') }}</label>
        <span>{{ $wasteLog->warehouse?->name ?? '—' }}</span>
        @if($wasteLog->warehouse?->code)
            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem;">
                {{ __('Code:') }} {{ $wasteLog->warehouse->code }}
            </div>
        @endif
    </div>

    <div class="doc-meta-item">
        <label>{{ __('Reason for Write-Off') }}</label>
        <span style="color: #ef4444; font-weight: 700; text-transform: uppercase;">
            {{ str_replace('_', ' ', $wasteLog->reason) }}
        </span>
    </div>

    <div class="doc-meta-item">
        <label>{{ __('Logged By') }}</label>
        <span>{{ $wasteLog->loggedBy?->name ?? '—' }}</span>
    </div>

    <div class="doc-meta-item">
        <label>{{ __('Authorized By') }}</label>
        <span>{{ $wasteLog->authorizedBy?->name ?? '—' }}</span>
    </div>
</div>

<!-- Written-Off Item Table -->
<div class="doc-section-title">
    <i class="fa-solid fa-trash-can"></i>
    <span>{{ __('Written-Off Inventory Item Details') }}</span>
</div>

<table class="doc-table">
    <thead>
        <tr>
            <th class="text-center" style="width: 35px;">#</th>
            <th>{{ __('Ingredient / Material') }}</th>
            <th style="width: 120px;">{{ __('SKU / Batch') }}</th>
            <th class="text-center" style="width: 70px;">{{ __('Unit') }}</th>
            <th class="text-right" style="width: 100px;">{{ __('Quantity') }}</th>
            <th class="text-right" style="width: 110px;">{{ __('Unit Cost') }}</th>
            <th class="text-right" style="width: 130px;">{{ __('Financial Loss') }} ({{ $vendor->currency }})</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-center mono">1</td>
            <td>
                <strong>{{ $wasteLog->ingredient?->name }}</strong>
            </td>
            <td class="mono" style="font-size: 0.78rem; color: #64748b;">
                {{ $wasteLog->batch?->batch_number ?: ($wasteLog->ingredient?->sku ?: '—') }}
            </td>
            <td class="text-center">
                {{ $wasteLog->unit?->code ?? ($wasteLog->unit?->symbol ?? ($wasteLog->ingredient?->unit?->code ?? 'kg')) }}
            </td>
            <td class="text-right mono" style="color: #ef4444; font-weight: 700;">
                {{ (float) $wasteLog->quantity }}
            </td>
            <td class="text-right mono">
                {{ number_format($wasteLog->unit_cost, 2) }}
            </td>
            <td class="text-right mono" style="font-weight: 800; color: #ef4444;">
                {{ number_format($wasteLog->total_cost, 2) }}
            </td>
        </tr>
    </tbody>
</table>

<!-- Financial Summary Block -->
<div class="doc-totals">
    <div class="doc-total-row">
        <span>{{ __('Total Wasted Qty:') }}</span>
        <span class="mono">{{ (float) $wasteLog->quantity }} {{ $wasteLog->unit?->code ?? 'kg' }}</span>
    </div>
    <div class="doc-total-row grand-total" style="color: #ef4444;">
        <span>{{ __('Total Financial Loss:') }}</span>
        <span class="mono">{{ number_format($wasteLog->total_cost, 2) }} {{ $vendor->currency }}</span>
    </div>
</div>

<!-- Reason & Explanation Notes -->
@if(!empty($wasteLog->notes))
    <div class="doc-notes">
        <strong style="display: block; margin-bottom: 0.25rem; color: #0f172a;">{{ __('Act Justification & Notes:') }}</strong>
        {{ $wasteLog->notes }}
    </div>
@endif

<!-- Commission Signatures Block -->
<div class="doc-signatures">
    <div class="doc-sig-box">
        <div class="doc-sig-title">{{ __('Storekeeper / Chef:') }}</div>
        <div class="doc-sig-line"></div>
        <div class="doc-sig-sub">{{ $wasteLog->loggedBy?->name ?? '________________' }}</div>
    </div>
    <div class="doc-sig-box">
        <div class="doc-sig-title">{{ __('Accountant / Checker:') }}</div>
        <div class="doc-sig-line"></div>
        <div class="doc-sig-sub">{{ $wasteLog->authorizedBy?->name ?? '________________' }}</div>
    </div>
    <div class="doc-sig-box">
        <div class="doc-sig-title">{{ __('Approved by Director:') }}</div>
        <div class="doc-sig-line"></div>
        <div class="doc-sig-sub">{{ __('Signature & Stamp (Կ.Տ.)') }}</div>
    </div>
</div>
@endsection

