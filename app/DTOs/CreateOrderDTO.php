<?php

namespace App\DTOs;

use App\Http\Requests\SubmitOrderRequest;

class CreateOrderDTO
{
    /**
     * @param  OrderItemDTO[]  $items
     */
    public function __construct(
        public readonly int $locationId,
        public readonly string $type,
        public readonly array $items,
        public readonly ?string $tableNumber = null,
        public readonly ?string $deliveryAddress = null,
        public readonly ?string $customerName = null,
        public readonly ?string $customerPhone = null,
        public readonly ?string $customerEmail = null,
        public readonly ?string $customerBirthdate = null,
        public readonly bool $marketingOptIn = true,
        public readonly ?string $notes = null,
        public readonly ?string $paymentMethod = null,
        public readonly ?string $activeOrderNumber = null,
        public readonly float $tipAmount = 0.00,
        public readonly ?string $tipType = null,
        public readonly ?float $tipValue = null,
        public readonly ?int $waiterId = null,
    ) {}

    /**
     * Instantiate from an associative array.
     */
    public static function fromArray(array $data): self
    {
        $rawItems = $data['items'] ?? [];
        $items = [];

        foreach ($rawItems as $item) {
            if ($item instanceof OrderItemDTO) {
                $items[] = $item;
            } elseif (is_array($item)) {
                $items[] = OrderItemDTO::fromArray($item);
            }
        }

        return new self(
            locationId: (int) $data['location_id'],
            type: ! empty($data['type']) ? (string) $data['type'] : 'dine_in',
            items: $items,
            tableNumber: ! empty($data['table_number']) ? trim((string) $data['table_number']) : null,
            deliveryAddress: ! empty($data['delivery_address']) ? trim((string) $data['delivery_address']) : null,
            customerName: ! empty($data['customer_name']) ? trim((string) $data['customer_name']) : null,
            customerPhone: ! empty($data['customer_phone']) ? trim((string) $data['customer_phone']) : null,
            customerEmail: ! empty($data['customer_email']) ? strtolower(trim((string) $data['customer_email'])) : null,
            customerBirthdate: ! empty($data['customer_birthdate']) ? trim((string) $data['customer_birthdate']) : null,
            marketingOptIn: filter_var($data['marketing_opt_in'] ?? true, FILTER_VALIDATE_BOOLEAN),
            notes: ! empty($data['notes']) ? trim((string) $data['notes']) : null,
            paymentMethod: ! empty($data['payment_method']) ? trim((string) $data['payment_method']) : null,
            activeOrderNumber: ! empty($data['active_order_number']) ? trim((string) $data['active_order_number']) : null,
            tipAmount: ! empty($data['tip_amount']) ? (float) $data['tip_amount'] : 0.00,
            tipType: ! empty($data['tip_type']) ? trim((string) $data['tip_type']) : null,
            tipValue: isset($data['tip_value']) ? (float) $data['tip_value'] : null,
            waiterId: ! empty($data['waiter_id']) ? (int) $data['waiter_id'] : null,
        );
    }

    /**
     * Convert DTO to associative array.
     */
    public function toArray(): array
    {
        return [
            'location_id' => $this->locationId,
            'type' => $this->type,
            'items' => array_map(fn (OrderItemDTO $item) => $item->toArray(), $this->items),
            'table_number' => $this->tableNumber,
            'delivery_address' => $this->deliveryAddress,
            'customer_name' => $this->customerName,
            'customer_phone' => $this->customerPhone,
            'customer_email' => $this->customerEmail,
            'customer_birthdate' => $this->customerBirthdate,
            'marketing_opt_in' => $this->marketingOptIn,
            'notes' => $this->notes,
            'payment_method' => $this->paymentMethod,
            'active_order_number' => $this->activeOrderNumber,
            'tip_amount' => $this->tipAmount,
            'tip_type' => $this->tipType,
            'tip_value' => $this->tipValue,
            'waiter_id' => $this->waiterId,
        ];
    }

    /**
     * Instantiate from a validated SubmitOrderRequest.
     */
    public static function fromRequest(SubmitOrderRequest $request): self
    {
        return self::fromArray($request->validated());
    }
}
