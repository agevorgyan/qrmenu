@php
    $targetLocale = $locale ?? app()->getLocale();
    $isBilingual = ($targetLocale === 'bilingual');
    $siteName = \App\Models\SystemSetting::getSiteName();
    $vendor = $order->vendor;
    $currency = $vendor?->currency ?? '֏';

    if ($isBilingual) {
        $orderTypeLabel = match ($order->type) {
            'dine_in' => 'Սրահում (Dine-in)' . ($order->table_number ? ' • Սեղան / Table #' . $order->table_number : ''),
            'takeaway' => 'Տանելու / Takeaway',
            'delivery' => 'Առաքում / Delivery',
            default => strtoupper($order->type),
        };
        $statusLabel = ($order->payment_status === 'paid') ? 'Վճարված է / Paid' : 'Չվճարված / Unpaid';
        $titleLabel = 'Պատվերի կտրոն / Order Receipt';
        $itemsHeader = 'Պատվիրված ուտեստներ / Order Items';
        $subtotalLabel = 'Միջանկյալ / Subtotal';
        $serviceLabel = 'Սպասարկման վճար / Service Fee';
        $deliveryLabel = 'Առաքման վճար / Delivery Fee';
        $discountLabel = 'Զեղչ / Discount';
        $totalLabel = 'Ընդամենը / Total Amount';
        $viewOnlineBtn = 'Դիտել թվային կտրոնը / View Digital Receipt';
        $thanksLabel = 'Շնորհակալություն այցելության համար! / Thank you for your visit!';
    } elseif ($targetLocale === 'en') {
        $orderTypeLabel = match ($order->type) {
            'dine_in' => 'Dine-in' . ($order->table_number ? ' • Table #' . $order->table_number : ''),
            'takeaway' => 'Takeaway',
            'delivery' => 'Delivery',
            default => strtoupper($order->type),
        };
        $statusLabel = ($order->payment_status === 'paid') ? 'Paid' : 'Unpaid';
        $titleLabel = 'Order Receipt';
        $itemsHeader = 'Order Items';
        $subtotalLabel = 'Subtotal';
        $serviceLabel = 'Service Fee';
        $deliveryLabel = 'Delivery Fee';
        $discountLabel = 'Discount';
        $totalLabel = 'Total Amount';
        $viewOnlineBtn = 'View Digital Receipt';
        $thanksLabel = 'Thank you for your visit!';
    } elseif ($targetLocale === 'ru') {
        $orderTypeLabel = match ($order->type) {
            'dine_in' => 'В зале (Dine-in)' . ($order->table_number ? ' • Стол #' . $order->table_number : ''),
            'takeaway' => 'На вынос (Takeaway)',
            'delivery' => 'Доставка (Delivery)',
            default => strtoupper($order->type),
        };
        $statusLabel = ($order->payment_status === 'paid') ? 'Оплачено' : 'Не оплачено';
        $titleLabel = 'Чек заказа';
        $itemsHeader = 'Заказанные блюда';
        $subtotalLabel = 'Подытог';
        $serviceLabel = 'Сервисный сбор';
        $deliveryLabel = 'Доставка';
        $discountLabel = 'Скидка';
        $totalLabel = 'Итого';
        $viewOnlineBtn = 'Посмотреть электронный чек';
        $thanksLabel = 'Спасибо за ваш визит!';
    } else {
        $orderTypeLabel = match ($order->type) {
            'dine_in' => 'Սրահում (Dine-in)' . ($order->table_number ? ' • Սեղան #' . $order->table_number : ''),
            'takeaway' => 'Տանելու (Takeaway)',
            'delivery' => 'Առաքում (Delivery)',
            default => strtoupper($order->type),
        };
        $statusLabel = ($order->payment_status === 'paid') ? 'Վճարված է' : 'Չվճարված';
        $titleLabel = 'Պատվերի կտրոն';
        $itemsHeader = 'Պատվիրված ուտեստներ';
        $subtotalLabel = 'Միջանկյալ';
        $serviceLabel = 'Սպասարկման վճար';
        $deliveryLabel = 'Առաքման վճար';
        $discountLabel = 'Զեղչ';
        $totalLabel = 'Ընդամենը';
        $viewOnlineBtn = 'Դիտել թվային կտրոնը';
        $thanksLabel = 'Շնորհակալություն այցելության համար!';
    }

    $receiptUrl = $vendor ? route('client.menu', [
        'vendor_slug' => $vendor->slug,
        'active_order_num' => $order->order_number,
    ]) : url('/');
