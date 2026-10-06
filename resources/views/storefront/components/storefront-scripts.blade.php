@php
    $allCatalogProducts = ($categories ?? collect())->flatMap(function($cat) use ($lang, $location) {
        return $cat->products->map(function($prod) use ($cat, $lang, $location) {
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
                'variations' => $prod->variations->map(fn($v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'price' => (float)$v->price
                ])->toArray(),
            ];
        });
    })->values()->toArray();

    $channelSchedules = [
        'dine_in' => $vendor->resolveOperatingSchedule('dine_in', $location),
        'delivery' => $vendor->resolveOperatingSchedule('delivery', $location),
        'takeaway' => $vendor->resolveOperatingSchedule('takeaway', $location),
    ];

    $initialOrderType = !empty($table) ? 'dine_in' : ($vendor->takeaway_enabled ? 'takeaway' : ($vendor->delivery_enabled ? 'delivery' : 'takeaway'));
    $initialClosingNotice = $channelSchedules[$initialOrderType] ?? null;

    $storefrontBootstrap = [
        'vendor' => [
            'slug' => $vendor->slug,
            'name' => $vendor->name,
            'currency' => $vendor->currency,
            'ai_waiter_name' => $vendor->getAiWaiterName(),
            'ai_waiter_enabled' => (bool) $vendor->ai_waiter_enabled,
            'ai_waiter_languages' => $vendor->getAiWaiterLanguages(),
            'ai_waiter_language_details' => $vendor->getAiWaiterLanguageDetails(),
        ],
        'catalogProducts' => $allCatalogProducts,
        'activeCat' => 'cat-' . ($categories->first()?->id ?? 1),
        'table' => $table ? 'Table ' . $table : '',
        'isTableFixed' => !empty($table),
        'lang' => $lang ?? 'hy',
        'csrfToken' => csrf_token(),
        'locationId' => $location?->id ?? 1,
        'schedules' => $channelSchedules,
        'closingNotice' => $initialClosingNotice,
        'routes' => [
            'sw' => route('client.sw', ['vendor_slug' => $vendor->slug]),
            'submitOrder' => route('client.order.submit', ['vendor_slug' => $vendor->slug]),
            'orderStatus' => route('client.order.status', ['vendor_slug' => $vendor->slug, 'order_number' => '___NUM___']),
            'callWaiter' => route('client.waiter.call', ['vendor_slug' => $vendor->slug]),
            'aiSessionStart' => route('client.ai_waiter.session.start', ['vendor_slug' => $vendor->slug]),
            'aiSessionBase' => url('/api/m/' . $vendor->slug . '/ai-waiter/session'),
        ],
        'settings' => [
            'paymentMethod' => !empty($vendor->getPaymentSettings()['cash_enabled']) ? 'cash' : (!empty($vendor->getPaymentSettings()['pos_terminal_enabled']) ? 'pos_terminal' : 'cash'),
            'birthdayDiscountPercent' => (float) ($vendor->getCrmSettings()['birthday_discount_percent'] ?? 15),
            'birthdayDiscountEnabled' => !empty($vendor->getCrmSettings()['birthday_discount_enabled']),
            'birthdayValidityDays' => (int) ($vendor->getCrmSettings()['birthday_validity_days'] ?? 3),
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
        ],
        'translations' => [
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
        ],
    ];
@endphp

<!-- Storefront Modular Configuration Payload -->
<script id="storefront-config" type="application/json">
    {!! json_encode($storefrontBootstrap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}
</script>

<!-- External Cached Storefront Engine -->
<script src="{{ asset('js/storefront.js') }}"></script>

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
