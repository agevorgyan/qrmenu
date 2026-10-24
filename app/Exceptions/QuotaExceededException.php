<?php

namespace App\Exceptions;

use App\DTOs\QuotaStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class QuotaExceededException extends RuntimeException
{
    public function __construct(
        public readonly QuotaStatus $status,
        ?string $customMessage = null,
        int $code = 422,
        ?\Throwable $previous = null
    ) {
        $message = $customMessage ?? self::buildDefaultMessage($status);
        parent::__construct($message, $code, $previous);
    }

    protected static function buildDefaultMessage(QuotaStatus $status): string
    {
        return __("Quota exceeded for ':feature'. Limit: :limit, Current Usage: :usage.", [
            'feature' => $status->displayName ?? $status->quotaKey,
            'limit' => $status->limit,
            'usage' => $status->currentUsage,
        ]);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error' => 'quota_exceeded',
                'code' => 'QUOTA_EXCEEDED',
                'quota_key' => $this->status->quotaKey,
                'limit' => $this->status->limit,
                'current_usage' => $this->status->currentUsage,
                'remaining_capacity' => $this->status->remainingCapacity,
                'reset_at' => $this->status->resetAt?->toISOString(),
                'unit' => $this->status->unit,
                'message' => $this->getMessage(),
            ], 422);
        }

        if ($request->hasSession()) {
            return redirect()
                ->route('admin.subscription')
                ->with('warning', $this->getMessage());
        }

        return response()->json([
            'error' => 'quota_exceeded',
            'code' => 'QUOTA_EXCEEDED',
            'message' => $this->getMessage(),
        ], 422);
    }
}
