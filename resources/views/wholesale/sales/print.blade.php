<!DOCTYPE html>
<html lang="hy">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Wholesale Invoice') }} - {{ $sale->invoice_number }}</title>
    <style>
        body {
            font-family: Arial, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 20px;
            color: #111;
            font-size: 13px;
            line-height: 1.4;
        }
        .header {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .title {
            font-size: 20px;
            font-weight: bold;
            margin: 0 0 5px 0;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .meta-table td {
            width: 50%;
            vertical-align: top;
            padding: 5px;
        }
        .box {
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 10px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th, .items-table td {
            border: 1px solid #333;
            padding: 6px 8px;
            text-align: left;
        }
        .items-table th {
            background-color: #f2f2f2;
            font-size: 11px;
            text-transform: uppercase;
        }
        .text-right {
            text-align: right;
        }
        .totals-table {
            width: 300px;
            margin-left: auto;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .totals-table td {
            padding: 4px 8px;
        }
        .grand-total {
            font-size: 15px;
            font-weight: bold;
            border-top: 2px solid #000;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 20px;
        }
        .signature-line {
            width: 200px;
            border-bottom: 1px solid #000;
            margin-top: 30px;
        }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #6366f1; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">
            {{ __('Print Document') }}
        </button>
    </div>

    <div class="header">
        <div>
            <div class="title">{{ __('ՀԱՇԻՎ-ԱՊՐԱՆՔԱԳԻՐ') }}</div>
            <div>{{ __('Wholesale Commercial Invoice') }}</div>
        </div>
        <div class="text-right">
            <div style="font-size: 16px; font-weight: bold; font-family: monospace;">{{ $sale->invoice_number }}</div>
            <div>{{ __('Ամսաթիվ / Date') }}: {{ $sale->sale_date?->format('d/m/Y') }}</div>
        </div>
    </div>

    <table class="meta-table">
        <tr>
            <td>
                <div class="box">
                    <strong>{{ __('ՄԱՏԱԿԱՐԱՐ / SUPPLIER') }}</strong><br>
                    <strong>{{ $sale->seller?->legal_name ?? $sale->seller?->name }}</strong><br>
                    ՀՎՀՀ / TIN: <strong>{{ $sale->seller?->tin }}</strong><br>
                    Հասցե / Address: {{ $sale->seller?->address }}<br>
                    Հարկային ռեժիմ: {{ $sale->seller?->tax_regime->value }}<br>
                    @if($sale->seller?->defaultBankAccount)
                        Բանկ: {{ $sale->seller->defaultBankAccount->bank_name }}<br>
                        Հ/Հ: {{ $sale->seller->defaultBankAccount->account_number }}
                    @endif
                </div>
            </td>
            <td>
                <div class="box">
                    <strong>{{ __('ԳՆՈՐԴ / BUYER') }}</strong><br>
                    <strong>{{ $sale->customer?->legal_name ?? $sale->customer?->name }}</strong><br>
                    ՀՎՀՀ / TIN: <strong>{{ $sale->customer?->tin ?? '—' }}</strong><br>
                    Հասցե / Address: {{ $sale->customer?->defaultAddress?->full_address ?? '—' }}<br>
                    Հեռ. / Phone: {{ $sale->customer?->phone ?? '—' }}
                </div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 30px;">Հ/Հ</th>
                <th>Ապրանքի անվանում</th>
                <th>ԱՏԳ ԱԱ</th>
                <th>Չ/Մ</th>
                <th class="text-right">Քանակ</th>
                <th class="text-right">Գին</th>
                <th class="text-right">ԱԱՀ (20%)</th>
                <th class="text-right">Ընդամենը</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        <strong>{{ $item->product_name }}</strong><br>
                        <span style="font-size: 11px; color: #555;">SKU: {{ $item->sku }}</span>
                    </td>
                    <td>{{ $item->classifier_code }}</td>
                    <td>{{ $item->unit_code }}</td>
                    <td class="text-right">{{ (float)$item->quantity }}</td>
                    <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">{{ number_format($item->vat_amount, 2) }}</td>
                    <td class="text-right"><strong>{{ number_format($item->total_amount, 2) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td>Ենթագումար / Subtotal:</td>
            <td class="text-right">{{ number_format($sale->subtotal, 2) }} AMD</td>
        </tr>
        <tr>
            <td>ԱԱՀ (20%) / VAT:</td>
            <td class="text-right">{{ number_format($sale->tax_total, 2) }} AMD</td>
        </tr>
        <tr class="grand-total">
            <td>Ընդամենը / Total:</td>
            <td class="text-right">{{ number_format($sale->total_amount, 2) }} AMD</td>
        </tr>
    </table>

    <div class="signatures">
        <div>
            <div>Հանձնեց (Մատակարար):</div>
            <div class="signature-line"></div>
        </div>
        <div>
            <div>Ստացավ (Գնորդ):</div>
            <div class="signature-line"></div>
        </div>
    </div>
</body>
</html>

