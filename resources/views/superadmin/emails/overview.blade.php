@extends('layouts.app')

@section('title', 'Email Management Overview - QRMenu SuperAdmin')

@section('styles')
<style>
    .superadmin-hero {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.12) 0%, rgba(99, 102, 241, 0.08) 100%);
        border: 1px solid var(--border-color);
        border-radius: 24px;
        padding: 1.75rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1.25rem;
        box-shadow: var(--shadow-card);
    }
    .chart-bar-container {
        display: flex;
        align-items: flex-end;
        gap: 0.5rem;
        height: 180px;
        padding-top: 1.5rem;
        border-bottom: 1px solid var(--border-color);
    }
    .chart-col {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        height: 100%;
        justify-content: flex-end;
        min-width: 24px;
    }
    .chart-bar-success {
        width: 100%;
        max-width: 28px;
        background: linear-gradient(180deg, #10b981 0%, #059669 100%);
        border-radius: 6px 6px 0 0;
        transition: height 0.4s ease;
    }
    .chart-bar-failed {
        width: 100%;
        max-width: 28px;
        background: linear-gradient(180deg, #ef4444 0%, #dc2626 100%);
        border-radius: 6px 6px 0 0;
        transition: height 0.4s ease;
    }
</style>
@endsection

@section('content')
<div x-data="{ inspectModal: false, activeLog: null }">

    @include('superadmin.emails._nav', [
        'title' => __('Email Management & Delivery Monitoring'),
        'subtitle' => __('Real-time dispatch metrics, queue health, deliverability status, and dynamic routing.')
    ])

    <!-- Flash Messages -->
    @if(session('success'))
        <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981; padding: 0.9rem 1.25rem; border-radius: 14px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.65rem; font-size: 0.88rem; font-weight: 600;">
            <i class="fa-solid fa-circle-check"></i> {{ __(session('success')) }}
        </div>
    @endif
    @if(session('error'))
        <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; padding: 0.9rem 1.25rem; border-radius: 14px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.65rem; font-size: 0.88rem; font-weight: 600;">
            <i class="fa-solid fa-circle-exclamation"></i> {{ __(session('error')) }}
        </div>
    @endif

    <!-- Time Range & Filter Bar -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1rem 1.25rem; margin-bottom: 1.75rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-right: 0.35rem;">
                <i class="fa-solid fa-filter"></i> {{ __('Filter Period:') }}
            </span>
            @foreach(['today' => 'Today', '7d' => 'Last 7 Days', '30d' => 'Last 30 Days'] as $rKey => $rLabel)
                <a href="{{ route('superadmin.emails.overview', array_merge($filters, ['range' => $rKey])) }}"
                   style="padding: 0.4rem 0.85rem; border-radius: 10px; font-size: 0.78rem; font-weight: 700; text-decoration: none; transition: all 0.15s ease; {{ ($filters['range'] ?? '30d') === $rKey ? 'background: var(--primary); color: #fff;' : 'background: var(--input-bg); color: var(--text-muted); border: 1px solid var(--border-color);' }}">
                    {{ __($rLabel) }}
                </a>
            @endforeach
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.8rem; color: var(--text-muted);">
            <span>{{ __('Default Connection:') }} <strong style="color: var(--text-main);">{{ $metrics['default_connection_name'] }}</strong></span>
            <span class="badge {{ $metrics['default_connection_status'] === 'success' ? 'badge-emerald' : 'badge-amber' }}">
                <i class="fa-solid fa-signal"></i> {{ ucfirst($metrics['default_connection_status']) }}
            </span>
        </div>
    </div>

    <!-- 4 Primary KPI Tiles -->
    <div class="grid-4" style="gap: 1.25rem; margin-bottom: 1.75rem;">
        <!-- Total Emails -->
        <div class="stat-kpi-card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="color: var(--text-muted); font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                        {{ __('Total Emails Processed') }}
                    </div>
                    <div style="font-family: 'Outfit', sans-serif; font-size: 2rem; font-weight: 800; color: var(--text-main); margin-top: 0.35rem;">
                        {{ number_format($metrics['total_emails']) }}
                    </div>
                </div>
                <div class="kpi-icon-badge" style="background: rgba(59, 130, 246, 0.12); color: #3b82f6;">
                    <i class="fa-solid fa-paper-plane"></i>
                </div>
            </div>
            <div style="margin-top: 0.75rem; font-size: 0.75rem; color: var(--text-muted); display: flex; align-items: center; justify-content: space-between;">
                <span>{{ __('Queue Backlog:') }} <strong style="color: var(--text-main);">{{ $metrics['queued'] }}</strong></span>
                <span>{{ __('Processing:') }} <strong style="color: #f59e0b;">{{ $metrics['processing'] }}</strong></span>
            </div>
        </div>

        <!-- Accepted by SMTP Transport -->
        <div class="stat-kpi-card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="color: var(--text-muted); font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                        {{ __('Accepted by Transport') }}
                    </div>
                    <div style="font-family: 'Outfit', sans-serif; font-size: 2rem; font-weight: 800; color: #10b981; margin-top: 0.35rem;">
                        {{ number_format($metrics['accepted_by_transport']) }}
                    </div>
                </div>
                <div class="kpi-icon-badge" style="background: rgba(16, 185, 129, 0.12); color: #10b981;">
                    <i class="fa-solid fa-check"></i>
                </div>
            </div>
            <div style="margin-top: 0.75rem; font-size: 0.75rem; color: var(--text-muted); display: flex; align-items: center; justify-content: space-between;">
                <span>{{ __('Success Rate:') }}</span>
                <span class="badge badge-emerald">{{ $metrics['sent_rate'] }}%</span>
            </div>
        </div>

        <!-- Verified Delivered -->
        <div class="stat-kpi-card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="color: var(--text-muted); font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                        {{ __('Verified Delivered') }}
                    </div>
                    <div style="font-family: 'Outfit', sans-serif; font-size: 2rem; font-weight: 800; color: #06b6d4; margin-top: 0.35rem;">
                        @if($metrics['has_provider_tracking'])
                            {{ number_format($metrics['delivered_verified']) }}
                        @else
                            <span style="font-size: 1.15rem; color: var(--text-muted); font-weight: 700;">{{ __('Not Tracked') }}</span>
                        @endif
                    </div>
                </div>
                <div class="kpi-icon-badge" style="background: rgba(6, 182, 212, 0.12); color: #06b6d4;">
                    <i class="fa-solid fa-check-double"></i>
                </div>
            </div>
            <div style="margin-top: 0.75rem; font-size: 0.73rem; color: var(--text-muted);">
                @if($metrics['has_provider_tracking'])
                    <span>{{ __('Provider webhook rate:') }} <strong>{{ $metrics['verified_delivery_rate'] }}%</strong></span>
                @else
                    <span title="Standard SMTP confirms transport acceptance, not recipient inbox placement."><i class="fa-solid fa-circle-info"></i> {{ __('SMTP confirms transport acceptance') }}</span>
                @endif
            </div>
        </div>

        <!-- Failed & Bounces -->
        <div class="stat-kpi-card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="color: var(--text-muted); font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                        {{ __('Failed & Bounces') }}
                    </div>
                    <div style="font-family: 'Outfit', sans-serif; font-size: 2rem; font-weight: 800; color: #ef4444; margin-top: 0.35rem;">
                        {{ number_format($metrics['failed'] + $metrics['bounced']) }}
                    </div>
                </div>
                <div class="kpi-icon-badge" style="background: rgba(239, 68, 68, 0.12); color: #ef4444;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
            </div>
            <div style="margin-top: 0.75rem; font-size: 0.75rem; color: var(--text-muted); display: flex; align-items: center; justify-content: space-between;">
                <span>{{ __('Bounces:') }} {{ $metrics['bounced'] }}</span>
                <span class="badge {{ ($metrics['failed'] + $metrics['bounced']) > 0 ? 'badge-rose' : 'badge-emerald' }}">
                    {{ $metrics['failure_rate'] }}%
                </span>
            </div>
        </div>
    </div>

    <!-- Secondary Metric Bar (Queue & Timing Details) -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.25rem 1.5rem; margin-bottom: 2rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem;">
        <div>
            <div style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Queue Backlog') }}</div>
            <div style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-top: 0.2rem;">
                {{ $queueStats['pending_jobs'] }} {{ __('jobs') }}
            </div>
            <div style="font-size: 0.72rem; color: var(--text-muted);">{{ __('Oldest:') }} {{ $queueStats['oldest_pending_age'] ?? 'None' }}</div>
        </div>

        <div>
            <div style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Avg Dispatch Duration') }}</div>
            <div style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-top: 0.2rem;">
                {{ $metrics['avg_duration_ms'] }} <span style="font-size: 0.85rem; font-weight: 600;">ms</span>
            </div>
            <div style="font-size: 0.72rem; color: #10b981;">{{ __('Measured across SMTP handshakes') }}</div>
        </div>

        <div>
            <div style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Last Successful Dispatch') }}</div>
            <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-main); margin-top: 0.3rem;">
                {{ $metrics['last_successful_dispatch_at'] ? $metrics['last_successful_dispatch_at']->diffForHumans() : __('None yet') }}
            </div>
            <div style="font-size: 0.72rem; color: var(--text-muted);">
                {{ $metrics['last_successful_dispatch_at'] ? $metrics['last_successful_dispatch_at']->format('Y-m-d H:i') : '' }}
            </div>
        </div>

        <div>
            <div style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Last Connection Test') }}</div>
            <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-main); margin-top: 0.3rem;">
                {{ $metrics['last_tested_at'] ? $metrics['last_tested_at']->diffForHumans() : __('Never tested') }}
            </div>
            <div style="font-size: 0.72rem; color: var(--text-muted);">
                <a href="{{ route('superadmin.emails.connections.index') }}" style="color: var(--primary); text-decoration: none; font-weight: 600;">
                    <i class="fa-solid fa-play"></i> {{ __('Run socket test') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Trend Chart & Quick Controls -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Volume Trend Chart -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-card);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <div>
                    <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.2rem;">
                        {{ __('Daily Email Sending Volume (Last 14 Days)') }}
                    </h3>
                    <p style="font-size: 0.78rem; color: var(--text-muted);">
                        {{ __('Real-time count of successfully dispatched vs failed emails.') }}
                    </p>
                </div>
                <div style="display: flex; gap: 0.85rem; font-size: 0.75rem; font-weight: 700;">
                    <span style="color: #10b981; display: inline-flex; align-items: center; gap: 0.3rem;">
                        <span style="width: 8px; height: 8px; border-radius: 2px; background: #10b981;"></span> {{ __('Success') }}
                    </span>
                    <span style="color: #ef4444; display: inline-flex; align-items: center; gap: 0.3rem;">
                        <span style="width: 8px; height: 8px; border-radius: 2px; background: #ef4444;"></span> {{ __('Failed') }}
                    </span>
                </div>
            </div>

            @php
                $maxVal = max(1, $volumeChart->max('total') ?? 1);
            @endphp
            <div class="chart-bar-container">
                @foreach($volumeChart as $pt)
                    @php
                        $successPct = round(($pt->success / $maxVal) * 100);
                        $failedPct = round(($pt->failed / $maxVal) * 100);
                    @endphp
                    <div class="chart-col" title="{{ $pt->date }}: {{ $pt->total }} emails ({{ $pt->success }} ok, {{ $pt->failed }} failed)">
                        <div style="width: 100%; display: flex; flex-direction: column-reverse; align-items: center; gap: 2px; height: 100%; justify-content: flex-start;">
                            @if($pt->failed > 0)
                                <div class="chart-bar-failed" style="height: {{ max(4, $failedPct) }}%;"></div>
                            @endif
                            @if($pt->success > 0)
                                <div class="chart-bar-success" style="height: {{ max(4, $successPct) }}%;"></div>
                            @endif
                            @if($pt->total === 0)
                                <div style="width: 4px; height: 4px; border-radius: 50%; background: var(--border-color); margin-bottom: 4px;"></div>
                            @endif
                        </div>
                        <div style="font-size: 0.65rem; color: var(--text-subtle); margin-top: 0.5rem; text-align: center;">
                            {{ $pt->label }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Quick Actions & Diagnostics -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-card); display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.2rem;">
                    {{ __('Email Diagnostics & Control') }}
                </h3>
                <p style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                    {{ __('Routine operational checks for administrators.') }}
                </p>

                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <a href="{{ route('superadmin.emails.connections.index') }}" 
                       style="display: flex; align-items: center; justify-content: space-between; padding: 0.85rem 1rem; border-radius: 12px; background: var(--input-bg); border: 1px solid var(--border-color); text-decoration: none; color: var(--text-main); font-size: 0.84rem; font-weight: 700; transition: all 0.15s ease;">
                        <span style="display: flex; align-items: center; gap: 0.65rem;">
                            <i class="fa-solid fa-vial" style="color: #f59e0b;"></i>
                            <span>{{ __('Test SMTP Connections') }}</span>
                        </span>
                        <i class="fa-solid fa-chevron-right" style="color: var(--text-subtle); font-size: 0.75rem;"></i>
                    </a>

                    <a href="{{ route('superadmin.emails.senders.index') }}" 
                       style="display: flex; align-items: center; justify-content: space-between; padding: 0.85rem 1rem; border-radius: 12px; background: var(--input-bg); border: 1px solid var(--border-color); text-decoration: none; color: var(--text-main); font-size: 0.84rem; font-weight: 700; transition: all 0.15s ease;">
                        <span style="display: flex; align-items: center; gap: 0.65rem;">
                            <i class="fa-solid fa-id-card" style="color: #10b981;"></i>
                            <span>{{ __('Sender Identities CRUD') }}</span>
                        </span>
                        <i class="fa-solid fa-chevron-right" style="color: var(--text-subtle); font-size: 0.75rem;"></i>
                    </a>

                    <a href="{{ route('superadmin.emails.queue.index') }}" 
                       style="display: flex; align-items: center; justify-content: space-between; padding: 0.85rem 1rem; border-radius: 12px; background: var(--input-bg); border: 1px solid var(--border-color); text-decoration: none; color: var(--text-main); font-size: 0.84rem; font-weight: 700; transition: all 0.15s ease;">
                        <span style="display: flex; align-items: center; gap: 0.65rem;">
                            <i class="fa-solid fa-layer-group" style="color: #6366f1;"></i>
                            <span>{{ __('Queue Monitor & Failures') }}</span>
                        </span>
                        <span class="badge {{ $queueStats['pending_jobs'] > 0 ? 'badge-amber' : 'badge-emerald' }}">
                            {{ $queueStats['pending_jobs'] }}
                        </span>
                    </a>

                    <a href="{{ route('superadmin.emails.reports.index') }}" 
                       style="display: flex; align-items: center; justify-content: space-between; padding: 0.85rem 1rem; border-radius: 12px; background: var(--input-bg); border: 1px solid var(--border-color); text-decoration: none; color: var(--text-main); font-size: 0.84rem; font-weight: 700; transition: all 0.15s ease;">
                        <span style="display: flex; align-items: center; gap: 0.65rem;">
                            <i class="fa-solid fa-file-csv" style="color: #06b6d4;"></i>
                            <span>{{ __('Export Analytics CSV') }}</span>
                        </span>
                        <i class="fa-solid fa-arrow-down" style="color: var(--text-subtle); font-size: 0.75rem;"></i>
                    </a>
                </div>
            </div>

            <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border-color); font-size: 0.75rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.4rem;">
                <i class="fa-solid fa-shield-halved" style="color: #10b981;"></i>
                <span>{{ __('Credentials encrypted at rest via AES-256.') }}</span>
            </div>
        </div>
    </div>

    <!-- Recent Outgoing Activity -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-card);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div>
                <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.2rem;">
                    {{ __('Recent Outgoing Email Activity') }}
                </h3>
                <p style="font-size: 0.8rem; color: var(--text-muted);">
                    {{ __('Persisted audit log of real application dispatches.') }}
                </p>
            </div>
            <a href="{{ route('superadmin.emails.logs.index') }}" class="btn btn-secondary" style="font-size: 0.8rem; border-radius: 10px; padding: 0.45rem 0.95rem;">
                {{ __('View All Logs') }} <i class="fa-solid fa-arrow-right" style="font-size: 0.75rem; margin-left: 0.25rem;"></i>
            </a>
        </div>

        <div class="responsive-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Timestamp') }}</th>
                        <th>{{ __('Recipient') }}</th>
                        <th>{{ __('Subject') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Sender Identity') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Duration') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentLogs as $log)
                        @php $badge = $log->getStatusBadge(); @endphp
                        <tr>
                            <td style="color: var(--text-muted); font-size: 0.8rem;">
                                {{ $log->created_at?->format('M d, H:i:s') }}
                            </td>
                            <td>
                                <strong style="color: var(--text-main);">{{ $log->recipient_email }}</strong>
                                @if($log->recipient_name)
                                    <div style="font-size: 0.72rem; color: var(--text-muted);">{{ $log->recipient_name }}</div>
                                @endif
                            </td>
                            <td style="max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ $log->subject }}
                            </td>
                            <td>
                                <span style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                                    {{ str_replace('_', ' ', $log->category) }}
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 0.78rem;">{{ $log->from_email }}</span>
                            </td>
                            <td>
                                <span class="badge {{ $badge['class'] }}">
                                    <i class="fa-solid {{ $badge['icon'] }}"></i> {{ $badge['label'] }}
                                </span>
                            </td>
                            <td style="font-size: 0.78rem; color: var(--text-muted);">
                                {{ $log->duration_ms ? $log->duration_ms . ' ms' : '—' }}
                            </td>
                            <td>
                                <a href="{{ route('superadmin.emails.logs.index', ['search' => $log->uuid]) }}" 
                                   class="btn btn-secondary" style="font-size: 0.72rem; padding: 0.3rem 0.65rem; border-radius: 8px;">
                                    <i class="fa-solid fa-magnifying-glass"></i> {{ __('Inspect') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                                <i class="fa-solid fa-envelope-open" style="font-size: 2rem; margin-bottom: 0.5rem; display: block; opacity: 0.4;"></i>
                                {{ __('No outgoing email activity recorded yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
