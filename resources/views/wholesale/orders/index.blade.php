@extends('layouts.app')

@section('title', __('Wholesale Orders'))

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
                {{ __('Wholesale Orders & Pre-orders') }}
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                {{ __('Track client order drafts, confirmed wholesale bookings, and fulfillment stages.') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <a href="{{ route('admin.wholesale.pos.index') }}" 
               style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #6366f1; color: #fff; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">
                <i class="fa-solid fa-cash-register"></i>
                <span>{{ __('Open Wholesale POS') }}</span>
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
        <form method="GET" action="{{ route('admin.wholesale.orders.index') }}" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search by order number or customer name...') }}"
                       style="width: 100%; padding: 0.55rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.88rem;">
            </div>

            <div style="min-width: 160px;">
                <select name="status" style="width: 100%; padding: 0.55rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); font-size: 0.88rem;">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="DRAFT" {{ ($filters['status'] ?? '') === 'DRAFT' ? 'selected' : '' }}>{{ __('Draft') }}</option>
                    <option value="CONFIRMED" {{ ($filters['status'] ?? '') === 'CONFIRMED' ? 'selected' : '' }}>{{ __('Confirmed') }}</option>
                    <option value="PARTIALLY_DELIVERED" {{ ($filters['status'] ?? '') === 'PARTIALLY_DELIVERED' ? 'selected' : '' }}>{{ __('Partially Delivered') }}</option>
                    <option value="DELIVERED" {{ ($filters['status'] ?? '') === 'DELIVERED' ? 'selected' : '' }}>{{ __('Delivered') }}</option>
                    <option value="CANCELLED" {{ ($filters['status'] ?? '') === 'CANCELLED' ? 'selected' : '' }}>{{ __('Cancelled') }}</option>
                </select>
            </div>

            <button type="submit" style="padding: 0.55rem 1rem; border-radius: 8px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 700; cursor: pointer;">
                <i class="fa-solid fa-filter"></i> {{ __('Filter') }}
            </button>
        </form>
    </div>

    <!-- Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.02);">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-main); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">
                    <th style="padding: 0.85rem 1.25rem;">{{ __('Order #') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Date') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Seller Company') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Buyer / Client') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Status') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Total Amount') }}</th>
                    <th style="padding: 0.85rem 1.25rem; text-align: right;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $o)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 1rem 1.25rem; font-family: monospace; font-weight: 700;">
                            <a href="{{ route('admin.wholesale.orders.show', $o) }}" style="color: #6366f1; text-decoration: none;">
                                {{ $o->order_number }}
                            </a>
                        </td>
                        <td style="padding: 1rem 1rem; font-size: 0.85rem; color: var(--text-muted);">
                            {{ $o->order_date?->format('d/m/Y') }}
                        </td>
                        <td style="padding: 1rem 1rem; font-weight: 600; color: var(--text-main);">
                            {{ $o->seller?->name }}
                        </td>
                        <td style="padding: 1rem 1rem;">
                            <div style="font-weight: 700; color: var(--text-main);">{{ $o->customer?->name }}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">TIN: {{ $o->customer?->tin ?? '—' }}</div>
                        </td>
                        <td style="padding: 1rem 1rem;">
                            @if($o->status === 'CONFIRMED')
                                <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px; background: rgba(16, 185, 129, 0.1); color: #10b981;">{{ __('Confirmed') }}</span>
                            @elseif($o->status === 'DRAFT')
                                <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px; background: rgba(245, 158, 11, 0.1); color: #d97706;">{{ __('Draft') }}</span>
                            @else
                                <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px; background: var(--bg-main); color: var(--text-main);">{{ $o->status }}</span>
                            @endif
                        </td>
                        <td style="padding: 1rem 1rem; font-weight: 800; color: var(--text-main);">
                            {{ number_format($o->total_amount, 2) }} AMD
                        </td>
                        <td style="padding: 1rem 1.25rem; text-align: right;">
                            <a href="{{ route('admin.wholesale.orders.show', $o) }}" 
                               style="padding: 0.4rem 0.75rem; border-radius: 8px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.82rem; font-weight: 600; text-decoration: none;">
                                {{ __('Details') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding: 3rem 1rem; text-align: center; color: var(--text-muted);">
                            <i class="fa-solid fa-file-invoice" style="font-size: 2rem; margin-bottom: 0.75rem; color: var(--border-color); display: block;"></i>
                            {{ __('No wholesale orders created yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1.5rem;">
        {{ $orders->links() }}
    </div>
</div>
@endsection

