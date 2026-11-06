<?php

namespace App\Domains\Loyalty\Http\Controllers\Staff;

use App\Domains\Loyalty\Actions\GenerateCustomerQrTokenAction;
use App\Domains\Loyalty\DTOs\PointsTransactionDTO;
use App\Domains\Loyalty\DTOs\StampAwardDTO;
use App\Domains\Loyalty\Enums\LoyaltyTransactionType;
use App\Domains\Loyalty\Models\LoyaltyAuditLog;
use App\Domains\Loyalty\Models\LoyaltyProgram;
use App\Domains\Loyalty\Models\LoyaltyStampCard;
use App\Domains\Loyalty\Models\LoyaltyStampProgram;
use App\Domains\Loyalty\Services\CustomerLoyaltyService;
use App\Domains\Loyalty\Services\PointsLedgerService;
use App\Domains\Loyalty\Services\RewardsService;
use App\Domains\Loyalty\Services\StampCardService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Security\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StaffLoyaltyOperationsController extends Controller
{
    public function __construct(
        protected CustomerLoyaltyService $customerLoyaltyService,
        protected PointsLedgerService $pointsLedgerService,
        protected RewardsService $rewardsService,
        protected StampCardService $stampCardService,
        protected GenerateCustomerQrTokenAction $qrTokenAction,
    ) {}

    /**
     * Show responsive staff loyalty scanner & operations dashboard.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $this->authorizeStaffAccess($user);

        $vendor = $user->vendor;
        $program = LoyaltyProgram::firstOrCreate(
            ['vendor_id' => $vendor->id],
            ['name' => "{$vendor->name} Loyalty Club"]
        );

        $stampPrograms = LoyaltyStampProgram::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->get();

        return view('admin.loyalty.staff', compact('vendor', 'program', 'stampPrograms'));
    }

    /**
     * Look up customer by phone, email, member code, or ephemeral QR token.
     */
    public function lookup(Request $request): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeStaffAccess($user);
        $vendor = $user->vendor;

        $query = trim((string) $request->input('query'));
        if (empty($query)) {
            return response()->json(['error' => 'Search query is required.'], 422);
        }

        $customer = null;

        // 1. Check if query is an ephemeral QR token (starts with lq_)
        if (str_starts_with($query, 'lq_')) {
            $customer = $this->qrTokenAction->resolve($vendor->id, $query);
        }

        // 2. Check if query is member card format MEM-00001234
        if (! $customer && preg_match('/^MEM-0*(\d+)$/', $query, $matches)) {
            $customerId = (int) $matches[1];
            $customer = Customer::where('vendor_id', $vendor->id)->where('id', $customerId)->first();
        }

        // 3. Fallback to phone or email lookup
        if (! $customer) {
            $cleanPhone = preg_replace('/[^\d+]/', '', $query);
            $cleanEmail = strtolower($query);

            $customer = Customer::where('vendor_id', $vendor->id)
                ->where(function ($q) use ($cleanPhone, $cleanEmail) {
                    if ($cleanPhone) {
                        $q->where('phone', 'like', "%{$cleanPhone}%");
                    }
                    if (filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
                        $q->orWhere('email', $cleanEmail);
                    }
                })
                ->first();
        }

        if (! $customer) {
            return response()->json(['error' => 'Customer not found.'], 404);
        }

        $overview = $this->customerLoyaltyService->getCustomerLoyaltyOverview($customer);
        $recentTransactions = $customer->pointsAccount?->transactions()->take(10)->get() ?? [];

        return response()->json([
            'success' => true,
            'overview' => $overview,
            'recent_transactions' => $recentTransactions,
        ]);
    }

    /**
     * Award loyalty points to a customer manually or for an offline transaction.
     */
    public function issuePoints(Request $request): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeStaffAccess($user);
        $vendor = $user->vendor;

        $request->validate([
            'customer_id' => 'required|integer',
            'points' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:255',
        ]);

        $customer = Customer::where('vendor_id', $vendor->id)->where('id', $request->input('customer_id'))->firstOrFail();

        $tx = $this->pointsLedgerService->creditPoints(new PointsTransactionDTO(
            vendorId: $vendor->id,
            customerId: $customer->id,
            points: (int) $request->input('points'),
            type: LoyaltyTransactionType::Earned,
            staffId: $user->id,
            notes: $request->input('notes') ?: 'Issued by staff',
        ));

        LoyaltyAuditLog::create([
            'vendor_id' => $vendor->id,
            'staff_id' => $user->id,
            'customer_id' => $customer->id,
            'action' => 'points_issued',
            'details' => ['points' => (int) $request->input('points'), 'tx_id' => $tx->id],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Points successfully issued.',
            'new_balance' => $tx->balance_after,
        ]);
    }

    /**
     * Redeem customer loyalty points as an order discount.
     */
    public function redeemPoints(Request $request): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeStaffAccess($user);
        $vendor = $user->vendor;

        $request->validate([
            'customer_id' => 'required|integer',
            'points' => 'required|integer|min:1',
            'order_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:255',
        ]);

        $customer = Customer::where('vendor_id', $vendor->id)->where('id', $request->input('customer_id'))->firstOrFail();

        $tx = $this->pointsLedgerService->debitPoints(new PointsTransactionDTO(
            vendorId: $vendor->id,
            customerId: $customer->id,
            points: (int) $request->input('points'),
            type: LoyaltyTransactionType::Redeemed,
            orderId: $request->input('order_id'),
            staffId: $user->id,
            notes: $request->input('notes') ?: 'Redeemed by staff at POS/counter',
        ));

        LoyaltyAuditLog::create([
            'vendor_id' => $vendor->id,
            'staff_id' => $user->id,
            'customer_id' => $customer->id,
            'action' => 'points_redeemed',
            'details' => ['points' => (int) $request->input('points'), 'tx_id' => $tx->id],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Points successfully redeemed.',
            'new_balance' => $tx->balance_after,
        ]);
    }

    /**
     * Redeem a reward voucher code presented by a customer.
     */
    public function redeemRewardVoucher(Request $request): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeStaffAccess($user);
        $vendor = $user->vendor;

        $request->validate([
            'code' => 'required|string',
        ]);

        $redemption = $this->rewardsService->useRedemptionCode(
            $vendor->id,
            $request->input('code'),
            $user->id
        );

        LoyaltyAuditLog::create([
            'vendor_id' => $vendor->id,
            'staff_id' => $user->id,
            'customer_id' => $redemption->customer_id,
            'action' => 'reward_redeemed',
            'details' => ['code' => $redemption->redemption_code, 'reward_id' => $redemption->reward_id],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reward voucher successfully validated and redeemed.',
            'reward_title' => $redemption->reward->title,
        ]);
    }

    /**
     * Award a stamp to customer's active stamp card.
     */
    public function issueStamp(Request $request): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeStaffAccess($user);
        $vendor = $user->vendor;

        $request->validate([
            'customer_id' => 'required|integer',
            'program_id' => 'required|integer',
            'stamps' => 'nullable|integer|min:1',
        ]);

        $card = $this->stampCardService->awardStamps(new StampAwardDTO(
            vendorId: $vendor->id,
            customerId: (int) $request->input('customer_id'),
            programId: (int) $request->input('program_id'),
            stamps: (int) $request->input('stamps', 1),
            staffId: $user->id,
            notes: 'Stamp awarded by staff'
        ));

        return response()->json([
            'success' => true,
            'message' => 'Stamp awarded successfully.',
            'stamps_collected' => $card->stamps_collected,
            'stamps_required' => $card->stamps_required,
            'status' => $card->status->value,
        ]);
    }

    /**
     * Redeem completed stamp card.
     */
    public function redeemStampCard(Request $request, LoyaltyStampCard $card): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeStaffAccess($user);

        if ((int) $card->vendor_id !== (int) $user->vendor_id) {
            abort(403);
        }

        $redeemedCard = $this->stampCardService->redeemStampCard($card, $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Stamp card reward redeemed.',
            'status' => $redeemedCard->status->value,
        ]);
    }

    /**
     * Verify staff has permission to access loyalty operations.
     */
    protected function authorizeStaffAccess($user): void
    {
        if (! $user) {
            abort(401);
        }

        if ($user->role === 'superadmin' || $user->role === 'owner' || $user->role === 'vendor_owner') {
            return;
        }

        if ($user->hasPermission(Permission::LOYALTY_OPERATE) ||
            $user->hasPermission(Permission::LOYALTY_MANAGE) ||
            $user->hasPermission('customers.manage') ||
            $user->hasPermission(Permission::POS_ACCESS)) {
            return;
        }

        abort(403, 'Unauthorized loyalty operations access.');
    }
}
