<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Payments\PaymentService;
use App\Services\Payments\PaymentVerificationService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected User $user;

    protected Location $location;

    protected Category $category;

    protected Product $product;

    protected SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        $this->plan = SubscriptionPlan::where('slug', 'pro')->first();

        $this->vendor = Vendor::create([
            'name' => 'Security Hardened Bistro',
            'slug' => 'security-bistro',
            'email' => 'security@bistro.am',
            'password' => bcrypt('password123'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $this->plan->id,
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(10),
            'is_active' => true,
            'payment_settings' => [
                'cash_enabled' => true,
                'pos_terminal_enabled' => true,
                'online_enabled' => true,
                'gateways' => [
                    'idram' => [
                        'enabled' => true,
                        'merchant_id' => '110000123',
                        'secret_key' => 'idram-super-secret-key',
                    ],
                    'telcell' => [
                        'enabled' => true,
                        'shop_id' => '220000456',
                        'security_key' => 'telcell-super-secret-key',
                    ],
                    'stripe' => [
                        'enabled' => true,
                        'secret_key' => 'sk_test_sample_key',
                    ],
                    'arca' => [
                        'enabled' => true,
                        'merchant_id' => 'arca-merchant',
                        'secret_key' => 'arca-secret',
                    ],
                ],
            ],
        ]);

        $this->user = User::create([
            'name' => 'Bistro Owner',
            'email' => 'security@bistro.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Center',
            'slug' => 'main-center',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Main Dishes',
            'slug' => 'main-dishes',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Premium Steak',
            'slug' => 'premium-steak',
            'price' => 5000,
            'is_active' => true,
        ]);
    }

    protected function createTestOrder(float $amount = 5000): Order
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-'.strtoupper(Str::random(6)),
            'subtotal' => $amount,
            'total_amount' => $amount,
            'status' => 'pending',
            'payment_method' => 'idram',
            'payment_status' => 'pending',
            'type' => 'dine_in',
        ]);

        return $order;
    }

    public function test_fake_client_callback_cannot_mark_order_as_paid(): void
    {
        $order = $this->createTestOrder(5000);
        $paymentService = app(PaymentService::class);
        $initiation = $paymentService->initiateOrderPayment($order, 'idram');

        $attempt = PaymentAttempt::where('order_id', $order->id)->first();
        $this->assertNotNull($attempt);
        $this->assertEquals(PaymentStatus::Pending, $attempt->status);

        // Attacker attempts browser callback with fake parameters
        $response = $this->get("/payment/callback/{$this->vendor->slug}/{$attempt->merchant_reference}?status=paid&token=FAKE-HACK-TRX");
        $response->assertRedirect();

        $attempt->refresh();
        $order->refresh();

        // Must remain pending - untrusted browser query params are NEVER trusted
        $this->assertEquals(PaymentStatus::Pending, $attempt->status);
        $this->assertEquals('pending', $order->payment_status);
        $this->assertNull($attempt->verified_at);
    }

    public function test_webhook_with_wrong_amount_is_rejected(): void
    {
        $order = $this->createTestOrder(5000);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'idram');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();

        // Webhook tries to pay with underpayment: 1000 AMD instead of 5000 AMD
        $tamperedAmount = '1000.00';
        $checksum = strtoupper(md5("110000123:{$tamperedAmount}:idram-super-secret-key:{$attempt->merchant_reference}:TRX-FAKE-AMOUNT"));

        $response = $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), [
            'EDP_BILL_NO' => $attempt->merchant_reference,
            'EDP_REC_ACCOUNT' => '110000123',
            'EDP_AMOUNT' => $tamperedAmount,
            'EDP_TRANS_ID' => 'TRX-FAKE-AMOUNT',
            'EDP_CHECKSUM' => $checksum,
        ]);

        $response->assertStatus(400);

        $attempt->refresh();
        $order->refresh();

        $this->assertEquals(PaymentStatus::Failed, $attempt->status);
        $this->assertEquals('failed', $order->payment_status);
    }

    public function test_webhook_with_wrong_currency_is_rejected(): void
    {
        $order = $this->createTestOrder(5000);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'telcell');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();

        // Webhook sends USD currency instead of authoritative AMD
        $price = 5000;
        $currency = 'USD';
        $time = time();
        $invoice = 'TEL-INV-999';
        $raw = "220000456:{$currency}:{$price}:{$attempt->merchant_reference}::{$time}:{$invoice}";
        $checksum = hash_hmac('sha256', $raw, 'telcell-super-secret-key');

        $response = $this->post(route('api.webhooks.payment', ['gateway' => 'telcell']), [
            'issuer' => '220000456',
            'currency' => $currency,
            'price' => $price,
            'product_id' => $attempt->merchant_reference,
            'buyer' => '',
            'time' => $time,
            'invoice' => $invoice,
            'checksum' => $checksum,
        ]);

        $response->assertStatus(400);

        $attempt->refresh();
        $this->assertEquals(PaymentStatus::Failed, $attempt->status);
    }

    public function test_webhook_with_wrong_vendor_merchant_account_is_rejected(): void
    {
        $order = $this->createTestOrder(5000);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'idram');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();

        // Webhook with a different merchant account
        $foreignAccount = '999999999';
        $amount = '5000.00';
        $checksum = strtoupper(md5("{$foreignAccount}:{$amount}:idram-super-secret-key:{$attempt->merchant_reference}:TRX-WRONG-ACC"));

        $response = $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), [
            'EDP_BILL_NO' => $attempt->merchant_reference,
            'EDP_REC_ACCOUNT' => $foreignAccount,
            'EDP_AMOUNT' => $amount,
            'EDP_TRANS_ID' => 'TRX-WRONG-ACC',
            'EDP_CHECKSUM' => $checksum,
        ]);

        $response->assertStatus(400);
        $attempt->refresh();
        $this->assertNotEquals(PaymentStatus::Paid, $attempt->status);
    }

    public function test_webhook_with_unknown_order_reference_is_rejected(): void
    {
        $response = $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), [
            'EDP_BILL_NO' => 'ORD-UNKNOWN-DOES-NOT-EXIST',
            'EDP_REC_ACCOUNT' => '110000123',
            'EDP_AMOUNT' => '5000.00',
            'EDP_TRANS_ID' => 'TRX-GHOST',
            'EDP_CHECKSUM' => 'MOCK_CHECKSUM',
        ]);

        $response->assertStatus(400);
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        $order = $this->createTestOrder(5000);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'idram');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();

        // Invalid checksum
        $response = $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), [
            'EDP_BILL_NO' => $attempt->merchant_reference,
            'EDP_REC_ACCOUNT' => '110000123',
            'EDP_AMOUNT' => '5000.00',
            'EDP_TRANS_ID' => 'TRX-VALID-ID',
            'EDP_CHECKSUM' => 'CORRUPTED_TAMPERED_CHECKSUM',
        ]);

        $response->assertStatus(400);
        $attempt->refresh();
        $order->refresh();

        $this->assertEquals(PaymentStatus::Failed, $attempt->status);
        $this->assertEquals('failed', $order->payment_status);
    }

    public function test_webhook_with_unknown_transaction_is_rejected(): void
    {
        $order = $this->createTestOrder(5000);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'idram');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();

        // Missing transaction ID
        $response = $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), [
            'EDP_BILL_NO' => $attempt->merchant_reference,
            'EDP_REC_ACCOUNT' => '110000123',
            'EDP_AMOUNT' => '5000.00',
            'EDP_TRANS_ID' => '',
            'EDP_CHECKSUM' => 'CHECKSUM',
        ]);

        $response->assertStatus(400);
    }

    public function test_duplicate_webhook_is_idempotent_and_does_not_duplicate_side_effects(): void
    {
        $order = $this->createTestOrder(5000);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'idram');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();

        $amount = number_format((float) $attempt->amount, 2, '.', '');
        $checksum = strtoupper(md5("110000123:{$amount}:idram-super-secret-key:{$attempt->merchant_reference}:TRX-IDEMPOTENT-1"));

        $payload = [
            'EDP_BILL_NO' => $attempt->merchant_reference,
            'EDP_REC_ACCOUNT' => '110000123',
            'EDP_AMOUNT' => $amount,
            'EDP_TRANS_ID' => 'TRX-IDEMPOTENT-1',
            'EDP_CHECKSUM' => $checksum,
        ];

        // First delivery: processes successfully
        $res1 = $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), $payload);
        $res1->assertStatus(200);

        $attempt->refresh();
        $order->refresh();
        $this->assertEquals(PaymentStatus::Paid, $attempt->status);
        $this->assertEquals('paid', $order->payment_status);
        $verifiedAt = $attempt->verified_at;
        $this->assertNotNull($verifiedAt);

        // Second delivery (duplicate webhook): returns 200 OK without re-running side effects
        $res2 = $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), $payload);
        $res2->assertStatus(200);

        $attempt->refresh();
        $this->assertEquals(PaymentStatus::Paid, $attempt->status);
        $this->assertEquals($verifiedAt->timestamp, $attempt->verified_at->timestamp);
    }

    public function test_repeated_callback_is_idempotent(): void
    {
        $order = $this->createTestOrder(5000);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'idram');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();

        // Mark paid via verified webhook
        $amount = number_format((float) $attempt->amount, 2, '.', '');
        $checksum = strtoupper(md5("110000123:{$amount}:idram-super-secret-key:{$attempt->merchant_reference}:TRX-CALLBACK-REP"));
        $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), [
            'EDP_BILL_NO' => $attempt->merchant_reference,
            'EDP_REC_ACCOUNT' => '110000123',
            'EDP_AMOUNT' => $amount,
            'EDP_TRANS_ID' => 'TRX-CALLBACK-REP',
            'EDP_CHECKSUM' => $checksum,
        ])->assertStatus(200);

        // Customer visits callback route repeatedly
        for ($i = 0; $i < 3; $i++) {
            $response = $this->get("/payment/callback/{$this->vendor->slug}/{$attempt->merchant_reference}");
            $response->assertRedirect();
            $response->assertSessionHas('success');
        }

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
    }

    public function test_already_paid_payment_cannot_be_overwritten_by_failed_payload(): void
    {
        $order = $this->createTestOrder(5000);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'idram');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();

        // Mark paid initially
        $amount = number_format((float) $attempt->amount, 2, '.', '');
        $checksum = strtoupper(md5("110000123:{$amount}:idram-super-secret-key:{$attempt->merchant_reference}:TRX-LEGIT-100"));
        $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), [
            'EDP_BILL_NO' => $attempt->merchant_reference,
            'EDP_REC_ACCOUNT' => '110000123',
            'EDP_AMOUNT' => $amount,
            'EDP_TRANS_ID' => 'TRX-LEGIT-100',
            'EDP_CHECKSUM' => $checksum,
        ])->assertStatus(200);

        $attempt->refresh();
        $this->assertEquals(PaymentStatus::Paid, $attempt->status);

        // Attacker sends subsequent failed or tampered webhook
        $tamperedAmount = '100.00';
        $badChecksum = strtoupper(md5("110000123:{$tamperedAmount}:idram-super-secret-key:{$attempt->merchant_reference}:TRX-ATTACK"));
        $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), [
            'EDP_BILL_NO' => $attempt->merchant_reference,
            'EDP_REC_ACCOUNT' => '110000123',
            'EDP_AMOUNT' => $tamperedAmount,
            'EDP_TRANS_ID' => 'TRX-ATTACK',
            'EDP_CHECKSUM' => $badChecksum,
        ]);

        $attempt->refresh();
        $order->refresh();

        // Payment status must STILL be paid
        $this->assertEquals(PaymentStatus::Paid, $attempt->status);
        $this->assertEquals('paid', $order->payment_status);
    }

    public function test_expired_payment_attempt_cannot_be_finalized(): void
    {
        $order = $this->createTestOrder(5000);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'idram');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();

        // Force expired timestamp
        $attempt->update(['expires_at' => now()->subMinutes(10)]);

        $amount = number_format((float) $attempt->amount, 2, '.', '');
        $checksum = strtoupper(md5("110000123:{$amount}:idram-super-secret-key:{$attempt->merchant_reference}:TRX-EXPIRED-TEST"));

        $response = $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), [
            'EDP_BILL_NO' => $attempt->merchant_reference,
            'EDP_REC_ACCOUNT' => '110000123',
            'EDP_AMOUNT' => $amount,
            'EDP_TRANS_ID' => 'TRX-EXPIRED-TEST',
            'EDP_CHECKSUM' => $checksum,
        ]);

        $response->assertStatus(400);

        $attempt->refresh();
        $order->refresh();

        $this->assertEquals(PaymentStatus::Expired, $attempt->status);
        $this->assertNotEquals('paid', $order->payment_status);
    }

    public function test_concurrent_webhook_processing(): void
    {
        $order = $this->createTestOrder(5000);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'idram');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();

        $amount = number_format((float) $attempt->amount, 2, '.', '');
        $checksum = strtoupper(md5("110000123:{$amount}:idram-super-secret-key:{$attempt->merchant_reference}:CONCURRENT-TRX-1"));

        $payload = [
            'EDP_BILL_NO' => $attempt->merchant_reference,
            'EDP_REC_ACCOUNT' => '110000123',
            'EDP_AMOUNT' => $amount,
            'EDP_TRANS_ID' => 'CONCURRENT-TRX-1',
            'EDP_CHECKSUM' => $checksum,
        ];

        $verificationService = app(PaymentVerificationService::class);

        // Process A executes
        $resultA = $verificationService->verifyAndFinalizeWebhook('idram', $payload);
        $this->assertTrue($resultA->success);
        $this->assertEquals(PaymentStatus::Paid, $resultA->status);

        // Process B executes concurrently right behind A
        $resultB = $verificationService->verifyAndFinalizeWebhook('idram', $payload);
        $this->assertTrue($resultB->success);
        $this->assertEquals(PaymentStatus::Paid, $resultB->status);

        // State remains strictly consistent with exactly one verified payment record
        $attempt->refresh();
        $order->refresh();
        $this->assertEquals(PaymentStatus::Paid, $attempt->status);
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('CONCURRENT-TRX-1', $attempt->provider_transaction_id);
    }

    public function test_duplicate_provider_transaction_across_different_attempts_is_rejected(): void
    {
        // First order and payment
        $order1 = $this->createTestOrder(5000);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order1, 'idram');
        $attempt1 = PaymentAttempt::where('order_id', $order1->id)->first();

        $amount1 = number_format((float) $attempt1->amount, 2, '.', '');
        $checksum1 = strtoupper(md5("110000123:{$amount1}:idram-super-secret-key:{$attempt1->merchant_reference}:UNIQUE-TRX-12345"));
        $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), [
            'EDP_BILL_NO' => $attempt1->merchant_reference,
            'EDP_REC_ACCOUNT' => '110000123',
            'EDP_AMOUNT' => $amount1,
            'EDP_TRANS_ID' => 'UNIQUE-TRX-12345',
            'EDP_CHECKSUM' => $checksum1,
        ])->assertStatus(200);

        $attempt1->refresh();
        $this->assertEquals(PaymentStatus::Paid, $attempt1->status);

        // Second order attempts to reuse the same provider transaction ID
        $order2 = $this->createTestOrder(5000);
        $paymentService->initiateOrderPayment($order2, 'idram');
        $attempt2 = PaymentAttempt::where('order_id', $order2->id)->first();

        $amount2 = number_format((float) $attempt2->amount, 2, '.', '');
        $checksum2 = strtoupper(md5("110000123:{$amount2}:idram-super-secret-key:{$attempt2->merchant_reference}:UNIQUE-TRX-12345"));
        $response = $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), [
            'EDP_BILL_NO' => $attempt2->merchant_reference,
            'EDP_REC_ACCOUNT' => '110000123',
            'EDP_AMOUNT' => $amount2,
            'EDP_TRANS_ID' => 'UNIQUE-TRX-12345', // Replay of identical provider transaction
            'EDP_CHECKSUM' => $checksum2,
        ]);

        $response->assertStatus(400);

        $attempt2->refresh();
        $order2->refresh();
        $this->assertEquals(PaymentStatus::Failed, $attempt2->status);
        $this->assertEquals('failed', $order2->payment_status);
    }

    public function test_failed_payment_webhook_marks_attempt_and_order_failed(): void
    {
        $order = $this->createTestOrder(5000);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'stripe');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();

        // Webhook for payment_failed
        $payload = [
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_failed_123',
                    'client_reference_id' => $attempt->merchant_reference,
                    'last_payment_error' => [
                        'message' => 'Your card was declined.',
                    ],
                ],
            ],
        ];

        $response = $this->post(route('api.webhooks.payment', ['gateway' => 'stripe']), $payload);
        $response->assertStatus(400);

        $attempt->refresh();
        $order->refresh();

        $this->assertEquals(PaymentStatus::Failed, $attempt->status);
        $this->assertEquals('failed', $order->payment_status);
    }

    public function test_refunded_payment_webhook_transitions_payment_and_order_to_refunded(): void
    {
        $order = $this->createTestOrder(5000);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'stripe');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();

        // Initially paid
        $paidPayload = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'payment_intent' => 'pi_test_123',
                    'client_reference_id' => $attempt->merchant_reference,
                    'amount_total' => 500000,
                    'currency' => 'amd',
                ],
            ],
        ];
        $this->post(route('api.webhooks.payment', ['gateway' => 'stripe']), $paidPayload)->assertStatus(200);

        $attempt->refresh();
        $this->assertEquals(PaymentStatus::Paid, $attempt->status);

        // Refund webhook arrives
        $refundPayload = [
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_refund_123',
                    'payment_intent' => 'pi_test_123',
                    'client_reference_id' => $attempt->merchant_reference,
                    'amount_refunded' => 500000,
                    'currency' => 'amd',
                ],
            ],
        ];
        $this->post(route('api.webhooks.payment', ['gateway' => 'stripe']), $refundPayload)->assertStatus(200);

        $attempt->refresh();
        $order->refresh();

        $this->assertEquals(PaymentStatus::Refunded, $attempt->status);
        $this->assertEquals('refunded', $order->payment_status);
    }

    public function test_subscription_renewal_creates_pending_payment_until_verified(): void
    {
        $oldExpiresAt = $this->vendor->subscription_expires_at;

        // Vendor owner submits renewal for 12 months with 20% discount
        $response = $this->actingAs($this->user)
            ->from(route('admin.subscription'))
            ->post(route('admin.subscription.renew'), [
                'subscription_plan_id' => $this->plan->id,
                'period_months' => 12,
                'payment_method' => 'arca',
            ]);

        $response->assertRedirect();

        // 1. SubscriptionPayment must be pending
        $subPayment = SubscriptionPayment::where('vendor_id', $this->vendor->id)->latest()->first();
        $this->assertNotNull($subPayment);
        $this->assertEquals('pending', $subPayment->status);

        // Authoritative discount verification: basePrice * 12 * 0.8
        $expectedAmount = ((float) $this->plan->price) * 12 * 0.80;
        $this->assertEquals($expectedAmount, (float) $subPayment->amount);

        // 2. Vendor expiration must NOT be extended yet!
        $this->vendor->refresh();
        $this->assertEquals($oldExpiresAt->timestamp, $this->vendor->subscription_expires_at->timestamp);

        // 3. Webhook arrives with verified payment
        $attempt = PaymentAttempt::where('subscription_id', $subPayment->id)->first();
        $this->assertNotNull($attempt);
        $this->assertEquals(PaymentStatus::Pending, $attempt->status);

        $webhookRes = $this->post(route('api.webhooks.payment', ['gateway' => 'arca']), [
            'orderNumber' => $attempt->merchant_reference,
            'orderId' => 'ARCA-SUB-TXN-777',
            'status' => 2,
            'amount' => ((int) round($attempt->amount)) * 100,
        ]);
        $webhookRes->assertStatus(200);

        // 4. Subscription payment and vendor are now completed and active
        $subPayment->refresh();
        $this->vendor->refresh();

        $this->assertEquals('completed', $subPayment->status);
        $this->assertEquals('active', $this->vendor->subscription_status);
        $this->assertTrue($this->vendor->subscription_expires_at->gt($oldExpiresAt));
    }

    public function test_subscription_renewal_replay_is_idempotent_and_does_not_extend_multiple_times(): void
    {
        $oldExpiresAt = $this->vendor->subscription_expires_at;

        $this->actingAs($this->user)->post(route('admin.subscription.renew'), [
            'subscription_plan_id' => $this->plan->id,
            'period_months' => 6,
            'payment_method' => 'arca',
        ]);

        $subPayment = SubscriptionPayment::where('vendor_id', $this->vendor->id)->latest()->first();
        $attempt = PaymentAttempt::where('subscription_id', $subPayment->id)->first();

        $payload = [
            'orderNumber' => $attempt->merchant_reference,
            'orderId' => 'ARCA-SUB-REPLAY-1',
            'status' => 2,
            'amount' => ((int) round($attempt->amount)) * 100,
        ];

        // First webhook confirmation: extends subscription
        $res1 = $this->post(route('api.webhooks.payment', ['gateway' => 'arca']), $payload);
        $res1->assertStatus(200);

        $this->vendor->refresh();
        $firstExtendedExpiry = $this->vendor->subscription_expires_at;

        // Replay identical webhook 3 more times
        for ($i = 0; $i < 3; $i++) {
            $replayRes = $this->post(route('api.webhooks.payment', ['gateway' => 'arca']), $payload);
            $replayRes->assertStatus(200);
        }

        $this->vendor->refresh();
        // Vendor expiry must remain identical to the first extension (NOT extended 4 times!)
        $this->assertEquals($firstExtendedExpiry->timestamp, $this->vendor->subscription_expires_at->timestamp);
    }

    public function test_client_cannot_tamper_with_amount_or_status_in_order_submission(): void
    {
        // Attacker attempts to post client-dictated price 1 AMD instead of product price 5000 AMD
        $orderPayload = [
            'type' => 'dine_in',
            'location_id' => $this->location->id,
            'table_number' => '5',
            'customer_name' => 'Attacker',
            'payment_method' => 'idram',
            'total_amount' => 1, // Tampered client total
            'payment_status' => 'paid', // Tampered client status
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'price' => 1, // Tampered item price
                ],
            ],
        ];

        $response = $this->postJson("/api/m/{$this->vendor->slug}/order", $orderPayload);
        $response->assertStatus(200);

        $orderId = $response->json('order_id');
        $order = Order::find($orderId);

        // Server authoritative calculation overrides client tampering
        $this->assertEquals(5000.00, (float) $order->total_amount);
        $this->assertEquals('pending', $order->payment_status);

        $attempt = PaymentAttempt::where('order_id', $order->id)->first();
        $this->assertEquals(5000.00, (float) $attempt->amount);
        $this->assertEquals(PaymentStatus::Pending, $attempt->status);
    }

    public function test_offline_payment_cannot_be_confirmed_online(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-OFFLINE-1',
            'subtotal' => 5000,
            'total_amount' => 5000,
            'status' => 'pending',
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'type' => 'dine_in',
        ]);

        $paymentService = app(PaymentService::class);
        $result = $paymentService->initiateOrderPayment($order, 'cash');
        $this->assertEquals('offline', $result->type);

        $attempt = PaymentAttempt::where('order_id', $order->id)->first();

        // Attempting to confirm offline payment online fails
        $verificationService = app(PaymentVerificationService::class);
        $verifyResult = $verificationService->verifyAndFinalizeAttempt($attempt, ['status' => 'paid']);

        $this->assertFalse($verifyResult->success);
        $order->refresh();
        $this->assertNotEquals('paid', $order->payment_status);
        $this->assertEquals(PaymentStatus::Failed, $attempt->fresh()->status);
    }
}
