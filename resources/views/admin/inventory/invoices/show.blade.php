@extends('layouts.app')

@section('title', __('Supplier Invoice #') . $supplierInvoice->invoice_number . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="max-width: 1050px; margin: 0 auto;" x-data="invoiceShowPage()" x-cloak>
    <!-- Top Action Navigation -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="{{ route('admin.inventory.invoices.index') }}" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-decoration: none; margin-bottom: 0.5rem;">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Invoices & AP') }}
            </a>
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ __('Invoice #') }} {{ $supplierInvoice->invoice_number }}
                </h1>
                @php
                    $stBadge = [
                        'paid' => ['bg' => 'rgba(16, 185, 129, 0.12)', 'color' => '#10b981', 'label' => __('Paid')],
                        'partially_paid' => ['bg' => 'rgba(245, 158, 11, 0.12)', 'color' => '#f59e0b', 'label' => __('Partially Paid')],
                        'unpaid' => ['bg' => 'rgba(239, 68, 68, 0.12)', 'color' => '#ef4444', 'label' => __('Unpaid')],
                        'overdue' => ['bg' => 'rgba(220, 38, 38, 0.2)', 'color' => '#dc2626', 'label' => __('Overdue')],
                    ];
                    $isOverdue = $supplierInvoice->payment_status !== 'paid' && $supplierInvoice->due_date->isPast();
                    $b = $stBadge[$isOverdue ? 'overdue' : $supplierInvoice->payment_status] ?? ['bg' => 'rgba(0,0,0,0.05)', 'color' => 'inherit', 'label' => $supplierInvoice->payment_status];
                @endphp
                <span style="display: inline-block; padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; background: {{ $b['bg'] }}; color: {{ $b['color'] }};">
                    {{ $b['label'] }}
                </span>
            </div>
        </div>

        <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
            <a href="{{ route('admin.inventory.invoices.print', $supplierInvoice) }}" target="_blank" class="btn btn-secondary" style="border-radius: 10px; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; text-decoration: none;">
                <i class="fa-solid fa-print"></i> {{ __('Print Invoice') }}
            </a>

            @can('procurement.pay')
                @if((float) $supplierInvoice->remaining_balance > 0)
                    <button type="button" onclick="document.getElementById('invoicePayModal').showModal();" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-money-bill-wave"></i> {{ __('Pay Invoice') }}
                    </button>
                @endif
            @endcan
        </div>
    </div>

    <!-- Invoice Header Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; box-shadow: var(--shadow-card);">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">{{ __('Supplier Information') }}</div>
            <div style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.35rem;">
                <a href="{{ route('admin.inventory.suppliers.show', $supplierInvoice->supplier) }}" style="color: var(--text-main); text-decoration: none;">
                    {{ $supplierInvoice->supplier->name }}
                </a>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">
                @if($supplierInvoice->supplier->tax_id)
                    <div><strong>{{ __('TIN (ՀՎՀՀ):') }}</strong> {{ $supplierInvoice->supplier->tax_id }}</div>
                @endif
                @if($supplierInvoice->supplier->vat_number)
                    <div><strong>{{ __('VAT Number:') }}</strong> {{ $supplierInvoice->supplier->vat_number }}</div>
                @endif
                @if($supplierInvoice->supplier->bank_account)
                    <div><strong>{{ __('Bank:') }}</strong> {{ $supplierInvoice->supplier->bank_name }} <span style="font-family: monospace;">{{ $supplierInvoice->supplier->bank_account }}</span></div>
                @endif
                @if($supplierInvoice->supply_location)
                    <div><strong>{{ __('Supply Location:') }}</strong> {{ $supplierInvoice->supply_location }}</div>
                @endif
                @if($supplierInvoice->supplier->phone)
                    <div><i class="fa-solid fa-phone"></i> {{ $supplierInvoice->supplier->phone }}</div>
                @endif
            </div>
        </div>

        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; box-shadow: var(--shadow-card);">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">{{ __('Dates & Warehouse') }}</div>
            <div style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.35rem;">
                <i class="fa-solid fa-warehouse" style="color: #3b82f6;"></i> {{ $supplierInvoice->warehouse->name }}
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">
                <div><strong>{{ __('Invoice Date:') }}</strong> {{ $supplierInvoice->invoice_date->toVendorTimezone()->format('M d, Y') }}</div>
                @if($supplierInvoice->supply_date)
                    <div><strong>{{ __('Supply Date:') }}</strong> {{ $supplierInvoice->supply_date->toVendorTimezone()->format('M d, Y') }}</div>
                @endif
                <div><strong>{{ __('Due Date:') }}</strong> {{ $supplierInvoice->due_date->toVendorTimezone()->format('M d, Y') }}</div>
                @if($supplierInvoice->delivery_location)
                    <div><strong>{{ __('Delivery Location:') }}</strong> {{ $supplierInvoice->delivery_location }}</div>
                @endif
                @if($supplierInvoice->additional_data)
                    <div><strong>{{ __('Additional Data:') }}</strong> {{ $supplierInvoice->additional_data }}</div>
                @endif
                @if($supplierInvoice->purchaseOrder)
                    <div>
                        <strong>{{ __('PO:') }}</strong>
                        <a href="{{ route('admin.inventory.procurement.show', $supplierInvoice->purchaseOrder) }}" style="color: #6366f1; text-decoration: none;">
                            {{ $supplierInvoice->purchaseOrder->po_number }}
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; box-shadow: var(--shadow-card);">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">{{ __('Debt & Balance') }}</div>
            <div style="font-size: 1.6rem; font-weight: 800; font-family: monospace; color: {{ (float) $supplierInvoice->remaining_balance > 0 ? '#ef4444' : '#10b981' }}; margin-bottom: 0.35rem;">
                {{ number_format($supplierInvoice->remaining_balance, 2) }} {{ Auth::user()->vendor->currency }}
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.4;">
                <div>{{ __('Subtotal (excl. VAT):') }} {{ number_format($supplierInvoice->subtotal, 2) }} {{ Auth::user()->vendor->currency }}</div>
                <div>{{ __('VAT Amount:') }} {{ number_format($supplierInvoice->tax_amount, 2) }} {{ Auth::user()->vendor->currency }}</div>
                @if((float) $supplierInvoice->environmental_fee > 0)
                    <div>{{ __('Environmental Fee:') }} {{ number_format($supplierInvoice->environmental_fee, 2) }} {{ Auth::user()->vendor->currency }}</div>
                @endif
                <div>{{ __('Total Invoiced:') }} {{ number_format($supplierInvoice->total_amount, 2) }} {{ Auth::user()->vendor->currency }}</div>
                <div>{{ __('Amount Paid:') }} {{ number_format($supplierInvoice->paid_amount, 2) }} {{ Auth::user()->vendor->currency }}</div>
            </div>
        </div>
    </div>

    @if($supplierInvoice->items->isNotEmpty())
        <!-- Received Items from Tax Invoice -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: var(--shadow-card);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                <div>
                    <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-boxes-stacked" style="color: #6366f1;"></i>
                        <span>{{ __('Received Products & Line Items') }} ({{ $supplierInvoice->items->count() }})</span>
                    </h2>
                    <p style="color: var(--text-muted); font-size: 0.8rem; margin: 0.2rem 0 0;">
                        {{ __('Products received from the tax invoice. You can convert packaged bulk goods into standard kitchen ingredients and units.') }}
                    </p>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color); background: rgba(0,0,0,0.02);">
                            <th style="padding: 0.65rem 0.5rem;">{{ __('Product / Good') }}</th>
                            <th style="padding: 0.65rem 0.5rem; color: var(--text-muted);">{{ __('Classifier (ԱՏԳ ԱԱ)') }}</th>
                            <th style="padding: 0.65rem 0.5rem; text-align: right;">{{ __('Quantity') }}</th>
                            <th style="padding: 0.65rem 0.5rem;">{{ __('Unit') }}</th>
                            <th style="padding: 0.65rem 0.5rem; text-align: right;">{{ __('Unit Price (incl. VAT)') }}</th>
                            <th style="padding: 0.65rem 0.5rem; text-align: right;">{{ __('VAT %') }}</th>
                            <th style="padding: 0.65rem 0.5rem; text-align: right;">{{ __('VAT Amount') }}</th>
                            <th style="padding: 0.65rem 0.5rem; text-align: right; font-weight: 700;">{{ __('Total') }}</th>
                            <th style="padding: 0.65rem 0.5rem; text-align: center; width: 130px;">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($supplierInvoice->items as $item)
                            @php
                                $itemData = [
                                    'id' => $item->id,
                                    'description' => $item->description,
                                    'classifier_code' => $item->classifier_code,
                                    'ingredient_id' => $item->ingredient_id,
                                    'ingredient_name' => $item->ingredient?->name ?? '',
                                    'quantity' => (float) $item->quantity,
                                    'unit_name' => $item->unit_name ?: ($item->unit?->name ?? $item->unit?->code),
                                    'original_quantity' => $item->original_quantity ? (float) $item->original_quantity : (float) $item->quantity,
                                    'original_unit_name' => $item->original_unit_name ?: $item->unit_name,
                                    'conversion_factor' => $item->conversion_factor ? (float) $item->conversion_factor : 1.0,
                                    'unit_price' => (float) $item->unit_price,
                                    'total_price' => (float) $item->total_price,
                                    'is_converted' => (bool) $item->is_converted,
                                    'convert_url' => route('admin.inventory.invoices.convert-item', [$supplierInvoice, $item]),
                                ];
                            @endphp
                            <tr style="border-bottom: 1px solid var(--border-color); background: {{ $item->is_converted ? 'rgba(16, 185, 129, 0.02)' : 'transparent' }};">
                                <td style="padding: 0.75rem 0.5rem;">
                                    <div style="font-weight: 700; color: var(--text-main);">{{ $item->description }}</div>
                                    @if($item->is_converted)
                                        <div style="margin-top: 0.25rem;">
                                            <span style="background: rgba(16, 185, 129, 0.12); color: #10b981; padding: 0.15rem 0.45rem; border-radius: 6px; font-size: 0.72rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                                                <i class="fa-solid fa-arrows-split-up-and-left"></i>
                                                {{ __('Converted: :orig :orig_u -> :qty :u', [
                                                    'orig' => (float) $item->original_quantity,
                                                    'orig_u' => $item->original_unit_name,
                                                    'qty' => (float) $item->quantity,
                                                    'u' => $item->unit_name ?: ($item->unit?->name ?? $item->unit?->code),
                                                ]) }}
                                            </span>
                                        </div>
                                    @endif
                                    @if($item->ingredient)
                                        <div style="font-size: 0.75rem; color: #6366f1; margin-top: 0.25rem; display: flex; align-items: center; gap: 0.3rem;">
                                            <i class="fa-solid fa-link"></i>
                                            <span>{{ __('Kitchen Ingredient:') }} <strong>{{ $item->ingredient->name }}</strong></span>
                                            <span style="color: var(--text-muted); font-size: 0.7rem;">({{ $item->ingredient->sku }})</span>
                                        </div>
                                    @endif
                                </td>
                                <td style="padding: 0.75rem 0.5rem; font-family: monospace; color: var(--text-muted); font-size: 0.82rem;">
                                    {{ $item->classifier_code ?: '—' }}
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: right; font-family: monospace; font-weight: 700;">
                                    {{ (float) $item->quantity }}
                                </td>
                                <td style="padding: 0.75rem 0.5rem; color: var(--text-muted);">
                                    {{ $item->unit_name ?: ($item->unit?->name ?? $item->unit?->code) }}
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: right; font-family: monospace;">
                                    {{ number_format($item->unit_price, 2) }}
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: right; font-family: monospace; color: var(--text-muted);">
                                    {{ (float) $item->vat_rate }}%
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: right; font-family: monospace; color: var(--text-muted);">
                                    {{ number_format($item->vat_amount, 2) }}
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: right; font-family: monospace; font-weight: 800; color: #10b981;">
                                    {{ number_format($item->total_price, 2) }} {{ Auth::user()->vendor->currency }}
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: center;">
                                    <button type="button"
                                            @click="openConvertModalById({{ $item->id }})"
                                            class="btn btn-secondary btn-sm"
                                            style="border-radius: 8px; font-weight: 600; font-size: 0.76rem; display: inline-flex; align-items: center; gap: 0.35rem; color: #6366f1; border-color: rgba(99, 102, 241, 0.3); background: rgba(99, 102, 241, 0.05);"
                                            title="{{ __('Convert / Map this item') }}">
                                        <i class="fa-solid fa-arrows-rotate"></i>
                                        <span>{{ $item->is_converted ? __('Edit') : __('Convert') }}</span>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Payments History on this Invoice -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: var(--shadow-card);">
        <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-receipt" style="color: #10b981;"></i>
            <span>{{ __('Payment Disbursements on this Invoice') }}</span>
        </h2>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); background: rgba(0,0,0,0.02);">
                        <th style="padding: 0.6rem;">{{ __('Payment #') }}</th>
                        <th style="padding: 0.6rem;">{{ __('Date') }}</th>
                        <th style="padding: 0.6rem;">{{ __('Method') }}</th>
                        <th style="padding: 0.6rem;">{{ __('Reference #') }}</th>
                        <th style="padding: 0.6rem; text-align: right;">{{ __('Amount Paid') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($supplierInvoice->payments as $pay)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.6rem; font-family: monospace; font-weight: 700;">{{ $pay->payment_number }}</td>
                            <td style="padding: 0.6rem; color: var(--text-muted);">{{ $pay->payment_date->toVendorTimezone()->format('Y-m-d') }}</td>
                            <td style="padding: 0.6rem; text-transform: capitalize;">{{ str_replace('_', ' ', $pay->payment_method) }}</td>
                            <td style="padding: 0.6rem; color: var(--text-muted); font-family: monospace;">{{ $pay->reference_number ?: '—' }}</td>
                            <td style="padding: 0.6rem; text-align: right; font-family: monospace; font-weight: 800; color: #10b981;">
                                {{ number_format($pay->amount, 2) }} {{ Auth::user()->vendor->currency }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                                {{ __('No payments posted against this invoice yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal: Convert / Map Line Item -->
    <dialog id="convertItemModal"
            @click.self="closeConvertModal()"
            @keydown.escape.window="closeConvertModal()"
            style="border: none; border-radius: 20px; padding: 0; background: transparent; max-width: 620px; width: 95%;">
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.75rem; box-shadow: var(--shadow-modal); max-height: 90vh; overflow-y: auto;">
            <!-- Modal Header -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem;">
                <div>
                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-arrows-rotate" style="color: #6366f1;"></i>
                        <span>{{ __('Convert & Unbundle Invoice Product') }}</span>
                    </h3>
                    <p style="color: var(--text-muted); font-size: 0.82rem; margin: 0.25rem 0 0;">
                        {{ __('Convert packaged bulk goods (e.g. 15 sacks of 10kg flour) into standard kitchen ingredients (150 kg).') }}
                    </p>
                </div>
                <button type="button" @click="closeConvertModal()" class="btn btn-secondary btn-sm" style="border-radius: 8px; width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>

            <form method="POST" :action="activeItem ? activeItem.convert_url : ''">
                @csrf

                <!-- 1. Source Item Details Summary -->
                <div style="background: rgba(99, 102, 241, 0.05); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.25rem;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: #6366f1; text-transform: uppercase; margin-bottom: 0.25rem;">
                        {{ __('Invoice Line Item') }}
                    </div>
                    <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-main);" x-text="activeItem?.description"></div>
                    <div style="display: flex; gap: 1.5rem; margin-top: 0.5rem; font-size: 0.82rem; color: var(--text-muted); flex-wrap: wrap;">
                        <div>
                            {{ __('Received Quantity:') }}
                            <strong style="color: var(--text-main);" x-text="(activeItem?.original_quantity || activeItem?.quantity) + ' ' + (activeItem?.original_unit_name || activeItem?.unit_name)"></strong>
                        </div>
                        <div>
                            {{ __('Total Line Cost:') }}
                            <strong style="color: #10b981;" x-text="formatNumber(activeItem?.total_price) + ' {{ Auth::user()->vendor->currency }}'"></strong>
                        </div>
                    </div>
                </div>

                <!-- 2. Target Kitchen Ingredient Selection -->
                <div style="margin-bottom: 1.25rem;">
                    <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                        {{ __('Target Kitchen Ingredient') }} <span style="color: #ef4444;">*</span>
                    </label>

                    <!-- Search filter box -->
                    <div style="margin-bottom: 0.4rem; position: relative;">
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem;"></i>
                        <input type="text"
                               x-model="searchQuery"
                               placeholder="{{ __('Search by name, SKU or category...') }}"
                               class="form-input"
                               style="width: 100%; border-radius: 8px; font-size: 0.85rem; padding-left: 2.2rem;">
                    </div>

                    <!-- Instant Filter Results (Click to select) -->
                    <template x-if="searchQuery.trim() !== ''">
                        <div style="max-height: 160px; overflow-y: auto; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 0.5rem; padding: 0.25rem; box-shadow: var(--shadow-sm);">
                            <template x-for="ing in filteredIngredients" :key="ing.id">
                                <div @click="selectTargetIngredient(ing)"
                                     style="padding: 0.45rem 0.65rem; border-radius: 6px; cursor: pointer; font-size: 0.82rem; display: flex; justify-content: space-between; align-items: center;"
                                     :style="String(form.target_ingredient_id) === String(ing.id) ? 'background: rgba(99, 102, 241, 0.12); font-weight: 700; color: #6366f1;' : ''"
                                     onmouseover="if(this.style.background.indexOf('rgba(99') === -1) this.style.background='var(--bg-hover, rgba(0,0,0,0.04))';"
                                     onmouseout="if(this.style.background.indexOf('rgba(99') === -1) this.style.background='transparent';">
                                    <span>
                                        <span x-text="ing.name"></span>
                                        <span style="color: var(--text-muted); font-size: 0.75rem;">(<span x-text="ing.unit_name"></span>)</span>
                                    </span>
                                    <span x-show="ing.category_name" style="font-size: 0.72rem; color: var(--text-muted); background: rgba(0,0,0,0.05); padding: 0.1rem 0.35rem; border-radius: 4px;" x-text="ing.category_name"></span>
                                </div>
                            </template>
                            <div x-show="filteredIngredients.length === 0" style="padding: 0.5rem; text-align: center; color: var(--text-muted); font-size: 0.8rem;">
                                {{ __('No matching ingredients found') }}
                            </div>
                        </div>
                    </template>

                    <!-- Native Dropdown for reliable HTML form submission -->
                    <select name="target_ingredient_id"
                            x-model="form.target_ingredient_id"
                            @change="onTargetIngredientChanged()"
                            required
                            class="form-select"
                            style="width: 100%; border-radius: 10px; font-size: 0.88rem;">
                        <option value="">{{ __('Select Target Ingredient...') }}</option>
                        @foreach($ingredients as $ing)
                            <option value="{{ $ing->id }}">
                                {{ $ing->name }} ({{ $ing->unit?->name ?: $ing->unit?->code }}){{ $ing->category ? ' • ' . $ing->category->name : '' }}
                            </option>
                        @endforeach
                    </select>

                    <div style="font-size: 0.74rem; color: var(--text-muted); margin-top: 0.25rem;">
                        {{ __('Warehouse stock will be deposited into this ingredient.') }}
                    </div>
                </div>

                <!-- 3. Conversion Inputs (Factor vs Total Quantity) -->
                <div style="background: var(--bg-hover, rgba(0,0,0,0.02)); border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.25rem;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.75rem;">
                        {{ __('Conversion Calculations') }}
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <!-- Package Size / Factor -->
                        <div>
                            <label style="font-size: 0.78rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 0.3rem;">
                                {{ __('Package Size / Factor') }}
                            </label>
                            <div style="display: flex; align-items: center; gap: 0.4rem;">
                                <input type="number"
                                       step="0.0001"
                                       min="0.0001"
                                       name="conversion_factor"
                                       x-model.number="form.package_size"
                                       @input="onPackageSizeChanged()"
                                       required
                                       class="form-input"
                                       style="width: 100%; border-radius: 8px; font-size: 0.88rem; font-family: monospace;">
                            </div>
                            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.25rem;">
                                {{ __('1 package = X :unit', ['unit' => '']) }}<span x-text="selectedIngredientUnit"></span>
                            </div>
                        </div>

                        <!-- Target Quantity in Kitchen Units -->
                        <div>
                            <label style="font-size: 0.78rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 0.3rem;">
                                {{ __('Total Converted Quantity') }} <span style="color: #ef4444;">*</span>
                            </label>
                            <div style="display: flex; align-items: center; gap: 0.4rem;">
                                <input type="number"
                                       step="0.0001"
                                       min="0.0001"
                                       name="new_quantity"
                                       x-model.number="form.new_quantity"
                                       @input="onNewQuantityChanged()"
                                       required
                                       class="form-input"
                                       style="width: 100%; border-radius: 8px; font-size: 0.88rem; font-family: monospace; font-weight: 700;">
                                <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted);" x-text="selectedIngredientUnit"></span>
                            </div>
                            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.25rem;">
                                {{ __('Total stock to receive') }}
                            </div>
                        </div>
                    </div>

                    <!-- Live Unit Cost Result -->
                    <div style="border-top: 1px dashed var(--border-color); margin-top: 0.75rem; padding-top: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                        <div style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted);">
                            {{ __('New Calculated Unit Price (incl. VAT):') }}
                        </div>
                        <div style="font-family: monospace; font-size: 1.1rem; font-weight: 800; color: #10b981;">
                            <span x-text="calculateNewUnitPrice()"></span> {{ Auth::user()->vendor->currency }} / <span x-text="selectedIngredientUnit"></span>
                        </div>
                    </div>
                </div>

                <!-- 4. Remember Mapping Checkbox -->
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; font-size: 0.85rem; color: var(--text-main);">
                        <input type="checkbox" name="remember_mapping" value="1" x-model="form.remember_mapping" style="width: 17px; height: 17px; border-radius: 4px; accent-color: #6366f1;">
                        <span style="font-weight: 600;">{{ __('Remember this conversion for future invoices from this supplier') }}</span>
                    </label>
                    <div style="font-size: 0.74rem; color: var(--text-muted); margin-left: 1.7rem; margin-top: 0.2rem;">
                        {{ __('Automatically links and converts this supplier item during future XML/Excel tax invoice imports.') }}
                    </div>
                </div>

                <!-- Submit & Cancel Buttons -->
                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; align-items: center;">
                    <button type="button" @click="closeConvertModal()" class="btn btn-secondary" style="border-radius: 10px; font-weight: 600;">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; padding: 0.65rem 1.5rem; background: #6366f1; border-color: #6366f1;">
                        <i class="fa-solid fa-check"></i> {{ __('Convert & Recalculate Stock') }}
                    </button>
                </div>
            </form>
        </div>
    </dialog>

    <!-- Modal: Pay Invoice -->
    <dialog id="invoicePayModal"
            @click.self="document.getElementById('invoicePayModal').close();"
            @keydown.escape.window="document.getElementById('invoicePayModal').close();"
            style="border: none; border-radius: 16px; padding: 0; background: transparent; max-width: 480px; width: 90%;">
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.75rem; box-shadow: var(--shadow-modal);">
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin: 0 0 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-money-bill-wave" style="color: #10b981;"></i>
                <span>{{ __('Pay Invoice #') }}{{ $supplierInvoice->invoice_number }}</span>
            </h3>
            <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0 0 1.25rem;">
                {{ __('Remaining unpaid balance:') }} <strong>{{ number_format($supplierInvoice->remaining_balance, 2) }} {{ Auth::user()->vendor->currency }}</strong>
            </p>

            <form method="POST" action="{{ route('admin.inventory.payments.store') }}">
                @csrf
                <input type="hidden" name="supplier_id" value="{{ $supplierInvoice->supplier_id }}">
                <input type="hidden" name="supplier_invoice_id" value="{{ $supplierInvoice->id }}">

                <div style="margin-bottom: 1rem;">
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                        {{ __('Disbursement Amount') }} ({{ Auth::user()->vendor->currency }}) <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="number" step="0.01" name="amount" value="{{ $supplierInvoice->remaining_balance }}" required class="form-input" style="width: 100%; border-radius: 8px;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                            {{ __('Payment Method') }}
                        </label>
                        <select name="payment_method" class="form-select" style="width: 100%; border-radius: 8px;">
                            <option value="bank_transfer">{{ __('Bank Transfer') }}</option>
                            <option value="cash">{{ __('Cash') }}</option>
                            <option value="card">{{ __('Card') }}</option>
                            <option value="check">{{ __('Check') }}</option>
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                            {{ __('Payment Date') }}
                        </label>
                        <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" class="form-input" style="width: 100%; border-radius: 8px;">
                    </div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                        {{ __('Reference / Check #') }}
                    </label>
                    <input type="text" name="reference_number" class="form-input" style="width: 100%; border-radius: 8px;">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" onclick="document.getElementById('invoicePayModal').close();" class="btn btn-secondary" style="border-radius: 10px;">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700;">
                        <i class="fa-solid fa-check"></i> {{ __('Post Payment') }}
                    </button>
                </div>
            </form>
        </div>
    </dialog>
</div>

<style>
dialog#convertItemModal {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    margin: 0;
    max-width: 620px;
    width: 95%;
    border: none;
    border-radius: 20px;
    padding: 0;
    background: transparent;
    z-index: 1050;
}
dialog#convertItemModal::backdrop {
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(4px);
}
dialog#invoicePayModal {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    margin: 0;
    max-width: 480px;
    width: 90%;
    border: none;
    border-radius: 16px;
    padding: 0;
    background: transparent;
    z-index: 1050;
}
dialog#invoicePayModal::backdrop {
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(4px);
}
</style>

