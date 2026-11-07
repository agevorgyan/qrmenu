<?php

namespace App\Domains\Wholesale\Services;

use App\Domains\Wholesale\Models\WholesaleAtgaaClassification;
use App\Domains\Wholesale\Models\WholesaleCategory;
use App\Domains\Wholesale\Models\WholesaleProduct;
use App\Domains\Wholesale\Models\WholesaleProductPackaging;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WholesaleCatalogService
{
    /**
     * List wholesale products with filtering and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listProducts(Vendor $vendor, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = WholesaleProduct::where('vendor_id', $vendor->id)
            ->with(['category', 'unit', 'packagingUnit', 'physicalStockIngredient', 'atgaaClassification'])
            ->orderBy('id', 'desc');

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('classifier_code', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new wholesale product.
     *
     * @param  array<string, mixed>  $data
     */
    public function createProduct(Vendor $vendor, array $data): WholesaleProduct
    {
        $sku = strtoupper(trim($data['sku']));

        // Verify SKU uniqueness for this tenant
        if (WholesaleProduct::where('vendor_id', $vendor->id)->where('sku', $sku)->exists()) {
            throw new InvalidArgumentException(__('A wholesale product with SKU :sku already exists in your catalog.', ['sku' => $sku]));
        }

        // Verify classifier code exists in official directory
        $classifier = trim($data['classifier_code']);
        if (! WholesaleAtgaaClassification::where('code', $classifier)->exists()) {
            throw new InvalidArgumentException(__('Invalid official ATGAA / FEACN classifier code (:code). You must select a valid code.', ['code' => $classifier]));
        }

        $basePrice = (float) $data['base_wholesale_price'];
        $minPrice = isset($data['min_wholesale_price']) ? (float) $data['min_wholesale_price'] : $basePrice;

        if ($minPrice > $basePrice) {
            throw new InvalidArgumentException(__('Minimum wholesale price cannot be greater than base wholesale price.'));
        }

        return DB::transaction(function () use ($vendor, $data, $sku, $classifier, $basePrice, $minPrice) {
            $product = WholesaleProduct::create([
                'vendor_id' => $vendor->id,
                'category_id' => $data['category_id'] ?? null,
                'name' => trim($data['name']),
                'sku' => $sku,
                'barcode' => ! empty($data['barcode']) ? trim($data['barcode']) : null,
                'gtin' => ! empty($data['gtin']) ? trim($data['gtin']) : null,
                'classifier_code' => $classifier,
                'unit_id' => $data['unit_id'],
                'packaging_unit_id' => $data['packaging_unit_id'] ?? null,
                'units_per_package' => $data['units_per_package'] ?? 1.0,
                'min_order_quantity' => $data['min_order_quantity'] ?? 1.0,
                'order_quantity_multiple' => $data['order_quantity_multiple'] ?? 1.0,
                'cost_price' => $data['cost_price'] ?? 0.0,
                'base_wholesale_price' => $basePrice,
                'min_wholesale_price' => $minPrice,
                'is_vat_applicable' => $data['is_vat_applicable'] ?? true,
                'vat_rate' => $data['vat_rate'] ?? 20.00,
                'physical_stock_ingredient_id' => $data['physical_stock_ingredient_id'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // Add packaging rule if provided
            if (! empty($data['packaging_type']) && ! empty($data['packaging_units_count'])) {
                WholesaleProductPackaging::create([
                    'product_id' => $product->id,
                    'packaging_type' => trim($data['packaging_type']),
                    'packaging_barcode' => ! empty($data['packaging_barcode']) ? trim($data['packaging_barcode']) : null,
                    'units_count' => (float) $data['packaging_units_count'],
                    'gross_weight_kg' => $data['gross_weight_kg'] ?? null,
                    'dimensions' => $data['dimensions'] ?? null,
                ]);
            }

            return $product->fresh(['category', 'unit', 'packagingUnit', 'physicalStockIngredient', 'atgaaClassification']);
        });
    }

    /**
     * Update an existing wholesale product.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProduct(WholesaleProduct $product, array $data): WholesaleProduct
    {
        if (isset($data['sku'])) {
            $sku = strtoupper(trim($data['sku']));
            if ($sku !== $product->sku && WholesaleProduct::where('vendor_id', $product->vendor_id)->where('sku', $sku)->exists()) {
                throw new InvalidArgumentException(__('A wholesale product with SKU :sku already exists.', ['sku' => $sku]));
            }
            $data['sku'] = $sku;
        }

        if (isset($data['classifier_code'])) {
            $classifier = trim($data['classifier_code']);
            if (! WholesaleAtgaaClassification::where('code', $classifier)->exists()) {
                throw new InvalidArgumentException(__('Invalid official ATGAA / FEACN classifier code (:code).', ['code' => $classifier]));
            }
            $data['classifier_code'] = $classifier;
        }

        if (isset($data['base_wholesale_price']) && isset($data['min_wholesale_price'])) {
            if ((float) $data['min_wholesale_price'] > (float) $data['base_wholesale_price']) {
                throw new InvalidArgumentException(__('Minimum wholesale price cannot be greater than base wholesale price.'));
            }
        }

        $product->update($data);

        return $product->fresh(['category', 'unit', 'packagingUnit', 'physicalStockIngredient', 'atgaaClassification']);
    }

    /**
     * Get or search official ATGAA classifications.
     *
     * @return Collection<int, WholesaleAtgaaClassification>
     */
    public function getAtgaaClassifications(?string $search = null): Collection
    {
        $query = WholesaleAtgaaClassification::query()->orderBy('is_common', 'desc')->orderBy('code', 'asc');

        if (! empty($search)) {
            $term = trim($search);
            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', "{$term}%")
                    ->orWhere('name_hy', 'like', "%{$term}%")
                    ->orWhere('name_en', 'like', "%{$term}%");
            });
        }

        return $query->get();
    }

    /**
     * Get categories for a tenant.
     *
     * @return Collection<int, WholesaleCategory>
     */
    public function listCategories(Vendor $vendor): Collection
    {
        return WholesaleCategory::where('vendor_id', $vendor->id)
            ->withCount('products')
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Create a category.
     */
    public function createCategory(Vendor $vendor, array $data): WholesaleCategory
    {
        return WholesaleCategory::create([
            'vendor_id' => $vendor->id,
            'name' => trim($data['name']),
            'parent_id' => $data['parent_id'] ?? null,
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Add packaging definition to product.
     */
    public function addPackaging(WholesaleProduct $product, array $data): WholesaleProductPackaging
    {
        return WholesaleProductPackaging::create([
            'product_id' => $product->id,
            'packaging_type' => trim($data['packaging_type']),
            'packaging_barcode' => ! empty($data['packaging_barcode']) ? trim($data['packaging_barcode']) : null,
            'units_count' => (float) $data['units_count'],
            'gross_weight_kg' => $data['gross_weight_kg'] ?? null,
            'dimensions' => $data['dimensions'] ?? null,
        ]);
    }
}
