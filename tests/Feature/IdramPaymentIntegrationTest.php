<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Payments\PaymentService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdramPaymentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected User $user;

    protected Location $location;

    protected Category $category;

    protected Product $product;

    protected SubscriptionPlan $plan;

    protected string $secretKey = 'idram-official-secret-key-1234';

    protected string $merchantId = '110000888';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        $this->plan = SubscriptionPlan::where('slug', 'pro')->first();

        $this->vendor = Vendor::create([
            'name' => 'Idram Restaurant Test',
            'slug' => 'idram-restaurant-test',
            'email' => 'idram-merchant@example.com',
            'password' => bcrypt('password123'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $this->plan->id,
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
            'payment_settings' => [
                'cash_enabled' => true,
                'pos_terminal_enabled' => true,
                'online_enabled' => true,
                'gateways' => [
                    'idram' => [
                        'enabled' => true,
                        'merchant_id' => $this->merchantId,
                        'secret_key' => $this->secretKey,
                        'sandbox' => false,
                    ],
                ],
            ],
        ]);

        $this->user = User::create([
            'name' => 'Merchant Admin',
            'email' => 'idram-merchant@example.com',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Hall',
            'slug' => 'main-hall',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Drinks',
            'slug' => 'drinks',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Coffee',
            'slug' => 'coffee',
            'price' => 1900,
            'is_active' => true,
        ]);
    }

    protected function createTestOrder(float $amount = 1900): Order
    {
        return Order::create([
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
    }

    /**
     * Test Idram Web initiation parameters match Section 2 of Idram documentation.
     */
    public function test_idram_web_initiation_generates_correct_form_parameters_and_url(): void
    {
        app()->setLocale('hy');
        $order = $this->createTestOrder(1900);
        $paymentService = app(PaymentService::class);
        $result = $paymentService->initiateOrderPayment($order, 'idram');

        $this->assertTrue($result->success);
        $this->assertEquals('redirect', $result->type);

        // Verify official Idram URL
        $this->assertStringStartsWith('https://web.idram.am/payment.aspx', $result->redirectUrl);

        // Verify required hidden form fields
        $data = $result->gatewayData;
        $this->assertEquals('AM', $data['EDP_LANGUAGE']);
        $this->assertEquals($this->merchantId, $data['EDP_REC_ACCOUNT']);
        $this->assertEquals('1900.00', $data['EDP_AMOUNT']);
        $this->assertNotNull($data['EDP_BILL_NO']);
        $this->assertEquals($order->latestPaymentAttempt->merchant_reference, $data['EDP_BILL_NO']);
        $this->assertStringContainsString('idram-merchant@example.com', $data['EDP_EMAIL']);
        $this->assertStringContainsString("Order #{$order->order_number}", $data['EDP_DESCRIPTION']);

        // Verify required callbacks
        $this->assertArrayHasKey('success_url', $data);
        $this->assertArrayHasKey('fail_url', $data);
        $this->assertArrayHasKey('result_url', $data);
    }

    /**
     * Test Idram Order Authenticity Confirmation (preliminary request EDP_PRECHECK = "YES").
     * Must return plain text "OK" without HTML formatting and keep attempt in pending status.
     */
    public function test_idram_order_authenticity_confirmation_precheck_successful(): void
    {
        $order = $this->createTestOrder(2500);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'idram');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();

        $response = $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), [
            'EDP_PRECHECK' => 'YES',
            'EDP_BILL_NO' => $attempt->merchant_reference,
            'EDP_REC_ACCOUNT' => $this->merchantId,
            'EDP_AMOUNT' => '2500.00',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('OK', $response->getContent());
        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));

        // Attempt and order must NOT be marked as paid yet during preliminary check
        $attempt->refresh();
        $order->refresh();
        $this->assertEquals(PaymentStatus::Pending, $attempt->status);
        $this->assertEquals('pending', $order->payment_status);
    }

    /**
     * Test Idram precheck fails if bill number does not exist.
     */
    public function test_idram_precheck_fails_if_bill_no_not_found(): void
    {
        $response = $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), [
            'EDP_PRECHECK' => 'YES',
            'EDP_BILL_NO' => 'NON-EXISTENT-BILL-12345',
            'EDP_REC_ACCOUNT' => $this->merchantId,
            'EDP_AMOUNT' => '2500.00',
        ]);

        $response->assertStatus(400);
        $this->assertNotEquals('OK', $response->getContent());
    }

    /**
     * Test Idram precheck fails if amount does not match authoritative order amount.
     */
    public function test_idram_precheck_fails_if_amount_mismatches(): void
    {
        $order = $this->createTestOrder(5000);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'idram');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();

        $response = $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), [
            'EDP_PRECHECK' => 'YES',
            'EDP_BILL_NO' => $attempt->merchant_reference,
            'EDP_REC_ACCOUNT' => $this->merchantId,
            'EDP_AMOUNT' => '1000.00', // Underpayment tampering
        ]);

        $response->assertStatus(400);
        $this->assertNotEquals('OK', $response->getContent());
    }

    /**
     * Test Idram precheck fails if merchant account does not match.
     */
    public function test_idram_precheck_fails_if_rec_account_mismatches(): void
    {
        $order = $this->createTestOrder(5000);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'idram');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();

        $response = $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), [
            'EDP_PRECHECK' => 'YES',
            'EDP_BILL_NO' => $attempt->merchant_reference,
            'EDP_REC_ACCOUNT' => '999999999', // Rogue account
            'EDP_AMOUNT' => '5000.00',
        ]);

        $response->assertStatus(400);
        $this->assertNotEquals('OK', $response->getContent());
    }

    /**
     * Test Idram Payment Confirmation with official 7-field checksum.
     * Checksum: EDP_REC_ACCOUNT:EDP_AMOUNT:SECRET_KEY:EDP_BILL_NO:EDP_PAYER_ACCOUNT:EDP_TRANS_ID:EDP_TRANS_DATE
     */
    public function test_idram_payment_confirmation_with_official_7_field_checksum(): void
    {
        $order = $this->createTestOrder(3500);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'idram');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();

        $payerAccount = '200000555';
        $transId = '14000000000001';
        $transDate = '10/11/2026';
        $amount = '3500.00';

        // Official checksum formula per documentation
        $txtToHash = "{$this->merchantId}:{$amount}:{$this->secretKey}:{$attempt->merchant_reference}:{$payerAccount}:{$transId}:{$transDate}";
        $checksum = strtoupper(md5($txtToHash));

        $response = $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), [
            'EDP_BILL_NO' => $attempt->merchant_reference,
            'EDP_REC_ACCOUNT' => $this->merchantId,
            'EDP_PAYER_ACCOUNT' => $payerAccount,
            'EDP_AMOUNT' => $amount,
            'EDP_TRANS_ID' => $transId,
            'EDP_TRANS_DATE' => $transDate,
            'EDP_CHECKSUM' => $checksum,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('OK', $response->getContent());

        $attempt->refresh();
        $order->refresh();

        $this->assertEquals(PaymentStatus::Paid, $attempt->status);
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals($transId, $attempt->provider_transaction_id);
        $this->assertNotNull($attempt->verified_at);
    }

    /**
     * Test Idram Payment Confirmation rejects invalid checksum.
     */
    public function test_idram_payment_confirmation_rejects_corrupted_checksum(): void
    {
        $order = $this->createTestOrder(3500);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'idram');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();

        $response = $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), [
            'EDP_BILL_NO' => $attempt->merchant_reference,
            'EDP_REC_ACCOUNT' => $this->merchantId,
            'EDP_PAYER_ACCOUNT' => '200000555',
            'EDP_AMOUNT' => '3500.00',
            'EDP_TRANS_ID' => '14000000000002',
            'EDP_TRANS_DATE' => '10/11/2026',
            'EDP_CHECKSUM' => 'CORRUPTED_CHECKSUM_VALUE',
        ]);

        $response->assertStatus(400);

        $attempt->refresh();
        $this->assertNotEquals(PaymentStatus::Paid, $attempt->status);
    }

    /**
     * Test Idram Payment Confirmation is idempotent on duplicate delivery.
     */
    public function test_idram_payment_confirmation_is_idempotent(): void
    {
        $order = $this->createTestOrder(4200);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'idram');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();

        $payerAccount = '200000777';
        $transId = '14000000000099';
        $transDate = '10/11/2026';
        $amount = '4200.00';

        $txtToHash = "{$this->merchantId}:{$amount}:{$this->secretKey}:{$attempt->merchant_reference}:{$payerAccount}:{$transId}:{$transDate}";
        $checksum = strtoupper(md5($txtToHash));

        $payload = [
            'EDP_BILL_NO' => $attempt->merchant_reference,
            'EDP_REC_ACCOUNT' => $this->merchantId,
            'EDP_PAYER_ACCOUNT' => $payerAccount,
            'EDP_AMOUNT' => $amount,
            'EDP_TRANS_ID' => $transId,
            'EDP_TRANS_DATE' => $transDate,
            'EDP_CHECKSUM' => $checksum,
        ];

        // First delivery: returns OK 200
        $res1 = $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), $payload);
        $res1->assertStatus(200);
        $this->assertEquals('OK', $res1->getContent());

        $attempt->refresh();
        $this->assertEquals(PaymentStatus::Paid, $attempt->status);
        $verifiedAt = $attempt->verified_at;

        // Second delivery (idempotent duplicate): returns OK 200 without error
        $res2 = $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), $payload);
        $res2->assertStatus(200);
        $this->assertEquals('OK', $res2->getContent());

        $attempt->refresh();
        $this->assertEquals(PaymentStatus::Paid, $attempt->status);
        $this->assertEquals($verifiedAt->timestamp, $attempt->verified_at->timestamp);
    }

    /**
     * Test Idram payment confirmation checksum with unpadded integer amount string.
     */
    public function test_idram_payment_confirmation_accepts_raw_amount_checksum(): void
    {
        $order = $this->createTestOrder(5000);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'idram');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();

        $payerAccount = '200000888';
        $transId = '14000000000888';
        $transDate = '10/11/2026';
        $rawAmount = '5000'; // Sent without .00 decimals

        $txtToHash = "{$this->merchantId}:{$rawAmount}:{$this->secretKey}:{$attempt->merchant_reference}:{$payerAccount}:{$transId}:{$transDate}";
        $checksum = strtoupper(md5($txtToHash));

        $response = $this->post(route('api.webhooks.payment', ['gateway' => 'idram']), [
            'EDP_BILL_NO' => $attempt->merchant_reference,
            'EDP_REC_ACCOUNT' => $this->merchantId,
            'EDP_PAYER_ACCOUNT' => $payerAccount,
            'EDP_AMOUNT' => $rawAmount,
            'EDP_TRANS_ID' => $transId,
            'EDP_TRANS_DATE' => $transDate,
            'EDP_CHECKSUM' => $checksum,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('OK', $response->getContent());

        $attempt->refresh();
        $this->assertEquals(PaymentStatus::Paid, $attempt->status);
    }
}
