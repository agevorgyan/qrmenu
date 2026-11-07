<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Domains\Wholesale\Services\WholesaleAccessService;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WholesaleAccessController extends Controller
{
    public function __construct(
        protected WholesaleAccessService $accessService
    ) {}

    /**
     * Display list of all tenants and their Wholesale POS access status.
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $status = $request->query('status'); // all, active, disabled

        $tenants = $this->accessService->getTenantsForSuperAdmin(
            search: $search,
            statusFilter: $status,
            perPage: 15
        );

        $stats = [
            'total_tenants' => Vendor::withoutGlobalScopes()->count(),
            'active_wholesale' => Vendor::withoutGlobalScopes()->whereHas('moduleAccess', function ($q) {
                $q->where('module_key', 'wholesale_pos')
                    ->where('is_enabled', true)
                    ->whereNull('revoked_at')
                    ->where(function ($sq) {
                        $sq->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now());
                    });
            })->count(),
        ];

        return view('superadmin.wholesale.access', compact('tenants', 'stats', 'search', 'status'));
    }

    /**
     * Grant or renew Wholesale POS access to a tenant.
     */
    public function grant(Request $request, Vendor $vendor): RedirectResponse
    {
        $validated = $request->validate([
            'expires_at' => ['nullable', 'date', 'after:today'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $expiresAt = ! empty($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null;
        $reason = $validated['reason'] ?? 'Access granted by Super Admin';

        $this->accessService->grantAccess($vendor, $request->user(), $expiresAt, $reason);

        return redirect()->back()->with('success', __("Wholesale POS access granted to restaurant ':name'.", ['name' => $vendor->name]));
    }

    /**
     * Temporarily suspend Wholesale POS access for a tenant.
     */
    public function suspend(Request $request, Vendor $vendor): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $reason = $validated['reason'] ?? 'Access temporarily suspended by Super Admin';
        $this->accessService->suspendAccess($vendor, $request->user(), $reason);

        return redirect()->back()->with('warning', __("Wholesale POS access suspended for restaurant ':name'.", ['name' => $vendor->name]));
    }

    /**
     * Permanently revoke Wholesale POS access for a tenant.
     */
    public function revoke(Request $request, Vendor $vendor): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $reason = $validated['reason'] ?? 'Access revoked by Super Admin';
        $this->accessService->revokeAccess($vendor, $request->user(), $reason);

        return redirect()->back()->with('error', __("Wholesale POS access permanently revoked for restaurant ':name'.", ['name' => $vendor->name]));
    }

    /**
     * Retrieve audit logs for a tenant's module access.
     */
    public function logs(Vendor $vendor)
    {
        $logs = $this->accessService->getAuditLogs($vendor);

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'logs' => $logs,
            ]);
        }

        return view('superadmin.wholesale.logs_partial', compact('vendor', 'logs'));
    }
}

