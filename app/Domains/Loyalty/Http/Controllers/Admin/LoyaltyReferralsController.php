<?php

namespace App\Domains\Loyalty\Http\Controllers\Admin;

use App\Domains\Loyalty\Models\LoyaltyProgram;
use App\Domains\Loyalty\Models\LoyaltyReferral;
use App\Http\Controllers\Controller;
use App\Security\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoyaltyReferralsController extends Controller
{
    /**
     * Show referrals management & configuration.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $vendor = $user->vendor;

        $program = LoyaltyProgram::firstOrCreate(['vendor_id' => $vendor->id]);

        $referrals = LoyaltyReferral::where('vendor_id', $vendor->id)
            ->with(['referrer', 'referred', 'qualifyingOrder'])
            ->latest()
            ->paginate(20);

        $totalReferrals = LoyaltyReferral::where('vendor_id', $vendor->id)->count();
        $qualifiedReferrals = LoyaltyReferral::where('vendor_id', $vendor->id)->where('status', 'qualified')->count();
        $totalReferralPoints = (int) LoyaltyReferral::where('vendor_id', $vendor->id)->sum('referrer_points_awarded');

        return view('admin.loyalty.referrals', compact(
            'vendor',
            'program',
            'referrals',
            'totalReferrals',
            'qualifiedReferrals',
            'totalReferralPoints'
        ));
    }

    /**
     * Update referral program reward parameters.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $vendor = $user->vendor;

        $request->validate([
            'referrer_points' => 'required|integer|min:0',
            'referred_points' => 'required|integer|min:0',
            'min_order_spend' => 'required|numeric|min:0',
        ]);

        $program = LoyaltyProgram::firstOrCreate(['vendor_id' => $vendor->id]);
        $settings = $program->settings ?? [];
        $settings['referral'] = [
            'referrer_points' => (int) $request->input('referrer_points'),
            'referred_points' => (int) $request->input('referred_points'),
            'min_order_spend' => (float) $request->input('min_order_spend'),
        ];

        $program->update(['settings' => $settings]);

        return back()->with('success', 'Referral program reward settings updated.');
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
