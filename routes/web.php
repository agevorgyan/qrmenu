<?php

use App\Http\Controllers\AdminEventInquiryController;
use App\Http\Controllers\AdminReservationController;
use App\Http\Controllers\AdminSplitBillController;
use App\Http\Controllers\AdminTableController;
use App\Http\Controllers\AdminTipRatingController;
use App\Http\Controllers\AdminWaiterController;
use App\Http\Controllers\AiMenuController;
use App\Http\Controllers\AiWaiterController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\ClientStorefrontController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EmailWebhookController;
use App\Http\Controllers\FloorPlanController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\MenuBuilderController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PosAuthController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PosDeviceController;
use App\Http\Controllers\PosSettingController;
use App\Http\Controllers\ProfileSecurityController;
use App\Http\Controllers\QrStudioController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\ReservationNotificationController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\StorefrontEventInquiryController;
use App\Http\Controllers\StorefrontReservationController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SuperAdminEmailController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\VendorAdminController;
use App\Http\Controllers\VendorSettingsController;
use App\Http\Middleware\EnsurePlanHasFeature;
use App\Http\Middleware\EnsurePosAccess;
use App\Http\Middleware\EnsureSubscriptionIsActive;
use App\Services\Localization\LocaleManager;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

// 1. Landing & Client Storefront PWA Routes
Route::get('/', [LandingController::class, 'index'])->name('landing');

Route::get('/manifest.json', function (TenantContext $tenantContext) {
    $vendor = $tenantContext->getTenant();
    if ($vendor) {
        return app(ClientStorefrontController::class)->manifest($vendor->slug);
    }
    abort(404);
});

Route::get('/sw.js', function (TenantContext $tenantContext) {
    $vendor = $tenantContext->getTenant();
    if ($vendor) {
        return app(ClientStorefrontController::class)->serviceWorker($vendor->slug);
    }
    abort(404);
});

Route::get('/m/{vendor_slug}/manifest.json', [ClientStorefrontController::class, 'manifest'])->name('client.manifest');
Route::get('/m/{vendor_slug}/sw.js', [ClientStorefrontController::class, 'serviceWorker'])->name('client.sw');

// Table Reservation & Pre-Order Storefront Routes
Route::get('/m/{vendor_slug}/reserve/{location_slug?}', [StorefrontReservationController::class, 'showReservationForm'])
    ->middleware('throttle:60,1')
    ->name('client.reserve');
Route::get('/m/{vendor_slug}/reservation/{reference}', [StorefrontReservationController::class, 'showReservationDetails'])
    ->middleware('throttle:60,1')
    ->name('client.reservation.show');
Route::get('/api/m/{vendor_slug}/reserve/availability', [StorefrontReservationController::class, 'getAvailability'])
    ->middleware('throttle:60,1')
    ->name('client.reserve.availability');
Route::post('/api/m/{vendor_slug}/reserve', [StorefrontReservationController::class, 'submitReservation'])
    ->middleware('throttle:15,1')
    ->name('client.reserve.submit');
Route::post('/api/m/{vendor_slug}/reservation/{reference}/cancel', [StorefrontReservationController::class, 'cancelReservation'])
    ->middleware('throttle:30,1')
    ->name('client.reservation.cancel');

// Event Planning Inquiries Storefront Routes
Route::get('/m/{vendor_slug}/events/{location_slug?}', [StorefrontEventInquiryController::class, 'showInquiryForm'])
    ->middleware('throttle:60,1')
    ->name('client.event_inquiry');
Route::get('/m/{vendor_slug}/events/status/{reference}', [StorefrontEventInquiryController::class, 'showInquiryStatus'])
    ->middleware('throttle:60,1')
    ->name('client.event_inquiry.status');
Route::post('/api/m/{vendor_slug}/events', [StorefrontEventInquiryController::class, 'submitInquiry'])
    ->middleware('throttle:15,1')
    ->name('client.event_inquiry.submit');

// Customer Feedback & Tips Rating Storefront Routes
Route::get('/m/{vendor_slug}/rate/{order_number?}', [ClientStorefrontController::class, 'showRatingPage'])
    ->middleware('throttle:60,1')
    ->name('client.rate');
Route::post('/api/m/{vendor_slug}/rate', [ClientStorefrontController::class, 'submitRating'])
    ->middleware('throttle:30,1')
    ->name('client.rating.submit');
Route::post('/api/m/{vendor_slug}/tip', [ClientStorefrontController::class, 'submitDirectTip'])
    ->middleware('throttle:30,1')
    ->name('client.tip.submit');

// Split Bill Customer Storefront Routes
Route::get('/m/{vendor_slug}/bill/{token}', [ClientStorefrontController::class, 'showSharedBill'])
    ->middleware('throttle:60,1')
    ->name('client.bill.show');
Route::get('/api/m/{vendor_slug}/bill/{token}', [ClientStorefrontController::class, 'getBillStatus'])
    ->middleware('throttle:60,1')
    ->name('client.bill.status');
Route::post('/api/m/{vendor_slug}/bill/{token}/split', [ClientStorefrontController::class, 'submitSplitPayment'])
    ->middleware('throttle:30,1')
    ->name('client.bill.split');

Route::get('/m/{vendor_slug}/{location_slug?}', [ClientStorefrontController::class, 'showMenu'])
    ->middleware('throttle:120,1')
    ->name('client.menu');

Route::post('/api/m/{vendor_slug}/language', [ClientStorefrontController::class, 'setLanguage'])
    ->middleware('throttle:60,1')
    ->name('client.language.set');

