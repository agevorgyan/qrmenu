@extends('layouts.app')

@section('title', __('B2B Wholesale & Distribution Dashboard'))

@section('content')
<div class="content-wrapper">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 12px; background: rgba(99, 102, 241, 0.15); color: #6366f1; font-size: 1.25rem;">
                    <i class="fa-solid fa-boxes-packing"></i>
                </span>
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ __('B2B Wholesale & Distribution') }}
                </h1>
            </div>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">
                {{ __('Enterprise wholesale operations, multi-seller management, POS, logistics and Armenian PEK e-Invoicing.') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.wholesale.sellers.create') }}" 
               style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #6366f1; color: #fff; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">
                <i class="fa-solid fa-plus"></i>
                <span>{{ __('New Seller Company') }}</span>
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 12px; padding: 1rem 1.25rem; color: #10b981; font-weight: 600; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Key Metrics Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        <!-- Metric 1: Sellers -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Seller Companies') }}</span>
                <span style="width: 34px; height: 34px; border-radius: 10px; background: rgba(99, 102, 241, 0.15); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 0.95rem;">
                    <i class="fa-solid fa-building-columns"></i>
                </span>
            </div>
            <div style="font-size: 1.8rem; font-weight: 800; color: var(--text-main);">{{ $stats['sellers_count'] }}</div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                <a href="{{ route('admin.wholesale.sellers.index') }}" style="color: #6366f1; text-decoration: none; font-weight: 600;">{{ __('Manage Sellers & Tax Profiles →') }}</a>
            </div>
        </div>

        <!-- Metric 2: B2B Customers -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('B2B Customers') }}</span>
                <span style="width: 34px; height: 34px; border-radius: 10px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 0.95rem;">
                    <i class="fa-solid fa-users"></i>
                </span>
            </div>
            <div style="font-size: 1.8rem; font-weight: 800; color: var(--text-main);">{{ $stats['customers_count'] }}</div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Contract accounts & buyers') }}
            </div>
        </div>

        <!-- Metric 3: Wholesale Catalog -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Wholesale Catalog') }}</span>
                <span style="width: 34px; height: 34px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 0.95rem;">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </span>
            </div>
            <div style="font-size: 1.8rem; font-weight: 800; color: var(--text-main);">{{ $stats['products_count'] }}</div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Independent SKUs & Packaging') }}
            </div>
        </div>

        <!-- Metric 4: Monthly Sales -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Monthly Sales') }}</span>
                <span style="width: 34px; height: 34px; border-radius: 10px; background: rgba(14, 165, 233, 0.15); color: #0ea5e9; display: flex; align-items: center; justify-content: center; font-size: 0.95rem;">
                    <i class="fa-solid fa-chart-line"></i>
                </span>
            </div>
            <div style="font-size: 1.8rem; font-weight: 800; color: var(--text-main);">{{ number_format($stats['monthly_sales_total'], 0) }} ֏</div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Receivables: :amount ֏', ['amount' => number_format($stats['total_receivables'], 0)]) }}
            </div>
        </div>
    </div>

    <!-- Active Sellers Overview Panel -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div>
                <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ __('Registered Seller Companies') }}
                </h2>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">
                    {{ __('Each seller maintains independent tax regimes, bank accounts and supplies.') }}
                </div>
            </div>
            <a href="{{ route('admin.wholesale.sellers.index') }}" style="color: #6366f1; font-weight: 700; font-size: 0.85rem; text-decoration: none;">
                {{ __('View All Sellers →') }}
            </a>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
            @forelse($activeSellers as $seller)
                <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                            <h3 style="font-size: 1rem; font-weight: 800; color: var(--text-main); margin: 0;">{{ $seller->name }}</h3>
                            <span style="font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 9999px; {{ $seller->tax_regime->isVatPayer() ? 'background: rgba(16, 185, 129, 0.15); color: #10b981;' : 'background: rgba(99, 102, 241, 0.15); color: #6366f1;' }}">
                                {{ $seller->tax_regime->documentTypeName() }}
                            </span>
                        </div>
                        <div style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 0.25rem;">
                            {{ $seller->legal_name }}
                        </div>
                        <div style="font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">
                            {{ __('TIN (ՀՎՀՀ)') }}: {{ $seller->tin }} {{ $seller->vat_number ? '• '.$seller->vat_number : '' }}
                        </div>
                        <div style="font-size: 0.78rem; color: var(--text-muted);">
                            <i class="fa-solid fa-location-dot" style="color: var(--text-muted); margin-right: 0.35rem;"></i>
                            {{ $seller->default_supply_location ?: $seller->address }}
                        </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid var(--border-color);">
                        <span style="font-size: 0.75rem; color: var(--text-muted);">
                            {{ __('Series') }}: <strong style="color: var(--text-main);">{{ $seller->activeTaxProfile?->document_series ?? $seller->tax_regime->defaultDocumentSeries() }}</strong>
                        </span>
                        <a href="{{ route('admin.wholesale.sellers.show', $seller) }}" style="font-size: 0.8rem; color: #6366f1; font-weight: 700; text-decoration: none;">
                            {{ __('Manage Profile →') }}
                        </a>
                    </div>
                </div>
            @empty
                <div style="grid-column: 1 / -1; padding: 2.5rem; text-align: center; color: var(--text-muted); border: 2px dashed var(--border-color); border-radius: 12px;">
                    <i class="fa-solid fa-building-circle-exclamation" style="font-size: 2rem; margin-bottom: 0.75rem; opacity: 0.5;"></i>
                    <div style="font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">{{ __('No seller companies configured yet.') }}</div>
                    <p style="font-size: 0.85rem; margin-bottom: 1rem;">{{ __('You must register at least one legal seller company to initiate wholesale POS sales and generate PEK tax documents.') }}</p>
                    <a href="{{ route('admin.wholesale.sellers.create') }}" style="padding: 0.5rem 1.2rem; border-radius: 8px; background: #6366f1; color: #fff; font-weight: 700; text-decoration: none; font-size: 0.85rem;">
                        {{ __('Add First Seller Company') }}
                    </a>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

