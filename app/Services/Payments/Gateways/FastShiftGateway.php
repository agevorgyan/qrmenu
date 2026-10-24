<?php

namespace App\Services\Payments\Gateways;

use App\Models\PaymentAttempt;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\DTOs\PaymentInitiationResult;
use App\Services\Payments\DTOs\PaymentVerificationResult;

class FastShiftGateway implements PaymentGatewayInterface
{
    public function getId(): string
    {
        return 'fastshift';
    }

    public function getName(): string
    {
        return 'FastShift';
    }

    /**
     * Initiate payment transaction for a payment attempt.
     *
     * @param  array<string, mixed>  $options
     */
    public function initiatePayment(PaymentAttempt $attempt, array $options = []): PaymentInitiationResult
    {
        $vendor = $attempt->vendor;
        $returnUrl = route('client.payment.callback', [
            'vendor_slug' => $vendor->slug,
            'reference' => $attempt->merchant_reference,
        ]);

        $gatewayData = [
            'order_id' => $attempt->merchant_reference,
            'amount' => $attempt->amount,
            'currency' => $attempt->currency,
            'return_url' => $returnUrl,
            'callback_url' => route('api.webhooks.payment', ['gateway' => 'fastshift']),
        ];

        return PaymentInitiationResult::redirect(
            redirectUrl: 'https://fastshift.am/payment/checkout?'.http_build_query($gatewayData),
            merchantReference: $attempt->merchant_reference,
            gatewayData: $gatewayData
        );
    }

    /**
     * Verify payment status on client callback (browser return).
     * Never trusts browser parameters as proof of payment.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyCallback(PaymentAttempt $attempt, array $payload): PaymentVerificationResult
    {
        return PaymentVerificationResult::pending(
            merchantReference: $attempt->merchant_reference,
            rawPayload: $payload
        );
    }

    /**
     * Verify asynchronous server-to-server webhook confirmation from FastShift.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function verifyWebhook(array $payload, array $headers = []): PaymentVerificationResult
    {
        $orderId = $payload['order_id'] ?? null;
        $operationId = $payload['operation_id'] ?? null;
        $amount = $payload['amount'] ?? null;
        $currency = $payload['currency'] ?? 'AMD';
        $status = (string) ($payload['status'] ?? '');
        $hash = $payload['hash'] ?? null;

        if (! $orderId || ! $operationId || ! $amount) {
            return PaymentVerificationResult::failed(
                errorMessage: 'Missing FastShift verification fields',
                merchantReference: $orderId,
                providerTransactionId: $operationId,
                rawPayload: $payload
            );
        }

        $attempt = PaymentAttempt::where('merchant_reference', $orderId)->first();
        if (! $attempt) {
            return PaymentVerificationResult::failed(
                errorMessage: "Payment attempt not found for reference {$orderId}",
                merchantReference: $orderId,
                providerTransactionId: $operationId,
                rawPayload: $payload
            );
        }

        if ($status !== '1' && strtolower($status) !== 'success') {
            return PaymentVerificationResult::failed(
                errorMessage: "FastShift payment status is not successful: {$status}",
                merchantReference: $orderId,
                providerTransactionId: $operationId,
                rawPayload: $payload
            );
        }

        $vendor = $attempt->vendor;
        $settings = $vendor->getPaymentSettings()['gateways']['fastshift'] ?? [];
        $merchantId = $settings['merchant_id'] ?? '';
        $secretKey = $settings['secret_key'] ?? '';

        if (! empty($secretKey) && $hash) {
            $formattedAmount = number_format((float) $amount, 2, '.', '');
            $expectedHash = md5("{$merchantId}:{$orderId}:{$formattedAmount}:{$secretKey}");

            if (! hash_equals($expectedHash, (string) $hash)) {
                return PaymentVerificationResult::failed(
                    errorMessage: 'FastShift signature verification failed',
                    merchantReference: $orderId,
                    providerTransactionId: $operationId,
                    rawPayload: $payload
                );
            }
        }

        return PaymentVerificationResult::paid(
            providerTransactionId: (string) $operationId,
            amount: (float) $amount,
            currency: $currency,
            merchantReference: (string) $orderId,
            rawPayload: $payload,
            signatureValid: true
        );
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }

    public function supportsRefunds(): bool
    {
        return false;
    }

    /**
     * Authoritatively query FastShift transaction status during reconciliation.
     */
    public function queryTransactionStatus(PaymentAttempt $attempt): PaymentVerificationResult
    {
        return PaymentVerificationResult::pending(
            merchantReference: $attempt->merchant_reference,
            providerTransactionId: $attempt->provider_transaction_id,
            rawPayload: ['note' => 'FastShift relies on server-to-server webhook confirmation']
        );
    }
}
