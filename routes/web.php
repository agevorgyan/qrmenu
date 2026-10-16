<?php

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
use App\Http\Controllers\ProfileSecurityController;
use App\Http\Controllers\QrStudioController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SuperAdminEmailController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\VendorAdminController;
use App\Http\Controllers\VendorSettingsController;
use App\Http\Middleware\EnsurePlanHasFeature;
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
Route::get('/m/{vendor_slug}/{location_slug?}', [ClientStorefrontController::class, 'showMenu'])
    ->middleware('throttle:120,1')
    ->name('client.menu');

// Rate-limited public order & waiter endpoints (anti-spam & DDoS protection)
Route::middleware('throttle:15,1')->group(function () {
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
    Route::get('/questions', [AiWaiterController::class, 'questions'])->name('client.ai_waiter.questions');
    Route::post('/session', [AiWaiterController::class, 'startSession'])->name('client.ai_waiter.session.start');
    Route::post('/session/{token}/language', [AiWaiterController::class, 'setLanguage'])->name('client.ai_waiter.session.language');
    Route::post('/session/{token}/answer', [AiWaiterController::class, 'submitAnswer'])->name('client.ai_waiter.session.answer');
    Route::post('/session/{token}/next-question', [AiWaiterController::class, 'nextQuestion'])->name('client.ai_waiter.session.next_question');
    Route::post('/session/{token}/recommendations', [AiWaiterController::class, 'recommendations'])->name('client.ai_waiter.session.recommendations');
    Route::post('/session/{token}/chat', [AiWaiterController::class, 'chat'])->name('client.ai_waiter.session.chat');
    Route::post('/session/{token}/add-to-cart', [AiWaiterController::class, 'addToCart'])->name('client.ai_waiter.session.add_to_cart');
    Route::post('/session/{token}/complete', [AiWaiterController::class, 'complete'])->name('client.ai_waiter.session.complete');

    // Legacy endpoints
    Route::post('/recommend', [AiWaiterController::class, 'recommend'])->name('client.ai_waiter.recommend');
    Route::get('/pairings/{product_id}', [AiWaiterController::class, 'pairings'])->name('client.ai_waiter.pairings');
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
            }
        }

        cookie()->queue('app_locale', $targetLocale, 525600);
        cookie()->queue('admin_locale', $targetLocale, 525600);
        cookie()->queue('locale', $targetLocale, 525600);

        // Sanitize previous URL so existing ?lang= or ?locale= params don't override the new selection
        $previousUrl = url()->previous();
        if ($previousUrl) {
            $parsed = parse_url($previousUrl);
            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $query);
                if (isset($query['lang']) || isset($query['locale'])) {
                    unset($query['lang'], $query['locale']);
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
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.post');
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
    Route::post('/subscriptions/{vendor}', [SuperAdminController::class, 'updateVendorSubscription'])->name('subscriptions.update');
    Route::post('/subscriptions/{vendor}/payments', [SuperAdminController::class, 'storeVendorPayment'])->name('subscriptions.payments.store');

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
    Route::post('/subscription/renew', [VendorAdminController::class, 'renewSubscription'])->name('subscription.renew');

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
    Route::post('/menu/categories/{category}', [MenuBuilderController::class, 'updateCategory'])->name('menu.categories.update');
    Route::delete('/menu/categories/{category}', [MenuBuilderController::class, 'destroyCategory'])->name('menu.categories.destroy');
    Route::post('/menu/products', [MenuBuilderController::class, 'storeProduct'])->name('menu.products.store');
    Route::post('/menu/products/{product}', [MenuBuilderController::class, 'updateProduct'])->name('menu.products.update');
    Route::post('/menu/products/{product}/toggle', [MenuBuilderController::class, 'toggleAvailability'])->name('menu.products.toggle');
    Route::post('/menu/products/{product}/override', [MenuBuilderController::class, 'saveOverride'])->name('menu.products.override');
    Route::delete('/menu/products/{product}', [MenuBuilderController::class, 'destroyProduct'])->name('menu.products.destroy');

    // AI Tools
    Route::get('/ai/import', [AiMenuController::class, 'showImportForm'])->name('ai.import');
    Route::post('/ai/import/process', [AiMenuController::class, 'processImport'])->name('ai.import.process');
    Route::post('/ai/import/confirm', [AiMenuController::class, 'confirmImport'])->name('ai.import.confirm');
    Route::post('/ai/translate', [AiMenuController::class, 'translateMenu'])->name('ai.translate');
    Route::post('/ai/languages', [AiMenuController::class, 'saveLanguage'])->name('ai.languages.save');
    Route::delete('/ai/languages/{code}', [AiMenuController::class, 'deleteLanguage'])->name('ai.languages.destroy');

    // Live Orders & Kitchen Panel (Pro / Business Plan Feature)
    Route::middleware([EnsurePlanHasFeature::class.':orders'])->group(function () {
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/feed', [OrderController::class, 'feed'])->name('orders.feed');
        Route::get('/orders/{order}/receipt-text', [OrderController::class, 'receiptText'])->name('orders.receipt_text');
        Route::post('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
        Route::post('/waiter-calls/{waiterCall}/status', [OrderController::class, 'updateWaiterCallStatus'])->name('waiter_calls.status');

        // Interactive Table Floor Plan
        Route::get('/floor-plan', [FloorPlanController::class, 'index'])->name('floor_plan.index');
        Route::post('/floor-plan', [FloorPlanController::class, 'saveFloorPlan'])->name('floor_plan.save');
    });

    // Branding & Theme Customizer
    Route::get('/branding', [BrandingController::class, 'index'])->name('branding.index');
    Route::post('/branding', [BrandingController::class, 'update'])->name('branding.update');

    // Restaurant Settings (Service Fee & Delivery)
    Route::get('/settings', [VendorSettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [VendorSettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/telegram/test', [VendorSettingsController::class, 'testTelegramConnection'])->name('settings.telegram.test');
    Route::post('/settings/domain/check', [VendorSettingsController::class, 'checkDomainDns'])->name('settings.domain.check');
    Route::post('/settings/domain/verify', [VendorSettingsController::class, 'verifyDomainOwnership'])->name('settings.domain.verify');
    Route::get('/settings/ai', [VendorSettingsController::class, 'aiIndex'])->name('settings.ai');
    Route::post('/settings/ai', [VendorSettingsController::class, 'aiUpdate'])->name('settings.ai.update');
    Route::post('/settings/ai/test', [VendorSettingsController::class, 'testAiConnection'])->name('settings.ai.test');

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

    // User Profile & 2FA Security
    Route::get('/profile', [ProfileSecurityController::class, 'index'])->name('profile');
    Route::post('/profile/update', [ProfileSecurityController::class, 'updateProfile'])->name('profile.update');
});

// 5. Custom Domain Branch/Location Route (e.g. https://example.com/cascades)
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
