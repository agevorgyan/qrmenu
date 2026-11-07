@extends('layouts.app')

@section('title', __('Plan Delivery for Order :num', ['num' => $order->order_number]))

@section('content')
<div class="content-wrapper" style="max-width: 860px; margin: 0 auto;">
    <!-- Header -->
    <div style="margin-bottom: 2rem;">
        <a href="{{ route('admin.wholesale.orders.show', $order) }}" style="color: #6366f1; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
            <i class="fa-solid fa-arrow-left"></i>
            <span>{{ __('Back to Order') }}</span>
        </a>
        <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
            {{ __('Schedule Order Delivery') }}
        </h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
            {{ __('Assign logistics driver, source fulfillment warehouse, and quantities to dispatch.') }}
        </p>
    </div>

    @if($errors->any())
        <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; padding: 1rem 1.25rem; color: #ef4444; margin-bottom: 1.5rem;">
            <div style="font-weight: 700; margin-bottom: 0.5rem;">{{ __('Please correct the following errors:') }}</div>
            <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.88rem;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.wholesale.deliveries.store') }}" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
        @csrf
        <input type="hidden" name="order_id" value="{{ $order->id }}">

        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0 0 1.25rem 0; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            1. {{ __('Logistics & Route Assignment') }}
        </h2>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Source Fulfillment Warehouse') }}
                </label>
                <select name="warehouse_id" style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ $wh->is_default ? 'selected' : '' }}>{{ $wh->name }} ({{ $wh->code }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Destination Drop-off Point') }}
                </label>
                <select name="destination_address_id" style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
                    @foreach($order->customer->addresses as $addr)
                        <option value="{{ $addr->id }}">{{ $addr->name }} &mdash; {{ $addr->full_address }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Driver Name') }}
                </label>
                <input type="text" name="driver_name" placeholder="Gor Karapetyan"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Driver Phone') }}
                </label>
                <input type="text" name="driver_phone" placeholder="+374 98 112233"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem;">
            </div>

            <div>
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem;">
                    {{ __('Vehicle License Plate') }}
                </label>
                <input type="text" name="vehicle_number" placeholder="77 GG 777"
                       style="width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.9rem; font-family: monospace;">
            </div>
        </div>

        <!-- 2. Items -->
        <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 2rem 0 1.25rem 0; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
            2. {{ __('Quantities to Load and Dispatch') }}
        </h2>

        <div style="margin-bottom: 1.5rem;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                        <th style="padding: 0.5rem 0.75rem;">{{ __('Item / SKU') }}</th>
                        <th style="padding: 0.5rem 0.75rem;">{{ __('Ordered') }}</th>
                        <th style="padding: 0.5rem 0.75rem;">{{ __('Already Delivered') }}</th>
                        <th style="padding: 0.5rem 0.75rem;">{{ __('Quantity This Run') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $idx => $item)
                        @php
                            $remaining = max(0, (float)$item->quantity_ordered - (float)$item->quantity_delivered);
                        @endphp
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.75rem;">
                                <div style="font-weight: 700; color: var(--text-main);">{{ $item->product_name }}</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">SKU: {{ $item->sku }}</div>
                                <input type="hidden" name="items[{{ $idx }}][order_item_id]" value="{{ $item->id }}">
                            </td>
                            <td style="padding: 0.75rem;">{{ (float)$item->quantity_ordered }} {{ $item->unit_code }}</td>
                            <td style="padding: 0.75rem; color: var(--text-muted);">{{ (float)$item->quantity_delivered }} {{ $item->unit_code }}</td>
                            <td style="padding: 0.75rem;">
                                <input type="number" step="0.0001" name="items[{{ $idx }}][quantity]" value="{{ $remaining }}" max="{{ $remaining }}" min="0" required
                                       style="width: 100px; padding: 0.4rem 0.6rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-weight: 700;">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
            <a href="{{ route('admin.wholesale.orders.show', $order) }}" 
               style="padding: 0.65rem 1.25rem; border-radius: 10px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 600; text-decoration: none;">
                {{ __('Cancel') }}
            </a>
            <button type="submit" 
                    style="padding: 0.65rem 1.5rem; border-radius: 10px; background: #6366f1; border: none; color: #fff; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">
                <i class="fa-solid fa-truck"></i>
                <span>{{ __('Schedule Delivery') }}</span>
            </button>
        </div>
    </form>
</div>
@endsection

