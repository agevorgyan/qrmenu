@extends('layouts.app')

@section('title', __('Attendance Management') . ' - ' . $vendor->name)

@section('content')
<div x-data="{
    showManualModal: false,
    showAdjustModal: false,
    showKioskModal: false,
    adjustItem: { id: null, employee: '', in: '', out: '', reason: '' },
    qrToken: '',
    qrCountdown: 60,
    openAdjust(att) {
        this.adjustItem = {
            id: att.id,
            employee: att.user ? att.user.name : '',
            in: att.check_in_at ? att.check_in_at.substring(0, 16) : '',
            out: att.check_out_at ? att.check_out_at.substring(0, 16) : '',
            reason: ''
        };
        this.showAdjustModal = true;
    },
    fetchQr() {
        fetch('{{ route('admin.hr.attendance.qr_token') }}')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.qrToken = data.token;
                    this.qrCountdown = data.expires_in;
                }
            });
    },
    startKiosk() {
        this.showKioskModal = true;
        this.fetchQr();
        setInterval(() => {
            if (this.qrCountdown > 1) {
                this.qrCountdown--;
            } else {
                this.fetchQr();
            }
        }, 1000);
    }
}" style="display: flex; flex-direction: column; gap: 1.5rem;">

    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="min-width: 0; flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(20, 184, 166, 0.15); color: #14b8a6; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-clipboard-user"></i>
                </span>
                <div>
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.35rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                        {{ __('Attendance Management') }}
                    </h1>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0.25rem 0 0;">
                        {{ __('Live punch records, GPS/PIN/QR verification, break tracking, and supervisor adjustments') }}
                    </p>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <button type="button" @click="startKiosk()" class="btn btn-secondary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px;">
                <i class="fa-solid fa-qrcode"></i> {{ __('Launch Punch Kiosk / QR') }}
            </button>
            @can('hr.attendance.manage')
                <button type="button" @click="showManualModal = true" class="btn btn-primary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px; box-shadow: 0 4px 14px rgba(20, 184, 166, 0.3);">
                    <i class="fa-solid fa-user-clock"></i> {{ __('Manual Punch Entry') }}
                </button>
            @endcan
        </div>
    </div>

    <!-- Quick Metrics -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-radius: 14px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(34, 197, 94, 0.12); color: #22c55e; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Clocked In Now') }}</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #22c55e;">
                    {{ $records->filter(fn($r) => is_null($r->check_out_at))->count() }}
                </div>
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-radius: 14px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(239, 68, 68, 0.12); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                <i class="fa-solid fa-user-clock"></i>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Late Today') }}</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #ef4444;">
                    {{ $records->filter(fn($r) => $r->is_late)->count() }}
                </div>
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-radius: 14px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                <i class="fa-solid fa-mug-hot"></i>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('On Break') }}</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #f59e0b;">
                    {{ $records->filter(fn($r) => $r->status === 'on_break')->count() }}
                </div>
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-radius: 14px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(14, 165, 233, 0.12); color: #0ea5e9; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                <i class="fa-solid fa-users"></i>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Total Logs for Date') }}</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-main);">{{ $records->total() }}</div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card" style="padding: 1rem; border-radius: 14px;">
        <form method="GET" action="{{ route('admin.hr.attendance.index') }}" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
            <div style="min-width: 170px;">
                <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 2px;">{{ __('Date') }}</label>
                <input type="date" name="date" value="{{ $date }}" class="form-control" style="border-radius: 10px; height: 40px;">
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

            <div style="min-width: 150px;">
                <label style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 2px;">{{ __('Status') }}</label>
                <select name="status" class="form-control" style="border-radius: 10px; height: 40px;">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="present" {{ request('status') === 'present' ? 'selected' : '' }}>{{ __('Present') }}</option>
                    <option value="late" {{ request('status') === 'late' ? 'selected' : '' }}>{{ __('Late') }}</option>
                    <option value="on_break" {{ request('status') === 'on_break' ? 'selected' : '' }}>{{ __('On Break') }}</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>{{ __('Completed') }}</option>
                </select>
            </div>

            <div style="align-self: flex-end; display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="height: 40px; border-radius: 10px; font-weight: 700;">
                    <i class="fa-solid fa-filter"></i> {{ __('Apply') }}
                </button>
                <a href="{{ route('admin.hr.attendance.index') }}" class="btn btn-secondary" style="height: 40px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center;">
                    {{ __('Today') }}
                </a>
            </div>
        </form>
    </div>

    <!-- Attendance Records Table -->
    <div class="card" style="border-radius: 14px; overflow: hidden; padding: 0;">
        <div class="table-responsive">
            <table class="table" style="margin: 0; vertical-align: middle;">
                <thead style="background: rgba(255,255,255,0.03); border-bottom: 1px solid var(--border-color);">
                    <tr>
                        <th style="padding: 1rem 1.25rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Employee') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Branch') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Clock In') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Clock Out') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Total Hours') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Status') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Method') }}</th>
                        <th style="padding: 1rem 1.25rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $rec)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem 1.25rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(20, 184, 166, 0.15); color: #14b8a6; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.95rem; flex-shrink: 0;">
                                        {{ strtoupper(mb_substr($rec->user?->name ?? '?', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; color: var(--text-main);">
                                            {{ $rec->user?->name ?? __('Unknown') }}
                                        </div>
                                        <div style="font-size: 0.78rem; color: var(--text-muted);">
                                            {{ $rec->user?->employeeProfile?->position?->title ?? 'Staff' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <span style="font-size: 0.85rem; color: var(--text-muted);">{{ $rec->location?->name ?? '—' }}</span>
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <div style="font-weight: 700; color: #22c55e;">
                                    {{ $rec->check_in_at ? \Carbon\Carbon::parse($rec->check_in_at)->format('H:i') : '—' }}
                                </div>
                                @if($rec->is_late)
                                    <span style="font-size: 0.72rem; color: #ef4444; font-weight: 600;">+{{ $rec->late_minutes }}m {{ __('late') }}</span>
                                @endif
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <div style="font-weight: 700; color: {{ $rec->check_out_at ? '#ef4444' : '#10b981' }};">
                                    {{ $rec->check_out_at ? \Carbon\Carbon::parse($rec->check_out_at)->format('H:i') : __('Active') }}
                                </div>
                            </td>
                            <td style="padding: 1rem 1rem; font-weight: 800; color: var(--text-main);">
                                {{ $rec->total_hours ? number_format((float) $rec->total_hours, 2) . 'h' : '—' }}
                            </td>
                            <td style="padding: 1rem 1rem;">
                                @php
                                    $st = $rec->status;
                                    $stBadge = match($st) {
                                        'present' => ['bg' => 'rgba(34, 197, 94, 0.15)', 'color' => '#22c55e'],
                                        'late' => ['bg' => 'rgba(239, 68, 68, 0.15)', 'color' => '#ef4444'],
                                        'on_break' => ['bg' => 'rgba(245, 158, 11, 0.15)', 'color' => '#f59e0b'],
                                        default => ['bg' => 'rgba(148, 163, 184, 0.15)', 'color' => '#94a3b8'],
                                    };
                                @endphp
                                <span class="badge" style="background: {{ $stBadge['bg'] }}; color: {{ $stBadge['color'] }}; text-transform: uppercase;">
                                    {{ $st }}
                                </span>
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <span class="badge" style="background: rgba(255,255,255,0.08); text-transform: uppercase;">
                                    {{ $rec->source }}
                                </span>
                            </td>
                            <td style="padding: 1rem 1.25rem; text-align: right;">
                                @can('hr.attendance.manage')
                                    <button type="button" @click="openAdjust({{ json_encode($rec) }})" class="btn btn-secondary btn-sm" style="border-radius: 8px;" title="{{ __('Adjust punch times') }}">
                                        <i class="fa-solid fa-wrench"></i> {{ __('Adjust') }}
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 3rem 1.5rem; color: var(--text-muted);">
                                <div style="font-size: 2.5rem; color: var(--border-color); margin-bottom: 0.75rem;">
                                    <i class="fa-solid fa-calendar-xmark"></i>
                                </div>
                                <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0 0 0.25rem; color: var(--text-main);">
                                    {{ __('No attendance records for this date') }}
                                </h3>
                                <p style="font-size: 0.85rem; margin: 0 0 1rem;">
                                    {{ __('Employees have not punched in yet or no records exist.') }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($records->hasPages())
            <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color);">
                {{ $records->links() }}
            </div>
        @endif
    </div>

    <!-- MODAL: MANUAL ENTRY -->
    <div x-show="showManualModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showManualModal = false" class="card" style="width: 100%; max-width: 500px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ __('Record Manual Punch') }}</h3>
                <button type="button" @click="showManualModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.hr.attendance.manual') }}">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Employee') }} <span style="color: #ef4444;">*</span></label>
                        <select name="user_id" class="form-control" required style="border-radius: 10px; height: 42px;">
                            <option value="">{{ __('Select Employee') }}</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Branch / Location') }} <span style="color: #ef4444;">*</span></label>
                        <select name="location_id" class="form-control" required style="border-radius: 10px; height: 42px;">
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Check In (Date & Time)') }} <span style="color: #ef4444;">*</span></label>
                            <input type="datetime-local" name="check_in_at" value="{{ date('Y-m-d\T09:00') }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                        </div>
                        <div>
                            <label class="form-label">{{ __('Check Out (Date & Time)') }} <span style="color: #ef4444;">*</span></label>
                            <input type="datetime-local" name="check_out_at" value="{{ date('Y-m-d\T18:00') }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Supervisor Note / Reason') }}</label>
                        <input type="text" name="notes" placeholder="{{ __('Forgot badge, offline shift, etc.') }}" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showManualModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save Punch Log') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: ADJUST PUNCH -->
    <div x-show="showAdjustModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showAdjustModal = false" class="card" style="width: 100%; max-width: 500px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">
                    {{ __('Adjust Punch for') }} <span x-text="adjustItem.employee" style="color: #14b8a6;"></span>
                </h3>
                <button type="button" @click="showAdjustModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <form method="POST" :action="'{{ url('admin/hr/attendance') }}/' + adjustItem.id + '/adjust'">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Adjusted Clock In') }} <span style="color: #ef4444;">*</span></label>
                            <input type="datetime-local" name="adjusted_check_in" x-model="adjustItem.in" class="form-control" required style="border-radius: 10px; height: 42px;">
                        </div>
                        <div>
                            <label class="form-label">{{ __('Adjusted Clock Out') }} <span style="color: #ef4444;">*</span></label>
                            <input type="datetime-local" name="adjusted_check_out" x-model="adjustItem.out" class="form-control" required style="border-radius: 10px; height: 42px;">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Audit Reason for Adjustment') }} <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="reason" x-model="adjustItem.reason" placeholder="{{ __('e.g. Employee forgot to punch out at end of shift') }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showAdjustModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save Adjustment') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: KIOSK QR -->
    <div x-show="showKioskModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showKioskModal = false" class="card" style="width: 100%; max-width: 440px; padding: 2rem; border-radius: 20px; text-align: center;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ __('Attendance Kiosk QR') }}</h3>
                <button type="button" @click="showKioskModal = false" style="background: none; border: none; font-size: 1.4rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>

            <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                {{ __('Staff can scan this rotating dynamic QR code using their camera or Self-Service Portal to instantly punch in/out.') }}
            </p>

            <div style="background: #ffffff; padding: 1.5rem; border-radius: 16px; display: inline-block; margin-bottom: 1.5rem; box-shadow: 0 8px 30px rgba(0,0,0,0.3);">
                <!-- Dynamic SVG QR placeholder or QR image -->
                <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(qrToken)" alt="Punch QR" style="width: 220px; height: 220px; display: block;">
            </div>

            <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-size: 0.9rem; font-weight: 700; color: #14b8a6;">
                <i class="fa-solid fa-arrows-rotate fa-spin"></i>
                <span>{{ __('Refreshes in') }} <span x-text="qrCountdown"></span>s</span>
            </div>
        </div>
    </div>
</div>
@endsection

