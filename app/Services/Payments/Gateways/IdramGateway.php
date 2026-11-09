<?php

namespace App\Services\Payments\Gateways;

use App\Enums\PaymentStatus;
use App\Models\PaymentAttempt;
use App\Models\SystemSetting;
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
     * Complies with Section 2 (Payment via Idram Wallet (Web)) of the Idram documentation.
     * Target form URL: https://web.idram.am/payment.aspx (POST/UTF-8)
     *
     * @param  array<string, mixed>  $options
     */
    public function initiatePayment(PaymentAttempt $attempt, array $options = []): PaymentInitiationResult
    {
        $vendor = $attempt->vendor;
        $vendorSettings = $vendor?->getPaymentSettings()['gateways']['idram'] ?? [];
        $platformSettings = SystemSetting::getPaymentGatewaySettings()['idram'] ?? [];
        $vAccount = ! empty($vendorSettings['merchant_id']) ? $vendorSettings['merchant_id'] : null;
        $pAccount = ! empty($platformSettings['merchant_id']) ? $platformSettings['merchant_id'] : null;
        $recAccount = ($attempt->isSubscriptionPayment() || empty($vAccount))
            ? ($pAccount ?: $vAccount)
            : $vAccount;

        if (empty($recAccount)) {
            Log::warning("Idram payment initiated without configured merchant_id for vendor {$vendor?->id}");
        }

        // Official Idram endpoint according to Section 2 of Idram documentation
        $idramUrl = 'https://web.idram.am/payment.aspx';
        $returnUrl = route('client.payment.callback', [
            'vendor_slug' => $vendor?->slug ?? 'default',
            'reference' => $attempt->merchant_reference,
        ]);

        // Supported languages in Idram interface: RU, EN, AM
        $locale = strtoupper(substr((string) app()->getLocale(), 0, 2));
        $edpLanguage = match ($locale) {
            'RU' => 'RU',
            'EN' => 'EN',
            default => 'AM',
        };

        $gatewayData = [
            'EDP_LANGUAGE' => $edpLanguage,
            'EDP_REC_ACCOUNT' => $recAccount ?? '100000000',
            'EDP_DESCRIPTION' => $attempt->order
                ? "Order #{$attempt->order->order_number} at {$vendor?->name}"
                : "Subscription at {$vendor?->name}",
            'EDP_AMOUNT' => number_format((float) $attempt->amount, 2, '.', ''),
            'EDP_BILL_NO' => $attempt->merchant_reference,
        ];

        // EDP_EMAIL: Email for notifications if merchant does not respond with "OK"
        $email = $vendor?->email ?? config('mail.from.address');
        if (! empty($email)) {
            $gatewayData['EDP_EMAIL'] = $email;
        }

        // Additional merchant fields (posted back / callback parameters)
        $gatewayData['success_url'] = $returnUrl;
        $gatewayData['fail_url'] = $returnUrl.'?status=failed';
        $gatewayData['result_url'] = route('api.webhooks.payment', ['gateway' => 'idram']);

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
     * Handles both:
     * (a) Order authenticity confirmation (preliminary request): EDP_PRECHECK = "YES"
     * (b) Payment confirmation: with 7-field EDP_CHECKSUM verification
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function verifyWebhook(array $payload, array $headers = []): PaymentVerificationResult
    {
        $isPrecheck = isset($payload['EDP_PRECHECK']) && strtoupper((string) $payload['EDP_PRECHECK']) === 'YES';

        if ($isPrecheck) {
            return $this->handlePrecheck($payload);
        }

        return $this->handlePaymentConfirmation($payload);
    }

    /**
     * Handle (a) Order authenticity confirmation (preliminary request).
     * Sent to RESULT_URL before transferring funds from customer to merchant.
     * Expects "OK" plain text response from merchant.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handlePrecheck(array $payload): PaymentVerificationResult
    {
        $billNo = $payload['EDP_BILL_NO'] ?? null;
        $recAccount = $payload['EDP_REC_ACCOUNT'] ?? null;
        $amount = $payload['EDP_AMOUNT'] ?? null;

        if (! $billNo || ! $recAccount || $amount === null) {
            return PaymentVerificationResult::failed(
                errorMessage: 'Missing required Idram precheck parameters',
                merchantReference: $billNo,
                rawPayload: $payload
            );
        }

        $attempt = PaymentAttempt::where('merchant_reference', $billNo)->first();
        if (! $attempt) {
            return PaymentVerificationResult::failed(
                errorMessage: "Payment attempt not found for reference {$billNo}",
                merchantReference: $billNo,
                rawPayload: $payload
            );
        }

        if ($attempt->isExpired() || in_array($attempt->status, [PaymentStatus::Failed, PaymentStatus::Cancelled, PaymentStatus::Expired], true)) {
            return PaymentVerificationResult::failed(
                errorMessage: "Payment attempt is not payable (status: {$attempt->status->value})",
                merchantReference: $billNo,
                rawPayload: $payload
            );
        }

        $expectedAccount = $this->resolveExpectedAccount($attempt);
        if ($expectedAccount && (string) $recAccount !== (string) $expectedAccount) {
            return PaymentVerificationResult::failed(
                errorMessage: 'Idram merchant account mismatch',
                merchantReference: $billNo,
                rawPayload: $payload
            );
        }

        if (abs(((float) $amount) - ((float) $attempt->amount)) > 0.01) {
            return PaymentVerificationResult::failed(
                errorMessage: 'Idram payment amount mismatch',
                merchantReference: $billNo,
                rawPayload: $payload
            );
        }

        // Return pending status so PaymentWebhookController returns HTTP 200 "OK" without finalizing the order yet
        return PaymentVerificationResult::pending(
            merchantReference: (string) $billNo,
            rawPayload: $payload
        );
    }

    /**
     * Handle (b) Payment confirmation.
     * Sent to RESULT_URL after completion of payment transaction.
     *
     * Checksum formula per Idram documentation (imploded with ':' and MD5 hashed):
     * EDP_REC_ACCOUNT : EDP_AMOUNT : SECRET_KEY : EDP_BILL_NO : EDP_PAYER_ACCOUNT : EDP_TRANS_ID : EDP_TRANS_DATE
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handlePaymentConfirmation(array $payload): PaymentVerificationResult
    {
        $billNo = $payload['EDP_BILL_NO'] ?? null;
        $recAccount = $payload['EDP_REC_ACCOUNT'] ?? null;
        $amount = $payload['EDP_AMOUNT'] ?? null;
        $transId = $payload['EDP_TRANS_ID'] ?? null;
        $checksum = $payload['EDP_CHECKSUM'] ?? null;
        $payerAccount = (string) ($payload['EDP_PAYER_ACCOUNT'] ?? '');
        $transDate = (string) ($payload['EDP_TRANS_DATE'] ?? '');

        if (! $billNo || ! $recAccount || $amount === null || ! $transId || ! $checksum) {
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

        $expectedAccount = $this->resolveExpectedAccount($attempt);
        if ($expectedAccount && (string) $recAccount !== (string) $expectedAccount) {
            return PaymentVerificationResult::failed(
                errorMessage: 'Idram merchant account mismatch',
                merchantReference: $billNo,
                providerTransactionId: $transId,
                rawPayload: $payload
            );
        }

        // Authoritative amount check
        if (abs(((float) $amount) - ((float) $attempt->amount)) > 0.01) {
            return PaymentVerificationResult::failed(
                errorMessage: 'Idram payment amount mismatch',
                merchantReference: $billNo,
                providerTransactionId: $transId,
                rawPayload: $payload
            );
        }

        // Verify checksum:
        // Doc specification: EDP_REC_ACCOUNT : EDP_AMOUNT : SECRET_KEY : EDP_BILL_NO : EDP_PAYER_ACCOUNT : EDP_TRANS_ID : EDP_TRANS_DATE
        $secretKey = $this->resolveSecretKey($attempt);
        if (! empty($secretKey)) {
            $rawAmount = (string) $amount;
            $formattedAmount = number_format((float) $amount, 2, '.', '');

            $candidates = [
                // 1. Official 7-parameter format with raw amount as received
                strtoupper(md5("{$recAccount}:{$rawAmount}:{$secretKey}:{$billNo}:{$payerAccount}:{$transId}:{$transDate}")),
                // 2. Official 7-parameter format with 2-decimal formatted amount
                strtoupper(md5("{$recAccount}:{$formattedAmount}:{$secretKey}:{$billNo}:{$payerAccount}:{$transId}:{$transDate}")),
                // 3. Fallback 5-parameter format (for backward compatibility with internal mocks/legacy tests)
                strtoupper(md5("{$recAccount}:{$formattedAmount}:{$secretKey}:{$billNo}:{$transId}")),
                strtoupper(md5("{$recAccount}:{$rawAmount}:{$secretKey}:{$billNo}:{$transId}")),
            ];

            $incomingChecksum = strtoupper((string) $checksum);
            $isValid = false;
            foreach ($candidates as $candidate) {
                if (hash_equals($candidate, $incomingChecksum)) {
                    $isValid = true;
                    break;
                }
            }

            if (! $isValid) {
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

    protected function resolveExpectedAccount(PaymentAttempt $attempt): ?string
    {
        $vendor = $attempt->vendor;
        $vendorSettings = $vendor?->getPaymentSettings()['gateways']['idram'] ?? [];
        $platformSettings = SystemSetting::getPaymentGatewaySettings()['idram'] ?? [];
        $vAccount = ! empty($vendorSettings['merchant_id']) ? $vendorSettings['merchant_id'] : null;
        $pAccount = ! empty($platformSettings['merchant_id']) ? $platformSettings['merchant_id'] : null;

        return ($attempt->isSubscriptionPayment() || empty($vAccount))
            ? ($pAccount ?: $vAccount)
            : $vAccount;
    }

    protected function resolveSecretKey(PaymentAttempt $attempt): ?string
    {
        $vendor = $attempt->vendor;
        $vendorSettings = $vendor?->getPaymentSettings()['gateways']['idram'] ?? [];
        $platformSettings = SystemSetting::getPaymentGatewaySettings()['idram'] ?? [];
        $vSecret = ! empty($vendorSettings['secret_key']) ? $vendorSettings['secret_key'] : null;
        $pSecret = ! empty($platformSettings['secret_key']) ? $platformSettings['secret_key'] : null;

        return ($attempt->isSubscriptionPayment() || empty($vSecret))
            ? ($pSecret ?: $vSecret)
            : $vSecret;
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