<script>
function invoiceShowPage() {
    const rawIngredients = @json($ingredientsData ?? []);
    const rawItems = @json($invoiceItemsData ?? []);

    return {
        ingredients: rawIngredients,
        items: rawItems,
        activeItem: null,
        searchQuery: '',
        form: {
            item_id: null,
            target_ingredient_id: '',
            package_size: 1.0,
            new_quantity: 1.0,
            remember_mapping: true,
        },

        openConvertModalById(itemId) {
            const item = this.items.find(i => String(i.id) === String(itemId));
            if (item) {
                this.openConvertModal(item);
            }
        },

        openConvertModal(item) {
            this.activeItem = item;
            const origQty = parseFloat(item.original_quantity || item.quantity) || 1.0;
            const factor = parseFloat(item.conversion_factor) || (item.quantity && origQty ? (parseFloat(item.quantity) / origQty) : 1.0);
            const currentQty = parseFloat(item.quantity) || origQty;

            this.form.item_id = item.id;
            this.form.target_ingredient_id = item.ingredient_id ? String(item.ingredient_id) : '';
            this.form.package_size = factor > 0 ? factor : 1.0;
            this.form.new_quantity = currentQty;
            this.form.remember_mapping = true;
            this.searchQuery = '';

            const modal = document.getElementById('convertItemModal');
            if (modal) modal.showModal();
        },

        closeConvertModal() {
            const modal = document.getElementById('convertItemModal');
            if (modal) modal.close();
            this.activeItem = null;
        },

        selectTargetIngredient(ing) {
            this.form.target_ingredient_id = String(ing.id);
            this.searchQuery = '';
            this.onTargetIngredientChanged();
        },

        onTargetIngredientChanged() {},

        onPackageSizeChanged() {
            const origQty = parseFloat(this.activeItem?.original_quantity || this.activeItem?.quantity) || 1.0;
            const factor = parseFloat(this.form.package_size) || 0;
            this.form.new_quantity = parseFloat((origQty * factor).toFixed(4));
        },

        onNewQuantityChanged() {
            const origQty = parseFloat(this.activeItem?.original_quantity || this.activeItem?.quantity) || 1.0;
            const newQty = parseFloat(this.form.new_quantity) || 0;
            if (origQty > 0 && newQty > 0) {
                this.form.package_size = parseFloat((newQty / origQty).toFixed(4));
            }
        },

        get selectedIngredient() {
            if (!this.form.target_ingredient_id) return null;
            return this.ingredients.find(i => String(i.id) === String(this.form.target_ingredient_id));
        },

        get selectedIngredientUnit() {
            return this.selectedIngredient ? this.selectedIngredient.unit_name : '{{ __("unit") }}';
        },

        calculateNewUnitPrice() {
            const total = parseFloat(this.activeItem?.total_price) || 0;
            const newQty = parseFloat(this.form.new_quantity) || 0;
            if (newQty <= 0) return '0.00';
            return (total / newQty).toFixed(2);
        },

        formatNumber(val) {
            return Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        get filteredIngredients() {
            if (!this.searchQuery || !this.searchQuery.trim()) {
                return this.ingredients;
            }
            const q = this.searchQuery.toLowerCase().trim();
            return this.ingredients.filter(i =>
                (i.name && i.name.toLowerCase().includes(q)) ||
                (i.sku && i.sku.toLowerCase().includes(q)) ||
                (i.category_name && i.category_name.toLowerCase().includes(q))
            );
        }
    };
}
</script>
@endsection

