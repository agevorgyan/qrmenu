@extends('layouts.app')

@section('title', __('Invoice') . ' #' . $invoice->invoice_number . ' - ' . $vendor->name)

@section('styles')
<style>
    .invoice-page-container {
        max-width: 900px;
        margin: 0 auto;
        padding-bottom: 3rem;
    }

    .invoice-top-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.75rem;
        flex-wrap: wrap;
    }

    .invoice-sheet {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        box-shadow: var(--shadow-card);
        overflow: hidden;
        position: relative;
    }

    .invoice-sheet-top-accent {
        height: 5px;
        background: linear-gradient(90deg, var(--primary) 0%, #10b981 100%);
    }

    .invoice-sheet-content {
        padding: clamp(1.5rem, 3.5vw, 2.75rem);
    }

    .invoice-sheet-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1.5rem;
        padding-bottom: 2rem;
        border-bottom: 1px solid var(--border-color);
        flex-wrap: wrap;
    }

    .invoice-brand-block {
        display: flex;
        align-items: center;
        gap: 0.9rem;
    }

    .invoice-brand-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: var(--nav-active);
        color: var(--primary);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        flex-shrink: 0;
        border: 1px solid var(--border-color);
    }

    .invoice-brand-title {
        font-family: 'Outfit', sans-serif;
        font-size: 1.45rem;
        font-weight: 800;
        color: var(--text-main);
        margin: 0;
        line-height: 1.2;
    }

    .invoice-brand-subtitle {
        font-size: 0.82rem;
        color: var(--text-muted);
        font-weight: 500;
        margin-top: 0.2rem;
    }

    .invoice-meta-block {
        text-align: right;
    }

    @media (max-width: 640px) {
        .invoice-meta-block {
            text-align: left;
            width: 100%;
        }
    }

    .invoice-number {
        font-family: 'Outfit', monospace;
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--text-main);
        letter-spacing: 0.02em;
        margin: 0.4rem 0 0.15rem 0;
    }

    .invoice-date {
        font-size: 0.82rem;
        color: var(--text-muted);
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .invoice-grid-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.25rem;
        margin: 1.75rem 0;
    }

    .invoice-info-card {
        background: var(--bg-card-hover);
        border: 1px solid var(--border-color-light);
        border-radius: 16px;
        padding: 1.35rem 1.45rem;
    }

    .invoice-info-card-title {
        font-size: 0.72rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--text-muted);
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.45rem;
    }

    .invoice-info-card-name {
        font-family: 'Outfit', sans-serif;
        font-size: 1.1rem;
        font-weight: 800;
        color: var(--text-main);
        margin: 0 0 0.35rem 0;
    }

    .invoice-info-card-text {
        font-size: 0.85rem;
        color: var(--text-muted);
        line-height: 1.55;
        display: flex;
        align-items: center;
        gap: 0.45rem;
        margin-top: 0.25rem;
    }

    .invoice-table-wrapper {
        border: 1px solid var(--border-color);
        border-radius: 16px;
        overflow: hidden;
        margin-top: 1.75rem;
        background: var(--bg-card);
    }

    .invoice-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.92rem;
    }

    .invoice-table th {
        background: var(--bg-card-hover);
        padding: 0.95rem 1.35rem;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        border-bottom: 1px solid var(--border-color);
    }

    .invoice-table td {
        padding: 1.25rem 1.35rem;
        color: var(--text-main);
        border-bottom: 1px solid var(--border-color-light);
        vertical-align: middle;
    }

    .invoice-table tbody tr:last-child td {
        border-bottom: none;
    }

    .invoice-table tfoot td {
        background: var(--bg-card-hover);
        padding: 1.25rem 1.35rem;
        border-top: 2px solid var(--border-color);
    }

    .bank-requisites-box {
        margin-top: 2rem;
        background: linear-gradient(145deg, rgba(16, 185, 129, 0.06) 0%, rgba(16, 185, 129, 0.02) 100%);
        border: 1px solid rgba(16, 185, 129, 0.28);
        border-left: 5px solid #10b981;
        border-radius: 16px;
        padding: 1.5rem 1.65rem;
    }

    .bank-requisites-title {
        font-family: 'Outfit', sans-serif;
        font-size: 0.95rem;
        font-weight: 800;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .bank-requisites-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.15rem 1.5rem;
    }

    .requisite-cell {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
    }

    .requisite-cell-label {
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .requisite-cell-value {
        font-size: 0.92rem;
        font-weight: 700;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 0.45rem;
    }

    .copy-pill-btn {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        color: var(--text-muted);
        cursor: pointer;
        padding: 0.25rem 0.5rem;
        border-radius: 8px;
        font-size: 0.75rem;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-weight: 600;
        transition: all 0.15s ease;
    }

    .copy-pill-btn:hover {
        color: var(--primary);
        border-color: var(--primary);
        background: var(--nav-hover);
        transform: translateY(-1px);
    }

    .invoice-footer-note {
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px dashed var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        font-size: 0.78rem;
        color: var(--text-subtle);
        flex-wrap: wrap;
    }

    /* Print Optimization Rules */
    @media print {
        @page {
            margin: 1.2cm;
            size: auto;
        }

        body {
            background: #ffffff !important;
            color: #0f172a !important;
            font-size: 10.5pt !important;
        }

        .no-print,
        .sidebar,
        .header-navbar,
        .sidebar-overlay,
        .subscription-top-banner {
            display: none !important;
        }

        .main-wrapper {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }

        .content-body {
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        .invoice-page-container {
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .invoice-sheet {
            border: 1px solid #cbd5e1 !important;
            box-shadow: none !important;
            border-radius: 8px !important;
            background: #ffffff !important;
        }

        .invoice-sheet-content {
            padding: 1.5rem !important;
        }

        .invoice-sheet-header {
            border-bottom: 1px solid #cbd5e1 !important;
        }

        .invoice-brand-title,
        .invoice-number,
        .invoice-info-card-name,
        .bank-requisites-title,
        .invoice-table td,
        .requisite-cell-value {
            color: #0f172a !important;
        }

        .invoice-brand-subtitle,
        .invoice-date,
        .invoice-info-card-title,
        .invoice-info-card-text,
        .invoice-table th,
        .requisite-cell-label,
        .invoice-footer-note {
            color: #475569 !important;
        }

        .invoice-info-card {
            background: #f8fafc !important;
            border: 1px solid #e2e8f0 !important;
        }

        .invoice-table-wrapper {
            border: 1px solid #cbd5e1 !important;
        }

        .invoice-table th,
        .invoice-table tfoot td {
            background: #f8fafc !important;
            border-color: #cbd5e1 !important;
            color: #0f172a !important;
        }

        .invoice-table td {
            border-color: #e2e8f0 !important;
        }

        .bank-requisites-box {
            background: #f8fafc !important;
            border: 1px solid #cbd5e1 !important;
            border-left: 4px solid #059669 !important;
        }

        .copy-pill-btn {
            display: none !important;
        }

        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>
@endsection

@section('content')
@php
    $statusLower = strtolower($invoice->status ?? 'pending');
    $isPaid = in_array($statusLower, ['completed', 'paid'], true);
    $isFailed = in_array($statusLower, ['failed', 'cancelled', 'rejected'], true);

    $methodCode = strtolower($invoice->payment_method ?? 'arca');
    $methodNames = [
        'bank_transfer' => __('Bank Transfer'),
        'arca'          => 'ArCa / Card Payment',
        'idram'         => 'Idram',
        'telcell'       => 'Telcell Wallet',
        'manual'        => __('Manual / Admin'),
        'free'          => __('Free Trial'),
    ];
    $methodLabel = $methodNames[$methodCode] ?? strtoupper($invoice->payment_method ?? '—');

    $methodIcons = [
        'bank_transfer' => 'fa-solid fa-building-columns',
        'arca'          => 'fa-solid fa-credit-card',
        'idram'         => 'fa-solid fa-wallet',
        'telcell'       => 'fa-solid fa-mobile-screen',
        'manual'        => 'fa-solid fa-user-shield',
        'free'          => 'fa-solid fa-gift',
    ];
    $methodIcon = $methodIcons[$methodCode] ?? 'fa-solid fa-receipt';

    $bankConfig = \App\Models\SystemSetting::getPaymentGatewaySettings()['bank_transfer'] ?? [];
@endphp

<div class="invoice-page-container">
    <!-- Top Action Breadcrumbs (Hidden on print) -->
    <div class="invoice-top-actions no-print">
        <a href="{{ route('admin.subscription') }}" class="btn btn-secondary" style="border-radius: 10px; font-weight: 600; font-size: 0.85rem; padding: 0.55rem 1rem; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none;">
            <i class="fa-solid fa-arrow-left"></i>
            <span>{{ __('Back to Subscriptions') }}</span>
        </a>

        <div style="display: flex; align-items: center; gap: 0.65rem;">
            <button type="button" onclick="window.print()" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; font-size: 0.85rem; padding: 0.55rem 1.25rem; display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; box-shadow: 0 4px 14px var(--primary-glow);">
                <i class="fa-solid fa-print"></i>
                <span>{{ __('Print / Save PDF') }}</span>
            </button>
        </div>
    </div>

    <!-- Printable Invoice Sheet -->
    <div class="invoice-sheet">
        <div class="invoice-sheet-top-accent"></div>

        <div class="invoice-sheet-content">
            <!-- Header Block -->
            <div class="invoice-sheet-header">
                <div class="invoice-brand-block">
                    <div class="invoice-brand-icon">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                    </div>
                    <div>
                        <h1 class="invoice-brand-title">{{ \App\Models\SystemSetting::getSiteName() }}</h1>
                        <div class="invoice-brand-subtitle">{{ __('Subscription & Billing Invoice') }}</div>
                    </div>
                </div>

                <div class="invoice-meta-block">
                    <div>
                        @if($isPaid)
                            <span class="badge badge-emerald" style="border-radius: 8px; font-size: 0.82rem; font-weight: 700; padding: 0.35rem 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>{{ __('Paid') }}</span>
                            </span>
                        @elseif($isFailed)
                            <span class="badge badge-rose" style="border-radius: 8px; font-size: 0.82rem; font-weight: 700; padding: 0.35rem 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                                <i class="fa-solid fa-circle-xmark"></i>
                                <span>{{ strtoupper($invoice->status) }}</span>
                            </span>
                        @else
                            <span class="badge badge-amber" style="border-radius: 8px; font-size: 0.82rem; font-weight: 700; padding: 0.35rem 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                                <i class="fa-solid fa-clock"></i>
                                <span>{{ __('Pending') }}</span>
                            </span>
                        @endif
                    </div>
                    <div class="invoice-number">{{ $invoice->invoice_number }}</div>
                    <div class="invoice-date">
                        <i class="fa-regular fa-calendar-days"></i>
                        <span>{{ $invoice->created_at?->format('d.m.Y H:i') }}</span>
                    </div>
                </div>
            </div>

            <!-- Parties / Billing Details Grid -->
            <div class="invoice-grid-details">
                <!-- Billed To -->
                <div class="invoice-info-card">
                    <div class="invoice-info-card-title">
                        <i class="fa-solid fa-store" style="color: var(--primary);"></i>
                        <span>{{ __('Billed To') }}</span>
                    </div>
                    <div class="invoice-info-card-name">{{ $vendor->name }}</div>
                    @if($vendor->email)
                        <div class="invoice-info-card-text">
                            <i class="fa-regular fa-envelope" style="width: 14px; color: var(--text-subtle);"></i>
                            <span>{{ $vendor->email }}</span>
                        </div>
                    @endif
                    @if($vendor->phone)
                        <div class="invoice-info-card-text">
                            <i class="fa-solid fa-phone" style="width: 14px; color: var(--text-subtle);"></i>
                            <span>{{ $vendor->phone }}</span>
                        </div>
                    @endif
                    @if($vendor->tax_id)
                        <div class="invoice-info-card-text">
                            <i class="fa-solid fa-id-card" style="width: 14px; color: var(--text-subtle);"></i>
                            <span><strong>{{ __('TIN (ՀՎՀՀ):') }}</strong> {{ $vendor->tax_id }}</span>
                        </div>
                    @endif
                    @if($vendor->legal_address || $vendor->operating_address)
                        <div class="invoice-info-card-text">
                            <i class="fa-solid fa-location-dot" style="width: 14px; color: var(--text-subtle);"></i>
                            <span>{{ $vendor->legal_address ?: $vendor->operating_address }}</span>
                        </div>
                    @endif
                </div>

                <!-- Payment Details -->
                <div class="invoice-info-card">
                    <div class="invoice-info-card-title">
                        <i class="fa-solid fa-credit-card" style="color: var(--primary);"></i>
                        <span>{{ __('Payment Method') }}</span>
                    </div>
                    <div class="invoice-info-card-name" style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="{{ $methodIcon }}" style="font-size: 1rem; color: var(--primary);"></i>
                        <span>{{ $methodLabel }}</span>
                    </div>
                    @if($invoice->latestPaymentAttempt?->merchant_reference)
                        <div class="invoice-info-card-text" style="font-family: monospace;">
                            <i class="fa-solid fa-hashtag" style="width: 14px; color: var(--text-subtle);"></i>
                            <span>Ref: {{ $invoice->latestPaymentAttempt->merchant_reference }}</span>
                        </div>
                    @endif
                    <div class="invoice-info-card-text">
                        <i class="fa-regular fa-clock" style="width: 14px; color: var(--text-subtle);"></i>
                        <span>{{ __('Status:') }} <strong style="color: var(--text-main); text-transform: uppercase;">{{ $invoice->status }}</strong></span>
                    </div>
                </div>
            </div>

            <!-- Invoice Line Items Table -->
            <div class="invoice-table-wrapper">
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th style="text-align: left;">{{ __('Description') }}</th>
                            <th style="text-align: center;">{{ __('Period') }}</th>
                            <th style="text-align: right;">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.05rem; color: var(--text-main);">
                                    {{ $invoice->plan?->name ?? __('SaaS Subscription') }}
                                </div>
                                @if($invoice->notes)
                                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                                        {{ $invoice->notes }}
                                    </div>
                                @endif
                            </td>
                            <td style="text-align: center; color: var(--text-muted); font-size: 0.88rem; white-space: nowrap;">
                                {{ $invoice->period_start?->format('d.m.Y') ?? '—' }} &rarr; {{ $invoice->period_end?->format('d.m.Y') ?? '—' }}
                            </td>
                            <td style="text-align: right; font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.1rem; color: var(--text-main); white-space: nowrap;">
                                {{ number_format($invoice->amount, 0, '.', ' ') }} {{ $invoice->currency }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" style="text-align: right; font-family: 'Outfit', sans-serif; font-size: 1rem; color: var(--text-main);">
                                {{ __('Total Due') }}:
                            </td>
                            <td style="text-align: right; font-family: 'Outfit', sans-serif; font-size: 1.35rem; font-weight: 900; color: #10b981; white-space: nowrap;">
                                {{ number_format($invoice->amount, 0, '.', ' ') }} {{ $invoice->currency }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Bank Transfer Details (if bank_transfer or pending) -->
            @if($methodCode === 'bank_transfer' || $invoice->status === 'pending')
                @php
                    $companyName = $bankConfig['company_name'] ?? '«Մենյու ԷյԷմ» ՍՊԸ';
                    $bankName    = $bankConfig['bank_name'] ?? '«Ամերիաբանկ» ՓԲԸ';
                    $tin         = $bankConfig['tin'] ?? '02893072';
                    $accountNo   = $bankConfig['account_number'] ?? '1570055555550100';
                    $purposeText = __('Բաժանորդագրության վճարում (Հաշիվ :invoice)', ['invoice' => $invoice->invoice_number]);
                @endphp
                <div class="bank-requisites-box">
                    <div class="bank-requisites-title">
                        <i class="fa-solid fa-building-columns" style="color: #10b981;"></i>
                        <span>{{ __('Bank Transfer Requisites') }}</span>
                    </div>

                    <div class="bank-requisites-grid">
                        <div class="requisite-cell">
                            <span class="requisite-cell-label">{{ __('Beneficiary Company:') }}</span>
                            <span class="requisite-cell-value">{{ $companyName }}</span>
                        </div>

                        <div class="requisite-cell">
                            <span class="requisite-cell-label">{{ __('Tax ID (ՀՎՀՀ):') }}</span>
                            <span class="requisite-cell-value font-mono">{{ $tin }}</span>
                        </div>

                        <div class="requisite-cell">
                            <span class="requisite-cell-label">{{ __('Bank Name:') }}</span>
                            <span class="requisite-cell-value">{{ $bankName }}</span>
                        </div>

                        <div class="requisite-cell">
                            <span class="requisite-cell-label">{{ __('Account Number (AMD):') }}</span>
                            <span class="requisite-cell-value" style="color: #10b981; font-family: monospace; font-size: 1rem;">
                                <span>{{ $accountNo }}</span>
                                <button type="button" onclick="navigator.clipboard.writeText('{{ $accountNo }}'); const btn=this; btn.innerHTML='<i class=\'fa-solid fa-check\'></i>'; setTimeout(() => btn.innerHTML='<i class=\'fa-regular fa-copy\'></i>', 1800);" class="copy-pill-btn no-print" title="{{ __('Copy Account Number') }}">
                                    <i class="fa-regular fa-copy"></i>
                                </button>
                            </span>
                        </div>
                    </div>

                    <div style="margin-top: 1.15rem; padding-top: 1rem; border-top: 1px solid rgba(16, 185, 129, 0.2); font-size: 0.85rem; color: var(--text-main);">
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap;">
                            <div>
                                <span style="font-weight: 700; color: var(--text-muted); text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.04em; display: block; margin-bottom: 0.2rem;">
                                    {{ __('Payment Purpose / Նպատակ:') }}
                                </span>
                                <span style="font-weight: 600; color: var(--text-main);">{{ $purposeText }}</span>
                            </div>
                            <button type="button" onclick="navigator.clipboard.writeText('{{ addslashes($purposeText) }}'); const btn=this; btn.innerHTML='<i class=\'fa-solid fa-check\'></i> {{ __('Copied') }}'; setTimeout(() => btn.innerHTML='<i class=\'fa-regular fa-copy\'></i> {{ __('Copy') }}', 1800);" class="copy-pill-btn no-print" title="{{ __('Copy Payment Purpose') }}">
                                <i class="fa-regular fa-copy"></i>
                                <span>{{ __('Copy') }}</span>
                            </button>
                        </div>
                        @if(!empty($bankConfig['notes']))
                            <div style="margin-top: 0.5rem; font-size: 0.8rem; color: var(--text-muted); font-style: italic;">
                                {{ $bankConfig['notes'] }}
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Invoice Footer Note -->
            <div class="invoice-footer-note">
                <div>
                    <i class="fa-solid fa-shield-halved" style="color: #10b981; margin-right: 0.35rem;"></i>
                    <span>{{ __('This invoice is generated electronically and is valid without physical seal or signature.') }}</span>
                </div>
                <div>
                    <span>{{ \App\Models\SystemSetting::getSiteName() }} Platform &bull; {{ date('Y') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
