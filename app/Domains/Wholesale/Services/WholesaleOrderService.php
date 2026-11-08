<?php

namespace App\Domains\Wholesale\Services;

use App\Domains\Wholesale\Models\WholesaleCustomer;
use App\Domains\Wholesale\Models\WholesaleOrder;
use App\Domains\Wholesale\Models\WholesaleOrderItem;
use App\Domains\Wholesale\Models\WholesaleProduct;
use App\Domains\Wholesale\Models\WholesaleSale;
use App\Domains\Wholesale\Models\WholesaleSaleItem;
use App\Domains\Wholesale\Models\WholesaleSeller;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WholesaleOrderService
{
    public function __construct(
        protected WholesalePricingEngine $pricingEngine
    ) {}

    /**
     * List orders for a tenant.
     *
     * @param  array{status?: string, customer_id?: int, search?: string}  $filters
     */
    public function listOrders(Vendor $vendor, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = WholesaleOrder::where('vendor_id', $vendor->id)
            ->with(['seller', 'customer', 'deliveryAddress']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'ilike', "%{$search}%")->orWhere('tin', 'like', "%{$search}%"));
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * List sales invoices for a tenant.
     *
     * @param  array{status?: string, customer_id?: int, search?: string}  $filters
     */
    public function listSales(Vendor $vendor, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = WholesaleSale::where('vendor_id', $vendor->id)
            ->with(['seller', 'customer', 'order']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'ilike', "%{$search}%")->orWhere('tin', 'like', "%{$search}%"));
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Create a new wholesale order draft.
     *
     * @param  array{
     *     seller_id: int,
     *     customer_id: int,
     *     order_type?: string,
     *     order_date?: string,
     *     expected_delivery_date?: string,
     *     delivery_address_id?: int|null,
     *     delivery_fee?: float,
     *     notes?: string|null,
     *     items: array<int, array{product_id: int, quantity: float}>
     * }  $data
     */
    public function createOrder(Vendor $vendor, array $data, ?User $user = null): WholesaleOrder
    {
        return DB::transaction(function () use ($vendor, $data, $user) {
            $seller = WholesaleSeller::where('vendor_id', $vendor->id)->findOrFail($data['seller_id']);
            $customer = WholesaleCustomer::where('vendor_id', $vendor->id)->findOrFail($data['customer_id']);

            $orderNumber = $this->generateOrderNumber($vendor);

            $order = WholesaleOrder::create([
                'vendor_id' => $vendor->id,
                'seller_id' => $seller->id,
                'customer_id' => $customer->id,
                'order_number' => $orderNumber,
                'order_type' => $data['order_type'] ?? 'STANDARD',
                'status' => 'DRAFT',
                'payment_status' => 'PENDING',
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'delivery_address_id' => $data['delivery_address_id'] ?? $customer->defaultAddress?->id,
                'delivery_fee' => (float) ($data['delivery_fee'] ?? 0),
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $user?->id,
            ]);

            foreach ($data['items'] as $itemData) {
                $product = WholesaleProduct::where('vendor_id', $vendor->id)->findOrFail($itemData['product_id']);
                $qty = (float) $itemData['quantity'];

                $pricing = $this->pricingEngine->resolvePrice($product, $customer, $qty, false, $seller);

                WholesaleOrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'product_name' => $product->name,
                    'classifier_code' => $product->classifier_code,
                    'unit_code' => $product->unit?->code ?? 'unit',
                    'quantity_ordered' => $qty,
                    'quantity_delivered' => 0.0000,
                    'base_price' => $pricing['base_price'],
                    'unit_price' => $pricing['resolved_unit_price'],
                    'discount_percent' => $pricing['base_price'] > 0 ? round(($pricing['discount_unit_amount'] / $pricing['base_price']) * 100, 2) : 0,
                    'discount_amount' => $pricing['discount_total'],
                    'subtotal' => $pricing['subtotal'],
                    'is_vat_applicable' => $pricing['is_vat_applicable'],
                    'vat_rate' => $pricing['vat_rate'],
                    'vat_amount' => $pricing['vat_amount'],
                    'total_amount' => $pricing['total_amount'],
                    'applied_rule' => $pricing['applied_rule'],
                ]);
            }

            $order->recalculateTotals();

            return $order->fresh(['items', 'seller', 'customer']);
        });
    }

    /**
     * Confirm an order. Enforces credit checks if payment terms are credit.
     */
    public function confirmOrder(WholesaleOrder $order, bool $allowCreditOverride = false): WholesaleOrder
    {
        if ($order->status !== 'DRAFT') {
            throw ValidationException::withMessages([
                'order' => __('Only draft orders can be confirmed.'),
            ]);
        }

        $customer = $order->customer;

        // Credit check
        if (! $customer->canAffordCredit((float) $order->total_amount)) {
            if (! $allowCreditOverride) {
                throw ValidationException::withMessages([
                    'credit_limit' => __('Customer ":name" has exceeded their credit limit. Outstanding: :bal AMD, Limit: :lim AMD. Order Total: :tot AMD.', [
                        'name' => $customer->name,
                        'bal' => number_format($customer->current_balance, 2),
                        'lim' => number_format($customer->credit_limit, 2),
                        'tot' => number_format($order->total_amount, 2),
                    ]),
                ]);
            }
        }

        $order->update([
            'status' => 'CONFIRMED',
            'confirmed_at' => now(),
        ]);

        return $order;
    }

    /**
     * Create a commercial sale directly or from an existing confirmed order.
     *
     * @param  array{
     *     seller_id?: int,
     *     customer_id?: int,
     *     payment_method?: string,
     *     paid_amount?: float,
     *     notes?: string|null,
     *     items?: array<int, array{product_id: int, quantity: float}>
     * }  $data
     */
    public function createSale(Vendor $vendor, array $data, ?WholesaleOrder $order = null, ?User $user = null): WholesaleSale
    {
        return DB::transaction(function () use ($vendor, $data, $order, $user) {
            $sellerId = $order ? $order->seller_id : $data['seller_id'];
            $customerId = $order ? $order->customer_id : $data['customer_id'];

            $seller = WholesaleSeller::where('vendor_id', $vendor->id)->findOrFail($sellerId);
            $customer = WholesaleCustomer::where('vendor_id', $vendor->id)->findOrFail($customerId);

            $paymentMethod = $data['payment_method'] ?? 'BANK_TRANSFER';
            $invoiceNumber = $this->generateInvoiceNumber($vendor);

            $sale = WholesaleSale::create([
                'vendor_id' => $vendor->id,
                'order_id' => $order?->id,
                'seller_id' => $seller->id,
                'customer_id' => $customer->id,
                'invoice_number' => $invoiceNumber,
                'sale_date' => now()->toDateString(),
                'status' => 'ISSUED',
                'payment_method' => $paymentMethod,
                'subtotal' => 0.0000,
                'discount_total' => 0.0000,
                'tax_total' => 0.0000,
                'delivery_fee' => $order ? (float) $order->delivery_fee : (float) ($data['delivery_fee'] ?? 0),
                'total_amount' => 0.0000,
                'paid_amount' => (float) ($data['paid_amount'] ?? 0),
                'due_date' => $customer->payment_term_days > 0 ? now()->addDays($customer->payment_term_days)->toDateString() : now()->toDateString(),
                'notes' => $data['notes'] ?? ($order ? __('Generated from Wholesale Order :num', ['num' => $order->order_number]) : null),
                'created_by_user_id' => $user?->id,
            ]);

            // Populate items
            if ($order) {
                foreach ($order->items as $orderItem) {
                    WholesaleSaleItem::create([
                        'sale_id' => $sale->id,
                        'order_item_id' => $orderItem->id,
                        'product_id' => $orderItem->product_id,
                        'sku' => $orderItem->sku,
                        'product_name' => $orderItem->product_name,
                        'classifier_code' => $orderItem->classifier_code,
                        'unit_code' => $orderItem->unit_code,
                        'quantity' => $orderItem->quantity_ordered,
                        'unit_price' => $orderItem->unit_price,
                        'discount_amount' => $orderItem->discount_amount,
                        'subtotal' => $orderItem->subtotal,
                        'is_vat_applicable' => $orderItem->is_vat_applicable,
                        'vat_rate' => $orderItem->vat_rate,
                        'vat_amount' => $orderItem->vat_amount,
                        'total_amount' => $orderItem->total_amount,
                    ]);
                }
            } else {
                foreach ($data['items'] as $itemData) {
                    $product = WholesaleProduct::where('vendor_id', $vendor->id)->findOrFail($itemData['product_id']);
                    $qty = (float) $itemData['quantity'];
                    $pricing = $this->pricingEngine->resolvePrice($product, $customer, $qty, false, $seller);

                    WholesaleSaleItem::create([
                        'sale_id' => $sale->id,
                        'order_item_id' => null,
                        'product_id' => $product->id,
                        'sku' => $product->sku,
                        'product_name' => $product->name,
                        'classifier_code' => $product->classifier_code,
                        'unit_code' => $product->unit?->code ?? 'unit',
                        'quantity' => $qty,
                        'unit_price' => $pricing['resolved_unit_price'],
                        'discount_amount' => $pricing['discount_total'],
                        'subtotal' => $pricing['subtotal'],
                        'is_vat_applicable' => $pricing['is_vat_applicable'],
                        'vat_rate' => $pricing['vat_rate'],
                        'vat_amount' => $pricing['vat_amount'],
                        'total_amount' => $pricing['total_amount'],
                    ]);
                }
            }

            $sale->recalculateTotals();

            // Update customer AR balance if credit sale or unpaid balance
            $unpaidAmount = max(0, (float) $sale->total_amount - (float) $sale->paid_amount);
            if ($unpaidAmount > 0) {
                $customer->increment('current_balance', $unpaidAmount);
            }

            if ((float) $sale->paid_amount >= (float) $sale->total_amount) {
                $sale->update(['status' => 'PAID']);
            }

            return $sale->fresh(['items', 'seller', 'customer']);
        });
    }

    /**
     * Generate sequential order number: WO-YYYYMMDD-####
     */
    public function generateOrderNumber(Vendor $vendor): string
    {
        $datePrefix = 'WO-'.now()->format('Ymd');
        $countToday = WholesaleOrder::where('vendor_id', $vendor->id)
            ->where('order_number', 'like', "{$datePrefix}-%")
            ->count();

        return sprintf('%s-%04d', $datePrefix, $countToday + 1);
    }

    /**
     * Generate sequential invoice number: WS-YYYYMMDD-####
     */
    public function generateInvoiceNumber(Vendor $vendor): string
    {
        $datePrefix = 'WS-'.now()->format('Ymd');
        $countToday = WholesaleSale::where('vendor_id', $vendor->id)
            ->where('invoice_number', 'like', "{$datePrefix}-%")
            ->count();

        return sprintf('%s-%04d', $datePrefix, $countToday + 1);
    }
}
