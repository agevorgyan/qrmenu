<?php

namespace App\Http\Controllers;

use App\Models\ServiceRating;
use App\Models\Tip;
use App\Models\TipRatingSetting;
use App\Models\User;
use App\Security\FeatureCatalog;
use App\Security\Permission;
use App\Services\EntitlementService;
use App\Services\SecurityAuditService;
use App\Services\TipRatingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminTipRatingController extends Controller
{
    public function __construct(
        protected TipRatingService $tipRatingService,
        protected EntitlementService $entitlementService,
        protected SecurityAuditService $auditService
    ) {}

    /**
     * Display the Tip and Waiter Rating settings configuration panel with live preview.
     */
    public function settingsIndex(Request $request): View
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $this->authorizeSettings($user);

        $settings = $vendor->getTipRatingSetting();
        $locations = $vendor->locations;
        $staffMembers = User::where('vendor_id', $vendor->id)
            ->whereIn('role', ['staff', 'manager'])
            ->orderBy('name')
            ->get();

        $isEntitled = $vendor->hasFeature(FeatureCatalog::TIPS_RATINGS);

        return view('admin.settings.tips_ratings', compact(
            'vendor',
            'settings',
            'locations',
            'staffMembers',
            'isEntitled'
        ));
    }

    /**
     * Update restaurant tip and waiter rating settings.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $this->authorizeSettingsManage($user);

        $validated = $request->validate([
            'is_enabled' => 'nullable|boolean',
            'tips_enabled' => 'nullable|boolean',
            'ratings_enabled' => 'nullable|boolean',
            'tip_percentages' => 'nullable|array',
            'tip_percentages.*' => 'integer|min:1|max:100',
            'fixed_tip_amounts' => 'nullable|array',
            'fixed_tip_amounts.*' => 'numeric|min:1|max:1000000',
            'allow_custom_tip' => 'nullable|boolean',
            'default_tip_selection' => 'required|string|max:20',
            'calculation_base' => 'required|string|in:subtotal,subtotal_with_fees',
            'applicable_order_types' => 'nullable|array',
            'applicable_order_types.*' => 'string|in:dine_in,takeaway,delivery',
            'min_tip_amount' => 'nullable|numeric|min:0',
            'max_tip_amount' => 'nullable|numeric|min:0',
            'supported_payment_methods' => 'nullable|array',
            'supported_payment_methods.*' => 'string|in:idram,telcell,arca,stripe,cash,pos_terminal',
            'allow_cash_tips' => 'nullable|boolean',
            'allocation_policy' => 'required|string|in:waiter,pool,house',
            'rating_scale' => 'required|integer|in:5,10',
            'allow_written_feedback' => 'nullable|boolean',
            'require_written_feedback' => 'nullable|boolean',
            'is_anonymous_by_default' => 'nullable|boolean',
            'allow_customer_name' => 'nullable|boolean',
            'feedback_categories' => 'nullable|array',
            'feedback_categories.*' => 'string|max:50',
            'allow_waiter_selection' => 'nullable|boolean',
            'submission_window_hours' => 'required|integer|min:1|max:720',
            'duplicate_policy' => 'required|string|in:once_per_order,once_per_session_day',
            'auto_publish_ratings' => 'nullable|boolean',
        ]);

        $settings = $vendor->getTipRatingSetting();
        $oldSettings = $settings->toArray();

        $percentages = ! empty($validated['tip_percentages'])
            ? array_values(array_unique(array_map('intval', $validated['tip_percentages'])))
            : TipRatingSetting::defaultTipPercentages();

        $fixedAmounts = ! empty($validated['fixed_tip_amounts'])
            ? array_values(array_unique(array_map('floatval', $validated['fixed_tip_amounts'])))
            : TipRatingSetting::defaultFixedTipAmounts();

        $categories = ! empty($validated['feedback_categories'])
            ? array_values(array_unique(array_filter(array_map('trim', $validated['feedback_categories']))))
            : TipRatingSetting::defaultFeedbackCategories();

        $applicableOrderTypes = ! empty($validated['applicable_order_types'])
            ? array_values(array_unique($validated['applicable_order_types']))
            : ['dine_in', 'takeaway', 'delivery'];

        $settings->update([
            'is_enabled' => $request->boolean('is_enabled', true),
            'tips_enabled' => $request->boolean('tips_enabled', true),
            'ratings_enabled' => $request->boolean('ratings_enabled', true),
            'tip_percentages' => $percentages,
            'fixed_tip_amounts' => $fixedAmounts,
            'allow_custom_tip' => $request->boolean('allow_custom_tip', true),
            'default_tip_selection' => $validated['default_tip_selection'] ?? 'none',
            'calculation_base' => $validated['calculation_base'] ?? 'subtotal',
            'applicable_order_types' => $applicableOrderTypes,
            'min_tip_amount' => $validated['min_tip_amount'] ?? 0.00,
            'max_tip_amount' => $validated['max_tip_amount'] ?? 50000.00,
            'supported_payment_methods' => $validated['supported_payment_methods'] ?? ['idram', 'telcell', 'arca', 'stripe', 'cash', 'pos_terminal'],
            'allow_cash_tips' => $request->boolean('allow_cash_tips', true),
            'allocation_policy' => $validated['allocation_policy'] ?? 'pool',
            'rating_scale' => (int) ($validated['rating_scale'] ?? 5),
            'allow_written_feedback' => $request->boolean('allow_written_feedback', true),
            'require_written_feedback' => $request->boolean('require_written_feedback', false),
            'is_anonymous_by_default' => $request->boolean('is_anonymous_by_default', true),
            'allow_customer_name' => $request->boolean('allow_customer_name', true),
            'feedback_categories' => $categories,
            'allow_waiter_selection' => $request->boolean('allow_waiter_selection', true),
            'submission_window_hours' => (int) ($validated['submission_window_hours'] ?? 24),
            'duplicate_policy' => $validated['duplicate_policy'] ?? 'once_per_order',
            'auto_publish_ratings' => $request->boolean('auto_publish_ratings', true),
        ]);

        $this->auditService->record(
            event: 'tip_rating_settings.updated',
            action: 'Updated tips and ratings module configuration',
            vendor: $vendor,
            actor: $user,
            targetType: 'tip_rating_setting',
            targetId: $settings->id,
            metadata: [
                'old' => $oldSettings,
                'new' => $settings->fresh()->toArray(),
            ]
        );

        return redirect()->back()->with('success', __('Tips and Rating settings updated successfully!'));
    }

    /**
     * Comprehensive tenant-scoped tips and ratings analytics reports dashboard.
     */
    public function reportsIndex(Request $request): View
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $this->authorizeReports($user);

        $activeLocationId = $user->location_id ?: session('active_location_id', $vendor->locations->first()?->id);
        $requestLocation = $request->get('location_id', $activeLocationId);

        $days = (int) $request->get('days', $request->get('range', 30));
        $startDate = ! empty($request->get('start_date'))
            ? Carbon::parse($request->get('start_date'))->startOfDay()
            : now()->subDays($days - 1)->startOfDay();
        $endDate = ! empty($request->get('end_date'))
            ? Carbon::parse($request->get('end_date'))->endOfDay()
            : now()->endOfDay();

        $selectedWaiter = $request->get('waiter_id');

        $filters = [
            'range' => $days,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'location_id' => $requestLocation ? (int) $requestLocation : null,
            'waiter_id' => $selectedWaiter,
        ];

        $tipStats = $this->tipRatingService->getTipAnalytics($vendor, $filters);
        $ratingStats = $this->tipRatingService->getRatingAnalytics($vendor, $filters);

        // Paginated tips
        $tips = Tip::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->when($selectedWaiter, fn ($q, $w) => $q->where('waiter_id', $w))
            ->with(['waiter', 'order'])
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'tips_page')
            ->withQueryString();

        // Paginated reviews & feedback for moderation
        $ratingsQuery = ServiceRating::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->when($filters['location_id'], fn ($q, $loc) => $q->where('location_id', $loc))
            ->when($selectedWaiter, fn ($q, $w) => $q->where('waiter_id', $w))
            ->when($request->filled('rating_filter'), fn ($q) => $q->where('rating', (int) $request->get('rating_filter')))
            ->when($request->filled('status_filter'), fn ($q) => $q->where('moderation_status', $request->get('status_filter')))
            ->with(['waiter', 'order', 'location', 'moderator'])
            ->orderByDesc('created_at');

        $ratings = $ratingsQuery->paginate(15, ['*'], 'ratings_page')->withQueryString();
        $locations = $vendor->locations;
        $waiters = $vendor->users()->whereIn('role', ['staff', 'manager', 'cashier'])->get(['id', 'name']);

        $canModerate = $user->isSuperAdmin() || $user->hasPermission(Permission::RATINGS_MANAGE) || $user->hasPermission(Permission::SETTINGS_MANAGE);
        $canExport = $user->isSuperAdmin() || $user->hasPermission(Permission::REPORTS_EXPORT);
        $canViewStaffMetrics = $user->isSuperAdmin() || $user->hasPermission(Permission::STAFF_METRICS_VIEW) || $user->hasPermission(Permission::TEAM_VIEW);

        return view('admin.reports.tips_ratings', compact(
            'vendor',
            'days',
            'startDate',
            'endDate',
            'tipStats',
            'ratingStats',
            'ratings',
            'tips',
            'selectedWaiter',
            'waiters',
            'locations',
            'filters',
            'canModerate',
            'canExport',
            'canViewStaffMetrics'
        ));
    }

    /**
     * Moderate a customer service rating (publish, hide, flag) with audit trail.
     */
    public function moderateRating(Request $request, ServiceRating $rating): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        // Tenant ownership check
        if ((int) $rating->vendor_id !== (int) $vendor->id && ! $user->isSuperAdmin()) {
            abort(403, 'Cross-tenant rating moderation forbidden.');
        }

        $this->authorizeRatingModeration($user);

        $validated = $request->validate([
            'status' => 'required|string|in:published,hidden,flagged',
            'moderation_note' => 'nullable|string|max:500',
        ]);

        $this->tipRatingService->moderateRating(
            $rating,
            $validated['status'],
            $validated['moderation_note'] ?? null,
            $user
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'status' => $rating->fresh()->status,
                'message' => __('Rating moderation status updated.'),
            ]);
        }

        return redirect()->back()->with('success', __('Rating moderation status updated successfully.'));
    }

    /**
     * Export tips data to CSV format with tenant isolation.
     */
    public function exportTips(Request $request): Response
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $this->authorizeExport($user);

        $csv = $this->tipRatingService->exportTipsCsv($vendor, $request->all());
        $filename = "tips_report_{$vendor->slug}_".now()->format('Ymd_His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Export service ratings and feedback to CSV format with tenant isolation.
     */
    public function exportRatings(Request $request): Response
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $this->authorizeExport($user);

        $csv = $this->tipRatingService->exportRatingsCsv($vendor, $request->all());
        $filename = "ratings_report_{$vendor->slug}_".now()->format('Ymd_His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    protected function authorizeSettings(User $user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if (! $user->hasAnyPermission([Permission::SETTINGS_VIEW, Permission::TIPS_VIEW, Permission::RATINGS_VIEW])) {
            abort(403, 'Unauthorized access to tips & ratings settings.');
        }
    }

    protected function authorizeSettingsManage(User $user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if (! $user->hasAnyPermission([Permission::SETTINGS_MANAGE, Permission::TIPS_MANAGE])) {
            abort(403, 'Unauthorized to modify tips & ratings settings.');
        }
    }

    protected function authorizeReports(User $user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if (! $user->hasAnyPermission([Permission::REPORTS_VIEW, Permission::TIPS_VIEW, Permission::RATINGS_VIEW])) {
            abort(403, 'Unauthorized to view tips & ratings reports.');
        }
    }

    protected function authorizeRatingModeration(User $user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if (! $user->hasAnyPermission([Permission::RATINGS_MANAGE, Permission::SETTINGS_MANAGE])) {
            abort(403, 'Unauthorized to moderate ratings.');
        }
    }

    protected function authorizeExport(User $user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if (! $user->hasPermission(Permission::REPORTS_EXPORT)) {
            abort(403, 'Unauthorized to export report data.');
        }
    }
}
