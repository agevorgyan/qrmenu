<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceRating;
use App\Models\SubscriptionPlan;
use App\Models\TenantFeatureOverride;
use App\Models\Tip;
use App\Models\TipRatingSetting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Payments\PaymentVerificationService;
use App\Services\TipRatingService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TipRatingModuleTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor1;

    protected Location $location1;

    protected User $owner1;

    protected User $waiter1;

    protected Product $product1;

    protected Vendor $vendor2;

    protected Location $location2;

    protected User $owner2;

    protected User $waiter2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        $proPlan = SubscriptionPlan::where('slug', 'pro')->first();
        $basicPlan = SubscriptionPlan::where('slug', 'basic')->first();

        // Vendor 1 (Pro Plan with tips.ratings)
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
            'name' => 'Mains',
            'is_active' => true,
            'order' => 1,
        ]);

        $this->product1 = Product::create([
            'vendor_id' => $this->vendor1->id,
            'category_id' => $category1->id,
            'name' => 'Burger',
            'price' => 3000,
            'is_active' => true,
        ]);

        // Vendor 2 (Basic Plan without tips.ratings)
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
            'name' => 'Beta Branch',
            'slug' => 'branch',
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

        $this->waiter2 = User::create([
            'vendor_id' => $this->vendor2->id,
            'location_id' => $this->location2->id,
            'name' => 'Waiter Beta',
            'email' => 'waiter@beta.am',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'is_active' => true,
        ]);
    }

    public function test_vendor_settings_initialization_and_tenant_isolation(): void
    {
        $setting1 = $this->vendor1->getTipRatingSetting();
        $setting2 = $this->vendor2->getTipRatingSetting();

        $this->assertInstanceOf(TipRatingSetting::class, $setting1);
        $this->assertEquals($this->vendor1->id, $setting1->vendor_id);
        $this->assertEquals($this->vendor2->id, $setting2->vendor_id);
        $this->assertNotEquals($setting1->id, $setting2->id);

        $setting1->update([
            'tip_percentages' => [10, 20, 30],
            'allow_custom_tip' => false,
        ]);

        $setting2->refresh();
        $this->assertNotEquals([10, 20, 30], $setting2->tip_percentages);
    }

    public function test_independent_capability_toggles(): void
    {
        $setting = $this->vendor1->getTipRatingSetting();

        // 1. Module enabled, tips enabled, ratings disabled
        $setting->update([
            'is_enabled' => true,
            'tips_enabled' => true,
            'ratings_enabled' => false,
        ]);
        $this->assertTrue($this->vendor1->isTipsEnabled());
        $this->assertFalse($this->vendor1->isRatingsEnabled());

        // 2. Module enabled, tips disabled, ratings enabled
        $setting->update([
            'is_enabled' => true,
            'tips_enabled' => false,
            'ratings_enabled' => true,
        ]);
        $this->assertFalse($this->vendor1->isTipsEnabled());
        $this->assertTrue($this->vendor1->isRatingsEnabled());

        // 3. Module disabled (both turn false)
        $setting->update([
            'is_enabled' => false,
            'tips_enabled' => true,
            'ratings_enabled' => true,
        ]);
        $this->assertFalse($this->vendor1->isTipsEnabled());
        $this->assertFalse($this->vendor1->isRatingsEnabled());
    }

    public function test_subscription_entitlement_enforcement(): void
    {
        // Pro vendor (allowed)
        $res = $this->actingAs($this->owner1)->get(route('admin.settings.tips_ratings'));
        $res->assertStatus(200);

        // Basic vendor (denied: redirects to subscription with flash warning)
        $resBeta = $this->actingAs($this->owner2)->get(route('admin.settings.tips_ratings'));
        $resBeta->assertRedirect(route('admin.subscription'));
        $this->actingAs($this->owner2)->getJson(route('admin.settings.tips_ratings'))->assertStatus(403);

        // Basic vendor with explicit feature override (allowed)
        TenantFeatureOverride::create([
            'vendor_id' => $this->vendor2->id,
            'feature_key' => 'tips.ratings',
            'is_enabled' => true,
            'reason' => 'Promo trial override',
        ]);

        $resBetaAllowed = $this->actingAs($this->owner2)->get(route('admin.settings.tips_ratings'));
        $resBetaAllowed->assertStatus(200);
    }

    public function test_tip_calculation_service_rounding_and_limits(): void
    {
        $service = app(TipRatingService::class);
        $setting = $this->vendor1->getTipRatingSetting();

        $setting->update([
            'is_enabled' => true,
            'tips_enabled' => true,
            'min_tip_amount' => 100,
            'max_tip_amount' => 5000,
        ]);

        // Percentage calculation (10% of 3000 = 300)
        $calc1 = $service->calculateTip($this->vendor1, 3000, 'percentage', 10, 'dine_in');
        $this->assertEquals(300, $calc1['amount']);

        // Explicit 0 tip
        $calc0 = $service->calculateTip($this->vendor1, 3000, 'none', 0, 'dine_in');
        $this->assertEquals(0, $calc0['amount']);

        // Fixed amount
        $calcFixed = $service->calculateTip($this->vendor1, 3000, 'fixed', 500, 'dine_in');
        $this->assertEquals(500, $calcFixed['amount']);

        // Max limit validation (10000 exceeds 5000 -> throws ValidationException)
        try {
            $service->calculateTip($this->vendor1, 3000, 'custom', 10000, 'dine_in');
            $this->fail('Expected ValidationException on tip exceeding max limit');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tip_amount', $e->errors());
        }

        // Min limit validation (50 is below 100 -> throws ValidationException)
        try {
            $service->calculateTip($this->vendor1, 3000, 'custom', 50, 'dine_in');
            $this->fail('Expected ValidationException on tip below min limit');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tip_amount', $e->errors());
        }
    }

    public function test_order_submission_with_tip_and_total_calculation(): void
    {
        $this->vendor1->getTipRatingSetting()->update([
            'is_enabled' => true,
            'tips_enabled' => true,
        ]);

        $payload = [
            'location_id' => $this->location1->id,
            'table_number' => '4',
            'type' => 'dine_in',
            'customer_name' => 'Karen Sargsyan',
            'payment_method' => 'cash',
            'tip_amount' => 300,
            'tip_type' => 'percentage',
            'tip_value' => 10,
            'waiter_id' => $this->waiter1->id,
            'items' => [
                [
                    'product_id' => $this->product1->id,
                    'quantity' => 2, // 2 * 3000 = 6000
                ],
            ],
        ];

        $res = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendor1->slug]), $payload);
        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $order = Order::where('vendor_id', $this->vendor1->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals(600, $order->tip_amount); // Server recalculates 10% of 6000 = 600
        $this->assertEquals(6600, $order->total_amount); // 6000 subtotal + 600 tip
        $this->assertEquals($this->waiter1->id, $order->waiter_id);

        // Verify Tip model recorded
        $tip = Tip::where('order_id', $order->id)->first();
        $this->assertNotNull($tip);
        $this->assertEquals(600, $tip->amount);
        $this->assertEquals($this->waiter1->id, $tip->waiter_id);
        $this->assertEquals('paid', $tip->payment_status); // Cash tip is marked as paid
    }

    public function test_payment_status_sync_for_tips(): void
    {
        $order = Order::create([
            'vendor_id' => $this->vendor1->id,
            'location_id' => $this->location1->id,
            'order_number' => 'ORD-TEST-100',
            'customer_name' => 'Guest',
            'type' => 'dine_in',
            'payment_method' => 'idram',
            'payment_status' => 'pending',
            'subtotal' => 5000,
            'tip_amount' => 500,
            'total_amount' => 5500,
            'currency' => 'AMD',
        ]);

        $tip = Tip::create([
            'vendor_id' => $this->vendor1->id,
            'location_id' => $this->location1->id,
            'order_id' => $order->id,
            'amount' => 500,
            'currency' => 'AMD',
            'payment_method' => 'idram',
            'payment_status' => 'pending',
        ]);

        // Simulate successful payment transition
        $verificationService = app(PaymentVerificationService::class);
        $reflection = new \ReflectionClass($verificationService);
        $method = $reflection->getMethod('syncTipPaymentStatus');
        $method->setAccessible(true);
        $method->invoke($verificationService, $order, 'paid');

        $tip->refresh();
        $this->assertEquals('paid', $tip->payment_status);

        // Simulate refund
        $method->invoke($verificationService, $order, 'refunded');
        $tip->refresh();
        $this->assertEquals('refunded', $tip->payment_status);
    }

    public function test_service_rating_submission_and_duplicate_prevention(): void
    {
        $this->vendor1->getTipRatingSetting()->update([
            'is_enabled' => true,
            'ratings_enabled' => true,
            'duplicate_policy' => 'per_order',
        ]);

        $order = Order::create([
            'vendor_id' => $this->vendor1->id,
            'location_id' => $this->location1->id,
            'order_number' => 'ORD-RATING-1',
            'customer_name' => 'Armen',
            'type' => 'dine_in',
            'status' => 'completed',
            'subtotal' => 2000,
            'total_amount' => 2000,
            'currency' => 'AMD',
        ]);

        $payload = [
            'order_number' => $order->order_number,
            'table_number' => '3',
            'waiter_id' => $this->waiter1->id,
            'rating' => 5,
            'categories' => ['food_quality', 'service_speed'],
            'comment' => '<script>alert("xss")</script>Delicious food & fast service!',
            'is_anonymous' => false,
            'customer_name' => 'Armen',
        ];

        // 1. First submission succeeds
        $res = $this->postJson(route('client.rating.submit', ['vendor_slug' => $this->vendor1->slug]), $payload);
        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $rating = ServiceRating::where('order_id', $order->id)->first();
        $this->assertNotNull($rating);
        $this->assertEquals(5, $rating->rating);
        $this->assertStringNotContainsString('<script>', $rating->comment); // XSS sanitized

        // 2. Duplicate submission for same order is rejected
        $resDuplicate = $this->postJson(route('client.rating.submit', ['vendor_slug' => $this->vendor1->slug]), $payload);
        $resDuplicate->assertStatus(422);
    }

    public function test_cross_tenant_waiter_assignment_prevention(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // Attempt to create tip for vendor1 with waiter belonging to vendor2
        Tip::create([
            'vendor_id' => $this->vendor1->id,
            'waiter_id' => $this->waiter2->id, // Cross-tenant!
            'amount' => 500,
            'currency' => 'AMD',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
    }

    public function test_admin_reports_and_moderation(): void
    {
        $tip = Tip::create([
            'vendor_id' => $this->vendor1->id,
            'amount' => 1000,
            'currency' => 'AMD',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'waiter_id' => $this->waiter1->id,
        ]);

        $rating = ServiceRating::create([
            'vendor_id' => $this->vendor1->id,
            'rating' => 4,
            'comment' => 'Very polite server',
            'status' => 'published',
            'waiter_id' => $this->waiter1->id,
        ]);

        // Access reports dashboard
        $res = $this->actingAs($this->owner1)->get(route('admin.tips_ratings.reports'));
        $res->assertStatus(200);
        $res->assertSee('1,000');
        $res->assertSee('Waiter John');

        // Moderate rating: hide review
        $resMod = $this->actingAs($this->owner1)->post(route('admin.tips_ratings.moderate', $rating), [
            'status' => 'hidden',
        ]);
        $resMod->assertStatus(302);

        $rating->refresh();
        $this->assertEquals('hidden', $rating->status);

        // CSV export permissions check
        $resExport = $this->actingAs($this->owner1)->get(route('admin.tips_ratings.export_tips'));
        $resExport->assertStatus(200);
        $this->assertStringContainsString('text/csv', $resExport->headers->get('content-type'));
    }

    public function test_admin_can_save_settings_from_admin_form(): void
    {
        $payload = [
            'is_enabled' => '1',
            'tips_enabled' => '1',
            'ratings_enabled' => '1',
            'tip_percentages_str' => '5, 10, 15, 20',
            'fixed_tip_amounts_str' => '200, 500, 1000, 2000',
            'calculation_base' => 'subtotal',
            'default_tip_selection' => 'none',
            'min_tip_amount' => '0.00',
            'max_tip_amount' => '50000.00',
            'allocation_policy' => 'waiter',
            'applicable_order_types' => ['dine_in', 'takeaway', 'delivery'],
            'allow_custom_tip' => '1',
            'allow_cash_tips' => '1',
            'allow_waiter_selection' => '1',
            'rating_scale' => '5',
            'duplicate_policy' => 'once_per_order',
            'submission_window_hours' => '24',
            'trigger_point' => 'manual_qr',
            'feedback_categories_str' => 'Food Quality, Speed of Service, Staff Friendliness, Cleanliness, Atmosphere',
        ];

        $res = $this->actingAs($this->owner1)->post(route('admin.settings.tips_ratings.update'), $payload);
        $res->assertSessionHasNoErrors();
        $res->assertRedirect();

        $settings = $this->vendor1->getTipRatingSetting()->fresh();
        $this->assertEquals('waiter', $settings->allocation_policy);
        $this->assertEquals('once_per_order', $settings->duplicate_policy);
        $this->assertEquals([5, 10, 15, 20], $settings->tip_percentages);
        $this->assertEquals([200, 500, 1000, 2000], $settings->fixed_tip_amounts);
        $this->assertCount(5, $settings->feedback_categories);
        $this->assertEquals('Food Quality', $settings->feedback_categories[0]);
    }

    public function test_admin_settings_legacy_field_compatibility(): void
    {
        $payload = [
            'is_enabled' => '1',
            'tips_enabled' => '1',
            'ratings_enabled' => '1',
            'tip_recipient_policy' => 'house',
            'duplicate_policy' => 'per_order',
            'calculation_base' => 'after_discount',
            'default_tip_selection' => 'percentage',
            'rating_scale' => '3',
            'submission_window_hours' => '12',
            'allow_anonymous_ratings' => '1',
            'is_written_feedback_optional' => '1',
        ];

        $res = $this->actingAs($this->owner1)->post(route('admin.settings.tips_ratings.update'), $payload);
        $res->assertSessionHasNoErrors();
        $res->assertRedirect();

        $settings = $this->vendor1->getTipRatingSetting()->fresh();
        $this->assertEquals('house', $settings->allocation_policy);
        $this->assertEquals('once_per_order', $settings->duplicate_policy);
        $this->assertEquals('after_discount', $settings->calculation_base);
        $this->assertEquals(3, $settings->rating_scale);
        $this->assertTrue($settings->is_anonymous_by_default);
        $this->assertFalse($settings->require_written_feedback);
    }

    public function test_storefront_rating_page_renders_properly(): void
    {
        $this->vendor1->getTipRatingSetting()->update([
            'is_enabled' => true,
            'ratings_enabled' => true,
            'tips_enabled' => true,
            'feedback_categories' => ['Food Quality', 'Speed of Service'],
        ]);

        $res = $this->get(route('client.rate', ['vendor_slug' => $this->vendor1->slug]));
        $res->assertStatus(200);
        $res->assertSee('Food Quality');
        $res->assertSee('Speed of Service');
    }
}
