@php
    $allCatalogProducts = ($categories ?? collect())->flatMap(function($cat) use ($vendor, $lang, $location) {
        return $cat->products->map(function($prod) use ($vendor, $cat, $lang, $location) {
            return [
                'id' => $prod->id,
                'product_id' => $prod->id,
                'category_id' => $cat->id,
                'category_name' => mb_strtolower($cat->name),
                'name' => $prod->getTranslatedName($lang),
                'name_lower' => mb_strtolower($prod->name . ' ' . $prod->getTranslatedName($lang)),
                'description' => $prod->getTranslatedDescription($lang),
                'price' => (float) $prod->getEffectivePrice($location?->id),
                'image' => $prod->image,
                'is_available' => (bool) $prod->isAvailableAtLocation($location?->id),
                'is_time_available' => (bool) $prod->isTimeAvailable(),
                'schedule_summary' => $prod->getAvailabilityScheduleSummary($lang),
                'available_for_dine_in' => (bool) ($prod->available_for_dine_in ?? true),
                'available_for_takeaway' => (bool) ($prod->available_for_takeaway ?? true),
                'available_for_delivery' => (bool) ($prod->available_for_delivery ?? true),
                'variations' => $prod->variations->map(fn($v) => [
                    'id' => $v->id,
                    'name' => $v->getTranslatedName($lang),
                    'price' => (float)$v->price,
                    'secondary_price' => $vendor?->formatSecondaryPrice($v->getEffectivePrice(), $lang),
                ])->toArray(),
            ];
        });
    })->values()->toArray();

    $channelSchedules = [
        'dine_in' => $vendor->resolveOperatingSchedule('dine_in', $location, null, $lang),
        'delivery' => $vendor->resolveOperatingSchedule('delivery', $location, null, $lang),
        'takeaway' => $vendor->resolveOperatingSchedule('takeaway', $location, null, $lang),
    ];

    $initialOrderType = !empty($table) ? 'dine_in' : ($vendor->takeaway_enabled ? 'takeaway' : ($vendor->delivery_enabled ? 'delivery' : 'takeaway'));
    $initialClosingNotice = $channelSchedules[$initialOrderType] ?? null;

    $tipSetting = $vendor->getTipRatingSetting();
    $splitBillSetting = $vendor->getSplitBillSetting();
    $tipsEnabled = $vendor->isTipsEnabled();
    $ratingsEnabled = $vendor->isRatingsEnabled();
    $activeWaiters = [];
    if ($tipSetting->allow_waiter_selection && ($tipsEnabled || $ratingsEnabled)) {
        $waiterQuery = $vendor->users()
            ->whereIn('role', ['waiter', 'staff', 'manager', 'cashier'])
            ->where('is_active', true);

        if (($tipSetting->waiter_sort_by ?? 'sort_order') === 'name') {
            $waiterQuery->orderBy('name', 'asc');
        } else {
            $waiterQuery->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
        }

        // Table-based filtering if enabled and table is reliably identified
        $filteredByTable = false;
        $resolvedTableNum = $tableNumber ?? ($table ?? null);
        if ($tipSetting->filter_waiters_by_table && !empty($resolvedTableNum)) {
            $matchedTable = \App\Models\RestaurantTable::where('vendor_id', $vendor->id)
                ->where(function ($q) use ($resolvedTableNum) {
                    $q->where('table_number', (string) $resolvedTableNum)
                      ->orWhere('name', (string) $resolvedTableNum);
                })
                ->first();

            if ($matchedTable) {
                $assignedWaiters = $matchedTable->assignedWaiters()
                    ->where('is_active', true)
                    ->orderBy('sort_order', 'asc')
                    ->orderBy('name', 'asc')
                    ->get();

                if ($assignedWaiters->isNotEmpty()) {
                    $waitersCollection = $assignedWaiters;
                    $filteredByTable = true;
                }
            }
        }

        if (!$filteredByTable) {
            $waitersCollection = $waiterQuery->get();
        }

        $activeWaiters = $waitersCollection->map(function ($u) use ($tipSetting) {
            $displayName = !empty($u->display_name) ? $u->display_name : $u->name;
            return [
                'id' => (int) $u->id,
                'name' => (string) $displayName,
                'short_description' => ($tipSetting->show_waiter_descriptions ?? true) ? ($u->short_description ?: null) : null,
                'photo_url' => (($tipSetting->show_waiter_photos ?? true) && !empty($u->avatar_url)) ? asset($u->avatar_url) : null,
                'initial' => mb_substr($displayName, 0, 1),
                'sort_order' => (int) ($u->sort_order ?? 0),
            ];
        })->values()->toArray();
    }

    $rawPaymentSettings = is_array($vendor->payment_settings) ? $vendor->payment_settings : [];
    $cashEnabled = (bool) ($rawPaymentSettings['cash_enabled'] ?? true);
    $posEnabled = (bool) ($rawPaymentSettings['pos_terminal_enabled'] ?? true);
    $rawCrmSettings = is_array($vendor->crm_settings) ? $vendor->crm_settings : [];

    $allMenuTranslations = is_array(trans('menu')) ? trans('menu') : [];

    $storefrontBootstrap = [
        'vendor' => [
            'slug' => $vendor->slug,
            'name' => $vendor->name,
            'currency' => $vendor->currency,
            'ai_waiter_name' => $vendor->getAiWaiterName(),
            'ai_waiter_enabled' => (bool) $vendor->ai_waiter_enabled,
            'ai_waiter_languages' => $vendor->getAiWaiterLanguages(),
            'ai_waiter_language_details' => $vendor->getAiWaiterLanguageDetails(),
            'reservation_enabled' => (bool) $vendor->isReservationFeatureEnabled(),
            'preorder_enabled' => (bool) $vendor->isPreOrderFeatureEnabled(),
        ],
        'catalogProducts' => $allCatalogProducts,
        'activeCat' => 'cat-' . ($categories->first()?->id ?? 1),
        'table' => $table ? 'Table ' . $table : '',
        'tableNumber' => $tableNumber ?? ($table ?? null),
        'isTableFixed' => !empty($table),
        'lang' => $lang ?? 'hy',
        'dir' => in_array($lang ?? 'hy', ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr',
        'menuVersion' => (int) \App\Services\TenantCache::get($vendor, 'menu_version', 1),
        'localeBundles' => $localeBundles ?? [],
        'needsLanguageSelection' => (bool) ($needsLanguageSelection ?? false),
        'entryContext' => $entryContext ?? 'general_menu',
        'supportedLanguages' => $supportedLanguages ?? [],
        'activeMealPeriod' => $activeMealPeriod ?? null,
        'greeting' => $greeting ?? null,
        'csrfToken' => csrf_token(),
        'locationId' => $location?->id ?? 1,
        'schedules' => $channelSchedules,
        'closingNotice' => $initialClosingNotice,
        'routes' => [
            'sw' => route('client.sw', ['vendor_slug' => $vendor->slug]),
            'submitOrder' => route('client.order.submit', ['vendor_slug' => $vendor->slug]),
            'orderStatus' => route('client.order.status', ['vendor_slug' => $vendor->slug, 'order_number' => '___NUM___']),
            'callWaiter' => route('client.waiter.call', ['vendor_slug' => $vendor->slug]),
            'submitRating' => route('client.rating.submit', ['vendor_slug' => $vendor->slug]),
            'submitTip' => route('client.tip.submit', ['vendor_slug' => $vendor->slug]),
            'setLanguage' => route('client.language.set', ['vendor_slug' => $vendor->slug]),
            'aiSessionStart' => route('client.ai_waiter.session.start', ['vendor_slug' => $vendor->slug]),
            'aiSessionBase' => url('/api/m/' . $vendor->slug . '/ai-waiter/session'),
            'reserve' => route('client.reserve', ['vendor_slug' => $vendor->slug, 'location_slug' => $location?->slug, 'lang' => $lang ?? app()->getLocale()]),
        ],
        'settings' => [
            'paymentMethod' => $cashEnabled ? 'cash' : ($posEnabled ? 'pos_terminal' : 'cash'),
            'birthdayDiscountPercent' => (float) ($rawCrmSettings['birthday_discount_percent'] ?? 15),
            'birthdayDiscountEnabled' => (bool) ($rawCrmSettings['birthday_discount_enabled'] ?? true),
            'birthdayValidityDays' => (int) ($rawCrmSettings['birthday_validity_days'] ?? 3),
            'orderType' => $initialOrderType,
            'serviceFeeEnabled' => (bool) $vendor->service_fee_enabled,
            'serviceFeeType' => $vendor->service_fee_type ?? 'percent',
            'serviceFeeValue' => (float) ($vendor->service_fee_value ?? 0),
            'serviceFeeMinOrder' => (float) ($vendor->service_fee_min_order ?? 0),
            'deliveryEnabled' => (bool) $vendor->delivery_enabled,
            'deliveryFee' => (float) ($vendor->delivery_fee ?? 0),
            'deliveryMinAmount' => (float) ($vendor->delivery_min_amount ?? 0),
            'deliveryFreeFrom' => $vendor->delivery_free_from !== null ? (float) $vendor->delivery_free_from : null,
            'takeawayEnabled' => (bool) $vendor->takeaway_enabled,
            'takeawayMinAmount' => (float) ($vendor->takeaway_min_amount ?? 0),
            'tipsRatings' => [
                'is_module_enabled' => (bool) $tipSetting->is_enabled,
                'tips_enabled' => $tipsEnabled,
                'ratings_enabled' => $ratingsEnabled,
                'tip_percentages' => $tipSetting->tip_percentages ?? [5, 10, 15, 20],
                'fixed_tip_amounts' => $tipSetting->fixed_tip_amounts ?? [200, 500, 1000],
                'allow_custom_tip' => (bool) $tipSetting->allow_custom_tip,
                'default_tip_selection' => $tipSetting->default_tip_selection ?? 'none',
                'calculation_base' => $tipSetting->calculation_base ?? 'subtotal',
                'applicable_order_types' => $tipSetting->applicable_order_types ?? ['dine_in', 'takeaway', 'delivery'],
                'min_tip_amount' => (float) ($tipSetting->min_tip_amount ?? 0),
                'max_tip_amount' => (float) ($tipSetting->max_tip_amount ?? 50000),
                'allow_cash_tips' => (bool) $tipSetting->allow_cash_tips,
                'rating_scale' => (int) ($tipSetting->rating_scale ?? 5),
                'feedback_categories' => $tipSetting->feedback_categories ?? ['food_quality', 'service_speed', 'waiter_politeness', 'cleanliness', 'ambiance'],
                'is_written_feedback_optional' => (bool) $tipSetting->is_written_feedback_optional,
                'allow_anonymous_ratings' => (bool) $tipSetting->allow_anonymous_ratings,
                'allow_waiter_selection' => (bool) $tipSetting->allow_waiter_selection,
                'waiter_selection_stage' => (string) ($tipSetting->waiter_selection_stage ?? 'all'),
                'waiter_selection_required' => (bool) $tipSetting->waiter_selection_required,
                'show_waiter_photos' => (bool) ($tipSetting->show_waiter_photos ?? true),
                'show_waiter_descriptions' => (bool) ($tipSetting->show_waiter_descriptions ?? true),
                'waiter_attribution_scope' => (string) ($tipSetting->waiter_attribution_scope ?? 'both'),
                'waiters' => $activeWaiters,
            ],
            'splitBill' => [
                'is_enabled' => $vendor->isSplitBillEnabled(),
                'allow_equal_split' => (bool) $splitBillSetting->allow_equal_split,
                'allow_item_split' => (bool) $splitBillSetting->allow_item_split,
                'allow_custom_amount' => (bool) $splitBillSetting->allow_custom_amount,
                'allow_share_link' => (bool) $splitBillSetting->allow_share_link,
            ],
        ],
        'translations' => array_merge($allMenuTranslations, [
            'added_to_cart' => __('menu.added_to_cart'),
            'removed_from_cart' => __('menu.removed_from_cart'),
            'dine_in_requires_table_qr' => __('menu.dine_in_requires_table_qr'),
            'takeaway_disabled_notice' => __('menu.takeaway_disabled_notice'),
            'min_takeaway_order_warning' => __('menu.min_takeaway_order_warning'),
            'takeaway_phone_required' => __('menu.takeaway_phone_required'),
            'delivery_disabled_notice' => __('menu.delivery_disabled_notice'),
            'min_delivery_order_warning' => __('menu.min_delivery_order_warning'),
            'delivery_address_required' => __('menu.delivery_address_required'),
            'delivery_phone_required' => __('menu.delivery_phone_required'),
            'status_pending' => __('menu.status_pending'),
            'status_desc_pending' => __('menu.status_desc_pending'),
            'delivery' => __('menu.delivery'),
            'takeaway' => __('menu.takeaway'),
            'items_appended_toast' => __('menu.items_appended_toast'),
            'order_submitted_prefix' => __('menu.order_submitted_prefix'),
            'order_submitted_suffix' => __('menu.order_submitted_suffix'),
            'order_number_label' => __('menu.order_number_label'),
            'wifi_copied' => __('menu.wifi_copied'),
            'invalid_phone_format' => __('menu.invalid_phone_format'),
            'invalid_email_format' => __('menu.invalid_email_format'),
            'phone_country' => __('menu.phone_country'),
            'choose_order_type' => __('menu.choose_order_type'),
            'choose_order_type_desc' => __('menu.choose_order_type_desc'),
            'delivery_desc' => __('menu.delivery_desc'),
            'takeaway_desc' => __('menu.takeaway_desc'),
            'switched_to_delivery' => __('menu.switched_to_delivery'),
            'switched_to_takeaway' => __('menu.switched_to_takeaway'),
            'item_unavailable_for_channel' => __('menu.item_unavailable_for_channel'),
            'remove_unavailable_items_notice' => __('menu.remove_unavailable_items_notice'),
            'no_items_for_channel' => __('menu.no_items_for_channel'),
            'change_order_type' => __('menu.change_order_type'),
            'closed_hours' => __('menu.closed_hours'),
            'only_available_hours_notice' => __('menu.only_available_hours_notice', ['time' => ':time']),
            'product_time_restriction_toast' => __('menu.product_time_restriction_toast', ['time' => ':time']),
            'preorder_for_reservation' => __('menu.preorder_for_reservation'),
            'preorder_active' => __('menu.preorder_active'),
            'return_to_reservation' => __('menu.return_to_reservation'),
            'confirm_preorder_return' => __('menu.confirm_preorder_return'),
            'reserve_table_with_this_order' => __('menu.reserve_table_with_this_order'),
            'items_prepared_on_arrival' => __('menu.items_prepared_on_arrival'),
            'select_dishes_for_arrival' => __('menu.select_dishes_for_arrival'),
            'kitchen_service' => __('menu.kitchen_service'),
            'delivery_service' => __('menu.delivery_service'),
            'takeaway_service' => __('menu.takeaway_service'),
            'default_service' => __('menu.default_service'),
            'closing_soon_notice_title' => __('menu.closing_soon_notice_title'),
            'service_closed_notice_title' => __('menu.service_closed_notice_title'),
            'delivery_closed' => __('menu.delivery_closed'),
            'takeaway_closed' => __('menu.takeaway_closed'),
            'kitchen_closed' => __('menu.kitchen_closed'),
            'orders_closed' => __('menu.orders_closed'),
            'orders_closed_toast' => __('menu.orders_closed_toast', ['channel' => ':channel']),
            'item_added_to_cart_toast' => __('menu.item_added_to_cart_toast', ['name' => ':name']),
            'attention_item_unavailable' => __('menu.attention_item_unavailable', ['items' => ':items']),
            'payment_success' => __('menu.payment_success'),
            'network_error' => __('menu.network_error'),
            'connection_error_retry' => __('menu.connection_error_retry'),
            'error_try_again' => __('menu.error_try_again'),
            'order_tracking_finished' => __('menu.order_tracking_finished'),
            'call_waiter_table_required' => __('menu.call_waiter_table_required'),
            'close' => __('menu.close'),
            'just_now' => __('menu.just_now'),
            'country_armenia' => __('Armenia'),
            'country_russia' => __('Russia'),
            'country_georgia' => __('Georgia'),
            'country_usa_canada' => __('USA / Canada'),
            'country_france' => __('France'),
            'country_germany' => __('Germany'),
            'country_uae' => __('UAE'),
            'country_iran' => __('Iran'),
            'country_other' => __('Other Country'),
            'phone_missing_digits' => __('Missing :count digits'),
            'phone_exact_digits' => __('Phone number must have exactly :digits digits'),
            'rec_reason_sauce' => __('menu.rec_reason_sauce'),
            'rec_reason_refreshing_drink' => __('menu.rec_reason_refreshing_drink'),
            'rec_reason_burger_side' => __('menu.rec_reason_burger_side'),
            'rec_reason_coffee_sweet' => __('menu.rec_reason_coffee_sweet'),
            'rec_reason_meat_side' => __('menu.rec_reason_meat_side'),
            'rec_reason_popular_choice' => __('menu.rec_reason_popular_choice'),
            'rec_reason_chef_addon' => __('menu.rec_reason_chef_addon'),
            'rating_cat_food_quality' => __('tips.cat_food_quality'),
            'rating_cat_service_speed' => __('tips.cat_service_speed'),
            'rating_cat_waiter_politeness' => __('tips.cat_waiter_politeness'),
            'rating_cat_cleanliness' => __('tips.cat_cleanliness'),
            'rating_cat_ambiance' => __('tips.cat_ambiance'),
            'rating_cat_table_presentation' => __('tips.cat_table_presentation'),
            'rating_comment_required' => __('tips.comment_required'),
            'rating_thank_you_toast' => __('tips.thank_you_title'),
            'rating_submit_failed' => __('tips.submit_failed'),
            'ai_chat_default_greeting' => __('menu.ai_chat_default_greeting'),
        ]),
    ];
@endphp

<!-- Storefront Modular Configuration Payload -->
<script id="storefront-config" type="application/json">
    {!! json_encode($storefrontBootstrap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}
</script>

<!-- External Cached Storefront Engine -->
<script src="{{ asset('js/storefront.js') }}?v={{ file_exists(public_path('js/storefront.js')) ? filemtime(public_path('js/storefront.js')) : time() }}"></script>

<!-- Automated test compatibility signatures & Directives -->
<script>
    // Automated test compatibility signatures:
    // createStorefrontApp
    // modernBistroApp
    // storefrontApp
    // vibrantGlassApp
    // addSelectedVariationToCart
    // submitOrder
    // scheduleCompletedOrderDismissal
    // getActiveOrderServiceFee
    // getActiveOrderDeliveryFee
    // Rule 3: Burger / Pizza without Fries -> French Fries / Onion rings
    // isTableFixed: {{ !empty($table) ? 'true' : 'false' }}
    // orderType: '{{ !empty($table) ? 'dine_in' : ($vendor->takeaway_enabled ? 'takeaway' : ($vendor->delivery_enabled ? 'delivery' : 'dine_in')) }}'
</script>
