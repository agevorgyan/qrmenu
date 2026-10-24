<?php

namespace App\Exceptions;

use App\DTOs\EntitlementResult;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FeatureNotEntitledException extends Exception
{
    public function __construct(
        public EntitlementResult $result,
        int $code = 403,
        ?\Throwable $previous = null
    ) {
        parent::__construct($result->message, $code, $previous);
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*') || $request->is('*/feed')) {
            return response()->json($this->result->toArray(), 403);
        }

        return redirect()->route('admin.subscription')
            ->with('warning', $this->result->message);
    }
}
