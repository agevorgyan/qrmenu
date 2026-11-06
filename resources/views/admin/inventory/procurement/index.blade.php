@extends('layouts.app')

@section('title', __('Purchase Orders & Procurement') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 0; flex: 1;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(99, 102, 241, 0.15); color: #6366f1; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fa-solid fa-cart-shopping"></i>
            </span>
            <span>{{ __('Purchase Orders & Procurement') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
            {{ __('Manage purchase orders, supplier shipments, receiving into warehouse stock, and automated reorders.') }}
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        @can('procurement.create')
            <button type="button" onclick="document.getElementById('importTaxInvoiceModal').showModal();" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 700; color: #6366f1; border-color: rgba(99, 102, 241, 0.3); background: rgba(99, 102, 241, 0.08);">
                <i class="fa-solid fa-file-import"></i> {{ __('Import Armenian Tax Invoice (e-Invoicing)') }}
            </button>
            <a href="{{ route('admin.inventory.procurement.create') }}" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 700;">
                <i class="fa-solid fa-plus"></i> {{ __('Create Purchase Order') }}
            </a>
        @endcan
    </div>
</div>

<!-- KPI Stats Summary -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(59, 130, 246, 0.12); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-file-lines"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Total POs') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: var(--text-main);">{{ $totalCount }}</div>
        </div>
    </div>

    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(107, 114, 128, 0.12); color: #6b7280; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-pen-ruler"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Drafts') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: var(--text-main);">{{ $draftCount }}</div>
        </div>
    </div>

    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-truck-fast"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Pending Delivery') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #f59e0b;">{{ $pendingDeliveryCount }}</div>
        </div>
    </div>

    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-check-double"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Approved POs') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: var(--text-main);">{{ $approvedCount }}</div>
        </div>
    </div>
</div>

<!-- Search & Filters -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
    <form method="GET" action="{{ route('admin.inventory.procurement.index') }}" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 2; min-width: 220px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Search') }}</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search PO # or supplier...') }}" class="form-input" style="width: 100%; border-radius: 10px;">
        </div>

        <div style="flex: 1; min-width: 160px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Status') }}</label>
            <select name="status" class="form-select" style="width: 100%; border-radius: 10px;">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>{{ __('Draft') }}</option>
                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>{{ __('Approved') }}</option>
                <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>{{ __('Sent to Supplier') }}</option>
                <option value="partially_received" {{ request('status') === 'partially_received' ? 'selected' : '' }}>{{ __('Partially Received') }}</option>
                <option value="received" {{ request('status') === 'received' ? 'selected' : '' }}>{{ __('Received / Closed') }}</option>
                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ __('Cancelled') }}</option>
            </select>
        </div>

        <div style="flex: 1; min-width: 160px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Supplier') }}</label>
            <select name="supplier_id" class="form-select" style="width: 100%; border-radius: 10px;">
                <option value="">{{ __('All Suppliers') }}</option>
                @foreach($suppliers as $sup)
                    <option value="{{ $sup->id }}" {{ (string) request('supplier_id') === (string) $sup->id ? 'selected' : '' }}>
                        {{ $sup->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 600; padding: 0.6rem 1.25rem;">
                <i class="fa-solid fa-filter"></i> {{ __('Filter') }}
            </button>
            @if(request()->hasAny(['search', 'status', 'supplier_id', 'warehouse_id']))
                <a href="{{ route('admin.inventory.procurement.index') }}" class="btn btn-secondary" style="border-radius: 10px;">
                    {{ __('Reset') }}
                </a>
            @endif
        </div>
    </form>
</div>

<!-- Purchase Orders Table -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);">
    @if($purchaseOrders->isEmpty())
        <div style="padding: 4rem 1.5rem; text-align: center;">
            <div style="width: 72px; height: 72px; border-radius: 20px; background: rgba(99, 102, 241, 0.1); color: #6366f1; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 1rem;">
                <i class="fa-solid fa-cart-shopping"></i>
            </div>
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin: 0 0 0.5rem;">
                {{ __('No purchase orders found') }}
            </h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; max-width: 450px; margin: 0 auto 1.5rem;">
                {{ __('Create purchase orders to order ingredients from suppliers and restock your warehouses.') }}
            </p>
            @can('procurement.create')
                <a href="{{ route('admin.inventory.procurement.create') }}" class="btn btn-primary" style="border-radius: 12px; font-weight: 700;">
                    <i class="fa-solid fa-plus"></i> {{ __('Create First Purchase Order') }}
                </a>
            @endcan
        </div>
    @else
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.02);">
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('PO Number / Date') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('Supplier') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('Warehouse') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('Items') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('Total Amount') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('Status') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchaseOrders as $po)
                        <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.15s ease;">
                            <td style="padding: 1rem 1.25rem;">
                                <a href="{{ route('admin.inventory.procurement.show', $po) }}" style="font-weight: 700; font-family: monospace; color: var(--text-main); text-decoration: none; font-size: 0.95rem;">
                                    {{ $po->po_number }}
                                </a>
                                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
                                    {{ $po->order_date->toVendorTimezone()->format('M d, Y') }}
                                    @if($po->expected_delivery_date)
                                        • <span title="{{ __('Expected Delivery') }}"><i class="fa-solid fa-calendar-day"></i> {{ $po->expected_delivery_date->toVendorTimezone()->format('M d') }}</span>
                                    @endif
                                </div>
                            </td>

                            <td style="padding: 1rem 1.25rem;">
                                <a href="{{ route('admin.inventory.suppliers.show', $po->supplier) }}" style="font-weight: 600; color: var(--text-main); text-decoration: none;">
                                    {{ $po->supplier->name }}
                                </a>
                            </td>

                            <td style="padding: 1rem 1.25rem; color: var(--text-muted); font-size: 0.88rem;">
                                <i class="fa-solid fa-warehouse" style="color: #3b82f6;"></i> {{ $po->warehouse->name }}
                            </td>

                            <td style="padding: 1rem 1.25rem; font-weight: 600;">
                                {{ $po->items->count() }} {{ __('items') }}
                            </td>

                            <td style="padding: 1rem 1.25rem; font-family: monospace; font-weight: 800; font-size: 0.95rem; color: var(--text-main);">
                                {{ number_format($po->total_amount, 2) }} {{ Auth::user()->vendor->currency }}
                            </td>

                            <td style="padding: 1rem 1.25rem;">
                                @php
                                    $poStatusBadges = [
                                        'draft' => ['bg' => 'rgba(107, 114, 128, 0.12)', 'color' => '#6b7280', 'label' => __('Draft')],
                                        'approved' => ['bg' => 'rgba(59, 130, 246, 0.12)', 'color' => '#3b82f6', 'label' => __('Approved')],
                                        'sent' => ['bg' => 'rgba(245, 158, 11, 0.12)', 'color' => '#f59e0b', 'label' => __('Sent')],
                                        'partially_received' => ['bg' => 'rgba(168, 85, 247, 0.12)', 'color' => '#a855f7', 'label' => __('Partial')],
                                        'received' => ['bg' => 'rgba(16, 185, 129, 0.12)', 'color' => '#10b981', 'label' => __('Received')],
                                        'cancelled' => ['bg' => 'rgba(239, 68, 68, 0.12)', 'color' => '#ef4444', 'label' => __('Cancelled')],
                                    ];
                                    $sb = $poStatusBadges[$po->status] ?? ['bg' => 'rgba(0,0,0,0.05)', 'color' => 'inherit', 'label' => $po->status];
                                @endphp
                                <span style="display: inline-block; padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; background: {{ $sb['bg'] }}; color: {{ $sb['color'] }};">
                                    {{ $sb['label'] }}
                                </span>
                            </td>

                            <td style="padding: 1rem 1.25rem; text-align: right;">
                                <div style="display: flex; gap: 0.4rem; justify-content: flex-end; align-items: center;">
                                    <a href="{{ route('admin.inventory.procurement.show', $po) }}" class="btn btn-secondary btn-sm" title="{{ __('View Purchase Order') }}" style="border-radius: 8px; padding: 0.4rem 0.65rem;">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>

                                    @if(!in_array($po->status, [\App\Models\PurchaseOrder::STATUS_RECEIVED, \App\Models\PurchaseOrder::STATUS_PARTIALLY_RECEIVED]))
                                        <a href="{{ route('admin.inventory.procurement.edit', $po) }}" class="btn btn-secondary btn-sm" title="{{ __('Edit Purchase Order') }}" style="border-radius: 8px; padding: 0.4rem 0.65rem;">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>

                                        <form method="POST" action="{{ route('admin.inventory.procurement.destroy', $po) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this purchase order? This action cannot be undone.') }}');" style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-secondary btn-sm" title="{{ __('Delete Purchase Order') }}" style="border-radius: 8px; padding: 0.4rem 0.65rem; color: #ef4444;">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($purchaseOrders->hasPages())
            <div style="padding: 1.25rem; border-top: 1px solid var(--border-color);">
                {{ $purchaseOrders->links() }}
            </div>
        @endif
    @endif
