@extends('layouts.app')

@section('title', __('My Subscription') . ' - ' . $vendor->name)

@section('content')
@php
    $plansMap = $allPlans->mapWithKeys(fn($p) => [$p->id => (float) $p->price])->toArray();
    $addonsMap = $availableAddons->mapWithKeys(fn($a) => [$a->id => [
        'id' => $a->id,
        'name' => $a->localizedName(),
        'description' => $a->localizedDescription(),
        'addon_type' => $a->addon_type,
        'monthly_price' => (float) $a->monthly_price,
        'yearly_price' => (float) ($a->yearly_price ?: ($a->monthly_price * 10)),
        'currency' => $a->currency,
        'billing_period' => $a->billing_period ?? 'monthly',
    ]])->toArray();
    $plansInfo = $allPlans->mapWithKeys(fn($p) => [$p->id => [
        'id' => $p->id,
        'name' => __($p->name),
        'price' => (float) $p->price,
        'formatted_price' => number_format($p->price, 0, '.', ' ') . ' ' . $p->currency,
        'currency' => $p->currency,
        'features' => array_values(array_map(fn($f) => __($f), array_slice($p->features ?? [], 0, 5))),
    ]])->toArray();
    $subStatus = $subscription->status;
    $isCancelAtPeriodEnd = $subscription->cancel_at_period_end;
@endphp

<div x-data="subscriptionManagerApp()">

<!-- Alerts / Messages -->
@php
    $pendingDowngradeId = $subscription->metadata['pending_downgrade_plan_id'] ?? null;
    $pendingPlan = $pendingDowngradeId ? $allPlans->firstWhere('id', $pendingDowngradeId) : null;
    $pendingEffectiveDate = !empty($subscription->metadata['pending_downgrade_effective_at']) 
        ? \Carbon\Carbon::parse($subscription->metadata['pending_downgrade_effective_at'])->format('d.m.Y') 
        : ($vendor->subscription_expires_at ? $vendor->subscription_expires_at->format('d.m.Y') : '—');
@endphp

@if($pendingPlan)
    <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 16px; padding: 1.15rem 1.35rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 0.85rem;">
            <span style="width: 40px; height: 40px; border-radius: 12px; background: rgba(245, 158, 11, 0.2); color: #d97706; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </span>
            <div>
                <div style="font-weight: 800; color: #b45309; font-size: 0.95rem;">
                    {{ __('Scheduled Plan Downgrade') }}: {{ __($pendingPlan->name) }}
                </div>
                <div style="font-size: 0.82rem; color: #92400e; margin-top: 2px;">
                    {{ __('Your subscription will switch to :plan on :date upon current period expiration. Your current tier entitlements remain active until then.', ['plan' => __($pendingPlan->name), 'date' => $pendingEffectiveDate]) }}
                </div>
            </div>
        </div>
        <form action="{{ route('admin.subscription.cancel_downgrade') }}" method="POST" style="margin: 0;">
            @csrf
            <button type="submit" class="btn btn-secondary" style="border-radius: 10px; font-weight: 700; font-size: 0.82rem; padding: 0.5rem 1rem; border-color: rgba(245, 158, 11, 0.4); color: #b45309;">
                <i class="fa-solid fa-xmark"></i> {{ __('Cancel Downgrade') }}
            </button>
        </form>
    </div>
@endif

@if(session('warning'))
    <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; color: #d97706;">
        <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.2rem; flex-shrink: 0;"></i>
        <div style="font-size: 0.9rem; font-weight: 600;">{{ session('warning') }}</div>
    </div>
@endif

@if(session('error'))
    <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; color: #dc2626;">
        <i class="fa-solid fa-circle-exclamation" style="font-size: 1.2rem; flex-shrink: 0;"></i>
        <div style="font-size: 0.9rem; font-weight: 600;">{{ session('error') }}</div>
    </div>
@endif

@if(session('success'))
    <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; color: #059669;">
        <i class="fa-solid fa-circle-check" style="font-size: 1.2rem; flex-shrink: 0;"></i>
        <div style="font-size: 0.9rem; font-weight: 600;">{{ session('success') }}</div>
    </div>
@endif

<!-- Cancellation Notice Banner if Scheduled to Cancel -->
@if($isCancelAtPeriodEnd)
    <div style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.4); border-radius: 18px; padding: 1.25rem 1.5rem; margin-bottom: 1.75rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; box-shadow: var(--shadow-card);">
        <div style="display: flex; align-items: center; gap: 0.85rem;">
            <span style="width: 40px; height: 40px; border-radius: 10px; background: rgba(245, 158, 11, 0.2); color: #d97706; display: inline-flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0;">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </span>
            <div>
                <h4 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 1.05rem; font-weight: 800; color: var(--text-main);">
                    {{ __('Subscription Scheduled for Cancellation') }}
                </h4>
                <p style="margin: 0.2rem 0 0; font-size: 0.84rem; color: var(--text-muted);">
                    {{ __('Your subscription will end on :date. You will continue to have full access until then.', ['date' => $subscription->current_period_end ? $subscription->current_period_end->format('d.m.Y') : __('end of billing period')]) }}
                </p>
            </div>
        </div>
        <form action="{{ route('admin.subscription.resume') }}" method="POST" style="margin: 0;">
            @csrf
            <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; font-size: 0.85rem; padding: 0.6rem 1.25rem; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 12px var(--primary-glow);">
                <i class="fa-solid fa-rotate-right"></i> {{ __('Resume Auto-Renewal') }}
            </button>
        </form>
    </div>
@endif

<!-- Page Header -->
<div class="page-header" style="margin-bottom: 2rem;">
    <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
        <span style="background: rgba(245, 158, 11, 0.15); color: var(--primary); width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
            <i class="fa-solid fa-file-invoice-dollar"></i>
        </span>
        <span>{{ __('Subscription & Billing') }}</span>
    </h1>
    <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0; word-break: break-word;">
        {{ __('View your current subscription plan, usage quotas, included features, and billing history') }}
    </p>
</div>

