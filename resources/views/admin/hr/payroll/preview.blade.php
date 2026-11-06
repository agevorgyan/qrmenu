@extends('layouts.app')

@section('title', $period->name . ' - ' . __('Payroll Calculation') . ' - ' . $vendor->name)

@section('content')
<div style="display: flex; flex-direction: column; gap: 1.5rem;">

    <!-- Breadcrumb & Top Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; color: var(--text-muted);">
            <a href="{{ route('admin.hr.payroll.index') }}" style="color: var(--text-muted); text-decoration: none;">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Payroll Periods') }}
            </a>
            <span>/</span>
            <span style="color: var(--text-main); font-weight: 700;">{{ $period->name }}</span>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            @if($period->status !== 'locked')
                @can('hr.payroll.calculate')
                    <form method="POST" action="{{ route('admin.hr.payroll.calculate', $period) }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm" style="border-radius: 10px; font-weight: 700;">
                            <i class="fa-solid fa-calculator"></i> {{ __('Recalculate Sheet') }}
                        </button>
                    </form>
                @endcan

                @if($period->status === 'pending_approval')
                    @can('hr.payroll.approve')
                        <form method="POST" action="{{ route('admin.hr.payroll.approve', $period) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm" style="border-radius: 10px; font-weight: 700; background: #22c55e; border-color: #22c55e;">
                                <i class="fa-solid fa-check-double"></i> {{ __('Approve Payroll') }}
                            </button>
                        </form>
                    @endcan
                @endif

                @if(in_array($period->status, ['approved', 'pending_approval']))
                    @can('hr.payroll.lock')
                        <form method="POST" action="{{ route('admin.hr.payroll.lock', $period) }}" onsubmit="return confirm('{{ __('Locking this period will freeze all payroll numbers permanently and create an immutable historical snapshot. Proceed?') }}');">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-sm" style="border-radius: 10px; font-weight: 700;">
                                <i class="fa-solid fa-lock"></i> {{ __('Lock & Finalize Period') }}
                            </button>
                        </form>
                    @endcan
                @endif
            @else
                <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #6366f1; padding: 6px 12px; font-size: 0.85rem;">
                    <i class="fa-solid fa-lock"></i> {{ __('Locked by') }} {{ $period->locker?->name ?? 'System' }} {{ __('on') }} {{ \Carbon\Carbon::parse($period->locked_at)->format('d.m.Y H:i') }}
                </span>
            @endif
        </div>
    </div>

    <!-- Period KPI Summary Banner -->
    <div class="card" style="padding: 1.5rem; border-radius: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1.25rem;">
            <div>
                <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    {{ $period->name }}
                </h1>
                <div style="font-size: 0.88rem; color: var(--text-muted); margin-top: 0.25rem;">
                    {{ \Carbon\Carbon::parse($period->start_date)->format('d.m.Y') }} — {{ \Carbon\Carbon::parse($period->end_date)->format('d.m.Y') }}
                    • {{ __('Cutoff:') }} {{ \Carbon\Carbon::parse($period->cutoff_date)->format('d.m.Y') }}
                    • {{ $period->items->count() }} {{ __('Staff Processed') }}
                </div>
            </div>

            @php
                $st = $period->status;
                $stColor = match($st) {
                    'locked' => ['bg' => 'rgba(99, 102, 241, 0.15)', 'color' => '#6366f1'],
                    'approved' => ['bg' => 'rgba(34, 197, 94, 0.15)', 'color' => '#22c55e'],
                    'pending_approval' => ['bg' => 'rgba(245, 158, 11, 0.15)', 'color' => '#f59e0b'],
                    default => ['bg' => 'rgba(148, 163, 184, 0.15)', 'color' => '#94a3b8'],
                };
            @endphp
            <span class="badge" style="background: {{ $stColor['bg'] }}; color: {{ $stColor['color'] }}; font-size: 0.85rem; padding: 6px 14px; text-transform: uppercase;">
                {{ str_replace('_', ' ', $st) }}
            </span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.25rem; margin-top: 1.25rem;">
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Gross Total') }}</div>
                <div style="font-size: 1.45rem; font-weight: 800; color: var(--text-main);">
                    {{ number_format((float) $period->total_gross, 2) }} <span style="font-size: 0.8rem; color: var(--text-muted);">AMD</span>
                </div>
            </div>

            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Income Tax (20%)') }}</div>
                <div style="font-size: 1.45rem; font-weight: 800; color: #ef4444;">
                    {{ number_format((float) $period->items->sum('income_tax'), 2) }} <span style="font-size: 0.8rem; color: var(--text-muted);">AMD</span>
                </div>
            </div>

            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Pension Fund (5%)') }}</div>
                <div style="font-size: 1.45rem; font-weight: 800; color: #f97316;">
                    {{ number_format((float) $period->items->sum('pension_contribution'), 2) }} <span style="font-size: 0.8rem; color: var(--text-muted);">AMD</span>
                </div>
            </div>

            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Military Stamp Duty') }}</div>
                <div style="font-size: 1.45rem; font-weight: 800; color: #8b5cf6;">
                    {{ number_format((float) $period->items->sum('military_stamp_duty'), 2) }} <span style="font-size: 0.8rem; color: var(--text-muted);">AMD</span>
                </div>
            </div>

            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Net Payable') }}</div>
                <div style="font-size: 1.6rem; font-weight: 900; color: #22c55e;">
                    {{ number_format((float) $period->total_net, 2) }} <span style="font-size: 0.8rem; color: var(--text-muted);">AMD</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Employee Payroll Sheet Table -->
    <div class="card" style="border-radius: 14px; overflow: hidden; padding: 0;">
        <div class="table-responsive">
            <table class="table" style="margin: 0; vertical-align: middle;">
                <thead style="background: rgba(255,255,255,0.03); border-bottom: 1px solid var(--border-color);">
                    <tr>
                        <th style="padding: 1rem 1.25rem; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Employee') }}</th>
                        <th style="padding: 1rem 0.75rem; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Hours (Reg/OT/Night)') }}</th>
                        <th style="padding: 1rem 0.75rem; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Base / OT Pay') }}</th>
                        <th style="padding: 1rem 0.75rem; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Gross Total') }}</th>
                        <th style="padding: 1rem 0.75rem; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Inc. Tax (20%)') }}</th>
                        <th style="padding: 1rem 0.75rem; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Pension (5%)') }}</th>
                        <th style="padding: 1rem 0.75rem; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Military') }}</th>
                        <th style="padding: 1rem 0.75rem; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Advances') }}</th>
                        <th style="padding: 1rem 0.75rem; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Net Pay') }}</th>
                        <th style="padding: 1rem 1.25rem; font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; text-align: right;">{{ __('Payslip') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($period->items as $item)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem 1.25rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(34, 197, 94, 0.15); color: #22c55e; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem; flex-shrink: 0;">
                                        {{ strtoupper(mb_substr($item->user?->name ?? '?', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; color: var(--text-main);">
                                            {{ $item->user?->name ?? __('Unknown') }}
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                                            {{ $item->user?->employeeProfile?->position?->title ?? 'Staff' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 1rem 0.75rem; font-size: 0.85rem;">
                                <span style="font-weight: 700;">{{ (float) $item->regular_hours }}h</span>
                                @if((float) $item->overtime_hours > 0)
                                    / <span style="color: #ef4444; font-weight: 700;">{{ (float) $item->overtime_hours }}h OT</span>
                                @endif
                                @if((float) $item->night_hours > 0)
                                    / <span style="color: #8b5cf6;">{{ (float) $item->night_hours }}h N</span>
                                @endif
                            </td>
                            <td style="padding: 1rem 0.75rem; font-size: 0.85rem;">
                                <div>{{ number_format((float) $item->base_pay, 2) }}</div>
                                @if((float) $item->overtime_pay > 0)
                                    <div style="font-size: 0.75rem; color: #ef4444;">+{{ number_format((float) $item->overtime_pay, 2) }} OT</div>
                                @endif
                            </td>
                            <td style="padding: 1rem 0.75rem; font-weight: 800; color: var(--text-main);">
                                {{ number_format((float) $item->gross_pay, 2) }}
                            </td>
                            <td style="padding: 1rem 0.75rem; color: #ef4444; font-weight: 600;">
                                -{{ number_format((float) $item->income_tax, 2) }}
                            </td>
                            <td style="padding: 1rem 0.75rem; color: #f97316; font-weight: 600;">
                                -{{ number_format((float) $item->pension_contribution, 2) }}
                            </td>
                            <td style="padding: 1rem 0.75rem; color: #8b5cf6; font-weight: 600;">
                                -{{ number_format((float) $item->military_stamp_duty, 2) }}
                            </td>
                            <td style="padding: 1rem 0.75rem; color: #d97706; font-weight: 600;">
                                @if((float) $item->advance_deductions > 0)
                                    -{{ number_format((float) $item->advance_deductions, 2) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td style="padding: 1rem 0.75rem; font-weight: 900; color: #22c55e; font-size: 0.95rem;">
                                {{ number_format((float) $item->net_pay, 2) }}
                            </td>
                            <td style="padding: 1rem 1.25rem; text-align: right;">
                                <a href="{{ route('admin.hr.payroll.payslip', $item) }}" target="_blank" class="btn btn-secondary btn-sm" style="border-radius: 6px; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-receipt"></i> {{ __('Payslip') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 3rem 1.5rem; color: var(--text-muted);">
                                <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0 0 0.5rem; color: var(--text-main);">
                                    {{ __('No payroll items generated yet') }}
                                </h3>
                                <p style="font-size: 0.85rem; margin: 0 0 1rem;">
                                    {{ __('Click "Recalculate Sheet" to run calculations for all active employees and timesheets in this period.') }}
                                </p>
                                @can('hr.payroll.calculate')
                                    <form method="POST" action="{{ route('admin.hr.payroll.calculate', $period) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm" style="border-radius: 8px;">
                                            <i class="fa-solid fa-calculator"></i> {{ __('Run Period Calculation') }}
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

