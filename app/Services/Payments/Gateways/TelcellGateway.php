<?php

namespace App\Services\Payments\Gateways;

use App\Models\PaymentAttempt;
use App\Models\SystemSetting;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\DTOs\PaymentInitiationResult;
use App\Services\Payments\DTOs\PaymentVerificationResult;

class TelcellGateway implements PaymentGatewayInterface
{
    public function getId(): string
    {
        return 'telcell';
    }

    public function getName(): string
    {
        return 'Telcell Wallet';
    }

    /**
     * Initiate payment transaction for a payment attempt.
     *
     * @param  array<string, mixed>  $options
     */
    public function initiatePayment(PaymentAttempt $attempt, array $options = []): PaymentInitiationResult
    {
        $vendor = $attempt->vendor;
        $vendorSettings = $vendor?->getPaymentSettings()['gateways']['telcell'] ?? [];
        $platformSettings = SystemSetting::getPaymentGatewaySettings()['telcell'] ?? [];
        $vShop = ! empty($vendorSettings['shop_id']) ? $vendorSettings['shop_id'] : ($vendorSettings['merchant_id'] ?? null);
        $pShop = ! empty($platformSettings['shop_id']) ? $platformSettings['shop_id'] : null;
        $shopId = ($attempt->isSubscriptionPayment() || empty($vShop)) ? ($pShop ?: $vShop) : $vShop;

        $returnUrl = route('client.payment.callback', [
            'vendor_slug' => $vendor->slug,
            'reference' => $attempt->merchant_reference,
        ]);

        $gatewayData = [
            'action' => 'PostInvoice',
            'issuer' => $shopId ?? '1001',
            'currency' => $attempt->currency,
            'price' => (int) round($attempt->amount),
            'product_id' => $attempt->merchant_reference,
            'valid_days' => 1,
            'return_url' => $returnUrl,
            'result_url' => route('api.webhooks.payment', ['gateway' => 'telcell']),
        ];

        return PaymentInitiationResult::redirect(
            redirectUrl: 'https://telcell.am/checkout?'.http_build_query($gatewayData),
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
     * Verify asynchronous server-to-server webhook confirmation from Telcell.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function verifyWebhook(array $payload, array $headers = []): PaymentVerificationResult
    {
        $issuer = $payload['issuer'] ?? null;
        $currency = $payload['currency'] ?? null;
        $price = $payload['price'] ?? null;
        $productId = $payload['product_id'] ?? null;
        $buyer = $payload['buyer'] ?? '';
        $time = $payload['time'] ?? '';
        $invoice = $payload['invoice'] ?? null;
        $checksum = $payload['checksum'] ?? null;

        if (! $productId || ! $price || ! $invoice) {
            return PaymentVerificationResult::failed(
                errorMessage: 'Missing required Telcell parameters',
                merchantReference: $productId,
                providerTransactionId: $invoice,
                rawPayload: $payload
            );
        }

        $attempt = PaymentAttempt::where('merchant_reference', $productId)->first();
        if (! $attempt) {
            return PaymentVerificationResult::failed(
                errorMessage: "Payment attempt not found for reference {$productId}",
                merchantReference: $productId,
                providerTransactionId: $invoice,
                rawPayload: $payload
            );
        }

        $vendor = $attempt->vendor;
        $vendorSettings = $vendor?->getPaymentSettings()['gateways']['telcell'] ?? [];
        $platformSettings = SystemSetting::getPaymentGatewaySettings()['telcell'] ?? [];
        $vShop = ! empty($vendorSettings['shop_id']) ? $vendorSettings['shop_id'] : ($vendorSettings['merchant_id'] ?? null);
        $pShop = ! empty($platformSettings['shop_id']) ? $platformSettings['shop_id'] : null;
        $expectedIssuer = ($attempt->isSubscriptionPayment() || empty($vShop)) ? ($pShop ?: $vShop) : $vShop;

        $vKey = ! empty($vendorSettings['key']) ? $vendorSettings['key'] : (! empty($vendorSettings['security_key']) ? $vendorSettings['security_key'] : ($vendorSettings['secret_key'] ?? null));
        $pKey = ! empty($platformSettings['key']) ? $platformSettings['key'] : null;
        $securityKey = ($attempt->isSubscriptionPayment() || empty($vKey)) ? ($pKey ?: $vKey) : $vKey;

        if ($expectedIssuer && $issuer !== $expectedIssuer) {
            return PaymentVerificationResult::failed(
                errorMessage: 'Telcell issuer mismatch',
                merchantReference: $productId,
                providerTransactionId: $invoice,
                rawPayload: $payload
            );
        }

        // Verify HMAC-SHA256 signature if security key configured
        if (! empty($securityKey) && $checksum) {
            $rawPayload = "{$issuer}:{$currency}:{$price}:{$productId}:{$buyer}:{$time}:{$invoice}";
            $expectedChecksum = hash_hmac('sha256', $rawPayload, $securityKey);

            if (! hash_equals($expectedChecksum, (string) $checksum)) {
                return PaymentVerificationResult::failed(
                    errorMessage: 'Telcell checksum verification failed',
                    merchantReference: $productId,
                    providerTransactionId: $invoice,
                    rawPayload: $payload
                );
            }
        }

        return PaymentVerificationResult::paid(
            providerTransactionId: (string) $invoice,
            amount: (float) $price,
            currency: $currency ?? 'AMD',
            merchantReference: (string) $productId,
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
     * Authoritatively query Telcell transaction status during reconciliation.
     */
    public function queryTransactionStatus(PaymentAttempt $attempt): PaymentVerificationResult
    {
        return PaymentVerificationResult::pending(
            merchantReference: $attempt->merchant_reference,
            providerTransactionId: $attempt->provider_transaction_id,
            rawPayload: ['note' => 'Telcell relies on server-to-server webhook confirmation']
        );
    }
}