@endphp
<!DOCTYPE html>
<html lang="{{ $targetLocale === 'bilingual' ? 'hy' : $targetLocale }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $titleLabel }} #{{ $order->order_number }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; color: #1e293b; margin: 0; padding: 24px 12px; }
        .receipt-card { max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 18px; padding: 30px 26px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0,0,0,0.06); }
        .receipt-header { text-align: center; border-bottom: 2px dashed #cbd5e1; padding-bottom: 20px; margin-bottom: 20px; }
        .brand-logo { width: 56px; height: 56px; border-radius: 12px; object-fit: cover; margin-bottom: 8px; border: 2px solid #e2e8f0; }
        .brand-name { font-size: 20px; font-weight: 800; color: #0f172a; margin: 0; line-height: 1.2; }
        .badge-status { display: inline-block; background: <?= $order->payment_status === 'paid' ? '#dcfce7' : '#fef3c7' ?>; color: <?= $order->payment_status === 'paid' ? '#166534' : '#92400e' ?>; font-weight: 700; font-size: 12px; padding: 3px 10px; border-radius: 9999px; margin-top: 6px; }
        .order-meta-grid { display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #64748b; margin-top: 10px; }
        .item-row { display: flex; justify-content: space-between; align-items: flex-start; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 13.5px; }
        .item-name { font-weight: 700; color: #1e293b; }
        .item-variation { font-size: 11.5px; color: #64748b; margin-top: 2px; }
        .item-notes { font-size: 11px; color: #d97706; font-style: italic; margin-top: 2px; }
        .item-price { font-weight: 700; color: #0f172a; white-space: nowrap; margin-left: 12px; }
        .fee-row { display: flex; justify-content: space-between; font-size: 13px; color: #64748b; padding: 4px 0; }
        .total-box { margin-top: 14px; padding-top: 14px; border-top: 2px solid #0f172a; display: flex; justify-content: space-between; align-items: baseline; font-size: 18px; font-weight: 900; color: #0f172a; }
        .action-btn { display: block; text-align: center; background: #0f172a; color: #ffffff !important; text-decoration: none; font-weight: 700; font-size: 14px; padding: 12px 20px; border-radius: 12px; margin-top: 22px; }
        .receipt-footer { text-align: center; margin-top: 22px; font-size: 12px; color: #94a3b8; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="receipt-card">
        <!-- Header -->
        <div class="receipt-header">
            @if($vendor?->logo)
                <img src="{{ $vendor->logo }}" alt="{{ $vendor->name }}" class="brand-logo">
            @endif
            <h1 class="brand-name">{{ $vendor?->name ?? $siteName }}</h1>
            @if($order->location)
                <div style="font-size: 13px; color: #64748b; margin-top: 2px;">{{ $order->location->name }}</div>
            @endif
            <div>
                <span class="badge-status">{{ $statusLabel }}</span>
            </div>

            <div class="order-meta-grid" style="margin-top: 14px; padding: 8px 12px; background: #f8fafc; border-radius: 10px;">
                <div style="text-align: left;">
                    <div style="font-weight: 800; font-size: 14px; color: #0f172a;">#{{ $order->order_number }}</div>
                    <div style="font-size: 12px; color: #475569; margin-top: 2px;">{{ $orderTypeLabel }}</div>
                </div>
                <div style="text-align: right; font-size: 12px;">
                    <div>{{ $order->created_at ? $order->created_at->format('d.m.Y H:i') : '' }}</div>
                    <div style="color: #64748b; font-weight: 600; text-transform: uppercase;">{{ $order->payment_method ?? 'CASH' }}</div>
                </div>
            </div>
        </div>

        <!-- Customer details -->
        @if(!empty($order->customer_name) || !empty($order->customer_phone) || !empty($order->delivery_address))
            <div style="background: #f8fafc; border-radius: 10px; padding: 10px 14px; font-size: 12.5px; margin-bottom: 16px; border: 1px solid #e2e8f0;">
                @if(!empty($order->customer_name))
                    <div style="color: #334155;"><strong>{{ $isBilingual ? 'Հաճախորդ / Customer:' : ($targetLocale === 'en' ? 'Customer:' : 'Հաճախորդ:') }}</strong> {{ $order->customer_name }}</div>
                @endif
                @if(!empty($order->customer_phone))
                    <div style="color: #334155; margin-top: 2px;"><strong>{{ $isBilingual ? 'Հեռախոս / Phone:' : ($targetLocale === 'en' ? 'Phone:' : 'Հեռախոս:') }}</strong> {{ $order->customer_phone }}</div>
                @endif
                @if(!empty($order->delivery_address))
                    <div style="color: #334155; margin-top: 2px;"><strong>{{ $isBilingual ? 'Հասցե / Address:' : ($targetLocale === 'en' ? 'Address:' : 'Հասցե:') }}</strong> {{ $order->delivery_address }}</div>
                @endif
            </div>
        @endif

        <!-- Items -->
        <div style="font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.04em;">
            {{ $itemsHeader }}
        </div>

        <div style="margin-bottom: 16px;">
            @foreach($order->items as $item)
                <div class="item-row">
                    <div>
                        <div class="item-name"><span style="color: #64748b; font-weight: 700;">{{ (int)$item->quantity }}x</span> {{ $item->product_name }}</div>
                        @if($item->variation_name && !in_array($item->variation_name, ['Standard', 'Standard Portion']))
                            <div class="item-variation">{{ $item->variation_name }}</div>
                        @endif
                        @if(!empty($item->notes))
                            <div class="item-notes">* {{ $item->notes }}</div>
                        @endif
                    </div>
                    <div class="item-price">{{ number_format((float) $item->subtotal) }} {{ $currency }}</div>
                </div>
            @endforeach
        </div>

        <!-- Breakdown -->
        <div style="border-top: 1px dashed #cbd5e1; padding-top: 10px;">
            <div class="fee-row">
                <span>{{ $subtotalLabel }}</span>
                <span style="font-weight: 600; color: #1e293b;">{{ number_format((float) ($order->subtotal ?? $order->total_amount)) }} {{ $currency }}</span>
            </div>

            @if((float)$order->service_fee > 0)
                <div class="fee-row">
                    <span>{{ $serviceLabel }}</span>
                    <span style="font-weight: 600; color: #1e293b;">+{{ number_format((float) $order->service_fee) }} {{ $currency }}</span>
                </div>
            @endif

            @if((float)$order->delivery_fee > 0)
                <div class="fee-row">
                    <span>{{ $deliveryLabel }}</span>
                    <span style="font-weight: 600; color: #1e293b;">+{{ number_format((float) $order->delivery_fee) }} {{ $currency }}</span>
                </div>
            @endif

            @if((float)$order->birthday_discount_amount > 0)
                <div class="fee-row" style="color: #10b981;">
                    <span>{{ $discountLabel }}</span>
                    <span style="font-weight: 700;">-{{ number_format((float) $order->birthday_discount_amount) }} {{ $currency }}</span>
                </div>
            @endif

            <div class="total-box">
                <span>{{ $totalLabel }}</span>
                <span>{{ number_format((float) $order->total_amount) }} <span style="font-size: 14px; font-weight: 700;">{{ $currency }}</span></span>
            </div>
        </div>

        <!-- View Online Button -->
        <a href="{{ $receiptUrl }}" class="action-btn" target="_blank">
            {{ $viewOnlineBtn }} &rarr;
        </a>

        <!-- Footer -->
        <div class="receipt-footer">
            {{ $thanksLabel }}<br>
            {{ $siteName }} • {{ url('/') }}
        </div>
    </div>
</body>
</html>
