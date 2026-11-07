@extends('layouts.app')

@section('title', __('Financial Approvals Queue') . ' - ' . config('app.name'))

@section('content')
<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Header -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-clipboard-check" style="color: #f97316;"></i>
                    {{ __('Financial Approvals Queue') }}
                </h1>
                <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.85rem;">
                    {{ __('Enforce multi-tier authorization limits on high-value expenses, shift variances, and customer refunds.') }}
                </p>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route('admin.finance.approvals.index') }}" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.25rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
            <select name="status" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ __('Pending Decision') }}</option>
                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>{{ __('Approved') }}</option>
                <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>{{ __('Rejected') }}</option>
            </select>
        </form>
    </div>

    <!-- Approvals Table -->
    <div class="card" style="padding: 1.5rem;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Request #') }}</th>
                        <th>{{ __('Type / Entity') }}</th>
                        <th>{{ __('Requested By') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th style="text-align: right;">{{ __('Amount') }}</th>
                        <th>{{ __('Notes / Justification') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th style="text-align: right;">{{ __('Decision') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                        <tr>
                            <td style="font-family: monospace; font-weight: 800;">#{{ $req->id }}</td>
                            <td>
                                <span class="badge badge-secondary">{{ class_basename($req->approvable_type) }} #{{ $req->approvable_id }}</span>
                            </td>
                            <td>{{ $req->requestedByUser?->name ?? '—' }}</td>
                            <td style="font-size: 0.85rem;">{{ $req->created_at->format('Y-m-d H:i') }}</td>
                            <td style="text-align: right; font-weight: 800; font-size: 1.05rem; color: #f97316;">
                                {{ number_format($req->amount, 2) }}
                            </td>
                            <td style="font-size: 0.85rem; max-width: 260px;">{{ $req->notes ?? '—' }}</td>
                            <td>
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
                                    <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem;">
                                        <form method="POST" action="{{ route('admin.finance.approvals.decide', $req) }}">
                                            @csrf
                                            <input type="hidden" name="decision" value="approve">
                                            <button type="submit" class="btn btn-xs btn-emerald">{{ __('Approve') }}</button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.finance.approvals.decide', $req) }}">
                                            @csrf
                                            <input type="hidden" name="decision" value="reject">
                                            <button type="submit" class="btn btn-xs btn-rose">{{ __('Reject') }}</button>
                                        </form>
                                    </div>
                                @else
                                    <span style="font-size: 0.75rem; color: var(--text-muted);">
                                        {{ __('Decided by') }} {{ $req->decidedByUser?->name ?? 'Manager' }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                                {{ __('No approval requests matching criteria.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1rem;">
            {{ $requests->links() }}
        </div>
    </div>
</div>
@endsection

