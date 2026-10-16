@extends('layouts.app')

@section('title', 'Email Reports & Analytics - QRMenu SuperAdmin')

@section('styles')
<style>
    .superadmin-hero {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(236, 72, 153, 0.08) 100%);
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
    .progress-bar-bg {
        width: 100%;
        height: 8px;
        background: var(--input-bg);
        border-radius: 9999px;
        overflow: hidden;
        margin-top: 0.35rem;
    }
    .progress-bar-fill {
        height: 100%;
        border-radius: 9999px;
        transition: width 0.3s ease;
    }
</style>
@endsection

@section('content')
<div>

    @include('superadmin.emails._nav', [
        'title' => __('Email Analytics & Deliverability Reports'),
        'subtitle' => __('Comprehensive statistical breakdown of volume, acceptance rates, categories, and failure distribution.')
    ])

    <!-- Filter & Export Header -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1rem 1.25rem; margin-bottom: 1.75rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                {{ __('Reporting Window:') }}
            </span>
            @foreach(['today' => 'Today', '7d' => 'Last 7 Days', '30d' => 'Last 30 Days'] as $rKey => $rLabel)
                <a href="{{ route('superadmin.emails.reports.index', array_merge($filters, ['range' => $rKey])) }}"
                   style="padding: 0.4rem 0.85rem; border-radius: 10px; font-size: 0.78rem; font-weight: 700; text-decoration: none; {{ ($filters['range'] ?? '30d') === $rKey ? 'background: var(--primary); color: #fff;' : 'background: var(--input-bg); color: var(--text-muted); border: 1px solid var(--border-color);' }}">
                    {{ __($rLabel) }}
                </a>
            @endforeach
        </div>

        <a href="{{ route('superadmin.emails.reports.export', $filters) }}" class="btn btn-secondary" style="border-radius: 10px; font-size: 0.82rem; padding: 0.5rem 1rem; color: #06b6d4;">
            <i class="fa-solid fa-file-csv"></i> {{ __('Export Executive Summary CSV') }}
        </a>
    </div>

    <!-- Core Rate Tiles -->
    <div class="grid-4" style="gap: 1.25rem; margin-bottom: 2rem;">
        <div class="stat-kpi-card">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Total Processed') }}</div>
            <div style="font-family: 'Outfit', sans-serif; font-size: 2rem; font-weight: 800; color: var(--text-main); margin-top: 0.25rem;">
                {{ number_format($metrics['total_emails']) }}
            </div>
            <div class="progress-bar-bg">
                <div class="progress-bar-fill" style="width: 100%; background: #3b82f6;"></div>
            </div>
        </div>

        <div class="stat-kpi-card">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('SMTP Acceptance Rate') }}</div>
            <div style="font-family: 'Outfit', sans-serif; font-size: 2rem; font-weight: 800; color: #10b981; margin-top: 0.25rem;">
                {{ $metrics['sent_rate'] }}%
            </div>
            <div class="progress-bar-bg">
                <div class="progress-bar-fill" style="width: {{ $metrics['sent_rate'] }}%; background: #10b981;"></div>
            </div>
        </div>

        <div class="stat-kpi-card">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Failure & Bounce Rate') }}</div>
            <div style="font-family: 'Outfit', sans-serif; font-size: 2rem; font-weight: 800; color: #ef4444; margin-top: 0.25rem;">
                {{ $metrics['failure_rate'] }}%
            </div>
            <div class="progress-bar-bg">
                <div class="progress-bar-fill" style="width: {{ min(100, $metrics['failure_rate'] * 3) }}%; background: #ef4444;"></div>
            </div>
        </div>

        <div class="stat-kpi-card">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Verified Delivery Rate') }}</div>
            <div style="font-family: 'Outfit', sans-serif; font-size: 1.5rem; font-weight: 800; color: #06b6d4; margin-top: 0.45rem;">
                {{ $metrics['verified_delivery_rate'] !== null ? $metrics['verified_delivery_rate'] . '%' : __('Not Tracked') }}
            </div>
            <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ $metrics['has_provider_tracking'] ? __('Verified via webhook') : __('Standard SMTP transport') }}
            </div>
        </div>
    </div>

    <!-- Category Breakdown & Senders Breakdown -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Category Breakdown -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-card);">
            <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.2rem;">
                {{ __('Volume by Business Category') }}
            </h3>
            <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                {{ __('Distribution across transactional, authentication, and marketing emails.') }}
            </p>

            <div class="responsive-table-wrapper">
                <table class="data-table" style="font-size: 0.82rem;">
                    <thead>
                        <tr>
                            <th>{{ __('Category') }}</th>
                            <th>{{ __('Total Volume') }}</th>
                            <th>{{ __('Success') }}</th>
                            <th>{{ __('Failed') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($byCategory as $c)
                            <tr>
                                <td>
                                    <strong style="color: var(--text-main);">{{ ucfirst(str_replace('_', ' ', $c->category)) }}</strong>
                                </td>
                                <td>{{ number_format($c->total) }}</td>
                                <td>
                                    <span class="badge badge-emerald">{{ number_format($c->success) }}</span>
                                </td>
                                <td>
                                    @if($c->failed > 0)
                                        <span class="badge badge-rose">{{ number_format($c->failed) }}</span>
                                    @else
                                        <span style="color: var(--text-subtle);">0</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">
                                    {{ __('No category data recorded in this period.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sender Breakdown -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-card);">
            <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.2rem;">
                {{ __('Volume by Sender Identity') }}
            </h3>
            <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                {{ __('Monitor reputation load across individual sender addresses.') }}
            </p>

            <div class="responsive-table-wrapper">
                <table class="data-table" style="font-size: 0.82rem;">
                    <thead>
                        <tr>
                            <th>{{ __('Sender') }}</th>
                            <th>{{ __('From Address') }}</th>
                            <th>{{ __('Total Dispatches') }}</th>
                            <th>{{ __('Accepted') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bySender as $s)
                            <tr>
                                <td>
                                    <strong style="color: var(--text-main);">{{ $s->from_name ?: 'System' }}</strong>
                                </td>
                                <td>
                                    <code style="font-size: 0.78rem;">{{ $s->from_email }}</code>
                                </td>
                                <td>{{ number_format($s->total) }}</td>
                                <td>
                                    <span class="badge badge-emerald">{{ number_format($s->success) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">
                                    {{ __('No sender activity in this period.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Connection Performance Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-card);">
        <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.2rem;">
            {{ __('Performance by SMTP Connection') }}
        </h3>
        <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1.25rem;">
            {{ __('Reliability and throughput across individual transport servers.') }}
        </p>

        <div class="responsive-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Connection') }}</th>
                        <th>{{ __('Transport Driver') }}</th>
                        <th>{{ __('Total Dispatched') }}</th>
                        <th>{{ __('Successful') }}</th>
                        <th>{{ __('Failed') }}</th>
                        <th>{{ __('Success Rate') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($byConnection as $conn)
                        @php
                            $rate = $conn->total > 0 ? round(($conn->success / $conn->total) * 100, 1) : 0;
                        @endphp
                        <tr>
                            <td>
                                <strong style="color: var(--text-main);">{{ $conn->connection_name ?: 'Primary' }}</strong>
                            </td>
                            <td>
                                <span class="badge badge-indigo">{{ strtoupper($conn->transport ?: 'SMTP') }}</span>
                            </td>
                            <td>{{ number_format($conn->total) }}</td>
                            <td>
                                <span class="badge badge-emerald">{{ number_format($conn->success) }}</span>
                            </td>
                            <td>
                                @if($conn->failed > 0)
                                    <span class="badge badge-rose">{{ number_format($conn->failed) }}</span>
                                @else
                                    <span style="color: var(--text-subtle);">0</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $rate >= 95 ? 'badge-emerald' : 'badge-amber' }}">
                                    {{ $rate }}%
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">
                                {{ __('No connection dispatches recorded.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
