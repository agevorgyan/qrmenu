<?php

namespace Tests\Feature;

use App\Models\Allergen;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\InventoryBalance;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Vendor $vendorA;

    protected User $ownerA;

    protected Warehouse $warehouseA;

    protected Unit $kg;

    protected Unit $g;

    protected Allergen $gluten;

    protected Vendor $vendorB;

    protected User $ownerB;

    protected Warehouse $warehouseB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kg = Unit::whereNull('vendor_id')->where('code', 'kg')->firstOrFail();
        $this->g = Unit::whereNull('vendor_id')->where('code', 'g')->firstOrFail();
        $this->gluten = Allergen::firstOrCreate(
            ['code' => 'gluten'],
            ['name' => 'Gluten', 'icon' => '🌾']
        );

        // Vendor A
        $this->vendorA = Vendor::create([
            'name' => 'Alpha Restaurant',
            'slug' => 'alpha-rest-'.uniqid(),
            'email' => 'alpha-'.uniqid().'@test.am',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
            'is_active' => true,
            'currency' => 'AMD',
        ]);

        $this->ownerA = User::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Owner Alpha',
            'email' => 'owner-alpha-'.uniqid().'@test.am',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->warehouseA = Warehouse::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Alpha Main Warehouse',
            'type' => 'main',
            'is_default' => true,
            'is_active' => true,
        ]);

        // Vendor B
        $this->vendorB = Vendor::create([
            'name' => 'Beta Restaurant',
            'slug' => 'beta-rest-'.uniqid(),
            'email' => 'beta-'.uniqid().'@test.am',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_status' => 'active',
            'is_active' => true,
            'currency' => 'AMD',
        ]);

        $this->ownerB = User::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Owner Beta',
            'email' => 'owner-beta-'.uniqid().'@test.am',
            'password' => bcrypt('password'),
            'role' => 'vendor_owner',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Beta Main Warehouse',
            'type' => 'main',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function test_vendor_owner_can_create_ingredient_with_allergens(): void
    {
        $category = IngredientCategory::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Grains & Flour',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->ownerA)
            ->post(route('admin.inventory.ingredients.store'), [
                'name' => 'Semolina Flour',
                'sku' => 'SEM-001',
                'type' => 'ingredient',
                'category_id' => $category->id,
                'unit_id' => $this->kg->id,
                'min_stock' => 5.0,
                'current_cost' => 450.0,
                'storage_conditions' => 'Ambient',
                'allergens' => [$this->gluten->id],
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('admin.inventory.ingredients.index'));

        $ingredient = Ingredient::where('vendor_id', $this->vendorA->id)
            ->where('sku', 'SEM-001')
            ->first();

        $this->assertNotNull($ingredient);
        $this->assertEquals('Semolina Flour', $ingredient->name);
        $this->assertEquals(450.0, (float) $ingredient->current_cost);
        $this->assertTrue($ingredient->allergens->contains('id', $this->gluten->id));
    }

    public function test_vendor_can_record_stock_transaction_via_http(): void
    {
        $ingredient = Ingredient::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Basmati Rice',
            'sku' => 'RICE-01',
            'unit_id' => $this->kg->id,
            'type' => 'raw_material',
            'current_cost' => 800.0,
        ]);

        // Record Receipt of 25 kg
        $response = $this->actingAs($this->ownerA)
            ->post(route('admin.inventory.stocks.transactions.store'), [
                'type' => 'receipt',
                'warehouse_id' => $this->warehouseA->id,
                'notes' => 'Bulk rice receipt',
                'items' => [
                    [
                        'ingredient_id' => $ingredient->id,
                        'quantity' => 25.0,
                        'unit_id' => $this->kg->id,
                        'unit_cost' => 800.0,
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.inventory.stocks.overview'));

        $balance = InventoryBalance::withoutGlobalScopes()
            ->where('warehouse_id', $this->warehouseA->id)
            ->where('ingredient_id', $ingredient->id)
            ->first();

        $this->assertNotNull($balance);
        $this->assertEquals(25.0, (float) $balance->quantity_on_hand);
        $this->assertEquals(20000.0, (float) $balance->total_value);
    }

    public function test_cannot_delete_warehouse_with_active_inventory(): void
    {
        $ingredient = Ingredient::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Sugar',
            'unit_id' => $this->kg->id,
            'type' => 'ingredient',
        ]);

        InventoryBalance::create([
            'vendor_id' => $this->vendorA->id,
            'warehouse_id' => $this->warehouseA->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 10.0,
            'unit_cost' => 300.0,
            'total_value' => 3000.0,
        ]);

        $response = $this->actingAs($this->ownerA)
            ->delete(route('admin.inventory.warehouses.destroy', $this->warehouseA));

        $response->assertSessionHas('error');
        $this->assertNull($this->warehouseA->fresh()->deleted_at);
    }

    public function test_strict_multi_tenant_isolation_on_inventory(): void
    {
        // Ingredient belonging to Vendor B
        $ingredientB = Ingredient::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Secret Spice Blend B',
            'sku' => 'SECRET-B',
            'unit_id' => $this->kg->id,
            'type' => 'ingredient',
            'current_cost' => 5000.0,
        ]);

        // 1. Owner A cannot view Vendor B's ingredient in edit page (403 or 404)
        $response = $this->actingAs($this->ownerA)
            ->get(route('admin.inventory.ingredients.edit', $ingredientB));

        $this->assertTrue(in_array($response->status(), [403, 404], true));

        // 2. Owner A cannot update Vendor B's ingredient
        $updateResponse = $this->actingAs($this->ownerA)
            ->put(route('admin.inventory.ingredients.update', $ingredientB), [
                'name' => 'Tampered Name',
                'type' => 'ingredient',
                'unit_id' => $this->kg->id,
            ]);

        $this->assertTrue(in_array($updateResponse->status(), [403, 404], true));
        $this->assertEquals('Secret Spice Blend B', $ingredientB->fresh()->name);

        // 3. Owner A cannot execute a stock transaction on Vendor B's warehouse
        $txResponse = $this->actingAs($this->ownerA)
            ->post(route('admin.inventory.stocks.transactions.store'), [
                'type' => 'receipt',
                'warehouse_id' => $this->warehouseB->id,
                'items' => [
                    [
                        'ingredient_id' => $ingredientB->id,
                        'quantity' => 10.0,
                        'unit_id' => $this->kg->id,
                    ],
                ],
            ]);

        $this->assertTrue(in_array($txResponse->status(), [403, 302], true));
        if ($txResponse->isRedirect()) {
            $txResponse->assertSessionHasErrors(['warehouse_id']);
        }
    }
}
