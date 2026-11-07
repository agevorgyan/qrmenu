<?php

namespace App\Domains\Wholesale\Services;

use App\Domains\Wholesale\Models\WholesaleCustomer;
use App\Domains\Wholesale\Models\WholesaleCustomerAddress;
use App\Domains\Wholesale\Models\WholesaleCustomerContact;
use App\Domains\Wholesale\Models\WholesaleCustomerPricing;
use App\Domains\Wholesale\Models\WholesalePriceList;
use App\Domains\Wholesale\Models\WholesalePriceListItem;
use App\Domains\Wholesale\Models\WholesaleProduct;
use App\Domains\Wholesale\Models\WholesaleQuantityDiscount;
use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WholesaleCustomerService
{
    /**
     * List customers for a tenant with optional filtering.
     *
     * @param  array{search?: string, tax_regime?: string, is_active?: bool}  $filters
     */
    public function listCustomers(Vendor $vendor, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = WholesaleCustomer::where('vendor_id', $vendor->id)
            ->with(['defaultAddress', 'primaryContact']);

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('legal_name', 'ilike', "%{$search}%")
                    ->orWhere('tin', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if (! empty($filters['tax_regime'])) {
            $query->where('tax_regime', $filters['tax_regime']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Create a new B2B customer.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCustomer(Vendor $vendor, array $data): WholesaleCustomer
    {
        return DB::transaction(function () use ($vendor, $data) {
            $customer = WholesaleCustomer::create([
                'vendor_id' => $vendor->id,
                'name' => $data['name'],
                'legal_name' => $data['legal_name'] ?? null,
                'tin' => ! empty($data['tin']) ? trim($data['tin']) : null,
                'tax_regime' => $data['tax_regime'] ?? 'vat_payer',
                'credit_limit' => $data['credit_limit'] ?? 0.0000,
                'payment_term_days' => $data['payment_term_days'] ?? 0,
                'is_credit_allowed' => ! empty($data['is_credit_allowed']),
                'current_balance' => 0.0000,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // Create initial address if supplied
            if (! empty($data['address_line1'])) {
                WholesaleCustomerAddress::create([
                    'customer_id' => $customer->id,
                    'name' => $data['address_name'] ?? __('Main Office / Warehouse'),
                    'address_line1' => $data['address_line1'],
                    'address_line2' => $data['address_line2'] ?? null,
                    'city' => $data['city'] ?? 'Yerevan',
                    'postal_code' => $data['postal_code'] ?? null,
                    'contact_name' => $data['contact_person'] ?? null,
                    'contact_phone' => $data['contact_phone'] ?? $data['phone'] ?? null,
                    'is_default' => true,
                ]);
            }

            // Create initial contact person if supplied
            if (! empty($data['contact_person'])) {
                WholesaleCustomerContact::create([
                    'customer_id' => $customer->id,
                    'name' => $data['contact_person'],
                    'phone' => $data['contact_phone'] ?? $data['phone'] ?? null,
                    'email' => $data['contact_email'] ?? $data['email'] ?? null,
                    'role' => $data['contact_role'] ?? __('Procurement Manager'),
                    'is_primary' => true,
                ]);
            }

            return $customer;
        });
    }

    /**
     * Update an existing B2B customer.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCustomer(WholesaleCustomer $customer, array $data): WholesaleCustomer
    {
        $customer->update([
            'name' => $data['name'] ?? $customer->name,
            'legal_name' => $data['legal_name'] ?? $customer->legal_name,
            'tin' => array_key_exists('tin', $data) ? (! empty($data['tin']) ? trim($data['tin']) : null) : $customer->tin,
            'tax_regime' => $data['tax_regime'] ?? $customer->tax_regime,
            'credit_limit' => $data['credit_limit'] ?? $customer->credit_limit,
            'payment_term_days' => $data['payment_term_days'] ?? $customer->payment_term_days,
            'is_credit_allowed' => array_key_exists('is_credit_allowed', $data) ? (bool) $data['is_credit_allowed'] : $customer->is_credit_allowed,
            'phone' => $data['phone'] ?? $customer->phone,
            'email' => $data['email'] ?? $customer->email,
            'notes' => $data['notes'] ?? $customer->notes,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $customer->is_active,
        ]);

        return $customer;
    }

    /**
     * Add a delivery address for customer.
     *
     * @param  array<string, mixed>  $data
     */
    public function addAddress(WholesaleCustomer $customer, array $data): WholesaleCustomerAddress
    {
        if (! empty($data['is_default'])) {
            WholesaleCustomerAddress::where('customer_id', $customer->id)->update(['is_default' => false]);
        }

        return WholesaleCustomerAddress::create([
            'customer_id' => $customer->id,
            'name' => $data['name'],
            'address_line1' => $data['address_line1'],
            'address_line2' => $data['address_line2'] ?? null,
            'city' => $data['city'] ?? 'Yerevan',
            'postal_code' => $data['postal_code'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'is_default' => ! empty($data['is_default']),
        ]);
    }

    /**
     * Create a price list.
     *
     * @param  array<string, mixed>  $data
     */
    public function createPriceList(Vendor $vendor, array $data): WholesalePriceList
    {
        return WholesalePriceList::create([
            'vendor_id' => $vendor->id,
            'name' => $data['name'],
            'code' => strtoupper(trim($data['code'])),
            'currency' => $data['currency'] ?? 'AMD',
            'effective_from' => $data['effective_from'] ?? null,
            'effective_to' => $data['effective_to'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'description' => $data['description'] ?? null,
        ]);
    }

    /**
     * Set a price list item.
     */
    public function setPriceListItem(WholesalePriceList $priceList, WholesaleProduct $product, float $price, float $minQuantity = 1.0): WholesalePriceListItem
    {
        return WholesalePriceListItem::updateOrCreate(
            [
                'price_list_id' => $priceList->id,
                'product_id' => $product->id,
                'min_quantity' => $minQuantity,
            ],
            [
                'price' => $price,
            ]
        );
    }

    /**
     * Set customer contract price or assign price list.
     *
     * @param  array<string, mixed>  $data
     */
    public function setCustomerPricing(WholesaleCustomer $customer, array $data): WholesaleCustomerPricing
    {
        return WholesaleCustomerPricing::create([
            'vendor_id' => $customer->vendor_id,
            'customer_id' => $customer->id,
            'price_list_id' => $data['price_list_id'] ?? null,
            'product_id' => $data['product_id'] ?? null,
            'contract_price' => $data['contract_price'] ?? null,
            'discount_percent' => $data['discount_percent'] ?? null,
            'valid_from' => $data['valid_from'] ?? null,
            'valid_to' => $data['valid_to'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Add volume tier discount for a product.
     *
     * @param  array<string, mixed>  $data
     */
    public function addQuantityDiscount(WholesaleProduct $product, array $data): WholesaleQuantityDiscount
    {
        return WholesaleQuantityDiscount::create([
            'vendor_id' => $product->vendor_id,
            'product_id' => $product->id,
            'price_list_id' => $data['price_list_id'] ?? null,
            'min_quantity' => $data['min_quantity'],
            'discount_percent' => $data['discount_percent'] ?? null,
            'fixed_price' => $data['fixed_price'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
