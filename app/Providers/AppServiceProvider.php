<?php

namespace App\Providers;

use App\Events\OrderCreated;
use App\Events\WaiterCalled;
use App\Listeners\EmailTrackingSubscriber;
use App\Listeners\SecurityAuditAuthSubscriber;
use App\Listeners\SendTelegramOrderNotification;
use App\Listeners\SendTelegramWaiterCallNotification;
use App\Models\AiWaiterSession;
use App\Models\Category;
use App\Models\ContentTranslation;
use App\Models\Customer;
use App\Models\Location;
use App\Models\LocationProductOverride;
use App\Models\Order;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationPreOrder;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorStorageFile;
use App\Models\WaiterCall;
use App\Policies\AiWaiterSessionPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\ContentTranslationPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\LocationPolicy;
use App\Policies\LocationProductOverridePolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ReservationPolicy;
use App\Policies\ReservationPreOrderPolicy;
use App\Policies\RestaurantTablePolicy;
use App\Policies\UserPolicy;
use App\Policies\VendorPolicy;
use App\Policies\VendorStorageFilePolicy;
use App\Policies\WaiterCallPolicy;
use App\Security\Permission;
use App\Services\EmailRoutingService;
use App\Services\EntitlementService;
use App\Services\Localization\Contracts\TranslationProviderInterface;
use App\Services\Localization\Providers\AiGatewayTranslationProvider;
use App\Services\Localization\TranslationService;
use App\Services\Localization\TranslationValidator;
use App\Services\QuotaService;
use App\Services\TenantContext;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, function () {
            return new TenantContext;
        });

        $this->app->singleton(TranslationProviderInterface::class, function ($app) {
            return $app->make(AiGatewayTranslationProvider::class);
        });

        $this->app->singleton(TranslationService::class, function ($app) {
            return new TranslationService(
                $app->make(TranslationProviderInterface::class),
                $app->make(TranslationValidator::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production') || str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Guarantee tenant context isolation across queue worker lifecycle
        Queue::before(function (JobProcessing $event): void {
            app(TenantContext::class)->clear();
        });

        Queue::after(function (JobProcessed $event): void {
            app(TenantContext::class)->clear();
        });

        Queue::failing(function (JobFailed $event): void {
            app(TenantContext::class)->clear();
        });

        Queue::exceptionOccurred(function (JobExceptionOccurred $event): void {
            app(TenantContext::class)->clear();
        });

        // Register Telegram notification event listeners
        Event::listen(OrderCreated::class, SendTelegramOrderNotification::class);
        Event::listen(WaiterCalled::class, SendTelegramWaiterCallNotification::class);

        // Register Security Audit Authentication Subscriber
        Event::subscribe(SecurityAuditAuthSubscriber::class);

        // Register Email Tracking & Routing Subscriber
        Event::subscribe(EmailTrackingSubscriber::class);

        // Apply active database mail configuration (SMTP / senders)
        if (! app()->runningInConsole() || app()->runningUnitTests()) {
            app(EmailRoutingService::class)->applyActiveMailConfiguration();
        }

        // Register Multi-Tenant Security Policies
        Gate::policy(Vendor::class, VendorPolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(WaiterCall::class, WaiterCallPolicy::class);
        Gate::policy(LocationProductOverride::class, LocationProductOverridePolicy::class);
        Gate::policy(AiWaiterSession::class, AiWaiterSessionPolicy::class);
        Gate::policy(VendorStorageFile::class, VendorStorageFilePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ContentTranslation::class, ContentTranslationPolicy::class);
        Gate::policy(Reservation::class, ReservationPolicy::class);
        Gate::policy(RestaurantTable::class, RestaurantTablePolicy::class);
        Gate::policy(ReservationPreOrder::class, ReservationPreOrderPolicy::class);

        // Register Gates for all granular permissions
        foreach (Permission::all() as $permission) {
            Gate::define($permission, function (User $user) use ($permission): bool {
                return $user->hasPermission($permission);
            });
        }

        // Subscription feature and quota Blade directives
        Blade::if('feature', function (string $featureKey, ?Vendor $vendor = null): bool {
            return app(EntitlementService::class)->can($vendor, $featureKey);
        });

        Blade::if('quota', function (string $quotaKey, int $quantity = 1, ?Vendor $vendor = null): bool {
            return app(QuotaService::class)->can($vendor, $quotaKey, $quantity);
        });
    }
}
