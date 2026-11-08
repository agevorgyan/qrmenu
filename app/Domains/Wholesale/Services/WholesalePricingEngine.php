<?php

namespace App\Domains\Wholesale\Services;

use App\Domains\Wholesale\Enums\SellerTaxRegime;
use App\Domains\Wholesale\Models\WholesaleCustomer;
use App\Domains\Wholesale\Models\WholesaleCustomerPricing;
use App\Domains\Wholesale\Models\WholesalePriceListItem;
use App\Domains\Wholesale\Models\WholesaleProduct;
use App\Domains\Wholesale\Models\WholesaleQuantityDiscount;
use App\Domains\Wholesale\Models\WholesaleSeller;

class WholesalePricingEngine
{
    /**
     * Resolve the effective wholesale unit price, discounts, and taxes for a given product and customer.
     *
     * @return array{
     *     product_id: int,
     *     sku: string,
     *     quantity: float,
     *     base_price: float,
     *     min_price_floor: float,
     *     resolved_unit_price: float,
     *     gross_unit_price: float,
     *     unit_vat_amount: float,
     *     discount_unit_amount: float,
     *     discount_total: float,
     *     subtotal: float,
     *     applied_rule: string,
     *     applied_rule_description: string,
     *     floor_enforced: bool,
     *     is_vat_applicable: bool,
     *     vat_rate: float,
     *     vat_amount: float,
     *     total_amount: float
     * }
     */
    public function resolvePrice(
        WholesaleProduct $product,
        ?WholesaleCustomer $customer = null,
        float $quantity = 1.0,
        bool $allowOverride = false,
        ?WholesaleSeller $seller = null
    ): array {
        $basePrice = (float) $product->base_wholesale_price;
        $minFloor = (float) ($product->min_wholesale_price ?? 0);
        $resolved = $basePrice;
        $appliedRule = 'BASE_PRICE';
        $ruleDescription = __('Base wholesale catalog price');
        $floorEnforced = false;

        // 1. Contract Price on Customer + Product
        if ($customer) {
            $contractPricing = WholesaleCustomerPricing::where('customer_id', $customer->id)
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->get()
                ->first(fn ($p) => $p->isCurrentlyValid());

            if ($contractPricing) {
                if ($contractPricing->contract_price !== null) {
                    $resolved = (float) $contractPricing->contract_price;
                    $appliedRule = 'CONTRACT_PRICE';
                    $ruleDescription = __('Customer-specific agreed contract price');
                } elseif ($contractPricing->discount_percent !== null) {
                    $resolved = $basePrice * (1 - ((float) $contractPricing->discount_percent / 100));
                    $appliedRule = 'CONTRACT_DISCOUNT';
                    $ruleDescription = __('Customer-specific contract discount (:pct%)', ['pct' => (float) $contractPricing->discount_percent]);
                }
            }
        }

        // 2. Customer Assigned Price List
        if ($appliedRule === 'BASE_PRICE' && $customer) {
            $customerPriceListAssignment = WholesaleCustomerPricing::where('customer_id', $customer->id)
                ->whereNull('product_id')
                ->whereNotNull('price_list_id')
                ->where('is_active', true)
                ->get()
                ->first(fn ($p) => $p->isCurrentlyValid());

            if ($customerPriceListAssignment && $customerPriceListAssignment->priceList?->isCurrentlyEffective()) {
                $priceListItem = WholesalePriceListItem::where('price_list_id', $customerPriceListAssignment->price_list_id)
                    ->where('product_id', $product->id)
                    ->where('min_quantity', '<=', $quantity)
                    ->orderBy('min_quantity', 'desc')
                    ->first();

                if ($priceListItem) {
                    $resolved = (float) $priceListItem->price;
                    $appliedRule = 'CUSTOMER_PRICE_LIST';
                    $ruleDescription = __('Customer assigned price list (:list)', ['list' => $customerPriceListAssignment->priceList->name]);
                }
            }
        }

        // 3. Volume / Quantity Tier Discounts
        if ($appliedRule === 'BASE_PRICE') {
            $quantityDiscount = WholesaleQuantityDiscount::where('product_id', $product->id)
                ->where('min_quantity', '<=', $quantity)
                ->where('is_active', true)
                ->orderBy('min_quantity', 'desc')
                ->first();

            if ($quantityDiscount) {
                if ($quantityDiscount->fixed_price !== null) {
                    $resolved = (float) $quantityDiscount->fixed_price;
                    $appliedRule = 'VOLUME_TIER_FIXED';
                    $ruleDescription = __('Volume tier break (>= :qty :unit)', ['qty' => (float) $quantityDiscount->min_quantity, 'unit' => $product->unit?->code ?? 'units']);
                } elseif ($quantityDiscount->discount_percent !== null) {
                    $resolved = $basePrice * (1 - ((float) $quantityDiscount->discount_percent / 100));
                    $appliedRule = 'VOLUME_TIER_PERCENT';
                    $ruleDescription = __('Volume tier discount (:pct% for >= :qty :unit)', [
                        'pct' => (float) $quantityDiscount->discount_percent,
                        'qty' => (float) $quantityDiscount->min_quantity,
                        'unit' => $product->unit?->code ?? 'units',
                    ]);
                }
            }
        }

        // 4. Hard Floor Enforcement
        if ($minFloor > 0 && $resolved < $minFloor) {
            if ($allowOverride) {
                $floorEnforced = false;
            } else {
                $resolved = $minFloor;
                $floorEnforced = true;
                $ruleDescription .= ' '.__('(Capped at minimum price floor)');
            }
        }

        // 5. Seller Tax Regime Verification
        $sellerIsVatPayer = $seller !== null
            ? ($seller->tax_regime instanceof SellerTaxRegime ? $seller->tax_regime->isVatPayer() : ($seller->tax_regime === 'vat_payer' || $seller->tax_regime === SellerTaxRegime::VAT_PAYER->value))
            : true;

        $isVatApplicable = $sellerIsVatPayer && (bool) $product->is_vat_applicable;
        $vatRate = $isVatApplicable ? (float) ($product->vat_rate ?? 20.00) : 0.00;

        // 6. Calculations (Armenian Tax Rules: Catalog price is VAT-inclusive)
        $grossUnitPrice = round($resolved, 4);
        $grossBasePrice = round($basePrice, 4);
        $discountPerUnit = max(0, round($grossBasePrice - $grossUnitPrice, 4));
        $discountTotal = round($discountPerUnit * $quantity, 2);
        $totalAmount = round($grossUnitPrice * $quantity, 2);

        if ($isVatApplicable && $vatRate > 0) {
            $vatMultiplier = 1 + ($vatRate / 100);
            $netUnitPrice = round($grossUnitPrice / $vatMultiplier, 5);
            $unitVatAmount = round($grossUnitPrice - $netUnitPrice, 5);
            $subtotal = round($totalAmount / $vatMultiplier, 2);
            $vatAmount = round($totalAmount - $subtotal, 2);
            $resolvedUnitPrice = $netUnitPrice;
        } else {
            $isVatApplicable = false;
            $vatRate = 0.00;
            $unitVatAmount = 0.00;
            $subtotal = $totalAmount;
            $vatAmount = 0.00;
            $resolvedUnitPrice = $grossUnitPrice;
        }

        return [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'quantity' => $quantity,
            'base_price' => $basePrice,
            'min_price_floor' => $minFloor,
            'resolved_unit_price' => $resolvedUnitPrice,
            'gross_unit_price' => $grossUnitPrice,
            'unit_vat_amount' => $unitVatAmount,
            'discount_unit_amount' => $discountPerUnit,
            'discount_total' => $discountTotal,
            'subtotal' => $subtotal,
            'applied_rule' => $appliedRule,
            'applied_rule_description' => $ruleDescription,
            'floor_enforced' => $floorEnforced,
            'is_vat_applicable' => $isVatApplicable,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'total_amount' => $totalAmount,
        ];
    }
}
