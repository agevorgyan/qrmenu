<?php

namespace App\Domains\Loyalty\DTOs;

use App\Domains\Loyalty\Enums\LoyaltyTransactionType;

readonly class CashbackTransactionDTO
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public int $vendorId,
        public int $customerId,
        public float $amount,
        public LoyaltyTransactionType $type,
        public ?int $orderId = null,
        public ?int $staffId = null,
        public ?string $idempotencyKey = null,
        public ?string $notes = null,
        public ?array $metadata = null,
    ) {}
}
