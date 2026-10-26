<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class ThermalPrinterService
{
    /**
     * Dictionary of translations for receipt generation.
     */
    protected array $translations = [
        'hy' => [
            'order' => 'ՊԱՏՎԵՐ #',
            'date' => 'ԱՄՍԱԹԻՎ:',
            'table' => 'ՍԵՂԱՆ:',
            'type' => 'ՏԵՍԱԿ:',
            'dine_in' => 'Սրահում (Dine-in)',
            'takeaway' => 'Տանելու (Takeaway)',
            'delivery' => 'Առաքում (Delivery)',
            'payment' => 'ՎՃԱՐՈՒՄ:',
            'paid' => 'ՎՃԱՐՎԱԾ',
            'unpaid' => 'ՉՎՃԱՐՎԱԾ',
            'customer' => 'ՀԱՃԱԽՈՐԴ:',
            'phone' => 'ՀԵՌ․:',
            'address' => 'ՀԱՍՑԵ:',
            'item_header' => 'ԱՆՎԱՆՈՒՄ',
            'qty_header' => 'ՔԱՆ․',
            'price_header' => 'ԳԻՆ',
            'note' => 'Նշում:',
            'kitchen_notes' => 'ԽՈՀԱՆՈՑԻ ՆՇՈՒՄ:',
            'subtotal' => 'Միջանկյալ:',
            'service_fee' => 'Սպասարկում:',
            'delivery_fee' => 'Առաքում:',
            'discount' => 'Զեղչ:',
            'total' => 'ԸՆԴԱՄԵՆԸ:',
            'thank_you' => 'Շնորհակալություն այցելության համար!',
            'digital_receipt' => 'Թվային կտրոն',
        ],
        'en' => [
            'order' => 'ORDER #',
            'date' => 'DATE:',
            'table' => 'TABLE:',
            'type' => 'TYPE:',
            'dine_in' => 'Dine-in',
            'takeaway' => 'Takeaway',
            'delivery' => 'Delivery',
            'payment' => 'PAYMENT:',
            'paid' => 'PAID',
            'unpaid' => 'UNPAID',
            'customer' => 'CUSTOMER:',
            'phone' => 'PHONE:',
            'address' => 'ADDRESS:',
            'item_header' => 'ITEM',
            'qty_header' => 'QTY',
            'price_header' => 'PRICE',
            'note' => 'Note:',
            'kitchen_notes' => 'KITCHEN NOTE:',
            'subtotal' => 'Subtotal:',
            'service_fee' => 'Service Fee:',
            'delivery_fee' => 'Delivery Fee:',
            'discount' => 'Discount:',
            'total' => 'TOTAL:',
            'thank_you' => 'Thank you for your visit!',
            'digital_receipt' => 'Digital Receipt',
        ],
        'ru' => [
            'order' => 'ЗАКАЗ #',
            'date' => 'ДАТА:',
            'table' => 'СТОЛ:',
            'type' => 'ТИП:',
            'dine_in' => 'В зале (Dine-in)',
            'takeaway' => 'На вынос (Takeaway)',
            'delivery' => 'Доставка (Delivery)',
            'payment' => 'ОПЛАТА:',
            'paid' => 'ОПЛАЧЕНО',
            'unpaid' => 'НЕ ОПЛАЧЕНО',
            'customer' => 'КЛИЕНТ:',
            'phone' => 'ТЕЛ.:',
            'address' => 'АДРЕС:',
            'item_header' => 'НАИМЕНОВАНИЕ',
            'qty_header' => 'КОЛ.',
            'price_header' => 'ЦЕНА',
            'note' => 'Прим.:',
            'kitchen_notes' => 'ПРИМЕЧАНИЕ:',
            'subtotal' => 'Подытог:',
            'service_fee' => 'Обслуживание:',
            'delivery_fee' => 'Доставка:',
            'discount' => 'Скидка:',
            'total' => 'ИТОГО:',
            'thank_you' => 'Спасибо за ваш визит!',
            'digital_receipt' => 'Электронный чек',
        ],
        'bilingual' => [
            'order' => 'ՊԱՏՎԵՐ / ORDER #',
            'date' => 'ԱՄՍԱԹԻՎ / DATE:',
            'table' => 'ՍԵՂԱՆ / TABLE:',
            'type' => 'ՏԵՍԱԿ / TYPE:',
            'dine_in' => 'Սրահում (Dine-in)',
            'takeaway' => 'Տանելու (Takeaway)',
            'delivery' => 'Առաքում (Delivery)',
            'payment' => 'ՎՃԱՐՈՒՄ / PAYMENT:',
            'paid' => 'ՎՃԱՐՎԱԾ / PAID',
            'unpaid' => 'ՉՎՃԱՐՎԱԾ / UNPAID',
            'customer' => 'ՀԱՃԱԽՈՐԴ / CUSTOMER:',
            'phone' => 'ՀԵՌ / TEL:',
            'address' => 'ՀԱՍՑԵ / ADDRESS:',
            'item_header' => 'ԱՆՎԱՆՈՒՄ / ITEM',
            'qty_header' => 'ՔԱՆ',
            'price_header' => 'ԳԻՆ',
            'note' => 'Նշում / Note:',
            'kitchen_notes' => 'ԽՈՀԱՆՈՑԻ ՆՇՈՒՄ / NOTE:',
            'subtotal' => 'Միջանկյալ / Subtotal:',
            'service_fee' => 'Սպասարկում / Service:',
            'delivery_fee' => 'Առաքում / Delivery:',
            'discount' => 'Զեղչ / Discount:',
            'total' => 'ԸՆԴԱՄԵՆԸ / TOTAL:',
            'thank_you' => 'Շնորհակալություն այցելության համար! / Thank you!',
            'digital_receipt' => 'Թվային կտրոն / Digital Receipt',
        ],
    ];

    /**
     * Get translation dictionary for given language.
     */
    public function getDict(string $lang): array
    {
        return $this->translations[$lang] ?? $this->translations['hy'];
    }

    /**
     * Generate ESC/POS raw text representation of an order for 58mm or 80mm printer.
     */
    public function generateReceiptText(Order $order, ?string $paperWidth = null, array $overrideSettings = []): string
    {
        $order->loadMissing(['items', 'location', 'vendor']);
        $vendor = $order->vendor;
        $settings = array_merge($vendor->getThermalPrinterSettings(), $overrideSettings);

        $width = $paperWidth ?: ($settings['paper_width'] ?? '80mm');
        $cols = ($width === '58mm') ? 32 : 48;
        $lang = $settings['receipt_language'] ?? 'hy';
        $dict = $this->getDict($lang);

        $divider = str_repeat('-', $cols);
        $doubleDivider = str_repeat('=', $cols);

        $lines = [];
        $headerTitle = ! empty($settings['header_title']) ? $settings['header_title'] : $vendor->name;
        $lines[] = $this->centerText($headerTitle, $cols);

        if (! empty($settings['header_subtitle'])) {
            $lines[] = $this->centerText($settings['header_subtitle'], $cols);
        } elseif ($order->location) {
            $lines[] = $this->centerText($order->location->name, $cols);
        }

        $lines[] = $doubleDivider;

        // Order Meta
        $lines[] = $dict['order'].$order->order_number;
        $lines[] = $dict['date'].' '.$order->created_at->format('d.m.Y H:i');

        if (($settings['print_order_type'] ?? true) && ! empty($order->table_number)) {
            $lines[] = $dict['table'].' #'.$order->table_number;
        }

        if ($settings['print_order_type'] ?? true) {
            $typeLabel = match ($order->type) {
                'dine_in' => $dict['dine_in'],
                'takeaway' => $dict['takeaway'],
                'delivery' => $dict['delivery'],
                default => strtoupper($order->type),
            };
            $lines[] = $dict['type'].' '.$typeLabel;
        }

        if ($settings['print_payment_details'] ?? true) {
            $payStatus = ($order->payment_status === 'paid') ? $dict['paid'] : $dict['unpaid'];
            $lines[] = $dict['payment'].' '.strtoupper($order->payment_method ?? 'CASH')." ({$payStatus})";
        }

        // Customer Info
        if (($settings['print_customer_info'] ?? true) && (! empty($order->customer_name) || ! empty($order->customer_phone))) {
            $lines[] = $divider;
            if (! empty($order->customer_name)) {
                $lines[] = $dict['customer'].' '.$order->customer_name;
            }
            if (! empty($order->customer_phone)) {
                $lines[] = $dict['phone'].' '.$order->customer_phone;
            }
            if (! empty($order->delivery_address)) {
                $lines[] = $dict['address'].' '.$order->delivery_address;
            }
        }

        $lines[] = $doubleDivider;
        $lines[] = $this->formatRow($dict['item_header'], $dict['qty_header'], $dict['price_header'], $cols);
        $lines[] = $divider;

        // Order items
        foreach ($order->items as $item) {
            $itemTitle = $item->product_name;
            if (! empty($item->variation_name) && ! in_array($item->variation_name, ['Standard', 'Standard Portion'])) {
                $itemTitle .= " ({$item->variation_name})";
            }
            $qty = 'x'.$item->quantity;
            $price = ($settings['print_prices'] ?? true)
                ? number_format($item->subtotal).' '.$vendor->currency
                : '';

            $lines[] = $this->formatRow($itemTitle, $qty, $price, $cols);

            if (($settings['print_item_notes'] ?? true) && ! empty($item->notes)) {
                $lines[] = '  * '.$dict['note'].' '.$item->notes;
            }
        }

        if ($settings['print_prices'] ?? true) {
            $lines[] = $divider;

            if ($settings['print_fee_breakdown'] ?? true) {
                $lines[] = $this->formatRow($dict['subtotal'], '', number_format($order->subtotal ?? $order->total_amount).' '.$vendor->currency, $cols);

                if ((float) $order->service_fee > 0) {
                    $lines[] = $this->formatRow($dict['service_fee'], '', number_format($order->service_fee).' '.$vendor->currency, $cols);
                }
                if ((float) $order->delivery_fee > 0) {
                    $lines[] = $this->formatRow($dict['delivery_fee'], '', number_format($order->delivery_fee).' '.$vendor->currency, $cols);
                }
                if ((float) $order->birthday_discount_amount > 0) {
                    $lines[] = $this->formatRow($dict['discount'], '', '-'.number_format($order->birthday_discount_amount).' '.$vendor->currency, $cols);
                }
            }

            $lines[] = $doubleDivider;
            $lines[] = $this->formatRow($dict['total'], '', number_format($order->total_amount).' '.$vendor->currency, $cols);
            $lines[] = $doubleDivider;
        }

        if (! empty($order->notes)) {
            $lines[] = $dict['kitchen_notes'];
            $lines[] = $order->notes;
            $lines[] = $divider;
        }

        $footerText = ! empty($settings['footer_text']) ? $settings['footer_text'] : $dict['thank_you'];
        if (! empty($footerText)) {
            $lines[] = $this->centerText($footerText, $cols);
        }

        return implode("\n", $lines);
    }

    /**
     * Generate modern, minimalist HTML representation of receipt for browser preview and high-quality printing.
     */
    public function generateReceiptHtml(Order $order, array $overrideSettings = []): string
    {
        $order->loadMissing(['items.product', 'location', 'vendor']);
        $vendor = $order->vendor;
        $settings = array_merge($vendor->getThermalPrinterSettings(), $overrideSettings);

        $width = $settings['paper_width'] ?? '80mm';
        $maxWidthPx = ($width === '58mm') ? '270px' : '340px';
        $lang = $settings['receipt_language'] ?? 'hy';
        $dict = $this->getDict($lang);

        $headerTitle = ! empty($settings['header_title']) ? $settings['header_title'] : $vendor->name;
        $headerSubtitle = $settings['header_subtitle'] ?? ($order->location?->name ?? '');
        $footerText = ! empty($settings['footer_text']) ? $settings['footer_text'] : $dict['thank_you'];
        $currency = $vendor->currency ?? '֏';

        $showLogo = ! empty($settings['show_logo']) && ! empty($vendor->logo);
        $logoUrl = $showLogo ? $vendor->logo : null;

        $typeLabel = match ($order->type) {
            'dine_in' => $dict['dine_in'],
            'takeaway' => $dict['takeaway'],
            'delivery' => $dict['delivery'],
            default => strtoupper($order->type),
        };
        $isPaid = ($order->payment_status === 'paid');
        $payStatusText = $isPaid ? $dict['paid'] : $dict['unpaid'];
        $payBadgeColor = $isPaid ? '#10b981' : '#f59e0b';
        $payBadgeBg = $isPaid ? 'rgba(16, 185, 129, 0.12)' : 'rgba(245, 158, 11, 0.12)';

        // Online Receipt URL
        $receiptUrl = route('client.menu', [
            'vendor_slug' => $vendor->slug,
            'active_order_num' => $order->order_number,
        ]);
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=130x130&data='.urlencode($receiptUrl);

        ob_start();
        ?>
        <div class="modern-receipt-slip" style="max-width: <?= $maxWidthPx ?>; margin: 0 auto; background: #ffffff; color: #111827; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 13px; line-height: 1.4; padding: 16px 14px; box-sizing: border-box; text-align: left;">
            <style>
                .modern-receipt-slip {
                    box-sizing: border-box;
                    color: #111827;
                }
                .modern-receipt-slip table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .receipt-divider {
                    border-bottom: 1px dashed #cbd5e1;
                    margin: 10px 0;
                }
                .receipt-solid-divider {
                    border-bottom: 1.5px solid #111827;
                    margin: 10px 0;
                }
                @media print {
                    @page {
                        size: <?= $width === '58mm' ? '58mm auto' : '80mm auto' ?>;
                        margin: 0;
                    }
                    body {
                        margin: 0 !important;
                        padding: 0 !important;
                        background: #fff !important;
                    }
                    .modern-receipt-slip {
                        width: <?= $width === '58mm' ? '54mm' : '76mm' ?> !important;
                        max-width: 100% !important;
                        padding: 2mm !important;
                        box-shadow: none !important;
                        border: none !important;
                    }
                    .no-print {
                        display: none !important;
                    }
                }
            </style>

            <!-- Receipt Header -->
            <div style="text-align: center; margin-bottom: 8px;">
                <?php if ($logoUrl) { ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" style="width: 50px; height: 50px; border-radius: 10px; object-fit: cover; margin-bottom: 6px;">
                <?php } ?>
                <div style="font-size: 18px; font-weight: 900; letter-spacing: -0.02em; color: #0f172a; line-height: 1.2;">
                    <?= htmlspecialchars($headerTitle) ?>
                </div>
                <?php if ($headerSubtitle) { ?>
                    <div style="font-size: 12px; color: #64748b; font-weight: 500; margin-top: 2px;">
                        <?= htmlspecialchars($headerSubtitle) ?>
                    </div>
                <?php } ?>
            </div>

            <div class="receipt-divider"></div>

            <!-- Meta: Order Number & Date -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                <span style="font-weight: 800; font-size: 15px; color: #0f172a;">
                    <?= $dict['order'] ?><?= htmlspecialchars($order->order_number) ?>
                </span>
                <span style="font-size: 11px; color: #64748b; font-weight: 600;">
                    <?= $order->created_at->format('d.m.Y H:i') ?>
                </span>
            </div>

            <!-- Order Type & Status Pills -->
            <div style="display: flex; flex-wrap: wrap; gap: 4px; align-items: center; margin-bottom: 8px;">
                <?php if ($settings['print_order_type'] ?? true) { ?>
                    <span style="display: inline-block; background: #f1f5f9; color: #334155; font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 6px;">
                        <?= htmlspecialchars($typeLabel) ?>
                        <?php if (! empty($order->table_number)) { ?>
                            • #<?= htmlspecialchars($order->table_number) ?>
                        <?php } ?>
                    </span>
                <?php } ?>

                <?php if ($settings['print_payment_details'] ?? true) { ?>
                    <span style="display: inline-block; background: <?= $payBadgeBg ?>; color: <?= $payBadgeColor ?>; font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 6px;">
                        <?= htmlspecialchars(strtoupper($order->payment_method ?? 'CASH')) ?>: <?= htmlspecialchars($payStatusText) ?>
                    </span>
                <?php } ?>
            </div>

            <!-- Customer Info -->
            <?php if (($settings['print_customer_info'] ?? true) && (! empty($order->customer_name) || ! empty($order->customer_phone))) { ?>
                <div style="background: #f8fafc; border-radius: 6px; padding: 6px 8px; font-size: 11.5px; margin-bottom: 8px; border: 1px solid #f1f5f9;">
                    <?php if (! empty($order->customer_name)) { ?>
                        <div><strong><?= $dict['customer'] ?></strong> <?= htmlspecialchars($order->customer_name) ?></div>
                    <?php } ?>
                    <?php if (! empty($order->customer_phone)) { ?>
                        <div><strong><?= $dict['phone'] ?></strong> <?= htmlspecialchars($order->customer_phone) ?></div>
                    <?php } ?>
                    <?php if (! empty($order->delivery_address)) { ?>
                        <div style="word-break: break-word;"><strong><?= $dict['address'] ?></strong> <?= htmlspecialchars($order->delivery_address) ?></div>
                    <?php } ?>
                </div>
            <?php } ?>

            <div class="receipt-solid-divider"></div>

            <!-- Items Table -->
            <table style="width: 100%; font-size: 12px; margin-bottom: 8px;">
                <thead>
                    <tr style="color: #64748b; font-size: 11px; font-weight: 800; border-bottom: 1px dashed #cbd5e1;">
                        <th style="text-align: left; padding-bottom: 4px;"><?= $dict['item_header'] ?></th>
                        <th style="text-align: center; width: 36px; padding-bottom: 4px;"><?= $dict['qty_header'] ?></th>
                        <?php if ($settings['print_prices'] ?? true) { ?>
                            <th style="text-align: right; width: 75px; padding-bottom: 4px;"><?= $dict['price_header'] ?></th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order->items as $item) { ?>
                        <tr>
                            <td style="padding: 5px 0 2px; vertical-align: top;">
                                <div style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($item->product_name) ?></div>
                                <?php if (! empty($item->variation_name) && ! in_array($item->variation_name, ['Standard', 'Standard Portion'])) { ?>
                                    <div style="font-size: 11px; color: #64748b;">(<?= htmlspecialchars($item->variation_name) ?>)</div>
                                <?php } ?>
                                <?php if (($settings['print_item_notes'] ?? true) && ! empty($item->notes)) { ?>
                                    <div style="font-size: 10.5px; color: #f59e0b; font-style: italic;">* <?= htmlspecialchars($item->notes) ?></div>
                                <?php } ?>
                            </td>
                            <td style="padding: 5px 0 2px; text-align: center; vertical-align: top; font-weight: 700; color: #475569;">
                                x<?= (int) $item->quantity ?>
                            </td>
                            <?php if ($settings['print_prices'] ?? true) { ?>
                                <td style="padding: 5px 0 2px; text-align: right; vertical-align: top; font-weight: 700; color: #0f172a; white-space: nowrap;">
                                    <?= number_format((float) $item->subtotal) ?> <?= $currency ?>
                                </td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <?php if ($settings['print_prices'] ?? true) { ?>
                <div class="receipt-divider"></div>

                <!-- Subtotals and Breakdown -->
                <?php if ($settings['print_fee_breakdown'] ?? true) { ?>
                    <div style="font-size: 12px; color: #475569; display: flex; flex-direction: column; gap: 3px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span><?= $dict['subtotal'] ?></span>
                            <span style="font-weight: 600; color: #1e293b;"><?= number_format((float) ($order->subtotal ?? $order->total_amount)) ?> <?= $currency ?></span>
                        </div>
                        <?php if ((float) $order->service_fee > 0) { ?>
                            <div style="display: flex; justify-content: space-between;">
                                <span><?= $dict['service_fee'] ?></span>
                                <span style="font-weight: 600; color: #1e293b;">+<?= number_format((float) $order->service_fee) ?> <?= $currency ?></span>
                            </div>
                        <?php } ?>
                        <?php if ((float) $order->delivery_fee > 0) { ?>
                            <div style="display: flex; justify-content: space-between;">
                                <span><?= $dict['delivery_fee'] ?></span>
                                <span style="font-weight: 600; color: #1e293b;">+<?= number_format((float) $order->delivery_fee) ?> <?= $currency ?></span>
                            </div>
                        <?php } ?>
                        <?php if ((float) $order->birthday_discount_amount > 0) { ?>
                            <div style="display: flex; justify-content: space-between; color: #10b981;">
                                <span><?= $dict['discount'] ?></span>
                                <span style="font-weight: 700;">-<?= number_format((float) $order->birthday_discount_amount) ?> <?= $currency ?></span>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>

                <div class="receipt-solid-divider"></div>

                <!-- Grand Total -->
                <div style="display: flex; justify-content: space-between; align-items: baseline; font-size: 17px; font-weight: 900; color: #0f172a; margin: 4px 0;">
                    <span><?= $dict['total'] ?></span>
                    <span><?= number_format((float) $order->total_amount) ?> <span style="font-size: 13px; font-weight: 700;"><?= $currency ?></span></span>
                </div>
                <div class="receipt-solid-divider"></div>
            <?php } ?>

            <!-- Kitchen Notes -->
            <?php if (! empty($order->notes)) { ?>
                <div style="background: #fffbeb; border: 1px dashed #fde68a; border-radius: 6px; padding: 6px 8px; margin: 6px 0; font-size: 11.5px; color: #92400e;">
                    <strong><?= $dict['kitchen_notes'] ?></strong> <?= htmlspecialchars($order->notes) ?>
                </div>
            <?php } ?>

            <!-- Footer Message & QR -->
            <div style="text-align: center; margin-top: 10px;">
                <?php if ($footerText) { ?>
                    <div style="font-size: 11px; font-weight: 600; color: #64748b; margin-bottom: 8px;">
                        <?= htmlspecialchars($footerText) ?>
                    </div>
                <?php } ?>

                <?php if ($settings['print_qr_code'] ?? true) { ?>
                    <div style="margin: 8px 0;">
                        <img src="<?= htmlspecialchars($qrCodeUrl) ?>" alt="QR" style="width: 80px; height: 80px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 2px;">
                        <div style="font-size: 9px; color: #94a3b8; font-weight: 600; margin-top: 2px;">
                            <?= $dict['digital_receipt'] ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate ESC/POS raw binary stream with formatting and paper cut.
     */
    public function generateEscPosBinary(Order $order, array $overrideSettings = []): string
    {
        $text = $this->generateReceiptText($order, null, $overrideSettings);

        // Standard ESC/POS commands:
        // ESC @ : Initialize printer
        $init = "\x1b\x40";
        // ESC t 0 : Character code table PC437
        $charTable = "\x1b\x74\x00";
        // Line feeds
        $feed = "\n\n\n";
        // GS V A 3 : Feed paper and cut (full cut with feed)
        $cut = "\x1d\x56\x41\x03";

        return $init.$charTable.$text.$feed.$cut;
    }

    /**
     * Print order directly to an ESC/POS network thermal printer over TCP socket (Port 9100).
     */
    public function printToNetworkPrinter(string $ip, int $port = 9100, $orderOrText = null, array $options = []): array
    {
        $ip = trim($ip);
        if (empty($ip)) {
            return [
                'success' => false,
                'message' => 'Տպիչի IP հասցեն նշված չէ։',
            ];
        }

        $port = ($port > 0) ? $port : 9100;

        if (! $this->isSafePrinterAddress($ip, $port)) {
            Log::warning("Blocked unsafe printer connection attempt to {$ip}:{$port}");

            return [
                'success' => false,
                'message' => 'Անվտանգության նկատառումներով նշված IP հասցեն կամ դարպասը (port) արգելափակված է։',
            ];
        }

        // Open socket connection with 3.5s timeout
        $socket = @fsockopen($ip, $port, $errno, $errstr, 3.5);
        if (! $socket) {
            Log::warning("Network thermal printer connection failed ($ip:$port): [$errno] $errstr");

            return [
                'success' => false,
                'message' => "Չհաջողվեց կապ հաստատել տպիչի հետ ({$ip}:{$port}): {$errstr}",
            ];
        }

        try {
            // Determine binary content
            if ($orderOrText instanceof Order) {
                $payload = $this->generateEscPosBinary($orderOrText, $options);
            } elseif (is_string($orderOrText)) {
                $payload = "\x1b\x40".$orderOrText."\n\n\n\x1d\x56\x41\x03";
            } else {
                $payload = "\x1b\x40TEST PRINT\n\n\n\x1d\x56\x41\x03";
            }

            stream_set_timeout($socket, 5);
            fwrite($socket, $payload);
            fflush($socket);
            fclose($socket);

            return [
                'success' => true,
                'message' => "Կտրոնը հաջողությամբ ուղարկվեց ցանցային տպիչին ({$ip}:{$port})։",
            ];
        } catch (\Throwable $e) {
            @fclose($socket);
            Log::error('Network printer write error: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Սխալ տպիչին տվյալներ ուղարկելիս՝ '.$e->getMessage(),
            ];
        }
    }

    /**
     * Send test print slip to verify network printer connectivity.
     */
    public function sendTestPrint(string $ip, int $port = 9100, ?string $vendorName = null): array
    {
        $ip = trim($ip);
        if (empty($ip)) {
            return [
                'success' => false,
                'message' => 'Տպիչի IP հասցեն նշված չէ։',
            ];
        }

        $port = ($port > 0) ? $port : 9100;
        $name = $vendorName ?: 'QRMenu Platform';
        $time = now()->format('d.m.Y H:i:s');

        $lines = [];
        $lines[] = '================================';
        $lines[] = '     '.$name;
        $lines[] = '  ESC/POS NETWORK PRINTER TEST  ';
        $lines[] = '================================';
        $lines[] = 'IP:        '.$ip.':'.$port;
        $lines[] = 'STATUS:    CONNECTED (OK)';
        $lines[] = 'TIMESTAMP: '.$time;
        $lines[] = '--------------------------------';
        $lines[] = 'Կապը հաջողությամբ հաստատված է!';
        $lines[] = 'Printing test successful.';
        $lines[] = '================================';

        $text = implode("\n", $lines);

        return $this->printToNetworkPrinter($ip, $port, $text);
    }

    /**
     * Center text within columns width.
     */
    private function centerText(string $text, int $width): string
    {
        $len = mb_strlen($text);
        if ($len >= $width) {
            return $text;
        }
        $leftPad = (int) floor(($width - $len) / 2);

        return str_repeat(' ', $leftPad).$text;
    }

    /**
     * Format a receipt row with name, qty, price aligned across width.
     */
    private function formatRow(string $name, string $qty, string $price, int $width): string
    {
        $priceLen = mb_strlen($price);
        $qtyLen = mb_strlen($qty);
        $reserved = $priceLen + $qtyLen + 2;
        $maxNameLen = max(10, $width - $reserved);

        if (mb_strlen($name) > $maxNameLen) {
            $name = mb_substr($name, 0, $maxNameLen - 1).'…';
        }

        $spaceCount = max(1, $width - mb_strlen($name) - $qtyLen - $priceLen);
        $leftSpaces = (int) floor($spaceCount / 2);
        $rightSpaces = $spaceCount - $leftSpaces;

        return $name.str_repeat(' ', $leftSpaces).$qty.str_repeat(' ', $rightSpaces).$price;
    }

    /**
     * Validate printer IP and port to prevent SSRF against loopback, metadata, and sensitive services.
     */
    public function isSafePrinterAddress(string $ip, int $port): bool
    {
        $ip = trim($ip);

        // Disallow hostname strings to prevent DNS rebinding SSRF; enforce strict IP format
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Block IPv4 loopback (127.0.0.0/8) & IPv6 loopback (::1)
        if ($ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '127.')) {
            return false;
        }

        // Block unspecified addresses
        if ($ip === '0.0.0.0' || $ip === '::') {
            return false;
        }

        // Block link-local addresses & cloud metadata service (169.254.0.0/16, fe80::)
        if (str_starts_with($ip, '169.254.') || str_starts_with(strtolower($ip), 'fe80:')) {
            return false;
        }

        // Block broadcast / reserved ranges
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        // Validate port range
        if ($port < 1 || $port > 65535) {
            return false;
        }

        // Block sensitive application and infrastructure service ports
        $blockedPorts = [
            21,    // FTP
            22,    // SSH
            23,    // Telnet
            25,    // SMTP
            80,    // HTTP
            443,   // HTTPS
            11211, // Memcached
            3306,  // MySQL
            5432,  // PostgreSQL
            6379,  // Redis
            27017, // MongoDB
            8080,  // Alternate HTTP
        ];

        if (in_array($port, $blockedPorts, true)) {
            return false;
        }

        return true;
    }

    /**
     * Get RawBT intent URL for direct thermal printing on Android devices.
     */
    public function getRawBtUrl(Order $order): string
    {
        $text = $this->generateReceiptText($order);

        return 'rawbt:data:text/plain;base64,'.base64_encode($text);
    }
}
