<?php

namespace Database\Seeders;

use App\Models\Allergen;
use App\Models\AnalyticsLog;
use App\Models\Category;
use App\Models\Location;
use App\Models\MenuTemplate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Subscription Plans & Email Management Setup
        $this->call(SubscriptionPlanSeeder::class);
        $this->call(EmailManagementSeeder::class);
        $proPlan = SubscriptionPlan::where('slug', 'pro')->first();
        $businessPlan = SubscriptionPlan::where('slug', 'business')->first();

        // 1. Menu Templates
        $t1 = MenuTemplate::updateOrCreate(
            ['slug' => 'modern-bistro'],
            [
                'name' => 'Modern Bistro Grid',
                'preview_image' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=600&q=80',
                'description' => 'Sleek white & vibrant accent theme with mobile-optimized category navigation and crisp dish cards.',
                'default_config' => ['primary' => '#e11d48', 'bg' => '#0f172a', 'card' => '#1e293b'],
                'is_active' => true,
            ]
        );

        $t2 = MenuTemplate::updateOrCreate(
            ['slug' => 'luxury-dark'],
            [
                'name' => 'Luxury Dark & Gold',
                'preview_image' => 'https://images.unsplash.com/photo-1550966871-3ed3cdb5ed0c?w=600&q=80',
                'description' => 'Ultra-premium dark theme with gold typography, high-res dish galleries, and cocktail lounge aesthetics.',
                'default_config' => ['primary' => '#d97706', 'bg' => '#09090b', 'card' => '#18181b'],
                'is_active' => true,
            ]
        );

        $t3 = MenuTemplate::updateOrCreate(
            ['slug' => 'vibrant-glass'],
            [
                'name' => 'Vibrant Glassmorphic Cafe',
                'preview_image' => 'https://images.unsplash.com/photo-1554118811-1e0d58224f24?w=600&q=80',
                'description' => 'Trendy frosted glass effects with gradient highlights, designed for modern coffee shops and brunch spots.',
                'default_config' => ['primary' => '#06b6d4', 'bg' => '#0f172a', 'card' => 'rgba(30, 41, 59, 0.7)'],
                'is_active' => true,
            ]
        );

        $t4 = MenuTemplate::updateOrCreate(
            ['slug' => 'minimalist-light'],
            [
                'name' => 'Minimalist Scandinavian Light',
                'preview_image' => 'https://images.unsplash.com/photo-1559339352-11d035aa65de?w=600&q=80',
                'description' => 'Clean, airy minimalist aesthetics with soft light palettes, refined typography, and elegant spacious dish presentations.',
                'default_config' => ['primary' => '#0f172a', 'bg' => '#fbfbfa', 'card' => '#ffffff'],
                'is_active' => true,
            ]
        );

        // 2. EU Allergens
        $allergensData = [
            ['code' => 'gluten', 'name' => 'Gluten', 'hy' => 'Գլյուտեն', 'en' => 'Gluten', 'ru' => 'Глютен', 'icon' => '🌾'],
            ['code' => 'milk', 'name' => 'Milk / Lactose', 'hy' => 'Կաթ / Լակտոզ', 'en' => 'Milk / Lactose', 'ru' => 'Молоко / Лактоза', 'icon' => '🥛'],
            ['code' => 'nuts', 'name' => 'Tree Nuts', 'hy' => 'Ընկուզեղեն', 'en' => 'Tree Nuts', 'ru' => 'Орехи', 'icon' => '🥜'],
            ['code' => 'eggs', 'name' => 'Eggs', 'hy' => 'Ձու', 'en' => 'Eggs', 'ru' => 'Яйца', 'icon' => '🥚'],
            ['code' => 'fish', 'name' => 'Fish', 'hy' => 'Ձուկ', 'en' => 'Fish', 'ru' => 'Рыба', 'icon' => '🐟'],
            ['code' => 'crustaceans', 'name' => 'Crustaceans', 'hy' => 'Խեցգետնանմաններ', 'en' => 'Crustaceans', 'ru' => 'Ракообразные', 'icon' => '🦐'],
            ['code' => 'soybeans', 'name' => 'Soybeans', 'hy' => 'Սոյա', 'en' => 'Soybeans', 'ru' => 'Соя', 'icon' => '🫛'],
            ['code' => 'sesame', 'name' => 'Sesame Seeds', 'hy' => 'Քունջութ', 'en' => 'Sesame Seeds', 'ru' => 'Кунжут', 'icon' => '🥯'],
        ];

        $allergensMap = [];
        foreach ($allergensData as $a) {
            $created = Allergen::updateOrCreate(
                ['code' => $a['code']],
                [
                    'name' => $a['name'],
                    'name_translations' => ['hy' => $a['hy'], 'en' => $a['en'], 'ru' => $a['ru']],
                    'icon' => $a['icon'],
                ]
            );
            $allergensMap[$a['code']] = $created->id;
        }

        // 3. Super Admin User
        User::firstOrCreate(
            ['email' => 'admin@qrmenu.local'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('password'),
                'role' => 'superadmin',
                'phone' => '+37491000000',
            ]
        );

        // 4. Primary Vendor: Bistro Yerevan
        $vendor1 = Vendor::updateOrCreate(
            ['slug' => 'bistro-yerevan'],
            [
                'name' => 'Bistro Yerevan & Grill',
                'type' => 'restaurant',
                'logo' => 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=300&q=80',
                'cover_image' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=1200&q=80',
                'phone' => '+37410500100',
                'email' => 'info@bistroyerevan.am',
                'currency' => 'AMD',
                'menu_template_id' => $t2->id,
                'primary_color' => '#f59e0b',
                'secondary_color' => '#ef4444',
                'theme_mode' => 'dark',
                'subscription_plan' => 'pro',
                'subscription_plan_id' => $proPlan?->id,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        // Locations for Bistro Yerevan
        $loc1 = Location::updateOrCreate(
            ['vendor_id' => $vendor1->id, 'slug' => 'cascades'],
            [
                'name' => 'Cascades Main Branch',
                'address' => 'Tamanyan St 2, Yerevan',
                'phone' => '+37410500101',
                'whatsapp_number' => '37491500101',
                'opening_hours' => ['mon_fri' => '10:00 - 23:30', 'sat_sun' => '10:00 - 01:00'],
                'table_count' => 30,
                'allow_dine_in_orders' => true,
                'allow_whatsapp_orders' => true,
                'minimum_order_amount' => 3000,
                'is_active' => true,
            ]
        );

        $loc2 = Location::updateOrCreate(
            ['vendor_id' => $vendor1->id, 'slug' => 'northern-avenue'],
            [
                'name' => 'Northern Avenue Express',
                'address' => 'Northern Ave 10, Yerevan',
                'phone' => '+37410500102',
                'whatsapp_number' => '37491500102',
                'opening_hours' => ['mon_fri' => '09:00 - 23:00', 'sat_sun' => '09:00 - 00:00'],
                'table_count' => 15,
                'allow_dine_in_orders' => true,
                'allow_whatsapp_orders' => true,
                'minimum_order_amount' => 2500,
                'is_active' => true,
            ]
        );

        // Users for Vendor 1
        User::firstOrCreate(
            ['email' => 'owner@bistro.am'],
            [
                'vendor_id' => $vendor1->id,
                'name' => 'Arman Petrosyan (Owner)',
                'password' => Hash::make('password'),
                'role' => 'vendor_owner',
                'phone' => '+37491111222',
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'manager@bistro.am'],
            [
                'vendor_id' => $vendor1->id,
                'location_id' => $loc1->id,
                'name' => 'Anahit Sargsyan (Cascades Manager)',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'phone' => '+37493333444',
                'email_verified_at' => now(),
            ]
        );

        // Categories & Products for Bistro Yerevan
        $cat1 = Category::firstOrCreate(
            ['vendor_id' => $vendor1->id, 'name' => 'Starters & Appetizers'],
            [
                'name_translations' => [
                    'hy' => 'Նախուտեստներ',
                    'en' => 'Starters & Appetizers',
                    'ru' => 'Закуски',
                ],
                'description' => 'Chef special appetizers prepared with fresh local herbs and cheeses.',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $p1 = Product::firstOrCreate(
            ['vendor_id' => $vendor1->id, 'name' => 'Truffle Hummus with Warm Pita'],
            [
                'category_id' => $cat1->id,
                'name_translations' => [
                    'hy' => 'Տրյուֆելային Հումուս Տաք Պիտայով',
                    'en' => 'Truffle Hummus with Warm Pita',
                    'ru' => 'Трюфельный Гуммус с Пита',
                ],
                'description' => 'Creamy chickpea hummus infused with black truffle oil, topped with crispy chickpeas and fresh pita bread.',
                'description_translations' => [
                    'hy' => 'Կրեմային սիսեռով հումուս՝ սև տրյուֆելի յուղով, խրթխրթան սիսեռով և տաք պիտա հացով։',
                    'en' => 'Creamy chickpea hummus infused with black truffle oil, topped with crispy chickpeas and fresh pita bread.',
                    'ru' => 'Кремовый нутовый хумус с черным трюфельным маслом и теплыми питами.',
                ],
                'price' => 3200,
                'image' => 'https://images.unsplash.com/photo-1637949385162-e416fb15b2ce?w=600&q=80',
                'dietary_tags' => ['vegan', 'vegetarian'],
                'calories' => 450,
                'protein_g' => 14.5,
                'carbs_g' => 52.0,
                'fat_g' => 21.0,
                'preparation_time_min' => 12,
                'is_featured' => true,
                'is_available' => true,
                'sort_order' => 1,
            ]
        );
        $p1->allergens()->syncWithoutDetaching([$allergensMap['gluten'], $allergensMap['sesame']]);

        $p2 = Product::firstOrCreate(
            ['vendor_id' => $vendor1->id, 'name' => 'Crispy Calamari Rings'],
            [
                'category_id' => $cat1->id,
                'name_translations' => [
                    'hy' => 'Խրթխրթան Կալամարի Օղակներ',
                    'en' => 'Crispy Calamari Rings',
                    'ru' => 'Хрустящие Кольца Кальмара',
                ],
                'description' => 'Golden fried calamari served with lemon garlic aioli and spicy marinara dip.',
                'description_translations' => [
                    'hy' => 'Ոսկեգույն տապակած կալամարիներ՝ կիտրոնա-սխտորային այոլիով և կծու մարինարայով։',
                    'en' => 'Golden fried calamari served with lemon garlic aioli and spicy marinara dip.',
                    'ru' => 'Обжаренные кальмары с лимонным чесночным айоли.',
                ],
                'price' => 4500,
                'image' => 'https://images.unsplash.com/photo-1599488615731-7e5c2823ff28?w=600&q=80',
                'dietary_tags' => ['chef_special'],
                'calories' => 520,
                'protein_g' => 28.0,
                'carbs_g' => 34.0,
                'fat_g' => 26.0,
                'preparation_time_min' => 15,
                'is_featured' => true,
                'is_available' => true,
                'sort_order' => 2,
            ]
        );
        $p2->allergens()->syncWithoutDetaching([$allergensMap['crustaceans'], $allergensMap['gluten'], $allergensMap['eggs']]);

        // Main Courses Category
        $cat2 = Category::firstOrCreate(
            ['vendor_id' => $vendor1->id, 'name' => 'Signature Mains & Steaks'],
            [
                'name_translations' => [
                    'hy' => 'Հիմնական Ուտեստներ և Սթեյքեր',
                    'en' => 'Signature Mains & Steaks',
                    'ru' => 'Фирменные Блюда и Стейки',
                ],
                'description' => 'Prime dry-aged beef cuts and charcoal grilled tenderloin.',
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        $p3 = Product::firstOrCreate(
            ['vendor_id' => $vendor1->id, 'name' => 'Ribeye Steak (350g)'],
            [
                'category_id' => $cat2->id,
                'name_translations' => [
                    'hy' => 'Ռիբայ Սթեյք (350գ)',
                    'en' => 'Ribeye Steak (350g)',
                    'ru' => 'Рибай Стейк (350г)',
                ],
                'description' => 'Prime aged Black Angus ribeye cooked over cherrywood charcoal, served with pepper sauce and roasted potatoes.',
                'description_translations' => [
                    'hy' => 'Black Angus ռիբայ սթեյք պատրաստված փայտածուխի վրա, մատուցվում է պղպեղային սոուսով և ջեռոցում տապակած կարտոֆիլով։',
                    'en' => 'Prime aged Black Angus ribeye cooked over cherrywood charcoal, served with pepper sauce and roasted potatoes.',
                    'ru' => 'Стейк Рибай из выдержанной говядины с соусом из черного перца.',
                ],
                'price' => 12500,
                'image' => 'https://images.unsplash.com/photo-1544025162-d76694265947?w=600&q=80',
                'dietary_tags' => ['gluten_free', 'chef_special'],
                'calories' => 880,
                'protein_g' => 62.0,
                'carbs_g' => 18.0,
                'fat_g' => 58.0,
                'preparation_time_min' => 25,
                'is_featured' => true,
                'is_available' => true,
                'sort_order' => 1,
            ]
        );

        // Product Variations for Ribeye
        ProductVariation::firstOrCreate(
            ['product_id' => $p3->id, 'name' => 'Medium Rare'],
            [
                'name_translations' => [
                    'en' => 'Medium Rare',
                    'hy' => 'Միջին հում (Medium Rare)',
                    'ru' => 'Средней прожарки (Medium Rare)',
                ],
                'price' => 12500,
                'is_default' => true,
            ]
        );
        ProductVariation::firstOrCreate(
            ['product_id' => $p3->id, 'name' => 'Medium Well'],
            [
                'name_translations' => [
                    'en' => 'Medium Well',
                    'hy' => 'Լավ տապակած (Medium Well)',
                    'ru' => 'Почти прожаренное (Medium Well)',
                ],
                'price' => 12500,
                'is_default' => false,
            ]
        );
        ProductVariation::firstOrCreate(
            ['product_id' => $p3->id, 'name' => 'Large Cut (500g)'],
            [
                'name_translations' => [
                    'en' => 'Large Cut (500g)',
                    'hy' => 'Մեծ կտոր (500գ)',
                    'ru' => 'Большой кусок (500г)',
                ],
                'price' => 17500,
                'is_default' => false,
            ]
        );

        $p4 = Product::firstOrCreate(
            ['vendor_id' => $vendor1->id, 'name' => 'Grilled Norwegian Salmon'],
            [
                'category_id' => $cat2->id,
                'name_translations' => [
                    'hy' => 'Գրիլ Անված Նորվեգական Սաղմոն',
                    'en' => 'Grilled Norwegian Salmon',
                    'ru' => 'Норвежский Лосось на Гриле',
                ],
                'description' => 'Pan-seared salmon fillet over asparagus risotto with lemon butter sauce.',
                'description_translations' => [
                    'hy' => 'Տապակած սաղմոնի ֆիլե՝ ծնեբեկով ռիզոտոյի և կիտրոնա-կարագային սոուսի հետ։',
                    'en' => 'Pan-seared salmon fillet over asparagus risotto with lemon butter sauce.',
                    'ru' => 'Филе лосося с ризотто из спаржи и лимонным соусом.',
                ],
                'price' => 8900,
                'image' => 'https://images.unsplash.com/photo-1467003909585-2f8a72700288?w=600&q=80',
                'dietary_tags' => ['gluten_free', 'keto'],
                'calories' => 640,
                'protein_g' => 45.0,
                'carbs_g' => 22.0,
                'fat_g' => 38.0,
                'preparation_time_min' => 20,
                'is_featured' => false,
                'is_available' => true,
                'sort_order' => 2,
            ]
        );
        $p4->allergens()->syncWithoutDetaching([$allergensMap['fish'], $allergensMap['milk']]);

        // Desserts Category
        $cat3 = Category::firstOrCreate(
            ['vendor_id' => $vendor1->id, 'name' => 'Desserts & Sweets'],
            [
                'name_translations' => [
                    'hy' => 'Աղանդերներ',
                    'en' => 'Desserts & Sweets',
                    'ru' => 'Десерты',
                ],
                'description' => 'Decadent house-baked pastries and artisanal desserts.',
                'sort_order' => 3,
                'is_active' => true,
            ]
        );

        $p5 = Product::firstOrCreate(
            ['vendor_id' => $vendor1->id, 'name' => 'Pistachio Molten Lava Cake'],
            [
                'category_id' => $cat3->id,
                'name_translations' => [
                    'hy' => 'Պիստակով Լավա Տորթ',
                    'en' => 'Pistachio Molten Lava Cake',
                    'ru' => 'Фисташковый Лава-Кейк',
                ],
                'description' => 'Warm chocolate fondant with a flowing pistachio cream heart, served with vanilla gelato.',
                'description_translations' => [
                    'hy' => 'Տաք շոկոլադե ֆոնդանտ՝ հոսող պիստակային կրեմով և վանիլային ջելատոյով։',
                    'en' => 'Warm chocolate fondant with a flowing pistachio cream heart, served with vanilla gelato.',
                    'ru' => 'Теплый шоколадный фондан с фисташковым кремом и ванильным мороженым.',
                ],
                'price' => 3800,
                'image' => 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?w=600&q=80',
                'dietary_tags' => ['vegetarian'],
                'calories' => 580,
                'protein_g' => 10.0,
                'carbs_g' => 64.0,
                'fat_g' => 32.0,
                'preparation_time_min' => 15,
                'is_featured' => true,
                'is_available' => true,
                'sort_order' => 1,
            ]
        );
        $p5->allergens()->syncWithoutDetaching([$allergensMap['gluten'], $allergensMap['milk'], $allergensMap['eggs'], $allergensMap['nuts']]);

        // Cocktails Category
        $cat4 = Category::firstOrCreate(
            ['vendor_id' => $vendor1->id, 'name' => 'Craft Cocktails & Wines'],
            [
                'name_translations' => [
                    'hy' => 'Կոկտեյլներ և Գինիներ',
                    'en' => 'Craft Cocktails & Wines',
                    'ru' => 'Коктейли и Вина',
                ],
                'description' => 'Handcrafted signature cocktails and select Armenian regional wines.',
                'sort_order' => 4,
                'is_active' => true,
            ]
        );

        $p6 = Product::firstOrCreate(
            ['vendor_id' => $vendor1->id, 'name' => 'Pomegranate Smoked Old Fashioned'],
            [
                'category_id' => $cat4->id,
                'name_translations' => [
                    'hy' => 'Նռան Ծխեցված Օլդ Ֆեշն',
                    'en' => 'Pomegranate Smoked Old Fashioned',
                    'ru' => 'Олд Фэшн с Гранатовым Дымом',
                ],
                'description' => 'Aged bourbon, house pomegranate reduction, angostura bitters, served under oak smoke.',
                'description_translations' => [
                    'hy' => 'Հնեցված բուրբոն, տան պատրաստման նռան սիրոպ, ծխեցված կաղնու տաշեղներով։',
                    'en' => 'Aged bourbon, house pomegranate reduction, angostura bitters, served under oak smoke.',
                    'ru' => 'Выдержанный бурбон с гранатовым сиропом и дубовым дымом.',
                ],
                'price' => 4200,
                'image' => 'https://images.unsplash.com/photo-1514362545857-3bc16c4c7d1b?w=600&q=80',
                'dietary_tags' => ['chef_special'],
                'calories' => 210,
                'protein_g' => 0.0,
                'carbs_g' => 14.0,
                'fat_g' => 0.0,
                'preparation_time_min' => 5,
                'is_featured' => true,
                'is_available' => true,
                'sort_order' => 1,
            ]
        );

        // 5. Vendor 2: Verona Lounge & Boutique Hotel
        $vendor2 = Vendor::updateOrCreate(
            ['slug' => 'verona-lounge'],
            [
                'name' => 'Verona Lounge & Hotel',
                'type' => 'hotel',
                'logo' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=300&q=80',
                'cover_image' => 'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=1200&q=80',
                'phone' => '+37410600700',
                'email' => 'welcome@veronahotel.am',
                'currency' => 'AMD',
                'menu_template_id' => $t3->id,
                'primary_color' => '#06b6d4',
                'secondary_color' => '#6366f1',
                'theme_mode' => 'dark',
                'subscription_plan' => 'business',
                'subscription_plan_id' => $businessPlan?->id,
                'is_active' => true,
            ]
        );

        $loc3 = Location::updateOrCreate(
            ['vendor_id' => $vendor2->id, 'slug' => 'rooftop'],
            [
                'name' => 'Resort & Rooftop Pool',
                'address' => 'Baghramyan Ave 24, Yerevan',
                'phone' => '+37410600701',
                'whatsapp_number' => '37491600701',
                'opening_hours' => ['mon_sun' => '24 Hours'],
                'table_count' => 40,
                'allow_dine_in_orders' => true,
                'allow_whatsapp_orders' => true,
                'minimum_order_amount' => 4000,
                'is_active' => true,
            ]
        );

        // 6. Sample Orders
        $o1 = Order::firstOrCreate(
            ['vendor_id' => $vendor1->id, 'order_number' => 'ORD-1001'],
            [
                'location_id' => $loc1->id,
                'table_number' => 'Table 4',
                'type' => 'dine_in',
                'total_amount' => 20200,
                'status' => 'preparing',
                'customer_name' => 'Gagik Hovhannisyan',
                'customer_phone' => '+37494112233',
                'notes' => 'No onions on the calamari please.',
            ]
        );

        OrderItem::firstOrCreate(
            ['order_id' => $o1->id, 'product_name' => 'Truffle Hummus with Warm Pita'],
            ['product_id' => $p1->id, 'unit_price' => 3200, 'quantity' => 1, 'subtotal' => 3200]
        );
        OrderItem::firstOrCreate(
            ['order_id' => $o1->id, 'product_name' => 'Ribeye Steak (350g)'],
            ['product_id' => $p3->id, 'variation_name' => 'Medium Rare', 'unit_price' => 12500, 'quantity' => 1, 'subtotal' => 12500]
        );
        OrderItem::firstOrCreate(
            ['order_id' => $o1->id, 'product_name' => 'Pistachio Molten Lava Cake'],
            ['product_id' => $p5->id, 'unit_price' => 3800, 'quantity' => 1, 'subtotal' => 3800]
        );

        $o2 = Order::firstOrCreate(
            ['vendor_id' => $vendor1->id, 'order_number' => 'ORD-1002'],
            [
                'location_id' => $loc1->id,
                'table_number' => 'Table 12',
                'type' => 'whatsapp',
                'total_amount' => 8700,
                'status' => 'pending',
                'customer_name' => 'Lilit Mkrtchyan',
                'customer_phone' => '+37498556677',
                'notes' => 'Deliver to Northern Ave branch pickup.',
            ]
        );

        OrderItem::firstOrCreate(
            ['order_id' => $o2->id, 'product_name' => 'Crispy Calamari Rings'],
            ['product_id' => $p2->id, 'unit_price' => 4500, 'quantity' => 1, 'subtotal' => 4500]
        );
        OrderItem::firstOrCreate(
            ['order_id' => $o2->id, 'product_name' => 'Pomegranate Smoked Old Fashioned'],
            ['product_id' => $p6->id, 'unit_price' => 4200, 'quantity' => 1, 'subtotal' => 4200]
        );

        // 7. Seed Analytics Logs for the past 7 days (only if empty)
        if (AnalyticsLog::where('vendor_id', $vendor1->id)->count() === 0) {
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i)->format('Y-m-d');

                // Dine in scans
                for ($k = 0; $k < rand(35, 120); $k++) {
                    AnalyticsLog::create([
                        'vendor_id' => $vendor1->id,
                        'location_id' => $loc1->id,
                        'channel' => 'dine_in',
                        'visit_date' => $date,
                    ]);
                }
                // Online ordering visits
                for ($k = 0; $k < rand(20, 60); $k++) {
                    AnalyticsLog::create([
                        'vendor_id' => $vendor1->id,
                        'location_id' => $loc1->id,
                        'channel' => 'ordering',
                        'visit_date' => $date,
                    ]);
                }
            }
        }
    }
}
