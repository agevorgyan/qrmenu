<?php

namespace App\Domains\Loyalty\Events;

use App\Domains\Loyalty\Models\WalletPass;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletPassUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WalletPass $pass,
        public string $changeReason = 'balance_update',
    ) {}
}
