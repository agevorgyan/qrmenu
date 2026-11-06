<?php

namespace App\Domains\Loyalty\Http\Controllers\Customer;

use App\Domains\Loyalty\Services\CustomerLoyaltyService;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerLoyaltyAuthController extends Controller
{
    public function __construct(
        protected CustomerLoyaltyService $customerLoyaltyService
    ) {}

    /**
     * Show customer loyalty login / enrollment page on storefront.
     */
    public function showLogin(Request $request, string $vendor_slug): View
    {
        $vendor = Vendor::where('slug', $vendor_slug)->firstOrFail();

        return view('storefront.loyalty.login', compact('vendor'));
    }

    /**
     * Send OTP verification code to customer phone or email.
     */
    public function sendOtp(Request $request, string $vendor_slug): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->firstOrFail();

        $request->validate([
            'identifier' => 'required|string|min:4|max:100',
        ]);

        $result = $this->customerLoyaltyService->sendOtp($vendor, $request->input('identifier'));

        return response()->json($result);
    }

    /**
     * Verify OTP and log in / enroll customer.
     */
    public function verifyOtp(Request $request, string $vendor_slug): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->firstOrFail();

        $request->validate([
            'identifier' => 'required|string',
            'code' => 'required|string|size:6',
            'name' => 'nullable|string|max:100',
        ]);

        $customer = $this->customerLoyaltyService->verifyOtp(
            $vendor,
            $request->input('identifier'),
            $request->input('code'),
            $request->input('name')
        );

        session([
            'loyalty_customer_id' => $customer->id,
            'loyalty_vendor_id' => $vendor->id,
        ]);

        return response()->json([
            'success' => true,
            'redirect_url' => route('client.loyalty.portal', ['vendor_slug' => $vendor->slug]),
        ]);
    }

    /**
     * Log out customer from loyalty portal.
     */
    public function logout(Request $request, string $vendor_slug): RedirectResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->firstOrFail();
        session()->forget(['loyalty_customer_id', 'loyalty_vendor_id']);

        return redirect()->route('client.loyalty.login', ['vendor_slug' => $vendor->slug]);
    }
}
