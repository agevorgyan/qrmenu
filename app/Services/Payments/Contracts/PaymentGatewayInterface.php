<?php

namespace App\Services\Payments\Contracts;

use App\Models\PaymentAttempt;
use App\Services\Payments\DTOs\PaymentInitiationResult;
use App\Services\Payments\DTOs\PaymentVerificationResult;

interface PaymentGatewayInterface
{
    public function getId(): string;

    public function getName(): string;

    /**
     * Initiate payment transaction for a payment attempt.
     *
     * @param  array<string, mixed>  $options
     */
    public function initiatePayment(PaymentAttempt $attempt, array $options = []): PaymentInitiationResult;

    /**
     * Verify payment status on client callback (e.g. redirect return).
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyCallback(PaymentAttempt $attempt, array $payload): PaymentVerificationResult;

    /**
     * Verify asynchronous webhook or server-to-server confirmation.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function verifyWebhook(array $payload, array $headers = []): PaymentVerificationResult;

    public function supportsWebhooks(): bool;

    public function supportsRefunds(): bool;

    /**
     * Authoritatively query the gateway provider for current transaction status during reconciliation.
     */
    public function queryTransactionStatus(PaymentAttempt $attempt): PaymentVerificationResult;
}
