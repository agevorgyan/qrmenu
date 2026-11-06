@extends('layouts.app')

@section('title', __('Waste Write-Off') . ' #' . $wasteLog->waste_number . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="max-width: 900px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ __('Waste Log') }} #{{ $wasteLog->waste_number }}
                </h1>
                <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; font-size: 0.8rem; text-transform: uppercase; font-weight: 700; padding: 0.4rem 0.8rem; border-radius: 8px;">
                    {{ str_replace('_', ' ', $wasteLog->reason) }}
                </span>
            </div>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
                {{ __('Recorded on') }} {{ $wasteLog->logged_at ? $wasteLog->logged_at->format('M d, Y H:i') : $wasteLog->created_at->format('M d, Y H:i') }}
            </p>
        </div>
        <a href="{{ route('admin.inventory.waste.index') }}" class="btn btn-outline-secondary" style="border-radius: 10px;">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Back to List') }}
        </a>
    </div>

    <!-- Main Detail Card -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 2rem; box-shadow: var(--shadow-card); margin-bottom: 2rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <!-- Item -->
            <div>
                <small style="text-transform: uppercase; font-size: 0.75rem; color: var(--text-muted); font-weight: 700; display: block; margin-bottom: 0.25rem;">{{ __('Item / Raw Material') }}</small>
                <div style="font-size: 1.15rem; font-weight: 700; color: var(--text-main);">{{ $wasteLog->ingredient->name }}</div>
                <small style="color: var(--text-muted); font-family: monospace;">SKU: {{ $wasteLog->ingredient->sku ?? 'N/A' }}</small>
            </div>

            <!-- Warehouse -->
            <div>
                <small style="text-transform: uppercase; font-size: 0.75rem; color: var(--text-muted); font-weight: 700; display: block; margin-bottom: 0.25rem;">{{ __('Warehouse') }}</small>
                <div style="font-size: 1.15rem; font-weight: 700; color: var(--text-main);">{{ $wasteLog->warehouse->name }}</div>
                <small style="color: var(--text-muted); font-family: monospace;">{{ $wasteLog->warehouse->code }}</small>
            </div>

            <!-- Quantity -->
            <div>
                <small style="text-transform: uppercase; font-size: 0.75rem; color: var(--text-muted); font-weight: 700; display: block; margin-bottom: 0.25rem;">{{ __('Wasted Quantity') }}</small>
                <div style="font-size: 1.25rem; font-weight: 800; color: #ef4444;">
                    {{ number_format((float) $wasteLog->quantity, 2) }} {{ $wasteLog->unit?->symbol ?? 'kg' }}
                </div>
            </div>

            <!-- Total Loss -->
            <div>
                <small style="text-transform: uppercase; font-size: 0.75rem; color: var(--text-muted); font-weight: 700; display: block; margin-bottom: 0.25rem;">{{ __('Financial Loss') }}</small>
                <div style="font-size: 1.25rem; font-weight: 800; color: #ef4444;">
                    {{ number_format((float) $wasteLog->total_cost, 2) }} {{ Auth::user()->vendor->currency }}
                </div>
                <small style="color: var(--text-muted);">{{ number_format((float) $wasteLog->unit_cost, 2) }} / {{ $wasteLog->unit?->symbol ?? 'kg' }}</small>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
            <!-- Batch -->
            <div>
                <small style="text-transform: uppercase; font-size: 0.75rem; color: var(--text-muted); font-weight: 700; display: block; margin-bottom: 0.25rem;">{{ __('Lot / Batch') }}</small>
                <div style="font-family: monospace; font-size: 1rem; color: var(--text-main);">
                    {{ $wasteLog->batch?->batch_number ?? __('Not assigned to specific lot') }}
                </div>
            </div>

            <!-- Action Taken -->
            <div>
                <small style="text-transform: uppercase; font-size: 0.75rem; color: var(--text-muted); font-weight: 700; display: block; margin-bottom: 0.25rem;">{{ __('Action Taken') }}</small>
                <div style="font-size: 1rem; color: var(--text-main); text-transform: capitalize;">
                    {{ str_replace('_', ' ', $wasteLog->action_taken) }}
                </div>
            </div>

            <!-- Logged By -->
            <div>
                <small style="text-transform: uppercase; font-size: 0.75rem; color: var(--text-muted); font-weight: 700; display: block; margin-bottom: 0.25rem;">{{ __('Logged By') }}</small>
                <div style="font-size: 1rem; color: var(--text-main);">
                    {{ $wasteLog->loggedBy?->name ?? 'System' }}
                </div>
            </div>
        </div>

        @if($wasteLog->notes)
            <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                <small style="text-transform: uppercase; font-size: 0.75rem; color: var(--text-muted); font-weight: 700; display: block; margin-bottom: 0.5rem;">{{ __('Notes & Circumstances') }}</small>
                <p style="color: var(--text-main); margin: 0; background: var(--bg-hover); padding: 1rem; border-radius: 10px; line-height: 1.5;">
                    {{ $wasteLog->notes }}
                </p>
            </div>
        @endif
    </div>

    <!-- Double-Entry Ledger Stock Depletion Impact -->
    @if($wasteLog->inventoryTransaction)
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);">
            <div style="padding: 1rem 1.5rem; background: var(--bg-hover); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-book-journal-whills" style="color: #6366f1;"></i> {{ __('Double-Entry Ledger Impact') }}
                </h3>
                <span style="font-family: monospace; font-size: 0.85rem; color: var(--text-muted);">
                    Ref: {{ $wasteLog->inventoryTransaction->reference_number }}
                </span>
            </div>

            <div class="table-responsive">
                <table class="table" style="margin: 0; vertical-align: middle;">
                    <thead style="background: var(--bg-hover); font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                        <tr>
                            <th style="padding: 0.75rem 1.5rem;">{{ __('Warehouse') }}</th>
                            <th style="padding: 0.75rem;">{{ __('Transaction Type') }}</th>
                            <th style="padding: 0.75rem; text-align: right;">{{ __('Quantity Change') }}</th>
                            <th style="padding: 0.75rem 1.5rem; text-align: right;">{{ __('Timestamp') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding: 1rem 1.5rem; font-weight: 600; color: var(--text-main);">
                                {{ $wasteLog->warehouse->name }}
                            </td>
                            <td style="padding: 1rem;">
                                <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; text-transform: uppercase; font-weight: 700;">
                                    {{ $wasteLog->inventoryTransaction->type }}
                                </span>
                            </td>
                            <td style="padding: 1rem; text-align: right; font-weight: 800; font-family: monospace; color: #ef4444;">
                                -{{ number_format((float) $wasteLog->quantity, 2) }} {{ $wasteLog->unit?->symbol ?? 'kg' }}
                            </td>
                            <td style="padding: 1rem 1.5rem; text-align: right; color: var(--text-muted); font-size: 0.85rem;">
                                {{ $wasteLog->inventoryTransaction->transacted_at ? $wasteLog->inventoryTransaction->transacted_at->format('M d, Y H:i:s') : 'N/A' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

