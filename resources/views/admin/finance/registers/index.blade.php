@extends('layouts.app')

@section('title', __('Cash Registers') . ' - ' . config('app.name'))

@section('content')
<div x-data="{ addModalOpen: false }" style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Header -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fa-solid fa-cash-register" style="color: #f59e0b;"></i>
                    {{ __('Cash Registers & Hardware Drawers') }}
                </h1>
                <p style="margin: 0.25rem 0 0; color: var(--text-muted); font-size: 0.85rem;">
                    {{ __('Manage physical POS terminals, default cash floats, user assignments, and drawer kick controls.') }}
                </p>
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <button type="button" @click="addModalOpen = true" class="btn btn-primary" style="gap: 0.5rem;">
                    <i class="fa-solid fa-plus"></i>
                    <span>{{ __('Add Cash Register') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Registers Table -->
    <div class="card" style="padding: 1.5rem;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Code / Name') }}</th>
                        <th>{{ __('Branch') }}</th>
                        <th>{{ __('Assigned Staff') }}</th>
                        <th>{{ __('Active Shift') }}</th>
                        <th style="text-align: right;">{{ __('Default Float') }}</th>
                        <th style="text-align: right;">{{ __('Live Till Balance') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th style="text-align: right;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($registers as $reg)
                        <tr>
                            <td>
                                <div style="font-weight: 800; color: var(--text-main);">{{ $reg->name }}</div>
                                <div style="font-size: 0.75rem; font-family: monospace; color: var(--text-muted);">{{ $reg->code }}</div>
                            </td>
                            <td>{{ $reg->location?->name ?? '—' }}</td>
                            <td>{{ $reg->assignedUser?->name ?? __('Any Cashier') }}</td>
                            <td>
                                @if($reg->activeShift)
                                    <a href="{{ route('admin.finance.shifts.show', $reg->activeShift) }}" class="badge badge-emerald" style="text-decoration: none;">
                                        <i class="fa-solid fa-lock-open" style="margin-right: 0.25rem;"></i>
                                        {{ __('Shift #') }}{{ $reg->activeShift->id }} ({{ $reg->activeShift->user?->name }})
                                    </a>
                                @else
                                    <span class="badge badge-secondary">{{ __('No Active Shift') }}</span>
                                @endif
                            </td>
                            <td style="text-align: right; font-weight: 600;">
                                {{ number_format($reg->opening_float, 2) }} {{ $reg->currency }}
                            </td>
                            <td style="text-align: right; font-weight: 800; font-size: 1.05rem; color: #10b981;">
                                {{ number_format($reg->current_balance, 2) }} {{ $reg->currency }}
                            </td>
                            <td>
                                @if($reg->is_active)
                                    <span class="badge badge-emerald">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-rose">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem;">
                                    <form method="POST" action="{{ route('admin.finance.registers.kick-drawer', $reg) }}" onsubmit="return confirm('Trigger drawer kick instruction?')">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-secondary" title="{{ __('Trigger Drawer Kick') }}">
                                            <i class="fa-solid fa-bolt"></i> {{ __('Kick') }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.finance.registers.toggle', $reg) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-xs {{ $reg->is_active ? 'btn-rose' : 'btn-emerald' }}">
                                            {{ $reg->is_active ? __('Deactivate') : __('Activate') }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                                {{ __('No cash registers defined. Click Add Cash Register to create one.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1rem;">
            {{ $registers->links() }}
        </div>
    </div>

    <!-- Add Cash Register Modal -->
    <div x-show="addModalOpen" x-cloak style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);">
        <div @click.away="addModalOpen = false" class="card" style="width: 100%; max-width: 520px; padding: 2rem; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                    {{ __('Create New Cash Register') }}
                </h3>
                <button type="button" @click="addModalOpen = false" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.finance.registers.store') }}" style="display: flex; flex-direction: column; gap: 1rem;">
                @csrf

                <div>
                    <label class="form-label">{{ __('Register Name') }} *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Main Bar POS 1" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Register Code') }} *</label>
                        <input type="text" name="code" class="form-control" placeholder="e.g. REG-BAR-01" required>
                    </div>

                    <div>
                        <label class="form-label">{{ __('Branch / Location') }} *</label>
                        <select name="location_id" class="form-control" required>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">{{ __('Default Opening Float') }}</label>
                        <input type="number" step="0.01" min="0" name="opening_float" class="form-control" placeholder="0.00">
                    </div>

                    <div>
                        <label class="form-label">{{ __('Assigned Staff (Optional)') }}</label>
                        <select name="assigned_user_id" class="form-control">
                            <option value="">{{ __('Any Cashier') }}</option>
                            @foreach($staffUsers as $st)
                                <option value="{{ $st->id }}">{{ $st->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="form-label">{{ __('Notes / Hardware Serial') }}</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Sunmi T2 terminal with RJ11 drawer"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
                    <button type="button" @click="addModalOpen = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save Register') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