<!-- Subscription Status Overview Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Current Plan Card -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.25rem, 3vw, 1.85rem); position: relative; overflow: hidden; box-shadow: var(--shadow-card); display: flex; flex-direction: column; justify-content: space-between;">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--primary), #ea580c);"></div>

        <div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em;">{{ __('Current Plan') }}</span>
                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin: 0.2rem 0 0 0; word-break: break-word;">
                        {{ __($vendor->plan?->name ?? strtoupper($vendor->subscription_plan ?? 'Basic')) }}
                    </h3>
                </div>
                @if($subStatus->value === 'suspended')
                    <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 0.3rem 0.65rem; font-size: 0.78rem; font-weight: 700;">
                        ⛔ {{ __('Suspended') }}
                    </span>
                @elseif($isCancelAtPeriodEnd)
                    <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 8px; padding: 0.3rem 0.65rem; font-size: 0.78rem; font-weight: 700;">
                        ⏳ {{ __('Cancels at Period End') }}
                    </span>
                @elseif($vendor->isExpired())
                    <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 0.3rem 0.65rem; font-size: 0.78rem; font-weight: 700;">
                        🔴 {{ __('Expired / Inactive') }}
                    </span>
                @elseif($vendor->isTrialing())
                    <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 8px; padding: 0.3rem 0.65rem; font-size: 0.78rem; font-weight: 700;">
                        ⏳ {{ __('Trial (:days days)', ['days' => $subscription->trial_duration_days ?: 14]) }}
                    </span>
                @elseif($subStatus->value === 'grace' || $subStatus->value === 'grace_period')
                    <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 8px; padding: 0.3rem 0.65rem; font-size: 0.78rem; font-weight: 700;">
                        ⚠️ {{ __('Grace Period') }}
                    </span>
                @else
                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; padding: 0.3rem 0.65rem; font-size: 0.78rem; font-weight: 700;">
                        🟢 {{ __('Active') }}
                    </span>
                @endif
            </div>

            <div style="font-family: 'Outfit', sans-serif; font-size: 1.5rem; font-weight: 800; color: var(--primary); margin-bottom: 0.75rem;">
                {{ $vendor->plan?->formatted_price ?? '—' }}
            </div>

            <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem; display: flex; flex-direction: column; gap: 0.35rem;">
                @if($vendor->isTrialing())
                    <div>⏱ {{ __('Trial period ends:') }} <strong>{{ $vendor->trial_ends_at ? $vendor->trial_ends_at->format('d.m.Y H:i') : '—' }}</strong></div>
                @else
                    <div>📅 {{ __('Current billing period:') }} <strong>{{ $subscription->current_period_start ? $subscription->current_period_start->format('d.m.Y') : '—' }} — {{ $subscription->current_period_end ? $subscription->current_period_end->format('d.m.Y') : __('Unlimited') }}</strong></div>
                @endif
                <div>🔄 {{ __('Billing interval:') }} <strong>{{ ucfirst($subscription->billing_interval ?: 'monthly') }}</strong></div>
            </div>
        </div>

        <div style="background: var(--bg-body); border: 1px solid var(--border-color); padding: 0.85rem 1.15rem; border-radius: 14px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">{{ __('Remaining Time:') }}</span>
            <span style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 800; color: {{ $vendor->daysLeft() <= 3 ? '#ef4444' : '#10b981' }};">
                {{ $vendor->daysLeft() }} {{ __('days') }}
            </span>
        </div>

        <div style="display: flex; gap: 0.5rem; justify-content: space-between; align-items: center; margin-top: 0.75rem; flex-wrap: wrap;">
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <button type="button" @click="openRenewModal({{ $vendor->subscription_plan_id ?? ($allPlans->first()?->id ?? 1) }}, 'change_plan')" class="btn btn-secondary" style="border-radius: 10px; font-weight: 700; font-size: 0.82rem; padding: 0.5rem 0.95rem; display: inline-flex; align-items: center; gap: 0.4rem;">
                    <i class="fa-solid fa-arrows-rotate"></i> {{ __('Change Plan') }}
                </button>
                <button type="button" @click="openRenewModal({{ $vendor->subscription_plan_id ?? ($allPlans->first()?->id ?? 1) }}, 'renew')" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; font-size: 0.82rem; padding: 0.5rem 0.95rem; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 2px 8px var(--primary-glow);">
                    <i class="fa-solid fa-bolt"></i> {{ __('Renew') }}
                </button>
            </div>
            @if($subStatus->allowsAccess() && ! $isCancelAtPeriodEnd)
                <button type="button" @click="cancelModalOpen = true" style="background: none; border: none; font-size: 0.78rem; color: #ef4444; cursor: pointer; text-decoration: underline; font-weight: 600;">
                    {{ __('Cancel Subscription') }}
                </button>
            @endif
        </div>
    </div>

    <!-- Online Renewal & Upgrade Card -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.25rem, 3vw, 1.85rem); position: relative; overflow: hidden; box-shadow: var(--shadow-card); display: flex; flex-direction: column; justify-content: space-between;">
        <div>
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.5rem 0; display: flex; align-items: center; gap: 0.5rem;">
                <span style="color: #10b981;"><i class="fa-solid fa-credit-card"></i></span>
                <span>{{ __('Online Renewal & Billing') }}</span>
            </h3>
            <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.6; margin: 0; word-break: break-word;">
                {{ __('Renew or upgrade your subscription seamlessly with Idram, Telcell, FastShift, ArCa/Ameriabank or Stripe. Save up to 20% on 6-month and 12-month billing intervals.') }}
            </p>
        </div>

        <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <button type="button" 
                    @click="openRenewModal({{ $vendor->subscription_plan_id ?? ($allPlans->first()?->id ?? 1) }}, 'renew')" 
                    class="btn btn-primary" 
                    style="flex: 1; min-width: 170px; justify-content: center; border-radius: 12px; font-weight: 800; padding: 0.85rem 1.25rem; box-shadow: 0 4px 14px var(--primary-glow); display: inline-flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-bolt"></i>
                <span>{{ __('Renew Subscription') }}</span>
            </button>
            <button type="button" 
                    @click="openRenewModal({{ $vendor->subscription_plan_id ?? ($allPlans->first()?->id ?? 1) }}, 'change_plan')" 
                    class="btn btn-secondary" 
                    style="flex: 1; min-width: 170px; justify-content: center; border-radius: 12px; font-weight: 700; padding: 0.85rem 1.25rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-arrows-rotate"></i>
                <span>{{ __('Change Plan') }}</span>
            </button>
        </div>
    </div>
</div>

<!-- Usage & Quota Meters Section -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.25rem, 3vw, 1.85rem); margin-bottom: 2rem; box-shadow: var(--shadow-card);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.5rem;">
        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <span style="color: #06b6d4;"><i class="fa-solid fa-chart-pie"></i></span>
            <span>{{ __('Usage & Quota Limits') }}</span>
        </h3>
        <span style="font-size: 0.78rem; color: var(--text-muted);">
            {{ __('Metered in real-time according to your plan rules') }}
        </span>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr)); gap: 1.15rem;">
        @foreach($quotas as $key => $status)
            @php
                $isUnlimited = $status->isUnlimited;
                $percent = $isUnlimited ? 0 : min(100, (int) round(($status->currentUsage / max(1, $status->limit)) * 100));
                $barColor = $percent >= 100 ? '#ef4444' : ($percent >= 80 ? '#f59e0b' : '#10b981');
            @endphp
            <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem 1.15rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                    <div>
                        <div style="font-weight: 700; font-size: 0.88rem; color: var(--text-main);">
                            {{ $status->displayName ?? $key }}
                        </div>
                        <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.03em;">
                            {{ $status->type === 'resource' ? __('Resource Count') : __('Period Events') }}
                        </div>
                    </div>
                    <div>
                        @if($isUnlimited)
                            <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700; font-size: 0.72rem; border-radius: 6px;">
                                ∞ {{ __('Unlimited') }}
                            </span>
                        @elseif($status->isExceeded)
                            <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; font-weight: 700; font-size: 0.72rem; border-radius: 6px;">
                                {{ __('Exceeded') }}
                            </span>
                        @else
                            <span style="font-size: 0.82rem; font-weight: 700; color: {{ $barColor }};">
                                {{ $percent }}%
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Progress Bar -->
                <div style="width: 100%; height: 7px; background: var(--border-color); border-radius: 9999px; overflow: hidden; margin-bottom: 0.55rem;">
                    <div style="height: 100%; width: {{ $isUnlimited ? 15 : max(3, $percent) }}%; background: {{ $barColor }}; border-radius: 9999px; transition: width 0.3s ease;"></div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.78rem; color: var(--text-muted);">
                    <span>
                        {{ __('Usage:') }} <strong style="color: var(--text-main);">{{ number_format($status->currentUsage) }}</strong>
                    </span>
                    <span>
                        {{ __('Limit:') }} <strong style="color: var(--text-main);">{{ $isUnlimited ? __('Unlimited') : number_format($status->limit) }}</strong> {{ $status->unit }}
                    </span>
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- Included & Locked Features Section -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.25rem, 3vw, 1.85rem); margin-bottom: 2rem; box-shadow: var(--shadow-card);">
    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0 0 1.25rem 0; display: flex; align-items: center; gap: 0.5rem;">
        <span style="color: var(--primary);"><i class="fa-solid fa-sparkles"></i></span>
        <span>{{ __('Feature Entitlements') }}</span>
    </h3>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 250px), 1fr)); gap: 0.85rem;">
        @foreach($allFeatures as $featKey)
            @php
                $isUnlocked = in_array($featKey, $entitledFeatures, true) || \App\Services\EntitlementService::hasFeature($vendor, $featKey);
                $displayName = \App\Security\FeatureCatalog::getDisplayName($featKey, app()->getLocale());
                $minPlan = \App\Security\FeatureCatalog::getMinimumPlan($featKey);
                $entMeta = ($allEntitlements ?? [])[$featKey] ?? null;
            @endphp
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.65rem; background: var(--bg-body); border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: 12px; font-size: 0.86rem;">
                <div style="display: flex; align-items: center; gap: 0.65rem; min-width: 0;">
                    @if($isUnlocked)
                        <i class="fa-solid fa-circle-check" style="color: #10b981; flex-shrink: 0; font-size: 1.05rem;"></i>
                        <span style="color: var(--text-main); font-weight: 600; word-break: break-word;">{{ $displayName }}</span>
                    @else
                        <i class="fa-solid fa-lock" style="color: var(--text-muted); flex-shrink: 0; font-size: 0.95rem;"></i>
                        <span style="color: var(--text-muted); text-decoration: line-through; word-break: break-word;">{{ $displayName }}</span>
                    @endif
                </div>

                @if($isUnlocked && $entMeta && ($entMeta['source'] ?? 'plan') !== 'plan')
                    <span class="badge badge-indigo" style="font-size: 0.65rem;">{{ strtoupper($entMeta['source']) }}</span>
                @elseif(! $isUnlocked)
                    <button type="button" @click="openRenewModal({{ $allPlans->where('slug', strtolower($minPlan))->first()?->id ?? null }})" style="background: none; border: none; padding: 0; color: var(--primary); font-size: 0.72rem; font-weight: 700; cursor: pointer; text-transform: uppercase; white-space: nowrap;">
                        {{ $minPlan }} <i class="fa-solid fa-chevron-right" style="font-size: 0.6rem;"></i>
                    </button>
                @endif
            </div>
        @endforeach
    </div>
