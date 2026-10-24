@extends('layouts.app')

@section('title', __('Usage & Quota Reporting') . ' - ' . $vendor->name)

@section('content')
<!-- Page Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
            <a href="{{ route('superadmin.subscriptions.index') }}" style="color: var(--text-muted); text-decoration: none; font-size: 0.85rem; font-weight: 600;">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Subscriptions') }}
            </a>
            <span style="color: var(--text-muted);">•</span>
            <span class="badge badge-indigo">{{ $vendor->name }}</span>
        </div>
        <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
            {{ __('Tenant Usage & Quota Reporting') }}
        </h1>
    </div>
</div>

<!-- Quota Meters Grid -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: var(--shadow-card);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0;">
                {{ __('Real-Time Usage Meters') }}
            </h3>
            <span style="font-size: 0.8rem; color: var(--text-muted);">
                {{ __('Monitored across resources and period billing intervals') }}
            </span>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr)); gap: 1.25rem;">
        @foreach($quotaStatuses as $key => $status)
            @php
                $isUnlimited = $status->isUnlimited;
                $percent = $isUnlimited ? 0 : min(100, (int) round(($status->currentUsage / max(1, $status->limit)) * 100));
                $barColor = $percent >= 100 ? '#ef4444' : ($percent >= 80 ? '#f59e0b' : '#10b981');
            @endphp
            <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                        <div>
                            <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);">
                                {{ $status->displayName ?? $key }}
                            </div>
                            <div style="font-size: 0.72rem; color: var(--text-muted); font-family: monospace;">
                                {{ $key }}
                            </div>
                        </div>
                        <div>
                            @if($isUnlimited)
                                <span class="badge badge-emerald">∞ {{ __('Unlimited') }}</span>
                            @elseif($status->isExceeded)
                                <span class="badge badge-rose">{{ __('Exceeded') }}</span>
                            @else
                                <span style="font-size: 0.85rem; font-weight: 700; color: {{ $barColor }};">{{ $percent }}%</span>
                            @endif
                        </div>
                    </div>

                    <div style="width: 100%; height: 8px; background: var(--border-color); border-radius: 9999px; overflow: hidden; margin: 0.75rem 0;">
                        <div style="height: 100%; width: {{ $isUnlimited ? 15 : max(3, $percent) }}%; background: {{ $barColor }}; border-radius: 9999px;"></div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">
                        <span>{{ __('Usage:') }} <strong style="color: var(--text-main);">{{ number_format($status->currentUsage) }}</strong></span>
                        <span>{{ __('Limit:') }} <strong style="color: var(--text-main);">{{ $isUnlimited ? __('Unlimited') : number_format($status->limit) }}</strong> {{ $status->unit }}</span>
                    </div>
                </div>

                <div style="display: flex; gap: 0.5rem; border-top: 1px solid var(--border-color); padding-top: 0.75rem;">
                    <!-- Reset Meter Form -->
                    <form action="{{ route('superadmin.subscriptions.usage_meters.reset', [$vendor->id, $key]) }}" method="POST" onsubmit="return confirm('{{ __('Reset usage counter for :key?', ['key' => $key]) }}');" style="margin: 0;">
                        @csrf
                        <button type="submit" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.35rem 0.65rem; border-radius: 8px;">
                            <i class="fa-solid fa-rotate-left"></i> {{ __('Reset') }}
                        </button>
                    </form>

                    <!-- Quota Override Form (Simple prompt) -->
                    <button type="button" onclick="openQuotaOverrideModal('{{ $key }}', '{{ $status->displayName }}', {{ $status->limit }})" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.35rem 0.65rem; border-radius: 8px; flex: 1; justify-content: center;">
                        <i class="fa-solid fa-sliders"></i> {{ __('Set Custom Limit') }}
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- Active Overrides Section -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Active Quota Overrides -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-card);">
        <h4 style="font-family: 'Outfit'; font-weight: 800; font-size: 1.1rem; color: var(--text-main); margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-sliders" style="color: var(--primary);"></i>
            <span>{{ __('Active Quota Overrides') }} ({{ $activeQuotaOverrides->count() }})</span>
        </h4>

        @forelse($activeQuotaOverrides as $qo)
            <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-weight: 700; font-size: 0.88rem; color: var(--text-main);">{{ $qo->quota_key }}</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem;">
                        {{ __('Custom Limit:') }} <strong>{{ $qo->quota_limit == -1 ? __('Unlimited') : $qo->quota_limit }}</strong> • {{ $qo->reason }}
                    </div>
                </div>
                <form action="{{ route('superadmin.subscriptions.quota_overrides.destroy', [$vendor->id, $qo->quota_key]) }}" method="POST" style="margin: 0;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-secondary" style="color: #ef4444; padding: 0.35rem 0.65rem; font-size: 0.75rem; border-radius: 8px;" title="{{ __('Remove') }}">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </form>
            </div>
        @empty
            <div style="text-align: center; padding: 1.5rem; color: var(--text-muted); font-size: 0.85rem;">
                {{ __('No custom quota overrides active for this partner.') }}
            </div>
        @endforelse
    </div>

    <!-- Active Feature Overrides -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-card);">
        <h4 style="font-family: 'Outfit'; font-weight: 800; font-size: 1.1rem; color: var(--text-main); margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-unlock-keyhole" style="color: #10b981;"></i>
            <span>{{ __('Active Feature Overrides') }} ({{ $activeFeatureOverrides->count() }})</span>
        </h4>

        @forelse($activeFeatureOverrides as $fo)
            <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-weight: 700; font-size: 0.88rem; color: var(--text-main);">
                        {{ $fo->feature_key }}
                        <span class="badge {{ $fo->is_enabled ? 'badge-emerald' : 'badge-rose' }}" style="font-size: 0.7rem; margin-left: 0.35rem;">
                            {{ $fo->is_enabled ? __('Granted') : __('Denied') }}
                        </span>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem;">
                        {{ $fo->reason }} @if($fo->expires_at) • {{ __('Expires:') }} {{ $fo->expires_at->format('d.m.Y') }} @endif
                    </div>
                </div>
                <form action="{{ route('superadmin.subscriptions.overrides.destroy', [$vendor->id, $fo->feature_key]) }}" method="POST" style="margin: 0;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-secondary" style="color: #ef4444; padding: 0.35rem 0.65rem; font-size: 0.75rem; border-radius: 8px;" title="{{ __('Remove') }}">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </form>
            </div>
        @empty
            <div style="text-align: center; padding: 1.5rem; color: var(--text-muted); font-size: 0.85rem;">
                {{ __('No feature overrides active for this partner.') }}
            </div>
        @endforelse
    </div>
