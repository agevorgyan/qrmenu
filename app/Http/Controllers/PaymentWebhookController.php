<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Services\AuditSanitizer;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    /**
     * Handle incoming server-to-server webhook from payment providers.
     */
    public function handleWebhook(
        Request $request,
        string $gateway,
        PaymentGatewayManager $gatewayManager,
        PaymentVerificationService $verificationService,
        AuditSanitizer $sanitizer
    ): Response|JsonResponse {
        if (! $gatewayManager->hasGateway($gateway)) {
            return response()->json(['error' => "Unknown gateway: {$gateway}"], 404);
        }

        $payload = $request->all();
        $headers = $request->headers->all();
        $rawBody = $request->getContent();
        if (! empty($rawBody)) {
            $headers['x-raw-body'] = $rawBody;
        }

        Log::info("Payment webhook received for gateway: {$gateway}", [
            'payload' => $sanitizer->sanitize($payload),
        ]);

        try {
            $result = $verificationService->verifyAndFinalizeWebhook(
                gatewayName: $gateway,
                payload: $payload,
                headers: $headers
            );

            if (! $result->success && $result->status === PaymentStatus::Failed) {
                Log::warning("Payment webhook verification failed for {$gateway}: {$result->errorMessage}");

                return response()->json([
                    'error' => $result->errorMessage,
                ], 400);
            }

            // Return provider-specific acknowledgment
            if ($gateway === 'idram') {
                return response('OK', 200)->header('Content-Type', 'text/plain');
            }

            return response()->json([
                'status' => 'success',
                'received' => true,
                'merchant_reference' => $result->merchantReference,
            ], 200);
        } catch (\Throwable $e) {
            Log::error("Payment webhook error for {$gateway}: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Webhook processing failed',
            ], 500);
        }
    }
}
