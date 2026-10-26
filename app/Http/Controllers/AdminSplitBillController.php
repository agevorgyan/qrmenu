<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SplitBillPayment;
use App\Security\Permission;
use App\Services\SecurityAuditService;
use App\Services\SplitBillService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminSplitBillController extends Controller
{
    public function __construct(
        protected SplitBillService $splitBillService,
        protected SecurityAuditService $auditService
    ) {}

    /**
     * Show Split Bill settings view.
     */
    public function settingsIndex(Request $request): View
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        if (! $user->isSuperAdmin() && ! $user->hasPermission(Permission::SPLIT_BILL_VIEW) && ! $user->hasPermission(Permission::SETTINGS_VIEW)) {
            abort(403, __('Unauthorized access to split bill settings.'));
        }

        $settings = $vendor->getSplitBillSetting();

        // Check if there are active split bills that might be affected by disabling
        $activeBillsCount = $vendor->splitBills()->where('status', 'active')->count();

        return view('admin.settings.split_bill', [
            'vendor' => $vendor,
            'settings' => $settings,
            'activeBillsCount' => $activeBillsCount,
        ]);
    }

    /**
     * Update Split Bill settings for the tenant.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        if (! $user->isSuperAdmin() && ! $user->hasPermission(Permission::SPLIT_BILL_MANAGE) && ! $user->hasPermission(Permission::SETTINGS_MANAGE)) {
            abort(403, __('Unauthorized to update split bill settings.'));
        }

        $validated = $request->validate([
            'is_enabled' => 'nullable|boolean',
            'allow_equal_split' => 'nullable|boolean',
            'allow_item_split' => 'nullable|boolean',
            'allow_custom_amount' => 'nullable|boolean',
            'allow_share_link' => 'nullable|boolean',
            'max_participants' => 'nullable|integer|min:2|max:50',
            'require_staff_approval' => 'nullable|boolean',
        ]);

        $settings = $vendor->getSplitBillSetting();
        $oldSettings = $settings->toArray();

        $maxParticipants = isset($validated['max_participants']) && $validated['max_participants'] !== null
            ? (int) $validated['max_participants']
            : (int) ($settings->max_participants ?? 10);

        $settings->update([
            'is_enabled' => $request->boolean('is_enabled', false),
            'allow_equal_split' => $request->boolean('allow_equal_split', true),
            'allow_item_split' => $request->boolean('allow_item_split', true),
            'allow_custom_amount' => $request->boolean('allow_custom_amount', true),
            'allow_share_link' => $request->boolean('allow_share_link', true),
            'max_participants' => $maxParticipants,
            'require_staff_approval' => $request->boolean('require_staff_approval', false),
        ]);

        $this->auditService->record(
            event: 'split_bill_settings.updated',
            action: 'Updated split bill module configuration',
            vendor: $vendor,
            actor: $user,
            targetType: 'split_bill_setting',
            targetId: $settings->id,
            metadata: [
                'old' => $oldSettings,
                'new' => $settings->fresh()->toArray(),
            ]
        );

        return redirect()->back()->with('success', __('Split bill settings saved successfully!'));
    }

    /**
     * View active split bill for an order in admin/waiter view.
     */
    public function showOrderSplit(Order $order): View
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        if ((int) $order->vendor_id !== (int) $vendor->id) {
            abort(403, __('Unauthorized order access.'));
        }

        $splitBill = $order->activeSplitBill ?? $order->splitBills()->latest()->first();

        if (! $splitBill && $vendor->isSplitBillEnabled()) {
            $splitBill = $this->splitBillService->getOrCreateSplitBill($order);
        }

        return view('admin.orders.split_bill_modal', [
            'order' => $order,
            'splitBill' => $splitBill,
            'vendor' => $vendor,
        ]);
    }

    /**
     * Approve a pending cash split payment.
     */
    public function approvePayment(Request $request, SplitBillPayment $payment): RedirectResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        if ((int) $payment->vendor_id !== (int) $vendor->id) {
            abort(403, __('Unauthorized access.'));
        }

        $this->splitBillService->approveCashPayment($payment, $user);

        return redirect()->back()->with('success', __('Cash split payment approved and balance updated!'));
    }
}
