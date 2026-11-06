@extends('layouts.app')

@section('content')
<div class="content-wrapper">

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-shield-halved" style="color: #6366f1;"></i>
                <span>{{ __('Loyalty Audit Trail & Security Logs') }}</span>
            </h1>
            <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Forensic logging of administrative actions, staff manual balance adjustments, and reconciliation runs.') }}
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <button type="button" class="btn btn-secondary" onclick="triggerReconciliationCheck()">
                <i class="fa-solid fa-scale-balanced mr-1"></i> {{ __('Run Ledger Reconciliation') }}
            </button>
            <a href="{{ route('admin.loyalty.index') }}" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left mr-1"></i> {{ __('Overview') }}
            </a>
        </div>
    </div>

    <!-- Reconciliation Result Alert (Dynamic) -->
    <div id="reconciliationBanner" style="display: none; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; font-size: 0.875rem;"></div>

    <!-- Logs Table Card -->
    <div class="card" style="padding: 1.5rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;">
        @if($logs->isEmpty())
            <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                <i class="fa-solid fa-shield-halved" style="font-size: 2.5rem; opacity: 0.3; margin-bottom: 0.75rem; display: block;"></i>
                <p style="margin: 0;">{{ __('No audit records logged yet. Operations will record timestamp, staff id, IP, and changes here.') }}</p>
            </div>
        @else
            <div class="table-responsive" style="overflow-x: auto;">
                <table class="table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                            <th style="padding: 0.75rem 1rem;">{{ __('Timestamp') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('Action') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('Staff Operator') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('Customer Target') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('Client IP & Device') }}</th>
                            <th style="padding: 0.75rem 1rem;">{{ __('Metadata') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                            <tr style="border-bottom: 1px solid var(--border-color); font-size: 0.85rem;">
                                <td style="padding: 0.85rem 1rem; color: var(--text-muted); font-size: 0.8rem; white-space: nowrap;">
                                    {{ $log->created_at->format('Y-m-d H:i:s') }}
                                </td>
                                <td style="padding: 0.85rem 1rem;">
                                    <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #818cf8; font-weight: 700; text-transform: uppercase; font-size: 0.7rem; padding: 0.25rem 0.5rem; border-radius: 6px;">
                                        {{ str_replace('_', ' ', $log->action) }}
                                    </span>
                                </td>
                                <td style="padding: 0.85rem 1rem;">
                                    @if($log->staff)
                                        <div style="font-weight: 600; color: var(--text-main);">
                                            {{ $log->staff->name }}
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                                            {{ $log->staff->email }}
                                        </div>
                                    @else
                                        <span style="color: var(--text-muted);">System / Automated</span>
                                    @endif
                                </td>
                                <td style="padding: 0.85rem 1rem;">
                                    @if($log->customer)
                                        <div style="font-weight: 600; color: var(--text-main);">
                                            {{ $log->customer->name ?: __('Customer') }}
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                                            {{ $log->customer->phone }}
                                        </div>
                                    @else
                                        <span style="color: var(--text-muted);">-</span>
                                    @endif
                                </td>
                                <td style="padding: 0.85rem 1rem; font-size: 0.75rem; color: var(--text-muted); white-space: nowrap;">
                                    <div><i class="fa-solid fa-network-wired mr-1"></i> {{ $log->ip_address ?: '127.0.0.1' }}</div>
                                    <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;" title="{{ $log->user_agent }}">
                                        {{ Str::limit($log->user_agent, 30) }}
                                    </div>
                                </td>
                                <td style="padding: 0.85rem 1rem;">
                                    @if($log->details && is_array($log->details))
                                        <pre style="background: var(--bg-main); border: 1px solid var(--border-color); padding: 0.4rem 0.6rem; border-radius: 6px; font-size: 0.7rem; color: var(--text-main); margin: 0; max-height: 80px; overflow-y: auto;">{{ json_encode($log->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    @else
                                        <span style="color: var(--text-muted);">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 1.5rem;">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

</div>

<script>
function triggerReconciliationCheck() {
    const banner = document.getElementById('reconciliationBanner');
    banner.style.display = 'block';
    banner.style.background = 'rgba(99, 102, 241, 0.1)';
    banner.style.border = '1px solid rgba(99, 102, 241, 0.3)';
    banner.style.color = '#818cf8';
    banner.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Analyzing ledgers and calculating audit hashes...';

    fetch('{{ route("admin.loyalty.reconcile") }}', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.is_healthy) {
            banner.style.background = 'rgba(16, 185, 129, 0.1)';
            banner.style.border = '1px solid rgba(16, 185, 129, 0.3)';
            banner.style.color = '#34d399';
            banner.innerHTML = '<i class="fa-solid fa-circle-check mr-2"></i> <strong>Ledgers Perfectly Balanced!</strong> All customer cached balances strictly match the sum of immutable ledger transactions.';
        } else {
            banner.style.background = 'rgba(239, 68, 68, 0.1)';
            banner.style.border = '1px solid rgba(239, 68, 68, 0.3)';
            banner.style.color = '#f87171';
            banner.innerHTML = '<i class="fa-solid fa-triangle-exclamation mr-2"></i> <strong>Discrepancies detected:</strong> ' +
                JSON.stringify(data.points_discrepancies || {}) + ' ' + JSON.stringify(data.cashback_discrepancies || {});
        }
    })
    .catch(err => {
        banner.style.background = 'rgba(239, 68, 68, 0.1)';
        banner.style.border = '1px solid rgba(239, 68, 68, 0.3)';
        banner.style.color = '#f87171';
        banner.innerHTML = '<i class="fa-solid fa-triangle-exclamation mr-2"></i> Failed to run reconciliation query.';
    });
}
</script>
@endsection

