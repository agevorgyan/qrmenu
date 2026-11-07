@extends('layouts.app')

@section('title', __('Return: :num', ['num' => $return->return_number]))

@section('content')
<div class="content-wrapper">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="{{ route('admin.wholesale.returns.index') }}" style="color: #6366f1; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-arrow-left"></i>
                <span>{{ __('Back to Returns') }}</span>
            </a>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0; font-family: monospace;">
                    {{ $return->return_number }}
                </h1>
                <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: rgba(16, 185, 129, 0.12); color: #10b981;">
                    <i class="fa-solid fa-check-circle"></i> {{ $return->status }}
                </span>
            </div>
            <div style="color: var(--text-muted); font-size: 0.88rem; margin-top: 0.35rem;">
                {{ __('Date') }}: {{ $return->return_date?->format('d/m/Y') }} &bull; {{ __('Reason') }}: {{ $return->reason }}
            </div>
        </div>
    </div>

    <!-- Cards Row -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Customer -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                {{ __('Returning Buyer') }}
            </div>
            <div style="font-size: 1.1rem; font-weight: 800; color: var(--text-main);">
                {{ $return->customer?->name }}
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem; font-family: monospace;">
                TIN: {{ $return->customer?->tin ?? '—' }}
            </div>
        </div>

        <!-- Action / Warehouse -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                {{ __('Restock Destination') }}
            </div>
            <div style="font-size: 1.1rem; font-weight: 800; color: var(--text-main);">
                {{ $return->warehouse?->name ?? __('No warehouse (Financial Credit)') }}
            </div>
            <div style="font-size: 0.82rem; color: #10b981; font-weight: 700; margin-top: 0.25rem;">
                {{ $return->action_type }}
            </div>
        </div>

        <!-- Total Credit Note -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                {{ __('Total Credited') }}
            </div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #ef4444;">
                -{{ number_format($return->total_return_amount, 2) }} AMD
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Credited to Customer AR Account') }}
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
                    <th style="padding: 0.85rem 1rem;">{{ __('Quantity') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Unit Credit Price') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Restocked to Warehouse') }}</th>
                    <th style="padding: 0.85rem 1.25rem; text-align: right;">{{ __('Line Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($return->items as $idx => $rItem)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 0.85rem 1.25rem; color: var(--text-muted); font-size: 0.8rem;">{{ $idx + 1 }}</td>
                        <td style="padding: 0.85rem 1rem;">
                            <div style="font-weight: 700; color: var(--text-main);">{{ $rItem->product?->name }}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">SKU: {{ $rItem->product?->sku }}</div>
                        </td>
                        <td style="padding: 0.85rem 1rem; font-weight: 700;">
                            {{ (float)$rItem->quantity }} {{ $rItem->product?->unit?->code }}
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            {{ number_format($rItem->unit_price, 2) }} AMD
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            @if($rItem->is_restocked)
                                <span style="color: #10b981; font-weight: 700;"><i class="fa-solid fa-check"></i> {{ __('Yes') }}</span>
                            @else
                                <span style="color: #ef4444; font-weight: 700;"><i class="fa-solid fa-ban"></i> {{ __('No (Waste)') }}</span>
                            @endif
                        </td>
                        <td style="padding: 0.85rem 1.25rem; text-align: right; font-weight: 800; color: #ef4444;">
                            -{{ number_format($rItem->total_amount, 2) }} AMD
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

