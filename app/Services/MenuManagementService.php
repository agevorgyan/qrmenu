<?php

namespace App\Services;

use App\Exceptions\StorageQuotaExceededException;
use App\Models\Category;
use App\Models\Location;
use App\Models\LocationProductOverride;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Vendor;
use App\Security\QuotaCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MenuManagementService
{
    protected StorageService $storageService;

    protected QuotaService $quotaService;

    public function __construct(
        ?StorageService $storageService = null,
        ?QuotaService $quotaService = null
    ) {
        $this->storageService = $storageService ?? app(StorageService::class);
        $this->quotaService = $quotaService ?? app(QuotaService::class);
    }

    /**
     * Build standardized multilingual translations map from input data.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $existing
     * @return array<string, string>
     */
    public function extractTranslations(array $data, string $field = 'name', array $existing = []): array
    {
        $translations = $existing;
        $primary = $data[$field] ?? null;
        if (! empty($primary)) {
            $translations['en'] = trim((string) $primary);
        }

        $transKey = "{$field}_translations";
        if (! empty($data[$transKey]) && is_array($data[$transKey])) {
            foreach ($data[$transKey] as $code => $val) {
                if (! empty($val)) {
                    $translations[$code] = trim((string) $val);
                }
            }
        }

        if (! empty($data["hy_{$field}"])) {
            $translations['hy'] = trim((string) $data["hy_{$field}"]);
        } elseif (! empty($data[$transKey]['hy'])) {
            $translations['hy'] = trim((string) $data[$transKey]['hy']);
        }

        if (! empty($data["ru_{$field}"])) {
            $translations['ru'] = trim((string) $data["ru_{$field}"]);
        } elseif (! empty($data[$transKey]['ru'])) {
            $translations['ru'] = trim((string) $data[$transKey]['ru']);
        }

        return $translations;
    }

    public function createCategory(Vendor $vendor, array $data): Category
    {
        return $this->quotaService->executeWithResourceQuota(
            $vendor,
            QuotaCatalog::KEY_CATEGORIES_COUNT,
            function (Vendor $lockedVendor) use ($data) {
                $name = $data['name'];
                $translations = $this->extractTranslations($data, 'name');

                $category = Category::create([
                    'vendor_id' => $lockedVendor->id,
                    'name' => $name,
                    'name_translations' => $translations,
                    'description' => $data['description'] ?? null,
                    'sort_order' => (int) (Category::where('vendor_id', $lockedVendor->id)->max('sort_order') ?? 0) + 1,
                    'is_active' => true,
                ]);

                TenantCache::invalidateMenu($lockedVendor);

                return $category;
            }
        );
    }

    /**
     * Update existing category.
     */
    public function updateCategory(Category $category, array $data): Category
    {
        $name = $data['name'];
        $translations = $this->extractTranslations(
            $data,
            'name',
            is_array($category->name_translations) ? $category->name_translations : []
        );

        $category->update([
            'name' => $name,
            'name_translations' => $translations,
            'description' => $data['description'] ?? null,
        ]);

        TenantCache::invalidateMenu($category->vendor_id);

        return $category;
    }

    /**
     * Delete a category.
     */
    public function deleteCategory(Category $category): void
    {
        $vendorId = $category->vendor_id;
        $category->delete();
        TenantCache::invalidateMenu($vendorId);
    }

    /**
     * Create a new dish/product with translations, nutritional info, allergens and default variation.
     */
    public function createProduct(Vendor $vendor, array $data, ?UploadedFile $imageFile = null): Product
    {
        return $this->quotaService->executeWithResourceQuota(
            $vendor,
            QuotaCatalog::KEY_PRODUCTS_COUNT,
            function (Vendor $lockedVendor) use ($data, $imageFile) {
                $imageUrl = $this->resolveProductImage(
                    imageFile: $imageFile,
                    vendor: $lockedVendor,
                    fallbackUrl: $data['image'] ?? null,
                    defaultUrl: Product::DEFAULT_IMAGE
                );

                $name = $data['name'];
                $desc = $data['description'] ?? null;

                $nameTranslations = $this->extractTranslations($data, 'name');
                $descTranslations = $this->extractTranslations($data, 'description');

                $product = Product::create([
                    'vendor_id' => $lockedVendor->id,
                    'category_id' => $data['category_id'],
                    'name' => $name,
                    'name_translations' => $nameTranslations,
                    'description' => $desc,
                    'description_translations' => $descTranslations,
                    'price' => $data['price'],
                    'discount_price' => ! empty($data['discount_price']) ? $data['discount_price'] : null,
                    'discount_days' => ! empty($data['discount_days']) ? $data['discount_days'] : null,
                    'discount_start_time' => ! empty($data['discount_start_time']) ? $data['discount_start_time'] : null,
                    'discount_end_time' => ! empty($data['discount_end_time']) ? $data['discount_end_time'] : null,
                    'is_discount_active' => array_key_exists('is_discount_active', $data) ? (bool) $data['is_discount_active'] : true,
                    'available_start_time' => ! empty($data['available_start_time']) ? $data['available_start_time'] : null,
                    'available_end_time' => ! empty($data['available_end_time']) ? $data['available_end_time'] : null,
                    'available_days' => ! empty($data['available_days']) ? $data['available_days'] : null,
                    'available_for_dine_in' => array_key_exists('available_for_dine_in', $data) ? (bool) $data['available_for_dine_in'] : true,
                    'available_for_takeaway' => array_key_exists('available_for_takeaway', $data) ? (bool) $data['available_for_takeaway'] : true,
                    'available_for_delivery' => array_key_exists('available_for_delivery', $data) ? (bool) $data['available_for_delivery'] : true,
                    'image' => $imageUrl,
                    'dietary_tags' => $data['dietary_tags'] ?? [],
                    'calories' => $data['calories'] ?? null,
                    'protein_g' => $data['protein_g'] ?? null,
                    'carbs_g' => $data['carbs_g'] ?? null,
                    'fat_g' => $data['fat_g'] ?? null,
                    'preparation_time_min' => $data['preparation_time_min'] ?? null,
                    'is_featured' => ! empty($data['is_featured']),
                    'ai_priority' => ! empty($data['ai_priority']),
                    'ai_priority_level' => (int) ($data['ai_priority_level'] ?? 0),
                    'ai_group' => ! empty($data['ai_group']) ? $data['ai_group'] : null,
                    'ai_tags' => $data['ai_tags'] ?? [],
                    'ai_spicy_level' => (int) ($data['ai_spicy_level'] ?? 0),
                    'ai_pairs_with' => $data['ai_pairs_with'] ?? [],
                    'ai_enabled' => array_key_exists('ai_enabled', $data) ? (bool) $data['ai_enabled'] : true,
                    'is_available' => true,
                    'sort_order' => (int) (Product::where('category_id', $data['category_id'])->max('sort_order') ?? 0) + 1,
                ]);

                if (array_key_exists('locations', $data)) {
                    $this->syncLocationAvailability($product, $lockedVendor, $data['locations']);
                }

                if (! empty($data['allergens'])) {
                    $product->allergens()->sync($data['allergens']);
                }

                if (! empty($data['variations']) && is_array($data['variations'])) {
                    $hasDefault = false;
                    foreach ($data['variations'] as $idx => $vData) {
                        if (empty($vData['name'])) {
                            continue;
                        }
                        $isDef = ! empty($vData['is_default']) || (! $hasDefault && $idx === 0);
                        if ($isDef) {
                            $hasDefault = true;
                        }
                        $varName = trim($vData['name']);
                        $transData = $this->extractTranslations($vData, 'name');

                        ProductVariation::create([
                            'product_id' => $product->id,
                            'name' => $varName,
                            'name_translations' => $transData,
                            'price' => (float) ($vData['price'] ?? $data['price']),
                            'is_default' => $isDef,
                        ]);
                    }
                } else {
                    // Initialize default standard portion variation
                    ProductVariation::create([
                        'product_id' => $product->id,
                        'name' => 'Standard Portion',
                        'name_translations' => [
                            'en' => 'Standard Portion',
                        ],
                        'price' => $data['price'],
                        'is_default' => true,
                    ]);
                }

                if (! empty($data['is_daily_offer'])) {
                    $lockedVendor->dailyOffers()->firstOrCreate(
                        ['product_id' => $product->id],
                        [
                            'badge' => $lockedVendor->featured_dish_badge ?: "⭐ TODAY'S SPECIAL",
                            'subtitle' => $product->description,
                            'is_active' => true,
                            'sort_order' => (int) ($lockedVendor->dailyOffers()->max('sort_order') ?? 0) + 1,
                        ]
                    );
                    if (! $lockedVendor->featured_dish_enabled) {
                        $lockedVendor->update(['featured_dish_enabled' => true]);
                    }
                    if (! $lockedVendor->featured_product_id) {
                        $lockedVendor->update(['featured_product_id' => $product->id]);
                    }
                }

                TenantCache::invalidateMenu($lockedVendor);

                return $product;
            }
        );
    }

    /**
     * Update an existing product, handling image replacement and allergen relationships.
     */
    public function updateProduct(Product $product, array $data, ?UploadedFile $imageFile = null): Product
    {
        if ($imageFile && $imageFile->isValid()) {
            try {
                $storageFile = $this->storageService->replace(
                    oldPathOrUuid: $product->image,
                    newFile: $imageFile,
                    namespace: 'products',
                    vendor: $product->vendor,
                    entity: $product
                );
                $imageUrl = $storageFile->getUrl();
            } catch (StorageQuotaExceededException $e) {
                throw ValidationException::withMessages([
                    'image_file' => $e->getMessage(),
                ]);
            } catch (\Throwable $e) {
                Log::error('Product image replacement failed: '.$e->getMessage(), ['exception' => $e]);

                throw ValidationException::withMessages([
                    'image_file' => 'Նկարի փոխարինումը ձախողվեց: '.$e->getMessage(),
                ]);
            }
        } else {
            $rawImage = ! empty($data['image']) ? $data['image'] : ($product->image ?: Product::DEFAULT_IMAGE);
            if (! empty($rawImage)) {
                $parsed = parse_url($rawImage, PHP_URL_PATH);
                if ($parsed && str_starts_with($parsed, '/storage/')) {
                    $rawImage = $parsed;
                }
            }
            $imageUrl = $rawImage;

            if (array_key_exists('image', $data) && $imageUrl !== $product->image && ! empty($product->image)) {
                $product->deleteImageFile();
            }
        }

        $name = $data['name'];
        $desc = $data['description'] ?? null;

        $nameTranslations = $this->extractTranslations(
            $data,
            'name',
            is_array($product->name_translations) ? $product->name_translations : []
        );
        $descTranslations = $this->extractTranslations(
            $data,
            'description',
            is_array($product->description_translations) ? $product->description_translations : []
        );

        $updatePayload = [
            'category_id' => $data['category_id'],
            'name' => $name,
            'name_translations' => $nameTranslations,
            'description' => $desc,
            'description_translations' => $descTranslations,
            'price' => $data['price'],
            'discount_price' => ! empty($data['discount_price']) ? $data['discount_price'] : null,
            'discount_days' => ! empty($data['discount_days']) ? $data['discount_days'] : null,
            'discount_start_time' => ! empty($data['discount_start_time']) ? $data['discount_start_time'] : null,
            'discount_end_time' => ! empty($data['discount_end_time']) ? $data['discount_end_time'] : null,
            'is_discount_active' => array_key_exists('is_discount_active', $data) ? (bool) $data['is_discount_active'] : true,
            'image' => $imageUrl,
            'dietary_tags' => $data['dietary_tags'] ?? [],
            'calories' => $data['calories'] ?? null,
            'protein_g' => $data['protein_g'] ?? null,
            'carbs_g' => $data['carbs_g'] ?? null,
            'fat_g' => $data['fat_g'] ?? null,
            'preparation_time_min' => $data['preparation_time_min'] ?? null,
            'is_featured' => ! empty($data['is_featured']),
        ];

        if (array_key_exists('available_start_time', $data)) {
            $updatePayload['available_start_time'] = ! empty($data['available_start_time']) ? $data['available_start_time'] : null;
        }
        if (array_key_exists('available_end_time', $data)) {
            $updatePayload['available_end_time'] = ! empty($data['available_end_time']) ? $data['available_end_time'] : null;
        }
        if (array_key_exists('available_days', $data)) {
            $updatePayload['available_days'] = ! empty($data['available_days']) ? $data['available_days'] : null;
        }
        if (array_key_exists('available_for_dine_in', $data)) {
            $updatePayload['available_for_dine_in'] = (bool) $data['available_for_dine_in'];
        }
        if (array_key_exists('available_for_takeaway', $data)) {
            $updatePayload['available_for_takeaway'] = (bool) $data['available_for_takeaway'];
        }
        if (array_key_exists('available_for_delivery', $data)) {
            $updatePayload['available_for_delivery'] = (bool) $data['available_for_delivery'];
        }

        if (array_key_exists('ai_priority', $data)) {
            $updatePayload['ai_priority'] = (bool) $data['ai_priority'];
        }
        if (array_key_exists('ai_priority_level', $data)) {
            $updatePayload['ai_priority_level'] = (int) $data['ai_priority_level'];
        }
        if (array_key_exists('ai_group', $data)) {
            $updatePayload['ai_group'] = ! empty($data['ai_group']) ? $data['ai_group'] : null;
        }
        if (array_key_exists('ai_tags', $data)) {
            $updatePayload['ai_tags'] = (array) $data['ai_tags'];
        }
        if (array_key_exists('ai_spicy_level', $data)) {
            $updatePayload['ai_spicy_level'] = (int) $data['ai_spicy_level'];
        }
        if (array_key_exists('ai_pairs_with', $data)) {
            $updatePayload['ai_pairs_with'] = (array) $data['ai_pairs_with'];
        }
        if (array_key_exists('ai_enabled', $data)) {
            $updatePayload['ai_enabled'] = (bool) $data['ai_enabled'];
        }

        if (array_key_exists('is_available', $data)) {
            $updatePayload['is_available'] = (bool) $data['is_available'];
        }

        $product->update($updatePayload);

        if (array_key_exists('locations', $data)) {
            $this->syncLocationAvailability($product, $product->vendor, $data['locations']);
        }

        if (! empty($data['allergens'])) {
            $product->allergens()->sync($data['allergens']);
        } else {
            $product->allergens()->detach();
        }

        if (array_key_exists('variations', $data) && is_array($data['variations'])) {
            $keptIds = [];
            $hasDefault = false;
            foreach ($data['variations'] as $v) {
                if (! empty($v['is_default'])) {
                    $hasDefault = true;
                    break;
                }
            }

            foreach ($data['variations'] as $idx => $varData) {
                if (empty($varData['name'])) {
                    continue;
                }
                $isDef = ! empty($varData['is_default']) || (! $hasDefault && $idx === 0);
                if ($isDef) {
                    $hasDefault = true;
                }

                $varName = trim($varData['name']);
                $transData = $this->extractTranslations($varData, 'name');

                $varId = ! empty($varData['id']) ? (int) $varData['id'] : null;
                $existing = $varId ? ProductVariation::where('product_id', $product->id)->find($varId) : null;

                if ($existing) {
                    $existing->update([
                        'name' => $varName,
                        'name_translations' => $transData,
                        'price' => (float) ($varData['price'] ?? $product->price),
                        'is_default' => $isDef,
                    ]);
                    $keptIds[] = $existing->id;
                } else {
                    $newVar = ProductVariation::create([
                        'product_id' => $product->id,
                        'name' => $varName,
                        'name_translations' => $transData,
                        'price' => (float) ($varData['price'] ?? $product->price),
                        'is_default' => $isDef,
                    ]);
                    $keptIds[] = $newVar->id;
                }
            }

            if (! empty($keptIds)) {
                ProductVariation::where('product_id', $product->id)
                    ->whereNotIn('id', $keptIds)
                    ->delete();
            }
        }

        if (array_key_exists('is_daily_offer', $data)) {
            $vendor = $product->vendor;
            if (! empty($data['is_daily_offer'])) {
                $vendor->dailyOffers()->firstOrCreate(
                    ['product_id' => $product->id],
                    [
                        'badge' => $vendor->featured_dish_badge ?: "⭐ TODAY'S SPECIAL",
                        'subtitle' => $product->description,
                        'is_active' => true,
                        'sort_order' => (int) ($vendor->dailyOffers()->max('sort_order') ?? 0) + 1,
                    ]
                );
                if (! $vendor->featured_dish_enabled) {
                    $vendor->update(['featured_dish_enabled' => true]);
                }
                if (! $vendor->featured_product_id) {
                    $vendor->update(['featured_product_id' => $product->id]);
                }
            } else {
                $vendor->dailyOffers()->where('product_id', $product->id)->delete();
                if ($vendor->featured_product_id == $product->id) {
                    $vendor->update(['featured_product_id' => $vendor->dailyOffers()->value('product_id')]);
                }
            }
        }

        TenantCache::invalidateMenu($product->vendor_id);

        return $product;
    }

    /**
     * Synchronize branch availability overrides for a product.
     */
    public function syncLocationAvailability(Product $product, Vendor $vendor, ?array $selectedLocationIds): void
    {
        if ($selectedLocationIds === null) {
            return;
        }

        $locations = Location::where('vendor_id', $vendor->id)->get();
        $selectedInts = array_map('intval', $selectedLocationIds);

        foreach ($locations as $loc) {
            $isAvailable = in_array((int) $loc->id, $selectedInts, true);

            $override = LocationProductOverride::firstOrNew([
                'vendor_id' => $vendor->id,
                'location_id' => $loc->id,
                'product_id' => $product->id,
            ]);
            $override->is_available = $isAvailable;
            $override->save();
        }
    }

    /**
     * Toggle product availability on/off (either globally or for a specific branch).
     */
    public function toggleProductAvailability(Product $product, ?int $locationId = null): bool
    {
        if ($locationId) {
            $override = LocationProductOverride::where('vendor_id', $product->vendor_id)
                ->where('location_id', $locationId)
                ->where('product_id', $product->id)
                ->first();

            $currentStatus = $override !== null ? (bool) $override->is_available : (bool) $product->is_available;
            $newStatus = ! $currentStatus;

            $locOverride = LocationProductOverride::firstOrNew([
                'vendor_id' => $product->vendor_id,
                'location_id' => $locationId,
                'product_id' => $product->id,
            ]);
            $locOverride->is_available = $newStatus;
            $locOverride->save();

            TenantCache::invalidateMenu($product->vendor_id);

            return $newStatus;
        }

        $product->update(['is_available' => ! $product->is_available]);
        TenantCache::invalidateMenu($product->vendor_id);

        return (bool) $product->is_available;
    }

    /**
     * Save branch/location pricing & availability override for a product.
     */
    public function saveLocationOverride(Product $product, array $data): LocationProductOverride
    {
        $location = Location::findOrFail($data['location_id']);

        if ((int) $location->vendor_id !== (int) $product->vendor_id) {
            throw new \InvalidArgumentException('Cross-vendor override attempt: location and product do not belong to the same vendor.');
        }

        $override = LocationProductOverride::updateOrCreate(
            [
                'vendor_id' => $product->vendor_id,
                'location_id' => $location->id,
                'product_id' => $product->id,
            ],
            [
                'override_price' => $data['override_price'] ?? null,
                'is_available' => (bool) $data['is_available'],
            ]
        );

        TenantCache::invalidateMenu($product->vendor_id);

        return $override;
    }

    /**
     * Delete a product and its associated media file.
     */
    public function deleteProduct(Product $product): void
    {
        $vendorId = $product->vendor_id;
        $product->deleteImageFile();
        $product->delete();
        TenantCache::invalidateMenu($vendorId);
    }

    /**
     * Helper to store uploaded file or return fallback image URL.
     */
    protected function resolveProductImage(?UploadedFile $imageFile, ?Vendor $vendor, ?string $fallbackUrl, ?string $defaultUrl): ?string
    {
        if ($imageFile && $imageFile->isValid()) {
            try {
                $storageFile = $this->storageService->store(
                    file: $imageFile,
                    namespace: 'products',
                    vendor: $vendor
                );

                return $storageFile->getUrl();
            } catch (StorageQuotaExceededException $e) {
                throw ValidationException::withMessages([
                    'image_file' => $e->getMessage(),
                ]);
            } catch (\Throwable $e) {
                Log::error('Product image upload failed: '.$e->getMessage(), ['exception' => $e]);

                throw ValidationException::withMessages([
                    'image_file' => 'Նկարի վերբեռնումը ձախողվեց: '.$e->getMessage(),
                ]);
            }
        }

        if (! empty($fallbackUrl)) {
            $parsed = parse_url($fallbackUrl, PHP_URL_PATH);
            if ($parsed && str_starts_with($parsed, '/storage/')) {
                return $parsed;
            }

            return $fallbackUrl;
        }

        return $defaultUrl;
    }

    /**
     * Bulk update multiple products for a vendor safely within a transaction.
     *
     * @param  array<int>  $productIds
     * @param  array<string, mixed>  $actions
     * @return array{updated_count: int, product_ids: array<int>}
     */
    public function bulkUpdateProducts(Vendor $vendor, array $productIds, array $actions): array
    {
        if (empty($productIds)) {
            return ['updated_count' => 0, 'product_ids' => []];
        }

        // Bounded payload safeguard (max 250 products per batch)
        $cleanIds = array_slice(array_unique(array_map('intval', $productIds)), 0, 250);

        // Multi-tenant isolation: verify all target products belong to the authenticated vendor
        $products = Product::where('vendor_id', $vendor->id)
            ->whereIn('id', $cleanIds)
            ->get();

        if ($products->isEmpty()) {
            return ['updated_count' => 0, 'product_ids' => []];
        }

        // Validate target category if category reassignment is requested
        $targetCategoryId = null;
        if (! empty($actions['category_id'])) {
            $cat = Category::where('vendor_id', $vendor->id)->find($actions['category_id']);
            if (! $cat) {
                throw ValidationException::withMessages([
                    'category_id' => __('The selected target category does not belong to your restaurant.'),
                ]);
            }
            $targetCategoryId = (int) $cat->id;
        }

        $priceAction = $actions['price_action'] ?? 'none';
        $priceValue = isset($actions['price_value']) ? (float) $actions['price_value'] : null;
        $priceRounding = $actions['price_rounding'] ?? 'none';

        if ($priceAction !== 'none' && ($priceValue === null || $priceValue < 0)) {
            throw ValidationException::withMessages([
                'price_value' => __('Price adjustment value must be a non-negative number.'),
            ]);
        }

        $updatedIds = [];

        DB::transaction(function () use (
            $products,
            $priceAction,
            $priceValue,
            $priceRounding,
            $targetCategoryId,
            $actions,
            &$updatedIds
        ) {
            foreach ($products as $product) {
                $dirty = false;

                // 1. Price Adjustment
                if ($priceAction !== 'none' && $priceValue !== null) {
                    $oldPrice = (float) $product->price;
                    $newPrice = match ($priceAction) {
                        'set' => $priceValue,
                        'increase_amount' => $oldPrice + $priceValue,
                        'decrease_amount' => max(0.0, $oldPrice - $priceValue),
                        'increase_percent' => $oldPrice * (1 + ($priceValue / 100)),
                        'decrease_percent' => max(0.0, $oldPrice * (1 - ($priceValue / 100))),
                        default => $oldPrice,
                    };

                    // Apply documented rounding rule
                    $newPrice = match ($priceRounding) {
                        'nearest_10' => round($newPrice / 10) * 10,
                        'nearest_50' => round($newPrice / 50) * 50,
                        'nearest_100' => round($newPrice / 100) * 100,
                        'integer' => round($newPrice),
                        default => round($newPrice, 2),
                    };

                    // Pricing safety constraint: never allow negative price
                    $newPrice = max(0.0, (float) $newPrice);

                    $product->price = $newPrice;
                    $dirty = true;

                    // Sync default portion variation price
                    ProductVariation::where('product_id', $product->id)
                        ->where('is_default', true)
                        ->update(['price' => $newPrice]);
                }

                // 2. Category Reassignment
                if ($targetCategoryId !== null && (int) $product->category_id !== $targetCategoryId) {
                    $product->category_id = $targetCategoryId;
                    $dirty = true;
                }

                // 3. Availability (In stock / Out of stock)
                if (array_key_exists('is_available', $actions) && $actions['is_available'] !== null) {
                    $product->is_available = (bool) $actions['is_available'];
                    $dirty = true;
                }

                // 4. Feature & Channel Attributes
                if (array_key_exists('is_featured', $actions) && $actions['is_featured'] !== null) {
                    $product->is_featured = (bool) $actions['is_featured'];
                    $dirty = true;
                }

                if (array_key_exists('available_for_dine_in', $actions) && $actions['available_for_dine_in'] !== null) {
                    $product->available_for_dine_in = (bool) $actions['available_for_dine_in'];
                    $dirty = true;
                }

                if (array_key_exists('available_for_takeaway', $actions) && $actions['available_for_takeaway'] !== null) {
                    $product->available_for_takeaway = (bool) $actions['available_for_takeaway'];
                    $dirty = true;
                }

                if (array_key_exists('available_for_delivery', $actions) && $actions['available_for_delivery'] !== null) {
                    $product->available_for_delivery = (bool) $actions['available_for_delivery'];
                    $dirty = true;
                }

                if (array_key_exists('ai_enabled', $actions) && $actions['ai_enabled'] !== null) {
                    $product->ai_enabled = (bool) $actions['ai_enabled'];
                    $dirty = true;
                }

                if ($dirty) {
                    $product->save();
                    $updatedIds[] = (int) $product->id;
                }
            }
        });

        TenantCache::invalidateMenu($vendor->id);

        return [
            'updated_count' => count($updatedIds),
            'product_ids' => $updatedIds,
        ];
    }

    /**
     * Bulk delete multiple products safely within a transaction.
     *
     * @param  array<int>  $productIds
     */
    public function bulkDeleteProducts(Vendor $vendor, array $productIds): int
    {
        if (empty($productIds)) {
            return 0;
        }

        $cleanIds = array_slice(array_unique(array_map('intval', $productIds)), 0, 250);

        $products = Product::where('vendor_id', $vendor->id)
            ->whereIn('id', $cleanIds)
            ->get();

        if ($products->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($products) {
            foreach ($products as $product) {
                $product->deleteImageFile();
                $product->delete();
            }
        });

        TenantCache::invalidateMenu($vendor->id);

        return $products->count();
    }

    /**
     * Inline update a single low-risk field on a product.
     */
    public function inlineUpdateProduct(Product $product, string $field, mixed $value): Product
    {
        $allowedFields = ['name', 'price', 'is_available', 'sort_order'];
        if (! in_array($field, $allowedFields, true)) {
            throw ValidationException::withMessages([
                'field' => __('Field :field is not eligible for inline updates.', ['field' => $field]),
            ]);
        }

        switch ($field) {
            case 'price':
                $price = (float) $value;
                if ($price < 0) {
                    throw ValidationException::withMessages([
                        'value' => __('Price cannot be negative.'),
                    ]);
                }
                $product->price = round($price, 2);
                $product->save();

                // Keep default variation in sync
                ProductVariation::where('product_id', $product->id)
                    ->where('is_default', true)
                    ->update(['price' => $product->price]);
                break;

            case 'name':
                $name = trim((string) $value);
                if (empty($name) || mb_strlen($name) > 255) {
                    throw ValidationException::withMessages([
                        'value' => __('Product name is required and cannot exceed 255 characters.'),
                    ]);
                }
                $product->name = $name;
                $translations = is_array($product->name_translations) ? $product->name_translations : [];
                $translations['en'] = $name;
                $product->name_translations = $translations;
                $product->save();
                break;

            case 'is_available':
                $product->is_available = (bool) $value;
                $product->save();
                break;

            case 'sort_order':
                $product->sort_order = max(0, (int) $value);
                $product->save();
                break;
        }

        TenantCache::invalidateMenu($product->vendor_id);

        return $product;
    }

    /**
     * Reorder categories for a vendor.
     *
     * @param  array<array{id: int, sort_order: int}>  $orderList
     */
    public function reorderCategories(Vendor $vendor, array $orderList): void
    {
        if (empty($orderList)) {
            return;
        }

        DB::transaction(function () use ($vendor, $orderList) {
            foreach ($orderList as $item) {
                if (! isset($item['id'], $item['sort_order'])) {
                    continue;
                }

                Category::where('id', (int) $item['id'])
                    ->where('vendor_id', $vendor->id)
                    ->update(['sort_order' => (int) $item['sort_order']]);
            }
        });

        TenantCache::invalidateMenu($vendor->id);
    }

    /**
     * Safely delete a category with product reassignment or explicit force deletion.
     *
     * @return array{reassigned_count: int, category_name: string}
     */
    public function deleteCategorySafely(Category $category, ?int $reassignToCategoryId = null, bool $forceDelete = false): array
    {
        $vendorId = $category->vendor_id;
        $categoryName = $category->name;
        $productCount = $category->products()->count();
        $reassignedCount = 0;

        if ($productCount > 0) {
            if ($reassignToCategoryId !== null) {
                if ($reassignToCategoryId === (int) $category->id) {
                    throw ValidationException::withMessages([
                        'reassign_to_category_id' => __('Cannot reassign products to the same category being deleted.'),
                    ]);
                }

                $targetCategory = Category::where('vendor_id', $vendorId)->find($reassignToCategoryId);
                if (! $targetCategory) {
                    throw ValidationException::withMessages([
                        'reassign_to_category_id' => __('Target category does not belong to this restaurant.'),
                    ]);
                }

                DB::transaction(function () use ($category, $targetCategory, &$reassignedCount) {
                    $reassignedCount = Product::where('category_id', $category->id)
                        ->where('vendor_id', $category->vendor_id)
                        ->update(['category_id' => $targetCategory->id]);

                    $category->delete();
                });
            } elseif (! $forceDelete) {
                throw ValidationException::withMessages([
                    'category' => __('This category contains :count products. Reassign them to another category or confirm permanent deletion.', ['count' => $productCount]),
                ]);
            } else {
                DB::transaction(function () use ($category) {
                    $category->products()->delete();
                    $category->delete();
                });
            }
        } else {
            $category->delete();
        }

        TenantCache::invalidateMenu($vendorId);

        return [
            'reassigned_count' => $reassignedCount,
            'category_name' => $categoryName,
        ];
    }

    /**
     * Duplicate an existing category with all its dishes.
     */
    public function duplicateCategory(Category $category): Category
    {
        $vendor = $category->vendor;

        return DB::transaction(function () use ($category, $vendor) {
            $maxOrder = (int) (Category::where('vendor_id', $vendor->id)->max('sort_order') ?? 0);
            $copyName = $category->name.' ('.__('Copy').')';

            $newCat = Category::create([
                'vendor_id' => $vendor->id,
                'location_id' => $category->location_id,
                'name' => $copyName,
                'name_translations' => $category->name_translations,
                'description' => $category->description,
                'image' => $category->image,
                'sort_order' => $maxOrder + 1,
                'is_active' => $category->is_active,
            ]);

            $products = Product::where('category_id', $category->id)
                ->where('vendor_id', $vendor->id)
                ->with(['variations', 'allergens'])
                ->get();

            foreach ($products as $prod) {
                $newProd = $prod->replicate(['id', 'created_at', 'updated_at', 'deleted_at']);
                $newProd->category_id = $newCat->id;
                $newProd->name = $prod->name;
                $newProd->save();

                // Replicate variations
                foreach ($prod->variations as $var) {
                    $newVar = $var->replicate(['id', 'product_id', 'created_at', 'updated_at']);
                    $newVar->product_id = $newProd->id;
                    $newVar->save();
                }

                // Sync allergens
                if ($prod->allergens->isNotEmpty()) {
                    $newProd->allergens()->sync($prod->allergens->pluck('id')->all());
                }
            }

            TenantCache::invalidateMenu($vendor->id);

            return $newCat;
        });
    }
}
