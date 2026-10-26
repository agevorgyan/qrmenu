<?php

namespace App\Http\Controllers;

use App\Actions\CreateOrderAction;
use App\DTOs\CreateOrderDTO;
use App\Enums\PaymentStatus;
use App\Events\WaiterCalled;
use App\Exceptions\QuotaExceededException;
use App\Http\Requests\CallWaiterRequest;
use App\Http\Requests\SubmitOrderRequest;
use App\Jobs\RecordAnalyticsVisitJob;
use App\Models\Category;
use App\Models\Location;
use App\Models\MenuTemplate;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Vendor;
use App\Models\WaiterCall;
use App\Services\PaymentGatewayService;
use App\Services\Payments\PaymentVerificationService;
use App\Services\SplitBillService;
use App\Services\TenantCache;
use App\Services\TimeAwareService;
use App\Services\TipRatingService;
use Illuminate\Http\JsonResponse;
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
        $defaultCode = $vendor->getDefaultLanguageCode() ?: ($allowedCodes[0] ?? 'en');

        $hasExplicitLangParam = $request->has('lang') || $request->has('locale');
        $savedSessionLang = session('storefront_lang_'.$vendor->slug);

        if ($hasExplicitLangParam) {
            $candidateLang = strtolower((string) ($request->get('lang') ?: $request->get('locale')));
            if (in_array($candidateLang, $allowedCodes, true)) {
                $lang = $candidateLang;
                session(['storefront_lang_'.$vendor->slug => $lang]);
                $needsLanguageSelection = false;
            } else {
                $lang = $defaultCode;
                $needsLanguageSelection = true;
            }
        } elseif ($savedSessionLang && in_array($savedSessionLang, $allowedCodes, true)) {
            $lang = $savedSessionLang;
            $needsLanguageSelection = false;
        } else {
            $lang = session('app_locale', $defaultCode);
            if (! in_array($lang, $allowedCodes, true)) {
                $lang = $defaultCode;
            }
            $needsLanguageSelection = true;
        }

        session(['app_locale' => $lang, 'locale' => $lang]);
        App::setLocale($lang);

        // Server-side validate entry context (Table QR vs General Menu)
        $table = null;
        $entryContext = 'general_menu';

        if ($request->has('table')) {
            $rawTable = trim((string) $request->get('table'));
            if ($rawTable !== '') {
                $isValidTable = false;
                $validatedTableNumber = null;

                // Check RestaurantTable table_number or uuid for this vendor
                $matchedTable = RestaurantTable::where('vendor_id', $vendor->id)
                    ->where('is_active', true)
                    ->where(function ($q) use ($rawTable) {
                        $q->where('table_number', $rawTable);
                        if (Str::isUuid($rawTable)) {
                            $q->orWhere('uuid', $rawTable);
                        }
                    })
                    ->when($location, fn ($q) => $q->where(function ($sub) use ($location) {
                        $sub->whereNull('location_id')->orWhere('location_id', $location->id);
                    }))
                    ->first();

                if ($matchedTable) {
                    $isValidTable = true;
                    $validatedTableNumber = $matchedTable->table_number;
                } else {
                    $hasAnyTables = RestaurantTable::where('vendor_id', $vendor->id)->exists();
                    if (! $hasAnyTables && is_numeric($rawTable) && (int) $rawTable >= 1 && (int) $rawTable <= ($location?->table_count ?? 50)) {
                        $isValidTable = true;
                        $validatedTableNumber = (string) (int) $rawTable;
                    }
                }

                if ($isValidTable) {
                    $table = $validatedTableNumber;
                    $entryContext = 'table_qr';
                    session([
                        'storefront_table_'.$vendor->slug => $table,
                        'storefront_entry_context_'.$vendor->slug => 'table_qr',
                    ]);
                } else {
                    // Forged or non-existent table: gracefully fallback to general menu
                    session()->forget('storefront_table_'.$vendor->slug);
                    session()->forget('storefront_entry_context_'.$vendor->slug);
                    $table = null;
                    $entryContext = 'general_menu';
                }
            } else {
                session()->forget('storefront_table_'.$vendor->slug);
                session()->forget('storefront_entry_context_'.$vendor->slug);
                $table = null;
                $entryContext = 'general_menu';
            }
        } else {
            // Preserve table session if customer already entered via QR
            $savedTable = session('storefront_table_'.$vendor->slug);
            if ($savedTable) {
                $table = (string) $savedTable;
                $entryContext = 'table_qr';
            } else {
                $table = null;
                $entryContext = 'general_menu';
            }
        }

        $timeAwareService = app(TimeAwareService::class);
        $timeSlice = $timeAwareService->getTimeSlice($vendor);
        $activeMealPeriod = $timeAwareService->getActiveMealPeriod($vendor);
        $isReturning = (bool) session('storefront_visited_'.$vendor->slug);
        session(['storefront_visited_'.$vendor->slug => true]);

        $greeting = $timeAwareService->getGreeting(
            $vendor,
            $lang,
            $entryContext,
            $table,
            $isReturning
        );

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

        // Featured Dishes / Daily Offers Carousel
        $dailyOffers = $vendor->getActiveDailyOffers($location);
        $featuredDish = $dailyOffers->first()?->product ?? null;

        return view("storefront.themes.{$themeSlug}", compact(
            'vendor',
            'location',
            'categories',
            'featuredDish',
            'dailyOffers',
            'lang',
            'table',
            'channel',
            'needsLanguageSelection',
            'entryContext',
            'supportedLanguages',
            'activeMealPeriod',
            'greeting',
            'timeSlice'
        ));
    }

    /**
     * Customer selects storefront language.
     */
    public function setLanguage(Request $request, string $vendor_slug): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();
        $supportedCodes = $vendor->getSupportedLanguageCodes();
        $raw = $request->input('language') ?? $request->input('locale');
        $lang = strtolower(trim((string) $raw));

        if (empty($lang) || ! in_array($lang, $supportedCodes, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Requested language is not supported or disabled.',
            ], 422);
        }

        session([
            'storefront_lang_'.$vendor->slug => $lang,
            'app_locale' => $lang,
            'locale' => $lang,
        ]);
        App::setLocale($lang);

        return response()->json([
            'success' => true,
            'language' => $lang,
            'locale' => $lang,
            'message' => 'Language selected successfully.',
        ]);
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

        if (! $vendor->hasFeature('orders.online')) {
            return response()->json([
                'success' => false,
                'error' => 'FEATURE_NOT_ENTITLED',
                'code' => 'FEATURE_NOT_ENTITLED',
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
        } catch (QuotaExceededException $e) {
            return response()->json([
                'success' => false,
                'error' => 'quota_exceeded',
                'code' => 'QUOTA_EXCEEDED',
                'quota_key' => $e->status->quotaKey,
                'limit' => $e->status->limit,
                'current_usage' => $e->status->currentUsage,
                'remaining_capacity' => $e->status->remainingCapacity,
                'message' => 'Այս ռեստորանը հասել է ամսական պատվերների առավելագույն սահմանաչափին:',
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

        if (! $vendor->hasFeature('orders.online')) {
            return response()->json([
                'success' => false,
                'error' => 'FEATURE_NOT_ENTITLED',
                'code' => 'FEATURE_NOT_ENTITLED',
                'message' => 'Մատուցողի կանչի ֆունկցիան ակտիվ չէ այս մենյուի համար (Basic փաթեթ)։',
            ], 403);
        }

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

    /**
     * Customer submits service rating / waiter review.
     */
    public function submitRating(Request $request, string $vendor_slug, TipRatingService $tipRatingService): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();

        if (! $vendor->isRatingsEnabled()) {
            return response()->json([
                'success' => false,
                'message' => __('Rating service is currently disabled for this restaurant.'),
            ], 403);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:10',
            'order_number' => 'nullable|string|max:100',
            'order_id' => 'nullable|integer',
            'table_number' => 'nullable|string|max:50',
            'waiter_id' => 'nullable|integer',
            'categories' => 'nullable|array',
            'categories.*' => 'string|max:50',
            'comment' => 'nullable|string|max:1000',
            'is_anonymous' => 'nullable|boolean',
            'customer_name' => 'nullable|string|max:100',
            'customer_phone' => 'nullable|string|max:50',
            'idempotency_key' => 'nullable|string|max:64',
        ]);

        try {
            $rating = $tipRatingService->submitRating(
                vendor: $vendor,
                validated: $validated,
                ip: $request->ip(),
                userAgent: $request->userAgent()
            );

            return response()->json([
                'success' => true,
                'message' => __('Thank you for your valuable feedback!'),
                'rating' => [
                    'id' => $rating->uuid,
                    'rating' => $rating->rating,
                    'created_at' => $rating->created_at->diffForHumans(),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Customer submits direct tip (standalone or table-based).
     */
    public function submitDirectTip(Request $request, string $vendor_slug, TipRatingService $tipRatingService): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();

        if (! $vendor->isTipsEnabled()) {
            return response()->json([
                'success' => false,
                'message' => __('Tips are currently disabled for this restaurant.'),
            ], 403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'nullable|string|in:cash,pos_terminal,idram,telcell,arca,stripe',
            'table_number' => 'nullable|string|max:50',
            'waiter_id' => 'nullable|integer',
            'location_id' => 'nullable|integer',
            'order_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:255',
            'tip_type' => 'nullable|string|in:percentage,fixed,custom',
            'idempotency_key' => 'nullable|string|max:64',
        ]);

        try {
            $tip = $tipRatingService->recordDirectTip($vendor, $validated);

            return response()->json([
                'success' => true,
                'message' => __('Thank you for tipping our staff!'),
                'tip' => [
                    'id' => $tip->uuid,
                    'amount' => (float) $tip->amount,
                    'currency' => $tip->currency,
                    'status' => $tip->payment_status,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Show dedicated standalone customer rating and tip screen.
     */
    public function showRatingPage(Request $request, string $vendor_slug, ?string $order_number = null)
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();
        $settings = $vendor->getTipRatingSetting();

        $order = null;
        if ($order_number) {
            $order = Order::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->where(function ($q) use ($order_number) {
                    $q->where('order_number', $order_number)
                        ->orWhere('tracking_token', $order_number);
                })
                ->with(['items.product', 'waiter'])
                ->first();
        }

        $table = $request->get('table') ?? ($order?->table_number ?? null);
        $waiters = User::where('vendor_id', $vendor->id)
            ->whereIn('role', ['staff', 'manager'])
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $lang = session('app_locale', $vendor->getDefaultLanguageCode() ?: 'hy');
        App::setLocale($lang);

        return view('storefront.rating', compact(
            'vendor',
            'settings',
            'order',
            'table',
            'waiters',
            'lang'
        ));
    }

    /**
     * Show customer shared bill view with live split capabilities.
     */
    public function showSharedBill(Request $request, string $vendor_slug, string $token, SplitBillService $splitBillService)
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();

        if (! $vendor->isSplitBillEnabled()) {
            return redirect()->route('client.menu', ['vendor_slug' => $vendor->slug])
                ->with('error', __('Split bill is currently disabled for this restaurant.'));
        }

        $order = $this->resolveAndAuthorizeOrder($vendor, $token, $request);
        if (! $order) {
            abort(404, __('Order not found or invalid tracking token.'));
        }

        $splitBill = $splitBillService->getOrCreateSplitBill($order);
        $settings = $vendor->getSplitBillSetting();
        $items = $splitBillService->getItemizedAvailability($order);

        $lang = session('app_locale', $vendor->getDefaultLanguageCode() ?: 'hy');
        App::setLocale($lang);

        return view('storefront.split_bill', compact(
            'vendor',
            'order',
            'splitBill',
            'settings',
            'items',
            'lang'
        ));
    }

    /**
     * Get live JSON status of the bill and its split participants.
     */
    public function getBillStatus(Request $request, string $vendor_slug, string $token, SplitBillService $splitBillService): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();

        if (! $vendor->isSplitBillEnabled()) {
            return response()->json([
                'success' => false,
                'error' => 'SPLIT_BILL_DISABLED',
                'message' => __('Split bill is disabled for this restaurant.'),
            ], 403);
        }

        $order = $this->resolveAndAuthorizeOrder($vendor, $token, $request);
        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => __('Order not found.'),
            ], 404);
        }

        $splitBill = $splitBillService->getOrCreateSplitBill($order);
        $settings = $vendor->getSplitBillSetting();
        $items = $splitBillService->getItemizedAvailability($order);

        $payments = $splitBill->payments()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($p) {
                return [
                    'uuid' => $p->uuid,
                    'participant_name' => $p->participant_name,
                    'amount' => (float) $p->amount,
                    'currency' => $p->currency,
                    'status' => $p->status,
                    'payment_method' => $p->payment_method,
                    'is_paid' => $p->isPaid(),
                    'created_at_time' => $p->created_at->format('H:i'),
                ];
            });

        return response()->json([
            'success' => true,
            'order' => [
                'order_number' => $order->order_number,
                'tracking_token' => $order->tracking_token,
                'table_number' => $order->table_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total_amount' => (float) $order->total_amount,
                'currency' => $vendor->currency ?? '֏',
            ],
            'bill' => [
                'split_token' => $splitBill->split_token,
                'status' => $splitBill->status,
                'total_amount' => (float) $splitBill->total_amount,
                'paid_amount' => (float) $splitBill->paid_amount,
                'remaining_amount' => (float) $splitBill->remaining_amount,
                'currency' => $splitBill->currency,
                'is_fully_paid' => $splitBill->isFullyPaid(),
            ],
            'settings' => [
                'allow_equal_split' => (bool) $settings->allow_equal_split,
                'allow_item_split' => (bool) $settings->allow_item_split,
                'allow_custom_amount' => (bool) $settings->allow_custom_amount,
                'allow_share_link' => (bool) $settings->allow_share_link,
                'max_participants' => (int) $settings->max_participants,
                'require_staff_approval' => (bool) $settings->require_staff_approval,
            ],
            'payments' => $payments,
            'items' => $items,
        ]);
    }

    /**
     * Submit a split payment (equal share, item claim, or custom amount).
     */
    public function submitSplitPayment(Request $request, string $vendor_slug, string $token, SplitBillService $splitBillService): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();

        if (! $vendor->isSplitBillEnabled()) {
            return response()->json([
                'success' => false,
                'error' => 'SPLIT_BILL_DISABLED',
                'message' => __('Split bill is disabled for this restaurant.'),
            ], 403);
        }

        $order = $this->resolveAndAuthorizeOrder($vendor, $token, $request);
        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => __('Order not found.'),
            ], 404);
        }

        $validated = $request->validate([
            'split_type' => 'required|string|in:equal,by_item,custom',
            'payment_method' => 'required|string',
            'participant_name' => 'nullable|string|max:100',
            'amount' => 'nullable|numeric|min:0.01',
            'participants_count' => 'nullable|integer|min:2|max:50',
            'items' => 'nullable|array',
            'items.*.order_item_id' => 'required_with:items|integer',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'idempotency_key' => 'nullable|string|max:64',
        ]);

        $splitBill = $splitBillService->getOrCreateSplitBill($order);
        $settings = $vendor->getSplitBillSetting();

        // Method-specific checks and amount calculation
        $amountToPay = 0.0;
        $itemsBreakdown = null;

        try {
            if ($validated['split_type'] === 'equal') {
                if (! $settings->allow_equal_split) {
                    throw new \InvalidArgumentException(__('Equal split is not permitted by restaurant settings.'));
                }
                $count = (int) ($validated['participants_count'] ?? 2);
                $equalCalc = $splitBillService->calculateEqualSplit($splitBill, $count);
                $amountToPay = (float) $equalCalc['per_person_amount'];
            } elseif ($validated['split_type'] === 'by_item') {
                if (! $settings->allow_item_split) {
                    throw new \InvalidArgumentException(__('Splitting by dish is not permitted by restaurant settings.'));
                }
                $itemCalc = $splitBillService->calculateItemSplit($order, $validated['items'] ?? []);
                $amountToPay = (float) $itemCalc['total_amount'];
                $itemsBreakdown = $itemCalc['items_breakdown'];
            } elseif ($validated['split_type'] === 'custom') {
                if (! $settings->allow_custom_amount) {
                    throw new \InvalidArgumentException(__('Custom amount partial payment is not permitted by restaurant settings.'));
                }
                $amountToPay = (float) ($validated['amount'] ?? 0);
            }

            $result = $splitBillService->initiateSplitPayment(
                bill: $splitBill,
                amount: $amountToPay,
                paymentMethod: $validated['payment_method'],
                participantName: $validated['participant_name'] ?? null,
                itemsBreakdown: $itemsBreakdown,
                idempotencyKey: $validated['idempotency_key'] ?? null
            );

            return response()->json(array_merge([
                'success' => true,
                'order_number' => $order->order_number,
                'remaining_amount' => (float) $splitBill->fresh()->remaining_amount,
            ], $result));
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Split bill payment initiation failed: '.$e->getMessage(), [
                'order' => $order->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Unable to process split payment. Please try again or ask your waiter.'),
            ], 500);
        }
    }

    /**
     * Helper to resolve and authorize order lookup via token or session.
     */
    protected function resolveAndAuthorizeOrder(Vendor $vendor, string $token, Request $request): ?Order
    {
        $isTokenLookup = str_starts_with($token, 'trk_');

        $order = Order::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where(function ($q) use ($token, $isTokenLookup) {
                if ($isTokenLookup) {
                    $q->where('tracking_token', $token);
                } else {
                    $q->where('order_number', $token)
                        ->orWhere('tracking_token', $token);
                }
            })
            ->with(['items.product', 'vendor'])
            ->first();

        if (! $order) {
            return null;
        }

        if (! $isTokenLookup) {
            $providedToken = $request->query('token') ?? $request->header('X-Tracking-Token');
            $sessionAuthorized = session('placed_order_'.$order->order_number) === $order->tracking_token
                || in_array($order->tracking_token, (array) session('active_tracking_tokens', []), true);

            $isStaff = $request->user() && (int) $request->user()->vendor_id === (int) $vendor->id;

            $isAuthorized = ($providedToken && hash_equals($order->tracking_token, (string) $providedToken))
                || $sessionAuthorized
                || $isStaff;

            if (! $isAuthorized) {
                return null;
            }
        }

        return $order;
    }
}
