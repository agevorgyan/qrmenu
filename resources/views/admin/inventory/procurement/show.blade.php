@extends('layouts.app')

@section('title', __('Purchase Order #') . $purchaseOrder->po_number . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="max-width: 1150px; margin: 0 auto;">
    <!-- Top Action Navigation -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="{{ route('admin.inventory.procurement.index') }}" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-decoration: none; margin-bottom: 0.5rem;">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Purchase Orders') }}
            </a>
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ $purchaseOrder->po_number }}
                </h1>
                @php
                    $poStatusBadges = [
                        'draft' => ['bg' => 'rgba(107, 114, 128, 0.12)', 'color' => '#6b7280', 'label' => __('Draft')],
                        'approved' => ['bg' => 'rgba(59, 130, 246, 0.12)', 'color' => '#3b82f6', 'label' => __('Approved')],
                        'sent' => ['bg' => 'rgba(245, 158, 11, 0.12)', 'color' => '#f59e0b', 'label' => __('Sent')],
                        'partially_received' => ['bg' => 'rgba(168, 85, 247, 0.12)', 'color' => '#a855f7', 'label' => __('Partially Received')],
                        'received' => ['bg' => 'rgba(16, 185, 129, 0.12)', 'color' => '#10b981', 'label' => __('Received / Closed')],
                        'cancelled' => ['bg' => 'rgba(239, 68, 68, 0.12)', 'color' => '#ef4444', 'label' => __('Cancelled')],
                    ];
                    $sb = $poStatusBadges[$purchaseOrder->status] ?? ['bg' => 'rgba(0,0,0,0.05)', 'color' => 'inherit', 'label' => $purchaseOrder->status];
                @endphp
                <span style="display: inline-block; padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; background: {{ $sb['bg'] }}; color: {{ $sb['color'] }};">
                    {{ $sb['label'] }}
                </span>
            </div>
        </div>

        <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
            <button onclick="window.print();" class="btn btn-secondary" style="border-radius: 10px; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-print"></i> {{ __('Print PO') }}
            </button>

            @can('procurement.approve')
                @if($purchaseOrder->status === \App\Models\PurchaseOrder::STATUS_DRAFT)
                    <form method="POST" action="{{ route('admin.inventory.procurement.approve', $purchaseOrder) }}" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-check"></i> {{ __('Approve PO') }}
                        </button>
                    </form>
                @elseif($purchaseOrder->status === \App\Models\PurchaseOrder::STATUS_APPROVED)
                    <form method="POST" action="{{ route('admin.inventory.procurement.send', $purchaseOrder) }}" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-paper-plane"></i> {{ __('Mark as Sent to Supplier') }}
                        </button>
                    </form>
                @endif
            @endcan

            @can('procurement.receive')
                @if(in_array($purchaseOrder->status, [\App\Models\PurchaseOrder::STATUS_SENT, \App\Models\PurchaseOrder::STATUS_APPROVED, \App\Models\PurchaseOrder::STATUS_PARTIALLY_RECEIVED]))
                    <button type="button" onclick="document.getElementById('receiveModal').showModal();" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; background: #10b981; border-color: #10b981;">
                        <i class="fa-solid fa-boxes-packing"></i> {{ __('Receive Goods into Stock') }}
                    </button>
                @endif
            @endcan

            @can('procurement.approve')
                @if(!in_array($purchaseOrder->status, [\App\Models\PurchaseOrder::STATUS_RECEIVED, \App\Models\PurchaseOrder::STATUS_CANCELLED]))
                    <form method="POST" action="{{ route('admin.inventory.procurement.cancel', $purchaseOrder) }}" onsubmit="return confirm('{{ __('Are you sure you want to cancel this purchase order?') }}');" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn btn-secondary" style="border-radius: 10px; font-weight: 600; color: #ef4444;">
                            <i class="fa-solid fa-ban"></i> {{ __('Cancel') }}
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    <!-- PO Details Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; box-shadow: var(--shadow-card);">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">{{ __('Supplier Information') }}</div>
            <div style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.35rem;">
                <a href="{{ route('admin.inventory.suppliers.show', $purchaseOrder->supplier) }}" style="color: var(--text-main); text-decoration: none;">
                    {{ $purchaseOrder->supplier->name }}
                </a>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">
                @if($purchaseOrder->supplier->contact_person)
                    <div><i class="fa-solid fa-user"></i> {{ $purchaseOrder->supplier->contact_person }}</div>
                @endif
                @if($purchaseOrder->supplier->phone)
                    <div><i class="fa-solid fa-phone"></i> {{ $purchaseOrder->supplier->phone }}</div>
                @endif
                <div><i class="fa-solid fa-credit-card"></i> {{ ucfirst(str_replace('_', ' ', $purchaseOrder->supplier->payment_terms)) }}</div>
            </div>
        </div>

        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; box-shadow: var(--shadow-card);">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">{{ __('Warehouse & Delivery') }}</div>
            <div style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.35rem;">
                <i class="fa-solid fa-warehouse" style="color: #3b82f6;"></i> {{ $purchaseOrder->warehouse->name }}
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">
                <div><strong>{{ __('Order Date:') }}</strong> {{ $purchaseOrder->order_date->toVendorTimezone()->format('M d, Y') }}</div>
                <div><strong>{{ __('Expected Delivery:') }}</strong> {{ $purchaseOrder->expected_delivery_date ? $purchaseOrder->expected_delivery_date->toVendorTimezone()->format('M d, Y') : __('Not specified') }}</div>
                @if($purchaseOrder->approver)
                    <div><strong>{{ __('Approved by:') }}</strong> {{ $purchaseOrder->approver->name }}</div>
                @endif
            </div>
        </div>

        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; box-shadow: var(--shadow-card);">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">{{ __('Order Valuation') }}</div>
            <div style="font-size: 1.6rem; font-weight: 800; font-family: monospace; color: var(--text-main); margin-bottom: 0.35rem;">
                {{ number_format($purchaseOrder->total_amount, 2) }} {{ Auth::user()->vendor->currency }}
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.4;">
                <div>{{ __('Subtotal:') }} {{ number_format($purchaseOrder->subtotal, 2) }} {{ Auth::user()->vendor->currency }}</div>
                <div>{{ __('Tax / VAT:') }} {{ number_format($purchaseOrder->tax_amount, 2) }} {{ Auth::user()->vendor->currency }}</div>
            </div>
        </div>
    </div>

    <!-- Line Items Table -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
        <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-boxes-stacked" style="color: #10b981;"></i>
            <span>{{ __('Ordered Items & Receiving Progress') }}</span>
        </h2>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); background: rgba(0,0,0,0.02);">
                        <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-main);">{{ __('Ingredient') }}</th>
                        <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 80px;">{{ __('Unit') }}</th>
                        <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); text-align: right; width: 110px;">{{ __('Ordered') }}</th>
                        <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); text-align: right; width: 110px;">{{ __('Received') }}</th>
                        <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 120px; text-align: center;">{{ __('Progress') }}</th>
                        <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); text-align: right; width: 120px;">{{ __('Unit Price') }}</th>
                        <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-main); text-align: right; width: 120px;">{{ __('Line Total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchaseOrder->items as $item)
                        @php
                            $ord = (float) $item->quantity_ordered;
                            $rec = (float) $item->quantity_received;
                            $pct = $ord > 0 ? min(100, round(($rec / $ord) * 100)) : 0;
                        @endphp
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-main);">
                                {{ $item->ingredient?->name }}
                            </td>
                            <td style="padding: 0.65rem 0.5rem; color: var(--text-muted);">
                                {{ $item->unit?->code }}
                            </td>
                            <td style="padding: 0.65rem 0.5rem; text-align: right; font-family: monospace; font-weight: 600;">
                                {{ $ord }}
                            </td>
                            <td style="padding: 0.65rem 0.5rem; text-align: right; font-family: monospace; font-weight: 800; color: {{ $rec >= $ord ? '#10b981' : ($rec > 0 ? '#f59e0b' : 'var(--text-muted)') }};">
                                {{ $rec }}
                            </td>
                            <td style="padding: 0.65rem 0.5rem; text-align: center;">
                                <div style="background: rgba(0,0,0,0.06); height: 8px; border-radius: 4px; overflow: hidden; width: 80px; margin: 0 auto;">
                                    <div style="background: {{ $pct >= 100 ? '#10b981' : '#f59e0b' }}; width: {{ $pct }}%; height: 100%;"></div>
                                </div>
                                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.15rem;">{{ $pct }}%</div>
                            </td>
                            <td style="padding: 0.65rem 0.5rem; text-align: right; font-family: monospace;">
                                {{ number_format($item->unit_price, 2) }} {{ Auth::user()->vendor->currency }}
                            </td>
                            <td style="padding: 0.65rem 0.5rem; text-align: right; font-family: monospace; font-weight: 800; color: var(--text-main);">
                                {{ number_format($item->total_amount, 2) }} {{ Auth::user()->vendor->currency }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Linked Accounts Payable Invoices -->
    @if($purchaseOrder->invoices->isNotEmpty())
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 3rem; box-shadow: var(--shadow-card);">
            <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-file-invoice-dollar" style="color: #6366f1;"></i>
                <span>{{ __('Generated Stock Intake Invoices') }}</span>
            </h2>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.02);">
                            <th style="padding: 0.6rem;">{{ __('Invoice #') }}</th>
                            <th style="padding: 0.6rem;">{{ __('Date') }}</th>
                            <th style="padding: 0.6rem; text-align: right;">{{ __('Amount') }}</th>
                            <th style="padding: 0.6rem; text-align: right;">{{ __('Paid') }}</th>
                            <th style="padding: 0.6rem; text-align: right;">{{ __('Remaining Debt') }}</th>
                            <th style="padding: 0.6rem; text-align: center;">{{ __('Payment Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchaseOrder->invoices as $inv)
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.6rem; font-family: monospace; font-weight: 700;">
                                    <a href="{{ route('admin.inventory.invoices.show', $inv) }}" style="color: var(--text-main); text-decoration: none;">
                                        {{ $inv->invoice_number }}
                                    </a>
                                </td>
                                <td style="padding: 0.6rem; color: var(--text-muted);">{{ $inv->invoice_date->toVendorTimezone()->format('Y-m-d') }}</td>
                                <td style="padding: 0.6rem; text-align: right; font-family: monospace;">{{ number_format($inv->total_amount, 2) }} {{ Auth::user()->vendor->currency }}</td>
                                <td style="padding: 0.6rem; text-align: right; font-family: monospace; color: #10b981;">{{ number_format($inv->paid_amount, 2) }} {{ Auth::user()->vendor->currency }}</td>
                                <td style="padding: 0.6rem; text-align: right; font-family: monospace; font-weight: 800; color: {{ (float) $inv->remaining_balance > 0 ? '#ef4444' : '#10b981' }};">
                                    {{ number_format($inv->remaining_balance, 2) }} {{ Auth::user()->vendor->currency }}
                                </td>
                                <td style="padding: 0.6rem; text-align: center;">
                                    <span style="font-size: 0.75rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 6px; background: rgba(0,0,0,0.05);">
                                        {{ ucfirst(str_replace('_', ' ', $inv->payment_status)) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<!-- Modal: Receive Goods into Warehouse -->
<dialog id="receiveModal" style="border: none; border-radius: 16px; padding: 0; background: transparent; max-width: 600px; width: 90%;">
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.75rem; box-shadow: var(--shadow-modal);">
        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin: 0 0 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-boxes-packing" style="color: #10b981;"></i>
            <span>{{ __('Physical Goods Receipt & Stock Intake') }}</span>
        </h3>
        <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0 0 1.25rem;">
            {{ __('Confirm received items and quantities delivered into') }} <strong>{{ $purchaseOrder->warehouse->name }}</strong>.
        </p>

        <form method="POST" action="{{ route('admin.inventory.procurement.receive', $purchaseOrder) }}">
            @csrf

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                        {{ __('Supplier Invoice / Slip #') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="invoice_number" required placeholder="{{ __('e.g., INV-2026-9812') }}" class="form-input" style="width: 100%; border-radius: 8px;">
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                        {{ __('Invoice Due Date') }}
                    </label>
                    <input type="date" name="due_date" value="{{ date('Y-m-d', strtotime('+30 days')) }}" class="form-input" style="width: 100%; border-radius: 8px;">
                </div>
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.5rem;">
                    {{ __('Items to Intake') }}
                </label>
                <div style="max-height: 250px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 10px; padding: 0.5rem;">
                    @foreach($purchaseOrder->items as $idx => $item)
                        @php
                            $remainingToReceive = max(0.0, (float) $item->quantity_ordered - (float) $item->quantity_received);
                        @endphp
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.5rem; border-bottom: 1px solid var(--border-color);">
                            <input type="hidden" name="items[{{ $idx }}][item_id]" value="{{ $item->id }}">
                            <div style="min-width: 0; flex: 1;">
                                <div style="font-weight: 700; font-size: 0.88rem; color: var(--text-main);">
                                    {{ $item->ingredient?->name }}
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    {{ __('Ordered:') }} {{ (float) $item->quantity_ordered }} {{ $item->unit?->code }} • {{ __('Already Received:') }} {{ (float) $item->quantity_received }}
                                </div>
                            </div>
                            <div style="width: 120px;">
                                <label style="font-size: 0.72rem; color: var(--text-muted); display: block;">{{ __('Intake Qty') }}</label>
                                <input type="number" step="0.0001" name="items[{{ $idx }}][quantity_received]" value="{{ $remainingToReceive }}" class="form-input" style="width: 100%; border-radius: 6px; font-size: 0.85rem; padding: 0.35rem;">
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                    {{ __('Intake Notes (HACCP Temp Check, Quality notes)') }}
                </label>
                <input type="text" name="notes" placeholder="{{ __('e.g., Temp +3.2°C, packaging intact, quality verified') }}" class="form-input" style="width: 100%; border-radius: 8px;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('receiveModal').close();" class="btn btn-secondary" style="border-radius: 10px;">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; background: #10b981; border-color: #10b981;">
                    <i class="fa-solid fa-check"></i> {{ __('Confirm Stock Intake') }}
                </button>
            </div>
        </form>
    </div>
</dialog>
@endsection

