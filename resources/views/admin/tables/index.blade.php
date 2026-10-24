@extends('layouts.app')

@section('title', __('Restaurant Tables & Capacity') . ' — ' . $vendor->name)

@section('content')
<div style="max-width: 1300px; margin: 0 auto; width: 100%; box-sizing: border-box;" x-data="adminTablesApp()">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.75rem;">
        <div>
            <h1 style="font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.65rem;">
                <span style="background: rgba(6, 182, 212, 0.12); color: #06b6d4; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-chair"></i>
                </span>
                <span>{{ __('Restaurant Tables & Capacity') }}</span>
            </h1>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">
                {{ __('Configure table numbers, seating capacity, hall sections, and availability for reservations.') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.reservations.index') }}" class="btn btn-secondary">
                <i class="fa-solid fa-calendar-check" style="color: #ec4899;"></i> {{ __('Table Reservations') }}
            </a>
            <a href="{{ route('admin.floor_plan.index') }}" class="btn btn-secondary" style="font-weight: 700; color: #3b82f6;">
                <i class="fa-solid fa-map-location-dot"></i> {{ __('Interactive Floor Plan') }}
            </a>
            <button type="button" class="btn btn-primary" @click="openCreateModal()">
                <i class="fa-solid fa-plus"></i> {{ __('Add New Table') }}
            </button>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
        <form method="GET" action="{{ route('admin.tables.index') }}" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
            @if($sections->count() > 0)
            <div style="min-width: 180px;">
                <label class="form-label">{{ __('Section / Hall') }}</label>
                <select name="section" class="form-control" style="font-size: 0.88rem; padding: 0.45rem 0.75rem;" onchange="this.form.submit()">
                    <option value="">{{ __('All Sections') }}</option>
                    @foreach($sections as $sec)
                        <option value="{{ $sec }}" {{ $section === $sec ? 'selected' : '' }}>{{ $sec }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            @if($locations->count() > 1)
            <div style="min-width: 180px;">
                <label class="form-label">{{ __('Location') }}</label>
                <select name="location_id" class="form-control" style="font-size: 0.88rem; padding: 0.45rem 0.75rem;" onchange="this.form.submit()">
                    <option value="">{{ __('All Locations') }}</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ (string)$locationId === (string)$loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            @if($section || ($locationId && $locations->count() > 1))
                <div>
                    <a href="{{ route('admin.tables.index', ['location_id' => '']) }}" class="btn btn-secondary" style="padding: 0.55rem 0.9rem; font-size: 0.88rem;">
                        <i class="fa-solid fa-rotate-left"></i> {{ __('Clear Filters') }}
                    </a>
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
                    <tr style="border-bottom: 1px solid var(--border-color); font-size: 0.9rem; transition: background 0.15s;">
                        <td style="padding: 1rem 1.25rem;">
                            <div style="display: flex; align-items: center; gap: 0.65rem;">
                                <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(6, 182, 212, 0.12); color: #06b6d4; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1rem; flex-shrink: 0;">
                                    {{ $t->table_number }}
                                </div>
                                <div>
                                    <span style="font-weight: 800; font-size: 1rem; color: var(--text-main);">#{{ $t->table_number }}</span>
                                    @if($t->isMerged())
                                        <span class="badge" style="background: rgba(147, 51, 234, 0.12); color: #9333ea; font-size: 0.72rem; padding: 0.15rem 0.45rem; border-radius: 6px; font-weight: 700; margin-left: 0.35rem;">
                                            <i class="fa-solid fa-link"></i> {{ __('Combined') }} ({{ $t->combined_capacity }} {{ __('seats') }})
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td style="padding: 1rem 1.25rem; font-weight: 600; color: var(--text-main);">
                            {{ $t->name ?: '—' }}
                        </td>
                        <td style="padding: 1rem 1.25rem;">
                            <span class="badge" style="background: rgba(100, 116, 139, 0.12); color: var(--text-main); font-size: 0.78rem; padding: 0.25rem 0.6rem; border-radius: 6px; font-weight: 600;">
                                <i class="fa-solid fa-door-open" style="color: var(--text-muted); margin-right: 0.35rem;"></i>{{ $t->section ?: __('Main Hall') }}
                            </span>
                        </td>
                        <td style="padding: 1rem 1.25rem; font-weight: 700; color: var(--text-main);">
                            <div style="display: flex; align-items: center; gap: 0.45rem;">
                                <span>
                                    @if($t->shape === 'round')
                                        <i class="fa-regular fa-circle" style="color: #3b82f6;" title="{{ __('Round') }}"></i>
                                    @elseif($t->shape === 'rectangle')
                                        <i class="fa-solid fa-vector-square" style="color: #f59e0b;" title="{{ __('Rectangle') }}"></i>
                                    @else
                                        <i class="fa-regular fa-square" style="color: #10b981;" title="{{ __('Square') }}"></i>
                                    @endif
                                </span>
                                <span>{{ $t->capacity }} {{ __('seats') }}</span>
                            </div>
                        </td>
                        <td style="padding: 1rem 1.25rem;">
                            @if($t->reservations_count > 0)
                                <span class="badge" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); font-size: 0.75rem; padding: 0.2rem 0.55rem; border-radius: 9999px; font-weight: 700;">
                                    <i class="fa-solid fa-calendar-check" style="margin-right: 0.25rem;"></i>{{ $t->reservations_count }} {{ __('Upcoming') }}
                                </span>
                            @else
                                <span style="color: var(--text-muted); font-size: 0.85rem;">0</span>
                            @endif
                        </td>
                        <td style="padding: 1rem 1.25rem;">
                            @if($t->is_active)
                                <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 0.72rem; padding: 0.2rem 0.55rem; border-radius: 9999px; font-weight: 700;">
                                    ● {{ __('Active') }}
                                </span>
                            @else
                                <span class="badge" style="background: rgba(100, 116, 139, 0.15); color: #64748b; font-size: 0.72rem; padding: 0.2rem 0.55rem; border-radius: 9999px; font-weight: 700;">
                                    {{ __('Inactive') }}
                                </span>
                            @endif
                        </td>
                        <td style="padding: 1rem 1.25rem; text-align: right;">
                            <div style="display: inline-flex; gap: 0.4rem; align-items: center; justify-content: flex-end;">
                                <button type="button" class="btn btn-secondary" style="padding: 0.4rem 0.65rem; font-size: 0.8rem;" @click="openEditModal({{ json_encode($t) }})" title="{{ __('Edit Table') }}">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <form method="POST" action="{{ route('admin.tables.destroy', $t->id) }}" style="display: inline;" onsubmit="return confirm('{{ __('Are you sure you want to delete this table?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-secondary" style="padding: 0.4rem 0.65rem; font-size: 0.8rem; color: #ef4444;" title="{{ __('Delete') }}">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" style="padding: 3.5rem 1rem; text-align: center; color: var(--text-muted);">
                            <div style="width: 60px; height: 60px; border-radius: 18px; background: rgba(100, 116, 139, 0.1); color: var(--text-muted); display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 1rem;">
                                <i class="fa-solid fa-chair"></i>
                            </div>
                            <p style="font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem;">
                                {{ __('No restaurant tables found.') }}
                            </p>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                                {{ __('Add your first dining table or sync your layout from the interactive floor plan.') }}
                            </p>
                            <button type="button" class="btn btn-primary" @click="openCreateModal()" style="font-size: 0.85rem; padding: 0.5rem 1.2rem;">
                                <i class="fa-solid fa-plus"></i> {{ __('Add New Table') }}
                            </button>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tables->hasPages())
        <div style="padding: 1.25rem; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted);">
                {{ __('Showing') }} {{ $tables->firstItem() }} {{ __('of') }} {{ $tables->total() }} {{ __('results') }}
            </div>
            <div>
                {{ $tables->links() }}
            </div>
        </div>
        @endif
    </div>

    <!-- Create / Edit Table Modal -->
    <div class="modal-backdrop" x-show="showModal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;" x-cloak>
        <div class="card" style="max-width: 480px; width: 100%; padding: 1.75rem; margin: 0; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.3);" @click.outside="showModal = false">
            <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.25rem; color: var(--text-main);" x-text="isEdit ? '{{ __('Edit Table') }}' : '{{ __('Create New Table') }}'"></h3>

            <form :action="formAction" method="POST">
                @csrf
                <template x-if="isEdit">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">{{ __('Table Number') }} *</label>
                    <input type="text" name="table_number" class="form-control" x-model="formData.table_number" placeholder="e.g. 1, 12, VIP-1" required>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">{{ __('Display Name') }}</label>
                    <input type="text" name="name" class="form-control" x-model="formData.name" placeholder="e.g. Table 1, Garden Window">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Capacity') }} ({{ __('seats') }}) *</label>
                        <input type="number" name="capacity" class="form-control" x-model="formData.capacity" min="1" max="50" required>
                    </div>
                    <div>
                        <label class="form-label">{{ __('Min Capacity') }} *</label>
                        <input type="number" name="min_capacity" class="form-control" x-model="formData.min_capacity" min="1" max="50" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Section / Hall') }}</label>
                        <input type="text" name="section" class="form-control" x-model="formData.section" placeholder="e.g. Main Hall, Terrace, VIP">
                    </div>
                    <div>
                        <label class="form-label">{{ __('Shape') }} *</label>
                        <select name="shape" class="form-control" x-model="formData.shape">
                            <option value="square">{{ __('Square') }}</option>
                            <option value="round">{{ __('Round') }}</option>
                            <option value="rectangle">{{ __('Rectangle') }}</option>
                        </select>
                    </div>
                </div>

                @if($locations->count() > 1)
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">{{ __('Location') }}</label>
                    <select name="location_id" class="form-control" x-model="formData.location_id">
                        <option value="">{{ __('All Locations') }}</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div style="margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; border-radius: 12px; background: var(--bg-body); border: 1px solid var(--border-color);">
                    <span style="font-size: 0.9rem; font-weight: 700; color: var(--text-main);">{{ __('Active & Available for Booking') }}</span>
                    <label class="switch">
                        <input type="checkbox" name="is_active" value="1" x-model="formData.is_active">
                        <span class="slider round"></span>
                    </label>
                </div>

                <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" @click="showModal = false">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">
                        <span x-text="isEdit ? '{{ __('Edit Table') }}' : '{{ __('Create New Table') }}'"></span>
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
            shape: 'square',
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
                shape: 'square',
                location_id: '{{ $locationId ?: '' }}',
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
                shape: table.shape || 'square',
                location_id: table.location_id || '',
                is_active: Boolean(table.is_active)
            };
            this.showModal = true;
        }
    };
}
</script>
@endsection
