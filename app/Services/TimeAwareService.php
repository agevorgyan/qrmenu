<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Carbon\Carbon;

class TimeAwareService
{
    /**
     * Resolve the restaurant's configured local time.
     */
    public function getVendorLocalTime(Vendor $vendor, ?Carbon $now = null): Carbon
    {
        $tz = $this->resolveVendorTimezone($vendor);

        if ($now !== null) {
            return $now->copy()->setTimezone($tz);
        }

        return Carbon::now($tz);
    }

    /**
     * Resolve vendor timezone with robust fallback.
     */
    public function resolveVendorTimezone(Vendor $vendor): string
    {
        $tz = trim((string) $vendor->timezone);

        if (! empty($tz) && in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            return $tz;
        }

        return (string) config('app.timezone', 'UTC');
    }

    public function resolveTimezone(Vendor $vendor): string
    {
        return $this->resolveVendorTimezone($vendor);
    }

    /**
     * Determine the current time-of-day slice.
     *
     * - morning: 06:00 - 11:59
     * - daytime: 12:00 - 17:59
     * - evening: 18:00 - 22:59
     * - late_night: 23:00 - 05:59
     */
    public function getTimeSlice(Vendor $vendor, ?Carbon $now = null): string
    {
        $local = $this->getVendorLocalTime($vendor, $now);
        $hour = (int) $local->format('G'); // 0 to 23

        if ($hour >= 6 && $hour < 12) {
            return 'morning';
        }

        if ($hour >= 12 && $hour < 18) {
            return 'daytime';
        }

        if ($hour >= 18 && $hour < 23) {
            return 'evening';
        }

        return 'late_night';
    }

    /**
     * Get natural time-aware greeting in customer's selected language.
     * Considers entry context (table QR vs general menu) and session continuity.
     */
    public function getGreeting(
        Vendor $vendor,
        string $lang,
        string $entryContext = 'general_menu',
        ?string $tableNumber = null,
        bool $isReturning = false,
        ?Carbon $now = null
    ): string {
        $slice = $this->getTimeSlice($vendor, $now);
        $waiterName = $vendor->getAiWaiterName();
        $lang = strtolower(trim($lang));

        // Check for vendor-customized greetings in ai_waiter_config
        $customGreetings = $vendor->getAiWaiterConfig()['greetings'] ?? [];
        if (! empty($customGreetings[$lang][$slice])) {
            $template = (string) $customGreetings[$lang][$slice];

            return str_replace(
                [':waiter', ':vendor', ':table'],
                [$waiterName, $vendor->name, (string) $tableNumber],
                $template
            );
        }

        // Table QR greeting: brief, natural, non-blocking, directly references table
        if ($entryContext === 'table_qr' && ! empty($tableNumber)) {
            if ($isReturning) {
                return match ($lang) {
                    'hy' => "Բարի վերադարձ: Ինչո՞վ կարող եմ օգնել Սեղան #{$tableNumber}-ում:",
                    'ru' => "С возвращением! Чем могу помочь за Столиком #{$tableNumber}?",
                    default => "Welcome back! How may I assist you at Table #{$tableNumber}?",
                };
            }

            return match ($slice) {
                'morning' => match ($lang) {
                    'hy' => "Բարի լույս: Ես {$waiterName}-ն եմ: Մենյուն պատրաստ է Ձեր տրամադրության տակ (Սեղան #{$tableNumber}):",
                    'ru' => "Доброе утро! Я {$waiterName}. Меню к вашим услугам (Столик #{$tableNumber}):",
                    default => "Good morning! I am {$waiterName}. The menu is ready for you at Table #{$tableNumber}:",
                },
                'daytime' => match ($lang) {
                    'hy' => "Բարի օր: Բարի գալուստ {$vendor->name} (Սեղան #{$tableNumber}): Մենյուն Ձեր տրամադրության տակ է, ի՞նչ կցանկանաք համտեսել:",
                    'ru' => "Добрый день! Добро пожаловать в {$vendor->name} (Столик #{$tableNumber}). Меню к вашим услугам, что бы вы хотели заказать?",
                    default => "Good afternoon! Welcome to {$vendor->name} (Table #{$tableNumber}). The menu is ready, what would you like to enjoy?",
                },
                'evening' => match ($lang) {
                    'hy' => "Բարի երեկո: Բարի ախորժակ Սեղան #{$tableNumber}-ում: Կօգնե՞մ ընտրել հաճելի ընթրիք կամ ըմպելիք:",
                    'ru' => "Добрый вечер! Приятного вечера за Столиком #{$tableNumber}. Помочь с выбором ужина или напитка?",
                    default => "Good evening! Enjoy your dining at Table #{$tableNumber}. May I assist with dinner or drinks?",
                },
                default => match ($lang) {
                    'hy' => "Բարի գիշեր: Մենյուն բաց է Սեղան #{$tableNumber}-ի համար: Ինչո՞վ կարող եմ օգնել այս ուշ ժամին:",
                    'ru' => "Доброй ночи! Меню открыто для Столика #{$tableNumber}. Чем могу помочь в этот поздний час?",
                    default => "Good night! The menu is open at Table #{$tableNumber}. How can I assist you tonight?",
                },
            };
        }

        // General Menu entry greeting
        if ($isReturning) {
            return match ($lang) {
                'hy' => "Բարի վերադարձ {$vendor->name}: Ինչո՞վ կարող եմ օգնել Ձեզ այսօր:",
                'ru' => "С возвращением в {$vendor->name}! Чем могу помочь вам сегодня?",
                default => "Welcome back to {$vendor->name}! How may I help you today?",
            };
        }

        return match ($slice) {
            'morning' => match ($lang) {
                'hy' => 'Բարի լույս։ Ի՞նչ կցանկանաք այսօր համտեսել։',
                'ru' => 'Доброе утро! Что бы вы хотели попробовать сегодня?',
                default => 'Good morning! What would you like to enjoy today?',
            },
            'daytime' => match ($lang) {
                'hy' => 'Բարի օր։ Կարո՞ղ եմ օգնել Ձեզ ընտրել։',
                'ru' => 'Добрый день! Могу я помочь вам выбрать блюдо?',
                default => 'Good afternoon! How may I assist you with the menu today?',
            },
            'evening' => match ($lang) {
                'hy' => 'Բարի երեկո։ Ի՞նչ համեղ բան կառաջարկեմ Ձեզ։',
                'ru' => 'Добрый вечер! Что вкусного предложить вам к ужину?',
                default => 'Good evening! What delicious dish or drink may I recommend?',
            },
            default => match ($lang) {
                'hy' => 'Բարի գիշեր։ Ի՞նչ թեթև կամ համեղ բան կցանկանայիք այս ուշ ժամին։',
                'ru' => 'Доброй ночи! Ищете что-то аппетитное или напиток на поздний вечер?',
                default => 'Good night! Looking for a late-night treat or drink?',
            },
        };
    }

