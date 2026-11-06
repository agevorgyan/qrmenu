<?php

namespace App\Domains\Loyalty\DTOs;

readonly class LoyaltyCalculationResult
{
    /**
     * @param  array<string, mixed>  $appliedRules
     */
    public function __construct(
        public int $pointsEarned,
        public float $cashbackEarned,
        public int $stampsEarned,
        public float $multiplier,
        public array $appliedRules = [],
    ) {}
}
