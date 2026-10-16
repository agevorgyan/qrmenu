<!DOCTYPE html>
<html lang="hy">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Պատվերի Կտրոն</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
        .card { max-width: 540px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 28px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { border-bottom: 2px dashed #e2e8f0; padding-bottom: 16px; margin-bottom: 20px; text-align: center; }
        .badge { display: inline-block; background: #dcfce7; color: #166534; font-weight: 700; font-size: 13px; padding: 4px 12px; border-radius: 9999px; margin-bottom: 8px; }
        h1 { font-size: 22px; font-weight: 800; color: #0f172a; margin: 4px 0; }
        .meta { font-size: 13px; color: #64748b; margin-top: 4px; }
        .item-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .item-name { font-weight: 600; color: #334155; }
        .item-variation { font-size: 12px; color: #64748b; }
        .item-price { font-weight: 700; color: #0f172a; }
        .total-box { margin-top: 20px; padding-top: 16px; border-top: 2px solid #0f172a; display: flex; justify-content: space-between; font-size: 18px; font-weight: 800; color: #0f172a; }
        .footer { text-align: center; margin-top: 24px; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
    @php
        $customTpl = app(\App\Services\EmailTemplateService::class)->render('order_receipt', [
            'customer_name' => $order->customer_name ?? 'Հաճախորդ',
            'vendor_name' => $order->vendor?->name ?? 'QRMenu',
            'order_number' => $order->order_number,
            'order_type' => $order->table_number ? ('Սեղան: ' . $order->table_number) : 'Takeaway',
            'total_amount' => number_format($order->total_amount) . ' ' . ($order->vendor?->currency ?? 'AMD'),
            'support_email' => 'support@menu.elab.am',
            'site_name' => \App\Models\SystemSetting::getSiteName(),
        ]);
    @endphp
    <div class="card">
        <div class="header">
            <span class="badge">✓ {{ $customTpl['cta_text'] ?? 'Պատվերն ընդունված է' }}</span>
            <h1>{{ $order->vendor?->name ?? 'QRMenu' }}</h1>
            <div class="meta">
                Պատվեր #<strong>{{ $order->order_number }}</strong> &bull; 
                {{ $order->table_number ? 'Սեղան: ' . $order->table_number : 'Takeaway' }}
            </div>
            @if($customTpl && !empty($customTpl['intro']))
                <p style="font-size: 13px; color: #475569; margin: 8px 0 0;">{!! nl2br(e($customTpl['intro'])) !!}</p>
            @endif
        </div>

        <div style="margin-bottom: 16px;">
            @foreach($order->items as $item)
                <div class="item-row">
                    <div>
                        <span class="item-name">{{ $item->quantity }}x {{ $item->product_name }}</span>
                        @if($item->variation_name && $item->variation_name !== 'Standard Portion')
                            <div class="item-variation">({{ $item->variation_name }})</div>
                        @endif
                    </div>
                    <div class="item-price">{{ number_format($item->subtotal) }} {{ $order->vendor?->currency ?? 'AMD' }}</div>
                </div>
            @endforeach
        </div>

        <div class="total-box">
            <span>Ընդհանուր գումար</span>
            <span style="color: #e11d48;">{{ number_format($order->total_amount) }} {{ $order->vendor?->currency ?? 'AMD' }}</span>
        </div>

        <div class="footer">
            {{ $customTpl['footer'] ?? 'Շնորհակալություն մեզ ընտրելու համար։' }}<br>
            {{ $order->created_at ? $order->created_at->format('Y-m-d H:i') : '' }}
        </div>
    </div>
</body>
</html>
