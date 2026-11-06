@extends('layouts.app')

@section('title', __('Departments') . ' - ' . $vendor->name)

@section('content')
<div x-data="{
    showCreateModal: false,
    showEditModal: false,
    editDept: { id: null, name: '', description: '', manager_id: '', sort_order: 0, is_active: true },
    openEdit(dept) {
        this.editDept = { ...dept };
        this.showEditModal = true;
    }
}" style="display: flex; flex-direction: column; gap: 1.5rem;">

    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="min-width: 0; flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-sitemap"></i>
                </span>
                <div>
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.35rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0;">
                        {{ __('Departments & Structure') }}
                    </h1>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0.25rem 0 0;">
                        {{ __('Organize your restaurant units (Kitchen, Bar, Service, Management, Cleaning)') }}
                    </p>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <a href="{{ route('admin.hr.positions.index') }}" class="btn btn-secondary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px;">
                <i class="fa-solid fa-user-tag"></i> {{ __('Manage Positions') }}
            </a>
            @can('hr.departments.manage')
                <button type="button" @click="showCreateModal = true" class="btn btn-primary" style="font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 12px; box-shadow: 0 4px 14px rgba(139, 92, 246, 0.3);">
                    <i class="fa-solid fa-plus-circle"></i> {{ __('Add Department') }}
                </button>
            @endcan
        </div>
    </div>

    <!-- Departments Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.25rem;">
        @forelse($departments as $dept)
            <div class="card" style="padding: 1.5rem; border-radius: 16px; display: flex; flex-direction: column; justify-content: space-between; gap: 1.25rem;">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.75rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(139, 92, 246, 0.12); color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                                <i class="fa-solid fa-folder-tree"></i>
                            </div>
                            <div>
                                <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0;">
                                    {{ $dept->name }}
                                </h3>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">
                                    @if($dept->manager)
                                        <i class="fa-solid fa-user-tie" style="color: #0ea5e9;"></i> {{ __('Lead:') }} <strong>{{ $dept->manager->name }}</strong>
                                    @else
                                        <span style="color: var(--text-muted);">{{ __('No Manager Assigned') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <span class="badge" style="background: {{ $dept->is_active ? 'rgba(34, 197, 94, 0.15)' : 'rgba(148, 163, 184, 0.15)' }}; color: {{ $dept->is_active ? '#22c55e' : '#94a3b8' }}; text-transform: uppercase; font-size: 0.72rem;">
                            {{ $dept->is_active ? __('Active') : __('Inactive') }}
                        </span>
                    </div>

                    @if($dept->description)
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin: 1rem 0 0; line-height: 1.4;">
                            {{ $dept->description }}
                        </p>
                    @endif

                    <!-- Stats Row -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-top: 1.25rem; padding: 0.75rem; background: rgba(255,255,255,0.02); border-radius: 10px; border: 1px solid var(--border-color);">
                        <div>
                            <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">{{ __('Headcount') }}</div>
                            <div style="font-size: 1.15rem; font-weight: 800; color: #0ea5e9;">{{ $dept->employee_profiles_count }} {{ __('Staff') }}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">{{ __('Positions') }}</div>
                            <div style="font-size: 1.15rem; font-weight: 800; color: #8b5cf6;">{{ $dept->positions->count() }} {{ __('Roles') }}</div>
                        </div>
                    </div>
                </div>

                <!-- Footer / Actions -->
                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 0.75rem; font-size: 0.85rem;">
                    <a href="{{ route('admin.hr.employees.index', ['department_id' => $dept->id]) }}" style="color: var(--text-muted); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem;">
                        <i class="fa-solid fa-users"></i> {{ __('View Employees') }}
                    </a>

                    @can('hr.departments.manage')
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="button" @click="openEdit({{ json_encode($dept) }})" class="btn btn-secondary btn-sm" style="border-radius: 8px;">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <form method="POST" action="{{ route('admin.hr.departments.destroy', $dept) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this department?') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" style="border-radius: 8px;">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    @endcan
                </div>
            </div>
        @empty
            <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 3rem 1.5rem; border-radius: 16px;">
                <div style="font-size: 2.5rem; color: var(--border-color); margin-bottom: 0.75rem;">
                    <i class="fa-solid fa-sitemap"></i>
                </div>
                <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0 0 0.25rem; color: var(--text-main);">
                    {{ __('No departments configured') }}
                </h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0 0 1rem;">
                    {{ __('Define your organizational units to group positions and employees.') }}
                </p>
                <button type="button" @click="showCreateModal = true" class="btn btn-primary btn-sm" style="border-radius: 8px;">
                    <i class="fa-solid fa-plus"></i> {{ __('Create First Department') }}
                </button>
            </div>
        @endforelse
    </div>

    <!-- CREATE MODAL -->
    <div x-show="showCreateModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showCreateModal = false" class="card" style="width: 100%; max-width: 500px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ __('Add Department') }}</h3>
                <button type="button" @click="showCreateModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.hr.departments.store') }}">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Department Name') }} <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="name" placeholder="{{ __('e.g. Kitchen, Service, Bar, Cleaning') }}" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Department Manager / Lead') }}</label>
                        <select name="manager_id" class="form-control" style="border-radius: 10px; height: 42px;">
                            <option value="">{{ __('None') }}</option>
                            @foreach($managers as $mgr)
                                <option value="{{ $mgr->id }}">{{ $mgr->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Description') }}</label>
                        <textarea name="description" rows="2" class="form-control" style="border-radius: 10px;"></textarea>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Sort Order') }}</label>
                        <input type="number" name="sort_order" value="0" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showCreateModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save Department') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div x-show="showEditModal" x-cloak class="modal-backdrop" style="position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div @click.away="showEditModal = false" class="card" style="width: 100%; max-width: 500px; padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">{{ __('Edit Department') }}</h3>
                <button type="button" @click="showEditModal = false" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">&times;</button>
            </div>
            <form method="POST" :action="'{{ url('admin/hr/departments') }}/' + editDept.id">
                @csrf
                @method('PUT')
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Department Name') }} <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="name" x-model="editDept.name" class="form-control" required style="border-radius: 10px; height: 42px;">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Department Manager / Lead') }}</label>
                        <select name="manager_id" x-model="editDept.manager_id" class="form-control" style="border-radius: 10px; height: 42px;">
                            <option value="">{{ __('None') }}</option>
                            @foreach($managers as $mgr)
                                <option value="{{ $mgr->id }}">{{ $mgr->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Description') }}</label>
                        <textarea name="description" x-model="editDept.description" rows="2" class="form-control" style="border-radius: 10px;"></textarea>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Sort Order') }}</label>
                        <input type="number" name="sort_order" x-model="editDept.sort_order" class="form-control" style="border-radius: 10px; height: 42px;">
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="is_active" value="1" id="dept_active" x-model="editDept.is_active">
                        <label for="dept_active" style="margin: 0; font-size: 0.9rem; color: var(--text-main);">{{ __('Active Status') }}</label>
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" @click="showEditModal = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Update Department') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

