@extends('layouts.app')

@section('title', __('Supplier Invoices & Accounts Payable') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 0; flex: 1;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fa-solid fa-file-invoice-dollar"></i>
            </span>
            <span>{{ __('Supplier Invoices & Accounts Payable') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
            {{ __('Track supplier bills from stock intakes, outstanding debts, due dates, and disbursements.') }}
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        @can('procurement.pay')
            <button type="button" onclick="document.getElementById('recordPaymentModal').showModal();" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 700;">
                <i class="fa-solid fa-money-bill-wave"></i> {{ __('Record Payment') }}
            </button>
        @endcan
    </div>
</div>

<!-- KPI Stats Summary -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(59, 130, 246, 0.12); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-file-invoice"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Total Invoiced') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; font-family: monospace; color: var(--text-main);">{{ number_format($totalInvoiced, 2) }} {{ Auth::user()->vendor->currency }}</div>
        </div>
    </div>

    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Total Paid') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; font-family: monospace; color: #10b981;">{{ number_format($totalPaid, 2) }} {{ Auth::user()->vendor->currency }}</div>
        </div>
    </div>

    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(239, 68, 68, 0.12); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Outstanding AP Debt') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; font-family: monospace; color: #ef4444;">{{ number_format($totalOutstanding, 2) }} {{ Auth::user()->vendor->currency }}</div>
        </div>
    </div>

    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(220, 38, 38, 0.12); color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Overdue Invoices') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #dc2626;">{{ $overdueCount }}</div>
        </div>
    </div>
</div>

<!-- Search & Filters -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
    <form method="GET" action="{{ route('admin.inventory.invoices.index') }}" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 2; min-width: 220px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Search') }}</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search invoice # or supplier...') }}" class="form-input" style="width: 100%; border-radius: 10px;">
        </div>

        <div style="flex: 1; min-width: 160px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Payment Status') }}</label>
            <select name="status" class="form-select" style="width: 100%; border-radius: 10px;">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="unpaid" {{ request('status') === 'unpaid' ? 'selected' : '' }}>{{ __('Unpaid') }}</option>
                <option value="partially_paid" {{ request('status') === 'partially_paid' ? 'selected' : '' }}>{{ __('Partially Paid') }}</option>
                <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>{{ __('Paid') }}</option>
                <option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>{{ __('Overdue') }}</option>
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
            @if(request()->hasAny(['search', 'status', 'supplier_id']))
                <a href="{{ route('admin.inventory.invoices.index') }}" class="btn btn-secondary" style="border-radius: 10px;">
                    {{ __('Reset') }}
                </a>
            @endif
        </div>
    </form>
</div>