</div>

<!-- Active & Available Subscription Add-ons Section -->
@if((!empty($activeVendorAddons) && $activeVendorAddons->count() > 0) || (!empty($availableAddons) && $availableAddons->count() > 0))
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: clamp(1.25rem, 3vw, 1.85rem); margin-bottom: 2rem; box-shadow: var(--shadow-card);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.5rem;">
        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <span style="color: #6366f1;"><i class="fa-solid fa-puzzle-piece"></i></span>
            <span>{{ __('Subscription Add-ons') }}</span>
        </h3>
        <span style="font-size: 0.78rem; color: var(--text-muted);">
            {{ __('Extend your plan with additional quotas, staff accounts, locations, or features') }}
        </span>
    </div>

    @if(!empty($activeVendorAddons) && $activeVendorAddons->count() > 0)
        <div style="margin-bottom: 1.5rem;">
            <h4 style="font-size: 0.88rem; font-weight: 700; color: #10b981; margin: 0 0 0.75rem; display: flex; align-items: center; gap: 0.4rem;">
                <i class="fa-solid fa-circle-check"></i> {{ __('Active Add-ons on Your Account') }}
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 0.85rem;">
                @foreach($activeVendorAddons as $va)
                    <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 14px; padding: 0.9rem 1.15rem; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;">
                        <div>
                            <div style="font-weight: 800; font-size: 0.92rem; color: var(--text-main);">
                                {{ $va->addon?->localizedName() }} <span style="color: #10b981; font-weight: 800;">(x{{ $va->quantity }})</span>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                                {{ $va->expires_at ? __('Expires:') . ' ' . $va->expires_at->format('d.m.Y') : __('Active with subscription') }}
                            </div>
                        </div>
                        <form action="{{ route('admin.subscription.addons.cancel', $va) }}" method="POST" onsubmit="return confirm('{{ __('Are you sure you want to cancel this add-on?') }}');" style="margin: 0;">
                            @csrf
                            <button type="submit" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.35rem 0.65rem; border-radius: 8px; color: #ef4444; border-color: rgba(239, 68, 68, 0.3); font-weight: 700; cursor: pointer;">
                                {{ __('Cancel') }}
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if(!empty($availableAddons) && $availableAddons->count() > 0)
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
            @foreach($availableAddons as $addon)
                @php
                    $activeCount = $activeVendorAddons->where('subscription_addon_id', $addon->id)->sum('quantity');
                @endphp
                <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.15rem; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.4rem;">
                            <strong style="font-size: 0.95rem; font-weight: 800; color: var(--text-main);">{{ $addon->localizedName() }}</strong>
                            <span class="badge badge-indigo" style="font-size: 0.65rem; text-transform: uppercase;">{{ $addon->addon_type }}</span>
                        </div>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0.35rem 0 0.85rem; line-height: 1.5;">
                            {{ $addon->localizedDescription() }}
                        </p>
                    </div>

                    <div style="border-top: 1px solid var(--border-color); padding-top: 0.85rem; display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <div>
                            <div style="font-family: 'Outfit'; font-weight: 800; font-size: 1rem; color: var(--primary);">
                                @if((float)$addon->monthly_price > 0)
                                    {{ number_format((float)$addon->monthly_price, 0) }} {{ $addon->currency }} <span style="font-size: 0.72rem; font-weight: 500; color: var(--text-muted);">/ {{ __($addon->billing_period ?: 'mo') }}</span>
                                @else
                                    <span style="color: #10b981;">0 ֏</span> <span style="font-size: 0.72rem; font-weight: 600; color: #10b981;">({{ __('Free / Included') }})</span>
                                @endif
                            </div>
                            @if($activeCount > 0)
                                <div style="font-size: 0.72rem; color: #10b981; font-weight: 700; margin-top: 2px;">
                                    ✓ {{ __('Active on account (x:count)', ['count' => $activeCount]) }}
                                </div>
                            @endif
                        </div>

                        <button type="button"
                                @click="openAddonModal({{ $addon->id }})"
                                class="btn btn-primary"
                                style="border-radius: 10px; font-weight: 700; font-size: 0.82rem; padding: 0.5rem 0.95rem; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 2px 8px var(--primary-glow);">
                            <i class="fa-solid fa-plus"></i> {{ __('Add to Plan') }}
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endif

<!-- Available Plans Comparison -->
<div style="margin-bottom: 2rem;">
    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0 0 1.25rem 0; display: flex; align-items: center; gap: 0.5rem;">
        <span style="color: var(--primary);"><i class="fa-solid fa-list-check"></i></span>
        <span>{{ __('Available Plans') }}</span>
    </h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 260px), 1fr)); gap: 1.25rem;">
        @foreach($allPlans as $plan)
            <div class="card" style="background: var(--bg-card); border: 2px solid {{ $vendor->subscription_plan_id == $plan->id ? 'var(--primary)' : 'var(--border-color)' }}; border-radius: 20px; padding: 1.5rem; position: relative; box-shadow: var(--shadow-card); display: flex; flex-direction: column; justify-content: space-between;">
                @if($vendor->subscription_plan_id == $plan->id)
                    <span style="position: absolute; top: -11px; right: 15px; background: var(--primary); color: #fff; font-size: 0.7rem; font-weight: 800; padding: 0.2rem 0.7rem; border-radius: 9999px; letter-spacing: 0.04em;">
                        {{ __('CURRENT PLAN') }}
                    </span>
                @endif
                <div>
                    <h4 style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0;">{{ __($plan->name) }}</h4>
                    <div style="font-family: 'Outfit', sans-serif; font-size: 1.4rem; font-weight: 800; color: var(--primary); margin: 0.4rem 0;">
                        {{ $plan->formatted_price }}
                    </div>
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 1rem; line-height: 1.5; word-break: break-word;">
                        {{ __($plan->description) }}
                    </p>
                    <ul style="list-style: none; padding: 0; margin: 0 0 1.25rem 0; font-size: 0.82rem; color: var(--text-muted); display: flex; flex-direction: column; gap: 0.4rem;">
                        @foreach($plan->features ?? [] as $f)
                            <li style="display: flex; align-items: flex-start; gap: 0.4rem; word-break: break-word;">
                                <i class="fa-solid fa-check" style="color: #10b981; margin-top: 0.2rem; flex-shrink: 0;"></i>
                                <span>{{ __($f) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <button type="button" 
                        @click="openRenewModal({{ $plan->id }}, '{{ $vendor->subscription_plan_id == $plan->id ? 'renew' : 'change_plan' }}')" 
                        class="btn {{ $vendor->subscription_plan_id == $plan->id ? 'btn-primary' : 'btn-secondary' }}" 
                        style="width: 100%; justify-content: center; border-radius: 12px; font-weight: 700; padding: 0.65rem 1rem;">
                    {{ $vendor->subscription_plan_id == $plan->id ? __('Renew this plan') : __('Change to this plan') }}
                </button>
            </div>
        @endforeach
    </div>
</div>

<!-- Interactive Renewal & Plan Change Modal (Alpine.js) -->
<style>
    /* Scoped styles for Subscription Modal */
    .sub-hidden-input {
        position: absolute !important;
        opacity: 0 !important;
        width: 0 !important;
        height: 0 !important;
        pointer-events: none !important;
        margin: 0 !important;
        padding: 0 !important;
        border: 0 !important;
        clip: rect(0 0 0 0) !important;
        clip-path: inset(50%) !important;
        overflow: hidden !important;
        white-space: nowrap !important;
        appearance: none !important;
        -webkit-appearance: none !important;
    }

    .sub-step-header {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        margin-bottom: 0.75rem;
    }

    .sub-step-num {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: rgba(37, 99, 235, 0.12);
        color: var(--primary);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 800;
        font-family: 'Outfit', sans-serif;
        flex-shrink: 0;
    }

    .sub-step-label {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--text-main);
        letter-spacing: -0.01em;
    }

    .sub-card {
        position: relative;
        border-radius: 16px;
        border: 2px solid var(--border-color);
        background: var(--bg-card);
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
        box-sizing: border-box;
    }

    .sub-card:hover {
        border-color: rgba(37, 99, 235, 0.4);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .sub-card.is-selected {
        border-color: var(--primary) !important;
        background: var(--bg-card) !important;
        box-shadow: 0 0 0 3px var(--primary-glow), 0 8px 20px -4px rgba(37, 99, 235, 0.15) !important;
        transform: translateY(-2px);
    }

    .sub-radio-dot {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 2px solid var(--border-color);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: all 0.2s ease;
        background: transparent;
    }

    .sub-card.is-selected .sub-radio-dot,
    .is-selected .sub-radio-dot {
        border-color: var(--primary) !important;
        background: var(--primary) !important;
        box-shadow: 0 0 0 3px var(--primary-glow);
    }

    .sub-radio-dot i {
        color: #ffffff;
        font-size: 0.65rem;
        line-height: 1;
    }

    .sub-period-card {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 0.9rem 0.6rem;
        border-radius: 14px;
        border: 2px solid var(--border-color);
        background: var(--bg-card);
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
        text-align: center;
    }

    .sub-period-card:hover {
        border-color: rgba(37, 99, 235, 0.4);
        transform: translateY(-1px);
    }

    .sub-period-card.is-selected {
        border-color: var(--primary) !important;
        background: rgba(37, 99, 235, 0.04) !important;
        box-shadow: 0 0 0 3px var(--primary-glow) !important;
        transform: translateY(-2px);
    }

    .sub-period-card .period-discount-badge {
        position: absolute;
        top: -9px;
        font-size: 0.65rem;
        font-weight: 800;
        padding: 2px 8px;
        border-radius: 9999px;
        color: #fff;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        letter-spacing: 0.02em;
    }

    .sub-pay-card {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.85rem 1rem;
        border-radius: 14px;
        border: 2px solid var(--border-color);
        background: var(--bg-card);
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
    }

    .sub-pay-card:hover {
        border-color: rgba(37, 99, 235, 0.4);
        transform: translateY(-1px);
    }

    .sub-pay-card.is-selected {
        border-color: var(--primary) !important;
        background: var(--bg-card) !important;
        box-shadow: 0 0 0 3px var(--primary-glow) !important;
        transform: translateY(-2px);
    }

    .sub-receipt-box {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 18px;
        padding: 1.25rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02);
    }

    .sub-receipt-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.85rem;
        padding: 0.45rem 0;
        color: var(--text-muted);
    }

    .sub-receipt-row strong {
        color: var(--text-main);
        font-weight: 700;
    }

    @media (max-width: 860px) {
        .subscription-modal-container {
            max-width: 580px !important;
            height: auto !important;
            max-height: 94vh !important;
        }
        .subscription-modal-grid {
            grid-template-columns: 1fr !important;
        }
        .subscription-summary-pane {
            border-left: none !important;
            border-top: 1px solid var(--border-color) !important;
        }
    }
