<?php

namespace App\Domains\Loyalty\Http\Controllers\Admin;

use App\Domains\Loyalty\Enums\WalletIssuerMode;
use App\Domains\Loyalty\Services\WalletService;
use App\Http\Controllers\Controller;
use App\Models\VendorCredential;
use App\Security\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WalletSettingsController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    /**
     * Show Apple & Google Wallet integration settings.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $vendor = $user->vendor;

        $issuer = $this->walletService->getOrCreateIssuer($vendor);

        return view('admin.loyalty.wallet_settings', compact('vendor', 'issuer'));
    }

    /**
     * Update issuer credentials and certificate configuration.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $vendor = $user->vendor;

        $request->validate([
            'issuer_mode' => 'required|in:platform,custom',
            'apple_pass_type_id' => 'nullable|string|max:150',
            'apple_team_id' => 'nullable|string|max:50',
            'apple_cert_file' => 'nullable|file|max:2048',
            'apple_key_file' => 'nullable|file|max:2048',
            'google_issuer_id' => 'nullable|string|max:100',
            'google_service_account_file' => 'nullable|file|max:2048',
        ]);

        $issuer = $this->walletService->getOrCreateIssuer($vendor);

        $data = [
            'issuer_mode' => WalletIssuerMode::from($request->input('issuer_mode')),
            'apple_pass_type_id' => $request->input('apple_pass_type_id'),
            'apple_team_id' => $request->input('apple_team_id'),
            'google_issuer_id' => $request->input('google_issuer_id'),
        ];

        // Apple Certificate Upload
        if ($request->hasFile('apple_cert_file')) {
            $certContent = file_get_contents($request->file('apple_cert_file')->getRealPath());
            $certParsed = openssl_x509_parse($certContent);
            $expiresAt = isset($certParsed['validTo_time_t']) ? date('Y-m-d H:i:s', $certParsed['validTo_time_t']) : null;

            $certCred = VendorCredential::create([
                'vendor_id' => $vendor->id,
                'provider' => 'apple_wallet',
                'credential_type' => 'pass_certificate',
                'encrypted_value' => $certContent,
            ]);

            $data['apple_cert_credential_id'] = $certCred->id;
            $data['apple_cert_expires_at'] = $expiresAt;
            $data['apple_status'] = 'active';
        }

        // Apple Private Key Upload
        if ($request->hasFile('apple_key_file')) {
            $keyContent = file_get_contents($request->file('apple_key_file')->getRealPath());

            $keyCred = VendorCredential::create([
                'vendor_id' => $vendor->id,
                'provider' => 'apple_wallet',
                'credential_type' => 'private_key',
                'encrypted_value' => $keyContent,
            ]);

            $data['apple_key_credential_id'] = $keyCred->id;
        }

        // Google Service Account JSON Upload
        if ($request->hasFile('google_service_account_file')) {
            $jsonContent = file_get_contents($request->file('google_service_account_file')->getRealPath());

            $saCred = VendorCredential::create([
                'vendor_id' => $vendor->id,
                'provider' => 'google_wallet',
                'credential_type' => 'service_account',
                'encrypted_value' => $jsonContent,
            ]);

            $data['google_service_account_credential_id'] = $saCred->id;
            $data['google_status'] = 'active';
        }

        $issuer->update($data);

        return redirect()->route('admin.loyalty.wallet_settings')->with('success', 'Digital wallet integration settings updated.');
    }

    protected function authorizeManage($user): void
    {
        if (! $user) {
            abort(401);
        }
        if ($user->role === 'superadmin' || $user->role === 'owner' || $user->role === 'vendor_owner') {
            return;
        }
        if ($user->hasPermission(Permission::LOYALTY_MANAGE)) {
            return;
        }
        abort(403);
    }
}
