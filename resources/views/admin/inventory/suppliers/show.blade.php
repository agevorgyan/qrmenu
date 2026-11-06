@extends('layouts.app')

@section('title', $supplier->name . ' - ' . __('Supplier Profile') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="max-width: 1200px; margin: 0 auto;">
    <!-- Top Action Navigation -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="{{ route('admin.inventory.suppliers.index') }}" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-decoration: none; margin-bottom: 0.5rem;">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Suppliers') }}
            </a>
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ $supplier->name }}
                </h1>
                @if($supplier->tax_id)
                    <span style="font-family: monospace; background: rgba(0,0,0,0.06); padding: 0.2rem 0.5rem; border-radius: 6px; font-weight: 700; font-size: 0.85rem;">
                        {{ __('Tax ID:') }} {{ $supplier->tax_id }}
                    </span>
                @endif
                @if($supplier->code)
                    <span style="background: rgba(6, 182, 212, 0.12); color: #06b6d4; padding: 0.2rem 0.6rem; border-radius: 6px; font-weight: 700; font-size: 0.78rem;">
                        #{{ $supplier->code }}
                    </span>
                @endif
            </div>
        </div>

        <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
            @can('procurement.create')
                <a href="{{ route('admin.inventory.procurement.create', ['supplier_id' => $supplier->id]) }}" class="btn btn-secondary" style="border-radius: 10px; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-cart-plus"></i> {{ __('New Purchase Order') }}
                </a>
            @endcan

            @can('procurement.pay')
                <button type="button" onclick="document.getElementById('paymentModal').showModal();" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-money-bill-wave"></i> {{ __('Record Payment') }}
                </button>
            @endcan

            @can('suppliers.update')
                <a href="{{ route('admin.inventory.suppliers.edit', $supplier) }}" class="btn btn-secondary" style="border-radius: 10px; font-weight: 600;">
                    <i class="fa-solid fa-pen-to-square"></i> {{ __('Edit') }}
                </a>
            @endcan
        </div>
    </div>

    <!-- KPI Summary Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.75rem;">
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Outstanding AP Debt') }}</div>
            <div style="font-size: 1.6rem; font-weight: 800; font-family: monospace; margin: 0.25rem 0; color: {{ (float) $supplier->current_balance > 0 ? '#ef4444' : '#10b981' }};">
                ${{ number_format($supplier->current_balance, 2) }}
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted);">
                {{ __('Terms:') }} {{ ucfirst(str_replace('_', ' ', $supplier->payment_terms)) }}
            </div>
        </div>

        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Total Invoiced') }}</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); font-family: monospace; margin: 0.25rem 0;">
                ${{ number_format($supplier->invoices->sum('total_amount'), 2) }}
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted);">
                {{ $supplier->invoices->count() }} {{ __('Total Invoices') }}
            </div>
        </div>

        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Total Paid') }}</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: #10b981; font-family: monospace; margin: 0.25rem 0;">
                ${{ number_format($supplier->payments->sum('amount'), 2) }}
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted);">
                {{ $supplier->payments->count() }} {{ __('Disbursements') }}
            </div>
        </div>

        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-card);">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Contracted Items') }}</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin: 0.25rem 0;">
                {{ $supplier->supplierIngredients->count() }}
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted);">
                {{ __('In price catalog') }}
            </div>
        </div>
    </div>

    <!-- Company Details Card -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
        <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-address-card" style="color: #3b82f6;"></i>
            <span>{{ __('Supplier Profile & Banking') }}</span>
        </h2>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; font-size: 0.88rem;">
            <div>
                <div style="color: var(--text-muted); font-size: 0.78rem; font-weight: 600;">{{ __('Contact Person') }}</div>
                <div style="font-weight: 700; color: var(--text-main); margin-top: 0.2rem;">{{ $supplier->contact_person ?: '—' }}</div>
            </div>

            <div>
                <div style="color: var(--text-muted); font-size: 0.78rem; font-weight: 600;">{{ __('Phone / Mobile') }}</div>
                <div style="font-weight: 600; color: var(--text-main); margin-top: 0.2rem;">{{ $supplier->phone ?: '—' }}</div>
            </div>

            <div>
                <div style="color: var(--text-muted); font-size: 0.78rem; font-weight: 600;">{{ __('Email') }}</div>
                <div style="font-weight: 600; color: var(--text-main); margin-top: 0.2rem;">{{ $supplier->email ?: '—' }}</div>
            </div>

            <div>
                <div style="color: var(--text-muted); font-size: 0.78rem; font-weight: 600;">{{ __('Bank & IBAN') }}</div>
                <div style="font-weight: 600; color: var(--text-main); margin-top: 0.2rem;">
                    {{ $supplier->bank_name ?: '' }} {{ $supplier->bank_account ? '(' . $supplier->bank_account . ')' : '—' }}
                </div>
            </div>

            <div style="grid-column: 1 / -1;">
                <div style="color: var(--text-muted); font-size: 0.78rem; font-weight: 600;">{{ __('Office / Warehouse Address') }}</div>
                <div style="font-weight: 600; color: var(--text-main); margin-top: 0.2rem;">{{ $supplier->address ?: '—' }}</div>
            </div>
        </div>
    </div>

    <!-- Contract Price Catalog Table -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
            <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-tags" style="color: #f59e0b;"></i>
                <span>{{ __('Contract Prices Catalog') }}</span>
            </h2>
            <button type="button" onclick="document.getElementById('addPriceModal').showModal();" class="btn btn-secondary btn-sm" style="border-radius: 8px; font-weight: 700;">
                <i class="fa-solid fa-plus"></i> {{ __('Add Ingredient Price') }}
            </button>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); background: rgba(0,0,0,0.02);">
                        <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-main);">{{ __('Ingredient') }}</th>
                        <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 120px;">{{ __('Supplier SKU') }}</th>
                        <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 100px;">{{ __('Unit') }}</th>
                        <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-main); text-align: right; width: 140px;">{{ __('Contract Price') }}</th>
                        <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); width: 120px; text-align: center;">{{ __('Primary?') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($supplier->supplierIngredients as $si)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-main);">
                                {{ $si->ingredient?->name }}
                            </td>
                            <td style="padding: 0.65rem 0.5rem; font-family: monospace; color: var(--text-muted);">
                                {{ $si->supplier_sku ?: '—' }}
                            </td>
                            <td style="padding: 0.65rem 0.5rem; color: var(--text-muted);">
                                {{ $si->unit?->code }}
                            </td>
                            <td style="padding: 0.65rem 0.5rem; text-align: right; font-family: monospace; font-weight: 800; color: var(--text-main);">
                                ${{ number_format($si->price, 2) }}
                            </td>
                            <td style="padding: 0.65rem 0.5rem; text-align: center;">
                                @if($si->is_primary_supplier)
                                    <span style="background: rgba(16, 185, 129, 0.12); color: #10b981; padding: 0.15rem 0.5rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">
                                        {{ __('Primary') }}
                                    </span>
                                @else
                                    <span style="color: var(--text-muted); font-size: 0.8rem;">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                                {{ __('No contract prices defined for this supplier yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Accounts Payable Invoices Table -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
        <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-file-invoice-dollar" style="color: #6366f1;"></i>
            <span>{{ __('Invoices & Bills Ledger') }}</span>
        </h2>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); background: rgba(0,0,0,0.02);">
                        <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-main);">{{ __('Invoice #') }}</th>
                        <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted);">{{ __('Date / Due') }}</th>
                        <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); text-align: right;">{{ __('Total') }}</th>
                        <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); text-align: right;">{{ __('Paid') }}</th>
                        <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-main); text-align: right;">{{ __('Remaining Balance') }}</th>
                        <th style="padding: 0.65rem 0.5rem; font-weight: 700; color: var(--text-muted); text-align: center;">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($supplier->invoices as $inv)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.65rem 0.5rem; font-family: monospace; font-weight: 700;">
                                <a href="{{ route('admin.inventory.invoices.show', $inv) }}" style="color: var(--text-main); text-decoration: none;">
                                    {{ $inv->invoice_number }}
                                </a>
                            </td>
                            <td style="padding: 0.65rem 0.5rem; color: var(--text-muted); font-size: 0.82rem;">
                                {{ $inv->invoice_date->format('Y-m-d') }} • <span style="color: {{ $inv->due_date->isPast() && $inv->payment_status !== 'paid' ? '#ef4444' : 'inherit' }}">{{ __('Due:') }} {{ $inv->due_date->format('Y-m-d') }}</span>
                            </td>
                            <td style="padding: 0.65rem 0.5rem; text-align: right; font-family: monospace;">${{ number_format($inv->total_amount, 2) }}</td>
                            <td style="padding: 0.65rem 0.5rem; text-align: right; font-family: monospace; color: #10b981;">${{ number_format($inv->paid_amount, 2) }}</td>
                            <td style="padding: 0.65rem 0.5rem; text-align: right; font-family: monospace; font-weight: 800; color: {{ (float) $inv->remaining_balance > 0 ? '#ef4444' : '#10b981' }};">
                                ${{ number_format($inv->remaining_balance, 2) }}
                            </td>
                            <td style="padding: 0.65rem 0.5rem; text-align: center;">
                                @php
                                    $stColors = [
                                        'paid' => ['bg' => 'rgba(16, 185, 129, 0.12)', 'color' => '#10b981', 'label' => __('Paid')],
                                        'partially_paid' => ['bg' => 'rgba(245, 158, 11, 0.12)', 'color' => '#f59e0b', 'label' => __('Partially Paid')],
                                        'unpaid' => ['bg' => 'rgba(239, 68, 68, 0.12)', 'color' => '#ef4444', 'label' => __('Unpaid')],
                                        'overdue' => ['bg' => 'rgba(239, 68, 68, 0.2)', 'color' => '#dc2626', 'label' => __('Overdue')],
                                    ];
                                    $st = $stColors[$inv->payment_status] ?? ['bg' => 'rgba(0,0,0,0.05)', 'color' => 'inherit', 'label' => $inv->payment_status];
                                @endphp
                                <span style="background: {{ $st['bg'] }}; color: {{ $st['color'] }}; padding: 0.2rem 0.55rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">
                                    {{ $st['label'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                                {{ __('No invoices recorded from this supplier yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Record Supplier Payment -->
<dialog id="paymentModal" style="border: none; border-radius: 16px; padding: 0; background: transparent; max-width: 480px; width: 90%;">
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.75rem; box-shadow: var(--shadow-modal);">
        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin: 0 0 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-money-bill-wave" style="color: #10b981;"></i>
            <span>{{ __('Record Supplier Payment') }}</span>
        </h3>
        <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0 0 1.25rem;">
            {{ __('Record a payment disbursement to') }} <strong>{{ $supplier->name }}</strong>. {{ __('Current Debt:') }} ${{ number_format($supplier->current_balance, 2) }}
        </p>

        <form method="POST" action="{{ route('admin.inventory.payments.store') }}">
            @csrf
            <input type="hidden" name="supplier_id" value="{{ $supplier->id }}">

            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                    {{ __('Payment Amount ($)') }} <span style="color: #ef4444;">*</span>
                </label>
                <input type="number" step="0.01" name="amount" value="{{ $supplier->current_balance > 0 ? $supplier->current_balance : '' }}" required class="form-input" style="width: 100%; border-radius: 8px;">
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
                    {{ __('Transaction Reference / Check #') }}
                </label>
                <input type="text" name="reference_number" placeholder="{{ __('e.g., TXN-998822') }}" class="form-input" style="width: 100%; border-radius: 8px;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('paymentModal').close();" class="btn btn-secondary" style="border-radius: 10px;">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700;">
                    <i class="fa-solid fa-check"></i> {{ __('Post Payment') }}
                </button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal: Add Contract Price -->
<dialog id="addPriceModal" style="border: none; border-radius: 16px; padding: 0; background: transparent; max-width: 440px; width: 90%;">
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.75rem; box-shadow: var(--shadow-modal);">
        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-tags" style="color: #f59e0b;"></i>
            <span>{{ __('Add Contract Price') }}</span>
        </h3>

        <form method="POST" action="{{ route('admin.inventory.suppliers.prices.save', $supplier) }}">
            @csrf

            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                    {{ __('Ingredient') }} <span style="color: #ef4444;">*</span>
                </label>
                <select name="ingredient_id" required class="form-select" style="width: 100%; border-radius: 8px;">
                    <option value="">{{ __('Select Ingredient...') }}</option>
                    @foreach($allIngredients as $ing)
                        <option value="{{ $ing->id }}" {{ $ing->unit_id ? 'data-unit='.$ing->unit_id : '' }}>
                            {{ $ing->name }} ({{ $ing->unit?->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                        {{ __('Contract Price ($)') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="number" step="0.01" name="price" required class="form-input" style="width: 100%; border-radius: 8px;">
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                        {{ __('Unit') }} <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="unit_id" required class="form-select" style="width: 100%; border-radius: 8px;">
                        @foreach($units as $u)
                            <option value="{{ $u->id }}">{{ $u->code }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                    {{ __('Supplier SKU (Optional)') }}
                </label>
                <input type="text" name="supplier_sku" placeholder="{{ __('Supplier Catalog Code') }}" class="form-input" style="width: 100%; border-radius: 8px;">
            </div>

            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem;">
                <input type="checkbox" name="is_primary_supplier" id="is_primary" value="1" style="width: 16px; height: 16px; border-radius: 4px;">
                <label for="is_primary" style="font-size: 0.85rem; font-weight: 600; color: var(--text-main); cursor: pointer;">
                    {{ __('Set as Primary Supplier for this item') }}
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('addPriceModal').close();" class="btn btn-secondary" style="border-radius: 10px;">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700;">
                    <i class="fa-solid fa-check"></i> {{ __('Save Price') }}
                </button>
            </div>
        </form>
    </div>
</dialog>
@endsection

