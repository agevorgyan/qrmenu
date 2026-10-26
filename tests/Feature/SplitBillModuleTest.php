<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\SplitBill;
use App\Models\SplitBillPayment;
use App\Models\SubscriptionPlan;
use App\Models\TenantFeatureOverride;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Payments\PaymentVerificationService;
use App\Services\SplitBillService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class SplitBillModuleTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor1;

    protected Location $location1;

    protected User $owner1;

    protected User $waiter1;

    protected Product $burger;

    protected Product $fries;

    protected Vendor $vendor2;

    protected Location $location2;

    protected User $owner2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        $proPlan = SubscriptionPlan::where('slug', 'pro')->first();
        $basicPlan = SubscriptionPlan::where('slug', 'basic')->first();

        // Vendor 1 (Pro Plan with bills.split entitled)
        $this->vendor1 = Vendor::create([
            'name' => 'Bistro Alpha',
            'slug' => 'bistro-alpha',
            'email' => 'alpha@bistro.am',
            'password' => bcrypt('password'),
            'subscription_plan_id' => $proPlan?->id,
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
            'is_active' => true,
            'currency' => 'AMD',
        ]);

        $this->location1 = Location::create([
            'vendor_id' => $this->vendor1->id,
            'name' => 'Alpha Main',
            'slug' => 'main',
            'is_active' => true,
        ]);

        $this->owner1 = User::create([
            'vendor_id' => $this->vendor1->id,
            'location_id' => $this->location1->id,
            'name' => 'Owner Alpha',
            'email' => 'owner@alpha.am',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->waiter1 = User::create([
            'vendor_id' => $this->vendor1->id,
            'location_id' => $this->location1->id,
            'name' => 'Waiter John',
            'email' => 'john@alpha.am',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'is_active' => true,
        ]);

        $category1 = Category::create([
            'vendor_id' => $this->vendor1->id,
            'name' => 'Food',
            'is_active' => true,
            'order' => 1,
        ]);

        $this->burger = Product::create([
            'vendor_id' => $this->vendor1->id,
            'category_id' => $category1->id,
            'name' => 'Cheeseburger',
            'price' => 3000,
            'is_active' => true,
        ]);

        $this->fries = Product::create([
            'vendor_id' => $this->vendor1->id,
            'category_id' => $category1->id,
            'name' => 'French Fries',
            'price' => 1000,
            'is_active' => true,
        ]);

        // Vendor 2 (Basic Plan)
        $this->vendor2 = Vendor::create([
            'name' => 'Cafe Beta',
            'slug' => 'cafe-beta',
            'email' => 'beta@cafe.am',
            'password' => bcrypt('password'),
            'subscription_plan_id' => $basicPlan?->id,
            'subscription_plan' => 'basic',
            'subscription_status' => 'active',
            'is_active' => true,
            'currency' => 'AMD',
        ]);

        $this->location2 = Location::create([
            'vendor_id' => $this->vendor2->id,
            'name' => 'Beta Downtown',
            'slug' => 'downtown',
            'is_active' => true,
        ]);

        $this->owner2 = User::create([
            'vendor_id' => $this->vendor2->id,
            'location_id' => $this->location2->id,
            'name' => 'Owner Beta',
            'email' => 'owner@beta.am',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);
    }

    protected function createSampleOrder(Vendor $vendor, Location $location, float $subtotal = 7000, float $serviceFee = 700): Order
    {
        $order = Order::create([
            'vendor_id' => $vendor->id,
            'location_id' => $location->id,
            'order_number' => 'ORD-'.strtoupper(Str::random(6)),
            'tracking_token' => Order::generateTrackingToken(),
            'table_number' => '5',
            'type' => 'dine_in',
            'subtotal' => $subtotal,
            'service_fee' => $serviceFee,
            'delivery_fee' => 0,
            'tip_amount' => 0,
            'total_amount' => $subtotal + $serviceFee,
            'status' => 'accepted',
            'payment_status' => 'unpaid',
            'payment_method' => 'cash',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->burger->id,
            'product_name' => $this->burger->name,
            'unit_price' => 3000,
            'quantity' => 2,
            'subtotal' => 6000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->fries->id,
            'product_name' => $this->fries->name,
            'unit_price' => 1000,
            'quantity' => 1,
            'subtotal' => 1000,
        ]);

        return $order;
    }

    public function test_split_bill_is_disabled_by_default_and_rejects_storefront_calls(): void
    {
        $order = $this->createSampleOrder($this->vendor1, $this->location1);

        // Settings row is created with is_enabled = false by default
        $settings = $this->vendor1->getSplitBillSetting();
        $this->assertFalse($settings->is_enabled);
        $this->assertFalse($this->vendor1->isSplitBillEnabled());

        // Storefront API rejects requests when disabled
        $res = $this->getJson(route('client.bill.status', [
            'vendor_slug' => $this->vendor1->slug,
            'token' => $order->tracking_token,
        ]));

        $res->assertStatus(403);
        $res->assertJson(['error' => 'SPLIT_BILL_DISABLED']);

        // Web view redirects back with error notice
        $webRes = $this->get(route('client.bill.show', [
            'vendor_slug' => $this->vendor1->slug,
            'token' => $order->tracking_token,
        ]));

        $webRes->assertRedirect(route('client.menu', ['vendor_slug' => $this->vendor1->slug]));
    }

    public function test_tenant_settings_isolation(): void
    {
        // Enable on Vendor 1
        $this->vendor1->getSplitBillSetting()->update([
            'is_enabled' => true,
            'allow_equal_split' => true,
            'max_participants' => 12,
        ]);

        $this->assertTrue($this->vendor1->isSplitBillEnabled());
        $this->assertFalse($this->vendor2->isSplitBillEnabled());
        $this->assertEquals(12, $this->vendor1->getSplitBillSetting()->max_participants);
        $this->assertEquals(10, $this->vendor2->getSplitBillSetting()->max_participants);
    }

    public function test_subscription_entitlement_enforcement_and_overrides(): void
    {
        // Pro vendor allowed
        $resPro = $this->actingAs($this->owner1)->get(route('admin.settings.split_bill'));
        $resPro->assertStatus(200);

        // Basic vendor denied (redirects on web, 403 on json)
        $resBasic = $this->actingAs($this->owner2)->get(route('admin.settings.split_bill'));
        $resBasic->assertRedirect(route('admin.subscription'));
        $this->actingAs($this->owner2)->getJson(route('admin.settings.split_bill'))->assertStatus(403);

        // Grant override to Basic vendor
        TenantFeatureOverride::create([
            'vendor_id' => $this->vendor2->id,
            'feature_key' => 'bills.split',
            'is_enabled' => true,
            'reason' => 'Trial extension',
        ]);

        $resBasicAllowed = $this->actingAs($this->owner2)->get(route('admin.settings.split_bill'));
        $resBasicAllowed->assertStatus(200);
    }

    public function test_equal_split_calculation_rounding_and_exact_balance_matching(): void
    {
        $this->vendor1->getSplitBillSetting()->update([
            'is_enabled' => true,
            'max_participants' => 10,
        ]);

        $order = $this->createSampleOrder($this->vendor1, $this->location1, subtotal: 9000, serviceFee: 1000); // Total: 10,000 AMD
        $service = app(SplitBillService::class);
        $bill = $service->getOrCreateSplitBill($order);

        // 10,000 AMD divided by 3 people
        $calc3 = $service->calculateEqualSplit($bill, 3);
        $this->assertEquals(10000, $calc3['total_amount']);
        $this->assertCount(3, $calc3['shares']);
        $this->assertEquals(3334, $calc3['shares'][0]);
        $this->assertEquals(3333, $calc3['shares'][1]);
        $this->assertEquals(3333, $calc3['shares'][2]);
        $this->assertEquals(10000, array_sum($calc3['shares']), 'Sum of shares must exactly equal 10,000');

        // 10,000 AMD divided by 7 people
        $calc7 = $service->calculateEqualSplit($bill, 7);
        $this->assertCount(7, $calc7['shares']);
        $this->assertEquals(10000, array_sum($calc7['shares']), 'Sum of 7 shares must exactly equal 10,000');

        // Participants out of bounds throws exception
        $this->expectException(InvalidArgumentException::class);
        $service->calculateEqualSplit($bill, 1);
    }

    public function test_itemized_split_availability_and_double_claim_prevention(): void
    {
        $this->vendor1->getSplitBillSetting()->update([
            'is_enabled' => true,
            'allow_item_split' => true,
        ]);

        // Order: 2x Burgers (3,000 ea), 1x Fries (1,000) = 7,000 subtotal + 700 service fee = 7,700 total
        $order = $this->createSampleOrder($this->vendor1, $this->location1, subtotal: 7000, serviceFee: 700);
        $service = app(SplitBillService::class);
        $bill = $service->getOrCreateSplitBill($order);

        $avail1 = $service->getItemizedAvailability($order);
        $burgerItem = collect($avail1)->firstWhere('name', 'Cheeseburger');
        $this->assertEquals(2, $burgerItem['available_quantity']);

        // Participant A claims 1x Burger via Cash
        $itemPayloadA = [
            'split_type' => 'by_item',
            'payment_method' => 'cash',
            'participant_name' => 'Alice',
            'items' => [
                [
                    'order_item_id' => $burgerItem['order_item_id'],
                    'quantity' => 1,
                ],
            ],
        ];

        $resA = $this->postJson(route('client.bill.split', [
            'vendor_slug' => $this->vendor1->slug,
            'token' => $order->tracking_token,
        ]), $itemPayloadA);

        $resA->assertStatus(200);
        $resA->assertJson(['success' => true]);

        // Verify remaining available burgers is now 1
        $avail2 = $service->getItemizedAvailability($order);
        $burgerItemAfter = collect($avail2)->firstWhere('name', 'Cheeseburger');
        $this->assertEquals(1, $burgerItemAfter['available_quantity']);

        // Participant B attempts to claim 2x Burgers (only 1 available!) -> Rejected
        $itemPayloadBOver = [
            'split_type' => 'by_item',
            'payment_method' => 'cash',
            'participant_name' => 'Bob',
            'items' => [
                [
                    'order_item_id' => $burgerItem['order_item_id'],
                    'quantity' => 2,
                ],
            ],
        ];

        $resB = $this->postJson(route('client.bill.split', [
            'vendor_slug' => $this->vendor1->slug,
            'token' => $order->tracking_token,
        ]), $itemPayloadBOver);

        $resB->assertStatus(422);
    }

    public function test_custom_amount_validation(): void
    {
        $this->vendor1->getSplitBillSetting()->update([
            'is_enabled' => true,
            'allow_custom_amount' => true,
        ]);

        $order = $this->createSampleOrder($this->vendor1, $this->location1, subtotal: 5000, serviceFee: 0); // Total: 5,000 AMD

        // Reject zero
        $resZero = $this->postJson(route('client.bill.split', [
            'vendor_slug' => $this->vendor1->slug,
            'token' => $order->tracking_token,
        ]), [
            'split_type' => 'custom',
            'payment_method' => 'cash',
            'amount' => 0,
        ]);
        $resZero->assertStatus(422);

        // Reject excessive amount (> 5,000)
        $resExcess = $this->postJson(route('client.bill.split', [
            'vendor_slug' => $this->vendor1->slug,
            'token' => $order->tracking_token,
        ]), [
            'split_type' => 'custom',
            'payment_method' => 'cash',
            'amount' => 6000,
        ]);
        $resExcess->assertStatus(422);

        // Accept valid partial payment (2,000)
        $resValid = $this->postJson(route('client.bill.split', [
            'vendor_slug' => $this->vendor1->slug,
            'token' => $order->tracking_token,
        ]), [
            'split_type' => 'custom',
            'payment_method' => 'cash',
            'amount' => 2000,
        ]);
        $resValid->assertStatus(200);

        $order->refresh();
        $this->assertEquals('partially_paid', $order->payment_status);
        $bill = SplitBill::where('order_id', $order->id)->first();
        $this->assertEquals(2000, $bill->paid_amount);
        $this->assertEquals(3000, $bill->remaining_amount);
    }

    public function test_digital_payment_verification_sync_transitions_order_status(): void
    {
        $this->vendor1->getSplitBillSetting()->update([
            'is_enabled' => true,
            'allow_custom_amount' => true,
        ]);

        $order = $this->createSampleOrder($this->vendor1, $this->location1, subtotal: 10000, serviceFee: 0);
        $service = app(SplitBillService::class);
        $bill = $service->getOrCreateSplitBill($order);

        // 1. Participant A initiates online payment for 4,000 AMD
        $initResultA = $service->initiateSplitPayment(
            bill: $bill,
            amount: 4000,
            paymentMethod: 'idram',
            participantName: 'Guest 1'
        );

        $this->assertTrue($initResultA['success']);
        $paymentA = $initResultA['payment'];
        $this->assertEquals('pending', $paymentA->status);

        $attemptA = PaymentAttempt::find($paymentA->payment_attempt_id);
        $this->assertNotNull($attemptA);

        // Simulate Gateway callback verification for Participant A
        $verificationService = app(PaymentVerificationService::class);
        $service->syncSplitPaymentStatus($attemptA, 'paid');

        $bill->refresh();
        $order->refresh();
        $this->assertEquals(4000, $bill->paid_amount);
        $this->assertEquals(6000, $bill->remaining_amount);
        $this->assertEquals('partially_paid', $order->payment_status, 'Order must be partially_paid after first split');
        $this->assertEquals('active', $bill->status);

        // 2. Participant B initiates online payment for remaining 6,000 AMD
        $initResultB = $service->initiateSplitPayment(
            bill: $bill,
            amount: 6000,
            paymentMethod: 'idram',
            participantName: 'Guest 2'
        );

        $paymentB = $initResultB['payment'];
        $attemptB = PaymentAttempt::find($paymentB->payment_attempt_id);

        // Finalize Attempt B
        $service->syncSplitPaymentStatus($attemptB, 'paid');

        $bill->refresh();
        $order->refresh();
        $this->assertEquals(10000, $bill->paid_amount);
        $this->assertEquals(0, $bill->remaining_amount);
        $this->assertEquals('completed', $bill->status, 'Bill must be completed once 100% paid');
        $this->assertEquals('paid', $order->payment_status, 'Order must transition to paid once remaining reaches 0');
    }

    public function test_staff_approval_workflow_for_cash_split_payments(): void
    {
        $this->vendor1->getSplitBillSetting()->update([
            'is_enabled' => true,
            'allow_custom_amount' => true,
            'require_staff_approval' => true,
        ]);

        $order = $this->createSampleOrder($this->vendor1, $this->location1, subtotal: 6000, serviceFee: 0);

        // Customer initiates 3,000 cash split
        $res = $this->postJson(route('client.bill.split', [
            'vendor_slug' => $this->vendor1->slug,
            'token' => $order->tracking_token,
        ]), [
            'split_type' => 'custom',
            'payment_method' => 'cash',
            'amount' => 3000,
            'participant_name' => 'Table Guest',
        ]);

        $res->assertStatus(200);
        $res->assertJson(['requires_approval' => true]);

        $payment = SplitBillPayment::where('order_id', $order->id)->latest()->first();
        $this->assertEquals('pending', $payment->status);

        // Bill remaining balance has NOT decreased yet because payment is pending staff approval
        $bill = SplitBill::where('order_id', $order->id)->first();
        $this->assertEquals(0, $bill->paid_amount);
        $this->assertEquals(6000, $bill->remaining_amount);

        // Staff approves cash payment
        $approveRes = $this->actingAs($this->waiter1)->post(route('admin.split_bill.approve_payment', $payment));
        $approveRes->assertStatus(302);

        $payment->refresh();
        $bill->refresh();
        $this->assertEquals('paid', $payment->status);
        $this->assertEquals($this->waiter1->id, $payment->approved_by);
        $this->assertEquals(3000, $bill->paid_amount);
        $this->assertEquals(3000, $bill->remaining_amount);
    }

    public function test_cross_tenant_order_guard_on_split_models(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $orderVendor2 = $this->createSampleOrder($this->vendor2, $this->location2);

        // Attempting to associate Vendor 1 with an order belonging to Vendor 2
        SplitBill::create([
            'vendor_id' => $this->vendor1->id,
            'order_id' => $orderVendor2->id, // Cross-tenant!
            'split_token' => SplitBill::generateSplitToken(),
            'total_amount' => 5000,
            'paid_amount' => 0,
            'remaining_amount' => 5000,
        ]);
    }

    public function test_admin_settings_can_be_updated_and_saved(): void
    {
        // 1. Enable split bill and customize limits
        $response = $this->actingAs($this->owner1)->post(route('admin.settings.split_bill.update'), [
            'is_enabled' => 1,
            'allow_equal_split' => 1,
            'allow_item_split' => 1,
            'allow_custom_amount' => 1,
            'allow_share_link' => 1,
            'max_participants' => 8,
            'require_staff_approval' => 1,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $settings = $this->vendor1->getSplitBillSetting()->fresh();
        $this->assertTrue((bool) $settings->is_enabled);
        $this->assertEquals(8, $settings->max_participants);
        $this->assertTrue((bool) $settings->require_staff_approval);
        $this->assertTrue($this->vendor1->isSplitBillEnabled());

        // 2. Disable split bill without providing max_participants (should not fail validation)
        $disableResponse = $this->actingAs($this->owner1)->post(route('admin.settings.split_bill.update'), [
            'is_enabled' => 0,
        ]);

        $disableResponse->assertStatus(302);
        $disableResponse->assertSessionHasNoErrors();
        $settings->refresh();
        $this->vendor1->refresh();
        $this->assertFalse((bool) $settings->is_enabled);
        $this->assertFalse($this->vendor1->isSplitBillEnabled());
    }

    public function test_admin_order_split_bill_view_renders_cleanly(): void
    {
        $this->vendor1->getSplitBillSetting()->update(['is_enabled' => true]);
        $order = $this->createSampleOrder($this->vendor1, $this->location1);

        $response = $this->actingAs($this->waiter1)->get(route('admin.orders.split_bill', $order));

        $response->assertStatus(200);
        $response->assertViewIs('admin.orders.split_bill_modal');
        $response->assertSee($order->order_number);
    }

    public function test_customer_shared_bill_accessible_via_split_token(): void
    {
        $this->vendor1->getSplitBillSetting()->update(['is_enabled' => true]);
        $order = $this->createSampleOrder($this->vendor1, $this->location1);

        $splitService = app(SplitBillService::class);
        $splitBill = $splitService->getOrCreateSplitBill($order);

        $this->assertStringStartsWith('spt_', $splitBill->split_token);

        // Access web view using split_token (spt_...)
        $webRes = $this->get(route('client.bill.show', [
            'vendor_slug' => $this->vendor1->slug,
            'token' => $splitBill->split_token,
        ]));
        $webRes->assertStatus(200);
        $webRes->assertSee($order->order_number);

        // Access JSON status API using split_token
        $apiRes = $this->getJson(route('client.bill.status', [
            'vendor_slug' => $this->vendor1->slug,
            'token' => $splitBill->split_token,
        ]));
        $apiRes->assertStatus(200);
        $apiRes->assertJson([
            'success' => true,
            'order' => [
                'order_number' => $order->order_number,
            ],
            'bill' => [
                'split_token' => $splitBill->split_token,
            ],
        ]);
    }

    public function test_order_status_api_returns_payment_status_and_method(): void
    {
        $order = $this->createSampleOrder($this->vendor1, $this->location1);

        $res = $this->getJson(route('client.order.status', [
            'vendor_slug' => $this->vendor1->slug,
            'order_number' => $order->tracking_token,
        ]));

        $res->assertStatus(200);
        $res->assertJson([
            'success' => true,
            'order' => [
                'order_number' => $order->order_number,
                'payment_status' => 'unpaid',
                'payment_method' => 'cash',
            ],
        ]);
    }
}
