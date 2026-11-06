@extends('layouts.app')

@section('title', __('Positions & Job Titles') . ' - ' . $vendor->name)

@section('content')
<div x-data="{
    showCreateModal: false,
    showEditModal: false,
    editPos: { id: null, title: '', department_id: '', description: '', default_pay_type: 'monthly', default_pay_rate: 0, is_active: true },
    openEdit(pos) {
        this.editPos = { ...pos };
        this.showEditModal = true;
    }
}" style="display: flex; flex-direction: column; gap: 1.5rem;">

    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="min-width: 0; flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(14, 165, 233, 0.15); color: #0ea5e9; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-user-tag"></i>
                </span>
                <div>
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.35rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                        {{ __('Positions & Job Titles') }}
                    </h1>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0.25rem 0 0;">
                        {{ __('Define organizational roles, standard pay rates, and departmental assignments') }}
                    </p>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <a href="{{ route('admin.hr.departments.index') }}" class="btn btn-secondary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px;">
                <i class="fa-solid fa-sitemap"></i> {{ __('Departments') }}
            </a>
            @can('hr.positions.manage')
                <button type="button" @click="showCreateModal = true" class="btn btn-primary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px; box-shadow: 0 4px 14px rgba(14, 165, 233, 0.3);">
                    <i class="fa-solid fa-plus-circle"></i> {{ __('Add Position') }}
                </button>
            @endcan
        </div>
    </div>

    <!-- Positions Table -->
    <div class="card" style="border-radius: 14px; overflow: hidden; padding: 0;">
        <div class="table-responsive">
            <table class="table" style="margin: 0; vertical-align: middle;">
                <thead style="background: rgba(255,255,255,0.03); border-bottom: 1px solid var(--border-color);">
                    <tr>
                        <th style="padding: 1rem 1.25rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Position Title') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Department') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Pay Type') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Default Pay Rate') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Staff Assigned') }}</th>
                        <th style="padding: 1rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">{{ __('Status') }}</th>
                        <th style="padding: 1rem 1.25rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($positions as $pos)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem 1.25rem;">
                                <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                    {{ $pos->title }}
                                </div>
                                @if($pos->description)
                                    <div style="font-size: 0.78rem; color: var(--text-muted);">
                                        {{ Str::limit($pos->description, 60) }}
                                    </div>
                                @endif
                            </td>
                            <td style="padding: 1rem 1rem;">
                                @if($pos->department)
                                    <span style="font-weight: 600; color: #8b5cf6;">{{ $pos->department->name }}</span>
                                @else
                                    <span style="color: var(--text-muted); font-size: 0.85rem;">—</span>
                                @endif
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <span class="badge" style="background: rgba(255,255,255,0.08); text-transform: capitalize;">
                                    {{ $pos->default_pay_type }}
                                </span>
                            </td>
                            <td style="padding: 1rem 1rem; font-weight: 800; color: #22c55e;">
                                {{ number_format((float) $pos->default_pay_rate, 2) }} AMD
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <a href="{{ route('admin.hr.employees.index', ['position_id' => $pos->id]) }}" style="font-weight: 700; color: #0ea5e9; text-decoration: none;">
                                    {{ $pos->employee_profiles_count }} {{ __('employees') }}
                                </a>
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <span class="badge" style="background: {{ $pos->is_active ? 'rgba(34, 197, 94, 0.15)' : 'rgba(148, 163, 184, 0.15)' }}; color: {{ $pos->is_active ? '#22c55e' : '#94a3b8' }}; text-transform: uppercase;">
                                    {{ $pos->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td style="padding: 1rem 1.25rem; text-align: right;">
                                @can('hr.positions.manage')
                                    <div style="display: inline-flex; align-items: center; gap: 0.5rem;">
                                        <button type="button" @click="openEdit({{ json_encode($pos) }})" class="btn btn-secondary btn-sm" style="border-radius: 8px;">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <form method="POST" action="{{ route('admin.hr.positions.destroy', $pos) }}" onsubmit="return confirm('{{ __('Delete this position?') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" style="border-radius: 8px;">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 3rem 1.5rem; color: var(--text-muted);">
                                <div style="font-size: 2.5rem; color: var(--border-color); margin-bottom: 0.75rem;">
                                    <i class="fa-solid fa-user-tag"></i>
                                </div>
                                <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0 0 0.25rem; color: var(--text-main);">
                                    {{ __('No positions configured') }}
                                </h3>
                                <p style="font-size: 0.85rem; margin: 0 0 1rem;">
                                    {{ __('Define position titles to assign standard roles and pay rates.') }}
                                </p>
                                <button type="button" @click="showCreateModal = true" class="btn btn-primary btn-sm" style="border-radius: 8px;">
                                    <i class="fa-solid fa-plus"></i> {{ __('Create First Position') }}
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- CREATE MODAL -->
    <div x-show="showCreateModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showCreateModal = false" class="card" style="width: 100%; max-width: 500px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ __('Add Position') }}</h3>
                <button type="button" @click="showCreateModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.hr.positions.store') }}">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Position Title') }} <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="title" placeholder="{{ __('e.g. Head Chef, Waiter, Bartender') }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Department') }}</label>
                        <select name="department_id" class="form-control" style="border-radius: 10px; height: 42px;">
                            <option value="">{{ __('None (Universal)') }}</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Default Pay Type') }}</label>
                            <select name="default_pay_type" class="form-control" required style="border-radius: 10px; height: 42px;">
                                <option value="monthly">{{ __('Monthly Fixed') }}</option>
                                <option value="hourly">{{ __('Hourly Rate') }}</option>
                                <option value="shift">{{ __('Shift Rate') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">{{ __('Default Rate (AMD)') }}</label>
                            <input type="number" step="0.01" min="0" name="default_pay_rate" value="0" class="form-control" required style="border-radius: 10px; height: 42px;">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Description') }}</label>
                        <textarea name="description" rows="2" class="form-control" style="border-radius: 10px;"></textarea>
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showCreateModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save Position') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div x-show="showEditModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showEditModal = false" class="card" style="width: 100%; max-width: 500px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ __('Edit Position') }}</h3>
                <button type="button" @click="showEditModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <form method="POST" :action="'{{ url('admin/hr/positions') }}/' + editPos.id">
                @csrf
                @method('PUT')
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Position Title') }} <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="title" x-model="editPos.title" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Department') }}</label>
                        <select name="department_id" x-model="editPos.department_id" class="form-control" style="border-radius: 10px; height: 42px;">
                            <option value="">{{ __('None (Universal)') }}</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">{{ __('Default Pay Type') }}</label>
                            <select name="default_pay_type" x-model="editPos.default_pay_type" class="form-control" required style="border-radius: 10px; height: 42px;">
                                <option value="monthly">{{ __('Monthly Fixed') }}</option>
                                <option value="hourly">{{ __('Hourly Rate') }}</option>
                                <option value="shift">{{ __('Shift Rate') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">{{ __('Default Rate (AMD)') }}</label>
                            <input type="number" step="0.01" min="0" name="default_pay_rate" x-model="editPos.default_pay_rate" class="form-control" required style="border-radius: 10px; height: 42px;">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Description') }}</label>
                        <textarea name="description" x-model="editPos.description" rows="2" class="form-control" style="border-radius: 10px;"></textarea>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="is_active" value="1" id="pos_active" x-model="editPos.is_active">
                        <label for="pos_active" style="margin: 0; font-size: 0.9rem; color: var(--text-main);">{{ __('Active Status') }}</label>
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showEditModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Update Position') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

