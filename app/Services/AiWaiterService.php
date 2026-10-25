<?php

namespace App\Services;

use App\Events\WaiterCalled;
use App\Models\AiWaiterSession;
use App\Models\Category;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Vendor;
use App\Models\WaiterCall;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AiWaiterService
{
    public function __construct(
        protected ?AiGatewayService $aiGateway = null,
        protected ?ReservationAvailabilityService $reservationAvailabilityService = null,
        protected ?EventInquiryNotificationService $eventInquiryNotificationService = null,
        protected ?TimeAwareService $timeAwareService = null,
    ) {
        $this->aiGateway = $aiGateway ?? app(AiGatewayService::class);
        $this->reservationAvailabilityService = $reservationAvailabilityService ?? app(ReservationAvailabilityService::class);
        $this->eventInquiryNotificationService = $eventInquiryNotificationService ?? app(EventInquiryNotificationService::class);
        $this->timeAwareService = $timeAwareService ?? app(TimeAwareService::class);
    }

    /**
     * Get the standardized question library with translations and icons.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getQuestionLibrary(string $lang = 'en'): array
    {
        return [
            'mood' => [
                'key' => 'mood',
                'priority' => 1,
                'title' => match ($lang) {
                    'hy' => '🍽️ Ի՞նչ տրամադրությամբ եք այսօր։',
                    'ru' => 'С каким настроением вы сегодня?',
                    'fr' => 'De quoi avez-vous envie aujourd\'hui ?',
                    'de' => 'Worauf haben Sie heute Lust?',
                    'es' => '¿Qué le apetece hoy?',
                    default => 'What are you in the mood for today?',
                },
                'subtitle' => match ($lang) {
                    'hy' => 'Ընտրեք համային ուղղությունը կամ գրեք Ձեր տարբերակը',
                    'ru' => 'Выберите вкусовое направление или напишите своими словами',
                    'fr' => 'Choisissez une orientation ou écrivez avec vos propres mots',
                    'de' => 'Wählen Sie eine Geschmacksrichtung oder beschreiben Sie sie',
                    'es' => 'Elija una dirección de sabor o escriba sus preferencias',
                    default => 'Select a flavor vibe or tell us in your own words',
                },
                'options' => [
                    ['value' => 'meat', 'emoji' => '🥩', 'label' => match ($lang) {
                        'hy' => 'Մսային', 'ru' => 'Мясное и сытное', 'fr' => 'Viande et gourmand', 'de' => 'Fleischig & Herzhaft', 'es' => 'Carne y sabroso', default => 'Meaty & Savory'
                    }],
                    ['value' => 'light', 'emoji' => '🍗', 'label' => match ($lang) {
                        'hy' => 'Թեթև', 'ru' => 'Легкое и нежное', 'fr' => 'Léger et tendre', 'de' => 'Leicht & Zart', 'es' => 'Ligero y tierno', default => 'Light & Tender'
                    }],
                    ['value' => 'spicy', 'emoji' => '🌶️', 'label' => match ($lang) {
                        'hy' => 'Կծու', 'ru' => 'Острое и пикантное', 'fr' => 'Épicé et relevé', 'de' => 'Scharf & Würzig', 'es' => 'Picante y sabroso', default => 'Spicy & Bold'
                    }],
                    ['value' => 'fresh', 'emoji' => '🥗', 'label' => match ($lang) {
                        'hy' => 'Թարմ և առողջ', 'ru' => 'Свежее и полезное', 'fr' => 'Frais et sain', 'de' => 'Frisch & Gesund', 'es' => 'Fresco y saludable', default => 'Fresh & Healthy'
                    }],
                    ['value' => 'sweet', 'emoji' => '🍰', 'label' => match ($lang) {
                        'hy' => 'Քաղցր', 'ru' => 'Сладкое и десертное', 'fr' => 'Sucré et dessert', 'de' => 'Süß & Verführerisch', 'es' => 'Dulce y delicioso', default => 'Sweet & Indulgent'
                    }],
                    ['value' => 'surprise', 'emoji' => '✨', 'label' => match ($lang) {
                        'hy' => 'Դու ընտրիր', 'ru' => 'Удиви меня', 'fr' => 'Surprenez-moi', 'de' => 'Überrasch mich', 'es' => 'Sorpréndeme', default => 'Surprise Me'
                    }],
                ],
            ],
            'preference' => [
                'key' => 'preference',
                'priority' => 2,
                'title' => match ($lang) {
                    'hy' => 'Ի՞նչ եք նախընտրում։',
                    'ru' => 'Что вы предпочитаете в основе?',
                    'fr' => 'Que préférez-vous comme base ?',
                    'de' => 'Was bevorzugen Sie als Hauptzutat?',
                    'es' => '¿Qué prefiere como ingrediente principal?',
                    default => 'What type of dish do you prefer?',
                },
                'subtitle' => match ($lang) {
                    'hy' => 'Ընտրեք հիմնական բաղադրիչը',
                    'ru' => 'Выберите ключевой ингредиент',
                    'fr' => 'Choisissez l\'ingrédient clé',
                    'de' => 'Wählen Sie die Hauptzutat',
                    'es' => 'Elija el ingrediente clave',
                    default => 'Pick your primary ingredient preference',
                },
                'options' => [
                    ['value' => 'beef', 'emoji' => '🥩', 'label' => match ($lang) {
                        'hy' => 'Միս / Տավար', 'ru' => 'Мясо / Говядина', 'fr' => 'Viande / Bœuf', 'de' => 'Fleisch / Rind', 'es' => 'Carne / Ternera', default => 'Meat / Beef'
                    }],
                    ['value' => 'chicken', 'emoji' => '🍗', 'label' => match ($lang) {
                        'hy' => 'Հավ', 'ru' => 'Курица / Птица', 'fr' => 'Poulet / Volaille', 'de' => 'Hähnchen / Geflügel', 'es' => 'Pollo / Aves', default => 'Chicken / Poultry'
                    }],
                    ['value' => 'fish', 'emoji' => '🐟', 'label' => match ($lang) {
                        'hy' => 'Ձուկ / Ծովամթերք', 'ru' => 'Рыба и морепродукты', 'fr' => 'Poisson & Fruits de mer', 'de' => 'Fisch & Meeresfrüchte', 'es' => 'Pescado y mariscos', default => 'Fish & Seafood'
                    }],
                    ['value' => 'vegetarian', 'emoji' => '🥦', 'label' => match ($lang) {
                        'hy' => 'Բուսական', 'ru' => 'Овощи и зелень', 'fr' => 'Végétarien / Légumes', 'de' => 'Vegetarisch / Gemüse', 'es' => 'Vegetariano / Verduras', default => 'Vegetarian / Greens'
                    }],
                    ['value' => 'all', 'emoji' => '🍽️', 'label' => match ($lang) {
                        'hy' => 'Ամեն ինչ', 'ru' => 'Любое блюдо', 'fr' => 'Ouvert à tout', 'de' => 'Offen für alles', 'es' => 'Abierto a todo', default => 'Open to Everything'
                    }],
                ],
            ],
            'spiciness' => [
                'key' => 'spiciness',
                'priority' => 3,
                'title' => match ($lang) {
                    'hy' => 'Որքա՞ն կծու եք սիրում։',
                    'ru' => 'Насколько острую еду вы любите?',
                    'fr' => 'Quel niveau de piquant préférez-vous ?',
                    'de' => 'Wie scharf mögen Sie Ihr Essen?',
                    'es' => '¿Cómo de picante le gusta la comida?',
                    default => 'How spicy do you like your food?',
                },
                'subtitle' => match ($lang) {
                    'hy' => 'Մենք կընտրենք համապատասխան կծվության մակարդակը',
                    'ru' => 'Мы подберем идеальный уровень остроты',
                    'fr' => 'Nous adapterons le niveau d\'épices pour vous',
                    'de' => 'Wir passen die Schärfe für Sie an',
                    'es' => 'Ajustaremos el nivel de picante para usted',
                    default => 'We will calibrate the spice level for you',
                },
                'options' => [
                    ['value' => 'none', 'emoji' => '🙅', 'label' => match ($lang) {
                        'hy' => 'Չեմ սիրում կծու', 'ru' => 'Совсем не острое', 'fr' => 'Pas épicé du tout', 'de' => 'Gar nicht scharf', 'es' => 'Nada picante', default => 'Not Spicy at all'
                    }],
                    ['value' => 'mild', 'emoji' => '🌶️', 'label' => match ($lang) {
                        'hy' => 'Թեթև կծու', 'ru' => 'Слегка пикантное', 'fr' => 'Légèrement épicé', 'de' => 'Mild scharf', 'es' => 'Ligeramente picante', default => 'Mildly Spicy'
                    }],
                    ['value' => 'medium', 'emoji' => '🌶️🌶️', 'label' => match ($lang) {
                        'hy' => 'Միջին', 'ru' => 'Средней остроты', 'fr' => 'Moyennement épicé', 'de' => 'Mittelscharf', 'es' => 'Picante medio', default => 'Medium Spice'
                    }],
                    ['value' => 'hot', 'emoji' => '🔥', 'label' => match ($lang) {
                        'hy' => 'Շատ կծու', 'ru' => 'Очень острое', 'fr' => 'Très épicé', 'de' => 'Sehr scharf', 'es' => 'Muy picante', default => 'Very Spicy'
                    }],
                ],
            ],
            'occasion' => [
                'key' => 'occasion',
                'priority' => 4,
                'title' => match ($lang) {
                    'hy' => 'Այսօր ինչպիսի՞ առիթ է։',
                    'ru' => 'Какой сегодня повод для визита?',
                    'fr' => 'Quelle est l\'occasion aujourd\'hui ?',
                    'de' => 'Was ist der Anlass heute?',
                    'es' => '¿Cuál es la ocasión hoy?',
                    default => 'What is the dining occasion today?',
                },
                'subtitle' => match ($lang) {
                    'hy' => 'Կօգնի ընտրել չափաբաժինը և մատուցման ոճը',
                    'ru' => 'Поможет составить идеальное сочетание порций',
                    'fr' => 'Nous aide à adapter les portions et les accords',
                    'de' => 'Hilft uns, Portionen und Empfehlungen anzupassen',
                    'es' => 'Nos ayuda a adaptar porciones y combinaciones',
                    default => 'Helps us tailor portion sizes and pairings',
                },
                'options' => [
                    ['value' => 'solo', 'emoji' => '👤', 'label' => match ($lang) {
                        'hy' => 'Միայն ինձ համար', 'ru' => 'Только для меня', 'fr' => 'Juste pour moi', 'de' => 'Nur für mich', 'es' => 'Solo para mí', default => 'Just for Me'
                    }],
                    ['value' => 'couple', 'emoji' => '💑', 'label' => match ($lang) {
                        'hy' => 'Զույգով', 'ru' => 'Вдвоем / Романтика', 'fr' => 'En couple / Romantique', 'de' => 'Zu zweit / Date', 'es' => 'En pareja / Cita', default => 'Date / Couple'
                    }],
                    ['value' => 'friends', 'emoji' => '👥', 'label' => match ($lang) {
                        'hy' => 'Ընկերներով', 'ru' => 'С друзьями', 'fr' => 'Entre amis', 'de' => 'Mit Freunden', 'es' => 'Con amigos', default => 'With Friends'
                    }],
                    ['value' => 'family', 'emoji' => '👨‍👩‍👧', 'label' => match ($lang) {
                        'hy' => 'Ընտանիքով', 'ru' => 'Семьей', 'fr' => 'En famille', 'de' => 'Mit der Familie', 'es' => 'En familia', default => 'Family Dinner'
                    }],
                    ['value' => 'celebration', 'emoji' => '🎉', 'label' => match ($lang) {
                        'hy' => 'Տոնական', 'ru' => 'Праздник / Событие', 'fr' => 'Fête / Célébration', 'de' => 'Feier / Party', 'es' => 'Celebración / Fiesta', default => 'Celebration / Party'
                    }],
                ],
            ],
            'budget' => [
                'key' => 'budget',
                'priority' => 5,
                'title' => match ($lang) {
                    'hy' => 'Մոտավորապես ի՞նչ բյուջե եք նախատեսում։',
                    'ru' => 'Какой бюджет вы планируете?',
                    'fr' => 'Quel est votre budget approximatif ?',
                    'de' => 'Was ist Ihr ungefähres Budget?',
                    'es' => '¿Cuál es su presupuesto aproximado?',
                    default => 'What is your approximate budget?',
                },
                'subtitle' => match ($lang) {
                    'hy' => 'Մեկ անձի համար նախատեսված',
                    'ru' => 'Примерная сумма на человека',
                    'fr' => 'Estimation par personne',
                    'de' => 'Schätzung pro Person',
                    'es' => 'Estimación por persona',
                    default => 'Per person estimate',
                },
                'options' => [
                    ['value' => '5000', 'emoji' => '💵', 'label' => match ($lang) {
                        'hy' => 'Մինչև 5,000 ֏', 'ru' => 'До 5,000 ֏', 'fr' => 'Jusqu\'à 5 000', 'de' => 'Bis zu 5.000', 'es' => 'Hasta 5.000', default => 'Up to 5,000'
                    }],
                    ['value' => '10000', 'emoji' => '💳', 'label' => match ($lang) {
                        'hy' => '5,000–10,000 ֏', 'ru' => '5,000 – 10,000 ֏', 'fr' => '5 000 – 10 000', 'de' => '5.000 – 10.000', 'es' => '5.000 – 10.000', default => '5,000 – 10,000'
                    }],
                    ['value' => '20000', 'emoji' => '💎', 'label' => match ($lang) {
                        'hy' => '10,000–20,000 ֏', 'ru' => '10,000 – 20,000 ֏', 'fr' => '10 000 – 20 000', 'de' => '10.000 – 20.000', 'es' => '10.000 – 20.000', default => '10,000 – 20,000'
                    }],
                    ['value' => 'any', 'emoji' => '🤷', 'label' => match ($lang) {
                        'hy' => 'Կարևոր չէ', 'ru' => 'Не имеет значения', 'fr' => 'Peu importe', 'de' => 'Egal', 'es' => 'No importa', default => "Doesn't Matter"
                    }],
                ],
            ],
            'drink' => [
                'key' => 'drink',
                'priority' => 6,
                'title' => match ($lang) {
                    'hy' => 'Ի՞նչ կցանկանաք խմել։',
                    'ru' => 'Что бы вы хотели выпить?',
                    'fr' => 'Que souhaiteriez-vous boire ?',
                    'de' => 'Was möchten Sie trinken?',
                    'es' => '¿Qué le gustaría beber?',
                    default => 'What would you like to drink?',
                },
                'subtitle' => match ($lang) {
                    'hy' => 'Ընտրեք ըմպելիքի տեսակը',
                    'ru' => 'Выберите напиток к блюду',
                    'fr' => 'Choisissez votre préférence de boisson',
                    'de' => 'Wählen Sie Ihr Getränk',
                    'es' => 'Elija su preferencia de bebida',
                    default => 'Choose your beverage preference',
                },
                'options' => [
                    ['value' => 'water', 'emoji' => '💧', 'label' => match ($lang) {
                        'hy' => 'Ջուր', 'ru' => 'Вода', 'fr' => 'Eau', 'de' => 'Wasser', 'es' => 'Agua', default => 'Water'
                    }],
                    ['value' => 'soft', 'emoji' => '🥤', 'label' => match ($lang) {
                        'hy' => 'Զովացուցիչ', 'ru' => 'Прохладительные', 'fr' => 'Boissons fraîches', 'de' => 'Erfrischungsgetränke', 'es' => 'Refrescos', default => 'Soft Drinks'
                    }],
                    ['value' => 'lemonade', 'emoji' => '🍋', 'label' => match ($lang) {
                        'hy' => 'Լիմոնադ', 'ru' => 'Лимонад', 'fr' => 'Limonade', 'de' => 'Limonade', 'es' => 'Limonada', default => 'Fresh Lemonade'
                    }],
                    ['value' => 'coffee', 'emoji' => '☕', 'label' => match ($lang) {
                        'hy' => 'Սուրճ / Թեյ', 'ru' => 'Кофе / Чай', 'fr' => 'Café / Thé', 'de' => 'Kaffee / Tee', 'es' => 'Café / Té', default => 'Coffee / Tea'
                    }],
                    ['value' => 'cocktail', 'emoji' => '🍸', 'label' => match ($lang) {
                        'hy' => 'Կոկտեյլ', 'ru' => 'Коктейли', 'fr' => 'Cocktails', 'de' => 'Cocktails', 'es' => 'Cócteles', default => 'Cocktails'
                    }],
                    ['value' => 'wine', 'emoji' => '🍷', 'label' => match ($lang) {
                        'hy' => 'Գինի', 'ru' => 'Вино', 'fr' => 'Vin', 'de' => 'Wein', 'es' => 'Vino', default => 'Wine'
                    }],
                    ['value' => 'beer', 'emoji' => '🍺', 'label' => match ($lang) {
                        'hy' => 'Գարեջուր', 'ru' => 'Пиво', 'fr' => 'Bière', 'de' => 'Bier', 'es' => 'Cerveza', default => 'Beer'
                    }],
                    ['value' => 'surprise', 'emoji' => '✨', 'label' => match ($lang) {
                        'hy' => 'Դու ընտրիր', 'ru' => 'На твой выбор', 'fr' => 'À vous de choisir', 'de' => 'Deine Wahl', 'es' => 'Tú eliges', default => 'You choose'
                    }],
                ],
            ],
        ];
    }

    /**
     * Determine next best question dynamically based on current profile and information gain.
     *
     * @param  array<string, mixed>  $preferences
     * @param  array<int, string>  $answeredKeys
     * @return array<string, mixed>|null Returns null if AI has sufficient confidence to stop questioning
     */
    public function getNextQuestion(
        Vendor $vendor,
        array $preferences,
        array $answeredKeys,
        string $lang = 'en'
    ): ?array {
        $library = $this->getQuestionLibrary($lang);
        $config = $vendor->getAiWaiterConfig();
        $configuredQuestions = $config['questions'] ?? [];

        // Check if we should stop questioning early (sufficient information gain)
        $answeredCount = count($answeredKeys);
        if ($this->hasSufficientInformation($preferences, $answeredCount)) {
            return null;
        }

        // Hard cap: maximum 5 questions
        if ($answeredCount >= 5) {
            return null;
        }

        // Determine question priority ordering
        $candidateKeys = [];
        foreach ($library as $key => $q) {
            if (in_array($key, $answeredKeys, true)) {
                continue;
            }

            // Check if question is disabled in vendor settings
            if (isset($configuredQuestions[$key]) && empty($configuredQuestions[$key]['enabled'])) {
                continue;
            }

            // If user's free text already answered this aspect, skip asking it
            if ($this->isPreferenceAlreadyResolved($key, $preferences)) {
                continue;
            }

            $priority = $configuredQuestions[$key]['priority'] ?? $q['priority'];
            $candidateKeys[$key] = $priority;
        }

        if (empty($candidateKeys)) {
            return null;
        }

        asort($candidateKeys);
        $nextKey = array_key_first($candidateKeys);

        $questionData = $library[$nextKey];
        $questionData['step_index'] = $answeredCount + 1;
        $questionData['total_estimated_steps'] = min(4, $answeredCount + count($candidateKeys));

        return $questionData;
    }

    /**
     * Check if AI currently has sufficient information to recommend dishes without further questions.
     *
     * @param  array<string, mixed>  $preferences
     */
    public function hasSufficientInformation(array $preferences, int $questionsAnswered): bool
    {
        // 1. Direct food request (e.g., chicken, steak, fish, vegetarian): 0 questions needed!
        if (! empty($preferences['preference']) || ! empty($preferences['protein']) || ! empty($preferences['dish_hint'])) {
            return true;
        }

        // 2. Budget and party size or occasion combination (e.g., 2 people under 10,000 AMD): 0 questions needed!
        if (! empty($preferences['budget']) && (! empty($preferences['occasion']) || ! empty($preferences['party_size']))) {
            return true;
        }

        // 3. User gave clear free-text request with intent
        if (! empty($preferences['free_text']) && (mb_strlen($preferences['free_text']) >= 8 || ! empty($preferences['mood']))) {
            return true;
        }

        // 4. Must have answered at least 2 questions unless free text provided rich profile
        if ($questionsAnswered < 2 && empty($preferences['free_text'])) {
            return false;
        }

        $hasCoreFood = ! empty($preferences['mood']) || ! empty($preferences['preference']) || ! empty($preferences['protein']);
        $hasFlavorOrOccasion = ! empty($preferences['spiciness']) || ! empty($preferences['occasion']) || ! empty($preferences['dietary']);

        if ($hasCoreFood && $hasFlavorOrOccasion && $questionsAnswered >= 3) {
            return true;
        }

        return false;
    }

    /**
     * Check if a specific preference aspect is already resolved.
     *
     * @param  array<string, mixed>  $preferences
     */
    protected function isPreferenceAlreadyResolved(string $key, array $preferences): bool
    {
        return match ($key) {
            'mood' => ! empty($preferences['mood']),
            'preference' => ! empty($preferences['preference']) || ! empty($preferences['protein']),
            'spiciness' => isset($preferences['spiciness']) && $preferences['spiciness'] !== null,
            'occasion' => ! empty($preferences['occasion']),
            'budget' => ! empty($preferences['budget']),
            'drink' => ! empty($preferences['drink']),
            default => false,
        };
    }

    /**
     * Parse natural language free-text input into structured customer preferences.
     *
     * @return array<string, mixed>
     */
    public function parseFreeText(string $text, string $lang = 'en', ?Vendor $vendor = null): array
    {
        $normalized = mb_strtolower(trim($text));
        if ($normalized === '') {
            return [];
        }

        $extracted = [
            'free_text' => $text,
            'dietary' => [],
            'exclusions' => [],
        ];

        // 1. Dietary restrictions & allergies
        if (preg_match('/(բուսակեր|վեգան|առանց մսի|vegetarian|vegan|meatless|веган|вегетариан)/u', $normalized)) {
            $extracted['dietary'][] = 'vegetarian';
            $extracted['preference'] = 'vegetarian';
        }
        if (preg_match('/(գլյուտեն|առանց գլյուտենի|gluten[- ]?free|без глютена)/u', $normalized)) {
            $extracted['dietary'][] = 'gluten_free';
        }
        if (preg_match('/(լակտոզ|առանց կաթի|lactose[- ]?free|dairy[- ]?free|без лактозы)/u', $normalized)) {
            $extracted['dietary'][] = 'lactose_free';
        }

        // 2. Explicit exclusions (e.g. "չեմ սիրում սունկ", "առանց սոխ")
        if (preg_match('/(չեմ սիրում|առանց|չլինի|no |without |не люблю|без )\s*([a-z\p{Armenian}\p{Cyrillic}\s]+)/ui', $normalized, $m)) {
            $exclusionTarget = trim($m[2]);
            if (mb_strlen($exclusionTarget) >= 3) {
                $extracted['exclusions'][] = $exclusionTarget;
            }
        }
        if (str_contains($normalized, 'սունկ') && (str_contains($normalized, 'չեմ') || str_contains($normalized, 'առանց'))) {
            $extracted['exclusions'][] = 'mushroom';
        }

        // 3. Protein / Food preference
        if (preg_match('/(սթեյք|տավար|միս|steak|beef|говядина|мясо)/u', $normalized)) {
            $extracted['preference'] = 'beef';
            $extracted['mood'] = 'meat';
        } elseif (preg_match('/(հավ|chicken|курица|птица)/u', $normalized)) {
            $extracted['preference'] = 'chicken';
            $extracted['mood'] = 'light';
        } elseif (preg_match('/(ձուկ|սաղմոն|ծովամթերք|fish|salmon|seafood|рыба|лосось)/u', $normalized)) {
            $extracted['preference'] = 'fish';
        } elseif (preg_match('/(խինկալի|պելմենի|քյուֆթա|пельмени|хинкали)/u', $normalized)) {
            $extracted['preference'] = 'beef';
            $extracted['mood'] = 'meat';
            $extracted['dish_hint'] = 'khinkali';
        }

        // 4. Spiciness
        if (preg_match('/(չեմ սիրում կծու|ոչ կծու|մեղմ|not spicy|mild|не остр)/u', $normalized)) {
            $extracted['spiciness'] = 'none';
        } elseif (preg_match('/(շատ կծու|hot|very spicy|очень остр)/u', $normalized)) {
            $extracted['spiciness'] = 'hot';
        } elseif (preg_match('/(կծու|spicy|остр)/u', $normalized)) {
            $extracted['spiciness'] = 'medium';
        }

        // 5. Mood / Vibe
        if (preg_match('/(թեթև|աղցան|light|fresh|салат|легк)/u', $normalized)) {
            $extracted['mood'] = 'light';
        } elseif (preg_match('/(քաղցր|դեսերտ|աղանդեր|sweet|dessert|десерт)/u', $normalized)) {
            $extracted['mood'] = 'sweet';
        }

        // 6. Occasion
        if (preg_match('/(երկուսով|երկուսիս|զույգով|date|couple|romantic|вдвоем|для двоих)/u', $normalized)) {
            $extracted['occasion'] = 'couple';
        } elseif (preg_match('/(ընտանիք|family|семь)/u', $normalized)) {
            $extracted['occasion'] = 'family';
        } elseif (preg_match('/(ընկեր|friends|друзь)/u', $normalized)) {
            $extracted['occasion'] = 'friends';
        }

        // 7. Budget detection (e.g. 5000, 10000, 15000)
        if (preg_match('/(\d{4,6})\s*(֏|դրամ|amd|rub|руб|\$)?/ui', $normalized, $matches)) {
            $amount = (int) $matches[1];
            if ($amount <= 6000) {
                $extracted['budget'] = '5000';
            } elseif ($amount <= 12000) {
                $extracted['budget'] = '10000';
            } else {
                $extracted['budget'] = '20000';
            }
            $extracted['raw_budget'] = $amount;
        }

        return $extracted;
    }

    /**
     * Generate personalized dish recommendations applying Hard Constraints and Restaurant-Controlled Priority Stack.
     *
     * @param  array<string, mixed>  $preferences
     * @return array<string, mixed>
     */
    public function recommendDishes(
        Vendor $vendor,
        array $preferences = [],
        ?string $prompt = null,
        string $lang = 'en',
        ?int $locationId = null
    ): array {
        $allowed = $vendor->getAiWaiterLanguages();
        if (! in_array($lang, $allowed, true)) {
            $lang = $allowed[0] ?? 'en';
        }

        // Merge prompt analysis into preferences if provided
        if (! empty($prompt)) {
            $parsedPrompt = $this->parseFreeText($prompt, $lang, $vendor);
            $preferences = array_merge($preferences, $parsedPrompt);
            $preferences['free_text'] = $prompt;
        }

        // 1. Fetch available products with relations
        $products = Product::where('vendor_id', $vendor->id)
            ->where('is_available', true)
            ->where('ai_enabled', true)
            ->whereHas('category', function ($q) {
                $q->where('is_active', true);
            })
            ->with(['category', 'variations', 'allergens', 'overrides'])
            ->get();

        if ($products->isEmpty()) {
            return [
                'commentary' => $this->getDefaultEmptyCommentary($lang),
                'main_recommendations' => [],
                'recommendations' => [],
                'secondary_recommendations' => [],
                'pairing_drink' => null,
                'bundle' => null,
            ];
        }

        // 2. HARD CONSTRAINTS FILTERING (Dietary, Allergies, Availability, Location Overrides, Explicit Exclusions)
        $candidates = $this->applyHardConstraints($products, $preferences, $lang, $locationId);

        if ($candidates->isEmpty()) {
            $dietary = (array) ($preferences['dietary'] ?? []);
            $allergies = (array) ($preferences['allergies'] ?? []);
            $exclusions = (array) ($preferences['exclusions'] ?? []);
            $pref = $preferences['preference'] ?? null;

            $hasStrictConstraints = ! empty($dietary) || ! empty($allergies) || ! empty($exclusions) || ($pref === 'vegetarian');

            if ($hasStrictConstraints) {
                return [
                    'commentary' => match ($lang) {
                        'hy' => 'Ներողություն, նշված խիստ սահմանափակումներով (ալերգիա կամ բուսական) համապատասխան ուտեստներ չգտնվեցին։ Մեր անձնակազմը սիրով կօգնի Ձեզ տեղում։',
                        'ru' => 'К сожалению, с учетом указанных строгих ограничений (аллергия или вегетарианство) подходящих блюд не найдено. Наш персонал с радостью поможет вам на месте.',
                        default => 'Unfortunately, no dishes matched your strict dietary restrictions or allergy exclusions. Our staff will be happy to assist you in person.',
                    },
                    'main_recommendations' => [],
                    'recommendations' => [],
                    'secondary_recommendations' => [],
                    'pairing_drink' => null,
                    'bundle' => null,
                ];
            }

            // Fallback gracefully to general available products only if no strict constraints were given
            $candidates = $products->filter(function (Product $prod) use ($locationId) {
                if (! $prod->is_available) {
                    return false;
                }
                if ($locationId) {
                    $override = $prod->overrides->firstWhere('location_id', $locationId);
                    if ($override && ! $override->is_available) {
                        return false;
                    }
                }

                return true;
            });
        }

        // 3. RANKING & SCORING HIERARCHY
        $scoredProducts = $this->scoreProductCandidates($candidates, $vendor, $preferences, $lang, $locationId);

        // 4. PARTITION INTO MAIN, ALTERNATIVE, DRINK, AND COMBO BUNDLE
        $mainDishes = [];
        $secondaryDishes = [];
        $drinkCandidates = [];

        foreach ($scoredProducts as $entry) {
            /** @var Product $p */
            $p = $entry['product'];
            $catName = mb_strtolower($p->category?->name ?? '');

            if ($this->isBeverageCategory($catName)) {
                $drinkCandidates[] = $entry;
            } else {
                if (count($mainDishes) < 3) {
                    $mainDishes[] = $entry;
                } elseif (count($secondaryDishes) < 2) {
                    $secondaryDishes[] = $entry;
                }
            }
        }

        // If no food found, populate from general candidates
        if (empty($mainDishes) && ! empty($scoredProducts)) {
            $mainDishes = array_slice($scoredProducts, 0, 2);
        }

        // Format recommended output models
        $formattedMain = array_map(fn ($item) => $this->formatProductPayload($item['product'], $item['match_score'], $item['reason'], $vendor, $lang, $locationId, $item['matched_priority_ingredients']), $mainDishes);
        $formattedSecondary = array_map(fn ($item) => $this->formatProductPayload($item['product'], $item['match_score'], $item['reason'], $vendor, $lang, $locationId, $item['matched_priority_ingredients']), $secondaryDishes);

        // Select primary drink pairing
        $selectedDrinkEntry = ! empty($drinkCandidates) ? $drinkCandidates[0] : null;
        $formattedDrink = $selectedDrinkEntry ? $this->formatProductPayload($selectedDrinkEntry['product'], $selectedDrinkEntry['match_score'], $selectedDrinkEntry['reason'], $vendor, $lang, $locationId) : null;

        // 5. SMART PAIRING BUNDLE (Main + Side/Appetizer + Drink)
        $bundle = $this->buildSmartPairingBundle($mainDishes, $products, $formattedDrink, $vendor, $lang, $locationId);

        // Attach pairings structure to main recommendations for backwards compatibility
        foreach ($formattedMain as &$fMain) {
            $fMain['pairings'] = [
                'pairing_note' => $bundle['title'] ?? 'Sommelier Pairing Selection',
                'drink' => $formattedDrink,
                'side' => $bundle['side'] ?? null,
            ];
        }
        unset($fMain);

        // 6. AI COMMENTARY
        $commentary = $this->generateSommelierCommentary($vendor, $formattedMain, $preferences, $prompt, $lang);

        return [
            'commentary' => $commentary,
            'recommendations' => $formattedMain, // For backwards-compatibility
            'main_recommendations' => $formattedMain,
            'secondary_recommendations' => $formattedSecondary,
            'pairing_drink' => $formattedDrink,
            'bundle' => $bundle,
        ];
    }

    /**
     * Apply Hard Constraints (Dietary, Allergies, Availability, Exclusions).
     *
     * @param  Collection<int, Product>  $products
     * @param  array<string, mixed>  $preferences
     * @return Collection<int, Product>
     */
    protected function applyHardConstraints(Collection $products, array $preferences, string $lang, ?int $locationId = null): Collection
    {
        $dietary = (array) ($preferences['dietary'] ?? []);
        $pref = $preferences['preference'] ?? null;
        if ($pref === 'vegetarian') {
            $dietary[] = 'vegetarian';
        }
        $dietary = array_unique($dietary);

        $exclusions = (array) ($preferences['exclusions'] ?? []);
        $allergies = (array) ($preferences['allergies'] ?? []);

        return $products->filter(function (Product $prod) use ($dietary, $exclusions, $allergies, $lang, $locationId) {
            // Must be available globally
            if (! $prod->is_available) {
                return false;
            }

            // Must be available for current location
            if ($locationId && $prod->relationLoaded('overrides')) {
                $override = $prod->overrides->firstWhere('location_id', $locationId);
                if ($override && ! $override->is_available) {
                    return false;
                }
            }

            $prodName = mb_strtolower($prod->name.' '.$prod->getTranslatedName($lang));
            $prodDesc = mb_strtolower(($prod->description ?? '').' '.$prod->getTranslatedDescription($lang));
            $fullText = $prodName.' '.$prodDesc;
            $tags = array_map('mb_strtolower', (array) ($prod->dietary_tags ?? []));

            // Hard Dietary Constraint: Vegetarian
            if (in_array('vegetarian', $dietary, true)) {
                $meatKeywords = ['steak', 'beef', 'chicken', 'pork', 'lamb', 'bacon', 'ham', 'sausage', 'meat', 'տավար', 'խոզ', 'գառ', 'հավ', 'միս', 'սթեյք', 'բաստուրմա', 'խինկալի', 'քյուֆթա', 'говядина', 'свинина', 'курица', 'мясо', 'стейк'];
                foreach ($meatKeywords as $kw) {
                    if (str_contains($fullText, $kw) && ! in_array('vegetarian', $tags) && ! in_array('vegan', $tags)) {
                        return false;
                    }
                }
            }

            // Hard Dietary Constraint: Vegan
            if (in_array('vegan', $dietary, true)) {
                if (! in_array('vegan', $tags)) {
                    $animalKeywords = ['cheese', 'milk', 'cream', 'butter', 'egg', 'beef', 'chicken', 'fish', 'պանիր', 'կաթ', 'սերուցք', 'կարագ', 'ձու', 'միս', 'ձուկ', 'сыр', 'молоко', 'сливки', 'масло', 'яйцо'];
                    foreach ($animalKeywords as $kw) {
                        if (str_contains($fullText, $kw)) {
                            return false;
                        }
                    }
                }
            }

            // Hard Allergy Exclusion
            if (! empty($allergies)) {
                $prodAllergenNames = $prod->allergens->pluck('name')->map(fn ($n) => mb_strtolower($n))->toArray();
                foreach ($allergies as $allergen) {
                    $allergenLower = mb_strtolower(trim($allergen));
                    if (in_array($allergenLower, $prodAllergenNames, true) || str_contains($fullText, $allergenLower)) {
                        return false;
                    }
                }
            }

            // Explicit customer exclusion words
            foreach ($exclusions as $ex) {
                $exLower = mb_strtolower(trim($ex));
                if ($exLower === 'mushroom' || str_contains($exLower, 'սունկ') || str_contains($exLower, 'гриб')) {
                    if (str_contains($fullText, 'սունկ') || str_contains($fullText, 'mushroom') || str_contains($fullText, 'гриб')) {
                        return false;
                    }
                } elseif (mb_strlen($exLower) >= 3 && str_contains($fullText, $exLower)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Score product candidates according to the restaurant priority hierarchy.
     *
     * @param  Collection<int, Product>  $candidates
     * @param  array<string, mixed>  $preferences
     * @return array<int, array{product: Product, score: float, match_score: int, reason: string, matched_priority_ingredients: array<int, string>}>
     */
    protected function scoreProductCandidates(
        Collection $candidates,
        Vendor $vendor,
        array $preferences,
        string $lang,
        ?int $locationId = null
    ): array {
        $weights = $vendor->getAiScoringWeights();
        $promotedProducts = $vendor->getAiPromotedProductsList();
        $promotedMap = [];
        foreach ($promotedProducts as $item) {
            $promotedMap[$item['product_id']] = (int) ($item['priority'] ?? 100);
        }

        $preferredIngredients = $vendor->getAiPreferredIngredients();
        $config = $vendor->getAiWaiterConfig();
        $groupPriorities = $config['group_priorities'] ?? [];
        $tagPriorities = $config['tag_priorities'] ?? [];

        $craving = $preferences['craving'] ?? ($preferences['mood'] ?? null);
        $prefProtein = $preferences['preference'] ?? ($preferences['protein'] ?? null);
        $spiciness = $preferences['spiciness'] ?? null;
        $occasion = $preferences['occasion'] ?? null;
        $budget = $preferences['budget'] ?? null;
        $freeText = mb_strtolower(trim($preferences['free_text'] ?? ''));

        $scored = [];

        foreach ($candidates as $product) {
            $nameText = mb_strtolower($product->getTranslatedName($lang).' '.$product->name);
            $descText = mb_strtolower(($product->description ?? '').' '.$product->getTranslatedDescription($lang));
            $catName = mb_strtolower($product->category?->getTranslatedName($lang) ?? ($product->category?->name ?? ''));
            $fullText = $nameText.' '.$descText.' '.$catName;

            // 1. Restaurant Explicit Priority (0 - 100 normalized)
            $explicitPriority = 0;
            if (isset($promotedMap[$product->id])) {
                $explicitPriority = max(80, $promotedMap[$product->id]);
            } elseif ($product->ai_priority) {
                $explicitPriority = $product->ai_priority_level > 0 ? $product->ai_priority_level : 90;
            } elseif ($product->is_featured || ($vendor->featured_product_id === $product->id)) {
                $explicitPriority = 70;
            }

            // 2. Preferred Ingredients Match (0 - 100 normalized)
            $ingredientScore = 0;
            $matchedIngredients = [];
            foreach ($preferredIngredients as $ingItem) {
                $ingName = mb_strtolower(trim($ingItem['ingredient'] ?? ''));
                if ($ingName !== '' && str_contains($fullText, $ingName)) {
                    $prio = (int) ($ingItem['priority'] ?? 80);
                    $ingredientScore = max($ingredientScore, $prio);
                    $matchedIngredients[] = $ingItem['ingredient'];
                }
            }

            // 3. Product Group & Tag Boosts
            $groupBoost = 0;
            if (! empty($product->ai_group) && isset($groupPriorities[$product->ai_group])) {
                $groupBoost = $groupPriorities[$product->ai_group] * 0.15;
            }

            $tagBoost = 0;
            $prodTags = (array) ($product->ai_tags ?? []);
            foreach ($prodTags as $t) {
                if (isset($tagPriorities[$t])) {
                    $tagBoost = max($tagBoost, $tagPriorities[$t] * 0.1);
                }
            }

            // 4. Customer Preference Match (0 - 100 normalized)
            $customerMatch = 50; // Neutral baseline

            // Protein / Food preference match
            if ($prefProtein && $prefProtein !== 'all') {
                $customerMatch += $this->calculateProteinMatch($prefProtein, $fullText);
            }

            // Mood match
            if ($craving) {
                $customerMatch += $this->calculateCravingMatch($craving, $fullText);
            }

            // Spiciness match
            if ($spiciness !== null) {
                $prodSpicy = (int) ($product->ai_spicy_level ?? 0);
                if ($spiciness === 'none') {
                    $customerMatch += ($prodSpicy === 0) ? 20 : -35;
                } elseif ($spiciness === 'mild') {
                    $customerMatch += ($prodSpicy <= 1) ? 20 : -10;
                } elseif ($spiciness === 'hot') {
                    $customerMatch += ($prodSpicy >= 2) ? 25 : -15;
                }
            }

            // Occasion match
            if ($occasion) {
                $customerMatch += $this->calculateOccasionMatch($occasion, $fullText, $product);
            }

            // Budget match
            $effectivePrice = (float) $product->getEffectivePrice($locationId);
            if ($budget && $budget !== 'any') {
                $maxBudget = (float) $budget;
                if ($effectivePrice <= $maxBudget) {
                    $customerMatch += 15;
                } elseif ($effectivePrice > $maxBudget * 1.3) {
                    $customerMatch -= 25;
                }
            }

            // Free text keyword matches
            if ($freeText !== '') {
                $customerMatch += $this->calculateFreeTextMatch($freeText, $fullText, $product);
            }

            $customerMatch = max(10, min(100, $customerMatch));

            // 5. Compute Weighted Score
            $wRest = $weights['restaurant_priority'] ?? 30;
            $wIng = $weights['preferred_ingredient'] ?? 20;
            $wCust = $weights['customer_preference'] ?? 25;
            $wDiet = $weights['dietary_compatibility'] ?? 10;
            $wTaste = $weights['taste_spiciness'] ?? 5;
            $wOccasion = $weights['occasion'] ?? 5;
            $wBudget = $weights['budget'] ?? 5;

            $totalWeight = $wRest + $wIng + $wCust + $wDiet + $wTaste + $wOccasion + $wBudget;
            if ($totalWeight <= 0) {
                $totalWeight = 100;
            }

            $totalScore = (
                ($explicitPriority * $wRest) +
                ($ingredientScore * $wIng) +
                ($customerMatch * $wCust) +
                (85 * $wDiet) +
                (80 * $wTaste) +
                (80 * $wOccasion) +
                (80 * $wBudget)
            ) / $totalWeight;

            $totalScore += $groupBoost + $tagBoost;

            // Diversity penalty: avoid repeating dishes already shown in this session
            $shownProductIds = array_map('intval', (array) ($preferences['shown_product_ids'] ?? []));
            if (in_array((int) $product->id, $shownProductIds, true)) {
                $totalScore -= 45.0;
            }

            // Rejection penalty: heavily avoid dishes explicitly rejected by guest
            $rejectedProductIds = array_map('intval', (array) ($preferences['rejected_product_ids'] ?? []));
            if (in_array((int) $product->id, $rejectedProductIds, true)) {
                $totalScore -= 150.0;
            }

            // Time-aware meal period boost
            $activeMealPeriod = $preferences['active_meal_period'] ?? null;
            if ($activeMealPeriod && is_array($activeMealPeriod)) {
                if ($this->timeAwareService->isProductRelevantToMealPeriod($product, $activeMealPeriod, $lang)) {
                    $totalScore += 25.0;
                }
            }

            // Compute human-friendly, genuine Match Percentage (82% - 98%)
            $matchPercentage = (int) round(min(98, max(75, 70 + ($totalScore * 0.28))));

            $reason = $this->buildRecommendationReason($product, $matchedIngredients, $craving, $prefProtein, $lang);

            $scored[] = [
                'product' => $product,
                'score' => $totalScore,
                'match_score' => $matchPercentage,
                'reason' => $reason,
                'matched_priority_ingredients' => array_unique($matchedIngredients),
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $scored;
    }

    /**
     * Calculate protein match.
     */
    protected function calculateProteinMatch(string $protein, string $text): int
    {
        $keywords = match ($protein) {
            'beef' => ['տավար', 'միս', 'սթեյք', 'անգուս', 'beef', 'steak', 'angus', 'ribeye', 'говядина', 'стейк', 'мясо'],
            'chicken' => ['հավ', 'թռչնամիս', 'chicken', 'poultry', 'курица', 'птица'],
            'fish' => ['ձուկ', 'սաղմոն', 'ծովամթերք', 'խեցգետին', 'fish', 'salmon', 'seafood', 'shrimp', 'рыба', 'лосось', 'креветки'],
            'vegetarian' => ['բուսական', 'բանջարեղեն', 'սունկ', 'պանիր', 'աղցան', 'vegetarian', 'vegan', 'salad', 'cheese', 'салат', 'овощи'],
            default => [],
        };

        foreach ($keywords as $kw) {
            if (str_contains($text, $kw)) {
                return 25;
            }
        }

        return -15;
    }

    /**
     * Calculate craving match.
     */
    protected function calculateCravingMatch(string $craving, string $text): int
    {
        $keywords = match ($craving) {
            'meat' => ['տավար', 'միս', 'սթեյք', 'հորթ', 'գառ', 'beef', 'steak', 'meat', 'lamb', 'говядина', 'стейк', 'мясо'],
            'seafood', 'fish' => ['ձուկ', 'սաղմոն', 'ծովամթերք', 'խեցգետին', 'fish', 'salmon', 'seafood', 'shrimp', 'рыба', 'лосось', 'креветки'],
            'vegetarian' => ['բուսական', 'բանջարեղեն', 'սունկ', 'պանիր', 'աղցան', 'vegetarian', 'vegan', 'salad', 'cheese', 'салат', 'овощи'],
            'light' => ['թեթև', 'հավ', 'նախուտեստ', 'light', 'chicken', 'starter', 'легк', 'курица'],
            'spicy' => ['կծու', 'չիլի', 'պղպեղ', 'spicy', 'chili', 'hot', 'остр', 'чили'],
            'fresh' => ['թարմ', 'աղցան', 'բանջարեղեն', 'fresh', 'salad', 'green', 'салат', 'свеж'],
            'sweet', 'dessert' => ['քաղցր', 'շոկոլադ', 'աղանդեր', 'թխվածք', 'sweet', 'chocolate', 'dessert', 'десерт', 'торт'],
            default => [],
        };

        if (empty($keywords)) {
            return 0;
        }

        foreach ($keywords as $kw) {
            if (str_contains($text, $kw)) {
                return 35;
            }
        }

        if (in_array($craving, ['meat', 'seafood', 'fish', 'vegetarian', 'sweet', 'dessert'], true)) {
            return -25;
        }

        return 0;
    }

    /**
     * Calculate occasion match.
     */
    protected function calculateOccasionMatch(string $occasion, string $text, Product $product): int
    {
        return match ($occasion) {
            'couple' => (str_contains($text, 'steak') || str_contains($text, 'wine') || str_contains($text, 'salmon') || $product->is_featured) ? 20 : 5,
            'family' => (str_contains($text, 'pizza') || str_contains($text, 'burger') || str_contains($text, 'խինկալի') || str_contains($text, 'sharing')) ? 20 : 5,
            'friends' => (str_contains($text, 'beer') || str_contains($text, 'snack') || str_contains($text, 'wings') || str_contains($text, 'sharing')) ? 20 : 5,
            'celebration' => ($product->is_featured || (float) $product->price > 4500) ? 25 : 5,
            default => 10,
        };
    }

    /**
     * Calculate free-text match.
     */
    protected function calculateFreeTextMatch(string $freeText, string $prodText, Product $product): int
    {
        $words = preg_split('/[\s,\.\!\?]+/', $freeText);
        $score = 0;
        foreach ($words ?: [] as $w) {
            $w = trim($w);
            if (mb_strlen($w) >= 3 && str_contains($prodText, $w)) {
                $score += 15;
            }
        }

        return min(40, $score);
    }

    /**
     * Build recommendation explanation reason.
     *
     * @param  array<int, string>  $matchedIngredients
     */
    protected function buildRecommendationReason(
        Product $product,
        array $matchedIngredients,
        ?string $craving,
        ?string $protein,
        string $lang
    ): string {
        if (! empty($matchedIngredients)) {
            $ingList = implode(', ', array_slice($matchedIngredients, 0, 2));

            return match ($lang) {
                'en' => "Features our chef's signature ingredient: {$ingList}, prepared with meticulous craft.",
                'ru' => "Приготовлено с использованием фирменного ингредиента ресторана: {$ingList}.",
                default => "Պարունակում է մեր ռեստորանի առաջնահերթ բաղադրիչը՝ «{$ingList}», որը պատրաստված է շեֆ-խոհարարի հատուկ բաղադրատոմսով:",
            };
        }

        if ($product->ai_priority || $product->is_featured) {
            return match ($lang) {
                'en' => 'One of our most celebrated dishes, matching your taste profile flawlessly.',
                'ru' => 'Фирменное блюдо нашего меню, идеально соответствующее вашим вкусовым предпочтениям.',
                default => 'Մեր մենյուի ամենասիրված ֆիրմային ուտեստներից է՝ կատարյալ համապատասխանությամբ Ձեր նախասիրություններին:',
            };
        }

        return match ($lang) {
            'en' => 'Handpicked by AI Sommelier for exceptional flavor balance and quality.',
            'ru' => 'Подобрано AI-сомелье для безупречного гастрономического баланса.',
            default => 'Ընտրված է AI մատուցողի կողմից՝ ճաշատեսակի բարձր որակի և ներդաշնակ համադրության շնորհիվ:',
        };
    }

    /**
     * Build smart pairing bundle combining Main Dish + Side / Starter + Drink.
     *
     * @param  array<int, array{product: Product, match_score: int}>  $mainDishes
     * @param  Collection<int, Product>  $allProducts
     * @param  array<string, mixed>|null  $formattedDrink
     * @return array<string, mixed>|null
     */
    protected function buildSmartPairingBundle(
        array $mainDishes,
        Collection $allProducts,
        ?array $formattedDrink,
        Vendor $vendor,
        string $lang,
        ?int $locationId = null
    ): ?array {
        if (empty($mainDishes)) {
            return null;
        }

        /** @var Product $mainProd */
        $mainProd = $mainDishes[0]['product'];
        $formattedMain = $this->formatProductPayload($mainProd, $mainDishes[0]['match_score'], '', $vendor, $lang, $locationId);

        // Find best side dish / salad / appetizer
        $sides = $allProducts->filter(function (Product $p) use ($mainProd) {
            if ($p->id === $mainProd->id) {
                return false;
            }
            $cat = mb_strtolower($p->category?->name ?? '');

            return ! $this->isBeverageCategory($cat);
        });

        $chosenSide = $sides->first(function (Product $p) {
            $cat = mb_strtolower($p->category?->name ?? '');

            return str_contains($cat, 'salad') || str_contains($cat, 'starter') || str_contains($cat, 'appetizer') || str_contains($cat, 'նախուտեստ') || str_contains($cat, 'աղցան');
        }) ?? $sides->first();

        $formattedSide = $chosenSide ? $this->formatProductPayload($chosenSide, 90, '', $vendor, $lang, $locationId) : null;

        $items = array_values(array_filter([$formattedMain, $formattedSide, $formattedDrink]));
        if (count($items) < 2) {
            return null;
        }

        $totalPrice = 0;
        foreach ($items as $it) {
            $totalPrice += (float) ($it['price'] ?? 0);
        }

        $bundleName = match ($lang) {
            'en' => "Chef's Complete Gastronomic Set",
            'ru' => 'Полный гастрономический сет от шефа',
            default => 'Շեֆի Ամբողջական Հավաքածու',
        };

        return [
            'title' => $bundleName,
            'items_count' => count($items),
            'total_price' => $totalPrice,
            'formatted_total_price' => number_format($totalPrice).' '.$vendor->currency,
            'main' => $formattedMain,
            'side' => $formattedSide,
            'drink' => $formattedDrink,
            'items' => $items,
        ];
    }

    /**
     * Format a product for UI payloads with variations and pricing.
     *
     * @param  array<int, string>  $matchedIngredients
     * @return array<string, mixed>
     */
    public function formatProductPayload(
        Product $prod,
        int $matchScore,
        string $reason,
        Vendor $vendor,
        string $lang,
        ?int $locationId = null,
        array $matchedIngredients = []
    ): array {
        $effectivePrice = (float) $prod->getEffectivePrice($locationId);
        $regularPrice = (float) $prod->getRegularPrice($locationId);

        return [
            'id' => $prod->id,
            'name' => $prod->getTranslatedName($lang),
            'category_name' => $prod->category?->getTranslatedName($lang) ?? '',
            'description' => $prod->getTranslatedDescription($lang),
            'price' => $effectivePrice,
            'regular_price' => $regularPrice,
            'is_discount_active' => $prod->isDiscountActive(),
            'discount_percentage' => $prod->getDiscountPercentage(),
            'formatted_price' => number_format($effectivePrice).' '.$vendor->currency,
            'image' => $prod->image ?: Product::DEFAULT_IMAGE,
            'match_score' => $matchScore,
            'reason' => $reason,
            'matched_ingredients' => $matchedIngredients,
            'dietary_tags' => $prod->dietary_tags ?? [],
            'calories' => $prod->calories,
            'preparation_time_min' => $prod->preparation_time_min,
            'payload' => [
                'id' => $prod->id,
                'name' => $prod->getTranslatedName($lang),
                'image' => $prod->image ?: Product::DEFAULT_IMAGE,
                'description' => $prod->getTranslatedDescription($lang),
                'base_price' => $effectivePrice,
                'regular_price' => $regularPrice,
                'is_discount_active' => $prod->isDiscountActive(),
                'discount_percentage' => $prod->getDiscountPercentage(),
                'variations' => $prod->variations->map(fn ($v) => [
                    'id' => $v->id,
                    'name' => $v->getTranslatedName($lang),
                    'price' => (float) $v->getEffectivePrice(),
                    'regular_price' => (float) $v->price,
                    'is_default' => (bool) $v->is_default,
                ])->values(),
            ],
        ];
    }

    /**
     * Grounded conversational Q&A assistant: NEVER invents products, answers strictly from menu.
     *
     * @param  array<string, mixed>  $sessionContext
     * @return array<string, mixed>
     */
    public function answerChatQuery(
        Vendor $vendor,
        string $message,
        array $sessionContext = [],
        string $lang = 'en',
        ?int $locationId = null,
        array $history = []
    ): array {
        $allowed = $vendor->getAiWaiterLanguages();
        if (! in_array($lang, $allowed, true)) {
            $lang = $allowed[0] ?? 'en';
        }

        $waiterName = $vendor->getAiWaiterName();

        // 1. Fetch all active menu products for grounded reference
        $allProducts = Product::where('vendor_id', $vendor->id)
            ->where('is_available', true)
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->with(['category', 'overrides'])
            ->get();

        if ($locationId) {
            $allProducts = $allProducts->filter(function (Product $p) use ($locationId) {
                $override = $p->overrides->firstWhere('location_id', $locationId);

                return ! ($override && ! $override->is_available);
            });
        }

        if ($allProducts->isEmpty()) {
            return [
                'reply' => $this->getDefaultEmptyCommentary($lang),
                'suggested_products' => [],
            ];
        }

        // 2. Build full menu context inventory for LLM
        $menuInventory = [];
        foreach ($allProducts as $p) {
            $catName = $p->category?->getTranslatedName($lang) ?? ($p->category?->name ?? 'General');
            $menuInventory[] = [
                'id' => $p->id,
                'name' => $p->getTranslatedName($lang),
                'category' => $catName,
                'price' => (float) $p->getEffectivePrice($locationId),
                'description' => $p->getTranslatedDescription($lang) ?? '',
                'tags' => (array) ($p->dietary_tags ?? []),
                'is_beverage' => $this->isBeverageCategory($catName),
                'spicy_level' => $p->ai_spicy_level ?? 0,
            ];
        }

        $apiKey = $vendor->getAiApiKey();

        // Try LLM response if credentials exist
        if (! app()->environment('testing') && (! empty($apiKey) || $vendor->hasCustomAiConfig() || ! empty(config('services.gemini.key')) || ! empty(env('GEMINI_API_KEY')) || ! empty(env('OPENAI_API_KEY')))) {
            try {
                // Full structured menu inventory
                $inventoryJson = json_encode($menuInventory, JSON_UNESCAPED_UNICODE);

                // Sanitize user message against delimiter breakout
                $sanitizedMessage = str_ireplace(
                    ['<USER_QUERY>', '</USER_QUERY>', '"""', '```', '<SYSTEM', '</SYSTEM'],
                    ['[USER_QUERY]', '[/USER_QUERY]', "'''", "'''", '', ''],
                    $message
                );

                // Partner custom training & knowledge base instructions
                $customInstructions = $vendor->getAiWaiterInstructions();
                $trainingBlock = '';
                if ($customInstructions !== '') {
                    $trainingBlock = "RESTAURANT SPECIFIC TRAINING & SOMMELIER KNOWLEDGE (Strictly follow):\n{$customInstructions}\n";
                }

                // Conversation history for live continuity
                $historyFormatted = '';
                if (! empty($history)) {
                    $historyFormatted = "PREVIOUS CONVERSATION HISTORY:\n";
                    foreach (array_slice($history, -8) as $h) {
                        $role = ($h['sender'] ?? '') === 'ai' ? $waiterName : 'Guest';
                        $txt = trim((string) ($h['text'] ?? ''));
                        if ($txt !== '') {
                            $historyFormatted .= "{$role}: {$txt}\n";
                        }
                    }
                    $historyFormatted .= "\n";
                }

                $systemPrompt = "You are {$waiterName}, the expert sommelier and friendly AI dining advisor at '{$vendor->name}'.

{$trainingBlock}
CRITICAL RULES & GROUNDING:
1. You have MEMORIZED the restaurant's entire menu inventory below. You MUST ONLY recommend dishes and beverages that actually exist in the inventory.
2. NEVER invent, hallucinate, or assume any dish, drink, or price not present in the inventory.
3. Live Conversation & Continuity: Pay close attention to the PREVIOUS CONVERSATION HISTORY. If the guest asks follow-ups (e.g. 'What about with wine?', 'Something without garlic?', 'What else do you suggest?'), connect your answer to their previous requests.
4. Keep replies appetizing, warm, and concise (2-4 sentences max). Never output markdown tables or large bulleted lists.
5. Language: {$lang}. Answer in {$lang}.
6. Currency: {$vendor->currency}.
7. Mention dish names in quotes (e.g. «Product Name») so guests can identify them clearly.

MENU INVENTORY:
{$inventoryJson}

{$historyFormatted}<USER_QUERY>
{$sanitizedMessage}
</USER_QUERY>

Respond directly to the guest in {$lang}:";

                $sessionModel = ! empty($sessionContext['session_token'])
                    ? AiWaiterSession::where('session_token', $sessionContext['session_token'])->first()
                    : (! empty($sessionContext['id']) ? AiWaiterSession::find($sessionContext['id']) : null);

                $generated = $this->aiGateway->generateText($vendor, $systemPrompt, [
                    'timeout' => 8,
                    'session' => $sessionModel,
                ]);

                if (! empty($generated)) {
                    $matchingProducts = $this->findMentionedProductsInText($generated, $allProducts, $lang, $vendor, $locationId);

                    return [
                        'reply' => trim($generated),
                        'suggested_products' => $matchingProducts,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('AI chat grounded response failed, using rule-based engine: '.$e->getMessage());
            }
        }

        // Rule-based Grounded Intelligent Fallback
        return $this->generateRuleBasedChatAnswer($message, $allProducts, $vendor, $lang, $locationId, $history, $sessionContext);
    }

    /**
     * Grounded intelligent rule-based chat query resolution.
     * Supports full customer service: food recommendations, table reservations,
     * delivery, takeaway, event inquiries, waiter calls/bills, and restaurant information.
     *
     * @param  Collection<int, Product>  $allProducts
     * @param  array<int, mixed>  $history
     * @param  array<string, mixed>  $sessionContext
     * @return array<string, mixed>
     */
    protected function generateRuleBasedChatAnswer(
        string $message,
        Collection $allProducts,
        Vendor $vendor,
        string $lang,
        ?int $locationId = null,
        array $history = [],
        array $sessionContext = []
    ): array {
        $msg = mb_strtolower(trim($message));
        $waiterName = $vendor->getAiWaiterName();
        $entryContext = $sessionContext['entry_context'] ?? session('storefront_entry_context_'.$vendor->slug, 'general_menu');
        $tableNumber = $sessionContext['table_number'] ?? session('storefront_table_'.$vendor->slug);
        $conversationState = (array) ($sessionContext['conversation_state'] ?? []);
        $rejectedProductIds = array_map('intval', (array) ($conversationState['rejected_product_ids'] ?? []));

        // 1. Waiter Call or Bill Request
        if (preg_match('/(մատուցող|հաշիվ|օֆիցիանտ|чек|счет|waiter|bill|check|բերեք հաշիվը)/ui', $msg)) {
            $isBill = (bool) preg_match('/(հաշիվ|чек|счет|bill|check)/ui', $msg);
            $type = $isBill ? 'bill_card' : 'call_waiter';

            if (! empty($tableNumber)) {
                $recentCall = WaiterCall::where('vendor_id', $vendor->id)
                    ->where('table_number', (string) $tableNumber)
                    ->where('status', 'pending')
                    ->where('created_at', '>=', now()->subSeconds(60))
                    ->first();

                if ($recentCall) {
                    $reply = match ($lang) {
                        'ru' => "Ваш запрос уже передан персоналу для столика #{$tableNumber}. Пожалуйста, подождите минутку.",
                        'en' => "Your request was already submitted for Table #{$tableNumber}. The waiter will be with you shortly.",
                        default => "Ձեր նախորդ կանչն արդեն փոխանցվել է մատուցողին (Սեղան #{$tableNumber})։ Խնդրում ենք սպասել մի փոքր։",
                    };

                    return [
                        'reply' => $reply,
                        'suggested_products' => [],
                        'quick_replies' => [
                            match ($lang) {
                                'ru' => 'Посмотреть меню', 'en' => 'Browse menu', default => 'Տեսնել մենյուն'
                            },
                        ],
                        'intent' => 'waiter_call',
                    ];
                }

                try {
                    $call = WaiterCall::create([
                        'vendor_id' => $vendor->id,
                        'location_id' => $locationId ?? $vendor->locations()->first()?->id,
                        'table_number' => (string) $tableNumber,
                        'type' => $type,
                        'status' => 'pending',
                        'notes' => 'AI Waiter Assistant',
                    ]);
                    event(new WaiterCalled($call));
                } catch (\Throwable $e) {
                    Log::warning('AI waiter call dispatch failed: '.$e->getMessage());
                }

                $reply = $isBill
                    ? match ($lang) {
                        'ru' => "Счёт для столика #{$tableNumber} запрошен. Официант скоро подойдёт с терминалом/счётом.",
                        'en' => "The bill for Table #{$tableNumber} has been requested. The server will bring it to your table shortly.",
                        default => "Սեղան #{$tableNumber}-ի հաշիվը փոխանցված է մատուցողին։ Շուտով կմոտենան Ձեզ։",
                    }
                : match ($lang) {
                    'ru' => "Официант вызван к столику #{$tableNumber} и уже спешит к вам!",
                    'en' => "The waiter has been notified and is on the way to Table #{$tableNumber}!",
                    default => "Մատուցողը տեղեկացված է և շուտով կմոտենա Սեղան #{$tableNumber}-ին։",
                };

                return [
                    'reply' => $reply,
                    'suggested_products' => [],
                    'quick_replies' => [
                        match ($lang) {
                            'ru' => 'Посмотреть десерты', 'en' => 'View desserts', default => 'Տեսնել աղանդերը'
                        },
                        match ($lang) {
                            'ru' => 'Меню напитков', 'en' => 'Drinks menu', default => 'Ըմպելիքներ'
                        },
                    ],
                    'action' => 'call_waiter',
                    'intent' => 'waiter_call',
                ];
            }

            $reply = match ($lang) {
                'ru' => 'Вызов официанта и запрос счёта доступны только для гостей за столиком в заведении.',
                'en' => 'Calling a waiter or requesting the bill is available only for seated guests at a table.',
                default => 'Մատուցողի կանչը կամ հաշվի պահանջը հասանելի է միայն ռեստորանում՝ սեղանի շուրջ գտնվող հյուրերի համար։',
            };

            return [
                'reply' => $reply,
                'suggested_products' => [],
                'quick_replies' => [
                    match ($lang) {
                        'ru' => 'Забронировать столик', 'en' => 'Book a table', default => 'Ամրագրել սեղան'
                    },
                    match ($lang) {
                        'ru' => 'Посмотреть меню', 'en' => 'Browse menu', default => 'Տեսնել մենյուն'
                    },
                ],
                'intent' => 'waiter_call',
            ];
        }

        // 2. Table Reservation Inquiry
        if (preg_match('/(ամրագր|սեղան|резերв|столик|бронь|reserv|table|book)/ui', $msg)) {
            $isExplicitFutureBooking = (bool) preg_match('/(վաղվա|մյուս|հաջորդ|ամսի|շաբաթ|ամսաթիվ|завтра|следующ|числа|дата|tomorrow|next|date|\d{1,2}[\.\/-]\d{1,2})/ui', $msg);

            // Table QR users are already seated: never proactively suggest reservations
            if ($entryContext === 'table_qr' && ! empty($tableNumber) && ! $isExplicitFutureBooking) {
                $reply = match ($lang) {
                    'ru' => "Вы уже находитесь в ресторане за столиком #{$tableNumber}! Желаете выбрать блюда из меню или забронировать столик на другую дату?",
                    'en' => "You are already seated at Table #{$tableNumber}! Would you like help choosing dishes from our menu or booking a table for another day?",
                    default => "Դուք արդեն գտնվում եք ռեստորանում (Սեղան #{$tableNumber})։ Կարո՞ղ եմ օգնել Ձեզ ուտեստ ընտրել կամ կցանկանայի՞ք ամրագրել այլ օրվա համար։",
                };

                return [
                    'reply' => $reply,
                    'suggested_products' => [],
                    'quick_replies' => [
                        match ($lang) {
                            'ru' => 'Выбрать блюдо', 'en' => 'Browse dishes', default => 'Ընտրել ուտեստ'
                        },
                        match ($lang) {
                            'ru' => 'Бронь на другую дату', 'en' => 'Book for another day', default => 'Ամրագրել այլ օրվա համար'
                        },
                        match ($lang) {
                            'ru' => 'Позвать официанта', 'en' => 'Call waiter', default => 'Կանչել մատուցող'
                        },
                    ],
                    'intent' => 'table_reservation',
                ];
            }

            if (! $vendor->isReservationFeatureEnabled()) {
                $reply = match ($lang) {
                    'ru' => 'Онлайн-бронирование столиков в данный момент недоступно для этого заведения. Вы можете ознакомиться с нашим меню или связаться с нами напрямую.',
                    'en' => 'Online table reservations are currently disabled for this restaurant. You can browse our menu or contact us directly.',
                    default => 'Առցանց սեղանների ամրագրումն այս պահին հասանելի չէ այս ռեստորանում։ Կարող եք ծանոթանալ մենյուին կամ կապ հաստատել մեզ հետ։',
                };

                return [
                    'reply' => $reply,
                    'suggested_products' => [],
                    'quick_replies' => [
                        match ($lang) {
                            'ru' => 'Посмотреть меню', 'en' => 'Browse menu', default => 'Տեսնել մենյուն'
                        },
                    ],
                    'intent' => 'table_reservation',
                ];
            }

            $location = $locationId ? $vendor->locations->firstWhere('id', $locationId) : $vendor->locations->first();
            $availableDates = $this->reservationAvailabilityService->getAvailableDates($vendor, $location);

            $guestCount = 2;
            if (preg_match('/(\d{1,2})\s*(հոգի|անձ|человек|гост|guest|person|people)/ui', $msg, $gm)) {
                $guestCount = max(1, min(30, (int) $gm[1]));
            }

            $targetDate = ! empty($availableDates) ? $availableDates[0] : now()->toDateString();
            if (preg_match('/(վաղը|завтра|tomorrow)/ui', $msg)) {
                $targetDate = now()->addDay()->toDateString();
            }

            $slots = $this->reservationAvailabilityService->getAvailableSlots($vendor, $targetDate, $guestCount, $location);

            if (empty($slots)) {
                $reply = match ($lang) {
                    'ru' => "На дату {$targetDate} для {$guestCount} гостей свободных столиков нет. Пожалуйста, выберите другую дату или свяжитесь с рестораном.",
                    'en' => "No available time slots found on {$targetDate} for {$guestCount} guests. Please try another date.",
                    default => "{$targetDate} ամսաթվին {$guestCount} հյուրի համար ազատ ժամեր չկան։ Խնդրում ենք ընտրել այլ ամսաթիվ կամ կապ հաստատել ռեստորանի հետ։",
                };

                return [
                    'reply' => $reply,
                    'suggested_products' => [],
                    'quick_replies' => array_slice($availableDates, 0, 3),
                    'intent' => 'table_reservation',
                ];
            }

            $slotSample = implode(', ', array_slice($slots, 0, 4));
            $reply = match ($lang) {
                'ru' => "С удовольствием помогу забронировать столик! На дату {$targetDate} ({$guestCount} гостя) доступны следующие часы: {$slotSample}. Какой промежуток вам удобен?",
                'en' => "I would be happy to help reserve a table! For {$targetDate} ({$guestCount} guests), available slots are: {$slotSample}. Which time works best?",
                default => "Սիրով կօգնեմ ամրագրել սեղան։ {$targetDate} օրվա համար ({$guestCount} անձ) ունենք հետևյալ ազատ ժամերը՝ {$slotSample}։ Ո՞ր ժամն է Ձեզ հարմար:",
            };

            return [
                'reply' => $reply,
                'suggested_products' => [],
                'quick_replies' => array_slice($slots, 0, 4),
                'action' => 'reserve_table',
                'intent' => 'table_reservation',
                'updated_conversation_state' => [
                    'current_workflow' => 'reservation',
                    'reservation_draft' => [
                        'date' => $targetDate,
                        'guest_count' => $guestCount,
                        'available_slots' => $slots,
                    ],
                ],
            ];
        }

        // 3. Delivery Inquiry
        if (preg_match('/(առաք|դոստավկ|delivery|deliver)/ui', $msg)) {
            if (! $vendor->delivery_enabled) {
                $reply = match ($lang) {
                    'ru' => 'Доставка в данный момент не осуществляется. Вы можете оформить самовывоз или посетить наш ресторан лично.',
                    'en' => 'Delivery is currently unavailable at this restaurant. You can choose takeaway or dine with us in person.',
                    default => 'Առաքման ծառայությունը ներկայումս հասանելի չէ այս ռեստորանում։ Կարող եք օգտվել «Ինքս կվերցնեմ» (takeaway) տարբերակից կամ այցելել մեզ տեղում։',
                };

                return [
                    'reply' => $reply,
                    'suggested_products' => [],
                    'quick_replies' => [
                        match ($lang) {
                            'ru' => 'Самовывоз', 'en' => 'Takeaway', default => 'Ինքս կվերցնեմ'
                        },
                        match ($lang) {
                            'ru' => 'Посмотреть меню', 'en' => 'Browse menu', default => 'Տեսնել մենյուն'
                        },
                    ],
                    'intent' => 'delivery',
                ];
            }

            $minOrder = number_format((float) ($vendor->delivery_min_amount ?? $vendor->min_order_amount ?? 0));
            $fee = number_format((float) $vendor->delivery_fee);
            $freeThreshold = $vendor->free_delivery_threshold ? number_format((float) $vendor->free_delivery_threshold) : null;

            $thresholdText = $freeThreshold ? match ($lang) {
                'ru' => " (при заказе от {$freeThreshold} {$vendor->currency} — бесплатно!)",
                'en' => " (free for orders above {$freeThreshold} {$vendor->currency}!)",
                default => " ({$freeThreshold} {$vendor->currency}-ից սկսած՝ անվճար։)",
            } : '';

            $reply = match ($lang) {
                'ru' => "У нас действует доставка! Минимальный заказ: {$minOrder} {$vendor->currency}, стоимость доставки: {$fee} {$vendor->currency}{$thresholdText}. Добавьте желаемые блюда в корзину и оформите заказ.",
                'en' => "We offer delivery! Minimum order is {$minOrder} {$vendor->currency}, delivery fee is {$fee} {$vendor->currency}{$thresholdText}. Simply add dishes to your cart to order.",
                default => "Մեզ մոտ գործում է առաքում։ Նվազագույն պատվերը՝ {$minOrder} {$vendor->currency}, առաքման արժեքը՝ {$fee} {$vendor->currency}{$thresholdText}։ Կարող եք ընտրել ուտեստները մենյուից և պատվիրել։",
            };

            return [
                'reply' => $reply,
                'suggested_products' => [],
                'quick_replies' => [
                    match ($lang) {
                        'ru' => 'Выбрать блюда', 'en' => 'Browse dishes', default => 'Ընտրել ուտեստ'
                    },
                    match ($lang) {
                        'ru' => 'Открыть корзину', 'en' => 'Open cart', default => 'Բացել զամբյուղը'
                    },
                ],
                'intent' => 'delivery',
            ];
        }

        // 4. Takeaway / Pickup Inquiry
        if (preg_match('/(ինքս կվերցնեմ|տանել|վերցնել|самовывоз|takeaway|pickup|pick up)/ui', $msg)) {
            if (! $vendor->takeaway_enabled) {
                $reply = match ($lang) {
                    'ru' => 'Самовывоз (takeaway) в данный момент не поддерживается.',
                    'en' => 'Takeaway ordering is currently unavailable for this restaurant.',
                    default => '«Ինքս կվերցնեմ» (takeaway) տարբերակն այս պահին անհասանելի է։',
                };

                return [
                    'reply' => $reply,
                    'suggested_products' => [],
                    'quick_replies' => [
                        match ($lang) {
                            'ru' => 'Посмотреть меню', 'en' => 'Browse menu', default => 'Տեսնել մենյուն'
                        },
                    ],
                    'intent' => 'takeaway',
                ];
            }

            $reply = match ($lang) {
                'ru' => 'Вы можете оформить заказ на самовывоз! Выберите блюда из меню, добавьте в корзину и при оформлении укажите «Самовывоз». Заказ приготовят к вашему приходу.',
                'en' => 'You can place a takeaway pickup order! Select dishes from the menu, add them to your cart, and choose Pickup during checkout.',
                default => 'Այո, կարող եք պատվիրել առցանց և ինքներդ վերցնել ռեստորանից։ Ընտրեք ուտեստները մենյուից և ձևակերպման ժամանակ նշեք «Ինքս կվերցնեմ»։',
            };

            return [
                'reply' => $reply,
                'suggested_products' => [],
                'quick_replies' => [
                    match ($lang) {
                        'ru' => 'Выбрать блюда', 'en' => 'Browse dishes', default => 'Ընտրել ուտեստ'
                    },
                    match ($lang) {
                        'ru' => 'Открыть корзину', 'en' => 'Open cart', default => 'Բացել զամբյուղը'
                    },
                ],
                'intent' => 'takeaway',
            ];
        }

        // 5. Event Planning Inquiry
        if (preg_match('/(միջոցառ|ծնունդ|հարսանիք|կորպորատիվ|банкет|мероприят|свадьб|день рожден|event|wedding|birthday|party)/ui', $msg)) {
            if (! $vendor->isEventInquiryFeatureEnabled()) {
                $reply = match ($lang) {
                    'ru' => 'Запросы на проведение мероприятий в данный момент отключены. Вы можете забронировать столик или связаться с нами по телефону.',
                    'en' => 'Event planning inquiries are currently unavailable online. Please call the restaurant directly.',
                    default => 'Միջոցառումների կազմակերպման առցանց հարցումները ներկայումս անջատված են։ Կարող եք կապ հաստատել մեզ հետ հեռախոսով։',
                };

                return [
                    'reply' => $reply,
                    'suggested_products' => [],
                    'quick_replies' => [
                        match ($lang) {
                            'ru' => 'Посмотреть меню', 'en' => 'Browse menu', default => 'Տեսնել մենյուն'
                        },
                    ],
                    'intent' => 'event_inquiry',
                ];
            }

            $reply = match ($lang) {
                'ru' => 'Мы с радостью поможем организовать ваш праздник (день рождения, корпоратив или свадьбу)! Укажите тип мероприятия, планируемую дату и количество гостей, и наш банкетный менеджер подготовит для вас персональное предложение.',
                'en' => 'We would love to host your special event (birthday, wedding, or corporate banquet)! Please let us know the event type, estimated guest count, and date. Our event manager will contact you with a customized offer.',
                default => 'Մենք մեծ սիրով կօգնենք կազմակերպել Ձեր միջոցառումը (ծնունդ, կորպորատիվ կամ հարսանիք)։ Խնդրում ենք նշել միջոցառման տեսակը, նախատեսվող օրը և հյուրերի քանակը, և մեր մենեջերը կկապվի Ձեզ հետ անհատական առաջարկով։',
            };

            return [
                'reply' => $reply,
                'suggested_products' => [],
                'quick_replies' => [
                    match ($lang) {
                        'ru' => 'День рождения', 'en' => 'Birthday', default => 'Ծննդյան տոն'
                    },
                    match ($lang) {
                        'ru' => 'Корпоратив', 'en' => 'Corporate Event', default => 'Կորպորատիվ'
                    },
                    match ($lang) {
                        'ru' => 'Свадьба', 'en' => 'Wedding', default => 'Հարսանիք'
                    },
                    match ($lang) {
                        'ru' => 'Семейный ужин', 'en' => 'Family Dinner', default => 'Ընտանեկան հավաքույթ'
                    },
                ],
                'action' => 'event_inquiry',
                'intent' => 'event_inquiry',
                'updated_conversation_state' => [
                    'current_workflow' => 'event_inquiry',
                ],
            ];
        }

        // 6. Budget Combo / Multi-Person Dinner (e.g. 2 people under 10,000 AMD)
        $normalizedMsg = str_ireplace(
            ['երկու', 'մեկ', 'երեք', 'չորս', 'հինգ', 'двух', 'двое', 'два', 'троих', 'три', 'two', 'three', 'four'],
            ['2', '1', '3', '4', '5', '2', '2', '2', '3', '3', '2', '3', '4'],
            $msg
        );
        $cleanNumberMsg = preg_replace('/(\d+)[,\s](\d{3})/', '$1$2', $normalizedMsg);

        if (preg_match('/(\d{1,2})\s*(հոգու|անձի|человек|гостей|people|persons?).*?(\d{4,7})\s*(դրամ|դ|amd|rub|руб|\$)?/ui', $cleanNumberMsg, $cm) ||
            preg_match('/(\d{4,7})\s*(դրամ|դ|amd|rub|руб|\$)?.*?(\d{1,2})\s*(հոգու|անձի|человек|гостей|people|persons?)/ui', $cleanNumberMsg, $cm2)) {

            $party = isset($cm[1]) ? (int) $cm[1] : (int) ($cm2[3] ?? 2);
            $budget = isset($cm[3]) ? (float) $cm[3] : (float) ($cm2[1] ?? 10000);

            $validProducts = $allProducts->filter(fn ($p) => ! in_array((int) $p->id, $rejectedProductIds, true));

            $mains = $validProducts->filter(function ($p) {
                $c = mb_strtolower($p->category?->name ?? '');

                return ! $this->isBeverageCategory($c) && ! str_contains($c, 'dessert') && ! str_contains($c, 'դեսերտ');
            })->sortBy(fn ($p) => $p->getEffectivePrice($locationId));

            $drinks = $validProducts->filter(function ($p) {
                $c = mb_strtolower($p->category?->name ?? '');

                return $this->isBeverageCategory($c);
            });

            $selectedCombo = [];
            $currentTotal = 0.0;

            foreach ($mains as $m) {
                $price = (float) $m->getEffectivePrice($locationId);
                if ($currentTotal + $price <= $budget && count($selectedCombo) < $party) {
                    $selectedCombo[] = $m;
                    $currentTotal += $price;
                }
            }

            foreach ($drinks as $d) {
                $price = (float) $d->getEffectivePrice($locationId);
                if ($currentTotal + $price <= $budget) {
                    $selectedCombo[] = $d;
                    $currentTotal += $price;
                    break;
                }
            }

            if (! empty($selectedCombo)) {
                $comboList = collect($selectedCombo)->map(function ($p) use ($lang, $locationId, $vendor) {
                    return '«'.$p->getTranslatedName($lang).'» ('.number_format($p->getEffectivePrice($locationId)).' '.$vendor->currency.')';
                })->implode(', ');

                $formattedTotal = number_format($currentTotal);
                $formattedBudget = number_format($budget);

                $reply = match ($lang) {
                    'ru' => "Для {$party} человек в бюджете до {$formattedBudget} {$vendor->currency} предлагаю отличный сет: {$comboList}. Итоговая сумма: {$formattedTotal} {$vendor->currency}.",
                    'en' => "For {$party} guests within {$formattedBudget} {$vendor->currency}, I recommend: {$comboList}. Total: {$formattedTotal} {$vendor->currency}.",
                    default => "{$party} անձի համար մինչև {$formattedBudget} {$vendor->currency} բյուջեում առաջարկում եմ հետևյալ համադրությունը՝ {$comboList}։ Ընդհանուր գումարը՝ {$formattedTotal} {$vendor->currency}։",
                };

                return [
                    'reply' => $reply,
                    'suggested_products' => collect($selectedCombo)->map(fn ($p) => $this->formatProductPayload($p, 96, '', $vendor, $lang, $locationId))->values()->toArray(),
                    'quick_replies' => [
                        match ($lang) {
                            'ru' => 'Добавить в корзину', 'en' => 'Add to cart', default => 'Ավելացնել զամբյուղ'
                        },
                        match ($lang) {
                            'ru' => 'Другие варианты', 'en' => 'Show other options', default => 'Ցույց տալ այլ տարբերակներ'
                        },
                    ],
                    'action' => 'budget_combo',
                    'intent' => 'budget_combo',
                ];
            }
        }

        // 7. Direct Chicken / Poultry Dish Inquiry (e.g. Ի՞նչ համեղ բան կառաջարկես հավով)
        if (preg_match('/(հավ|chicken|куриц|птиц)/ui', $msg)) {
            $chickenDishes = $allProducts->filter(function ($p) use ($rejectedProductIds, $lang) {
                if (in_array((int) $p->id, $rejectedProductIds, true)) {
                    return false;
                }
                $text = mb_strtolower($p->name.' '.$p->getTranslatedName($lang).' '.($p->description ?? '').' '.($p->category?->name ?? ''));

                return preg_match('/(հավ|chicken|куриц|птиц)/ui', $text);
            })->take(3);

            if ($chickenDishes->isNotEmpty()) {
                $names = $chickenDishes->map(fn ($p) => '«'.$p->getTranslatedName($lang).'» ('.number_format($p->getEffectivePrice($locationId)).' '.$vendor->currency.')')->implode(', ');
                $reply = match ($lang) {
                    'ru' => "Из блюд с курицей рекомендую: {$names}. Нежное филе и фирменные соусы!",
                    'en' => "From our chicken specialties, I highly recommend: {$names}. Prepared tender and flavorful!",
                    default => "Հավի մսով մեր մենյուում հատկապես առաջարկում եմ՝ {$names}: Պատրաստվում են թարմ և համեղ բաղադրիչներով:",
                };

                return [
                    'reply' => $reply,
                    'suggested_products' => $chickenDishes->map(fn ($p) => $this->formatProductPayload($p, 97, '', $vendor, $lang, $locationId))->values()->toArray(),
                    'quick_replies' => [
                        match ($lang) {
                            'ru' => 'Добавить в корзину', 'en' => 'Add to cart', default => 'Ավելացնել զամբյուղ'
                        },
                        match ($lang) {
                            'ru' => 'Подробнее', 'en' => 'View details', default => 'Տեսնել մանրամասները'
                        },
                        match ($lang) {
                            'ru' => 'Другие варианты', 'en' => 'Show other options', default => 'Ցույց տալ այլ տարբերակներ'
                        },
                    ],
                    'intent' => 'menu_recommendation',
                ];
            }
        }

        // 8. Restaurant General Information (Hours, Location, Contacts)
        if (preg_match('/(ժամ[եր]?|հասցե|wifi|վայֆայ|հեռախոս|կոնտակտ|վճար|քարտ|часы|время работы|адрес|где вы|вайфай|телефон|оплата|hours|location|address|contact|wifi)/ui', $msg)) {
            $addr = $vendor->address ?? $vendor->locations()->first()?->address ?? 'Центр города';
            $phone = $vendor->phone ?? $vendor->locations()->first()?->phone ?? '';
            $hours = $vendor->opening_hours ?? '10:00 - 23:00';

            $reply = match ($lang) {
                'ru' => "Информация о «{$vendor->name}»:\n📍 Адрес: {$addr}\n🕒 Часы работы: {$hours}".($phone ? "\n📞 Телефон: {$phone}" : ''),
                'en' => "Information about «{$vendor->name}»:\n📍 Address: {$addr}\n🕒 Opening Hours: {$hours}".($phone ? "\n📞 Phone: {$phone}" : ''),
                default => "«{$vendor->name}»-ի տվյալները՝\n📍 Հասցե՝ {$addr}\n🕒 Աշխատանքային ժամեր՝ {$hours}".($phone ? "\n📞 Հեռախոս՝ {$phone}" : ''),
            };

            return [
                'reply' => $reply,
                'suggested_products' => [],
                'quick_replies' => [
                    match ($lang) {
                        'ru' => 'Посмотреть меню', 'en' => 'Browse menu', default => 'Տեսնել մենյուն'
                    },
                    match ($lang) {
                        'ru' => 'Забронировать столик', 'en' => 'Book a table', default => 'Ամրագրել սեղան'
                    },
                ],
                'intent' => 'restaurant_info',
            ];
        }

        // 9. Drinks / Wine / Beverages pairing inquiry
        if (preg_match('/(գին[իու]|խմիչք|ըմպելիք|կոկտեյլ|գարեջուր|սուրճ|թեյ|շամպայն|հյութ|wine|drink|beverage|cocktail|beer|coffee|tea|вино|напиток|коктейль|пиво|кофе|чай)/ui', $msg)) {
            $drinks = $allProducts->filter(function ($p) {
                $c = mb_strtolower($p->category?->name ?? '');
                $name = mb_strtolower($p->name.' '.$p->description);

                return $this->isBeverageCategory($c) || preg_match('/(գինի|կոնյակ|վիսկի|օղի|կոկտեյլ|հյութ|գարեջուր|սուրճ|թեյ|wine|cocktail|beer|juice|drink|водка|вино)/ui', $name);
            });

            if ($drinks->isNotEmpty()) {
                if (preg_match('/(գին[իու]|wine|вин[оа])/ui', $msg)) {
                    $wineDishes = $drinks->filter(fn ($p) => preg_match('/(գինի|wine|вин[оа])/ui', $p->name.' '.$p->description));
                    if ($wineDishes->isNotEmpty()) {
                        $drinks = $wineDishes;
                    }
                }

                $selectedDrinks = $drinks->take(3);
                $names = $selectedDrinks->map(fn ($p) => '«'.$p->getTranslatedName($lang).'» ('.number_format($p->getEffectivePrice($locationId)).' '.$vendor->currency.')')->implode(', ');

                $reply = match ($lang) {
                    'en' => "For a delightful pairing, I recommend our finest selections: {$names}. They complement our dishes perfectly!",
                    'ru' => "К вашим блюдам идеально подойдут наши напитки: {$names}. Великолепный букет вкуса!",
                    default => "Մեր խմիչքների և գինիների ընտրանուց խորհուրդ կտամ՝ {$names}: Իդեալական կլրացնեն Ձեր պատվերը:",
                };

                return [
                    'reply' => $reply,
                    'suggested_products' => $selectedDrinks->map(fn ($p) => $this->formatProductPayload($p, 95, '', $vendor, $lang, $locationId))->values()->toArray(),
                    'intent' => 'menu_recommendation',
                ];
            }
        }

        // 10. Without meat / Vegetarian / Vegan
        if (preg_match('/(առանց մսի|բուսակեր|վեգան|vegetarian|vegan|без мяса|вегетариан)/ui', $msg)) {
            $vegDishes = $allProducts->filter(function ($p) {
                $c = mb_strtolower($p->category?->name ?? '');
                $tags = (array) ($p->dietary_tags ?? []);

                return in_array('vegetarian', $tags) || in_array('vegan', $tags) || str_contains($c, 'salad') || str_contains($c, 'աղցան');
            })->take(3);

            if ($vegDishes->isNotEmpty()) {
                $names = $vegDishes->map(fn ($p) => '«'.$p->getTranslatedName($lang).'» ('.number_format($p->getEffectivePrice($locationId)).' '.$vendor->currency.')')->implode(', ');
                $reply = match ($lang) {
                    'en' => "We have wonderful vegetarian selections: {$names}. Freshly prepared and bursting with flavor!",
                    'ru' => "У нас есть прекрасные блюда без мяса: {$names}. Очень свежие и аппетитные!",
                    default => "Մեր մենյուում ունենք հիանալի բուսական և թեթև տարբերակներ՝ {$names}: Բոլորն էլ պատրաստվում են թարմ բաղադրիչներով:",
                };

                return [
                    'reply' => $reply,
                    'suggested_products' => $vegDishes->map(fn ($p) => $this->formatProductPayload($p, 95, '', $vendor, $lang, $locationId))->values()->toArray(),
                    'intent' => 'menu_recommendation',
                ];
            }
        }

        // 11. Soups
        if (preg_match('/(ապուր|սուպ|soup|суп)/ui', $msg)) {
            $soups = $allProducts->filter(function ($p) {
                $text = mb_strtolower($p->name.' '.$p->description.' '.($p->category?->name ?? ''));

                return preg_match('/(ապուր|soup|суп)/ui', $text);
            })->take(3);

            if ($soups->isNotEmpty()) {
                $names = $soups->map(fn ($p) => '«'.$p->getTranslatedName($lang).'» ('.number_format($p->getEffectivePrice($locationId)).' '.$vendor->currency.')')->implode(', ');
                $reply = match ($lang) {
                    'en' => "From our comforting soup selection, I highly suggest: {$names}!",
                    'ru' => "Из наших горячих супов рекомендую попробовать: {$names}!",
                    default => "Մեր տաք ապուրների ընտրանուց հատկապես խորհուրդ կտամ՝ {$names}:",
                };

                return [
                    'reply' => $reply,
                    'suggested_products' => $soups->map(fn ($p) => $this->formatProductPayload($p, 95, '', $vendor, $lang, $locationId))->values()->toArray(),
                    'intent' => 'menu_recommendation',
                ];
            }
        }

        // 12. Desserts & Sweets
        if (preg_match('/(աղանդեր|քաղցր|դեսերտ|տորթ|dessert|sweet|cake|десерт|сладк)/ui', $msg)) {
            $desserts = $allProducts->filter(function ($p) {
                $text = mb_strtolower($p->name.' '.$p->description.' '.($p->category?->name ?? ''));

                return preg_match('/(աղանդեր|դեսերտ|տորթ|dessert|sweet|cake|десерт)/ui', $text);
            })->take(3);

            if ($desserts->isNotEmpty()) {
                $names = $desserts->map(fn ($p) => '«'.$p->getTranslatedName($lang).'» ('.number_format($p->getEffectivePrice($locationId)).' '.$vendor->currency.')')->implode(', ');
                $reply = match ($lang) {
                    'en' => "To satisfy your sweet tooth, I recommend our decadent desserts: {$names}!",
                    'ru' => "На десерт рекомендую наши фирменные сладости: {$names}!",
                    default => "Աղանդերի համար առաջարկում եմ մեր ամենասիրված քաղցրավենիքները՝ {$names}:",
                };

                return [
                    'reply' => $reply,
                    'suggested_products' => $desserts->map(fn ($p) => $this->formatProductPayload($p, 95, '', $vendor, $lang, $locationId))->values()->toArray(),
                    'intent' => 'menu_recommendation',
                ];
            }
        }

        // 13. Keyword & Ingredient Stem Matching across all dishes
        $words = preg_split('/[^\p{L}\p{N}]+/u', $msg, -1, PREG_SPLIT_NO_EMPTY);
        $stopWords = ['ինչ', 'ունեք', 'մենյուում', 'հետ', 'իսկ', 'խնդրում', 'եմ', 'մի', 'բան', 'ուտել', 'տվեք', 'որն', 'է', 'եւ', 'և', 'կամ', 'the', 'and', 'what', 'have', 'you', 'with', 'for', 'что', 'есть', 'вас', 'меню', 'или'];
        $cleanWords = array_filter($words, fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, $stopWords, true));

        if (! empty($cleanWords)) {
            $scoredProducts = [];
            foreach ($allProducts as $p) {
                if (in_array((int) $p->id, $rejectedProductIds, true)) {
                    continue;
                }
                $haystack = mb_strtolower($p->name.' '.$p->getTranslatedName($lang).' '.($p->description ?? '').' '.($p->category?->name ?? ''));
                $matchScore = 0;
                $matchedKeyword = '';

                foreach ($cleanWords as $cw) {
                    $stem = mb_substr($cw, 0, max(4, mb_strlen($cw) - 2));
                    if (str_contains($haystack, $cw) || str_contains($haystack, $stem)) {
                        $matchScore += 10;
                        if (empty($matchedKeyword)) {
                            $matchedKeyword = $cw;
                        }
                    }
                }

                if ($matchScore > 0) {
                    $scoredProducts[] = ['product' => $p, 'score' => $matchScore, 'keyword' => $matchedKeyword];
                }
            }

            if (! empty($scoredProducts)) {
                usort($scoredProducts, fn ($a, $b) => $b['score'] <=> $a['score']);
                $bestMatches = array_slice(array_column($scoredProducts, 'product'), 0, 3);
                $kw = $scoredProducts[0]['keyword'] ?? '';
                $names = collect($bestMatches)->map(fn ($p) => '«'.$p->getTranslatedName($lang).'» ('.number_format($p->getEffectivePrice($locationId)).' '.$vendor->currency.')')->implode(', ');

                $reply = match ($lang) {
                    'en' => "Looking for dishes with {$kw}? I recommend: {$names}.",
                    'ru' => "Блюда с ингредиентом «{$kw}»: {$names}. Очень рекомендую попробовать!",
                    default => "«{$kw}» բաղադրիչով մեր մենյուում առաջարկում ենք՝ {$names}:",
                };

                return [
                    'reply' => $reply,
                    'suggested_products' => collect($bestMatches)->map(fn ($p) => $this->formatProductPayload($p, 96, '', $vendor, $lang, $locationId))->values()->toArray(),
                    'intent' => 'menu_recommendation',
                ];
            }
        }

        // 14. Rotating Chef's Specials (avoiding rejected dishes)
        $candidates = $allProducts->filter(fn ($p) => ! in_array((int) $p->id, $rejectedProductIds, true))
            ->sortByDesc(function ($p) {
                return ($p->is_featured ? 50 : 0) + ($p->ai_priority ? 40 : 0) + rand(1, 20);
            })->take(2);

        $featuredNames = $candidates->map(fn ($p) => '«'.$p->getTranslatedName($lang).'»')->implode(', ');

        $reply = match ($lang) {
            'en' => "At {$vendor->name}, our guests love {$featuredNames}. May I assist you with ingredients, beverage pairings or specific dietary preferences?",
            'ru' => "В {$vendor->name} гости особенно хвалят {$featuredNames}. Подсказать вам ингредиенты, напитки или подходящие блюда?",
            default => "«{$vendor->name}»-ում հատկապես սիրված են {$featuredNames} ուտեստները: Կցանկանա՞ք իմանալ բաղադրիչների, համապատասխան գինիների կամ այլ ուտեստների մասին:",
        };

        return [
            'reply' => $reply,
            'suggested_products' => $candidates->map(fn ($p) => $this->formatProductPayload($p, 90, '', $vendor, $lang, $locationId))->values()->toArray(),
            'intent' => 'menu_recommendation',
        ];
    }

    /**
     * Find products referenced in generated text.
     *
     * @param  Collection<int, Product>  $allProducts
     * @return array<int, mixed>
     */
    protected function findMentionedProductsInText(string $text, Collection $allProducts, string $lang, Vendor $vendor, ?int $locationId = null): array
    {
        $textLower = mb_strtolower($text);
        $found = [];

        foreach ($allProducts as $p) {
            $nameEn = mb_strtolower($p->name);
            $nameLoc = mb_strtolower($p->getTranslatedName($lang));

            if (str_contains($textLower, $nameEn) || str_contains($textLower, $nameLoc)) {
                $found[] = $this->formatProductPayload($p, 95, '', $vendor, $lang, $locationId);
                if (count($found) >= 3) {
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * Determine if a category is for beverages.
     */
    public function isBeverageCategory(string $categoryName): bool
    {
        $cat = trim(mb_strtolower($categoryName));
        if ($cat === '') {
            return false;
        }

        $pattern = '/\b(drinks?|beverages?|cocktails?|wines?|beers?|bar|coffee|teas?|խմիչք[ա-ֆ]*|կոկտեյլ[ա-ֆ]*|գինի[ա-ֆ]*|գարեջուր|սուրճ|թեյ|напитк[а-я]*|коктейл[а-я]*|вин[ао][а-я]*|пив[оа]|кофе|чай)\b/ui';

        return (bool) preg_match($pattern, $cat);
    }

    /**
     * Generate narrative commentary from AI Sommelier.
     *
     * @param  array<int, mixed>  $recommendations
     * @param  array<string, mixed>  $preferences
     */
    protected function generateSommelierCommentary(
        Vendor $vendor,
        array $recommendations,
        array $preferences,
        ?string $prompt,
        string $lang
    ): string {
        $waiterName = $vendor->getAiWaiterName();

        if (! empty($recommendations)) {
            try {
                $dishNames = implode(', ', array_column($recommendations, 'name'));
                $promptText = "You are {$waiterName}, a friendly and ultra-sophisticated AI waiter & sommelier at '{$vendor->name}'. Write a short (2-3 sentences), warm, appetizing and enthusiastic recommendation directly to the guest in the requested language ({$lang}). Explain why the selected dishes ({$dishNames}) are the perfect choice for their taste and occasion. Keep it elegant, concise, and without any markdown bullet points.";

                $aiText = $this->aiGateway->generateText($vendor, $promptText, ['timeout' => 4]);
                if (! empty($aiText)) {
                    return $aiText;
                }
            } catch (\Throwable $e) {
                Log::info('AI waiter commentary skipped, using expert template: '.$e->getMessage());
            }
        }

        $dishSeparator = match ($lang) {
            'hy' => ' և ',
            'ru' => ' и ',
            'fr' => ' et ',
            'de' => ' und ',
            'es' => ' y ',
            default => ' and ',
        };
        $dishNames = ! empty($recommendations) ? implode($dishSeparator, array_column(array_slice($recommendations, 0, 2), 'name')) : '';

        return match ($lang) {
            'hy' => "Ողջույն! Ես {$waiterName}-ն եմ՝ Ձեր անձնական խոհարարական խորհրդատուն: Ելնելով Ձեր նախասիրություններից՝ ընտրել եմ մեր մենյուի լավագույն ճաշատեսակները՝ «{$dishNames}»: Յուրաքանչյուր ուտեստի հետ պատրաստել եմ նաև համահունչ ըմպելիքների զուգորդումներ, որոնք կդարձնեն Ձեր այցը անմոռանալի:",
            'ru' => "Здравствуйте! Я {$waiterName}, ваш персональный гастрономический консультант. Основываясь на ваших пожеланиях, я подобрал лучшие блюда: {$dishNames}. Каждое из них дополнено идеально гармонирующими напитками для великолепного вечера!",
            'fr' => "Bonjour ! Je suis {$waiterName}, votre conseiller gastronomique personnel. Selon vos préférences, j'ai sélectionné nos meilleures spécialités : {$dishNames}. Chacune est accompagnée de boissons harmonieuses pour un moment inoubliable !",
            'de' => "Hallo! Ich bin {$waiterName}, Ihr persönlicher Genussberater. Basierend auf Ihren Vorlieben habe ich unsere besten Spezialitäten ausgewählt: {$dishNames}. Jedes Gericht wird von passenden Getränken begleitet, um Ihren Besuch unvergesslich zu machen!",
            'es' => "¡Hola! Soy {$waiterName}, su asesor gastronómico personal. Según sus preferencias, he seleccionado nuestras especialidades destacadas: {$dishNames}. ¡Cada plato se combina con bebidas ideales para una velada inolvidable!",
            default => "Hello! I am {$waiterName}, your personal dining advisor. Based on your preferences, I have hand-picked our standout specialties: {$dishNames}. Each dish is paired with the finest drinks to create an unforgettable gastronomic journey for you!",
        };
    }

    /**
     * Default empty commentary.
     */
    protected function getDefaultEmptyCommentary(string $lang): string
    {
        return match ($lang) {
            'hy' => 'Բարի գալուստ! Խնդրում ենք ծանոթանալ մեր մենյուին կամ փոփոխել նախասիրությունները՝ լավագույն առաջարկները տեսնելու համար:',
            'ru' => 'Добро пожаловать! Ознакомьтесь с нашим меню или измените фильтры, чтобы найти подходящие блюда.',
            'fr' => 'Bienvenue ! Veuillez parcourir notre menu ou ajuster vos critères pour découvrir nos spécialités.',
            'de' => 'Willkommen! Bitte durchsuchen Sie unsere Speisekarte oder passen Sie Ihre Suche an.',
            'es' => '¡Bienvenido! Explore nuestro menú o ajuste sus preferencias para descubrir nuestras especialidades.',
            default => 'Welcome! Please browse our curated menu or adjust your search to discover our specialties.',
        };
    }
}
