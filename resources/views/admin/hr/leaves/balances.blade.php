@extends('layouts.app')

@section('title', __('Leave Balances') . ' - ' . $vendor->name)

@section('content')
<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="min-width: 0; flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(249, 115, 22, 0.15); color: #f97316; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-scale-balanced"></i>
                </span>
                <div>
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.35rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                        {{ __('Leave Balances (:year)', ['year' => $year]) }}
                    </h1>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0.25rem 0 0;">
                        {{ __('Annual vacation entitlement, statutory leave tracking, used days, and remaining balances') }}
                    </p>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <a href="{{ route('admin.hr.leaves.index') }}" class="btn btn-secondary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px;">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Leave Requests') }}
            </a>
            <form method="GET" action="{{ route('admin.hr.leaves.balances') }}" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                <select name="year" onchange="this.form.submit()" class="form-control form-control-sm" style="border-radius: 8px; height: 36px;">
                    @for($y = date('Y') + 1; $y >= date('Y') - 2; $y--)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </form>
        </div>
    </div>

    <!-- Balances Table -->
    <div class="card" style="border-radius: 14px; overflow: hidden; padding: 0;">
        <div class="table-responsive">
            <table class="table" style="margin: 0; vertical-align: middle;">
                <thead style="background: rgba(255,255,255,0.03); border-bottom: 1px solid var(--border-color);">
                    <tr>
                        <th style="padding: 1rem 1.25rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Employee') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Category') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Allocated') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Used') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Pending') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Remaining') }}</th>
                        <th style="padding: 1rem 1.25rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; text-align: right;">{{ __('Profile') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($balances as $bal)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem 1.25rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(249, 115, 22, 0.15); color: #f97316; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem; flex-shrink: 0;">
                                        {{ strtoupper(mb_substr($bal->user?->name ?? '?', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; color: var(--text-main);">
                                            {{ $bal->user?->name ?? '—' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 1rem 1rem; font-weight: 600;">
                                {{ $bal->leaveType?->name ?? 'Annual Leave' }}
                            </td>
                            <td style="padding: 1rem 1rem; font-weight: 700; color: var(--text-main);">
                                {{ $bal->allocated_days }} {{ __('days') }}
                            </td>
                            <td style="padding: 1rem 1rem; color: #ef4444; font-weight: 700;">
                                {{ $bal->used_days }} {{ __('days') }}
                            </td>
                            <td style="padding: 1rem 1rem; color: #f59e0b; font-weight: 700;">
                                {{ $bal->pending_days }} {{ __('days') }}
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <span style="font-size: 1.1rem; font-weight: 800; color: #22c55e;">
                                    {{ $bal->remaining_days }}
                                </span>
                                <span style="font-size: 0.75rem; color: var(--text-muted);">{{ __('days left') }}</span>
                            </td>
                            <td style="padding: 1rem 1.25rem; text-align: right;">
                                @if($bal->user)
                                    <a href="{{ route('admin.hr.employees.show', $bal->user) }}" class="btn btn-secondary btn-sm" style="border-radius: 6px;">
                                        <i class="fa-solid fa-user"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 3rem 1.5rem; color: var(--text-muted);">
                                {{ __('No leave balance allocations found for this year.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

