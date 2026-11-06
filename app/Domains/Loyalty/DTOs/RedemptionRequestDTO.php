<?php

namespace App\Domains\Loyalty\DTOs;

readonly class RedemptionRequestDTO
{
    public function __construct(
        public int $vendorId,
        public int $customerId,
        public int $rewardId,
        public ?int $orderId = null,
        public ?int $staffId = null,
    ) {}
}
