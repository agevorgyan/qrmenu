@extends('layouts.app')

@section('title', __('Wholesale Returns & Restocking'))

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
                {{ __('Wholesale Returns & RMA') }}
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                {{ __('Process customer returns, warehouse physical restocking, and accounts receivable credit adjustments.') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <a href="{{ route('admin.wholesale.returns.create') }}" 
               style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #6366f1; color: #fff; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">
                <i class="fa-solid fa-rotate-left"></i>
                <span>{{ __('New Return') }}</span>
            </a>
        </div>
    </div>

    <!-- Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.02);">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-main); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">
                    <th style="padding: 0.85rem 1.25rem;">{{ __('Return #') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Date') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Customer') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Reason') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Action') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Total Credit') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Status') }}</th>
                    <th style="padding: 0.85rem 1.25rem; text-align: right;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($returns as $r)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 1rem 1.25rem; font-family: monospace; font-weight: 700;">
                            <a href="{{ route('admin.wholesale.returns.show', $r) }}" style="color: #6366f1; text-decoration: none;">
                                {{ $r->return_number }}
                            </a>
                        </td>
                        <td style="padding: 1rem 1rem; font-size: 0.85rem; color: var(--text-muted);">
                            {{ $r->return_date?->format('d/m/Y') }}
                        </td>
                        <td style="padding: 1rem 1rem; font-weight: 700; color: var(--text-main);">
                            {{ $r->customer?->name }}
                        </td>
                        <td style="padding: 1rem 1rem;">
                            {{ $r->reason }}
                        </td>
                        <td style="padding: 1rem 1rem; font-weight: 600; font-size: 0.85rem;">
                            @if($r->action_type === 'RESTOCK')
                                <span style="color: #10b981;"><i class="fa-solid fa-boxes-packing"></i> {{ __('Restock into Warehouse') }}</span>
                            @else
                                <span style="color: #ef4444;"><i class="fa-solid fa-trash"></i> {{ __('Waste / Write-off') }}</span>
                            @endif
                        </td>
                        <td style="padding: 1rem 1rem; font-weight: 800; color: #ef4444;">
                            -{{ number_format($r->total_return_amount, 2) }} AMD
                        </td>
                        <td style="padding: 1rem 1rem;">
                            <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px; background: rgba(16, 185, 129, 0.1); color: #10b981;">
                                {{ $r->status }}
                            </span>
                        </td>
                        <td style="padding: 1rem 1.25rem; text-align: right;">
                            <a href="{{ route('admin.wholesale.returns.show', $r) }}" 
                               style="padding: 0.4rem 0.75rem; border-radius: 8px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.82rem; font-weight: 600; text-decoration: none;">
                                {{ __('Details') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="padding: 3rem 1rem; text-align: center; color: var(--text-muted);">
                            <i class="fa-solid fa-rotate-left" style="font-size: 2rem; margin-bottom: 0.75rem; color: var(--border-color); display: block;"></i>
                            {{ __('No wholesale returns processed yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1.5rem;">
        {{ $returns->links() }}
    </div>
</div>
@endsection

