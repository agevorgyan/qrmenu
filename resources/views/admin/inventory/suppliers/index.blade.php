@extends('layouts.app')

@section('title', __('Suppliers & Vendors') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div style="min-width: 0; flex: 1;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
            <span style="background: rgba(6, 182, 212, 0.15); color: #06b6d4; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fa-solid fa-truck-field"></i>
            </span>
            <span>{{ __('Suppliers & Vendor Management') }}</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
            {{ __('Manage food suppliers, contract price catalogs, payment terms, and outstanding accounts payable.') }}
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        @can('suppliers.create')
            <a href="{{ route('admin.inventory.suppliers.create') }}" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 700;">
                <i class="fa-solid fa-plus"></i> {{ __('Add Supplier') }}
            </a>
        @endcan
    </div>
</div>

<!-- KPI Stats Summary -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(59, 130, 246, 0.12); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-truck"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Total Suppliers') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: var(--text-main);">{{ $totalSuppliers }}</div>
        </div>
    </div>

    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Active Suppliers') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: var(--text-main);">{{ $activeSuppliers }}</div>
        </div>
    </div>

    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(239, 68, 68, 0.12); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-file-invoice-dollar"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Total Accounts Payable') }}</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #ef4444; font-family: monospace;">{{ number_format($totalDebt, 2) }} {{ Auth::user()->vendor->currency }}</div>
        </div>
    </div>
</div>

<!-- Search & Filter Form -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-card);">
    <form method="GET" action="{{ route('admin.inventory.suppliers.index') }}" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 2; min-width: 240px;">
            <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; display: block;">{{ __('Search') }}</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by company name, tax ID, contact person...') }}" class="form-input" style="width: 100%; border-radius: 10px;">
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem; padding-bottom: 0.6rem;">
            <input type="checkbox" name="with_balance" id="with_balance" value="1" {{ request('with_balance') ? 'checked' : '' }} style="width: 18px; height: 18px; border-radius: 5px;">
            <label for="with_balance" style="font-size: 0.88rem; font-weight: 600; color: var(--text-main); cursor: pointer;">
                {{ __('With Outstanding Balance (AP)') }}
            </label>
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 600; padding: 0.6rem 1.25rem;">
                <i class="fa-solid fa-filter"></i> {{ __('Filter') }}
            </button>
            @if(request()->hasAny(['search', 'with_balance']))
                <a href="{{ route('admin.inventory.suppliers.index') }}" class="btn btn-secondary" style="border-radius: 10px;">
                    {{ __('Reset') }}
                </a>
            @endif
        </div>
    </form>
</div>

