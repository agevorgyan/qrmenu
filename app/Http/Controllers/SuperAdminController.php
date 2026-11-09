<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Requests\UpdateSystemSettingsRequest;
use App\Models\EmailLog;
use App\Models\Feature;
use App\Models\FeatureCategory;
use App\Models\FeatureDependency;
use App\Models\Location;
use App\Models\MenuTemplate;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\SecurityAuditLog;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionAuditLog;
use App\Models\SubscriptionLifecycleEvent;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use App\Models\SubscriptionPlanVersion;
use App\Models\SystemSetting;
use App\Models\TenantFeatureOverride;
use App\Models\TenantQuotaOverride;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorSubscriptionAddon;
use App\Security\FeatureCatalog;
use App\Security\QuotaCatalog;
use App\Services\EntitlementService;
use App\Services\FeatureDependencyService;
use App\Services\PlanVersionService;
use App\Services\QrCodeService;
use App\Services\QuotaService;
use App\Services\Security\ImageProcessor;
use App\Services\SecurityAuditService;
use App\Services\SubscriptionAddonService;
use App\Services\SubscriptionAuditService;
use App\Services\SubscriptionService;
use App\Services\TelegramNotificationService;
use App\Services\TenantCache;
use App\Services\TwoFactorAuthService;
use Carbon\Carbon;
use Illuminate\Http\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SuperAdminController extends Controller
{
    public function dashboard()
    {
        $totalVendors = Vendor::count();
        $totalLocations = Location::count();
        $totalOrders = Order::count();
        $totalRevenue = Order::sum('total_amount');

        $recentVendors = Vendor::withCount('locations')->latest()->take(5)->get();
        $templates = MenuTemplate::withCount('vendors')->get();
        $plans = SubscriptionPlan::withCount('vendors')->get();

        return view('superadmin.dashboard', compact(
            'totalVendors',
            'totalLocations',
            'totalOrders',
            'totalRevenue',
            'recentVendors',
            'templates',
            'plans'
        ));
    }

    public function vendorsIndex()
    {
        $vendors = Vendor::with('locations', 'menuTemplate', 'plan')->latest()->get();
        $templates = MenuTemplate::all();
        $plans = SubscriptionPlan::where('is_active', true)->get();

        return view('superadmin.vendors', compact('vendors', 'templates', 'plans'));
    }

    public function storeVendor(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:restaurant,cafe,hotel',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string',
            'subscription_plan' => 'required|string',
            'menu_template_id' => 'required|exists:menu_templates,id',
            'owner_name' => 'required|string|max:255',
            'password' => 'required|string|min:6',
        ]);

        $plan = SubscriptionPlan::where('slug', $validated['subscription_plan'])->first()
            ?? SubscriptionPlan::first();

        DB::transaction(function () use ($validated, $plan) {
            $trialDays = $plan?->trial_days ?? 14;

            $vendor = Vendor::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']).'-'.Str::random(4),
                'type' => $validated['type'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'subscription_plan' => $plan?->slug ?? 'pro',
                'subscription_plan_id' => $plan?->id,
                'subscription_status' => 'trialing',
                'trial_ends_at' => now()->addDays($trialDays),
                'subscription_expires_at' => now()->addDays($trialDays),
                'menu_template_id' => $validated['menu_template_id'],
                'primary_color' => '#e11d48',
                'secondary_color' => '#4f46e5',
                'is_active' => true,
            ]);

            if ($plan) {
                app(SubscriptionService::class)->startTrial($vendor, $plan, $trialDays);
            }

            // Default Location
            $location = Location::create([
                'vendor_id' => $vendor->id,
                'name' => 'Main Location',
                'slug' => 'main',
                'phone' => $validated['phone'] ?? null,
                'table_count' => 20,
                'is_active' => true,
            ]);

            // Create Owner User
            User::create([
                'vendor_id' => $vendor->id,
                'location_id' => $location->id,
                'name' => $validated['owner_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'vendor_owner',
                'phone' => $validated['phone'] ?? null,
            ]);
        });

        return back()->with('success', __('Vendor & Owner Account successfully created!'));
    }

    public function toggleStatus(Vendor $vendor)
    {
        $vendor->update(['is_active' => ! $vendor->is_active]);

        return back()->with('success', __('Vendor status updated successfully.'));
    }

    // Plan Management
    public function plansIndex()
    {
        $plans = SubscriptionPlan::withCount('vendors')
            ->with(['activePrices', 'versions', 'planFeatures.feature'])
            ->orderBy('sort_order', 'asc')
            ->get();
        $availableFeatures = FeatureCatalog::all();
        $availableQuotas = QuotaCatalog::all();

        $featureCategories = FeatureCategory::with(['features' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
        $catalogFeatures = Feature::with('category')->orderBy('sort_order')->get();
        $featureDependencies = FeatureDependency::all();
        $subscriptionAddons = SubscriptionAddon::orderBy('sort_order')->get();
        $subscriptionSettings = [
            'default_trial_days' => (int) (SystemSetting::get('subscription_default_trial_days') ?? 14),
            'grace_period_days' => (int) (SystemSetting::get('subscription_grace_period_days') ?? 3),
            'warning_threshold_percent' => (int) (SystemSetting::get('subscription_warning_threshold_percent') ?? 80),
            'downgrade_behavior' => (string) (SystemSetting::get('subscription_downgrade_behavior') ?? 'preserve_data_restrict_new'),
        ];

        return view('superadmin.plans.index', compact(
            'plans',
            'availableFeatures',
            'availableQuotas',
            'featureCategories',
            'catalogFeatures',
            'featureDependencies',
            'subscriptionAddons',
            'subscriptionSettings'
        ));
    }

    public function storePlan(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'billing_interval' => 'required|string|in:monthly,yearly,custom',
            'duration_days' => 'required|integer|min:1',
            'trial_days' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'features' => 'nullable|string',
            'feature_codes' => 'nullable|array',
            'feature_codes.*' => 'string|max:100',
            'quota_limits' => 'nullable|array',
            'yearly_price' => 'nullable|numeric|min:0',
        ]);

        $featuresArray = array_values(array_filter(array_map('trim', explode("\n", $validated['features'] ?? ''))));
        $currency = strtoupper($validated['currency'] ?? 'AMD');

        // Normalize feature codes against catalog
        $validFeatures = FeatureCatalog::all();
        $featureCodes = array_values(array_intersect($validated['feature_codes'] ?? [], $validFeatures));

        // Normalize quota limits against catalog
        $quotaLimits = [];
        if (! empty($validated['quota_limits']) && is_array($validated['quota_limits'])) {
            foreach ($validated['quota_limits'] as $k => $val) {
                if (QuotaCatalog::isValid($k) && is_numeric($val)) {
                    $quotaLimits[QuotaCatalog::normalize($k)] = (int) $val;
                }
            }
        }

        $plan = SubscriptionPlan::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'price' => $validated['price'],
            'currency' => $currency,
            'billing_interval' => $validated['billing_interval'],
            'duration_days' => $validated['duration_days'],
            'trial_days' => $validated['trial_days'],
            'description' => $validated['description'] ?? null,
            'features' => $featuresArray,
            'feature_codes' => $featureCodes,
            'quota_limits' => $quotaLimits,
            'is_custom' => $validated['price'] == 0 || $validated['billing_interval'] === 'custom',
            'is_active' => true,
            'sort_order' => (SubscriptionPlan::max('sort_order') ?? 0) + 1,
        ]);

        // Create default monthly price tier
        SubscriptionPlanPrice::create([
            'subscription_plan_id' => $plan->id,
            'billing_interval' => 'monthly',
            'currency' => $currency,
            'price' => $validated['price'],
            'is_active' => true,
            'description' => 'Monthly billing interval',
        ]);

        // Optional yearly price tier
        if (isset($validated['yearly_price']) && is_numeric($validated['yearly_price'])) {
            SubscriptionPlanPrice::create([
                'subscription_plan_id' => $plan->id,
                'billing_interval' => 'yearly',
                'currency' => $currency,
                'price' => (float) $validated['yearly_price'],
                'is_active' => true,
                'description' => 'Yearly billing interval',
            ]);
        }

        app(SecurityAuditService::class)->record(
            event: SecurityAuditLog::EVENT_SUBSCRIPTION_CHANGED,
            action: "Created subscription plan [{$plan->name}] (Slug: {$plan->slug}).",
            actor: Auth::user(),
            targetType: SubscriptionPlan::class,
            targetId: $plan->id,
            metadata: [
                'price' => $plan->price,
                'currency' => $currency,
                'feature_codes' => $featureCodes,
                'quota_limits' => $quotaLimits,
            ]
        );

        return back()->with('success', __('Բաժանորդագրության փաթեթը հաջողությամբ ստեղծվեց։'));
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'billing_interval' => 'required|string|in:monthly,yearly,custom',
            'duration_days' => 'required|integer|min:1',
            'trial_days' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'features' => 'nullable|string',
            'feature_codes' => 'nullable|array',
            'feature_codes.*' => 'string|max:100',
            'quota_limits' => 'nullable|array',
            'yearly_price' => 'nullable|numeric|min:0',
        ]);

        $featuresArray = array_values(array_filter(array_map('trim', explode("\n", $validated['features'] ?? ''))));
        $currency = strtoupper($validated['currency'] ?? $plan->currency ?? 'AMD');

        // Normalize feature codes against catalog
        $validFeatures = FeatureCatalog::all();
        $featureCodes = array_values(array_intersect($validated['feature_codes'] ?? [], $validFeatures));

        // Normalize quota limits against catalog
        $quotaLimits = [];
        if (! empty($validated['quota_limits']) && is_array($validated['quota_limits'])) {
            foreach ($validated['quota_limits'] as $k => $val) {
                if (QuotaCatalog::isValid($k) && is_numeric($val)) {
                    $quotaLimits[QuotaCatalog::normalize($k)] = (int) $val;
                }
            }
        }

        $plan->update([
            'name' => $validated['name'],
            'price' => $validated['price'],
            'currency' => $currency,
            'billing_interval' => $validated['billing_interval'],
            'duration_days' => $validated['duration_days'],
            'trial_days' => $validated['trial_days'],
            'description' => $validated['description'] ?? null,
            'features' => $featuresArray,
            'feature_codes' => $featureCodes,
            'quota_limits' => $quotaLimits,
            'is_custom' => $validated['price'] == 0 || $validated['billing_interval'] === 'custom',
            'is_active' => $request->has('is_active'),
        ]);

        // Sync monthly price tier
        SubscriptionPlanPrice::updateOrCreate(
            ['subscription_plan_id' => $plan->id, 'billing_interval' => 'monthly', 'currency' => $currency],
            ['price' => $validated['price'], 'is_active' => true]
        );

        // Sync yearly price tier if specified
        if (isset($validated['yearly_price']) && is_numeric($validated['yearly_price'])) {
            SubscriptionPlanPrice::updateOrCreate(
                ['subscription_plan_id' => $plan->id, 'billing_interval' => 'yearly', 'currency' => $currency],
                ['price' => (float) $validated['yearly_price'], 'is_active' => true]
            );
        }

        app(SecurityAuditService::class)->record(
            event: SecurityAuditLog::EVENT_SUBSCRIPTION_CHANGED,
            action: "Updated subscription plan [{$plan->name}] (Slug: {$plan->slug}).",
            actor: Auth::user(),
            targetType: SubscriptionPlan::class,
            targetId: $plan->id,
            metadata: [
                'price' => $plan->price,
                'feature_codes' => $featureCodes,
                'quota_limits' => $quotaLimits,
            ]
        );

        return back()->with('success', __('Բաժանորդագրության փաթեթը թարմացվեց։'));
    }

    public function togglePlan(SubscriptionPlan $plan)
    {
        $plan->update(['is_active' => ! $plan->is_active]);

        app(SecurityAuditService::class)->record(
            event: SecurityAuditLog::EVENT_SUBSCRIPTION_CHANGED,
            action: "Toggled subscription plan [{$plan->name}] to ".($plan->is_active ? 'active' : 'disabled').'.',
            actor: Auth::user(),
            targetType: SubscriptionPlan::class,
            targetId: $plan->id
        );

        return back()->with('success', __('Փաթեթի կարգավիճակը թարմացվեց։'));
    }

    public function destroyPlan(Request $request, SubscriptionPlan $plan)
    {
        $vendorCount = $plan->vendors()->count();
        $subCount = $plan->subscriptions()->count();

        if ($vendorCount > 0 || $subCount > 0) {
            $fallbackPlan = SubscriptionPlan::where('id', '!=', $plan->id)
                ->where('is_active', true)
                ->orderBy('sort_order', 'asc')
                ->first();

            if ($request->boolean('force') && $fallbackPlan) {
                Vendor::where('subscription_plan_id', $plan->id)
                    ->update(['subscription_plan_id' => $fallbackPlan->id]);
                Subscription::where('subscription_plan_id', $plan->id)
                    ->update(['subscription_plan_id' => $fallbackPlan->id]);
            } else {
                return back()->with('error', __('Հնարավոր չէ ջնջել փաթեթը, քանի որ այն կցված է :count գործընկերների։ Նախ տեղափոխեք գործընկերներին այլ փաթեթ կամ արխիվացրեք այն։', [
                    'count' => $vendorCount ?: $subCount,
                ]));
            }
        }

        app(SecurityAuditService::class)->record(
            event: SecurityAuditLog::EVENT_SUBSCRIPTION_CHANGED,
            action: "Deleted subscription plan [{$plan->name}] (ID: {$plan->id}).",
            actor: Auth::user(),
            targetType: SubscriptionPlan::class,
            targetId: $plan->id
        );

        $plan->prices()->delete();
        $plan->planFeatures()->delete();
        $plan->versions()->delete();
        $plan->delete();

        return back()->with('success', __('Փաթեթը հաջողությամբ ջնջվեց։'));
    }

    // Vendor Subscriptions & Subscriber Management
    public function subscriptionsIndex(Request $request)
    {
        $query = Vendor::withoutGlobalScopes()->with(['plan', 'subscription.plan', 'payments' => fn ($q) => $q->latest()->limit(5)]);

        // Search filter (name, email, phone, slug)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('slug', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        // Status filter
        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->whereHas('subscription', fn ($q) => $q->where('status', 'active'));
            } elseif ($status === 'trialing') {
                $query->whereHas('subscription', fn ($q) => $q->where('status', 'trialing'));
            } elseif ($status === 'expired') {
                $query->whereHas('subscription', fn ($q) => $q->where('status', 'expired'));
            } elseif ($status === 'suspended') {
                $query->whereHas('subscription', fn ($q) => $q->where('status', 'suspended'));
            } elseif ($status === 'past_due') {
                $query->whereHas('subscription', fn ($q) => $q->whereIn('status', ['past_due', 'grace', 'grace_period']));
            } elseif ($status === 'cancelled') {
                $query->whereHas('subscription', fn ($q) => $q->whereIn('status', ['cancelled', 'cancel_at_period_end']));
            }
        }

        // Plan filter
        if ($planId = $request->input('plan_id')) {
            $query->where(function ($q) use ($planId) {
                $q->where('subscription_plan_id', $planId)
                    ->orWhereHas('subscription', fn ($sq) => $sq->where('subscription_plan_id', $planId));
            });
        }

        // Failed payment filter
        if ($request->boolean('failed_only')) {
            $query->whereHas('payments', fn ($q) => $q->where('status', 'failed'));
        }

        $vendors = $query->latest('id')->paginate(15)->withQueryString();

        $plans = SubscriptionPlan::with('versions')->orderBy('sort_order', 'asc')->get();
        $availableFeatures = FeatureCatalog::all();
        $availableQuotas = QuotaCatalog::all();
        $catalogFeatures = Feature::with('category')->where('is_active', true)->orderBy('sort_order')->get();
        $subscriptionAddons = SubscriptionAddon::where('is_active', true)->orderBy('sort_order')->get();
        $recentAuditLogs = SubscriptionAuditLog::with(['vendor:id,name,slug', 'actor:id,name,email'])
            ->latest('created_at')
            ->limit(25)
            ->get();

        // Calculate KPI stats
        $stats = [
            'total' => Vendor::withoutGlobalScopes()->count(),
            'active' => Subscription::withoutTenantQuery()->where('status', SubscriptionStatus::Active)->count(),
            'trialing' => Subscription::withoutTenantQuery()->where('status', SubscriptionStatus::Trialing)->count(),
            'expired_suspended' => Subscription::withoutTenantQuery()->whereIn('status', [SubscriptionStatus::Expired, SubscriptionStatus::Suspended])->count(),
            'failed_payments' => SubscriptionPayment::withoutGlobalScopes()->where('status', 'failed')->count(),
            'active_overrides' => TenantFeatureOverride::withoutGlobalScopes()->active()->count() + TenantQuotaOverride::withoutGlobalScopes()->active()->count(),
            'active_addons' => VendorSubscriptionAddon::where('status', 'active')->count(),
        ];

        return view('superadmin.subscriptions.index', compact(
            'vendors',
            'plans',
            'availableFeatures',
            'availableQuotas',
            'catalogFeatures',
            'subscriptionAddons',
            'recentAuditLogs',
            'stats'
        ));
    }

    public function updateVendorSubscription(Request $request, Vendor $vendor)
    {
        $validated = $request->validate([
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
            'subscription_status' => 'required|string|in:trialing,active,past_due,grace,cancelled,expired,suspended',
            'subscription_expires_at' => 'nullable|date',
            'custom_plan_notes' => 'nullable|string',
        ]);

        $plan = SubscriptionPlan::find($validated['subscription_plan_id']);
        $newExpiry = $validated['subscription_expires_at'] ? Carbon::parse($validated['subscription_expires_at']) : null;
        $status = SubscriptionStatus::tryFrom($validated['subscription_status']) ?? SubscriptionStatus::Active;

        $subscriptionService = app(SubscriptionService::class);
        $subscription = $vendor->subscription ?? $subscriptionService->getOrCreateForVendor($vendor);

        $oldStatus = $subscription->status->value;

        $metadata = $subscription->metadata ?? [];
        unset(
            $metadata['pending_downgrade_plan_id'],
            $metadata['pending_downgrade_plan_name'],
            $metadata['pending_downgrade_scheduled_at'],
            $metadata['pending_downgrade_effective_at'],
            $metadata['pending_downgrade_reason']
        );

        $subscription->update([
            'subscription_plan_id' => $plan->id,
            'status' => $status,
            'current_period_end' => $newExpiry,
            'grace_ends_at' => $status === SubscriptionStatus::Grace ? ($vendor->grace_ends_at ?? now()->addDays(3)) : null,
            'cancelled_at' => $status === SubscriptionStatus::Cancelled ? ($vendor->cancelled_at ?? now()) : null,
            'metadata' => $metadata,
        ]);

        $subscription->recordLifecycleEvent(
            event: 'superadmin_granted',
            toStatus: $status,
            fromStatus: SubscriptionStatus::tryFrom($oldStatus),
            reason: $validated['custom_plan_notes'] ?: 'Upgraded/Modified on Free or Administrative basis by SuperAdmin',
            actor: Auth::user(),
            actorType: 'superadmin',
            toPlanId: $plan->id
        );

        $subscriptionService->syncVendor($subscription);
        if (isset($validated['custom_plan_notes'])) {
            $vendor->custom_plan_notes = $validated['custom_plan_notes'];
            $vendor->saveQuietly();
        }

        app(SecurityAuditService::class)->logSubscriptionChange(
            vendor: $vendor,
            oldStatus: $oldStatus,
            newStatus: $status->value,
            planSlug: $plan->slug,
            actor: Auth::user()
        );

        return back()->with('success', __(':name-ի բաժանորդագրությունը թարմացվեց։', ['name' => $vendor->name]));
    }

    /**
     * Terminate / cancel and delete a vendor's active subscription.
     */
    public function destroyVendorSubscription(
        Vendor $vendor,
        SubscriptionAuditService $auditService
    ): RedirectResponse {
        $subscription = $vendor->subscription;
        $previous = $subscription ? $subscription->toArray() : [
            'vendor_id' => $vendor->id,
            'subscription_status' => $vendor->subscription_status,
        ];

        if ($subscription) {
            $auditService->record(
                actionType: 'subscription.deleted',
                entityType: Subscription::class,
                entityId: $subscription->id,
                previousValues: $previous,
                actor: Auth::user()
            );
            $subscription->delete();
        }

        $vendor->update([
            'subscription_plan_id' => null,
            'subscription_status' => 'cancelled',
            'subscription_expires_at' => null,
            'trial_ends_at' => null,
            'cancelled_at' => now(),
        ]);

        app(SecurityAuditService::class)->record(
            event: SecurityAuditLog::EVENT_SUBSCRIPTION_CHANGED,
            action: "Terminated and removed subscription for vendor [{$vendor->name}] (ID: {$vendor->id}).",
            actor: Auth::user(),
            targetType: Vendor::class,
            targetId: $vendor->id
        );

        return back()->with('success', __('Գործընկերոջ բաժանորդագրությունը հաջողությամբ չեղարկվեց և հեռացվեց։'));
    }

    /**
     * Administratively suspend vendor subscription (mandatory reason required).
     */
    public function suspendVendorSubscription(Request $request, Vendor $vendor)
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $subscriptionService = app(SubscriptionService::class);
        $subscription = $vendor->subscription ?? $subscriptionService->getOrCreateForVendor($vendor);

        $subscriptionService->suspend($subscription, reason: $validated['reason'], actor: Auth::user());

        app(SecurityAuditService::class)->logVendorSuspension($vendor, reason: $validated['reason'], actor: Auth::user());

        return back()->with('success', __(':name-ի բաժանորդագրությունը կասեցվեց։', ['name' => $vendor->name]));
    }

    /**
     * Administratively restore vendor subscription (mandatory reason required).
     */
    public function restoreVendorSubscription(Request $request, Vendor $vendor)
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $subscriptionService = app(SubscriptionService::class);
        $subscription = $vendor->subscription ?? $subscriptionService->getOrCreateForVendor($vendor);

        $subscriptionService->restore($subscription, reason: $validated['reason'], actor: Auth::user());

        app(SecurityAuditService::class)->logSubscriptionChange(
            vendor: $vendor,
            oldStatus: 'suspended',
            newStatus: 'active',
            planSlug: $subscription->plan?->slug,
            actor: Auth::user()
        );

        return back()->with('success', __(':name-ի բաժանորդագրությունը հաջողությամբ վերականգնվեց։', ['name' => $vendor->name]));
    }

    /**
     * View subscription lifecycle events history for a vendor.
     */
    public function vendorLifecycleHistory(Vendor $vendor)
    {
        $subscription = $vendor->subscription ?? app(SubscriptionService::class)->getOrCreateForVendor($vendor);
        $events = SubscriptionLifecycleEvent::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->latest('created_at')
            ->paginate(20);

        if (request()->wantsJson()) {
            return response()->json($events);
        }

        return view('superadmin.subscriptions.lifecycle', compact('vendor', 'subscription', 'events'));
    }

    /**
     * View real-time quota usage and active overrides for a vendor.
     */
    public function vendorUsageReporting(Vendor $vendor, QuotaService $quotaService)
    {
        $subscription = $vendor->subscription ?? app(SubscriptionService::class)->getOrCreateForVendor($vendor);
        $allQuotas = QuotaCatalog::all();

        $quotaStatuses = [];
        foreach ($allQuotas as $key) {
            $quotaStatuses[$key] = $quotaService->getQuotaStatus($vendor, $key);
        }

        $activeQuotaOverrides = TenantQuotaOverride::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->active()
            ->get();

        $activeFeatureOverrides = TenantFeatureOverride::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->active()
            ->get();

        if (request()->wantsJson()) {
            return response()->json([
                'vendor' => $vendor->only('id', 'name', 'slug'),
                'quotas' => collect($quotaStatuses)->map(fn ($q) => $q->toArray()),
                'quota_overrides' => $activeQuotaOverrides,
                'feature_overrides' => $activeFeatureOverrides,
            ]);
        }

        return view('superadmin.subscriptions.usage', compact('vendor', 'subscription', 'quotaStatuses', 'activeQuotaOverrides', 'activeFeatureOverrides'));
    }

    /**
     * Global Payment & Invoice History with failed payment review.
     */
    public function allPaymentsIndex(Request $request)
    {
        $query = SubscriptionPayment::withoutGlobalScopes()->with(['vendor:id,name,slug,email', 'plan:id,name,slug']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'ilike', "%{$search}%")
                    ->orWhereHas('vendor', fn ($vq) => $vq->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"));
            });
        }

        $payments = $query->latest('id')->paginate(25)->withQueryString();

        $stats = [
            'total_payments' => SubscriptionPayment::withoutGlobalScopes()->count(),
            'completed' => SubscriptionPayment::withoutGlobalScopes()->whereIn('status', ['paid', 'completed'])->count(),
            'failed' => SubscriptionPayment::withoutGlobalScopes()->where('status', 'failed')->count(),
            'pending' => SubscriptionPayment::withoutGlobalScopes()->where('status', 'pending')->count(),
        ];

        return view('superadmin.subscriptions.payments', compact('payments', 'stats'));
    }

    /**
     * Subscription and Security Administrative Audit Logs.
     */
    public function subscriptionAuditLogs(Request $request)
    {
        $query = SecurityAuditLog::withoutGlobalScopes()
            ->whereIn('event', [
                SecurityAuditLog::EVENT_SUBSCRIPTION_CHANGED,
                SecurityAuditLog::EVENT_VENDOR_SUSPENDED,
                SecurityAuditLog::EVENT_PAYMENT_STATUS_CHANGED,
                SecurityAuditLog::EVENT_SECURITY_CONFIG_CHANGED,
            ])
            ->with(['vendor:id,name,slug', 'user:id,name,email']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'ilike', "%{$search}%")
                    ->orWhereHas('vendor', fn ($vq) => $vq->where('name', 'ilike', "%{$search}%"));
            });
        }

        $logs = $query->latest('created_at')->paginate(25)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($logs);
        }

        return view('superadmin.subscriptions.audit_logs', compact('logs'));
    }

    public function storeVendorPayment(Request $request, Vendor $vendor)
    {
        $validated = $request->validate([
            'subscription_plan_id' => 'nullable|exists:subscription_plans,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'invoice_number' => 'nullable|string',
            'period_start' => 'required|date',
            'period_end' => 'required|date',
            'status' => 'required|string|in:paid,completed,pending,failed',
            'notes' => 'nullable|string',
        ]);

        $subscriptionService = app(SubscriptionService::class);
        $subscription = $vendor->subscription ?? $subscriptionService->getOrCreateForVendor($vendor);

        $paymentStatus = in_array($validated['status'], ['paid', 'completed'], true) ? 'completed' : $validated['status'];
        $targetPlanId = $validated['subscription_plan_id'] ?? $vendor->subscription_plan_id;
        $targetPlan = SubscriptionPlan::find($targetPlanId) ?? $vendor->plan ?? SubscriptionPlan::find($vendor->subscription_plan_id);

        $subPayment = SubscriptionPayment::create([
            'vendor_id' => $vendor->id,
            'subscription_id' => $subscription->id,
            'subscription_plan_id' => $targetPlan?->id ?? $vendor->subscription_plan_id,
            'amount' => $validated['amount'],
            'currency' => Vendor::normalizeCurrencyCode($vendor->getRawOriginal('currency') ?: $vendor->currency),
            'payment_method' => $validated['payment_method'],
            'invoice_number' => $validated['invoice_number'] ?? 'INV-'.strtoupper(Str::random(6)),
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'status' => $paymentStatus,
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($request->has('extend_subscription') && $paymentStatus === 'completed') {
            if ($targetPlan) {
                $subscriptionService->activate($subscription, $targetPlan, $subPayment, actor: Auth::user());
            }
        } elseif ($paymentStatus === 'failed') {
            $subscriptionService->handlePaymentFailure($subscription, $subPayment);
        }

        app(SecurityAuditService::class)->logPaymentStatusChange(
            orderOrPaymentId: $subPayment->id,
            oldStatus: 'new',
            newStatus: $paymentStatus,
            vendor: $vendor,
            metadata: ['amount' => $validated['amount'], 'method' => $validated['payment_method']]
        );

        return back()->with('success', __('Վճարման գրանցումը հաջողությամբ պահպանվեց։'));
    }

    /**
     * Confirm a pending offline/online subscription payment and upgrade/renew subscription.
     */
    public function confirmSubscriptionPayment(SubscriptionPayment $payment, SubscriptionService $subscriptionService)
    {
        $vendor = $payment->vendor;
        if (! $vendor) {
            return back()->with('error', __('Գործընկերը չի գտնվել:'));
        }

        $subscription = $vendor->subscription ?? $subscriptionService->getOrCreateForVendor($vendor);
        $targetPlan = $payment->plan ?? SubscriptionPlan::find($payment->subscription_plan_id) ?? $vendor->plan;

        $payment->update([
            'status' => 'completed',
        ]);

        $attempt = PaymentAttempt::where('subscription_id', $payment->id)->latest()->first();

        $subscriptionService->renew(
            subscription: $subscription,
            payment: $payment,
            attempt: $attempt ?? new PaymentAttempt([
                'uuid' => (string) Str::uuid(),
                'vendor_id' => $vendor->id,
                'gateway' => $payment->payment_method ?? 'bank_transfer',
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'merchant_reference' => $payment->invoice_number,
                'provider_transaction_id' => $payment->invoice_number,
                'status' => PaymentStatus::Paid,
            ]),
            actor: Auth::user()
        );

        app(SecurityAuditService::class)->logPaymentStatusChange(
            orderOrPaymentId: $payment->id,
            oldStatus: 'pending',
            newStatus: 'completed',
            vendor: $vendor,
            metadata: ['amount' => $payment->amount, 'method' => $payment->payment_method, 'confirmed_by' => Auth::id()]
        );

        return back()->with('success', __('Offline վճարումը հաջողությամբ հաստատվեց: Բաժանորդագրությունն ակտիվացվել/թարմացվել է:'));
    }

    public function storeFeatureOverride(Request $request, Vendor $vendor)
    {
        $validated = $request->validate([
            'feature_key' => 'required|string|max:100',
            'is_enabled' => 'nullable|boolean',
            'value_type' => 'nullable|string|in:BOOLEAN,LIMIT,USAGE,CONFIGURATION,ENUM,METERED',
            'limit_value' => 'nullable|integer|min:-1',
            'usage_quota' => 'nullable|integer|min:-1',
            'config_value' => 'nullable',
            'reason' => 'required|string|min:3|max:1000',
            'scope' => 'nullable|string|max:50',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $enabled = array_key_exists('is_enabled', $validated) && $validated['is_enabled'] !== null
            ? (bool) $validated['is_enabled']
            : true;

        $configVal = $validated['config_value'] ?? null;
        if (is_string($configVal) && $configVal !== '') {
            $decoded = json_decode($configVal, true);
            $configVal = json_last_error() === JSON_ERROR_NONE ? $decoded : ['value' => $configVal];
        }

        $entitlementService = app(EntitlementService::class);
        $entitlementService->setOverride(
            vendor: $vendor,
            featureKey: $validated['feature_key'],
            enabled: $enabled,
            reason: $validated['reason'],
            scope: $validated['scope'] ?? 'global',
            expiresAt: ! empty($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null,
            actor: Auth::user(),
            limitValue: isset($validated['limit_value']) && $validated['limit_value'] !== '' ? (int) $validated['limit_value'] : null,
            usageQuota: isset($validated['usage_quota']) && $validated['usage_quota'] !== '' ? (int) $validated['usage_quota'] : null,
            configValue: is_array($configVal) ? $configVal : null,
            valueType: $validated['value_type'] ?? null
        );

        app(SubscriptionAuditService::class)->record(
            actionType: 'vendor.feature_override.set',
            entityType: Vendor::class,
            entityId: $vendor->id,
            vendor: $vendor,
            newValues: $validated,
            reason: $validated['reason'],
            actor: Auth::user()
        );

        app(SecurityAuditService::class)->record(
            event: SecurityAuditLog::EVENT_SECURITY_CONFIG_CHANGED,
            action: "Set feature override [{$validated['feature_key']}] to ".($enabled ? 'enabled' : 'disabled')." for vendor [{$vendor->name}]. Reason: {$validated['reason']}",
            vendor: $vendor,
            actor: Auth::user(),
            targetType: Vendor::class,
            targetId: $vendor->id,
            metadata: $validated
        );

        return back()->with('success', __('Հատուկ ֆունկցիոնալության փոփոխությունը պահպանվեց։'));
    }

    public function destroyFeatureOverride(Vendor $vendor, string $featureKey)
    {
        $entitlementService = app(EntitlementService::class);
        $entitlementService->revokeOverride($vendor, $featureKey, Auth::user());

        app(SecurityAuditService::class)->record(
            event: SecurityAuditLog::EVENT_SECURITY_CONFIG_CHANGED,
            action: "Revoked feature override [{$featureKey}] for vendor [{$vendor->name}].",
            vendor: $vendor,
            actor: Auth::user(),
            targetType: Vendor::class,
            targetId: $vendor->id
        );

        return back()->with('success', __('Հատուկ ֆունկցիոնալության կարգավորումը հեռացվեց։'));
    }

    public function storeQuotaOverride(Request $request, Vendor $vendor)
    {
        $validated = $request->validate([
            'quota_key' => 'required|string|max:64',
            'quota_limit' => 'required|integer|min:-1',
            'reason' => 'required|string|min:3|max:1000',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $quotaService = app(QuotaService::class);
        $quotaService->setOverride(
            vendor: $vendor,
            quotaKey: $validated['quota_key'],
            limit: (int) $validated['quota_limit'],
            reason: $validated['reason'],
            expiresAt: ! empty($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null,
            actor: Auth::user()
        );

        app(SecurityAuditService::class)->record(
            event: SecurityAuditLog::EVENT_SECURITY_CONFIG_CHANGED,
            action: "Set quota override [{$validated['quota_key']}] to [{$validated['quota_limit']}] for vendor [{$vendor->name}]. Reason: {$validated['reason']}",
            vendor: $vendor,
            actor: Auth::user(),
            targetType: Vendor::class,
            targetId: $vendor->id,
            metadata: $validated
        );

        return back()->with('success', __('Քվոտայի սահմանաչափը հաջողությամբ փոփոխվեց։'));
    }

    public function destroyQuotaOverride(Vendor $vendor, string $quotaKey)
    {
        $quotaService = app(QuotaService::class);
        $quotaService->removeOverride($vendor, $quotaKey);

        app(SecurityAuditService::class)->record(
            event: SecurityAuditLog::EVENT_SECURITY_CONFIG_CHANGED,
            action: "Removed quota override [{$quotaKey}] for vendor [{$vendor->name}].",
            vendor: $vendor,
            actor: Auth::user(),
            targetType: Vendor::class,
            targetId: $vendor->id
        );

        return back()->with('success', __('Քվոտայի հատուկ սահմանաչափը հեռացվեց։'));
    }

    public function resetUsageMeter(Vendor $vendor, string $quotaKey)
    {
        $quotaService = app(QuotaService::class);
        $quotaService->resetMeter($vendor, $quotaKey, actor: Auth::user());

        app(SecurityAuditService::class)->record(
            event: SecurityAuditLog::EVENT_SUBSCRIPTION_CHANGED,
            action: "Reset usage meter [{$quotaKey}] for vendor [{$vendor->name}].",
            vendor: $vendor,
            actor: Auth::user(),
            targetType: Vendor::class,
            targetId: $vendor->id
        );

        return back()->with('success', __('Օգտագործման հաշվիչը զրոյացվեց։'));
    }

    public function settingsIndex()
    {
        $settings = SystemSetting::getAll();
        $user = auth()->user();

        $setupSecret = $user->two_factor_secret ?: TwoFactorAuthService::generateSecretKey();
        $otpAuthUri = TwoFactorAuthService::getOtpAuthUri($user, $setupSecret);
        $qrCodeUrl = app(QrCodeService::class)->generateDataUri($otpAuthUri, 200);
        $paymentGatewaySettings = SystemSetting::getPaymentGatewaySettings();

        return view('superadmin.settings.index', compact('settings', 'user', 'setupSecret', 'otpAuthUri', 'qrCodeUrl', 'paymentGatewaySettings'));
    }

    public function updateSettings(UpdateSystemSettingsRequest $request)
    {
        $validated = $request->validated();

        // File uploads for branding assets
        $fileMap = [
            'logo_light_file' => 'site_logo_light',
            'logo_dark_file' => 'site_logo_dark',
            'favicon_file' => 'site_favicon',
            'og_image_file' => 'seo_og_image',
        ];

        foreach ($fileMap as $inputName => $settingKey) {
            $removeFlag = 'remove_'.str_replace(['site_', 'seo_'], '', $settingKey);
            if ($request->boolean($removeFlag)) {
                $oldPath = SystemSetting::get($settingKey);
                if ($oldPath && str_starts_with($oldPath, '/storage/system/')) {
                    $relative = ltrim(str_replace('/storage/', '', $oldPath), '/');
                    if (Storage::disk('public')->exists($relative)) {
                        Storage::disk('public')->delete($relative);
                    }
                }
                SystemSetting::set($settingKey, null, 'branding');
                unset($validated[$settingKey]);
            }

            if ($request->hasFile($inputName) && $request->file($inputName)->isValid()) {
                try {
                    $uploadedFile = $request->file($inputName);
                    $oldPath = SystemSetting::get($settingKey);
                    if ($oldPath && str_starts_with($oldPath, '/storage/system/')) {
                        $relative = ltrim(str_replace('/storage/', '', $oldPath), '/');
                        if (Storage::disk('public')->exists($relative)) {
                            Storage::disk('public')->delete($relative);
                        }
                    }

                    $ext = strtolower($uploadedFile->getClientOriginalExtension());
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = $finfo ? finfo_file($finfo, $uploadedFile->getRealPath()) : $uploadedFile->getMimeType();

                    $uuid = (string) Str::uuid();
                    $filename = "{$uuid}.{$ext}";
                    $tempClean = tempnam(sys_get_temp_dir(), 'sys_img_');
                    $imageProcessor = app(ImageProcessor::class);
                    $imageProcessor->reencodeAndStripMetadata($uploadedFile->getRealPath(), $mime, $tempClean);

                    $stored = Storage::disk('public')->putFileAs('system', new File($tempClean), $filename);
                    @unlink($tempClean);

                    if ($stored) {
                        $validated[$settingKey] = '/storage/'.$stored;
                    }
                } catch (\Throwable $e) {
                    Log::error("System setting upload failed for {$inputName}: ".$e->getMessage());
                }
            }

            unset($validated[$inputName]);
        }

        foreach ($validated as $key => $value) {
            $group = match (true) {
                str_starts_with($key, 'contact_') => 'contact',
                str_starts_with($key, 'social_') => 'social',
                str_starts_with($key, 'telegram_') => 'telegram',
                str_starts_with($key, 'site_') || str_starts_with($key, 'footer_') => 'branding',
                str_starts_with($key, 'seo_') => 'seo',
                default => 'landing',
            };
            SystemSetting::set($key, $value, $group);
        }

        return back()->with('success', __('Համակարգի, բրենդինգի և SEO կարգավորումները հաջողությամբ պահպանվեցին։'));
    }

    public function editVendor(Vendor $vendor)
    {
        $vendor->load(['locations', 'users.location', 'menuTemplate', 'plan']);
        $templates = MenuTemplate::all();
        $plans = SubscriptionPlan::where('is_active', true)->get();

        $notificationStats = [
            'total_emails' => EmailLog::where('vendor_id', $vendor->id)->count(),
            'delivered' => EmailLog::where('vendor_id', $vendor->id)->whereIn('status', [EmailLog::STATUS_DELIVERED, EmailLog::STATUS_SENT])->count(),
            'failed' => EmailLog::where('vendor_id', $vendor->id)->where('status', EmailLog::STATUS_FAILED)->count(),
            'queued' => EmailLog::where('vendor_id', $vendor->id)->whereIn('status', [EmailLog::STATUS_QUEUED, EmailLog::STATUS_PROCESSING])->count(),
            'recent_logs' => EmailLog::where('vendor_id', $vendor->id)->latest()->take(10)->get(),
        ];

        $featureAuditLogs = SecurityAuditLog::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('event', SecurityAuditLog::EVENT_SECURITY_CONFIG_CHANGED)
            ->where('metadata->config_name', 'reservation_and_preorder_features')
            ->latest('created_at')
            ->take(10)
            ->get();

        return view('superadmin.vendor_edit', compact('vendor', 'templates', 'plans', 'notificationStats', 'featureAuditLogs'));
    }

    public function updateVendor(Request $request, Vendor $vendor)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|alpha_dash|unique:vendors,slug,'.$vendor->id,
            'type' => 'required|string|in:restaurant,cafe,hotel',
            'custom_domain' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'currency' => ['required', 'string', Rule::in(['AMD', '֏', 'USD', 'EUR', 'RUB', 'GEL', 'GBP', 'AED', 'CNY', 'KZT', 'TRY'])],
            'timezone' => ['nullable', 'string', 'max:50', Rule::in(\DateTimeZone::listIdentifiers())],
            'weight_unit' => ['nullable', 'string', Rule::in(['g', 'kg', 'oz', 'lb'])],
            'volume_unit' => ['nullable', 'string', Rule::in(['ml', 'l', 'fl_oz', 'fl oz'])],
            'tax_id' => 'nullable|string|max:50',
            'legal_address' => 'nullable|string|max:500',
            'operating_address' => 'nullable|string|max:500',
            'director_name' => 'nullable|string|max:255',
            'director_phone' => 'nullable|string|max:50',
            'contact_person_name' => 'nullable|string|max:255',
            'contact_person_phone' => 'nullable|string|max:50',
            'menu_template_id' => 'required|exists:menu_templates,id',
            'subscription_plan_id' => 'nullable|exists:subscription_plans,id',
            'primary_color' => 'nullable|string|max:20',
            'secondary_color' => 'nullable|string|max:20',
            'theme_mode' => 'nullable|string|in:light,dark,auto',
            'wifi_ssid' => 'nullable|string|max:100',
            'wifi_password' => 'nullable|string|max:100',
            'working_hours' => 'nullable|string|max:255',
            'service_fee_enabled' => 'nullable|boolean',
            'service_fee_type' => 'nullable|string|in:percentage,fixed',
            'service_fee_value' => 'nullable|numeric|min:0',
            'delivery_enabled' => 'nullable|boolean',
            'delivery_fee' => 'nullable|numeric|min:0',
            'delivery_min_amount' => 'nullable|numeric|min:0',
            'takeaway_enabled' => 'nullable|boolean',
            'takeaway_min_amount' => 'nullable|numeric|min:0',
            'allow_whatsapp_orders' => 'nullable|boolean',
        ]);

        if (! empty($validated['custom_domain'])) {
            $domain = preg_replace('#^https?://#i', '', trim($validated['custom_domain']));
            $domain = rtrim(explode('/', $domain)[0], '/');
            $validated['custom_domain'] = strtolower($domain);
        } else {
            $validated['custom_domain'] = null;
        }

        $validated['slug'] = Str::slug($validated['slug']);
        $validated['currency'] = Vendor::normalizeCurrencyCode($validated['currency']);
        $validated['service_fee_enabled'] = $request->boolean('service_fee_enabled');
        $validated['delivery_enabled'] = $request->boolean('delivery_enabled');
        $validated['takeaway_enabled'] = $request->boolean('takeaway_enabled');
        $validated['allow_whatsapp_orders'] = $request->boolean('allow_whatsapp_orders');

        if (! empty($validated['subscription_plan_id'])) {
            $plan = SubscriptionPlan::find($validated['subscription_plan_id']);
            if ($plan) {
                $validated['subscription_plan'] = $plan->slug;
                $subscriptionService = app(SubscriptionService::class);
                $subscription = $vendor->subscription ?? $subscriptionService->getOrCreateForVendor($vendor);
                if ((int) $subscription->subscription_plan_id !== (int) $plan->id) {
                    $subscriptionService->changePlan($subscription, $plan, reason: 'SuperAdmin updated vendor profile', actor: Auth::user());
                }
            }
        }

        $vendor->update($validated);

        return back()->with('success', __('Գործընկերոջ տվյալները և հղումները հաջողությամբ թարմացվեցին։'));
    }

    /**
     * Update SuperAdmin notification and reservation restrictions for a vendor.
     */
    public function updateVendorNotificationRestrictions(Request $request, Vendor $vendor): RedirectResponse
    {
        $permittedMode = $request->input('permitted_mode', Vendor::RESERVATION_MODE_RESERVATION_AND_PREORDER);
        if (! in_array($permittedMode, [
            Vendor::RESERVATION_MODE_DISABLED,
            Vendor::RESERVATION_MODE_RESERVATION_ONLY,
            Vendor::RESERVATION_MODE_RESERVATION_AND_PREORDER,
        ], true)) {
            $permittedMode = Vendor::RESERVATION_MODE_RESERVATION_AND_PREORDER;
        }

        $reservationsEnabled = $request->has('reservations_enabled')
            ? $request->boolean('reservations_enabled')
            : ($permittedMode !== Vendor::RESERVATION_MODE_DISABLED);

        $preordersEnabled = $request->has('preorders_enabled')
            ? $request->boolean('preorders_enabled')
            : ($permittedMode === Vendor::RESERVATION_MODE_RESERVATION_AND_PREORDER);

        // If reservations are disabled, preorders must be disabled
        if (! $reservationsEnabled) {
            $preordersEnabled = false;
            $permittedMode = Vendor::RESERVATION_MODE_DISABLED;
        } elseif (! $preordersEnabled && $permittedMode === Vendor::RESERVATION_MODE_RESERVATION_AND_PREORDER) {
            $permittedMode = Vendor::RESERVATION_MODE_RESERVATION_ONLY;
        }

        $restrictions = array_merge($vendor->superadmin_notification_restrictions, [
            'permitted_mode' => $permittedMode,
            'reservations_enabled' => $reservationsEnabled,
            'preorders_enabled' => $preordersEnabled,
            'reservation_emails_enabled' => $request->boolean('reservation_emails_enabled'),
            'preorder_emails_enabled' => $request->boolean('preorder_emails_enabled'),
            'customer_reminders_enabled' => $request->boolean('customer_reminders_enabled'),
            'restaurant_reminders_enabled' => $request->boolean('restaurant_reminders_enabled'),
            'template_customization_allowed' => $request->boolean('template_customization_allowed'),
        ]);

        $vendor->update([
            'superadmin_notification_restrictions' => $restrictions,
        ]);

        // Audit Logging: Record who changed feature configuration and when
        app(SecurityAuditService::class)->logSecurityConfigChange(
            vendor: $vendor,
            configName: 'reservation_and_preorder_features',
            summary: "Super Admin updated reservation features: mode={$permittedMode}, reservations=".($reservationsEnabled ? 'enabled' : 'disabled').', preorders='.($preordersEnabled ? 'enabled' : 'disabled'),
            actor: Auth::user(),
            metadata: [
                'permitted_mode' => $permittedMode,
                'reservations_enabled' => $reservationsEnabled,
                'preorders_enabled' => $preordersEnabled,
                'restrictions' => $restrictions,
            ]
        );

        // Invalidate cached tenant data
        TenantCache::invalidateAll($vendor);

        return back()->with('success', __('Գործընկերոջ ամրագրումների և ծանուցումների սահմանափակումները հաջողությամբ պահպանվեցին:'));
    }

    public function storeVendorLocation(Request $request, Vendor $vendor)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'whatsapp_number' => 'nullable|string|max:50',
            'wifi_ssid' => 'nullable|string|max:100',
            'wifi_password' => 'nullable|string|max:100',
            'working_hours' => 'nullable|string|max:255',
            'table_count' => 'required|integer|min:1|max:500',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'allow_dine_in_orders' => 'nullable|boolean',
            'allow_whatsapp_orders' => 'nullable|boolean',
        ]);

        $slug = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']);
        if (Location::where('vendor_id', $vendor->id)->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::random(4);
        }

        Location::create([
            'vendor_id' => $vendor->id,
            'name' => $validated['name'],
            'slug' => $slug,
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'whatsapp_number' => $validated['whatsapp_number'] ?? null,
            'wifi_ssid' => $validated['wifi_ssid'] ?? null,
            'wifi_password' => $validated['wifi_password'] ?? null,
            'working_hours' => $validated['working_hours'] ?? null,
            'table_count' => $validated['table_count'],
            'minimum_order_amount' => $validated['minimum_order_amount'] ?? 0,
            'allow_dine_in_orders' => $request->boolean('allow_dine_in_orders', true),
            'allow_whatsapp_orders' => $request->boolean('allow_whatsapp_orders', true),
            'is_active' => true,
        ]);

        return back()->with('success', __('Նոր մասնաճյուղը հաջողությամբ ավելացվեց։'));
    }

    public function updateVendorLocation(Request $request, Vendor $vendor, Location $location)
    {
        abort_if($location->vendor_id !== $vendor->id, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'whatsapp_number' => 'nullable|string|max:50',
            'wifi_ssid' => 'nullable|string|max:100',
            'wifi_password' => 'nullable|string|max:100',
            'working_hours' => 'nullable|string|max:255',
            'table_count' => 'required|integer|min:1|max:500',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'allow_dine_in_orders' => 'nullable|boolean',
            'allow_whatsapp_orders' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if (! empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['slug']);
        }
        $validated['allow_dine_in_orders'] = $request->boolean('allow_dine_in_orders');
        $validated['allow_whatsapp_orders'] = $request->boolean('allow_whatsapp_orders');
        $validated['is_active'] = $request->boolean('is_active', true);

        $location->update($validated);

        return back()->with('success', __('«:name» մասնաճյուղի տվյալները հաջողությամբ թարմացվեցին։', ['name' => $location->name]));
    }

    public function destroyVendorLocation(Vendor $vendor, Location $location)
    {
        abort_if($location->vendor_id !== $vendor->id, 403);

        if ($vendor->locations()->count() <= 1) {
            return back()->with('error', __('Հնարավոր չէ ջնջել վերջին մնացած մասնաճյուղը։ Գործընկերը պետք է ունենա առնվազն մեկ ակտիվ մասնաճյուղ։'));
        }

        User::where('location_id', $location->id)->update(['location_id' => null]);

        $locationName = $location->name;
        $location->delete();

        return back()->with('success', __('«:name» մասնաճյուղը հաջողությամբ ջնջվեց։', ['name' => $locationName]));
    }

    public function updateVendorLocationTables(Request $request, Vendor $vendor, Location $location)
    {
        abort_if($location->vendor_id !== $vendor->id, 403);

        $validated = $request->validate([
            'table_count' => 'required|integer|min:1|max:500',
        ]);

        $location->update(['table_count' => $validated['table_count']]);

        return back()->with('success', __('«:name» մասնաճյուղի սեղանների քանակը փոխվեց՝ :count։', ['name' => $location->name, 'count' => $validated['table_count']]));
    }

    public function storeVendorUser(Request $request, Vendor $vendor)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|string|in:vendor_owner,branch_manager,manager,waiter,kitchen_staff,staff',
            'location_id' => 'nullable|exists:locations,id',
            'phone' => 'nullable|string|max:50',
            'password' => 'required|string|min:6',
        ]);

        if (! empty($validated['location_id'])) {
            $loc = Location::find($validated['location_id']);
            abort_if(! $loc || $loc->vendor_id !== $vendor->id, 403, 'Invalid location assignment');
        }

        User::create([
            'vendor_id' => $vendor->id,
            'location_id' => $validated['location_id'] ?? null,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', __('Նոր աշխատակիցը/օգտատերը հաջողությամբ ավելացվեց։'));
    }

    public function updateVendorUser(Request $request, Vendor $vendor, User $user)
    {
        abort_if($user->vendor_id !== $vendor->id, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'role' => 'required|string|in:vendor_owner,branch_manager,manager,waiter,kitchen_staff,staff',
            'location_id' => 'nullable|exists:locations,id',
            'phone' => 'nullable|string|max:50',
            'password' => 'nullable|string|min:6',
        ]);

        if (! empty($validated['location_id'])) {
            $loc = Location::find($validated['location_id']);
            abort_if(! $loc || $loc->vendor_id !== $vendor->id, 403, 'Invalid location assignment');
        }

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'location_id' => $validated['location_id'] ?? null,
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        return back()->with('success', __('«:name» օգտատիրոջ տվյալները հաջողությամբ թարմացվեցին։', ['name' => $user->name]));
    }

    public function destroyVendorUser(Vendor $vendor, User $user)
    {
        abort_if($user->vendor_id !== $vendor->id, 403);

        if ($user->id === auth()->id()) {
            return back()->with('error', __('Հնարավոր չէ ջնջել ընթացիկ մուտք գործած հաշիվը։'));
        }

        if ($vendor->users()->count() <= 1) {
            return back()->with('error', __('Հնարավոր չէ ջնջել գործընկերոջ վերջին մնացած օգտատիրոջը։'));
        }

        $userName = $user->name;
        $user->delete();

        return back()->with('success', __('«:name» օգտատերը հաջողությամբ ջնջվեց։', ['name' => $userName]));
    }

    public function updateProfileSecurity(Request $request)
    {
        $user = auth()->user();
        $type = $request->input('action_type', 'password');

        return match ($type) {
            'email' => $this->updateSecurityEmail($request, $user),
            'password' => $this->updateSecurityPassword($request, $user),
            '2fa_enable' => $this->enableTwoFactorSecurity($request, $user),
            '2fa_disable' => $this->disableTwoFactorSecurity($request, $user),
            default => back(),
        };
    }

    protected function updateSecurityEmail(Request $request, User $user)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'two_factor_code' => $user->hasTwoFactorEnabled() ? 'required|string' : 'nullable|string',
        ], [
            'email.unique' => 'Այս էլ․ փոստի հասցեն արդեն գրանցված է համակարգում։',
            'two_factor_code.required' => '2FA անվտանգության կոդը պարտադիր է էլ․ փոստը փոխելու համար։',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Ընթացիկ գաղտնաբառը սխալ է։'])->with('error_type', 'email');
        }

        if ($user->hasTwoFactorEnabled() || $request->filled('two_factor_code')) {
            if (! TwoFactorAuthService::verifyCode($user, $request->input('two_factor_code'))) {
                return back()->withErrors(['two_factor_code' => '2FA անվտանգության կոդը սխալ է կամ ժամկետանց։'])->with('error_type', 'email')->withInput();
            }
        }

        $user->update(['email' => $validated['email']]);

        return back()->with('success', __('Ձեր էլ․ փոստի հասցեն հաջողությամբ թարմացվեց։'));
    }

    protected function updateSecurityPassword(Request $request, User $user)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
            'two_factor_code' => $user->hasTwoFactorEnabled() ? 'required|string' : 'nullable|string',
        ], [
            'password.confirmed' => 'Նոր գաղտնաբառի հաստատումը չի համընկնում։',
            'password.min' => 'Գաղտնաբառը պետք է լինի առնվազն 6 նիշ։',
            'two_factor_code.required' => '2FA անվտանգության կոդը պարտադիր է գաղտնաբառը փոխելու համար։',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Ընթացիկ գաղտնաբառը սխալ է։'])->with('error_type', 'password');
        }

        if ($user->hasTwoFactorEnabled() || $request->filled('two_factor_code')) {
            if (! TwoFactorAuthService::verifyCode($user, $request->input('two_factor_code'))) {
                return back()->withErrors(['two_factor_code' => '2FA անվտանգության կոդը սխալ է կամ ժամկետանց։'])->with('error_type', 'password')->withInput();
            }
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        return back()->with('success', __('Ձեր գաղտնաբառը հաջողությամբ փոխվեց։'));
    }

    protected function enableTwoFactorSecurity(Request $request, User $user)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:email,authenticator',
            'code' => 'required|string|min:6|max:8',
            'secret' => 'nullable|string',
        ]);

        $code = trim($validated['code']);

        if ($validated['type'] === 'authenticator') {
            $secret = $validated['secret'] ?? $user->two_factor_secret;

            if (empty($secret) || ! TwoFactorAuthService::verifyGoogleAuthenticator($secret, $code)) {
                if (! (app()->environment('local', 'testing') && $code === '123456')) {
                    return back()->withErrors(['two_factor_code' => 'Google Authenticator կոդը սխալ է։'])->with('error_type', '2fa');
                }
            }

            $user->update([
                'two_factor_enabled' => true,
                'two_factor_type' => 'authenticator',
                'two_factor_secret' => $secret,
                'two_factor_confirmed_at' => now(),
            ]);
        } else {
            if (! TwoFactorAuthService::verifyEmailCode($user, $code)) {
                if (! (app()->environment('local', 'testing') && $code === '123456')) {
                    return back()->withErrors(['two_factor_code' => 'Էլ․ փոստի կոդը սխալ է կամ ժամկետանց։'])->with('error_type', '2fa');
                }
            }

            $user->update([
                'two_factor_enabled' => true,
                'two_factor_type' => 'email',
                'two_factor_confirmed_at' => now(),
            ]);
        }

        return back()->with('success', __('Երկփուլային նույնականացումը (2FA) հաջողությամբ ակտիվացվեց։'));
    }

    protected function disableTwoFactorSecurity(Request $request, User $user)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Ընթացիկ գաղտնաբառը սխալ է։'])->with('error_type', '2fa_disable');
        }

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_email_code' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return back()->with('success', __('Երկփուլային նույնականացումը (2FA) անջատվեց։'));
    }

    /**
     * Test SuperAdmin Telegram notification connection.
     */
    public function testTelegramConnection(Request $request, TelegramNotificationService $telegramService): JsonResponse
    {
        $chatId = $request->input('chat_id') ?: SystemSetting::get('telegram_admin_chat_id');
        $botToken = $request->input('bot_token') ?: SystemSetting::get('telegram_bot_token');

        if (empty($chatId)) {
            return response()->json([
                'success' => false,
                'message' => 'Խնդրում ենք լրացնել SuperAdmin Chat ID դաշտը թեստային հաղորդագրություն ուղարկելու համար։',
            ]);
        }

        $result = $telegramService->sendTestMessage(
            chatId: (string) $chatId,
            botToken: $botToken,
            topicId: null,
            sourceName: 'SuperAdmin Կառավարման Համակարգ'
        );

        return response()->json($result);
    }

    /**
     * View cross-tenant platform security audit logs (metadata only, no exposed credentials).
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $logs = SecurityAuditLog::withoutGlobalScopes()
            ->with(['vendor:id,name,slug', 'user:id,name,email'])
            ->latest('created_at')
            ->paginate(50);

        return response()->json($logs);
    }

    /**
     * Save visual Plan Builder configuration and create an immutable Plan Version.
     */
    public function savePlanBuilder(
        Request $request,
        SubscriptionPlan $plan,
        PlanVersionService $planVersionService,
        FeatureDependencyService $dependencyService
    ): RedirectResponse|JsonResponse {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'name_translations' => 'nullable|array',
            'description' => 'nullable|string|max:2000',
            'description_translations' => 'nullable|array',
            'price' => 'nullable|numeric|min:0',
            'yearly_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'trial_days' => 'nullable|integer|min:0',
            'duration_days' => 'nullable|integer|min:1',
            'billing_interval' => 'nullable|string|in:monthly,yearly,custom',
            'plan_features' => 'required|array',
            'change_summary' => 'nullable|string|max:1000',
            'migrate_subscribers' => 'nullable|boolean',
        ]);

        $currency = strtoupper($validated['currency'] ?? $plan->currency ?? 'AMD');

        $planUpdates = [];
        if (isset($validated['name'])) {
            $planUpdates['name'] = $validated['name'];
        }
        if (isset($validated['name_translations'])) {
            $planUpdates['name_translations'] = $validated['name_translations'];
        }
        if (array_key_exists('description', $validated)) {
            $planUpdates['description'] = $validated['description'];
        }
        if (isset($validated['description_translations'])) {
            $planUpdates['description_translations'] = $validated['description_translations'];
        }
        if (isset($validated['price'])) {
            $planUpdates['price'] = (float) $validated['price'];
        }
        if (array_key_exists('yearly_price', $validated) && $validated['yearly_price'] !== null) {
            $planUpdates['yearly_price'] = (float) $validated['yearly_price'];
        }
        if (isset($validated['trial_days'])) {
            $planUpdates['trial_days'] = (int) $validated['trial_days'];
        }
        if (isset($validated['duration_days'])) {
            $planUpdates['duration_days'] = (int) $validated['duration_days'];
        }
        if (isset($validated['billing_interval'])) {
            $planUpdates['billing_interval'] = $validated['billing_interval'];
        }
        $planUpdates['currency'] = $currency;

        if (! empty($planUpdates)) {
            $plan->update($planUpdates);
        }

        if (isset($validated['price'])) {
            SubscriptionPlanPrice::updateOrCreate(
                ['subscription_plan_id' => $plan->id, 'billing_interval' => 'monthly', 'currency' => $currency],
                ['price' => (float) $validated['price'], 'is_active' => true]
            );
        }

        if (isset($validated['yearly_price']) && is_numeric($validated['yearly_price'])) {
            SubscriptionPlanPrice::updateOrCreate(
                ['subscription_plan_id' => $plan->id, 'billing_interval' => 'yearly', 'currency' => $currency],
                ['price' => (float) $validated['yearly_price'], 'is_active' => true]
            );
        }

        $featurePayload = [];
        $enabledCheckMap = [];
        foreach ($validated['plan_features'] as $featureCode => $cfg) {
            $isEnabled = filter_var($cfg['is_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $isUnlimited = filter_var($cfg['is_unlimited'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $limitVal = isset($cfg['limit_value']) && $cfg['limit_value'] !== '' ? (int) $cfg['limit_value'] : null;
            if ($isUnlimited) {
                $limitVal = -1;
            }
            $usageQuota = isset($cfg['usage_quota']) && $cfg['usage_quota'] !== '' ? (int) $cfg['usage_quota'] : null;
            if ($isUnlimited && $usageQuota !== null) {
                $usageQuota = -1;
            }
            $configVal = $cfg['config_value'] ?? null;
            if (is_string($configVal) && $configVal !== '') {
                $decoded = json_decode($configVal, true);
                $configVal = json_last_error() === JSON_ERROR_NONE ? $decoded : ['value' => $configVal];
            }

            $featurePayload[$featureCode] = [
                'is_enabled' => $isEnabled,
                'limit_value' => $limitVal,
                'is_unlimited' => $isUnlimited || $limitVal === -1 || $usageQuota === -1,
                'usage_quota' => $usageQuota,
                'config_value' => is_array($configVal) ? $configVal : null,
            ];
            $enabledCheckMap[$featureCode] = $isEnabled;
        }

        $dependencyWarnings = $dependencyService->validateFeatureMap($enabledCheckMap);

        $version = $planVersionService->createVersion(
            plan: $plan->fresh(),
            featureConfigs: $featurePayload,
            changeSummary: $validated['change_summary'] ?? 'Updated via Visual Plan Builder',
            migrateExistingSubscribers: $request->boolean('migrate_subscribers', false),
            actor: Auth::user()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'version' => $version,
                'dependency_warnings' => $dependencyWarnings,
            ]);
        }

        $message = __('Plan Builder configuration saved as Version v:ver.', ['ver' => $version->version_number]);
        if (! empty($dependencyWarnings)) {
            $message .= ' '.__('Note: :count dependency warning(s) detected.', ['count' => count($dependencyWarnings)]);
        }

        return back()->with('success', $message);
    }

    /**
     * Duplicate an existing subscription plan with all its features and limits.
     */
    public function duplicatePlan(Request $request, SubscriptionPlan $plan, PlanVersionService $planVersionService): RedirectResponse
    {
        $validated = $request->validate([
            'new_name' => 'required|string|max:255',
            'new_slug' => 'nullable|string|max:255',
        ]);

        $copy = $planVersionService->duplicatePlan(
            sourcePlan: $plan,
            newName: $validated['new_name'],
            newSlug: $validated['new_slug'] ?? null,
            actor: Auth::user()
        );

        return back()->with('success', __('Plan duplicated successfully as :name.', ['name' => $copy->name]));
    }

    /**
     * Archive a subscription plan safely without breaking existing subscribers.
     */
    public function archivePlan(SubscriptionPlan $plan, PlanVersionService $planVersionService): RedirectResponse
    {
        $planVersionService->archivePlan($plan, Auth::user());

        return back()->with('success', __('Subscription plan archived successfully.'));
    }

    /**
     * Explicitly migrate existing subscribers of a plan to a specific Plan Version.
     */
    public function migratePlanVersion(
        Request $request,
        SubscriptionPlan $plan,
        SubscriptionPlanVersion $version,
        PlanVersionService $planVersionService
    ): RedirectResponse {
        if ((int) $version->subscription_plan_id !== (int) $plan->id) {
            abort(400, 'Version does not belong to the specified plan.');
        }

        $migratedCount = $planVersionService->migrateSubscriptionsToVersion($plan, $version, Auth::user());

        return back()->with('success', __('Migrated :count subscriber(s) to version v:ver.', [
            'count' => $migratedCount,
            'ver' => $version->version_number,
        ]));
    }

    /**
     * Store a new Feature Category.
     */
    public function storeFeatureCategory(Request $request, SubscriptionAuditService $auditService): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:64|unique:feature_categories,code',
            'name_translations' => 'required|array',
            'description_translations' => 'nullable|array',
            'icon' => 'nullable|string|max:64',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $category = FeatureCategory::create([
            'code' => strtolower(trim($validated['code'])),
            'name_translations' => $validated['name_translations'],
            'description_translations' => $validated['description_translations'] ?? [],
            'icon' => $validated['icon'] ?? 'fa-layer-group',
            'sort_order' => (int) ($validated['sort_order'] ?? ((FeatureCategory::max('sort_order') ?? 0) + 1)),
            'is_active' => true,
        ]);

        $auditService->record(
            actionType: 'feature_category.created',
            entityType: FeatureCategory::class,
            entityId: $category->id,
            newValues: $category->toArray(),
            actor: Auth::user()
        );

        return back()->with('success', __('Feature category created successfully.'));
    }

    /**
     * Update an existing Feature Category.
     */
    public function updateFeatureCategory(
        Request $request,
        FeatureCategory $category,
        SubscriptionAuditService $auditService
    ): RedirectResponse {
        $validated = $request->validate([
            'name_translations' => 'required|array',
            'description_translations' => 'nullable|array',
            'icon' => 'nullable|string|max:64',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $previous = $category->toArray();

        $category->update([
            'name_translations' => $validated['name_translations'],
            'description_translations' => $validated['description_translations'] ?? $category->description_translations,
            'icon' => $validated['icon'] ?? $category->icon,
            'sort_order' => (int) ($validated['sort_order'] ?? $category->sort_order),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $category->is_active,
        ]);

        $auditService->record(
            actionType: 'feature_category.updated',
            entityType: FeatureCategory::class,
            entityId: $category->id,
            previousValues: $previous,
            newValues: $category->fresh()->toArray(),
            actor: Auth::user()
        );

        return back()->with('success', __('Feature category updated successfully.'));
    }

    /**
     * Create a new Feature in the Feature Catalog.
     */
    public function storeFeature(Request $request, SubscriptionAuditService $auditService): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:100|unique:features,code',
            'feature_category_id' => 'required|exists:feature_categories,id',
            'feature_type' => 'required|string|in:BOOLEAN,LIMIT,USAGE,CONFIGURATION,ENUM,METERED',
            'name_translations' => 'required|array',
            'description_translations' => 'nullable|array',
            'default_value' => 'nullable',
            'allowed_options' => 'nullable',
            'unit' => 'nullable|string|max:50',
            'reset_period' => 'nullable|string|max:32',
            'sort_order' => 'nullable|integer|min:0',
            'admin_visible' => 'nullable|boolean',
            'vendor_visible' => 'nullable|boolean',
        ]);

        $defaultVal = $validated['default_value'] ?? false;
        if ($validated['feature_type'] === Feature::TYPE_BOOLEAN) {
            $defaultVal = ['value' => filter_var($defaultVal, FILTER_VALIDATE_BOOLEAN)];
        } elseif (in_array($validated['feature_type'], [Feature::TYPE_LIMIT, Feature::TYPE_USAGE, Feature::TYPE_METERED], true)) {
            $defaultVal = ['value' => (int) $defaultVal];
        } else {
            $defaultVal = is_array($defaultVal) ? $defaultVal : ['value' => $defaultVal];
        }

        $allowedOptions = $validated['allowed_options'] ?? null;
        if (is_string($allowedOptions) && $allowedOptions !== '') {
            $allowedOptions = array_values(array_filter(array_map('trim', explode(',', $allowedOptions))));
        }

        $feature = Feature::create([
            'code' => strtolower(trim($validated['code'])),
            'feature_category_id' => (int) $validated['feature_category_id'],
            'feature_type' => $validated['feature_type'],
            'name_translations' => $validated['name_translations'],
            'description_translations' => $validated['description_translations'] ?? [],
            'default_value' => $defaultVal,
            'allowed_options' => is_array($allowedOptions) ? $allowedOptions : null,
            'unit' => $validated['unit'] ?? null,
            'reset_period' => $validated['reset_period'] ?? null,
            'is_active' => true,
            'is_archived' => false,
            'admin_visible' => $request->has('admin_visible') ? $request->boolean('admin_visible') : true,
            'vendor_visible' => $request->has('vendor_visible') ? $request->boolean('vendor_visible') : true,
            'sort_order' => (int) ($validated['sort_order'] ?? ((Feature::max('sort_order') ?? 0) + 1)),
        ]);

        $auditService->record(
            actionType: 'feature.created',
            entityType: Feature::class,
            entityId: $feature->id,
            newValues: $feature->toArray(),
            actor: Auth::user()
        );

        return back()->with('success', __('Feature added to catalog successfully.'));
    }

    /**
     * Update an existing Feature in the Feature Catalog (code is immutable).
     */
    public function updateFeature(
        Request $request,
        Feature $feature,
        SubscriptionAuditService $auditService
    ): RedirectResponse {
        $validated = $request->validate([
            'feature_category_id' => 'required|exists:feature_categories,id',
            'feature_type' => 'required|string|in:BOOLEAN,LIMIT,USAGE,CONFIGURATION,ENUM,METERED',
            'name_translations' => 'required|array',
            'description_translations' => 'nullable|array',
            'default_value' => 'nullable',
            'allowed_options' => 'nullable',
            'unit' => 'nullable|string|max:50',
            'reset_period' => 'nullable|string|max:32',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'admin_visible' => 'nullable|boolean',
            'vendor_visible' => 'nullable|boolean',
        ]);

        $previous = $feature->toArray();

        $defaultVal = $validated['default_value'] ?? $feature->resolveDefaultScalar();
        if ($validated['feature_type'] === Feature::TYPE_BOOLEAN) {
            $defaultVal = ['value' => filter_var($defaultVal, FILTER_VALIDATE_BOOLEAN)];
        } elseif (in_array($validated['feature_type'], [Feature::TYPE_LIMIT, Feature::TYPE_USAGE, Feature::TYPE_METERED], true)) {
            $defaultVal = ['value' => (int) $defaultVal];
        } else {
            $defaultVal = is_array($defaultVal) ? $defaultVal : ['value' => $defaultVal];
        }

        $allowedOptions = $validated['allowed_options'] ?? $feature->allowed_options;
        if (is_string($allowedOptions)) {
            $allowedOptions = $allowedOptions !== ''
                ? array_values(array_filter(array_map('trim', explode(',', $allowedOptions))))
                : null;
        }

        $feature->update([
            'feature_category_id' => (int) $validated['feature_category_id'],
            'feature_type' => $validated['feature_type'],
            'name_translations' => $validated['name_translations'],
            'description_translations' => $validated['description_translations'] ?? $feature->description_translations,
            'default_value' => $defaultVal,
            'allowed_options' => is_array($allowedOptions) ? $allowedOptions : null,
            'unit' => $validated['unit'] ?? $feature->unit,
            'reset_period' => $validated['reset_period'] ?? $feature->reset_period,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $feature->is_active,
            'admin_visible' => $request->has('admin_visible') ? $request->boolean('admin_visible') : $feature->admin_visible,
            'vendor_visible' => $request->has('vendor_visible') ? $request->boolean('vendor_visible') : $feature->vendor_visible,
            'sort_order' => (int) ($validated['sort_order'] ?? $feature->sort_order),
        ]);

        $auditService->record(
            actionType: 'feature.updated',
            entityType: Feature::class,
            entityId: $feature->id,
            previousValues: $previous,
            newValues: $feature->fresh()->toArray(),
            actor: Auth::user()
        );

        return back()->with('success', __('Feature updated successfully.'));
    }

    /**
     * Archive a Feature instead of deleting it when used by subscriptions.
     */
    public function archiveFeature(Feature $feature, SubscriptionAuditService $auditService): RedirectResponse
    {
        $previous = $feature->toArray();

        $feature->update([
            'is_archived' => true,
            'is_active' => false,
            'archived_at' => now(),
        ]);

        $auditService->record(
            actionType: 'feature.archived',
            entityType: Feature::class,
            entityId: $feature->id,
            previousValues: $previous,
            newValues: $feature->fresh()->toArray(),
            actor: Auth::user()
        );

        return back()->with('success', __('Feature archived successfully.'));
    }

    /**
     * Delete a Feature ONLY if it is not used by any active subscription.
     */
    public function destroyFeature(Feature $feature, SubscriptionAuditService $auditService): RedirectResponse
    {
        if ($feature->isUsedByActiveSubscriptions()) {
            return back()->with('error', __('Cannot delete a feature that is used by active subscriptions. Please archive it instead.'));
        }

        $previous = $feature->toArray();
        $featureId = $feature->id;
        $feature->delete();

        $auditService->record(
            actionType: 'feature.deleted',
            entityType: Feature::class,
            entityId: $featureId,
            previousValues: $previous,
            actor: Auth::user()
        );

        return back()->with('success', __('Feature deleted successfully.'));
    }

    /**
     * Add a Feature Dependency rule with circular dependency detection.
     */
    public function storeFeatureDependency(
        Request $request,
        FeatureDependencyService $dependencyService,
        SubscriptionAuditService $auditService
    ): RedirectResponse {
        $validated = $request->validate([
            'feature_code' => 'required|string|max:100',
            'depends_on_feature_code' => 'required|string|max:100|different:feature_code',
        ]);

        try {
            $dep = $dependencyService->addDependency(
                feature: $validated['feature_code'],
                dependsOn: $validated['depends_on_feature_code']
            );

            $auditService->record(
                actionType: 'feature_dependency.created',
                entityType: FeatureDependency::class,
                entityId: $dep->id,
                newValues: $dep->toArray(),
                actor: Auth::user()
            );

            return back()->with('success', __('Feature dependency rule added successfully.'));
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove a Feature Dependency rule.
     */
    public function destroyFeatureDependency(
        FeatureDependency $dependency,
        SubscriptionAuditService $auditService
    ): RedirectResponse {
        $previous = $dependency->toArray();
        $id = $dependency->id;
        $dependency->delete();

        $auditService->record(
            actionType: 'feature_dependency.deleted',
            entityType: FeatureDependency::class,
            entityId: $id,
            previousValues: $previous,
            actor: Auth::user()
        );

        return back()->with('success', __('Feature dependency removed.'));
    }

    /**
     * Create a new Subscription Add-on.
     */
    public function storeAddon(Request $request, SubscriptionAuditService $auditService): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:100|unique:subscription_addons,code',
            'name_translations' => 'required|array',
            'description_translations' => 'nullable|array',
            'addon_type' => 'required|string|in:FEATURE,LIMIT,USAGE',
            'feature_code' => 'required|string|max:100',
            'value_increment' => 'nullable|integer|min:1',
            'monthly_price' => 'required|numeric|min:0',
            'yearly_price' => 'nullable|numeric|min:0',
            'one_time_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'billing_period' => 'required|string|in:monthly,yearly,one_time',
            'compatible_plan_slugs' => 'nullable|array',
        ]);

        $primaryName = $validated['name_translations']['hy']
            ?? $validated['name_translations']['en']
            ?? reset($validated['name_translations']);
        $primaryDesc = $validated['description_translations']['hy']
            ?? $validated['description_translations']['en']
            ?? (isset($validated['description_translations']) ? reset($validated['description_translations']) : null);

        $addon = SubscriptionAddon::create([
            'code' => strtolower(trim($validated['code'])),
            'name' => $primaryName ?: $validated['code'],
            'name_translations' => $validated['name_translations'],
            'description' => $primaryDesc,
            'description_translations' => $validated['description_translations'] ?? [],
            'addon_type' => $validated['addon_type'],
            'billing_type' => $validated['billing_period'],
            'feature_code' => $validated['feature_code'],
            'quantity_value' => isset($validated['value_increment']) ? (int) $validated['value_increment'] : 1,
            'monthly_price' => (float) $validated['monthly_price'],
            'yearly_price' => isset($validated['yearly_price']) && $validated['yearly_price'] !== null ? (float) $validated['yearly_price'] : 0,
            'one_time_price' => isset($validated['one_time_price']) && $validated['one_time_price'] !== null ? (float) $validated['one_time_price'] : 0,
            'currency' => strtoupper($validated['currency'] ?? 'AMD'),
            'compatible_plan_slugs' => $validated['compatible_plan_slugs'] ?? ['basic', 'pro', 'advanced'],
            'is_active' => true,
            'sort_order' => (SubscriptionAddon::max('sort_order') ?? 0) + 1,
        ]);

        $auditService->record(
            actionType: 'addon.created',
            entityType: SubscriptionAddon::class,
            entityId: $addon->id,
            newValues: $addon->toArray(),
            actor: Auth::user()
        );

        return back()->with('success', __('Add-on created successfully.'));
    }

    /**
     * Update an existing Subscription Add-on.
     */
    public function updateAddon(
        Request $request,
        SubscriptionAddon $addon,
        SubscriptionAuditService $auditService
    ): RedirectResponse {
        $validated = $request->validate([
            'name_translations' => 'required|array',
            'description_translations' => 'nullable|array',
            'addon_type' => 'required|string|in:FEATURE,LIMIT,USAGE',
            'feature_code' => 'required|string|max:100',
            'value_increment' => 'nullable|integer|min:1',
            'monthly_price' => 'required|numeric|min:0',
            'yearly_price' => 'nullable|numeric|min:0',
            'one_time_price' => 'nullable|numeric|min:0',
            'billing_period' => 'required|string|in:monthly,yearly,one_time',
            'compatible_plan_slugs' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $previous = $addon->toArray();

        $primaryName = $validated['name_translations']['hy']
            ?? $validated['name_translations']['en']
            ?? reset($validated['name_translations']);
        $primaryDesc = $validated['description_translations']['hy']
            ?? $validated['description_translations']['en']
            ?? (isset($validated['description_translations']) ? reset($validated['description_translations']) : null);

        $addon->update([
            'name' => $primaryName ?: $addon->name,
            'name_translations' => $validated['name_translations'],
            'description' => $primaryDesc ?: $addon->description,
            'description_translations' => $validated['description_translations'] ?? $addon->description_translations,
            'addon_type' => $validated['addon_type'],
            'billing_type' => $validated['billing_period'],
            'feature_code' => $validated['feature_code'],
            'quantity_value' => isset($validated['value_increment']) ? (int) $validated['value_increment'] : $addon->quantity_value,
            'monthly_price' => (float) $validated['monthly_price'],
            'yearly_price' => isset($validated['yearly_price']) && $validated['yearly_price'] !== null ? (float) $validated['yearly_price'] : ($addon->yearly_price ?? 0),
            'one_time_price' => isset($validated['one_time_price']) && $validated['one_time_price'] !== null ? (float) $validated['one_time_price'] : ($addon->one_time_price ?? 0),
            'compatible_plan_slugs' => $validated['compatible_plan_slugs'] ?? $addon->compatible_plan_slugs,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $addon->is_active,
        ]);

        $auditService->record(
            actionType: 'addon.updated',
            entityType: SubscriptionAddon::class,
            entityId: $addon->id,
            previousValues: $previous,
            newValues: $addon->fresh()->toArray(),
            actor: Auth::user()
        );

        return back()->with('success', __('Add-on updated successfully.'));
    }

    /**
     * Delete an existing Subscription Add-on.
     */
    public function destroyAddon(
        SubscriptionAddon $addon,
        SubscriptionAuditService $auditService
    ): RedirectResponse {
        $activeUsageCount = $addon->vendorAddons()->where('status', 'active')->count();

        if ($activeUsageCount > 0) {
            return back()->with('error', __('Չի կարող հեռացվել․ հավելումն ակտիվ է :count գործընկերների մոտ։', ['count' => $activeUsageCount]));
        }

        $auditService->record(
            actionType: 'addon.deleted',
            entityType: SubscriptionAddon::class,
            entityId: $addon->id,
            previousValues: $addon->toArray(),
            actor: Auth::user()
        );

        $addon->vendorAddons()->delete();
        $addon->delete();

        return back()->with('success', __('Հավելումը հաջողությամբ հեռացվեց։'));
    }

    /**
     * Assign a Subscription Add-on to a Vendor.
     */
    public function assignVendorAddon(
        Request $request,
        Vendor $vendor,
        SubscriptionAddonService $addonService
    ): RedirectResponse {
        $validated = $request->validate([
            'subscription_addon_id' => 'required|exists:subscription_addons,id',
            'quantity' => 'nullable|integer|min:1|max:100',
            'billing_period' => 'nullable|string|in:monthly,yearly,one_time',
            'expires_at' => 'nullable|date|after:now',
            'notes' => 'nullable|string|max:1000',
        ]);

        $addon = SubscriptionAddon::findOrFail($validated['subscription_addon_id']);

        try {
            $addonService->assignAddon(
                vendor: $vendor,
                addon: $addon,
                quantity: (int) ($validated['quantity'] ?? 1),
                billingPeriod: $validated['billing_period'] ?? $addon->billing_period,
                expiresAt: ! empty($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null,
                notes: $validated['notes'] ?? null,
                actor: Auth::user()
            );

            return back()->with('success', __('Add-on assigned to vendor successfully.'));
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Cancel an active Vendor Add-on.
     */
    public function cancelVendorAddon(
        Vendor $vendor,
        VendorSubscriptionAddon $vendorAddon,
        SubscriptionAddonService $addonService
    ): RedirectResponse {
        if ((int) $vendorAddon->vendor_id !== (int) $vendor->id) {
            abort(403, 'Add-on does not belong to this vendor.');
        }

        $addonService->cancelVendorAddon($vendorAddon, Auth::user());

        return back()->with('success', __('Vendor add-on cancelled.'));
    }

    /**
     * Update global Subscription Settings.
     */
    public function updateSubscriptionSettings(
        Request $request,
        SubscriptionAuditService $auditService
    ): RedirectResponse {
        $validated = $request->validate([
            'default_trial_days' => 'required|integer|min:0|max:365',
            'grace_period_days' => 'required|integer|min:0|max:90',
            'warning_threshold_percent' => 'required|integer|min:50|max:99',
            'downgrade_behavior' => 'required|string|in:preserve_data_restrict_new,strict_block',
        ]);

        SystemSetting::set('subscription_default_trial_days', (string) $validated['default_trial_days'], 'subscription');
        SystemSetting::set('subscription_grace_period_days', (string) $validated['grace_period_days'], 'subscription');
        SystemSetting::set('subscription_warning_threshold_percent', (string) $validated['warning_threshold_percent'], 'subscription');
        SystemSetting::set('subscription_downgrade_behavior', $validated['downgrade_behavior'], 'subscription');

        $auditService->record(
            actionType: 'subscription.settings.updated',
            entityType: SystemSetting::class,
            entityId: 'subscription_settings',
            newValues: $validated,
            actor: Auth::user()
        );

        return back()->with('success', __('Subscription settings updated successfully.'));
    }

    /**
     * Update SuperAdmin platform payment gateway credentials and bank requisites.
     */
    public function updatePaymentGatewaySettings(
        Request $request,
        SecurityAuditService $securityAuditService
    ): RedirectResponse {
        $validated = $request->validate([
            // Idram
            'payment_idram_enabled' => 'nullable|boolean',
            'payment_idram_merchant_id' => 'nullable|string|max:100',
            'payment_idram_secret_key' => 'nullable|string|max:255',
            'payment_idram_sandbox' => 'nullable|boolean',

            // Telcell
            'payment_telcell_enabled' => 'nullable|boolean',
            'payment_telcell_shop_id' => 'nullable|string|max:100',
            'payment_telcell_key' => 'nullable|string|max:255',
            'payment_telcell_sandbox' => 'nullable|boolean',

            // FastShift
            'payment_fastshift_enabled' => 'nullable|boolean',
            'payment_fastshift_merchant_id' => 'nullable|string|max:100',
            'payment_fastshift_api_key' => 'nullable|string|max:255',
            'payment_fastshift_sandbox' => 'nullable|boolean',

            // ArCa / Ameriabank
            'payment_arca_enabled' => 'nullable|boolean',
            'payment_arca_merchant_id' => 'nullable|string|max:100',
            'payment_arca_terminal_id' => 'nullable|string|max:100',
            'payment_arca_username' => 'nullable|string|max:100',
            'payment_arca_password' => 'nullable|string|max:255',
            'payment_arca_sandbox' => 'nullable|boolean',

            // Stripe
            'payment_stripe_enabled' => 'nullable|boolean',
            'payment_stripe_publishable_key' => 'nullable|string|max:255',
            'payment_stripe_secret_key' => 'nullable|string|max:255',
            'payment_stripe_webhook_secret' => 'nullable|string|max:255',
            'payment_stripe_sandbox' => 'nullable|boolean',

            // Bank Transfer Requisites
            'payment_bank_transfer_enabled' => 'nullable|boolean',
            'payment_bank_transfer_company_name' => 'nullable|string|max:255',
            'payment_bank_transfer_tin' => 'nullable|string|max:50',
            'payment_bank_transfer_bank_name' => 'nullable|string|max:255',
            'payment_bank_transfer_account_number' => 'nullable|string|max:100',
            'payment_bank_transfer_notes' => 'nullable|string|max:1000',
        ]);

        $booleanFields = [
            'payment_idram_enabled', 'payment_idram_sandbox',
            'payment_telcell_enabled', 'payment_telcell_sandbox',
            'payment_fastshift_enabled', 'payment_fastshift_sandbox',
            'payment_arca_enabled', 'payment_arca_sandbox',
            'payment_stripe_enabled', 'payment_stripe_sandbox',
            'payment_bank_transfer_enabled',
        ];

        foreach ($booleanFields as $field) {
            $val = $request->boolean($field) ? '1' : '0';
            SystemSetting::set($field, $val, 'payment');
        }

        $textFields = [
            'payment_idram_merchant_id', 'payment_idram_secret_key',
            'payment_telcell_shop_id', 'payment_telcell_key',
            'payment_fastshift_merchant_id', 'payment_fastshift_api_key',
            'payment_arca_merchant_id', 'payment_arca_terminal_id', 'payment_arca_username', 'payment_arca_password',
            'payment_stripe_publishable_key', 'payment_stripe_secret_key', 'payment_stripe_webhook_secret',
            'payment_bank_transfer_company_name', 'payment_bank_transfer_tin', 'payment_bank_transfer_bank_name',
            'payment_bank_transfer_account_number', 'payment_bank_transfer_notes',
        ];

        foreach ($textFields as $field) {
            if ($request->has($field)) {
                SystemSetting::set($field, $request->input($field), 'payment');
            }
        }

        $securityAuditService->record(
            event: SecurityAuditLog::EVENT_SECURITY_CONFIG_CHANGED,
            action: 'Updated platform payment gateway settings and bank requisites.',
            actor: Auth::user(),
            targetType: SystemSetting::class,
            targetId: null,
            metadata: ['group' => 'payment']
        );

        return back()->with('success', __('Վճարային համակարգերի և ռեկվիզիտների կարգավորումները հաջողությամբ պահպանվեցին:'));
    }
}
