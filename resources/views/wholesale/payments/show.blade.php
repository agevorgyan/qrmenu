@extends('layouts.app')

@section('title', __('Payment: :num', ['num' => $payment->payment_number]))

@section('content')
<div class="content-wrapper">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="{{ route('admin.wholesale.payments.index') }}" style="color: #6366f1; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-arrow-left"></i>
                <span>{{ __('Back to Payments') }}</span>
            </a>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0; font-family: monospace;">
                    {{ $payment->payment_number }}
                </h1>
                <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: rgba(16, 185, 129, 0.12); color: #10b981;">
                    <i class="fa-solid fa-check-circle"></i> {{ $payment->status }}
                </span>
            </div>
            <div style="color: var(--text-muted); font-size: 0.88rem; margin-top: 0.35rem;">
                {{ __('Date') }}: {{ $payment->payment_date?->format('d/m/Y') }} &bull; {{ __('Method') }}: {{ $payment->payment_method }}
            </div>
        </div>
    </div>

    <!-- Cards Row -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Amount Card -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                {{ __('Total Amount Collected') }}
            </div>
            <div style="font-size: 1.75rem; font-weight: 800; color: #10b981;">
                {{ number_format($payment->amount, 2) }} AMD
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem; border-top: 1px solid var(--border-color); padding-top: 0.5rem;">
                <span>{{ __('Allocated') }}: {{ number_format($payment->allocated_amount, 2) }}</span>
                <span>{{ __('Unallocated') }}: {{ number_format($payment->unallocated_amount, 2) }}</span>
            </div>
        </div>

        <!-- Customer Card -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                {{ __('Buyer Account') }}
            </div>
            <div style="font-size: 1.1rem; font-weight: 800; color: var(--text-main);">
                {{ $payment->customer?->name }}
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem; font-family: monospace;">
                TIN: {{ $payment->customer?->tin ?? '—' }}
            </div>
        </div>

        <!-- GL Entry -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                {{ __('General Ledger Double-Entry') }}
            </div>
            @if($payment->journalEntry)
                <div style="font-size: 1.1rem; font-weight: 800; color: #6366f1; font-family: monospace;">
                    {{ $payment->journalEntry->entry_number }}
                </div>
                <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem;">
                    {{ __('Balanced journal entry posted in Core Finance Ledger.') }}
                </div>
            @else
                <div style="font-size: 0.9rem; color: var(--text-muted);">
                    {{ __('No direct GL entry linked.') }}
                </div>
            @endif
        </div>
    </div>

    <!-- Allocations Table -->
    @if($payment->allocations->count() > 0)
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem;">
            <h2 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0 0 1rem 0;">
                {{ __('Payment Allocations against Sales Invoices') }}
            </h2>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                        <th style="padding: 0.65rem 0.75rem;">{{ __('Invoice #') }}</th>
                        <th style="padding: 0.65rem 0.75rem;">{{ __('Invoice Date') }}</th>
                        <th style="padding: 0.65rem 0.75rem;">{{ __('Allocated Amount') }}</th>
                        <th style="padding: 0.65rem 0.75rem;">{{ __('Invoice Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payment->allocations as $alloc)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.75rem; font-family: monospace; font-weight: 700;">
                                <a href="{{ route('admin.wholesale.sales.show', $alloc->sale) }}" style="color: #6366f1; text-decoration: none;">
                                    {{ $alloc->sale?->invoice_number }}
                                </a>
                            </td>
                            <td style="padding: 0.75rem; font-size: 0.85rem; color: var(--text-muted);">
                                {{ $alloc->sale?->sale_date?->format('d/m/Y') }}
                            </td>
                            <td style="padding: 0.75rem; font-weight: 800; color: #10b981;">
                                {{ number_format($alloc->amount, 2) }} AMD
                            </td>
                            <td style="padding: 0.75rem;">
                                <span style="font-size: 0.75rem; font-weight: 700; padding: 0.15rem 0.4rem; border-radius: 4px; background: rgba(16, 185, 129, 0.1); color: #10b981;">
                                    {{ $alloc->sale?->status }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection

