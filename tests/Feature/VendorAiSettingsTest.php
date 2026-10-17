<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AiWaiterService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VendorAiSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);

        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        $this->vendor = Vendor::create([
            'name' => 'Gourmet Bistro',
            'slug' => 'gourmet-bistro',
            'email' => 'ai@bistro.com',
            'password' => bcrypt('password'),
            'subscription_plan' => 'pro',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Bistro Owner',
            'email' => 'ai@bistro.com',
            'password' => bcrypt('password'),
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
            'email_verified_at' => now(),
        ]);
    }

    public function test_vendor_can_view_ai_settings_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('admin.settings.ai'));

        $response->assertStatus(200);
        $response->assertSee(__('AI Settings & Models'));
        $response->assertSee('Google Gemini');
        $response->assertSee('OpenAI');
        $response->assertSee('Anthropic Claude');
        $response->assertSee('DeepSeek');
        $response->assertSee('Groq');
        $response->assertSee('OpenRouter');
        $response->assertSee(__('AI Waiter Assistant'));
    }

    public function test_vendor_can_update_ai_provider_and_waiter_settings(): void
    {
        $payload = [
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4o-mini',
            'ai_api_key' => 'sk-vendor-openai-secret-key-12345',
            'ai_waiter_enabled' => '1',
            'ai_waiter_name' => 'Շեֆ Ալեքս',
            'ai_waiter_priority_ingredients' => 'Սաղմոն, Տրյուֆել, Black Angus',
            'ai_waiter_welcome_text' => 'Բարի օր! Ես Ձեր շեֆ խորհրդատուն եմ:',
        ];

        $response = $this->actingAs($this->user)->post(route('admin.settings.ai.update'), $payload);

        $response->assertRedirect(route('admin.settings.ai'));
        $response->assertSessionHas('success');

        $this->vendor->refresh();

        $this->assertEquals('openai', $this->vendor->getAiProvider());
        $this->assertEquals('gpt-4o-mini', $this->vendor->getAiModel());
        $this->assertEquals('sk-vendor-openai-secret-key-12345', $this->vendor->getAiApiKey());
        $this->assertTrue($this->vendor->hasCustomAiConfig());

        $this->assertTrue((bool) $this->vendor->ai_waiter_enabled);
        $this->assertEquals('Շեֆ Ալեքս', $this->vendor->ai_waiter_name);
        $this->assertEquals('Բարի օր! Ես Ձեր շեֆ խորհրդատուն եմ:', $this->vendor->ai_waiter_welcome_text);
        $this->assertContains('Սաղմոն', $this->vendor->getAiWaiterPriorityIngredientsList());
        $this->assertContains('Տրյուֆել', $this->vendor->getAiWaiterPriorityIngredientsList());
        $this->assertContains('Black Angus', $this->vendor->getAiWaiterPriorityIngredientsList());
    }

    public function test_vendor_can_configure_deepseek_with_custom_model_and_base_url(): void
    {
        $payload = [
            'ai_provider' => 'deepseek',
            'ai_model' => 'custom',
            'custom_model' => 'deepseek-reasoner',
            'ai_api_key' => 'sk-deepseek-custom-key',
            'ai_base_url' => 'https://api.deepseek.com',
            'ai_waiter_enabled' => '0',
            'ai_waiter_name' => 'AI Մատուցող',
        ];

        $response = $this->actingAs($this->user)->post(route('admin.settings.ai.update'), $payload);

        $response->assertRedirect(route('admin.settings.ai'));
        $this->vendor->refresh();

        $this->assertEquals('deepseek', $this->vendor->getAiProvider());
        $this->assertEquals('deepseek-reasoner', $this->vendor->getAiModel());
        $this->assertEquals('sk-deepseek-custom-key', $this->vendor->getAiApiKey());
        $this->assertEquals('https://api.deepseek.com', $this->vendor->getAiBaseUrl());
        $this->assertFalse((bool) $this->vendor->ai_waiter_enabled);
    }

    public function test_vendor_can_clear_saved_api_key(): void
    {
        $this->vendor->update([
            'ai_settings' => [
                'provider' => 'openai',
                'model' => 'gpt-4o',
                'api_key' => 'sk-saved-key-to-remove',
            ],
        ]);

        $this->assertEquals('sk-saved-key-to-remove', $this->vendor->getAiApiKey());

        $payload = [
            'ai_provider' => 'gemini',
            'ai_model' => 'gemini-2.5-flash',
            'clear_api_key' => '1',
        ];

        $response = $this->actingAs($this->user)->post(route('admin.settings.ai.update'), $payload);

        $response->assertRedirect(route('admin.settings.ai'));
        $this->vendor->refresh();

        $this->assertNull($this->vendor->getAiApiKey());
        $this->assertEquals('gemini', $this->vendor->getAiProvider());
    }

    public function test_vendor_can_test_ai_connection_successfully(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Hello from OpenAI',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('admin.settings.ai.test'), [
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4o-mini',
            'ai_api_key' => 'sk-test-openai-fake-key',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertStringContainsString('OpenAI', $response->json('message'));
        $this->assertIsInt($response->json('latency_ms'));
    }

    public function test_test_ai_connection_reports_error_when_provider_fails(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Incorrect API key provided: sk-invalid...',
                ],
            ], 401),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('admin.settings.ai.test'), [
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4o-mini',
            'ai_api_key' => 'sk-invalid-key',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
        ]);
        $this->assertStringContainsString('Incorrect API key', $response->json('message'));
    }

    public function test_ai_waiter_service_executes_with_vendor_custom_provider(): void
    {
        $category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Steaks',
            'is_active' => true,
        ]);

        Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $category->id,
            'name' => 'Ribeye Steak',
            'price' => 8500,
            'is_available' => true,
        ]);

        $this->vendor->update([
            'ai_waiter_enabled' => true,
            'ai_settings' => [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => 'sk-vendor-active-openai',
            ],
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Բարի գալուստ OpenAI-ի կողմից! Ռիբայ սթեյքը հիանալի ընտրություն է:',
                        ],
                    ],
                ],
            ], 200),
        ]);

        /** @var AiWaiterService $waiterService */
        $waiterService = app(AiWaiterService::class);
        $result = $waiterService->recommendDishes(
            vendor: $this->vendor,
            preferences: ['craving' => 'meat'],
            prompt: 'Ի՞նչ մսային ուտեստ առաջարկեք',
            lang: 'hy'
        );

        $this->assertIsArray($result);
        $this->assertEquals('Բարի գալուստ OpenAI-ի կողմից! Ռիբայ սթեյքը հիանալի ընտրություն է:', $result['commentary']);
        $this->assertNotEmpty($result['recommendations']);

        // Verify that the request was dispatched to OpenAI endpoint with vendor's key
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer sk-vendor-active-openai');
        });
    }
}
