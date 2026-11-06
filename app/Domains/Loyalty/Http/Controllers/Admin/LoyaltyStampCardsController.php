<?php

namespace App\Domains\Loyalty\Http\Controllers\Admin;

use App\Domains\Loyalty\Models\LoyaltyStampProgram;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Security\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoyaltyStampCardsController extends Controller
{
    /**
     * Show digital stamp card programs.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $vendor = $user->vendor;

        $programs = LoyaltyStampProgram::where('vendor_id', $vendor->id)
            ->with('rewardProduct')
            ->latest()
            ->get();

        $categories = Category::where('vendor_id', $vendor->id)->get();
        $products = Product::where('vendor_id', $vendor->id)->where('is_available', true)->get();

        return view('admin.loyalty.stamps', compact('vendor', 'programs', 'categories', 'products'));
    }

    /**
     * Store new stamp program.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $vendor = $user->vendor;

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'stamps_required' => 'required|integer|min:2|max:30',
            'min_order_spend' => 'nullable|numeric|min:0',
            'reward_description' => 'required|string|max:255',
            'reward_product_id' => 'nullable|integer',
            'card_validity_days' => 'nullable|integer|min:1',
        ]);

        LoyaltyStampProgram::create([
            'vendor_id' => $vendor->id,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'stamps_required' => (int) $request->input('stamps_required'),
            'min_order_spend' => (float) $request->input('min_order_spend', 0.0),
            'reward_description' => $request->input('reward_description'),
            'reward_product_id' => $request->input('reward_product_id'),
            'card_validity_days' => $request->input('card_validity_days'),
            'is_active' => true,
        ]);

        return redirect()->route('admin.loyalty.stamps')->with('success', 'Stamp card program created successfully.');
    }

    /**
     * Toggle active state.
     */
    public function toggle(Request $request, LoyaltyStampProgram $program): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        if ((int) $program->vendor_id !== (int) $user->vendor_id) {
            abort(403);
        }

        $program->update(['is_active' => ! $program->is_active]);

        return back()->with('success', 'Stamp program status updated.');
    }

    /**
     * Delete stamp program.
     */
    public function destroy(Request $request, LoyaltyStampProgram $program): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        if ((int) $program->vendor_id !== (int) $user->vendor_id) {
            abort(403);
        }

        $program->delete();

        return back()->with('success', 'Stamp program deleted.');
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
