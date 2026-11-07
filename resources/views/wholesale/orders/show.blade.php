@extends('layouts.app')

@section('title', __('Wholesale Order: :num', ['num' => $order->order_number]))

@section('content')
<div class="content-wrapper">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="{{ route('admin.wholesale.orders.index') }}" style="color: #6366f1; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-arrow-left"></i>
                <span>{{ __('Back to Orders') }}</span>
            </a>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0; font-family: monospace;">
                    {{ $order->order_number }}
                </h1>
                @if($order->status === 'CONFIRMED')
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: rgba(16, 185, 129, 0.12); color: #10b981;">
                        <i class="fa-solid fa-check-circle"></i> {{ __('Confirmed') }}
                    </span>
                @elseif($order->status === 'DRAFT')
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: rgba(245, 158, 11, 0.12); color: #d97706;">
                        <i class="fa-solid fa-clock"></i> {{ __('Draft') }}
                    </span>
                @else
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: var(--bg-main); color: var(--text-main);">
                        {{ $order->status }}
                    </span>
                @endif
            </div>
            <div style="color: var(--text-muted); font-size: 0.88rem; margin-top: 0.35rem;">
                {{ __('Date') }}: {{ $order->order_date?->format('d/m/Y') }} &bull; {{ __('Type') }}: {{ $order->order_type }}
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            @if($order->status === 'DRAFT')
                <form method="POST" action="{{ route('admin.wholesale.orders.confirm', $order) }}">
                    @csrf
                    <button type="submit" 
                            style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #10b981; color: #fff; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);">
                        <i class="fa-solid fa-check"></i>
                        <span>{{ __('Confirm Order') }}</span>
                    </button>
                </form>
            @endif

            @if($order->status === 'CONFIRMED' && $order->sales->isEmpty())
                <form method="POST" action="{{ route('admin.wholesale.orders.sale', $order) }}">
                    @csrf
                    <input type="hidden" name="payment_method" value="BANK_TRANSFER">
                    <button type="submit" 
                            style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #6366f1; color: #fff; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3);">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                        <span>{{ __('Generate Commercial Sale') }}</span>
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Cards Info Row -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Seller -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                {{ __('Issuing Seller') }}
            </div>
            <div style="font-size: 1.1rem; font-weight: 800; color: var(--text-main);">
                {{ $order->seller?->name }}
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem; font-family: monospace;">
                TIN: {{ $order->seller?->tin }} &bull; {{ $order->seller?->tax_regime->value }}
            </div>
        </div>

        <!-- Customer -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                {{ __('B2B Customer') }}
            </div>
            <div style="font-size: 1.1rem; font-weight: 800; color: var(--text-main);">
                {{ $order->customer?->name }}
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem; font-family: monospace;">
                TIN: {{ $order->customer?->tin ?? '—' }} &bull; {{ $order->customer?->tax_regime }}
            </div>
        </div>

        <!-- Delivery -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                {{ __('Delivery Point') }}
            </div>
            <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-main);">
                {{ $order->deliveryAddress?->name ?? __('Standard Delivery') }}
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ $order->deliveryAddress?->full_address ?? '—' }}
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; margin-bottom: 2rem;">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-main); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                    <th style="padding: 0.85rem 1.25rem;">#</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Item / SKU') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('ATGAA') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Qty Ordered') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Unit Price') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Discount') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('VAT (20%)') }}</th>
                    <th style="padding: 0.85rem 1.25rem; text-align: right;">{{ __('Line Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $idx => $item)
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
                            {{ (float)$item->quantity_ordered }} {{ $item->unit_code }}
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
                    <span style="font-weight: 700; color: var(--text-main);">{{ number_format($order->subtotal, 2) }} AMD</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text-muted);">
                    <span>{{ __('Discounts') }}:</span>
                    <span style="font-weight: 700; color: #f59e0b;">-{{ number_format($order->discount_total, 2) }} AMD</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.88rem; margin-bottom: 0.4rem; color: var(--text-muted);">
                    <span>{{ __('VAT (20%)') }}:</span>
                    <span style="font-weight: 700; color: var(--text-main);">{{ number_format($order->tax_total, 2) }} AMD</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 1.25rem; font-weight: 800; color: var(--text-main); padding-top: 0.5rem; border-top: 2px solid var(--border-color);">
                    <span>{{ __('Grand Total') }}:</span>
                    <span style="color: #6366f1;">{{ number_format($order->total_amount, 2) }} AMD</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

