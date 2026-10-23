<?php

namespace App\Services;

use App\Models\Vendor;
use App\Services\Security\SsrfProtectionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiGatewayService
{
    /**
     * Provider metadata dictionary.
     */
    public const PROVIDERS = [
        'gemini' => [
            'name' => 'Google Gemini',
            'badge' => 'GEMINI 3.6',
            'description' => 'Google-ի գերարագ, մուլտիմոդալ մոդելներ (անվճար քվոտա և բարձր արագություն)',
            'icon' => 'fa-solid fa-gem',
            'color' => '#4285F4',
            'default_model' => 'gemini-3.6-flash',
            'models' => [
                'gemini-3.6-flash' => 'Gemini 3.6 Flash (Առաջարկվող & Գերարագ - Լռելյայն)',
                'gemini-3.5-flash' => 'Gemini 3.5 Flash (Արագ & Կայուն)',
                'gemini-flash-latest' => 'Gemini Flash Latest (Ավտոմատ նորագույն)',
                'gemini-3.1-pro-preview' => 'Gemini 3.1 Pro (Բարձր ինտելեկտ)',
            ],
            'key_url' => 'https://aistudio.google.com/app/apikey',
            'doc_url' => 'https://aistudio.google.com/app/apikey',
            'supports_vision' => true,
        ],
        'openai' => [
            'name' => 'OpenAI (ChatGPT)',
            'badge' => 'GPT-4o',
            'description' => 'Համաշխարհային ստանդարտ GPT-4o, GPT-4o-mini և o3-mini մոդելներ',
            'icon' => 'fa-solid fa-circle-nodes',
            'color' => '#10a37f',
            'default_model' => 'gpt-4o-mini',
            'models' => [
                'gpt-4o-mini' => 'GPT-4o Mini (Օպտիմալ արագություն և գին - Լռելյայն)',
                'gpt-4o' => 'GPT-4o (Ֆլագմանական մուլտիմոդալ)',
                'o3-mini' => 'o3-mini (Տրամաբանական խորը վերլուծություն)',
                'gpt-4-turbo' => 'GPT-4 Turbo',
                'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
            ],
            'key_url' => 'https://platform.openai.com/api-keys',
            'doc_url' => 'https://platform.openai.com/api-keys',
            'supports_vision' => true,
        ],
        'claude' => [
            'name' => 'Anthropic Claude',
            'badge' => 'CLAUDE 3.7',
            'description' => 'Պրեմիում տեքստային որակ և նրբաճաշակ խոհարարական խորհրդատվություն',
            'icon' => 'fa-solid fa-feather-pointed',
            'color' => '#d97706',
            'default_model' => 'claude-3-5-haiku-20241022',
            'models' => [
                'claude-3-5-haiku-20241022' => 'Claude 3.5 Haiku (Կայծակնային արագ - Լռելյայն)',
                'claude-3-7-sonnet-20250219' => 'Claude 3.7 Sonnet (Հիբրիդային մտածողություն)',
                'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (Պրեմիում խոհարարական ինտելեկտ)',
                'claude-3-opus-20240229' => 'Claude 3 Opus',
            ],
            'key_url' => 'https://console.anthropic.com/settings/keys',
            'doc_url' => 'https://console.anthropic.com/settings/keys',
            'supports_vision' => true,
        ],
        'deepseek' => [
            'name' => 'DeepSeek',
            'badge' => 'ULTRA-LOW COST',
            'description' => 'DeepSeek V3 & R1՝ գերմատչելի գին, հզոր տրամաբանություն և OpenAI-compatible API',
            'icon' => 'fa-solid fa-compass',
            'color' => '#2563eb',
            'default_model' => 'deepseek-chat',
            'models' => [
                'deepseek-chat' => 'DeepSeek-V3 (Գերմատչելի և հզոր - Լռելյայն)',
                'deepseek-reasoner' => 'DeepSeek-R1 (Խորը տրամաբանություն)',
            ],
            'default_base_url' => 'https://api.deepseek.com',
            'key_url' => 'https://platform.deepseek.com/api_keys',
            'doc_url' => 'https://platform.deepseek.com/api_keys',
            'supports_vision' => false,
        ],
        'groq' => [
            'name' => 'Groq (Ultra-Fast)',
            'badge' => '500+ TOK/S',
            'description' => 'Groq LPU ապարատային գերարագ արձագանք Llama 3.3 և Mixtral մոդելներով',
            'icon' => 'fa-solid fa-bolt',
            'color' => '#f97316',
            'default_model' => 'llama-3.3-70b-versatile',
            'models' => [
                'llama-3.3-70b-versatile' => 'Llama 3.3 70B (500+ tok/s - Լռելյայն)',
                'mixtral-8x7b-32768' => 'Mixtral 8x7B (Երկար կոնտեքստ)',
                'gemma2-9b-it' => 'Gemma 2 9B IT',
            ],
            'default_base_url' => 'https://api.groq.com/openai/v1',
            'key_url' => 'https://console.groq.com/keys',
            'doc_url' => 'https://console.groq.com/keys',
            'supports_vision' => false,
        ],
        'openrouter' => [
            'name' => 'OpenRouter (All-in-One)',
            'badge' => 'ROUTER',
            'description' => 'Բոլոր AI մոդելները մեկ ընդհանուր հաշվեկշռով և միասնական API ինտերֆեյսով',
            'icon' => 'fa-solid fa-network-wired',
            'color' => '#8b5cf6',
            'default_model' => 'openai/gpt-4o-mini',
            'models' => [
                'openai/gpt-4o-mini' => 'OpenAI GPT-4o Mini (via OpenRouter)',
                'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet (via OpenRouter)',
                'meta-llama/llama-3.3-70b-instruct' => 'Llama 3.3 70B (via OpenRouter)',
                'deepseek/deepseek-chat' => 'DeepSeek V3 (via OpenRouter)',
            ],
            'default_base_url' => 'https://openrouter.ai/api/v1',
            'key_url' => 'https://openrouter.ai/keys',
            'doc_url' => 'https://openrouter.ai/keys',
            'supports_vision' => true,
        ],
        'custom' => [
            'name' => 'Custom / Local (Ollama, vLLM)',
            'badge' => 'SELF-HOSTED',
            'description' => 'Տեղային Ollama, vLLM կամ ցանկացած այլ OpenAI-compatible API endpoint',
            'icon' => 'fa-solid fa-server',
            'color' => '#64748b',
            'default_model' => 'custom-model',
            'models' => [
                'custom-model' => 'Custom Model (Specify below)',
            ],
            'default_base_url' => 'http://localhost:11434/v1',
            'key_url' => '',
            'doc_url' => '',
            'supports_vision' => true,
        ],
    ];

    public function __construct(
        protected ?AiQuotaService $quotaService = null
    ) {
        $this->quotaService = $quotaService ?? app(AiQuotaService::class);
    }

    /**
     * Generate plain text using vendor configured AI provider.
     */
    public function generateText(Vendor $vendor, string $prompt, array $options = []): string
    {
        $provider = $vendor->getAiProvider();
        $model = $vendor->getAiModel();
        $apiKey = $vendor->getAiApiKey();
        $baseUrl = $vendor->getAiBaseUrl();
        $timeout = $options['timeout'] ?? 15;
        $session = $options['session'] ?? null;

        if (empty($apiKey) && $provider !== 'custom') {
            return '';
        }

        // 1. Quota Enforcement: Prevent uncontrolled AI costs
        $quotaCheck = $this->quotaService->checkQuotas($vendor);
        if (! $quotaCheck['allowed']) {
            Log::warning("AI request blocked by quota [vendor: {$vendor->id}]: {$quotaCheck['reason']}");
            $this->quotaService->logUsage(
                $vendor,
                $session,
                $provider,
                $model,
                $this->quotaService->estimateTokens($prompt),
                0,
                0,
                'quota_exceeded',
                $quotaCheck['reason']
            );

            return '';
        }

        $startTime = microtime(true);

        try {
            $result = match ($provider) {
                'gemini' => $this->callGeminiText($apiKey, $model, $prompt, $timeout),
                'claude' => $this->callClaudeText($apiKey, $model, $prompt, $timeout),
                default => $this->callOpenAiCompatibleText($provider, $apiKey, $model, $prompt, $baseUrl, $timeout),
            };

            $latency = (int) round((microtime(true) - $startTime) * 1000);
            $inputTokens = $this->quotaService->estimateTokens($prompt);
            $outputTokens = $this->quotaService->estimateTokens($result);

            $this->quotaService->logUsage(
                $vendor,
                $session,
                $provider,
                $model,
                $inputTokens,
                $outputTokens,
                $latency,
                'success'
            );

            return $result;
        } catch (\Throwable $e) {
            $latency = (int) round((microtime(true) - $startTime) * 1000);
            Log::warning("AiGateway generateText failed [{$provider}/{$model}]: ".$e->getMessage());

            $this->quotaService->logUsage(
                $vendor,
                $session,
                $provider,
                $model,
                $this->quotaService->estimateTokens($prompt),
                0,
                $latency,
                'failed',
                $e->getMessage()
            );

            return '';
        }
    }

    /**
     * Generate and extract structured JSON from vendor configured AI provider.
     */
    public function generateStructuredJson(Vendor $vendor, string $prompt, array $options = []): ?array
    {
        $rawText = $this->generateText($vendor, $prompt, $options);
        if (empty($rawText)) {
            return null;
        }

        $cleaned = trim($rawText);

        // 1. Direct JSON parse
        $decoded = json_decode($cleaned, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // 2. Strip markdown code fence if present
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $cleaned, $codeBlock)) {
            $decoded = json_decode(trim($codeBlock[1]), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // 3. Robust substring extraction between outermost { and }
        $firstBrace = strpos($cleaned, '{');
        $lastBrace = strrpos($cleaned, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $jsonStr = substr($cleaned, $firstBrace, $lastBrace - $firstBrace + 1);
            $decoded = json_decode($jsonStr, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // 4. Robust substring extraction between outermost [ and ]
        $firstBracket = strpos($cleaned, '[');
        $lastBracket = strrpos($cleaned, ']');
        if ($firstBracket !== false && $lastBracket !== false && $lastBracket > $firstBracket) {
            $jsonStr = substr($cleaned, $firstBracket, $lastBracket - $firstBracket + 1);
            $decoded = json_decode($jsonStr, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Analyze image (OCR / Menu recognition) via Vision-capable LLM.
     */
    public function analyzeImage(Vendor $vendor, string $prompt, string $base64Data, string $mimeType): ?array
    {
        $provider = $vendor->getAiProvider();
        $model = $vendor->getAiModel();
        $apiKey = $vendor->getAiApiKey();
        $baseUrl = $vendor->getAiBaseUrl();

        // If current provider does not support vision (e.g. deepseek, groq), fallback to Gemini
        if (! (self::PROVIDERS[$provider]['supports_vision'] ?? false)) {
            $provider = 'gemini';
            $model = 'gemini-3.6-flash';
            $apiKey = config('services.gemini.key') ?? env('GEMINI_API_KEY');
        }

        if ($provider === 'gemini' && in_array($model, [
            'gemini-2.0-flash', 'gemini-2.0-flash-exp', 'gemini-2.0-flash-thinking-exp',
            'gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.5-flash-lite',
            'gemini-1.5-flash', 'gemini-1.5-pro',
        ])) {
            $model = 'gemini-3.6-flash';
        }

        if (empty($apiKey)) {
            return null;
        }

        try {
            if ($provider === 'gemini') {
                $response = Http::timeout(35)->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                    [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt],
                                    [
                                        'inlineData' => [
                                            'mimeType' => $mimeType,
                                            'data' => $base64Data,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ]
                );

                if ($response->successful()) {
                    $jsonText = $response->json('candidates.0.content.parts.0.text') ?? '';
                    preg_match('/\{.*\}/s', $jsonText, $matches);
                    if (! empty($matches[0])) {
                        return json_decode($matches[0], true);
                    }
                }
            } elseif ($provider === 'claude') {
                $response = Http::timeout(35)
                    ->withHeaders([
                        'x-api-key' => $apiKey,
                        'anthropic-version' => '2023-06-01',
                        'content-type' => 'application/json',
                    ])
                    ->post('https://api.anthropic.com/v1/messages', [
                        'model' => $model,
                        'max_tokens' => 4096,
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => [
                                    [
                                        'type' => 'image',
                                        'source' => [
                                            'type' => 'base64',
                                            'media_type' => $mimeType,
                                            'data' => $base64Data,
                                        ],
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $prompt,
                                    ],
                                ],
                            ],
                        ],
                    ]);

                if ($response->successful()) {
                    $text = $response->json('content.0.text') ?? '';
                    preg_match('/\{.*\}/s', $text, $matches);
                    if (! empty($matches[0])) {
                        return json_decode($matches[0], true);
                    }
                }
            } else {
                // OpenAI-compatible Vision
                $url = rtrim($baseUrl ?: 'https://api.openai.com/v1', '/').'/chat/completions';
                $response = Http::timeout(35)
                    ->withHeaders(['Authorization' => "Bearer {$apiKey}"])
                    ->post($url, [
                        'model' => $model,
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => [
                                    ['type' => 'text', 'text' => $prompt],
                                    [
                                        'type' => 'image_url',
                                        'image_url' => [
                                            'url' => "data:{$mimeType};base64,{$base64Data}",
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ]);

                if ($response->successful()) {
                    $text = $response->json('choices.0.message.content') ?? '';
                    preg_match('/\{.*\}/s', $text, $matches);
                    if (! empty($matches[0])) {
                        return json_decode($matches[0], true);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("AiGateway analyzeImage failed [{$provider}/{$model}]: ".$e->getMessage());
        }

        return null;
    }

    /**
     * Test connection to selected AI Provider with given credentials.
     *
     * @return array{success: bool, message: string, latency_ms?: int}
     */
    public function testConnection(string $provider, ?string $apiKey, ?string $model, ?string $baseUrl = null): array
    {
        $startTime = microtime(true);

        if (empty($apiKey) && $provider !== 'custom') {
            return [
                'success' => false,
                'message' => 'API Key-ը դատարկ է: Խնդրում ենք մուտքագրել վավեր API Key:',
            ];
        }

        $model = $model ?: (self::PROVIDERS[$provider]['default_model'] ?? 'gemini-3.6-flash');

        if ($provider === 'gemini' && in_array($model, [
            'gemini-2.0-flash', 'gemini-2.0-flash-exp', 'gemini-2.0-flash-thinking-exp',
            'gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.5-flash-lite',
            'gemini-1.5-flash', 'gemini-1.5-pro',
        ])) {
            $model = 'gemini-3.6-flash';
        }

        $testPrompt = 'Respond with exact word "OK" to verify API connection.';

        try {
            $timeout = 15;
            $output = match ($provider) {
                'gemini' => $this->callGeminiText((string) $apiKey, $model, $testPrompt, $timeout),
                'claude' => $this->callClaudeText((string) $apiKey, $model, $testPrompt, $timeout),
                default => $this->callOpenAiCompatibleText($provider, (string) $apiKey, $model, $testPrompt, $baseUrl, $timeout),
            };

            $latency = (int) round((microtime(true) - $startTime) * 1000);

            if (! empty($output)) {
                $providerName = self::PROVIDERS[$provider]['name'] ?? ucfirst($provider);

                return [
                    'success' => true,
                    'message' => "Կապը հաջողությամբ հաստատվեց! [{$providerName} / {$model}]",
                    'latency_ms' => $latency,
                ];
            }

            return [
                'success' => false,
                'message' => 'Պրովայդերից դատարկ պատասխան ստացվեց: Ստուգեք մոդելի անվանումը կամ լիմիտները:',
            ];
        } catch (\Throwable $e) {
            $latency = (int) round((microtime(true) - $startTime) * 1000);
            $cleanMsg = app(CredentialService::class)->redactString($e->getMessage(), array_filter([(string) $apiKey]));

            return [
                'success' => false,
                'message' => 'Սխալ: '.$cleanMsg,
                'latency_ms' => $latency,
            ];
        }
    }

    private function callGeminiText(string $apiKey, string $model, string $prompt, int $timeout): string
    {
        if (in_array($model, [
            'gemini-2.0-flash', 'gemini-2.0-flash-exp', 'gemini-2.0-flash-thinking-exp',
            'gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.5-flash-lite',
            'gemini-1.5-flash', 'gemini-1.5-pro',
        ])) {
            $model = 'gemini-3.6-flash';
        }

        $response = Http::timeout($timeout)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
            [
                'contents' => [['parts' => [['text' => $prompt]]]],
            ]
        );

        // Fallback to gemini-3.5-flash if gemini-3.6-flash encounters high demand (503), rate limits (429), or 404
        if (! $response->successful() && in_array($response->status(), [404, 429, 503])) {
            $fallbackModel = ($model === 'gemini-3.5-flash') ? 'gemini-3.6-flash' : 'gemini-3.5-flash';
            $fallbackResponse = Http::timeout($timeout)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$fallbackModel}:generateContent?key={$apiKey}",
                [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                ]
            );
            if ($fallbackResponse->successful()) {
                $response = $fallbackResponse;
            }
        }

        if (! $response->successful()) {
            throw new \RuntimeException("Gemini API Error ({$response->status()}): ".($response->json('error.message') ?? $response->body()));
        }

        $parts = $response->json('candidates.0.content.parts') ?? [];
        $text = '';
        foreach ($parts as $part) {
            if (! empty($part['text'])) {
                $text .= $part['text'];
            }
        }

        return trim($text);
    }

    private function callClaudeText(string $apiKey, string $model, string $prompt, int $timeout): string
    {
        $response = Http::timeout($timeout)
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 1024,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Claude API Error ({$response->status()}): ".($response->json('error.message') ?? $response->body()));
        }

        return trim($response->json('content.0.text') ?? '');
    }

    private function callOpenAiCompatibleText(string $provider, string $apiKey, string $model, string $prompt, ?string $baseUrl, int $timeout): string
    {
        if (! empty($baseUrl)) {
            app(SsrfProtectionService::class)->assertSafeUrl($baseUrl, 'Custom AI Base URL');
        }

        $defaultUrl = self::PROVIDERS[$provider]['default_base_url'] ?? 'https://api.openai.com/v1';
        $url = rtrim($baseUrl ?: $defaultUrl, '/').'/chat/completions';

        $headers = [
            'Content-Type' => 'application/json',
        ];
        if (! empty($apiKey)) {
            $headers['Authorization'] = "Bearer {$apiKey}";
        }
        if ($provider === 'openrouter') {
            $headers['HTTP-Referer'] = config('app.url', 'https://parkside.rest');
            $headers['X-Title'] = config('app.name', 'QRMenu');
        }

        $response = Http::timeout($timeout)
            ->withHeaders($headers)
            ->post($url, [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $response->successful()) {
            $errMsg = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException("{$provider} API Error ({$response->status()}): {$errMsg}");
        }

        return trim($response->json('choices.0.message.content') ?? '');
    }
}
