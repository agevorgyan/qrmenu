<?php

namespace App\Domains\Loyalty\Http\Controllers\Customer;

use App\Domains\Loyalty\DTOs\RedemptionRequestDTO;
use App\Domains\Loyalty\Models\LoyaltyProgram;
use App\Domains\Loyalty\Models\LoyaltyReward;
use App\Domains\Loyalty\Models\LoyaltyStampCard;
use App\Domains\Loyalty\Models\LoyaltyTier;
use App\Domains\Loyalty\Services\CustomerLoyaltyService;
use App\Domains\Loyalty\Services\ReferralService;
use App\Domains\Loyalty\Services\RewardsService;
use App\Domains\Loyalty\Services\WalletService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerLoyaltyPortalController extends Controller
{
    public function __construct(
        protected CustomerLoyaltyService $customerLoyaltyService,
        protected RewardsService $rewardsService,
        protected ReferralService $referralService,
        protected WalletService $walletService,
    ) {}

    /**
     * Show responsive, multilingual customer loyalty portal.
     */
    public function index(Request $request, string $vendor_slug): View|RedirectResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->firstOrFail();
        $customer = $this->resolveAuthenticatedCustomer($vendor);

        if (! $customer) {
            return redirect()->route('client.loyalty.login', ['vendor_slug' => $vendor->slug]);
        }

        $program = LoyaltyProgram::firstOrCreate(
            ['vendor_id' => $vendor->id],
            ['name' => "{$vendor->name} Loyalty Club"]
        );

        $overview = $this->customerLoyaltyService->getCustomerLoyaltyOverview($customer);

        $rewards = LoyaltyReward::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->orderBy('points_cost', 'asc')
            ->get();

        $stampCards = LoyaltyStampCard::where('vendor_id', $vendor->id)
            ->where('customer_id', $customer->id)
            ->with('program')
            ->latest()
            ->get();

        $tiers = LoyaltyTier::where('vendor_id', $vendor->id)->orderBy('level', 'asc')->get();
        $referralCode = $this->referralService->getCustomerReferralCode($customer);

        $pass = $this->walletService->getOrIssuePass($customer);
        $walletDesign = $this->walletService->getOrCreateDesign($vendor);

        $recentTransactions = $customer->pointsAccount?->transactions()->take(15)->get() ?? [];

        return view('storefront.loyalty.portal', compact(
            'vendor',
            'customer',
            'program',
            'overview',
            'rewards',
            'stampCards',
            'tiers',
            'referralCode',
            'pass',
            'walletDesign',
            'recentTransactions'
        ));
    }

    /**
     * Customer claim/redeem reward from portal.
     */
    public function claimReward(Request $request, string $vendor_slug, LoyaltyReward $reward): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->firstOrFail();
        $customer = $this->resolveAuthenticatedCustomer($vendor);

        if (! $customer) {
            return response()->json(['error' => 'Authentication required.'], 401);
        }

        if ((int) $reward->vendor_id !== (int) $vendor->id) {
            return response()->json(['error' => 'Reward belongs to another vendor.'], 403);
        }

        try {
            $redemption = $this->rewardsService->redeemReward(new RedemptionRequestDTO(
                vendorId: $vendor->id,
                customerId: $customer->id,
                rewardId: $reward->id,
            ));

            return response()->json([
                'success' => true,
                'message' => 'Reward redeemed successfully! Present the code to staff.',
                'code' => $redemption->redemption_code,
                'expires_at' => $redemption->expires_at->toIso8601String(),
                'new_balance' => (int) $customer->fresh()->loyalty_points,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Submit referral code during or after enrollment.
     */
    public function applyReferral(Request $request, string $vendor_slug): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->firstOrFail();
        $customer = $this->resolveAuthenticatedCustomer($vendor);

        if (! $customer) {
            return response()->json(['error' => 'Authentication required.'], 401);
        }

        $request->validate([
            'referral_code' => 'required|string',
        ]);

        try {
            $referral = $this->referralService->registerReferral($customer, $request->input('referral_code'));

            return response()->json([
                'success' => true,
                'message' => 'Referral code applied! You will receive bonus points after your first order.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Resolve the currently logged in customer from session for the vendor.
     */
    protected function resolveAuthenticatedCustomer(Vendor $vendor): ?Customer
    {
        $customerId = session('loyalty_customer_id');
        $sessionVendorId = session('loyalty_vendor_id');

        if (! $customerId || (int) $sessionVendorId !== (int) $vendor->id) {
            return null;
        }

        return Customer::where('vendor_id', $vendor->id)->where('id', $customerId)->first();
    }
}
