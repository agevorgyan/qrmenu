<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\FeatureCategory;
use App\Models\FeatureDependency;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use App\Models\SubscriptionPlanVersion;
use App\Models\Vendor;
use App\Security\FeatureCatalog;
use App\Security\QuotaCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $hasDynamicTables = Schema::hasTable('feature_categories')
            && Schema::hasTable('features')
            && Schema::hasTable('plan_features');

        $categoryMap = [];
        $featureMap = [];

        if ($hasDynamicTables) {
            $categoryMap = $this->seedCategories();
            $featureMap = $this->seedFeatures($categoryMap);
            $this->seedDependencies($featureMap);
            $this->seedAddons($featureMap);
        }

        // Remove any legacy plans other than the 3 initial plans so initial state strictly has Basic, Pro, Advanced
        SubscriptionPlan::query()
            ->whereNotIn('slug', ['basic', 'pro', 'advanced'])
            ->whereDoesntHave('vendors')
            ->whereDoesntHave('subscriptions')
            ->delete();

        $plans = $this->initialPlansDefinition();

        foreach ($plans as $p) {
            $plan = SubscriptionPlan::updateOrCreate(
                ['slug' => $p['slug']],
                [
                    'name' => $p['name'],
                    'name_translations' => $p['name_translations'],
                    'price' => $p['price'],
                    'yearly_price' => $p['yearly_price'],
                    'currency' => $p['currency'],
                    'billing_interval' => $p['billing_interval'],
                    'duration_days' => $p['duration_days'],
                    'trial_days' => $p['trial_days'],
                    'grace_period_days' => 3,
                    'warning_threshold_percent' => 80,
                    'description' => $p['description'],
                    'description_translations' => $p['description_translations'],
                    'features' => $p['features'],
                    'feature_codes' => $p['feature_codes'],
                    'quota_limits' => $p['quota_limits'],
                    'is_active' => true,
                    'is_custom' => false,
                    'is_archived' => false,
                    'sort_order' => $p['sort_order'],
                ]
            );

            $basePrice = (float) $plan->price;
            $yearlyPrice = (float) ($p['yearly_price'] ?? ($basePrice > 0 ? round($basePrice * 12 * 0.80, 2) : 0));
            $currency = 'AMD';

            SubscriptionPlanPrice::updateOrCreate(
                [
                    'subscription_plan_id' => $plan->id,
                    'billing_interval' => 'monthly',
                    'currency' => $currency,
                ],
                [
                    'price' => $basePrice,
                    'is_active' => true,
                    'description' => "Monthly {$plan->name} billing tier",
                ]
            );

            SubscriptionPlanPrice::updateOrCreate(
                [
                    'subscription_plan_id' => $plan->id,
                    'billing_interval' => 'yearly',
                    'currency' => $currency,
                ],
                [
                    'price' => $yearlyPrice,
                    'is_active' => true,
                    'description' => "Yearly {$plan->name} billing tier (20% discount)",
                ]
            );

            if ($hasDynamicTables && ! empty($featureMap)) {
                $this->seedPlanVersionAndFeatures($plan, $p['plan_matrix'], $featureMap);
            }
        }

        // Reassign any legacy plans (e.g. business, custom) to Advanced and remove legacy initial plans
        $advancedPlan = SubscriptionPlan::where('slug', 'advanced')->first();
        if ($advancedPlan) {
            $legacyPlanIds = SubscriptionPlan::whereNotIn('slug', ['basic', 'pro', 'advanced'])->pluck('id')->toArray();
            if (! empty($legacyPlanIds)) {
                Vendor::withoutGlobalScopes()
                    ->whereIn('subscription_plan_id', $legacyPlanIds)
                    ->update([
                        'subscription_plan_id' => $advancedPlan->id,
                        'subscription_plan' => 'advanced',
                    ]);
                Subscription::withoutGlobalScopes()
                    ->whereIn('subscription_plan_id', $legacyPlanIds)
                    ->update([
                        'subscription_plan_id' => $advancedPlan->id,
                        'plan_version_id' => $advancedPlan->current_version_id,
                        'plan_version_number' => $advancedPlan->current_version_number ?? 1,
                    ]);
                SubscriptionPayment::withoutGlobalScopes()
                    ->whereIn('subscription_plan_id', $legacyPlanIds)
                    ->update(['subscription_plan_id' => $advancedPlan->id]);
                SubscriptionPlanPrice::whereIn('subscription_plan_id', $legacyPlanIds)->delete();
                SubscriptionPlan::whereIn('id', $legacyPlanIds)->delete();
            }
        }

        // Reassign any legacy plans (e.g. business, custom) to Advanced and remove legacy initial plans
        $advancedPlan = SubscriptionPlan::where('slug', 'advanced')->first();
        if ($advancedPlan) {
            $legacyPlanIds = SubscriptionPlan::whereNotIn('slug', ['basic', 'pro', 'advanced'])->pluck('id')->toArray();
            if (! empty($legacyPlanIds)) {
                \App\Models\Vendor::withoutGlobalScopes()
                    ->whereIn('subscription_plan_id', $legacyPlanIds)
                    ->update([
                        'subscription_plan_id' => $advancedPlan->id,
                        'subscription_plan' => 'advanced',
                    ]);
                \App\Models\Subscription::withoutGlobalScopes()
                    ->whereIn('subscription_plan_id', $legacyPlanIds)
                    ->update([
                        'subscription_plan_id' => $advancedPlan->id,
                        'plan_version_id' => $advancedPlan->current_version_id,
                        'plan_version_number' => $advancedPlan->current_version_number ?? 1,
                    ]);
                \App\Models\SubscriptionPayment::withoutGlobalScopes()
                    ->whereIn('subscription_plan_id', $legacyPlanIds)
                    ->update(['subscription_plan_id' => $advancedPlan->id]);
                SubscriptionPlanPrice::whereIn('subscription_plan_id', $legacyPlanIds)->delete();
                SubscriptionPlan::whereIn('id', $legacyPlanIds)->delete();
            }
        }
    }

    /**
     * @return array<string, FeatureCategory>
     */
    protected function seedCategories(): array
    {
        $categories = [
            [
                'code' => 'menu_products',
                'name' => 'Menu & Products',
                'name_translations' => [
                    'hy' => 'Մենյու և Ապրանքներ',
                    'en' => 'Menu & Products',
                    'ru' => 'Меню и товары',
                ],
                'description' => 'Digital QR menu, categories, products, modifiers, variants, and scheduling.',
                'description_translations' => [
                    'hy' => 'Թվային QR մենյու, բաժիններ, ապրանքներ, մոդիֆիկատորներ, տարբերակներ և ժամանակացույց։',
                    'en' => 'Digital QR menu, categories, products, modifiers, variants, and scheduling.',
                    'ru' => 'Цифровое QR-меню, категории, товары, модификаторы, варианты и расписание.',
                ],
                'icon' => 'fa-utensils',
                'sort_order' => 1,
            ],
            [
                'code' => 'languages_translation',
                'name' => 'Languages & Translation',
                'name_translations' => [
                    'hy' => 'Լեզուներ և Թարգմանություն',
                    'en' => 'Languages & Translation',
                    'ru' => 'Языки и перевод',
                ],
                'description' => 'Multilingual menu support, maximum languages limit, and AI translation.',
                'description_translations' => [
                    'hy' => 'Բազմալեզու մենյու, լեզուների քանակի սահմանափակում և AI թարգմանություն։',
                    'en' => 'Multilingual menu support, maximum languages limit, and AI translation.',
                    'ru' => 'Многоязычное меню, лимит языков и AI-перевод.',
                ],
                'icon' => 'fa-language',
                'sort_order' => 2,
            ],
            [
                'code' => 'currency_pricing',
                'name' => 'Currency & Pricing',
                'name_translations' => [
                    'hy' => 'Արժույթ և Գնագոյացում',
                    'en' => 'Currency & Pricing',
                    'ru' => 'Валюта и ценообразование',
                ],
                'description' => 'Primary currency, multi-currency display, and automatic/manual exchange rates.',
                'description_translations' => [
                    'hy' => 'Հիմնական արժույթ, բազմաարժույթ ցուցադրում և ավտոմատ/ձեռքով փոխարժեքներ։',
                    'en' => 'Primary currency, multi-currency display, and automatic/manual exchange rates.',
                    'ru' => 'Основная валюта, мультивалютное отображение и курсы валют.',
                ],
                'icon' => 'fa-coins',
                'sort_order' => 3,
            ],
            [
                'code' => 'online_ordering',
                'name' => 'Online Ordering',
                'name_translations' => [
                    'hy' => 'Օնլայն Պատվերներ',
                    'en' => 'Online Ordering',
                    'ru' => 'Онлайн-заказы',
                ],
                'description' => 'Online ordering, table QR ordering, order management, payments, and tracking.',
                'description_translations' => [
                    'hy' => 'Օնլայն պատվերներ, սեղանի QR պատվերներ, պատվերների կառավարում և վճարումներ։',
                    'en' => 'Online ordering, table QR ordering, order management, payments, and tracking.',
                    'ru' => 'Онлайн-заказы, заказ по QR со стола, управление заказами и оплата.',
                ],
                'icon' => 'fa-cart-shopping',
                'sort_order' => 4,
            ],
            [
                'code' => 'delivery_pickup',
                'name' => 'Delivery & Pickup',
                'name_translations' => [
                    'hy' => 'Առաքում և Տեղից վերցնում',
                    'en' => 'Delivery & Pickup',
                    'ru' => 'Доставка и самовывоз',
                ],
                'description' => 'Delivery, pickup, delivery zones, fees, minimum order, and delivery scheduling.',
                'description_translations' => [
                    'hy' => 'Առաքում, տեղից վերցնում, առաքման գոտիներ, սակագներ և ժամանակացույց։',
                    'en' => 'Delivery, pickup, delivery zones, fees, minimum order, and delivery scheduling.',
                    'ru' => 'Доставка, самовывоз, зоны доставки, тарифы и расписание доставки.',
                ],
                'icon' => 'fa-truck-fast',
                'sort_order' => 5,
            ],
            [
                'code' => 'restaurant_management',
                'name' => 'Restaurant Management',
                'name_translations' => [
                    'hy' => 'Ռեստորանի Կառավարում',
                    'en' => 'Restaurant Management',
                    'ru' => 'Управление рестораном',
                ],
                'description' => 'Table management, reservations, pre-orders, and Kitchen Display System (KDS).',
                'description_translations' => [
                    'hy' => 'Սեղանների կառավարում, ամրագրումներ, նախնական պատվերներ և խոհանոցային էկրան (KDS)։',
                    'en' => 'Table management, reservations, pre-orders, and Kitchen Display System (KDS).',
                    'ru' => 'Управление столами, бронирование, предзаказы и кухонный экран (KDS).',
                ],
                'icon' => 'fa-chair',
                'sort_order' => 6,
            ],
            [
                'code' => 'pos_operations',
                'name' => 'POS & Operations',
                'name_translations' => [
                    'hy' => 'POS և Գործառնություններ',
                    'en' => 'POS & Operations',
                    'ru' => 'POS и операции',
                ],
                'description' => 'Point of Sale terminal, Manager mode, Cashier mode, and Waiter mode.',
                'description_translations' => [
                    'hy' => 'POS տերմինալ, Մենեջերի ռեժիմ, Գանձապահի ռեժիմ և Մատուցողի ռեժիմ։',
                    'en' => 'Point of Sale terminal, Manager mode, Cashier mode, and Waiter mode.',
                    'ru' => 'POS-терминал, режим менеджера, режим кассира и режим официанта.',
                ],
                'icon' => 'fa-cash-register',
                'sort_order' => 7,
            ],
            [
                'code' => 'staff_management',
                'name' => 'Staff Management',
                'name_translations' => [
                    'hy' => 'Անձնակազմի Կառավարում',
                    'en' => 'Staff Management',
                    'ru' => 'Управление персоналом',
                ],
                'description' => 'Staff user accounts limit and role-based team management.',
                'description_translations' => [
                    'hy' => 'Աշխատակիցների հաշիվների սահմանաչափ և դերերի կառավարում։',
                    'en' => 'Staff user accounts limit and role-based team management.',
                    'ru' => 'Лимит аккаунтов сотрудников и управление ролями.',
                ],
                'icon' => 'fa-users-gear',
                'sort_order' => 8,
            ],
            [
                'code' => 'marketing_loyalty',
                'name' => 'Marketing & Loyalty',
                'name_translations' => [
                    'hy' => 'Մարքեթինգ և Հավատարմություն',
                    'en' => 'Marketing & Loyalty',
                    'ru' => 'Маркетинг и лояльность',
                ],
                'description' => 'Discount coupons, loyalty program, Apple Wallet, CRM customer database, and segmentation.',
                'description_translations' => [
                    'hy' => 'Զեղչի կտրոններ, լոյալության ծրագիր, Apple Wallet, հաճախորդների բազա և սեգմենտավորում։',
                    'en' => 'Discount coupons, loyalty program, Apple Wallet, CRM customer database, and segmentation.',
                    'ru' => 'Купоны скидок, программа лояльности, Apple Wallet, база клиентов и сегментация.',
                ],
                'icon' => 'fa-bullhorn',
                'sort_order' => 9,
            ],
            [
                'code' => 'reviews_reputation',
                'name' => 'Reviews & Reputation',
                'name_translations' => [
                    'hy' => 'Կարծիքներ և Վարկանիշ',
                    'en' => 'Reviews & Reputation',
                    'ru' => 'Отзывы и репутация',
                ],
                'description' => 'Review generation and integrations with Google Reviews, Tripadvisor, and Yandex.',
                'description_translations' => [
                    'hy' => 'Կարծիքների հավաքագրում և ինտեգրում Google Reviews, Tripadvisor և Yandex հարթակների հետ։',
                    'en' => 'Review generation and integrations with Google Reviews, Tripadvisor, and Yandex.',
                    'ru' => 'Сбор отзывов и интеграция с Google Reviews, Tripadvisor и Яндекс.',
                ],
                'icon' => 'fa-star',
                'sort_order' => 10,
            ],
            [
                'code' => 'ai_features',
                'name' => 'AI Features',
                'name_translations' => [
                    'hy' => 'AI Հնարավորություններ',
                    'en' => 'AI Features',
                    'ru' => 'AI-функции',
                ],
                'description' => 'AI Waiter, AI Recommendations, AI Assistant, and monthly AI credits.',
                'description_translations' => [
                    'hy' => 'AI Մատուցող, AI Առաջարկներ, AI Օգնական և ամսական AI կրեդիտներ։',
                    'en' => 'AI Waiter, AI Recommendations, AI Assistant, and monthly AI credits.',
                    'ru' => 'AI-официант, AI-рекомендации, AI-ассистент и ежемесячные AI-кредиты.',
                ],
                'icon' => 'fa-wand-magic-sparkles',
                'sort_order' => 11,
            ],
            [
                'code' => 'analytics_reports',
                'name' => 'Analytics & Reports',
                'name_translations' => [
                    'hy' => 'Վերլուծություն և Հաշվետվություններ',
                    'en' => 'Analytics & Reports',
                    'ru' => 'Аналитика и отчеты',
                ],
                'description' => 'Basic analytics, advanced analytics, CSV/Excel export, and business intelligence.',
                'description_translations' => [
                    'hy' => 'Հիմնական և ընդլայնված վերլուծություն, Excel/CSV արտահանում և բիզնես հաշվետվություններ։',
                    'en' => 'Basic analytics, advanced analytics, CSV/Excel export, and business intelligence.',
                    'ru' => 'Базовая и расширенная аналитика, экспорт в Excel/CSV и бизнес-отчеты.',
                ],
                'icon' => 'fa-chart-line',
                'sort_order' => 12,
            ],
            [
                'code' => 'integrations_api',
                'name' => 'Integrations & API',
                'name_translations' => [
                    'hy' => 'Ինտեգրացիաներ և API',
                    'en' => 'Integrations & API',
                    'ru' => 'Интеграции и API',
                ],
                'description' => 'REST API access, webhooks, and custom domain connectivity.',
                'description_translations' => [
                    'hy' => 'API հասանելիություն, Webhooks և սեփական դոմենի միացում։',
                    'en' => 'REST API access, webhooks, and custom domain connectivity.',
                    'ru' => 'Доступ к API, вебхуки и подключение собственного домена.',
                ],
                'icon' => 'fa-plug',
                'sort_order' => 13,
            ],
            [
                'code' => 'branding_customization',
                'name' => 'Branding & Customization',
                'name_translations' => [
                    'hy' => 'Բրենդինգ և Անհատականացում',
                    'en' => 'Branding & Customization',
                    'ru' => 'Брендинг и кастомизация',
                ],
                'description' => 'White-label branding and storefront visual customization.',
                'description_translations' => [
                    'hy' => 'White-label բրենդինգ և մենյուի դիզայնի անհատականացում։',
                    'en' => 'White-label branding and storefront visual customization.',
                    'ru' => 'White-label брендинг и кастомизация внешнего вида меню.',
                ],
                'icon' => 'fa-palette',
                'sort_order' => 14,
            ],
            [
                'code' => 'multi_location',
                'name' => 'Multi-location Management',
                'name_translations' => [
                    'hy' => 'Բազմամասնաճյուղ Կառավարում',
                    'en' => 'Multi-location Management',
                    'ru' => 'Мультифилиальное управление',
                ],
                'description' => 'Multi-branch management and maximum locations capacity.',
                'description_translations' => [
                    'hy' => 'Մի քանի մասնաճյուղերի կառավարում և մասնաճյուղերի քանակի սահմանաչափ։',
                    'en' => 'Multi-branch management and maximum locations capacity.',
                    'ru' => 'Управление сетью заведений и лимит филиалов.',
                ],
                'icon' => 'fa-store',
                'sort_order' => 15,
            ],
        ];

        $map = [];
        foreach ($categories as $cat) {
            $map[$cat['code']] = FeatureCategory::updateOrCreate(
                ['code' => $cat['code']],
                array_merge($cat, ['is_active' => true])
            );
        }

        return $map;
    }

    /**
     * @param  array<string, FeatureCategory>  $categories
     * @return array<string, Feature>
     */
    protected function seedFeatures(array $categories): array
    {
        $definitions = [
            // 1. Menu & Products
            ['cat' => 'menu_products', 'code' => 'menu.qr', 'type' => Feature::TYPE_BOOLEAN, 'default' => true, 'hy' => 'QR Մենյու', 'en' => 'QR Menu', 'ru' => 'QR-меню'],
            ['cat' => 'menu_products', 'code' => 'menu.categories.limit', 'type' => Feature::TYPE_LIMIT, 'default' => -1, 'unit' => 'categories', 'hy' => 'Բաժինների քանակ', 'en' => 'Categories Limit', 'ru' => 'Лимит категорий'],
            ['cat' => 'menu_products', 'code' => 'menu.products.limit', 'type' => Feature::TYPE_LIMIT, 'default' => 100, 'unit' => 'products', 'hy' => 'Ապրանքների քանակ', 'en' => 'Products Limit', 'ru' => 'Лимит товаров'],
            ['cat' => 'menu_products', 'code' => 'menu.product_images', 'type' => Feature::TYPE_BOOLEAN, 'default' => true, 'hy' => 'Ապրանքի նկարներ', 'en' => 'Product Images', 'ru' => 'Фотографии товаров'],
            ['cat' => 'menu_products', 'code' => 'menu.videos', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Ապրանքի տեսանյութեր', 'en' => 'Product Videos', 'ru' => 'Видео товаров'],
            ['cat' => 'menu_products', 'code' => 'menu.modifiers', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Ապրանքի մոդիֆիկատորներ', 'en' => 'Product Modifiers', 'ru' => 'Модификаторы товаров'],
            ['cat' => 'menu_products', 'code' => 'menu.variants', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Ապրանքի տարբերակներ', 'en' => 'Product Variants', 'ru' => 'Варианты товаров'],
            ['cat' => 'menu_products', 'code' => 'menu.scheduling', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Մենյուի ժամանակացույց', 'en' => 'Menu Scheduling', 'ru' => 'Расписание меню'],

            // 2. Languages & Translation
            ['cat' => 'languages_translation', 'code' => 'languages.multilingual', 'type' => Feature::TYPE_BOOLEAN, 'default' => true, 'hy' => 'Բազմալեզու մենյու', 'en' => 'Multilingual Menu', 'ru' => 'Многоязычное меню'],
            ['cat' => 'languages_translation', 'code' => 'menu.languages.limit', 'type' => Feature::TYPE_LIMIT, 'default' => 2, 'unit' => 'languages', 'hy' => 'Առավելագույն լեզուներ', 'en' => 'Maximum Languages', 'ru' => 'Максимум языков'],
            ['cat' => 'languages_translation', 'code' => 'ai.translation', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'AI Թարգմանություն', 'en' => 'AI Translation', 'ru' => 'AI-перевод'],

            // 3. Currency & Pricing
            ['cat' => 'currency_pricing', 'code' => 'currency.primary', 'type' => Feature::TYPE_BOOLEAN, 'default' => true, 'hy' => 'Հիմնական արժույթ', 'en' => 'Primary Currency', 'ru' => 'Основная валюта'],
            ['cat' => 'currency_pricing', 'code' => 'currency.multi', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Բազմաարժույթ ցուցադրում', 'en' => 'Multiple Currency Display', 'ru' => 'Мультивалютное отображение'],
            ['cat' => 'currency_pricing', 'code' => 'currency.max_additional', 'type' => Feature::TYPE_LIMIT, 'default' => 0, 'unit' => 'currencies', 'hy' => 'Լրացուցիչ արժույթների քանակ', 'en' => 'Maximum Additional Currencies', 'ru' => 'Максимум доп. валют'],
            ['cat' => 'currency_pricing', 'code' => 'currency.auto_rate', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Ավտոմատ փոխարժեքներ', 'en' => 'Automatic Exchange Rates', 'ru' => 'Автоматический курс валют'],
            ['cat' => 'currency_pricing', 'code' => 'currency.manual_rate', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Ձեռքով փոխարժեքներ', 'en' => 'Manual Exchange Rates', 'ru' => 'Ручной курс валют'],

            // 4. Online Ordering
            ['cat' => 'online_ordering', 'code' => 'orders.online', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Օնլայն պատվերներ', 'en' => 'Online Ordering', 'ru' => 'Онлайн-заказы'],
            ['cat' => 'online_ordering', 'code' => 'orders.table_qr', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Սեղանի QR պատվերներ', 'en' => 'Table QR Ordering', 'ru' => 'Заказ по QR со стола'],
            ['cat' => 'online_ordering', 'code' => 'orders.management', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Պատվերների կառավարում', 'en' => 'Order Management', 'ru' => 'Управление заказами'],
            ['cat' => 'online_ordering', 'code' => 'orders.monthly_limit', 'type' => Feature::TYPE_USAGE, 'default' => 0, 'unit' => 'orders', 'reset' => 'monthly', 'hy' => 'Ամսական պատվերների սահմանաչափ', 'en' => 'Monthly Orders Limit', 'ru' => 'Месячный лимит заказов'],
            ['cat' => 'online_ordering', 'code' => 'orders.online_payments', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Օնլայն վճարումներ', 'en' => 'Online Payments', 'ru' => 'Онлайн-платежи'],
            ['cat' => 'online_ordering', 'code' => 'orders.cash_payments', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Կանխիկ վճարումներ', 'en' => 'Cash Payments', 'ru' => 'Оплата наличными'],
            ['cat' => 'online_ordering', 'code' => 'orders.notifications', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Պատվերի ծանուցումներ', 'en' => 'Order Notifications', 'ru' => 'Уведомления о заказах'],
            ['cat' => 'online_ordering', 'code' => 'orders.tracking', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Պատվերի կարգավիճակի հետևում', 'en' => 'Customer Order Tracking', 'ru' => 'Отслеживание заказа клиентом'],
            ['cat' => 'online_ordering', 'code' => 'orders.advanced_workflows', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Պատվերների ընդլայնված հոսքեր', 'en' => 'Advanced Order Workflows', 'ru' => 'Расширенные процессы заказов'],
            ['cat' => 'online_ordering', 'code' => 'orders.workflow_config', 'type' => Feature::TYPE_CONFIGURATION, 'default' => ['auto_accept' => false, 'prep_time_minutes' => 20], 'hy' => 'Պատվերների հոսքի կարգավորում', 'en' => 'Order Workflow Configuration', 'ru' => 'Конфигурация процесса заказов'],

            // 5. Delivery & Pickup
            ['cat' => 'delivery_pickup', 'code' => 'delivery.enabled', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Առաքում', 'en' => 'Delivery', 'ru' => 'Доставка'],
            ['cat' => 'delivery_pickup', 'code' => 'pickup.enabled', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Տեղից վերցնում (Pickup)', 'en' => 'Pickup', 'ru' => 'Самовывоз'],
            ['cat' => 'delivery_pickup', 'code' => 'delivery.zones.limit', 'type' => Feature::TYPE_LIMIT, 'default' => 0, 'unit' => 'zones', 'hy' => 'Առաքման գոտիներ', 'en' => 'Delivery Zones Limit', 'ru' => 'Лимит зон доставки'],
            ['cat' => 'delivery_pickup', 'code' => 'delivery.fee_config', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Առաքման վճարի կարգավորում', 'en' => 'Delivery Fee Configuration', 'ru' => 'Настройка стоимости доставки'],
            ['cat' => 'delivery_pickup', 'code' => 'delivery.min_order', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Նվազագույն պատվերի գումար', 'en' => 'Minimum Order Amount', 'ru' => 'Минимальная сумма заказа'],
            ['cat' => 'delivery_pickup', 'code' => 'delivery.scheduling', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Առաքման ժամանակացույց', 'en' => 'Delivery Scheduling', 'ru' => 'Расписание доставки'],
            ['cat' => 'delivery_pickup', 'code' => 'delivery.advanced_scheduling', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Ընդլայնված առաքման ժամանակացույց', 'en' => 'Advanced Delivery Scheduling', 'ru' => 'Расширенное расписание доставки'],

            // 6. Restaurant Management
            ['cat' => 'restaurant_management', 'code' => 'tables.management', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Սեղանների կառավարում', 'en' => 'Table Management', 'ru' => 'Управление столами'],
            ['cat' => 'restaurant_management', 'code' => 'reservations.enabled', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Ամրագրումներ', 'en' => 'Reservations', 'ru' => 'Бронирование столов'],
            ['cat' => 'restaurant_management', 'code' => 'preorders.enabled', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Նախնական պատվերներ', 'en' => 'Pre-orders', 'ru' => 'Предзаказы'],
            ['cat' => 'restaurant_management', 'code' => 'kds.enabled', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Խոհանոցային էկրան (KDS)', 'en' => 'KDS (Kitchen Display System)', 'ru' => 'Кухонный экран (KDS)'],

            // 7. POS & Operations
            ['cat' => 'pos_operations', 'code' => 'pos.enabled', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'POS Համակարգ', 'en' => 'POS Terminal', 'ru' => 'POS-система'],
            ['cat' => 'pos_operations', 'code' => 'pos.manager_mode', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Մենեջերի ռեժիմ', 'en' => 'Manager Mode', 'ru' => 'Режим менеджера'],
            ['cat' => 'pos_operations', 'code' => 'pos.cashier_mode', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Գանձապահի ռեժիմ', 'en' => 'Cashier Mode', 'ru' => 'Режим кассира'],
            ['cat' => 'pos_operations', 'code' => 'pos.waiter_mode', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Մատուցողի ռեժիմ', 'en' => 'Waiter Mode', 'ru' => 'Режим официанта'],

            // 8. Staff Management
            ['cat' => 'staff_management', 'code' => 'staff.users.limit', 'type' => Feature::TYPE_LIMIT, 'default' => 1, 'unit' => 'staff', 'hy' => 'Աշխատակիցների հաշիվներ', 'en' => 'Staff Accounts Limit', 'ru' => 'Лимит аккаунтов сотрудников'],
            ['cat' => 'staff_management', 'code' => 'team.manage', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Անձնակազմի դերեր և իրավունքներ', 'en' => 'Team & Role Management', 'ru' => 'Управление командой и ролями'],

            // 9. Marketing & Loyalty
            ['cat' => 'marketing_loyalty', 'code' => 'marketing.coupons', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Զեղչի կտրոններ և ակցիաներ', 'en' => 'Discount Coupons & Promotions', 'ru' => 'Купоны скидок и акции'],
            ['cat' => 'marketing_loyalty', 'code' => 'marketing.loyalty', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Հավատարմության ծրագիր', 'en' => 'Loyalty Program', 'ru' => 'Программа лояльности'],
            ['cat' => 'marketing_loyalty', 'code' => 'marketing.apple_wallet', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Apple Wallet քարտեր', 'en' => 'Apple Wallet Integration', 'ru' => 'Карты Apple Wallet'],
            ['cat' => 'marketing_loyalty', 'code' => 'marketing.customers', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Հաճախորդների բազա (CRM)', 'en' => 'Customer Database (CRM)', 'ru' => 'База клиентов (CRM)'],
            ['cat' => 'marketing_loyalty', 'code' => 'marketing.segmentation', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Հաճախորդների խորացված սեգմենտավորում', 'en' => 'Advanced Customer Segmentation', 'ru' => 'Расширенная сегментация клиентов'],

            // 10. Reviews & Reputation
            ['cat' => 'reviews_reputation', 'code' => 'reviews.generation', 'type' => Feature::TYPE_BOOLEAN, 'default' => true, 'hy' => 'Կարծիքների գեներացում', 'en' => 'Review Generation', 'ru' => 'Генерация отзывов'],
            ['cat' => 'reviews_reputation', 'code' => 'reviews.google', 'type' => Feature::TYPE_BOOLEAN, 'default' => true, 'hy' => 'Google Reviews ինտեգրում', 'en' => 'Google Reviews Integration', 'ru' => 'Интеграция с Google Reviews'],
            ['cat' => 'reviews_reputation', 'code' => 'reviews.tripadvisor', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Tripadvisor ինտեգրում', 'en' => 'Tripadvisor Integration', 'ru' => 'Интеграция с Tripadvisor'],
            ['cat' => 'reviews_reputation', 'code' => 'reviews.yandex', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Yandex ինտեգրում', 'en' => 'Yandex Integration', 'ru' => 'Интеграция с Яндекс'],

            // 11. AI Features
            ['cat' => 'ai_features', 'code' => 'ai.waiter', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'AI Մատուցող', 'en' => 'AI Waiter', 'ru' => 'AI-официант'],
            ['cat' => 'ai_features', 'code' => 'ai.recommendations', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'AI Առաջարկներ', 'en' => 'AI Recommendations', 'ru' => 'AI-рекомендации'],
            ['cat' => 'ai_features', 'code' => 'ai.assistant', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'AI Օգնական', 'en' => 'AI Assistant', 'ru' => 'AI-ассистент'],
            ['cat' => 'ai_features', 'code' => 'ai.monthly_credits', 'type' => Feature::TYPE_METERED, 'default' => 0, 'unit' => 'credits', 'reset' => 'monthly', 'hy' => 'AI կրեդիտներ (ամսական)', 'en' => 'AI Credits (Monthly)', 'ru' => 'AI-кредиты (в месяц)'],

            // 12. Analytics & Reports
            ['cat' => 'analytics_reports', 'code' => 'analytics.basic', 'type' => Feature::TYPE_BOOLEAN, 'default' => true, 'hy' => 'Հիմնական վերլուծություն', 'en' => 'Basic Analytics', 'ru' => 'Базовая аналитика'],
            ['cat' => 'analytics_reports', 'code' => 'analytics.advanced', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Ընդլայնված վերլուծություն', 'en' => 'Advanced Analytics', 'ru' => 'Расширенная аналитика'],
            ['cat' => 'analytics_reports', 'code' => 'analytics.export', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Excel / CSV արտահանում', 'en' => 'Excel / CSV Export', 'ru' => 'Экспорт в Excel / CSV'],
            ['cat' => 'analytics_reports', 'code' => 'analytics.full_reporting', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Ամբողջական հաշվետվություններ', 'en' => 'Full Reporting', 'ru' => 'Полная отчетность'],
            ['cat' => 'analytics_reports', 'code' => 'analytics.business', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Ընդլայնված բիզնես վերլուծություն', 'en' => 'Advanced Business Analytics', 'ru' => 'Расширенная бизнес-аналитика'],

            // 13. Integrations & API
            ['cat' => 'integrations_api', 'code' => 'integrations.api', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'API հասանելիություն', 'en' => 'API Access', 'ru' => 'Доступ к API'],
            ['cat' => 'integrations_api', 'code' => 'integrations.webhooks', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Webhooks', 'en' => 'Webhooks', 'ru' => 'Вебхуки'],
            ['cat' => 'integrations_api', 'code' => 'integrations.custom_domain', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Սեփական դոմեն', 'en' => 'Custom Domain', 'ru' => 'Собственный домен'],

            // 14. Branding & Customization
            ['cat' => 'branding_customization', 'code' => 'branding.white_label', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'White Label (առանց հարթակի լոգոյի)', 'en' => 'White Label', 'ru' => 'White Label'],
            ['cat' => 'branding_customization', 'code' => 'branding.theme_preset', 'type' => Feature::TYPE_ENUM, 'default' => 'modern', 'options' => ['classic', 'modern', 'minimal', 'luxury'], 'hy' => 'Թեմայի ոճ', 'en' => 'Theme Preset', 'ru' => 'Стиль темы'],

            // 15. Multi-location Management
            ['cat' => 'multi_location', 'code' => 'locations.multi', 'type' => Feature::TYPE_BOOLEAN, 'default' => false, 'hy' => 'Բազմամասնաճյուղ կառավարում', 'en' => 'Multi-location Management', 'ru' => 'Мультифилиальное управление'],
            ['cat' => 'multi_location', 'code' => 'locations.limit', 'type' => Feature::TYPE_LIMIT, 'default' => 1, 'unit' => 'locations', 'hy' => 'Առավելագույն մասնաճյուղեր', 'en' => 'Maximum Locations', 'ru' => 'Максимум филиалов'],
        ];

        $map = [];
        $sort = 1;
        foreach ($definitions as $def) {
            $category = $categories[$def['cat']] ?? null;
            $feature = Feature::updateOrCreate(
                ['code' => $def['code']],
                [
                    'category_id' => $category?->id,
                    'name' => $def['en'],
                    'name_translations' => [
                        'hy' => $def['hy'],
                        'en' => $def['en'],
                        'ru' => $def['ru'],
                    ],
                    'description' => $def['en'],
                    'description_translations' => [
                        'hy' => $def['hy'],
                        'en' => $def['en'],
                        'ru' => $def['ru'],
                    ],
                    'feature_type' => $def['type'],
                    'default_value' => ['value' => $def['default']],
                    'allowed_options' => $def['options'] ?? null,
                    'unit' => $def['unit'] ?? null,
                    'reset_period' => $def['reset'] ?? 'none',
                    'is_active' => true,
                    'is_archived' => false,
                    'admin_visible' => true,
                    'vendor_visible' => true,
                    'sort_order' => $sort++,
                ]
            );
            $map[$def['code']] = $feature;
        }

        return $map;
    }

    /**
     * @param  array<string, Feature>  $features
     */
    protected function seedDependencies(array $features): void
    {
        $pairs = [
            ['feature' => 'orders.online', 'depends_on' => 'orders.management'],
            ['feature' => 'delivery.enabled', 'depends_on' => 'orders.online'],
            ['feature' => 'pickup.enabled', 'depends_on' => 'orders.online'],
            ['feature' => 'orders.online_payments', 'depends_on' => 'orders.online'],
            ['feature' => 'ai.waiter', 'depends_on' => 'ai.recommendations'],
            ['feature' => 'marketing.apple_wallet', 'depends_on' => 'marketing.loyalty'],
            ['feature' => 'analytics.advanced', 'depends_on' => 'analytics.basic'],
            ['feature' => 'branding.white_label', 'depends_on' => 'integrations.custom_domain'],
            ['feature' => 'locations.limit', 'depends_on' => 'locations.multi'],
        ];

        foreach ($pairs as $pair) {
            $child = $features[$pair['feature']] ?? null;
            $parent = $features[$pair['depends_on']] ?? null;
            if ($child && $parent) {
                FeatureDependency::updateOrCreate(
                    [
                        'feature_id' => $child->id,
                        'depends_on_feature_id' => $parent->id,
                    ],
                    [
                        'feature_code' => $child->code,
                        'depends_on_code' => $parent->code,
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    /**
     * @param  array<string, Feature>  $features
     */
    protected function seedAddons(array $features): void
    {
        $addons = [
            [
                'code' => 'addon.ai_credits_1000',
                'name' => 'Additional AI Credits (+1,000)',
                'name_translations' => ['hy' => 'Լրացուցիչ AI Կրեդիտներ (+1,000)', 'en' => 'Additional AI Credits (+1,000)', 'ru' => 'Дополнительные AI-кредиты (+1,000)'],
                'description' => 'Adds 1,000 monthly AI credits for AI Waiter and translations.',
                'description_translations' => ['hy' => 'Ավելացնում է 1,000 ամսական AI կրեդիտ։', 'en' => 'Adds 1,000 monthly AI credits for AI Waiter and translations.', 'ru' => 'Добавляет 1,000 ежемесячных AI-кредитов.'],
                'addon_type' => SubscriptionAddon::TYPE_USAGE_PACK,
                'billing_type' => 'monthly',
                'feature_code' => 'ai.monthly_credits',
                'quantity_value' => 1000,
                'compatible_plan_slugs' => ['basic', 'pro', 'advanced'],
                'sort_order' => 1,
            ],
            [
                'code' => 'addon.staff_accounts_5',
                'name' => 'Additional Staff Accounts (+5)',
                'name_translations' => ['hy' => 'Լրացուցիչ Աշխատակիցների Հաշիվներ (+5)', 'en' => 'Additional Staff Accounts (+5)', 'ru' => 'Дополнительные аккаунты сотрудников (+5)'],
                'description' => 'Increases staff accounts limit by 5.',
                'description_translations' => ['hy' => 'Ավելացնում է աշխատակիցների հաշիվների սահմանաչափը 5-ով։', 'en' => 'Increases staff accounts limit by 5.', 'ru' => 'Увеличивает лимит сотрудников на 5.'],
                'addon_type' => SubscriptionAddon::TYPE_LIMIT_BOOST,
                'billing_type' => 'monthly',
                'feature_code' => 'staff.users.limit',
                'quantity_value' => 5,
                'compatible_plan_slugs' => ['basic', 'pro'],
                'sort_order' => 2,
            ],
            [
                'code' => 'addon.locations_1',
                'name' => 'Additional Location (+1)',
                'name_translations' => ['hy' => 'Լրացուցիչ Մասնաճյուղ (+1)', 'en' => 'Additional Location (+1)', 'ru' => 'Дополнительный филиал (+1)'],
                'description' => 'Adds 1 additional restaurant branch/location.',
                'description_translations' => ['hy' => 'Ավելացնում է 1 լրացուցիչ մասնաճյուղ։', 'en' => 'Adds 1 additional restaurant branch/location.', 'ru' => 'Добавляет 1 дополнительный филиал.'],
                'addon_type' => SubscriptionAddon::TYPE_LIMIT_BOOST,
                'billing_type' => 'monthly',
                'feature_code' => 'locations.limit',
                'quantity_value' => 1,
                'compatible_plan_slugs' => ['basic', 'pro', 'advanced'],
                'sort_order' => 3,
            ],
            [
                'code' => 'addon.online_ordering',
                'name' => 'Online Ordering Add-on',
                'name_translations' => ['hy' => 'Օնլայն Պատվերների մոդուլ', 'en' => 'Online Ordering Add-on', 'ru' => 'Модуль онлайн-заказов'],
                'description' => 'Unlocks online ordering and order management.',
                'description_translations' => ['hy' => 'Ակտիվացնում է օնլայն պատվերների և կառավարման ֆունկցիոնալությունը։', 'en' => 'Unlocks online ordering and order management.', 'ru' => 'Открывает функции онлайн-заказов.'],
                'addon_type' => SubscriptionAddon::TYPE_FEATURE,
                'billing_type' => 'monthly',
                'feature_code' => 'orders.online',
                'quantity_value' => 1,
                'compatible_plan_slugs' => ['basic'],
                'sort_order' => 4,
            ],
            [
                'code' => 'addon.languages_3',
                'name' => 'Additional Languages (+3)',
                'name_translations' => ['hy' => 'Լրացուցիչ Լեզուներ (+3)', 'en' => 'Additional Languages (+3)', 'ru' => 'Дополнительные языки (+3)'],
                'description' => 'Adds 3 additional menu languages.',
                'description_translations' => ['hy' => 'Ավելացնում է 3 լրացուցիչ մենյուի լեզու։', 'en' => 'Adds 3 additional menu languages.', 'ru' => 'Добавляет 3 дополнительных языка меню.'],
                'addon_type' => SubscriptionAddon::TYPE_LIMIT_BOOST,
                'billing_type' => 'monthly',
                'feature_code' => 'menu.languages.limit',
                'quantity_value' => 3,
                'compatible_plan_slugs' => ['basic', 'pro'],
                'sort_order' => 5,
            ],
            [
                'code' => 'addon.advanced_analytics',
                'name' => 'Advanced Analytics Add-on',
                'name_translations' => ['hy' => 'Ընդլայնված Վերլուծության մոդուլ', 'en' => 'Advanced Analytics Add-on', 'ru' => 'Модуль расширенной аналитики'],
                'description' => 'Unlocks advanced analytics and data export.',
                'description_translations' => ['hy' => 'Ակտիվացնում է ընդլայնված վերլուծությունը։', 'en' => 'Unlocks advanced analytics and data export.', 'ru' => 'Открывает расширенную аналитику.'],
                'addon_type' => SubscriptionAddon::TYPE_FEATURE,
                'billing_type' => 'monthly',
                'feature_code' => 'analytics.advanced',
                'quantity_value' => 1,
                'compatible_plan_slugs' => ['basic'],
                'sort_order' => 6,
            ],
            [
                'code' => 'addon.custom_domain',
                'name' => 'Custom Domain Add-on',
                'name_translations' => ['hy' => 'Սեփական Դոմենի մոդուլ', 'en' => 'Custom Domain Add-on', 'ru' => 'Модуль собственного домена'],
                'description' => 'Allows connecting a custom domain to the digital menu.',
                'description_translations' => ['hy' => 'Թույլ է տալիս կցել սեփական դոմեն։', 'en' => 'Allows connecting a custom domain to the digital menu.', 'ru' => 'Позволяет подключить собственный домен.'],
                'addon_type' => SubscriptionAddon::TYPE_FEATURE,
                'billing_type' => 'monthly',
                'feature_code' => 'integrations.custom_domain',
                'quantity_value' => 1,
                'compatible_plan_slugs' => ['basic', 'pro'],
                'sort_order' => 7,
            ],
            [
                'code' => 'addon.delivery_zones_5',
                'name' => 'Additional Delivery Zones (+5)',
                'name_translations' => ['hy' => 'Լրացուցիչ Առաքման Գոտիներ (+5)', 'en' => 'Additional Delivery Zones (+5)', 'ru' => 'Дополнительные зоны доставки (+5)'],
                'description' => 'Increases delivery zones limit by 5.',
                'description_translations' => ['hy' => 'Ավելացնում է առաքման գոտիների քանակը 5-ով։', 'en' => 'Increases delivery zones limit by 5.', 'ru' => 'Увеличивает лимит зон доставки на 5.'],
                'addon_type' => SubscriptionAddon::TYPE_LIMIT_BOOST,
                'billing_type' => 'monthly',
                'feature_code' => 'delivery.zones.limit',
                'quantity_value' => 5,
                'compatible_plan_slugs' => ['pro'],
                'sort_order' => 8,
            ],
        ];

        foreach ($addons as $addonData) {
            $feature = isset($addonData['feature_code']) ? ($features[$addonData['feature_code']] ?? null) : null;
            SubscriptionAddon::updateOrCreate(
                ['code' => $addonData['code']],
                array_merge($addonData, [
                    'feature_id' => $feature?->id,
                    'monthly_price' => 0,
                    'yearly_price' => 0,
                    'one_time_price' => 0,
                    'currency' => 'AMD',
                    'is_active' => true,
                    'is_archived' => false,
                ])
            );
        }
    }

    /**
     * @param  array<string, mixed>  $matrix
     * @param  array<string, Feature>  $features
     */
    protected function seedPlanVersionAndFeatures(SubscriptionPlan $plan, array $matrix, array $features): void
    {
        $featuresSnapshot = [];
        $limitsSnapshot = [];

        foreach ($features as $code => $feature) {
            $cfg = $matrix[$code] ?? ['enabled' => false, 'limit' => null, 'quota' => null, 'config' => null];
            $featuresSnapshot[$code] = [
                'is_enabled' => (bool) ($cfg['enabled'] ?? false),
                'limit_value' => $cfg['limit'] ?? null,
                'usage_quota' => $cfg['quota'] ?? null,
                'config_value' => $cfg['config'] ?? null,
            ];
            if (($cfg['limit'] ?? null) !== null) {
                $limitsSnapshot[$code] = (int) $cfg['limit'];
            } elseif (($cfg['quota'] ?? null) !== null) {
                $limitsSnapshot[$code] = (int) $cfg['quota'];
            }
        }

        $version = SubscriptionPlanVersion::updateOrCreate(
            [
                'subscription_plan_id' => $plan->id,
                'version_number' => 1,
            ],
            [
                'name' => $plan->name,
                'name_translations' => $plan->name_translations,
                'description' => $plan->description,
                'description_translations' => $plan->description_translations,
                'monthly_price' => $plan->price,
                'yearly_price' => $plan->yearly_price,
                'currency' => 'AMD',
                'trial_days' => $plan->trial_days ?? 14,
                'features_snapshot' => $featuresSnapshot,
                'limits_snapshot' => $limitsSnapshot,
                'change_summary' => 'Initial system configuration (v1)',
                'is_current' => true,
            ]
        );

        $plan->updateQuietly([
            'current_version_id' => $version->id,
            'current_version_number' => 1,
        ]);

        $sortOrder = 1;
        foreach ($features as $code => $feature) {
            $cfg = $matrix[$code] ?? ['enabled' => false, 'limit' => null, 'quota' => null, 'config' => null];
            PlanFeature::updateOrCreate(
                [
                    'subscription_plan_id' => $plan->id,
                    'plan_version_id' => $version->id,
                    'feature_id' => $feature->id,
                ],
                [
                    'feature_code' => $code,
                    'is_enabled' => (bool) ($cfg['enabled'] ?? false),
                    'limit_value' => $cfg['limit'] ?? null,
                    'usage_quota' => $cfg['quota'] ?? null,
                    'config_value' => $cfg['config'] ?? null,
                    'sort_order' => $sortOrder++,
                ]
            );
        }
    }

    /**
     * Initial 3 plans: Basic, Pro, Advanced.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function initialPlansDefinition(): array
    {
        $basicMatrix = [
            // Menu Management
            'menu.qr' => ['enabled' => true],
            'menu.categories.limit' => ['enabled' => true, 'limit' => -1],
            'menu.products.limit' => ['enabled' => true, 'limit' => 100],
            'menu.product_images' => ['enabled' => true],
            'menu.videos' => ['enabled' => false],
            'menu.modifiers' => ['enabled' => false],
            'menu.variants' => ['enabled' => false],
            'menu.scheduling' => ['enabled' => false],
            // Languages
            'languages.multilingual' => ['enabled' => true],
            'menu.languages.limit' => ['enabled' => true, 'limit' => 2],
            'ai.translation' => ['enabled' => false],
            // Currency
            'currency.primary' => ['enabled' => true],
            'currency.multi' => ['enabled' => false],
            'currency.max_additional' => ['enabled' => false, 'limit' => 0],
            'currency.auto_rate' => ['enabled' => false],
            'currency.manual_rate' => ['enabled' => false],
            // Ordering
            'orders.online' => ['enabled' => false],
            'orders.table_qr' => ['enabled' => false],
            'orders.management' => ['enabled' => false],
            'orders.monthly_limit' => ['enabled' => false, 'quota' => 0],
            'orders.online_payments' => ['enabled' => false],
            'orders.cash_payments' => ['enabled' => false],
            'orders.notifications' => ['enabled' => false],
            'orders.tracking' => ['enabled' => false],
            'orders.advanced_workflows' => ['enabled' => false],
            'orders.workflow_config' => ['enabled' => false, 'config' => ['auto_accept' => false, 'prep_time_minutes' => 20]],
            // Delivery
            'delivery.enabled' => ['enabled' => false],
            'pickup.enabled' => ['enabled' => false],
            'delivery.zones.limit' => ['enabled' => false, 'limit' => 0],
            'delivery.fee_config' => ['enabled' => false],
            'delivery.min_order' => ['enabled' => false],
            'delivery.scheduling' => ['enabled' => false],
            'delivery.advanced_scheduling' => ['enabled' => false],
            // Restaurant Management
            'tables.management' => ['enabled' => false],
            'reservations.enabled' => ['enabled' => false],
            'preorders.enabled' => ['enabled' => false],
            'kds.enabled' => ['enabled' => false],
            // POS
            'pos.enabled' => ['enabled' => false],
            'pos.manager_mode' => ['enabled' => false],
            'pos.cashier_mode' => ['enabled' => false],
            'pos.waiter_mode' => ['enabled' => false],
            // Staff
            'staff.users.limit' => ['enabled' => true, 'limit' => 1],
            'team.manage' => ['enabled' => false],
            // Marketing
            'marketing.coupons' => ['enabled' => false],
            'marketing.loyalty' => ['enabled' => false],
            'marketing.apple_wallet' => ['enabled' => false],
            'marketing.customers' => ['enabled' => false],
            'marketing.segmentation' => ['enabled' => false],
            // Reviews
            'reviews.generation' => ['enabled' => true],
            'reviews.google' => ['enabled' => true],
            'reviews.tripadvisor' => ['enabled' => false],
            'reviews.yandex' => ['enabled' => false],
            // Analytics
            'analytics.basic' => ['enabled' => true],
            'analytics.advanced' => ['enabled' => false],
            'analytics.export' => ['enabled' => false],
            'analytics.full_reporting' => ['enabled' => false],
            'analytics.business' => ['enabled' => false],
            // AI
            'ai.waiter' => ['enabled' => false],
            'ai.recommendations' => ['enabled' => false],
            'ai.assistant' => ['enabled' => false],
            'ai.monthly_credits' => ['enabled' => false, 'quota' => 0],
            // Integrations
            'integrations.api' => ['enabled' => false],
            'integrations.webhooks' => ['enabled' => false],
            'integrations.custom_domain' => ['enabled' => false],
            // Branding
            'branding.white_label' => ['enabled' => false],
            'branding.theme_preset' => ['enabled' => true, 'config' => ['value' => 'modern']],
            // Multi-location
            'locations.multi' => ['enabled' => false],
            'locations.limit' => ['enabled' => true, 'limit' => 1],
        ];

        $proMatrix = [
            // Menu Management
            'menu.qr' => ['enabled' => true],
            'menu.categories.limit' => ['enabled' => true, 'limit' => -1],
            'menu.products.limit' => ['enabled' => true, 'limit' => 1000],
            'menu.product_images' => ['enabled' => true],
            'menu.videos' => ['enabled' => true],
            'menu.modifiers' => ['enabled' => true],
            'menu.variants' => ['enabled' => true],
            'menu.scheduling' => ['enabled' => true],
            // Languages
            'languages.multilingual' => ['enabled' => true],
            'menu.languages.limit' => ['enabled' => true, 'limit' => 5],
            'ai.translation' => ['enabled' => true],
            // Currency
            'currency.primary' => ['enabled' => true],
            'currency.multi' => ['enabled' => true],
            'currency.max_additional' => ['enabled' => true, 'limit' => 3],
            'currency.auto_rate' => ['enabled' => true],
            'currency.manual_rate' => ['enabled' => true],
            // Ordering
            'orders.online' => ['enabled' => true],
            'orders.table_qr' => ['enabled' => true],
            'orders.management' => ['enabled' => true],
            'orders.monthly_limit' => ['enabled' => true, 'quota' => -1],
            'orders.online_payments' => ['enabled' => true],
            'orders.cash_payments' => ['enabled' => true],
            'orders.notifications' => ['enabled' => true],
            'orders.tracking' => ['enabled' => true],
            'orders.advanced_workflows' => ['enabled' => false],
            'orders.workflow_config' => ['enabled' => true, 'config' => ['auto_accept' => false, 'prep_time_minutes' => 20]],
            // Delivery
            'delivery.enabled' => ['enabled' => true],
            'pickup.enabled' => ['enabled' => true],
            'delivery.zones.limit' => ['enabled' => true, 'limit' => 5],
            'delivery.fee_config' => ['enabled' => true],
            'delivery.min_order' => ['enabled' => true],
            'delivery.scheduling' => ['enabled' => true],
            'delivery.advanced_scheduling' => ['enabled' => false],
            // Restaurant Management
            'tables.management' => ['enabled' => true],
            'reservations.enabled' => ['enabled' => true],
            'preorders.enabled' => ['enabled' => true],
            'kds.enabled' => ['enabled' => true],
            // POS
            'pos.enabled' => ['enabled' => false],
            'pos.manager_mode' => ['enabled' => false],
            'pos.cashier_mode' => ['enabled' => false],
            'pos.waiter_mode' => ['enabled' => false],
            // Staff
            'staff.users.limit' => ['enabled' => true, 'limit' => 10],
            'team.manage' => ['enabled' => false],
            // Marketing
            'marketing.coupons' => ['enabled' => true],
            'marketing.loyalty' => ['enabled' => true],
            'marketing.apple_wallet' => ['enabled' => true],
            'marketing.customers' => ['enabled' => true],
            'marketing.segmentation' => ['enabled' => false],
            // Reviews
            'reviews.generation' => ['enabled' => true],
            'reviews.google' => ['enabled' => true],
            'reviews.tripadvisor' => ['enabled' => true],
            'reviews.yandex' => ['enabled' => true],
            // Analytics
            'analytics.basic' => ['enabled' => true],
            'analytics.advanced' => ['enabled' => true],
            'analytics.export' => ['enabled' => true],
            'analytics.full_reporting' => ['enabled' => false],
            'analytics.business' => ['enabled' => false],
            // AI
            'ai.waiter' => ['enabled' => true],
            'ai.recommendations' => ['enabled' => true],
            'ai.assistant' => ['enabled' => false],
            'ai.monthly_credits' => ['enabled' => true, 'quota' => 1000],
            // Integrations
            'integrations.api' => ['enabled' => false],
            'integrations.webhooks' => ['enabled' => false],
            'integrations.custom_domain' => ['enabled' => false],
            // Branding
            'branding.white_label' => ['enabled' => false],
            'branding.theme_preset' => ['enabled' => true, 'config' => ['value' => 'modern']],
            // Multi-location
            'locations.multi' => ['enabled' => false],
            'locations.limit' => ['enabled' => true, 'limit' => 1],
        ];

        $advancedMatrix = [
            // Menu Management
            'menu.qr' => ['enabled' => true],
            'menu.categories.limit' => ['enabled' => true, 'limit' => -1],
            'menu.products.limit' => ['enabled' => true, 'limit' => -1],
            'menu.product_images' => ['enabled' => true],
            'menu.videos' => ['enabled' => true],
            'menu.modifiers' => ['enabled' => true],
            'menu.variants' => ['enabled' => true],
            'menu.scheduling' => ['enabled' => true],
            // Languages
            'languages.multilingual' => ['enabled' => true],
            'menu.languages.limit' => ['enabled' => true, 'limit' => -1],
            'ai.translation' => ['enabled' => true],
            // Currency
            'currency.primary' => ['enabled' => true],
            'currency.multi' => ['enabled' => true],
            'currency.max_additional' => ['enabled' => true, 'limit' => -1],
            'currency.auto_rate' => ['enabled' => true],
            'currency.manual_rate' => ['enabled' => true],
            // Ordering
            'orders.online' => ['enabled' => true],
            'orders.table_qr' => ['enabled' => true],
            'orders.management' => ['enabled' => true],
            'orders.monthly_limit' => ['enabled' => true, 'quota' => -1],
            'orders.online_payments' => ['enabled' => true],
            'orders.cash_payments' => ['enabled' => true],
            'orders.notifications' => ['enabled' => true],
            'orders.tracking' => ['enabled' => true],
            'orders.advanced_workflows' => ['enabled' => true],
            'orders.workflow_config' => ['enabled' => true, 'config' => ['auto_accept' => true, 'prep_time_minutes' => 15]],
            // Delivery
            'delivery.enabled' => ['enabled' => true],
            'pickup.enabled' => ['enabled' => true],
            'delivery.zones.limit' => ['enabled' => true, 'limit' => -1],
            'delivery.fee_config' => ['enabled' => true],
            'delivery.min_order' => ['enabled' => true],
            'delivery.scheduling' => ['enabled' => true],
            'delivery.advanced_scheduling' => ['enabled' => true],
            // Restaurant Management
            'tables.management' => ['enabled' => true],
            'reservations.enabled' => ['enabled' => true],
            'preorders.enabled' => ['enabled' => true],
            'kds.enabled' => ['enabled' => true],
            // POS
            'pos.enabled' => ['enabled' => true],
            'pos.manager_mode' => ['enabled' => true],
            'pos.cashier_mode' => ['enabled' => true],
            'pos.waiter_mode' => ['enabled' => true],
            // Staff
            'staff.users.limit' => ['enabled' => true, 'limit' => -1],
            'team.manage' => ['enabled' => true],
            // Marketing
            'marketing.coupons' => ['enabled' => true],
            'marketing.loyalty' => ['enabled' => true],
            'marketing.apple_wallet' => ['enabled' => true],
            'marketing.customers' => ['enabled' => true],
            'marketing.segmentation' => ['enabled' => true],
            // Reviews
            'reviews.generation' => ['enabled' => true],
            'reviews.google' => ['enabled' => true],
            'reviews.tripadvisor' => ['enabled' => true],
            'reviews.yandex' => ['enabled' => true],
            // Analytics
            'analytics.basic' => ['enabled' => true],
            'analytics.advanced' => ['enabled' => true],
            'analytics.export' => ['enabled' => true],
            'analytics.full_reporting' => ['enabled' => true],
            'analytics.business' => ['enabled' => true],
            // AI
            'ai.waiter' => ['enabled' => true],
            'ai.recommendations' => ['enabled' => true],
            'ai.assistant' => ['enabled' => true],
            'ai.monthly_credits' => ['enabled' => true, 'quota' => 10000],
            // Integrations
            'integrations.api' => ['enabled' => true],
            'integrations.webhooks' => ['enabled' => true],
            'integrations.custom_domain' => ['enabled' => true],
            // Branding
            'branding.white_label' => ['enabled' => true],
            'branding.theme_preset' => ['enabled' => true, 'config' => ['value' => 'luxury']],
            // Multi-location
            'locations.multi' => ['enabled' => true],
            'locations.limit' => ['enabled' => true, 'limit' => 10],
        ];

        return [
            [
                'name' => 'Basic',
                'name_translations' => ['hy' => 'Basic', 'en' => 'Basic', 'ru' => 'Basic'],
                'slug' => 'basic',
                'price' => 9900,
                'yearly_price' => 95040,
                'currency' => 'AMD',
                'billing_interval' => 'monthly',
                'duration_days' => 30,
                'trial_days' => 14,
                'description' => 'Թվային մենյու փոքր ռեստորանների և սրճարանների համար',
                'description_translations' => [
                    'hy' => 'Թվային մենյու փոքր ռեստորանների և սրճարանների համար',
                    'en' => 'Digital menu for small restaurants and businesses',
                    'ru' => 'Цифровое меню для небольших ресторанов и кафе',
                ],
                'features' => [
                    'QR մենյու (Անսահմանափակ բաժիններ, մինչև 100 ապրանք)',
                    'Բազմալեզու մենյու (մինչև 2 լեզու)',
                    'Կարծիքների գեներացում և Google Reviews',
                    'Հիմնական վիճակագրություն',
                ],
                'feature_codes' => FeatureCatalog::getDefaultFeaturesForPlan('basic'),
                'quota_limits' => [
                    QuotaCatalog::KEY_PRODUCTS_COUNT => 100,
                    QuotaCatalog::KEY_CATEGORIES_COUNT => QuotaCatalog::UNLIMITED,
                    QuotaCatalog::KEY_BRANCHES_COUNT => 1,
                    QuotaCatalog::KEY_TEAM_MEMBERS_COUNT => 1,
                    QuotaCatalog::KEY_STORAGE_BYTES => 104857600,
                    QuotaCatalog::KEY_ORDERS_PERIOD => 0,
                    QuotaCatalog::KEY_RESERVATIONS_PERIOD => 0,
                    QuotaCatalog::KEY_AI_REQUESTS_PERIOD => 0,
                    QuotaCatalog::KEY_API_REQUESTS_PERIOD => 0,
                    QuotaCatalog::KEY_LANGUAGES_COUNT => 2,
                    QuotaCatalog::KEY_DELIVERY_ZONES_COUNT => 0,
                    QuotaCatalog::KEY_CURRENCIES_COUNT => 0,
                ],
                'plan_matrix' => $basicMatrix,
                'sort_order' => 1,
            ],
            [
                'name' => 'Pro',
                'name_translations' => ['hy' => 'Pro', 'en' => 'Pro', 'ru' => 'Pro'],
                'slug' => 'pro',
                'price' => 19900,
                'yearly_price' => 191040,
                'currency' => 'AMD',
                'billing_interval' => 'monthly',
                'duration_days' => 30,
                'trial_days' => 14,
                'description' => 'Աճող ռեստորանների համար՝ օնլայն պատվերներով և հաճախորդների ներգրավմամբ',
                'description_translations' => [
                    'hy' => 'Աճող ռեստորանների համար՝ օնլայն պատվերներով և հաճախորդների ներգրավմամբ',
                    'en' => 'For growing restaurants that need online ordering and customer engagement',
                    'ru' => 'Для растущих ресторанов с онлайн-заказами и вовлечением гостей',
                ],
                'features' => [
                    'Մինչև 1,000 ապրանք, տեսանյութեր, մոդիֆիկատորներ և տարբերակներ',
                    'Մինչև 5 լեզու + AI Թարգմանություն',
                    'Օնլայն պատվերներ, Առաքում (5 գոտի) և Pickup',
                    'Սեղանների ամրագրում, Նախնական պատվերներ և KDS',
                    'AI Մատուցող (1,000 կրեդիտ/ամիս), CRM, Լոյալություն և Apple Wallet',
                ],
                'feature_codes' => FeatureCatalog::getDefaultFeaturesForPlan('pro'),
                'quota_limits' => [
                    QuotaCatalog::KEY_PRODUCTS_COUNT => 1000,
                    QuotaCatalog::KEY_CATEGORIES_COUNT => QuotaCatalog::UNLIMITED,
                    QuotaCatalog::KEY_BRANCHES_COUNT => 1,
                    QuotaCatalog::KEY_TEAM_MEMBERS_COUNT => 10,
                    QuotaCatalog::KEY_STORAGE_BYTES => 524288000,
                    QuotaCatalog::KEY_ORDERS_PERIOD => QuotaCatalog::UNLIMITED,
                    QuotaCatalog::KEY_RESERVATIONS_PERIOD => QuotaCatalog::UNLIMITED,
                    QuotaCatalog::KEY_AI_REQUESTS_PERIOD => 1000,
                    QuotaCatalog::KEY_API_REQUESTS_PERIOD => 0,
                    QuotaCatalog::KEY_LANGUAGES_COUNT => 5,
                    QuotaCatalog::KEY_DELIVERY_ZONES_COUNT => 5,
                    QuotaCatalog::KEY_CURRENCIES_COUNT => 3,
                ],
                'plan_matrix' => $proMatrix,
                'sort_order' => 2,
            ],
            [
                'name' => 'Advanced',
                'name_translations' => ['hy' => 'Advanced', 'en' => 'Advanced', 'ru' => 'Advanced'],
                'slug' => 'advanced',
                'price' => 39900,
                'yearly_price' => 383040,
                'currency' => 'AMD',
                'billing_interval' => 'monthly',
                'duration_days' => 30,
                'trial_days' => 14,
                'description' => 'Մեծ ռեստորանների և ցանցերի համար՝ POS, բազմամասնաճյուղ կառավարում և API',
                'description_translations' => [
                    'hy' => 'Մեծ ռեստորանների և ցանցերի համար՝ POS, բազմամասնաճյուղ կառավարում և API',
                    'en' => 'For large restaurants and restaurant groups requiring advanced operational tools',
                    'ru' => 'Для крупных ресторанов и сетей с POS, филиалами и API',
                ],
                'features' => [
                    'Անսահմանափակ ապրանքներ, բաժիններ, լեզուներ և արժույթներ',
                    'POS Համակարգ (Մենեջեր, Գանձապահ, Մատուցող) և անսահմանափակ անձնակազմ',
                    'Բազմամասնաճյուղ կառավարում (մինչև 10 մասնաճյուղ)',
                    'AI Օգնական և 10,000 AI կրեդիտ / ամիս',
                    'API, Webhooks, Սեփական դոմեն և White Label',
                ],
                'feature_codes' => FeatureCatalog::getDefaultFeaturesForPlan('advanced'),
                'quota_limits' => [
                    QuotaCatalog::KEY_PRODUCTS_COUNT => QuotaCatalog::UNLIMITED,
                    QuotaCatalog::KEY_CATEGORIES_COUNT => QuotaCatalog::UNLIMITED,
                    QuotaCatalog::KEY_BRANCHES_COUNT => 10,
                    QuotaCatalog::KEY_TEAM_MEMBERS_COUNT => QuotaCatalog::UNLIMITED,
                    QuotaCatalog::KEY_STORAGE_BYTES => 2147483648,
                    QuotaCatalog::KEY_ORDERS_PERIOD => QuotaCatalog::UNLIMITED,
                    QuotaCatalog::KEY_RESERVATIONS_PERIOD => QuotaCatalog::UNLIMITED,
                    QuotaCatalog::KEY_AI_REQUESTS_PERIOD => 10000,
                    QuotaCatalog::KEY_API_REQUESTS_PERIOD => QuotaCatalog::UNLIMITED,
                    QuotaCatalog::KEY_LANGUAGES_COUNT => QuotaCatalog::UNLIMITED,
                    QuotaCatalog::KEY_DELIVERY_ZONES_COUNT => QuotaCatalog::UNLIMITED,
                    QuotaCatalog::KEY_CURRENCIES_COUNT => QuotaCatalog::UNLIMITED,
                ],
                'plan_matrix' => $advancedMatrix,
                'sort_order' => 3,
            ],
        ];
    }
}
