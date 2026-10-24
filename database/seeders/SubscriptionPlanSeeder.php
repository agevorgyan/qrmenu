<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use App\Security\FeatureCatalog;
use App\Security\QuotaCatalog;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'price' => 9900,
                'currency' => 'AMD',
                'billing_interval' => 'monthly',
                'duration_days' => 30,
                'trial_days' => 14,
                'description' => 'Թվային մենյու փոքր ռեստորանների և սրճարանների համար',
                'features' => [
                    'QR մենյու',
                    'Ապրանքների, նկարների և գների կառավարում',
                    'Բազմալեզու մենյու',
                    'QR կոդերի ստեղծում',
                    'Հիմնական վիճակագրություն',
                ],
                'is_active' => true,
                'is_custom' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price' => 19900,
                'currency' => 'AMD',
                'billing_interval' => 'monthly',
                'duration_days' => 30,
                'trial_days' => 14,
                'description' => 'QR Menu + օնլայն պատվերներ սեղաններից',
                'features' => [
                    'Basic-ի բոլոր հնարավորությունները',
                    'Օնլայն պատվերներ',
                    'Սեղանի համարով պատվիրում',
                    'Պատվերների կառավարման վահանակ',
                    'Պատվերի կարգավիճակներ',
                    'QR կոդեր՝ ըստ սեղանների',
                    'Վաճառքի և պատվերների վիճակագրություն',
                ],
                'is_active' => true,
                'is_custom' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'price' => 39900,
                'currency' => 'AMD',
                'billing_interval' => 'monthly',
                'duration_days' => 30,
                'trial_days' => 14,
                'description' => 'Բազմամասնաճյուղ և ընդլայնված կառավարում ցանցերի համար',
                'features' => [
                    'Pro-ի բոլոր հնարավորությունները',
                    'Մի քանի մասնաճյուղի կառավարում',
                    'Աշխատակիցների դերեր',
                    'Ընդլայնված հաշվետվություններ',
                    'Առաջնահերթ աջակցություն',
                    'Ինտեգրացիաների հնարավորություն',
                ],
                'is_active' => true,
                'is_custom' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'Custom',
                'slug' => 'custom',
                'price' => 0,
                'currency' => 'AMD',
                'billing_interval' => 'custom',
                'duration_days' => 30,
                'trial_days' => 14,
                'description' => 'Պայմանագրային գին՝ կախված հաճախորդի ցանկություններից և ֆունկցիոնալությունից',
                'features' => [
                    'Անհատական ֆունկցիոնալություն',
                    'Անհատական ինտեգրացիաներ',
                    'Առանձնացված սերվերային ռեսուրսներ',
                    '24/7 Անհատական մենեջեր',
                ],
                'is_active' => true,
                'is_custom' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $p) {
            $p['feature_codes'] = FeatureCatalog::getDefaultFeaturesForPlan($p['slug']);
            $p['quota_limits'] = QuotaCatalog::getDefaultQuotasForPlan($p['slug']);
            $plan = SubscriptionPlan::updateOrCreate(['slug' => $p['slug']], $p);

            $basePrice = (float) $plan->price;
            $currency = $plan->currency ?: 'AMD';

            // Seed Monthly Price
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

            // Seed Yearly Price (20% discount for non-custom)
            $yearlyPrice = $basePrice > 0 ? round($basePrice * 12 * 0.80, 2) : 0;
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
        }
    }
}