    /**
     * Get all configured or default meal periods for the vendor.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getMealPeriods(Vendor $vendor): array
    {
        $config = $vendor->getAiWaiterConfig();
        $configured = $config['meal_periods'] ?? null;

        if (is_array($configured) && ! empty($configured)) {
            return $configured;
        }

        // Sensible defaults
        return [
            'breakfast' => [
                'key' => 'breakfast',
                'name' => 'Breakfast',
                'names' => ['hy' => 'Նախաճաշ', 'en' => 'Breakfast', 'ru' => 'Завтрак'],
                'start_time' => '07:00',
                'end_time' => '11:30',
                'days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
                'is_active' => true,
                'categories' => [],
                'priority_tags' => ['breakfast', 'eggs', 'coffee', 'pancakes', 'նախաճաշ', 'սուրճ'],
            ],
            'brunch' => [
                'key' => 'brunch',
                'name' => 'Brunch',
                'names' => ['hy' => 'Բրանչ', 'en' => 'Brunch', 'ru' => 'Бранч'],
                'start_time' => '11:00',
                'end_time' => '13:00',
                'days' => ['sat', 'sun'],
                'is_active' => true,
                'categories' => [],
                'priority_tags' => ['brunch', 'bakery', 'omelette', 'բրանչ'],
            ],
            'lunch' => [
                'key' => 'lunch',
                'name' => 'Lunch',
                'names' => ['hy' => 'Ճաշ', 'en' => 'Lunch', 'ru' => 'Обед'],
                'start_time' => '12:00',
                'end_time' => '16:30',
                'days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
                'is_active' => true,
                'categories' => [],
                'priority_tags' => ['lunch', 'soup', 'salad', 'business-lunch', 'ճաշ', 'ապուր'],
            ],
            'afternoon_snacks' => [
                'key' => 'afternoon_snacks',
                'name' => 'Afternoon Snacks',
                'names' => ['hy' => 'Թեթև նախուտեստներ', 'en' => 'Afternoon Snacks', 'ru' => 'Полдник и десерты'],
                'start_time' => '16:30',
                'end_time' => '18:30',
                'days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
                'is_active' => true,
                'categories' => [],
                'priority_tags' => ['snack', 'dessert', 'coffee', 'tea', 'թեյ', 'սուրճ', 'դեսերտ'],
            ],
            'dinner' => [
                'key' => 'dinner',
                'name' => 'Dinner',
                'names' => ['hy' => 'Ընթրիք', 'en' => 'Dinner', 'ru' => 'Ужин'],
                'start_time' => '18:30',
                'end_time' => '23:00',
                'days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
                'is_active' => true,
                'categories' => [],
                'priority_tags' => ['dinner', 'main', 'steak', 'wine', 'barbecue', 'ընթրիք', 'տաք ուտեստ', 'գինի'],
            ],
            'late_night' => [
                'key' => 'late_night',
                'name' => 'Late-Night Menu',
                'names' => ['hy' => 'Գիշերային մենյու', 'en' => 'Late-Night Menu', 'ru' => 'Ночное меню'],
                'start_time' => '23:00',
                'end_time' => '05:59',
                'days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
                'is_active' => true,
                'categories' => [],
                'priority_tags' => ['cocktail', 'late-night', 'beer', 'fast-food', 'կոկտեյլ', 'գարեջուր'],
            ],
        ];
    }

    /**
     * Resolve the active meal period for the current local time.
     * Handles crossing midnight and day-of-week checks.
     *
     * @return array<string, mixed>|null
     */
    public function getActiveMealPeriod(Vendor $vendor, ?Carbon $now = null): ?array
    {
        $config = $vendor->getAiWaiterConfig();
        if (isset($config['time_aware_recommendations']) && ! $config['time_aware_recommendations']) {
            return null;
        }

        $local = $this->getVendorLocalTime($vendor, $now);
        $currentTime = $local->format('H:i');
        $currentDay = strtolower($local->format('D')); // mon, tue, etc.
        $currentDayIndex = (int) $local->format('w'); // 0 (Sunday) to 6 (Saturday)

        $periods = $this->getMealPeriods($vendor);

        foreach ($periods as $key => $period) {
            $period['id'] = $period['id'] ?? $period['key'] ?? (string) $key;
            $period['key'] = $period['key'] ?? $period['id'] ?? (string) $key;

            if (isset($period['is_active']) && ! $period['is_active']) {
                continue;
            }

            // Check days of week
            if (! empty($period['days']) && is_array($period['days'])) {
                $dayMatches = false;
                foreach ($period['days'] as $d) {
                    if (is_numeric($d) && (int) $d === $currentDayIndex) {
                        $dayMatches = true;
                        break;
                    }
                    if (is_string($d) && str_starts_with(strtolower(trim($d)), substr($currentDay, 0, 3))) {
                        $dayMatches = true;
                        break;
                    }
                }
                if (! $dayMatches) {
                    continue;
                }
            }

            $start = substr($period['start_time'] ?? '00:00', 0, 5);
            $end = substr($period['end_time'] ?? '23:59', 0, 5);

            if ($this->isTimeInRange($currentTime, $start, $end)) {
                return $period;
            }
        }

        return null;
    }

