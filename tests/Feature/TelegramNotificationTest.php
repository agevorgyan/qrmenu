<?php

namespace Tests\Feature;

use App\Events\OrderCreated;
use App\Events\WaiterCalled;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WaiterCall;
use App\Services\TelegramNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_telegram_service_sends_message_successfully(): void
    {
        Http::fake([
            'https://api.telegram.org/bot12345:TOKEN/sendMessage' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 777,
                    'chat' => ['id' => -100123456],
                    'text' => 'Hello',
                ],
            ], 200),
        ]);

        $service = app(TelegramNotificationService::class);
        $result = $service->sendMessage(
            chatId: '-100123456',
            htmlText: '<b>Test message</b>',
            botToken: '12345:TOKEN'
        );

        $this->assertTrue($result['success']);
        $this->assertSame(777, $result['response']['result']['message_id']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bot12345:TOKEN/sendMessage'
                && $request['chat_id'] === '-100123456'
                && $request['parse_mode'] === 'HTML'
                && str_contains($request['text'], '<b>Test message</b>');
        });
    }

    public function test_telegram_service_handles_api_failure_gracefully(): void
    {
        Http::fake([
            'https://api.telegram.org/bot12345:TOKEN/sendMessage' => Http::response([
                'ok' => false,
                'error_code' => 400,
                'description' => 'Bad Request: chat not found',
            ], 400),
        ]);

        $service = app(TelegramNotificationService::class);
        $result = $service->sendMessage(
            chatId: '-100999999',
            htmlText: 'Hello',
            botToken: '12345:TOKEN'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('chat not found', $result['message']);
    }

    public function test_order_created_event_triggers_telegram_notification(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        $vendor = Vendor::first();
        $vendor->update([
            'telegram_settings' => [
                'enabled' => true,
                'bot_token' => '555:CUSTOM_BOT_TOKEN',
                'chat_id' => '-100777888',
                'notify_orders' => true,
            ],
        ]);

        $location = $vendor->locations()->first();

        $order = Order::create([
            'vendor_id' => $vendor->id,
            'location_id' => $location?->id,
            'order_number' => 'ORD-TG-001',
            'type' => 'dine_in',
            'table_number' => '7',
            'status' => 'pending',
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'subtotal' => 4000,
            'total_amount' => 4000,
            'customer_name' => 'Արմեն Գրիգորյան',
            'customer_phone' => '+37499112233',
            'notes' => 'Առանց սոխի',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Խորոված խոզի',
            'unit_price' => 4000,
            'quantity' => 1,
            'subtotal' => 4000,
        ]);

        event(new OrderCreated($order));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'bot555:CUSTOM_BOT_TOKEN/sendMessage')
                && $request['chat_id'] === '-100777888'
                && str_contains($request['text'], 'ORD-TG-001')
                && str_contains($request['text'], 'Սեղան #7')
                && str_contains($request['text'], 'Խորոված խոզի')
                && str_contains($request['text'], 'Արմեն Գրիգորյան');
        });
    }

    public function test_order_created_event_does_not_send_when_telegram_disabled(): void
    {
        Http::fake();

        $vendor = Vendor::first();
        $location = $vendor->locations()->first();

        $vendor->update([
            'telegram_settings' => [
                'enabled' => false,
                'chat_id' => '-100777888',
            ],
        ]);

        $order = Order::create([
            'vendor_id' => $vendor->id,
            'location_id' => $location?->id,
            'order_number' => 'ORD-TG-002',
            'type' => 'takeaway',
            'status' => 'pending',
            'subtotal' => 1500,
            'total_amount' => 1500,
        ]);

        event(new OrderCreated($order));

        Http::assertNothingSent();
    }

    public function test_waiter_called_event_triggers_telegram_notification(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 2]], 200),
        ]);

        $vendor = Vendor::first();
        $vendor->update([
            'telegram_settings' => [
                'enabled' => true,
                'bot_token' => '555:CUSTOM_BOT_TOKEN',
                'chat_id' => '-100777888',
                'notify_waiter_calls' => true,
            ],
        ]);

        $waiterCall = WaiterCall::create([
            'vendor_id' => $vendor->id,
            'table_number' => '12',
            'type' => 'bill_card',
            'status' => 'pending',
            'notes' => 'Խնդրում եմ հաշիվը բերեք',
        ]);

        event(new WaiterCalled($waiterCall));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'bot555:CUSTOM_BOT_TOKEN/sendMessage')
                && $request['chat_id'] === '-100777888'
                && str_contains($request['text'], 'Սեղան #12')
                && str_contains($request['text'], 'Հաշիվ (Քարտով)');
        });
    }

    public function test_payment_notification_is_sent_to_telegram(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 3]], 200),
        ]);

        $vendor = Vendor::first();
        $location = $vendor->locations()->first();

        $vendor->update([
            'telegram_settings' => [
                'enabled' => true,
                'bot_token' => '555:CUSTOM_BOT_TOKEN',
                'chat_id' => '-100777888',
                'notify_payments' => true,
            ],
        ]);

        $order = Order::create([
            'vendor_id' => $vendor->id,
            'location_id' => $location?->id,
            'order_number' => 'ORD-TG-PAY-100',
            'type' => 'dine_in',
            'table_number' => '3',
            'status' => 'accepted',
            'payment_method' => 'idram',
            'payment_status' => 'paid',
            'payment_transaction_id' => 'TRX-998877',
            'subtotal' => 8500,
            'total_amount' => 8500,
        ]);

        $service = app(TelegramNotificationService::class);
        $result = $service->sendPaymentNotification($order, 'idram');

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'bot555:CUSTOM_BOT_TOKEN/sendMessage')
                && $request['chat_id'] === '-100777888'
                && str_contains($request['text'], 'ORD-TG-PAY-100')
                && str_contains($request['text'], 'Idram')
                && str_contains($request['text'], '8,500 ֏')
                && str_contains($request['text'], 'TRX-998877');
        });
    }

    public function test_vendor_can_update_telegram_settings(): void
    {
        $vendor = Vendor::first();
        $user = User::where('vendor_id', $vendor->id)->where('role', 'vendor_owner')->first()
            ?? User::create([
                'vendor_id' => $vendor->id,
                'name' => 'Owner',
                'email' => 'owner-tg@test.com',
                'password' => bcrypt('password'),
                'role' => 'vendor_owner',
            ]);

        $this->actingAs($user);

        $response = $this->post(route('admin.settings.update'), [
            'service_fee_type' => 'percent',
            'service_fee_value' => 10,
            'delivery_fee' => 1000,
            'delivery_min_amount' => 3000,
            'telegram_settings' => [
                'enabled' => '1',
                'bot_token' => '111222:TEST_TOKEN',
                'chat_id' => '-100987654321',
                'topic_id' => '15',
                'notify_orders' => '1',
                'notify_waiter_calls' => '1',
                'notify_payments' => '1',
            ],
        ]);

        $response->assertSessionHas('success');

        $vendor->refresh();
        $this->assertTrue($vendor->hasTelegramEnabled());
        $this->assertSame('111222:TEST_TOKEN', $vendor->getTelegramBotToken());
        $this->assertSame('-100987654321', $vendor->getTelegramChatId());
        $this->assertSame(15, $vendor->getTelegramTopicId());
        $this->assertTrue($vendor->shouldNotifyTelegram('orders'));
        $this->assertTrue($vendor->shouldNotifyTelegram('waiter_calls'));
    }

    public function test_vendor_telegram_test_endpoint(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 99]], 200),
        ]);

        $vendor = Vendor::query()->where('slug', 'bistro-yerevan')->firstOrFail();
        $user = User::where('vendor_id', $vendor->id)->where('role', 'vendor_owner')->first()
            ?? User::create([
                'vendor_id' => $vendor->id,
                'name' => 'Telegram Test Owner',
                'email' => 'telegram-test-owner@example.test',
        $vendor = Vendor::whereHas('users', fn ($q) => $q->where('role', 'vendor_owner'))->first() ?? Vendor::first();
        $user = User::where('vendor_id', $vendor->id)->where('role', 'vendor_owner')->first()
            ?? User::create([
                'vendor_id' => $vendor->id,
                'name' => 'Owner',
                'email' => 'owner-tg-test@test.com',
                'password' => bcrypt('password'),
                'role' => 'vendor_owner',
            ]);

        $this->actingAs($user);

        $response = $this->postJson(route('admin.settings.telegram.test'), [
            'chat_id' => '-100123456789',
            'bot_token' => '999:TEST_BOT_TOKEN',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request['text'], 'Թեստային Ծանուցում')
                && $request['chat_id'] === '-100123456789';
        });
    }

    public function test_superadmin_telegram_settings_and_test_endpoint(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 88]], 200),
        ]);

        $superadmin = User::where('role', 'superadmin')->first();
        $this->actingAs($superadmin);

        // Update settings
        $response = $this->post(route('superadmin.settings.update'), [
            'contact_phone' => '+37455112233',
            'contact_email' => 'admin@elab.am',
            'trial_days' => 14,
            'telegram_bot_token' => '888:SA_TOKEN',
            'telegram_admin_chat_id' => '-100888999',
        ]);

        $response->assertSessionHas('success');
        $this->assertSame('888:SA_TOKEN', SystemSetting::get('telegram_bot_token'));
        $this->assertSame('-100888999', SystemSetting::get('telegram_admin_chat_id'));

        // Test endpoint
        $testResponse = $this->postJson(route('superadmin.settings.telegram.test'), [
            'chat_id' => '-100888999',
            'bot_token' => '888:SA_TOKEN',
        ]);

        $testResponse->assertStatus(200);
        $testResponse->assertJson([
            'success' => true,
        ]);
    }
}
