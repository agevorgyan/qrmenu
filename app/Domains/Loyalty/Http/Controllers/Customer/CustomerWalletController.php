<?php

namespace App\Domains\Loyalty\Http\Controllers\Customer;

use App\Domains\Loyalty\Services\WalletService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerWalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    /**
     * Download signed Apple Wallet .pkpass file.
     */
    public function downloadApplePass(Request $request, string $vendor_slug): Response|RedirectResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->firstOrFail();
        $customer = $this->resolveCustomer($vendor);

        if (! $customer) {
            return redirect()->route('client.loyalty.login', ['vendor_slug' => $vendor->slug]);
        }

        $pkpassBinary = $this->walletService->getAppleWalletPassBinary($customer);

        return response($pkpassBinary, 200, [
            'Content-Type' => 'application/vnd.apple.pkpass',
            'Content-Disposition' => "attachment; filename=\"{$vendor->slug}_card.pkpass\"",
        ]);
    }

    /**
     * Redirect customer to Add to Google Wallet signed URL.
     */
    public function addToGoogleWallet(Request $request, string $vendor_slug): RedirectResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->firstOrFail();
        $customer = $this->resolveCustomer($vendor);

        if (! $customer) {
            return redirect()->route('client.loyalty.login', ['vendor_slug' => $vendor->slug]);
        }

        $googleUrl = $this->walletService->getGoogleWalletSaveUrl($customer);

        return redirect()->away($googleUrl);
    }

    protected function resolveCustomer(Vendor $vendor): ?Customer
    {
        $customerId = session('loyalty_customer_id');
        $sessionVendorId = session('loyalty_vendor_id');

        if (! $customerId || (int) $sessionVendorId !== (int) $vendor->id) {
            return null;
        }

        return Customer::where('vendor_id', $vendor->id)->where('id', $customerId)->first();
    }
}
