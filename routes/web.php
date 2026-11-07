<?php

use App\Domains\Loyalty\Http\Controllers\Admin\LoyaltyCampaignsController;
use App\Domains\Loyalty\Http\Controllers\Admin\LoyaltyDashboardController;
use App\Domains\Loyalty\Http\Controllers\Admin\LoyaltyProgramController;
use App\Domains\Loyalty\Http\Controllers\Admin\LoyaltyReferralsController;
use App\Domains\Loyalty\Http\Controllers\Admin\LoyaltyRewardsController;
use App\Domains\Loyalty\Http\Controllers\Admin\LoyaltyRulesController;
use App\Domains\Loyalty\Http\Controllers\Admin\LoyaltyStampCardsController;
use App\Domains\Loyalty\Http\Controllers\Admin\LoyaltyTiersController;
use App\Domains\Loyalty\Http\Controllers\Admin\WalletDesignerController;
use App\Domains\Loyalty\Http\Controllers\Admin\WalletSettingsController;
use App\Domains\Loyalty\Http\Controllers\Customer\CustomerLoyaltyAuthController;
use App\Domains\Loyalty\Http\Controllers\Customer\CustomerLoyaltyPortalController;
use App\Domains\Loyalty\Http\Controllers\Customer\CustomerWalletController;
use App\Domains\Loyalty\Http\Controllers\Staff\StaffLoyaltyOperationsController;
use App\Domains\Loyalty\Http\Controllers\Webhooks\ApplePassKitWebserviceController;
use App\Http\Controllers\Admin\AccessControlController;
use App\Http\Controllers\Admin\Finance\BankAccountController;
use App\Http\Controllers\Admin\Finance\CashRegisterController;
use App\Http\Controllers\Admin\Finance\CashShiftController;
use App\Http\Controllers\Admin\Finance\ExpenseController;
use App\Http\Controllers\Admin\Finance\FinancialApprovalController;
use App\Http\Controllers\Admin\Finance\FinancialAuditController;
use App\Http\Controllers\Admin\Finance\FinancialDashboardController;
use App\Http\Controllers\Admin\Finance\FinancialReportController;
use App\Http\Controllers\Admin\Finance\FinancialTransferController;
use App\Http\Controllers\Admin\Finance\GeneralLedgerController;
use App\Http\Controllers\Admin\Finance\IncomeController;
use App\Http\Controllers\Admin\HaccpController;
use App\Http\Controllers\Admin\Hr\HrAttendanceController;
use App\Http\Controllers\Admin\Hr\HrDepartmentPositionController;
use App\Http\Controllers\Admin\Hr\HrEmployeeProfileController;
use App\Http\Controllers\Admin\Hr\HrLeaveController;
use App\Http\Controllers\Admin\Hr\HrPaymentAdvanceController;
use App\Http\Controllers\Admin\Hr\HrPayrollController;
use App\Http\Controllers\Admin\Hr\HrReportController;
use App\Http\Controllers\Admin\Hr\HrScheduleController;
use App\Http\Controllers\Admin\Hr\HrSelfServiceController;
use App\Http\Controllers\Admin\Hr\HrTimesheetController;
use App\Http\Controllers\Admin\IngredientCategoryController;
use App\Http\Controllers\Admin\IngredientController;
use App\Http\Controllers\Admin\InventoryReportController;
use App\Http\Controllers\Admin\InventoryStockController;
use App\Http\Controllers\Admin\ProductionOrderController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\RecipeController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\SupplierInvoiceController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\Admin\WasteController;
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
use App\Http\Controllers\PosIntegrationsController;
use App\Http\Controllers\PosKdsController;
use App\Http\Controllers\PosLanController;
use App\Http\Controllers\PosOfflineSyncController;
use App\Http\Controllers\PosPaymentShiftController;
use App\Http\Controllers\PosReportingController;
use App\Http\Controllers\PosSettingController;
use App\Http\Controllers\PosWorkspaceController;
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

// Customer Loyalty Storefront & Digital Wallet Routes
Route::get('/m/{vendor_slug}/loyalty/login', [CustomerLoyaltyAuthController::class, 'showLogin'])->name('client.loyalty.login');
Route::post('/api/m/{vendor_slug}/loyalty/otp/send', [CustomerLoyaltyAuthController::class, 'sendOtp'])->name('client.loyalty.otp.send');
Route::post('/api/m/{vendor_slug}/loyalty/otp/verify', [CustomerLoyaltyAuthController::class, 'verifyOtp'])->name('client.loyalty.otp.verify');
Route::get('/m/{vendor_slug}/loyalty/logout', [CustomerLoyaltyAuthController::class, 'logout'])->name('client.loyalty.logout');
Route::get('/m/{vendor_slug}/loyalty', [CustomerLoyaltyPortalController::class, 'index'])->name('client.loyalty.portal');
Route::post('/api/m/{vendor_slug}/loyalty/rewards/{reward}/claim', [CustomerLoyaltyPortalController::class, 'claimReward'])->name('client.loyalty.rewards.claim');
Route::post('/api/m/{vendor_slug}/loyalty/referral/apply', [CustomerLoyaltyPortalController::class, 'applyReferral'])->name('client.loyalty.referral.apply');
Route::get('/m/{vendor_slug}/loyalty/wallet/apple', [CustomerWalletController::class, 'downloadApplePass'])->name('client.loyalty.wallet.apple');
Route::get('/m/{vendor_slug}/loyalty/wallet/google', [CustomerWalletController::class, 'addToGoogleWallet'])->name('client.loyalty.wallet.google');

