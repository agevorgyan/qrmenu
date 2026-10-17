<?php

namespace Tests\Feature;

use App\Jobs\TranslateMenuJob;
use App\Models\Category;
use App\Models\Location;
use App\Models\MenuTemplate;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AiMenuService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected User $user;

    protected Location $location;

    protected Category $category;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::create([
            'name' => 'Test Vendor Menu',
            'slug' => 'test-vendor-menu',
            'email' => 'vendor@test.com',
            'password' => bcrypt('password123'),
            'currency' => 'AMD',
            'is_active' => true,
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
            'allow_whatsapp_orders' => true,
            'supported_languages' => [
                ['code' => 'hy', 'name' => 'Հայերեն', 'flag' => '🇦🇲'],
                ['code' => 'en', 'name' => 'English', 'flag' => '🇬🇧'],
            ],
        ]);

        $this->user = User::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch',
            'address' => 'Center 1',
            'is_active' => true,
            'allow_whatsapp_orders' => true,
        ]);

        $this->category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Starters',
            'name_translations' => ['en' => 'Starters', 'hy' => 'Նախուտեստներ'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'French Fries',
            'name_translations' => ['en' => 'French Fries', 'hy' => 'Կարտոֆիլ ֆրի'],
            'description' => 'Crispy golden fries',
            'price' => 1200,
            'is_available' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_whatsapp_orders_toggle_can_be_updated_in_settings(): void
    {
        $this->actingAs($this->user);

        // Turn off WhatsApp orders
        $response = $this->post(route('admin.settings.update'), [
            'name' => $this->vendor->name,
            'service_fee_type' => 'percent',
            'service_fee_value' => 0,
            'delivery_fee' => 0,
            'delivery_min_amount' => 0,
            'allow_whatsapp_orders' => '0',
            'locations' => [
                $this->location->id => [
                    'name' => $this->location->name,
                    'address' => $this->location->address,
                    'allow_whatsapp_orders' => '0',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->vendor->refresh();
        $this->location->refresh();

        $this->assertFalse((bool) $this->vendor->allow_whatsapp_orders);
        $this->assertFalse($this->vendor->hasWhatsAppOrdersEnabled($this->location));

        // Storefront cart should not show the WhatsApp button when disabled
        $storefrontResponse = $this->get(route('client.menu', [
            'vendor_slug' => $this->vendor->slug,
            'location_slug' => $this->location->slug,
        ]));

        $storefrontResponse->assertOk();
        $storefrontResponse->assertDontSee('cart-whatsapp-order-btn');
    }

    public function test_whatsapp_button_shown_when_enabled(): void
    {
        $this->vendor->update(['allow_whatsapp_orders' => true]);
        $this->location->update(['allow_whatsapp_orders' => true]);

        $this->assertTrue($this->vendor->hasWhatsAppOrdersEnabled($this->location));

        $storefrontResponse = $this->get(route('client.menu', [
            'vendor_slug' => $this->vendor->slug,
            'location_slug' => $this->location->slug,
        ]));

        $storefrontResponse->assertOk();
        $storefrontResponse->assertSee('cart-whatsapp-order-btn');
    }

    public function test_vendor_can_add_edit_and_delete_languages(): void
    {
        $this->actingAs($this->user);

        // 1. Add new language (French)
        $addResponse = $this->post(route('admin.ai.languages.save'), [
            'code' => 'fr',
            'name' => 'Français',
            'flag' => '🇫🇷',
        ]);

        $addResponse->assertSessionHas('success');
        $this->vendor->refresh();
        $codes = array_column($this->vendor->getSupportedLanguages(), 'code');
        $this->assertContains('fr', $codes);

        // 2. Edit existing language
        $editResponse = $this->post(route('admin.ai.languages.save'), [
            'original_code' => 'fr',
            'code' => 'fr',
            'name' => 'Français (Modern)',
            'flag' => '🇫🇷',
        ]);

        $editResponse->assertSessionHas('success');
        $this->vendor->refresh();
        $langs = $this->vendor->getSupportedLanguages();
        $frLang = collect($langs)->firstWhere('code', 'fr');
        $this->assertEquals('Français (Modern)', $frLang['name']);

        // 3. Delete language
        $deleteResponse = $this->delete(route('admin.ai.languages.destroy', 'fr'));
        $deleteResponse->assertSessionHas('success');
        $this->vendor->refresh();
        $updatedCodes = array_column($this->vendor->getSupportedLanguages(), 'code');
        $this->assertNotContains('fr', $updatedCodes);
    }

    public function test_vendor_cannot_delete_last_remaining_language(): void
    {
        $this->actingAs($this->user);

        $this->vendor->update([
            'supported_languages' => [
                ['code' => 'en', 'name' => 'English', 'flag' => '🇬🇧'],
            ],
        ]);

        $response = $this->delete(route('admin.ai.languages.destroy', 'en'));
        $response->assertSessionHas('error');
        $this->vendor->refresh();
        $this->assertCount(1, $this->vendor->getSupportedLanguages());
    }

    public function test_translate_menu_job_respects_overwrite_existing_flag(): void
    {
        // Mock AiMenuService
        $aiMock = $this->createMock(AiMenuService::class);
        $aiMock->method('translateMenuBatch')->willReturn([
            "cat_{$this->category->id}" => 'Starters Updated',
            "prod_name_{$this->product->id}" => 'French Fries Updated',
            "prod_desc_{$this->product->id}" => 'Updated Crispy Fries',
        ]);

        $tenantContext = app(TenantContext::class);

        // Run with overwriteExisting = true
        $job = new TranslateMenuJob($this->vendor->id, 'en', true);
        $job->handle($aiMock, $tenantContext);

        $this->category->refresh();
        $this->product->refresh();

        $this->assertEquals('Starters Updated', $this->category->name_translations['en']);
        $this->assertEquals('French Fries Updated', $this->product->name_translations['en']);
        $this->assertEquals('Updated Crispy Fries', $this->product->description_translations['en']);
    }

    public function test_minimalist_light_theme_renders_correctly(): void
    {
        $template = MenuTemplate::firstOrCreate(
            ['slug' => 'minimalist-light'],
            [
                'name' => 'Minimalist Scandinavian Light',
                'preview_image' => 'https://example.com/preview.jpg',
                'description' => 'Light minimalist theme',
                'default_config' => ['primary' => '#0f172a', 'bg' => '#fbfbfa', 'card' => '#ffffff'],
                'is_active' => true,
            ]
        );

        $this->vendor->update(['menu_template_id' => $template->id]);

        $response = $this->get(route('client.menu', [
            'vendor_slug' => $this->vendor->slug,
            'location_slug' => $this->location->slug,
        ]));

        $response->assertOk();
        $response->assertSee('French Fries');
        $response->assertSee('cart-ai-recs-container');
        $response->assertSee('cartRecommendations');
    }

    public function test_ai_menu_service_translates_into_french_correctly(): void
    {
        $service = app(AiMenuService::class);
        $items = [
            'item_1' => 'Starters & Appetizers',
            'item_2' => 'Grilled Norwegian Salmon',
        ];

        $translated = $service->translateMenuBatch($items, 'fr', $this->vendor);

        $this->assertNotEmpty($translated['item_1']);
        $this->assertStringNotContainsString('(Fr)', $translated['item_1']);
        $this->assertNotEquals('Starters & Appetizers', $translated['item_1']);

        $this->assertNotEmpty($translated['item_2']);
        $this->assertStringNotContainsString('(Fr)', $translated['item_2']);
        $this->assertNotEquals('Grilled Norwegian Salmon', $translated['item_2']);
    }
}