// Rate-limited public order & waiter endpoints (anti-spam & DDoS protection)
Route::middleware(['throttle:15,1', EnsurePlanHasFeature::class.':orders.online'])->group(function () {
    Route::post('/api/m/{vendor_slug}/order', [ClientStorefrontController::class, 'submitOrder'])->name('client.order.submit');
    Route::post('/api/m/{vendor_slug}/call-waiter', [ClientStorefrontController::class, 'callWaiter'])->name('client.waiter.call');
});
Route::get('/api/m/{vendor_slug}/order/{order_number}/status', [ClientStorefrontController::class, 'orderStatus'])
    ->middleware('throttle:60,1')
    ->name('client.order.status');

Route::get('/payment/callback/{vendor_slug}/{reference}', [ClientStorefrontController::class, 'paymentCallback'])
    ->middleware('throttle:30,1')
    ->name('client.payment.callback');

// Payment Webhook endpoint for asynchronous server-to-server notifications
Route::post('/api/webhooks/payment/{gateway}', [PaymentWebhookController::class, 'handleWebhook'])
    ->name('api.webhooks.payment')
    ->middleware('throttle:60,1');

// AI Waiter Advisor Endpoints
Route::prefix('/api/m/{vendor_slug}/ai-waiter')->middleware('throttle:60,1')->group(function () {
    Route::get('/config', [AiWaiterController::class, 'config'])->name('client.ai_waiter.config');

    Route::middleware([EnsurePlanHasFeature::class.':ai.waiter'])->group(function () {
        Route::get('/questions', [AiWaiterController::class, 'questions'])->name('client.ai_waiter.questions');
        Route::post('/session', [AiWaiterController::class, 'startSession'])->name('client.ai_waiter.session.start');
        Route::post('/session/{token}/language', [AiWaiterController::class, 'setLanguage'])->name('client.ai_waiter.session.language');
        Route::post('/session/{token}/answer', [AiWaiterController::class, 'submitAnswer'])->name('client.ai_waiter.session.answer');
        Route::post('/session/{token}/next-question', [AiWaiterController::class, 'nextQuestion'])->name('client.ai_waiter.session.next_question');
        Route::post('/session/{token}/recommendations', [AiWaiterController::class, 'recommendations'])->name('client.ai_waiter.session.recommendations');
        Route::post('/session/{token}/chat', [AiWaiterController::class, 'chat'])->name('client.ai_waiter.session.chat');
        Route::post('/session/{token}/add-to-cart', [AiWaiterController::class, 'addToCart'])->name('client.ai_waiter.session.add_to_cart');
        Route::post('/session/{token}/reject', [AiWaiterController::class, 'rejectProduct'])->name('client.ai_waiter.session.reject');
        Route::post('/session/{token}/complete', [AiWaiterController::class, 'complete'])->name('client.ai_waiter.session.complete');

        // Legacy endpoints
        Route::post('/recommend', [AiWaiterController::class, 'recommend'])->name('client.ai_waiter.recommend');
        Route::get('/pairings/{product_id}', [AiWaiterController::class, 'pairings'])->name('client.ai_waiter.pairings');
    });
});

// Admin / Platform Language Switcher
Route::get('/lang/{locale}', function (Request $request, string $locale, LocaleManager $localeManager) {
    $targetLocale = strtolower(trim($locale));
    if ($localeManager->isValidLocale($targetLocale)) {
        session([
            'admin_locale' => $targetLocale,
            'app_locale' => $targetLocale,
            'locale' => $targetLocale,
        ]);
        App::setLocale($targetLocale);

        if (Auth::check()) {
            $user = Auth::user();
            if ($user) {
                $user->locale = $targetLocale;
                $user->saveQuietly();
                if ($user->vendor) {
                    session(['storefront_lang_'.$user->vendor->slug => $targetLocale]);
                }
            }
        }

        cookie()->queue('app_locale', $targetLocale, 525600);
        cookie()->queue('admin_locale', $targetLocale, 525600);
        cookie()->queue('locale', $targetLocale, 525600);

        // Sanitize previous URL so existing ?lang= or ?locale= params reflect the new selection
        $previousUrl = url()->previous();
        if ($previousUrl) {
            $parsed = parse_url($previousUrl);
            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $query);
                if (isset($query['lang']) || isset($query['locale'])) {
                    $query['lang'] = $targetLocale;
                    unset($query['locale']);
                    $queryString = http_build_query($query);
                    $cleanUrl = ($parsed['scheme'] ?? 'http').'://'.($parsed['host'] ?? 'localhost')
                        .(isset($parsed['port']) ? ':'.$parsed['port'] : '')
                        .($parsed['path'] ?? '/')
                        .($queryString ? '?'.$queryString : '')
                        .(isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '');

                    return redirect()->to($cleanUrl)
                        ->withCookie(cookie('app_locale', $targetLocale, 525600))
                        ->withCookie(cookie('admin_locale', $targetLocale, 525600))
                        ->withCookie(cookie('locale', $targetLocale, 525600));
                }
            }
        }

        return redirect()->back()
            ->withCookie(cookie('app_locale', $targetLocale, 525600))
            ->withCookie(cookie('admin_locale', $targetLocale, 525600))
            ->withCookie(cookie('locale', $targetLocale, 525600));
    }

    return redirect()->back();
})->name('lang.switch');

// Legal Documents
Route::get('/privacy-policy', function () {
    return view('legal.privacy');
})->name('legal.privacy');

Route::get('/terms-of-service', function () {
    return view('legal.terms');
})->name('legal.terms');