// Apple PassKit Web Service Endpoints (RFC 6750 / PassKit specification)
Route::prefix('api/v1')->group(function () {
    Route::post('/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}', [ApplePassKitWebserviceController::class, 'registerDevice']);
    Route::delete('/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}', [ApplePassKitWebserviceController::class, 'unregisterDevice']);
    Route::get('/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}', [ApplePassKitWebserviceController::class, 'getSerialNumbersForDevice']);
    Route::get('/passes/{passTypeIdentifier}/{serialNumber}', [ApplePassKitWebserviceController::class, 'deliverPass']);
    Route::post('/log', [ApplePassKitWebserviceController::class, 'log']);
});

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
    Route::post('/security/pin', [ProfileSecurityController::class, 'updatePin'])->name('security.pin.update');
    Route::post('/security/pin/send-code', [ProfileSecurityController::class, 'sendPinVerificationCode'])->name('security.pin.send_code');
    Route::post('/security/logout-other-devices', [ProfileSecurityController::class, 'logoutOtherDevices'])->name('security.logout_other_devices');
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
    Route::post('/plans/{plan}/builder', [SuperAdminController::class, 'savePlanBuilder'])->name('plans.builder.save');
    Route::post('/plans/{plan}/duplicate', [SuperAdminController::class, 'duplicatePlan'])->name('plans.duplicate');
    Route::post('/plans/{plan}/archive', [SuperAdminController::class, 'archivePlan'])->name('plans.archive');
    Route::post('/plans/{plan}/versions/{version}/migrate', [SuperAdminController::class, 'migratePlanVersion'])->name('plans.versions.migrate');
    Route::post('/plans/{plan}/toggle', [SuperAdminController::class, 'togglePlan'])->name('plans.toggle');
    Route::delete('/plans/{plan}', [SuperAdminController::class, 'destroyPlan'])->name('plans.destroy');

    // Feature Catalog, Categories, Dependencies & Add-ons
    Route::post('/features/categories', [SuperAdminController::class, 'storeFeatureCategory'])->name('features.categories.store');
    Route::put('/features/categories/{category}', [SuperAdminController::class, 'updateFeatureCategory'])->name('features.categories.update');
    Route::post('/features', [SuperAdminController::class, 'storeFeature'])->name('features.store');
    Route::put('/features/{feature}', [SuperAdminController::class, 'updateFeature'])->name('features.update');
    Route::post('/features/{feature}/archive', [SuperAdminController::class, 'archiveFeature'])->name('features.archive');
    Route::delete('/features/{feature}', [SuperAdminController::class, 'destroyFeature'])->name('features.destroy');
    Route::post('/features/dependencies', [SuperAdminController::class, 'storeFeatureDependency'])->name('features.dependencies.store');
    Route::delete('/features/dependencies/{dependency}', [SuperAdminController::class, 'destroyFeatureDependency'])->name('features.dependencies.destroy');
    Route::post('/addons', [SuperAdminController::class, 'storeAddon'])->name('addons.store');
    Route::put('/addons/{addon}', [SuperAdminController::class, 'updateAddon'])->name('addons.update');

    // Vendor Subscriptions & Payment Logs
    Route::get('/subscriptions', [SuperAdminController::class, 'subscriptionsIndex'])->name('subscriptions.index');
    Route::get('/subscriptions/payments', [SuperAdminController::class, 'allPaymentsIndex'])->name('subscriptions.payments.index');
    Route::get('/subscriptions/audit-logs', [SuperAdminController::class, 'subscriptionAuditLogs'])->name('subscriptions.audit_logs');
    Route::post('/subscriptions/settings', [SuperAdminController::class, 'updateSubscriptionSettings'])->name('subscriptions.settings.update');
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
    Route::post('/subscriptions/{vendor}/addons', [SuperAdminController::class, 'assignVendorAddon'])->name('subscriptions.addons.assign');
    Route::delete('/subscriptions/{vendor}/addons/{vendorAddon}', [SuperAdminController::class, 'cancelVendorAddon'])->name('subscriptions.addons.cancel');
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

