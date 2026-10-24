<?php

namespace App\Http\Controllers;

use App\Http\Requests\RenewSubscriptionRequest;
use App\Models\AnalyticsLog;
use App\Models\Location;
use App\Models\Order;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Security\FeatureCatalog;
use App\Security\QuotaCatalog;
use App\Services\EntitlementService;
use App\Services\PaymentGatewayService;
use App\Services\QuotaService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VendorAdminController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        if (! $vendor) {
            abort(404, 'Vendor not found for user.');
        }

        $this->authorize('view', $vendor);

        if ($user->location_id) {
            $activeLocationId = $user->location_id;
            if ($request->has('location_id') && (int) $request->get('location_id') !== (int) $user->location_id) {
                abort(403, 'Unauthorized access to other branch location.');
            }
        } else {
            $activeLocationId = $request->get('location_id', session('active_location_id', $vendor->locations->first()?->id));
            if ($activeLocationId) {
                session(['active_location_id' => $activeLocationId]);
            }
        }

        $location = ($activeLocationId ? $vendor->locations->firstWhere('id', (int) $activeLocationId) : null) ?? $vendor->locations->first();

        // Stats
        $ordersQuery = Order::where('vendor_id', $vendor->id);
        if ($location) {
            $ordersQuery->where('location_id', $location->id);
        }

        $tz = ! empty($vendor->timezone) ? $vendor->timezone : 'Asia/Yerevan';
        $todayStart = Carbon::now($tz)->startOfDay()->setTimezone('UTC');
        $todayEnd = Carbon::now($tz)->endOfDay()->setTimezone('UTC');

        $todayOrders = (clone $ordersQuery)->whereBetween('created_at', [$todayStart, $todayEnd])->count();
        $todayRevenue = (clone $ordersQuery)->whereBetween('created_at', [$todayStart, $todayEnd])->where('status', 'completed')->sum('total_amount');
        $pendingOrdersCount = (clone $ordersQuery)->where('status', 'pending')->count();

        // Analytics visits (past 7 days)
        $analyticsQuery = AnalyticsLog::where('vendor_id', $vendor->id);
        if ($location) {
            $analyticsQuery->where('location_id', $location->id);
        }
        $dineInVisits = (clone $analyticsQuery)->where('channel', 'dine_in')->count();
        $orderingVisits = (clone $analyticsQuery)->where('channel', 'ordering')->count();

        $recentOrders = (clone $ordersQuery)->with('items')->latest()->take(6)->get();

        return view('admin.dashboard', compact(
            'vendor',
            'location',
            'todayOrders',
            'todayRevenue',
            'pendingOrdersCount',
            'dineInVisits',
            'orderingVisits',
            'recentOrders'
        ));
    }

    public function locationsIndex()
    {
        $this->authorize('locations.view');

        $vendor = Auth::user()->vendor;
        $locations = $vendor->locations;

        return view('admin.locations', compact('vendor', 'locations'));
    }

    public function storeLocation(Request $request)
    {
        $this->authorize('locations.manage');

        $vendor = Auth::user()->vendor;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'whatsapp_number' => 'nullable|string',
            'table_count' => 'required|integer|min:1|max:200',
            'minimum_order_amount' => 'nullable|numeric|min:0',
        ]);

        app(QuotaService::class)->executeWithResourceQuota(
            $vendor,
            QuotaCatalog::KEY_BRANCHES_COUNT,
            function ($lockedVendor) use ($validated) {
                Location::create([
                    'vendor_id' => $lockedVendor->id,
                    'name' => $validated['name'],
                    'slug' => Str::slug($validated['name']),
                    'address' => $validated['address'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'whatsapp_number' => $validated['whatsapp_number'] ?? null,
                    'table_count' => $validated['table_count'],
                    'minimum_order_amount' => $validated['minimum_order_amount'] ?? 0,
                    'is_active' => true,
                ]);
            }
        );

        return back()->with('success', __('New Location added successfully!'));
    }

    public function teamIndex()
    {
        $this->authorize('team.view');

        $vendor = Auth::user()->vendor;
        $team = User::where('vendor_id', $vendor->id)->get();
        $locations = $vendor->locations;

        return view('admin.team', compact('vendor', 'team', 'locations'));
    }

    public function storeTeamMember(Request $request)
    {
        $this->authorize('team.manage');

        $vendor = Auth::user()->vendor;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|string|in:manager,staff,chef,cashier',
            'location_id' => 'nullable|integer',
            'password' => 'required|string|min:6',
        ]);

        if (! empty($validated['location_id'])) {
            $loc = $vendor->locations->firstWhere('id', (int) $validated['location_id']) ?? $vendor->locations()->find($validated['location_id']);
            if (! $loc) {
                abort(403, 'Unauthorized location assignment.');
            }
        }

        app(QuotaService::class)->executeWithResourceQuota(
            $vendor,
            QuotaCatalog::KEY_TEAM_MEMBERS_COUNT,
            function ($lockedVendor) use ($validated) {
                User::create([
                    'vendor_id' => $lockedVendor->id,
                    'location_id' => $validated['location_id'] ?? null,
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'role' => $validated['role'],
                ]);
            }
        );

        return back()->with('success', __('Team member invited successfully!'));
    }

    public function subscriptionIndex(
        SubscriptionService $subscriptionService,
        EntitlementService $entitlementService,
        QuotaService $quotaService
    ) {
        $this->authorize('billing.view');

        $vendor = Auth::user()->vendor;
        $vendor->load('plan', 'payments');
        $vendor->unsetRelation('subscription');

        $subscription = $subscriptionService->getOrCreateForVendor($vendor);
        $allPlans = SubscriptionPlan::where('is_active', true)->with('activePrices')->orderBy('sort_order', 'asc')->get();

        $entitledFeatures = $entitlementService->getEntitledFeatures($vendor);
        $allFeatures = FeatureCatalog::all();

        $keyQuotas = [
            QuotaCatalog::KEY_PRODUCTS_COUNT,
            QuotaCatalog::KEY_CATEGORIES_COUNT,
            QuotaCatalog::KEY_BRANCHES_COUNT,
            QuotaCatalog::KEY_TEAM_MEMBERS_COUNT,
            QuotaCatalog::KEY_ORDERS_PERIOD,
            QuotaCatalog::KEY_RESERVATIONS_PERIOD,
            QuotaCatalog::KEY_AI_REQUESTS_PERIOD,
        ];

        $quotas = [];
        foreach ($keyQuotas as $qk) {
            $quotas[$qk] = $quotaService->getQuotaStatus($vendor, $qk);
        }

        $payments = $vendor->payments()->latest()->paginate(15);

        return view('admin.subscription.index', compact(
            'vendor',
            'subscription',
            'allPlans',
            'entitledFeatures',
            'allFeatures',
            'quotas',
            'payments'
        ));
    }

    /**
     * Renew or upgrade vendor SaaS subscription via selected payment gateway.
     */
    public function renewSubscription(RenewSubscriptionRequest $request, PaymentGatewayService $paymentService)
    {
        $vendor = Auth::user()->vendor;
        $this->authorize('manageBilling', $vendor);

        $validated = $request->validated();

        $plan = SubscriptionPlan::findOrFail($validated['subscription_plan_id']);

        $renewalData = $paymentService->initiateSubscriptionRenewal(
            $vendor,
            $plan,
            (int) $validated['period_months'],
            $validated['payment_method']
        );

        $payment = $renewalData['payment'];
        $result = $renewalData['result'];

        $methodNames = [
            'idram' => 'Idram Wallet / QR',
            'telcell' => 'Telcell Wallet',
            'fastshift' => 'FastShift',
            'arca' => 'ArCa / Ameriabank vPOS',
            'stripe' => 'Stripe',
            'bank_transfer' => 'Bank Transfer',
            'cash' => 'Cash',
        ];
        $selectedMethodName = $methodNames[$validated['payment_method']] ?? strtoupper($validated['payment_method']);

        if ($result->type === 'redirect' && $result->redirectUrl) {
            return redirect($result->redirectUrl)->with(
                'info',
                __('Payment request created (:method). Invoice: :invoice.', [
                    'method' => __($selectedMethodName),
                    'invoice' => $payment->invoice_number,
                ])
            );
        }

        return back()->with(
            'success',
            __('Invoice created (:method). Invoice: :invoice. Subscription will be activated upon payment confirmation.', [
                'method' => __($selectedMethodName),
                'invoice' => $payment->invoice_number,
            ])
        );
    }

    /**
     * Cancel vendor subscription (at period end or immediately if requested).
     */
    public function cancelSubscription(Request $request, SubscriptionService $subscriptionService)
    {
        $vendor = Auth::user()->vendor;
        $this->authorize('manageBilling', $vendor);
        $vendor->unsetRelation('subscription');

        $subscription = $subscriptionService->getOrCreateForVendor($vendor);
        $immediately = $request->boolean('immediately', false);
        $reason = $request->input('reason', 'Cancelled by tenant administrator');

        $subscriptionService->cancel($subscription, immediately: $immediately, reason: $reason, actor: Auth::user());

        $message = $immediately
            ? __('Subscription has been cancelled immediately.')
            : __('Subscription will cancel at the end of the current billing period.');

        return back()->with('success', $message);
    }

    /**
     * Resume auto-renewal on a subscription scheduled to cancel at period end.
     */
    public function resumeSubscription(SubscriptionService $subscriptionService)
    {
        $vendor = Auth::user()->vendor;
        $this->authorize('manageBilling', $vendor);
        $vendor->unsetRelation('subscription');

        $subscription = $subscriptionService->getOrCreateForVendor($vendor);
        $subscriptionService->resume($subscription, actor: Auth::user());

        return back()->with('success', __('Subscription auto-renewal has been resumed.'));
    }

    /**
     * View or print specific invoice transaction.
     */
    public function showInvoice($invoice)
    {
        $this->authorize('billing.view');

        $invoiceModel = $invoice instanceof SubscriptionPayment
            ? $invoice
            : SubscriptionPayment::withoutGlobalScopes()->findOrFail($invoice);

        $vendor = Auth::user()->vendor;
        if ((int) $invoiceModel->vendor_id !== (int) $vendor->id) {
            abort(403, 'Unauthorized invoice access.');
        }

        $invoiceModel->load('plan', 'latestPaymentAttempt');

        if (request()->wantsJson()) {
            return response()->json([
                'invoice_number' => $invoiceModel->invoice_number,
                'amount' => $invoiceModel->amount,
                'currency' => $invoiceModel->currency,
                'status' => $invoiceModel->status,
                'period_start' => $invoiceModel->period_start?->toDateString(),
                'period_end' => $invoiceModel->period_end?->toDateString(),
                'payment_method' => $invoiceModel->payment_method,
                'plan_name' => $invoiceModel->plan?->name,
                'notes' => $invoiceModel->notes,
                'created_at' => $invoiceModel->created_at?->toIso8601String(),
            ]);
        }

        return view('admin.subscription.invoice', ['invoice' => $invoiceModel, 'vendor' => $vendor]);
    }
}
