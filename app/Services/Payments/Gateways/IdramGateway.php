<?php

namespace App\Services\Payments\Gateways;

use App\Models\PaymentAttempt;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\DTOs\PaymentInitiationResult;
use App\Services\Payments\DTOs\PaymentVerificationResult;
use Illuminate\Support\Facades\Log;

class IdramGateway implements PaymentGatewayInterface
{
    public function getId(): string
    {
        return 'idram';
    }

    public function getName(): string
    {
        return 'Idram Wallet / QR';
    }

    /**
     * Initiate payment transaction for a payment attempt.
     *
     * @param  array<string, mixed>  $options
     */
    public function initiatePayment(PaymentAttempt $attempt, array $options = []): PaymentInitiationResult
    {
        $vendor = $attempt->vendor;
        $settings = $vendor->getPaymentSettings()['gateways']['idram'] ?? [];
        $recAccount = $settings['merchant_id'] ?? null;

        if (empty($recAccount)) {
            Log::warning("Idram payment initiated without configured merchant_id for vendor {$vendor->id}");
        }

        $idramUrl = 'https://banking.idram.am/Payment/GetPayment';
        $returnUrl = route('client.payment.callback', [
            'vendor_slug' => $vendor->slug,
            'reference' => $attempt->merchant_reference,
        ]);

        $gatewayData = [
            'EDP_LANGUAGE' => 'AM',
            'EDP_REC_ACCOUNT' => $recAccount ?? '100000000',
            'EDP_DESCRIPTION' => $attempt->order
                ? "Order #{$attempt->order->order_number} at {$vendor->name}"
                : "Subscription at {$vendor->name}",
            'EDP_AMOUNT' => number_format((float) $attempt->amount, 2, '.', ''),
            'EDP_BILL_NO' => $attempt->merchant_reference,
            'success_url' => $returnUrl,
            'fail_url' => $returnUrl.'?status=failed',
            'result_url' => route('api.webhooks.payment', ['gateway' => 'idram']),
        ];

        return PaymentInitiationResult::redirect(
            redirectUrl: $idramUrl.'?'.http_build_query($gatewayData),
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
        // Client redirect from browser cannot be trusted alone for Idram
        return PaymentVerificationResult::pending(
            merchantReference: $attempt->merchant_reference,
            rawPayload: $payload
        );
    }

    /**
     * Verify asynchronous server-to-server webhook confirmation from Idram.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function verifyWebhook(array $payload, array $headers = []): PaymentVerificationResult
    {
        $billNo = $payload['EDP_BILL_NO'] ?? null;
        $recAccount = $payload['EDP_REC_ACCOUNT'] ?? null;
        $amount = $payload['EDP_AMOUNT'] ?? null;
        $transId = $payload['EDP_TRANS_ID'] ?? null;
        $checksum = $payload['EDP_CHECKSUM'] ?? null;

        if (! $billNo || ! $recAccount || ! $amount || ! $transId || ! $checksum) {
            return PaymentVerificationResult::failed(
                errorMessage: 'Missing required Idram confirmation parameters',
                merchantReference: $billNo,
                providerTransactionId: $transId,
                rawPayload: $payload
            );
        }

        // Find attempt by merchant reference
        $attempt = PaymentAttempt::where('merchant_reference', $billNo)->first();
        if (! $attempt) {
            return PaymentVerificationResult::failed(
                errorMessage: "Payment attempt not found for reference {$billNo}",
                merchantReference: $billNo,
                providerTransactionId: $transId,
                rawPayload: $payload
            );
        }

        $vendor = $attempt->vendor;
        $settings = $vendor->getPaymentSettings()['gateways']['idram'] ?? [];
        $expectedAccount = $settings['merchant_id'] ?? null;
        $secretKey = $settings['secret_key'] ?? null;

        // Verify merchant account
        if ($expectedAccount && $recAccount !== $expectedAccount) {
            return PaymentVerificationResult::failed(
                errorMessage: 'Idram merchant account mismatch',
                merchantReference: $billNo,
                providerTransactionId: $transId,
                rawPayload: $payload
            );
        }

        // Verify checksum: strtoupper(md5(EDP_REC_ACCOUNT:EDP_AMOUNT:SECRET_KEY:EDP_BILL_NO:EDP_TRANS_ID))
        if (! empty($secretKey)) {
            $formattedAmount = number_format((float) $amount, 2, '.', '');
            $rawString = "{$recAccount}:{$formattedAmount}:{$secretKey}:{$billNo}:{$transId}";
            $expectedChecksum = strtoupper(md5($rawString));

            if (! hash_equals($expectedChecksum, strtoupper((string) $checksum))) {
                return PaymentVerificationResult::failed(
                    errorMessage: 'Idram checksum verification failed',
                    merchantReference: $billNo,
                    providerTransactionId: $transId,
                    rawPayload: $payload
                );
            }
        }

        return PaymentVerificationResult::paid(
            providerTransactionId: (string) $transId,
            amount: (float) $amount,
            currency: 'AMD',
            merchantReference: (string) $billNo,
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
     * Authoritatively query Idram transaction status during reconciliation.
     * Idram uses server-to-server push confirmation; return pending if not yet pushed.
     */
    public function queryTransactionStatus(PaymentAttempt $attempt): PaymentVerificationResult
    {
        return PaymentVerificationResult::pending(
            merchantReference: $attempt->merchant_reference,
            providerTransactionId: $attempt->provider_transaction_id,
            rawPayload: ['note' => 'Idram relies on server-to-server webhook confirmation']
        );
    }
}