// 4. Vendor Admin Panel (/admin) - Strictly restricted to Vendor Owners, Managers & authorized operational staff
Route::middleware(['auth', 'verified', 'role:vendor_owner,manager,chef,staff', EnsureSubscriptionIsActive::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [VendorAdminController::class, 'dashboard'])->name('dashboard');

    // Vendor Subscription Status & Payment History (Vendor Owner only)
    Route::middleware(['role:vendor_owner'])->group(function () {
        Route::get('/subscription', [VendorAdminController::class, 'subscriptionIndex'])->name('subscription');
        Route::get('/subscription/overview', [VendorAdminController::class, 'subscriptionIndex'])->name('subscription.index');
        Route::post('/subscription/renew', [VendorAdminController::class, 'renewSubscription'])->name('subscription.renew');
        Route::post('/subscription/cancel', [VendorAdminController::class, 'cancelSubscription'])->name('subscription.cancel');
        Route::post('/subscription/resume', [VendorAdminController::class, 'resumeSubscription'])->name('subscription.resume');
        Route::get('/subscription/invoices/{invoice}', [VendorAdminController::class, 'showInvoice'])->name('subscription.invoices.show');
    });

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
        Route::get('/team', [AccessControlController::class, 'index'])->name('team.index');
        Route::post('/team', [VendorAdminController::class, 'storeTeamMember'])->name('team.store');

        Route::prefix('access-control')->name('access_control.')->group(function () {
            Route::get('/', [AccessControlController::class, 'index'])->name('index');
            Route::get('/users/{user}/effective-permissions', [AccessControlController::class, 'userEffectivePermissions'])->name('users.effective_permissions');
            Route::post('/users/{user}/roles', [AccessControlController::class, 'updateUserRoles'])->name('users.roles.update');
            Route::post('/users/{user}/locations', [AccessControlController::class, 'updateUserLocations'])->name('users.locations.update');
            Route::post('/users/{user}/status', [AccessControlController::class, 'updateUserStatus'])->name('users.status.update');
            Route::post('/users/{user}/overrides', [AccessControlController::class, 'storeUserOverride'])->name('users.overrides.store');
            Route::delete('/users/{user}/overrides/{permissionKey}', [AccessControlController::class, 'destroyUserOverride'])->name('users.overrides.destroy');
            Route::post('/users/{user}/revoke-sessions', [AccessControlController::class, 'revokeUserSessions'])->name('users.revoke_sessions');

            Route::post('/roles', [AccessControlController::class, 'storeRole'])->name('roles.store');
            Route::put('/roles/{role}', [AccessControlController::class, 'updateRole'])->name('roles.update');
            Route::post('/roles/{role}/duplicate', [AccessControlController::class, 'duplicateRole'])->name('roles.duplicate');
            Route::delete('/roles/{role}', [AccessControlController::class, 'destroyRole'])->name('roles.destroy');

            Route::get('/audit-logs/export', [AccessControlController::class, 'exportAuditLogs'])->name('audit_logs.export');
        });

        // HR & Workforce Management Sub-Module (Within Team & Employees)
        Route::prefix('hr')->name('hr.')->group(function () {
            // 1. Departments & Positions
            Route::get('/departments', [HrDepartmentPositionController::class, 'departmentsIndex'])->name('departments.index');
            Route::post('/departments', [HrDepartmentPositionController::class, 'storeDepartment'])->name('departments.store');
            Route::put('/departments/{department}', [HrDepartmentPositionController::class, 'updateDepartment'])->name('departments.update');
            Route::delete('/departments/{department}', [HrDepartmentPositionController::class, 'destroyDepartment'])->name('departments.destroy');

            Route::get('/positions', [HrDepartmentPositionController::class, 'positionsIndex'])->name('positions.index');
            Route::post('/positions', [HrDepartmentPositionController::class, 'storePosition'])->name('positions.store');
            Route::put('/positions/{position}', [HrDepartmentPositionController::class, 'updatePosition'])->name('positions.update');
            Route::delete('/positions/{position}', [HrDepartmentPositionController::class, 'destroyPosition'])->name('positions.destroy');

            // 2. Employees & Detailed Profiles
            Route::get('/employees', [HrEmployeeProfileController::class, 'index'])->name('employees.index');
            Route::get('/employees/{employee}', [HrEmployeeProfileController::class, 'show'])->name('employees.show');
            Route::put('/employees/{employee}', [HrEmployeeProfileController::class, 'update'])->name('employees.update');
            Route::post('/employees/{employee}/transfer', [HrEmployeeProfileController::class, 'transfer'])->name('employees.transfer');
            Route::post('/employees/{employee}/salary', [HrEmployeeProfileController::class, 'reviseSalary'])->name('employees.salary');
            Route::post('/employees/{employee}/contracts', [HrEmployeeProfileController::class, 'storeContract'])->name('employees.contracts.store');
            Route::post('/employees/{employee}/contract', [HrEmployeeProfileController::class, 'storeContract'])->name('employees.contract');
            Route::post('/employees/{employee}/documents', [HrEmployeeProfileController::class, 'uploadDocument'])->name('employees.documents.store');
            Route::post('/employees/{employee}/document/upload', [HrEmployeeProfileController::class, 'uploadDocument'])->name('employees.document.upload');
            Route::get('/employees/{employee}/documents/{document}', [HrEmployeeProfileController::class, 'downloadDocument'])->name('employees.documents.download');
            Route::get('/employees/{employee}/document/{document}/download', [HrEmployeeProfileController::class, 'downloadDocument'])->name('employees.document.download');
            Route::delete('/employees/{employee}/documents/{document}', [HrEmployeeProfileController::class, 'destroyDocument'])->name('employees.documents.destroy');
            Route::delete('/employees/{employee}/document/{document}', [HrEmployeeProfileController::class, 'destroyDocument'])->name('employees.document.destroy');

            // 3. Work Schedules & Shifts
            Route::get('/schedules', [HrScheduleController::class, 'index'])->name('schedules.index');
            Route::post('/shifts', [HrScheduleController::class, 'storeShift'])->name('shifts.store');
            Route::post('/schedules/store', [HrScheduleController::class, 'storeShift'])->name('schedules.store');
            Route::put('/shifts/{shift}', [HrScheduleController::class, 'updateShift'])->name('shifts.update');
            Route::delete('/shifts/{shift}', [HrScheduleController::class, 'destroyShift'])->name('shifts.destroy');
            Route::delete('/schedules/{shift}', [HrScheduleController::class, 'destroyShift'])->name('schedules.destroy');
            Route::post('/shifts/copy-week', [HrScheduleController::class, 'copyWeek'])->name('shifts.copy_week');
            Route::post('/schedules/copy-week', [HrScheduleController::class, 'copyWeek'])->name('schedules.copy_week');

            // 4. Attendance & Time Clock
            Route::get('/attendance', [HrAttendanceController::class, 'index'])->name('attendance.index');
            Route::post('/attendance/punch', [HrAttendanceController::class, 'punch'])->name('attendance.punch');
            Route::post('/attendance/manual', [HrAttendanceController::class, 'manualEntry'])->name('attendance.manual');
            Route::post('/attendance/{record}/adjust', [HrAttendanceController::class, 'adjust'])->name('attendance.adjust');
            Route::get('/attendance/qr-token', [HrAttendanceController::class, 'getQrToken'])->name('attendance.qr_token');
            Route::get('/attendance/qr', [HrAttendanceController::class, 'getQrToken'])->name('attendance.qr');

            // 5. Timesheets (Tabel)
            Route::get('/timesheets', [HrTimesheetController::class, 'index'])->name('timesheets.index');
            Route::post('/timesheets/generate', [HrTimesheetController::class, 'generate'])->name('timesheets.generate');
            Route::post('/timesheets/{timesheet}/approve', [HrTimesheetController::class, 'approve'])->name('timesheets.approve');
            Route::put('/timesheets/{timesheet}', [HrTimesheetController::class, 'update'])->name('timesheets.update');

            // 6. Leaves & Absences
            Route::get('/leaves', [HrLeaveController::class, 'index'])->name('leaves.index');
            Route::post('/leaves', [HrLeaveController::class, 'storeRequest'])->name('leaves.store');
            Route::post('/leaves/{leaveRequest}/review', [HrLeaveController::class, 'review'])->name('leaves.review');
            Route::get('/leaves/balances', [HrLeaveController::class, 'balancesIndex'])->name('leaves.balances');

            // 7. Payroll & Payslips
            Route::get('/payroll', [HrPayrollController::class, 'index'])->name('payroll.index');
            Route::post('/payroll/create-period', [HrPayrollController::class, 'createPeriod'])->name('payroll.create_period');
            Route::post('/payroll/period/create', [HrPayrollController::class, 'createPeriod'])->name('payroll.period.create');
            Route::post('/payroll/{period}/calculate', [HrPayrollController::class, 'calculate'])->name('payroll.calculate');
            Route::get('/payroll/{period}/preview', [HrPayrollController::class, 'preview'])->name('payroll.preview');
            Route::post('/payroll/{period}/approve', [HrPayrollController::class, 'approve'])->name('payroll.approve');
            Route::post('/payroll/{period}/lock', [HrPayrollController::class, 'lock'])->name('payroll.lock');
            Route::get('/payroll/items/{item}/payslip', [HrPayrollController::class, 'payslip'])->name('payroll.payslip');

            // 8. Payments & Advances
            Route::get('/payments', [HrPaymentAdvanceController::class, 'paymentsIndex'])->name('payments.index');
            Route::post('/payments', [HrPaymentAdvanceController::class, 'storePayment'])->name('payments.store');
            Route::get('/advances', [HrPaymentAdvanceController::class, 'advancesIndex'])->name('advances.index');
            Route::post('/advances', [HrPaymentAdvanceController::class, 'storeAdvance'])->name('advances.store');
            Route::post('/advances/{advance}/review', [HrPaymentAdvanceController::class, 'reviewAdvance'])->name('advances.review');

            // 9. Employee Self-Service (Staff Portal)
            Route::get('/self-service', [HrSelfServiceController::class, 'index'])->name('self_service.index');
            Route::post('/self-service/leaves', [HrSelfServiceController::class, 'requestLeave'])->name('self_service.leaves.request');
            Route::post('/self-service/advances', [HrSelfServiceController::class, 'requestAdvance'])->name('self_service.advances.request');

            // 10. Reports & Exports
            Route::get('/reports/dashboard', [HrReportController::class, 'dashboard'])->name('reports.dashboard');
            Route::get('/reports/export/{type}', [HrReportController::class, 'export'])->name('reports.export');
        });
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

    // ==========================================
    // Inventory, Procurement & Production (Phase 1 Foundation)
    // ==========================================
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/dashboard', [InventoryStockController::class, 'dashboard'])->name('dashboard');

        // Ingredients Catalog
        Route::get('/ingredients/template', [IngredientController::class, 'template'])->name('ingredients.template');
        Route::post('/ingredients/import', [IngredientController::class, 'import'])->name('ingredients.import');
        Route::get('/ingredients/export', [IngredientController::class, 'export'])->name('ingredients.export');
        Route::resource('ingredients', IngredientController::class);

        // Ingredient Categories
        Route::resource('categories', IngredientCategoryController::class)->except(['create', 'show', 'edit']);

        // Units & Conversions
        Route::get('/units', [UnitController::class, 'index'])->name('units.index');
        Route::post('/units', [UnitController::class, 'store'])->name('units.store');
        Route::post('/units/conversions', [UnitController::class, 'storeConversion'])->name('units.conversions.store');
        Route::delete('/units/conversions/{conversion}', [UnitController::class, 'destroyConversion'])->name('units.conversions.destroy');

        // Warehouses
        Route::post('/warehouses/{warehouse}/default', [WarehouseController::class, 'setDefault'])->name('warehouses.default');
        Route::resource('warehouses', WarehouseController::class);

        // Stock Balances & Movements Ledger
        Route::get('/stocks', [InventoryStockController::class, 'overview'])->name('stocks.overview');
        Route::get('/movements', [InventoryStockController::class, 'movements'])->name('stocks.movements');
        Route::post('/transactions', [InventoryStockController::class, 'storeTransaction'])->name('stocks.transactions.store');

        // Recipes & Technical Cards (Phase 2)
        Route::post('/recipes/quick-packaging', [RecipeController::class, 'quickStorePackaging'])->name('recipes.quick-packaging');
        Route::post('/recipes/{recipe}/approve', [RecipeController::class, 'approve'])->name('recipes.approve');
        Route::post('/recipes/{recipe}/recalculate', [RecipeController::class, 'recalculate'])->name('recipes.recalculate');
        Route::resource('recipes', RecipeController::class);

        // Suppliers & Price Lists (Phase 3)
        Route::post('/suppliers/{supplier}/prices', [SupplierController::class, 'savePrices'])->name('suppliers.prices.save');
        Route::resource('suppliers', SupplierController::class);

        // Procurement & Purchase Orders (Phase 3)
        Route::get('/procurement/suggestions', [PurchaseOrderController::class, 'suggestions'])->name('procurement.suggestions');
        Route::post('/procurement/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('procurement.approve');
        Route::post('/procurement/{purchaseOrder}/send', [PurchaseOrderController::class, 'markSent'])->name('procurement.send');
        Route::post('/procurement/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('procurement.receive');
        Route::post('/procurement/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('procurement.cancel');
        Route::resource('procurement', PurchaseOrderController::class)->parameters(['procurement' => 'purchaseOrder']);

        // Accounts Payable Invoices & Payments (Phase 3)
        Route::post('/invoices/tax-import', [SupplierInvoiceController::class, 'importTaxInvoice'])->name('invoices.tax-import');
        Route::get('/invoices', [SupplierInvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{supplierInvoice}', [SupplierInvoiceController::class, 'show'])->name('invoices.show');
        Route::post('/invoices/{supplierInvoice}/convert-item/{item}', [SupplierInvoiceController::class, 'convertItem'])->name('invoices.convert-item');
        Route::post('/payments', [SupplierInvoiceController::class, 'storePayment'])->name('payments.store');

        // Production & Semi-Finished Processing (Phase 4)
        Route::post('/production/{productionOrder}/start', [ProductionOrderController::class, 'start'])->name('production.start');
        Route::post('/production/{productionOrder}/complete', [ProductionOrderController::class, 'complete'])->name('production.complete');
        Route::post('/production/{productionOrder}/cancel', [ProductionOrderController::class, 'cancel'])->name('production.cancel');
        Route::resource('production', ProductionOrderController::class)->parameters(['production' => 'productionOrder']);

        // HACCP, Food Safety & Traceability (Phase 6)
        Route::get('/haccp', [HaccpController::class, 'dashboard'])->name('haccp.dashboard');
        Route::get('/haccp/temperatures', [HaccpController::class, 'temperatureLogs'])->name('haccp.temperatures');
        Route::post('/haccp/temperatures', [HaccpController::class, 'storeTemperature'])->name('haccp.temperatures.store');
        Route::get('/haccp/checklists', [HaccpController::class, 'checklists'])->name('haccp.checklists');
        Route::post('/haccp/checklists', [HaccpController::class, 'storeChecklist'])->name('haccp.checklists.store');
        Route::get('/haccp/ccps', [HaccpController::class, 'ccps'])->name('haccp.ccps');
        Route::post('/haccp/ccps/seed', [HaccpController::class, 'seedCcps'])->name('haccp.ccps.seed');
        Route::get('/haccp/batches', [HaccpController::class, 'batches'])->name('haccp.batches');
        Route::get('/haccp/traceability', [HaccpController::class, 'traceability'])->name('haccp.traceability');

        // Waste & Loss Management (Phase 7)
        Route::get('/waste', [WasteController::class, 'index'])->name('waste.index');
        Route::get('/waste/create', [WasteController::class, 'create'])->name('waste.create');
        Route::post('/waste', [WasteController::class, 'store'])->name('waste.store');
        Route::get('/waste/{wasteLog}', [WasteController::class, 'show'])->name('waste.show');

        // Inventory Reports & Business Intelligence (Phase 7)
        Route::get('/reports/inventory', [InventoryReportController::class, 'index'])->name('reports.inventory.index');
        Route::get('/reports/inventory/food-cost', [InventoryReportController::class, 'foodCostVariance'])->name('reports.inventory.food-cost');
        Route::get('/reports/inventory/abc-analysis', [InventoryReportController::class, 'abcAnalysis'])->name('reports.inventory.abc-analysis');
        Route::get('/reports/inventory/turnover', [InventoryReportController::class, 'turnover'])->name('reports.inventory.turnover');
        Route::get('/reports/inventory/waste', [InventoryReportController::class, 'wasteAnalysis'])->name('reports.inventory.waste');
    });

    // Cash Management & Financial Operations
    Route::prefix('finance')->name('finance.')->group(function () {
        // Executive Financial Dashboard
        Route::get('/', [FinancialDashboardController::class, 'index'])->name('dashboard');

        // Cash Registers & Hardware Drawers
        Route::get('/registers', [CashRegisterController::class, 'index'])->name('registers.index');
        Route::post('/registers', [CashRegisterController::class, 'store'])->name('registers.store');
        Route::put('/registers/{register}', [CashRegisterController::class, 'update'])->name('registers.update');
        Route::post('/registers/{register}/toggle', [CashRegisterController::class, 'toggle'])->name('registers.toggle');
        Route::post('/registers/{register}/kick-drawer', [CashRegisterController::class, 'kickDrawer'])->name('registers.kick-drawer');

        // Cash Shifts & Blind Counting
        Route::get('/shifts', [CashShiftController::class, 'index'])->name('shifts.index');
        Route::post('/shifts/open', [CashShiftController::class, 'open'])->name('shifts.open');
        Route::get('/shifts/{shift}', [CashShiftController::class, 'show'])->name('shifts.show');
        Route::post('/shifts/{shift}/close', [CashShiftController::class, 'close'])->name('shifts.close');
        Route::post('/shifts/{shift}/movement', [CashShiftController::class, 'movement'])->name('shifts.movement');
        Route::post('/shifts/{shift}/approve', [CashShiftController::class, 'approve'])->name('shifts.approve');

        // Expenses & Accounts Payable
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
        Route::post('/expenses/{expense}/pay', [ExpenseController::class, 'pay'])->name('expenses.pay');
        Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
        Route::post('/expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject');

        // Other Operating Income
        Route::get('/incomes', [IncomeController::class, 'index'])->name('incomes.index');
        Route::post('/incomes', [IncomeController::class, 'store'])->name('incomes.store');

        // Financial Asset Transfers
        Route::get('/transfers', [FinancialTransferController::class, 'index'])->name('transfers.index');
        Route::post('/transfers', [FinancialTransferController::class, 'store'])->name('transfers.store');

        // Bank Accounts & Reconciliations
        Route::get('/bank-accounts', [BankAccountController::class, 'index'])->name('bank_accounts.index');
        Route::post('/bank-accounts', [BankAccountController::class, 'store'])->name('bank_accounts.store');
        Route::get('/bank-accounts/{bankAccount}', [BankAccountController::class, 'show'])->name('bank_accounts.show');
        Route::post('/bank-accounts/{bankAccount}/import', [BankAccountController::class, 'import'])->name('bank_accounts.import');
        Route::post('/bank-accounts/{bankAccount}/reconcile', [BankAccountController::class, 'reconcile'])->name('bank_accounts.reconcile');

        // Chart of Accounts & General Ledger
        Route::get('/ledger/accounts', [GeneralLedgerController::class, 'index'])->name('ledger.index');
        Route::post('/ledger/accounts/seed', [GeneralLedgerController::class, 'seedCoa'])->name('ledger.seed');
        Route::get('/ledger/entries', [GeneralLedgerController::class, 'entries'])->name('ledger.entries');
        Route::get('/ledger/entries/{entry}', [GeneralLedgerController::class, 'showEntry'])->name('ledger.entries.show');
        Route::post('/ledger/entries/{entry}/reverse', [GeneralLedgerController::class, 'reverse'])->name('ledger.entries.reverse');

        // Financial Reports
        Route::get('/reports', [FinancialReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/profit-loss', [FinancialReportController::class, 'profitAndLoss'])->name('reports.profit_loss');
        Route::get('/reports/balance-sheet', [FinancialReportController::class, 'balanceSheet'])->name('reports.balance_sheet');
        Route::get('/reports/cash-flow', [FinancialReportController::class, 'cashFlow'])->name('reports.cash_flow');
        Route::get('/reports/trial-balance', [FinancialReportController::class, 'trialBalance'])->name('reports.trial_balance');
        Route::get('/reports/sales-analytics', [FinancialReportController::class, 'salesAnalytics'])->name('reports.sales_analytics');
        Route::get('/reports/export/{type}', [FinancialReportController::class, 'export'])->name('reports.export');

        // Approvals Queue
        Route::get('/approvals', [FinancialApprovalController::class, 'index'])->name('approvals.index');
        Route::post('/approvals/{approvalRequest}/decide', [FinancialApprovalController::class, 'decide'])->name('approvals.decide');

        // Immutable Audit Trail & Integrity Check
        Route::get('/audit', [FinancialAuditController::class, 'index'])->name('audit.index');
        Route::get('/audit/verify', [FinancialAuditController::class, 'verify'])->name('audit.verify');
    });

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

    // Loyalty & Digital Wallet Module (Enterprise)
    Route::prefix('loyalty')->name('loyalty.')->group(function () {
        Route::get('/', [LoyaltyDashboardController::class, 'index'])->name('index');
        Route::get('/customers', [LoyaltyDashboardController::class, 'customers'])->name('customers');
        Route::post('/adjust-balance', [LoyaltyDashboardController::class, 'adjustBalance'])->name('adjust_balance');
        Route::get('/transactions', [LoyaltyDashboardController::class, 'transactions'])->name('transactions');
        Route::get('/transactions/export', [LoyaltyDashboardController::class, 'exportTransactions'])->name('transactions.export');
        Route::post('/reconcile', [LoyaltyDashboardController::class, 'reconcile'])->name('reconcile');
        Route::get('/audit-logs', [LoyaltyDashboardController::class, 'auditLogs'])->name('audit_logs');

        // Program Settings
        Route::get('/settings', [LoyaltyProgramController::class, 'index'])->name('settings');
        Route::post('/settings', [LoyaltyProgramController::class, 'update'])->name('settings.update');

        // Rules
        Route::get('/rules', [LoyaltyRulesController::class, 'index'])->name('rules');
        Route::post('/rules', [LoyaltyRulesController::class, 'store'])->name('rules.store');
        Route::post('/rules/{rule}/toggle', [LoyaltyRulesController::class, 'toggle'])->name('rules.toggle');
        Route::delete('/rules/{rule}', [LoyaltyRulesController::class, 'destroy'])->name('rules.destroy');

        // Rewards
        Route::get('/rewards', [LoyaltyRewardsController::class, 'index'])->name('rewards');
        Route::post('/rewards', [LoyaltyRewardsController::class, 'store'])->name('rewards.store');
        Route::post('/rewards/{reward}/toggle', [LoyaltyRewardsController::class, 'toggle'])->name('rewards.toggle');
        Route::delete('/rewards/{reward}', [LoyaltyRewardsController::class, 'destroy'])->name('rewards.destroy');

        // Tiers
        Route::get('/tiers', [LoyaltyTiersController::class, 'index'])->name('tiers');
        Route::post('/tiers/{tier}', [LoyaltyTiersController::class, 'update'])->name('tiers.update');

        // Stamps
        Route::get('/stamps', [LoyaltyStampCardsController::class, 'index'])->name('stamps');
        Route::post('/stamps', [LoyaltyStampCardsController::class, 'store'])->name('stamps.store');
        Route::post('/stamps/{program}/toggle', [LoyaltyStampCardsController::class, 'toggle'])->name('stamps.toggle');
        Route::delete('/stamps/{program}', [LoyaltyStampCardsController::class, 'destroy'])->name('stamps.destroy');

        // Referrals
        Route::get('/referrals', [LoyaltyReferralsController::class, 'index'])->name('referrals');
        Route::match(['post', 'put'], '/referrals/settings', [LoyaltyReferralsController::class, 'updateSettings'])->name('referrals.settings');
        Route::match(['post', 'put'], '/referrals/settings-update', [LoyaltyReferralsController::class, 'updateSettings'])->name('referrals.settings.update');

        // Campaigns
        Route::get('/campaigns', [LoyaltyCampaignsController::class, 'index'])->name('campaigns');
        Route::post('/campaigns', [LoyaltyCampaignsController::class, 'store'])->name('campaigns.store');
        Route::post('/campaigns/{campaign}/toggle', [LoyaltyCampaignsController::class, 'toggle'])->name('campaigns.toggle');
        Route::delete('/campaigns/{campaign}', [LoyaltyCampaignsController::class, 'destroy'])->name('campaigns.destroy');

        // Wallet Designer & Settings
        Route::get('/designer', [WalletDesignerController::class, 'index'])->name('designer');
        Route::post('/designer', [WalletDesignerController::class, 'update'])->name('designer.update');
        Route::get('/designer/preview-data', [WalletDesignerController::class, 'previewData'])->name('designer.preview');
        Route::get('/wallet-settings', [WalletSettingsController::class, 'index'])->name('wallet_settings');
        Route::post('/wallet-settings', [WalletSettingsController::class, 'update'])->name('wallet_settings.update');

        // Staff Operations
        Route::get('/staff', [StaffLoyaltyOperationsController::class, 'index'])->name('staff');
        Route::post('/staff/lookup', [StaffLoyaltyOperationsController::class, 'lookup'])->name('staff.lookup');
        Route::post('/staff/points/issue', [StaffLoyaltyOperationsController::class, 'issuePoints'])->name('staff.points.issue');
        Route::post('/staff/points/redeem', [StaffLoyaltyOperationsController::class, 'redeemPoints'])->name('staff.points.redeem');
        Route::post('/staff/rewards/redeem', [StaffLoyaltyOperationsController::class, 'redeemRewardVoucher'])->name('staff.rewards.redeem');
        Route::post('/staff/stamps/issue', [StaffLoyaltyOperationsController::class, 'issueStamp'])->name('staff.stamps.issue');
        Route::post('/staff/stamps/{card}/redeem', [StaffLoyaltyOperationsController::class, 'redeemStampCard'])->name('staff.stamps.redeem');
    });

    // User Profile & 2FA Security
    Route::get('/profile', [ProfileSecurityController::class, 'index'])->name('profile');
    Route::post('/profile/update', [ProfileSecurityController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/pin', [ProfileSecurityController::class, 'updatePin'])->name('profile.pin.update');
    Route::post('/profile/logout-other-devices', [ProfileSecurityController::class, 'logoutOtherDevices'])->name('profile.logout_other_devices');
});

// 5. Point of Sale (POS) Module Routes
Route::prefix('pos/api/auth')->name('pos.api.auth.')->group(function () {
    Route::post('/login', [PosAuthController::class, 'login'])->middleware('throttle:10,1')->name('login');
    Route::post('/pin-login', [PosAuthController::class, 'pinLogin'])->middleware('throttle:15,1')->name('pin_login');
});

Route::middleware(['auth', EnsurePosAccess::class])->group(function () {
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/admin/pos', [PosController::class, 'index'])->name('admin.pos.index');
    Route::get('/pos/kds', [PosKdsController::class, 'index'])->name('pos.kds');

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

        // Dedicated Role-Based Workspaces & Employee Profile
        Route::prefix('workspace')->name('workspace.')->group(function () {
            Route::get('/profile', [PosWorkspaceController::class, 'getProfile'])->name('profile');
            Route::post('/profile', [PosWorkspaceController::class, 'updateProfile'])->name('profile.update');
            Route::post('/password', [PosWorkspaceController::class, 'changePassword'])->name('password.change');
            Route::post('/pin', [PosWorkspaceController::class, 'updatePin'])->name('pin.update');
            Route::post('/pin/send-code', [PosWorkspaceController::class, 'sendPinCode'])->name('pin.send_code');
            Route::post('/logout-other-devices', [PosWorkspaceController::class, 'logoutOtherDevices'])->name('logout_other_devices');

            // Waiter Workspace
            Route::get('/waiter/dashboard', [PosWorkspaceController::class, 'getWaiterDashboard'])->name('waiter.dashboard');
            Route::get('/waiter/tips', [PosWorkspaceController::class, 'getWaiterTips'])->name('waiter.tips');
            Route::post('/orders/{order}/cancel-request', [PosWorkspaceController::class, 'requestOrderCancellation'])->name('orders.cancel_request');

            // Cashier Workspace
            Route::get('/cashier/dashboard', [PosWorkspaceController::class, 'getCashierDashboard'])->name('cashier.dashboard');

            // Manager Workspace
            Route::get('/manager/dashboard', [PosWorkspaceController::class, 'getManagerDashboard'])->name('manager.dashboard');
            Route::post('/orders/{order}/approve-cancellation', [PosWorkspaceController::class, 'approveOrderCancellation'])->name('orders.approve_cancellation');
            Route::post('/orders/{order}/reject-cancellation', [PosWorkspaceController::class, 'rejectOrderCancellation'])->name('orders.reject_cancellation');
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

        // Shifts & Cash Drawer Management
        Route::get('/shifts/current', [PosPaymentShiftController::class, 'getCurrentShift'])->name('shifts.current');
        Route::post('/shifts/open', [PosPaymentShiftController::class, 'openShift'])->name('shifts.open');
        Route::post('/shifts/cash-movement', [PosPaymentShiftController::class, 'recordCashMovement'])->name('shifts.cash_movement');
        Route::post('/shifts/{shift}/close', [PosPaymentShiftController::class, 'closeShift'])->name('shifts.close');
        Route::get('/shifts/{shift}/report', [PosPaymentShiftController::class, 'getShiftReport'])->name('shifts.report');

        // Financial Operations (Payments & Refunds)
        Route::post('/orders/{order}/payment', [PosPaymentShiftController::class, 'processPayment'])->middleware('throttle:60,1')->name('orders.payment');
        Route::post('/payments/{payment}/refund', [PosPaymentShiftController::class, 'refundPayment'])->middleware('throttle:30,1')->name('payments.refund');

        // Kitchen Display System (KDS) & Hardware Printing
        Route::prefix('kds')->name('kds.')->group(function () {
            Route::get('/tickets', [PosKdsController::class, 'getTickets'])->name('tickets');
            Route::get('/stations', [PosKdsController::class, 'getStations'])->name('stations');
            Route::post('/items/{item}/status', [PosKdsController::class, 'updateItemStatus'])->name('items.status');
            Route::post('/orders/{order}/bump', [PosKdsController::class, 'bumpOrder'])->name('orders.bump');
            Route::post('/orders/{order}/print', [PosKdsController::class, 'printKitchenTicket'])->name('orders.print');
            Route::get('/orders/{order}/receipt', [PosKdsController::class, 'printCustomerReceipt'])->name('orders.receipt');
            Route::post('/orders/{order}/receipt', [PosKdsController::class, 'printCustomerReceipt'])->name('orders.receipt.post');
        });

        // Offline-First Sync & Cache Management
        Route::prefix('offline')->name('offline.')->group(function () {
            Route::get('/manifest', [PosOfflineSyncController::class, 'manifest'])->name('manifest');
            Route::post('/sync', [PosOfflineSyncController::class, 'sync'])->middleware('throttle:60,1')->name('sync');
            Route::get('/conflicts', [PosOfflineSyncController::class, 'getConflicts'])->name('conflicts');
            Route::post('/conflicts/{conflict}/resolve', [PosOfflineSyncController::class, 'resolveConflict'])->name('conflicts.resolve');
        });

        // Local Network Mode (LAN Mode)
        Route::prefix('lan')->name('lan.')->group(function () {
            Route::get('/peers', [PosLanController::class, 'getPeers'])->name('peers');
            Route::post('/heartbeat', [PosLanController::class, 'heartbeat'])->name('heartbeat');
            Route::post('/hub/elect', [PosLanController::class, 'electHub'])->name('hub.elect');
            Route::post('/dispatch', [PosLanController::class, 'dispatchEvent'])->name('dispatch');
            Route::get('/dispatches/pending', [PosLanController::class, 'getPendingDispatches'])->name('dispatches.pending');
            Route::post('/dispatches/{dispatch}/ack', [PosLanController::class, 'acknowledgeDispatch'])->name('dispatches.ack');
        });

        // Integrations (QR Menu, Online Orders, Reservations, Waiter Calls, Loyalty, Fiscal, Inventory)
        Route::prefix('integrations')->name('integrations.')->group(function () {
            Route::get('/incoming-orders', [PosIntegrationsController::class, 'incomingOrders'])->name('orders.incoming');
            Route::post('/orders/{order}/accept', [PosIntegrationsController::class, 'acceptOrder'])->name('orders.accept');
            Route::post('/orders/{order}/reject', [PosIntegrationsController::class, 'rejectOrder'])->name('orders.reject');
            Route::get('/reservations', [PosIntegrationsController::class, 'reservations'])->name('reservations.index');
            Route::post('/reservations/{reservation}/seat', [PosIntegrationsController::class, 'seatReservation'])->name('reservations.seat');
            Route::get('/waiter-calls', [PosIntegrationsController::class, 'waiterCalls'])->name('waiter_calls.index');
            Route::post('/waiter-calls/{waiterCall}/resolve', [PosIntegrationsController::class, 'resolveWaiterCall'])->name('waiter_calls.resolve');
            Route::get('/customers/search', [PosIntegrationsController::class, 'searchCustomers'])->name('customers.search');
            Route::post('/customers/{customer}/loyalty/redeem', [PosIntegrationsController::class, 'redeemLoyalty'])->name('customers.loyalty.redeem');
            Route::post('/orders/{order}/qr-payment', [PosIntegrationsController::class, 'initiateQrPayment'])->name('orders.qr_payment');
            Route::post('/orders/{order}/fiscal-receipt', [PosIntegrationsController::class, 'printFiscalReceipt'])->name('orders.fiscal_receipt');
            Route::get('/inventory/{product}', [PosIntegrationsController::class, 'getInventory'])->name('inventory.show');
            Route::post('/inventory/{product}/adjust', [PosIntegrationsController::class, 'adjustInventory'])->name('inventory.adjust');
        });

        // Reports & Analytics (Sales, Employees, Products, Categories, Cash, Tips, Discounts, Dashboard) - Manager & Owner Only
        Route::prefix('reports')->middleware('role:vendor_owner,manager')->name('reports.')->group(function () {
            Route::get('/sales', [PosReportingController::class, 'sales'])->name('sales');
            Route::get('/employees', [PosReportingController::class, 'employees'])->name('employees');
            Route::get('/products', [PosReportingController::class, 'products'])->name('products');
            Route::get('/categories', [PosReportingController::class, 'categories'])->name('categories');
            Route::get('/cash', [PosReportingController::class, 'cash'])->name('cash');
            Route::get('/tips', [PosReportingController::class, 'tips'])->name('tips');
            Route::get('/discounts-refunds', [PosReportingController::class, 'discountsAndRefunds'])->name('discounts_refunds');
            Route::get('/dashboard', [PosReportingController::class, 'dashboard'])->name('dashboard');
        });

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
