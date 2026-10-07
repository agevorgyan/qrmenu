<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuTemplate;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Services\MenuManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductStorageCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected Vendor $vendor;

    protected User $user;

    protected Category $category;

    protected MenuManagementService $menuService;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->vendor = Vendor::create([
            'name' => 'Cleanup Bistro',
            'slug' => 'cleanup-bistro',
            'email' => 'cleanup@bistro.com',
            'password' => bcrypt('password123'),
        ]);

        $this->user = User::create([
            'name' => 'Owner',
            'email' => 'owner@bistro.com',
            'password' => bcrypt('password123'),
            'vendor_id' => $this->vendor->id,
            'role' => 'vendor_owner',
        ]);

        $this->category = Category::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Pizzas',
        ]);

        $this->menuService = app(MenuManagementService::class);
    }

    public function test_creating_product_with_image_stores_file_on_public_disk(): void
    {
        $file = UploadedFile::fake()->image('pizza.jpg', 400, 400);

        $product = $this->menuService->createProduct(
            vendor: $this->vendor,
            data: [
                'category_id' => $this->category->id,
                'name' => 'Margherita',
                'price' => 2800,
            ],
            imageFile: $file
        );

        $this->assertNotNull($product->image);
        $this->assertStringStartsWith("/storage/vendors/{$this->vendor->uuid}/products/", $product->image);

        $relativeDiskPath = str_replace('/storage/', '', $product->image);
        Storage::disk('public')->assertExists($relativeDiskPath);
    }

    public function test_updating_product_with_new_image_deletes_old_image_file(): void
    {
        $firstFile = UploadedFile::fake()->image('old_pizza.jpg', 400, 400);
        $product = $this->menuService->createProduct(
            vendor: $this->vendor,
            data: [
                'category_id' => $this->category->id,
                'name' => 'Quattro Formaggi',
                'price' => 3200,
            ],
            imageFile: $firstFile
        );

        $oldPath = str_replace('/storage/', '', $product->image);
        Storage::disk('public')->assertExists($oldPath);

        // Update product with a brand new image
        $newFile = UploadedFile::fake()->image('new_pizza.jpg', 400, 400);
        $this->menuService->updateProduct(
            product: $product,
            data: [
                'category_id' => $this->category->id,
                'name' => 'Quattro Formaggi Extra',
                'price' => 3500,
            ],
            imageFile: $newFile
        );

        $product->refresh();
        $newPath = str_replace('/storage/', '', $product->image);

        $this->assertNotSame($oldPath, $newPath);
        // The old file must be purged from disk
        Storage::disk('public')->assertMissing($oldPath);
        // The new file must exist
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_deleting_product_purges_image_from_public_storage(): void
    {
        $file = UploadedFile::fake()->image('to_delete.jpg', 400, 400);
        $product = $this->menuService->createProduct(
            vendor: $this->vendor,
            data: [
                'category_id' => $this->category->id,
                'name' => 'Calzone',
                'price' => 3000,
            ],
            imageFile: $file
        );

        $diskPath = str_replace('/storage/', '', $product->image);
        Storage::disk('public')->assertExists($diskPath);

        // Delete product via controller or service
        $this->actingAs($this->user)
            ->delete(route('admin.menu.products.destroy', ['product' => $product->id]));

        // Assert product row is soft deleted in DB
        $this->assertSoftDeleted('products', ['id' => $product->id]);

        // Assert file is deleted from public storage disk
        Storage::disk('public')->assertMissing($diskPath);
    }

    public function test_deleting_product_with_external_image_does_not_throw(): void
    {
        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Unsplash Salad',
            'price' => 1800,
            'image' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600',
        ]);

        $this->actingAs($this->user)
            ->delete(route('admin.menu.products.destroy', ['product' => $product->id]));

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_branding_controller_cleans_up_old_logo_and_cover_when_reuploaded(): void
    {
        $template = MenuTemplate::create([
            'name' => 'Bistro',
            'slug' => 'modern-bistro',
            'blade_view' => 'modern-bistro',
            'is_active' => true,
        ]);

        // 1. First upload
        $logo1 = UploadedFile::fake()->image('logo1.png', 200, 200);
        $cover1 = UploadedFile::fake()->image('cover1.jpg', 800, 400);

        $this->actingAs($this->user)->post(route('admin.branding.update'), [
            'menu_template_id' => $template->id,
            'primary_color' => '#ff0000',
            'theme_mode' => 'dark',
            'logo_file' => $logo1,
            'cover_file' => $cover1,
        ]);

        $this->vendor->refresh();
        $this->assertStringStartsWith("/storage/vendors/{$this->vendor->uuid}/branding/", $this->vendor->logo);
        $this->assertStringStartsWith("/storage/vendors/{$this->vendor->uuid}/branding/", $this->vendor->cover_image);

        $oldLogo = str_replace('/storage/', '', $this->vendor->logo);
        $oldCover = str_replace('/storage/', '', $this->vendor->cover_image);

        Storage::disk('public')->assertExists($oldLogo);
        Storage::disk('public')->assertExists($oldCover);

        // 2. Second upload replaces them
        $logo2 = UploadedFile::fake()->image('logo2.png', 200, 200);
        $cover2 = UploadedFile::fake()->image('cover2.jpg', 800, 400);

        $this->actingAs($this->user)->post(route('admin.branding.update'), [
            'menu_template_id' => $template->id,
            'primary_color' => '#00ff00',
            'theme_mode' => 'light',
            'logo_file' => $logo2,
            'cover_file' => $cover2,
        ]);

        $this->vendor->refresh();
        $newLogo = str_replace('/storage/', '', $this->vendor->logo);
        $newCover = str_replace('/storage/', '', $this->vendor->cover_image);

        $this->assertNotSame($oldLogo, $newLogo);
        $this->assertNotSame($oldCover, $newCover);

        // Old files should be purged
        Storage::disk('public')->assertMissing($oldLogo);
        Storage::disk('public')->assertMissing($oldCover);

        // New files should exist
        Storage::disk('public')->assertExists($newLogo);
        Storage::disk('public')->assertExists($newCover);
    }

    public function test_branding_update_succeeds_when_submitting_existing_storage_paths(): void
    {
        $template = MenuTemplate::create([
            'name' => 'Bistro 2',
            'slug' => 'modern-bistro-2',
            'blade_view' => 'modern-bistro',
            'is_active' => true,
        ]);

        $logo = UploadedFile::fake()->image('logo.png', 200, 200);
        $cover = UploadedFile::fake()->image('cover.jpg', 800, 400);

        // Upload initial images
        $this->actingAs($this->user)->post(route('admin.branding.update'), [
            'menu_template_id' => $template->id,
            'primary_color' => '#ff0000',
            'theme_mode' => 'dark',
            'logo_file' => $logo,
            'cover_file' => $cover,
        ]);

        $this->vendor->refresh();
        $storedLogo = $this->vendor->logo;
        $storedCover = $this->vendor->cover_image;

        // Now edit colors/settings while submitting the existing storage paths in logo & cover_image inputs
        $response = $this->actingAs($this->user)->post(route('admin.branding.update'), [
            'menu_template_id' => $template->id,
            'primary_color' => '#123456',
            'theme_mode' => 'light',
            'logo' => $storedLogo,
            'cover_image' => $storedCover,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->vendor->refresh();
        $this->assertEquals('#123456', $this->vendor->primary_color);
        $this->assertEquals('light', $this->vendor->theme_mode);
        $this->assertEquals($storedLogo, $this->vendor->logo);
        $this->assertEquals($storedCover, $this->vendor->cover_image);

        // Files must still exist
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $storedLogo));
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $storedCover));
    }

    public function test_branding_update_normalizes_full_storage_urls(): void
    {
        $template = MenuTemplate::create([
            'name' => 'Bistro 3',
            'slug' => 'modern-bistro-3',
            'blade_view' => 'modern-bistro',
            'is_active' => true,
        ]);

        $logo = UploadedFile::fake()->image('logo.png', 200, 200);

        $this->actingAs($this->user)->post(route('admin.branding.update'), [
            'menu_template_id' => $template->id,
            'primary_color' => '#ff0000',
            'theme_mode' => 'dark',
            'logo_file' => $logo,
        ]);

        $this->vendor->refresh();
        $storedLogo = $this->vendor->logo;
        $fullLogoUrl = 'https://menu.elab.am'.$storedLogo;

        // Submit with full domain URL
        $response = $this->actingAs($this->user)->post(route('admin.branding.update'), [
            'menu_template_id' => $template->id,
            'primary_color' => '#990000',
            'theme_mode' => 'dark',
            'logo' => $fullLogoUrl,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->vendor->refresh();
        $this->assertEquals($storedLogo, $this->vendor->logo);
    }

    public function test_branding_blade_renders_text_inputs_for_logo_and_cover(): void
    {
        $response = $this->actingAs($this->user)->get(route('admin.branding.index'));

        $response->assertOk();
        $response->assertSee('<input type="text" name="logo" id="logoUrlInput"', false);
        $response->assertSee('<input type="text" name="cover_image" id="coverUrlInput"', false);
        $response->assertDontSee('<input type="url" name="logo"', false);
        $response->assertDontSee('<input type="url" name="cover_image"', false);
    }
}
