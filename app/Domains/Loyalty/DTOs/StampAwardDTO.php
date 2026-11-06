<?php

namespace App\Domains\Loyalty\DTOs;

readonly class StampAwardDTO
{
    public function __construct(
        public int $vendorId,
        public int $customerId,
        public int $programId,
        public int $stamps = 1,
        public ?int $orderId = null,
        public ?int $staffId = null,
        public ?string $notes = null,
    ) {}
}
