@extends('layouts.app')

@section('title', __('Subscription Plans & Entitlement System') . ' - SuperAdmin')

@section('styles')
<style>
    .plan-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: 1.75rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: var(--shadow-card);
        position: relative;
        overflow: hidden;
        transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    }
    .plan-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.15);
    }
    .plan-card-basic { border-top: 5px solid #64748b; }
    .plan-card-pro { border-top: 5px solid #f59e0b; }
    .plan-card-advanced, .plan-card-business { border-top: 5px solid #06b6d4; }
    .plan-card-custom { border-top: 5px solid #a855f7; }

    .feature-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.82rem;
        color: var(--text-muted);
        margin-bottom: 0.45rem;
    }
    .feature-icon-check {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: rgba(16, 185, 129, 0.15);
        color: #10b981;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.65rem;
        flex-shrink: 0;
    }
    .sub-nav-tabs {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-bottom: 1.75rem;
        padding: 0.4rem;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 14px;
    }
    .sub-nav-btn {
        padding: 0.6rem 1rem;
        border-radius: 10px;
        border: none;
        background: transparent;
        color: var(--text-muted);
        font-size: 0.82rem;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        transition: all 0.2s ease;
    }
    .sub-nav-btn.active {
        background: var(--primary);
        color: #fff;
        box-shadow: 0 4px 12px rgba(225, 29, 72, 0.25);
    }
    .builder-cat-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        margin-bottom: 1.25rem;
        overflow: hidden;
    }
    .builder-cat-header {
        padding: 1rem 1.25rem;
        background: var(--input-bg);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .builder-feature-row {
        display: grid;
        grid-template-columns: 1.6fr 1fr 1.4fr;
        gap: 1rem;
        align-items: center;
        padding: 0.85rem 1.25rem;
        border-bottom: 1px solid var(--border-color);
    }
    .builder-feature-row:last-child {
        border-bottom: none;
    }
    @media (max-width: 900px) {
        .builder-feature-row {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('content')
@php
    $featureCategories = $featureCategories ?? collect();
    $catalogFeatures = $catalogFeatures ?? collect();
    $featureDependencies = $featureDependencies ?? collect();
    $subscriptionAddons = $subscriptionAddons ?? collect();
    $subscriptionSettings = $subscriptionSettings ?? [
        'default_trial_days' => 14,
        'grace_period_days' => 3,
        'warning_threshold_percent' => 80,
        'downgrade_behavior' => 'preserve_data_restrict_new',
    ];
    $depMapByFeature = $featureDependencies->groupBy('feature_code');
@endphp

<!-- Page Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
            <span class="badge badge-amber">
                <i class="fa-solid fa-box-archive"></i> {{ __('Plan Management') }}
            </span>
            <span style="font-size: 0.78rem; color: var(--text-muted);">{{ __('Total') }}: {{ $plans->count() }}</span>
            <span class="badge badge-emerald" style="font-size: 0.72rem;">
                <i class="fa-solid fa-cubes"></i> {{ $catalogFeatures->count() }} {{ __('Features') }}
            </span>
        </div>
        <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
            {{ __('Subscription Plans') }}
        </h1>
    </div>
    <div style="display: flex; gap: 0.65rem; flex-wrap: wrap;">
        <a href="{{ route('superadmin.subscriptions.index') }}" class="btn btn-secondary" style="border-radius: 12px; font-size: 0.85rem;">
            <i class="fa-solid fa-users-gear"></i> {{ __('Vendor Subscriptions & Overrides') }}
        </a>
        <button class="btn btn-primary" onclick="document.getElementById('createPlanModal').style.display='flex'" style="border-radius: 12px; font-size: 0.88rem; padding: 0.65rem 1.25rem;">
            <i class="fa-solid fa-plus"></i> {{ __('Add New Plan') }}
        </button>
    </div>
</div>

<!-- Dynamic Management Navigation Tabs -->
<div class="sub-nav-tabs" id="planSystemTabs">
    <button type="button" class="sub-nav-btn active" onclick="switchPlanTab('overview', this)">
        <i class="fa-solid fa-layer-group"></i> {{ __('Plans Overview') }}
    </button>
    <button type="button" class="sub-nav-btn" onclick="switchPlanTab('builder', this)">
        <i class="fa-solid fa-sliders"></i> {{ __('Plan Builder') }}
    </button>
    <button type="button" class="sub-nav-btn" onclick="switchPlanTab('comparison', this)">
        <i class="fa-solid fa-table-columns"></i> {{ __('Compare & Versions') }}
    </button>
    <button type="button" class="sub-nav-btn" onclick="switchPlanTab('catalog', this)">
        <i class="fa-solid fa-list-check"></i> {{ __('Feature Catalog') }}
    </button>
    <button type="button" class="sub-nav-btn" onclick="switchPlanTab('categories', this)">
        <i class="fa-solid fa-folder-tree"></i> {{ __('Feature Categories') }}
    </button>
    <button type="button" class="sub-nav-btn" onclick="switchPlanTab('dependencies', this)">
        <i class="fa-solid fa-diagram-project"></i> {{ __('Dependencies') }}
    </button>
    <button type="button" class="sub-nav-btn" onclick="switchPlanTab('addons', this)">
        <i class="fa-solid fa-puzzle-piece"></i> {{ __('Add-ons') }}
    </button>
    <button type="button" class="sub-nav-btn" onclick="switchPlanTab('settings', this)">
        <i class="fa-solid fa-gear"></i> {{ __('Subscription Settings') }}
    </button>
</div>

<!-- TAB 1: PLANS OVERVIEW -->
<div id="tab-overview" class="plan-tab-pane">
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
        @foreach($plans as $plan)
            @php
                $slugLower = strtolower($plan->slug);
                $cardClass = in_array($slugLower, ['basic', 'pro', 'advanced', 'business', 'custom']) ? 'plan-card-'.$slugLower : 'plan-card-pro';
                $enabledPlanFeaturesCount = $plan->planFeatures ? $plan->planFeatures->where('is_enabled', true)->count() : count($plan->feature_codes ?? []);
            @endphp
            <div class="plan-card {{ $cardClass }}">
                <div>
                    <!-- Plan Top Bar -->
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.85rem;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.45rem;">
                                <h3 style="font-size: 1.3rem; font-weight: 800; font-family: 'Outfit'; color: var(--text-main); margin-bottom: 0.15rem;">
                                    {{ $plan->localizedName() }}
                                </h3>
                                <span class="badge badge-indigo" style="font-size: 0.68rem;">v{{ $plan->current_version_number ?? 1 }}</span>
                                @if($plan->is_archived)
                                    <span class="badge badge-rose" style="font-size: 0.68rem;">{{ __('Archived') }}</span>
                                @endif
                            </div>
                            <span style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">
                                SLUG: {{ $plan->slug }}
                            </span>
                        </div>

                        <form action="{{ route('superadmin.plans.toggle', $plan->id) }}" method="POST" style="margin: 0;">
                            @csrf
                            <button type="submit" class="badge {{ $plan->is_active ? 'badge-emerald' : 'badge-rose' }}" style="cursor: pointer; border-radius: 8px;">
                                <i class="fa-solid fa-circle" style="font-size: 0.45rem;"></i> {{ $plan->is_active ? __('Active') : __('Disabled') }}
                            </button>
                        </form>
                    </div>

                    <!-- Price & Duration -->
                    <div style="background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 1rem;">
                        <div style="font-size: 1.65rem; font-weight: 800; font-family: 'Outfit'; color: var(--primary);">
                            {{ $plan->formatted_price }}
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                            <span><i class="fa-solid fa-calendar-days"></i> {{ $plan->duration_days }} {{ __('days') }}</span>
                            <span>•</span>
                            <span><i class="fa-solid fa-clock"></i> {{ __('Trial: :days days', ['days' => $plan->trial_days]) }}</span>
                            @if($plan->yearly_price !== null)
                                <span>•</span>
                                <span><i class="fa-solid fa-calendar-check"></i> {{ __('Yearly') }}: {{ number_format((float)$plan->yearly_price, 0) }} {{ $plan->currency }}</span>
                            @endif
                        </div>
                    </div>

                    <!-- Description -->
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 1.25rem; line-height: 1.45; min-height: 2.5rem;">
                        {{ $plan->localizedDescription() ?: __('Standard pricing plan with all essential QRMenu features.') }}
                    </p>

                    <div style="border-top: 1px solid var(--border-color); padding-top: 1rem; margin-bottom: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.65rem;">
                            <h4 style="font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin: 0;">
                                {{ __('Included Features') }} ({{ count($plan->features ?? []) }})
                            </h4>
                            <span style="font-size: 0.72rem; color: var(--primary); font-weight: 700;">
                                {{ $enabledPlanFeaturesCount }} {{ __('entitlements active') }}
                            </span>
                        </div>
                        <div style="display: flex; flex-direction: column;">
                            @forelse($plan->features ?? [] as $feat)
                                <div class="feature-item">
                                    <div class="feature-icon-check"><i class="fa-solid fa-check"></i></div>
                                    <span>{{ $feat }}</span>
                                </div>
                            @empty
                                <div style="font-size: 0.78rem; color: var(--text-muted);">{{ __('No features listed') }}</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Card Bottom Bar -->
                <div style="border-top: 1px solid var(--border-color); padding-top: 1rem;">
                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.85rem; display: flex; align-items: center; justify-content: space-between;">
                        <span>🏢 {{ __('Active Partners') }}</span>
                        <strong style="color: var(--text-main); font-family: 'Outfit'; font-size: 0.95rem;">{{ $plan->vendors_count }}</strong>
                    </div>
                    <div style="display: flex; gap: 0.45rem; flex-wrap: wrap;">
                        <button type="button" class="btn btn-primary" style="flex: 1; font-size: 0.78rem; justify-content: center; border-radius: 10px;" onclick="openPlanInBuilder({{ $plan->id }})">
                            <i class="fa-solid fa-sliders"></i> {{ __('Builder') }}
                        </button>
                        <button type="button" class="btn btn-secondary" style="font-size: 0.78rem; justify-content: center; border-radius: 10px;" onclick="openEditModal({{ json_encode($plan) }})">
                            <i class="fa-solid fa-pen-to-square"></i> {{ __('Edit Plan') }}
                        </button>
                        <button type="button" class="btn btn-secondary" style="font-size: 0.78rem; border-radius: 10px; padding: 0.5rem 0.65rem;" title="{{ __('Duplicate Plan') }}" onclick="openDuplicatePlanModal({{ $plan->id }}, '{{ addslashes($plan->name) }}')">
                            <i class="fa-solid fa-copy"></i>
                        </button>
                        @if(!$plan->is_archived)
                            <form action="{{ route('superadmin.plans.archive', $plan->id) }}" method="POST" style="margin: 0;">
                                @csrf
                                <button type="submit" class="btn btn-secondary" style="color: #f59e0b; border-radius: 10px; padding: 0.5rem 0.65rem;" title="{{ __('Archive Plan') }}">
                                    <i class="fa-solid fa-box-archive"></i>
                                </button>
                            </form>
                        @endif
                        @if($plan->vendors_count == 0)
                            <form action="{{ route('superadmin.plans.destroy', $plan->id) }}" method="POST" onsubmit="return confirm('{{ __('Are you sure you want to delete this plan?') }}');" style="margin: 0;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-secondary" style="color: #ef4444; border-radius: 10px; padding: 0.5rem 0.65rem;" title="{{ __('Delete') }}">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- TAB 2: VISUAL PLAN BUILDER -->
<div id="tab-builder" class="plan-tab-pane" style="display: none;">
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.5rem; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
            <div>
                <h2 style="font-family: 'Outfit'; font-size: 1.35rem; font-weight: 800; margin: 0;">
                    <i class="fa-solid fa-sliders" style="color: var(--primary);"></i> {{ __('Visual Subscription Plan Builder') }}
                </h2>
                <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0.25rem 0 0;">
                    {{ __('Configure features, numerical limits (-1 = Unlimited), usage quotas, pricing, and immutable plan versions.') }}
                </p>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-muted);">{{ __('Select Plan') }}:</label>
                @foreach($plans as $p)
                    <button type="button" class="btn {{ $loop->first ? 'btn-primary' : 'btn-secondary' }} builder-plan-selector" data-plan-id="{{ $p->id }}" onclick="selectBuilderPlan({{ $p->id }}, this)" style="border-radius: 10px; font-size: 0.82rem; padding: 0.45rem 0.9rem;">
                        {{ $p->name }} <span style="opacity: 0.75; font-size: 0.7rem;">(v{{ $p->current_version_number ?? 1 }})</span>
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Search & Bulk Controls -->
        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; padding-top: 1rem; border-top: 1px solid var(--border-color);">
            <div style="flex: 1; min-width: 220px;">
                <input type="text" id="builderFeatureSearch" class="form-input" placeholder="🔍 {{ __('Search feature by name or code (e.g. menu.products.limit, pos.enabled)...') }}" oninput="filterBuilderFeatures(this.value)">
            </div>
            <button type="button" class="btn btn-secondary" style="font-size: 0.78rem;" onclick="bulkToggleBuilderFeatures(true)">
                <i class="fa-solid fa-check-double"></i> {{ __('Enable All Visible') }}
            </button>
            <button type="button" class="btn btn-secondary" style="font-size: 0.78rem;" onclick="bulkToggleBuilderFeatures(false)">
                <i class="fa-solid fa-ban"></i> {{ __('Disable All Visible') }}
            </button>
        </div>
    </div>

    @foreach($plans as $plan)
        @php
            $pfMap = $plan->planFeatures ? $plan->planFeatures->keyBy('feature_code') : collect();
            $legacyFeatureCodes = $plan->feature_codes ?? [];
            $legacyQuotaLimits = $plan->quota_limits ?? [];
        @endphp
        <form action="{{ route('superadmin.plans.builder.save', $plan->id) }}" method="POST" id="builder-form-{{ $plan->id }}" class="builder-plan-form" style="{{ $loop->first ? '' : 'display: none;' }}">
            @csrf
            <!-- Plan Pricing & Version Settings Bar -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.25rem;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Plan Name') }}</label>
                        <input type="text" name="name" value="{{ $plan->name }}" class="form-input" required>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Monthly Price') }} ({{ $plan->currency ?? 'AMD' }})</label>
                        <input type="number" step="0.01" min="0" name="price" value="{{ $plan->price }}" class="form-input" required>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Yearly Price') }} ({{ $plan->currency ?? 'AMD' }})</label>
                        <input type="number" step="0.01" min="0" name="yearly_price" value="{{ $plan->yearly_price }}" class="form-input" placeholder="{{ __('Optional yearly price') }}">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Trial Days') }}</label>
                        <input type="number" min="0" name="trial_days" value="{{ $plan->trial_days }}" class="form-input" required>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Billing Duration (Days)') }}</label>
                        <input type="number" min="1" name="duration_days" value="{{ $plan->duration_days }}" class="form-input" required>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; padding-top: 0.85rem; border-top: 1px solid var(--border-color);">
                    <div style="flex: 1; min-width: 240px;">
                        <input type="text" name="change_summary" class="form-input" placeholder="{{ __('Version change summary (e.g. Increased product limit to 1,500)...') }}">
                    </div>
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.82rem; font-weight: 600; cursor: pointer;">
                        <input type="checkbox" name="migrate_subscribers" value="1">
                        <span>{{ __('Migrate existing :count subscriber(s) to this new version immediately', ['count' => $plan->vendors_count]) }}</span>
                    </label>
                    <button type="submit" class="btn btn-primary" style="border-radius: 10px;">
                        <i class="fa-solid fa-floppy-disk"></i> {{ __('Save as New Version (v:next)', ['next' => ($plan->current_version_number ?? 1) + 1]) }}
                    </button>
                </div>
            </div>

            <!-- Category Grouped Feature Controls -->
            @foreach($featureCategories as $cat)
                <div class="builder-cat-card">
                    <div class="builder-cat-header">
                        <div style="display: flex; align-items: center; gap: 0.6rem;">
                            <i class="fa-solid {{ $cat->icon ?? 'fa-cube' }}" style="color: var(--primary);"></i>
                            <strong style="font-family: 'Outfit'; font-size: 1rem;">{{ $cat->localizedName() }}</strong>
                            <span class="badge badge-amber" style="font-size: 0.68rem;">{{ $cat->features->count() }}</span>
                        </div>
                        <span style="font-size: 0.75rem; color: var(--text-muted);">{{ $cat->localizedDescription() }}</span>
                    </div>
                    <div>
                        @foreach($cat->features as $feat)
                            @php
                                $pf = $pfMap->get($feat->code);
                                $isEnabled = $pf ? (bool) $pf->is_enabled : in_array($feat->code, $legacyFeatureCodes, true);
                                $limitVal = $pf ? $pf->limit_value : ($legacyQuotaLimits[$feat->code] ?? $feat->resolveDefaultScalar());
                                $usageQuota = $pf ? $pf->usage_quota : ($legacyQuotaLimits[$feat->code] ?? $feat->resolveDefaultScalar());
                                $isUnlimited = $pf ? (bool) $pf->is_unlimited : ((int) $limitVal === -1 || (int) $usageQuota === -1);
                                $configVal = $pf && is_array($pf->config_value) ? ($pf->config_value['value'] ?? json_encode($pf->config_value)) : $feat->resolveDefaultScalar();
                                $deps = $depMapByFeature->get($feat->code, collect());
                            @endphp
                            <div class="builder-feature-row" data-search="{{ strtolower($feat->code . ' ' . $feat->localizedName()) }}">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap;">
                                        <strong style="font-size: 0.88rem; color: var(--text-main);">{{ $feat->localizedName() }}</strong>
                                        <code style="font-size: 0.72rem; background: var(--input-bg); padding: 0.1rem 0.4rem; border-radius: 5px; color: var(--primary);">{{ $feat->code }}</code>
                                        <span class="badge badge-indigo" style="font-size: 0.64rem;">{{ $feat->feature_type }}</span>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem;">
                                        {{ $feat->localizedDescription() }}
                                    </div>
                                    @if($deps->isNotEmpty())
                                        <div style="font-size: 0.7rem; color: #f59e0b; margin-top: 0.2rem;">
                                            <i class="fa-solid fa-link"></i> {{ __('Requires') }}: {{ $deps->pluck('depends_on_feature_code')->implode(', ') }}
                                        </div>
                                    @endif
                                </div>

                                <!-- Enable / Disable Toggle -->
                                <div>
                                    <input type="hidden" name="plan_features[{{ $feat->code }}][is_enabled]" value="0">
                                    <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.82rem; font-weight: 600;">
                                        <input type="checkbox" class="builder-enable-checkbox" name="plan_features[{{ $feat->code }}][is_enabled]" value="1" {{ $isEnabled ? 'checked' : '' }}>
                                        <span>{{ __('Enabled') }}</span>
                                    </label>
                                </div>

                                <!-- Value / Limit / Quota / Enum Controls -->
                                <div>
                                    @if($feat->feature_type === 'LIMIT')
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <input type="number" min="-1" name="plan_features[{{ $feat->code }}][limit_value]" value="{{ $isUnlimited ? -1 : (int) $limitVal }}" class="form-input" style="max-width: 120px; padding: 0.35rem 0.6rem; font-size: 0.82rem;" placeholder="{{ __('Limit') }}">
                                            <label style="display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.76rem; cursor: pointer;">
                                                <input type="checkbox" name="plan_features[{{ $feat->code }}][is_unlimited]" value="1" {{ $isUnlimited ? 'checked' : '' }}>
                                                <span>{{ __('Unlimited') }}</span>
                                            </label>
                                        </div>
                                    @elseif(in_array($feat->feature_type, ['USAGE', 'METERED']))
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <input type="number" min="-1" name="plan_features[{{ $feat->code }}][usage_quota]" value="{{ $isUnlimited ? -1 : (int) $usageQuota }}" class="form-input" style="max-width: 120px; padding: 0.35rem 0.6rem; font-size: 0.82rem;" placeholder="{{ __('Quota') }}">
                                            <label style="display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.76rem; cursor: pointer;">
                                                <input type="checkbox" name="plan_features[{{ $feat->code }}][is_unlimited]" value="1" {{ $isUnlimited ? 'checked' : '' }}>
                                                <span>{{ __('Unlimited') }}</span>
                                            </label>
                                        </div>
                                    @elseif($feat->feature_type === 'ENUM' && !empty($feat->allowed_options))
                                        <select name="plan_features[{{ $feat->code }}][config_value]" class="form-select" style="padding: 0.35rem 0.6rem; font-size: 0.82rem;">
                                            @foreach($feat->allowed_options as $opt)
                                                <option value="{{ $opt }}" {{ (string) $configVal === (string) $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                            @endforeach
                                        </select>
                                    @elseif($feat->feature_type === 'CONFIGURATION')
                                        <input type="text" name="plan_features[{{ $feat->code }}][config_value]" value="{{ is_scalar($configVal) ? $configVal : json_encode($configVal) }}" class="form-input" style="padding: 0.35rem 0.6rem; font-size: 0.82rem;" placeholder="{{ __('Configuration value') }}">
                                    @else
                                        <span style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Boolean Toggle') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </form>
    @endforeach
</div>

<!-- TAB 3: PLAN COMPARISON MATRIX & VERSION HISTORY -->
<div id="tab-comparison" class="plan-tab-pane" style="display: none;">
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.5rem; margin-bottom: 1.75rem; overflow-x: auto;">
        <h3 style="font-family: 'Outfit'; font-size: 1.25rem; font-weight: 800; margin: 0 0 1rem;">
            <i class="fa-solid fa-table-columns" style="color: var(--primary);"></i> {{ __('Side-by-Side Plan Comparison Matrix') }}
        </h3>
        <table style="width: 100%; border-collapse: collapse; font-size: 0.83rem;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border-color); text-align: left;">
                    <th style="padding: 0.75rem;">{{ __('Feature / Category') }}</th>
                    @foreach($plans as $plan)
                        <th style="padding: 0.75rem; text-align: center;">
                            <div style="font-weight: 800; font-size: 0.95rem;">{{ $plan->localizedName() }}</div>
                            <div style="font-size: 0.72rem; color: var(--text-muted);">v{{ $plan->current_version_number ?? 1 }} • {{ $plan->formatted_price }}</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($featureCategories as $cat)
                    <tr style="background: var(--input-bg);">
                        <td colspan="{{ $plans->count() + 1 }}" style="padding: 0.6rem 0.75rem; font-weight: 800; color: var(--primary);">
                            <i class="fa-solid {{ $cat->icon ?? 'fa-folder' }}"></i> {{ $cat->localizedName() }}
                        </td>
                    </tr>
                    @foreach($cat->features as $feat)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.6rem 0.75rem;">
                                <strong>{{ $feat->localizedName() }}</strong>
                                <div style="font-size: 0.7rem; color: var(--text-muted);">{{ $feat->code }}</div>
                            </td>
                            @foreach($plans as $plan)
                                @php
                                    $pf = $plan->planFeatures ? $plan->planFeatures->firstWhere('feature_code', $feat->code) : null;
                                @endphp
                                <td style="padding: 0.6rem 0.75rem; text-align: center;">
                                    @if($pf && $pf->is_enabled)
                                        @if($pf->is_unlimited || $pf->limit_value === -1 || $pf->usage_quota === -1)
                                            <span class="badge badge-emerald">{{ __('Unlimited') }}</span>
                                        @elseif($pf->limit_value !== null)
                                            <strong>{{ number_format($pf->limit_value) }}</strong>
                                        @elseif($pf->usage_quota !== null)
                                            <strong>{{ number_format($pf->usage_quota) }}</strong>
                                        @else
                                            <span style="color: #10b981; font-weight: 800;"><i class="fa-solid fa-check"></i></span>
                                        @endif
                                    @else
                                        <span style="color: var(--text-muted); opacity: 0.5;">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Immutable Plan Version History -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.5rem;">
        <h3 style="font-family: 'Outfit'; font-size: 1.25rem; font-weight: 800; margin: 0 0 1rem;">
            <i class="fa-solid fa-clock-rotate-left" style="color: #6366f1;"></i> {{ __('Immutable Plan Versions & Subscriber Migration') }}
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
            @foreach($plans as $plan)
                <div style="border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <strong style="font-size: 1rem;">{{ $plan->name }}</strong>
                        <span class="badge badge-indigo">{{ __('Current') }}: v{{ $plan->current_version_number ?? 1 }}</span>
                    </div>
                    @forelse($plan->versions as $ver)
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-top: 1px solid var(--border-color); font-size: 0.8rem;">
                            <div>
                                <strong>v{{ $ver->version_number }}</strong>
                                <span style="color: var(--text-muted);">— {{ $ver->change_summary ?: __('Initial configuration') }}</span>
                            </div>
                            <form action="{{ route('superadmin.plans.versions.migrate', [$plan->id, $ver->id]) }}" method="POST" style="margin: 0;">
                                @csrf
                                <button type="submit" class="btn btn-secondary" style="font-size: 0.72rem; padding: 0.25rem 0.6rem;">
                                    {{ __('Migrate Subscribers') }}
                                </button>
                            </form>
                        </div>
                    @empty
                        <div style="font-size: 0.78rem; color: var(--text-muted);">{{ __('No version snapshots recorded yet.') }}</div>
                    @endforelse
                </div>
            @endforeach
        </div>
    </div>
</div>

<!-- TAB 4: CENTRALIZED FEATURE CATALOG -->
<div id="tab-catalog" class="plan-tab-pane" style="display: none;">
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.5rem; margin-bottom: 1.5rem;">
        <h3 style="font-family: 'Outfit'; font-size: 1.2rem; font-weight: 800; margin: 0 0 1rem;">
            <i class="fa-solid fa-plus-circle" style="color: var(--primary);"></i> {{ __('Add Feature to Catalog') }}
        </h3>
        <form action="{{ route('superadmin.features.store') }}" method="POST">
            @csrf
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 0.85rem; margin-bottom: 0.85rem;">
                <div>
                    <label class="form-label">{{ __('Feature Code (Immutable)') }} *</label>
                    <input type="text" name="code" class="form-input" required placeholder="e.g. marketing.sms_campaigns">
                </div>
                <div>
                    <label class="form-label">{{ __('Category') }} *</label>
                    <select name="feature_category_id" class="form-select" required>
                        @foreach($featureCategories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->localizedName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">{{ __('Feature Type') }} *</label>
                    <select name="feature_type" class="form-select" required>
                        <option value="BOOLEAN">BOOLEAN</option>
                        <option value="LIMIT">LIMIT</option>
                        <option value="USAGE">USAGE</option>
                        <option value="CONFIGURATION">CONFIGURATION</option>
                        <option value="ENUM">ENUM</option>
                        <option value="METERED">METERED</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">{{ __('Default Value') }}</label>
                    <input type="text" name="default_value" class="form-input" value="0">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.85rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">{{ __('Name (Armenian)') }} *</label>
                    <input type="text" name="name_translations[hy]" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">{{ __('Name (English)') }} *</label>
                    <input type="text" name="name_translations[en]" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">{{ __('Name (Russian)') }} *</label>
                    <input type="text" name="name_translations[ru]" class="form-input" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-size: 0.82rem;">
                <i class="fa-solid fa-plus"></i> {{ __('Create Feature') }}
            </button>
        </form>
    </div>

    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.5rem; overflow-x: auto;">
        <h3 style="font-family: 'Outfit'; font-size: 1.2rem; font-weight: 800; margin: 0 0 1rem;">
            {{ __('Feature Catalog') }} ({{ $catalogFeatures->count() }})
        </h3>
        <table style="width: 100%; border-collapse: collapse; font-size: 0.82rem;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border-color); text-align: left;">
                    <th style="padding: 0.65rem;">{{ __('Code') }}</th>
                    <th style="padding: 0.65rem;">{{ __('Name') }}</th>
                    <th style="padding: 0.65rem;">{{ __('Category') }}</th>
                    <th style="padding: 0.65rem;">{{ __('Type') }}</th>
                    <th style="padding: 0.65rem;">{{ __('Status') }}</th>
                    <th style="padding: 0.65rem; text-align: right;">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($catalogFeatures as $feat)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 0.65rem;"><code>{{ $feat->code }}</code></td>
                        <td style="padding: 0.65rem;">
                            <strong>{{ $feat->localizedName() }}</strong>
                            <div style="font-size: 0.72rem; color: var(--text-muted);">{{ $feat->localizedDescription() }}</div>
                        </td>
                        <td style="padding: 0.65rem;">{{ $feat->category?->localizedName() }}</td>
                        <td style="padding: 0.65rem;"><span class="badge badge-indigo">{{ $feat->feature_type }}</span></td>
                        <td style="padding: 0.65rem;">
                            @if($feat->is_archived)
                                <span class="badge badge-rose">{{ __('Archived') }}</span>
                            @elseif($feat->is_active)
                                <span class="badge badge-emerald">{{ __('Active') }}</span>
                            @else
                                <span class="badge badge-amber">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td style="padding: 0.65rem; text-align: right;">
                            <div style="display: inline-flex; gap: 0.4rem;">
                                @if(!$feat->is_archived)
                                    <form action="{{ route('superadmin.features.archive', $feat->id) }}" method="POST" style="margin: 0;">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary" style="font-size: 0.72rem; padding: 0.3rem 0.6rem;">
                                            {{ __('Archive') }}
                                        </button>
                                    </form>
                                @endif
                                <form action="{{ route('superadmin.features.destroy', $feat->id) }}" method="POST" style="margin: 0;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-secondary" style="font-size: 0.72rem; padding: 0.3rem 0.6rem; color: #ef4444;">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- TAB 5: FEATURE CATEGORIES -->
<div id="tab-categories" class="plan-tab-pane" style="display: none;">
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.5rem; margin-bottom: 1.5rem;">
        <h3 style="font-family: 'Outfit'; font-size: 1.2rem; font-weight: 800; margin: 0 0 1rem;">
            {{ __('Add Feature Category') }}
        </h3>
        <form action="{{ route('superadmin.features.categories.store') }}" method="POST">
            @csrf
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.85rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">{{ __('Category Code') }} *</label>
                    <input type="text" name="code" class="form-input" required placeholder="e.g. hardware_iot">
                </div>
                <div>
                    <label class="form-label">{{ __('Name (HY)') }} *</label>
                    <input type="text" name="name_translations[hy]" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">{{ __('Name (EN)') }} *</label>
                    <input type="text" name="name_translations[en]" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">{{ __('Name (RU)') }} *</label>
                    <input type="text" name="name_translations[ru]" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">{{ __('Icon') }}</label>
                    <input type="text" name="icon" class="form-input" value="fa-layer-group">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-size: 0.82rem;">
                <i class="fa-solid fa-plus"></i> {{ __('Save Category') }}
            </button>
        </form>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1rem;">
        @foreach($featureCategories as $cat)
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.15rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <strong style="font-size: 0.95rem;"><i class="fa-solid {{ $cat->icon }}" style="color: var(--primary);"></i> {{ $cat->localizedName() }}</strong>
                    <span class="badge badge-indigo">{{ $cat->features->count() }} {{ __('features') }}</span>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Code: <code>{{ $cat->code }}</code> • Sort: #{{ $cat->sort_order }}</div>
            </div>
        @endforeach
    </div>
</div>

<!-- TAB 6: FEATURE DEPENDENCIES -->
<div id="tab-dependencies" class="plan-tab-pane" style="display: none;">
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.5rem; margin-bottom: 1.5rem;">
        <h3 style="font-family: 'Outfit'; font-size: 1.2rem; font-weight: 800; margin: 0 0 1rem;">
            <i class="fa-solid fa-diagram-project" style="color: var(--primary);"></i> {{ __('Add Feature Dependency Rule') }}
        </h3>
        <form action="{{ route('superadmin.features.dependencies.store') }}" method="POST" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
            @csrf
            <div style="flex: 1; min-width: 220px;">
                <label class="form-label">{{ __('Dependent Feature') }}</label>
                <select name="feature_code" class="form-select" required>
                    @foreach($catalogFeatures as $f)
                        <option value="{{ $f->code }}">{{ $f->localizedName() }} ({{ $f->code }})</option>
                    @endforeach
                </select>
            </div>
            <div style="flex: 1; min-width: 220px;">
                <label class="form-label">{{ __('Requires Feature (Parent)') }}</label>
                <select name="depends_on_feature_code" class="form-select" required>
                    @foreach($catalogFeatures as $f)
                        <option value="{{ $f->code }}">{{ $f->localizedName() }} ({{ $f->code }})</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="border-radius: 10px;">
                <i class="fa-solid fa-link"></i> {{ __('Add Dependency') }}
            </button>
        </form>
    </div>

    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.5rem;">
        <h3 style="font-family: 'Outfit'; font-size: 1.15rem; font-weight: 800; margin: 0 0 1rem;">
            {{ __('Active Dependency Rules') }} ({{ $featureDependencies->count() }})
        </h3>
        @forelse($featureDependencies as $dep)
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0; border-bottom: 1px solid var(--border-color); font-size: 0.85rem;">
                <div>
                    <code>{{ $dep->feature_code }}</code>
                    <span style="margin: 0 0.5rem; color: var(--text-muted);">{{ __('requires') }}</span>
                    <code style="color: var(--primary);">{{ $dep->depends_on_feature_code }}</code>
                </div>
                <form action="{{ route('superadmin.features.dependencies.destroy', $dep->id) }}" method="POST" style="margin: 0;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-secondary" style="font-size: 0.72rem; padding: 0.25rem 0.6rem; color: #ef4444;">
                        <i class="fa-solid fa-unlink"></i> {{ __('Remove') }}
                    </button>
                </form>
            </div>
        @empty
            <div style="font-size: 0.82rem; color: var(--text-muted);">{{ __('No dependencies configured.') }}</div>
        @endforelse
    </div>
</div>

<!-- TAB 7: ADD-ONS MANAGEMENT -->
<div id="tab-addons" class="plan-tab-pane" style="display: none;">
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.5rem; margin-bottom: 1.5rem;">
        <h3 style="font-family: 'Outfit'; font-size: 1.2rem; font-weight: 800; margin: 0 0 1rem;">
            <i class="fa-solid fa-puzzle-piece" style="color: var(--primary);"></i> {{ __('Create Subscription Add-on') }}
        </h3>
        <form action="{{ route('superadmin.addons.store') }}" method="POST">
            @csrf
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.85rem; margin-bottom: 0.85rem;">
                <div>
                    <label class="form-label">{{ __('Add-on Code') }} *</label>
                    <input type="text" name="code" class="form-input" required placeholder="e.g. addon.pos_module">
                </div>
                <div>
                    <label class="form-label">{{ __('Add-on Type') }} *</label>
                    <select name="addon_type" class="form-select" required>
                        <option value="LIMIT">LIMIT (+Limit Increment)</option>
                        <option value="USAGE">USAGE (+Quota Increment)</option>
                        <option value="FEATURE">FEATURE (Boolean Unlock)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">{{ __('Target Feature Code') }} *</label>
                    <input type="text" name="feature_code" class="form-input" required placeholder="e.g. staff.users.limit">
                </div>
                <div>
                    <label class="form-label">{{ __('Increment Value') }}</label>
                    <input type="number" name="value_increment" value="1" min="1" class="form-input">
                </div>
                <div>
                    <label class="form-label">{{ __('Monthly Price (AMD)') }} *</label>
                    <input type="number" name="monthly_price" value="0" min="0" step="100" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">{{ __('Billing Period') }} *</label>
                    <select name="billing_period" class="form-select" required>
                        <option value="monthly">{{ __('Monthly') }}</option>
                        <option value="yearly">{{ __('Yearly') }}</option>
                        <option value="one_time">{{ __('One-Time') }}</option>
                    </select>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.85rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">{{ __('Name (HY)') }} *</label>
                    <input type="text" name="name_translations[hy]" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">{{ __('Name (EN)') }} *</label>
                    <input type="text" name="name_translations[en]" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">{{ __('Name (RU)') }} *</label>
                    <input type="text" name="name_translations[ru]" class="form-input" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-size: 0.82rem;">
                <i class="fa-solid fa-plus"></i> {{ __('Create Add-on') }}
            </button>
        </form>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem;">
        @foreach($subscriptionAddons as $addon)
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                    <strong>{{ $addon->localizedName() }}</strong>
                    <span class="badge badge-indigo">{{ $addon->addon_type }}</span>
                </div>
                <p style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.75rem;">{{ $addon->localizedDescription() }}</p>
                <div style="font-size: 0.78rem; color: var(--text-main);">
                    <div><strong>{{ __('Feature') }}:</strong> <code>{{ $addon->feature_code }}</code> (+{{ $addon->value_increment }})</div>
                    <div><strong>{{ __('Monthly Price') }}:</strong> {{ number_format((float)$addon->monthly_price, 0) }} {{ $addon->currency }}</div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- TAB 8: SUBSCRIPTION SETTINGS -->
<div id="tab-settings" class="plan-tab-pane" style="display: none;">
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.5rem; max-width: 720px;">
        <h3 style="font-family: 'Outfit'; font-size: 1.25rem; font-weight: 800; margin: 0 0 1rem;">
            <i class="fa-solid fa-gear" style="color: var(--primary);"></i> {{ __('Global Subscription & Entitlement Settings') }}
        </h3>
        <form action="{{ route('superadmin.subscriptions.settings.update') }}" method="POST">
            @csrf
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">{{ __('Default Trial Duration (Days)') }}</label>
                    <input type="number" name="default_trial_days" value="{{ $subscriptionSettings['default_trial_days'] }}" min="0" max="365" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">{{ __('Payment Grace Period (Days)') }}</label>
                    <input type="number" name="grace_period_days" value="{{ $subscriptionSettings['grace_period_days'] }}" min="0" max="90" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">{{ __('Quota Warning Threshold (%)') }}</label>
                    <input type="number" name="warning_threshold_percent" value="{{ $subscriptionSettings['warning_threshold_percent'] }}" min="50" max="99" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">{{ __('Downgrade Behavior') }}</label>
                    <select name="downgrade_behavior" class="form-select">
                        <option value="preserve_data_restrict_new" {{ $subscriptionSettings['downgrade_behavior'] === 'preserve_data_restrict_new' ? 'selected' : '' }}>
                            {{ __('Preserve existing data, restrict new creation above limit') }}
                        </option>
                        <option value="strict_block" {{ $subscriptionSettings['downgrade_behavior'] === 'strict_block' ? 'selected' : '' }}>
                            {{ __('Strict block until usage is reduced') }}
                        </option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="border-radius: 10px;">
                <i class="fa-solid fa-floppy-disk"></i> {{ __('Save Subscription Settings') }}
            </button>
        </form>
    </div>
</div>

<!-- Duplicate Plan Modal -->
<div id="duplicatePlanModal" class="modern-modal-overlay" style="display: none;">
    <div class="modern-modal-box" style="max-width: 460px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="font-family: 'Outfit'; font-weight: 800; font-size: 1.2rem; margin: 0;">{{ __('Duplicate Plan') }}</h3>
            <button onclick="document.getElementById('duplicatePlanModal').style.display='none'" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1.2rem;">✕</button>
        </div>
        <form id="duplicatePlanForm" method="POST">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label class="form-label">{{ __('New Plan Name') }} *</label>
                <input type="text" id="duplicate_new_name" name="new_name" class="form-input" required>
            </div>
            <div style="margin-bottom: 1.25rem;">
                <label class="form-label">{{ __('New Plan Slug (Optional)') }}</label>
                <input type="text" name="new_slug" class="form-input" placeholder="e.g. pro-plus">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('duplicatePlanModal').style.display='none'">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Duplicate') }}</button>
            </div>
        </form>
    </div>
</div>

<!-- Modern Create Plan Modal -->
<div id="createPlanModal" class="modern-modal-overlay" style="display: none;">
    <div class="modern-modal-box">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 0.85rem; border-bottom: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                    <i class="fa-solid fa-box-archive"></i>
                </div>
                <h3 style="font-family: 'Outfit'; font-weight: 800; font-size: 1.25rem; color: var(--text-main); margin: 0;">
                    Ավելացնել Նոր Փաթեթ
                </h3>
            </div>
            <button onclick="document.getElementById('createPlanModal').style.display='none'" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1.25rem;">✕</button>
        </div>

        <form action="{{ route('superadmin.plans.store') }}" method="POST">
            @csrf
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Փաթեթի Անվանում *</label>
                    <input type="text" name="name" class="form-input" required placeholder="օր․ Ultra Business">
                </div>
                <div>
                    <label class="form-label">Գին (֏ / ամիս) *</label>
                    <input type="number" name="price" step="100" min="0" class="form-input" required placeholder="0 = Պայմանագրային">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Վճարման պարբերություն</label>
                    <select name="billing_interval" class="form-select">
                        <option value="monthly">Ամսական (Monthly)</option>
                        <option value="yearly">Տարեկան (Yearly)</option>
                        <option value="custom">Անհատական (Custom)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Տևողություն (օրեր)</label>
                    <input type="number" name="duration_days" value="30" min="1" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Փորձնական (օրեր)</label>
                    <input type="number" name="trial_days" value="14" min="0" class="form-input" required>
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label class="form-label">Նկարագրություն</label>
                <input type="text" name="description" class="form-input" placeholder="Փաթեթի համառոտ նկարագրություն...">
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label class="form-label">Ֆունկցիոնալության ցանկ (յուրաքանչյուրը նոր տողում)</label>
                <textarea name="features" rows="5" class="form-textarea" placeholder="QR մենյու&#10;Օնլայն պատվերներ&#10;Սեղանի համարով պատվիրում"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('createPlanModal').style.display='none'">Չեղարկել</button>
                <button type="submit" class="btn btn-primary">Պահպանել Փաթեթը</button>
            </div>
        </form>
    </div>
</div>

<!-- Modern Edit Plan Modal -->
<div id="editPlanModal" class="modern-modal-overlay" style="display: none;">
    <div class="modern-modal-box">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 0.85rem; border-bottom: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(99, 102, 241, 0.15); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                    <i class="fa-solid fa-pen-to-square"></i>
                </div>
                <h3 style="font-family: 'Outfit'; font-weight: 800; font-size: 1.25rem; color: var(--text-main); margin: 0;">
                    Խմբագրել Փաթեթը
                </h3>
            </div>
            <button onclick="document.getElementById('editPlanModal').style.display='none'" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1.25rem;">✕</button>
        </div>

        <form id="editPlanForm" method="POST">
            @csrf
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Փաթեթի Անվանում *</label>
                    <input type="text" id="edit_name" name="name" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Գին (֏ / ամիս) *</label>
                    <input type="number" id="edit_price" name="price" step="100" min="0" class="form-input" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Վճարման պարբերություն</label>
                    <select id="edit_billing_interval" name="billing_interval" class="form-select">
                        <option value="monthly">Ամսական (Monthly)</option>
                        <option value="yearly">Տարեկան (Yearly)</option>
                        <option value="custom">Անհատական (Custom)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Տևողություն (օրեր)</label>
                    <input type="number" id="edit_duration_days" name="duration_days" min="1" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Փորձնական (օրեր)</label>
                    <input type="number" id="edit_trial_days" name="trial_days" min="0" class="form-input" required>
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label class="form-label">Նկարագրություն</label>
                <input type="text" id="edit_description" name="description" class="form-input">
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label class="form-label">Ֆունկցիոնալության ցանկ (յուրաքանչյուրը նոր տողում)</label>
                <textarea id="edit_features" name="features" rows="5" class="form-textarea"></textarea>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-main); cursor: pointer;">
                    <input type="checkbox" id="edit_is_active" name="is_active" value="1">
                    <span>Ակտիվ փաթեթ (հասանելի է գրանցումների համար)</span>
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('editPlanModal').style.display='none'">Չեղարկել</button>
                <button type="submit" class="btn btn-primary">Թարմացնել Փաթեթը</button>
            </div>
        </form>
    </div>
</div>

<script>
    function switchPlanTab(tabKey, btnEl) {
        document.querySelectorAll('.plan-tab-pane').forEach(el => el.style.display = 'none');
        const target = document.getElementById('tab-' + tabKey);
        if (target) target.style.display = 'block';

        if (btnEl) {
            document.querySelectorAll('#planSystemTabs .sub-nav-btn').forEach(b => b.classList.remove('active'));
            btnEl.classList.add('active');
        }
    }

    function selectBuilderPlan(planId, btnEl) {
        document.querySelectorAll('.builder-plan-form').forEach(f => f.style.display = 'none');
        const targetForm = document.getElementById('builder-form-' + planId);
        if (targetForm) targetForm.style.display = 'block';

        document.querySelectorAll('.builder-plan-selector').forEach(b => {
            b.classList.remove('btn-primary');
            b.classList.add('btn-secondary');
        });
        if (btnEl) {
            btnEl.classList.remove('btn-secondary');
            btnEl.classList.add('btn-primary');
        }
    }

    function openPlanInBuilder(planId) {
        const builderTabBtn = document.querySelectorAll('#planSystemTabs .sub-nav-btn')[1];
        switchPlanTab('builder', builderTabBtn);
        const selectorBtn = document.querySelector('.builder-plan-selector[data-plan-id="' + planId + '"]');
        selectBuilderPlan(planId, selectorBtn);
    }

    function filterBuilderFeatures(query) {
        const q = (query || '').toLowerCase().trim();
        document.querySelectorAll('.builder-feature-row').forEach(row => {
            const hay = row.getAttribute('data-search') || '';
            row.style.display = (!q || hay.includes(q)) ? 'grid' : 'none';
        });
    }

    function bulkToggleBuilderFeatures(enable) {
        document.querySelectorAll('.builder-plan-form').forEach(form => {
            if (form.style.display !== 'none') {
                form.querySelectorAll('.builder-feature-row').forEach(row => {
                    if (row.style.display !== 'none') {
                        const cb = row.querySelector('.builder-enable-checkbox');
                        if (cb) cb.checked = enable;
                    }
                });
            }
        });
    }

    function openDuplicatePlanModal(planId, planName) {
        document.getElementById('duplicatePlanForm').action = '/superadmin/plans/' + planId + '/duplicate';
        document.getElementById('duplicate_new_name').value = planName + ' (Copy)';
        document.getElementById('duplicatePlanModal').style.display = 'flex';
    }

    function openEditModal(plan) {
        document.getElementById('editPlanForm').action = '/superadmin/plans/' + plan.id;
        document.getElementById('edit_name').value = plan.name;
        document.getElementById('edit_price').value = plan.price;
        document.getElementById('edit_billing_interval').value = plan.billing_interval || 'monthly';
        document.getElementById('edit_duration_days').value = plan.duration_days || 30;
        document.getElementById('edit_trial_days').value = plan.trial_days || 14;
        document.getElementById('edit_description').value = plan.description || '';
        document.getElementById('edit_features').value = (plan.features || []).join('\n');
        document.getElementById('edit_is_active').checked = plan.is_active;

        document.getElementById('editPlanModal').style.display = 'flex';
    }
</script>
@endsection
