@extends('layouts.app')

@section('title', __('Wholesale Commercial Sales & Invoices'))

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
                {{ __('Wholesale Sales Invoices') }}
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                {{ __('Commercial wholesale invoices issued to corporate buyers, with payment methods, tax breakdowns, and PEK export readiness.') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <a href="{{ route('admin.wholesale.pos.index') }}" 
               style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #6366f1; color: #fff; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">
                <i class="fa-solid fa-cash-register"></i>
                <span>{{ __('New POS Sale') }}</span>
            </a>
        </div>
    </div>

    <!-- Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.02);">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-main); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">
                    <th style="padding: 0.85rem 1.25rem;">{{ __('Invoice #') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Date') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Seller') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Buyer / Customer') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Payment') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Total') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Status') }}</th>
                    <th style="padding: 0.85rem 1.25rem; text-align: right;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sales as $s)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 1rem 1.25rem; font-family: monospace; font-weight: 700;">
                            <a href="{{ route('admin.wholesale.sales.show', $s) }}" style="color: #6366f1; text-decoration: none;">
                                {{ $s->invoice_number }}
                            </a>
                        </td>
                        <td style="padding: 1rem 1rem; font-size: 0.85rem; color: var(--text-muted);">
                            {{ $s->sale_date?->format('d/m/Y') }}
                        </td>
                        <td style="padding: 1rem 1rem; font-weight: 600; color: var(--text-main);">
                            {{ $s->seller?->name }}
                        </td>
                        <td style="padding: 1rem 1rem;">
                            <div style="font-weight: 700; color: var(--text-main);">{{ $s->customer?->name }}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">TIN: {{ $s->customer?->tin ?? '—' }}</div>
                        </td>
                        <td style="padding: 1rem 1rem; font-size: 0.82rem;">
                            {{ $s->payment_method }}
                        </td>
                        <td style="padding: 1rem 1rem; font-weight: 800; color: var(--text-main);">
                            {{ number_format($s->total_amount, 2) }} AMD
                        </td>
                        <td style="padding: 1rem 1rem;">
                            @if($s->status === 'PAID')
                                <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px; background: rgba(16, 185, 129, 0.1); color: #10b981;">{{ __('Paid') }}</span>
                            @else
                                <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px; background: rgba(99, 102, 241, 0.1); color: #6366f1;">{{ __('Issued') }}</span>
                            @endif
                        </td>
                        <td style="padding: 1rem 1.25rem; text-align: right;">
                            <a href="{{ route('admin.wholesale.sales.print', $s) }}" target="_blank"
                               style="padding: 0.4rem 0.75rem; border-radius: 8px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.82rem; font-weight: 600; text-decoration: none; margin-right: 0.35rem;">
                                <i class="fa-solid fa-print"></i> {{ __('Print') }}
                            </a>
                            <a href="{{ route('admin.wholesale.sales.show', $s) }}" 
                               style="padding: 0.4rem 0.75rem; border-radius: 8px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.82rem; font-weight: 600; text-decoration: none;">
                                {{ __('Details') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="padding: 3rem 1rem; text-align: center; color: var(--text-muted);">
                            <i class="fa-solid fa-receipt" style="font-size: 2rem; margin-bottom: 0.75rem; color: var(--border-color); display: block;"></i>
                            {{ __('No commercial sales invoices recorded yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1.5rem;">
        {{ $sales->links() }}
    </div>
</div>
@endsection

