@extends('layouts.app')

@section('title', __('Wholesale Payments & Cash Receipts'))

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
                {{ __('Wholesale Payments & Collections') }}
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                {{ __('Cash receipts, bank transfers, invoice payment allocations, and automated General Ledger double-entry posting.') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <a href="{{ route('admin.wholesale.payments.create') }}" 
               style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #6366f1; color: #fff; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">
                <i class="fa-solid fa-money-bill-wave"></i>
                <span>{{ __('Record Payment') }}</span>
            </a>
        </div>
    </div>

    <!-- Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.02);">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-main); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">
                    <th style="padding: 0.85rem 1.25rem;">{{ __('Payment #') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Date') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Customer / Client') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Seller Company') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Method / Ref') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Amount') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('GL Entry') }}</th>
                    <th style="padding: 0.85rem 1.25rem; text-align: right;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $p)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 1rem 1.25rem; font-family: monospace; font-weight: 700;">
                            <a href="{{ route('admin.wholesale.payments.show', $p) }}" style="color: #6366f1; text-decoration: none;">
                                {{ $p->payment_number }}
                            </a>
                        </td>
                        <td style="padding: 1rem 1rem; font-size: 0.85rem; color: var(--text-muted);">
                            {{ $p->payment_date?->format('d/m/Y') }}
                        </td>
                        <td style="padding: 1rem 1rem; font-weight: 700; color: var(--text-main);">
                            {{ $p->customer?->name }}
                        </td>
                        <td style="padding: 1rem 1rem; font-size: 0.85rem;">
                            {{ $p->seller?->name }}
                        </td>
                        <td style="padding: 1rem 1rem; font-size: 0.85rem;">
                            <span style="font-weight: 700;">{{ $p->payment_method }}</span>
                            @if($p->reference_number)
                                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">{{ __('Ref:') }} {{ $p->reference_number }}</div>
                            @endif
                        </td>
                        <td style="padding: 1rem 1rem; font-weight: 800; color: #10b981;">
                            {{ number_format($p->amount, 2) }} AMD
                        </td>
                        <td style="padding: 1rem 1rem; font-family: monospace; font-size: 0.82rem;">
                            @if($p->journal_entry_id)
                                <span style="color: #6366f1; font-weight: 700;">{{ __('JE #') }}{{ $p->journal_entry_id }}</span>
                            @else
                                <span style="color: var(--text-muted);">—</span>
                            @endif
                        </td>
                        <td style="padding: 1rem 1.25rem; text-align: right;">
                            <a href="{{ route('admin.wholesale.payments.show', $p) }}" 
                               style="padding: 0.4rem 0.75rem; border-radius: 8px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.82rem; font-weight: 600; text-decoration: none;">
                                {{ __('Details') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="padding: 3rem 1rem; text-align: center; color: var(--text-muted);">
                            <i class="fa-solid fa-money-bill-transfer" style="font-size: 2rem; margin-bottom: 0.75rem; color: var(--border-color); display: block;"></i>
                            {{ __('No wholesale payments recorded yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1.5rem;">
        {{ $payments->links() }}
    </div>
</div>
@endsection

