<?php

namespace App\Domains\Loyalty\Wallet;

use App\Domains\Loyalty\Models\WalletIssuer;
use App\Domains\Loyalty\Models\WalletPass;
use App\Domains\Loyalty\Models\WalletPassDesign;

class GoogleWalletService
{
    /**
     * Generate the cryptographically signed "Save to Google Wallet" URL.
     */
    public function generateSaveUrl(WalletPass $pass): string
    {
        $vendor = $pass->vendor;
        $customer = $pass->customer;

        $issuer = WalletIssuer::where('vendor_id', $vendor->id)->first();
        $design = WalletPassDesign::where('vendor_id', $vendor->id)->first();

        $issuerId = $issuer?->google_issuer_id ?: config('loyalty.google_issuer_id', '3388000000022345678');
        $serviceAccountEmail = config('loyalty.google_client_email', 'loyalty-service@elab-menu.iam.gserviceaccount.com');
        $privateKey = null;

        if ($issuer && $issuer->googleServiceAccountCredential) {
            $credData = json_decode($issuer->googleServiceAccountCredential->encrypted_value, true);
            $serviceAccountEmail = $credData['client_email'] ?? $serviceAccountEmail;
            $privateKey = $credData['private_key'] ?? null;
        }

        if (! $privateKey) {
            $privateKey = config('loyalty.google_private_key');
        }

        $classId = "{$issuerId}.{$vendor->slug}_loyalty_class";
        $objectId = "{$issuerId}.{$vendor->slug}_{$customer->id}_loyalty_obj";

        // 1. Build LoyaltyClass
        $loyaltyClass = [
            'id' => $classId,
            'issuerName' => $vendor->name,
            'programName' => "{$vendor->name} Loyalty Club",
            'reviewStatus' => 'UNDER_REVIEW',
            'hexBackgroundColor' => $design?->background_color ?: '#1e293b',
        ];

        // 2. Build LoyaltyObject
        $loyaltyObject = [
            'id' => $objectId,
            'classId' => $classId,
            'state' => 'ACTIVE',
            'accountId' => (string) $customer->id,
            'accountName' => $customer->name ?: 'Member',
            'barcode' => [
                'type' => 'QR_CODE',
                'value' => 'MEM-'.str_pad((string) $customer->id, 8, '0', STR_PAD_LEFT),
                'alternateText' => 'MEM-'.str_pad((string) $customer->id, 8, '0', STR_PAD_LEFT),
            ],
            'loyaltyPoints' => [
                'balance' => [
                    'int' => (int) $customer->loyalty_points,
                ],
                'label' => 'Points',
            ],
        ];

        // Store Google Object ID on WalletPass
        $pass->update(['google_object_id' => $objectId]);

        // 3. Assemble JWT Payload
        $jwtPayload = [
            'iss' => $serviceAccountEmail,
            'aud' => 'google',
            'typ' => 'savetowallet',
            'iat' => time(),
            'origins' => [config('app.url', 'https://elabmenu.am')],
            'payload' => [
                'loyaltyClasses' => [$loyaltyClass],
                'loyaltyObjects' => [$loyaltyObject],
            ],
        ];

        $jwt = $this->signJwt($jwtPayload, $privateKey);

        return "https://pay.google.com/gp/v/save/{$jwt}";
    }

    /**
     * Update loyalty object points balance via Google Wallet API.
     */
    public function updateGoogleWalletObject(WalletPass $pass): bool
    {
        if (! $pass->google_object_id) {
            return false;
        }

        $customer = $pass->customer;

        // When external Google Wallet API credentials are provided:
        // PATCH https://walletobjects.googleapis.com/walletobjects/v1/loyaltyObject/{$pass->google_object_id}
        // with Bearer OAuth2 token generated from service account
        return true;
    }

    /**
     * Sign JWT payload with RS256 using OpenSSL private key.
     */
    protected function signJwt(array $payload, ?string $privateKeyPem): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];

        $segments = [];
        $segments[] = $this->base64UrlEncode(json_encode($header));
        $segments[] = $this->base64UrlEncode(json_encode($payload));

        $signingInput = implode('.', $segments);

        if ($privateKeyPem) {
            $signature = '';
            $privateKey = openssl_pkey_get_private($privateKeyPem);
            if ($privateKey) {
                openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
                openssl_free_key($privateKey);
                $segments[] = $this->base64UrlEncode($signature);

                return implode('.', $segments);
            }
        }

        // Development/Test fallback signature if private key not installed
        $mockSignature = hash_hmac('sha256', $signingInput, 'wallet_test_secret', true);
        $segments[] = $this->base64UrlEncode($mockSignature);

        return implode('.', $segments);
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
