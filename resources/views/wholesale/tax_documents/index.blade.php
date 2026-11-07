@extends('layouts.app')

@section('title', __('Wholesale Official Tax Documents (PEK)'))

@section('content')
<div class="content-wrapper">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #10b981;">
                    {{ __('Armenian SRC e-Invoicing (ՊԵԿ)') }}
                </span>
            </div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
                {{ __('Official Tax Documents') }}
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                {{ __('Armenian Tax Service electronic invoices (Հարկային հաշիվ) and accounting documents (Հաշիվ վավերագիր) with W3C XSD schema validation.') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <a href="{{ route('admin.wholesale.pek.index') }}" 
               style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #6366f1; color: #fff; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">
                <i class="fa-solid fa-file-zipper"></i>
                <span>{{ __('Batch PEK Export') }}</span>
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap;">
        <form method="GET" action="{{ route('admin.wholesale.tax_documents.index') }}" style="display: flex; gap: 1rem; width: 100%; flex-wrap: wrap;">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by doc number or customer...') }}" 
                   style="flex: 1; min-width: 200px; padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main);">
            
            <select name="type" style="padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main);">
                <option value="">{{ __('All Document Types') }}</option>
                <option value="TAX_INVOICE" {{ request('type') === 'TAX_INVOICE' ? 'selected' : '' }}>{{ __('Հարկային հաշիվ (VAT)') }}</option>
                <option value="ACCOUNTING_DOCUMENT" {{ request('type') === 'ACCOUNTING_DOCUMENT' ? 'selected' : '' }}>{{ __('Հաշիվ վավերագիր (Non-VAT)') }}</option>
            </select>

            <select name="status" style="padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main);">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="DRAFT" {{ request('status') === 'DRAFT' ? 'selected' : '' }}>{{ __('Draft') }}</option>
                <option value="GENERATED" {{ request('status') === 'GENERATED' ? 'selected' : '' }}>{{ __('Generated') }}</option>
                <option value="EXPORTED" {{ request('status') === 'EXPORTED' ? 'selected' : '' }}>{{ __('Exported') }}</option>
            </select>

            <button type="submit" style="padding: 0.5rem 1rem; border-radius: 8px; background: #2563eb; color: #fff; font-weight: 600; border: none; cursor: pointer;">
                <i class="fa-solid fa-filter"></i> {{ __('Filter') }}
            </button>
        </form>
    </div>

    <!-- Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.02);">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-main); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">
                    <th style="padding: 0.85rem 1.25rem;">{{ __('Doc #') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Type') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Seller') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Buyer') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Taxable Base') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('VAT') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('Total') }}</th>
                    <th style="padding: 0.85rem 1rem;">{{ __('XSD Status') }}</th>
                    <th style="padding: 0.85rem 1.25rem; text-align: right;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($documents as $doc)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 1rem 1.25rem; font-family: monospace; font-weight: 700;">
                            <a href="{{ route('admin.wholesale.tax_documents.show', $doc) }}" style="color: #6366f1; text-decoration: none;">
                                {{ $doc->series }}{{ $doc->document_number }}
                            </a>
                        </td>
                        <td style="padding: 1rem;">
                            @if($doc->isInvoice())
                                <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 6px; background: rgba(59, 130, 246, 0.12); color: #2563eb;">
                                    {{ __('Հարկային հաշիվ') }}
                                </span>
                            @else
                                <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 6px; background: rgba(245, 158, 11, 0.12); color: #d97706;">
                                    {{ __('Հաշիվ վավերագիր') }}
                                </span>
                            @endif
                        </td>
                        <td style="padding: 1rem;">{{ $doc->seller->legal_name ?? '—' }}</td>
                        <td style="padding: 1rem; font-weight: 600;">{{ $doc->customer->company_name ?? '—' }}</td>
                        <td style="padding: 1rem;">{{ number_format((float)$doc->taxable_amount, 2) }} {{ $doc->currency }}</td>
                        <td style="padding: 1rem;">{{ number_format((float)$doc->vat_amount, 2) }} {{ $doc->currency }}</td>
                        <td style="padding: 1rem; font-weight: 700;">{{ number_format((float)$doc->total_amount, 2) }} {{ $doc->currency }}</td>
                        <td style="padding: 1rem;">
                            @if($doc->is_xsd_valid)
                                <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 6px; background: rgba(16, 185, 129, 0.12); color: #059669; display: inline-flex; align-items: center; gap: 0.25rem;">
                                    <i class="fa-solid fa-circle-check"></i> {{ __('XSD Valid') }}
                                </span>
                            @else
                                <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 6px; background: rgba(239, 68, 68, 0.12); color: #dc2626; display: inline-flex; align-items: center; gap: 0.25rem;">
                                    <i class="fa-solid fa-circle-exclamation"></i> {{ __('Invalid') }}
                                </span>
                            @endif
                        </td>
                        <td style="padding: 1rem 1.25rem; text-align: right;">
                            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                <a href="{{ route('admin.wholesale.tax_documents.show', $doc) }}" 
                                   style="padding: 0.35rem 0.65rem; border-radius: 6px; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); text-decoration: none; font-size: 0.8rem; font-weight: 600;">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <a href="{{ route('admin.wholesale.tax_documents.download_xml', $doc) }}" 
                                   style="padding: 0.35rem 0.65rem; border-radius: 6px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #059669; text-decoration: none; font-size: 0.8rem; font-weight: 600;">
                                    <i class="fa-solid fa-file-code"></i> XML
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                            <i class="fa-solid fa-file-invoice" style="font-size: 2rem; margin-bottom: 0.75rem; opacity: 0.4; display: block;"></i>
                            <div style="font-weight: 600; font-size: 1rem;">{{ __('No tax documents found') }}</div>
                            <div style="font-size: 0.85rem; margin-top: 0.25rem;">{{ __('Tax documents are generated automatically or manually from confirmed Wholesale Sales.') }}</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($documents->hasPages())
            <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color);">
                {{ $documents->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