// 2. Auth & Registration Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// 2FA Challenge & Verification
Route::get('/login/2fa', [TwoFactorController::class, 'showChallenge'])->name('2fa.challenge');
Route::post('/login/2fa', [TwoFactorController::class, 'verify'])->middleware('throttle:10,1')->name('2fa.verify');
Route::post('/login/2fa/resend', [TwoFactorController::class, 'resendEmailCode'])->middleware('throttle:5,1')->name('2fa.resend');

// Password Reset Routes
Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware('throttle:5,1')->name('password.email');
Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');

// Captcha Refresh Endpoint
Route::get('/captcha/refresh', [AuthController::class, 'refreshCaptcha'])->name('captcha.refresh');

Route::get('/demo/login', [AuthController::class, 'showDemoLogin'])->name('demo.login');
Route::post('/demo/login', [AuthController::class, 'demoLogin'])->name('demo.login.post');

Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register.show');
Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:10,1')->name('register.post');

Route::get('/email/verify', [RegisterController::class, 'showVerificationNotice'])->name('verification.notice');
Route::get('/email/verify/{id}/{hash}', [RegisterController::class, 'verifyEmail'])->name('verification.verify');
Route::post('/email/verify/code', [RegisterController::class, 'verifyCode'])->middleware(['auth', 'throttle:10,1'])->name('verification.code');
Route::post('/email/verification-notification', [RegisterController::class, 'resendVerificationEmail'])->middleware(['auth', 'throttle:6,1'])->name('verification.send');

// Authenticated 2FA & Profile Security Routes
Route::middleware(['auth'])->group(function () {
    Route::post('/security/2fa/send-code', [ProfileSecurityController::class, 'sendTwoFactorCode'])->name('security.2fa.send_code');
    Route::get('/security/2fa/secret', [ProfileSecurityController::class, 'getSecretKey'])->name('security.2fa.secret');
    Route::post('/security/2fa/enable', [ProfileSecurityController::class, 'enableTwoFactor'])->name('security.2fa.enable');
    Route::post('/security/2fa/disable', [ProfileSecurityController::class, 'disableTwoFactor'])->name('security.2fa.disable');
    Route::post('/security/password', [ProfileSecurityController::class, 'updatePassword'])->name('security.password.update');
    Route::post('/security/email', [ProfileSecurityController::class, 'updateEmail'])->name('security.email.update');
    Route::get('/security/audit-logs', [ProfileSecurityController::class, 'auditLogs'])->name('security.audit_logs');
});