    /**
     * Check if a given HH:MM time falls between start and end (handles midnight crossing).
     */
    public function isTimeInRange(string $current, string $start, string $end): bool
    {
        if ($start <= $end) {
            return $current >= $start && $current <= $end;
        }

        // Crosses midnight, e.g. 23:00 to 05:00
        return $current >= $start || $current <= $end;
    }

    /**
     * Check if product matches the active meal period.
     */
    public function isProductRelevantToMealPeriod(Product $product, array $mealPeriod, string $lang = 'en'): bool
    {
        // 1. Direct category match
        $catIds = array_map('intval', (array) ($mealPeriod['categories'] ?? []));
        if (! empty($catIds) && in_array((int) $product->category_id, $catIds, true)) {
            return true;
        }

        // 2. Priority tag match
        $priorityTags = array_map('mb_strtolower', (array) ($mealPeriod['priority_tags'] ?? []));
        if (! empty($priorityTags)) {
            $prodTags = array_map('mb_strtolower', (array) ($product->dietary_tags ?? []));
            $aiTags = array_map('mb_strtolower', (array) ($product->ai_tags ?? []));
            $catName = mb_strtolower($product->category?->name ?? '');
            $prodName = mb_strtolower($product->name.' '.$product->getTranslatedName($lang));

            foreach ($priorityTags as $tag) {
                if (in_array($tag, $prodTags, true) || in_array($tag, $aiTags, true)) {
                    return true;
                }
                if (str_contains($catName, $tag) || str_contains($prodName, $tag)) {
                    return true;
                }
            }
        }

        return false;
    }
}
