@extends('layouts.app')

@section('title', __('My Employee Portal') . ' - ' . $vendor->name)

@section('content')
@php
    $latestPunch = $user->attendanceRecords->first();
    $isClockedIn = $latestPunch && is_null($latestPunch->check_out_at);
    $isOnBreak = $isClockedIn && $latestPunch->status === 'on_break';
@endphp
<div x-data="{
    showLeaveModal: false,
    showAdvanceModal: false,
    isClockedIn: {{ $isClockedIn ? 'true' : 'false' }},
    isOnBreak: {{ $isOnBreak ? 'true' : 'false' }},
    loadingPunch: false,
    punchAction(act) {
        this.loadingPunch = true;
        fetch('{{ route('admin.hr.attendance.punch') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ action: act })
        })
        .then(res => res.json())
        .then(data => {
            this.loadingPunch = false;
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Error occurred');
            }
        })
        .catch(err => {
            this.loadingPunch = false;
            alert('Punch request failed');
        });
    }
}" style="display: flex; flex-direction: column; gap: 1.5rem;">

    <!-- Employee Welcome Banner -->
    <div class="card" style="padding: 1.5rem; border-radius: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 1.25rem;">
                <div style="width: 64px; height: 64px; border-radius: 16px; background: linear-gradient(135deg, #a855f7, #6366f1); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 1.8rem; flex-shrink: 0; box-shadow: 0 6px 16px rgba(168, 85, 247, 0.3);">
                    {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                </div>
                <div>
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin: 0;">
                        {{ __('Welcome back, :name', ['name' => $user->name]) }}
                    </h1>
                    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-top: 0.35rem; font-size: 0.85rem; color: var(--text-muted);">
                        <span><i class="fa-solid fa-user-tag" style="color: #0ea5e9;"></i> {{ $user->employeeProfile?->position?->title ?? __('Team Member') }}</span>
                        <span><i class="fa-solid fa-sitemap" style="color: #8b5cf6;"></i> {{ $user->employeeProfile?->department?->name ?? '—' }}</span>
                        <span><i class="fa-solid fa-location-dot" style="color: #14b8a6;"></i> {{ $user->location?->name ?? __('All Branches') }}</span>
                    </div>
                </div>
            </div>

            <!-- Quick Punch Controls -->
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; background: rgba(255,255,255,0.03); padding: 0.75rem 1rem; border-radius: 14px; border: 1px solid var(--border-color);">
                <div style="margin-right: 0.5rem;">
                    <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">{{ __('Attendance Status') }}</div>
                    <div style="font-size: 0.95rem; font-weight: 800; color: {{ $isClockedIn ? ($isOnBreak ? '#f59e0b' : '#22c55e') : 'var(--text-muted)' }};">
                        @if(!$isClockedIn)
                            <i class="fa-solid fa-circle-xmark"></i> {{ __('Clocked Out') }}
                        @elseif($isOnBreak)
                            <i class="fa-solid fa-mug-hot"></i> {{ __('On Break') }}
                        @else
                            <i class="fa-solid fa-circle-check"></i> {{ __('Clocked In') }}
                        @endif
                    </div>
                </div>

                <template x-if="!isClockedIn">
                    <button type="button" @click="punchAction('clock_in')" :disabled="loadingPunch" class="btn btn-primary" style="font-size: 0.88rem; font-weight: 700; border-radius: 10px; background: #22c55e; border-color: #22c55e;">
                        <i class="fa-solid fa-play"></i> {{ __('Clock In') }}
                    </button>
                </template>

                <template x-if="isClockedIn && !isOnBreak">
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="button" @click="punchAction('break_start')" :disabled="loadingPunch" class="btn btn-secondary" style="font-size: 0.88rem; font-weight: 700; border-radius: 10px;">
                            <i class="fa-solid fa-mug-hot"></i> {{ __('Start Break') }}
                        </button>
                        <button type="button" @click="punchAction('clock_out')" :disabled="loadingPunch" class="btn btn-danger" style="font-size: 0.88rem; font-weight: 700; border-radius: 10px;">
                            <i class="fa-solid fa-stop"></i> {{ __('Clock Out') }}
                        </button>
                    </div>
                </template>

                <template x-if="isClockedIn && isOnBreak">
                    <button type="button" @click="punchAction('break_end')" :disabled="loadingPunch" class="btn btn-primary" style="font-size: 0.88rem; font-weight: 700; border-radius: 10px; background: #f59e0b; border-color: #f59e0b;">
                        <i class="fa-solid fa-play"></i> {{ __('End Break') }}
                    </button>
                </template>
            </div>
        </div>
    </div>

    <!-- 3-Column Highlights -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
        
        <!-- Leave Balances -->
        <div class="card" style="padding: 1.25rem; border-radius: 16px; display: flex; flex-direction: column; justify-content: space-between; gap: 1rem;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <div style="font-weight: 800; font-size: 1.05rem; color: var(--text-main);">
                        <i class="fa-solid fa-plane-departure" style="color: #f97316; margin-right: 6px;"></i> {{ __('Time Off & Leaves') }}
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                    @forelse($user->leaveBalances as $bal)
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0.75rem; background: rgba(255,255,255,0.02); border-radius: 8px;">
                            <span style="font-size: 0.85rem; font-weight: 600;">{{ $bal->leaveType?->name }}</span>
                            <span style="font-weight: 800; color: #f97316;">{{ $bal->remaining_days }} / {{ $bal->allocated_days }} {{ __('days') }}</span>
                        </div>
                    @empty
                        <div style="font-size: 0.82rem; color: var(--text-muted);">{{ __('No active leave balance allocations.') }}</div>
                    @endforelse
                </div>
            </div>

            <button type="button" @click="showLeaveModal = true" class="btn btn-secondary btn-sm" style="border-radius: 10px; font-weight: 700; width: 100%;">
                <i class="fa-solid fa-calendar-plus"></i> {{ __('Apply for Leave') }}
            </button>
        </div>

        <!-- Upcoming Shifts -->
        <div class="card" style="padding: 1.25rem; border-radius: 16px; display: flex; flex-direction: column; justify-content: space-between; gap: 1rem;">
            <div>
                <div style="font-weight: 800; font-size: 1.05rem; color: var(--text-main); margin-bottom: 0.75rem;">
                    <i class="fa-solid fa-calendar-days" style="color: #10b981; margin-right: 6px;"></i> {{ __('Upcoming Scheduled Shifts') }}
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    @forelse($user->workShifts as $sh)
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0.75rem; background: rgba(255,255,255,0.02); border-radius: 8px; font-size: 0.85rem;">
                            <span style="font-weight: 700;">{{ \Carbon\Carbon::parse($sh->shift_date)->format('D, d M') }}</span>
                            <span style="color: #10b981; font-weight: 800;">{{ substr($sh->start_time, 0, 5) }} - {{ substr($sh->end_time, 0, 5) }}</span>
                        </div>
                    @empty
                        <div style="font-size: 0.82rem; color: var(--text-muted); text-align: center; padding: 1rem;">
                            {{ __('No shifts scheduled for the coming week.') }}
                        </div>
                    @endforelse
                </div>
            </div>

            <div style="font-size: 0.78rem; color: var(--text-muted); text-align: center;">
                {{ __('Notify supervisor if shift changes are needed.') }}
            </div>
        </div>

        <!-- Salary Advance -->
        <div class="card" style="padding: 1.25rem; border-radius: 16px; display: flex; flex-direction: column; justify-content: space-between; gap: 1rem;">
            <div>
                <div style="font-weight: 800; font-size: 1.05rem; color: var(--text-main); margin-bottom: 0.75rem;">
                    <i class="fa-solid fa-hand-holding-dollar" style="color: #d97706; margin-right: 6px;"></i> {{ __('Salary Advances') }}
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    @forelse($user->employeeAdvances as $adv)
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0.75rem; background: rgba(255,255,255,0.02); border-radius: 8px; font-size: 0.85rem;">
                            <span>{{ \Carbon\Carbon::parse($adv->requested_date)->format('d.m.Y') }}</span>
                            <span style="font-weight: 700;">{{ number_format((float) $adv->amount, 2) }} AMD</span>
                            <span class="badge" style="background: rgba(255,255,255,0.08); text-transform: uppercase; font-size: 0.68rem;">{{ $adv->status }}</span>
                        </div>
                    @empty
                        <div style="font-size: 0.82rem; color: var(--text-muted); text-align: center; padding: 1rem;">
                            {{ __('No advances requested.') }}
                        </div>
                    @endforelse
                </div>
            </div>

            <button type="button" @click="showAdvanceModal = true" class="btn btn-secondary btn-sm" style="border-radius: 10px; font-weight: 700; width: 100%;">
                <i class="fa-solid fa-plus"></i> {{ __('Request Advance') }}
            </button>
        </div>
    </div>

    <!-- My Recent Payslips Table -->
    <div class="card" style="border-radius: 16px; overflow: hidden; padding: 0;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">
                <i class="fa-solid fa-receipt" style="color: #22c55e; margin-right: 6px;"></i> {{ __('My Payslips History') }}
            </h2>
        </div>

        <div class="table-responsive">
            <table class="table" style="margin: 0; vertical-align: middle;">
                <thead style="background: rgba(255,255,255,0.03); border-bottom: 1px solid var(--border-color);">
                    <tr>
                        <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Period') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Hours') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Gross Pay') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Deductions') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Net Salary') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Payment') }}</th>
                        <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; text-align: right;">{{ __('View Payslip') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($user->payrollItems as $item)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem 1.5rem; font-weight: 700; color: var(--text-main);">
                                {{ $item->period?->name }}
                            </td>
                            <td style="padding: 1rem 1rem;">
                                {{ (float) $item->regular_hours }}h
                                @if((float)$item->overtime_hours > 0)
                                    <span style="color: #ef4444; font-weight: 700;">(+{{ (float)$item->overtime_hours }}h)</span>
                                @endif
                            </td>
                            <td style="padding: 1rem 1rem; font-weight: 700;">
                                {{ number_format((float) $item->gross_pay, 2) }} AMD
                            </td>
                            <td style="padding: 1rem 1rem; color: #ef4444; font-weight: 600;">
                                -{{ number_format((float) $item->total_deductions, 2) }} AMD
                            </td>
                            <td style="padding: 1rem 1rem; font-weight: 900; color: #22c55e; font-size: 1.05rem;">
                                {{ number_format((float) $item->net_pay, 2) }} AMD
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <span class="badge" style="background: rgba(255,255,255,0.08); text-transform: uppercase;">
                                    {{ $item->payment_status }}
                                </span>
                            </td>
                            <td style="padding: 1rem 1.5rem; text-align: right;">
                                <a href="{{ route('admin.hr.payroll.payslip', $item) }}" target="_blank" class="btn btn-secondary btn-sm" style="border-radius: 8px; font-weight: 600;">
                                    <i class="fa-solid fa-file-invoice"></i> {{ __('View / Print') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                                {{ __('No payslips calculated for you yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- REQUEST LEAVE MODAL -->
    <div x-show="showLeaveModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showLeaveModal = false" class="card" style="width: 100%; max-width: 500px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ __('Apply for Leave / Time Off') }}</h3>
                <button type="button" @click="showLeaveModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.hr.self_service.leaves.request') }}" enctype="multipart/form-data">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Leave Category') }} <span style="color: #ef4444;">*</span></label>
                        <select name="leave_type_id" class="form-control" required style="border-radius: 10px; height: 42px;">
                            @foreach($leaveTypes as $lt)
                                <option value="{{ $lt->id }}">{{ $lt->name }} ({{ $lt->is_paid ? __('Paid') : __('Unpaid') }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Start Date') }} <span style="color: #ef4444;">*</span></label>
                            <input type="date" name="start_date" value="{{ date('Y-m-d') }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                        </div>
                        <div>
                            <label class="form-label">{{ __('End Date') }} <span style="color: #ef4444;">*</span></label>
                            <input type="date" name="end_date" value="{{ date('Y-m-d') }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Reason / Details') }} <span style="color: #ef4444;">*</span></label>
                        <textarea name="reason" rows="2" class="form-control" required style="border-radius: 10px;"></textarea>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Attachment (Certificate / note - optional)') }}</label>
                        <input type="file" name="attachment" class="form-control" style="border-radius: 10px;">
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showLeaveModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Submit Application') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- REQUEST ADVANCE MODAL -->
    <div x-show="showAdvanceModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showAdvanceModal = false" class="card" style="width: 100%; max-width: 480px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ __('Request Salary Advance') }}</h3>
                <button type="button" @click="showAdvanceModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.hr.self_service.advances.request') }}">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Advance Amount (AMD)') }} <span style="color: #ef4444;">*</span></label>
                        <input type="number" step="1000" min="1000" name="amount" placeholder="{{ __('e.g. 50000') }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Reason') }} <span style="color: #ef4444;">*</span></label>
                        <textarea name="reason" rows="2" class="form-control" required style="border-radius: 10px;"></textarea>
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showAdvanceModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Submit Request') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
