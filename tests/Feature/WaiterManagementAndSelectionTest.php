<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\ServiceRating;
use App\Models\SubscriptionPlan;
use App\Models\Tip;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WaiterManagementAndSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendorA;

    protected Location $locationA;

    protected User $ownerA;

    protected RestaurantTable $tableA1;

    protected RestaurantTable $tableA2;

    protected User $waiterA1;

    protected User $waiterA2;

    protected Product $productA;

    protected Vendor $vendorB;

    protected Location $locationB;

    protected User $ownerB;

    protected User $waiterB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->seed(SubscriptionPlanSeeder::class);
        $proPlan = SubscriptionPlan::where('slug', 'pro')->first();

        // Vendor A Setup (Pro Plan with tips.ratings)
        $this->vendorA = Vendor::create([
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

        $this->locationA = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Alpha Main',
            'slug' => 'alpha-main',
            'is_active' => true,
        ]);

        $this->ownerA = User::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'name' => 'Owner Alpha',
            'email' => 'owner@alpha.am',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->tableA1 = RestaurantTable::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'table_number' => '10',
            'name' => 'Table 10',
            'is_active' => true,
        ]);

        $this->tableA2 = RestaurantTable::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'table_number' => '20',
            'name' => 'Table 20',
            'is_active' => true,
        ]);

        $this->waiterA1 = User::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'name' => 'Armen Sargsyan',
            'display_name' => 'Armen S.',
            'short_description' => 'Coffee & Wine specialist',
            'employee_id' => 'EMP-001',
            'email' => 'armen@alpha.am',
            'password' => bcrypt('password'),
            'role' => 'waiter',
            'is_active' => true,
            'sort_order' => 1,
            'assigned_table_ids' => [$this->tableA1->id],
        ]);
        $this->waiterA1->assignedTables()->sync([$this->tableA1->id => ['vendor_id' => $this->vendorA->id]]);

        $this->waiterA2 = User::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'name' => 'Ani Grigoryan',
            'display_name' => 'Ani G.',
            'employee_id' => 'EMP-002',
            'email' => 'ani@alpha.am',
            'password' => bcrypt('password'),
            'role' => 'waiter',
            'is_active' => false, // Inactive
            'sort_order' => 2,
        ]);

        $categoryA = Category::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Kitchen',
            'is_active' => true,
            'order' => 1,
        ]);

        $this->productA = Product::create([
            'vendor_id' => $this->vendorA->id,
            'category_id' => $categoryA->id,
            'name' => 'Gourmet Burger',
            'price' => 3500,
            'is_active' => true,
        ]);

        // Vendor B Setup (Tenant Isolation check)
        $this->vendorB = Vendor::create([
            'name' => 'Cafe Beta',
            'slug' => 'cafe-beta',
            'email' => 'beta@cafe.am',
            'password' => bcrypt('password'),
            'subscription_plan_id' => $proPlan?->id,
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
            'is_active' => true,
            'currency' => 'AMD',
        ]);

        $this->locationB = Location::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Beta Main',
            'slug' => 'beta-main',
            'is_active' => true,
        ]);

        $this->ownerB = User::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'name' => 'Owner Beta',
            'email' => 'owner@beta.am',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->waiterB = User::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'name' => 'Davit Beta',
            'email' => 'davit@beta.am',
            'password' => bcrypt('password'),
            'role' => 'waiter',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_view_waiters_index_and_metrics(): void
    {
        $response = $this->actingAs($this->ownerA)->get(route('admin.waiters.index'));

        $response->assertStatus(200);
        $response->assertSee('Waiters & Staff Management');
        $response->assertSee('Armen Sargsyan');
        $response->assertSee('Ani Grigoryan');
        $response->assertSee('10');
    }

    public function test_admin_can_create_waiter_with_table_assignments_and_avatar(): void
    {
        $photo = UploadedFile::fake()->image('waiter.jpg', 400, 400);

        $payload = [
            'name' => 'Karen Vardanyan',
            'display_name' => 'Karen V.',
            'short_description' => 'Friendly mixologist',
            'employee_id' => 'EMP-003',
            'role' => 'waiter',
            'location_id' => $this->locationA->id,
            'is_active' => '1',
            'sort_order' => 5,
            'assigned_tables' => [$this->tableA1->id, $this->tableA2->id],
            'photo' => $photo,
        ];

        $response = $this->actingAs($this->ownerA)
            ->post(route('admin.waiters.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $created = User::where('vendor_id', $this->vendorA->id)
            ->where('name', 'Karen Vardanyan')
            ->first();

        $this->assertNotNull($created);
        $this->assertEquals('Karen V.', $created->display_name);
        $this->assertEquals('EMP-003', $created->employee_id);
        $this->assertTrue($created->is_active);
        $this->assertEquals(5, $created->sort_order);
        $this->assertNotNull($created->avatar_url);
        $this->assertCount(2, $created->assignedTables);
    }

    public function test_admin_can_update_waiter_profile_and_reassign_tables(): void
    {
        $payload = [
            'name' => 'Armen Sargsyan Updated',
            'display_name' => 'Armen Senior',
            'short_description' => 'Lead Sommelier',
            'employee_id' => 'EMP-001-A',
            'role' => 'waiter',
            'is_active' => '1',
            'sort_order' => 2,
            'assigned_tables' => [$this->tableA2->id], // Changed from tableA1 to tableA2
        ];

        $response = $this->actingAs($this->ownerA)
            ->put(route('admin.waiters.update', $this->waiterA1), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->waiterA1->refresh();
        $this->assertEquals('Armen Sargsyan Updated', $this->waiterA1->name);
        $this->assertEquals('Armen Senior', $this->waiterA1->display_name);
        $this->assertEquals('Lead Sommelier', $this->waiterA1->short_description);
        $this->assertCount(1, $this->waiterA1->assignedTables);
        $this->assertEquals($this->tableA2->id, $this->waiterA1->assignedTables->first()->id);
    }

    public function test_admin_photo_upload_replace_and_delete(): void
    {
        // 1. Upload new photo
        $photo1 = UploadedFile::fake()->image('first.png', 300, 300);
        $this->actingAs($this->ownerA)->put(route('admin.waiters.update', $this->waiterA1), [
            'name' => $this->waiterA1->name,
            'photo' => $photo1,
        ]);
        $this->waiterA1->refresh();
        $firstAvatar = $this->waiterA1->avatar_url;
        $this->assertNotNull($firstAvatar);

        // 2. Replace with new photo
        $photo2 = UploadedFile::fake()->image('second.webp', 350, 350);
        $this->actingAs($this->ownerA)->put(route('admin.waiters.update', $this->waiterA1), [
            'name' => $this->waiterA1->name,
            'photo' => $photo2,
        ]);
        $this->waiterA1->refresh();
        $secondAvatar = $this->waiterA1->avatar_url;
        $this->assertNotNull($secondAvatar);
        $this->assertNotEquals($firstAvatar, $secondAvatar);

        // 3. Delete photo via dedicated endpoint
        $delResponse = $this->actingAs($this->ownerA)
            ->delete(route('admin.waiters.delete_photo', $this->waiterA1));
        $delResponse->assertRedirect();
        $this->waiterA1->refresh();
        $this->assertNull($this->waiterA1->avatar_url);
    }

    public function test_admin_photo_upload_rejects_invalid_file_types(): void
    {
        $badFile = UploadedFile::fake()->create('malicious.php', 100, 'application/x-php');

        $response = $this->actingAs($this->ownerA)
            ->post(route('admin.waiters.store'), [
                'name' => 'Bad Waiter',
                'photo' => $badFile,
            ]);

        $response->assertSessionHasErrors('photo');
    }

    public function test_admin_toggle_status_activation_and_deactivation(): void
    {
        $this->assertTrue($this->waiterA1->is_active);

        // Deactivate
        $this->actingAs($this->ownerA)
            ->patch(route('admin.waiters.toggle_status', $this->waiterA1));
        $this->waiterA1->refresh();
        $this->assertFalse($this->waiterA1->is_active);

        // Reactivate
        $this->actingAs($this->ownerA)
            ->patch(route('admin.waiters.toggle_status', $this->waiterA1));
        $this->waiterA1->refresh();
        $this->assertTrue($this->waiterA1->is_active);
    }

    public function test_admin_reorder_waiters(): void
    {
        $response = $this->actingAs($this->ownerA)
            ->postJson(route('admin.waiters.reorder'), [
                'order' => [
                    ['id' => $this->waiterA1->id, 'sort_order' => 10],
                    ['id' => $this->waiterA2->id, 'sort_order' => 5],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->waiterA1->refresh();
        $this->waiterA2->refresh();
        $this->assertEquals(10, $this->waiterA1->sort_order);
        $this->assertEquals(5, $this->waiterA2->sort_order);
    }

    public function test_tenant_isolation_cannot_access_or_modify_other_tenant_waiter(): void
    {
        // Owner B tries to update Waiter A1 (Belongs to Vendor A)
        $resUpdate = $this->actingAs($this->ownerB)
            ->put(route('admin.waiters.update', $this->waiterA1), [
                'name' => 'Hacked Name',
            ]);
        $resUpdate->assertStatus(403);

        // Owner B tries to toggle status of Waiter A1
        $resToggle = $this->actingAs($this->ownerB)
            ->patch(route('admin.waiters.toggle_status', $this->waiterA1));
        $resToggle->assertStatus(403);

        // Owner B tries to delete photo of Waiter A1
        $resDeletePhoto = $this->actingAs($this->ownerB)
            ->delete(route('admin.waiters.delete_photo', $this->waiterA1));
        $resDeletePhoto->assertStatus(403);

        // Owner B tries to delete Waiter A1
        $resDestroy = $this->actingAs($this->ownerB)
            ->delete(route('admin.waiters.destroy', $this->waiterA1));
        $resDestroy->assertStatus(403);

        // Verify Waiter A1 remains untouched
        $this->waiterA1->refresh();
        $this->assertEquals('Armen Sargsyan', $this->waiterA1->name);
    }

    public function test_lifecycle_protection_prevents_deleting_waiter_with_historical_records(): void
    {
        // 1. Create an order associated with waiterA1
        Order::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'order_number' => 'ORD-HIST-01',
            'customer_name' => 'Guest',
            'type' => 'dine_in',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'subtotal' => 2000,
            'total_amount' => 2000,
            'currency' => 'AMD',
            'waiter_id' => $this->waiterA1->id,
        ]);

        // Try to destroy waiter with order history
        $response = $this->actingAs($this->ownerA)
            ->delete(route('admin.waiters.destroy', $this->waiterA1));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Verify record still exists
        $this->assertDatabaseHas('users', ['id' => $this->waiterA1->id]);
    }

    public function test_admin_can_delete_waiter_without_historical_records(): void
    {
        // waiterA2 has no orders, tips, or ratings
        $response = $this->actingAs($this->ownerA)
            ->delete(route('admin.waiters.destroy', $this->waiterA2));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $this->waiterA2->id]);
    }

    public function test_admin_can_update_customer_selection_settings(): void
    {
        $payload = [
            'allow_waiter_selection' => '1',
            'waiter_selection_stage' => 'order',
            'waiter_selection_required' => '1',
            'show_waiter_photos' => '1',
            'show_waiter_descriptions' => '1',
            'waiter_sort_by' => 'sort_order',
            'filter_waiters_by_table' => '1',
            'waiter_attribution_scope' => 'both',
        ];

        $response = $this->actingAs($this->ownerA)
            ->put(route('admin.waiters.settings.update'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setting = $this->vendorA->getTipRatingSetting();
        $this->assertTrue($setting->allow_waiter_selection);
        $this->assertEquals('order', $setting->waiter_selection_stage);
        $this->assertTrue($setting->waiter_selection_required);
        $this->assertTrue($setting->show_waiter_photos);
        $this->assertTrue($setting->show_waiter_descriptions);
        $this->assertEquals('sort_order', $setting->waiter_sort_by);
        $this->assertTrue($setting->filter_waiters_by_table);
        $this->assertEquals('both', $setting->waiter_attribution_scope);
    }

    public function test_customer_waiter_selection_shows_only_active_waiters(): void
    {
        $this->vendorA->getTipRatingSetting()->update([
            'is_enabled' => true,
            'allow_waiter_selection' => true,
        ]);

        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendorA->slug]));
        $response->assertStatus(200);

        // Armen Sargsyan is active -> should be present in activeWaiters JSON
        $response->assertSee('Armen S.');

        // Ani Grigoryan is inactive -> must NEVER be present
        $response->assertDontSee('Ani G.');
    }

    public function test_customer_waiter_selection_filters_by_assigned_table(): void
    {
        // Assign Waiter A1 to Table 10 (done in setUp)
        // Create Waiter A3 assigned to Table 20
        $waiterA3 = User::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'name' => 'Vahan Melikyan',
            'display_name' => 'Vahan M.',
            'email' => 'vahan@alpha.am',
            'password' => bcrypt('password'),
            'role' => 'waiter',
            'is_active' => true,
            'sort_order' => 3,
            'assigned_table_ids' => [$this->tableA2->id],
        ]);
        $waiterA3->assignedTables()->sync([$this->tableA2->id => ['vendor_id' => $this->vendorA->id]]);

        $this->vendorA->getTipRatingSetting()->update([
            'is_enabled' => true,
            'allow_waiter_selection' => true,
            'filter_waiters_by_table' => true,
        ]);

        // When visiting Table 10: Waiter A1 (Armen) is present, Waiter A3 (Vahan) is NOT
        $resTable10 = $this->get(route('client.menu', [
            'vendor_slug' => $this->vendorA->slug,
            'table' => '10',
        ]));
        $resTable10->assertStatus(200);
        $resTable10->assertSee('Armen S.');
        $resTable10->assertDontSee('Vahan M.');

        // When visiting Table 20: Waiter A3 (Vahan) is present, Waiter A1 (Armen) is NOT
        $resTable20 = $this->get(route('client.menu', [
            'vendor_slug' => $this->vendorA->slug,
            'table' => '20',
        ]));
        $resTable20->assertStatus(200);
        $resTable20->assertSee('Vahan M.');
        $resTable20->assertDontSee('Armen S.');
    }

    public function test_order_submission_validates_waiter_is_active_and_belongs_to_tenant(): void
    {
        $this->vendorA->getTipRatingSetting()->update([
            'is_enabled' => true,
            'allow_waiter_selection' => true,
        ]);

        $basePayload = [
            'location_id' => $this->locationA->id,
            'table_number' => '10',
            'type' => 'dine_in',
            'customer_name' => 'Arman',
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 1,
                ],
            ],
        ];

        // 1. Submit with inactive waiter (Ani) -> Rejects validation
        $payloadInactive = array_merge($basePayload, ['waiter_id' => $this->waiterA2->id]);
        $resInactive = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendorA->slug]), $payloadInactive);
        $resInactive->assertStatus(422);
        $resInactive->assertJsonValidationErrors(['waiter_id']);

        // 2. Submit with other tenant's waiter (Davit from Vendor B) -> Rejects validation
        $payloadOtherTenant = array_merge($basePayload, ['waiter_id' => $this->waiterB->id]);
        $resOtherTenant = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendorA->slug]), $payloadOtherTenant);
        $resOtherTenant->assertStatus(422);
        $resOtherTenant->assertJsonValidationErrors(['waiter_id']);

        // 3. Submit with active waiter from same tenant (Armen) -> Success
        $payloadActive = array_merge($basePayload, ['waiter_id' => $this->waiterA1->id]);
        $resActive = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendorA->slug]), $payloadActive);
        $resActive->assertStatus(200);
        $resActive->assertJson(['success' => true]);

        $order = Order::where('vendor_id', $this->vendorA->id)->latest()->first();
        $this->assertEquals($this->waiterA1->id, $order->waiter_id);
    }

    public function test_order_submission_enforces_waiter_when_selection_required(): void
    {
        $this->vendorA->getTipRatingSetting()->update([
            'is_enabled' => true,
            'allow_waiter_selection' => true,
            'waiter_selection_required' => true,
        ]);

        $payloadWithoutWaiter = [
            'location_id' => $this->locationA->id,
            'table_number' => '10',
            'type' => 'dine_in',
            'customer_name' => 'Arman',
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 1,
                ],
            ],
        ];

        // Fails because waiter is required
        $res = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendorA->slug]), $payloadWithoutWaiter);
        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['waiter_id']);
    }

    public function test_order_submission_allows_skipping_waiter_when_optional(): void
    {
        $this->vendorA->getTipRatingSetting()->update([
            'is_enabled' => true,
            'allow_waiter_selection' => true,
            'waiter_selection_required' => false,
        ]);

        $payloadWithoutWaiter = [
            'location_id' => $this->locationA->id,
            'table_number' => '10',
            'type' => 'dine_in',
            'customer_name' => 'Arman',
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $res = $this->postJson(route('client.order.submit', ['vendor_slug' => $this->vendorA->slug]), $payloadWithoutWaiter);
        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $order = Order::where('vendor_id', $this->vendorA->id)->latest()->first();
        $this->assertNull($order->waiter_id);
    }

    public function test_service_rating_attribution_to_waiter_and_anonymous_ratings(): void
    {
        $this->vendorA->getTipRatingSetting()->update([
            'is_enabled' => true,
            'ratings_enabled' => true,
            'allow_waiter_ratings' => true,
            'waiter_attribution_scope' => 'both',
        ]);

        $ratingPayload = [
            'rating' => 5,
            'waiter_rating' => 5,
            'waiter_id' => $this->waiterA1->id,
            'feedback' => 'Exceptional wine recommendation and warm hospitality!',
            'customer_name' => null, // Anonymous
        ];

        $res = $this->postJson(route('client.rating.submit', ['vendor_slug' => $this->vendorA->slug]), $ratingPayload);
        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $rating = ServiceRating::where('vendor_id', $this->vendorA->id)->latest()->first();
        $this->assertNotNull($rating);
        $this->assertEquals($this->waiterA1->id, $rating->waiter_id);
        $this->assertEquals(5, $rating->rating);
        $this->assertNull($rating->customer_name); // Verified anonymous
    }

    public function test_historical_records_remain_intact_after_waiter_deactivation(): void
    {
        // Record rating and tip for waiterA1
        ServiceRating::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'rating' => 5,
            'waiter_rating' => 5,
            'waiter_id' => $this->waiterA1->id,
            'feedback' => 'Loved it',
        ]);

        Tip::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'waiter_id' => $this->waiterA1->id,
            'amount' => 1000,
            'currency' => 'AMD',
            'payment_status' => 'paid',
        ]);

        // Deactivate waiterA1
        $this->waiterA1->update(['is_active' => false]);

        // Historical query must still correctly aggregate and link to waiter
        $this->assertEquals(1, $this->waiterA1->ratings()->count());
        $this->assertEquals(1, $this->waiterA1->tips()->count());
        $this->assertEquals(1000, $this->waiterA1->tips()->sum('amount'));
    }

    public function test_localization_keys_exist_for_waiters_in_all_locales(): void
    {
        $locales = ['hy', 'en', 'ru'];
        $keys = [
            'Waiters & Staff Management',
            'Add Waiter',
            'Edit Waiter Profile',
            'Customer Waiter Selection Settings',
            'Not sure / Skip',
            'Assigned Tables',
        ];

        foreach ($locales as $locale) {
            $langPath = base_path("lang/{$locale}.json");
            $this->assertFileExists($langPath);
            $translations = json_decode(file_get_contents($langPath), true);
            $this->assertIsArray($translations);

            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $translations, "Missing key [{$key}] in {$locale}.json");
            }
        }
    }
}
