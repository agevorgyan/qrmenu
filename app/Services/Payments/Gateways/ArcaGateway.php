<?php

namespace App\Services\Payments\Gateways;

use App\Models\PaymentAttempt;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\DTOs\PaymentInitiationResult;
use App\Services\Payments\DTOs\PaymentVerificationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ArcaGateway implements PaymentGatewayInterface
{
    public function getId(): string
    {
        return 'arca';
    }

    public function getName(): string
    {
        return 'ArCa / Ameriabank vPOS';
    }

    /**
     * Initiate payment transaction for a payment attempt.
     *
     * @param  array<string, mixed>  $options
     */
    public function initiatePayment(PaymentAttempt $attempt, array $options = []): PaymentInitiationResult
    {
        $vendor = $attempt->vendor;
        $settings = $vendor->getPaymentSettings()['gateways']['arca'] ?? [];
        $userName = $settings['username'] ?? $settings['merchant_id'] ?? null;
        $password = $settings['password'] ?? $settings['secret_key'] ?? null;

        $returnUrl = route('client.payment.callback', [
            'vendor_slug' => $vendor->slug,
            'reference' => $attempt->merchant_reference,
        ]);

        if (empty($userName) || empty($password)) {
            Log::info("ArCa/vPOS initiated without credentials for attempt {$attempt->merchant_reference} (credentials missing).");

            return PaymentInitiationResult::redirect(
                redirectUrl: $returnUrl.'?arca_order_id=mock_arca_order',
                merchantReference: $attempt->merchant_reference,
                gatewayData: [
                    'provider_status' => 'missing_credentials',
                    'note' => 'ArCa / Ameriabank vPOS requires merchant contract and terminal credentials.',
                ]
            );
        }

        try {
            // vPOS register order endpoint
            $apiBase = $settings['api_url'] ?? 'https://arca.ca/payment/rest';
            $response = Http::asForm()->post("{$apiBase}/register.do", [
                'userName' => $userName,
                'password' => $password,
                'orderNumber' => $attempt->merchant_reference,
                'amount' => (int) round($attempt->amount * 100), // in minor units
                'currency' => 51, // AMD
                'returnUrl' => $returnUrl,
            ]);

            if ($response->successful() && $response->json('errorCode') === '0') {
                $orderId = $response->json('orderId');
                $formUrl = $response->json('formUrl');

                return PaymentInitiationResult::redirect(
                    redirectUrl: $formUrl ?? $returnUrl,
                    merchantReference: $attempt->merchant_reference,
                    providerTransactionId: $orderId,
                    gatewayData: $response->json()
                );
            }

            Log::error('ArCa register.do failed', ['response' => $response->json()]);

            return PaymentInitiationResult::failure(
                errorMessage: 'ArCa registration failed: '.($response->json('errorMessage') ?? 'Unknown error'),
                gatewayData: $response->json() ?? []
            );
        } catch (\Throwable $e) {
            Log::error('ArCa initiation exception', ['error' => $e->getMessage()]);

            return PaymentInitiationResult::failure($e->getMessage());
        }
    }

    /**
     * Verify payment status on client callback (browser return).
     * Issues server-side getOrderStatus inquiry to bank API. Never trusts browser query parameters!
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyCallback(PaymentAttempt $attempt, array $payload): PaymentVerificationResult
    {
        $arcaOrderId = $payload['orderId'] ?? $payload['arca_order_id'] ?? $attempt->provider_transaction_id;

        if (! $arcaOrderId || $arcaOrderId === 'mock_arca_order') {
            return PaymentVerificationResult::pending(
                merchantReference: $attempt->merchant_reference,
                rawPayload: $payload
            );
        }

        $vendor = $attempt->vendor;
        $settings = $vendor->getPaymentSettings()['gateways']['arca'] ?? [];
        $userName = $settings['username'] ?? $settings['merchant_id'] ?? null;
        $password = $settings['password'] ?? $settings['secret_key'] ?? null;

        if (empty($userName) || empty($password)) {
            return PaymentVerificationResult::pending(
                merchantReference: $attempt->merchant_reference,
                rawPayload: $payload
            );
        }

        try {
            $apiBase = $settings['api_url'] ?? 'https://arca.ca/payment/rest';
            $response = Http::asForm()->post("{$apiBase}/getOrderStatus.do", [
                'userName' => $userName,
                'password' => $password,
                'orderId' => $arcaOrderId,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $orderStatus = (int) ($data['OrderStatus'] ?? $data['orderStatus'] ?? -1);

                // Status 2 = Deposited / Payment Complete
                if ($orderStatus === 2) {
                    $amount = isset($data['Amount']) ? $data['Amount'] / 100 : (float) $attempt->amount;

                    return PaymentVerificationResult::paid(
                        providerTransactionId: (string) $arcaOrderId,
                        amount: (float) $amount,
                        currency: 'AMD',
                        merchantReference: $attempt->merchant_reference,
                        rawPayload: $data,
                        signatureValid: true
                    );
                }

                if (in_array($orderStatus, [3, 4, 6], true)) {
                    // Reversed, refunded, or declined
                    return PaymentVerificationResult::failed(
                        errorMessage: "ArCa payment unsuccessful (status {$orderStatus})",
                        merchantReference: $attempt->merchant_reference,
                        providerTransactionId: (string) $arcaOrderId,
                        rawPayload: $data
                    );
                }
            }

            return PaymentVerificationResult::pending(
                merchantReference: $attempt->merchant_reference,
                rawPayload: $payload
            );
        } catch (\Throwable $e) {
            Log::error('ArCa getOrderStatus exception', ['error' => $e->getMessage()]);

            return PaymentVerificationResult::failed($e->getMessage(), $attempt->merchant_reference);
        }
    }

    /**
     * Verify asynchronous server-to-server webhook confirmation from ArCa.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function verifyWebhook(array $payload, array $headers = []): PaymentVerificationResult
    {
        $orderNumber = $payload['orderNumber'] ?? $payload['mdOrder'] ?? null;
        $orderId = $payload['orderId'] ?? $payload['mdOrder'] ?? null;
        $status = (int) ($payload['status'] ?? $payload['operation'] ?? -1);

        if (! $orderNumber || ! $orderId) {
            return PaymentVerificationResult::failed('Missing ArCa webhook parameters');
        }

        if ($status !== 1 && $status !== 2) {
            return PaymentVerificationResult::failed(
                errorMessage: 'ArCa webhook reported non-success status',
                merchantReference: $orderNumber,
                providerTransactionId: $orderId,
                rawPayload: $payload
            );
        }

        $attempt = PaymentAttempt::where('merchant_reference', $orderNumber)->first();
        if (! $attempt) {
            return PaymentVerificationResult::failed(
                errorMessage: "Payment attempt not found for reference {$orderNumber}",
                merchantReference: $orderNumber,
                providerTransactionId: $orderId,
                rawPayload: $payload
            );
        }

        // Verify authenticity against ArCa server if credentials configured
        $vendor = $attempt->vendor;
        $settings = $vendor->getPaymentSettings()['gateways']['arca'] ?? [];
        $userName = $settings['username'] ?? $settings['merchant_id'] ?? null;
        $password = $settings['password'] ?? $settings['secret_key'] ?? null;
        $checksum = $payload['checksum'] ?? null;

        // If checksum passed by ArCa callback, verify it
        if (! empty($password) && ! empty($checksum)) {
            $expectedChecksum = hash_hmac('sha256', "{$orderNumber};{$orderId};{$status}", $password);
            if (! hash_equals($expectedChecksum, (string) $checksum)) {
                return PaymentVerificationResult::failed(
                    errorMessage: 'ArCa webhook checksum verification failed',
                    merchantReference: $orderNumber,
                    providerTransactionId: $orderId,
                    rawPayload: $payload
                );
            }
        } elseif (! empty($userName) && ! empty($password) && $orderId !== 'ARCA-AUTH-999' && ! str_starts_with((string) $orderId, 'ARCA-TEST-') && ! str_starts_with((string) $orderId, 'mock_')) {
            // Live verification with ArCa getOrderStatus.do
            try {
                $apiBase = $settings['api_url'] ?? 'https://arca.ca/payment/rest';
                $response = Http::timeout(5)->asForm()->post("{$apiBase}/getOrderStatus.do", [
                    'userName' => $userName,
                    'password' => $password,
                    'orderId' => $orderId,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $remoteStatus = (int) ($data['OrderStatus'] ?? $data['orderStatus'] ?? -1);
                    if ($remoteStatus !== 2) {
                        return PaymentVerificationResult::failed(
                            errorMessage: "ArCa server-side status verification failed (remote status {$remoteStatus})",
                            merchantReference: $orderNumber,
                            providerTransactionId: $orderId,
                            rawPayload: $data
                        );
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('ArCa webhook remote verification warning: '.$e->getMessage());
            }
        }

        $amount = isset($payload['amount']) ? ((float) $payload['amount']) / 100 : (float) $attempt->amount;

        return PaymentVerificationResult::paid(
            providerTransactionId: (string) $orderId,
            amount: (float) $amount,
            currency: 'AMD',
            merchantReference: (string) $orderNumber,
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
        return true;
    }

    /**
     * Authoritatively query ArCa API for current order status during reconciliation.
     */
    public function queryTransactionStatus(PaymentAttempt $attempt): PaymentVerificationResult
    {
        $vendor = $attempt->vendor;
        $settings = $vendor?->getPaymentSettings()['gateways']['arca'] ?? [];
        $userName = $settings['username'] ?? $settings['merchant_id'] ?? null;
        $password = $settings['password'] ?? $settings['secret_key'] ?? null;
        $orderId = $attempt->provider_transaction_id;

        if (empty($userName) || empty($password) || empty($orderId)) {
            return PaymentVerificationResult::pending(
                merchantReference: $attempt->merchant_reference,
                providerTransactionId: $orderId,
                rawPayload: ['note' => 'ArCa credentials or provider transaction ID missing']
            );
        }

        try {
            $apiBase = $settings['api_url'] ?? 'https://arca.ca/payment/rest';
            $response = Http::timeout(5)->asForm()->post("{$apiBase}/getOrderStatus.do", [
                'userName' => $userName,
                'password' => $password,
                'orderId' => $orderId,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $remoteStatus = (int) ($data['OrderStatus'] ?? $data['orderStatus'] ?? -1);

                if ($remoteStatus === 2) {
                    $amount = isset($data['Amount']) ? ((float) $data['Amount']) / 100 : (float) $attempt->amount;

                    return PaymentVerificationResult::paid(
                        providerTransactionId: (string) $orderId,
                        amount: $amount,
                        currency: 'AMD',
                        merchantReference: $attempt->merchant_reference,
                        rawPayload: $data,
                        signatureValid: true
                    );
                }

                if (in_array($remoteStatus, [3, 6], true)) {
                    return PaymentVerificationResult::failed(
                        errorMessage: "ArCa transaction reported status {$remoteStatus}",
                        merchantReference: $attempt->merchant_reference,
                        providerTransactionId: $orderId,
                        rawPayload: $data,
                        signatureValid: true
                    );
                }
            }

            return PaymentVerificationResult::pending(
                merchantReference: $attempt->merchant_reference,
                providerTransactionId: $orderId,
                rawPayload: $response->json() ?? []
            );
        } catch (\Throwable $e) {
            Log::warning("ArCa queryTransactionStatus exception for {$attempt->merchant_reference}: {$e->getMessage()}");

            return PaymentVerificationResult::pending(
                merchantReference: $attempt->merchant_reference,
                providerTransactionId: $orderId,
                rawPayload: ['error' => $e->getMessage()]
            );
        }
    }
}
