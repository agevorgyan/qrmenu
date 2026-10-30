<?php

namespace App\Http\Controllers;

use App\Models\PosOperationLog;
use App\Models\PosSetting;
use App\Security\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PosSettingController extends Controller
{
    /**
     * Get POS settings for current branch or vendor.
     */
    public function show(Request $request): JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $locationId = $request->filled('location_id') ? (int) $request->get('location_id') : $user->location_id;
        $settings = PosSetting::forLocation($vendor, $locationId);

        return response()->json([
            'success' => true,
            'settings' => $settings,
        ]);
    }

    /**
     * Update POS settings.
     */
    public function update(Request $request): JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        if (! $user->hasPermission(Permission::POS_SETTINGS) && ! $user->hasPermission(Permission::SETTINGS_MANAGE)) {
            abort(403, 'Unauthorized to modify POS settings.');
        }

        $validated = $request->validate([
            'location_id' => 'nullable|integer',
            'enable_quick_pin' => 'nullable|boolean',
            'require_table_selection' => 'nullable|boolean',
            'auto_lock_seconds' => 'nullable|integer|min:0|max:3600',
            'allow_manual_discounts' => 'nullable|boolean',
            'max_discount_percent' => 'nullable|numeric|min:0|max:100',
            'require_manager_approval_discount' => 'nullable|boolean',
            'require_manager_approval_void' => 'nullable|boolean',
            'default_order_type' => 'nullable|string|in:dine_in,takeaway,delivery',
            'default_payment_method' => 'nullable|string|max:30',
            'allow_offline_orders' => 'nullable|boolean',
            'print_receipt_on_payment' => 'nullable|boolean',
            'print_kitchen_ticket_on_order' => 'nullable|boolean',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_inclusive' => 'nullable|boolean',
            'service_charge_percent' => 'nullable|numeric|min:0|max:100',
            'custom_settings' => 'nullable|array',
        ]);

        $locationId = $validated['location_id'] ?? null;
        $setting = PosSetting::where('vendor_id', $vendor->id)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId), fn ($q) => $q->whereNull('location_id'))
            ->first();

        if (! $setting) {
            $setting = PosSetting::create(array_merge($validated, [
                'vendor_id' => $vendor->id,
                'location_id' => $locationId,
            ]));
        } else {
            $setting->update($validated);
        }

        PosOperationLog::log(
            vendor: $vendor,
            action: 'settings_update',
            user: $user,
            entity: $setting,
            details: $validated,
            locationId: $locationId
        );

        return response()->json([
            'success' => true,
            'message' => 'POS settings updated successfully.',
            'settings' => $setting->fresh(),
        ]);
    }
}

