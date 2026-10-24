<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Http\Requests\UpdateSystemSettingsRequest;
use App\Models\EmailLog;
use App\Models\Location;
use App\Models\MenuTemplate;
use App\Models\Order;
use App\Models\SecurityAuditLog;
use App\Models\Subscription;
use App\Models\SubscriptionLifecycleEvent;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use App\Models\SystemSetting;
use App\Models\TenantFeatureOverride;
use App\Models\TenantQuotaOverride;
use App\Models\User;
use App\Models\Vendor;
use App\Security\FeatureCatalog;
use App\Security\QuotaCatalog;
use App\Services\EntitlementService;
use App\Services\QrCodeService;
use App\Services\QuotaService;
use App\Services\Security\ImageProcessor;
use App\Services\SecurityAuditService;
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
        $plans = SubscriptionPlan::withCount('vendors')->with('activePrices')->orderBy('sort_order', 'asc')->get();
        $availableFeatures = FeatureCatalog::all();
        $availableQuotas = QuotaCatalog::all();

        return view('superadmin.plans.index', compact('plans', 'availableFeatures', 'availableQuotas'));
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

    public function destroyPlan(SubscriptionPlan $plan)
    {
        if ($plan->vendors()->count() > 0 || $plan->subscriptions()->count() > 0) {
            return back()->with('error', __('Հնարավոր չէ ջնջել փաթեթը, քանի որ այն կցված է գործընկերների։'));
        }

        app(SecurityAuditService::class)->record(
            event: SecurityAuditLog::EVENT_SUBSCRIPTION_CHANGED,
            action: "Deleted subscription plan [{$plan->name}] (ID: {$plan->id}).",
            actor: Auth::user(),
            targetType: SubscriptionPlan::class,
            targetId: $plan->id
        );

        $plan->prices()->delete();
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

        $plans = SubscriptionPlan::orderBy('sort_order', 'asc')->get();
        $availableFeatures = FeatureCatalog::all();
        $availableQuotas = QuotaCatalog::all();

        // Calculate KPI stats
        $stats = [
            'total' => Vendor::withoutGlobalScopes()->count(),
            'active' => Subscription::withoutTenantQuery()->where('status', SubscriptionStatus::Active)->count(),
            'trialing' => Subscription::withoutTenantQuery()->where('status', SubscriptionStatus::Trialing)->count(),
            'expired_suspended' => Subscription::withoutTenantQuery()->whereIn('status', [SubscriptionStatus::Expired, SubscriptionStatus::Suspended])->count(),
            'failed_payments' => SubscriptionPayment::withoutGlobalScopes()->where('status', 'failed')->count(),
        ];

        return view('superadmin.subscriptions.index', compact('vendors', 'plans', 'availableFeatures', 'availableQuotas', 'stats'));
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

        $subscription->update([
            'subscription_plan_id' => $plan->id,
            'status' => $status,
            'current_period_end' => $newExpiry,
            'grace_ends_at' => $status === SubscriptionStatus::Grace ? ($vendor->grace_ends_at ?? now()->addDays(3)) : null,
            'cancelled_at' => $status === SubscriptionStatus::Cancelled ? ($vendor->cancelled_at ?? now()) : null,
        ]);

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

        $subPayment = SubscriptionPayment::create([
            'vendor_id' => $vendor->id,
            'subscription_id' => $subscription->id,
            'subscription_plan_id' => $vendor->subscription_plan_id,
            'amount' => $validated['amount'],
            'currency' => $vendor->currency ?? 'AMD',
            'payment_method' => $validated['payment_method'],
            'invoice_number' => $validated['invoice_number'] ?? 'INV-'.strtoupper(Str::random(6)),
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'status' => $paymentStatus,
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($request->has('extend_subscription') && $paymentStatus === 'completed') {
            $plan = $vendor->plan ?? SubscriptionPlan::find($vendor->subscription_plan_id);
            if ($plan) {
                $subscriptionService->activate($subscription, $plan, $subPayment);
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

    public function storeFeatureOverride(Request $request, Vendor $vendor)
    {
        $validated = $request->validate([
            'feature_key' => 'required|string|max:100',
            'is_enabled' => 'required|boolean',
            'reason' => 'required|string|min:3|max:1000',
            'scope' => 'nullable|string|max:50',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $entitlementService = app(EntitlementService::class);
        $entitlementService->setOverride(
            vendor: $vendor,
            featureKey: $validated['feature_key'],
            enabled: (bool) $validated['is_enabled'],
            reason: $validated['reason'],
            scope: $validated['scope'] ?? 'global',
            expiresAt: ! empty($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null,
            actor: Auth::user()
        );

        app(SecurityAuditService::class)->record(
            event: SecurityAuditLog::EVENT_SECURITY_CONFIG_CHANGED,
            action: "Set feature override [{$validated['feature_key']}] to ".($validated['is_enabled'] ? 'enabled' : 'disabled')." for vendor [{$vendor->name}]. Reason: {$validated['reason']}",
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

        return view('superadmin.settings.index', compact('settings', 'user', 'setupSecret', 'otpAuthUri', 'qrCodeUrl'));
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
            'currency' => ['required', 'string', Rule::in(['AMD', 'USD', 'EUR', 'RUB', 'GEL', 'GBP', 'AED', 'CNY', 'KZT', 'TRY'])],
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
}
