<?php

namespace App\Domains\Loyalty\Http\Controllers\Admin;

use App\Domains\Loyalty\Enums\CampaignTriggerType;
use App\Domains\Loyalty\Models\LoyaltyCampaign;
use App\Domains\Loyalty\Models\LoyaltyReward;
use App\Http\Controllers\Controller;
use App\Security\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoyaltyCampaignsController extends Controller
{
    /**
     * Show marketing campaigns list.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $vendor = $user->vendor;

        $campaigns = LoyaltyCampaign::where('vendor_id', $vendor->id)
            ->with(['reward'])
            ->withCount('events')
            ->latest()
            ->paginate(15);

        $rewards = LoyaltyReward::where('vendor_id', $vendor->id)->where('is_active', true)->get();

        return view('admin.loyalty.campaigns', compact('vendor', 'campaigns', 'rewards'));
    }

    /**
     * Store new marketing campaign.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $vendor = $user->vendor;

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'required|in:welcome,birthday,first_order,inactive_customer,seasonal,custom',
            'reward_type' => 'required|in:points,cashback,reward_voucher',
            'reward_value' => 'required|numeric|min:0',
            'reward_id' => 'nullable|integer',
            'days_inactive' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
        ]);

        LoyaltyCampaign::create([
            'vendor_id' => $vendor->id,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'trigger_type' => CampaignTriggerType::from($request->input('trigger_type')),
            'reward_type' => $request->input('reward_type'),
            'reward_value' => (float) $request->input('reward_value'),
            'reward_id' => $request->input('reward_id'),
            'days_inactive' => $request->input('days_inactive'),
            'starts_at' => $request->input('starts_at'),
            'ends_at' => $request->input('ends_at'),
            'is_active' => true,
        ]);

        return redirect()->route('admin.loyalty.campaigns')->with('success', 'Marketing campaign created.');
    }

    /**
     * Toggle campaign active state.
     */
    public function toggle(Request $request, LoyaltyCampaign $campaign): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        if ((int) $campaign->vendor_id !== (int) $user->vendor_id) {
            abort(403);
        }

        $campaign->update(['is_active' => ! $campaign->is_active]);

        return back()->with('success', 'Campaign status updated.');
    }

    /**
     * Delete campaign.
     */
    public function destroy(Request $request, LoyaltyCampaign $campaign): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        if ((int) $campaign->vendor_id !== (int) $user->vendor_id) {
            abort(403);
        }

        $campaign->delete();

        return back()->with('success', 'Campaign deleted.');
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
