<?php

namespace App\Services\Payments\Gateways;

use App\Models\PaymentAttempt;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\DTOs\PaymentInitiationResult;
use App\Services\Payments\DTOs\PaymentVerificationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeGateway implements PaymentGatewayInterface
{
    public function getId(): string
    {
        return 'stripe';
    }

    public function getName(): string
    {
        return 'Stripe';
    }

    /**
     * Initiate Stripe checkout or payment session.
     *
     * @param  array<string, mixed>  $options
     */
    public function initiatePayment(PaymentAttempt $attempt, array $options = []): PaymentInitiationResult
    {
        $vendor = $attempt->vendor;
        $settings = $vendor->getPaymentSettings()['gateways']['stripe'] ?? [];
        $secretKey = ! empty($settings['secret_key']) ? $settings['secret_key'] : config('services.stripe.secret');

        // Build checkout redirect URL or fallback to internal checkout URL
        $returnUrl = route('client.payment.callback', [
            'vendor_slug' => $vendor->slug,
            'reference' => $attempt->merchant_reference,
        ]);

        if (empty($secretKey)) {
            Log::info("Stripe gateway initiated without live secret key for attempt {$attempt->merchant_reference} (credentials missing).");

            return PaymentInitiationResult::redirect(
                redirectUrl: $returnUrl.'?session_id=mock_stripe_session',
                merchantReference: $attempt->merchant_reference,
                gatewayData: [
                    'provider_status' => 'missing_credentials',
                    'note' => 'Live Stripe requires vendor or system STRIPE_SECRET_KEY.',
                ]
            );
        }

        try {
            // Attempt to create Stripe Checkout Session via Stripe API
            $response = Http::withToken($secretKey)
                ->asForm()
                ->post('https://api.stripe.com/v1/checkout/sessions', [
                    'payment_method_types' => ['card'],
                    'client_reference_id' => $attempt->merchant_reference,
                    'customer_email' => $attempt->order?->customer_email ?? $vendor->email,
                    'line_items' => [
                        [
                            'price_data' => [
                                'currency' => strtolower($attempt->currency),
                                'product_data' => [
                                    'name' => $attempt->order
                                        ? "Order #{$attempt->order->order_number} - {$vendor->name}"
                                        : "Subscription - {$vendor->name}",
                                ],
                                'unit_amount' => (int) round($attempt->amount * 100),
                            ],
                            'quantity' => 1,
                        ],
                    ],
                    'mode' => 'payment',
                    'success_url' => $returnUrl.'?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => $returnUrl.'?cancelled=1',
                    'metadata' => [
                        'payment_attempt_id' => $attempt->id,
                        'merchant_reference' => $attempt->merchant_reference,
                        'vendor_id' => $vendor->id,
                    ],
                ]);

            if ($response->successful()) {
                $session = $response->json();

                return PaymentInitiationResult::redirect(
                    redirectUrl: $session['url'] ?? $returnUrl,
                    merchantReference: $attempt->merchant_reference,
                    providerTransactionId: $session['id'] ?? null,
                    gatewayData: $session
                );
            }

            Log::error('Stripe Checkout session creation failed', ['response' => $response->json()]);

            return PaymentInitiationResult::failure(
                errorMessage: 'Stripe session creation failed: '.($response->json('error.message') ?? 'Unknown error'),
                gatewayData: $response->json() ?? []
            );
        } catch (\Throwable $e) {
            Log::error('Stripe Checkout exception', ['error' => $e->getMessage()]);

            return PaymentInitiationResult::failure($e->getMessage());
        }
    }

    /**
     * Verify payment status on client callback redirect.
     * Never trusts query parameters directly; verifies with Stripe API if session_id is provided.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyCallback(PaymentAttempt $attempt, array $payload): PaymentVerificationResult
    {
        $sessionId = $payload['session_id'] ?? null;
        if (! $sessionId || $sessionId === 'mock_stripe_session') {
            return PaymentVerificationResult::pending(
                merchantReference: $attempt->merchant_reference,
                rawPayload: $payload
            );
        }

        $vendor = $attempt->vendor;
        $settings = $vendor->getPaymentSettings()['gateways']['stripe'] ?? [];
        $secretKey = ! empty($settings['secret_key']) ? $settings['secret_key'] : config('services.stripe.secret');

        if (empty($secretKey)) {
            return PaymentVerificationResult::pending(
                merchantReference: $attempt->merchant_reference,
                rawPayload: $payload
            );
        }

        try {
            $response = Http::withToken($secretKey)
                ->get("https://api.stripe.com/v1/checkout/sessions/{$sessionId}");

            if ($response->successful()) {
                $session = $response->json();
                if (($session['payment_status'] ?? '') === 'paid') {
                    $amount = ($session['amount_total'] ?? 0) / 100;
                    $currency = strtoupper($session['currency'] ?? 'AMD');
                    $providerTxId = $session['payment_intent'] ?? $session['id'];

                    return PaymentVerificationResult::paid(
                        providerTransactionId: $providerTxId,
                        amount: (float) $amount,
                        currency: $currency,
                        merchantReference: $attempt->merchant_reference,
                        rawPayload: $session,
                        signatureValid: true
                    );
                }
            }

            return PaymentVerificationResult::pending(
                merchantReference: $attempt->merchant_reference,
                rawPayload: $payload
            );
        } catch (\Throwable $e) {
            Log::error('Stripe callback verification failed', ['error' => $e->getMessage()]);

            return PaymentVerificationResult::failed($e->getMessage(), $attempt->merchant_reference);
        }
    }

    /**
     * Verify asynchronous Stripe webhook.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function verifyWebhook(array $payload, array $headers = []): PaymentVerificationResult
    {
        $stripeSignature = $headers['stripe-signature'][0] ?? $headers['stripe-signature'] ?? null;
        $webhookSecret = config('services.stripe.webhook_secret') ?? env('STRIPE_WEBHOOK_SECRET');

        // If webhook secret configured, strictly verify HMAC signature
        if ($webhookSecret) {
            if (! $stripeSignature) {
                return PaymentVerificationResult::failed('Missing Stripe webhook signature');
            }

            $parsedSig = $this->parseStripeSignature((string) $stripeSignature);
            if (! $parsedSig['timestamp'] || empty($parsedSig['signatures'])) {
                return PaymentVerificationResult::failed('Invalid Stripe signature format');
            }

            // Check timestamp tolerance (5 minutes replay protection)
            if (abs(time() - (int) $parsedSig['timestamp']) > 300) {
                return PaymentVerificationResult::failed('Stripe webhook signature expired');
            }

            $rawBodyCandidates = [];
            if (! empty($headers['x-raw-body'])) {
                $rawBodyCandidates[] = is_array($headers['x-raw-body']) ? $headers['x-raw-body'][0] : $headers['x-raw-body'];
            }
            if (! empty($payload['_raw_body'])) {
                $rawBodyCandidates[] = $payload['_raw_body'];
            }
            $cleanPayload = $payload;
            unset($cleanPayload['_raw_body']);
            $rawBodyCandidates[] = json_encode($cleanPayload, JSON_UNESCAPED_SLASHES);
            $rawBodyCandidates[] = json_encode($cleanPayload);

            $matched = false;
            foreach ($rawBodyCandidates as $rawCandidate) {
                $signedPayload = "{$parsedSig['timestamp']}.{$rawCandidate}";
                $expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);
                foreach ($parsedSig['signatures'] as $sig) {
                    if (hash_equals($expectedSignature, $sig)) {
                        $matched = true;
                        break 2;
                    }
                }
            }

            if (! $matched) {
                return PaymentVerificationResult::failed('Stripe signature verification failed');
            }
        }

        $eventType = $payload['type'] ?? '';
        $dataObject = $payload['data']['object'] ?? [];
        $merchantReference = $dataObject['client_reference_id']
            ?? $dataObject['metadata']['merchant_reference']
            ?? $dataObject['subscription_details']['metadata']['merchant_reference']
            ?? $dataObject['lines']['data'][0]['metadata']['merchant_reference']
            ?? null;

        if ($eventType === 'checkout.session.completed' || $eventType === 'payment_intent.succeeded' || $eventType === 'invoice.payment_succeeded') {
            $amount = isset($dataObject['amount_total'])
                ? $dataObject['amount_total'] / 100
                : (isset($dataObject['amount_received'])
                    ? $dataObject['amount_received'] / 100
                    : (isset($dataObject['amount_paid']) ? $dataObject['amount_paid'] / 100 : 0));

            $currency = strtoupper($dataObject['currency'] ?? 'AMD');
            $providerTxId = $dataObject['payment_intent'] ?? $dataObject['id'] ?? null;

            if (! $providerTxId) {
                return PaymentVerificationResult::failed('Missing provider transaction ID in Stripe webhook', $merchantReference);
            }

            return PaymentVerificationResult::paid(
                providerTransactionId: (string) $providerTxId,
                amount: (float) $amount,
                currency: $currency,
                merchantReference: (string) $merchantReference,
                rawPayload: $payload,
                signatureValid: true
            );
        }

        if ($eventType === 'payment_intent.payment_failed' || $eventType === 'invoice.payment_failed') {
            return PaymentVerificationResult::failed(
                errorMessage: $dataObject['last_payment_error']['message'] ?? 'Payment failed',
                merchantReference: $merchantReference,
                rawPayload: $payload,
                signatureValid: true
            );
        }

        if ($eventType === 'charge.refunded' || $eventType === 'customer.subscription.deleted') {
            $amount = ($dataObject['amount_refunded'] ?? $dataObject['amount_total'] ?? 0) / 100;
            $currency = strtoupper($dataObject['currency'] ?? 'AMD');
            $providerTxId = $dataObject['payment_intent'] ?? $dataObject['id'] ?? null;

            return PaymentVerificationResult::refunded(
                providerTransactionId: (string) $providerTxId,
                amount: (float) $amount,
                currency: $currency,
                merchantReference: (string) $merchantReference,
                rawPayload: $payload
            );
        }

        return PaymentVerificationResult::failed(
            errorMessage: "Unhandled Stripe webhook event: {$eventType}",
            merchantReference: $merchantReference,
            rawPayload: $payload,
            signatureValid: true
        );
    }

    /**
     * Authoritatively query Stripe API for current transaction status during reconciliation.
     */
    public function queryTransactionStatus(PaymentAttempt $attempt): PaymentVerificationResult
    {
        $vendor = $attempt->vendor;
        $settings = $vendor?->getPaymentSettings()['gateways']['stripe'] ?? [];
        $secretKey = ! empty($settings['secret_key']) ? $settings['secret_key'] : config('services.stripe.secret');

        if (empty($secretKey)) {
            return PaymentVerificationResult::pending(
                merchantReference: $attempt->merchant_reference,
                providerTransactionId: $attempt->provider_transaction_id,
                rawPayload: ['note' => 'Stripe secret key not configured']
            );
        }

        $txId = $attempt->provider_transaction_id;
        if (empty($txId)) {
            return PaymentVerificationResult::pending(
                merchantReference: $attempt->merchant_reference,
                rawPayload: ['note' => 'No provider transaction ID on attempt']
            );
        }

        try {
            $endpoint = str_starts_with($txId, 'cs_')
                ? "https://api.stripe.com/v1/checkout/sessions/{$txId}"
                : "https://api.stripe.com/v1/payment_intents/{$txId}";

            $response = Http::withToken($secretKey)->timeout(5)->get($endpoint);
            if (! $response->successful()) {
                return PaymentVerificationResult::pending(
                    merchantReference: $attempt->merchant_reference,
                    providerTransactionId: $txId,
                    rawPayload: $response->json() ?? []
                );
            }

            $data = $response->json();

            // Checkout session check
            if (str_starts_with($txId, 'cs_')) {
                $paymentStatus = $data['payment_status'] ?? '';
                if ($paymentStatus === 'paid') {
                    $amount = ($data['amount_total'] ?? 0) / 100;
                    $currency = strtoupper($data['currency'] ?? 'AMD');
                    $resolvedTxId = $data['payment_intent'] ?? $data['id'];

                    return PaymentVerificationResult::paid(
                        providerTransactionId: (string) $resolvedTxId,
                        amount: (float) $amount,
                        currency: $currency,
                        merchantReference: $attempt->merchant_reference,
                        rawPayload: $data,
                        signatureValid: true
                    );
                }

                if (($data['status'] ?? '') === 'expired') {
                    return PaymentVerificationResult::failed(
                        errorMessage: 'Stripe checkout session expired',
                        merchantReference: $attempt->merchant_reference,
                        providerTransactionId: $txId,
                        rawPayload: $data,
                        signatureValid: true
                    );
                }

                return PaymentVerificationResult::pending(
                    merchantReference: $attempt->merchant_reference,
                    providerTransactionId: $txId,
                    rawPayload: $data
                );
            }

            // Payment intent check
            $intentStatus = $data['status'] ?? '';
            if ($intentStatus === 'succeeded') {
                $amount = ($data['amount_received'] ?? $data['amount'] ?? 0) / 100;
                $currency = strtoupper($data['currency'] ?? 'AMD');

                return PaymentVerificationResult::paid(
                    providerTransactionId: (string) $data['id'],
                    amount: (float) $amount,
                    currency: $currency,
                    merchantReference: $attempt->merchant_reference,
                    rawPayload: $data,
                    signatureValid: true
                );
            }

            if ($intentStatus === 'canceled') {
                return PaymentVerificationResult::failed(
                    errorMessage: 'Stripe payment intent was canceled',
                    merchantReference: $attempt->merchant_reference,
                    providerTransactionId: $txId,
                    rawPayload: $data,
                    signatureValid: true
                );
            }

            return PaymentVerificationResult::pending(
                merchantReference: $attempt->merchant_reference,
                providerTransactionId: $txId,
                rawPayload: $data
            );
        } catch (\Throwable $e) {
            Log::warning("Stripe queryTransactionStatus exception for {$attempt->merchant_reference}: {$e->getMessage()}");

            return PaymentVerificationResult::pending(
                merchantReference: $attempt->merchant_reference,
                providerTransactionId: $txId,
                rawPayload: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Parse Stripe signature header into timestamp and signatures list.
     *
     * @return array{timestamp: ?string, signatures: list<string>}
     */
    protected function parseStripeSignature(string $header): array
    {
        $items = explode(',', $header);
        $timestamp = null;
        $signatures = [];

        foreach ($items as $item) {
            $parts = explode('=', trim($item), 2);
            if (count($parts) === 2) {
                if ($parts[0] === 't') {
                    $timestamp = $parts[1];
                } elseif ($parts[0] === 'v1') {
                    $signatures[] = $parts[1];
                }
            }
        }

        return [
            'timestamp' => $timestamp,
            'signatures' => $signatures,
        ];
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }

    public function supportsRefunds(): bool
    {
        return true;
    }
}
