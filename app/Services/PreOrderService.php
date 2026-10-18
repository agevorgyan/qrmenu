<?php

namespace App\Services;

use App\DTOs\OrderItemDTO;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationPreOrder;
use App\Models\Vendor;
use Illuminate\Validation\ValidationException;

class PreOrderService
{
    public function __construct(
        protected OrderPricingService $pricingService,
    ) {}

    /**
     * Revalidate menu items availability, resolve official prices, and compute fee-inclusive total.
     *
     * @param  array<int, array<string, mixed>>  $rawItems
     * @return array{items: array, subtotal: float, service_fee: float, total_amount: float, currency: string}
     *
     * @throws ValidationException
     */
    public function validateAndPriceItems(Vendor $vendor, array $rawItems, ?int $locationId = null): array
    {
        if (empty($rawItems)) {
            return [
                'items' => [],
                'subtotal' => 0.0,
                'service_fee' => 0.0,
                'total_amount' => 0.0,
                'currency' => $vendor->currency ?: 'AMD',
            ];
        }

        $locationId = $locationId ?: $vendor->locations->first()?->id;
        $productIds = collect($rawItems)->pluck('product_id')->filter()->unique()->all();

        // Multi-tenant protection: strictly load products belonging to this vendor
        $products = Product::where('vendor_id', $vendor->id)
            ->whereIn('id', $productIds)
            ->with(['variations', 'overrides'])
            ->get()
            ->keyBy('id');

        if (count($productIds) !== $products->count()) {
            throw ValidationException::withMessages([
                'items' => 'One or more selected menu items do not belong to this restaurant.',
            ]);
        }

        $snapshotItems = [];
        $itemsSubtotal = 0.0;

        foreach ($rawItems as $raw) {
            $productId = (int) ($raw['product_id'] ?? 0);
            $qty = max(1, (int) ($raw['quantity'] ?? 1));
            $product = $products->get($productId);

            if (! $product) {
                throw ValidationException::withMessages([
                    'items' => "Product #{$productId} is not available.",
                ]);
            }

            // Check branch availability if location specified
            if ($locationId && ! $product->isAvailableAtLocation($locationId)) {
                throw ValidationException::withMessages([
                    'items' => "The item \"{$product->name}\" is not available at this location.",
                ]);
            }

            // Build DTO and resolve official pricing from backend to prevent client tampering
            $dto = OrderItemDTO::fromArray([
                'product_id' => $productId,
                'quantity' => $qty,
                'variation_id' => $raw['variation_id'] ?? null,
                'variation_name' => $raw['variation_name'] ?? null,
            ]);

            $pricing = $this->pricingService->resolveItemPricing($product, $dto, (int) $locationId);
            $lineSubtotal = round($pricing['unit_price'] * $qty, 2);
            $itemsSubtotal += $lineSubtotal;

            $itemNotes = ! empty($raw['notes']) ? trim((string) $raw['notes']) : null;

            $snapshotItems[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'name_translations' => $product->name_translations ?? [],
                'variation_name' => $pricing['variation_name'],
                'unit_price' => (float) $pricing['unit_price'],
                'quantity' => $qty,
                'subtotal' => $lineSubtotal,
                'notes' => $itemNotes,
            ];
        }

        // Apply restaurant fees (service fee / dine-in)
        $fees = $this->pricingService->calculateFees($vendor, $itemsSubtotal, 'dine_in');

        return [
            'items' => $snapshotItems,
            'subtotal' => round($itemsSubtotal, 2),
            'service_fee' => round((float) ($fees['service_fee'] ?? 0), 2),
            'total_amount' => round((float) ($fees['final_total'] ?? $itemsSubtotal), 2),
            'currency' => $vendor->currency ?: 'AMD',
        ];
    }

    /**
     * Attach or update a pre-order on a reservation.
     *
     * @throws ValidationException
     */
    public function attachPreOrder(Reservation $reservation, array $rawItems, ?string $notes = null): ?ReservationPreOrder
    {
        $vendor = $reservation->vendor;
        $settings = $vendor->reservation_settings;
        $mode = $settings['pre_orders_mode'] ?? 'optional';

        if (! $vendor->isPreOrderFeatureEnabled() && ! empty($rawItems)) {
            throw ValidationException::withMessages([
                'pre_order' => 'Pre-orders are currently disabled for this restaurant.',
            ]);
        }

        if ($mode === 'disabled' && ! empty($rawItems)) {
            throw ValidationException::withMessages([
                'pre_order' => 'Pre-orders are currently disabled for this restaurant.',
            ]);
        }

        if ($mode === 'mandatory' && empty($rawItems)) {
            throw ValidationException::withMessages([
                'pre_order' => 'A pre-order from the menu is required for reservations at this restaurant.',
            ]);
        }

        if (empty($rawItems)) {
            return null;
        }

        $priced = $this->validateAndPriceItems($vendor, $rawItems, $reservation->location_id);

        $preOrder = ReservationPreOrder::updateOrCreate(
            ['reservation_id' => $reservation->id],
            [
                'vendor_id' => $vendor->id,
                'status' => ReservationPreOrder::STATUS_PENDING,
                'items' => $priced['items'],
                'subtotal' => $priced['subtotal'],
                'total_amount' => $priced['total_amount'],
                'currency' => $priced['currency'],
                'notes' => $notes,
            ]
        );

        return $preOrder;
    }
}