</style>

<div x-show="renewModalOpen" 
     x-cloak 
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     style="position: fixed; inset: 0; background: rgba(15, 23, 42, 0.72); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 999; display: flex; align-items: center; justify-content: center; padding: 1rem;">
    
    <div @click.away="renewModalOpen = false" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="subscription-modal-container"
         style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 24px; width: 100%; max-width: 960px; height: auto; min-height: 560px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 60px -15px rgba(0,0,0,0.5);">
        
        <!-- Fixed Header with Title & Segmented Navigation -->
        <div style="padding: 1.15rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; background: var(--bg-card); gap: 1rem; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="width: 42px; height: 42px; border-radius: 12px; background: rgba(37, 99, 235, 0.12); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">
                    <i class="fa-solid fa-gem"></i>
                </span>
                <div>
                    <h3 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 800; color: var(--text-main); line-height: 1.2;">
                        {{ __('Subscription Management') }}
                    </h3>
                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">
                        {{ $vendor->name }} &bull; <span style="font-weight: 700; color: var(--primary);">{{ $vendor->subscriptionPlan?->name ?? __('Current Plan') }}</span>
                    </div>
                </div>
            </div>

            <!-- Segmented Switcher in Header -->
            <div style="background: var(--bg-body); padding: 4px; border-radius: 12px; display: inline-flex; gap: 4px; border: 1px solid var(--border-color);">
                <button type="button" 
                        @click="modalTab = 'renew'"
                        :style="modalTab === 'renew' ? 'background: var(--bg-card); color: var(--primary); font-weight: 800; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-color: var(--border-color);' : 'background: transparent; color: var(--text-muted); font-weight: 600; border-color: transparent;'"
                        style="padding: 0.5rem 1rem; border-width: 1px; border-style: solid; border-radius: 9px; font-size: 0.85rem; cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 0.45rem; outline: none;">
                    <i class="fa-solid fa-bolt" style="color: #f59e0b;"></i>
                    <span>{{ __('Renew & Pay') }}</span>
                </button>
                <button type="button" 
                        @click="modalTab = 'change_plan'"
                        :style="modalTab === 'change_plan' ? 'background: var(--bg-card); color: var(--primary); font-weight: 800; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-color: var(--border-color);' : 'background: transparent; color: var(--text-muted); font-weight: 600; border-color: transparent;'"
                        style="padding: 0.5rem 1rem; border-width: 1px; border-style: solid; border-radius: 9px; font-size: 0.85rem; cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 0.45rem; outline: none;">
                    <i class="fa-solid fa-arrows-rotate"></i>
                    <span>{{ __('Change Plan') }}</span>
                </button>
            </div>

            <button type="button" @click="renewModalOpen = false" style="width: 36px; height: 36px; border-radius: 10px; background: var(--bg-body); border: 1px solid var(--border-color); font-size: 1.1rem; color: var(--text-muted); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all 0.15s ease;" title="{{ __('Close') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- TAB 1: Direct Plan Change (2-Column Split) -->
        <div x-show="modalTab === 'change_plan'" style="display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden;">
            <form action="{{ route('admin.subscription.change_plan') }}" method="POST" style="display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden; margin: 0;">
                @csrf
                <div class="subscription-modal-grid" style="display: grid; grid-template-columns: 1fr 340px; flex: 1; min-height: 0; overflow: hidden;">
                    <!-- Left: Plan Selection -->
                    <div style="overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem;">
                        <div>
                            <div class="sub-step-header" style="justify-content: space-between;">
                                <div style="display: flex; align-items: center; gap: 0.6rem;">
                                    <span class="sub-step-num"><i class="fa-solid fa-arrows-rotate" style="font-size: 0.65rem;"></i></span>
                                    <span class="sub-step-label">{{ __('Select Target Plan') }}</span>
                                </div>
                                <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">{{ count($allPlans) }} {{ __('plans available') }}</span>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem;">
                                @foreach($allPlans as $p)
                                    <label class="sub-card"
                                           :class="{ 'is-selected': selectedPlanId == {{ $p->id }} }"
                                           style="padding: 1rem 1.15rem; display: flex; flex-direction: column; justify-content: space-between; min-height: 106px;">
                                        <input type="radio" name="subscription_plan_id" value="{{ $p->id }}" x-model="selectedPlanId" class="sub-hidden-input">
                                        <div>
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem;">
                                                <span style="font-weight: 800; font-size: 1rem; color: var(--text-main); line-height: 1.3;">{{ __($p->name) }}</span>
                                                <span class="sub-radio-dot">
                                                    <i class="fa-solid fa-check" x-show="selectedPlanId == {{ $p->id }}"></i>
                                                </span>
                                            </div>
                                            <div style="margin-top: 0.45rem; display: flex; align-items: baseline; gap: 0.25rem;">
                                                <span style="font-size: 1.25rem; font-weight: 900; color: var(--text-main); font-family: 'Outfit', sans-serif;">{{ number_format($p->price, 0, '.', ' ') }}</span>
                                                <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted);">{{ $p->currency }}/{{ __('mo') }}</span>
                                            </div>
                                        </div>
                                        <div style="margin-top: 0.65rem; min-height: 22px; display: flex; align-items: center;">
                                            @if($vendor->subscription_plan_id == $p->id)
                                                <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #10b981; font-weight: 800; font-size: 0.68rem; padding: 2px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.35rem; border: 1px solid rgba(16, 185, 129, 0.25);">
                                                    <i class="fa-solid fa-circle-check" style="font-size: 0.6rem;"></i> {{ __('Current Plan') }}
                                                </span>
                                            @endif
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div style="background: rgba(37, 99, 235, 0.06); border: 1px solid rgba(37, 99, 235, 0.2); border-radius: 16px; padding: 1rem 1.25rem; display: flex; gap: 0.85rem; align-items: flex-start;">
                            <span style="width: 32px; height: 32px; border-radius: 10px; background: rgba(37, 99, 235, 0.15); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; margin-top: 2px;">
                                <i class="fa-solid fa-shield-halved"></i>
                            </span>
                            <div style="font-size: 0.85rem; color: var(--text-main); line-height: 1.5;">
                                <template x-if="isUpgradeSelected()">
                                    <span>{{ __('Plan upgrades require payment confirmation. Once payment is confirmed, your new limits and features activate immediately.') }}</span>
                                </template>
                                <template x-if="isDowngradeSelected()">
                                    <span>{{ __('Plan downgrades take effect only after your current billing period expires. Your current features remain active until then.') }}</span>
                                </template>
                                <template x-if="isSameSelected()">
                                    <span>{{ __('This is your current plan. To extend your subscription or change billing cycle, switch to Renew & Pay.') }}</span>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Plan Preview & Action -->
                    <div class="subscription-summary-pane" style="background: var(--bg-body); border-left: 1px solid var(--border-color); padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between; overflow-y: auto;">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.6rem;">
                                <div style="display: flex; align-items: center; gap: 0.6rem;">
                                    <span style="width: 32px; height: 32px; border-radius: 10px; background: rgba(37, 99, 235, 0.12); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; font-size: 0.95rem;">
                                        <i class="fa-solid fa-sparkles"></i>
                                    </span>
                                    <h4 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 1.05rem; font-weight: 800; color: var(--text-main);">
                                        {{ __('Target Plan') }}
                                    </h4>
                                </div>
                                <template x-if="isUpgradeSelected()">
                                    <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #d97706; font-weight: 800; font-size: 0.72rem; padding: 3px 8px; border-radius: 6px;">
                                        <i class="fa-solid fa-bolt"></i> {{ __('Upgrade') }}
                                    </span>
                                </template>
                                <template x-if="isDowngradeSelected()">
                                    <span class="badge" style="background: rgba(37, 99, 235, 0.12); color: var(--primary); font-weight: 800; font-size: 0.72rem; padding: 3px 8px; border-radius: 6px;">
                                        <i class="fa-solid fa-arrow-down"></i> {{ __('Downgrade') }}
                                    </span>
                                </template>
                                <template x-if="isSameSelected()">
                                    <span class="badge badge-emerald" style="font-weight: 800; font-size: 0.72rem; padding: 3px 8px; border-radius: 6px;">
                                        <i class="fa-solid fa-circle-check"></i> {{ __('Current') }}
                                    </span>
                                </template>
                            </div>

                            <div class="sub-receipt-box">
                                <div style="font-size: 1.2rem; font-weight: 800; color: var(--text-main);" x-text="plansInfo[selectedPlanId]?.name"></div>
                                <div style="font-family: 'Outfit'; font-size: 1.55rem; font-weight: 900; color: var(--primary); margin: 0.35rem 0;" x-text="plansInfo[selectedPlanId]?.formatted_price + ' / {{ __('mo') }}'"></div>
                                
                                <div style="margin-top: 1rem; border-top: 1px dashed var(--border-color); padding-top: 0.85rem;">
                                    <div style="font-size: 0.78rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                                        {{ __('Features Included') }}
                                    </div>
                                    <template x-for="feat in (plansInfo[selectedPlanId]?.features || [])" :key="feat">
                                        <div style="display: flex; align-items: flex-start; gap: 0.4rem; font-size: 0.82rem; color: var(--text-main); margin-bottom: 0.4rem; line-height: 1.35;">
                                            <i class="fa-solid fa-check" style="color: #10b981; margin-top: 3px; flex-shrink: 0; font-size: 0.75rem;"></i>
                                            <span x-text="feat"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 1.5rem;">
                            <!-- Case 1: UPGRADE (Requires payment) -->
                            <template x-if="isUpgradeSelected()">
                                <div>
                                    <button type="button" @click="modalTab = 'renew'" class="btn btn-primary" style="width: 100%; border-radius: 14px; font-weight: 800; padding: 0.95rem 1.25rem; font-size: 1rem; display: flex; align-items: center; justify-content: center; gap: 0.65rem; box-shadow: 0 8px 24px var(--primary-glow); cursor: pointer;">
                                        <i class="fa-solid fa-credit-card"></i>
                                        <span>{{ __('Proceed to Payment (Upgrade)') }}</span>
                                        <i class="fa-solid fa-arrow-right" style="font-size: 0.85rem;"></i>
                                    </button>
                                    <div style="display: flex; align-items: center; justify-content: center; gap: 0.45rem; font-size: 0.75rem; color: var(--text-muted); margin-top: 0.85rem; font-weight: 600;">
                                        <i class="fa-solid fa-shield-halved" style="color: #10b981;"></i>
                                        <span>{{ __('Activated immediately upon payment confirmation') }}</span>
                                    </div>
                                </div>
                            </template>

                            <!-- Case 2: DOWNGRADE (Takes effect at period end) -->
                            <template x-if="isDowngradeSelected()">
                                <div>
                                    <button type="submit" class="btn btn-secondary" style="width: 100%; border-radius: 14px; font-weight: 800; padding: 0.95rem 1.25rem; font-size: 1rem; display: flex; align-items: center; justify-content: center; gap: 0.65rem; cursor: pointer; border-color: var(--border-color); background: var(--bg-card); color: var(--text-main);">
                                        <i class="fa-solid fa-clock-rotate-left" style="color: #f59e0b;"></i>
                                        <span>{{ __('Schedule Downgrade (At Period End)') }}</span>
                                    </button>
                                    <div style="display: flex; align-items: center; justify-content: center; gap: 0.45rem; font-size: 0.75rem; color: var(--text-muted); margin-top: 0.85rem; font-weight: 600;">
                                        <i class="fa-solid fa-circle-check" style="color: #10b981;"></i>
                                        <span>{{ __('Current features remain active until period expiration') }}</span>
                                    </div>
                                </div>
                            </template>

                            <!-- Case 3: SAME PLAN -->
                            <template x-if="isSameSelected()">
                                <div>
                                    <button type="button" @click="modalTab = 'renew'" class="btn btn-primary" style="width: 100%; border-radius: 14px; font-weight: 800; padding: 0.95rem 1.25rem; font-size: 1rem; display: flex; align-items: center; justify-content: center; gap: 0.65rem; cursor: pointer;">
                                        <i class="fa-solid fa-bolt" style="color: #f59e0b;"></i>
                                        <span>{{ __('Renew This Plan') }}</span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- TAB 2: Renewal & Payment Form (2-Column Split) -->
        <div x-show="modalTab === 'renew'" style="display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden;">
            <form action="{{ route('admin.subscription.renew') }}" method="POST" style="display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden; margin: 0;">
                @csrf
                <div class="subscription-modal-grid" style="display: grid; grid-template-columns: 1fr 340px; flex: 1; min-height: 0; overflow: hidden;">
                    
                    <!-- Left: Configuration Canvas -->
                    <div style="overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1.35rem;">
                        
                        <!-- 1. Select Plan to Renew -->
                        <div>
                            <div class="sub-step-header">
                                <span class="sub-step-num">1</span>
                                <span class="sub-step-label">{{ __('Select Plan to Renew') }}</span>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem;">
                                @foreach($allPlans as $p)
                                    <label class="sub-card"
                                           :class="{ 'is-selected': selectedPlanId == {{ $p->id }} }"
                                           style="padding: 1rem 1.15rem; display: flex; flex-direction: column; justify-content: space-between; min-height: 106px;">
                                        <input type="radio" name="subscription_plan_id" value="{{ $p->id }}" x-model="selectedPlanId" class="sub-hidden-input">
                                        <div>
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem;">
                                                <span style="font-weight: 800; font-size: 1rem; color: var(--text-main); line-height: 1.3;">{{ __($p->name) }}</span>
                                                <span class="sub-radio-dot">
                                                    <i class="fa-solid fa-check" x-show="selectedPlanId == {{ $p->id }}"></i>
                                                </span>
                                            </div>
                                            <div style="margin-top: 0.45rem; display: flex; align-items: baseline; gap: 0.25rem;">
                                                <span style="font-size: 1.25rem; font-weight: 900; color: var(--text-main); font-family: 'Outfit', sans-serif;">{{ number_format($p->price, 0, '.', ' ') }}</span>
                                                <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted);">{{ $p->currency }}/{{ __('mo') }}</span>
                                            </div>
                                        </div>
                                        <div style="margin-top: 0.65rem; min-height: 22px; display: flex; align-items: center;">
                                            @if($vendor->subscription_plan_id == $p->id)
                                                <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #10b981; font-weight: 800; font-size: 0.68rem; padding: 2px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.35rem; border: 1px solid rgba(16, 185, 129, 0.25);">
                                                    <i class="fa-solid fa-circle-check" style="font-size: 0.6rem;"></i> {{ __('Current Plan') }}
                                                </span>
                                            @endif
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <!-- 2. Select Duration Period -->
                        <div>
                            <div class="sub-step-header">
                                <span class="sub-step-num">2</span>
                                <span class="sub-step-label">{{ __('Select Billing Interval') }}</span>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.65rem;">
                                <!-- 1 Mo -->
                                <label class="sub-period-card" :class="{ 'is-selected': periodMonths == 1 }">
                                    <input type="radio" name="period_months" value="1" x-model="periodMonths" class="sub-hidden-input">
                                    <span style="font-weight: 800; font-size: 1.05rem; color: var(--text-main); font-family: 'Outfit', sans-serif;">{{ __('1 Mo') }}</span>
                                    <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 600; margin-top: 3px;">{{ __('Standard') }}</span>
                                </label>

                                <!-- 3 Mo -->
                                <label class="sub-period-card" :class="{ 'is-selected': periodMonths == 3 }">
                                    <input type="radio" name="period_months" value="3" x-model="periodMonths" class="sub-hidden-input">
                                    <span style="font-weight: 800; font-size: 1.05rem; color: var(--text-main); font-family: 'Outfit', sans-serif;">{{ __('3 Mo') }}</span>
                                    <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 600; margin-top: 3px;">{{ __('Quarterly') }}</span>
                                </label>

                                <!-- 6 Mo -->
                                <label class="sub-period-card" :class="{ 'is-selected': periodMonths == 6 }">
                                    <input type="radio" name="period_months" value="6" x-model="periodMonths" class="sub-hidden-input">
                                    <span class="period-discount-badge" style="background: #10b981;">-10%</span>
                                    <span style="font-weight: 800; font-size: 1.05rem; color: var(--text-main); font-family: 'Outfit', sans-serif;">{{ __('6 Mo') }}</span>
                                    <span style="font-size: 0.72rem; color: #10b981; font-weight: 700; margin-top: 3px;">{{ __('10% Off') }}</span>
                                </label>

                                <!-- 12 Mo -->
                                <label class="sub-period-card" :class="{ 'is-selected': periodMonths == 12 }">
                                    <input type="radio" name="period_months" value="12" x-model="periodMonths" class="sub-hidden-input">
                                    <span class="period-discount-badge" style="background: #f97316;">-20%</span>
                                    <span style="font-weight: 800; font-size: 1.05rem; color: var(--text-main); font-family: 'Outfit', sans-serif;">{{ __('12 Mo') }}</span>
                                    <span style="font-size: 0.72rem; color: #f97316; font-weight: 700; margin-top: 3px;">{{ __('20% Off') }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- 3. Select Payment Method -->
                        <div>
                            <div class="sub-step-header">
                                <span class="sub-step-num">3</span>
                                <span class="sub-step-label">{{ __('Select Payment Method') }}</span>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.65rem;">
                                @php
                                    $paymentMethods = [
                                        ['code' => 'bank_transfer', 'name' => __('Bank Transfer'), 'icon' => 'fa-solid fa-building-columns', 'color' => '#10b981'],
                                        ['code' => 'idram', 'name' => __('Idram Wallet / QR'), 'icon' => 'fa-solid fa-wallet', 'color' => '#f97316'],
                                        ['code' => 'telcell', 'name' => __('Telcell Wallet'), 'icon' => 'fa-solid fa-mobile-screen-button', 'color' => '#eab308'],
                                        ['code' => 'fastshift', 'name' => __('FastShift'), 'icon' => 'fa-solid fa-bolt', 'color' => '#06b6d4'],
                                        ['code' => 'arca', 'name' => __('ArCa / Ameriabank'), 'icon' => 'fa-solid fa-credit-card', 'color' => '#2563eb'],
                                        ['code' => 'stripe', 'name' => __('Stripe (Cards)'), 'icon' => 'fa-brands fa-stripe', 'color' => '#6366f1'],
                                    ];
                                @endphp
                                @foreach($paymentMethods as $pm)
                                    <label class="sub-pay-card" :class="{ 'is-selected': paymentMethod == '{{ $pm['code'] }}' }">
                                        <input type="radio" name="payment_method" value="{{ $pm['code'] }}" x-model="paymentMethod" class="sub-hidden-input">
                                        <div style="display: flex; align-items: center; gap: 0.75rem; min-width: 0; flex: 1;">
                                            <span style="width: 36px; height: 36px; border-radius: 10px; background: {{ $pm['color'] }}18; color: {{ $pm['color'] }}; display: inline-flex; align-items: center; justify-content: center; font-size: 1.05rem; flex-shrink: 0;">
                                                <i class="{{ $pm['icon'] }}"></i>
                                            </span>
                                            <span style="font-weight: 700; font-size: 0.85rem; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $pm['name'] }}</span>
                                        </div>
                                        <span class="sub-radio-dot">
                                            <i class="fa-solid fa-check" x-show="paymentMethod == '{{ $pm['code'] }}'"></i>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Right: Order Summary & Checkout Card -->
                    <div class="subscription-summary-pane" style="background: var(--bg-body); border-left: 1px solid var(--border-color); padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between; overflow-y: auto;">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <!-- Summary Header -->
                            <div style="display: flex; align-items: center; gap: 0.6rem;">
                                <span style="width: 32px; height: 32px; border-radius: 10px; background: rgba(37, 99, 235, 0.12); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; font-size: 0.95rem;">
                                    <i class="fa-solid fa-receipt"></i>
                                </span>
                                <h4 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 1.05rem; font-weight: 800; color: var(--text-main);">
                                    {{ __('Order Summary') }}
                                </h4>
                            </div>

                            <!-- Receipt Details Box -->
                            <div class="sub-receipt-box">
                                <div class="sub-receipt-row">
                                    <span>{{ __('Plan') }}</span>
                                    <strong x-text="plansInfo[selectedPlanId]?.name"></strong>
                                </div>
                                
                                <div class="sub-receipt-row">
                                    <span>{{ __('Monthly Rate') }}</span>
                                    <span style="font-weight: 700; color: var(--text-muted);" x-text="plansInfo[selectedPlanId]?.formatted_price + ' / {{ __('mo') }}'"></span>
                                </div>

                                <div class="sub-receipt-row">
                                    <span>{{ __('Duration') }}</span>
                                    <strong x-text="periodMonths + ' ' + (periodMonths == 1 ? '{{ __('month') }}' : '{{ __('months') }}')"></strong>
                                </div>

                                <div class="sub-receipt-row" x-show="periodMonths > 1">
                                    <span>{{ __('Base Subtotal') }}</span>
                                    <span style="font-weight: 700; color: var(--text-muted);" x-text="calculateBaseTotal() + ' {{ $vendor->currency ?? '֏' }}'"></span>
                                </div>

                                <div class="sub-receipt-row" x-show="calculateDiscountPercent() > 0" style="color: #10b981;">
                                    <span style="display: flex; align-items: center; gap: 0.35rem; font-weight: 700;">
                                        <i class="fa-solid fa-tag"></i> <span x-text="'{{ __('Discount') }} (' + calculateDiscountPercent() + '%)'"></span>
                                    </span>
                                    <strong style="color: #10b981;" x-text="'-' + calculateDiscountSavings() + ' {{ $vendor->currency ?? '֏' }}'"></strong>
                                </div>

                                <div class="sub-receipt-row" style="border-top: 1px dashed var(--border-color); margin-top: 0.4rem; padding-top: 0.65rem;">
                                    <span>{{ __('Method') }}</span>
                                    <strong style="display: inline-flex; align-items: center; gap: 0.4rem;">
                                        <i :class="paymentMethodsInfo[paymentMethod]?.icon" :style="'color: ' + paymentMethodsInfo[paymentMethod]?.color"></i>
                                        <span x-text="paymentMethodsInfo[paymentMethod]?.name"></span>
                                    </strong>
                                </div>
                            </div>
                        </div>

                        <!-- Summary Bottom Action Area -->
                        <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 0.85rem;">
                            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 0.85rem 1rem; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 800; color: var(--text-muted); letter-spacing: 0.05em;">
                                        {{ __('Total Due') }}
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 1px;">
                                        {{ __('One-time payment') }}
                                    </div>
                                </div>
                                <div style="font-family: 'Outfit', sans-serif; font-weight: 900; font-size: 1.65rem; color: var(--primary); text-align: right; line-height: 1;" x-text="calculateTotal() + ' ' + '{{ $vendor->currency ?? '֏' }}'"></div>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width: 100%; border-radius: 14px; font-weight: 800; padding: 0.95rem 1.25rem; font-size: 1rem; display: flex; align-items: center; justify-content: center; gap: 0.65rem; box-shadow: 0 8px 24px var(--primary-glow); transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer;">
                                <i class="fa-solid fa-lock"></i>
                                <span>{{ __('Proceed to Payment') }}</span>
                                <i class="fa-solid fa-arrow-right" style="font-size: 0.85rem; margin-left: 2px;"></i>
                            </button>

                            <div style="display: flex; align-items: center; justify-content: center; gap: 0.45rem; font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">
                                <i class="fa-solid fa-shield-halved" style="color: #10b981;"></i>
                                <span>{{ __('SSL 256-bit Encrypted & Secure Payment') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add-on Activation / Purchase Modal (Alpine.js) -->
<div x-show="addonModalOpen" 
     x-cloak 
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     style="position: fixed; inset: 0; background: rgba(15, 23, 42, 0.72); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 999; display: flex; align-items: center; justify-content: center; padding: 1rem;">
    <div @click.away="addonModalOpen = false" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 24px; width: 100%; max-width: 480px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 60px -15px rgba(0,0,0,0.5);">
        
        <!-- Header -->
        <div style="padding: 1.15rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; background: var(--bg-card);">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="width: 42px; height: 42px; border-radius: 12px; background: rgba(99, 102, 241, 0.12); color: #6366f1; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                    <i class="fa-solid fa-puzzle-piece"></i>
                </span>
                <div>
                    <h3 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 800; color: var(--text-main); line-height: 1.2;">
                        {{ __('Add-on Activation') }}
                    </h3>
                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;" x-text="selectedAddon ? selectedAddon.name : ''"></div>
                </div>
            </div>
            <button type="button" @click="addonModalOpen = false" style="width: 36px; height: 36px; border-radius: 10px; background: var(--bg-body); border: 1px solid var(--border-color); font-size: 1.1rem; color: var(--text-muted); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all 0.15s ease;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="{{ route('admin.subscription.addons.store') }}" method="POST" style="margin: 0; display: flex; flex-direction: column;">
            @csrf
            <input type="hidden" name="subscription_addon_id" :value="selectedAddon ? selectedAddon.id : ''">

            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem;">
                <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.15rem;">
                    <div style="font-weight: 800; font-size: 1.05rem; color: var(--text-main);" x-text="selectedAddon ? selectedAddon.name : ''"></div>
                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.35rem; line-height: 1.5;" x-text="selectedAddon ? selectedAddon.description : ''"></div>
                </div>

                <!-- Quantity Selector -->
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 800; color: var(--text-muted); margin-bottom: 0.65rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        {{ __('Quantity / Multiplier') }}
                    </label>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <button type="button" @click="if (addonQuantity > 1) addonQuantity--" class="btn btn-secondary" style="width: 44px; height: 44px; border-radius: 12px; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; font-weight: 800;">
                            -
                        </button>
                        <input type="number" name="quantity" min="1" max="50" x-model.number="addonQuantity" style="width: 80px; text-align: center; font-size: 1.2rem; font-weight: 800; padding: 0.65rem; border-radius: 12px; border: 1px solid var(--border-color); background: var(--bg-body); color: var(--text-main); font-family: 'Outfit', sans-serif;">
                        <button type="button" @click="if (addonQuantity < 50) addonQuantity++" class="btn btn-secondary" style="width: 44px; height: 44px; border-radius: 12px; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; font-weight: 800;">
                            +
                        </button>
                    </div>
                </div>

                <!-- Billing Period -->
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 800; color: var(--text-muted); margin-bottom: 0.65rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        {{ __('Billing Period') }}
                    </label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                        <label style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.85rem 0.5rem; border-radius: 14px; border: 2px solid; cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); user-select: none;"
                               :style="addonBillingPeriod === 'monthly' ? 'border-color: var(--primary); background: var(--bg-card); box-shadow: 0 0 0 3px var(--primary-glow); color: var(--primary); font-weight: 800;' : 'border-color: var(--border-color); background: var(--bg-body); color: var(--text-muted);'">
                            <input type="radio" name="billing_period" value="monthly" x-model="addonBillingPeriod" style="display: none;">
                            <span>{{ __('Monthly') }}</span>
                        </label>
                        <label style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.85rem 0.5rem; border-radius: 14px; border: 2px solid; cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); user-select: none;"
                               :style="addonBillingPeriod === 'yearly' ? 'border-color: var(--primary); background: var(--bg-card); box-shadow: 0 0 0 3px var(--primary-glow); color: var(--primary); font-weight: 800;' : 'border-color: var(--border-color); background: var(--bg-body); color: var(--text-muted);'">
                            <input type="radio" name="billing_period" value="yearly" x-model="addonBillingPeriod" style="display: none;">
                            <span>{{ __('Yearly') }}</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Fixed Footer -->
            <div style="padding: 1.25rem 1.5rem; border-top: 1px solid var(--border-color); background: var(--bg-body); display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
                <div>
                    <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 800; color: var(--text-muted); letter-spacing: 0.05em;">{{ __('Total') }}</div>
                    <div style="font-family: 'Outfit', sans-serif; font-weight: 900; font-size: 1.55rem; color: var(--primary); line-height: 1.1;" x-text="calculateAddonTotal() + ' ' + (selectedAddon ? selectedAddon.currency : '֏')"></div>
                </div>
                <div style="display: flex; gap: 0.75rem;">
                    <button type="button" @click="addonModalOpen = false" class="btn btn-secondary" style="border-radius: 12px; font-weight: 700; padding: 0.7rem 1.25rem;">
                        {{ __('Close') }}
                    </button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-weight: 800; padding: 0.7rem 1.75rem; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 6px 20px var(--primary-glow);">
                        <i class="fa-solid fa-check"></i>
                        <span>{{ __('Activate Add-on') }}</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Cancel Subscription Confirmation Modal (Alpine.js) -->
<div x-show="cancelModalOpen" x-cloak style="position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); z-index: 999; display: flex; align-items: center; justify-content: center; padding: 1.25rem;">
    <div @click.away="cancelModalOpen = false" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 24px; width: 100%; max-width: 480px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); padding: 1.75rem;">
        <div style="text-align: center; margin-bottom: 1.25rem;">
            <div style="width: 56px; height: 56px; border-radius: 18px; background: rgba(239, 68, 68, 0.12); color: #ef4444; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.35rem 0;">
                {{ __('Cancel Subscription?') }}
            </h3>
            <p style="font-size: 0.88rem; color: var(--text-muted); margin: 0; line-height: 1.5;">
                {{ __('Your subscription will remain active until the end of the current billing period (:date). No further charges will be made.', ['date' => $subscription->current_period_end ? $subscription->current_period_end->format('d.m.Y') : __('current cycle')]) }}
            </p>
        </div>

        <form action="{{ route('admin.subscription.cancel') }}" method="POST">
            @csrf
            <div style="margin-bottom: 1.25rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem;">
                    {{ __('Reason for cancellation (optional):') }}
                </label>
                <textarea name="reason" rows="2" class="form-input" placeholder="{{ __('Let us know how we can improve...') }}" style="width: 100%; border-radius: 10px; font-size: 0.85rem;"></textarea>
            </div>

            <div style="display: flex; gap: 0.75rem;">
                <button type="button" @click="cancelModalOpen = false" class="btn btn-secondary" style="flex: 1; justify-content: center; border-radius: 12px; font-weight: 700;">
                    {{ __('Keep Subscription') }}
                </button>
                <button type="submit" class="btn btn-danger" style="flex: 1; justify-content: center; border-radius: 12px; font-weight: 800; background: #ef4444; color: #fff;">
                    {{ __('Confirm Cancel') }}
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Payment History Table -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 0; overflow: hidden; box-shadow: var(--shadow-card);">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <span style="color: var(--primary);"><i class="fa-solid fa-receipt"></i></span>
            <span>{{ __('Payment & Invoice History') }}</span>
        </h3>
    </div>

    @if($payments->count() > 0)
        <div class="responsive-table-wrapper">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead>
                    <tr style="background: var(--bg-body); border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em;">
                        <th style="padding: 1rem 1.25rem;">{{ __('Invoice #') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Date') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Plan') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Amount') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Period') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Method') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Status') }}</th>
                        <th style="padding: 1rem 1.25rem; text-align: right;">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $p)
                        <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-main); transition: background 0.15s ease;">
                            <td style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-main); font-family: 'Outfit', sans-serif;">
                                <a href="{{ route('admin.subscription.invoices.show', $p->id) }}" style="color: var(--primary); text-decoration: none;">
                                    {{ $p->invoice_number ?? '—' }}
                                </a>
                            </td>
                            <td style="padding: 1rem 1.25rem; font-size: 0.85rem; color: var(--text-muted); white-space: nowrap;">
                                {{ $p->created_at->format('d.m.Y H:i') }}
                            </td>
                            <td style="padding: 1rem 1.25rem; font-weight: 600;">{{ $p->plan?->name ?? 'Standard' }}</td>
                            <td style="padding: 1rem 1.25rem; font-weight: 800; color: #10b981; font-family: 'Outfit', sans-serif; white-space: nowrap;">
                                {{ number_format($p->amount, 0, '.', ' ') }} {{ $p->currency }}
                            </td>
                            <td style="padding: 1rem 1.25rem; font-size: 0.82rem; color: var(--text-muted); white-space: nowrap;">
                                {{ $p->period_start ? $p->period_start->format('d.m.Y') : '—' }} - {{ $p->period_end ? $p->period_end->format('d.m.Y') : '—' }}
                            </td>
                            <td style="padding: 1rem 1.25rem; font-size: 0.85rem; font-weight: 600;">
                                {{ ucfirst(str_replace('_', ' ', $p->payment_method)) }}
                            </td>
                            <td style="padding: 1rem 1.25rem; white-space: nowrap;">
                                @if(in_array($p->status, ['paid', 'completed']))
                                    <span class="badge badge-emerald" style="border-radius: 8px;">
                                        <i class="fa-solid fa-check" style="font-size: 0.7rem;"></i> {{ __('Paid') }}
                                    </span>
                                @elseif($p->status === 'failed')
                                    <span class="badge badge-rose" style="border-radius: 8px;">
                                        <i class="fa-solid fa-xmark" style="font-size: 0.7rem;"></i> {{ __('Failed') }}
                                    </span>
                                @else
                                    <span class="badge badge-amber" style="border-radius: 8px;">
                                        <i class="fa-solid fa-clock" style="font-size: 0.7rem;"></i> {{ ucfirst($p->status) }}
                                    </span>
                                @endif
                            </td>
                            <td style="padding: 1rem 1.25rem; text-align: right; white-space: nowrap;">
                                <a href="{{ route('admin.subscription.invoices.show', $p->id) }}" class="btn btn-secondary" style="font-size: 0.78rem; padding: 0.35rem 0.65rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-file-invoice"></i> {{ __('View') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($payments->hasPages())
            <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color);">
                {{ $payments->links() }}
            </div>
        @endif
    @else
        <div style="text-align: center; padding: 3.5rem 1rem; color: var(--text-muted);">
            <div style="width: 56px; height: 56px; border-radius: 16px; background: rgba(245, 158, 11, 0.1); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 0.85rem;">
                <i class="fa-solid fa-receipt"></i>
            </div>
            <div style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem;">{{ __('No payment history available yet') }}</div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">{{ __('Invoices will appear here once renewals or offline payments are processed.') }}</p>
        </div>
    @endif
</div>
</div>

<script>
function subscriptionManagerApp() {
    return {
        renewModalOpen: false,
        cancelModalOpen: false,
        addonModalOpen: false,
        modalTab: 'renew',
        selectedPlanId: {{ $vendor->subscription_plan_id ?? ($allPlans->first()?->id ?? 1) }},
        periodMonths: 1,
        paymentMethod: 'bank_transfer',
        pricesMap: @json($plansMap),
        addonsMap: @json($addonsMap),
        plansInfo: @json($plansInfo),
        paymentMethodsInfo: {
            'bank_transfer': { name: '{{ __('Bank Transfer') }}', icon: 'fa-solid fa-building-columns', color: '#10b981' },
            'idram': { name: '{{ __('Idram Wallet / QR') }}', icon: 'fa-solid fa-wallet', color: '#f97316' },
            'telcell': { name: '{{ __('Telcell Wallet') }}', icon: 'fa-solid fa-mobile-screen-button', color: '#eab308' },
            'fastshift': { name: '{{ __('FastShift') }}', icon: 'fa-solid fa-bolt', color: '#06b6d4' },
            'arca': { name: '{{ __('ArCa / Ameriabank') }}', icon: 'fa-solid fa-credit-card', color: '#2563eb' },
            'stripe': { name: '{{ __('Stripe (Cards)') }}', icon: 'fa-brands fa-stripe', color: '#6366f1' },
        },
        selectedAddon: null,
        addonQuantity: 1,
        addonBillingPeriod: 'monthly',
        isUpgradeSelected() {
            const currentPrice = this.pricesMap[{{ $vendor->subscription_plan_id ?? 0 }}] || 0;
            const newPrice = this.pricesMap[this.selectedPlanId] || 0;
            return newPrice > currentPrice;
        },
        isDowngradeSelected() {
            const currentPrice = this.pricesMap[{{ $vendor->subscription_plan_id ?? 0 }}] || 0;
            const newPrice = this.pricesMap[this.selectedPlanId] || 0;
            return newPrice < currentPrice;
        },
        isSameSelected() {
            return this.selectedPlanId == {{ $vendor->subscription_plan_id ?? 0 }};
        },
        openRenewModal(planId, tab = 'renew') {
            if (planId) {
                this.selectedPlanId = planId;
            }
            this.modalTab = tab;
            this.renewModalOpen = true;
        },
        openAddonModal(addonId) {
            const addon = this.addonsMap[addonId];
            if (!addon) return;
            this.selectedAddon = addon;
            this.addonQuantity = 1;
            this.addonBillingPeriod = addon.billing_period || 'monthly';
            this.addonModalOpen = true;
        },
        calculateBaseTotal() {
            const base = this.pricesMap[this.selectedPlanId] || 0;
            return Number(Math.round(base * this.periodMonths)).toLocaleString();
        },
        calculateDiscountPercent() {
            if (this.periodMonths == 12) return 20;
            if (this.periodMonths == 6) return 10;
            return 0;
        },
        calculateDiscountSavings() {
            const base = this.pricesMap[this.selectedPlanId] || 0;
            const full = base * this.periodMonths;
            const pct = this.calculateDiscountPercent();
            if (pct === 0) return '0';
            const savings = full * (pct / 100);
            return Number(Math.round(savings)).toLocaleString();
        },
        calculateTotal() {
            const base = this.pricesMap[this.selectedPlanId] || 0;
            let total = base * this.periodMonths;
            if (this.periodMonths == 12) total = total * 0.80;
            else if (this.periodMonths == 6) total = total * 0.90;
            return Number(Math.round(total)).toLocaleString();
        },
        calculateAddonTotal() {
            if (!this.selectedAddon) return '0';
            let price = (this.addonBillingPeriod === 'yearly' && this.selectedAddon.yearly_price > 0)
                ? this.selectedAddon.yearly_price
                : (this.selectedAddon.monthly_price || 0);
            return Number(Math.round(price * this.addonQuantity)).toLocaleString();
        }
    };
}
</script>
@endsection
