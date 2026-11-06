@extends('layouts.app')

@section('title', $employee->name . ' - ' . __('Employee Profile') . ' - ' . $vendor->name)

@section('content')
<div x-data="{
    activeTab: 'overview',
    showTransferModal: false,
    showSalaryModal: false,
    showContractModal: false,
    showDocModal: false,
}" style="display: flex; flex-direction: column; gap: 1.5rem;">

    <!-- Breadcrumb & Top Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; color: var(--text-muted);">
            <a href="{{ route('admin.hr.employees.index') }}" style="color: var(--text-muted); text-decoration: none;">
                <i class="fa-solid fa-arrow-left"></i> {{ __('Employees') }}
            </a>
            <span>/</span>
            <span style="color: var(--text-main); font-weight: 700;">{{ $employee->name }}</span>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            @can('hr.positions.manage')
                <button type="button" @click="showTransferModal = true" class="btn btn-secondary btn-sm" style="border-radius: 10px; font-weight: 700;">
                    <i class="fa-solid fa-arrows-split-up-and-left"></i> {{ __('Transfer / Change Position') }}
                </button>
            @endcan
            @can('hr.payroll.view_sensitive')
                <button type="button" @click="showSalaryModal = true" class="btn btn-secondary btn-sm" style="border-radius: 10px; font-weight: 700;">
                    <i class="fa-solid fa-money-check-dollar"></i> {{ __('Revise Salary') }}
                </button>
            @endcan
            @can('hr.contracts.manage')
                <button type="button" @click="showContractModal = true" class="btn btn-primary btn-sm" style="border-radius: 10px; font-weight: 700;">
                    <i class="fa-solid fa-file-signature"></i> {{ __('New Contract') }}
                </button>
            @endcan
        </div>
    </div>

    <!-- Employee Summary Header Card -->
    <div class="card" style="padding: 1.5rem; border-radius: 16px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 1.25rem;">
                <div style="width: 72px; height: 72px; border-radius: 20px; background: linear-gradient(135deg, #0ea5e9, #38bdf8); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 2rem; flex-shrink: 0; box-shadow: 0 8px 20px rgba(14, 165, 233, 0.3);">
                    {{ strtoupper(mb_substr($employee->name, 0, 1)) }}
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                        <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin: 0;">
                            {{ $employee->name }}
                        </h1>
                        <span style="font-family: monospace; font-size: 0.82rem; background: rgba(255,255,255,0.08); padding: 3px 10px; border-radius: 8px; font-weight: 600;">
                            {{ $profile->employee_code ?? ('EMP-' . str_pad($employee->id, 4, '0', STR_PAD_LEFT)) }}
                        </span>
                        @php
                            $status = $profile->employment_status ?? ($employee->is_active ? 'active' : 'suspended');
                            $statusColors = [
                                'active' => ['bg' => 'rgba(34, 197, 94, 0.15)', 'text' => '#22c55e'],
                                'on_leave' => ['bg' => 'rgba(249, 115, 22, 0.15)', 'text' => '#f97316'],
                                'suspended' => ['bg' => 'rgba(239, 68, 68, 0.15)', 'text' => '#ef4444'],
                                'terminated' => ['bg' => 'rgba(148, 163, 184, 0.15)', 'text' => '#94a3b8'],
                            ];
                            $c = $statusColors[$status] ?? ['bg' => 'rgba(148, 163, 184, 0.15)', 'text' => '#94a3b8'];
                        @endphp
                        <span style="font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 999px; background: {{ $c['bg'] }}; color: {{ $c['text'] }}; text-transform: uppercase;">
                            {{ __(ucfirst(str_replace('_', ' ', $status))) }}
                        </span>
                    </div>

                    <div style="display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap; margin-top: 0.5rem; font-size: 0.88rem; color: var(--text-muted);">
                        <span><i class="fa-solid fa-sitemap" style="color: #8b5cf6;"></i> {{ $profile->department?->name ?? __('No Department') }}</span>
                        <span><i class="fa-solid fa-user-tag" style="color: #0ea5e9;"></i> {{ $profile->position?->title ?? __('No Position') }}</span>
                        <span><i class="fa-solid fa-location-dot" style="color: #14b8a6;"></i> {{ $employee->location?->name ?? __('All Branches') }}</span>
                        <span><i class="fa-solid fa-envelope" style="color: #f59e0b;"></i> {{ $employee->email }}</span>
                        @if($employee->phone)
                            <span><i class="fa-solid fa-phone" style="color: #22c55e;"></i> {{ $employee->phone }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Quick Metrics -->
            <div style="display: flex; gap: 1.5rem; border-left: 1px solid var(--border-color); padding-left: 1.5rem;">
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Hired Date') }}</div>
                    <div style="font-size: 1.1rem; font-weight: 800; color: var(--text-main);">
                        {{ $profile->hired_at ? \Carbon\Carbon::parse($profile->hired_at)->format('d.m.Y') : '—' }}
                    </div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">{{ __('Base Salary') }}</div>
                    <div style="font-size: 1.1rem; font-weight: 800; color: #22c55e;">
                        @can('hr.payroll.view_sensitive')
                            {{ number_format((float) ($profile->base_salary ?? 0), 2) }} AMD
                            <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 500;">/ {{ __($profile->salary_type ?? 'monthly') }}</span>
                        @else
                            •••••••
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div style="display: flex; gap: 0.5rem; border-bottom: 1px solid var(--border-color); overflow-x: auto; padding-bottom: 2px;">
        <button type="button" @click="activeTab = 'overview'" class="btn" :class="activeTab === 'overview' ? 'btn-primary' : 'btn-secondary'" style="border-radius: 10px; font-size: 0.85rem; font-weight: 700;">
            <i class="fa-solid fa-address-card"></i> {{ __('Overview & Details') }}
        </button>
        <button type="button" @click="activeTab = 'career'" class="btn" :class="activeTab === 'career' ? 'btn-primary' : 'btn-secondary'" style="border-radius: 10px; font-size: 0.85rem; font-weight: 700;">
            <i class="fa-solid fa-briefcase"></i> {{ __('Position History') }}
        </button>
        <button type="button" @click="activeTab = 'compensation'" class="btn" :class="activeTab === 'compensation' ? 'btn-primary' : 'btn-secondary'" style="border-radius: 10px; font-size: 0.85rem; font-weight: 700;">
            <i class="fa-solid fa-money-bill-wave"></i> {{ __('Salary History') }}
        </button>
        <button type="button" @click="activeTab = 'contracts'" class="btn" :class="activeTab === 'contracts' ? 'btn-primary' : 'btn-secondary'" style="border-radius: 10px; font-size: 0.85rem; font-weight: 700;">
            <i class="fa-solid fa-file-contract"></i> {{ __('Contracts') }} ({{ $employee->employmentContracts->count() }})
        </button>
        <button type="button" @click="activeTab = 'documents'" class="btn" :class="activeTab === 'documents' ? 'btn-primary' : 'btn-secondary'" style="border-radius: 10px; font-size: 0.85rem; font-weight: 700;">
            <i class="fa-solid fa-folder-open"></i> {{ __('Documents') }} ({{ $employee->employeeDocuments->count() }})
        </button>
        <button type="button" @click="activeTab = 'attendance'" class="btn" :class="activeTab === 'attendance' ? 'btn-primary' : 'btn-secondary'" style="border-radius: 10px; font-size: 0.85rem; font-weight: 700;">
            <i class="fa-solid fa-clock"></i> {{ __('Attendance & Shifts') }}
        </button>
        <button type="button" @click="activeTab = 'leaves'" class="btn" :class="activeTab === 'leaves' ? 'btn-primary' : 'btn-secondary'" style="border-radius: 10px; font-size: 0.85rem; font-weight: 700;">
            <i class="fa-solid fa-plane-departure"></i> {{ __('Leaves') }}
        </button>
        <button type="button" @click="activeTab = 'payslips'" class="btn" :class="activeTab === 'payslips' ? 'btn-primary' : 'btn-secondary'" style="border-radius: 10px; font-size: 0.85rem; font-weight: 700;">
            <i class="fa-solid fa-receipt"></i> {{ __('Payslips') }}
        </button>
    </div>

    <!-- TAB 1: OVERVIEW & EDIT FORM -->
    <div x-show="activeTab === 'overview'" x-transition.opacity>
        <div class="card" style="padding: 1.5rem; border-radius: 16px;">
            <h2 style="font-size: 1.15rem; font-weight: 800; margin: 0 0 1.25rem; color: var(--text-main);">
                {{ __('Employee Information') }}
            </h2>

            <form method="POST" action="{{ route('admin.hr.employees.update', $employee) }}">
                @csrf
                @method('PUT')

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                    <div>
                        <label class="form-label">{{ __('Full Name') }} <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $employee->name) }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>

                    <div>
                        <label class="form-label">{{ __('Email Address') }} <span style="color: #ef4444;">*</span></label>
                        <input type="email" name="email" value="{{ old('email', $employee->email) }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>

                    <div>
                        <label class="form-label">{{ __('Phone Number') }}</label>
                        <input type="text" name="phone" value="{{ old('phone', $employee->phone) }}" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>

                    <div>
                        <label class="form-label">{{ __('Internal Employee Code') }}</label>
                        <input type="text" name="internal_employee_code" value="{{ old('internal_employee_code', $profile->internal_employee_code ?? $profile->employee_code) }}" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>

                    <div>
                        <label class="form-label">{{ __('Department') }}</label>
                        <select name="department_id" class="form-control" style="border-radius: 10px; height: 42px;">
                            <option value="">{{ __('None') }}</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ old('department_id', $profile->department_id) == $dept->id ? 'selected' : '' }}>
                                    {{ $dept->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Position') }}</label>
                        <select name="position_id" class="form-control" style="border-radius: 10px; height: 42px;">
                            <option value="">{{ __('None') }}</option>
                            @foreach($positions as $pos)
                                <option value="{{ $pos->id }}" {{ old('position_id', $profile->position_id) == $pos->id ? 'selected' : '' }}>
                                    {{ $pos->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Primary Branch / Location') }}</label>
                        <select name="location_id" class="form-control" style="border-radius: 10px; height: 42px;">
                            <option value="">{{ __('All Branches (Global)') }}</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}" {{ old('location_id', $employee->location_id) == $loc->id ? 'selected' : '' }}>
                                    {{ $loc->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Direct Manager / Supervisor') }}</label>
                        <select name="manager_id" class="form-control" style="border-radius: 10px; height: 42px;">
                            <option value="">{{ __('None') }}</option>
                            @foreach($managers as $mgr)
                                @if($mgr->id !== $employee->id)
                                    <option value="{{ $mgr->id }}" {{ old('manager_id', $profile->manager_id) == $mgr->id ? 'selected' : '' }}>
                                        {{ $mgr->name }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Employment Status') }}</label>
                        <select name="employment_status" class="form-control" style="border-radius: 10px; height: 42px;">
                            <option value="active" {{ old('employment_status', $profile->employment_status) === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                            <option value="on_leave" {{ old('employment_status', $profile->employment_status) === 'on_leave' ? 'selected' : '' }}>{{ __('On Leave') }}</option>
                            <option value="suspended" {{ old('employment_status', $profile->employment_status) === 'suspended' ? 'selected' : '' }}>{{ __('Suspended') }}</option>
                            <option value="terminated" {{ old('employment_status', $profile->employment_status) === 'terminated' ? 'selected' : '' }}>{{ __('Terminated') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Employment Type') }}</label>
                        <select name="employment_type" class="form-control" style="border-radius: 10px; height: 42px;">
                            <option value="full_time" {{ old('employment_type', $profile->employment_type) === 'full_time' ? 'selected' : '' }}>{{ __('Full-Time') }}</option>
                            <option value="part_time" {{ old('employment_type', $profile->employment_type) === 'part_time' ? 'selected' : '' }}>{{ __('Part-Time') }}</option>
                            <option value="contract" {{ old('employment_type', $profile->employment_type) === 'contract' ? 'selected' : '' }}>{{ __('Contractor') }}</option>
                            <option value="seasonal" {{ old('employment_type', $profile->employment_type) === 'seasonal' ? 'selected' : '' }}>{{ __('Seasonal') }}</option>
                            <option value="internship" {{ old('employment_type', $profile->employment_type) === 'internship' ? 'selected' : '' }}>{{ __('Internship') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Date of Birth') }}</label>
                        <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $profile->date_of_birth ? \Carbon\Carbon::parse($profile->date_of_birth)->format('Y-m-d') : ($profile->birth_date ? \Carbon\Carbon::parse($profile->birth_date)->format('Y-m-d') : '')) }}" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>

                    <div>
                        <label class="form-label">{{ __('Date of Hire') }}</label>
                        <input type="date" name="hired_at" value="{{ old('hired_at', $profile->hired_at ? \Carbon\Carbon::parse($profile->hired_at)->format('Y-m-d') : '') }}" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>

                    <div>
                        <label class="form-label">{{ __('Emergency Contact Name') }}</label>
                        <input type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name', $profile->emergency_contact_name) }}" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>

                    <div>
                        <label class="form-label">{{ __('Emergency Contact Phone') }}</label>
                        <input type="text" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $profile->emergency_contact_phone) }}" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>

                    <div>
                        <label class="form-label">{{ __('Kiosk PIN Code (4 digits)') }}</label>
                        <input type="password" name="pin_code" maxlength="4" placeholder="••••" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>

                    <div style="grid-column: 1 / -1;">
                        <label class="form-label">{{ __('Residential Address') }}</label>
                        <textarea name="address" rows="2" class="form-control" style="border-radius: 10px;">{{ old('address', $profile->address) }}</textarea>
                    </div>
                </div>

                @can('hr.employees.manage')
                    <div style="margin-top: 1.5rem; text-align: right;">
                        <button type="submit" class="btn btn-primary" style="font-weight: 700; border-radius: 10px; padding: 0.6rem 1.5rem;">
                            <i class="fa-solid fa-floppy-disk"></i> {{ __('Save Profile Changes') }}
                        </button>
                    </div>
                @endcan
            </form>
        </div>
    </div>

    <!-- TAB 2: POSITION & CAREER HISTORY -->
    <div x-show="activeTab === 'career'" x-transition.opacity>
        <div class="card" style="padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h2 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">
                    {{ __('Position & Career History') }}
                </h2>
                @can('hr.positions.manage')
                    <button type="button" @click="showTransferModal = true" class="btn btn-primary btn-sm" style="border-radius: 8px;">
                        <i class="fa-solid fa-plus"></i> {{ __('Transfer / Change') }}
                    </button>
                @endcan
            </div>

            <div class="table-responsive">
                <table class="table" style="vertical-align: middle;">
                    <thead>
                        <tr>
                            <th>{{ __('Department') }}</th>
                            <th>{{ __('Position') }}</th>
                            <th>{{ __('Start Date') }}</th>
                            <th>{{ __('End Date') }}</th>
                            <th>{{ __('Reason for Change') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($profile->positionHistories()->latest('effective_from')->latest('id')->get() as $hist)
                            <tr>
                                <td>{{ $hist->department?->name ?? '—' }}</td>
                                <td style="font-weight: 700; color: #0ea5e9;">{{ $hist->position?->title ?? '—' }}</td>
                                <td>{{ ($hist->effective_from ?? $hist->start_date) ? \Carbon\Carbon::parse($hist->effective_from ?? $hist->start_date)->format('d.m.Y') : '—' }}</td>
                                <td>{{ ($hist->effective_until ?? $hist->end_date) ? \Carbon\Carbon::parse($hist->effective_until ?? $hist->end_date)->format('d.m.Y') : __('Current') }}</td>
                                <td>{{ $hist->reason ?? $hist->change_reason ?? __('Promotion / Transfer') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    {{ __('No historical position records found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 3: SALARY HISTORY -->
    <div x-show="activeTab === 'compensation'" x-transition.opacity>
        <div class="card" style="padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h2 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">
                    {{ __('Salary & Compensation History') }}
                </h2>
                @can('hr.payroll.view_sensitive')
                    <button type="button" @click="showSalaryModal = true" class="btn btn-primary btn-sm" style="border-radius: 8px;">
                        <i class="fa-solid fa-plus"></i> {{ __('Revise Salary') }}
                    </button>
                @endcan
            </div>

            <div class="table-responsive">
                <table class="table" style="vertical-align: middle;">
                    <thead>
                        <tr>
                            <th>{{ __('Effective Date') }}</th>
                            <th>{{ __('Pay Type') }}</th>
                            <th>{{ __('Base Salary') }}</th>
                            <th>{{ __('Hourly Rate') }}</th>
                            <th>{{ __('Reason') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($profile->salaryHistories()->latest('effective_from')->latest('id')->get() as $sal)
                            <tr>
                                <td>{{ ($sal->effective_from ?? $sal->effective_date) ? \Carbon\Carbon::parse($sal->effective_from ?? $sal->effective_date)->format('d.m.Y') : '—' }}</td>
                                <td><span class="badge" style="background: rgba(255,255,255,0.08); text-transform: capitalize;">{{ $sal->compensation_type ?? $sal->salary_type }}</span></td>
                                <td style="font-weight: 800; color: #22c55e;">{{ number_format((float) $sal->base_salary, 2) }} AMD</td>
                                <td>{{ $sal->hourly_rate ? number_format((float) $sal->hourly_rate, 2) . ' AMD' : '—' }}</td>
                                <td>{{ $sal->reason ?? $sal->change_reason ?? __('Annual Review / Adjustment') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    {{ __('No salary revision records found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 4: EMPLOYMENT CONTRACTS -->
    <div x-show="activeTab === 'contracts'" x-transition.opacity>
        <div class="card" style="padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h2 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">
                    {{ __('Employment Contracts') }}
                </h2>
                @can('hr.contracts.manage')
                    <button type="button" @click="showContractModal = true" class="btn btn-primary btn-sm" style="border-radius: 8px;">
                        <i class="fa-solid fa-plus"></i> {{ __('Add Contract') }}
                    </button>
                @endcan
            </div>

            <div class="table-responsive">
                <table class="table" style="vertical-align: middle;">
                    <thead>
                        <tr>
                            <th>{{ __('Contract #') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Start Date') }}</th>
                            <th>{{ __('End Date') }}</th>
                            <th>{{ __('Base Pay') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employee->employmentContracts as $c)
                            <tr>
                                <td style="font-family: monospace; font-weight: 700;">{{ $c->contract_number }}</td>
                                <td><span class="badge" style="background: rgba(255,255,255,0.08);">{{ __(ucfirst(str_replace('_', ' ', $c->contract_type))) }}</span></td>
                                <td>{{ \Carbon\Carbon::parse($c->start_date)->format('d.m.Y') }}</td>
                                <td>{{ $c->end_date ? \Carbon\Carbon::parse($c->end_date)->format('d.m.Y') : __('Indefinite') }}</td>
                                <td style="font-weight: 700; color: #22c55e;">{{ number_format((float) ($c->wage_amount ?? $c->base_salary), 2) }} AMD</td>
                                <td>
                                    <span class="badge" style="background: {{ $c->status === 'active' ? 'rgba(34, 197, 94, 0.15)' : 'rgba(148, 163, 184, 0.15)' }}; color: {{ $c->status === 'active' ? '#22c55e' : '#94a3b8' }}; text-transform: uppercase;">
                                        {{ $c->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    {{ __('No contracts on file for this employee.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 5: DOCUMENTS -->
    <div x-show="activeTab === 'documents'" x-transition.opacity>
        <div class="card" style="padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h2 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">
                    {{ __('Employee Documents & Attachments') }}
                </h2>
                @can('hr.documents.manage')
                    <button type="button" @click="showDocModal = true" class="btn btn-primary btn-sm" style="border-radius: 8px;">
                        <i class="fa-solid fa-cloud-arrow-up"></i> {{ __('Upload Document') }}
                    </button>
                @endcan
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1rem;">
                @forelse($employee->employeeDocuments as $doc)
                    <div class="card" style="padding: 1rem; border-radius: 12px; background: rgba(255,255,255,0.02); display: flex; flex-direction: column; justify-content: space-between; gap: 0.75rem;">
                        <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(14, 165, 233, 0.15); color: #0ea5e9; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">
                                <i class="fa-solid fa-file-pdf"></i>
                            </div>
                            <div style="min-width: 0;">
                                <div style="font-weight: 700; color: var(--text-main); font-size: 0.9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    {{ $doc->title ?? $doc->document_title }}
                                </div>
                                <div style="font-size: 0.78rem; color: var(--text-muted); text-transform: capitalize;">
                                    {{ str_replace('_', ' ', $doc->category ?? $doc->document_type) }} • {{ round(($doc->file_size_bytes ?? $doc->file_size) / 1024, 1) }} KB
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 0.5rem; font-size: 0.8rem;">
                            <a href="{{ route('admin.hr.employees.documents.download', [$employee, $doc]) }}" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.75rem;">
                                <i class="fa-solid fa-download"></i> {{ __('Download') }}
                            </a>
                            @can('hr.documents.manage')
                                <form method="POST" action="{{ route('admin.hr.employees.documents.destroy', [$employee, $doc]) }}" onsubmit="return confirm('{{ __('Delete this document?') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" style="padding: 2px 8px; font-size: 0.75rem;">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </div>
                @empty
                    <div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--text-muted);">
                        {{ __('No documents uploaded yet.') }}
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- TAB 6: ATTENDANCE & SHIFTS -->
    <div x-show="activeTab === 'attendance'" x-transition.opacity>
        <div class="card" style="padding: 1.5rem; border-radius: 16px;">
            <h2 style="font-size: 1.15rem; font-weight: 800; margin: 0 0 1.25rem; color: var(--text-main);">
                {{ __('Recent Attendance Punches & Shifts') }}
            </h2>

            <div class="table-responsive">
                <table class="table" style="vertical-align: middle;">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Clock In') }}</th>
                            <th>{{ __('Clock Out') }}</th>
                            <th>{{ __('Total Hours') }}</th>
                            <th>{{ __('Late (min)') }}</th>
                            <th>{{ __('Method') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employee->attendanceRecords as $att)
                            <tr>
                                <td style="font-weight: 700;">{{ \Carbon\Carbon::parse($att->check_in_at)->format('d.m.Y') }}</td>
                                <td style="color: #22c55e;">{{ \Carbon\Carbon::parse($att->check_in_at)->format('H:i') }}</td>
                                <td style="color: #ef4444;">{{ $att->check_out_at ? \Carbon\Carbon::parse($att->check_out_at)->format('H:i') : __('Active') }}</td>
                                <td style="font-weight: 700;">{{ $att->total_hours ? number_format((float) $att->total_hours, 2) . 'h' : '—' }}</td>
                                <td>
                                    @if($att->is_late)
                                        <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">{{ $att->late_minutes }}m</span>
                                    @else
                                        <span style="color: #22c55e;"><i class="fa-solid fa-check"></i></span>
                                    @endif
                                </td>
                                <td><span class="badge" style="background: rgba(255,255,255,0.08); text-transform: uppercase;">{{ $att->punch_method ?? $att->source }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    {{ __('No attendance punches recorded.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 7: LEAVES -->
    <div x-show="activeTab === 'leaves'" x-transition.opacity>
        <div class="card" style="padding: 1.5rem; border-radius: 16px;">
            <h2 style="font-size: 1.15rem; font-weight: 800; margin: 0 0 1.25rem; color: var(--text-main);">
                {{ __('Leave Balances & Recent Requests') }}
            </h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                @foreach($employee->leaveBalances as $bal)
                    <div class="card" style="padding: 1rem; border-radius: 12px; background: rgba(255,255,255,0.02);">
                        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">{{ $bal->leaveType?->name ?? __('Leave') }} ({{ $bal->year }})</div>
                        <div style="font-size: 1.4rem; font-weight: 800; color: #f97316; margin-top: 0.25rem;">
                            {{ $bal->remaining_days }} <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500;">/ {{ $bal->allocated_days }} {{ __('days') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="table-responsive">
                <table class="table" style="vertical-align: middle;">
                    <thead>
                        <tr>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('From') }}</th>
                            <th>{{ __('To') }}</th>
                            <th>{{ __('Days') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employee->leaveRequests as $req)
                            <tr>
                                <td>{{ $req->leaveType?->name ?? '—' }}</td>
                                <td>{{ \Carbon\Carbon::parse($req->start_date)->format('d.m.Y') }}</td>
                                <td>{{ \Carbon\Carbon::parse($req->end_date)->format('d.m.Y') }}</td>
                                <td style="font-weight: 700;">{{ $req->total_days }}</td>
                                <td>
                                    <span class="badge" style="background: rgba(255,255,255,0.08); text-transform: uppercase;">{{ $req->status }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    {{ __('No leave requests on record.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 8: PAYSLIPS -->
    <div x-show="activeTab === 'payslips'" x-transition.opacity>
        <div class="card" style="padding: 1.5rem; border-radius: 16px;">
            <h2 style="font-size: 1.15rem; font-weight: 800; margin: 0 0 1.25rem; color: var(--text-main);">
                {{ __('Issued Payslips & Payroll Items') }}
            </h2>

            <div class="table-responsive">
                <table class="table" style="vertical-align: middle;">
                    <thead>
                        <tr>
                            <th>{{ __('Period') }}</th>
                            <th>{{ __('Gross Pay') }}</th>
                            <th>{{ __('Taxes & Pension') }}</th>
                            <th>{{ __('Net Payable') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th style="text-align: right;">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employee->payrollItems as $item)
                            <tr>
                                <td style="font-weight: 700;">{{ $item->period?->name ?? $item->period?->period_name }}</td>
                                <td style="font-weight: 700;">{{ number_format((float) $item->gross_pay, 2) }} AMD</td>
                                <td style="color: #ef4444;">{{ number_format((float) $item->total_deductions, 2) }} AMD</td>
                                <td style="font-weight: 800; color: #22c55e;">{{ number_format((float) $item->net_pay, 2) }} AMD</td>
                                <td><span class="badge" style="background: rgba(255,255,255,0.08); text-transform: uppercase;">{{ $item->payment_status }}</span></td>
                                <td style="text-align: right;">
                                    <a href="{{ route('admin.hr.payroll.payslip', [$item->period, $item]) }}" target="_blank" class="btn btn-secondary btn-sm" style="border-radius: 6px;">
                                        <i class="fa-solid fa-print"></i> {{ __('Payslip') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    {{ __('No payslips calculated for this employee yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL: TRANSFER POSITION -->
    <div x-show="showTransferModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showTransferModal = false" class="card" style="width: 100%; max-width: 500px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ __('Transfer / Change Position') }}</h3>
                <button type="button" @click="showTransferModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.hr.employees.transfer', $employee) }}">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('New Department') }}</label>
                        <select name="department_id" class="form-control" required style="border-radius: 10px; height: 42px;">
                            <option value="">{{ __('Select Department') }}</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ $profile->department_id == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">{{ __('New Position') }}</label>
                        <select name="position_id" class="form-control" required style="border-radius: 10px; height: 42px;">
                            <option value="">{{ __('Select Position') }}</option>
                            @foreach($positions as $pos)
                                <option value="{{ $pos->id }}" {{ $profile->position_id == $pos->id ? 'selected' : '' }}>{{ $pos->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Effective Start Date') }}</label>
                        <input type="date" name="effective_from" value="{{ date('Y-m-d') }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Reason / Notes') }}</label>
                        <input type="text" name="reason" placeholder="{{ __('Promotion, department transfer, etc.') }}" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showTransferModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Confirm Transfer') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: REVISE SALARY -->
    <div x-show="showSalaryModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showSalaryModal = false" class="card" style="width: 100%; max-width: 500px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ __('Revise Salary / Compensation') }}</h3>
                <button type="button" @click="showSalaryModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.hr.employees.salary', $employee) }}">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Salary Pay Type') }}</label>
                        <select name="compensation_type" class="form-control" required style="border-radius: 10px; height: 42px;">
                            <option value="monthly" {{ ($profile->compensation_type ?? $profile->salary_type ?? 'monthly') === 'monthly' ? 'selected' : '' }}>{{ __('Monthly Fixed Salary') }}</option>
                            <option value="hourly" {{ ($profile->compensation_type ?? $profile->salary_type ?? '') === 'hourly' ? 'selected' : '' }}>{{ __('Hourly Rate') }}</option>
                            <option value="shift" {{ in_array(($profile->compensation_type ?? $profile->salary_type ?? ''), ['shift', 'shift_based']) ? 'selected' : '' }}>{{ __('Shift-Based Fixed Rate') }}</option>
                            <option value="daily" {{ ($profile->compensation_type ?? $profile->salary_type ?? '') === 'daily' ? 'selected' : '' }}>{{ __('Daily Fixed Rate') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">{{ __('New Base Salary (AMD)') }}</label>
                        <input type="number" step="0.01" min="0" name="base_salary" value="{{ (float) ($profile->base_salary ?? 0) }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Hourly Rate (Optional, AMD)') }}</label>
                        <input type="number" step="0.01" min="0" name="hourly_rate" value="{{ (float) ($profile->hourly_rate ?? 0) }}" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Effective Date') }}</label>
                        <input type="date" name="effective_from" value="{{ date('Y-m-d') }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Reason for Change') }}</label>
                        <input type="text" name="reason" placeholder="{{ __('Annual indexation, merit raise, etc.') }}" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showSalaryModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save Salary Revision') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: NEW CONTRACT -->
    <div x-show="showContractModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showContractModal = false" class="card" style="width: 100%; max-width: 550px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ __('Issue Employment Contract') }}</h3>
                <button type="button" @click="showContractModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.hr.employees.contracts.store', $employee) }}">
                @csrf
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Contract Number') }} <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="contract_number" value="CTR-{{ date('Y') }}-{{ str_pad(rand(10,999), 3, '0', STR_PAD_LEFT) }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Contract Type') }}</label>
                        <select name="contract_type" class="form-control" required style="border-radius: 10px; height: 42px;">
                            <option value="indefinite">{{ __('Indefinite (Permanent)') }}</option>
                            <option value="fixed_term">{{ __('Fixed Term') }}</option>
                            <option value="service">{{ __('Service / Civil') }}</option>
                            <option value="internship">{{ __('Internship') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Start Date') }} <span style="color: #ef4444;">*</span></label>
                        <input type="date" name="start_date" value="{{ date('Y-m-d') }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('End Date (Optional)') }}</label>
                        <input type="date" name="end_date" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Wage Type') }}</label>
                        <select name="wage_type" class="form-control" required style="border-radius: 10px; height: 42px;">
                            <option value="monthly">{{ __('Monthly Fixed') }}</option>
                            <option value="hourly">{{ __('Hourly Rate') }}</option>
                            <option value="daily">{{ __('Daily Rate') }}</option>
                            <option value="shift">{{ __('Shift Rate') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Wage / Base Pay (AMD)') }} <span style="color: #ef4444;">*</span></label>
                        <input type="number" step="0.01" min="0" name="wage_amount" value="{{ (float) ($profile->base_salary ?? 0) }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Weekly Work Hours') }}</label>
                        <input type="number" step="1" min="1" max="80" name="working_hours_per_week" value="40" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showContractModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save & Issue Contract') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: UPLOAD DOCUMENT -->
    <div x-show="showDocModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showDocModal = false" class="card" style="width: 100%; max-width: 500px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ __('Upload Employee Document') }}</h3>
                <button type="button" @click="showDocModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.hr.employees.documents.store', $employee) }}" enctype="multipart/form-data">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Document Title') }} <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="title" placeholder="{{ __('e.g. Passport Copy, Order #12') }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Document Type') }}</label>
                        <select name="category" class="form-control" required style="border-radius: 10px; height: 42px;">
                            <option value="contract">{{ __('Signed Employment Contract') }}</option>
                            <option value="id">{{ __('ID Card / Passport') }}</option>
                            <option value="medical">{{ __('Medical / Health Book (SanKnizhka)') }}</option>
                            <option value="decree">{{ __('Hiring / Termination Order') }}</option>
                            <option value="other">{{ __('Other Document') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Select File (PDF, DOCX, PNG, JPG - max 15MB)') }} <span style="color: #ef4444;">*</span></label>
                        <input type="file" name="document" class="form-control" required style="border-radius: 10px;">
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showDocModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Upload Document') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

