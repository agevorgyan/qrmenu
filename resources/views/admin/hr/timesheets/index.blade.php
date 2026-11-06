@extends('layouts.app')

@section('title', __('Timesheets Management') . ' - ' . $vendor->name)

@section('content')
<div x-data="{
    showGenModal: false,
    showEditModal: false,
    editTs: { id: null, employee: '', reg: 0, ot: 0, night: 0, holiday: 0, paid_leave: 0, unpaid_leave: 0, notes: '' },
    openEdit(ts) {
        this.editTs = {
            id: ts.id,
            employee: ts.user ? ts.user.name : '',
            reg: parseFloat(ts.regular_hours || 0),
            ot: parseFloat(ts.overtime_hours || 0),
            night: parseFloat(ts.night_hours || 0),
            holiday: parseFloat(ts.holiday_hours || 0),
            paid_leave: parseFloat(ts.paid_leave_hours || 0),
            unpaid_leave: parseFloat(ts.unpaid_leave_hours || 0),
            notes: ts.notes || ''
        };
        this.showEditModal = true;
    }
}" style="display: flex; flex-direction: column; gap: 1.5rem;">

    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="min-width: 0; flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-business-time"></i>
                </span>
                <div>
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.35rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                        {{ __('Timesheets Management') }}
                    </h1>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0.25rem 0 0;">
                        {{ __('Aggregated working hours, overtime premiums, night shifts, and manager sign-off') }}
                    </p>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <a href="{{ route('admin.hr.payroll.index') }}" class="btn btn-secondary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px;">
                <i class="fa-solid fa-file-invoice-dollar"></i> {{ __('Proceed to Payroll') }}
            </a>
            @can('hr.timesheets.manage')
                <button type="button" @click="showGenModal = true" class="btn btn-primary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.3);">
                    <i class="fa-solid fa-calculator"></i> {{ __('Compile Timesheets') }}
                </button>
            @endcan
        </div>
    </div>

    <!-- Quick Hours Metrics -->
    @php
        $totalReg = $timesheets->sum('regular_hours');
        $totalOt = $timesheets->sum('overtime_hours');
        $totalNight = $timesheets->sum('night_hours');
        $approvedCount = $timesheets->filter(fn($t) => $t->status === 'approved')->count();
    @endphp
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-radius: 14px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(14, 165, 233, 0.12); color: #0ea5e9; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Regular Hours') }}</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-main);">{{ number_format((float) $totalReg, 1) }}h</div>
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-radius: 14px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(239, 68, 68, 0.12); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Overtime Hours') }}</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #ef4444;">{{ number_format((float) $totalOt, 1) }}h</div>
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-radius: 14px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(139, 92, 246, 0.12); color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                <i class="fa-solid fa-moon"></i>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Night Hours') }}</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #8b5cf6;">{{ number_format((float) $totalNight, 1) }}h</div>
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-radius: 14px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(34, 197, 94, 0.12); color: #22c55e; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                <i class="fa-solid fa-signature"></i>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Approved') }}</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #22c55e;">
                    {{ $approvedCount }} / {{ $timesheets->count() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Period Filter Bar -->
    <div class="card" style="padding: 1rem; border-radius: 14px;">
        <form method="GET" action="{{ route('admin.hr.timesheets.index') }}" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
            <div style="min-width: 160px;">
                <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 2px;">{{ __('Period Start') }}</label>
                <input type="date" name="start_date" value="{{ $periodStart->toDateString() }}" class="form-control" style="border-radius: 10px; height: 40px;">
            </div>

            <div style="min-width: 160px;">
                <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 2px;">{{ __('Period End') }}</label>
                <input type="date" name="end_date" value="{{ $periodEnd->toDateString() }}" class="form-control" style="border-radius: 10px; height: 40px;">
            </div>

            @if($locations->count() > 1)
                <div style="min-width: 160px;">
                    <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 2px;">{{ __('Branch') }}</label>
                    <select name="location_id" class="form-control" style="border-radius: 10px; height: 40px;">
                        <option value="">{{ __('All Branches') }}</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div style="align-self: flex-end; display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="height: 40px; border-radius: 10px; font-weight: 700;">
                    <i class="fa-solid fa-filter"></i> {{ __('View Period') }}
                </button>
            </div>
        </form>
    </div>

    <!-- Timesheets Table -->
    <div class="card" style="border-radius: 14px; overflow: hidden; padding: 0;">
        <div class="table-responsive">
            <table class="table" style="margin: 0; vertical-align: middle;">
                <thead style="background: rgba(255,255,255,0.03); border-bottom: 1px solid var(--border-color);">
                    <tr>
                        <th style="padding: 1rem 1.25rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Employee') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Regular') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Overtime') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Night') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Paid Leave') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Total Worked') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Status') }}</th>
                        <th style="padding: 1rem 1.25rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($timesheets as $ts)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem 1.25rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.95rem; flex-shrink: 0;">
                                        {{ strtoupper(mb_substr($ts->user?->name ?? '?', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; color: var(--text-main);">
                                            {{ $ts->user?->name ?? __('Unknown') }}
                                        </div>
                                        <div style="font-size: 0.78rem; color: var(--text-muted);">
                                            {{ $ts->user?->employeeProfile?->position?->title ?? 'Staff' }} • {{ $ts->user?->employeeProfile?->department?->name ?? '—' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 1rem 1rem; font-weight: 700; color: var(--text-main);">
                                {{ number_format((float) $ts->regular_hours, 2) }}h
                            </td>
                            <td style="padding: 1rem 1rem; font-weight: 700; color: #ef4444;">
                                {{ number_format((float) $ts->overtime_hours, 2) }}h
                            </td>
                            <td style="padding: 1rem 1rem; color: #8b5cf6;">
                                {{ number_format((float) $ts->night_hours, 2) }}h
                            </td>
                            <td style="padding: 1rem 1rem; color: #f97316;">
                                {{ number_format((float) $ts->paid_leave_hours, 2) }}h
                            </td>
                            <td style="padding: 1rem 1rem; font-weight: 800; color: #22c55e;">
                                {{ number_format((float) $ts->worked_hours, 2) }}h
                            </td>
                            <td style="padding: 1rem 1rem;">
                                @if($ts->status === 'approved')
                                    <span class="badge" style="background: rgba(34, 197, 94, 0.15); color: #22c55e;">
                                        <i class="fa-solid fa-check-double"></i> {{ __('Approved') }}
                                    </span>
                                @else
                                    <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; text-transform: uppercase;">
                                        {{ $ts->status }}
                                    </span>
                                @endif
                            </td>
                            <td style="padding: 1rem 1.25rem; text-align: right;">
                                <div style="display: inline-flex; align-items: center; gap: 0.5rem;">
                                    @if($ts->status !== 'approved')
                                        @can('hr.timesheets.manage')
                                            <button type="button" @click="openEdit({{ json_encode($ts) }})" class="btn btn-secondary btn-sm" style="border-radius: 6px;">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                        @endcan
                                        @can('hr.timesheets.approve')
                                            <form method="POST" action="{{ route('admin.hr.timesheets.approve', $ts) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-primary btn-sm" style="border-radius: 6px; font-weight: 700;">
                                                    <i class="fa-solid fa-check"></i> {{ __('Approve') }}
                                                </button>
                                            </form>
                                        @endcan
                                    @else
                                        <span style="font-size: 0.75rem; color: var(--text-muted);">
                                            {{ __('Signed by') }} {{ $ts->approver?->name ?? 'Manager' }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 3rem 1.5rem; color: var(--text-muted);">
                                <div style="font-size: 2.5rem; color: var(--border-color); margin-bottom: 0.75rem;">
                                    <i class="fa-solid fa-business-time"></i>
                                </div>
                                <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0 0 0.25rem; color: var(--text-main);">
                                    {{ __('No timesheets for this period') }}
                                </h3>
                                <p style="font-size: 0.85rem; margin: 0 0 1rem;">
                                    {{ __('Compile timesheets to aggregate attendance punches into regular and overtime hours.') }}
                                </p>
                                @can('hr.timesheets.manage')
                                    <button type="button" @click="showGenModal = true" class="btn btn-primary btn-sm" style="border-radius: 8px;">
                                        <i class="fa-solid fa-calculator"></i> {{ __('Compile Timesheets Now') }}
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- COMPILE MODAL -->
    <div x-show="showGenModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showGenModal = false" class="card" style="width: 100%; max-width: 480px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ __('Compile Period Timesheets') }}</h3>
                <button type="button" @click="showGenModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                {{ __('This calculates daily attendance logs, detects overtime hours above 8h/day or 40h/week, separates night hours (22:00-06:00), and links approved leaves.') }}
            </p>
            <form method="POST" action="{{ route('admin.hr.timesheets.generate') }}">
                @csrf
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Start Date') }} <span style="color: #ef4444;">*</span></label>
                        <input type="date" name="start_date" value="{{ $periodStart->toDateString() }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('End Date') }} <span style="color: #ef4444;">*</span></label>
                        <input type="date" name="end_date" value="{{ $periodEnd->toDateString() }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showGenModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Compile & Save') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div x-show="showEditModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showEditModal = false" class="card" style="width: 100%; max-width: 500px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">
                    {{ __('Edit Timesheet for') }} <span x-text="editTs.employee" style="color: #f59e0b;"></span>
                </h3>
                <button type="button" @click="showEditModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <form method="POST" :action="'{{ url('admin/hr/timesheets') }}/' + editTs.id">
                @csrf
                @method('PUT')
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Regular Hours') }}</label>
                        <input type="number" step="0.01" min="0" name="regular_hours" x-model="editTs.reg" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Overtime Hours') }}</label>
                        <input type="number" step="0.01" min="0" name="overtime_hours" x-model="editTs.ot" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Night Hours') }}</label>
                        <input type="number" step="0.01" min="0" name="night_hours" x-model="editTs.night" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Holiday Hours') }}</label>
                        <input type="number" step="0.01" min="0" name="holiday_hours" x-model="editTs.holiday" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Paid Leave (h)') }}</label>
                        <input type="number" step="0.01" min="0" name="paid_leave_hours" x-model="editTs.paid_leave" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Unpaid Leave (h)') }}</label>
                        <input type="number" step="0.01" min="0" name="unpaid_leave_hours" x-model="editTs.unpaid_leave" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label class="form-label">{{ __('Manager Notes') }}</label>
                        <input type="text" name="notes" x-model="editTs.notes" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showEditModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

