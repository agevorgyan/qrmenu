@extends('layouts.app')

@section('title', __('Delivery: :num', ['num' => $delivery->delivery_number]))

@section('content')
<div class="content-wrapper">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="{{ route('admin.wholesale.deliveries.index') }}" style="color: #6366f1; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-arrow-left"></i>
                <span>{{ __('Back to Deliveries') }}</span>
            </a>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0; font-family: monospace;">
                    {{ $delivery->delivery_number }}
                </h1>
                @if($delivery->status === 'DELIVERED')
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: rgba(16, 185, 129, 0.12); color: #10b981;">
                        <i class="fa-solid fa-check-circle"></i> {{ __('Delivered') }}
                    </span>
                @elseif($delivery->status === 'DISPATCHED')
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: rgba(99, 102, 241, 0.12); color: #6366f1;">
                        <i class="fa-solid fa-truck"></i> {{ __('In Transit') }}
                    </span>
                @else
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: rgba(245, 158, 11, 0.12); color: #d97706;">
                        <i class="fa-solid fa-clock"></i> {{ __('Planned') }}
                    </span>
                @endif
            </div>
            <div style="color: var(--text-muted); font-size: 0.88rem; margin-top: 0.35rem;">
                {{ __('Order') }}: <a href="{{ route('admin.wholesale.orders.show', $delivery->order) }}" style="color: #6366f1; font-family: monospace;">{{ $delivery->order?->order_number }}</a>
                &bull; {{ __('Client') }}: {{ $delivery->order?->customer?->name }}
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            @if($delivery->status === 'PLANNED')
                <form method="POST" action="{{ route('admin.wholesale.deliveries.dispatch', $delivery) }}">
                    @csrf
                    <button type="submit" 
                            style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #6366f1; color: #fff; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3);">
                        <i class="fa-solid fa-truck-fast"></i>
                        <span>{{ __('Dispatch Vehicle') }}</span>
                    </button>
                </form>
            @endif

            @if($delivery->status === 'DISPATCHED')
                <form method="POST" action="{{ route('admin.wholesale.deliveries.confirm', $delivery) }}">
                    @csrf
                    <button type="submit" 
                            style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #10b981; color: #fff; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);">
                        <i class="fa-solid fa-check-double"></i>
                        <span>{{ __('Confirm Proof of Delivery') }}</span>
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Cards Row -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Warehouse & Route -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                {{ __('Fulfillment Origin') }}
            </div>
            <div style="font-size: 1.1rem; font-weight: 800; color: var(--text-main);">
                {{ $delivery->warehouse?->name ?? __('Default Central Warehouse') }}
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ $delivery->warehouse?->address ?? 'Central Hub' }}
            </div>
        </div>

        <!-- Driver -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                {{ __('Courier / Driver') }}
            </div>
            <div style="font-size: 1.1rem; font-weight: 800; color: var(--text-main);">
                {{ $delivery->driver_name ?? '—' }}
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ $delivery->driver_phone ?? '—' }} &bull; {{ __('Plate:') }} {{ $delivery->vehicle_number ?? '—' }}
            </div>
        </div>

        <!-- Drop-off Destination -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                {{ __('Destination') }}
            </div>
            <div style="font-size: 1rem; font-weight: 700; color: var(--text-main);">
                {{ $delivery->destinationAddress?->name ?? __('Standard Delivery') }}
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ $delivery->destinationAddress?->full_address ?? '—' }}
            </div>
        </div>
    </div>

    <!-- Items Manifest -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; margin-bottom: 2rem;">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-main); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                    <th style="padding: 0.85rem 1.25rem;">#</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Item / SKU') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Planned Qty') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Delivered Qty') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Physical Stock Mapping') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($delivery->items as $idx => $dItem)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 0.85rem 1.25rem; color: var(--text-muted); font-size: 0.8rem;">{{ $idx + 1 }}</td>
                        <td style="padding: 0.85rem 1rem;">
                            <div style="font-weight: 700; color: var(--text-main);">{{ $dItem->product?->name }}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">SKU: {{ $dItem->product?->sku }}</div>
                        </td>
                        <td style="padding: 0.85rem 1rem; font-weight: 700;">
                            {{ (float)$dItem->quantity_planned }} {{ $dItem->unit_code }}
                        </td>
                        <td style="padding: 0.85rem 1rem; font-weight: 700; color: #10b981;">
                            {{ (float)$dItem->quantity_delivered }} {{ $dItem->unit_code }}
                        </td>
                        <td style="padding: 0.85rem 1rem; font-size: 0.82rem;">
                            @if($dItem->product?->physical_stock_ingredient_id)
                                <span style="color: #6366f1; font-weight: 700;">
                                    <i class="fa-solid fa-link"></i> {{ __('Linked (Depleted from Warehouse)') }}
                                </span>
                            @else
                                <span style="color: var(--text-muted);">{{ __('No physical link') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

