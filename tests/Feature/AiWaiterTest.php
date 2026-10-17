<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiWaiterTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected User $user;

    protected Location $location;

    protected Category $categoryMains;

    protected Category $categoryDrinks;

    protected Product $steakProduct;

    protected Product $salmonProduct;

    protected Product $wineProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $this->vendor = Vendor::create([
            'name' => 'Sommelier Bistro',
            'slug' => 'sommelier-bistro',
            'email' => 'waiter@bistro.am',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
            'service_fee_enabled' => false,
            'delivery_enabled' => false,
            'service_fee_type' => 'percent',
            'service_fee_value' => 0,
            'delivery_fee' => 0,
            'delivery_min_amount' => 0,
            'ai_waiter_enabled' => true,
            'ai_waiter_name' => 'Ալեքս AI',
            'ai_waiter_priority_ingredients' => 'Black Angus, Սաղմոն',
            'ai_waiter_welcome_text' => 'Բարի գալուստ! Կօգնե՞մ ընտրել լավագույն ուտեստը:',
        ]);

        $this->user = User::create([
            'name' => 'Owner',
            'email' => 'waiter@bistro.am',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
        ]);

        $this->location = Location::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch',
            'address' => 'Northern Ave 5',
            'is_active' => true,
        ]);

        $this->categoryMains = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Signature Steaks & Mains',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->categoryDrinks = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Fine Wines & Cocktails',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->steakProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->categoryMains->id,
            'name' => 'Ribeye Steak',
            'name_translations' => ['hy' => 'Ռիբայ Սթեյք', 'en' => 'Ribeye Steak'],
            'description' => 'Prime aged Black Angus ribeye with roasted garlic butter',
            'price' => 12500,
            'is_available' => true,
            'is_featured' => true,
        ]);

        $this->salmonProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->categoryMains->id,
            'name' => 'Norwegian Salmon',
            'name_translations' => ['hy' => 'Նորվեգական Սաղմոն', 'en' => 'Norwegian Salmon'],
            'description' => 'Pan-seared fresh salmon fillet with asparagus and lemon butter sauce',
            'description_translations' => ['hy' => 'Թարմ սաղմոն կիտրոնի սոուսով', 'en' => 'Pan-seared fresh salmon fillet with asparagus and lemon butter sauce'],
            'price' => 8900,
            'is_available' => true,
            'is_featured' => false,
        ]);

        $this->wineProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->categoryDrinks->id,
            'name' => 'Reserve Red Wine',
            'description' => 'Full-bodied aged Areni red wine',
            'price' => 4500,
            'is_available' => true,
        ]);
    }

    public function test_vendor_can_save_ai_waiter_settings(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.settings.update'), [
            'name' => 'Sommelier Bistro Updated',
            'ai_waiter_enabled' => 1,
            'ai_waiter_name' => 'Շեֆ Խորհրդատու',
            'ai_waiter_priority_ingredients' => 'Տրյուֆել, Սաղմոն, Black Angus, Պիստակ',
            'ai_waiter_welcome_text' => 'Բարի գալուստ մեր ռեստորան!',
            'service_fee_type' => 'percent',
            'service_fee_value' => 10,
            'delivery_fee' => 1000,
            'delivery_min_amount' => 5000,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->vendor->refresh();
        $this->assertTrue($this->vendor->ai_waiter_enabled);
        $this->assertEquals('Շեֆ Խորհրդատու', $this->vendor->ai_waiter_name);
        $this->assertStringContainsString('Տրյուֆել', $this->vendor->ai_waiter_priority_ingredients);
        $this->assertContains('Տրյուֆել', $this->vendor->getAiWaiterPriorityIngredientsList());
        $this->assertContains('Սաղմոն', $this->vendor->getAiWaiterPriorityIngredientsList());
    }

    public function test_client_can_receive_ai_recommendations_with_preferences(): void
    {
        $response = $this->postJson(route('client.ai_waiter.recommend', ['vendor_slug' => $this->vendor->slug]), [
            'craving' => 'meat',
            'occasion' => 'romantic',
            'drink_preference' => 'wine',
            'lang' => 'hy',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'waiter_name',
            'commentary',
            'recommendations' => [
                '*' => [
                    'id',
                    'name',
                    'category_name',
                    'description',
                    'price',
                    'formatted_price',
                    'reason',
                    'pairings' => [
                        'pairing_note',
                        'drink',
                    ],
                ],
            ],
        ]);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertNotEmpty($data['recommendations']);

        // First recommendation should be Ribeye Steak
        $firstRec = $data['recommendations'][0];
        $this->assertEquals($this->steakProduct->id, $firstRec['id']);
        $this->assertNotNull($firstRec['pairings']['drink']);
        $this->assertEquals($this->wineProduct->id, $firstRec['pairings']['drink']['id']);
    }

    public function test_priority_ingredients_are_boosted_in_recommendations(): void
    {
        // Prioritize Salmon
        $this->vendor->update([
            'ai_waiter_priority_ingredients' => 'Սաղմոն, Norwegian Salmon',
            'supported_languages' => [
                ['code' => 'hy', 'name' => 'Հայերեն', 'flag' => '🇦🇲'],
                ['code' => 'en', 'name' => 'English', 'flag' => '🇬🇧'],
            ],
            'ai_waiter_config' => [
                'languages' => ['hy', 'en'],
            ],
        ]);

        $response = $this->postJson(route('client.ai_waiter.recommend', ['vendor_slug' => $this->vendor->slug]), [
            'craving' => 'seafood',
            'lang' => 'hy',
        ]);

        $response->assertOk();
        $recs = $response->json('recommendations');
        $this->assertNotEmpty($recs);
        $this->assertEquals($this->salmonProduct->id, $recs[0]['id']);
        $this->assertStringContainsString('Սաղմոն', $recs[0]['reason']);
    }

    public function test_pairing_recommendations_endpoint(): void
    {
        $response = $this->getJson(route('client.ai_waiter.pairings', [
            'vendor_slug' => $this->vendor->slug,
            'product_id' => $this->steakProduct->id,
        ]));

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'product_id',
            'pairings' => [
                'pairing_note',
                'drink',
            ],
        ]);

        $drink = $response->json('pairings.drink');
        $this->assertNotNull($drink);
        $this->assertEquals($this->wineProduct->id, $drink['id']);
    }

    public function test_storefront_menu_renders_ai_waiter_components(): void
    {
        $response = $this->get(route('client.menu', ['vendor_slug' => $this->vendor->slug]));

        $response->assertOk();
        $response->assertSee('ai-welcome-modal-backdrop', false);
        $response->assertSee('fullscreen-ai-waiter-backdrop', false);
        $response->assertSee('ai-waiter-floating-bubble', false);
        $response->assertSee($this->vendor->getAiWaiterName());
    }
}
