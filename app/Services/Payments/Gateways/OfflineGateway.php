<?php

namespace App\Services\Payments\Gateways;

use App\Models\PaymentAttempt;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\DTOs\PaymentInitiationResult;
use App\Services\Payments\DTOs\PaymentVerificationResult;

class OfflineGateway implements PaymentGatewayInterface
{
    public function __construct(protected string $id = 'cash', protected string $name = 'Offline Payment') {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function initiatePayment(PaymentAttempt $attempt, array $options = []): PaymentInitiationResult
    {
        return PaymentInitiationResult::offline(
            merchantReference: $attempt->merchant_reference,
            message: 'Payment to be completed offline (cash or POS terminal).'
        );
    }

    public function verifyCallback(PaymentAttempt $attempt, array $payload): PaymentVerificationResult
    {
        return PaymentVerificationResult::failed(
            errorMessage: 'Offline payments cannot be confirmed via online callbacks.',
            merchantReference: $attempt->merchant_reference
        );
    }

    public function verifyWebhook(array $payload, array $headers = []): PaymentVerificationResult
    {
        return PaymentVerificationResult::failed('Offline payments do not support webhooks.');
    }

    public function supportsWebhooks(): bool
    {
        return false;
    }

    public function supportsRefunds(): bool
    {
        return false;
    }

    public function queryTransactionStatus(PaymentAttempt $attempt): PaymentVerificationResult
    {
        return PaymentVerificationResult::pending(
            merchantReference: $attempt->merchant_reference,
            providerTransactionId: $attempt->provider_transaction_id,
            rawPayload: ['note' => 'Offline payments require manual administrative confirmation']
        );
    }
}