// 3. Super Admin Panel (/superadmin)
Route::middleware(['auth', 'role:superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/audit-logs', [SuperAdminController::class, 'auditLogs'])->name('audit_logs');
    Route::get('/vendors', [SuperAdminController::class, 'vendorsIndex'])->name('vendors.index');
    Route::post('/vendors', [SuperAdminController::class, 'storeVendor'])->name('vendors.store');
    Route::get('/vendors/{vendor}/edit', [SuperAdminController::class, 'editVendor'])->name('vendors.edit');
    Route::put('/vendors/{vendor}', [SuperAdminController::class, 'updateVendor'])->name('vendors.update');
    Route::post('/vendors/{vendor}/toggle', [SuperAdminController::class, 'toggleStatus'])->name('vendors.toggle');
    Route::post('/vendors/{vendor}/notifications', [SuperAdminController::class, 'updateVendorNotificationRestrictions'])->name('vendors.notifications');

    // Vendor Locations & Tables Management
    Route::post('/vendors/{vendor}/locations', [SuperAdminController::class, 'storeVendorLocation'])->name('vendors.locations.store');
    Route::put('/vendors/{vendor}/locations/{location}', [SuperAdminController::class, 'updateVendorLocation'])->name('vendors.locations.update');
    Route::delete('/vendors/{vendor}/locations/{location}', [SuperAdminController::class, 'destroyVendorLocation'])->name('vendors.locations.destroy');
    Route::post('/vendors/{vendor}/locations/{location}/tables', [SuperAdminController::class, 'updateVendorLocationTables'])->name('vendors.locations.tables');

    // Vendor Users Management
    Route::post('/vendors/{vendor}/users', [SuperAdminController::class, 'storeVendorUser'])->name('vendors.users.store');
    Route::put('/vendors/{vendor}/users/{user}', [SuperAdminController::class, 'updateVendorUser'])->name('vendors.users.update');
    Route::delete('/vendors/{vendor}/users/{user}', [SuperAdminController::class, 'destroyVendorUser'])->name('vendors.users.destroy');

    // Subscription Plans Management
    Route::get('/plans', [SuperAdminController::class, 'plansIndex'])->name('plans.index');
    Route::post('/plans', [SuperAdminController::class, 'storePlan'])->name('plans.store');
    Route::post('/plans/{plan}', [SuperAdminController::class, 'updatePlan'])->name('plans.update');
    Route::post('/plans/{plan}/toggle', [SuperAdminController::class, 'togglePlan'])->name('plans.toggle');
    Route::delete('/plans/{plan}', [SuperAdminController::class, 'destroyPlan'])->name('plans.destroy');

    // Vendor Subscriptions & Payment Logs
    Route::get('/subscriptions', [SuperAdminController::class, 'subscriptionsIndex'])->name('subscriptions.index');
    Route::get('/subscriptions/payments', [SuperAdminController::class, 'allPaymentsIndex'])->name('subscriptions.payments.index');
    Route::get('/subscriptions/audit-logs', [SuperAdminController::class, 'subscriptionAuditLogs'])->name('subscriptions.audit_logs');
    Route::post('/subscriptions/{vendor}', [SuperAdminController::class, 'updateVendorSubscription'])->name('subscriptions.update');
    Route::post('/subscriptions/{vendor}/suspend', [SuperAdminController::class, 'suspendVendorSubscription'])->name('subscriptions.suspend');
    Route::post('/subscriptions/{vendor}/restore', [SuperAdminController::class, 'restoreVendorSubscription'])->name('subscriptions.restore');
    Route::get('/subscriptions/{vendor}/lifecycle', [SuperAdminController::class, 'vendorLifecycleHistory'])->name('subscriptions.lifecycle');
    Route::get('/subscriptions/{vendor}/usage', [SuperAdminController::class, 'vendorUsageReporting'])->name('subscriptions.usage');
    Route::post('/subscriptions/{vendor}/payments', [SuperAdminController::class, 'storeVendorPayment'])->name('subscriptions.payments.store');
    Route::post('/subscriptions/{vendor}/overrides', [SuperAdminController::class, 'storeFeatureOverride'])->name('subscriptions.overrides.store');
    Route::delete('/subscriptions/{vendor}/overrides/{featureKey}', [SuperAdminController::class, 'destroyFeatureOverride'])->name('subscriptions.overrides.destroy');
    Route::post('/subscriptions/{vendor}/quota-overrides', [SuperAdminController::class, 'storeQuotaOverride'])->name('subscriptions.quota_overrides.store');
    Route::delete('/subscriptions/{vendor}/quota-overrides/{quotaKey}', [SuperAdminController::class, 'destroyQuotaOverride'])->name('subscriptions.quota_overrides.destroy');
    Route::post('/subscriptions/{vendor}/usage-meters/{quotaKey}/reset', [SuperAdminController::class, 'resetUsageMeter'])->name('subscriptions.usage_meters.reset');

    // Landing, System & Security Settings Management
    Route::get('/settings', [SuperAdminController::class, 'settingsIndex'])->name('settings.index');
    Route::post('/settings', [SuperAdminController::class, 'updateSettings'])->name('settings.update');
    Route::post('/settings/telegram/test', [SuperAdminController::class, 'testTelegramConnection'])->name('settings.telegram.test');
    Route::post('/settings/security', [SuperAdminController::class, 'updateProfileSecurity'])->name('settings.security');

    // Platform Languages & i18n Management
    Route::get('/languages', [LanguageController::class, 'index'])->name('languages.index');
    Route::post('/languages', [LanguageController::class, 'store'])->name('languages.store');
    Route::put('/languages/{language}', [LanguageController::class, 'update'])->name('languages.update');
    Route::post('/languages/{language}/toggle', [LanguageController::class, 'toggleActive'])->name('languages.toggle');
    Route::post('/languages/{language}/default', [LanguageController::class, 'setDefault'])->name('languages.default');

    // Centralized Email Management Console
    Route::prefix('emails')->name('emails.')->group(function () {
        Route::get('/overview', [SuperAdminEmailController::class, 'overview'])->name('overview');

        // Sender Identities
        Route::get('/senders', [SuperAdminEmailController::class, 'sendersIndex'])->name('senders.index');
        Route::post('/senders', [SuperAdminEmailController::class, 'storeSender'])->name('senders.store');
        Route::put('/senders/{sender}', [SuperAdminEmailController::class, 'updateSender'])->name('senders.update');
        Route::post('/senders/{sender}/toggle', [SuperAdminEmailController::class, 'toggleSender'])->name('senders.toggle');
        Route::delete('/senders/{sender}', [SuperAdminEmailController::class, 'destroySender'])->name('senders.destroy');

        // SMTP Connections
        Route::get('/connections', [SuperAdminEmailController::class, 'connectionsIndex'])->name('connections.index');
        Route::post('/connections', [SuperAdminEmailController::class, 'storeConnection'])->name('connections.store');
        Route::put('/connections/{connection}', [SuperAdminEmailController::class, 'updateConnection'])->name('connections.update');
        Route::post('/connections/{connection}/test', [SuperAdminEmailController::class, 'testConnection'])->name('connections.test');
        Route::post('/connections/{connection}/send-test', [SuperAdminEmailController::class, 'sendTestEmail'])->name('connections.send_test');
        Route::post('/connections/{connection}/default', [SuperAdminEmailController::class, 'setDefaultConnection'])->name('connections.default');
        Route::delete('/connections/{connection}', [SuperAdminEmailController::class, 'destroyConnection'])->name('connections.destroy');

        // Email Routing
        Route::get('/routing', [SuperAdminEmailController::class, 'routingIndex'])->name('routing.index');
        Route::put('/routing/{rule}', [SuperAdminEmailController::class, 'updateRouting'])->name('routing.update');

        // Email Templates
        Route::get('/templates', [SuperAdminEmailController::class, 'templatesIndex'])->name('templates.index');
        Route::put('/templates/{template}', [SuperAdminEmailController::class, 'updateTemplate'])->name('templates.update');
        Route::get('/templates/{template}/preview', [SuperAdminEmailController::class, 'previewTemplate'])->name('templates.preview');

        // Email Logs
        Route::get('/logs', [SuperAdminEmailController::class, 'logsIndex'])->name('logs.index');
        Route::get('/logs/export', [SuperAdminEmailController::class, 'exportLogs'])->name('logs.export');
        Route::get('/logs/{log}', [SuperAdminEmailController::class, 'showLog'])->name('logs.show');

        // Queue Monitor
        Route::get('/queue', [SuperAdminEmailController::class, 'queueIndex'])->name('queue.index');
        Route::post('/queue/retry/{uuid}', [SuperAdminEmailController::class, 'retryJob'])->name('queue.retry');
        Route::delete('/queue/cancel/{id}', [SuperAdminEmailController::class, 'cancelJob'])->name('queue.cancel');

        // Failed Emails
        Route::get('/failed', [SuperAdminEmailController::class, 'failedIndex'])->name('failed.index');
        Route::post('/failed/{log}/retry', [SuperAdminEmailController::class, 'retryEmail'])->name('failed.retry');
        Route::post('/failed/{log}/cancel', [SuperAdminEmailController::class, 'cancelEmail'])->name('failed.cancel');

        // Reports & Analytics
        Route::get('/reports', [SuperAdminEmailController::class, 'reportsIndex'])->name('reports.index');
        Route::get('/reports/export', [SuperAdminEmailController::class, 'exportReport'])->name('reports.export');

        // Settings
        Route::get('/settings', [SuperAdminEmailController::class, 'settingsIndex'])->name('settings.index');
        Route::post('/settings', [SuperAdminEmailController::class, 'updateSettings'])->name('settings.update');
    });
});

