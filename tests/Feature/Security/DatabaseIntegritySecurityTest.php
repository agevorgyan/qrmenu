<?php

namespace Tests\Feature\Security;

use App\Models\Location;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseIntegritySecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendorA;

    protected Vendor $vendorB;

    protected Location $locationA;

    protected Location $locationB;

    protected RestaurantTable $tableA;

    protected RestaurantTable $tableB;

    protected Reservation $reservationA;

    protected Reservation $reservationB;

    protected function setUp(): void
    {
        parent::setUp();

        // Vendor A
        $this->vendorA = Vendor::create([
            'name' => 'Integrity Vendor A',
            'slug' => 'integrity-vendor-a',
            'email' => 'vendorA@integrity.am',
            'is_active' => true,
        ]);

        $this->locationA = Location::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Branch A',
            'slug' => 'branch-a',
            'is_active' => true,
        ]);

        $this->tableA = RestaurantTable::create([
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'table_number' => 'TA1',
            'capacity' => 4,
            'min_capacity' => 1,
            'is_active' => true,
        ]);

        $this->reservationA = Reservation::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'RES-INT-A',
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'restaurant_table_id' => $this->tableA->id,
            'customer_name' => 'User A',
            'customer_email' => 'userA@test.com',
            'reservation_date' => now()->addDays(1)->format('Y-m-d'),
            'reservation_time' => '18:00',
            'guest_count' => 2,
        ]);

        // Vendor B
        $this->vendorB = Vendor::create([
            'name' => 'Integrity Vendor B',
            'slug' => 'integrity-vendor-b',
            'email' => 'vendorB@integrity.am',
            'is_active' => true,
        ]);

        $this->locationB = Location::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Branch B',
            'slug' => 'branch-b',
            'is_active' => true,
        ]);

        $this->tableB = RestaurantTable::create([
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'table_number' => 'TB1',
            'capacity' => 4,
            'min_capacity' => 1,
            'is_active' => true,
        ]);

        $this->reservationB = Reservation::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'RES-INT-B',
            'vendor_id' => $this->vendorB->id,
            'location_id' => $this->locationB->id,
            'restaurant_table_id' => $this->tableB->id,
            'customer_name' => 'User B',
            'customer_email' => 'userB@test.com',
            'reservation_date' => now()->addDays(1)->format('Y-m-d'),
            'reservation_time' => '19:00',
            'guest_count' => 3,
        ]);
    }

    public function test_postgresql_composite_fk_rejects_cross_tenant_table_to_location(): void
    {
        $this->expectException(QueryException::class);

        // Raw SQL bypasses Eloquent hooks to directly test PostgreSQL constraint
        DB::table('restaurant_tables')->insert([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationB->id, // Belongs to Vendor B
            'table_number' => 'MALICIOUS_TABLE',
            'capacity' => 4,
            'min_capacity' => 1,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_postgresql_composite_fk_rejects_cross_tenant_reservation_to_location(): void
    {
        $this->expectException(QueryException::class);

        DB::table('reservations')->insert([
            'uuid' => (string) Str::uuid(),
            'reference' => 'RES-BAD-LOC',
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationB->id, // Belongs to Vendor B
            'customer_name' => 'Malicious User',
            'customer_email' => 'bad@test.com',
            'guest_count' => 2,
            'reservation_date' => now()->addDays(2)->format('Y-m-d'),
            'reservation_time' => '20:00',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_postgresql_composite_fk_rejects_cross_tenant_reservation_to_table(): void
    {
        $this->expectException(QueryException::class);

        DB::table('reservations')->insert([
            'uuid' => (string) Str::uuid(),
            'reference' => 'RES-BAD-TABLE',
            'vendor_id' => $this->vendorA->id,
            'location_id' => $this->locationA->id,
            'restaurant_table_id' => $this->tableB->id, // Belongs to Vendor B
            'customer_name' => 'Malicious User',
            'customer_email' => 'bad@test.com',
            'guest_count' => 2,
            'reservation_date' => now()->addDays(2)->format('Y-m-d'),
            'reservation_time' => '20:00',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_postgresql_composite_fk_rejects_cross_tenant_preorder_to_reservation(): void
    {
        $this->expectException(QueryException::class);

        DB::table('reservation_pre_orders')->insert([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $this->vendorA->id,
            'reservation_id' => $this->reservationB->id, // Belongs to Vendor B
            'status' => 'pending',
            'subtotal' => 1000,
            'total_amount' => 1000,
            'currency' => 'AMD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_postgresql_composite_fk_rejects_cross_tenant_subscription_payment(): void
    {
        $planId = DB::table('subscription_plans')->insertGetId([
            'name' => 'Integrity Plan',
            'slug' => 'integrity-plan-'.Str::random(6),
            'price' => 1000,
            'currency' => 'AMD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subBId = DB::table('subscriptions')->insertGetId([
            'vendor_id' => $this->vendorB->id,
            'subscription_plan_id' => $planId,
            'status' => 'active',
            'billing_interval' => 'monthly',
            'auto_renew' => true,
            'renewal_state' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        // Attempt to insert payment for Vendor A referencing Vendor B's subscription
        DB::table('subscription_payments')->insert([
            'vendor_id' => $this->vendorA->id,
            'subscription_id' => $subBId,
            'amount' => 5000,
            'currency' => 'AMD',
            'payment_method' => 'bank_transfer',
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_postgresql_composite_fk_allows_same_tenant_subscription_payment(): void
    {
        $planId = DB::table('subscription_plans')->insertGetId([
            'name' => 'Integrity Plan A',
            'slug' => 'integrity-plan-'.Str::random(6),
            'price' => 1000,
            'currency' => 'AMD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subAId = DB::table('subscriptions')->insertGetId([
            'vendor_id' => $this->vendorA->id,
            'subscription_plan_id' => $planId,
            'status' => 'active',
            'billing_interval' => 'monthly',
            'auto_renew' => true,
            'renewal_state' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentId = DB::table('subscription_payments')->insertGetId([
            'vendor_id' => $this->vendorA->id,
            'subscription_id' => $subAId,
            'amount' => 5000,
            'currency' => 'AMD',
            'payment_method' => 'bank_transfer',
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('subscription_payments', [
            'id' => $paymentId,
            'vendor_id' => $this->vendorA->id,
            'subscription_id' => $subAId,
        ]);
    }

    public function test_postgresql_composite_fk_rejects_cross_tenant_translation_history(): void
    {
        $ctBId = DB::table('content_translations')->insertGetId([
            'vendor_id' => $this->vendorB->id,
            'translatable_type' => 'App\\Models\\Product',
            'translatable_id' => 9999,
            'field' => 'name',
            'locale' => 'en',
            'source_locale' => 'hy',
            'source_text' => 'B Product',
            'status' => 'draft',
            'source_type' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        // Attempt to insert translation history for Vendor A referencing Vendor B's translation
        DB::table('translation_histories')->insert([
            'vendor_id' => $this->vendorA->id,
            'content_translation_id' => $ctBId,
            'version_number' => 1,
            'translation' => 'Malicious Cross-Tenant Translation',
            'status' => 'draft',
            'source_type' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_postgresql_composite_fk_allows_same_tenant_translation_history(): void
    {
        $ctAId = DB::table('content_translations')->insertGetId([
            'vendor_id' => $this->vendorA->id,
            'translatable_type' => 'App\\Models\\Product',
            'translatable_id' => 8888,
            'field' => 'name',
            'locale' => 'en',
            'source_locale' => 'hy',
            'source_text' => 'A Product',
            'status' => 'draft',
            'source_type' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $historyId = DB::table('translation_histories')->insertGetId([
            'vendor_id' => $this->vendorA->id,
            'content_translation_id' => $ctAId,
            'version_number' => 1,
            'translation' => 'Legitimate Translation',
            'status' => 'draft',
            'source_type' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('translation_histories', [
            'id' => $historyId,
            'vendor_id' => $this->vendorA->id,
            'content_translation_id' => $ctAId,
        ]);
    }

    public function test_postgresql_cascade_behavior_on_content_translation_deletion(): void
    {
        $ctId = DB::table('content_translations')->insertGetId([
            'vendor_id' => $this->vendorA->id,
            'translatable_type' => 'App\\Models\\Product',
            'translatable_id' => 7777,
            'field' => 'description',
            'locale' => 'ru',
            'source_locale' => 'hy',
            'source_text' => 'Description text',
            'status' => 'draft',
            'source_type' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $historyId = DB::table('translation_histories')->insertGetId([
            'vendor_id' => $this->vendorA->id,
            'content_translation_id' => $ctId,
            'version_number' => 1,
            'translation' => 'Russian Description',
            'status' => 'draft',
            'source_type' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('translation_histories', ['id' => $historyId]);

        // Delete parent content_translation
        DB::table('content_translations')->where('id', $ctId)->delete();

        // Foreign key cascade should have deleted the child translation history
        $this->assertDatabaseMissing('translation_histories', ['id' => $historyId]);
    }

    public function test_postgresql_nullify_behavior_on_subscription_deletion(): void
    {
        $planId = DB::table('subscription_plans')->insertGetId([
            'name' => 'Integrity Plan Nullify',
            'slug' => 'integrity-plan-'.Str::random(6),
            'price' => 1000,
            'currency' => 'AMD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subId = DB::table('subscriptions')->insertGetId([
            'vendor_id' => $this->vendorA->id,
            'subscription_plan_id' => $planId,
            'status' => 'active',
            'billing_interval' => 'monthly',
            'auto_renew' => true,
            'renewal_state' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentId = DB::table('subscription_payments')->insertGetId([
            'vendor_id' => $this->vendorA->id,
            'subscription_id' => $subId,
            'amount' => 5000,
            'currency' => 'AMD',
            'payment_method' => 'bank_transfer',
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('subscription_payments', ['id' => $paymentId, 'subscription_id' => $subId]);

        // Delete subscription
        DB::table('subscriptions')->where('id', $subId)->delete();

        // Foreign key ON DELETE SET NULL should have set subscription_id to null without deleting financial record
        $this->assertDatabaseHas('subscription_payments', [
            'id' => $paymentId,
            'subscription_id' => null,
            'vendor_id' => $this->vendorA->id,
        ]);
    }
}
