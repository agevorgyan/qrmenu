<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\Vendor;
use App\Models\WaiterCall;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    /**
     * Send a raw Telegram message via the Bot API.
     *
     * @param  array<string, mixed>|null  $replyMarkup
     * @return array{success: bool, message: string, response: ?array<string, mixed>}
     */
    public function sendMessage(
        string $chatId,
        string $htmlText,
        ?string $botToken = null,
        ?int $topicId = null,
        ?array $replyMarkup = null
    ): array {
        $token = $this->resolveBotToken($botToken);

        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'Telegram Bot Token-ը նշված չէ։ Խնդրում ենք մուտքագրել Bot Token-ը կամ կարգավորել հարթակի լռելյայն բոտը։',
                'response' => null,
            ];
        }

        $chatId = trim($chatId);
        if (empty($chatId)) {
            return [
                'success' => false,
                'message' => 'Telegram Chat ID-ն նշված չէ։',
                'response' => null,
            ];
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $htmlText,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if (! empty($topicId)) {
            $payload['message_thread_id'] = (int) $topicId;
        }

        if (! empty($replyMarkup)) {
            $payload['reply_markup'] = $replyMarkup;
        }

        try {
            $response = Http::timeout(6)
                ->asJson()
                ->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);

            $responseData = $response->json() ?? [];

            if ($response->successful() && ! empty($responseData['ok'])) {
                return [
                    'success' => true,
                    'message' => 'Telegram ծանուցումը հաջողությամբ ուղարկվեց։',
                    'response' => $responseData,
                ];
            }

            $errorDescription = $responseData['description'] ?? 'Անհայտ սխալ Telegram API-ից (կոդ՝ '.$response->status().')';
            Log::warning('Telegram sendMessage failed', [
                'chat_id' => $chatId,
                'status' => $response->status(),
                'response' => $responseData,
            ]);

            return [
                'success' => false,
                'message' => 'Telegram API սխալ. '.$errorDescription,
                'response' => $responseData,
            ];
        } catch (\Throwable $e) {
            $sanitizedMessage = app(CredentialService::class)->redactString($e->getMessage(), array_filter([$token]));
            Log::error('Telegram sendMessage exception: '.$sanitizedMessage, [
                'chat_id' => $chatId,
            ]);

            return [
                'success' => false,
                'message' => 'Կապի խափանում Telegram սերվերի հետ. '.$sanitizedMessage,
                'response' => null,
            ];
        }
    }

    /**
     * Send an interactive test message to verify Telegram bot connection and chat permissions.
     *
     * @return array{success: bool, message: string, response: ?array<string, mixed>}
     */
    public function sendTestMessage(
        string $chatId,
        ?string $botToken = null,
        ?int $topicId = null,
        ?string $sourceName = null
    ): array {
        $source = $sourceName ? $this->escapeHtml($sourceName) : 'QR Menu Կառավարման Համակարգ';
        $timeStr = now()->format('d/m/Y H:i:s');

        $html = "🚀 <b>QR Menu — Թեստային Ծանուցում</b>\n";
        $html .= "━━━━━━━━━━━━━━━━━━━━\n";
        $html .= "✅ <b>Telegram բոտը հաջողությամբ միացված է:</b>\n\n";
        $html .= "🏢 <b>Օբյեկտ:</b> {$source}\n";
        $html .= "💬 <b>Chat ID:</b> <code>{$this->escapeHtml($chatId)}</code>\n";
        if ($topicId) {
            $html .= "📌 <b>Topic / Thread ID:</b> <code>{$topicId}</code>\n";
        }
        $html .= "⏱ <b>Ամսաթիվ:</b> {$timeStr}\n\n";
        $html .= '🔔 <i>Այս չատում դուք կստանաք ակնթարթային ծանուցումներ նոր պատվերների, սեղանների մատուցողի կանչերի և օնլայն վճարումների մասին:</i>';

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '🌐 Բացել QR Menu Համակարգը',
                        'url' => config('app.url', 'https://menu.elab.am'),
                    ],
                ],
            ],
        ];

        return $this->sendMessage($chatId, $html, $botToken, $topicId, $replyMarkup);
    }

    /**
     * Send real-time notification for a newly created or updated order.
     */
    public function sendOrderNotification(Order $order): bool
    {
        $vendor = $order->vendor;
        if (! $vendor instanceof Vendor || ! $vendor->shouldNotifyTelegram('orders')) {
            return false;
        }

        $chatId = $this->resolveChatIdForOrder($order);
        if (empty($chatId)) {
            return false;
        }

        $botToken = $vendor->getTelegramBotToken();
        $topicId = $vendor->getTelegramTopicId();

        $order->loadMissing(['items.product', 'location']);

        $vendorName = $this->escapeHtml($vendor->name);
        $locationName = $order->location ? ' ('.$this->escapeHtml($order->location->name).')' : '';

        // Order Type emoji & label
        $typeDetails = match ($order->type) {
            'dine_in' => '🍽️ <b>Սեղան #'.($order->table_number ?: 'Անհայտ').'</b> (Տեղում)',
            'takeaway' => '🛍️ <b>Տանելու (Takeaway)</b>',
            'delivery' => '🚗 <b>Առաքում (Delivery)</b>',
            default => '📋 <b>'.ucfirst($order->type).'</b>',
        };

        $html = "🔔 <b>ՆՈՐ ՊԱՏՎԵՐ #{$order->order_number}</b>\n";
        $html .= "━━━━━━━━━━━━━━━━━━━━\n";
        $html .= "🏪 <b>Ռեստորան:</b> {$vendorName}{$locationName}\n";
        $html .= "📍 <b>Տեսակը:</b> {$typeDetails}\n";

        // Customer details
        if (! empty($order->customer_name) || ! empty($order->customer_phone)) {
            $customerParts = [];
            if (! empty($order->customer_name)) {
                $customerParts[] = $this->escapeHtml($order->customer_name);
            }
            if (! empty($order->customer_phone)) {
                $customerParts[] = '<code>'.$this->escapeHtml($order->customer_phone).'</code>';
            }
            $html .= '👤 <b>Հաճախորդ:</b> '.implode(' | ', $customerParts)."\n";
        }

        // Delivery address if applicable
        if ($order->type === 'delivery' && ! empty($order->delivery_address)) {
            $html .= "🏠 <b>Հասցե:</b> {$this->escapeHtml($order->delivery_address)}\n";
        }

        $html .= "\n📋 <b>Պատվիրված տեսականի:</b>\n";
        $items = $order->items;
        if ($items->isEmpty()) {
            $html .= "• <i>Տեսականին նշված չէ</i>\n";
        } else {
            foreach ($items as $item) {
                $productTitle = $this->escapeHtml($item->product_name);
                if (! empty($item->variation_name)) {
                    $productTitle .= ' ('.$this->escapeHtml($item->variation_name).')';
                }
                $qty = (int) $item->quantity;
                $lineSubtotal = number_format((float) $item->subtotal, 0, '.', ',');

                $html .= "• <b>{$qty}x</b> {$productTitle} — <b>{$lineSubtotal} ֏</b>\n";
                if (! empty($item->notes)) {
                    $html .= "   ↳ <i>📝 {$this->escapeHtml($item->notes)}</i>\n";
                }
            }
        }

        // Price breakdown
        $html .= "\n💰 <b>Ընդհանուր գումար:</b> <b>".number_format((float) $order->total_amount, 0, '.', ',')." ֏</b>\n";

        if ((float) $order->service_fee > 0) {
            $html .= '  ▫️ Սպասարկման վճար: +'.number_format((float) $order->service_fee, 0, '.', ",'")." ֏\n";
        }
        if ((float) $order->delivery_fee > 0) {
            $html .= '  ▫️ Առաքում: +'.number_format((float) $order->delivery_fee, 0, '.', ",'")." ֏\n";
        }
        if ((float) $order->birthday_discount_amount > 0) {
            $html .= '  ▫️ Ծննդյան զեղչ: -'.number_format((float) $order->birthday_discount_amount, 0, '.', ",'")." ֏\n";
        }

        // Payment status & method
        $paymentLabel = match ($order->payment_method) {
            'cash' => '💵 Կանխիկ',
            'pos', 'pos_terminal' => '💳 Տեղում քարտով (POS)',
            'idram' => '🧡 Idram',
            'telcell' => '⚡ Telcell Wallet',
            'arca' => '💳 ArCa / vPOS',
            'stripe' => '🌍 Stripe',
            default => '💳 '.ucfirst((string) $order->payment_method),
        };

        $paymentStatusBadge = match ($order->payment_status) {
            'paid' => '🟢 <b>Վճարված</b>',
            'failed' => '🔴 Ձախողված',
            default => '⏳ Չվճարված',
        };

        $html .= "💳 <b>Վճարում:</b> {$paymentLabel} ({$paymentStatusBadge})\n";

        // Order Notes
        if (! empty($order->notes)) {
            $html .= "📝 <b>Մեկնաբանություն:</b> «{$this->escapeHtml($order->notes)}»\n";
        }

        $createdAt = $order->created_at ? $order->created_at->format('H:i (d/m/Y)') : now()->format('H:i');
        $html .= "⏱ <b>Ժամ:</b> {$createdAt}";

        $adminOrderUrl = url('/admin/orders');

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '📦 Բացել Պատվերը Համակարգում',
                        'url' => $adminOrderUrl,
                    ],
                ],
            ],
        ];

        $result = $this->sendMessage($chatId, $html, $botToken, $topicId, $replyMarkup);

        return $result['success'];
    }

    /**
     * Send real-time notification for a waiter call or bill request.
     */
    public function sendWaiterCallNotification(WaiterCall $call): bool
    {
        $vendor = $call->vendor;
        if (! $vendor instanceof Vendor || ! $vendor->shouldNotifyTelegram('waiter_calls')) {
            return false;
        }

        $chatId = $this->resolveChatIdForWaiterCall($call);
        if (empty($chatId)) {
            return false;
        }

        $botToken = $vendor->getTelegramBotToken();
        $topicId = $vendor->getTelegramTopicId();

        $call->loadMissing('location');

        $vendorName = $this->escapeHtml($vendor->name);
        $locationName = $call->location ? ' ('.$this->escapeHtml($call->location->name).')' : '';
        $tableNumber = $this->escapeHtml((string) $call->table_number);

        $typeEmoji = match ($call->type) {
            'bill_cash' => '💵',
            'bill_card' => '💳',
            default => '🙋‍♂️',
        };

        $adminLocale = $vendor->admin_locale ?: 'hy';
        $prevLocale = app()->getLocale();
        app()->setLocale($adminLocale);

        try {
            $html = "🛎️ <b>ՄԱՏՈՒՑՈՂԻ ԿԱՆՉ!</b>\n";
            $html .= "━━━━━━━━━━━━━━━━━━━━\n";
            $html .= "🏪 <b>Ռեստորան:</b> {$vendorName}{$locationName}\n";
            $html .= "🪑 <b>Սեղան:</b> <b>Սեղան #{$tableNumber}</b>\n";
            $html .= "⚡ <b>Տեսակը:</b> {$typeEmoji} <b>{$call->getTypeLabel()}</b>\n";

            if (! empty($call->notes)) {
                $html .= "📝 <b>Նշում:</b> «{$this->escapeHtml($call->notes)}»\n";
            }

            $createdAt = $call->created_at ? $call->created_at->format('H:i') : now()->format('H:i');
            $html .= "⏱ <b>Ժամ:</b> {$createdAt}";

            $adminUrl = url('/admin/orders');

            $replyMarkup = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => '🛎️ Բացել Սրահի Պանելը',
                            'url' => $adminUrl,
                        ],
                    ],
                ],
            ];

            $result = $this->sendMessage($chatId, $html, $botToken, $topicId, $replyMarkup);

            return $result['success'];
        } finally {
            app()->setLocale($prevLocale);
        }
    }

    /**
     * Send real-time notification when an online payment is successfully received.
     */
    public function sendPaymentNotification(Order $order, string $gateway): bool
    {
        $vendor = $order->vendor;
        if (! $vendor instanceof Vendor || ! $vendor->shouldNotifyTelegram('payments')) {
            return false;
        }

        $chatId = $this->resolveChatIdForOrder($order);
        if (empty($chatId)) {
            return false;
        }

        $botToken = $vendor->getTelegramBotToken();
        $topicId = $vendor->getTelegramTopicId();

        $vendorName = $this->escapeHtml($vendor->name);
        $gatewayTitle = match (strtolower($gateway)) {
            'idram' => '🧡 Idram',
            'telcell' => '⚡ Telcell Wallet',
            'fastshift' => '🔷 FastShift',
            'arca' => '💳 ArCa / Ameriabank vPOS',
            'stripe' => '🌍 Stripe',
            default => '💳 '.ucfirst($gateway),
        };

        $amountFormatted = number_format((float) $order->total_amount, 0, '.', ',');

        $html = "✅ <b>ՎՃԱՐՈՒՄԸ ՀԱՍՏԱՏՎԱԾ Է!</b>\n";
        $html .= "━━━━━━━━━━━━━━━━━━━━\n";
        $html .= "🧾 <b>Պատվեր:</b> #{$order->order_number}\n";
        $html .= "🏪 <b>Ռեստորան:</b> {$vendorName}\n";
        $html .= "💳 <b>Համակարգ:</b> {$gatewayTitle}\n";
        $html .= "💰 <b>Վճարված գումար:</b> <b>{$amountFormatted} ֏</b>\n";

        if (! empty($order->payment_transaction_id)) {
            $html .= "🆔 <b>Գործարքի ID:</b> <code>{$this->escapeHtml($order->payment_transaction_id)}</code>\n";
        }

        $timeStr = now()->format('H:i (d/m/Y)');
        $html .= "⏱ <b>Ժամ:</b> {$timeStr}";

        $adminOrderUrl = url('/admin/orders');

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '📦 Դիտել Պատվերը',
                        'url' => $adminOrderUrl,
                    ],
                ],
            ],
        ];

        $result = $this->sendMessage($chatId, $html, $botToken, $topicId, $replyMarkup);

        return $result['success'];
    }

    /**
     * Resolve the Bot Token (explicit token > platform SystemSetting > config > env).
     */
    protected function resolveBotToken(?string $customToken = null): ?string
    {
        if (! empty($customToken)) {
            return trim($customToken);
        }

        return SystemSetting::get('telegram_bot_token')
            ?: config('services.telegram.bot_token')
            ?: env('TELEGRAM_BOT_TOKEN');
    }

    /**
     * Resolve target chat ID for an order (branch-specific chat ID if set, otherwise vendor chat ID).
     */
    protected function resolveChatIdForOrder(Order $order): ?string
    {
        if ($order->location && ! empty($order->location->telegram_chat_id)) {
            return trim($order->location->telegram_chat_id);
        }

        return $order->vendor?->getTelegramChatId();
    }

    /**
     * Resolve target chat ID for a waiter call.
     */
    protected function resolveChatIdForWaiterCall(WaiterCall $call): ?string
    {
        if ($call->location && ! empty($call->location->telegram_chat_id)) {
            return trim($call->location->telegram_chat_id);
        }

        return $call->vendor?->getTelegramChatId();
    }

    /**
     * Escape special HTML characters for Telegram HTML mode.
     */
    protected function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
