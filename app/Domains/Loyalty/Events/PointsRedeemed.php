<?php

namespace App\Domains\Loyalty\Events;

use App\Domains\Loyalty\Models\LoyaltyLedgerTransaction;
use App\Models\Customer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PointsRedeemed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Customer $customer,
        public LoyaltyLedgerTransaction $transaction,
    ) {}
}
