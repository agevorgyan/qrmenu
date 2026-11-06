@extends('layouts.app')

@section('title', __('Payroll Management') . ' - ' . $vendor->name)

@section('content')
<div x-data="{
    showCreateModal: false
}" style="display: flex; flex-direction: column; gap: 1.5rem;">

    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="min-width: 0; flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(34, 197, 94, 0.15); color: #22c55e; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </span>
                <div>
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.35rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                        {{ __('Payroll Management') }}
                    </h1>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0.25rem 0 0;">
                        {{ __('Armenian Tax Code (Income Tax 20%, Pension 5%, Stamp Duty) calculations, timesheet synchronization, and net disbursements') }}
                    </p>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <a href="{{ route('admin.hr.payments.index') }}" class="btn btn-secondary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px;">
                <i class="fa-solid fa-money-bill-transfer"></i> {{ __('Salary Payouts') }}
            </a>
            <a href="{{ route('admin.hr.advances.index') }}" class="btn btn-secondary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px;">
                <i class="fa-solid fa-hand-holding-dollar"></i> {{ __('Employee Advances') }}
            </a>
            @can('hr.payroll.calculate')
                <button type="button" @click="showCreateModal = true" class="btn btn-primary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px; box-shadow: 0 4px 14px rgba(34, 197, 94, 0.3);">
                    <i class="fa-solid fa-plus-circle"></i> {{ __('New Payroll Period') }}
                </button>
            @endcan
        </div>
    </div>

    <!-- Periods Table -->
    <div class="card" style="border-radius: 14px; overflow: hidden; padding: 0;">
        <div class="table-responsive">
            <table class="table" style="margin: 0; vertical-align: middle;">
                <thead style="background: rgba(255,255,255,0.03); border-bottom: 1px solid var(--border-color);">
                    <tr>
                        <th style="padding: 1rem 1.25rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Payroll Period') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Date Range') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Headcount') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Gross Total') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Taxes & Ded.') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Net Payable') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Status') }}</th>
                        <th style="padding: 1rem 1.25rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($periods as $period)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem 1.25rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(34, 197, 94, 0.15); color: #22c55e; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0;">
                                        <i class="fa-solid fa-calendar-check"></i>
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.hr.payroll.preview', $period) }}" style="font-weight: 800; color: var(--text-main); text-decoration: none; font-size: 0.95rem;">
                                            {{ $period->name }}
                                        </a>
                                        <div style="font-size: 0.78rem; color: var(--text-muted); text-transform: capitalize;">
                                            {{ str_replace('_', ' ', $period->cycle_type) }} • Cutoff: {{ \Carbon\Carbon::parse($period->cutoff_date)->format('d.m.Y') }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <span style="font-size: 0.88rem; color: var(--text-main); font-weight: 600;">
                                    {{ \Carbon\Carbon::parse($period->start_date)->format('d.m.Y') }} - {{ \Carbon\Carbon::parse($period->end_date)->format('d.m.Y') }}
                                </span>
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <span style="font-weight: 700; color: #0ea5e9;">
                                    {{ $period->items_count }} {{ __('employees') }}
                                </span>
                            </td>
                            <td style="padding: 1rem 1rem; font-weight: 800; color: var(--text-main);">
                                {{ number_format((float) $period->total_gross, 2) }} AMD
                            </td>
                            <td style="padding: 1rem 1rem; font-weight: 700; color: #ef4444;">
                                {{ number_format((float) $period->total_deductions, 2) }} AMD
                            </td>
                            <td style="padding: 1rem 1rem; font-weight: 900; color: #22c55e; font-size: 1.05rem;">
                                {{ number_format((float) $period->total_net, 2) }} AMD
                            </td>
                            <td style="padding: 1rem 1rem;">
                                @php
                                    $st = $period->status;
                                    $badge = match($st) {
                                        'locked' => ['bg' => 'rgba(99, 102, 241, 0.15)', 'color' => '#6366f1', 'icon' => 'fa-lock'],
                                        'approved' => ['bg' => 'rgba(34, 197, 94, 0.15)', 'color' => '#22c55e', 'icon' => 'fa-check-double'],
                                        'pending_approval' => ['bg' => 'rgba(245, 158, 11, 0.15)', 'color' => '#f59e0b', 'icon' => 'fa-clock'],
                                        'paid' => ['bg' => 'rgba(5, 150, 105, 0.15)', 'color' => '#059669', 'icon' => 'fa-money-bill-wave'],
                                        default => ['bg' => 'rgba(148, 163, 184, 0.15)', 'color' => '#94a3b8', 'icon' => 'fa-pen-ruler'],
                                    };
                                @endphp
                                <span class="badge" style="background: {{ $badge['bg'] }}; color: {{ $badge['color'] }}; text-transform: uppercase;">
                                    <i class="fa-solid {{ $badge['icon'] }}" style="margin-right: 3px;"></i> {{ str_replace('_', ' ', $st) }}
                                </span>
                            </td>
                            <td style="padding: 1rem 1.25rem; text-align: right;">
                                <div style="display: inline-flex; align-items: center; gap: 0.5rem;">
                                    <a href="{{ route('admin.hr.payroll.preview', $period) }}" class="btn btn-secondary btn-sm" style="border-radius: 8px; font-weight: 600;">
                                        <i class="fa-solid fa-eye"></i> {{ __('Review Sheet') }}
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 3rem 1.5rem; color: var(--text-muted);">
                                <div style="font-size: 2.5rem; color: var(--border-color); margin-bottom: 0.75rem;">
                                    <i class="fa-solid fa-file-invoice-dollar"></i>
                                </div>
                                <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0 0 0.25rem; color: var(--text-main);">
                                    {{ __('No payroll periods yet') }}
                                </h3>
                                <p style="font-size: 0.85rem; margin: 0 0 1rem;">
                                    {{ __('Create a payroll period to start computing salaries with Armenian statutory tax formulas.') }}
                                </p>
                                @can('hr.payroll.calculate')
                                    <button type="button" @click="showCreateModal = true" class="btn btn-primary btn-sm" style="border-radius: 8px;">
                                        <i class="fa-solid fa-plus"></i> {{ __('Create First Period') }}
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($periods->hasPages())
            <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color);">
                {{ $periods->links() }}
            </div>
        @endif
    </div>

    <!-- CREATE PERIOD MODAL -->
    <div x-show="showCreateModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showCreateModal = false" class="card" style="width: 100%; max-width: 500px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ __('Create Payroll Period') }}</h3>
                <button type="button" @click="showCreateModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.hr.payroll.create_period') }}">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Period Name / Title') }} <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="name" value="{{ date('F Y') }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Pay Cycle') }}</label>
                        <select name="cycle_type" class="form-control" required style="border-radius: 10px; height: 42px;">
                            <option value="monthly">{{ __('Monthly (Full Month)') }}</option>
                            <option value="semi_monthly">{{ __('Semi-Monthly (15 Days)') }}</option>
                            <option value="weekly">{{ __('Weekly') }}</option>
                            <option value="custom">{{ __('Custom Date Range') }}</option>
                        </select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Start Date') }} <span style="color: #ef4444;">*</span></label>
                            <input type="date" name="start_date" value="{{ date('Y-m-01') }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                        </div>
                        <div>
                            <label class="form-label">{{ __('End Date') }} <span style="color: #ef4444;">*</span></label>
                            <input type="date" name="end_date" value="{{ date('Y-m-t') }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Timesheet Cutoff Date') }} <span style="color: #ef4444;">*</span></label>
                        <input type="date" name="cutoff_date" value="{{ date('Y-m-t') }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showCreateModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Create & Open') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

