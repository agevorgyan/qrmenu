@extends('layouts.app')

@section('title', __('Tax Document: ').$taxDocument->series.$taxDocument->document_number)

@section('content')
<div class="content-wrapper">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="{{ route('admin.wholesale.tax_documents.index') }}" style="color: #6366f1; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem; margin-bottom: 0.5rem;">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Tax Documents') }}
            </a>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0; font-family: monospace;">
                    {{ $taxDocument->series }}{{ $taxDocument->document_number }}
                </h1>
                @if($taxDocument->isInvoice())
                    <span style="font-size: 0.8rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: 6px; background: rgba(59, 130, 246, 0.12); color: #2563eb;">
                        {{ __('Հարկային հաշիվ (VAT Invoice)') }}
                    </span>
                @else
                    <span style="font-size: 0.8rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: 6px; background: rgba(245, 158, 11, 0.12); color: #d97706;">
                        {{ __('Հաշիվ վավերագիր (Accounting Document)') }}
                    </span>
                @endif

                @if($taxDocument->is_xsd_valid)
                    <span style="font-size: 0.8rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: 6px; background: rgba(16, 185, 129, 0.12); color: #059669; display: inline-flex; align-items: center; gap: 0.35rem;">
                        <i class="fa-solid fa-circle-check"></i> {{ __('W3C XSD Valid') }}
                    </span>
                @else
                    <span style="font-size: 0.8rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: 6px; background: rgba(239, 68, 68, 0.12); color: #dc2626; display: inline-flex; align-items: center; gap: 0.35rem;">
                        <i class="fa-solid fa-circle-exclamation"></i> {{ __('XSD Errors') }}
                    </span>
                @endif
            </div>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                {{ __('Issued on: ') }} {{ $taxDocument->issue_date->format('Y-m-d') }} &bull; {{ __('Supply Date: ') }} {{ $taxDocument->supply_date->format('Y-m-d') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <form method="POST" action="{{ route('admin.wholesale.tax_documents.revalidate', $taxDocument) }}">
                @csrf
                <button type="submit" style="padding: 0.65rem 1.25rem; border-radius: 10px; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-rotate"></i> {{ __('Revalidate XSD') }}
                </button>
            </form>
            <a href="{{ route('admin.wholesale.tax_documents.download_xml', $taxDocument) }}" 
               style="padding: 0.65rem 1.25rem; border-radius: 10px; background: #10b981; color: #fff; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);">
                <i class="fa-solid fa-download"></i>
                <span>{{ __('Download XML') }}</span>
            </a>
        </div>
    </div>

    @if(!$taxDocument->is_xsd_valid && !empty($taxDocument->xsd_validation_errors))
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; color: #b91c1c;">
            <div style="font-weight: 700; margin-bottom: 0.5rem;"><i class="fa-solid fa-triangle-exclamation"></i> {{ __('XSD Schema Validation Failures:') }}</div>
            <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.85rem; font-family: monospace;">
                @foreach($taxDocument->xsd_validation_errors as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Parties Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Seller -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.5rem;">
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.5rem;">
                {{ __('Supplier / Seller Company') }}
            </div>
            <div style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;">
                {{ $taxDocument->seller_snapshot['legal_name'] ?? '—' }}
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                <strong>{{ __('TIN (ՀՎՀՀ):') }}</strong> {{ $taxDocument->seller_snapshot['tin'] ?? '—' }}
                @if(!empty($taxDocument->seller_snapshot['vat_number']))
                    &bull; <strong>{{ __('VAT #:') }}</strong> {{ $taxDocument->seller_snapshot['vat_number'] }}
                @endif
            </div>
            <div style="font-size: 0.85rem; color: var(--text-main); margin-bottom: 0.5rem;">
                <i class="fa-solid fa-location-dot" style="color: #6366f1; width: 16px;"></i>
                {{ $taxDocument->seller_snapshot['legal_address'] ?? '—' }}
            </div>
            @if(!empty($taxDocument->seller_snapshot['bank_accounts'][0]))
                <div style="font-size: 0.85rem; color: var(--text-muted); background: var(--bg-main); padding: 0.5rem 0.75rem; border-radius: 8px;">
                    <strong>{{ $taxDocument->seller_snapshot['bank_accounts'][0]['bank_name'] }}:</strong>
                    <span style="font-family: monospace;">{{ $taxDocument->seller_snapshot['bank_accounts'][0]['account_number'] }}</span>
                </div>
            @endif
        </div>

        <!-- Buyer -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.5rem;">
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.5rem;">
                {{ __('Customer / Corporate Buyer') }}
            </div>
            <div style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;">
                {{ $taxDocument->customer_snapshot['legal_name'] ?? '—' }}
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                <strong>{{ __('TIN (ՀՎՀՀ):') }}</strong> {{ $taxDocument->customer_snapshot['tax_number'] ?? '—' }}
                @if(!empty($taxDocument->customer_snapshot['vat_number']))
                    &bull; <strong>{{ __('VAT #:') }}</strong> {{ $taxDocument->customer_snapshot['vat_number'] }}
                @endif
            </div>
            <div style="font-size: 0.85rem; color: var(--text-main); margin-bottom: 0.5rem;">
                <i class="fa-solid fa-building" style="color: #6366f1; width: 16px;"></i>
                {{ $taxDocument->customer_snapshot['legal_address'] ?? '—' }}
            </div>
            <div style="font-size: 0.85rem; color: var(--text-main);">
                <i class="fa-solid fa-truck" style="color: #10b981; width: 16px;"></i>
                {{ $taxDocument->customer_snapshot['delivery_address'] ?? '—' }}
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; margin-bottom: 2rem;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); font-weight: 700;">
            {{ __('Document Line Items & ATGAA Classifications') }}
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border-color); background: var(--bg-main); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                    <th style="padding: 0.75rem 1rem;">#</th>
                    <th style="padding: 0.75rem 1rem;">{{ __('Description') }}</th>
                    <th style="padding: 0.75rem 1rem;">{{ __('ATGAA (ԱՏԳ ԱԱ)') }}</th>
                    <th style="padding: 0.75rem 1rem;">{{ __('Unit') }}</th>
                    <th style="padding: 0.75rem 1rem;">{{ __('Qty') }}</th>
                    <th style="padding: 0.75rem 1rem;">{{ __('Unit Price') }}</th>
                    <th style="padding: 0.75rem 1rem;">{{ __('Taxable Base') }}</th>
                    @if($taxDocument->isInvoice())
                        <th style="padding: 0.75rem 1rem;">{{ __('VAT Rate') }}</th>
                        <th style="padding: 0.75rem 1rem;">{{ __('VAT') }}</th>
                    @else
                        <th style="padding: 0.75rem 1rem;">{{ __('Deal Type') }}</th>
                    @endif
                    <th style="padding: 0.75rem 1.5rem; text-align: right;">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($taxDocument->items as $item)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 0.85rem 1rem; font-family: monospace;">{{ $item->line_number }}</td>
                        <td style="padding: 0.85rem 1rem; font-weight: 600;">{{ $item->description }}</td>
                        <td style="padding: 0.85rem 1rem; font-family: monospace; font-weight: 700; color: #6366f1;">{{ $item->classifier_code }}</td>
                        <td style="padding: 0.85rem 1rem;">{{ $item->unit }}</td>
                        <td style="padding: 0.85rem 1rem;">{{ (float)$item->quantity }}</td>
                        <td style="padding: 0.85rem 1rem;">{{ number_format((float)$item->unit_price, 2) }}</td>
                        <td style="padding: 0.85rem 1rem;">{{ number_format((float)$item->taxable_price, 2) }}</td>
                        @if($taxDocument->isInvoice())
                            <td style="padding: 0.85rem 1rem;">{{ $item->vat_rate ? (float)$item->vat_rate.'%' : '—' }}</td>
                            <td style="padding: 0.85rem 1rem;">{{ number_format((float)$item->vat_amount, 2) }}</td>
                        @else
                            <td style="padding: 0.85rem 1rem; font-size: 0.75rem; font-weight: 700; color: #d97706;">{{ $item->deal_type }}</td>
                        @endif
                        <td style="padding: 0.85rem 1.5rem; text-align: right; font-weight: 700;">{{ number_format((float)$item->total_price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background: var(--bg-main); font-weight: 700;">
                    <td colspan="6" style="padding: 1rem; text-align: right;">{{ __('Totals:') }}</td>
                    <td style="padding: 1rem;">{{ number_format((float)$taxDocument->taxable_amount, 2) }}</td>
                    @if($taxDocument->isInvoice())
                        <td></td>
                        <td style="padding: 1rem;">{{ number_format((float)$taxDocument->vat_amount, 2) }}</td>
                    @else
                        <td></td>
                    @endif
                    <td style="padding: 1rem 1.5rem; text-align: right; color: #10b981; font-size: 1.05rem;">
                        {{ number_format((float)$taxDocument->total_amount, 2) }} {{ $taxDocument->currency }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Raw XML Inspector -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden;">
        <div style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <span style="font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-code" style="color: #6366f1;"></i> {{ __('Generated PEK XML Output') }}
            </span>
            <span style="font-size: 0.8rem; font-family: monospace; color: var(--text-muted);">
                {{ strlen($taxDocument->xml_content ?? '') }} {{ __('bytes') }}
            </span>
        </div>
        <div style="padding: 1.25rem 1.5rem; background: #0f172a; overflow-x: auto;">
            <pre style="margin: 0; font-family: 'Fira Code', monospace; font-size: 0.82rem; color: #e2e8f0; line-height: 1.5;"><code>{{ $taxDocument->xml_content }}</code></pre>
        </div>
    </div>
</div>
@endsection
