@extends('layouts.app')

@section('title', __('Subscription Audit Logs - SuperAdmin'))

@section('content')
<!-- Page Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
            <a href="{{ route('superadmin.subscriptions.index') }}" style="color: var(--text-muted); text-decoration: none; font-size: 0.85rem; font-weight: 600;">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Back to Subscriptions') }}
            </a>
            <span style="color: var(--text-muted);">•</span>
            <span class="badge badge-amber">{{ __('Administrative Logs') }}</span>
        </div>
        <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0;">
            {{ __('Subscription & Administrative Event Logs') }}
        </h1>
    </div>
</div>

<!-- Search Panel -->
<div class="filter-panel" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.25rem; margin-bottom: 1.5rem;">
    <form method="GET" action="{{ route('superadmin.subscriptions.audit_logs') }}" style="display: flex; gap: 1rem; align-items: center; margin: 0;">
        <div style="flex: 1; position: relative;">
            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem;"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by action description or partner name...') }}" class="form-input" style="padding-left: 2.5rem; border-radius: 12px; width: 100%;">
        </div>
        <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-size: 0.85rem; padding: 0.6rem 1.25rem;">
            {{ __('Search') }}
        </button>
    </form>
</div>

<!-- Audit Logs Table -->
<div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 0; overflow: hidden; box-shadow: var(--shadow-card);">
    @if($logs->count() > 0)
        <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="padding: 1rem 1.25rem;">{{ __('Timestamp') }}</th>
                        <th>{{ __('Event') }}</th>
                        <th>{{ __('Action Description') }}</th>
                        <th>{{ __('Target Partner') }}</th>
                        <th>{{ __('Actor') }}</th>
                        <th>{{ __('Metadata') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem 1.25rem; font-size: 0.82rem; color: var(--text-muted); white-space: nowrap;">
                                {{ $log->created_at ? $log->created_at->format('d.m.Y H:i:s') : '—' }}
                            </td>
                            <td>
                                <span class="badge badge-purple" style="font-weight: 700; font-size: 0.72rem; text-transform: uppercase;">
                                    {{ $log->event }}
                                </span>
                            </td>
                            <td style="font-size: 0.88rem; color: var(--text-main); font-weight: 600; max-width: 340px; word-break: break-word;">
                                {{ $log->action }}
                            </td>
                            <td>
                                @if($log->vendor)
                                    <div style="font-weight: 700; color: var(--text-main); font-size: 0.85rem;">{{ $log->vendor->name }}</div>
                                @else
                                    <span style="color: var(--text-muted); font-size: 0.8rem;">—</span>
                                @endif
                            </td>
                            <td style="font-size: 0.82rem; color: var(--text-muted);">
                                {{ $log->actor_name ?: ($log->user?->name ?? __('System')) }}
                            </td>
                            <td style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                @if(!empty($log->metadata))
                                    {{ json_encode($log->metadata, JSON_UNESCAPED_UNICODE) }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color);">
                {{ $logs->links() }}
            </div>
        @endif
    @else
        <div style="text-align: center; padding: 3.5rem 1rem; color: var(--text-muted);">
            <div style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">
                {{ __('No subscription administrative audit logs found.') }}
            </div>
        </div>
    @endif
</div>
@endsection
