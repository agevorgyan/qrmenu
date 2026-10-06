<?php

namespace App\Models;

use App\Services\CredentialService;
use App\Services\Localization\LocaleManager;
use App\Services\Security\CssSanitizer;
use App\Services\TenantCache;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class Vendor extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'takeaway_enabled' => true,
        'delivery_enabled' => true,
        'allow_whatsapp_orders' => true,
        'timezone' => 'Asia/Yerevan',
        'currency' => 'AMD',
        'weight_unit' => 'g',
        'volume_unit' => 'ml',
    ];

    protected $fillable = [
        'name',
        'slug',
        'type',
        'legal_name',
        'legal_address',
        'tax_id',
        'director_name',
        'director_phone',
        'contact_person_name',
        'contact_person_phone',
        'operating_address',
        'wifi_ssid',
        'wifi_password',
        'working_hours',
        'expected_locations_count',
        'logo',
        'cover_image',
        'phone',
        'email',
        'timezone',
        'currency',
        'weight_unit',
        'volume_unit',
        'custom_domain',
        'allow_whatsapp_orders',
        'supported_languages',
        'menu_template_id',
        'primary_color',
        'secondary_color',
        'accent_color',
        'text_color',
        'bg_color',
        'theme_mode',
        'desktop_max_width',
        'custom_css',
        'subscription_plan',
        'subscription_plan_id',
        'subscription_status',
        'trial_ends_at',
        'current_period_start',
        'subscription_expires_at',
        'grace_ends_at',
        'cancelled_at',
        'custom_plan_notes',
        'is_active',
        'email_verified_at',
        'service_fee_enabled',
        'service_fee_type',
        'service_fee_value',
        'service_fee_min_order',
        'delivery_enabled',
        'delivery_fee',
        'delivery_min_amount',
        'delivery_free_from',
        'takeaway_enabled',
        'takeaway_min_amount',
        'featured_product_id',
        'featured_dish_enabled',
        'featured_dish_badge',
        'featured_dish_subtitle',
        'ai_waiter_enabled',
        'ai_waiter_name',
        'ai_waiter_avatar',
        'ai_waiter_priority_ingredients',
        'ai_waiter_welcome_text',
        'ai_waiter_featured_product_ids',
        'ai_waiter_config',
        'ai_settings',
        'payment_settings',
        'crm_settings',
        'thermal_printer_settings',
        'floor_plan_data',
        'telegram_settings',
        'dine_in_schedule_enabled',
        'dine_in_start_time',
        'dine_in_end_time',
        'dine_in_days',
        'delivery_schedule_enabled',
        'delivery_start_time',
        'delivery_end_time',
        'delivery_days',
        'takeaway_schedule_enabled',
        'takeaway_start_time',
        'takeaway_end_time',
        'takeaway_days',
        'closing_warning_enabled',
        'closing_warning_minutes',
        'closing_warning_message',
        'uuid',
        'storage_limit_bytes',
        'storage_used_bytes',
        'storage_files_count',
        'lifecycle_status',
        'termination_requested_at',
        'termination_requested_by',
        'termination_reason',
        'retention_ends_at',
        'deletion_queued_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'subscription_expires_at' => 'datetime',
        'grace_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'termination_requested_at' => 'datetime',
        'retention_ends_at' => 'datetime',
        'deletion_queued_at' => 'datetime',
        'is_active' => 'boolean',
        'storage_limit_bytes' => 'integer',
        'storage_used_bytes' => 'integer',
        'storage_files_count' => 'integer',
        'service_fee_enabled' => 'boolean',
        'service_fee_value' => 'decimal:2',
        'service_fee_min_order' => 'decimal:2',
        'delivery_enabled' => 'boolean',
        'delivery_fee' => 'decimal:2',
        'delivery_min_amount' => 'decimal:2',
        'delivery_free_from' => 'decimal:2',
        'takeaway_enabled' => 'boolean',
        'takeaway_min_amount' => 'decimal:2',
        'featured_dish_enabled' => 'boolean',
        'ai_waiter_enabled' => 'boolean',
        'ai_waiter_featured_product_ids' => 'array',
        'ai_waiter_config' => 'array',
        'ai_settings' => 'array',
        'payment_settings' => 'array',
        'crm_settings' => 'array',
        'thermal_printer_settings' => 'array',
        'floor_plan_data' => 'array',
        'telegram_settings' => 'array',
        'allow_whatsapp_orders' => 'boolean',
        'supported_languages' => 'array',
        'dine_in_schedule_enabled' => 'boolean',
        'dine_in_days' => 'array',
        'delivery_schedule_enabled' => 'boolean',
        'delivery_days' => 'array',
        'takeaway_schedule_enabled' => 'boolean',
        'takeaway_days' => 'array',
        'closing_warning_enabled' => 'boolean',
        'closing_warning_minutes' => 'integer',
    ];

    /**
     * Resolve operating schedule, open/closed status, and closing warning for a given channel (dine_in, delivery, takeaway).
     *
     * @return array{
     *     channel: string,
     *     schedule_enabled: bool,
     *     is_open: bool,
     *     start_time: ?string,
     *     end_time: ?string,
     *     days: ?array,
     *     minutes_until_close: ?int,
     *     is_warning_active: bool,
     *     warning_minutes: int,
     *     status: 'open'|'closed'|'closing_soon',
     *     badge_text: string,
     *     notice_title: ?string,
     *     notice_message: ?string
     * }
     */
    public function resolveOperatingSchedule(string $channel, ?Location $location = null, ?Carbon $now = null): array
    {
        $channel = in_array($channel, ['dine_in', 'delivery', 'takeaway']) ? $channel : 'dine_in';

        // Extract settings with location override support
        $scheduleEnabled = match ($channel) {
            'dine_in' => ($location && $location->getRawOriginal('dine_in_schedule_enabled') !== null) ? (bool) $location->dine_in_schedule_enabled : (bool) $this->dine_in_schedule_enabled,
            'delivery' => ($location && $location->getRawOriginal('delivery_schedule_enabled') !== null) ? (bool) $location->delivery_schedule_enabled : (bool) $this->delivery_schedule_enabled,
            'takeaway' => ($location && $location->getRawOriginal('takeaway_schedule_enabled') !== null) ? (bool) $location->takeaway_schedule_enabled : (bool) $this->takeaway_schedule_enabled,
        };

        $startTime = match ($channel) {
            'dine_in' => ($location && ! empty($location->dine_in_start_time)) ? $location->dine_in_start_time : $this->dine_in_start_time,
            'delivery' => ($location && ! empty($location->delivery_start_time)) ? $location->delivery_start_time : $this->delivery_start_time,
            'takeaway' => ($location && ! empty($location->takeaway_start_time)) ? $location->takeaway_start_time : $this->takeaway_start_time,
        };

        $endTime = match ($channel) {
            'dine_in' => ($location && ! empty($location->dine_in_end_time)) ? $location->dine_in_end_time : $this->dine_in_end_time,
            'delivery' => ($location && ! empty($location->delivery_end_time)) ? $location->delivery_end_time : $this->delivery_end_time,
            'takeaway' => ($location && ! empty($location->takeaway_end_time)) ? $location->takeaway_end_time : $this->takeaway_end_time,
        };

        $days = match ($channel) {
            'dine_in' => ($location && ! empty($location->dine_in_days)) ? $location->dine_in_days : $this->dine_in_days,
            'delivery' => ($location && ! empty($location->delivery_days)) ? $location->delivery_days : $this->delivery_days,
            'takeaway' => ($location && ! empty($location->takeaway_days)) ? $location->takeaway_days : $this->takeaway_days,
        };

        $warningEnabled = ($location && $location->getRawOriginal('closing_warning_enabled') !== null)
            ? (bool) $location->closing_warning_enabled
            : (bool) ($this->closing_warning_enabled ?? true);

        $warningMinutes = ($location && $location->getRawOriginal('closing_warning_minutes') !== null)
            ? (int) $location->closing_warning_minutes
            : (int) ($this->closing_warning_minutes ?? 30);

        $customMessage = $location && ! empty($location->closing_warning_message)
            ? $location->closing_warning_message
            : $this->closing_warning_message;

        $tz = ! empty($this->timezone) ? $this->timezone : 'Asia/Yerevan';
        $now = $now ? $now->copy()->setTimezone($tz) : Carbon::now($tz);

        $channelNames = [
            'dine_in' => 'Ռեստորանի խոհանոցը',
            'delivery' => 'Առաքման ծառայությունը',
            'takeaway' => 'Տանելու (Takeaway) ծառայությունը',
        ];
        $channelName = $channelNames[$channel] ?? 'Ծառայությունը';

        // If schedule not enabled or no hours defined, it is open 24/7
        if (! $scheduleEnabled || (empty($startTime) && empty($endTime) && empty($days))) {
            return [
                'channel' => $channel,
                'enabled' => false,
                'schedule_enabled' => false,
                'is_open' => true,
                'is_closed' => false,
                'start_time' => null,
                'end_time' => null,
                'days' => null,
                'minutes_left' => null,
                'minutes_until_close' => null,
                'closing_soon' => false,
                'is_warning_active' => false,
                'warning_minutes' => $warningMinutes,
                'status' => 'open',
                'badge_text' => 'Բաց է',
                'title' => null,
                'notice_title' => null,
                'message' => null,
                'notice_message' => null,
            ];
        }

        $cleanStart = $startTime ? substr($startTime, 0, 5) : '00:00';
        $cleanEnd = $endTime ? substr($endTime, 0, 5) : '23:59';

        // 1. Day of week check
        if (! empty($days) && is_array($days) && count($days) < 7) {
            $currentDay = strtolower($now->format('D'));
            $allowedDays = array_map(fn ($d) => substr(strtolower($d), 0, 3), $days);
            if (! in_array($currentDay, $allowedDays)) {
                return [
                    'channel' => $channel,
                    'enabled' => true,
                    'schedule_enabled' => true,
                    'is_open' => false,
                    'is_closed' => true,
                    'start_time' => $cleanStart,
                    'end_time' => $cleanEnd,
                    'days' => $days,
                    'minutes_left' => null,
                    'minutes_until_close' => null,
                    'closing_soon' => false,
                    'is_warning_active' => false,
                    'warning_minutes' => $warningMinutes,
                    'status' => 'closed',
                    'badge_text' => 'Փակ է (ոչ աշխատանքային օր)',
                    'title' => "{$channelName} այսօր չի աշխատում",
                    'notice_title' => "{$channelName} այսօր չի աշխատում",
                    'message' => "Այսօր {$channelName} հանգստյան օր է: Պատվերներ չեն ընդունվում:",
                    'notice_message' => "Այսօր {$channelName} հանգստյան օր է: Պատվերներ չեն ընդունվում:",
                ];
            }
        }

        // 2. Time window check
        $currentTime = $now->format('H:i:s');
        $startSeconds = Carbon::parse($cleanStart, $tz)->format('H:i:s');
        $endSeconds = Carbon::parse($cleanEnd, $tz)->format('H:i:s');

        $isOpen = false;
        $minutesUntilClose = null;

        if ($startSeconds <= $endSeconds) {
            // Normal intra-day schedule (e.g. 10:00 to 23:00)
            if ($currentTime >= $startSeconds && $currentTime <= $endSeconds) {
                $isOpen = true;
                $closingDateTime = Carbon::parse($now->format('Y-m-d').' '.$cleanEnd.':00', $tz);
                $minutesUntilClose = max(0, (int) $now->diffInMinutes($closingDateTime, false));
            }
        } else {
            // Overnight shift (e.g. 18:00 to 02:00)
            if ($currentTime >= $startSeconds) {
                $isOpen = true;
                $closingDateTime = Carbon::parse($now->format('Y-m-d').' '.$cleanEnd.':00', $tz)->addDay();
                $minutesUntilClose = max(0, (int) $now->diffInMinutes($closingDateTime, false));
            } elseif ($currentTime <= $endSeconds) {
                $isOpen = true;
                $closingDateTime = Carbon::parse($now->format('Y-m-d').' '.$cleanEnd.':00', $tz);
                $minutesUntilClose = max(0, (int) $now->diffInMinutes($closingDateTime, false));
            }
        }

        $isWarningActive = ($isOpen && $warningEnabled && $minutesUntilClose !== null && $minutesUntilClose <= $warningMinutes && $minutesUntilClose > 0);

        if (! $isOpen) {
            $status = 'closed';
            $badgeText = "Փակ է (բացվում է {$cleanStart}-ին)";
            $noticeTitle = "{$channelName} այս պահին փակ է";
            $noticeMessage = "{$channelName} այս պահին փակ է (աշխատանքային ժամեր՝ {$cleanStart} - {$cleanEnd}): Պատվերներ չեն ընդունվում:";
        } elseif ($isWarningActive) {
            $status = 'closing_soon';
            $badgeText = "Փակվում է {$minutesUntilClose} րոպեից";
            $noticeTitle = "Ուշադրություն. {$channelName} փակվում է {$minutesUntilClose} րոպեից";
            $noticeMessage = $customMessage ?: "Խնդրում ենք ձևակերպել Ձեր պատվերը մինչև {$cleanEnd}:";
        } else {
            $status = 'open';
            $badgeText = "Բաց է մինչև {$cleanEnd}";
            $noticeTitle = null;
            $noticeMessage = null;
        }

        return [
            'channel' => $channel,
            'enabled' => true,
            'schedule_enabled' => true,
            'is_open' => $isOpen,
            'is_closed' => ! $isOpen,
            'start_time' => $cleanStart,
            'end_time' => $cleanEnd,
            'days' => $days,
            'minutes_left' => $minutesUntilClose,
            'minutes_until_close' => $minutesUntilClose,
            'closing_soon' => $isWarningActive,
            'is_warning_active' => $isWarningActive,
            'warning_minutes' => $warningMinutes,
            'status' => $status,
            'badge_text' => $badgeText,
            'title' => $noticeTitle,
            'notice_title' => $noticeTitle,
            'message' => $noticeMessage,
            'notice_message' => $noticeMessage,
        ];
    }

    public function isChannelOpen(string $channel, ?Location $location = null, ?Carbon $now = null): bool
    {
        $res = $this->resolveOperatingSchedule($channel, $location, $now);

        return (bool) $res['is_open'];
    }

    public function getClosingNotice(string $channel, ?Location $location = null, ?Carbon $now = null, ?string $lang = 'en'): ?array
    {
        $res = $this->resolveOperatingSchedule($channel, $location, $now);
        if (! $res['is_open'] || $res['is_warning_active']) {
            return $res;
        }

        return null;
    }

    /**
     * Transparently decrypt Wi-Fi password when retrieved, falling back to plaintext for legacy rows.
     */
    public function getWifiPasswordAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * Transparently encrypt Wi-Fi password when stored at rest in the database.
     */
    public function setWifiPasswordAttribute(?string $value): void
    {
        $this->attributes['wifi_password'] = ($value !== null && $value !== '')
            ? Crypt::encryptString($value)
            : null;
    }

    /**
     * Check if Telegram notifications are enabled for this vendor.
     */
    public function hasTelegramEnabled(): bool
    {
        return ! empty($this->telegram_settings['enabled']);
    }

    /**
     * Get configured Telegram Bot Token or fallback to platform settings / env.
     * Checks dedicated encrypted vendor_credentials first.
     */
    public function getTelegramBotToken(): ?string
    {
        $token = app(CredentialService::class)->get($this, 'telegram', 'bot_token');
        if (! empty($token)) {
            return trim($token);
        }

        return SystemSetting::get('telegram_bot_token')
            ?: config('services.telegram.bot_token')
            ?: env('TELEGRAM_BOT_TOKEN');
    }

    /**
     * Get configured Telegram Chat ID.
     */
    public function getTelegramChatId(): ?string
    {
        return ! empty($this->telegram_settings['chat_id'])
            ? trim((string) $this->telegram_settings['chat_id'])
            : null;
    }

    /**
     * Get optional Telegram Message Thread / Topic ID.
     */
    public function getTelegramTopicId(): ?int
    {
        return ! empty($this->telegram_settings['topic_id'])
            ? (int) $this->telegram_settings['topic_id']
            : null;
    }

    /**
     * Check if a specific notification type is enabled (orders, waiter_calls, payments).
     */
    public function shouldNotifyTelegram(string $type): bool
    {
        if (! $this->hasTelegramEnabled()) {
            return false;
        }

        $key = 'notify_'.$type;

        // If explicitly set, respect it; otherwise default to true when telegram is enabled
        return array_key_exists($key, $this->telegram_settings ?? [])
            ? (bool) $this->telegram_settings[$key]
            : true;
    }

    /**
     * Get configured AI Provider (gemini, openai, claude, deepseek, groq, openrouter, custom).
     */
    public function getAiProvider(): string
    {
        return $this->ai_settings['provider'] ?? 'gemini';
    }

    /**
     * Get configured AI Model for this vendor.
     */
    public function getAiModel(): string
    {
        if (! empty($this->ai_settings['model'])) {
            return $this->ai_settings['model'];
        }

        return match ($this->getAiProvider()) {
            'openai' => 'gpt-4o-mini',
            'claude' => 'claude-3-5-haiku-20241022',
            'deepseek' => 'deepseek-chat',
            'groq' => 'llama-3.3-70b-versatile',
            'openrouter' => 'openai/gpt-4o-mini',
            'custom' => 'custom-model',
            default => 'gemini-1.5-flash',
        };
    }

    /**
     * Get configured vendor AI API Key or fallback to system key.
     * Checks dedicated encrypted vendor_credentials first.
     */
    public function getAiApiKey(): ?string
    {
        $apiKey = app(CredentialService::class)->get($this, 'ai', 'api_key');
        if (! empty($apiKey)) {
            return trim($apiKey);
        }

        // Fallback to system key for gemini
        if ($this->getAiProvider() === 'gemini') {
            return config('services.gemini.key') ?? env('GEMINI_API_KEY');
        }

        return null;
    }

    /**
     * Get custom base URL if configured.
     */
    public function getAiBaseUrl(): ?string
    {
        return $this->ai_settings['base_url'] ?? null;
    }

    /**
     * Check if vendor configured their own custom AI credentials.
     */
    public function hasCustomAiConfig(): bool
    {
        return app(CredentialService::class)->has($this, 'ai', 'api_key')
            || ! empty($this->ai_settings['api_key'])
            || ! empty($this->ai_settings['provider']);
    }

    /**
     * Get list of priority ingredients configured by the vendor for AI waiter recommendations.
     *
     * @return array<int, string>
     */
    public function getAiWaiterPriorityIngredientsList(): array
    {
        if (empty($this->ai_waiter_priority_ingredients)) {
            return [];
        }

        $items = preg_split('/[,\n\r]+/', (string) $this->ai_waiter_priority_ingredients);

        return array_values(array_filter(array_map(function ($item) {
            return trim($item);
        }, $items ?: [])));
    }

    /**
     * Get AI Waiter display name.
     */
    public function getAiWaiterName(): string
    {
        return ! empty($this->ai_waiter_name) ? $this->ai_waiter_name : 'AI Մատուցող';
    }

    /**
     * Relationship to AI waiter sessions.
     */
    public function aiWaiterSessions()
    {
        return $this->hasMany(AiWaiterSession::class);
    }

    /**
     * Relationship to AI usage logs.
     */
    public function aiUsageLogs()
    {
        return $this->hasMany(AiUsageLog::class);
    }

    /**
     * Get AI Waiter configuration array with defaults.
     */
    public function getAiWaiterConfig(): array
    {
        $defaultConfig = [
            'languages' => $this->getSupportedLanguageCodes(),
            'personality' => 'friendly',
            'max_recommendations' => 3,
            'free_text_enabled' => true,
            'ai_chat_enabled' => true,
            'auto_popup' => true,
            'quotas' => [
                'requests_per_minute' => 30,
                'requests_per_hour' => 300,
                'daily_requests' => 1000,
                'monthly_requests' => 15000,
                'daily_tokens' => null,
                'monthly_tokens' => null,
                'spending_limit' => null,
            ],
            'promoted_products' => [], // array of ['product_id' => int, 'priority' => int, 'active' => bool]
            'preferred_ingredients' => [], // array of ['ingredient' => string, 'priority' => int, 'active' => bool]
            'preferred_categories' => [], // array of ['category_id' => int, 'priority' => int]
            'group_priorities' => [
                'bestseller' => 90,
                'chef_recommendation' => 85,
                'high_margin' => 75,
                'new_products' => 65,
            ],
            'tag_priorities' => [
                'bestseller' => 90,
                'signature' => 85,
                'chef-choice' => 80,
                'high-margin' => 75,
                'popular' => 70,
                'seasonal' => 65,
            ],
            'scoring_weights' => [
                'restaurant_priority' => 30,
                'preferred_ingredient' => 20,
                'customer_preference' => 25,
                'dietary_compatibility' => 10,
                'taste_spiciness' => 5,
                'occasion' => 5,
                'budget' => 5,
            ],
            'questions' => [
                'mood' => ['enabled' => true, 'priority' => 1],
                'preference' => ['enabled' => true, 'priority' => 2],
                'spiciness' => ['enabled' => true, 'priority' => 3],
                'occasion' => ['enabled' => true, 'priority' => 4],
                'budget' => ['enabled' => true, 'priority' => 5],
                'drink' => ['enabled' => true, 'priority' => 6],
            ],
        ];

        $saved = $this->ai_waiter_config ?? [];

        return array_replace_recursive($defaultConfig, $saved);
    }

    /**
     * Get configured AI quotas for this vendor.
     *
     * @return array<string, mixed>
     */
    public function getAiQuotas(): array
    {
        return $this->getAiWaiterConfig()['quotas'] ?? [];
    }

    /**
     * Get list of promoted products configured by vendor.
     *
     * @return array<int, array{product_id: int, priority: int, active: bool}>
     */
    public function getAiPromotedProductsList(): array
    {
        $config = $this->getAiWaiterConfig();

        return array_values(array_filter($config['promoted_products'] ?? [], fn ($p) => ! empty($p['active'])));
    }

    /**
     * Get preferred ingredients with priorities.
     *
     * @return array<int, array{ingredient: string, priority: int, active: bool}>
     */
    public function getAiPreferredIngredients(): array
    {
        $config = $this->getAiWaiterConfig();
        $list = array_values(array_filter($config['preferred_ingredients'] ?? [], fn ($i) => ! empty($i['active'])));

        // Merge backwards-compatible text list if structured list is empty
        if (empty($list) && ! empty($this->ai_waiter_priority_ingredients)) {
            $legacy = $this->getAiWaiterPriorityIngredientsList();
            foreach ($legacy as $ing) {
                $list[] = [
                    'ingredient' => $ing,
                    'priority' => 90,
                    'active' => true,
                ];
            }
        }

        return $list;
    }

    /**
     * Get scoring weights.
     */
    public function getAiScoringWeights(): array
    {
        $config = $this->getAiWaiterConfig();

        return $config['scoring_weights'] ?? [
            'restaurant_priority' => 30,
            'preferred_ingredient' => 20,
            'customer_preference' => 25,
            'dietary_compatibility' => 10,
            'taste_spiciness' => 5,
            'occasion' => 5,
            'budget' => 5,
        ];
    }

    /**
     * Get allowed languages for AI waiter.
     */
    public function getAiWaiterLanguages(): array
    {
        $config = $this->getAiWaiterConfig();
        $allowed = $config['languages'] ?? [];
        $vendorSupported = $this->getSupportedLanguageCodes();

        if (empty($vendorSupported)) {
            return ! empty($allowed) ? $allowed : ['en'];
        }

        if (empty($allowed)) {
            return $vendorSupported;
        }

        $filtered = array_values(array_intersect($allowed, $vendorSupported));

        return ! empty($filtered) ? $filtered : [$vendorSupported[0]];
    }

    /**
     * Get detailed metadata (code, name, native_name, flag, direction) for AI waiter active languages.
     *
     * @return array<int, array{code: string, name: string, native_name: string, flag: string, direction: string, is_default: bool}>
     */
    public function getAiWaiterLanguageDetails(): array
    {
        $allowedCodes = $this->getAiWaiterLanguages();
        $supported = $this->getSupportedLanguages();
        $indexedSupported = [];
        foreach ($supported as $item) {
            $code = strtolower($item['code'] ?? '');
            if ($code !== '') {
                $indexedSupported[$code] = $item;
            }
        }

        $result = [];
        foreach ($allowedCodes as $code) {
            if (isset($indexedSupported[$code])) {
                $result[] = $indexedSupported[$code];
            } else {
                $sysLang = Language::where('code', $code)->first();
                $result[] = [
                    'code' => $code,
                    'name' => $sysLang?->name ?? strtoupper($code),
                    'native_name' => $sysLang?->native_name ?? strtoupper($code),
                    'flag' => $sysLang?->flag ?? '🌐',
                    'direction' => $sysLang?->direction ?? 'ltr',
                    'is_default' => false,
                ];
            }
        }

        return $result;
    }

    public function featuredProduct()
    {
        return $this->belongsTo(Product::class, 'featured_product_id');
    }

    public function menuTemplate()
    {
        return $this->belongsTo(MenuTemplate::class);
    }

    public function locations()
    {
        return $this->hasMany(Location::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(VendorCredential::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class)->orderBy('sort_order', 'asc');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function analyticsLogs()
    {
        return $this->hasMany(AnalyticsLog::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class)->latest();
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->latest();
    }

    public function payments()
    {
        return $this->hasMany(SubscriptionPayment::class)->latest();
    }

    public function storageFiles()
    {
        return $this->hasMany(VendorStorageFile::class, 'vendor_id');
    }

    public function deletionJobs()
    {
        return $this->hasMany(VendorDeletionJob::class, 'vendor_id');
    }

    public function lifecycleLogs()
    {
        return $this->hasMany(VendorLifecycleLog::class, 'vendor_id');
    }

    public function securityAuditLogs()
    {
        return $this->hasMany(SecurityAuditLog::class, 'vendor_id')->latest('created_at');
    }

    public function isSuspended(): bool
    {
        return $this->lifecycle_status === 'suspended' || ! $this->is_active;
    }

    public function isInRetention(): bool
    {
        return $this->lifecycle_status === 'retention';
    }

    public function isDeleting(): bool
    {
        return in_array($this->lifecycle_status, ['deletion_queued', 'deleting'], true);
    }

    public function isDeleted(): bool
    {
        return $this->lifecycle_status === 'deleted' || $this->trashed();
    }

    protected static function booted(): void
    {
        static::creating(function (Vendor $vendor): void {
            if (empty($vendor->uuid)) {
                $vendor->uuid = (string) Str::uuid();
            }
            if ($vendor->storage_limit_bytes === null) {
                $vendor->storage_limit_bytes = $vendor->resolveStorageLimit();
            }
        });

        static::saving(function (Vendor $vendor): void {
            if ($vendor->isDirty('custom_css') && ! empty($vendor->custom_css)) {
                $sanitizer = app(CssSanitizer::class);
                $vendor->custom_css = $sanitizer->sanitize($vendor->custom_css);
            }

            // Phase 11: Single authoritative source of truth for subscription plan is subscription_plan_id.
            // Avoid duplicated authoritative state.
            if ($vendor->isDirty('subscription_plan_id') && $vendor->subscription_plan_id) {
                $plan = SubscriptionPlan::find($vendor->subscription_plan_id);
                if ($plan) {
                    $vendor->attributes['subscription_plan'] = $plan->slug;
                }
            } elseif ($vendor->isDirty('subscription_plan') && ! empty($vendor->subscription_plan)) {
                $plan = SubscriptionPlan::where('slug', $vendor->subscription_plan)->first();
                if ($plan) {
                    $vendor->attributes['subscription_plan_id'] = $plan->id;
                    $vendor->attributes['subscription_plan'] = $plan->slug;
                }
            }
        });

        static::saved(function (Vendor $vendor): void {
            if ($vendor->isDirty('custom_domain')) {
                if (! empty($vendor->custom_domain)) {
                    try {
                        $clean = CustomDomain::normalize($vendor->custom_domain);
                        $cd = CustomDomain::withoutGlobalScopes()->where('normalized_domain', $clean)->first();
                        if (! $cd) {
                            CustomDomain::withoutGlobalScopes()->where('vendor_id', $vendor->id)->update(['is_primary' => false]);
                            CustomDomain::create([
                                'vendor_id' => $vendor->id,
                                'domain' => $vendor->custom_domain,
                                'normalized_domain' => $clean,
                                'is_primary' => true,
                                'verification_token' => CustomDomain::generateVerificationToken(),
                                'verification_method' => CustomDomain::METHOD_DNS_TXT,
                                'verified_at' => now(),
                                'dns_status' => CustomDomain::DNS_DETECTED,
                                'dns_detected_at' => now(),
                                'ssl_status' => CustomDomain::SSL_ACTIVE,
                                'status' => CustomDomain::STATUS_ACTIVE,
                            ]);
                        } elseif ((int) $cd->vendor_id === (int) $vendor->id && ! $cd->is_primary) {
                            $cd->makePrimary();
                        }
                    } catch (\Throwable $e) {
                        // Ignore normalization errors during legacy model attribute sync
                    }
                }
            }
        });
    }

    /**
     * Get clean, injection-safe custom CSS for storefront rendering.
     */
    public function getSanitizedCustomCssAttribute(): string
    {
        return app(CssSanitizer::class)->sanitize($this->custom_css ?? '');
    }

    /**
     * Generate tenant-sensitive cache key with vendor UUID.
     */
    public function cacheKey(string $suffix): string
    {
        return TenantCache::key($this, $suffix);
    }

    /**
     * Get subscription plan slug dynamically derived from authoritative subscription_plan_id.
     */
    public function getSubscriptionPlanAttribute($value): string
    {
        return $this->plan?->slug ?? $value ?? 'pro';
    }

    /**
     * Set subscription plan slug while keeping subscription_plan_id authoritative.
     */
    public function setSubscriptionPlanAttribute($value): void
    {
        $this->unsetRelation('plan');
        if ($value) {
            $plan = SubscriptionPlan::where('slug', $value)->first();
            if ($plan) {
                $this->attributes['subscription_plan_id'] = $plan->id;
                $this->attributes['subscription_plan'] = $plan->slug;

                return;
            }
        }
        $this->attributes['subscription_plan'] = $value;
    }

    /**
     * Set authoritative subscription_plan_id and synchronize plan slug.
     */
    public function setSubscriptionPlanIdAttribute($value): void
    {
        $this->attributes['subscription_plan_id'] = $value;
        $this->unsetRelation('plan');
        if ($value) {
            $plan = SubscriptionPlan::find($value);
            if ($plan) {
                $this->attributes['subscription_plan'] = $plan->slug;
            }
        }
    }

    /**
     * Resolve default storage quota limit based on subscription plan.
     */
    public function resolveStorageLimit(): int
    {
        $planSlug = $this->plan?->slug ?? $this->subscription_plan ?? 'basic';

        return match ($planSlug) {
            'pro' => 524288000,          // 500 MB
            'business' => 2147483648,    // 2 GB
            'custom' => 10737418240,     // 10 GB
            default => 104857600,        // 100 MB (Basic / default)
        };
    }

    /**
     * Check if currently in a valid trial period.
     */
    public function isTrialing(): bool
    {
        if ($this->subscription) {
            return $this->subscription->isTrialing();
        }

        if ($this->subscription_status === 'trialing') {
            return $this->trial_ends_at ? $this->trial_ends_at->isFuture() : true;
        }

        return false;
    }

    /**
     * Check if currently in grace period.
     */
    public function isInGracePeriod(): bool
    {
        if ($this->subscription) {
            return $this->subscription->isInGracePeriod();
        }

        return in_array($this->subscription_status, ['grace', 'past_due'], true)
            && $this->grace_ends_at !== null
            && $this->grace_ends_at->isFuture();
    }

    /**
     * Check if subscription has expired.
     */
    public function isExpired(): bool
    {
        if ($this->subscription) {
            return $this->subscription->isExpired();
        }

        if (! $this->is_active || in_array($this->subscription_status, ['expired', 'suspended', 'past_due'], true)) {
            return true;
        }

        if ($this->isInGracePeriod()) {
            return false;
        }

        if ($this->subscription_status === 'grace') {
            return true;
        }

        if ($this->subscription_status === 'trialing' && $this->trial_ends_at && $this->trial_ends_at->isPast()) {
            return true;
        }

        if ($this->subscription_expires_at && $this->subscription_expires_at->isPast()) {
            return true;
        }

        return false;
    }

    /**
     * Check if subscription allows access to system features.
     */
    public function allowsAccess(): bool
    {
        if ($this->subscription) {
            return $this->subscription->allowsAccess();
        }

        if ($this->subscription_status === 'cancelled') {
            return $this->subscription_expires_at ? $this->subscription_expires_at->isFuture() : false;
        }

        return ! $this->isExpired();
    }

    /**
     * Check if vendor has an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->is_active && $this->allowsAccess();
    }

    /**
     * Days left in current subscription, trial, or grace period.
     */
    public function daysLeft(): int
    {
        if ($this->subscription) {
            return $this->subscription->daysLeft();
        }

        $targetDate = $this->isInGracePeriod()
            ? $this->grace_ends_at
            : ($this->isTrialing() ? $this->trial_ends_at : $this->subscription_expires_at);

        if (! $targetDate) {
            return 0;
        }

        return max(0, (int) ceil(now()->diffInHours($targetDate, false) / 24));
    }

    /**
     * Human-readable label for subscription status.
     */
    public function getSubscriptionStatusLabelAttribute(): string
    {
        if ($this->subscription_status === 'suspended') {
            return 'Կասեցված (Suspended)';
        }
        if ($this->isInGracePeriod()) {
            return 'Արտոնյալ ժամկետ (Grace Period)';
        }
        if ($this->isExpired()) {
            return 'Ավարտված / Անջատված';
        }
        if ($this->isTrialing()) {
            return 'Փորձնական (14 օր)';
        }
        if ($this->subscription_status === 'active') {
            return 'Ակտիվ';
        }
        if ($this->subscription_status === 'cancelled') {
            return 'Չեղարկված';
        }
        if ($this->subscription_status === 'past_due') {
            return 'Ժամկետանց';
        }

        return ucfirst($this->subscription_status ?? 'Active');
    }

    public function hasFeature(string $feature): bool
    {
        $planSlug = strtolower($this->plan?->slug ?? $this->subscription_plan ?? 'pro');

        if ($planSlug === 'custom' || $planSlug === 'business') {
            return true;
        }

        switch ($feature) {
            case 'orders':
            case 'online_orders':
            case 'customers':
                return in_array($planSlug, ['pro', 'business', 'custom']);

            case 'locations':
            case 'multi_location':
            case 'team':
            case 'staff_roles':
            case 'advanced_analytics':
                return in_array($planSlug, ['business', 'custom']);

            case 'menu':
            case 'qr':
            case 'branding':
            case 'analytics':
            default:
                return true;
        }
    }

    /**
     * Custom domains associated with this vendor.
     */
    public function customDomains(): HasMany
    {
        return $this->hasMany(CustomDomain::class);
    }

    /**
     * Primary custom domain for this vendor.
     */
    public function primaryCustomDomain(): HasOne
    {
        return $this->hasOne(CustomDomain::class)->where('is_primary', true);
    }

    /**
     * Active verified custom domains for this vendor.
     */
    public function activeCustomDomains(): HasMany
    {
        return $this->hasMany(CustomDomain::class)->where('status', CustomDomain::STATUS_ACTIVE);
    }

    /**
     * Mutator to normalize custom_domain attribute on assignment.
     */
    public function setCustomDomainAttribute($value): void
    {
        if (empty($value) || trim($value) === '') {
            $this->attributes['custom_domain'] = null;

            return;
        }

        try {
            $this->attributes['custom_domain'] = CustomDomain::normalize($value);
        } catch (\Throwable $e) {
            $domain = preg_replace('#^https?://#i', '', trim($value));
            $domain = explode('/', $domain)[0];
            $domain = explode(':', $domain)[0];
            $this->attributes['custom_domain'] = strtolower(trim($domain));
        }
    }

    /**
     * Check if vendor has a valid custom domain configured.
     */
    public function hasCustomDomain(): bool
    {
        return ! empty($this->custom_domain) && trim($this->custom_domain) !== '';
    }

    /**
     * Get the normalized lowercase custom domain without protocol.
     */
    public function getCleanCustomDomain(): ?string
    {
        if (! $this->hasCustomDomain()) {
            return null;
        }

        try {
            return CustomDomain::normalize($this->custom_domain);
        } catch (\Throwable $e) {
            $domain = preg_replace('#^https?://#i', '', trim($this->custom_domain));
            $domain = explode('/', $domain)[0];

            return strtolower(trim($domain));
        }
    }

    /**
     * Get the full public storefront URL (custom domain or platform route).
     */
    public function getStorefrontUrl(?string $locationSlug = null): string
    {
        if ($this->hasCustomDomain()) {
            $scheme = (request()->isSecure() || str_starts_with(config('app.url'), 'https://')) ? 'https://' : 'http://';
            $base = $scheme.$this->getCleanCustomDomain();

            return $locationSlug ? "{$base}/{$locationSlug}" : "{$base}/";
        }

        return route('client.menu', array_filter([
            'vendor_slug' => $this->slug,
            'location_slug' => $locationSlug,
        ]));
    }

    /**
     * Get the admin panel URL for this vendor.
     */
    public function getAdminUrl(): string
    {
        if ($this->hasCustomDomain()) {
            $scheme = (request()->isSecure() || str_starts_with(config('app.url'), 'https://')) ? 'https://' : 'http://';

            return "{$scheme}".$this->getCleanCustomDomain().'/admin';
        }

        return route('admin.dashboard');
    }

    /**
     * Relationship to vendor languages pivot records.
     */
    public function vendorLanguages(): HasMany
    {
        return $this->hasMany(VendorLanguage::class)->orderBy('sort_order', 'asc');
    }

    /**
     * Relationship to enabled languages for this vendor.
     */
    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class, 'vendor_languages')
            ->withPivot(['is_default', 'is_active', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order', 'asc');
    }

    /**
     * Get configured supported languages for this vendor.
     */
    public function getSupportedLanguages(): array
    {
        if ($this->relationLoaded('vendorLanguages')) {
            $active = $this->vendorLanguages->where('is_active', true)->sortBy('sort_order');
            if ($active->isNotEmpty()) {
                return $active->map(fn ($vl) => [
                    'code' => $vl->language?->code ?? 'en',
                    'name' => $vl->language?->name ?? 'English',
                    'native_name' => $vl->language?->native_name ?? 'English',
                    'flag' => $vl->language?->flag ?? '🌐',
                    'direction' => $vl->language?->direction ?? 'ltr',
                    'is_default' => (bool) $vl->is_default,
                ])->values()->all();
            }
        } else {
            $relational = $this->vendorLanguages()->with('language')->where('is_active', true)->get();
            if ($relational->isNotEmpty()) {
                return $relational->map(fn ($vl) => [
                    'code' => $vl->language?->code ?? 'en',
                    'name' => $vl->language?->name ?? 'English',
                    'native_name' => $vl->language?->native_name ?? 'English',
                    'flag' => $vl->language?->flag ?? '🌐',
                    'direction' => $vl->language?->direction ?? 'ltr',
                    'is_default' => (bool) $vl->is_default,
                ])->values()->all();
            }
        }

        if (! empty($this->supported_languages) && is_array($this->supported_languages)) {
            return $this->supported_languages;
        }

        return [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'flag' => '🇬🇧', 'direction' => 'ltr', 'is_default' => true],
            ['code' => 'hy', 'name' => 'Armenian', 'native_name' => 'Հայերեն', 'flag' => '🇦🇲', 'direction' => 'ltr', 'is_default' => false],
            ['code' => 'ru', 'name' => 'Russian', 'native_name' => 'Русский', 'flag' => '🇷🇺', 'direction' => 'ltr', 'is_default' => false],
        ];
    }

    /**
     * Get the default language code for this vendor.
     */
    public function getDefaultLanguageCode(): string
    {
        $supported = $this->getSupportedLanguages();
        foreach ($supported as $lang) {
            if (! empty($lang['is_default'])) {
                return $lang['code'];
            }
        }

        return $supported[0]['code'] ?? 'en';
    }

    /**
     * Get an array of only language codes supported by this vendor (e.g. ['hy', 'en', 'ru']).
     *
     * @return array<int, string>
     */
    public function getSupportedLanguageCodes(): array
    {
        return array_values(array_filter(array_map(fn ($l) => is_array($l) ? ($l['code'] ?? '') : (string) $l, $this->getSupportedLanguages())));
    }

    /**
     * Ensure this vendor has relational vendor_languages initialized from supported_languages JSON or system defaults.
     */
    public function ensureVendorLanguagesInitialized(): void
    {
        if ($this->vendorLanguages()->count() > 0) {
            return;
        }

        $source = ! empty($this->supported_languages) && is_array($this->supported_languages)
            ? $this->supported_languages
            : [
                ['code' => 'en', 'name' => 'English', 'flag' => '🇬🇧', 'is_default' => true],
                ['code' => 'hy', 'name' => 'Armenian', 'flag' => '🇦🇲', 'is_default' => false],
                ['code' => 'ru', 'name' => 'Russian', 'flag' => '🇷🇺', 'is_default' => false],
            ];

        $order = 1;
        foreach ($source as $langData) {
            $code = is_array($langData) ? strtolower(trim($langData['code'] ?? '')) : strtolower(trim((string) $langData));
            if (empty($code)) {
                continue;
            }
            $name = is_array($langData) ? ($langData['name'] ?? strtoupper($code)) : strtoupper($code);
            $flag = is_array($langData) ? ($langData['flag'] ?? '🌐') : '🌐';
            $isDefault = is_array($langData) ? (! empty($langData['is_default']) || $order === 1) : ($order === 1);

            $language = Language::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'native_name' => $name,
                    'flag' => $flag,
                    'direction' => in_array($code, ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr',
                    'is_active' => true,
                    'is_default' => false,
                    'sort_order' => (Language::max('sort_order') ?? 0) + 1,
                ]
            );

            VendorLanguage::firstOrCreate(
                [
                    'vendor_id' => $this->id,
                    'language_id' => $language->id,
                ],
                [
                    'is_active' => true,
                    'is_default' => $isDefault,
                    'sort_order' => $order++,
                ]
            );
        }

        $this->unsetRelation('vendorLanguages');
    }

    /**
     * Add or update a language for this vendor, ensuring Language, VendorLanguage,
     * and the supported_languages JSON column stay 100% in sync.
     */
    public function syncLanguage(
        string $code,
        ?string $name = null,
        ?string $flag = null,
        bool $isActive = true,
        bool $isDefault = false
    ): VendorLanguage {
        $this->ensureVendorLanguagesInitialized();

        $code = strtolower(trim($code));

        $language = Language::firstOrCreate(
            ['code' => $code],
            [
                'name' => $name ?? strtoupper($code),
                'native_name' => $name ?? strtoupper($code),
                'flag' => $flag ?? '🌐',
                'direction' => in_array($code, ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr',
                'is_active' => true,
                'is_default' => false,
                'sort_order' => (Language::max('sort_order') ?? 0) + 1,
            ]
        );

        $langUpdates = [];
        if (! empty($name) && $language->name !== $name) {
            $langUpdates['name'] = $name;
            $langUpdates['native_name'] = $name;
        }
        if (! empty($flag) && $language->flag !== $flag) {
            $langUpdates['flag'] = $flag;
        }
        if (! empty($langUpdates)) {
            $language->update($langUpdates);
        }

        if ($isDefault) {
            $this->vendorLanguages()->update(['is_default' => false]);
        }

        $nextOrder = (VendorLanguage::where('vendor_id', $this->id)->max('sort_order') ?? 0) + 1;

        $vendorLang = VendorLanguage::updateOrCreate(
            [
                'vendor_id' => $this->id,
                'language_id' => $language->id,
            ],
            [
                'is_active' => $isActive,
                'is_default' => $isDefault,
                'sort_order' => $nextOrder,
            ]
        );

        $this->refreshSupportedLanguagesJson();

        // Keep AI Waiter configured languages in sync
        $aiConfig = $this->ai_waiter_config;
        if (is_array($aiConfig) && isset($aiConfig['languages']) && is_array($aiConfig['languages'])) {
            if ($isActive && ! in_array($code, $aiConfig['languages'], true)) {
                $aiConfig['languages'][] = $code;
                $this->updateQuietly(['ai_waiter_config' => $aiConfig]);
            }
        }

        return $vendorLang;
    }

    /**
     * Remove or deactivate a supported language for this vendor.
     */
    public function removeLanguage(string $code): bool
    {
        $this->ensureVendorLanguagesInitialized();

        $code = strtolower(trim($code));
        $language = Language::where('code', $code)->first();
        if (! $language) {
            return false;
        }

        $activeLangs = $this->getSupportedLanguages();
        if (count($activeLangs) <= 1) {
            return false;
        }

        $vl = $this->vendorLanguages()->where('language_id', $language->id)->first();
        if ($vl) {
            $wasDefault = (bool) $vl->is_default;
            $vl->update(['is_active' => false, 'is_default' => false]);

            if ($wasDefault) {
                $firstActive = $this->vendorLanguages()->where('is_active', true)->first();
                if ($firstActive) {
                    $firstActive->update(['is_default' => true]);
                }
            }
        }

        $this->refreshSupportedLanguagesJson();

        // Keep AI Waiter configured languages in sync
        $aiConfig = $this->ai_waiter_config;
        if (is_array($aiConfig) && isset($aiConfig['languages']) && is_array($aiConfig['languages'])) {
            $aiConfig['languages'] = array_values(array_filter($aiConfig['languages'], fn ($c) => $c !== $code));
            $this->updateQuietly(['ai_waiter_config' => $aiConfig]);
        }

        return true;
    }

    /**
     * Refresh and synchronize the supported_languages JSON column with current active vendor languages.
     */
    public function refreshSupportedLanguagesJson(): array
    {
        $this->unsetRelation('vendorLanguages');

        $active = $this->vendorLanguages()->with('language')->where('is_active', true)->orderBy('sort_order')->get();
        $formatted = $active->map(fn ($vl) => [
            'code' => $vl->language?->code ?? 'en',
            'name' => $vl->language?->name ?? 'English',
            'native_name' => $vl->language?->native_name ?? 'English',
            'flag' => $vl->language?->flag ?? '🌐',
            'direction' => $vl->language?->direction ?? 'ltr',
            'is_default' => (bool) $vl->is_default,
        ])->values()->all();

        $this->updateQuietly(['supported_languages' => $formatted]);

        if (class_exists(LocaleManager::class)) {
            try {
                app(LocaleManager::class)->clearCache();
            } catch (\Throwable) {
                // Ignore if in early bootstrap or testing
            }
        }

        return $formatted;
    }

    /**
     * Check if WhatsApp order button is enabled for this vendor.
     */
    public function hasWhatsAppOrdersEnabled(?Location $location = null): bool
    {
        if ($this->allow_whatsapp_orders === false) {
            return false;
        }

        if ($location && $location->allow_whatsapp_orders === false) {
            return false;
        }

        return true;
    }

    /**
     * Get vendor payment gateways configuration.
     */
    public function getPaymentSettings(): array
    {
        $defaults = [
            'cash_enabled' => true,
            'pos_terminal_enabled' => true,
            'online_enabled' => true,
            'default_method' => 'cash',
            'gateways' => [
                'idram' => [
                    'enabled' => false,
                    'title' => 'Idram Wallet / QR',
                    'merchant_id' => '',
                    'secret_key' => '',
                    'sandbox' => true,
                ],
                'telcell' => [
                    'enabled' => false,
                    'title' => 'Telcell Wallet',
                    'shop_id' => '',
                    'key' => '',
                    'sandbox' => true,
                ],
                'fastshift' => [
                    'enabled' => false,
                    'title' => 'FastShift',
                    'merchant_id' => '',
                    'api_key' => '',
                    'sandbox' => true,
                ],
                'arca' => [
                    'enabled' => false,
                    'title' => 'ArCa / Ameriabank vPOS',
                    'merchant_id' => '',
                    'terminal_id' => '',
                    'secret_key' => '',
                    'sandbox' => true,
                ],
                'stripe' => [
                    'enabled' => false,
                    'title' => 'Stripe (Cards / Apple Pay)',
                    'publishable_key' => '',
                    'secret_key' => '',
                    'sandbox' => true,
                ],
            ],
        ];

        $merged = array_replace_recursive($defaults, $this->payment_settings ?? []);

        // Decrypt / inject credentials from CredentialService for server-side processing
        $service = app(CredentialService::class);
        foreach (['idram' => 'secret_key', 'telcell' => 'key', 'fastshift' => 'api_key', 'arca' => 'secret_key', 'stripe' => 'secret_key'] as $gw => $type) {
            $secret = $service->get($this, $gw, $type);
            if ($secret !== null) {
                $merged['gateways'][$gw][$type] = $secret;
            }
        }
        $stripePub = $service->get($this, 'stripe', 'publishable_key');
        if ($stripePub !== null) {
            $merged['gateways']['stripe']['publishable_key'] = $stripePub;
        }

        return $merged;
    }

    /**
     * Get payment settings scrubbed of secrets for frontend/views.
     */
    public function getSafePaymentSettings(): array
    {
        $settings = $this->getPaymentSettings();
        $service = app(CredentialService::class);

        foreach (['idram' => 'secret_key', 'telcell' => 'key', 'fastshift' => 'api_key', 'arca' => 'secret_key', 'stripe' => 'secret_key'] as $gw => $type) {
            if (isset($settings['gateways'][$gw])) {
                $isConfigured = $service->has($this, $gw, $type);
                $settings['gateways'][$gw]['configured'] = $isConfigured;
                $settings['gateways'][$gw]['masked'] = $service->mask($this, $gw, $type);
                $settings['gateways'][$gw][$type] = ''; // Scrubbed
            }
        }

        return $settings;
    }

    /**
     * Check if a specific payment gateway is enabled for this vendor.
     */
    public function isPaymentMethodEnabled(string $method): bool
    {
        $settings = $this->getPaymentSettings();

        if ($method === 'cash') {
            return (bool) ($settings['cash_enabled'] ?? true);
        }
        if ($method === 'pos_terminal') {
            return (bool) ($settings['pos_terminal_enabled'] ?? true);
        }

        if (empty($settings['online_enabled'])) {
            return false;
        }

        return ! empty($settings['gateways'][$method]['enabled']);
    }

    /**
     * Check if at least one online payment method is enabled.
     */
    public function hasOnlinePaymentsEnabled(): bool
    {
        $settings = $this->getPaymentSettings();
        if (empty($settings['online_enabled'])) {
            return false;
        }

        foreach ($settings['gateways'] ?? [] as $gw) {
            if (! empty($gw['enabled'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get vendor CRM automation settings (Birthday discounts & SMS).
     */
    public function getCrmSettings(): array
    {
        $defaults = [
            'birthday_discount_enabled' => true,
            'birthday_discount_percent' => 15,
            'birthday_validity_days' => 3, // ±3 days from birthday
            'birthday_sms_enabled' => false,
            'birthday_sms_template' => 'Շնորհավոր Ձեր ծննդյան օրը {NAME}։ Ձեզ սպասում է {DISCOUNT}% զեղչ {VENDOR}-ում։',
            'order_ready_sms_enabled' => false,
            'sms_provider' => 'mobipace', // mobipace, smsam, twilio, log
            'sms_api_key' => '',
            'sms_sender_id' => 'QRMENU',
        ];

        $merged = array_merge($defaults, $this->crm_settings ?? []);

        $secret = app(CredentialService::class)->get($this, 'sms', 'api_key');
        if ($secret !== null) {
            $merged['sms_api_key'] = $secret;
        }

        return $merged;
    }

    /**
     * Get CRM settings scrubbed of secrets for frontend/views.
     */
    public function getSafeCrmSettings(): array
    {
        $settings = $this->getCrmSettings();
        $service = app(CredentialService::class);
        $settings['configured'] = $service->has($this, 'sms', 'api_key');
        $settings['masked'] = $service->mask($this, 'sms', 'api_key');
        $settings['sms_api_key'] = ''; // Scrubbed

        return $settings;
    }

    /**
     * Get vendor thermal printer settings.
     */
    public function getThermalPrinterSettings(): array
    {
        return array_merge([
            'auto_print_live_orders' => false,
            'paper_width' => '80mm', // 58mm or 80mm
            'header_title' => $this->name,
            'footer_text' => 'Շնորհակալություն այցելության համար!',
            'print_customer_info' => true,
            'print_prices' => true,
            'copies' => 1,
        ], $this->thermal_printer_settings ?? []);
    }

    /**
     * Get visual floor plan tables and halls layout data.
     */
    public function getFloorPlanData(): array
    {
        if (! empty($this->floor_plan_data) && is_array($this->floor_plan_data)) {
            return $this->floor_plan_data;
        }

        // Default layout generator using location table count
        $halls = [
            ['id' => 'main', 'name' => 'Գլխավոր Սրահ', 'color' => '#3b82f6'],
            ['id' => 'terrace', 'name' => 'Տեռաս / Պատշգամբ', 'color' => '#10b981'],
            ['id' => 'vip', 'name' => 'VIP Սրահ', 'color' => '#f59e0b'],
        ];

        $location = $this->locations->first();
        $totalTables = $location?->table_count ?? 12;

        $tables = [];
        for ($i = 1; $i <= $totalTables; $i++) {
            $hallId = $i <= 6 ? 'main' : ($i <= 10 ? 'terrace' : 'vip');
            $tables[] = [
                'id' => $i,
                'number' => (string) $i,
                'hall_id' => $hallId,
                'capacity' => ($i % 3 === 0) ? 6 : 4,
                'shape' => ($i % 4 === 0) ? 'round' : 'square',
                'x' => (($i - 1) % 4) * 140 + 40,
                'y' => floor(($i - 1) / 4) * 140 + 40,
            ];
        }

        return [
            'halls' => $halls,
            'tables' => $tables,
        ];
    }

    /**
     * Get maximum desktop container width for digital menu storefront (e.g. 600px).
     */
    public function getDesktopMaxWidth(): string
    {
        $val = trim((string) ($this->desktop_max_width ?? '600px'));
        if ($val === '' || $val === '0') {
            return '600px';
        }
        if (is_numeric($val)) {
            return $val.'px';
        }

        return $val;
    }
}
