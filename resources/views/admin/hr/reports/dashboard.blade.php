@extends('layouts.app')

@section('title', __('HR & Labor Analytics') . ' - ' . $vendor->name)

@section('content')
<div style="display: flex; flex-direction: column; gap: 1.5rem;">

    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="min-width: 0; flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-chart-pie"></i>
                </span>
                <div>
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.35rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                        {{ __('HR & Labor Cost Analytics') }}
                    </h1>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0.25rem 0 0;">
                        {{ __('Real-time workforce KPIs, attendance trends, departmental labor expenses, and audit exports') }}
                    </p>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            @can('hr.reports.export')
                <a href="{{ route('admin.hr.reports.export', 'attendance') }}" class="btn btn-secondary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px;">
                    <i class="fa-solid fa-file-csv"></i> {{ __('Export Attendance CSV') }}
                </a>
                <a href="{{ route('admin.hr.reports.export', 'payroll') }}" class="btn btn-secondary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px;">
                    <i class="fa-solid fa-file-csv"></i> {{ __('Export Payroll CSV') }}
                </a>
            @endcan
        </div>
    </div>

    <!-- Top KPI Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
        
        <div class="card" style="padding: 1.25rem; border-radius: 16px;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(14, 165, 233, 0.12); color: #0ea5e9; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Active Workforce') }}</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-main);">
                        {{ $metrics['active_employees'] }} <span style="font-size: 0.85rem; color: var(--text-muted);">/ {{ $metrics['total_employees'] }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; border-radius: 16px;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(34, 197, 94, 0.12); color: #22c55e; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Clocked In Today') }}</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #22c55e;">
                        {{ $metrics['clocked_in_today'] }} {{ __('staff') }}
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; border-radius: 16px;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(239, 68, 68, 0.12); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                    <i class="fa-solid fa-user-xmark"></i>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Late Today') }}</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #ef4444;">
                        {{ $metrics['late_today'] }} {{ __('staff') }}
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; border-radius: 16px;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(249, 115, 22, 0.12); color: #f97316; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                    <i class="fa-solid fa-plane-departure"></i>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Pending Leaves') }}</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #f97316;">
                        {{ $metrics['pending_leaves_count'] }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payroll Expense Highlights -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
        <div class="card" style="padding: 1.5rem; border-radius: 16px; background: linear-gradient(135deg, rgba(34, 197, 94, 0.08), rgba(14, 165, 233, 0.05)); border: 1px solid rgba(34, 197, 94, 0.2);">
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">
                {{ __('Current Month Gross Labor') }}
            </div>
            <div style="font-size: 2rem; font-weight: 900; color: var(--text-main); margin-top: 0.5rem;">
                {{ number_format((float) $metrics['current_month_gross_payroll'], 2) }} <span style="font-size: 1rem; color: var(--text-muted);">AMD</span>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.5rem;">
                {{ __('Calculated salary commitments before statutory tax withholding') }}
            </div>
        </div>

        <div class="card" style="padding: 1.5rem; border-radius: 16px; background: linear-gradient(135deg, rgba(59, 130, 246, 0.08), rgba(99, 102, 241, 0.05)); border: 1px solid rgba(59, 130, 246, 0.2);">
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">
                {{ __('Current Month Net Payroll') }}
            </div>
            <div style="font-size: 2rem; font-weight: 900; color: #22c55e; margin-top: 0.5rem;">
                {{ number_format((float) $metrics['current_month_net_payroll'], 2) }} <span style="font-size: 1rem; color: var(--text-muted);">AMD</span>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.5rem;">
                {{ __('Total take-home wages payable directly to employees') }}
            </div>
        </div>

        <div class="card" style="padding: 1.5rem; border-radius: 16px; background: linear-gradient(135deg, rgba(239, 68, 68, 0.08), rgba(245, 158, 11, 0.05)); border: 1px solid rgba(239, 68, 68, 0.2);">
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">
                {{ __('Unpaid Payroll Balance') }}
            </div>
            <div style="font-size: 2rem; font-weight: 900; color: {{ (float)$metrics['unpaid_payroll_balance'] > 0 ? '#ef4444' : '#22c55e' }}; margin-top: 0.5rem;">
                {{ number_format((float) $metrics['unpaid_payroll_balance'], 2) }} <span style="font-size: 1rem; color: var(--text-muted);">AMD</span>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.5rem;">
                {{ __('Outstanding amount awaiting disbursement records') }}
            </div>
        </div>
    </div>

    <!-- Departmental Labor Cost Breakdown -->
    <div class="card" style="border-radius: 16px; padding: 1.5rem;">
        <h2 style="font-size: 1.15rem; font-weight: 800; margin: 0 0 1.25rem; color: var(--text-main);">
            <i class="fa-solid fa-sitemap" style="color: #8b5cf6; margin-right: 6px;"></i> {{ __('Departmental Labor Expense Distribution') }}
        </h2>

        <div class="table-responsive">
            <table class="table" style="vertical-align: middle; margin: 0;">
                <thead style="background: rgba(255,255,255,0.03); border-bottom: 1px solid var(--border-color);">
                    <tr>
                        <th style="padding: 0.85rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Department') }}</th>
                        <th style="padding: 0.85rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Staff Headcount') }}</th>
                        <th style="padding: 0.85rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Total Gross (AMD)') }}</th>
                        <th style="padding: 0.85rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Total Net (AMD)') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deptCosts as $dc)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem 1rem; font-weight: 700; color: var(--text-main);">
                                {{ $dc['department_name'] ?? __('Unassigned') }}
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <span class="badge" style="background: rgba(14, 165, 233, 0.15); color: #0ea5e9; font-weight: 700;">
                                    {{ $dc['employee_count'] }} {{ __('staff') }}
                                </span>
                            </td>
                            <td style="padding: 1rem 1rem; font-weight: 800; color: var(--text-main);">
                                {{ number_format((float) ($dc['total_gross'] ?? 0), 2) }} AMD
                            </td>
                            <td style="padding: 1rem 1rem; font-weight: 900; color: #22c55e;">
                                {{ number_format((float) ($dc['total_net'] ?? 0), 2) }} AMD
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                                {{ __('No departmental payroll data recorded yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