// Provider Webhook Endpoint (No CSRF needed, signature verified)
Route::post('/api/webhooks/email/{provider}', [EmailWebhookController::class, 'handle'])->name('webhooks.email');

// 4. Vendor Admin Panel (/admin)
Route::middleware(['auth', 'verified', 'role:vendor_owner,manager,staff,chef,cashier', EnsureSubscriptionIsActive::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [VendorAdminController::class, 'dashboard'])->name('dashboard');

    // Vendor Subscription Status & Payment History
    Route::get('/subscription', [VendorAdminController::class, 'subscriptionIndex'])->name('subscription');
    Route::get('/subscription/overview', [VendorAdminController::class, 'subscriptionIndex'])->name('subscription.index');
    Route::post('/subscription/renew', [VendorAdminController::class, 'renewSubscription'])->name('subscription.renew');
    Route::post('/subscription/cancel', [VendorAdminController::class, 'cancelSubscription'])->name('subscription.cancel');
    Route::post('/subscription/resume', [VendorAdminController::class, 'resumeSubscription'])->name('subscription.resume');
    Route::get('/subscription/invoices/{invoice}', [VendorAdminController::class, 'showInvoice'])->name('subscription.invoices.show');

    // Translation Management & AI Localization Studio
    Route::get('/translations', [TranslationController::class, 'index'])->name('translations.index');
    Route::get('/translations/{translation}/edit', [TranslationController::class, 'edit'])->name('translations.edit');
    Route::post('/translations/{translation}/action', [TranslationController::class, 'handleAction'])->name('translations.action');
    Route::post('/translations/{translation}/restore/{history}', [TranslationController::class, 'restore'])->name('translations.restore');
    Route::post('/translations/batch-ai', [TranslationController::class, 'batchAiTranslate'])->name('translations.batch_ai');
    Route::post('/translations/languages/sync', [TranslationController::class, 'syncVendorLanguages'])->name('translations.languages.sync');
    Route::get('/translations/glossary', [TranslationController::class, 'glossaryIndex'])->name('translations.glossary.index');
    Route::post('/translations/glossary', [TranslationController::class, 'glossaryStore'])->name('translations.glossary.store');
    Route::delete('/translations/glossary/{glossary}', [TranslationController::class, 'glossaryDestroy'])->name('translations.glossary.destroy');

    // Locations & Team (Business Plan Feature)
    Route::middleware([EnsurePlanHasFeature::class.':locations'])->group(function () {
        Route::get('/locations', [VendorAdminController::class, 'locationsIndex'])->name('locations.index');
        Route::post('/locations', [VendorAdminController::class, 'storeLocation'])->name('locations.store');
    });

    Route::middleware([EnsurePlanHasFeature::class.':team'])->group(function () {
        Route::get('/team', [VendorAdminController::class, 'teamIndex'])->name('team.index');
        Route::post('/team', [VendorAdminController::class, 'storeTeamMember'])->name('team.store');
    });

    // Menu Builder
    Route::get('/menu', [MenuBuilderController::class, 'index'])->name('menu.index');
    Route::post('/menu/categories', [MenuBuilderController::class, 'storeCategory'])->name('menu.categories.store');
    Route::post('/menu/categories/reorder', [MenuBuilderController::class, 'reorderCategories'])->name('menu.categories.reorder');
    Route::post('/menu/categories/{category}/duplicate', [MenuBuilderController::class, 'duplicateCategory'])->name('menu.categories.duplicate');
    Route::post('/menu/categories/{category}', [MenuBuilderController::class, 'updateCategory'])->name('menu.categories.update');
    Route::delete('/menu/categories/{category}', [MenuBuilderController::class, 'destroyCategory'])->name('menu.categories.destroy');
    Route::post('/menu/products', [MenuBuilderController::class, 'storeProduct'])->name('menu.products.store');
    Route::post('/menu/products/bulk-update', [MenuBuilderController::class, 'bulkUpdateProducts'])->name('menu.products.bulk_update');
    Route::post('/menu/products/bulk-delete', [MenuBuilderController::class, 'bulkDeleteProducts'])->name('menu.products.bulk_delete');
    Route::post('/menu/products/{product}/inline-update', [MenuBuilderController::class, 'inlineUpdateProduct'])->name('menu.products.inline_update');
    Route::post('/menu/products/{product}', [MenuBuilderController::class, 'updateProduct'])->name('menu.products.update');
    Route::post('/menu/products/{product}/toggle', [MenuBuilderController::class, 'toggleAvailability'])->name('menu.products.toggle');
    Route::post('/menu/products/{product}/daily-offer-toggle', [MenuBuilderController::class, 'toggleDailyOffer'])->name('menu.products.daily_offer.toggle');
    Route::post('/menu/products/{product}/override', [MenuBuilderController::class, 'saveOverride'])->name('menu.products.override');
    Route::delete('/menu/products/{product}', [MenuBuilderController::class, 'destroyProduct'])->name('menu.products.destroy');

    // AI Tools (Business Plan Feature)
    Route::middleware([EnsurePlanHasFeature::class.':ai.menu_builder'])->group(function () {
        Route::get('/ai/import', [AiMenuController::class, 'showImportForm'])->name('ai.import');
        Route::post('/ai/import/process', [AiMenuController::class, 'processImport'])->name('ai.import.process');
        Route::post('/ai/import/confirm', [AiMenuController::class, 'confirmImport'])->name('ai.import.confirm');
        Route::post('/ai/translate', [AiMenuController::class, 'translateMenu'])->name('ai.translate');
        Route::post('/ai/languages', [AiMenuController::class, 'saveLanguage'])->name('ai.languages.save');
        Route::delete('/ai/languages/{code}', [AiMenuController::class, 'deleteLanguage'])->name('ai.languages.destroy');
    });

    // Live Orders & Kitchen Panel (Pro / Business Plan Feature)
    Route::middleware([EnsurePlanHasFeature::class.':orders'])->group(function () {
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/feed', [OrderController::class, 'feed'])->name('orders.feed');
        Route::get('/orders/{order}/receipt-text', [OrderController::class, 'receiptText'])->name('orders.receipt_text');
        Route::post('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
        Route::post('/orders/{order}/print-network', [OrderController::class, 'printNetwork'])->name('orders.print_network');
        Route::post('/orders/{order}/send-email-receipt', [OrderController::class, 'sendEmailReceipt'])->name('orders.send_email_receipt');
        Route::post('/waiter-calls/{waiterCall}/status', [OrderController::class, 'updateWaiterCallStatus'])->name('waiter_calls.status');

        // Interactive Table Floor Plan
        Route::get('/floor-plan', [FloorPlanController::class, 'index'])->name('floor_plan.index');
        Route::post('/floor-plan', [FloorPlanController::class, 'saveFloorPlan'])->name('floor_plan.save');
        Route::get('/floor-plan/live-status', [FloorPlanController::class, 'liveStatus'])->name('floor_plan.live_status');
        Route::post('/floor-plan/tables', [FloorPlanController::class, 'storeTable'])->name('floor_plan.tables.store');
        Route::put('/floor-plan/tables/{table}', [FloorPlanController::class, 'updateTable'])->name('floor_plan.tables.update');
        Route::delete('/floor-plan/tables/{table}', [FloorPlanController::class, 'destroyTable'])->name('floor_plan.tables.destroy');
        Route::post('/floor-plan/combine', [FloorPlanController::class, 'combineTables'])->name('floor_plan.combine');
        Route::post('/floor-plan/tables/{table}/uncombine', [FloorPlanController::class, 'uncombineTable'])->name('floor_plan.uncombine');
        Route::post('/floor-plan/halls', [FloorPlanController::class, 'saveHalls'])->name('floor_plan.halls.save');
    });

    // Table Reservations & Pre-Orders (Pro / Business Plan Feature)
    Route::middleware([EnsurePlanHasFeature::class.':reservations'])->group(function () {
        // Event Planning Inquiries Management
        Route::get('/reservations/events', [AdminEventInquiryController::class, 'index'])->name('event_inquiries.index');
        Route::get('/reservations/events/{eventInquiry}', [AdminEventInquiryController::class, 'show'])->name('event_inquiries.show');
        Route::patch('/reservations/events/{eventInquiry}/status', [AdminEventInquiryController::class, 'updateStatus'])->name('event_inquiries.status');
        Route::post('/reservations/events/{eventInquiry}/status', [AdminEventInquiryController::class, 'updateStatus']);
        Route::post('/reservations/events/{eventInquiry}/notes', [AdminEventInquiryController::class, 'storeNote'])->name('event_inquiries.notes.store');
        Route::patch('/reservations/events/{eventInquiry}/assign', [AdminEventInquiryController::class, 'assign'])->name('event_inquiries.assign');
        Route::patch('/reservations/events/{eventInquiry}/quote', [AdminEventInquiryController::class, 'updateQuote'])->name('event_inquiries.quote');
        Route::delete('/reservations/events/{eventInquiry}', [AdminEventInquiryController::class, 'destroy'])->name('event_inquiries.destroy');

        // Table Reservations
        Route::get('/reservations', [AdminReservationController::class, 'index'])->name('reservations.index');
        Route::get('/reservations/{reservation}', [AdminReservationController::class, 'show'])->name('reservations.show');
        Route::put('/reservations/{reservation}', [AdminReservationController::class, 'update'])->name('reservations.update');
        Route::patch('/reservations/{reservation}', [AdminReservationController::class, 'update']);
        Route::patch('/reservations/{reservation}/status', [AdminReservationController::class, 'updateStatus'])->name('reservations.status');
        Route::post('/reservations/{reservation}/status', [AdminReservationController::class, 'updateStatus']);

        // Restaurant Tables Management
        Route::get('/tables', [AdminTableController::class, 'index'])->name('tables.index');
        Route::post('/tables', [AdminTableController::class, 'store'])->name('tables.store');
        Route::put('/tables/{table}', [AdminTableController::class, 'update'])->name('tables.update');
        Route::post('/tables/{table}/toggle', [AdminTableController::class, 'toggleActive'])->name('tables.toggle');
        Route::delete('/tables/{table}', [AdminTableController::class, 'destroy'])->name('tables.destroy');
        Route::post('/tables/sync-floor-plan', [AdminTableController::class, 'syncFloorPlan'])->name('tables.sync_floor_plan');

        // Reservation Settings
        Route::get('/settings/reservations', [AdminReservationController::class, 'settingsIndex'])->name('settings.reservations');
        Route::post('/settings/reservations', [AdminReservationController::class, 'updateSettings'])->name('settings.reservations.update');

        // Event Planning Settings
        Route::get('/settings/reservations/events', [AdminEventInquiryController::class, 'settingsIndex'])->name('settings.reservations.events');
        Route::post('/settings/reservations/events', [AdminEventInquiryController::class, 'updateSettings'])->name('settings.reservations.events.update');
    });

    // Branding & Theme Customizer
    Route::get('/branding', [BrandingController::class, 'index'])->name('branding.index');
    Route::post('/branding', [BrandingController::class, 'update'])->name('branding.update');

    // Restaurant Settings (Service Fee & Delivery)
    Route::get('/settings', [VendorSettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [VendorSettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/telegram/test', [VendorSettingsController::class, 'testTelegramConnection'])->name('settings.telegram.test');
    Route::post('/settings/printer/test', [VendorSettingsController::class, 'testPrinterConnection'])->name('settings.printer.test');
    Route::get('/settings/currency/live-rate', [VendorSettingsController::class, 'getLiveRate'])->name('settings.currency.live_rate');
    Route::post('/settings/daily-offers', [VendorSettingsController::class, 'updateDailyOffers'])->name('settings.daily_offers.update');

    Route::middleware([EnsurePlanHasFeature::class.':branding.custom_domain'])->group(function () {
        Route::post('/settings/domain/check', [VendorSettingsController::class, 'checkDomainDns'])->name('settings.domain.check');
        Route::post('/settings/domain/verify', [VendorSettingsController::class, 'verifyDomainOwnership'])->name('settings.domain.verify');
    });

    Route::middleware([EnsurePlanHasFeature::class.':ai.waiter'])->group(function () {
        Route::get('/settings/ai', [VendorSettingsController::class, 'aiIndex'])->name('settings.ai');
        Route::post('/settings/ai', [VendorSettingsController::class, 'aiUpdate'])->name('settings.ai.update');
        Route::post('/settings/ai/test', [VendorSettingsController::class, 'testAiConnection'])->name('settings.ai.test');
    });

    // Settings → Reservations & Pre-Orders → Notifications
    Route::get('/settings/reservations/notifications', [ReservationNotificationController::class, 'index'])->name('settings.reservations.notifications');
    Route::post('/settings/reservations/notifications', [ReservationNotificationController::class, 'update'])->name('settings.reservations.notifications.update');
    Route::post('/settings/reservations/notifications/test', [ReservationNotificationController::class, 'sendTestEmail'])->name('settings.reservations.notifications.test');
    Route::get('/settings/reservations/notifications/preview', [ReservationNotificationController::class, 'previewTemplate'])->name('settings.reservations.notifications.preview');

    // QR Code Studio
    Route::get('/qr', [QrStudioController::class, 'index'])->name('qr.index');

    // Customers CRM (Pro / Business Plan Feature)
    Route::middleware([EnsurePlanHasFeature::class.':customers'])->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/export', [CustomerController::class, 'export'])->name('customers.export');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::post('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::post('/customers/{customer}/birthday-sms', [CustomerController::class, 'sendBirthdayGreeting'])->name('customers.birthday_sms');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    });

    // Analytics
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    // Tips & Waiter Ratings (Pro / Business Plan Feature)
    Route::middleware([EnsurePlanHasFeature::class.':tips.ratings'])->group(function () {
        Route::get('/tips-ratings', [AdminTipRatingController::class, 'reportsIndex'])->name('tips_ratings.reports');
        Route::get('/tips-ratings/export-tips', [AdminTipRatingController::class, 'exportTips'])->name('tips_ratings.export_tips');
        Route::get('/tips-ratings/export-ratings', [AdminTipRatingController::class, 'exportRatings'])->name('tips_ratings.export_ratings');
        Route::post('/tips-ratings/ratings/{rating}/moderate', [AdminTipRatingController::class, 'moderateRating'])->name('tips_ratings.moderate');
        Route::get('/settings/tips-ratings', [AdminTipRatingController::class, 'settingsIndex'])->name('settings.tips_ratings');
        Route::post('/settings/tips-ratings', [AdminTipRatingController::class, 'updateSettings'])->name('settings.tips_ratings.update');

        // Waiter Management
        Route::get('/waiters', [AdminWaiterController::class, 'index'])->name('waiters.index');
        Route::post('/waiters', [AdminWaiterController::class, 'store'])->name('waiters.store');
        Route::post('/waiters/reorder', [AdminWaiterController::class, 'reorder'])->name('waiters.reorder');
        Route::match(['post', 'put'], '/waiters/settings', [AdminWaiterController::class, 'updateCustomerSelectionSettings'])->name('waiters.settings.update');
        Route::match(['post', 'put'], '/waiters/{waiter}', [AdminWaiterController::class, 'update'])->name('waiters.update');
        Route::match(['post', 'patch'], '/waiters/{waiter}/toggle-status', [AdminWaiterController::class, 'toggleStatus'])->name('waiters.toggle_status');
        Route::delete('/waiters/{waiter}/photo', [AdminWaiterController::class, 'deletePhoto'])->name('waiters.delete_photo');
        Route::delete('/waiters/{waiter}', [AdminWaiterController::class, 'destroy'])->name('waiters.destroy');
    });

    // Split Bill (Pro / Business Plan Feature)
    Route::middleware([EnsurePlanHasFeature::class.':bills.split'])->group(function () {
        Route::get('/settings/split-bill', [AdminSplitBillController::class, 'settingsIndex'])->name('settings.split_bill');
        Route::post('/settings/split-bill', [AdminSplitBillController::class, 'updateSettings'])->name('settings.split_bill.update');
        Route::get('/orders/{order}/split-bill', [AdminSplitBillController::class, 'showOrderSplit'])->name('orders.split_bill');
        Route::post('/split-bill-payments/{payment}/approve', [AdminSplitBillController::class, 'approvePayment'])->name('split_bill.approve_payment');
    });

    // User Profile & 2FA Security
    Route::get('/profile', [ProfileSecurityController::class, 'index'])->name('profile');
    Route::post('/profile/update', [ProfileSecurityController::class, 'updateProfile'])->name('profile.update');
});

// 5. Point of Sale (POS) Module Routes
Route::prefix('pos/api/auth')->name('pos.api.auth.')->group(function () {
    Route::post('/login', [PosAuthController::class, 'login'])->middleware('throttle:10,1')->name('login');
    Route::post('/pin-login', [PosAuthController::class, 'pinLogin'])->middleware('throttle:15,1')->name('pin_login');
});

Route::middleware(['auth', EnsurePosAccess::class])->group(function () {
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/admin/pos', [PosController::class, 'index'])->name('admin.pos.index');

    Route::prefix('pos/api')->name('pos.api.')->group(function () {
        // POS Authentication & Employee Switcher
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::get('/employees', [PosAuthController::class, 'getEmployees'])->name('employees');
            Route::post('/quick-switch', [PosAuthController::class, 'quickSwitch'])->name('quick_switch');
            Route::post('/pin', [PosAuthController::class, 'setPin'])->name('set_pin');
            Route::post('/lock', [PosAuthController::class, 'lock'])->name('lock');
            Route::post('/unlock', [PosAuthController::class, 'unlock'])->name('unlock');
            Route::post('/logout', [PosAuthController::class, 'logout'])->name('logout');
        });

        Route::get('/bootstrap', [PosController::class, 'bootstrap'])->name('bootstrap');
        Route::get('/menu', [PosController::class, 'getMenu'])->name('menu');
        Route::get('/orders', [PosController::class, 'getOrders'])->name('orders.index');
        Route::post('/orders', [PosController::class, 'storeOrder'])->name('orders.store');
        Route::get('/orders/{order}', [PosController::class, 'showOrder'])->name('orders.show');
        Route::post('/orders/{order}/status', [PosController::class, 'updateStatus'])->name('orders.status');
        Route::post('/orders/{order}/hold', [PosController::class, 'holdOrder'])->name('orders.hold');
        Route::post('/orders/{order}/resume', [PosController::class, 'resumeOrder'])->name('orders.resume');
        Route::post('/orders/{order}/transfer-table', [PosController::class, 'transferTable'])->name('orders.transfer_table');
        Route::post('/orders/merge', [PosController::class, 'mergeOrders'])->name('orders.merge');
        Route::post('/orders/{order}/split', [PosController::class, 'splitOrder'])->name('orders.split');
        Route::post('/orders/{order}/assign-waiter', [PosController::class, 'assignWaiter'])->name('orders.assign_waiter');

        // Tables & Floor Plan
        Route::get('/tables', [PosController::class, 'getTables'])->name('tables.index');
        Route::post('/tables/{table}/status', [PosController::class, 'updateTableStatus'])->name('tables.status');

        Route::get('/settings', [PosSettingController::class, 'show'])->name('settings.show');
        Route::post('/settings', [PosSettingController::class, 'update'])->name('settings.update');

        Route::get('/devices', [PosDeviceController::class, 'index'])->name('devices.index');
        Route::post('/devices/register', [PosDeviceController::class, 'register'])->name('devices.register');
        Route::post('/devices/pair', [PosDeviceController::class, 'pair'])->name('devices.pair');
        Route::post('/devices/{device}/regenerate-code', [PosDeviceController::class, 'regenerateCode'])->name('devices.regenerate_code');
        Route::post('/devices/heartbeat', [PosDeviceController::class, 'heartbeat'])->name('devices.heartbeat');
        Route::post('/devices/{device}/toggle', [PosDeviceController::class, 'toggle'])->name('devices.toggle');
        Route::delete('/devices/{device}', [PosDeviceController::class, 'destroy'])->name('devices.destroy');
        Route::post('/devices/{device}/session', [PosDeviceController::class, 'createSession'])->name('devices.session.create');
        Route::post('/sessions/{session}/lock', [PosDeviceController::class, 'lockSession'])->name('sessions.lock');
        Route::post('/sessions/{session}/unlock', [PosDeviceController::class, 'unlockSession'])->name('sessions.unlock');
        Route::post('/sessions/{session}/terminate', [PosDeviceController::class, 'terminateSession'])->name('sessions.terminate');
    });
});

// 6. Custom Domain Branch/Location Route (e.g. https://example.com/cascades)
Route::get('/{location_slug}', function (Request $request, string $location_slug, TenantContext $tenantContext) {
    $customDomainVendor = $tenantContext->getTenant();
    if ($customDomainVendor) {
        $location = $customDomainVendor->locations()->where('slug', $location_slug)->first();
        if ($location) {
            return app(ClientStorefrontController::class)->showMenu($request, $customDomainVendor->slug, $location_slug);
        }
    }

    abort(404);
})->where('location_slug', '^[a-zA-Z0-9_-]+$');
