@props([
    'feature',
    'title' => null,
    'description' => null,
    'hideIfDenied' => false,
])

@php
    $entitlementService = app(\App\Services\EntitlementService::class);
    $result = $entitlementService->check(null, $feature);
    $isAllowed = $result->allowed;
    $canonicalKey = \App\Security\FeatureCatalog::normalize($feature);
    $featureTitle = $title ?? \App\Security\FeatureCatalog::getDisplayName($canonicalKey, app()->getLocale());
    $minimumPlan = $result->planNeeded ?? \App\Security\FeatureCatalog::getMinimumPlan($canonicalKey);
@endphp

@if($isAllowed)
    {{ $slot }}
@elseif(! $hideIfDenied)
    <div class="feature-gate-card" style="background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e2e8f0); border-radius: 18px; padding: 2rem; text-align: center; box-shadow: var(--shadow-card, 0 4px 12px rgba(0,0,0,0.05)); margin: 1rem 0;">
        <div style="width: 52px; height: 52px; border-radius: 16px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem;">
            <i class="fa-solid fa-lock"></i>
        </div>
        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main, #1e293b); margin: 0 0 0.5rem 0;">
            {{ $featureTitle }}
        </h3>
        <p style="color: var(--text-muted, #64748b); font-size: 0.88rem; max-width: 480px; margin: 0 auto 1.25rem auto; line-height: 1.5;">
            {{ $description ?? $result->message ?? __('This advanced feature is not included in your current subscription plan. Upgrade your plan to unlock.') }}
        </p>
        <div style="display: inline-flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; justify-content: center;">
            <span class="badge" style="background: rgba(37, 99, 235, 0.1); color: #2563eb; border: 1px solid rgba(37, 99, 235, 0.2); padding: 0.4rem 0.8rem; border-radius: 8px; font-weight: 700; font-size: 0.8rem;">
                <i class="fa-solid fa-crown" style="margin-right: 0.35rem;"></i> {{ __('Requires :plan Plan', ['plan' => $minimumPlan]) }}
            </span>
            <a href="{{ route('admin.subscription.index') }}" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; font-size: 0.85rem; padding: 0.5rem 1.15rem; display: inline-flex; align-items: center; gap: 0.45rem; text-decoration: none;">
                <i class="fa-solid fa-bolt"></i> {{ __('Upgrade Plan') }}
            </a>
        </div>
    </div>
@endif
