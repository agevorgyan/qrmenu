<?php

namespace App\Domains\Loyalty\Http\Controllers\Admin;

use App\Domains\Loyalty\Models\LoyaltyProgram;
use App\Domains\Loyalty\Models\LoyaltyProgramVersion;
use App\Http\Controllers\Controller;
use App\Security\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoyaltyProgramController extends Controller
{
    /**
     * Show program settings editor.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $vendor = $user->vendor;

        $program = LoyaltyProgram::firstOrCreate(
            ['vendor_id' => $vendor->id],
            ['name' => "{$vendor->name} Loyalty Club"]
        );

        return view('admin.loyalty.settings', compact('vendor', 'program'));
    }

    /**
     * Update program settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $vendor = $user->vendor;

        $request->validate([
            'name' => 'required|string|max:255',
            'currency' => 'required|string|max:10',
            'default_points_ratio' => 'required|numeric|min:0',
            'points_redemption_rate' => 'required|numeric|min:0.0001',
            'points_expiration_days' => 'nullable|integer|min:1',
            'cashback_expiration_days' => 'nullable|integer|min:1',
            'points_enabled' => 'nullable|boolean',
            'cashback_enabled' => 'nullable|boolean',
            'tiers_enabled' => 'nullable|boolean',
            'stamps_enabled' => 'nullable|boolean',
            'referrals_enabled' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $program = LoyaltyProgram::firstOrCreate(['vendor_id' => $vendor->id]);

        $updateData = [
            'name' => $request->input('name'),
            'currency' => strtoupper($request->input('currency')),
            'default_points_ratio' => $request->input('default_points_ratio'),
            'points_redemption_rate' => $request->input('points_redemption_rate'),
            'points_expiration_days' => $request->input('points_expiration_days'),
            'cashback_expiration_days' => $request->input('cashback_expiration_days'),
            'points_enabled' => $request->boolean('points_enabled'),
            'cashback_enabled' => $request->boolean('cashback_enabled'),
            'tiers_enabled' => $request->boolean('tiers_enabled'),
            'stamps_enabled' => $request->boolean('stamps_enabled'),
            'referrals_enabled' => $request->boolean('referrals_enabled'),
            'is_active' => $request->boolean('is_active'),
        ];

        $program->update($updateData);

        // Snapshot program version
        $latestVersion = (int) LoyaltyProgramVersion::where('program_id', $program->id)->max('version_number');
        LoyaltyProgramVersion::create([
            'program_id' => $program->id,
            'vendor_id' => $vendor->id,
            'version_number' => $latestVersion + 1,
            'config_snapshot' => $updateData,
            'published_by' => $user->id,
        ]);

        return redirect()->route('admin.loyalty.settings')->with('success', 'Loyalty program settings updated successfully.');
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