<!-- Suppliers Table Card -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);">
    @if($suppliers->isEmpty())
        <div style="padding: 4rem 1.5rem; text-align: center;">
            <div style="width: 72px; height: 72px; border-radius: 20px; background: rgba(6, 182, 212, 0.1); color: #06b6d4; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 1rem;">
                <i class="fa-solid fa-truck-field"></i>
            </div>
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin: 0 0 0.5rem;">
                {{ __('No suppliers found') }}
            </h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; max-width: 450px; margin: 0 auto 1.5rem;">
                {{ __('Add your food and beverage suppliers, link ingredient contract prices, and automate purchase orders.') }}
            </p>
            @can('suppliers.create')
                <a href="{{ route('admin.inventory.suppliers.create') }}" class="btn btn-primary" style="border-radius: 12px; font-weight: 700;">
                    <i class="fa-solid fa-plus"></i> {{ __('Add First Supplier') }}
                </a>
            @endcan
        </div>
    @else
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.02);">
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('Supplier / Tax ID') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('Contact Person') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('Payment Terms') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('Orders / Invoices') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('Outstanding Balance (Debt)') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase;">{{ __('Status') }}</th>
                        <th style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($suppliers as $supplier)
                        <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.15s ease;">
                            <td style="padding: 1rem 1.25rem;">
                                <a href="{{ route('admin.inventory.suppliers.show', $supplier) }}" style="font-weight: 700; color: var(--text-main); text-decoration: none; font-size: 0.95rem;">
                                    {{ $supplier->name }}
                                </a>
                                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem; display: flex; align-items: center; gap: 0.5rem;">
                                    @if($supplier->tax_id)
                                        <span style="font-family: monospace; background: rgba(0,0,0,0.05); padding: 0.15rem 0.4rem; border-radius: 4px;">{{ __('Tax ID:') }} {{ $supplier->tax_id }}</span>
                                    @endif
                                    @if($supplier->code)
                                        <span>#{{ $supplier->code }}</span>
                                    @endif
                                </div>
                            </td>

                            <td style="padding: 1rem 1.25rem;">
                                <div style="font-weight: 600; color: var(--text-main);">
                                    {{ $supplier->contact_person ?: '—' }}
                                </div>
                                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">
                                    @if($supplier->phone)
                                        <i class="fa-solid fa-phone" style="font-size: 0.7rem;"></i> {{ $supplier->phone }}
                                    @endif
                                    @if($supplier->email)
                                        • <i class="fa-solid fa-envelope" style="font-size: 0.7rem;"></i> {{ $supplier->email }}
                                    @endif
                                </div>
                            </td>

                            <td style="padding: 1rem 1.25rem;">
                                @php
                                    $termBadges = [
                                        'cash' => ['label' => __('Cash on Delivery'), 'bg' => 'rgba(16, 185, 129, 0.12)', 'color' => '#10b981'],
                                        'net_7' => ['label' => __('Net 7 Days'), 'bg' => 'rgba(59, 130, 246, 0.12)', 'color' => '#3b82f6'],
                                        'net_15' => ['label' => __('Net 15 Days'), 'bg' => 'rgba(59, 130, 246, 0.12)', 'color' => '#3b82f6'],
                                        'net_30' => ['label' => __('Net 30 Days'), 'bg' => 'rgba(168, 85, 247, 0.12)', 'color' => '#a855f7'],
                                        'prepayment' => ['label' => __('Prepayment'), 'bg' => 'rgba(245, 158, 11, 0.12)', 'color' => '#f59e0b'],
                                    ];
                                    $tb = $termBadges[$supplier->payment_terms] ?? ['label' => $supplier->payment_terms, 'bg' => 'rgba(0,0,0,0.05)', 'color' => 'inherit'];
                                @endphp
                                <span style="display: inline-block; padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; background: {{ $tb['bg'] }}; color: {{ $tb['color'] }};">
                                    {{ $tb['label'] }}
                                </span>
                            </td>

                            <td style="padding: 1rem 1.25rem; font-size: 0.85rem; color: var(--text-muted);">
                                <div><i class="fa-solid fa-cart-shopping" style="color: #6366f1;"></i> {{ $supplier->purchase_orders_count }} {{ __('POs') }}</div>
                                <div style="margin-top: 0.15rem;"><i class="fa-solid fa-file-invoice" style="color: #10b981;"></i> {{ $supplier->invoices_count }} {{ __('Invoices') }}</div>
                            </td>

                            <td style="padding: 1rem 1.25rem; font-family: monospace; font-weight: 800; font-size: 0.95rem;">
                                @if((float) $supplier->current_balance > 0)
                                    <span style="color: #ef4444;">{{ number_format($supplier->current_balance, 2) }} {{ Auth::user()->vendor->currency }}</span>
                                @else
                                    <span style="color: #10b981;">0.00 {{ Auth::user()->vendor->currency }}</span>
                                @endif
                            </td>

                            <td style="padding: 1rem 1.25rem;">
                                @if($supplier->is_active)
                                    <span style="display: inline-block; padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; background: rgba(16, 185, 129, 0.12); color: #10b981;">
                                        {{ __('Active') }}
                                    </span>
                                @else
                                    <span style="display: inline-block; padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; background: rgba(107, 114, 128, 0.12); color: #6b7280;">
                                        {{ __('Inactive') }}
                                    </span>
                                @endif
                            </td>

                            <td style="padding: 1rem 1.25rem; text-align: right;">
                                <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                                    <a href="{{ route('admin.inventory.suppliers.show', $supplier) }}" class="btn btn-secondary btn-sm" title="{{ __('View Profile & Ledger') }}" style="border-radius: 8px; padding: 0.4rem 0.65rem;">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>

                                    @can('suppliers.update')
                                        <a href="{{ route('admin.inventory.suppliers.edit', $supplier) }}" class="btn btn-secondary btn-sm" title="{{ __('Edit') }}" style="border-radius: 8px; padding: 0.4rem 0.65rem;">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                    @endcan

                                    @can('suppliers.delete')
                                        <form method="POST" action="{{ route('admin.inventory.suppliers.destroy', $supplier) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this supplier?') }}');" style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-secondary btn-sm" title="{{ __('Delete') }}" style="border-radius: 8px; padding: 0.4rem 0.65rem; color: #ef4444;">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($suppliers->hasPages())
            <div style="padding: 1.25rem; border-top: 1px solid var(--border-color);">
                {{ $suppliers->links() }}
            </div>
        @endif
    @endif
</div>
@endsection

