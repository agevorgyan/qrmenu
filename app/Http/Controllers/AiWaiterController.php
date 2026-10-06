<?php

namespace App\Http\Controllers;

use App\Models\AiWaiterSession;
use App\Models\LocationProductOverride;
use App\Models\Order;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\AiQuotaService;
use App\Services\AiWaiterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiWaiterController extends Controller
{
    public function __construct(
        public AiWaiterService $aiWaiterService,
        public AiQuotaService $quotaService
    ) {}

    /**
     * Get public configuration for AI Waiter.
     */
    public function config(Request $request, string $vendor_slug): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();
        $config = $vendor->getAiWaiterConfig();

        return response()->json([
            'success' => true,
            'enabled' => (bool) $vendor->ai_waiter_enabled,
            'waiter_name' => $vendor->getAiWaiterName(),
            'avatar' => $vendor->ai_waiter_avatar,
            'welcome_text' => $vendor->ai_waiter_welcome_text,
            'languages' => $vendor->getAiWaiterLanguages(),
            'auto_popup' => $config['auto_popup'] ?? true,
            'free_text_enabled' => $config['free_text_enabled'] ?? true,
            'ai_chat_enabled' => $config['ai_chat_enabled'] ?? true,
            'currency' => $vendor->currency,
        ]);
    }

    /**
     * Get question library for current language.
     */
    public function questions(Request $request, string $vendor_slug): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();
        $lang = $this->resolveLanguage($request, $vendor);
        $questions = $this->aiWaiterService->getQuestionLibrary($lang);

        return response()->json([
            'success' => true,
            'lang' => $lang,
            'questions' => array_values($questions),
        ]);
    }

    /**
     * Start an AI Waiter session.
     * Uses cryptographically random opaque tokens.
     */
    public function startSession(Request $request, string $vendor_slug): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();
        $this->enforceRateLimits($request, $vendor, null);

        $lang = $this->resolveLanguage($request, $vendor);
        $locationId = $request->input('location_id') ? (int) $request->input('location_id') : null;
        if ($locationId && ! $vendor->locations()->where('id', $locationId)->exists()) {
            $locationId = null;
        }
        $tableNumber = $request->input('table_number');

        $session = AiWaiterSession::create([
            'vendor_id' => $vendor->id,
            'location_id' => $locationId,
            'session_token' => AiWaiterSession::generateSecureToken(),
            'table_number' => $tableNumber,
            'language' => $lang,
            'status' => 'started',
            'preferences' => [],
            'questions_history' => [],
            'answers_history' => [],
            'recommendations' => [],
            'cart_items' => [],
        ]);

        $nextQuestion = $this->aiWaiterService->getNextQuestion($vendor, [], [], $lang);

        return response()->json([
            'success' => true,
            'session_id' => $session->session_token,
            'session_token' => $session->session_token,
            'language' => $lang,
            'allowed_languages' => $vendor->getAiWaiterLanguages(),
            'allowed_language_details' => $vendor->getAiWaiterLanguageDetails(),
            'waiter_name' => $vendor->getAiWaiterName(),
            'next_question' => $nextQuestion,
        ]);
    }

    /**
     * Set language on session.
     */
    public function setLanguage(Request $request, string $vendor_slug, string $token): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();
        $this->enforceRateLimits($request, $vendor, $token);
        $session = $this->resolveSession($vendor, $token, $request);

        $lang = strtolower((string) $request->input('language', 'en'));
        $allowed = $vendor->getAiWaiterLanguages();
        if (! in_array($lang, $allowed, true)) {
            $lang = $allowed[0] ?? 'en';
        }

        $preferences = $session->preferences ?? [];
        $preferences['language'] = $lang;

        $session->update([
            'language' => $lang,
            'preferences' => $preferences,
        ]);

        session(['app_locale' => $lang, 'locale' => $lang]);

        $nextQuestion = $this->aiWaiterService->getNextQuestion(
            $vendor,
            $preferences,
            $session->questions_history ?? [],
            $lang
        );

        return response()->json([
            'success' => true,
            'session_id' => $session->session_token,
            'session_token' => $session->session_token,
            'language' => $lang,
            'next_question' => $nextQuestion,
        ]);
    }

    /**
     * Submit an answer to a question.
     */
    public function submitAnswer(Request $request, string $vendor_slug, string $token): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();
        $this->enforceRateLimits($request, $vendor, $token);
        $session = $this->resolveSession($vendor, $token, $request);

        $questionKey = (string) $request->input('question_key');
        $answerValue = $request->input('answer_value');
        $freeText = $request->input('free_text');

        $lang = $session->language ?? $this->resolveLanguage($request, $vendor);
        $preferences = $session->preferences ?? [];

        // Save question key to preferences
        if (! empty($questionKey)) {
            $preferences[$questionKey] = $answerValue;
        }

        // Parse free text if provided
        if (! empty($freeText)) {
            $parsed = $this->aiWaiterService->parseFreeText((string) $freeText, $lang, $vendor);
            $preferences = array_merge($preferences, $parsed);
        }

        $questionsHistory = (array) ($session->questions_history ?? []);
        if (! empty($questionKey) && ! in_array($questionKey, $questionsHistory, true)) {
            $questionsHistory[] = $questionKey;
        }

        $answersHistory = (array) ($session->answers_history ?? []);
        $answersHistory[] = [
            'question_key' => $questionKey,
            'answer_value' => $answerValue,
            'free_text' => $freeText,
            'answered_at' => now()->toISOString(),
        ];

        $nextQuestion = $this->aiWaiterService->getNextQuestion(
            $vendor,
            $preferences,
            $questionsHistory,
            $lang
        );

        $session->update([
            'preferences' => $preferences,
            'questions_history' => $questionsHistory,
            'answers_history' => $answersHistory,
            'status' => 'questions_in_progress',
        ]);

        return response()->json([
            'success' => true,
            'session_id' => $session->session_token,
            'session_token' => $session->session_token,
            'is_ready_for_recommendations' => $nextQuestion === null,
            'next_question' => $nextQuestion,
            'preferences' => $preferences,
        ]);
    }

    /**
     * Get next question for this session.
     */
    public function nextQuestion(Request $request, string $vendor_slug, string $token): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();
        $this->enforceRateLimits($request, $vendor, $token);
        $session = $this->resolveSession($vendor, $token, $request);

        $lang = $session->language ?? $this->resolveLanguage($request, $vendor);
        $next = $this->aiWaiterService->getNextQuestion(
            $vendor,
            $session->preferences ?? [],
            $session->questions_history ?? [],
            $lang
        );

        return response()->json([
            'success' => true,
            'session_id' => $session->session_token,
            'session_token' => $session->session_token,
            'is_ready_for_recommendations' => $next === null,
            'next_question' => $next,
        ]);
    }

    /**
     * Generate and retrieve personalized recommendations for the session.
     */
    public function recommendations(Request $request, string $vendor_slug, string $token): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();
        $this->enforceRateLimits($request, $vendor, $token);
        $this->enforceQuotas($vendor);
        $session = $this->resolveSession($vendor, $token, $request);

        $lang = $session->language ?? $this->resolveLanguage($request, $vendor);
        $locationId = $session->location_id ?: ($request->input('location_id') ? (int) $request->input('location_id') : null);

        $result = $this->aiWaiterService->recommendDishes(
            $vendor,
            $session->preferences ?? [],
            $session->preferences['free_text'] ?? null,
            $lang,
            $locationId
        );

        $session->update([
            'status' => 'recommended',
            'recommendations' => [
                'main' => array_column($result['main_recommendations'], 'id'),
                'secondary' => array_column($result['secondary_recommendations'], 'id'),
                'pairing_drink' => $result['pairing_drink']['id'] ?? null,
                'bundle_items' => isset($result['bundle']['items']) ? array_column($result['bundle']['items'], 'id') : [],
            ],
        ]);

        return response()->json([
            'success' => true,
            'session_id' => $session->session_token,
            'session_token' => $session->session_token,
            'waiter_name' => $vendor->getAiWaiterName(),
            'commentary' => $result['commentary'],
            'main_recommendations' => $result['main_recommendations'],
            'secondary_recommendations' => $result['secondary_recommendations'],
            'pairing_drink' => $result['pairing_drink'],
            'bundle' => $result['bundle'],
            // Backwards-compatible field
            'recommendations' => $result['main_recommendations'],
        ]);
    }

    /**
     * Conversational Q&A chat grounded strictly in menu items.
     */
    public function chat(Request $request, string $vendor_slug, string $token): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();
        $this->enforceRateLimits($request, $vendor, $token);
        $this->enforceQuotas($vendor);
        $session = $this->resolveSession($vendor, $token, $request);

        $message = trim((string) $request->input('message'));
        if ($message === '') {
            return response()->json([
                'success' => false,
                'message' => 'Message cannot be empty.',
            ], 422);
        }

        $lang = $session->language ?? $this->resolveLanguage($request, $vendor);
        $locationId = $session->location_id ?: ($request->input('location_id') ? (int) $request->input('location_id') : null);

        $chatResponse = $this->aiWaiterService->answerChatQuery(
            $vendor,
            $message,
            $session->toArray(),
            $lang,
            $locationId
        );

        return response()->json([
            'success' => true,
            'session_id' => $session->session_token,
            'session_token' => $session->session_token,
            'reply' => $chatResponse['reply'],
            'suggested_products' => $chatResponse['suggested_products'],
        ]);
    }

    /**
     * Track item or bundle addition to cart.
     * Validates vendor ownership, availability, location overrides, and server-side pricing.
     */
    public function addToCart(Request $request, string $vendor_slug, string $token): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();
        $this->enforceRateLimits($request, $vendor, $token);
        $session = $this->resolveSession($vendor, $token, $request);

        $productId = $request->input('product_id');
        $bundle = $request->input('bundle');
        $items = (array) ($session->cart_items ?? []);

        if (! empty($productId)) {
            $product = Product::withoutGlobalScopes()->where('id', $productId)->first();
            if (! $product) {
                return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
            }

            // Defense: Validate vendor ownership (prevent cross-vendor injection)
            if ((int) $product->vendor_id !== (int) $vendor->id) {
                return response()->json(['success' => false, 'message' => 'Product does not belong to this vendor.'], 422);
            }

            // Defense: Validate product availability
            if (! $product->is_available) {
                return response()->json(['success' => false, 'message' => 'Product is currently unavailable.'], 422);
            }

            // Defense: Resolve location override and calculate authoritative server-side price
            $authoritativePrice = (float) $product->price;
            if ($session->location_id) {
                $override = LocationProductOverride::where('vendor_id', $vendor->id)
                    ->where('location_id', $session->location_id)
                    ->where('product_id', $product->id)
                    ->first();

                if ($override) {
                    if (! $override->is_available) {
                        return response()->json(['success' => false, 'message' => 'Product is unavailable at this location.'], 422);
                    }
                    if ($override->override_price !== null) {
                        $authoritativePrice = (float) $override->override_price;
                    }
                }
            }

            $items[] = [
                'type' => 'product',
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => $authoritativePrice,
                'authoritative_price' => $authoritativePrice,
                'location_id' => $session->location_id,
                'added_at' => now()->toISOString(),
            ];
        } elseif (! empty($bundle) && is_array($bundle)) {
            // Defense: Validate every item in the bundle against vendor ownership and availability
            $rawItems = (array) ($bundle['items'] ?? []);
            $validatedItems = [];
            $authoritativeBundleTotal = 0.0;

            foreach ($rawItems as $rawItem) {
                $bId = $rawItem['id'] ?? $rawItem['product_id'] ?? null;
                if (! $bId) {
                    continue;
                }
                $bProd = Product::withoutGlobalScopes()->where('id', $bId)->first();
                if (! $bProd || (int) $bProd->vendor_id !== (int) $vendor->id || ! $bProd->is_available) {
                    continue;
                }

                $bPrice = (float) $bProd->price;
                if ($session->location_id) {
                    $bOverride = LocationProductOverride::where('vendor_id', $vendor->id)
                        ->where('location_id', $session->location_id)
                        ->where('product_id', $bProd->id)
                        ->first();

                    if ($bOverride) {
                        if (! $bOverride->is_available) {
                            continue;
                        }
                        if ($bOverride->override_price !== null) {
                            $bPrice = (float) $bOverride->override_price;
                        }
                    }
                }

                $validatedItems[] = [
                    'id' => $bProd->id,
                    'name' => $bProd->name,
                    'price' => $bPrice,
                ];
                $authoritativeBundleTotal += $bPrice;
            }

            if (! empty($validatedItems)) {
                $items[] = [
                    'type' => 'bundle',
                    'bundle_data' => [
                        'title' => $bundle['title'] ?? 'Bundle',
                        'items' => $validatedItems,
                        'total_price' => $authoritativeBundleTotal,
                    ],
                    'added_at' => now()->toISOString(),
                ];
            }
        }

        $session->update([
            'status' => 'added_to_cart',
            'cart_items' => $items,
        ]);

        return response()->json([
            'success' => true,
            'session_id' => $session->session_token,
            'session_token' => $session->session_token,
            'cart_items_count' => count($items),
        ]);
    }

    /**
     * Mark AI session as completed or converted to an order.
     * Never trusts client for order totals or payment status.
     */
    public function complete(Request $request, string $vendor_slug, string $token): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();
        $this->enforceRateLimits($request, $vendor, $token);
        $session = $this->resolveSession($vendor, $token, $request);

        $orderId = $request->input('order_id');
        $orderNumber = $request->input('order_number');
        $authoritativeOrderAmount = 0.0;

        // Verify order strictly belongs to this vendor
        if (! empty($orderId)) {
            $order = Order::where('vendor_id', $vendor->id)->find($orderId);
            if ($order) {
                $authoritativeOrderAmount = (float) $order->total_amount;
            } else {
                $orderId = null;
            }
        } elseif (! empty($orderNumber)) {
            $order = Order::where('vendor_id', $vendor->id)->where('order_number', $orderNumber)->first();
            if ($order) {
                $orderId = $order->id;
                $authoritativeOrderAmount = (float) $order->total_amount;
            }
        }

        // If no verified order was linked, compute authoritative total from session cart items
        if (! $orderId) {
            $cartItems = (array) ($session->cart_items ?? []);
            foreach ($cartItems as $item) {
                if (($item['type'] ?? '') === 'product') {
                    $authoritativeOrderAmount += (float) ($item['price'] ?? 0);
                } elseif (($item['type'] ?? '') === 'bundle') {
                    $authoritativeOrderAmount += (float) ($item['bundle_data']['total_price'] ?? 0);
                }
            }
        }

        $session->update([
            'status' => $orderId ? 'order_placed' : 'completed',
            'order_id' => $orderId,
            'total_order_amount' => $authoritativeOrderAmount,
            'completed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'session_id' => $session->session_token,
            'session_token' => $session->session_token,
            'status' => $session->status,
            'authoritative_total' => $authoritativeOrderAmount,
        ]);
    }

    /**
     * Legacy direct recommendation endpoint.
     */
    public function recommend(Request $request, string $vendor_slug): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();
        $this->enforceRateLimits($request, $vendor, null);
        $this->enforceQuotas($vendor);

        $preferences = [
            'craving' => $request->input('craving'),
            'occasion' => $request->input('occasion'),
            'drink_preference' => $request->input('drink_preference'),
            'dietary' => (array) $request->input('dietary', []),
        ];

        $prompt = $request->input('prompt');
        $lang = $this->resolveLanguage($request, $vendor);
        $locationId = $request->input('location_id') ? (int) $request->input('location_id') : null;

        $result = $this->aiWaiterService->recommendDishes(
            $vendor,
            $preferences,
            $prompt,
            $lang,
            $locationId
        );

        return response()->json([
            'success' => true,
            'waiter_name' => $vendor->getAiWaiterName(),
            'commentary' => $result['commentary'],
            'recommendations' => $result['main_recommendations'],
            'main_recommendations' => $result['main_recommendations'],
            'secondary_recommendations' => $result['secondary_recommendations'],
            'pairing_drink' => $result['pairing_drink'],
            'bundle' => $result['bundle'],
        ]);
    }

    /**
     * Legacy pairings endpoint for a product.
     */
    public function pairings(Request $request, string $vendor_slug, int $productId): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();
        $this->enforceRateLimits($request, $vendor, null);
        $this->enforceQuotas($vendor);

        $product = Product::where('vendor_id', $vendor->id)
            ->where('id', $productId)
            ->where('is_available', true)
            ->firstOrFail();

        $lang = $this->resolveLanguage($request, $vendor);
        $locationId = $request->input('location_id') ? (int) $request->input('location_id') : null;

        $result = $this->aiWaiterService->recommendDishes(
            $vendor,
            ['craving' => $product->category?->name],
            $product->name,
            $lang,
            $locationId
        );

        return response()->json([
            'success' => true,
            'product_id' => $product->id,
            'pairings' => [
                'drink' => $result['pairing_drink'],
                'side' => $result['secondary_recommendations'][0] ?? null,
                'pairing_note' => $result['commentary'] ?? '',
            ],
        ]);
    }

    /**
     * Resolve session by cryptographically random opaque token.
     * Prevents sequential enumeration attacks (e.g. /session/1/...).
     */
    protected function resolveSession(Vendor $vendor, string $token, ?Request $request = null): AiWaiterSession
    {
        $resolvedToken = $request?->header('X-Session-Token') ?? $token;

        $session = AiWaiterSession::where('vendor_id', $vendor->id)
            ->where('session_token', $resolvedToken)
            ->first();

        if (! $session) {
            abort(404, 'AI Waiter session not found.');
        }

        return $session;
    }

    /**
     * Multi-tier rate limiting enforcement:
     * - IP level
     * - Session level
     * - Vendor level
     */
    protected function enforceRateLimits(Request $request, Vendor $vendor, ?string $token): void
    {
        $ip = $request->ip() ?: '127.0.0.1';
        $result = $this->quotaService->checkRateLimits($vendor, $token, $ip);

        if (! $result['allowed']) {
            abort(response()->json([
                'success' => false,
                'message' => $result['reason'] ?? 'Rate limit exceeded.',
                'tier' => $result['tier'] ?? 'rate_limit',
                'retry_after' => $result['retry_after'] ?? 60,
            ], 429, [
                'Retry-After' => (string) ($result['retry_after'] ?? 60),
            ]));
        }

        $this->quotaService->hitRateLimits($vendor, $token, $ip);
    }

    /**
     * Quota enforcement before generative AI calls:
     * - Requests per min/hour/day/month
     * - Token limits
     * - Cost/spending limits
     */
    protected function enforceQuotas(Vendor $vendor): void
    {
        $check = $this->quotaService->checkQuotas($vendor);

        if (! $check['allowed']) {
            abort(response()->json([
                'success' => false,
                'message' => $check['reason'] ?? 'AI usage quota exceeded.',
                'tier' => $check['tier'] ?? 'quota',
            ], 429));
        }
    }

    /**
     * Helper to resolve supported language.
     */
    protected function resolveLanguage(Request $request, Vendor $vendor): string
    {
        $lang = $request->input('lang', $request->input('language', session('app_locale', 'en')));
        $allowed = $vendor->getAiWaiterLanguages();

        if (in_array($lang, $allowed, true)) {
            return $lang;
        }

        return $allowed[0] ?? 'en';
    }
}
