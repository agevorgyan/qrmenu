<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentTranslation;
use App\Models\Language;
use App\Models\Product;
use App\Models\TranslationGlossary;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLanguage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TranslationManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Vendor $vendorA;

    protected User $userA;

    protected Vendor $vendorB;

    protected User $userB;

    protected Category $categoryA;

    protected Product $productA;

    protected ContentTranslation $translationA;

    protected function setUp(): void
    {
        parent::setUp();

        // Vendor A setup
        $this->vendorA = Vendor::create([
            'name' => 'Vendor Alpha',
            'slug' => 'vendor-alpha-'.uniqid(),
            'subscription_status' => 'active',
        ]);

        $hyLang = Language::where('code', 'hy')->first();
        if ($hyLang) {
            VendorLanguage::create([
                'vendor_id' => $this->vendorA->id,
                'language_id' => $hyLang->id,
                'is_active' => true,
                'is_default' => false,
            ]);
        }

        $this->userA = User::factory()->create([
            'role' => 'vendor_owner',
            'vendor_id' => $this->vendorA->id,
        ]);

        $this->categoryA = Category::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Main Courses',
        ]);

        $this->productA = Product::create([
            'vendor_id' => $this->vendorA->id,
            'category_id' => $this->categoryA->id,
            'name' => 'Signature Steak',
            'description' => 'Prime cut ribeye steak',
            'price' => 7500,
        ]);

        $this->translationA = ContentTranslation::create([
            'vendor_id' => $this->vendorA->id,
            'translatable_type' => $this->productA->getMorphClass(),
            'translatable_id' => $this->productA->id,
            'field' => 'name',
            'locale' => 'hy',
            'source_locale' => 'en',
            'source_text' => 'Signature Steak',
            'translation' => 'Ֆիրմային Սթեյք (Draft)',
            'status' => ContentTranslation::STATUS_DRAFT,
            'source_type' => ContentTranslation::SOURCE_MANUAL,
        ]);

        // Vendor B setup (Tenant Isolation Target)
        $this->vendorB = Vendor::create([
            'name' => 'Vendor Beta',
            'slug' => 'vendor-beta-'.uniqid(),
            'subscription_status' => 'active',
        ]);

        $this->userB = User::factory()->create([
            'role' => 'vendor_owner',
            'vendor_id' => $this->vendorB->id,
        ]);
    }

    public function test_vendor_can_view_translations_index(): void
    {
        $response = $this->actingAs($this->userA)->get(route('admin.translations.index', ['locale' => 'hy']));

        $response->assertStatus(200);
        $response->assertSee(__('Translations & AI Localization'));
        $response->assertSee('Signature Steak');
    }

    public function test_tenant_isolation_vendor_b_cannot_view_or_edit_vendor_a_translation(): void
    {
        // Vendor B tries to view Vendor A's translation (blocked by BelongsToVendor scope or Policy)
        $response = $this->actingAs($this->userB)->get(route('admin.translations.edit', $this->translationA));

        $this->assertContains($response->getStatusCode(), [403, 404]);

        // Vendor B tries to modify Vendor A's translation
        $postResponse = $this->actingAs($this->userB)->post(route('admin.translations.action', $this->translationA), [
            'action' => 'save_draft',
            'translated_text' => 'Hacked Translation',
        ]);

        $this->assertContains($postResponse->getStatusCode(), [403, 404]);
        $this->assertNotEquals('Hacked Translation', $this->translationA->fresh()->translation);
    }

    public function test_vendor_can_save_draft_translation(): void
    {
        $response = $this->actingAs($this->userA)->post(route('admin.translations.action', $this->translationA), [
            'action' => 'save_draft',
            'translated_text' => 'Նոր Ֆիրմային Սթեյք',
            'notes' => 'Refined terminology',
        ]);

        $response->assertRedirect(route('admin.translations.edit', $this->translationA));
        $this->assertEquals('Նոր Ֆիրմային Սթեյք', $this->translationA->fresh()->translation);
        $this->assertEquals(ContentTranslation::STATUS_DRAFT, $this->translationA->fresh()->status);
    }

    public function test_vendor_can_approve_and_publish_translation(): void
    {
        // Approve
        $approveResponse = $this->actingAs($this->userA)->post(route('admin.translations.action', $this->translationA), [
            'action' => 'approve',
        ]);

        $approveResponse->assertRedirect(route('admin.translations.edit', $this->translationA));
        $this->assertEquals(ContentTranslation::STATUS_APPROVED, $this->translationA->fresh()->status);

        // Publish
        $publishResponse = $this->actingAs($this->userA)->post(route('admin.translations.action', $this->translationA), [
            'action' => 'publish',
        ]);

        $publishResponse->assertRedirect(route('admin.translations.edit', $this->translationA));
        $this->assertEquals(ContentTranslation::STATUS_PUBLISHED, $this->translationA->fresh()->status);

        // Verify synced into product name_translations JSON column for fast storefront loading
        $productTranslations = $this->productA->fresh()->name_translations;
        $this->assertNotNull($productTranslations);
        $this->assertEquals('Ֆիրմային Սթեյք (Draft)', $productTranslations['hy']);
    }

    public function test_vendor_can_manage_glossary_rules(): void
    {
        $createResponse = $this->actingAs($this->userA)->post(route('admin.translations.glossary.store'), [
            'term' => 'Ribeye',
            'translated_term' => 'Ռիբայ',
            'target_locale' => 'hy',
            'is_verbatim' => 0,
            'notes' => 'Prime cut ribeye',
        ]);

        $createResponse->assertRedirect(route('admin.translations.glossary.index'));

        $this->assertDatabaseHas('translation_glossaries', [
            'vendor_id' => $this->vendorA->id,
            'term' => 'Ribeye',
            'translation' => 'Ռիբայ',
        ]);

        $rule = TranslationGlossary::where('vendor_id', $this->vendorA->id)->where('term', 'Ribeye')->first();

        // Vendor B cannot delete Vendor A's glossary rule
        $forbiddenDelete = $this->actingAs($this->userB)->delete(route('admin.translations.glossary.destroy', $rule));
        $this->assertContains($forbiddenDelete->getStatusCode(), [403, 404]);
        $this->assertDatabaseHas('translation_glossaries', ['id' => $rule->id]);

        // Vendor A can delete own rule
        $deleteResponse = $this->actingAs($this->userA)->delete(route('admin.translations.glossary.destroy', $rule));
        $deleteResponse->assertRedirect(route('admin.translations.glossary.index'));
        $this->assertDatabaseMissing('translation_glossaries', ['id' => $rule->id]);
    }
}
