<?php

namespace App\Domains\Loyalty\Services;

use App\Domains\Loyalty\Events\WalletPassIssued;
use App\Domains\Loyalty\Events\WalletPassUpdated;
use App\Domains\Loyalty\Models\WalletIssuer;
use App\Domains\Loyalty\Models\WalletPass;
use App\Domains\Loyalty\Models\WalletPassDesign;
use App\Domains\Loyalty\Wallet\AppleWalletService;
use App\Domains\Loyalty\Wallet\GoogleWalletService;
use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Support\Str;

class WalletService
{
    public function __construct(
        protected AppleWalletService $appleWalletService,
        protected GoogleWalletService $googleWalletService,
    ) {}

    /**
     * Get or issue a wallet pass for a customer.
     */
    public function getOrIssuePass(Customer $customer): WalletPass
    {
        $pass = WalletPass::where('vendor_id', $customer->vendor_id)
            ->where('customer_id', $customer->id)
            ->first();

        if (! $pass) {
            $issuer = WalletIssuer::where('vendor_id', $customer->vendor_id)->first();
            $passType = $issuer?->apple_pass_type_id ?: config('loyalty.apple_pass_type_id', 'pass.am.elab.menu.loyalty');

            $pass = WalletPass::create([
                'uuid' => (string) Str::uuid(),
                'vendor_id' => $customer->vendor_id,
                'customer_id' => $customer->id,
                'serial_number' => 'PASS-'.strtoupper(Str::random(12)),
                'authentication_token' => bin2hex(random_bytes(16)),
                'pass_type_identifier' => $passType,
                'status' => 'active',
            ]);

            event(new WalletPassIssued($pass));
        }

        return $pass;
    }

    /**
     * Get or create default wallet pass design for a vendor.
     */
    public function getOrCreateDesign(Vendor $vendor): WalletPassDesign
    {
        return WalletPassDesign::firstOrCreate(
            ['vendor_id' => $vendor->id],
            [
                'background_color' => '#1e293b',
                'foreground_color' => '#ffffff',
                'label_color' => '#94a3b8',
                'card_title' => "{$vendor->name} Member Pass",
                'header_field' => 'points',
                'primary_field' => 'customer_name',
                'barcode_type' => 'PKBarcodeFormatQR',
                'is_published' => true,
                'version' => 1,
            ]
        );
    }

    /**
     * Get or create default wallet issuer config for a vendor.
     */
    public function getOrCreateIssuer(Vendor $vendor): WalletIssuer
    {
        return WalletIssuer::firstOrCreate(
            ['vendor_id' => $vendor->id],
            [
                'issuer_mode' => 'platform',
                'apple_pass_type_id' => config('loyalty.apple_pass_type_id', 'pass.am.elab.menu.loyalty'),
                'apple_team_id' => config('loyalty.apple_team_id', '9X8K43L21A'),
                'apple_status' => 'active',
                'google_issuer_id' => config('loyalty.google_issuer_id', '3388000000022345678'),
                'google_status' => 'active',
            ]
        );
    }

    /**
     * Generate .pkpass binary content for Apple Wallet.
     */
    public function getAppleWalletPassBinary(Customer $customer): string
    {
        $pass = $this->getOrIssuePass($customer);

        return $this->appleWalletService->generatePkPass($pass);
    }

    /**
     * Generate Google Wallet save URL.
     */
    public function getGoogleWalletSaveUrl(Customer $customer): string
    {
        $pass = $this->getOrIssuePass($customer);

        return $this->googleWalletService->generateSaveUrl($pass);
    }

    /**
     * Synchronize wallet pass updates across active devices.
     */
    public function syncPassUpdates(Customer $customer): void
    {
        $pass = WalletPass::where('vendor_id', $customer->vendor_id)
            ->where('customer_id', $customer->id)
            ->first();

        if ($pass) {
            $this->appleWalletService->pushPassUpdate($pass);
            $this->googleWalletService->updateGoogleWalletObject($pass);
            event(new WalletPassUpdated($pass));
        }
    }
}
