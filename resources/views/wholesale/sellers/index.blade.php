@extends('layouts.app')

@section('title', __('Seller Companies & Tax Profiles'))

@section('content')
<div class="content-wrapper">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 12px; background: rgba(99, 102, 241, 0.15); color: #6366f1; font-size: 1.25rem;">
                    <i class="fa-solid fa-building-columns"></i>
                </span>
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ __('Seller Companies & Tax Profiles') }}
                </h1>
            </div>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">
                {{ __('Manage tenant-level seller companies. Each sale, order, and PEK tax document is issued on behalf of a chosen seller entity.') }}
            </p>
        </div>

        <a href="{{ route('admin.wholesale.sellers.create') }}" 
           style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #6366f1; color: #fff; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">
            <i class="fa-solid fa-plus"></i>
            <span>{{ __('Add Seller Company') }}</span>
        </a>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 12px; padding: 1rem 1.25rem; color: #10b981; font-weight: 600; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Sellers List -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.88rem;">
            <thead>
                <tr style="background: var(--bg-main); border-bottom: 1px solid var(--border-color); color: var(--text-muted); text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.05em;">
                    <th style="padding: 1rem 1.25rem;">{{ __('Company Name') }}</th>
                    <th style="padding: 1rem 1rem;">{{ __('TIN (ՀՎՀՀ) & VAT') }}</th>
                    <th style="padding: 1rem 1rem;">{{ __('Tax Regime') }}</th>
                    <th style="padding: 1rem 1rem;">{{ __('Document Series') }}</th>
                    <th style="padding: 1rem 1rem;">{{ __('Default Bank Account') }}</th>
                    <th style="padding: 1rem 1rem;">{{ __('Status') }}</th>
                    <th style="padding: 1rem 1.25rem; text-align: right;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sellers as $seller)
                    <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.15s ease;">
                        <td style="padding: 1rem 1.25rem;">
                            <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">{{ $seller->name }}</div>
                            <div style="font-size: 0.78rem; color: var(--text-muted);">{{ $seller->legal_name }}</div>
                        </td>

                        <td style="padding: 1rem 1rem;">
                            <div style="font-weight: 700; color: var(--text-main); font-family: monospace;">{{ $seller->tin }}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $seller->vat_number ?: __('No VAT reg.') }}</div>
                        </td>

                        <td style="padding: 1rem 1rem;">
                            <span style="font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.6rem; border-radius: 9999px; {{ $seller->tax_regime->isVatPayer() ? 'background: rgba(16, 185, 129, 0.15); color: #10b981;' : 'background: rgba(99, 102, 241, 0.15); color: #6366f1;' }}">
                                {{ $seller->tax_regime->label() }}
                            </span>
                        </td>

                        <td style="padding: 1rem 1rem;">
                            <span style="font-weight: 800; font-size: 0.9rem; color: var(--text-main);">
                                {{ $seller->activeTaxProfile?->document_series ?? $seller->tax_regime->defaultDocumentSeries() }}
                            </span>
                            <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">
                                ({{ $seller->tax_regime->documentTypeName() }})
                            </span>
                        </td>

                        <td style="padding: 1rem 1rem;">
                            @if($seller->defaultBankAccount)
                                <div style="font-weight: 600; color: var(--text-main); font-size: 0.82rem;">
                                    {{ $seller->defaultBankAccount->bank_name }}
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">
                                    {{ $seller->defaultBankAccount->account_number }} ({{ $seller->defaultBankAccount->currency }})
                                </div>
                            @else
                                <span style="color: var(--text-muted); font-size: 0.8rem;">&mdash;</span>
                            @endif
                        </td>

                        <td style="padding: 1rem 1rem;">
                            @if($seller->is_active)
                                <span style="color: #10b981; font-weight: 700; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span>
                                    {{ __('Active') }}
                                </span>
                            @else
                                <span style="color: var(--text-muted); font-weight: 600; font-size: 0.75rem;">
                                    {{ __('Inactive') }}
                                </span>
                            @endif
                        </td>

                        <td style="padding: 1rem 1.25rem; text-align: right;">
                            <div style="display: inline-flex; gap: 0.4rem; align-items: center;">
                                <a href="{{ route('admin.wholesale.sellers.show', $seller) }}" 
                                   style="padding: 0.4rem 0.75rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; background: var(--bg-main); color: var(--text-main); border: 1px solid var(--border-color); text-decoration: none;"
                                   title="{{ __('View Profile & Tax History') }}">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <a href="{{ route('admin.wholesale.sellers.edit', $seller) }}" 
                                   style="padding: 0.4rem 0.75rem; border-radius: 8px; font-size: 0.78rem; font-weight: 700; background: #6366f1; color: #fff; border: none; text-decoration: none;"
                                   title="{{ __('Edit Seller') }}">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                            <i class="fa-solid fa-building-columns" style="font-size: 2rem; margin-bottom: 0.75rem; opacity: 0.5;"></i>
                            <div>{{ __('No seller companies found.') }}</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

