@extends('layouts.app')

@section('title', __('Restaurant Tables') . ' — ' . $vendor->name)

@section('content')
<div style="max-width: 1300px; margin: 0 auto;" x-data="adminTablesApp()">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;">
                <i class="fa-solid fa-chair" style="color: #06b6d4; margin-right: 0.5rem;"></i>
                {{ __('Restaurant Tables & Capacity') }}
            </h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">
                {{ __('Configure table numbers, seating capacity, hall sections, and availability for reservations.') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.reservations.index') }}" class="btn btn-secondary">
                <i class="fa-solid fa-calendar-check" style="color: #ec4899;"></i> {{ __('Reservations') }}
            </a>
            <form method="POST" action="{{ route('admin.tables.sync_floor_plan') }}" style="display: inline;">
                @csrf
                <button type="submit" class="btn btn-secondary" title="{{ __('Sync from interactive floor plan layout') }}">
                    <i class="fa-solid fa-rotate" style="color: #3b82f6;"></i> {{ __('Sync Floor Plan') }}
                </button>
            </form>
            <button type="button" class="btn btn-primary" @click="openCreateModal()">
                <i class="fa-solid fa-plus"></i> {{ __('Add New Table') }}
            </button>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
        <form method="GET" action="{{ route('admin.tables.index') }}" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
            @if($sections->count() > 0)
            <div>
                <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">{{ __('Section / Hall') }}</label>
                <select name="section" class="form-control" style="font-size: 0.88rem; padding: 0.45rem 0.75rem;" onchange="this.form.submit()">
                    <option value="">{{ __('All Sections') }}</option>
                    @foreach($sections as $sec)
                        <option value="{{ $sec }}" {{ $section === $sec ? 'selected' : '' }}>{{ $sec }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            @if($locations->count() > 1)
            <div>
                <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">{{ __('Location') }}</label>
                <select name="location_id" class="form-control" style="font-size: 0.88rem; padding: 0.45rem 0.75rem;" onchange="this.form.submit()">
                    <option value="">{{ __('All Locations') }}</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ (string)$locationId === (string)$loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            @if($section || $locationId)
                <div style="align-self: flex-end;">
                    <a href="{{ route('admin.tables.index') }}" class="btn btn-secondary" style="padding: 0.45rem 0.8rem; font-size: 0.88rem;">{{ __('Clear Filters') }}</a>
                </div>
            @endif
        </form>
    </div>

    <!-- Tables Grid / List -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse; margin-bottom: 0;">
                <thead>
                    <tr style="background: rgba(0,0,0,0.02); border-bottom: 1px solid var(--border-color); text-align: left; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
                        <th style="padding: 1rem 1.25rem;">{{ __('Table Number') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Display Name') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Section') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Capacity') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Active Bookings') }}</th>
                        <th style="padding: 1rem 1.25rem;">{{ __('Status') }}</th>
                        <th style="padding: 1rem 1.25rem; text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tables as $t)
                    <tr style="border-bottom: 1px solid var(--border-color); font-size: 0.9rem;">
                        <td style="padding: 1rem 1.25rem;">
                            <div style="display: flex; align-items: center; gap: 0.6rem;">
                                <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(6, 182, 212, 0.1); color: #06b6d4; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1rem;">
                                    {{ $t->table_number }}
                                </div>
                                <span style="font-weight: 800; font-size: 1rem; color: var(--text-main);">#{{ $t->table_number }}</span>
                            </div>
                        </td>
                        <td style="padding: 1rem 1.25rem; font-weight: 600;">
                            {{ $t->name ?: '—' }}
                        </td>
                        <td style="padding: 1rem 1.25rem;">
                            <span class="badge badge-slate" style="font-size: 0.75rem;">
                                <i class="fa-solid fa-map-pin" style="margin-right: 0.25rem;"></i> {{ $t->section ?: 'Main Hall' }}
                            </span>
                        </td>
                        <td style="padding: 1rem 1.25rem;">
                            <span style="font-weight: 700; color: var(--text-main);">{{ $t->capacity }} {{ __('seats') }}</span>
                            @if($t->min_capacity > 1)
                                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ __('Min:') }} {{ $t->min_capacity }}</div>
                            @endif
                        </td>
                        <td style="padding: 1rem 1.25rem;">
                            @if($t->reservations_count > 0)
                                <span class="badge badge-emerald" style="font-size: 0.75rem;">
                                    {{ $t->reservations_count }} {{ __('upcoming') }}
                                </span>
                            @else
                                <span style="color: var(--text-muted); font-size: 0.8rem;">0</span>
                            @endif
                        </td>
                        <td style="padding: 1rem 1.25rem;">
                            <form method="POST" action="{{ route('admin.tables.toggle', $t->id) }}" style="display: inline;">
                                @csrf
                                <button type="submit" class="badge {{ $t->is_active ? 'badge-emerald' : 'badge-rose' }}" style="cursor: pointer; border: none; font-size: 0.75rem;" title="{{ __('Click to toggle availability') }}">
                                    {{ $t->is_active ? '● ' . __('Active') : '○ ' . __('Inactive') }}
                                </button>
                            </form>
                        </td>
                        <td style="padding: 1rem 1.25rem; text-align: right;">
                            <div style="display: inline-flex; gap: 0.4rem;">
                                <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;" @click="openEditModal({{ json_encode($t) }})" title="{{ __('Edit Table') }}">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <form method="POST" action="{{ route('admin.tables.destroy', $t->id) }}" style="display: inline;" onsubmit="return confirm('{{ __('Are you sure you want to delete this table?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8rem; color: #ef4444;" title="{{ __('Delete Table') }}">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" style="padding: 3rem 1rem; text-align: center; color: var(--text-muted);">
                            <i class="fa-solid fa-chair" style="font-size: 2.5rem; margin-bottom: 0.75rem; opacity: 0.4;"></i>
                            <p style="font-size: 1rem; font-weight: 600;">{{ __('No tables configured yet.') }}</p>
                            <p style="font-size: 0.85rem; margin-top: 0.25rem;">{{ __('Click "Add New Table" or "Sync Floor Plan" to set up your floor capacity.') }}</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tables->hasPages())
        <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color);">
            {{ $tables->links() }}
        </div>
        @endif
    </div>

    <!-- Create / Edit Table Modal -->
    <div class="modal-backdrop" x-show="showModal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;" x-cloak>
        <div class="card" style="max-width: 480px; width: 100%; padding: 1.75rem; margin: 0;" @click.outside="showModal = false">
            <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.25rem;" x-text="isEdit ? '{{ __('Edit Restaurant Table') }}' : '{{ __('Create New Table') }}'"></h3>

            <form :action="formAction" method="POST">
                @csrf
                <template x-if="isEdit">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="display: block; font-size: 0.85rem; font-weight: 700; margin-bottom: 0.35rem;">{{ __('Table Number / Identifier') }} *</label>
                    <input type="text" name="table_number" class="form-control" x-model="formData.table_number" placeholder="e.g. 1, 12, VIP-1" required>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="display: block; font-size: 0.85rem; font-weight: 700; margin-bottom: 0.35rem;">{{ __('Display Name (Optional)') }}</label>
                    <input type="text" name="name" class="form-control" x-model="formData.name" placeholder="e.g. Garden Window Booth">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.85rem; font-weight: 700; margin-bottom: 0.35rem;">{{ __('Capacity (Seats)') }} *</label>
                        <input type="number" name="capacity" class="form-control" x-model="formData.capacity" min="1" max="50" required>
                    </div>
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.85rem; font-weight: 700; margin-bottom: 0.35rem;">{{ __('Min Capacity') }} *</label>
                        <input type="number" name="min_capacity" class="form-control" x-model="formData.min_capacity" min="1" max="50" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="display: block; font-size: 0.85rem; font-weight: 700; margin-bottom: 0.35rem;">{{ __('Section / Hall') }}</label>
                    <input type="text" name="section" class="form-control" x-model="formData.section" placeholder="e.g. Main Hall, Terrace, VIP">
                </div>

                @if($locations->count() > 1)
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="display: block; font-size: 0.85rem; font-weight: 700; margin-bottom: 0.35rem;">{{ __('Location Branch') }}</label>
                    <select name="location_id" class="form-control" x-model="formData.location_id">
                        <option value="">{{ __('Default / Primary Location') }}</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="is_active" value="1" x-model="formData.is_active" style="width: 18px; height: 18px;">
                        <span style="font-size: 0.9rem; font-weight: 600;">{{ __('Active & Available for Booking') }}</span>
                    </label>
                </div>

                <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" @click="showModal = false">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">
                        <span x-text="isEdit ? '{{ __('Update Table') }}' : '{{ __('Save Table') }}'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function adminTablesApp() {
    return {
        showModal: false,
        isEdit: false,
        formAction: '{{ route('admin.tables.store') }}',
        formData: {
            table_number: '',
            name: '',
            capacity: 4,
            min_capacity: 1,
            section: 'Main Hall',
            location_id: '',
            is_active: true
        },

        openCreateModal() {
            this.isEdit = false;
            this.formAction = '{{ route('admin.tables.store') }}';
            this.formData = {
                table_number: '',
                name: '',
                capacity: 4,
                min_capacity: 1,
                section: 'Main Hall',
                location_id: '',
                is_active: true
            };
            this.showModal = true;
        },

        openEditModal(table) {
            this.isEdit = true;
            this.formAction = `{{ url('/admin/tables') }}/${table.id}`;
            this.formData = {
                table_number: table.table_number,
                name: table.name || '',
                capacity: table.capacity,
                min_capacity: table.min_capacity || 1,
                section: table.section || 'Main Hall',
                location_id: table.location_id || '',
                is_active: Boolean(table.is_active)
            };
            this.showModal = true;
        }
    };
}
</script>
@endsection