<!-- Invoices Table -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);">
    @if($invoices->isEmpty())
        <div style="padding: 4rem 1.5rem; text-align: center;">
            <div style="width: 72px; height: 72px; border-radius: 20px; background: rgba(16, 185, 129, 0.1); color: #10b981; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 1rem;">
                <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin: 0 0 0.5rem;">
                {{ __('No invoices found') }}
            </h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; max-width: 450px; margin: 0 auto;">
                {{ __('Invoices are generated automatically when stock is received against purchase orders or via direct supplier intake.') }}
            </p>
        </div>
    @else
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.02);">
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('Invoice # / Supplier') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('Invoice Date') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('Due Date') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; text-align: right;">{{ __('Total') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; text-align: right;">{{ __('Paid') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-main); font-size: 0.78rem; text-transform: uppercase; text-align: right;">{{ __('Remaining Debt') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; text-align: center;">{{ __('Status') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $inv)
                        @php
                            $isOverdue = $inv->payment_status !== 'paid' && $inv->due_date->isPast();
                        @endphp
                        <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.15s ease;">
                            <td style="padding: 1rem 1.25rem;">
                                <a href="{{ route('admin.inventory.invoices.show', $inv) }}" style="font-weight: 700; font-family: monospace; color: var(--text-main); text-decoration: none;">
                                    {{ $inv->invoice_number }}
                                </a>
                                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
                                    <a href="{{ route('admin.inventory.suppliers.show', $inv->supplier) }}" style="color: inherit; text-decoration: none;">
                                        {{ $inv->supplier->name }}
                                    </a>
                                    @if($inv->purchaseOrder)
                                        • <a href="{{ route('admin.inventory.procurement.show', $inv->purchaseOrder) }}" style="color: #6366f1; text-decoration: none;">
                                            {{ $inv->purchaseOrder->po_number }}
                                        </a>
                                    @endif
                                </div>
                            </td>

                            <td style="padding: 1rem 1.25rem; color: var(--text-muted);">
                                {{ $inv->invoice_date->toVendorTimezone()->format('M d, Y') }}
                            </td>

                            <td style="padding: 1rem 1.25rem; font-weight: 600; color: {{ $isOverdue ? '#dc2626' : 'var(--text-main)' }};">
                                {{ $inv->due_date->toVendorTimezone()->format('M d, Y') }}
                                @if($isOverdue)
                                    <span style="font-size: 0.72rem; color: #dc2626; display: block;">{{ __('Overdue') }}</span>
                                @endif
                            </td>

                            <td style="padding: 1rem 1.25rem; text-align: right; font-family: monospace; font-weight: 600;">
                                {{ number_format($inv->total_amount, 2) }} {{ Auth::user()->vendor->currency }}
                            </td>

                            <td style="padding: 1rem 1.25rem; text-align: right; font-family: monospace; font-weight: 600; color: #10b981;">
                                {{ number_format($inv->paid_amount, 2) }} {{ Auth::user()->vendor->currency }}
                            </td>

                            <td style="padding: 1rem 1.25rem; text-align: right; font-family: monospace; font-weight: 800; color: {{ (float) $inv->remaining_balance > 0 ? '#ef4444' : '#10b981' }};">
                                {{ number_format($inv->remaining_balance, 2) }} {{ Auth::user()->vendor->currency }}
                            </td>

                            <td style="padding: 1rem 1.25rem; text-align: center;">
                                @php
                                    $stBadge = [
                                        'paid' => ['bg' => 'rgba(16, 185, 129, 0.12)', 'color' => '#10b981', 'label' => __('Paid')],
                                        'partially_paid' => ['bg' => 'rgba(245, 158, 11, 0.12)', 'color' => '#f59e0b', 'label' => __('Partially Paid')],
                                        'unpaid' => ['bg' => 'rgba(239, 68, 68, 0.12)', 'color' => '#ef4444', 'label' => __('Unpaid')],
                                        'overdue' => ['bg' => 'rgba(220, 38, 38, 0.2)', 'color' => '#dc2626', 'label' => __('Overdue')],
                                    ];
                                    $statusKey = $isOverdue ? 'overdue' : $inv->payment_status;
                                    $b = $stBadge[$statusKey] ?? ['bg' => 'rgba(0,0,0,0.05)', 'color' => 'inherit', 'label' => $inv->payment_status];
                                @endphp
                                <span style="display: inline-block; padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; background: {{ $b['bg'] }}; color: {{ $b['color'] }};">
                                    {{ $b['label'] }}
                                </span>
                            </td>

                            <td style="padding: 1rem 1.25rem; text-align: right;">
                                <a href="{{ route('admin.inventory.invoices.show', $inv) }}" class="btn btn-secondary btn-sm" title="{{ __('View Details & Pay') }}" style="border-radius: 8px; padding: 0.4rem 0.65rem;">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($invoices->hasPages())
            <div style="padding: 1.25rem; border-top: 1px solid var(--border-color);">
                {{ $invoices->links() }}
            </div>
        @endif
    @endif
</div>

<!-- Modal: Record Supplier Payment -->
<dialog id="recordPaymentModal" style="border: none; border-radius: 16px; padding: 0; background: transparent; max-width: 480px; width: 90%;">
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.75rem; box-shadow: var(--shadow-modal);">
        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin: 0 0 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-money-bill-wave" style="color: #10b981;"></i>
            <span>{{ __('Record Supplier Payment') }}</span>
        </h3>
        <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0 0 1.25rem;">
            {{ __('Post a disbursement payment to a supplier. The payment will be automatically allocated to unpaid invoices.') }}
        </p>

        <form method="POST" action="{{ route('admin.inventory.payments.store') }}">
            @csrf

            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                    {{ __('Select Supplier') }} <span style="color: #ef4444;">*</span>
                </label>
                <select name="supplier_id" required class="form-select" style="width: 100%; border-radius: 8px;">
                    <option value="">{{ __('Select Supplier...') }}</option>
                    @foreach($suppliers as $sup)
                        <option value="{{ $sup->id }}">
                            {{ $sup->name }} ({{ __('Debt:') }} {{ number_format($sup->current_balance, 2) }} {{ Auth::user()->vendor->currency }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: block; margin-bottom: 0.35rem;">
                    {{ __('Payment Amount') }} ({{ Auth::user()->vendor->currency }}) <span style="color: #ef4444;">*</span>
                </label>
                <input type="number" step="0.01" name="amount" required class="form-input" style="width: 100%; border-radius: 8px;">
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
                <input type="text" name="reference_number" class="form-input" style="width: 100%; border-radius: 8px;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('recordPaymentModal').close();" class="btn btn-secondary" style="border-radius: 10px;">
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

