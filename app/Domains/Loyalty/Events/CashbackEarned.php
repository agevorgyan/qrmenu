<?php

namespace App\Domains\Loyalty\Events;

use App\Domains\Loyalty\Models\LoyaltyCashbackTransaction;
use App\Models\Customer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CashbackEarned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Customer $customer,
        public LoyaltyCashbackTransaction $transaction,
    ) {}
}
