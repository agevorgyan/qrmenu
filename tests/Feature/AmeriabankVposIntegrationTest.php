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
use App\Services\Payments\Gateways\ArcaGateway;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AmeriabankVposIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected User $user;

    protected Location $location;

    protected Category $category;

    protected Product $product;

    protected SubscriptionPlan $plan;

    protected string $clientId = 'ameria-client-12345';

    protected string $username = 'ameria_api_user';

    protected string $password = 'ameria_secret_pass';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        $this->plan = SubscriptionPlan::where('slug', 'pro')->first();

        $this->vendor = Vendor::create([
            'name' => 'Ameria Gourmet Restaurant',
            'slug' => 'ameria-gourmet',
            'email' => 'contact@ameriagourmet.am',
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
                    'arca' => [
                        'enabled' => true,
                        'merchant_id' => $this->clientId,
                        'username' => $this->username,
                        'secret_key' => $this->password,
                        'terminal_id' => 'TERM_99',
                        'sandbox' => true,
                    ],
                ],
            ],
        ]);

        $this->user = User::create([
            'name' => 'Ameria Admin',
            'email' => 'contact@ameriagourmet.am',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Terrace',
            'slug' => 'main-terrace',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'name' => 'Kitchen',
            'slug' => 'kitchen',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Dinner Dish',
            'slug' => 'dinner-dish',
            'price' => 7500,
            'is_active' => true,
        ]);
    }

    protected function createTestOrder(float $amount = 7500): Order
    {
        return Order::create([
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
            'order_number' => 'ORD-'.strtoupper(Str::random(6)),
            'subtotal' => $amount,
            'total_amount' => $amount,
            'status' => 'pending',
            'payment_method' => 'arca',
            'payment_status' => 'pending',
            'type' => 'dine_in',
        ]);
    }

    /**
     * Test InitPayment initiates order registration in Ameriabank vPOS 3.0.
     * Section 1 of documentation.
     */
    public function test_ameriabank_init_payment_success(): void
    {
        Http::fake([
            'https://servicestest.ameriabank.am/VPOS/api/VPOS/InitPayment' => Http::response([
                'PaymentID' => 'b3201a82-dc65-430c-9c46-d1bf294c5770',
                'ResponseCode' => 1,
                'ResponseMessage' => 'Success',
            ], 200),
        ]);

        app()->setLocale('hy');
        $order = $this->createTestOrder(7500);
        $paymentService = app(PaymentService::class);
        $result = $paymentService->initiateOrderPayment($order, 'arca');

        $this->assertTrue($result->success);
        $this->assertEquals('redirect', $result->type);
        $this->assertEquals('b3201a82-dc65-430c-9c46-d1bf294c5770', $result->providerTransactionId);

        // Verification of redirect URL structure according to Section 1:
        // https://servicestest.ameriabank.am/VPOS/Payments/Pay?id=@id&lang=@lang
        $expectedPayUrl = 'https://servicestest.ameriabank.am/VPOS/Payments/Pay?id=b3201a82-dc65-430c-9c46-d1bf294c5770&lang=am';
        $this->assertEquals($expectedPayUrl, $result->redirectUrl);

        // Verify request payload sent to Ameriabank
        Http::assertSent(function ($request) use ($order) {
            $data = $request->data();

            return $request->url() === 'https://servicestest.ameriabank.am/VPOS/api/VPOS/InitPayment'
                && $data['ClientID'] === $this->clientId
                && $data['Username'] === $this->username
                && $data['Password'] === $this->password
                && $data['Amount'] === 7500.0
                && $data['Currency'] === '051'
                && $data['Opaque'] === $order->latestPaymentAttempt->merchant_reference;
        });
    }

    /**
     * Test client return callback queries GetPaymentDetails from Ameriabank.
     * Section 2 of documentation.
     */
    public function test_ameriabank_verify_callback_with_get_payment_details_success(): void
    {
        $order = $this->createTestOrder(7500);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'arca');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();
        $attempt->update(['provider_transaction_id' => 'b3201a82-dc65-430c-9c46-d1bf294c5770']);

        Http::fake([
            'https://servicestest.ameriabank.am/VPOS/api/VPOS/GetPaymentDetails' => Http::response([
                'PaymentID' => 'b3201a82-dc65-430c-9c46-d1bf294c5770',
                'ResponseCode' => '00',
                'OrderStatus' => 2, // payment_deposited
                'PaymentState' => 'payment_deposited',
                'Amount' => 7500.0,
                'DepositedAmount' => 7500.0,
                'ApprovalCode' => '422841',
                'CardNumber' => '408306**3143',
                'ClientName' => 'Cardholder Name',
                'Currency' => '051',
                'rrn' => '123456789012',
                'Opaque' => $attempt->merchant_reference,
            ], 200),
        ]);

        // Customer returns from Ameriabank to BackURL
        $response = $this->get(route('client.payment.callback', [
            'vendor_slug' => $this->vendor->slug,
            'reference' => $attempt->merchant_reference,
            'paymentID' => 'b3201a82-dc65-430c-9c46-d1bf294c5770',
            'orderID' => $attempt->id,
            'resposneCode' => '00',
            'opaque' => $attempt->merchant_reference,
        ]));

        $response->assertRedirect();

        $attempt->refresh();
        $order->refresh();

        $this->assertEquals(PaymentStatus::Paid, $attempt->status);
        $this->assertEquals('paid', $order->payment_status);
        $this->assertNotNull($attempt->verified_at);

        // Verify GetPaymentDetails was invoked with credentials
        Http::assertSent(function ($request) {
            return $request->url() === 'https://servicestest.ameriabank.am/VPOS/api/VPOS/GetPaymentDetails'
                && $request['PaymentID'] === 'b3201a82-dc65-430c-9c46-d1bf294c5770'
                && $request['Username'] === $this->username
                && $request['Password'] === $this->password;
        });
    }

    /**
     * Test client return callback when payment is declined (OrderStatus == 6).
     */
    public function test_ameriabank_verify_callback_declined(): void
    {
        $order = $this->createTestOrder(7500);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'arca');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();
        $attempt->update(['provider_transaction_id' => 'declined-payment-id-123']);

        Http::fake([
            'https://servicestest.ameriabank.am/VPOS/api/VPOS/GetPaymentDetails' => Http::response([
                'PaymentID' => 'declined-payment-id-123',
                'ResponseCode' => '0116', // Not enough money
                'OrderStatus' => 6, // payment_declined
                'PaymentState' => 'payment_declined',
                'Amount' => 7500.0,
                'Description' => 'Decline. Not enough money',
            ], 200),
        ]);

        $response = $this->get(route('client.payment.callback', [
            'vendor_slug' => $this->vendor->slug,
            'reference' => $attempt->merchant_reference,
            'paymentID' => 'declined-payment-id-123',
            'resposneCode' => '0116',
        ]));

        $response->assertRedirect();

        $attempt->refresh();
        $order->refresh();

        $this->assertEquals(PaymentStatus::Failed, $attempt->status);
        $this->assertEquals('failed', $order->payment_status);
    }

    /**
     * Test two-stage preauthorization (OrderStatus == 1) confirms payment automatically.
     * Section 3: ConfirmPayment.
     */
    public function test_ameriabank_two_stage_preauthorization_confirm_payment(): void
    {
        $order = $this->createTestOrder(7500);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'arca');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();
        $attempt->update(['provider_transaction_id' => 'two-stage-payment-id-456']);

        Http::fake([
            'https://servicestest.ameriabank.am/VPOS/api/VPOS/GetPaymentDetails' => Http::response([
                'PaymentID' => 'two-stage-payment-id-456',
                'ResponseCode' => '00',
                'OrderStatus' => 1, // payment_approved (preauthorized hold)
                'PaymentState' => 'payment_approved',
                'ApprovedAmount' => 7500.0,
                'Amount' => 7500.0,
            ], 200),
            'https://servicestest.ameriabank.am/VPOS/api/VPOS/ConfirmPayment' => Http::response([
                'ResponseCode' => '00',
                'ResponseMessage' => 'Payment Confirmed',
                'Opaque' => $attempt->merchant_reference,
            ], 200),
        ]);

        $gateway = app(PaymentGatewayManager::class)->gateway('arca');
        $result = $gateway->verifyCallback($attempt, [
            'paymentID' => 'two-stage-payment-id-456',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals(PaymentStatus::Paid, $result->status);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://servicestest.ameriabank.am/VPOS/api/VPOS/ConfirmPayment'
                && $request['PaymentID'] === 'two-stage-payment-id-456'
                && $request['Amount'] == 7500.0;
        });
    }

    /**
     * Test partial refund via RefundPayment (Section 5).
     */
    public function test_ameriabank_refund_payment(): void
    {
        Http::fake([
            'https://servicestest.ameriabank.am/VPOS/api/VPOS/RefundPayment' => Http::response([
                'ResponseCode' => '00',
                'ResponseMessage' => 'Refund Successful',
            ], 200),
        ]);

        $order = $this->createTestOrder(7500);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'arca');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();
        $attempt->update([
            'status' => PaymentStatus::Paid,
            'provider_transaction_id' => 'refund-test-payment-id',
        ]);

        /** @var ArcaGateway $gateway */
        $gateway = app(PaymentGatewayManager::class)->gateway('arca');
        $result = $gateway->refundPayment($attempt, 2500);

        $this->assertTrue($result->success);
        $this->assertEquals(PaymentStatus::Refunded, $result->status);
        $this->assertEquals(2500, $result->amount);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://servicestest.ameriabank.am/VPOS/api/VPOS/RefundPayment'
                && $request['PaymentID'] === 'refund-test-payment-id'
                && $request['Amount'] == 2500.0;
        });
    }

    /**
     * Test cancellation of preauthorized payment via CancelPayment (Section 4).
     */
    public function test_ameriabank_cancel_payment(): void
    {
        Http::fake([
            'https://servicestest.ameriabank.am/VPOS/api/VPOS/CancelPayment' => Http::response([
                'ResponseCode' => '00',
                'ResponseMessage' => 'Payment Cancelled',
            ], 200),
        ]);

        $order = $this->createTestOrder(7500);
        $paymentService = app(PaymentService::class);
        $paymentService->initiateOrderPayment($order, 'arca');
        $attempt = PaymentAttempt::where('order_id', $order->id)->first();
        $attempt->update([
            'provider_transaction_id' => 'cancel-test-payment-id',
        ]);

        /** @var ArcaGateway $gateway */
        $gateway = app(PaymentGatewayManager::class)->gateway('arca');
        $result = $gateway->cancelPayment($attempt);

        $this->assertFalse($result->success);
        $this->assertEquals(PaymentStatus::Cancelled, $result->status);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://servicestest.ameriabank.am/VPOS/api/VPOS/CancelPayment'
                && $request['PaymentID'] === 'cancel-test-payment-id';
        });
    }

    /**
     * Test that PaymentGatewayManager resolves 'ameriabank' and 'vpos' aliases to ArcaGateway.
     */
    public function test_payment_gateway_manager_resolves_ameriabank_aliases(): void
    {
        $manager = app(PaymentGatewayManager::class);

        $this->assertTrue($manager->hasGateway('arca'));
        $this->assertTrue($manager->hasGateway('ameriabank'));
        $this->assertTrue($manager->hasGateway('vpos'));

        $this->assertInstanceOf(ArcaGateway::class, $manager->gateway('ameriabank'));
        $this->assertInstanceOf(ArcaGateway::class, $manager->gateway('vpos'));
        $this->assertSame($manager->gateway('arca'), $manager->gateway('ameriabank'));
    }
}