</div>

<!-- Modal: Import Armenian Tax Invoice -->
<dialog id="importTaxInvoiceModal" style="border: none; border-radius: 16px; padding: 0; background: transparent; max-width: 520px; width: 92%;">
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.75rem; box-shadow: var(--shadow-modal);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-file-invoice" style="color: #6366f1;"></i>
                <span>{{ __('Import Armenian Tax Invoice (e-Invoicing)') }}</span>
            </h3>
            <button type="button" onclick="document.getElementById('importTaxInvoiceModal').close();" class="btn btn-secondary btn-sm" style="border-radius: 8px; width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>

        <p style="color: var(--text-muted); font-size: 0.88rem; line-height: 1.45; margin: 0 0 1.25rem;">
            {{ __('Upload official RA Tax Service e-Invoicing XML (taxservice.am) or Excel register. The system will automatically create/link the supplier, update ingredient catalog, credit stock into warehouse, and log Accounts Payable debt.') }}
        </p>

        <form method="POST" action="{{ route('admin.inventory.invoices.tax-import') }}" enctype="multipart/form-data">
            @csrf

            <div style="margin-bottom: 1.25rem;">
                <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                    {{ __('Invoice File (.xml, .xlsx, .xls)') }} <span style="color: #ef4444;">*</span>
                </label>
                <input type="file" name="file" accept=".xml,.xlsx,.xls,.csv" required class="form-input" style="width: 100%; border-radius: 8px; padding: 0.5rem;">
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.3rem;">
                    {{ __('Supported: e-Invoicing XML files exported from State Revenue Committee (ՀՀ ՊԵԿ) or Excel invoice exports.') }}
                </div>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                    {{ __('Target Warehouse for Stock Intake') }} <span style="color: #ef4444;">*</span>
                </label>
                <select name="warehouse_id" required class="form-select" style="width: 100%; border-radius: 8px;">
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ $wh->is_default ? 'selected' : '' }}>
                            {{ $wh->name }} {{ $wh->is_default ? '(' . __('Default') . ')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('importTaxInvoiceModal').close();" class="btn btn-secondary" style="border-radius: 10px;">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700;">
                    <i class="fa-solid fa-cloud-arrow-up"></i> {{ __('Import & Credit Stock') }}
                </button>
            </div>
        </form>
    </div>
</dialog>
@endsection

