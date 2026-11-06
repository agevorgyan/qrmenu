<?php

namespace App\Domains\Loyalty\Events;

use App\Domains\Loyalty\Models\LoyaltyRewardRedemption;
use App\Models\Customer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RewardRedeemed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Customer $customer,
        public LoyaltyRewardRedemption $redemption,
    ) {}
}
