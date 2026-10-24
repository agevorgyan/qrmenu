@extends('layouts.app')

@section('title', __('Subscription Lifecycle History') . ' - ' . $vendor->name)

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
            {{ __('Subscription Lifecycle History') }}
        </h1>
    </div>
</div>

<!-- Vendor & Subscription Overview Card -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; margin-bottom: 1.75rem; box-shadow: var(--shadow-card);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Partner') }}</div>
            <div style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit';">
                {{ $vendor->name }} <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 400;">({{ $vendor->email }})</span>
            </div>
        </div>
        <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <div>
                <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Current Plan') }}</div>
                <span class="badge badge-amber" style="font-weight: 700; margin-top: 0.2rem;">
                    {{ $subscription->plan?->name ?? 'Standard' }}
                </span>
            </div>
            <div>
                <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Current Status') }}</div>
                @php $st = $subscription->status->value; @endphp
                @if($st === 'active')
                    <span class="badge badge-emerald">🟢 {{ __('Active') }}</span>
                @elseif($st === 'suspended')
                    <span class="badge badge-rose">⛔ {{ __('Suspended') }}</span>
                @elseif($st === 'trialing')
                    <span class="badge badge-amber">⏳ {{ __('Trial') }}</span>
                @else
                    <span class="badge badge-rose">🔴 {{ ucfirst($st) }}</span>
                @endif
            </div>
            <div>
                <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Billing Period') }}</div>
                <div style="font-size: 0.82rem; color: var(--text-main); font-weight: 600; margin-top: 0.2rem;">
                    {{ $subscription->current_period_start ? $subscription->current_period_start->format('d.m.Y') : '—' }} - {{ $subscription->current_period_end ? $subscription->current_period_end->format('d.m.Y') : __('Unlimited') }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lifecycle Events Audit Trail Table -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 0; overflow: hidden; box-shadow: var(--shadow-card);">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-timeline" style="color: var(--primary);"></i>
            <span>{{ __('Audit Trail of Lifecycle Events') }}</span>
        </h3>
        <span style="font-size: 0.78rem; color: var(--text-muted);">{{ __('Total Events') }}: {{ $events->total() }}</span>
    </div>

    @if($events->count() > 0)
        <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="padding: 1rem 1.25rem;">{{ __('Timestamp') }}</th>
                        <th>{{ __('Event') }}</th>
                        <th>{{ __('State Transition') }}</th>
                        <th>{{ __('Actor') }}</th>
                        <th>{{ __('Reason / Note') }}</th>
                        <th>{{ __('Metadata') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($events as $ev)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem 1.25rem; font-size: 0.84rem; color: var(--text-muted); white-space: nowrap;">
                                {{ $ev->created_at ? $ev->created_at->format('d.m.Y H:i:s') : '—' }}
                            </td>
                            <td>
                                <span class="badge badge-purple" style="font-weight: 700; text-transform: uppercase; font-size: 0.75rem;">
                                    {{ $ev->event }}
                                </span>
                            </td>
                            <td>
                                @if($ev->from_status || $ev->to_status)
                                    <span style="font-size: 0.8rem; font-weight: 600;">
                                        <span class="badge {{ $ev->from_status ? 'badge-rose' : 'badge-gray' }}">{{ $ev->from_status ?? 'init' }}</span>
                                        <i class="fa-solid fa-arrow-right" style="font-size: 0.65rem; color: var(--text-muted); margin: 0 0.25rem;"></i>
                                        <span class="badge {{ in_array($ev->to_status, ['active', 'trialing']) ? 'badge-emerald' : 'badge-amber' }}">{{ $ev->to_status ?? 'none' }}</span>
                                    </span>
                                @else
                                    <span style="color: var(--text-muted); font-size: 0.8rem;">—</span>
                                @endif
                            </td>
                            <td style="font-size: 0.82rem; color: var(--text-main);">
                                {{ $ev->actor_type ? class_basename($ev->actor_type) . ' #' . $ev->actor_id : __('System') }}
                            </td>
                            <td style="font-size: 0.84rem; color: var(--text-main); max-width: 300px; word-break: break-word;">
                                {{ $ev->reason ?: '—' }}
                            </td>
                            <td style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">
                                @if(!empty($ev->metadata))
                                    {{ json_encode($ev->metadata, JSON_UNESCAPED_UNICODE) }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($events->hasPages())
            <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color);">
                {{ $events->links() }}
            </div>
        @endif
    @else
        <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
            <div style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">{{ __('No lifecycle events recorded yet') }}</div>
            <p style="font-size: 0.85rem; margin: 0;">{{ __('Events will be logged automatically as subscriptions transition states.') }}</p>
        </div>
    @endif
</div>
@endsection
