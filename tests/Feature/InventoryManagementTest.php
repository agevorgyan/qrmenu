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
use App\Services\IngredientImportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
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

    public function test_can_render_inventory_dashboard_with_low_stock_query_on_postgresql(): void
    {
        // Create an ingredient with min_stock > 0 and 0 stock balance
        $cat = IngredientCategory::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Baking Supplies',
        ]);

        Ingredient::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Organic Vanilla Extract',
            'sku' => 'VANILLA-001',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'category_id' => $cat->id,
            'unit_id' => $this->kg->id,
            'min_stock' => 5.0,
        ]);

        $response = $this->actingAs($this->ownerA)->get(route('admin.inventory.dashboard'));

        $response->assertOk();
        $response->assertSeeText('Inventory');
    }

    public function test_can_filter_ingredients_by_low_stock_on_postgresql(): void
    {
        $cat = IngredientCategory::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Dry Goods',
        ]);

        // Low stock item: min_stock 10, current balance 0
        Ingredient::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Low Stock Sugar',
            'sku' => 'SUGAR-LOW-01',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'category_id' => $cat->id,
            'unit_id' => $this->kg->id,
            'min_stock' => 10.0,
        ]);

        // Plentiful item: min_stock 2, current balance 50
        $flour = Ingredient::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Plentiful Flour',
            'sku' => 'FLOUR-HIGH-01',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'category_id' => $cat->id,
            'unit_id' => $this->kg->id,
            'min_stock' => 2.0,
        ]);

        InventoryBalance::create([
            'vendor_id' => $this->vendorA->id,
            'warehouse_id' => $this->warehouseA->id,
            'ingredient_id' => $flour->id,
            'quantity_on_hand' => 50.0,
            'quantity_reserved' => 0.0,
            'unit_cost' => 500.0,
            'total_value' => 25000.0,
        ]);

        $response = $this->actingAs($this->ownerA)->get(route('admin.inventory.ingredients.index', ['low_stock' => 1]));

        $response->assertOk();
        $response->assertSeeText('Low Stock Sugar');
        $response->assertDontSeeText('Plentiful Flour');
    }

    public function test_can_download_ingredient_import_template_xlsx(): void
    {
        $response = $this->actingAs($this->ownerA)
            ->get(route('admin.inventory.ingredients.template', ['format' => 'xlsx']));

        $response->assertOk();
        $this->assertEquals(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
        $this->assertStringContainsString('ingredients_import_template.xlsx', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_can_download_ingredient_import_template_csv(): void
    {
        $response = $this->actingAs($this->ownerA)
            ->get(route('admin.inventory.ingredients.template', ['format' => 'csv']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('ingredients_import_template.csv', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_can_import_ingredients_from_xlsx_file(): void
    {
        $service = app(IngredientImportService::class);
        $tpl = $service->generateTemplate('xlsx');

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_import_').'.xlsx';
        file_put_contents($tmpFile, $tpl['content']);

        $uploadedFile = new UploadedFile(
            $tmpFile,
            'ingredients_test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->ownerA)
            ->post(route('admin.inventory.ingredients.import'), [
                'file' => $uploadedFile,
                'update_existing' => 1,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ingredients', [
            'vendor_id' => $this->vendorA->id,
            'sku' => 'FLOUR-001',
            'name' => 'Ցորենի ալյուր բարձր տեսակի',
            'unit_id' => $this->kg->id,
            'current_cost' => 450,
        ]);

        $this->assertDatabaseHas('ingredients', [
            'vendor_id' => $this->vendorA->id,
            'sku' => 'BUTTER-01',
            'name' => 'Կարագ 82.5%',
            'current_cost' => 4200,
        ]);

        $this->assertDatabaseHas('ingredient_categories', [
            'vendor_id' => $this->vendorA->id,
            'name' => 'Հացահատիկ և ալյուր',
        ]);

        @unlink($tmpFile);
    }

    public function test_can_import_and_update_existing_ingredient_by_sku(): void
    {
        $existing = Ingredient::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Old Butter Name',
            'sku' => 'BUTTER-01',
            'type' => Ingredient::TYPE_RAW_MATERIAL,
            'unit_id' => $this->kg->id,
            'current_cost' => 2000,
            'min_stock' => 1,
            'is_active' => true,
        ]);

        $csvContent = "\xEF\xBB\xBF"
            ."Name,SKU,Type,Category,Unit,Cost,Min Stock\n"
            ."Updated Butter 82.5%,BUTTER-01,raw_material,Dairy,kg,4500,10\n";

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_csv_').'.csv';
        file_put_contents($tmpFile, $csvContent);

        $uploadedFile = new UploadedFile(
            $tmpFile,
            'ingredients.csv',
            'text/csv',
            null,
            true
        );

        $response = $this->actingAs($this->ownerA)
            ->post(route('admin.inventory.ingredients.import'), [
                'file' => $uploadedFile,
                'update_existing' => 1,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $existing->refresh();
        $this->assertEquals('Updated Butter 82.5%', $existing->name);
        $this->assertEquals(4500.0, (float) $existing->current_cost);
        $this->assertEquals(10.0, (float) $existing->min_stock);

        @unlink($tmpFile);
    }

    public function test_import_validates_file_presence_and_format(): void
    {
        $response = $this->actingAs($this->ownerA)
            ->post(route('admin.inventory.ingredients.import'), [
                'file' => '',
            ]);

        $response->assertSessionHasErrors(['file']);
    }
}
