<?php

namespace App\Exceptions;

use App\Enums\SubscriptionStatus;
use DomainException;

class InvalidSubscriptionTransitionException extends DomainException
{
    public function __construct(
        public readonly SubscriptionStatus $from,
        public readonly SubscriptionStatus $to,
        string $message = ''
    ) {
        $msg = $message ?: "Subscription state transition from '{$from->value}' to '{$to->value}' is not permitted by the state machine.";
        parent::__construct($msg);
    }
}
