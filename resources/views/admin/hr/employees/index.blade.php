@extends('layouts.app')

@section('title', __('Employees Directory') . ' - ' . $vendor->name)

@section('content')
<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="min-width: 0; flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(14, 165, 233, 0.15); color: #0ea5e9; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-user-group"></i>
                </span>
                <div>
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.35rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                        {{ __('Employees Directory') }}
                    </h1>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0.25rem 0 0;">
                        {{ __('Comprehensive workforce directory, positions, contracts, and employment records') }}
                    </p>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <a href="{{ route('admin.team.index') }}" class="btn btn-secondary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px;">
                <i class="fa-solid fa-user-shield"></i> {{ __('Roles & Permissions') }}
            </a>
            <a href="{{ route('admin.hr.reports.dashboard') }}" class="btn btn-secondary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px;">
                <i class="fa-solid fa-chart-pie"></i> {{ __('HR Analytics') }}
            </a>
            <a href="{{ route('admin.team.index') }}" class="btn btn-primary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px; box-shadow: 0 4px 14px rgba(14, 165, 233, 0.3);">
                <i class="fa-solid fa-user-plus"></i> {{ __('Add / Invite Staff') }}
            </a>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-radius: 14px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(14, 165, 233, 0.12); color: #0ea5e9; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                <i class="fa-solid fa-users"></i>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Total Workforce') }}</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-main);">{{ $employees->total() }}</div>
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-radius: 14px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(34, 197, 94, 0.12); color: #22c55e; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Active Staff') }}</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-main);">
                    {{ $employees->filter(fn($e) => ($e->employeeProfile?->employment_status ?? 'active') === 'active')->count() }}
                </div>
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-radius: 14px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(249, 115, 22, 0.12); color: #f97316; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                <i class="fa-solid fa-plane-departure"></i>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('On Leave') }}</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-main);">
                    {{ $employees->filter(fn($e) => ($e->employeeProfile?->employment_status ?? '') === 'on_leave')->count() }}
                </div>
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-radius: 14px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(139, 92, 246, 0.12); color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                <i class="fa-solid fa-sitemap"></i>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Departments') }}</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-main);">{{ $departments->count() }}</div>
            </div>
        </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="card" style="padding: 1rem; border-radius: 14px;">
        <form method="GET" action="{{ route('admin.hr.employees.index') }}" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 220px; position: relative;">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by name, email, phone...') }}" class="form-control" style="border-radius: 10px; padding-left: 2.25rem; height: 42px;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.9rem;"></i>
            </div>

            <div style="min-width: 160px;">
                <select name="department_id" class="form-control" style="border-radius: 10px; height: 42px;">
                    <option value="">{{ __('All Departments') }}</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                            {{ $dept->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="min-width: 150px;">
                <select name="position_id" class="form-control" style="border-radius: 10px; height: 42px;">
                    <option value="">{{ __('All Positions') }}</option>
                    @foreach($positions as $pos)
                        <option value="{{ $pos->id }}" {{ request('position_id') == $pos->id ? 'selected' : '' }}>
                            {{ $pos->title }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="min-width: 140px;">
                <select name="status" class="form-control" style="border-radius: 10px; height: 42px;">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                    <option value="on_leave" {{ request('status') === 'on_leave' ? 'selected' : '' }}>{{ __('On Leave') }}</option>
                    <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>{{ __('Suspended') }}</option>
                    <option value="terminated" {{ request('status') === 'terminated' ? 'selected' : '' }}>{{ __('Terminated') }}</option>
                </select>
            </div>

            @if($locations->count() > 1)
                <div style="min-width: 150px;">
                    <select name="location_id" class="form-control" style="border-radius: 10px; height: 42px;">
                        <option value="">{{ __('All Branches') }}</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>
                                {{ $loc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="height: 42px; border-radius: 10px; font-weight: 700;">
                    <i class="fa-solid fa-filter"></i> {{ __('Filter') }}
                </button>
                @if(request()->anyFilled(['search', 'department_id', 'position_id', 'status', 'location_id']))
                    <a href="{{ route('admin.hr.employees.index') }}" class="btn btn-secondary" style="height: 42px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Employee Directory Table -->
    <div class="card" style="border-radius: 14px; overflow: hidden; padding: 0;">
        <div class="table-responsive">
            <table class="table" style="margin: 0; vertical-align: middle;">
                <thead style="background: rgba(255,255,255,0.03); border-bottom: 1px solid var(--border-color);">
                    <tr>
                        <th style="padding: 1rem 1.25rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Employee') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Code') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Department') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Position') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Branch') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Status') }}</th>
                        <th style="padding: 1rem 1.25rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $emp)
                        @php
                            $prof = $emp->employeeProfile;
                            $status = $prof?->employment_status ?? ($emp->is_active ? 'active' : 'suspended');
                            $statusColors = [
                                'active' => ['bg' => 'rgba(34, 197, 94, 0.15)', 'text' => '#22c55e'],
                                'on_leave' => ['bg' => 'rgba(249, 115, 22, 0.15)', 'text' => '#f97316'],
                                'suspended' => ['bg' => 'rgba(239, 68, 68, 0.15)', 'text' => '#ef4444'],
                                'terminated' => ['bg' => 'rgba(148, 163, 184, 0.15)', 'text' => '#94a3b8'],
                            ];
                            $color = $statusColors[$status] ?? ['bg' => 'rgba(148, 163, 184, 0.15)', 'text' => '#94a3b8'];
                        @endphp
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem 1.25rem;">
                                <div style="display: flex; align-items: center; gap: 0.85rem;">
                                    <div style="width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, #0ea5e9, #38bdf8); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1rem; flex-shrink: 0; box-shadow: 0 3px 8px rgba(14, 165, 233, 0.25);">
                                        {{ strtoupper(mb_substr($emp->name, 0, 1)) }}
                                    </div>
                                    <div style="min-width: 0;">
                                        <a href="{{ route('admin.hr.employees.show', $emp) }}" style="font-weight: 700; color: var(--text-main); text-decoration: none; display: block;">
                                            {{ $emp->name }}
                                        </a>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);">
                                            {{ $emp->email }}
                                            @if($emp->phone) • {{ $emp->phone }} @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <span style="font-family: monospace; font-size: 0.85rem; background: rgba(255,255,255,0.06); padding: 3px 8px; border-radius: 6px; font-weight: 600;">
                                    {{ $prof?->employee_code ?? ('EMP-' . str_pad($emp->id, 4, '0', STR_PAD_LEFT)) }}
                                </span>
                            </td>
                            <td style="padding: 1rem 1rem;">
                                @if($prof?->department)
                                    <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">
                                        {{ $prof->department->name }}
                                    </span>
                                @else
                                    <span style="color: var(--text-muted); font-size: 0.82rem;">—</span>
                                @endif
                            </td>
                            <td style="padding: 1rem 1rem;">
                                @if($prof?->position)
                                    <span style="font-size: 0.85rem; font-weight: 600; color: #0ea5e9;">
                                        {{ $prof->position->title }}
                                    </span>
                                @else
                                    <span style="color: var(--text-muted); font-size: 0.82rem;">—</span>
                                @endif
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <span style="font-size: 0.85rem; color: var(--text-muted);">
                                    {{ $emp->location?->name ?? __('All Branches') }}
                                </span>
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 999px; background: {{ $color['bg'] }}; color: {{ $color['text'] }}; text-transform: uppercase;">
                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: {{ $color['text'] }};"></span>
                                    {{ __(ucfirst(str_replace('_', ' ', $status))) }}
                                </span>
                            </td>
                            <td style="padding: 1rem 1.25rem; text-align: right;">
                                <div style="display: inline-flex; align-items: center; gap: 0.5rem;">
                                    <a href="{{ route('admin.hr.employees.show', $emp) }}" class="btn btn-secondary btn-sm" style="border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem;" title="{{ __('View Profile & Records') }}">
                                        <i class="fa-solid fa-address-card"></i> {{ __('Profile') }}
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 3rem 1.5rem; color: var(--text-muted);">
                                <div style="font-size: 2.5rem; color: var(--border-color); margin-bottom: 0.75rem;">
                                    <i class="fa-solid fa-users-slash"></i>
                                </div>
                                <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0 0 0.25rem; color: var(--text-main);">
                                    {{ __('No employees found') }}
                                </h3>
                                <p style="font-size: 0.85rem; margin: 0 0 1rem;">
                                    {{ __('Try adjusting your filters or invite new team members.') }}
                                </p>
                                <a href="{{ route('admin.team.index') }}" class="btn btn-primary btn-sm" style="border-radius: 8px;">
                                    <i class="fa-solid fa-user-plus"></i> {{ __('Invite Employee') }}
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($employees->hasPages())
            <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color);">
                {{ $employees->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

