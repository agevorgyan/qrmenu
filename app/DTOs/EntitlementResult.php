<?php

namespace App\DTOs;

class EntitlementResult
{
    public const ALLOWED = 'ALLOWED';

    public const PLAN_UPGRADE_REQUIRED = 'PLAN_UPGRADE_REQUIRED';

    public const SUBSCRIPTION_INACTIVE = 'SUBSCRIPTION_INACTIVE';

    public const FEATURE_OVERRIDE_DISABLED = 'FEATURE_OVERRIDE_DISABLED';

    public const FEATURE_OVERRIDE_ENABLED = 'FEATURE_OVERRIDE_ENABLED';

    public const TENANT_INACTIVE = 'TENANT_INACTIVE';

    public const TENANT_NOT_FOUND = 'TENANT_NOT_FOUND';

    public const UNAUTHORIZED_USER = 'UNAUTHORIZED_USER';

    public const UNKNOWN_FEATURE = 'UNKNOWN_FEATURE';

    public function __construct(
        public bool $allowed,
        public string $code,
        public string $feature,
        public string $featureName,
        public string $planNeeded,
        public string $message,
        public ?string $currentPlan = null
    ) {}

    public static function grant(
        string $feature,
        string $featureName,
        ?string $currentPlan = null,
        string $code = self::ALLOWED,
        string $message = 'Feature is entitled and accessible.'
    ): self {
        return new self(
            allowed: true,
            code: $code,
            feature: $feature,
            featureName: $featureName,
            planNeeded: 'Basic',
            message: $message,
            currentPlan: $currentPlan
        );
    }

    public static function deny(
        string $code,
        string $feature,
        string $featureName,
        string $planNeeded,
        string $message,
        ?string $currentPlan = null
    ): self {
        return new self(
            allowed: false,
            code: $code,
            feature: $feature,
            featureName: $featureName,
            planNeeded: $planNeeded,
            message: $message,
            currentPlan: $currentPlan
        );
    }

    /**
     * Convert to standardized JSON error response array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->allowed,
            'error' => $this->allowed ? null : 'FEATURE_NOT_ENTITLED',
            'code' => $this->code,
            'feature' => $this->feature,
            'feature_name' => $this->featureName,
            'plan_needed' => $this->planNeeded,
            'current_plan' => $this->currentPlan,
            'message' => $this->message,
        ];
    }
}
