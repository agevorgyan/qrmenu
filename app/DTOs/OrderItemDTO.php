<?php

namespace App\DTOs;

class OrderItemDTO
{
    public function __construct(
        public readonly int $productId,
        public readonly int $quantity,
        public readonly ?int $variationId = null,
        public readonly ?string $variationName = null,
        public readonly ?string $notes = null,
        public readonly array $modifiers = [],
        public readonly array $variants = [],
        public readonly string $status = 'pending',
        public readonly ?string $kitchenStation = null,
    ) {}

    /**
     * Instantiate from an associative array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            productId: (int) $data['product_id'],
            quantity: max(1, (int) ($data['quantity'] ?? 1)),
            variationId: ! empty($data['variation_id']) ? (int) $data['variation_id'] : null,
            variationName: ! empty($data['variation_name']) ? trim((string) $data['variation_name']) : null,
            notes: ! empty($data['notes']) ? trim((string) $data['notes']) : null,
            modifiers: is_array($data['modifiers'] ?? null) ? $data['modifiers'] : [],
            variants: is_array($data['variants'] ?? null) ? $data['variants'] : [],
            status: ! empty($data['status']) ? (string) $data['status'] : 'pending',
            kitchenStation: ! empty($data['kitchen_station']) ? (string) $data['kitchen_station'] : null,
        );
    }

    /**
     * Convert DTO to associative array.
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'variation_id' => $this->variationId,
            'variation_name' => $this->variationName,
            'notes' => $this->notes,
            'modifiers' => $this->modifiers,
            'variants' => $this->variants,
            'status' => $this->status,
            'kitchen_station' => $this->kitchenStation,
        ];
    }
}
