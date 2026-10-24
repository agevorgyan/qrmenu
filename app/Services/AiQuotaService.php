<?php

namespace App\Services;

use App\Models\AiUsageLog;
use App\Models\AiWaiterSession;
use App\Models\Vendor;
use App\Security\QuotaCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class AiQuotaService
{
    /**
     * Standard pricing rates per 1,000,000 tokens (USD).
     * [input_per_m, output_per_m]
     */
    protected const MODEL_PRICING = [
        'gemini-1.5-flash' => [0.075, 0.30],
        'gemini-2.0-flash' => [0.075, 0.30],
        'gemini-3.5-flash' => [0.075, 0.30],
        'gemini-3.6-flash' => [0.075, 0.30],
        'gemini-1.5-pro' => [1.25, 5.00],
        'gpt-4o-mini' => [0.15, 0.60],
        'gpt-4o' => [2.50, 10.00],
        'o3-mini' => [1.10, 4.40],
        'claude-3-5-haiku-20241022' => [0.80, 4.00],
        'claude-3-5-sonnet-20241022' => [3.00, 15.00],
        'claude-3-7-sonnet-20250219' => [3.00, 15.00],
        'deepseek-chat' => [0.14, 0.28],
        'deepseek-reasoner' => [0.55, 2.19],
        'llama-3.3-70b-versatile' => [0.59, 0.79],
    ];

    /**
     * Check rate limits at:
     * 1. IP level
     * 2. Session level
     * 3. Vendor level
     *
     * @return array{allowed: bool, reason?: string, retry_after?: int, tier?: string}
     */
    public function checkRateLimits(Vendor $vendor, ?string $sessionToken, string $ip): array
    {
        // 1. IP level rate limit: 60 requests per minute
        $ipKey = 'ai_rl_ip:'.md5($ip);
        if (RateLimiter::tooManyAttempts($ipKey, 60)) {
            $retryAfter = RateLimiter::availableIn($ipKey);

            return [
                'allowed' => false,
                'tier' => 'ip',
                'reason' => 'IP rate limit exceeded. Please wait before retrying.',
                'retry_after' => $retryAfter,
            ];
        }

        // 2. Session level rate limit: 25 requests per minute per session token
        if (! empty($sessionToken)) {
            $sessionKey = 'ai_rl_session:'.md5($sessionToken);
            if (RateLimiter::tooManyAttempts($sessionKey, 25)) {
                $retryAfter = RateLimiter::availableIn($sessionKey);

                return [
                    'allowed' => false,
                    'tier' => 'session',
                    'reason' => 'Session rate limit exceeded. Please slow down.',
                    'retry_after' => $retryAfter,
                ];
            }
        }

        // 3. Vendor level rate limit: default 60 requests per minute across all vendor sessions
        $vendorQuotas = $vendor->getAiQuotas();
        $vendorRpm = (int) ($vendorQuotas['requests_per_minute'] ?? 60);
        $vendorKey = 'ai_rl_vendor:'.$vendor->id;
        if (RateLimiter::tooManyAttempts($vendorKey, max(1, $vendorRpm))) {
            $retryAfter = RateLimiter::availableIn($vendorKey);

            return [
                'allowed' => false,
                'tier' => 'vendor',
                'reason' => 'Vendor rate limit exceeded. Please wait.',
                'retry_after' => $retryAfter,
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Hit rate limit counters upon request acceptance.
     */
    public function hitRateLimits(Vendor $vendor, ?string $sessionToken, string $ip): void
    {
        RateLimiter::hit('ai_rl_ip:'.md5($ip), 60);

        if (! empty($sessionToken)) {
            RateLimiter::hit('ai_rl_session:'.md5($sessionToken), 60);
        }

        $vendorQuotas = $vendor->getAiQuotas();
        $vendorRpm = (int) ($vendorQuotas['requests_per_minute'] ?? 60);
        RateLimiter::hit('ai_rl_vendor:'.$vendor->id, 60);
    }

    /**
     * Check configurable quotas:
     * - requests per minute
     * - requests per hour
     * - daily requests
     * - monthly requests
     * - token limits (daily / monthly)
     * - optional spending limits (monthly)
     *
     * @return array{allowed: bool, reason?: string, tier?: string}
     */
    public function checkQuotas(Vendor $vendor): array
    {
        $quotas = $vendor->getAiQuotas();
        $now = now();
        $vendorId = $vendor->id;

        // 1. Requests per minute
        if (! empty($quotas['requests_per_minute'])) {
            $rpmLimit = (int) $quotas['requests_per_minute'];
            $minKey = TenantCache::key($vendor, 'ai_q_rpm:'.$now->format('YmdHi'));
            $currentMin = (int) Cache::get($minKey, 0);
            if ($currentMin >= $rpmLimit) {
                return [
                    'allowed' => false,
                    'tier' => 'requests_per_minute',
                    'reason' => 'Per-minute request quota reached for this vendor.',
                ];
            }
        }

        // 2. Requests per hour
        if (! empty($quotas['requests_per_hour'])) {
            $rphLimit = (int) $quotas['requests_per_hour'];
            $hrKey = TenantCache::key($vendor, 'ai_q_rph:'.$now->format('YmdH'));
            $currentHr = (int) Cache::get($hrKey, function () use ($vendorId, $now) {
                return AiUsageLog::where('vendor_id', $vendorId)
                    ->where('created_at', '>=', $now->copy()->startOfHour())
                    ->count();
            });
            if ($currentHr >= $rphLimit) {
                return [
                    'allowed' => false,
                    'tier' => 'requests_per_hour',
                    'reason' => 'Hourly request quota reached for this vendor.',
                ];
            }
        }

        // 3. Daily requests
        if (! empty($quotas['daily_requests'])) {
            $dailyLimit = (int) $quotas['daily_requests'];
            $dayKey = TenantCache::key($vendor, 'ai_q_day:'.$now->format('Ymd'));
            $currentDay = (int) Cache::get($dayKey, function () use ($vendorId, $now) {
                return AiUsageLog::where('vendor_id', $vendorId)
                    ->where('created_at', '>=', $now->copy()->startOfDay())
                    ->count();
            });
            if ($currentDay >= $dailyLimit) {
                return [
                    'allowed' => false,
                    'tier' => 'daily_requests',
                    'reason' => 'Daily request quota exceeded for this vendor.',
                ];
            }
        }

        // 4. Monthly requests
        if (! empty($quotas['monthly_requests'])) {
            $monthlyLimit = (int) $quotas['monthly_requests'];
            $monthKey = TenantCache::key($vendor, 'ai_q_month:'.$now->format('Ym'));
            $currentMonth = (int) Cache::get($monthKey, function () use ($vendorId, $now) {
                return AiUsageLog::where('vendor_id', $vendorId)
                    ->where('created_at', '>=', $now->copy()->startOfMonth())
                    ->count();
            });
            if ($currentMonth >= $monthlyLimit) {
                return [
                    'allowed' => false,
                    'tier' => 'monthly_requests',
                    'reason' => 'Monthly request quota exceeded for this vendor.',
                ];
            }
        } else {
            // Subscription plan period check
            $periodStatus = app(QuotaService::class)->getQuotaStatus($vendor, QuotaCatalog::KEY_AI_REQUESTS_PERIOD);
            if ($periodStatus->limit > 0 && $periodStatus->isExceeded) {
                return [
                    'allowed' => false,
                    'tier' => 'monthly_requests',
                    'reason' => 'Monthly AI request quota exceeded for this vendor.',
                ];
            }
        }

        // 5. Token limits (daily or monthly)
        $tokenLimit = $quotas['token_limit'] ?? $quotas['monthly_tokens'] ?? null;
        if (! empty($tokenLimit)) {
            $tokenLimitInt = (int) $tokenLimit;
            $monthTokKey = TenantCache::key($vendor, 'ai_q_tok_month:'.$now->format('Ym'));
            $tokensUsed = (int) Cache::get($monthTokKey, function () use ($vendorId, $now) {
                return (int) AiUsageLog::where('vendor_id', $vendorId)
                    ->where('created_at', '>=', $now->copy()->startOfMonth())
                    ->selectRaw('COALESCE(SUM(input_tokens + output_tokens), 0) as total')
                    ->value('total');
            });
            if ($tokensUsed >= $tokenLimitInt) {
                return [
                    'allowed' => false,
                    'tier' => 'token_limit',
                    'reason' => 'AI token limit reached for this billing period.',
                ];
            }
        }

        // 6. Spending limits (monthly)
        $spendingLimit = $quotas['spending_limit'] ?? null;
        if ($spendingLimit !== null && (float) $spendingLimit > 0) {
            $spendingLimitFloat = (float) $spendingLimit;
            $monthCostKey = TenantCache::key($vendor, 'ai_q_cost_month:'.$now->format('Ym'));
            $costSpent = (float) Cache::get($monthCostKey, function () use ($vendorId, $now) {
                return (float) AiUsageLog::where('vendor_id', $vendorId)
                    ->where('created_at', '>=', $now->copy()->startOfMonth())
                    ->sum('estimated_cost');
            });
            if ($costSpent >= $spendingLimitFloat) {
                return [
                    'allowed' => false,
                    'tier' => 'spending_limit',
                    'reason' => 'AI monthly spending limit reached for this vendor.',
                ];
            }
        }

        return ['allowed' => true];
    }

    /**
     * Calculate estimated cost for an LLM invocation.
     */
    public function calculateEstimatedCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $rates = self::MODEL_PRICING[$model] ?? [0.20, 0.80];
        $inputCost = ($inputTokens / 1_000_000) * $rates[0];
        $outputCost = ($outputTokens / 1_000_000) * $rates[1];

        return round($inputCost + $outputCost, 6);
    }

    /**
     * Estimate token count from raw text when provider does not return usage stats.
     */
    public function estimateTokens(?string $text): int
    {
        if (empty($text)) {
            return 0;
        }

        // Multi-language approximation: ~3.5 characters per token average
        return max(1, (int) ceil(mb_strlen($text) / 3.5));
    }

    /**
     * Record usage log in database and increment quota counters in cache.
     */
    public function logUsage(
        Vendor $vendor,
        ?AiWaiterSession $session,
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $latencyMs,
        string $status = 'success',
        ?string $errorMessage = null,
        array $metadata = []
    ): AiUsageLog {
        $cost = $this->calculateEstimatedCost($model, $inputTokens, $outputTokens);

        $log = AiUsageLog::create([
            'vendor_id' => $vendor->id,
            'session_id' => $session?->id,
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'estimated_cost' => $cost,
            'latency_ms' => $latencyMs,
            'status' => $status,
            'error_message' => $errorMessage,
            'metadata' => $metadata,
        ]);

        // Increment cache quota counters
        $now = now();
        $vendorId = $vendor->id;
        $totalTokens = $inputTokens + $outputTokens;

        // RPM
        $minKey = TenantCache::key($vendor, 'ai_q_rpm:'.$now->format('YmdHi'));
        Cache::increment($minKey);
        Cache::put($minKey, Cache::get($minKey), 120);

        // RPH
        $hrKey = TenantCache::key($vendor, 'ai_q_rph:'.$now->format('YmdH'));
        Cache::increment($hrKey);
        Cache::put($hrKey, Cache::get($hrKey), 7200);

        // Daily
        $dayKey = TenantCache::key($vendor, 'ai_q_day:'.$now->format('Ymd'));
        Cache::increment($dayKey);
        Cache::put($dayKey, Cache::get($dayKey), 86400 * 2);

        // Monthly
        $monthKey = TenantCache::key($vendor, 'ai_q_month:'.$now->format('Ym'));
        Cache::increment($monthKey);
        Cache::put($monthKey, Cache::get($monthKey), 86400 * 35);

        // Tokens
        $monthTokKey = TenantCache::key($vendor, 'ai_q_tok_month:'.$now->format('Ym'));
        if (Cache::has($monthTokKey)) {
            Cache::increment($monthTokKey, $totalTokens);
        } else {
            Cache::put($monthTokKey, $totalTokens, 86400 * 35);
        }

        // Cost
        $monthCostKey = TenantCache::key($vendor, 'ai_q_cost_month:'.$now->format('Ym'));
        $currentCost = (float) Cache::get($monthCostKey, 0);
        Cache::put($monthCostKey, $currentCost + $cost, 86400 * 35);

        try {
            app(QuotaService::class)->recordUsage(
                vendor: $vendor,
                quotaKey: QuotaCatalog::KEY_AI_REQUESTS_PERIOD,
                quantity: 1,
                idempotencyKey: 'ai_log_'.$log->id,
                metadata: ['model' => $model, 'tokens' => $totalTokens]
            );
        } catch (\Throwable $e) {
            // Non-blocking for log completion
        }

        return $log;
    }
}
