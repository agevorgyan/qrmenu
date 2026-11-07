@extends('layouts.app')

@section('title', __('Wholesale Deliveries & Logistics'))

@section('content')
<div class="content-wrapper">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6366f1;">
                    {{ __('Wholesale Subsystem') }}
                </span>
            </div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
                {{ __('Wholesale Deliveries & Logistics') }}
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                {{ __('Dispatch routes, driver assignments, proof-of-delivery confirmation, and physical stock depletions.') }}
            </p>
        </div>
    </div>

    <!-- Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.02);">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-main); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">
                    <th style="padding: 0.85rem 1.25rem;">{{ __('Delivery #') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Order #') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Customer / Destination') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Driver / Vehicle') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Warehouse') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Status') }}</th>
                    <th style="padding: 0.85rem 1.25rem; text-align: right;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($deliveries as $d)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 1rem 1.25rem; font-family: monospace; font-weight: 700;">
                            <a href="{{ route('admin.wholesale.deliveries.show', $d) }}" style="color: #6366f1; text-decoration: none;">
                                {{ $d->delivery_number }}
                            </a>
                        </td>
                        <td style="padding: 1rem 1rem; font-family: monospace;">
                            <a href="{{ route('admin.wholesale.orders.show', $d->order) }}" style="color: inherit; text-decoration: none;">
                                {{ $d->order?->order_number }}
                            </a>
                        </td>
                        <td style="padding: 1rem 1rem;">
                            <div style="font-weight: 700; color: var(--text-main);">{{ $d->order?->customer?->name }}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $d->destinationAddress?->name ?? 'Standard' }}</div>
                        </td>
                        <td style="padding: 1rem 1rem; font-size: 0.85rem;">
                            @if($d->driver_name)
                                <div><i class="fa-solid fa-truck"></i> {{ $d->driver_name }}</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $d->vehicle_number }}</div>
                            @else
                                <span style="color: var(--text-muted);">{{ __('Unassigned') }}</span>
                            @endif
                        </td>
                        <td style="padding: 1rem 1rem; font-size: 0.85rem; color: var(--text-main);">
                            {{ $d->warehouse?->name ?? __('Default Warehouse') }}
                        </td>
                        <td style="padding: 1rem 1rem;">
                            @if($d->status === 'DELIVERED')
                                <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px; background: rgba(16, 185, 129, 0.1); color: #10b981;">{{ __('Delivered') }}</span>
                            @elseif($d->status === 'DISPATCHED')
                                <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px; background: rgba(99, 102, 241, 0.1); color: #6366f1;">{{ __('In Transit') }}</span>
                            @else
                                <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px; background: rgba(245, 158, 11, 0.1); color: #d97706;">{{ __('Planned') }}</span>
                            @endif
                        </td>
                        <td style="padding: 1rem 1.25rem; text-align: right;">
                            <a href="{{ route('admin.wholesale.deliveries.show', $d) }}" 
                               style="padding: 0.4rem 0.75rem; border-radius: 8px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.82rem; font-weight: 600; text-decoration: none;">
                                {{ __('Details') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding: 3rem 1rem; text-align: center; color: var(--text-muted);">
                            <i class="fa-solid fa-truck-ramp-box" style="font-size: 2rem; margin-bottom: 0.75rem; color: var(--border-color); display: block;"></i>
                            {{ __('No wholesale deliveries scheduled yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1.5rem;">
        {{ $deliveries->links() }}
    </div>
</div>
@endsection

