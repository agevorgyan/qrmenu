<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LanguageManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;

    protected User $vendorUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role' => 'superadmin',
        ]);

        $vendor = Vendor::create([
            'name' => 'Test Vendor',
            'slug' => 'test-vendor-'.uniqid(),
        ]);

        $this->vendorUser = User::factory()->create([
            'role' => 'vendor_owner',
            'vendor_id' => $vendor->id,
        ]);
    }

    public function test_superadmin_can_view_languages_index(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('superadmin.languages.index'));

        $response->assertStatus(200);
        $response->assertSee('Platform Language Management');
    }

    public function test_non_superadmin_cannot_access_languages_index(): void
    {
        $response = $this->actingAs($this->vendorUser)->get(route('superadmin.languages.index'));

        $response->assertStatus(403);
    }

    public function test_superadmin_can_register_new_language(): void
    {
        $uniqueCode = 't'.substr(uniqid(), -3);

        $response = $this->actingAs($this->superAdmin)->post(route('superadmin.languages.store'), [
            'code' => $uniqueCode,
            'name' => 'Testish Language',
            'native_name' => 'Testish',
            'flag' => '🏳️',
            'direction' => 'ltr',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('superadmin.languages.index'));
        $this->assertDatabaseHas('languages', [
            'code' => $uniqueCode,
            'name' => 'Testish Language',
            'is_active' => true,
        ]);
    }

    public function test_cannot_deactivate_system_default_language(): void
    {
        $defaultLang = Language::where('is_default', true)->first();
        if (! $defaultLang) {
            $defaultLang = Language::create([
                'code' => 'def',
                'name' => 'Default Lang',
                'native_name' => 'Default',
                'is_default' => true,
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($this->superAdmin)->post(route('superadmin.languages.toggle', $defaultLang));

        $response->assertRedirect(route('superadmin.languages.index'));
        $response->assertSessionHas('error');
        $this->assertTrue($defaultLang->fresh()->is_active);
    }

    public function test_superadmin_can_set_new_default_language(): void
    {
        $lang = Language::where('is_default', false)->first();
        if (! $lang) {
            $lang = Language::create([
                'code' => 'newdef',
                'name' => 'New Default',
                'native_name' => 'New Default',
                'is_default' => false,
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($this->superAdmin)->post(route('superadmin.languages.default', $lang));

        $response->assertRedirect(route('superadmin.languages.index'));
        $this->assertTrue($lang->fresh()->is_default);
        $this->assertTrue($lang->fresh()->is_active);
    }

    public function test_vendor_can_add_new_language_via_ai_menu_controller(): void
    {
        $vendor = $this->vendorUser->vendor;

        $response = $this->actingAs($this->vendorUser)->post(route('admin.ai.languages.save'), [
            'code' => 'it',
            'name' => 'Italiano',
            'flag' => '🇮🇹',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('languages', ['code' => 'it']);
        $this->assertDatabaseHas('vendor_languages', [
            'vendor_id' => $vendor->id,
            'is_active' => true,
        ]);

        $codes = $vendor->fresh()->getSupportedLanguageCodes();
        $this->assertContains('it', $codes);
    }

    public function test_vendor_can_delete_language_via_ai_menu_controller(): void
    {
        $vendor = $this->vendorUser->vendor;
        $vendor->syncLanguage('it', 'Italiano', '🇮🇹', isActive: true);
        $vendor->syncLanguage('es', 'Español', '🇪🇸', isActive: true);

        $response = $this->actingAs($this->vendorUser)->delete(route('admin.ai.languages.destroy', 'it'));

        $response->assertSessionHas('success');
        $codes = $vendor->fresh()->getSupportedLanguageCodes();
        $this->assertNotContains('it', $codes);
    }

    public function test_vendor_cannot_delete_last_language(): void
    {
        $vendor = $this->vendorUser->vendor;
        $vendor->ensureVendorLanguagesInitialized();

        // Keep only 1 active language
        $activeLangs = $vendor->vendorLanguages()->where('is_active', true)->get();
        foreach ($activeLangs->skip(1) as $l) {
            $l->update(['is_active' => false]);
        }
        $vendor->refreshSupportedLanguagesJson();

        $lastLang = $vendor->getSupportedLanguages()[0];
        $response = $this->actingAs($this->vendorUser)->delete(route('admin.ai.languages.destroy', $lastLang['code']));

        $response->assertSessionHas('error');
        $codes = $vendor->fresh()->getSupportedLanguageCodes();
        $this->assertContains($lastLang['code'], $codes);
    }

    public function test_vendor_can_sync_languages_via_translation_controller(): void
    {
        $vendor = $this->vendorUser->vendor;

        $response = $this->actingAs($this->vendorUser)->post(route('admin.translations.languages.sync'), [
            'active_locales' => ['en', 'hy'],
            'default_locale' => 'en',
        ]);

        $response->assertRedirect(route('admin.translations.index'));
        $response->assertSessionHas('success');

        $codes = $vendor->fresh()->getSupportedLanguageCodes();
        $this->assertContains('en', $codes);
        $this->assertContains('hy', $codes);
    }

    public function test_superadmin_reactivates_existing_language_on_duplicate_store(): void
    {
        $lang = Language::where('code', 'en')->first();
        $this->assertNotNull($lang);

        $response = $this->actingAs($this->superAdmin)->post(route('superadmin.languages.store'), [
            'code' => 'en',
            'name' => 'English Updated',
            'native_name' => 'English',
            'flag' => '🇬🇧',
            'direction' => 'ltr',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('superadmin.languages.index'));
        $response->assertSessionHas('success');
        $this->assertEquals('English Updated', $lang->fresh()->name);
    }

    public function test_adding_new_language_to_vendor_automatically_enables_it_in_ai_waiter(): void
    {
        $vendor = Vendor::create([
            'name' => 'AI Lang Test Resto',
            'slug' => 'ai-lang-test-resto',
            'email' => 'ai-test@example.com',
            'phone' => '+37499112233',
            'is_active' => true,
            'ai_waiter_enabled' => true,
            'ai_waiter_config' => [
                'languages' => ['hy', 'en', 'ru'],
            ],
        ]);

        $this->assertFalse(in_array('fr', $vendor->getAiWaiterLanguages(), true));

        // Partner / admin adds French to the menu
        $vendor->syncLanguage('fr', 'French', '🇫🇷', true);

        $fresh = $vendor->fresh();
        $aiLangs = $fresh->getAiWaiterLanguages();
        $this->assertTrue(in_array('fr', $aiLangs, true), 'French must automatically be in AI waiter languages after syncLanguage.');

        $details = $fresh->getAiWaiterLanguageDetails();
        $detailCodes = array_column($details, 'code');
        $this->assertTrue(in_array('fr', $detailCodes, true), 'French must be present in AI waiter language details.');

        // Starting session with French
        $response = $this->postJson(route('client.ai_waiter.session.start', ['vendor_slug' => $vendor->slug]), [
            'lang' => 'fr',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'language' => 'fr',
            ]);

        $this->assertContains('fr', $response->json('allowed_languages'));

        // Removing language should also remove it from AI waiter config
        $fresh->removeLanguage('fr');
        $this->assertFalse(in_array('fr', $fresh->fresh()->getAiWaiterLanguages(), true));
    }
}
