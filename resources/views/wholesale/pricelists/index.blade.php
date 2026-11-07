@extends('layouts.app')

@section('title', __('Wholesale Price Lists & Contracts'))

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
                {{ __('Price Lists & Contracts') }}
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                {{ __('Manage customer tier pricing matrices, seasonal catalogs, and contracted prices.') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <a href="{{ route('admin.wholesale.pricelists.create') }}" 
               style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #6366f1; color: #fff; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">
                <i class="fa-solid fa-plus"></i>
                <span>{{ __('New Price List') }}</span>
            </a>
        </div>
    </div>

    <!-- Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.02);">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-main); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">
                    <th style="padding: 0.85rem 1.25rem;">{{ __('Price List Name') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Code') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Currency') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Products Count') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Effective Dates') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Status') }}</th>
                    <th style="padding: 0.85rem 1.25rem; text-align: right;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($priceLists as $pl)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-main);">
                            <a href="{{ route('admin.wholesale.pricelists.show', $pl) }}" style="color: inherit; text-decoration: none;">
                                {{ $pl->name }}
                            </a>
                        </td>
                        <td style="padding: 1rem 1rem; font-family: monospace; font-weight: 700; color: #6366f1;">
                            {{ $pl->code }}
                        </td>
                        <td style="padding: 1rem 1rem;">
                            {{ $pl->currency }}
                        </td>
                        <td style="padding: 1rem 1rem; font-weight: 700;">
                            {{ $pl->items_count }} {{ __('items') }}
                        </td>
                        <td style="padding: 1rem 1rem; font-size: 0.82rem; color: var(--text-muted);">
                            {{ $pl->effective_from?->format('d/m/Y') ?? 'Immediate' }} &mdash; {{ $pl->effective_to?->format('d/m/Y') ?? 'Open-ended' }}
                        </td>
                        <td style="padding: 1rem 1rem;">
                            @if($pl->isCurrentlyEffective())
                                <span style="font-size: 0.75rem; font-weight: 700; color: #10b981;">{{ __('Active') }}</span>
                            @else
                                <span style="font-size: 0.75rem; font-weight: 700; color: #ef4444;">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td style="padding: 1rem 1.25rem; text-align: right;">
                            <a href="{{ route('admin.wholesale.pricelists.show', $pl) }}" 
                               style="padding: 0.4rem 0.75rem; border-radius: 8px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); font-size: 0.82rem; font-weight: 600; text-decoration: none;">
                                {{ __('Manage Items') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding: 3rem 1rem; text-align: center; color: var(--text-muted);">
                            <i class="fa-solid fa-tags" style="font-size: 2rem; margin-bottom: 0.75rem; color: var(--border-color); display: block;"></i>
                            {{ __('No custom price lists found. Create a price list to configure tier or contract rates.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1.5rem;">
        {{ $priceLists->links() }}
    </div>
</div>
@endsection