</div>

<!-- Modal for setting Quota Override -->
<div id="quotaOverrideModal" class="modern-modal-overlay" style="display: none;">
    <div class="modern-modal-box">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.85rem; border-bottom: 1px solid var(--border-color);">
            <h3 style="font-family: 'Outfit'; font-weight: 800; font-size: 1.2rem; margin: 0; color: var(--text-main);">
                {{ __('Set Custom Quota Override') }}
            </h3>
            <button onclick="document.getElementById('quotaOverrideModal').style.display='none'" style="background:none; border:none; color:var(--text-muted); font-size:1.25rem; cursor:pointer;">✕</button>
        </div>

        <form action="{{ route('superadmin.subscriptions.quota_overrides.store', $vendor->id) }}" method="POST">
            @csrf
            <input type="hidden" id="modal_quota_key" name="quota_key">

            <div style="margin-bottom: 1rem;">
                <label class="form-label">{{ __('Quota Resource') }}</label>
                <input type="text" id="modal_quota_name" class="form-input" readonly style="background: var(--bg-body);">
            </div>

            <div style="margin-bottom: 1rem;">
                <label class="form-label">{{ __('Custom Limit (-1 = Unlimited) *') }}</label>
                <input type="number" id="modal_quota_limit" name="quota_limit" class="form-input" required min="-1">
            </div>

            <div style="margin-bottom: 1rem;">
                <label class="form-label">{{ __('Administrative Reason *') }}</label>
                <input type="text" name="reason" class="form-input" required placeholder="{{ __('e.g., Enterprise trial extension approval') }}">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('quotaOverrideModal').style.display='none'">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Save Override') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openQuotaOverrideModal(key, name, currentLimit) {
        document.getElementById('modal_quota_key').value = key;
        document.getElementById('modal_quota_name').value = (name || key) + ' (' + key + ')';
        document.getElementById('modal_quota_limit').value = currentLimit;
        document.getElementById('quotaOverrideModal').style.display = 'flex';
    }
</script>
@endsection
