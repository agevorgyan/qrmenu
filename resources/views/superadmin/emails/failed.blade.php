@extends('layouts.app')

@section('title', 'Failed Emails & Retries - QRMenu SuperAdmin')

@section('styles')
<style>
    .superadmin-hero {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.12) 0%, rgba(245, 158, 11, 0.08) 100%);
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
    retryingId: null,
    async retryEmail(logId) {
        if (!confirm('Safely retry sending this email?')) return;
        this.retryingId = logId;

        try {
            const resp = await fetch('{{ url('superadmin/emails/failed') }}/' + logId + '/retry', {
                method: 'POST',
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
            alert('Failed to execute retry.');
        } finally {
            this.retryingId = null;
        }
    }
}">

    @include('superadmin.emails._nav', [
        'title' => __('Failed Emails & Safe Recovery'),
        'subtitle' => __('Investigate rejected messages, SMTP authentication errors, and initiate idempotent safe retries.')
    ])

    <!-- Common Failure Reasons Breakdown -->
    @if($commonReasons->isNotEmpty())
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; margin-bottom: 1.75rem; box-shadow: var(--shadow-card);">
            <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.2rem;">
                <i class="fa-solid fa-triangle-exclamation" style="color: #ef4444;"></i> {{ __('Top Observed Failure Reasons') }}
            </h3>
            <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">
                {{ __('Common diagnostic error summaries across recent failures.') }}
            </p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                @foreach($commonReasons as $reason)
                    <div style="background: var(--input-bg); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 12px; padding: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                            <span class="badge badge-rose">{{ $reason->error_code }}</span>
                            <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted);">
                                {{ $reason->occurrences }} {{ __('occurrences') }}
                            </span>
                        </div>
                        <div style="font-size: 0.82rem; color: var(--text-main); font-family: monospace; word-break: break-word;">
                            {{ $reason->summary ?: __('Connection timeout or unhandled mailer exception') }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Failed Emails Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-card);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.2rem;">
                    {{ __('Failed Email Records') }} ({{ $failedLogs->total() }})
                </h3>
                <p style="font-size: 0.82rem; color: var(--text-muted);">
                    {{ __('Safeguards are enforced against duplicate transactional emails.') }}
                </p>
            </div>
        </div>

        <div class="responsive-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Recipient') }}</th>
                        <th>{{ __('Subject') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Connection') }}</th>
                        <th>{{ __('Failure Reason') }}</th>
                        <th>{{ __('Attempts') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($failedLogs as $fl)
                        <tr>
                            <td style="color: var(--text-muted); font-size: 0.78rem;">
                                {{ $fl->last_attempt_at?->diffForHumans() ?? $fl->created_at?->diffForHumans() }}
                            </td>
                            <td>
                                <strong style="color: var(--text-main); font-size: 0.85rem;">{{ $fl->recipient_email }}</strong>
                            </td>
                            <td style="max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ $fl->subject }}
                            </td>
                            <td>
                                <span style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                                    {{ str_replace('_', ' ', $fl->category) }}
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 0.78rem; color: var(--text-muted);">
                                    {{ $fl->connection_name ?? 'Default' }}
                                </span>
                            </td>
                            <td style="max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #ef4444; font-family: monospace; font-size: 0.78rem;" title="{{ $fl->error_message }}">
                                {{ $fl->error_message ?: 'Transport rejected connection' }}
                            </td>
                            <td>
                                <span class="badge badge-amber">{{ $fl->attempts }}</span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.4rem;">
                                    @if($fl->canRetry())
                                        <button @click="retryEmail({{ $fl->id }})" 
                                                class="btn btn-primary" 
                                                :disabled="retryingId === {{ $fl->id }}"
                                                style="font-size: 0.72rem; padding: 0.3rem 0.65rem; border-radius: 8px;">
                                            <i class="fa-solid" :class="retryingId === {{ $fl->id }} ? 'fa-spinner fa-spin' : 'fa-arrows-rotate'"></i> {{ __('Retry') }}
                                        </button>
                                    @else
                                        <span style="font-size: 0.72rem; color: var(--text-subtle);">{{ __('Not Retriable') }}</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                                <i class="fa-solid fa-circle-check" style="color: #10b981; font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                                {{ __('Zero failed emails found. All outgoing messages delivered successfully.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1.25rem;">
            {{ $failedLogs->links() }}
        </div>
    </div>

</div>
@endsection
