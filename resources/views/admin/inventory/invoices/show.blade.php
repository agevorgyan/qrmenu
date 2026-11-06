@extends('layouts.app')

@section('title', __('Supplier Invoice #') . $supplierInvoice->invoice_number . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="max-width: 1050px; margin: 0 auto;">
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
            <button onclick="window.print();" class="btn btn-secondary" style="border-radius: 10px; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-print"></i> {{ __('Print Invoice') }}
            </button>

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
            <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-boxes-stacked" style="color: #6366f1;"></i>
                <span>{{ __('Received Products & Line Items') }} ({{ $supplierInvoice->items->count() }})</span>
            </h2>

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
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($supplierInvoice->items as $item)
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.65rem 0.5rem;">
                                    <strong style="color: var(--text-main);">{{ $item->description }}</strong>
                                    @if($item->ingredient)
                                        <div style="font-size: 0.75rem; color: #10b981;">
                                            <i class="fa-solid fa-link"></i> {{ __('Linked to:') }} {{ $item->ingredient->name }} (SKU: {{ $item->ingredient->sku }})
                                        </div>
                                    @endif
                                </td>
                                <td style="padding: 0.65rem 0.5rem; font-family: monospace; color: var(--text-muted);">
                                    {{ $item->classifier_code ?: '—' }}
                                </td>
                                <td style="padding: 0.65rem 0.5rem; text-align: right; font-family: monospace; font-weight: 700;">
                                    {{ (float) $item->quantity }}
                                </td>
                                <td style="padding: 0.65rem 0.5rem; color: var(--text-muted);">
                                    {{ $item->unit_name ?: $item->unit?->code }}
                                </td>
                                <td style="padding: 0.65rem 0.5rem; text-align: right; font-family: monospace;">
                                    {{ number_format($item->unit_price, 2) }}
                                </td>
                                <td style="padding: 0.65rem 0.5rem; text-align: right; font-family: monospace; color: var(--text-muted);">
                                    {{ (float) $item->vat_rate }}%
                                </td>
                                <td style="padding: 0.65rem 0.5rem; text-align: right; font-family: monospace; color: var(--text-muted);">
                                    {{ number_format($item->vat_amount, 2) }}
                                </td>
                                <td style="padding: 0.65rem 0.5rem; text-align: right; font-family: monospace; font-weight: 800; color: #10b981;">
                                    {{ number_format($item->total_price, 2) }} {{ Auth::user()->vendor->currency }}
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
</div>

<!-- Modal: Pay Invoice -->
<dialog id="invoicePayModal" style="border: none; border-radius: 16px; padding: 0; background: transparent; max-width: 480px; width: 90%;">
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
@endsection

