<?php

namespace App\Domains\Loyalty\Http\Controllers\Admin;

use App\Domains\Loyalty\Models\LoyaltyProgram;
use App\Domains\Loyalty\Models\LoyaltyTier;
use App\Domains\Loyalty\Services\TierService;
use App\Http\Controllers\Controller;
use App\Security\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoyaltyTiersController extends Controller
{
    public function __construct(
        protected TierService $tierService
    ) {}

    /**
     * Show membership tiers management screen.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $vendor = $user->vendor;

        $program = LoyaltyProgram::firstOrCreate(['vendor_id' => $vendor->id]);
        $this->tierService->ensureDefaultTiers($vendor, $program);

        $tiers = LoyaltyTier::where('vendor_id', $vendor->id)
            ->orderBy('level', 'asc')
            ->get();

        return view('admin.loyalty.tiers', compact('vendor', 'program', 'tiers'));
    }

    /**
     * Update tier parameters (thresholds, benefits, colors).
     */
    public function update(Request $request, LoyaltyTier $tier): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        if ((int) $tier->vendor_id !== (int) $user->vendor_id) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:100',
            'min_points' => 'required|integer|min:0',
            'min_spend' => 'required|numeric|min:0',
            'points_multiplier' => 'required|numeric|min:1.0',
            'cashback_percent' => 'required|numeric|min:0|max:100',
            'order_discount_percent' => 'required|numeric|min:0|max:100',
            'color_hex' => 'required|string|max:20',
            'perks_description' => 'nullable|string',
        ]);

        $tier->update([
            'name' => $request->input('name'),
            'min_points' => (int) $request->input('min_points'),
            'min_spend' => (float) $request->input('min_spend'),
            'points_multiplier' => (float) $request->input('points_multiplier'),
            'cashback_percent' => (float) $request->input('cashback_percent'),
            'order_discount_percent' => (float) $request->input('order_discount_percent'),
            'color_hex' => $request->input('color_hex'),
            'perks_description' => $request->input('perks_description'),
        ]);

        return back()->with('success', "Tier {$tier->name} updated successfully.");
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
