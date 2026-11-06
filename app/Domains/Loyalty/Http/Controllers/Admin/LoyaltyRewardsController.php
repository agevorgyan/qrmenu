<?php

namespace App\Domains\Loyalty\Http\Controllers\Admin;

use App\Domains\Loyalty\Enums\RewardType;
use App\Domains\Loyalty\Models\LoyaltyReward;
use App\Domains\Loyalty\Models\LoyaltyTier;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Security\Permission;
use App\Services\StorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoyaltyRewardsController extends Controller
{
    public function __construct(
        protected StorageService $storageService
    ) {}

    /**
     * List rewards catalog.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $vendor = $user->vendor;

        $rewards = LoyaltyReward::where('vendor_id', $vendor->id)
            ->with(['product', 'requiredTier'])
            ->latest()
            ->paginate(15);

        $tiers = LoyaltyTier::where('vendor_id', $vendor->id)->orderBy('level', 'asc')->get();
        $products = Product::where('vendor_id', $vendor->id)->where('is_available', true)->get();

        return view('admin.loyalty.rewards', compact('vendor', 'rewards', 'tiers', 'products'));
    }

    /**
     * Store new reward in catalog.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $vendor = $user->vendor;

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'reward_type' => 'required|in:free_product,fixed_discount,percentage_discount,voucher',
            'points_cost' => 'required|integer|min:0',
            'discount_value' => 'nullable|numeric|min:0',
            'product_id' => 'nullable|integer',
            'required_tier_id' => 'nullable|integer',
            'stock_quantity' => 'nullable|integer|min:1',
            'max_per_customer' => 'nullable|integer|min:1',
            'image' => 'nullable|image|max:2048',
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $file = $this->storageService->storeFile($vendor, $request->file('image'), 'branding');
            $imageUrl = $file->url;
        }

        LoyaltyReward::create([
            'vendor_id' => $vendor->id,
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'reward_type' => RewardType::from($request->input('reward_type')),
            'points_cost' => (int) $request->input('points_cost'),
            'discount_value' => (float) $request->input('discount_value', 0.0),
            'product_id' => $request->input('product_id'),
            'required_tier_id' => $request->input('required_tier_id'),
            'stock_quantity' => $request->input('stock_quantity'),
            'max_per_customer' => $request->input('max_per_customer'),
            'image_url' => $imageUrl,
            'is_active' => true,
        ]);

        return redirect()->route('admin.loyalty.rewards')->with('success', 'Reward added to catalog.');
    }

    /**
     * Toggle reward active status.
     */
    public function toggle(Request $request, LoyaltyReward $reward): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        if ((int) $reward->vendor_id !== (int) $user->vendor_id) {
            abort(403);
        }

        $reward->update(['is_active' => ! $reward->is_active]);

        return back()->with('success', 'Reward status updated.');
    }

    /**
     * Delete a reward.
     */
    public function destroy(Request $request, LoyaltyReward $reward): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        if ((int) $reward->vendor_id !== (int) $user->vendor_id) {
            abort(403);
        }

        $reward->delete();

        return back()->with('success', 'Reward removed from catalog.');
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
