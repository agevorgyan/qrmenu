@extends('layouts.app')

@section('title', __('Financial Approvals Queue') . ' - ' . config('app.name'))

@section('content')
<div style="max-width: 1400px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Page Header -->
    <div class="card" style="padding: 1.5rem; margin-bottom: 0;">
        <div class="page-header-flex" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(249, 115, 22, 0.12); color: #f97316; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>
                <div>
                    <h1 style="font-size: clamp(1.25rem, 2.5vw, 1.65rem); font-weight: 800; margin: 0; color: var(--text-main); line-height: 1.2;">
                        {{ __('Financial Approvals Queue') }}
                    </h1>
                    <p style="margin: 0.35rem 0 0; color: var(--text-muted); font-size: 0.875rem;">
                        {{ __('Enforce multi-tier authorization limits on high-value expenses, shift variances, and customer refunds.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route('admin.finance.approvals.index') }}" class="filter-bar-flex" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.25rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
            <select name="status" class="form-control" style="width: auto; font-size: 0.875rem;" onchange="this.form.submit()">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ __('Pending Decision') }}</option>
                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>{{ __('Approved') }}</option>
                <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>{{ __('Rejected') }}</option>
            </select>
        </form>
    </div>

    <!-- Approvals Table Card -->
    <div class="card card-flush" style="border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-card);">
        <div class="table-responsive" style="margin-bottom: 0; border: none; border-radius: 0;">
            <table class="table" style="min-width: 980px;">
                <thead>
                    <tr>
                        <th style="width: 110px;">{{ __('Request #') }}</th>
                        <th style="min-width: 170px;">{{ __('Type / Entity') }}</th>
                        <th style="min-width: 150px;">{{ __('Requested By') }}</th>
                        <th style="width: 130px;">{{ __('Date') }}</th>
                        <th style="min-width: 140px; text-align: right;">{{ __('Amount') }}</th>
                        <th style="min-width: 200px;">{{ __('Notes / Justification') }}</th>
                        <th style="width: 120px; text-align: center;">{{ __('Status') }}</th>
                        <th style="min-width: 160px; text-align: right;">{{ __('Decision') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                        <tr>
                            <td>
                                <span style="font-family: monospace; font-weight: 800; font-size: 0.9rem; background: rgba(249, 115, 22, 0.08); color: #f97316; padding: 0.2rem 0.5rem; border-radius: 6px;">
                                    #{{ $req->id }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-secondary" style="font-weight: 700; font-size: 0.775rem;">
                                    {{ class_basename($req->approvable_type) }} #{{ $req->approvable_id }}
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem;">
                                    <div style="width: 22px; height: 22px; border-radius: 50%; background: rgba(59, 130, 246, 0.1); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700;">
                                        {{ mb_substr($req->requestedByUser?->name ?? '?', 0, 1) }}
                                    </div>
                                    <span style="font-weight: 600;">{{ $req->requestedByUser?->name ?? '—' }}</span>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 0.825rem; color: var(--text-muted); font-weight: 500;">
                                    {{ $req->created_at->format('Y-m-d H:i') }}
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: 800; font-size: 1.05rem; color: #f97316;">
                                {{ number_format($req->amount, 2) }}
                            </td>
                            <td>
                                <div style="font-size: 0.825rem; color: var(--text-muted); line-height: 1.35;">
                                    {{ $req->notes ?? '—' }}
                                </div>
                            </td>
                            <td style="text-align: center;">
                                @if($req->status === 'pending')
                                    <span class="badge badge-amber">{{ __('Pending') }}</span>
                                @elseif($req->status === 'approved')
                                    <span class="badge badge-emerald">{{ __('Approved') }}</span>
                                @else
                                    <span class="badge badge-rose">{{ __('Rejected') }}</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                @if($req->status === 'pending')
                                    <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.45rem;">
                                        <form method="POST" action="{{ route('admin.finance.approvals.decide', $req) }}">
                                            @csrf
                                            <input type="hidden" name="decision" value="approve">
                                            <button type="submit" class="btn btn-xs btn-emerald">
                                                <i class="fa-solid fa-check"></i>
                                                <span>{{ __('Approve') }}</span>
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.finance.approvals.decide', $req) }}">
                                            @csrf
                                            <input type="hidden" name="decision" value="reject">
                                            <button type="submit" class="btn btn-xs btn-rose">
                                                <i class="fa-solid fa-xmark"></i>
                                                <span>{{ __('Reject') }}</span>
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <span style="font-size: 0.775rem; color: var(--text-muted);">
                                        {{ __('Decided by') }} <span style="font-weight: 600; color: var(--text-main);">{{ $req->decidedByUser?->name ?? 'Manager' }}</span>
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 3.5rem 1.5rem;">
                                <div style="width: 54px; height: 54px; border-radius: 16px; background: rgba(249, 115, 22, 0.08); color: #f97316; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1rem;">
                                    <i class="fa-solid fa-clipboard-check"></i>
                                </div>
                                <div style="font-weight: 700; font-size: 1rem; color: var(--text-main); margin-bottom: 0.25rem;">
                                    {{ __('No approval requests matching criteria.') }}
                                </div>
                                <p style="font-size: 0.85rem; margin: 0; color: var(--text-muted);">
                                    {{ __('High-value expenses or sensitive actions requiring management approval will appear here.') }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($requests->hasPages())
            <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); background: var(--bg-card);">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
