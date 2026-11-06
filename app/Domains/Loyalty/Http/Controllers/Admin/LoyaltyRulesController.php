<?php

namespace App\Domains\Loyalty\Http\Controllers\Admin;

use App\Domains\Loyalty\Models\LoyaltyEarningRule;
use App\Domains\Loyalty\Models\LoyaltyProgram;
use App\Http\Controllers\Controller;
use App\Security\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoyaltyRulesController extends Controller
{
    /**
     * List points and cashback earning rules.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $vendor = $user->vendor;

        $program = LoyaltyProgram::firstOrCreate(['vendor_id' => $vendor->id]);

        $pointsRules = LoyaltyEarningRule::where('vendor_id', $vendor->id)
            ->where('reward_type', 'points')
            ->orderBy('priority', 'desc')
            ->get();

        $cashbackRules = LoyaltyEarningRule::where('vendor_id', $vendor->id)
            ->where('reward_type', 'cashback')
            ->orderBy('priority', 'desc')
            ->get();

        return view('admin.loyalty.rules', compact('vendor', 'program', 'pointsRules', 'cashbackRules'));
    }

    /**
     * Store new earning rule.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $vendor = $user->vendor;
        $program = LoyaltyProgram::firstOrCreate(['vendor_id' => $vendor->id]);

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:spending,percentage,fixed,multiplier',
            'reward_type' => 'required|in:points,cashback',
            'rate_value' => 'required|numeric|min:0.0001',
            'min_order_spend' => 'nullable|numeric|min:0',
            'max_points_per_order' => 'nullable|integer|min:1',
            'priority' => 'nullable|integer',
        ]);

        LoyaltyEarningRule::create([
            'vendor_id' => $vendor->id,
            'program_id' => $program->id,
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'reward_type' => $request->input('reward_type'),
            'rate_value' => $request->input('rate_value'),
            'min_order_spend' => $request->input('min_order_spend'),
            'max_points_per_order' => $request->input('max_points_per_order'),
            'priority' => (int) $request->input('priority', 0),
            'is_active' => true,
        ]);

        return redirect()->route('admin.loyalty.rules')->with('success', 'Earning rule created successfully.');
    }

    /**
     * Toggle active state of earning rule.
     */
    public function toggle(Request $request, LoyaltyEarningRule $rule): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        if ((int) $rule->vendor_id !== (int) $user->vendor_id) {
            abort(403);
        }

        $rule->update(['is_active' => ! $rule->is_active]);

        return back()->with('success', 'Rule status updated.');
    }

    /**
     * Delete an earning rule.
     */
    public function destroy(Request $request, LoyaltyEarningRule $rule): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        if ((int) $rule->vendor_id !== (int) $user->vendor_id) {
            abort(403);
        }

        $rule->delete();

        return back()->with('success', 'Rule deleted successfully.');
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
