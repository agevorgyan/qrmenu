<?php

namespace App\DTOs;

use App\Security\QuotaCatalog;
use Carbon\Carbon;

class QuotaStatus
{
    public function __construct(
        public readonly string $quotaKey,
        public readonly string $type,
        public readonly int $limit,
        public readonly int $currentUsage,
        public readonly int $remainingCapacity,
        public readonly bool $isUnlimited,
        public readonly bool $isExceeded,
        public readonly ?Carbon $resetAt = null,
        public readonly string $scope = 'tenant',
        public readonly string $unit = 'units',
        public readonly ?string $displayName = null,
    ) {}

    public static function make(
        string $quotaKey,
        int $limit,
        int $currentUsage,
        ?Carbon $resetAt = null,
        string $scope = 'tenant',
        ?string $locale = 'hy'
    ): self {
        $canonicalKey = QuotaCatalog::normalize($quotaKey);
        $type = QuotaCatalog::getType($canonicalKey);
        $unit = QuotaCatalog::getUnit($canonicalKey);
        $displayName = QuotaCatalog::getDisplayName($canonicalKey, $locale ?? 'hy');

        $isUnlimited = ($limit === QuotaCatalog::UNLIMITED);
        $remaining = $isUnlimited ? QuotaCatalog::UNLIMITED : max(0, $limit - $currentUsage);
        $isExceeded = (! $isUnlimited && $currentUsage >= $limit);

        return new self(
            quotaKey: $canonicalKey,
            type: $type,
            limit: $limit,
            currentUsage: $currentUsage,
            remainingCapacity: $remaining,
            isUnlimited: $isUnlimited,
            isExceeded: $isExceeded,
            resetAt: $resetAt,
            scope: $scope,
            unit: $unit,
            displayName: $displayName,
        );
    }

    public function toArray(): array
    {
        return [
            'quota_key' => $this->quotaKey,
            'type' => $this->type,
            'limit' => $this->limit,
            'current_usage' => $this->currentUsage,
            'remaining_capacity' => $this->remainingCapacity,
            'is_unlimited' => $this->isUnlimited,
            'is_exceeded' => $this->isExceeded,
            'reset_at' => $this->resetAt?->toISOString(),
            'scope' => $this->scope,
            'unit' => $this->unit,
            'display_name' => $this->displayName,
        ];
    }
}
