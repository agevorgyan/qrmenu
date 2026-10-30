<?php

namespace App\Actions;

use App\DTOs\CreateOrderDTO;
use App\DTOs\OrderItemDTO;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Models\Vendor;
use App\Security\QuotaCatalog;
use App\Services\CrmAutomationService;
use App\Services\CustomerSyncService;
use App\Services\OrderDispatchService;
use App\Services\OrderPricingService;
use App\Services\QuotaService;
use App\Services\TipRatingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateOrderAction
{
    public function __construct(
        protected CustomerSyncService $customerSyncService,
        protected ?CrmAutomationService $crmService = null,
        protected ?OrderPricingService $pricingService = null,
        protected ?OrderDispatchService $dispatchService = null,
        protected ?QuotaService $quotaService = null,
        protected ?TipRatingService $tipRatingService = null,
    ) {
        $this->crmService = $crmService ?? app(CrmAutomationService::class);
        $this->pricingService = $pricingService ?? app(OrderPricingService::class);
        $this->dispatchService = $dispatchService ?? app(OrderDispatchService::class);
        $this->quotaService = $quotaService ?? app(QuotaService::class);
        $this->tipRatingService = $tipRatingService ?? app(TipRatingService::class);
    }

    /**
     * Process order submission within a database transaction.
     *
     * @param  CreateOrderDTO|array  $data  Validated DTO or parameters array
     * @return array{order: Order, whatsapp_url: ?string, is_appended: bool}
     */
    public function execute(Vendor $vendor, CreateOrderDTO|array $data): array
    {
        $dto = $data instanceof CreateOrderDTO ? $data : CreateOrderDTO::fromArray($data);

        // Multi-tenant validation: ensure location belongs to vendor
        $location = Location::where('vendor_id', $vendor->id)->find($dto->locationId);
        if (! $location) {
            throw ValidationException::withMessages([
                'location_id' => 'The selected location does not belong to this vendor.',
            ]);
        }

        // Check if customer is appending to an open, non-closed order
        $existingOrder = null;
        if (! empty($dto->activeOrderNumber)) {
            $existingOrder = Order::where('vendor_id', $vendor->id)
                ->where('location_id', $dto->locationId)
                ->where('order_number', $dto->activeOrderNumber)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->first();
        }

        if ($existingOrder) {
            return $this->appendItemsToOrder($vendor, $existingOrder, $dto, $location);
        }

        return $this->createNewOrder($vendor, $dto, $location);
    }

    /**
     * Append items to an existing open order.
     *
     * @return array{order: Order, whatsapp_url: ?string, is_appended: bool}
     */
    protected function appendItemsToOrder(Vendor $vendor, Order $order, CreateOrderDTO $dto, ?Location $location = null): array
    {
        $this->validateChannelOperatingHours($vendor, $location ?? (int) $dto->locationId, (string) $order->type);

        DB::transaction(function () use ($vendor, $order, $dto) {
            // Update customer link if provided
            if (! empty($dto->customerPhone) || ! empty($dto->customerEmail)) {
                $customer = $this->customerSyncService->syncCustomerFromOrder(
                    vendor: $vendor,
                    name: $dto->customerName,
                    phone: $dto->customerPhone,
                    email: $dto->customerEmail,
                    marketingOptIn: $dto->marketingOptIn,
                    locationId: $dto->locationId,
                    birthdate: $dto->customerBirthdate,
                    address: $dto->deliveryAddress
                );
                if ($customer && ! $order->customer_id) {
                    $order->customer_id = $customer->id;
                }
            }

            $productIds = collect($dto->items)->pluck('productId')->unique()->all();
            $products = Product::where('vendor_id', $vendor->id)
                ->whereIn('id', $productIds)
                ->with(['variations', 'overrides'])
                ->get()
                ->keyBy('id');

            $existingOrderItems = OrderItem::where('order_id', $order->id)->get();

            foreach ($dto->items as $item) {
                $product = $products->get($item->productId);
                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => "Product #{$item->productId} does not belong to this vendor.",
                    ]);
                }

                $this->validateProductAvailability($product, (int) $dto->locationId, (string) $order->type);

                $pricing = $this->resolveItemPricing($product, $item, $dto->locationId);
                $subtotal = $pricing['unit_price'] * $item->quantity;

                // Check in-memory if exact same item and variation already exists in the order
                $existingItem = $existingOrderItems->first(function ($oi) use ($product, $pricing) {
                    return (int) $oi->product_id === (int) $product->id
                        && $oi->variation_name === $pricing['variation_name'];
                });

                if ($existingItem) {
                    $existingItem->quantity += $item->quantity;
                    $existingItem->subtotal = $existingItem->quantity * $existingItem->unit_price;
                    $existingItem->save();
                } else {
                    $orderItem = new OrderItem([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'variation_name' => $pricing['variation_name'],
                        'unit_price' => $pricing['unit_price'],
                        'quantity' => $item->quantity,
                        'subtotal' => $subtotal,
                        'notes' => $item->notes,
                        'variants' => $item->variants,
                        'modifiers' => $item->modifiers,
                        'status' => $item->status ?: 'pending',
                        'kitchen_station' => $item->kitchenStation,
                    ]);
                    $orderItem->setRelation('order', $order);
                    $orderItem->setRelation('product', $product);
                    $orderItem->save();
                    $existingOrderItems->push($orderItem);
                }
            }

            // Append additional notes if provided
            if (! empty($dto->notes)) {
                $appendNote = '[Հավելում] '.$dto->notes;
                $order->notes = ! empty($order->notes) ? $order->notes.' | '.$appendNote : $appendNote;
            }

            // Recalculate totals
            $itemsSubtotal = (float) $existingOrderItems->sum('subtotal');
            $birthdate = $order->customer_birthdate ?? ($order->customer?->birthdate ? ($order->customer->birthdate instanceof \DateTimeInterface ? $order->customer->birthdate->format('Y-m-d') : (string) $order->customer->birthdate) : null);
            $birthdayDiscount = $this->crmService->calculateBirthdayDiscount($itemsSubtotal, $birthdate, $vendor);
            $fees = $this->calculateFees($vendor, $itemsSubtotal, $order->type);

            $tipAmount = (float) ($order->tip_amount ?? 0);
            if ($dto->tipAmount > 0) {
                $tipCalc = $this->tipRatingService->calculateTip(
                    vendor: $vendor,
                    baseAmount: $itemsSubtotal,
                    tipType: $dto->tipType ?? 'custom',
                    tipValue: $dto->tipValue ?? $dto->tipAmount,
                    orderType: $order->type
                );
                $tipAmount = $tipCalc['amount'];
            }

            $finalTotal = max(0, $fees['final_total'] - $birthdayDiscount + $tipAmount);

            $order->update([
                'subtotal' => $itemsSubtotal,
                'birthday_discount_amount' => $birthdayDiscount,
                'service_fee' => $fees['service_fee'],
                'delivery_fee' => $fees['delivery_fee'],
                'tip_amount' => $tipAmount,
                'total_amount' => $finalTotal,
                'notes' => $order->notes,
            ]);

            if ($tipAmount > 0) {
                $this->tipRatingService->recordOrderTip(
                    order: $order,
                    tipAmount: $tipAmount,
                    paymentMethod: $order->payment_method ?? 'cash',
                    tipType: $dto->tipType ?? 'custom',
                    percentage: $dto->tipType === 'percentage' ? $dto->tipValue : null,
                    waiterId: $dto->waiterId ?? $order->waiter_id
                );
            }

            if ($order->customer) {
                $this->customerSyncService->recalculateStats($order->customer);
            }
        });

        $order->refresh();
        $order->load(['items.product', 'location', 'customer']);

        $this->dispatchService->dispatchNotifications($order, true);
        $whatsappUrl = $this->buildWhatsAppUrl($order, $vendor, $dto->locationId, true);

        return [
            'order' => $order,
            'whatsapp_url' => $whatsappUrl,
            'is_appended' => true,
        ];
    }

    /**
     * Create a brand new order.
     *
     * @return array{order: Order, whatsapp_url: ?string, is_appended: bool}
     */
    protected function createNewOrder(Vendor $vendor, CreateOrderDTO $dto, ?Location $location = null): array
    {
        $this->validateChannelOperatingHours($vendor, $location ?? (int) $dto->locationId, (string) $dto->type);

        $order = null;

        DB::transaction(function () use ($vendor, $dto, &$order) {
            // Dine-in orders require a table number (scanned from table QR)
            if ($dto->source !== 'pos' && $dto->type === 'dine_in' && empty($dto->tableNumber)) {
                throw ValidationException::withMessages([
                    'table_number' => 'Ռեստորանում պատվիրելու համար անհրաժեշտ է սկանավորել սեղանի QR կոդը:',
                ]);
            }

            // Synchronize or create customer profile
            $customer = $this->customerSyncService->syncCustomerFromOrder(
                vendor: $vendor,
                name: $dto->customerName,
                phone: $dto->customerPhone,
                email: $dto->customerEmail,
                marketingOptIn: $dto->marketingOptIn,
                locationId: $dto->locationId,
                birthdate: $dto->customerBirthdate,
                address: $dto->deliveryAddress
            );

            $paymentMethod = $dto->paymentMethod ?: 'cash';
            $paymentStatus = in_array($paymentMethod, ['cash', 'pos_terminal']) ? 'unpaid' : 'pending';
            $orderNumber = $this->generateOrderNumber($vendor->id);

            // Record orders.period usage (throws QuotaExceededException if hard limit reached)
            $this->quotaService->recordUsage(
                vendor: $vendor,
                quotaKey: QuotaCatalog::KEY_ORDERS_PERIOD,
                quantity: 1,
                idempotencyKey: 'ord_'.$orderNumber,
                metadata: ['type' => $dto->type, 'order_number' => $orderNumber]
            );

            // Create base order record
            $order = Order::create([
                'vendor_id' => $vendor->id,
                'location_id' => $dto->locationId,
                'customer_id' => $customer?->id,
                'order_number' => $orderNumber,
                'source' => $dto->source,
                'table_number' => in_array($dto->type, ['delivery', 'takeaway']) ? null : ($dto->tableNumber ?? 'Counter'),
                'restaurant_table_id' => $dto->restaurantTableId,
                'guest_count' => $dto->guestCount,
                'delivery_address' => $dto->deliveryAddress,
                'type' => $dto->type,
                'total_amount' => 0,
                'status' => $dto->status ?: 'pending',
                'waiter_id' => $dto->waiterId,
                'cashier_id' => $dto->cashierId,
                'pos_device_id' => $dto->posDeviceId,
                'customer_name' => $dto->customerName ?? 'Guest',
                'customer_phone' => $dto->customerPhone,
                'customer_email' => $dto->customerEmail,
                'customer_birthdate' => $dto->customerBirthdate,
                'marketing_opt_in' => $dto->marketingOptIn,
                'notes' => $dto->notes,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'birthday_discount_amount' => 0,
                'discount_amount' => $dto->discountAmount,
                'discount_type' => $dto->discountType,
                'discount_reason' => $dto->discountReason,
                'tax_amount' => $dto->taxAmount,
                'tip_amount' => 0,
                'opened_at' => $dto->source === 'pos' ? now() : null,
            ]);

            $totalAmount = 0;

            $productIds = collect($dto->items)->pluck('productId')->unique()->all();
            $products = Product::where('vendor_id', $vendor->id)
                ->whereIn('id', $productIds)
                ->with(['variations', 'overrides'])
                ->get()
                ->keyBy('id');

            foreach ($dto->items as $item) {
                $product = $products->get($item->productId);
                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => "Product #{$item->productId} does not belong to this vendor.",
                    ]);
                }

                $this->validateProductAvailability($product, (int) $dto->locationId, (string) $dto->type);

                $pricing = $this->resolveItemPricing($product, $item, $dto->locationId);
                $subtotal = $pricing['unit_price'] * $item->quantity;
                $totalAmount += $subtotal;

                $orderItem = new OrderItem([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'variation_name' => $pricing['variation_name'],
                    'unit_price' => $pricing['unit_price'],
                    'quantity' => $item->quantity,
                    'subtotal' => $subtotal,
                    'notes' => $item->notes,
                    'variants' => $item->variants,
                    'modifiers' => $item->modifiers,
                    'status' => $item->status ?: 'pending',
                    'kitchen_station' => $item->kitchenStation,
                ]);
                $orderItem->setRelation('order', $order);
                $orderItem->setRelation('product', $product);
                $orderItem->save();
            }

            $itemsSubtotal = $totalAmount;

            if ($dto->type === 'delivery') {
                if (! $vendor->delivery_enabled) {
                    throw ValidationException::withMessages([
                        'delivery' => 'Առաքման ծառայությունը ներկայումս հասանելի չէ:',
                    ]);
                }

                if ((float) $vendor->delivery_min_amount > 0 && $itemsSubtotal < (float) $vendor->delivery_min_amount) {
                    throw ValidationException::withMessages([
                        'delivery' => 'Նվազագույն պատվերի գումարը առաքման համար՝ '.number_format((float) $vendor->delivery_min_amount)." {$vendor->currency}:",
                    ]);
                }
            } elseif ($dto->type === 'takeaway') {
                if (! ($vendor->takeaway_enabled ?? true)) {
                    throw ValidationException::withMessages([
                        'takeaway' => 'Տեղում վերցնելու (Takeaway) ծառայությունը ներկայումս հասանելի չէ:',
                    ]);
                }

                if ((float) $vendor->takeaway_min_amount > 0 && $itemsSubtotal < (float) $vendor->takeaway_min_amount) {
                    throw ValidationException::withMessages([
                        'takeaway' => 'Նվազագույն պատվերի գումարը տեղում վերցնելու համար՝ '.number_format((float) $vendor->takeaway_min_amount)." {$vendor->currency}:",
                    ]);
                }
            }

            $effectiveBirthdate = $dto->customerBirthdate ?? ($customer?->birthdate ? ($customer->birthdate instanceof \DateTimeInterface ? $customer->birthdate->format('Y-m-d') : (string) $customer->birthdate) : null);
            $birthdayDiscount = $this->crmService->calculateBirthdayDiscount($itemsSubtotal, $effectiveBirthdate, $vendor);
            $fees = $this->calculateFees($vendor, $itemsSubtotal, $dto->type);

            $tipResult = $this->tipRatingService->calculateTip(
                vendor: $vendor,
                baseAmount: $itemsSubtotal,
                tipType: $dto->tipType ?? 'custom',
                tipValue: $dto->tipValue ?? $dto->tipAmount,
                orderType: $dto->type
            );
            $tipAmount = $tipResult['amount'];

            $finalTotal = max(0, $fees['final_total'] - $birthdayDiscount - $dto->discountAmount + $dto->taxAmount + $tipAmount);

            $order->update([
                'subtotal' => $itemsSubtotal,
                'birthday_discount_amount' => $birthdayDiscount,
                'discount_amount' => $dto->discountAmount,
                'discount_type' => $dto->discountType,
                'discount_reason' => $dto->discountReason,
                'tax_amount' => $dto->taxAmount,
                'service_fee' => $fees['service_fee'],
                'delivery_fee' => $fees['delivery_fee'],
                'tip_amount' => $tipAmount,
                'total_amount' => $finalTotal,
            ]);

            if ($tipAmount > 0) {
                $this->tipRatingService->recordOrderTip(
                    order: $order,
                    tipAmount: $tipAmount,
                    paymentMethod: $paymentMethod,
                    tipType: $tipResult['type'],
                    percentage: $tipResult['percentage'],
                    waiterId: $dto->waiterId
                );
            }

            if ($customer) {
                $this->customerSyncService->recalculateStats($customer);
            }

            // Automatically link and occupy table if dine-in
            if (! in_array($dto->type, ['delivery', 'takeaway']) && ($dto->restaurantTableId || $dto->tableNumber)) {
                $table = $dto->restaurantTableId
                    ? RestaurantTable::find($dto->restaurantTableId)
                    : RestaurantTable::where('vendor_id', $vendor->id)
                        ->when($dto->locationId, fn ($q) => $q->where('location_id', $dto->locationId))
                        ->where(function ($q) use ($dto) {
                            $q->where('table_number', $dto->tableNumber)
                                ->orWhere('name', $dto->tableNumber);
                        })->first();

                if ($table) {
                    if (! $order->restaurant_table_id) {
                        $order->update(['restaurant_table_id' => $table->id]);
                    }
                    $table->markOccupied($order, $dto->waiterId ? User::find($dto->waiterId) : null);
                }
            }
        });

        $this->dispatchService->dispatchNotifications($order, false);
        $whatsappUrl = $this->buildWhatsAppUrl($order, $vendor, $dto->locationId, false);

        return [
            'order' => $order,
            'whatsapp_url' => $whatsappUrl,
            'is_appended' => false,
        ];
    }

    /**
     * Resolve unit price and variation name for an item.
     *
     * @return array{unit_price: float, variation_name: string}
     */
    public function resolveItemPricing(Product $product, OrderItemDTO $item, int $locationId): array
    {
        return $this->pricingService->resolveItemPricing($product, $item, $locationId);
    }

    /**
     * Calculate order fees based on vendor settings and order type.
     *
     * @return array{service_fee: float, delivery_fee: float, final_total: float}
     */
    public function calculateFees(Vendor $vendor, float $itemsSubtotal, string $orderType): array
    {
        return $this->pricingService->calculateFees($vendor, $itemsSubtotal, $orderType);
    }

    /**
     * Build WhatsApp dispatch URL if branch has configured phone number.
     */
    public function buildWhatsAppUrl(Order $order, Vendor $vendor, int $locationId, bool $isAppended = false): ?string
    {
        return $this->dispatchService->buildWhatsAppUrl($order, $vendor, $locationId, $isAppended);
    }

    /**
     * Generate collision-resistant unique order number.
     */
    protected function generateOrderNumber(int $vendorId): string
    {
        return retry(5, function () use ($vendorId) {
            $candidate = 'ORD-'.strtoupper(Str::random(8));
            if (Order::withoutGlobalScopes()->where('vendor_id', $vendorId)->where('order_number', $candidate)->exists()) {
                throw new \RuntimeException("Collision detected for order number {$candidate}");
            }

            return $candidate;
        });
    }

    /**
     * Validate product availability for the given location, time, and order channel.
     *
     * @throws ValidationException
     */
    protected function validateProductAvailability(Product $product, int $locationId, string $orderType): void
    {
        // 1. Branch availability
        if (! $product->isAvailableAtLocation($locationId)) {
            throw ValidationException::withMessages([
                'items' => "«{$product->name}» ուտեստը սպառված է կամ հասանելի չէ ընտրված մասնաճյուղում:",
            ]);
        }

        // 2. Schedule / time availability
        if (! $product->isTimeAvailable()) {
            $locale = app()->getLocale() ?: 'hy';
            $scheduleDesc = $product->getAvailabilityScheduleSummary($locale);
            $notice = $scheduleDesc
                ? __('menu.only_available_hours_notice', ['time' => $scheduleDesc], $locale)
                : __('menu.closed_hours', [], $locale);
            throw ValidationException::withMessages([
                'items' => "«{$product->name}» - {$notice}:",
            ]);
        }

        // 3. Order type / channel availability
        if (! $product->isOrderTypeAvailable($orderType)) {
            $channelNames = [
                'dine_in' => 'Dine-in',
                'takeaway' => 'Takeaway',
                'delivery' => 'Delivery',
            ];
            $curChannel = $channelNames[$orderType] ?? $orderType;
            throw ValidationException::withMessages([
                'items' => "The item \"{$product->name}\" is not available for {$curChannel} orders.",
            ]);
        }
    }

    /**
     * Validate that the order channel / kitchen is currently open according to operating schedule.
     *
     * @throws ValidationException
     */
    protected function validateChannelOperatingHours(Vendor $vendor, Location|int $location, string $orderType): void
    {
        $loc = $location instanceof Location
            ? $location
            : (($vendor->relationLoaded('locations') ? $vendor->locations->firstWhere('id', (int) $location) : null) ?? $vendor->locations()->find($location));
        $schedule = $vendor->resolveOperatingSchedule($orderType, $loc, null, app()->getLocale());

        if ($schedule['schedule_enabled'] && ! $schedule['is_open']) {
            $msg = $schedule['notice_message'] ?? __('menu.service_closed_notice_title');
            throw ValidationException::withMessages([
                'type' => $msg,
            ]);
        }
    }
}
