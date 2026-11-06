@extends('layouts.app')

@section('title', __('Work Schedules & Shifts') . ' - ' . $vendor->name)

@section('content')
<div x-data="{
    showAddModal: false,
    showCopyModal: false,
    selectedDate: '{{ $weekStart->toDateString() }}',
    shiftDateForAdd: '{{ $weekStart->toDateString() }}',
    openAddForDay(date) {
        this.shiftDateForAdd = date;
        this.showAddModal = true;
    }
}" style="display: flex; flex-direction: column; gap: 1.5rem;">

    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="min-width: 0; flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-calendar-days"></i>
                </span>
                <div>
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.35rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                        {{ __('Work Schedules & Shifts') }}
                    </h1>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0.25rem 0 0;">
                        {{ __('Roster planning, shift assignment, cross-midnight support, and week copying') }}
                    </p>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            @can('hr.schedules.manage')
                <button type="button" @click="showCopyModal = true" class="btn btn-secondary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px;">
                    <i class="fa-solid fa-copy"></i> {{ __('Copy Week Roster') }}
                </button>
                <button type="button" @click="showAddModal = true" class="btn btn-primary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);">
                    <i class="fa-solid fa-plus-circle"></i> {{ __('Schedule Shift') }}
                </button>
            @endcan
        </div>
    </div>

    <!-- Week Navigation Bar -->
    <div class="card" style="padding: 1rem 1.25rem; border-radius: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            @php
                $prevWeek = $weekStart->copy()->subWeek()->toDateString();
                $nextWeek = $weekStart->copy()->addWeek()->toDateString();
                $curWeek = now()->startOfWeek()->toDateString();
            @endphp
            <a href="{{ route('admin.hr.schedules.index', array_merge(request()->query(), ['date' => $prevWeek])) }}" class="btn btn-secondary btn-sm" style="border-radius: 8px;">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <a href="{{ route('admin.hr.schedules.index', array_merge(request()->query(), ['date' => $curWeek])) }}" class="btn btn-secondary btn-sm" style="border-radius: 8px; font-weight: 700;">
                {{ __('This Week') }}
            </a>
            <a href="{{ route('admin.hr.schedules.index', array_merge(request()->query(), ['date' => $nextWeek])) }}" class="btn btn-secondary btn-sm" style="border-radius: 8px;">
                <i class="fa-solid fa-chevron-right"></i>
            </a>

            <div style="font-size: 1.05rem; font-weight: 800; color: var(--text-main); margin-left: 0.5rem;">
                {{ $weekStart->format('d M') }} — {{ $weekEnd->format('d M, Y') }}
            </div>
        </div>

        <form method="GET" action="{{ route('admin.hr.schedules.index') }}" style="display: flex; align-items: center; gap: 0.75rem;">
            <input type="hidden" name="date" value="{{ $weekStart->toDateString() }}">
            @if($locations->count() > 1)
                <select name="location_id" onchange="this.form.submit()" class="form-control form-control-sm" style="border-radius: 8px; height: 36px; min-width: 160px;">
                    <option value="">{{ __('All Branches') }}</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                    @endforeach
                </select>
            @endif
            <div style="font-size: 0.85rem; font-weight: 700; color: #10b981; background: rgba(16, 185, 129, 0.12); padding: 6px 12px; border-radius: 8px;">
                <i class="fa-solid fa-clock"></i> {{ $shifts->count() }} {{ __('Shifts') }}
            </div>
        </form>
    </div>

    <!-- 7-Day Calendar Grid -->
    <div style="display: grid; grid-template-columns: repeat(7, minmax(170px, 1fr)); gap: 0.75rem; overflow-x: auto; padding-bottom: 0.5rem;">
        @for($dayOffset = 0; $dayOffset < 7; $dayOffset++)
            @php
                $dayDate = $weekStart->copy()->addDays($dayOffset);
                $dateStr = $dayDate->toDateString();
                $isToday = $dayDate->isToday();
                $dayShifts = $shifts->filter(fn($s) => $s->getShiftDateString() === $dateStr);
            @endphp
            <div class="card" style="padding: 0.75rem; border-radius: 12px; background: {{ $isToday ? 'rgba(16, 185, 129, 0.05)' : 'var(--card-bg)' }}; border: {{ $isToday ? '2px solid rgba(16, 185, 129, 0.5)' : '1px solid var(--border-color)' }}; min-height: 480px; display: flex; flex-direction: column;">
                
                <!-- Day Header -->
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 0.75rem;">
                    <div>
                        <div style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: {{ $isToday ? '#10b981' : 'var(--text-muted)' }};">
                            {{ $dayDate->isoFormat('ddd') }}
                        </div>
                        <div style="font-size: 1.15rem; font-weight: 900; color: {{ $isToday ? '#10b981' : 'var(--text-main)' }};">
                            {{ $dayDate->format('d') }}
                        </div>
                    </div>
                    @can('hr.schedules.manage')
                        <button type="button" @click="openAddForDay('{{ $dateStr }}')" class="btn btn-secondary btn-sm" style="padding: 2px 7px; font-size: 0.75rem; border-radius: 6px;" title="{{ __('Add shift') }}">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    @endcan
                </div>

                <!-- Shift Cards -->
                <div style="display: flex; flex-direction: column; gap: 0.5rem; flex: 1;">
                    @forelse($dayShifts as $shift)
                        <div class="card" style="padding: 0.65rem; border-radius: 8px; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 0.35rem;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.35rem;">
                                <div style="font-weight: 700; font-size: 0.85rem; color: var(--text-main); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    {{ $shift->user?->name }}
                                </div>
                                @can('hr.schedules.manage')
                                    <form method="POST" action="{{ route('admin.hr.shifts.destroy', $shift) }}" onsubmit="return confirm('{{ __('Cancel this shift?') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" style="background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 0; font-size: 0.75rem;">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </form>
                                @endcan
                            </div>

                            <div style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.8rem; font-weight: 800; color: #10b981;">
                                <i class="fa-regular fa-clock" style="font-size: 0.75rem;"></i>
                                {{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }}
                                @if($shift->cross_midnight)
                                    <span style="font-size: 0.65rem; background: rgba(239, 68, 68, 0.2); color: #ef4444; padding: 1px 4px; border-radius: 4px;">+1d</span>
                                @endif
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.72rem; color: var(--text-muted);">
                                <span>{{ $shift->location?->name ?? '—' }}</span>
                                <span class="badge" style="background: rgba(255,255,255,0.06); font-size: 0.65rem; text-transform: uppercase;">
                                    {{ $shift->status }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div style="text-align: center; padding: 2rem 0.5rem; color: var(--text-muted); font-size: 0.78rem;">
                            {{ __('No shifts') }}
                        </div>
                    @endforelse
                </div>
            </div>
        @endfor
    </div>

    <!-- SCHEDULE SHIFT MODAL -->
    <div x-show="showAddModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showAddModal = false" class="card" style="width: 100%; max-width: 500px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ __('Schedule Shift') }}</h3>
                <button type="button" @click="showAddModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.hr.shifts.store') }}">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Employee') }} <span style="color: #ef4444;">*</span></label>
                        <select name="user_id" class="form-control" required style="border-radius: 10px; height: 42px;">
                            <option value="">{{ __('Select Employee') }}</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->name }} ({{ $emp->employeeProfile?->position?->title ?? 'Staff' }})</option>
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

                    <div>
                        <label class="form-label">{{ __('Shift Date') }} <span style="color: #ef4444;">*</span></label>
                        <input type="date" name="shift_date" x-model="shiftDateForAdd" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Start Time') }} <span style="color: #ef4444;">*</span></label>
                            <input type="time" name="start_time" value="09:00" class="form-control" required style="border-radius: 10px; height: 42px;">
                        </div>
                        <div>
                            <label class="form-label">{{ __('End Time') }} <span style="color: #ef4444;">*</span></label>
                            <input type="time" name="end_time" value="18:00" class="form-control" required style="border-radius: 10px; height: 42px;">
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="cross_midnight" value="1" id="cross_mid">
                            <label for="cross_mid" style="margin: 0; font-size: 0.88rem; color: var(--text-main);">{{ __('Cross Midnight (Night shift)') }}</label>
                        </div>

                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <label style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">{{ __('Break (min):') }}</label>
                            <input type="number" name="unpaid_break_minutes" value="30" min="0" max="180" style="width: 70px; height: 36px; border-radius: 8px;" class="form-control">
                        </div>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Notes / Tasks') }}</label>
                        <input type="text" name="notes" placeholder="{{ __('Optional shift instructions') }}" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showAddModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Assign Shift') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- COPY WEEK MODAL -->
    <div x-show="showCopyModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showCopyModal = false" class="card" style="width: 100%; max-width: 480px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ __('Copy Week Roster') }}</h3>
                <button type="button" @click="showCopyModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.hr.shifts.copy_week') }}">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Source Week (Any date in source week)') }}</label>
                        <input type="date" name="source_date" value="{{ $weekStart->toDateString() }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Target Week (Any date in target week)') }}</label>
                        <input type="date" name="target_date" value="{{ $weekStart->copy()->addWeek()->toDateString() }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>
                    @if($locations->count() > 1)
                        <div>
                            <label class="form-label">{{ __('Branch (Optional - leave blank for all)') }}</label>
                            <select name="location_id" class="form-control" style="border-radius: 10px; height: 42px;">
                                <option value="">{{ __('All Branches') }}</option>
                                @foreach($locations as $loc)
                                    <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showCopyModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Duplicate Roster') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

