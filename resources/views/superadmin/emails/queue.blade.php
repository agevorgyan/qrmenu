@extends('layouts.app')

@section('title', 'Queue Monitor - QRMenu SuperAdmin')

@section('styles')
<style>
    .superadmin-hero {
        background: linear-gradient(135deg, rgba(234, 179, 8, 0.12) 0%, rgba(59, 130, 246, 0.08) 100%);
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
</style>
@endsection

@section('content')
<div x-data="{
    cancelLoadingId: null,
    async cancelJob(jobId) {
        if (!confirm('Cancel and remove this pending email job from the queue?')) return;
        this.cancelLoadingId = jobId;

        try {
            const resp = await fetch('{{ url('superadmin/emails/queue/cancel') }}/' + jobId, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const data = await resp.json();
            alert(data.message);
            if (data.success) {
                window.location.reload();
            }
        } catch (err) {
            alert('Failed to cancel queue job.');
        } finally {
            this.cancelLoadingId = null;
        }
    }
}">

    @include('superadmin.emails._nav', [
        'title' => __('Queue Monitor & Worker Backlog'),
        'subtitle' => __('Real-time database queue state, pending dispatch jobs, latency metrics, and safe queue cancellation.')
    ])

    <!-- Health & Metric Tiles -->
    <div class="grid-4" style="gap: 1.25rem; margin-bottom: 1.75rem;">
        <div class="stat-kpi-card">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Queue Driver') }}</div>
            <div style="font-family: 'Outfit', sans-serif; font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.25rem;">
                {{ strtoupper($stats['driver']) }}
            </div>
            <div style="font-size: 0.72rem; color: #10b981; margin-top: 0.4rem;">
                <i class="fa-solid fa-check"></i> {{ __('PostgreSQL database table backend') }}
            </div>
        </div>

        <div class="stat-kpi-card">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Pending Backlog') }}</div>
            <div style="font-family: 'Outfit', sans-serif; font-size: 1.75rem; font-weight: 800; color: {{ $stats['pending_jobs'] > 100 ? '#f59e0b' : 'var(--text-main)' }}; margin-top: 0.25rem;">
                {{ number_format($stats['pending_jobs']) }}
            </div>
            <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.4rem;">
                {{ __('Oldest:') }} {{ $stats['oldest_pending_age'] ?? 'None pending' }}
            </div>
        </div>

        <div class="stat-kpi-card">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Currently Processing') }}</div>
            <div style="font-family: 'Outfit', sans-serif; font-size: 1.75rem; font-weight: 800; color: #f59e0b; margin-top: 0.25rem;">
                {{ number_format($stats['processing_jobs']) }}
            </div>
            <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.4rem;">
                {{ __('Reserved by active workers') }}
            </div>
        </div>

        <div class="stat-kpi-card">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Total Failed Jobs') }}</div>
            <div style="font-family: 'Outfit', sans-serif; font-size: 1.75rem; font-weight: 800; color: {{ $stats['failed_jobs'] > 0 ? '#ef4444' : '#10b981' }}; margin-top: 0.25rem;">
                {{ number_format($stats['failed_jobs']) }}
            </div>
            <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.4rem;">
                <span class="badge {{ $stats['email_failed_jobs'] > 0 ? 'badge-rose' : 'badge-emerald' }}">
                    {{ $stats['email_failed_jobs'] }} {{ __('email failures') }}
                </span>
            </div>
        </div>
    </div>

    <!-- Active Queues Breakdown -->
    @if(!empty($stats['queues']))
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 1rem 1.25rem; margin-bottom: 1.75rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                <i class="fa-solid fa-layer-group"></i> {{ __('Queue Channels:') }}
            </span>
            @foreach($stats['queues'] as $qName => $qCount)
                <span style="background: var(--input-bg); border: 1px solid var(--border-color); padding: 0.35rem 0.75rem; border-radius: 10px; font-size: 0.8rem; color: var(--text-main);">
                    <strong>{{ $qName }}</strong>: {{ $qCount }} {{ __('jobs') }}
                </span>
            @endforeach
        </div>
    @endif

    <!-- Pending Jobs Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-card);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.2rem;">
                    {{ __('Pending Queue Backlog') }} ({{ $pendingJobs->count() }})
                </h3>
                <p style="font-size: 0.82rem; color: var(--text-muted);">
                    {{ __('Inspect upcoming jobs. Non-email jobs are strictly protected from modification.') }}
                </p>
            </div>
        </div>

        <div class="responsive-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Job ID') }}</th>
                        <th>{{ __('Queue') }}</th>
                        <th>{{ __('Type / Handler') }}</th>
                        <th>{{ __('Email Job') }}</th>
                        <th>{{ __('Attempts') }}</th>
                        <th>{{ __('Created At') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pendingJobs as $pj)
                        <tr>
                            <td>#<strong>{{ $pj->id }}</strong></td>
                            <td>
                                <span class="badge badge-indigo">{{ $pj->queue }}</span>
                            </td>
                            <td>
                                <code style="font-size: 0.82rem; color: var(--text-main);">
                                    {{ $pj->display_name }}
                                </code>
                            </td>
                            <td>
                                @if($pj->is_email)
                                    <span class="badge badge-emerald">
                                        <i class="fa-solid fa-envelope"></i> {{ __('Email Job') }}
                                    </span>
                                @else
                                    <span class="badge badge-amber" title="Protected system background job">
                                        <i class="fa-solid fa-gear"></i> {{ __('Other Job') }}
                                    </span>
                                @endif
                            </td>
                            <td>{{ $pj->attempts }}</td>
                            <td style="font-size: 0.8rem; color: var(--text-muted);">
                                {{ $pj->created_at->diffForHumans() }}
                            </td>
                            <td>
                                @if($pj->is_email)
                                    <button @click="cancelJob({{ $pj->id }})" 
                                            class="btn btn-secondary" 
                                            :disabled="cancelLoadingId === {{ $pj->id }}"
                                            style="font-size: 0.72rem; padding: 0.3rem 0.65rem; border-radius: 8px; color: #ef4444;">
                                        <i class="fa-solid fa-ban"></i> {{ __('Cancel') }}
                                    </button>
                                @else
                                    <span style="font-size: 0.72rem; color: var(--text-subtle);">🔒 {{ __('Protected') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                                <i class="fa-solid fa-circle-check" style="color: #10b981; font-size: 1.75rem; margin-bottom: 0.5rem; display: block;"></i>
                                {{ __('Queue is clear. No pending jobs in the backlog.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
