@extends('layouts.app')

@section('title', __('Sale Invoice: :num', ['num' => $sale->invoice_number]))

@section('content')
<div class="content-wrapper">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="{{ route('admin.wholesale.sales.index') }}" style="color: #6366f1; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-arrow-left"></i>
                <span>{{ __('Back to Sales Invoices') }}</span>
            </a>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0; font-family: monospace;">
                    {{ $sale->invoice_number }}
                </h1>
                @if($sale->status === 'PAID')
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: rgba(16, 185, 129, 0.12); color: #10b981;">
                        <i class="fa-solid fa-check-circle"></i> {{ __('Paid') }}
                    </span>
                @else
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: rgba(99, 102, 241, 0.12); color: #6366f1;">
                        <i class="fa-solid fa-file-invoice"></i> {{ __('Issued') }}
                    </span>
                @endif
            </div>
            <div style="color: var(--text-muted); font-size: 0.88rem; margin-top: 0.35rem;">
                {{ __('Date') }}: {{ $sale->sale_date?->format('d/m/Y') }} &bull; {{ __('Payment Method') }}: {{ $sale->payment_method }}
                @if($sale->order)
                    &bull; {{ __('Source Order') }}: <a href="{{ route('admin.wholesale.orders.show', $sale->order) }}" style="color: #6366f1;">{{ $sale->order->order_number }}</a>
                @endif
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <form method="POST" action="{{ route('admin.wholesale.tax_documents.generate_from_sale', $sale) }}">
                @csrf
                <button type="submit"
                        style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #10b981; color: #fff; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);">
                    <i class="fa-solid fa-stamp"></i>
                    <span>{{ __('Generate PEK Tax Doc') }}</span>
                </button>
            </form>
            <a href="{{ route('admin.wholesale.sales.print', $sale) }}" target="_blank"
               style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #6366f1; color: #fff; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3);">
                <i class="fa-solid fa-print"></i>
                <span>{{ __('Print Invoice') }}</span>
            </a>
        </div>
    </div>

    <!-- Cards Row -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Seller -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                {{ __('Seller / Supplier') }}
            </div>
            <div style="font-size: 1.1rem; font-weight: 800; color: var(--text-main);">
                {{ $sale->seller?->legal_name ?? $sale->seller?->name }}
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem; font-family: monospace;">
                TIN: {{ $sale->seller?->tin }} &bull; {{ $sale->seller?->tax_regime->value }}
            </div>
            @if($sale->seller?->defaultBankAccount)
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                    {{ $sale->seller->defaultBankAccount->bank_name }}: {{ $sale->seller->defaultBankAccount->account_number }}
                </div>
            @endif
        </div>

        <!-- Buyer -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                {{ __('Buyer / Customer') }}
            </div>
            <div style="font-size: 1.1rem; font-weight: 800; color: var(--text-main);">
                {{ $sale->customer?->legal_name ?? $sale->customer?->name }}
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem; font-family: monospace;">
                TIN: {{ $sale->customer?->tin ?? '—' }} &bull; {{ $sale->customer?->tax_regime }}
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ $sale->customer?->phone }} &bull; {{ $sale->customer?->email }}
            </div>
        </div>
    </div>

    <!-- Sale Items Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; margin-bottom: 2rem;">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-main); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                    <th style="padding: 0.85rem 1.25rem;">#</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Item / SKU') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('ATGAA') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Quantity') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Unit Price') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Discount') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('VAT (20%)') }}</th>
                    <th style="padding: 0.85rem 1.25rem; text-align: right;">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->items as $idx => $item)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 0.85rem 1.25rem; color: var(--text-muted); font-size: 0.8rem;">{{ $idx + 1 }}</td>
                        <td style="padding: 0.85rem 1rem;">
                            <div style="font-weight: 700; color: var(--text-main);">{{ $item->product_name }}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">SKU: {{ $item->sku }}</div>
                        </td>
                        <td style="padding: 0.85rem 1rem; font-family: monospace; font-size: 0.85rem; color: #10b981;">
                            {{ $item->classifier_code }}
                        </td>
                        <td style="padding: 0.85rem 1rem; font-weight: 700; color: var(--text-main);">
                            {{ (float)$item->quantity }} {{ $item->unit_code }}
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            {{ number_format($item->unit_price, 2) }} AMD
                        </td>
                        <td style="padding: 0.85rem 1rem; color: #f59e0b;">
                            {{ (float)$item->discount_amount > 0 ? '-' . number_format($item->discount_amount, 2) : '—' }}
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            {{ number_format($item->vat_amount, 2) }} AMD
                        </td>
                        <td style="padding: 0.85rem 1.25rem; text-align: right; font-weight: 800; color: var(--text-main);">
                            {{ number_format($item->total_amount, 2) }} AMD
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals Card -->
        <div style="padding: 1.5rem; background: var(--bg-main); border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
            <div style="width: 320px;">
                <div style="display: flex; justify-content: space-between; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text-muted);">
                    <span>{{ __('Subtotal') }}:</span>
                    <span style="font-weight: 700; color: var(--text-main);">{{ number_format($sale->subtotal, 2) }} AMD</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text-muted);">
                    <span>{{ __('Discounts') }}:</span>
                    <span style="font-weight: 700; color: #f59e0b;">-{{ number_format($sale->discount_total, 2) }} AMD</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text-muted);">
                    <span>{{ __('VAT (20%)') }}:</span>
                    <span style="font-weight: 700; color: var(--text-main);">{{ number_format($sale->tax_total, 2) }} AMD</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 1.25rem; font-weight: 800; color: var(--text-main); padding-top: 0.5rem; border-top: 2px solid var(--border-color);">
                    <span>{{ __('Grand Total') }}:</span>
                    <span style="color: #6366f1;">{{ number_format($sale->total_amount, 2) }} AMD</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
