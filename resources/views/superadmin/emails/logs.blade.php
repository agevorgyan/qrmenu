@extends('layouts.app')

@section('title', 'Email Logs & Audit Trail - QRMenu SuperAdmin')

@section('styles')
<style>
    .superadmin-hero {
        background: linear-gradient(135deg, rgba(6, 182, 212, 0.12) 0%, rgba(59, 130, 246, 0.08) 100%);
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
    detailModal: false,
    loadingDetail: false,
    selectedLog: null,
    attempts: [],
    events: [],
    async inspectLog(logId) {
        this.loadingDetail = true;
        this.detailModal = true;
        this.selectedLog = null;
        this.attempts = [];
        this.events = [];

        try {
            const resp = await fetch('{{ url('superadmin/emails/logs') }}/' + logId);
            const data = await resp.json();
            this.selectedLog = data.log;
            this.attempts = data.attempts || [];
            this.events = data.events || [];
        } catch (err) {
            alert('Failed to load log audit details.');
        } finally {
            this.loadingDetail = false;
        }
    }
}">

    @include('superadmin.emails._nav', [
        'title' => __('Searchable Email Logs & Audit Trail'),
        'subtitle' => __('Full persistent lifecycle logs: queue status, transport delivery, provider confirmation, and error diagnostics.')
    ])

    <!-- Search & Multi-Filter Bar -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.25rem 1.5rem; margin-bottom: 1.75rem; box-shadow: var(--shadow-card);">
        <form action="{{ route('superadmin.emails.logs.index') }}" method="GET">
            <div class="grid-4" style="gap: 1rem; margin-bottom: 1rem;">
                <!-- Search Term -->
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem;">
                        {{ __('Search Query') }}
                    </label>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-input" style="width: 100%; border-radius: 10px; padding: 0.55rem 0.8rem; font-size: 0.85rem;" placeholder="Recipient, subject, UUID, message ID...">
                </div>

                <!-- Status Filter -->
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem;">
                        {{ __('Status') }}
                    </label>
                    <select name="status" class="form-select" style="width: 100%; border-radius: 10px; padding: 0.55rem 0.8rem; font-size: 0.85rem;">
                        <option value="all">{{ __('All Statuses') }}</option>
                        <option value="sent" {{ ($filters['status'] ?? '') === 'sent' ? 'selected' : '' }}>Sent (Accepted by SMTP)</option>
                        <option value="delivered" {{ ($filters['status'] ?? '') === 'delivered' ? 'selected' : '' }}>Delivered (Provider Verified)</option>
                        <option value="queued" {{ ($filters['status'] ?? '') === 'queued' ? 'selected' : '' }}>Queued</option>
                        <option value="processing" {{ ($filters['status'] ?? '') === 'processing' ? 'selected' : '' }}>Processing</option>
                        <option value="failed" {{ ($filters['status'] ?? '') === 'failed' ? 'selected' : '' }}>Failed</option>
                        <option value="bounced" {{ ($filters['status'] ?? '') === 'bounced' ? 'selected' : '' }}>Bounced</option>
                        <option value="complained" {{ ($filters['status'] ?? '') === 'complained' ? 'selected' : '' }}>Complaint</option>
                        <option value="cancelled" {{ ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>

                <!-- Category Filter -->
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem;">
                        {{ __('Email Category') }}
                    </label>
                    <select name="category" class="form-select" style="width: 100%; border-radius: 10px; padding: 0.55rem 0.8rem; font-size: 0.85rem;">
                        <option value="all">{{ __('All Categories') }}</option>
                        @foreach($categories as $catKey => $catMeta)
                            <option value="{{ $catKey }}" {{ ($filters['category'] ?? '') === $catKey ? 'selected' : '' }}>{{ $catMeta['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Time Range Filter -->
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem;">
                        {{ __('Date Range') }}
                    </label>
                    <select name="range" class="form-select" style="width: 100%; border-radius: 10px; padding: 0.55rem 0.8rem; font-size: 0.85rem;">
                        <option value="today" {{ ($filters['range'] ?? '') === 'today' ? 'selected' : '' }}>Today</option>
                        <option value="7d" {{ ($filters['range'] ?? '') === '7d' ? 'selected' : '' }}>Last 7 Days</option>
                        <option value="30d" {{ ($filters['range'] ?? '30d') === '30d' ? 'selected' : '' }}>Last 30 Days</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <div style="font-size: 0.8rem; color: var(--text-muted);">
                    {{ __('Showing') }} <strong style="color: var(--text-main);">{{ $logs->total() }}</strong> {{ __('records matching criteria') }}
                </div>
                <div style="display: flex; gap: 0.65rem;">
                    <a href="{{ route('superadmin.emails.logs.index') }}" class="btn btn-secondary" style="border-radius: 10px; font-size: 0.82rem; padding: 0.5rem 1rem;">
                        <i class="fa-solid fa-arrows-rotate"></i> {{ __('Reset') }}
                    </a>
                    <a href="{{ route('superadmin.emails.logs.export', $filters) }}" class="btn btn-secondary" style="border-radius: 10px; font-size: 0.82rem; padding: 0.5rem 1rem; color: #06b6d4;">
                        <i class="fa-solid fa-file-csv"></i> {{ __('Export CSV') }}
                    </a>
                    <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-size: 0.82rem; padding: 0.5rem 1.25rem;">
                        <i class="fa-solid fa-magnifying-glass"></i> {{ __('Apply Filters') }}
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-card); margin-bottom: 1.5rem;">
        <div class="responsive-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Date & Time') }}</th>
                        <th>{{ __('Recipient') }}</th>
                        <th>{{ __('Subject') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('From') }}</th>
                        <th>{{ __('Connection') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Latency') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        @php $badge = $log->getStatusBadge(); @endphp
                        <tr>
                            <td style="color: var(--text-muted); font-size: 0.78rem;">
                                {{ $log->created_at?->format('Y-m-d H:i:s') }}
                            </td>
                            <td>
                                <strong style="color: var(--text-main); font-size: 0.85rem;">{{ $log->recipient_email }}</strong>
                                @if($log->recipient_name)
                                    <div style="font-size: 0.72rem; color: var(--text-muted);">{{ $log->recipient_name }}</div>
                                @endif
                            </td>
                            <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ $log->subject }}
                            </td>
                            <td>
                                <span style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                                    {{ str_replace('_', ' ', $log->category) }}
                                </span>
                            </td>
                            <td style="font-size: 0.78rem; color: var(--text-main);">
                                {{ $log->from_email }}
                            </td>
                            <td>
                                <span style="font-size: 0.75rem; color: var(--text-muted);">
                                    {{ $log->connection_name ?? 'SMTP' }}
                                </span>
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
                                <button @click="inspectLog({{ $log->id }})" class="btn btn-secondary" style="font-size: 0.72rem; padding: 0.3rem 0.65rem; border-radius: 8px;">
                                    <i class="fa-solid fa-magnifying-glass"></i> {{ __('Audit') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                                {{ __('No email records found matching the specified filters.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1.25rem;">
            {{ $logs->links() }}
        </div>
    </div>

    <!-- Log Audit Detail Slideover / Modal -->
    <div x-show="detailModal" class="modern-modal-overlay" style="display: none;" @keydown.escape.window="detailModal = false">
        <div class="modern-modal-box" style="max-width: 720px;" @click.away="detailModal = false">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--text-main);">
                    <i class="fa-solid fa-shield-halved" style="color: #06b6d4;"></i> {{ __('Email Dispatch Audit Details') }}
                </h3>
                <button @click="detailModal = false" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>

            <div x-show="loadingDetail" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                <i class="fa-solid fa-spinner fa-spin" style="font-size: 1.5rem;"></i> {{ __('Loading log telemetry...') }}
            </div>

            <div x-show="!loadingDetail && selectedLog">
                <!-- Metadata Grid -->
                <div style="background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.15rem; margin-bottom: 1.25rem; display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem; font-size: 0.8rem;">
                    <div><strong>UUID:</strong> <code style="font-size: 0.75rem;" x-text="selectedLog ? selectedLog.uuid : ''"></code></div>
                    <div><strong>Category:</strong> <span style="text-transform: uppercase; font-weight: 700;" x-text="selectedLog ? selectedLog.category : ''"></span></div>
                    <div><strong>Recipient:</strong> <span x-text="selectedLog ? selectedLog.recipient_email : ''"></span></div>
                    <div><strong>From:</strong> <span x-text="selectedLog ? selectedLog.from_email : ''"></span></div>
                    <div><strong>Connection:</strong> <span x-text="selectedLog ? selectedLog.connection_name : ''"></span> (<span x-text="selectedLog ? selectedLog.transport : ''"></span>)</div>
                    <div><strong>Provider Msg ID:</strong> <span style="word-break: break-all;" x-text="selectedLog && selectedLog.provider_message_id ? selectedLog.provider_message_id : 'None'"></span></div>
                    <div><strong>Attempts:</strong> <span x-text="selectedLog ? selectedLog.attempts : ''"></span></div>
                    <div><strong>Duration:</strong> <span x-text="selectedLog && selectedLog.duration_ms ? selectedLog.duration_ms + ' ms' : '—'"></span></div>
                </div>

                <!-- Error Box if Failed -->
                <div x-show="selectedLog && selectedLog.error_message" style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; padding: 1rem; margin-bottom: 1.25rem; font-size: 0.82rem; color: #ef4444;">
                    <div style="font-weight: 700; margin-bottom: 0.25rem;"><i class="fa-solid fa-triangle-exclamation"></i> Error Diagnostics:</div>
                    <div style="font-family: monospace; white-space: pre-wrap;" x-text="selectedLog ? selectedLog.error_message : ''"></div>
                </div>

                <!-- Attempts History Timeline -->
                <div style="margin-bottom: 1.25rem;">
                    <h4 style="font-size: 0.88rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.5rem;">
                        {{ __('Dispatch Attempts History') }} (<span x-text="attempts.length"></span>)
                    </h4>
                    <div class="responsive-table-wrapper">
                        <table class="data-table" style="font-size: 0.78rem;">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Status</th>
                                    <th>Connection</th>
                                    <th>Latency</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="att in attempts" :key="att.id">
                                    <tr>
                                        <td x-text="'Attempt #' + att.attempt_number"></td>
                                        <td>
                                            <span class="badge" :class="att.status === 'sent' ? 'badge-emerald' : 'badge-rose'" x-text="att.status"></span>
                                        </td>
                                        <td x-text="att.connection_name || 'SMTP'"></td>
                                        <td x-text="att.duration_ms ? att.duration_ms + ' ms' : '—'"></td>
                                        <td x-text="att.created_at"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Provider Events Timeline -->
                <div x-show="events.length > 0" style="margin-bottom: 1rem;">
                    <h4 style="font-size: 0.88rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.5rem;">
                        {{ __('Verified Provider Events') }} (<span x-text="events.length"></span>)
                    </h4>
                    <div class="responsive-table-wrapper">
                        <table class="data-table" style="font-size: 0.78rem;">
                            <thead>
                                <tr>
                                    <th>Provider</th>
                                    <th>Event</th>
                                    <th>Occurred At</th>
                                    <th>Signature Verified</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="ev in events" :key="ev.id">
                                    <tr>
                                        <td x-text="ev.provider"></td>
                                        <td><span class="badge badge-cyan" x-text="ev.event_type"></span></td>
                                        <td x-text="ev.occurred_at || ev.created_at"></td>
                                        <td>
                                            <span class="badge badge-emerald" x-show="ev.signature_verified"><i class="fa-solid fa-check"></i> Yes</span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 1.25rem;">
                <button type="button" @click="detailModal = false" class="btn btn-secondary" style="border-radius: 10px; padding: 0.5rem 1.25rem;">{{ __('Close') }}</button>
            </div>
        </div>
    </div>

</div>
@endsection
