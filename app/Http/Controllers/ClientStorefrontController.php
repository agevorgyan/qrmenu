<?php

namespace App\Http\Controllers;

use App\Actions\CreateOrderAction;
use App\DTOs\CreateOrderDTO;
use App\Enums\PaymentStatus;
use App\Events\WaiterCalled;
use App\Http\Requests\CallWaiterRequest;
use App\Http\Requests\SubmitOrderRequest;
use App\Jobs\RecordAnalyticsVisitJob;
use App\Models\Category;
use App\Models\Location;
use App\Models\MenuTemplate;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\WaiterCall;
use App\Services\PaymentGatewayService;
use App\Services\Payments\PaymentVerificationService;
use App\Services\TenantCache;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientStorefrontController extends Controller
{
    public function showMenu(Request $request, string $vendor_slug, ?string $location_slug = null)
    {
        $vendor = Vendor::where('slug', $vendor_slug)
            ->where('is_active', true)
            ->with(['menuTemplate', 'locations'])
            ->firstOrFail();

        $location = null;
        if ($location_slug) {
            $location = $vendor->locations->firstWhere('slug', $location_slug)
                ?? Location::where('vendor_id', $vendor->id)->where('slug', $location_slug)->first();
        }
        if (! $location) {
            $location = $vendor->locations->first();
        }

        $supportedLanguages = $vendor->getSupportedLanguages();
        $allowedCodes = array_map(fn ($l) => strtolower($l['code'] ?? ''), $supportedLanguages);
        $defaultCode = $allowedCodes[0] ?? 'en';

        $lang = strtolower((string) ($request->get('lang') ?? session('app_locale', $defaultCode)));
        if (! in_array($lang, $allowedCodes) && ! in_array($lang, ['hy', 'en', 'ru', 'fr', 'de', 'es', 'it', 'ge', 'ar'])) {
            $lang = $defaultCode;
        }
        session(['app_locale' => $lang, 'locale' => $lang]);
        App::setLocale($lang);
        $table = null;
        if ($request->has('table') && $request->filled('table')) {
            $table = $request->get('table');
            session(['storefront_table_'.$vendor->slug => $table]);
        } elseif ($request->has('table') && ! $request->filled('table')) {
            session()->forget('storefront_table_'.$vendor->slug);
            $table = null;
        } elseif (! $request->has('table')) {
            session()->forget('storefront_table_'.$vendor->slug);
            $table = null;
        }
        $channel = $request->get('mode', ! empty($table) ? 'dine_in' : ($vendor->delivery_enabled ? 'delivery' : ($vendor->takeaway_enabled ? 'takeaway' : 'dine_in')));

        // Deduplicate analytics visit per IP/user-agent per hour to prevent metric inflation
        $visitHash = md5($request->ip().'|'.($request->userAgent() ?? ''));
        $visitCacheKey = TenantCache::key($vendor, "analytics_visit:{$visitHash}");

        if (! cache()->has($visitCacheKey)) {
            cache()->put($visitCacheKey, true, now()->addHours(1));

            // Dispatch analytics visit asynchronously to background queue with tenant context
            RecordAnalyticsVisitJob::dispatch(
                data: [
                    'vendor_id' => $vendor->id,
                    'location_id' => $location?->id,
                    'channel' => $channel,
                    'user_agent' => substr($request->userAgent() ?? '', 0, 255),
                    'ip_address' => $request->ip(),
                    'visit_date' => now()->format('Y-m-d'),
                ],
                vendorId: $vendor->id,
                idempotencyKey: "analytics_visit_{$vendor->id}_{$visitHash}"
            );
        }

        // Fetch categories and active products with tenant-scoped caching
        $menuVersion = (int) TenantCache::get($vendor, 'menu_version', 1);
        $locationKey = $location?->id ?? 'all';
        $menuCacheKey = "storefront_menu:v{$menuVersion}:{$locationKey}";

        $fetchFreshCategories = function () use ($vendor) {
            return Category::where('vendor_id', $vendor->id)
                ->where('is_active', true)
                ->with(['products' => function ($q) {
                    $q->where('is_available', true)
                        ->with(['variations', 'allergens', 'overrides'])
                        ->orderBy('sort_order', 'asc');
                }])
                ->orderBy('sort_order', 'asc')
                ->get();
        };

        $cachedCategories = TenantCache::get($vendor, $menuCacheKey);

        if ($cachedCategories instanceof Collection
            && ($cachedCategories->isEmpty() || $cachedCategories->first() instanceof Category)) {
            $categories = $cachedCategories;
        } else {
            $categories = $fetchFreshCategories();
            TenantCache::put($vendor, $menuCacheKey, $categories, now()->addDay());
        }

        $themeSlug = $vendor->menuTemplate?->slug ?? 'modern-bistro';

        // Support real-time live preview query parameter overrides (guarded by staff auth or valid signature)
        $hasPreviewParams = $request->hasAny([
            'menu_template_id', 'theme_mode', 'primary_color', 'accent_color',
            'secondary_color', 'bg_color', 'text_color', 'logo', 'cover_image',
        ]);

        if ($hasPreviewParams) {
            $isAuthorizedStaff = auth('web')->check() && (
                (bool) auth('web')->user()->isSuperAdmin() ||
                (int) auth('web')->user()->vendor_id === (int) $vendor->id
            );

            if ($isAuthorizedStaff || $request->hasValidSignature()) {
                if ($request->filled('menu_template_id')) {
                    $tmpl = MenuTemplate::find($request->get('menu_template_id'));
                    if ($tmpl) {
                        $themeSlug = $tmpl->slug;
                    }
                }
                if ($request->filled('theme_mode')) {
                    $vendor->theme_mode = $request->get('theme_mode');
                }
                if ($request->filled('primary_color')) {
                    $vendor->primary_color = $request->get('primary_color');
                }
                if ($request->filled('accent_color')) {
                    $vendor->accent_color = $request->get('accent_color');
                }
                if ($request->filled('secondary_color')) {
                    $vendor->secondary_color = $request->get('secondary_color');
                }
                if ($request->filled('bg_color')) {
                    $vendor->bg_color = $request->get('bg_color');
                }
                if ($request->filled('text_color')) {
                    $vendor->text_color = $request->get('text_color');
                }
                if ($request->filled('logo')) {
                    $vendor->logo = $request->get('logo');
                }
                if ($request->filled('cover_image')) {
                    $vendor->cover_image = $request->get('cover_image');
                }
                if ($request->filled('custom_css')) {
                    $vendor->custom_css = $request->get('custom_css');
                }
                if ($request->filled('desktop_max_width')) {
                    $vendor->desktop_max_width = $request->get('desktop_max_width');
                }
            }
        }

        // Featured Dish / Dish of the Day
        $featuredDish = null;
        if ($vendor->featured_dish_enabled && $vendor->featured_product_id) {
            foreach ($categories as $cat) {
                if ($cat->relationLoaded('products')) {
                    $found = $cat->products->firstWhere('id', (int) $vendor->featured_product_id);
                    if ($found) {
                        $found->setRelation('category', $cat);
                        $featuredDish = $found;
                        break;
                    }
                }
            }

            if (! $featuredDish) {
                $featuredDish = Product::where('vendor_id', $vendor->id)
                    ->where('id', $vendor->featured_product_id)
                    ->where('is_available', true)
                    ->with(['variations', 'allergens', 'overrides', 'category'])
                    ->first();
            }
        }

        return view("storefront.themes.{$themeSlug}", compact(
            'vendor',
            'location',
            'categories',
            'featuredDish',
            'lang',
            'table',
            'channel'
        ));
    }

    public function manifest(string $vendor_slug)
    {
        $vendor = Vendor::where('slug', $vendor_slug)->firstOrFail();

        return response()->json([
            'short_name' => $vendor->name,
            'name' => "{$vendor->name} - Digital Menu & Orders",
            'icons' => [
                [
                    'src' => $vendor->logo ?? 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=192&h=192&q=80',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                ],
                [
                    'src' => $vendor->logo ?? 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=512&h=512&q=80',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                ],
            ],
            'start_url' => route('client.menu', ['vendor_slug' => $vendor->slug]),
            'background_color' => '#0f172a',
            'theme_color' => $vendor->primary_color ?? '#e11d48',
            'display' => 'standalone',
            'orientation' => 'portrait',
        ]);
    }

    public function serviceWorker(string $vendor_slug)
    {
        $content = "
const CACHE_NAME = 'qrmenu-".$vendor_slug."-v1';

self.addEventListener('install', event => {
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(clients.claim());
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => response || fetch(event.request))
      .catch(() => fetch(event.request))
  );
});
        ";

        return response($content, 200)->header('Content-Type', 'application/javascript');
    }

    public function submitOrder(SubmitOrderRequest $request, string $vendor_slug, CreateOrderAction $createOrderAction, PaymentGatewayService $paymentService)
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();

        if (! $vendor->hasFeature('orders')) {
            return response()->json([
                'success' => false,
                'message' => 'Օնլայն պատվերների ֆունկցիան ակտիվ չէ այս մենյուի համար (Basic փաթեթ)։',
            ], 403);
        }

        $validated = $request->validated();

        // Ensure all sent product_ids belong to this vendor
        $productIds = collect($validated['items'])->pluck('product_id')->unique();
        $vendorProductsCount = Product::where('vendor_id', $vendor->id)
            ->whereIn('id', $productIds)
            ->count();

        if ($vendorProductsCount !== $productIds->count()) {
            return response()->json([
                'success' => false,
                'message' => 'Պատվերի մեջ առկա են անվավեր կամ այլ գործընկերոջ պատկանող ապրանքներ։',
            ], 422);
        }

        $dto = CreateOrderDTO::fromArray($validated);

        try {
            $result = DB::transaction(function () use ($createOrderAction, $vendor, $dto) {
                return $createOrderAction->execute($vendor, $dto);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        // Handle online payment gateway redirection
        $paymentResult = null;
        $chosenPayment = $validated['payment_method'] ?? 'cash';
        if (! in_array($chosenPayment, ['cash', 'pos_terminal'])) {
            $paymentResult = $paymentService->initiateOrderPayment($result['order'], $chosenPayment);
        }

        // Store order tracking authorization in customer session
        session([
            'placed_order_'.$result['order']->order_number => $result['order']->tracking_token,
            'active_tracking_tokens' => array_unique(array_merge(
                (array) session('active_tracking_tokens', []),
                [$result['order']->tracking_token]
            )),
        ]);

        return response()->json([
            'success' => true,
            'is_appended' => $result['is_appended'] ?? false,
            'order_number' => $result['order']->order_number,
            'order_id' => $result['order']->id,
            'tracking_token' => $result['order']->tracking_token,
            'type' => $result['order']->type,
            'table_number' => $result['order']->table_number,
            'status' => $result['order']->status,
            'status_label' => __('menu.status_'.$result['order']->status),
            'payment_method' => $result['order']->payment_method,
            'payment_status' => $result['order']->payment_status,
            'payment_redirect_url' => $paymentResult['redirect_url'] ?? null,
            'birthday_discount' => (float) ($result['order']->birthday_discount_amount ?? 0),
            'subtotal' => (float) ($result['order']->subtotal ?? 0),
            'service_fee' => (float) ($result['order']->service_fee ?? 0),
            'delivery_fee' => (float) ($result['order']->delivery_fee ?? 0),
            'total_amount' => (float) $result['order']->total_amount,
            'whatsapp_url' => $result['whatsapp_url'],
            'items' => $result['order']->items->map(function ($item, $idx) {
                $unitPrice = (float) ($item->unit_price ?? $item->price ?? 0);
                $subtotal = (float) ($item->subtotal ?? ($unitPrice * $item->quantity));

                return [
                    'id' => 'item_'.($idx + 1),
                    'name' => $item->product_name ?? $item->product?->name ?? 'Dish',
                    'variation_name' => $item->variation_name,
                    'quantity' => $item->quantity,
                    'price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];
            }),
        ]);
    }

    /**
     * Handle payment gateway callback.
     * Never trusts browser parameters as proof of payment.
     * Only returns success if payment has been server-verified with the provider.
     */
    public function paymentCallback(
        string $vendor_slug,
        string $reference,
        Request $request,
        PaymentVerificationService $verificationService
    ) {
        $vendor = Vendor::where('slug', $vendor_slug)->firstOrFail();

        // Find attempt by merchant reference, uuid, or order ID
        $attempt = PaymentAttempt::where('vendor_id', $vendor->id)
            ->where(function ($query) use ($reference) {
                $query->where('merchant_reference', $reference);
                if (Str::isUuid($reference)) {
                    $query->orWhere('uuid', $reference);
                }
                if (is_numeric($reference)) {
                    $query->orWhere('order_id', (int) $reference);
                }
            })
            ->latest()
            ->first();

        if (! $attempt) {
            return redirect()->route('client.menu', [
                'vendor_slug' => $vendor->slug,
            ])->with('error', 'Վճարման գրառումը չի գտնվել։');
        }

        $order = $attempt->order;

        // 1. If already verified by server-to-server webhook
        if ($attempt->status === PaymentStatus::Paid) {
            return redirect()->route('client.menu', [
                'vendor_slug' => $vendor->slug,
                'order_placed' => $order?->order_number,
                'payment_success' => 1,
                'gateway' => $attempt->gateway,
            ])->with('success', 'Վճարումը հաջողությամբ կատարվել է։ Պատվերը փոխանցվել է խոհանոց։');
        }

        // 2. Perform server-side provider verification
        $result = $verificationService->verifyAndFinalizeAttempt($attempt, $request->all(), $request->headers->all());

        if ($result->success && $result->status === PaymentStatus::Paid) {
            $order?->refresh();

            return redirect()->route('client.menu', [
                'vendor_slug' => $vendor->slug,
                'order_placed' => $order?->order_number,
                'payment_success' => 1,
                'gateway' => $attempt->gateway,
            ])->with('success', 'Վճարումը հաջողությամբ կատարվել է։ Պատվերը փոխանցվել է խոհանոց։');
        }

        if ($result->status === PaymentStatus::Failed) {
            return redirect()->route('client.menu', [
                'vendor_slug' => $vendor->slug,
                'order_placed' => $order?->order_number,
                'payment_failed' => 1,
                'gateway' => $attempt->gateway,
            ])->with('error', 'Վճարումը մերժվել է կամ տեղի է ունեցել սխալ։');
        }

        return redirect()->route('client.menu', [
            'vendor_slug' => $vendor->slug,
            'order_placed' => $order?->order_number,
            'payment_pending' => 1,
            'gateway' => $attempt->gateway,
        ])->with('info', 'Վճարումը մշակման փուլում է։ Խնդրում ենք սպասել հաստատմանը։');
    }

    public function orderStatus(Request $request, string $vendor_slug, string $order_number)
    {
        $vendor = Vendor::where('slug', $vendor_slug)->firstOrFail();

        // 1. IP brute-force lockout check (prevents scraping and automated probing)
        $ip = $request->ip() ?: '127.0.0.1';
        $lockoutKey = "order_lookup_lockout:{$ip}";
        if (cache()->has($lockoutKey)) {
            $retryAfter = max(1, (int) cache()->get($lockoutKey) - now()->timestamp);

            return response()->json([
                'success' => false,
                'message' => 'Չափազանց շատ անհաջող փորձեր։ Մուտքն արգելափակված է։',
                'retry_after' => $retryAfter,
            ], 429, ['Retry-After' => (string) $retryAfter]);
        }

        // 2. Identify order: either by tracking token (opaque secret) or order_number
        $isTokenLookup = str_starts_with($order_number, 'trk_') || strlen($order_number) >= 32;

        if ($isTokenLookup) {
            $order = Order::where('vendor_id', $vendor->id)
                ->where('tracking_token', $order_number)
                ->with(['items.product', 'location'])
                ->first();
        } else {
            $order = Order::where('vendor_id', $vendor->id)
                ->where('order_number', $order_number)
                ->with(['items.product', 'location'])
                ->first();
        }

        if (! $order) {
            $this->recordFailedOrderLookup($ip);

            return response()->json([
                'success' => false,
                'message' => 'Պատվերը չի գտնվել։',
            ], 404);
        }

        // 3. Authorization check: if queried by predictable order_number, verify proof of possession
        if (! $isTokenLookup) {
            $providedToken = $request->query('token')
                ?? $request->header('X-Tracking-Token');

            $sessionAuthorized = session('placed_order_'.$order->order_number) === $order->tracking_token
                || in_array($order->tracking_token, (array) session('active_tracking_tokens', []), true);

            $isStaff = $request->user() && (int) $request->user()->vendor_id === (int) $vendor->id;

            $isAuthorized = ($providedToken && hash_equals($order->tracking_token, (string) $providedToken))
                || $sessionAuthorized
                || $isStaff;

            if (! $isAuthorized) {
                $this->recordFailedOrderLookup($ip);

                return response()->json([
                    'success' => false,
                    'message' => 'Մուտքն արգելված է։ Պահանջվում է վավեր հետևման թոքեն (Tracking token)։',
                ], 403);
            }
        }

        $stepMap = [
            'pending' => [
                'step' => 1,
                'percent' => 25,
                'label' => __('menu.status_pending'),
                'desc' => __('menu.status_desc_pending'),
                'icon' => 'fa-solid fa-clock',
            ],
            'accepted' => [
                'step' => 2,
                'percent' => 50,
                'label' => __('menu.status_accepted'),
                'desc' => __('menu.status_desc_accepted'),
                'icon' => 'fa-solid fa-clipboard-check',
            ],
            'preparing' => [
                'step' => 3,
                'percent' => 75,
                'label' => __('menu.status_preparing'),
                'desc' => __('menu.status_desc_preparing'),
                'icon' => 'fa-solid fa-utensils',
            ],
            'ready' => [
                'step' => 4,
                'percent' => 95,
                'label' => __('menu.status_ready'),
                'desc' => __('menu.status_desc_ready'),
                'icon' => 'fa-solid fa-bell-concierge',
            ],
            'completed' => [
                'step' => 5,
                'percent' => 100,
                'label' => __('menu.status_completed'),
                'desc' => __('menu.status_desc_completed'),
                'icon' => 'fa-solid fa-circle-check',
            ],
            'cancelled' => [
                'step' => 0,
                'percent' => 0,
                'label' => __('menu.status_cancelled'),
                'desc' => __('menu.status_desc_cancelled'),
                'icon' => 'fa-solid fa-circle-xmark',
            ],
        ];

        $meta = $stepMap[$order->status] ?? [
            'step' => 1,
            'percent' => 20,
            'label' => ucfirst($order->status),
            'desc' => '',
            'icon' => 'fa-solid fa-clock',
        ];

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->tracking_token,
                'tracking_token' => $order->tracking_token,
                'order_number' => $order->order_number,
                'table_number' => $order->table_number,
                'type' => $order->type,
                'status' => $order->status,
                'status_step' => $meta['step'],
                'status_percent' => $meta['percent'],
                'status_label' => $meta['label'],
                'status_desc' => $meta['desc'],
                'status_icon' => $meta['icon'],
                'subtotal' => (float) ($order->subtotal ?? 0),
                'service_fee' => (float) ($order->service_fee ?? 0),
                'delivery_fee' => (float) ($order->delivery_fee ?? 0),
                'total_amount' => (float) $order->total_amount,
                'currency' => $vendor->currency,
                'created_at_human' => $order->created_at->diffForHumans(),
                'created_at_time' => $order->created_at->format('H:i'),
                'updated_at_timestamp' => $order->updated_at?->timestamp,
                'items' => $order->items->map(function ($item, $idx) {
                    $unitPrice = (float) ($item->unit_price ?? $item->price ?? 0);
                    $subtotal = (float) ($item->subtotal ?? ($unitPrice * $item->quantity));

                    return [
                        'id' => 'item_'.($idx + 1),
                        'name' => $item->product_name ?? $item->product?->name ?? 'Dish',
                        'variation_name' => $item->variation_name,
                        'quantity' => $item->quantity,
                        'price' => $unitPrice,
                        'subtotal' => $subtotal,
                    ];
                }),
            ],
        ]);
    }

    public function callWaiter(CallWaiterRequest $request, string $vendor_slug)
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();

        $validated = $request->validated();

        $sessionTable = session('storefront_table_'.$vendor->slug);
        if ($sessionTable) {
            $normalizedSession = strtolower(trim((string) $sessionTable));
            $normalizedTable = strtolower(trim((string) $validated['table_number']));
            $matches = ($normalizedTable === $normalizedSession)
                || ($normalizedTable === 'table '.$normalizedSession)
                || ($normalizedTable === 'սեղան '.$normalizedSession)
                || ('table '.$normalizedTable === $normalizedSession);

            if (! $matches) {
                return response()->json([
                    'success' => false,
                    'message' => 'Դուք չեք կարող կանչել մատուցող այլ սեղանի համար։',
                ], 403);
            }
        }

        // Table-level anti-spam cooldown: 60 seconds
        $recentTableCall = WaiterCall::where('vendor_id', $vendor->id)
            ->where('table_number', $validated['table_number'])
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subSeconds(60))
            ->first();

        if ($recentTableCall) {
            $retryAfter = max(1, 60 - now()->diffInSeconds($recentTableCall->created_at));

            return response()->json([
                'success' => false,
                'message' => 'Խնդրում ենք սպասել, Ձեր նախորդ կանչն արդեն փոխանցվել է մատուցողին։',
                'retry_after' => $retryAfter,
            ], 429, ['Retry-After' => (string) $retryAfter]);
        }

        $locationId = $validated['location_id'] ?? $vendor->locations->first()?->id;

        $call = WaiterCall::create([
            'vendor_id' => $vendor->id,
            'location_id' => $locationId,
            'table_number' => $validated['table_number'],
            'type' => $validated['type'],
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        // Broadcast real-time WaiterCalled event
        try {
            event(new WaiterCalled($call));
        } catch (\Throwable $e) {
            Log::warning('WaiterCalled broadcast failed: '.$e->getMessage());
        }

        $message = match ($call->type) {
            'call_waiter' => __('menu.waiter_called_success'),
            'bill_cash', 'bill_card' => __('menu.bill_requested_success'),
            default => 'Հարցումն ուղարկված է։',
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'call' => [
                'id' => $call->call_token,
                'call_token' => $call->call_token,
                'table_number' => $call->table_number,
                'type' => $call->type,
                'type_label' => $call->getTypeLabel(),
                'status' => $call->status,
                'created_at' => $call->created_at->diffForHumans(),
            ],
        ]);
    }

    /**
     * Record failed order lookup attempt and enforce 15-minute IP lockout after 5 failures.
     */
    protected function recordFailedOrderLookup(string $ip): void
    {
        $failKey = "order_lookup_failures:{$ip}";
        $attempts = (int) cache()->get($failKey, 0) + 1;
        cache()->put($failKey, $attempts, now()->addMinutes(10));

        if ($attempts >= 5) {
            cache()->put("order_lookup_lockout:{$ip}", now()->addMinutes(15)->timestamp, now()->addMinutes(15));
            cache()->forget($failKey);
        }
    }
}
